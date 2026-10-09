<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/api/Sk_Base_Api.php';

/**
 * Meta WhatsApp Cloud webhook.
 * With Meta Business Agent enabled, this app is standby: store history only;
 * Meta Agent is the automatic responder. Handovers update thread ownership.
 *
 * Standby payloads nest under value.standby.messages / value.standby.message_echoes
 * (AI replies). Those must be persisted for the inbox tenant (phone_number_id → vendor).
 */
class Sk_Whatsapp_webhook extends Sk_Base_Api {

    public function __construct() {
        parent::__construct();
        $this->load->helper(['sk_whatsapp_cloud', 'sk_whatsapp', 'sk_whatsapp_mcp', 'sk_meta_business_agent']);
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

                    // Meta BA standby nests messages/echoes under value.standby.
                    $value = $this->_normalize_change_value($field, $value);

                    $phoneNumberId = trim((string)(
                        $value['metadata']['phone_number_id']
                        ?? $value['recipient']['phone_number_id']
                        ?? ''
                    ));
                    $vendorMatch = $phoneNumberId !== '' ? sk_wa_cloud_resolve_vendor_from_phone($phoneNumberId, $settings) : null;
                    $vendorId = $vendorMatch ? (int)$vendorMatch['vendor_id'] : 0;

                    if ($field === 'messaging_handovers') {
                        $this->_store_handovers($value, $vendorId, $phoneNumberId);
                        continue;
                    }

                    $this->_store_statuses((array)($value['statuses'] ?? []));

                    $forceOwner = ($field === 'standby') ? 'meta_agent' : null;
                    $this->_store_messages(
                        (array)($value['messages'] ?? []),
                        (array)($value['contacts'] ?? []),
                        $vendorId,
                        $phoneNumberId,
                        $settings,
                        'in',
                        $forceOwner
                    );
                    // AI / business app outbound echoes (standby.message_echoes, smb_message_echoes).
                    $this->_store_messages(
                        (array)($value['message_echoes'] ?? []),
                        (array)($value['contacts'] ?? []),
                        $vendorId,
                        $phoneNumberId,
                        $settings,
                        'out',
                        $forceOwner
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

    /**
     * Flatten field-specific nesting so messages/echoes sit on value.* like Cloud API messages.
     */
    private function _normalize_change_value(string $field, array $value): array {
        if ($field === 'standby' && !empty($value['standby']) && is_array($value['standby'])) {
            $nested = $value['standby'];
            foreach (['messages', 'message_echoes', 'contacts', 'statuses'] as $k) {
                if (empty($nested[$k]) || !is_array($nested[$k])) {
                    continue;
                }
                $existing = isset($value[$k]) && is_array($value[$k]) ? $value[$k] : [];
                $value[$k] = array_merge($existing, $nested[$k]);
            }
        }

        // smb_message_echoes: business-app outbound; treat any messages[] as echoes.
        if ($field === 'smb_message_echoes'
            && empty($value['message_echoes'])
            && !empty($value['messages'])
            && is_array($value['messages'])
        ) {
            $value['message_echoes'] = $value['messages'];
            unset($value['messages']);
        }

        return $value;
    }

    private function _store_handovers(array $value, int $vendorId, string $phoneNumberId): void {
        $to = '';
        if (!empty($value['contacts'][0]['wa_id'])) {
            $to = sk_wa_cloud_normalize_phone((string)$value['contacts'][0]['wa_id']);
        } elseif (!empty($value['sender']['phone_number'])) {
            $to = sk_wa_cloud_normalize_phone((string)$value['sender']['phone_number']);
        } elseif (!empty($value['recipient_id'])) {
            $to = sk_wa_cloud_normalize_phone((string)$value['recipient_id']);
        } elseif (!empty($value['from'])) {
            $to = sk_wa_cloud_normalize_phone((string)$value['from']);
        }

        if ($phoneNumberId === '' && !empty($value['recipient']['phone_number_id'])) {
            $phoneNumberId = trim((string)$value['recipient']['phone_number_id']);
            if ($vendorId < 1 && $phoneNumberId !== '') {
                $match = sk_wa_cloud_resolve_vendor_from_phone($phoneNumberId);
                $vendorId = $match ? (int)$match['vendor_id'] : 0;
            }
        }

        $prevRole = strtolower((string)($value['control_passed']['previous_owner_app_role'] ?? ''));
        $type = strtolower((string)($value['type'] ?? ''));
        $newOwnerRaw = strtolower((string)(
            $value['new_owner']['app_id']
            ?? $value['new_thread_owner']
            ?? $value['thread_owner']
            ?? $value['new_owner']
            ?? ''
        ));

        $owner = 'meta_agent';
        if ($type === 'control_passed' || $prevRole === 'meta_business_agent') {
            // Agent passed control to the app / human.
            $owner = 'app';
        } elseif ($newOwnerRaw !== '') {
            if (strpos($newOwnerRaw, 'ai') !== false || strpos($newOwnerRaw, 'agent') !== false) {
                $owner = 'meta_agent';
            } else {
                $owner = 'app';
            }
        }
        if (!empty($value['handover']) || !empty($value['passed_control']) || !empty($value['requested'])) {
            if (!empty($value['requested']) || (isset($value['passed_control']['new_owner']['app']))) {
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
            if (function_exists('sk_whatsapp_apply_meta_status')) {
                sk_whatsapp_apply_meta_status($wamid, $status, $err);
            }
        }
    }

    /**
     * Persist inbound customer messages or outbound AI/business echoes.
     *
     * @param string $direction 'in' | 'out'
     */
    private function _store_messages(
        array $messages,
        array $contacts,
        int $vendorId,
        string $phoneNumberId,
        array $settings,
        string $direction = 'in',
        ?string $forceOwner = null
    ): void {
        $names = [];
        foreach ($contacts as $c) {
            $wa = (string)($c['wa_id'] ?? '');
            if ($wa !== '') {
                $names[sk_wa_cloud_normalize_phone($wa)] = (string)($c['profile']['name'] ?? '');
            }
        }

        foreach ($messages as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $m = $this->_normalize_message_item($raw, $direction);
            if ($m === null) {
                continue;
            }

            $peer = sk_wa_cloud_normalize_phone((string)($m['_peer'] ?? ''));
            $wamid = (string)($m['id'] ?? '');
            if ($peer === '' || $wamid === '' || $this->Sk_Whatsapp_cloud_model->find_by_wamid($wamid)) {
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
                $peer,
                $names[$peer] ?? '',
                $vendorId > 0 ? $vendorId : null,
                $phoneNumberId !== '' ? $phoneNumberId : null
            );
            $this->Sk_Whatsapp_cloud_model->add_message((int)$conv['id'], [
                'vendor_id'       => $vendorId > 0 ? $vendorId : null,
                'phone_number_id' => $phoneNumberId !== '' ? $phoneNumberId : null,
                'wamid'           => $wamid,
                'direction'       => $direction === 'out' ? 'out' : 'in',
                'type'            => $storeType,
                'body'            => $body,
                'media_url'       => $mediaUrl !== '' ? $mediaUrl : ($mediaId !== '' ? $mediaId : null),
                'media_id'        => $mediaId !== '' ? $mediaId : null,
                'status'          => $direction === 'out' ? 'sent' : 'received',
                'raw_json'        => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            if ($forceOwner !== null) {
                $this->Sk_Vendor_meta_agent_model->set_conversation_owner((int)$conv['id'], $forceOwner, 'standby');
            } elseif ($direction === 'out' && $this->_is_bizai_echo($raw, $m)) {
                $this->Sk_Vendor_meta_agent_model->set_conversation_owner((int)$conv['id'], 'meta_agent', 'message_echo');
            }
        }
    }

    /**
     * Normalize Cloud messages and nested standby echoes into a common message shape.
     * Echo shape A: { from, to, id, type, text }
     * Echo shape B: { id, timestamp, message: { to, type, text, biz_opaque_callback_data } }
     */
    private function _normalize_message_item(array $raw, string $direction): ?array {
        $m = $raw;
        if (!empty($raw['message']) && is_array($raw['message'])) {
            $inner = $raw['message'];
            $m = array_merge($inner, [
                'id' => (string)($raw['id'] ?? $inner['id'] ?? ''),
                'timestamp' => (string)($raw['timestamp'] ?? $inner['timestamp'] ?? ''),
            ]);
        }

        $wamid = (string)($m['id'] ?? $raw['id'] ?? '');
        if ($wamid === '') {
            return null;
        }
        $m['id'] = $wamid;

        if ($direction === 'out') {
            $peer = (string)($m['to'] ?? $raw['to'] ?? $m['recipient'] ?? '');
        } else {
            $peer = (string)($m['from'] ?? $raw['from'] ?? '');
        }
        $peer = sk_wa_cloud_normalize_phone($peer);
        if ($peer === '') {
            return null;
        }
        $m['_peer'] = $peer;
        if (empty($m['type'])) {
            $m['type'] = 'text';
        }
        return $m;
    }

    private function _is_bizai_echo(array $raw, array $m): bool {
        $opaque = $m['biz_opaque_callback_data'] ?? $raw['biz_opaque_callback_data'] ?? null;
        if (is_string($opaque)) {
            $decoded = json_decode($opaque, true);
            $opaque = is_array($decoded) ? $decoded : null;
        }
        if (is_array($opaque) && strtolower((string)($opaque['originator'] ?? '')) === 'bizai') {
            return true;
        }
        return false;
    }
}
