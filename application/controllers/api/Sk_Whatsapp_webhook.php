<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/api/Sk_Base_Api.php';

/**
 * Meta WhatsApp Cloud webhook.
 * With Meta Business Agent enabled, this app is standby: store history only;
 * Meta Agent is the automatic responder. Handovers update thread ownership.
 */
class Sk_Whatsapp_webhook extends Sk_Base_Api {

    public function __construct() {
        parent::__construct();
        $this->load->helper(['sk_whatsapp_cloud', 'sk_whatsapp_mcp', 'sk_meta_business_agent']);
        $this->load->model(['Sk_Whatsapp_cloud_model', 'Sk_Admin_model', 'Sk_Vendor_meta_agent_model']);
        sk_wa_cloud_ensure_schema();
        $this->Sk_Vendor_meta_agent_model->ensure_schema();
    }

    /** GET verify + POST incoming (Meta Cloud). */
    public function index() {
        $method = strtoupper((string)$this->input->method(true));
        if ($method === 'GET') {
            return $this->_verify();
        }
        return $this->_ingest();
    }

    private function _verify() {
        $cfg = sk_wa_cloud_config($this->Sk_Admin_model->get_settings());
        $mode = (string)$this->input->get('hub_mode', FALSE);
        if ($mode === '') {
            $mode = (string)$this->input->get('hub.mode', FALSE);
        }
        $token = (string)$this->input->get('hub_verify_token', FALSE);
        if ($token === '') {
            $token = (string)$this->input->get('hub.verify_token', FALSE);
        }
        $challenge = (string)$this->input->get('hub_challenge', FALSE);
        if ($challenge === '') {
            $challenge = (string)$this->input->get('hub.challenge', FALSE);
        }
        if ($mode === 'subscribe' && $token !== '' && hash_equals($cfg['verify_token'], $token)) {
            header('Content-Type: text/plain; charset=UTF-8');
            echo $challenge;
            exit;
        }
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    private function _ingest() {
        $raw = (string)$this->input->raw_input_stream;
        $settings = $this->Sk_Admin_model->get_settings();
        $cfg = sk_wa_cloud_config($settings);
        if ($cfg['app_secret'] !== '') {
            $sig = (string)$this->input->get_request_header('X-Hub-Signature-256', true);
            $expect = 'sha256=' . hash_hmac('sha256', $raw, $cfg['app_secret']);
            if ($sig === '' || !hash_equals($expect, $sig)) {
                log_message('error', 'WhatsApp Cloud webhook signature mismatch');
                http_response_code(403);
                echo 'Invalid signature';
                exit;
            }
        }

        $payload = json_decode($raw, true);
        if (is_array($payload)) {
            foreach ((array)($payload['entry'] ?? []) as $entry) {
                foreach ((array)($entry['changes'] ?? []) as $change) {
                    $field = (string)($change['field'] ?? '');
                    $value = $change['value'] ?? [];
                    if (!is_array($value)) {
                        continue;
                    }
                    $phoneNumberId = trim((string)($value['metadata']['phone_number_id'] ?? ''));
                    $vendorMatch = $phoneNumberId !== '' ? sk_wa_cloud_resolve_vendor_from_phone($phoneNumberId, $settings) : null;
                    $vendorId = $vendorMatch ? (int)$vendorMatch['vendor_id'] : 0;

                    if ($field === 'messaging_handovers') {
                        $this->_store_handovers($value, $vendorId, $phoneNumberId);
                        continue;
                    }

                    // messages + standby: same message/status shape; standby means Meta Agent owns the thread.
                    $this->_store_statuses((array)($value['statuses'] ?? []));
                    $this->_store_messages(
                        (array)($value['messages'] ?? []),
                        (array)($value['contacts'] ?? []),
                        $vendorId,
                        $phoneNumberId,
                        $settings,
                        $field === 'standby' ? 'meta_agent' : null
                    );
                }
            }
        }

        // No local AI / MCP auto-reply — Meta Business Agent is the primary responder.
        http_response_code(200);
        header('Content-Type: application/json');
        echo '{"success":true}';
        exit;
    }

    private function _store_handovers(array $value, int $vendorId, string $phoneNumberId): void {
        $events = [];
        if (!empty($value['message_echoes']) && is_array($value['message_echoes'])) {
            // ignore echoes here
        }
        foreach (['history', 'messages'] as $k) {
            if (!empty($value[$k]) && is_array($value[$k])) {
                $events = array_merge($events, $value[$k]);
            }
        }
        // Common Cloud handover payload: value.contacts + metadata + new_owner / previous_owner
        $to = '';
        if (!empty($value['contacts'][0]['wa_id'])) {
            $to = sk_wa_cloud_normalize_phone((string)$value['contacts'][0]['wa_id']);
        } elseif (!empty($value['recipient_id'])) {
            $to = sk_wa_cloud_normalize_phone((string)$value['recipient_id']);
        } elseif (!empty($value['from'])) {
            $to = sk_wa_cloud_normalize_phone((string)$value['from']);
        }

        $newOwnerRaw = strtolower((string)(
            $value['new_owner']['app_id']
            ?? $value['new_thread_owner']
            ?? $value['thread_owner']
            ?? $value['new_owner']
            ?? ''
        ));
        $owner = 'meta_agent';
        if ($newOwnerRaw !== '') {
            if (strpos($newOwnerRaw, 'ai') !== false || strpos($newOwnerRaw, 'agent') !== false) {
                $owner = 'meta_agent';
            } else {
                $owner = 'app';
            }
        }
        if (!empty($value['handover']) || !empty($value['passed_control']) || !empty($value['requested'])) {
            // If customer requested human / control passed to app
            if (!empty($value['requested']) || (isset($value['passed_control']['new_owner']['app']) )) {
                $owner = 'human';
            }
        }

        if ($to !== '') {
            $conv = $this->Sk_Whatsapp_cloud_model->find_or_create_conversation(
                $to,
                (string)($value['contacts'][0]['profile']['name'] ?? ''),
                $vendorId > 0 ? $vendorId : null,
                $phoneNumberId !== '' ? $phoneNumberId : null
            );
            $this->Sk_Vendor_meta_agent_model->set_conversation_owner(
                (int)$conv['id'],
                $owner,
                'messaging_handovers'
            );
        }

        // Persist raw handover for debugging
        log_message('info', 'WhatsApp messaging_handovers: ' . json_encode([
            'phone_number_id' => $phoneNumberId,
            'vendor_id' => $vendorId,
            'to' => $to,
            'owner' => $owner,
        ]));
    }

    private function _store_statuses(array $statuses): void {
        foreach ($statuses as $st) {
            if (!is_array($st)) {
                continue;
            }
            $wamid = (string)($st['id'] ?? '');
            $status = (string)($st['status'] ?? '');
            $err = '';
            if (!empty($st['errors'][0]['title'])) {
                $err = (string)$st['errors'][0]['title'];
            }
            $this->Sk_Whatsapp_cloud_model->update_message_status($wamid, $status, $err);
        }
    }

    private function _store_messages(
        array $messages,
        array $contacts,
        int $vendorId,
        string $phoneNumberId,
        array $settings,
        ?string $forceOwner = null
    ): void {
        $names = [];
        foreach ($contacts as $c) {
            $wa = (string)($c['wa_id'] ?? '');
            if ($wa !== '') {
                $names[$wa] = (string)($c['profile']['name'] ?? '');
            }
        }
        foreach ($messages as $m) {
            if (!is_array($m)) {
                continue;
            }
            $from = sk_wa_cloud_normalize_phone((string)($m['from'] ?? ''));
            $wamid = (string)($m['id'] ?? '');
            if ($from === '' || $this->Sk_Whatsapp_cloud_model->find_by_wamid($wamid)) {
                continue;
            }
            $parsed = sk_wa_mcp_parse_inbound($m);
            $type = (string)($m['type'] ?? 'text');
            $mediaTypes = ['image', 'video', 'audio', 'document', 'sticker'];
            $mediaId = '';
            $filename = '';
            if (in_array($type, $mediaTypes, true) && is_array($m[$type] ?? null)) {
                $mediaId = (string)($m[$type]['id'] ?? '');
                $filename = trim((string)($m[$type]['filename'] ?? ''));
            }
            $body = $parsed['text'] !== '' ? $parsed['text'] : ($filename !== '' ? $filename : ucfirst($type));
            $mediaUrl = '';
            if ($mediaId !== '') {
                $resolved = $settings;
                if ($vendorId > 0) {
                    $resolved['vendor_id'] = $vendorId;
                }
                if ($phoneNumberId !== '') {
                    $resolved['_wa_phone_number_id'] = $phoneNumberId;
                }
                $cfg = sk_wa_cloud_config($resolved, $vendorId > 0 ? $vendorId : null);
                $mediaUrl = sk_wa_cloud_cache_media($mediaId, (string)($cfg['access_token'] ?? ''), (string)($cfg['graph_base'] ?? ''));
            }
            $storeType = in_array($type, array_merge(['text'], $mediaTypes), true) ? $type : 'text';
            $conv = $this->Sk_Whatsapp_cloud_model->find_or_create_conversation(
                $from,
                $names[$from] ?? '',
                $vendorId > 0 ? $vendorId : null,
                $phoneNumberId !== '' ? $phoneNumberId : null
            );
            $this->Sk_Whatsapp_cloud_model->add_message((int)$conv['id'], [
                'vendor_id'       => $vendorId > 0 ? $vendorId : null,
                'phone_number_id' => $phoneNumberId !== '' ? $phoneNumberId : null,
                'wamid'           => $wamid,
                'direction'       => 'in',
                'type'            => $storeType,
                'body'            => $body,
                'media_url'       => $mediaUrl !== '' ? $mediaUrl : ($mediaId !== '' ? $mediaId : null),
                'media_id'        => $mediaId !== '' ? $mediaId : null,
                'status'          => 'received',
                'raw_json'        => json_encode($m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            if ($forceOwner !== null) {
                $this->Sk_Vendor_meta_agent_model->set_conversation_owner((int)$conv['id'], $forceOwner, 'standby');
            }
        }
    }
}
