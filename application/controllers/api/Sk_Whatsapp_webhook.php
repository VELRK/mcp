<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/api/Sk_Base_Api.php';

class Sk_Whatsapp_webhook extends Sk_Base_Api {

    public function __construct() {
        parent::__construct();
        $this->load->helper(['sk_whatsapp_cloud', 'sk_whatsapp_mcp', 'sk_mcp_tool', 'sk_wa_ai']);
        $this->load->model(['Sk_Whatsapp_cloud_model', 'Sk_Admin_model']);
        sk_wa_cloud_ensure_schema();
    }

    /** GET verify + POST incoming (Meta Cloud). */
    public function index() {
        $method = strtoupper((string)$this->input->method(true));
        if ($method === 'GET') {
            return $this->_verify();
        }
        return $this->_ingest();
    }

    /**
     * MCP push: convert structured MCP JSON to WhatsApp and send.
     * POST /shopkart-api/whatsapp/mcp
     * Header: Authorization: Bearer {wa_mcp_token}  or  X-MCP-Token
     * Body: { "to": "60...", "messages": [ { "type": "list"|"buttons"|"text"|"cta"|"image", ... } ] }
     */
    public function mcp() {
        $settings = $this->Sk_Admin_model->get_settings();
        $cfg = sk_wa_mcp_config($settings);
        $token = $this->_mcp_request_token();
        if ($cfg['token'] !== '' && !hash_equals($cfg['token'], $token)) {
            $this->error('Invalid MCP token.', 403);
            return;
        }
        $raw = json_decode((string)$this->input->raw_input_stream, true);
        if (!is_array($raw)) {
            $raw = $this->input->post() ?: [];
        }
        $to = sk_wa_cloud_normalize_phone((string)($raw['to'] ?? $raw['phone'] ?? ''));
        if (strlen($to) < 8) {
            $this->error('Provide to / phone with country code.');
            return;
        }
        if (!sk_wa_cloud_is_ready($settings)) {
            $this->error('WhatsApp Cloud API is not connected.');
            return;
        }
        $specs = sk_wa_mcp_normalize_messages($raw);
        if (!$specs) {
            $this->error('No sendable WhatsApp messages in MCP payload.');
            return;
        }
        $name = trim((string)($raw['name'] ?? ''));
        $conv = $this->Sk_Whatsapp_cloud_model->find_or_create_conversation($to, $name);
        $n = sk_wa_mcp_send_specs($to, $specs, $conv, $settings);
        $this->success(['sent' => $n, 'conversation_id' => (int)$conv['id']], 'Sent.');
    }

    private function _mcp_request_token(): string {
        $auth = (string)$this->input->get_request_header('Authorization', true);
        if (stripos($auth, 'Bearer ') === 0) {
            return trim(substr($auth, 7));
        }
        $hdr = (string)$this->input->get_request_header('X-MCP-Token', true);
        if ($hdr !== '') {
            return trim($hdr);
        }
        return trim((string)$this->input->get_request_header('X-Api-Key', true));
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
        $jobs = [];
        if (is_array($payload)) {
            foreach ((array)($payload['entry'] ?? []) as $entry) {
                foreach ((array)($entry['changes'] ?? []) as $change) {
                    $value = $change['value'] ?? [];
                    if (!is_array($value)) {
                        continue;
                    }
                    $phoneNumberId = trim((string)($value['metadata']['phone_number_id'] ?? ''));
                    $vendorMatch = $phoneNumberId !== '' ? sk_wa_cloud_resolve_vendor_from_phone($phoneNumberId, $settings) : null;
                    $vendorId = $vendorMatch ? (int)$vendorMatch['vendor_id'] : 0;
                    $this->_store_statuses((array)($value['statuses'] ?? []));
                    $jobs = array_merge(
                        $jobs,
                        $this->_store_messages((array)($value['messages'] ?? []), (array)($value['contacts'] ?? []), $vendorId, $phoneNumberId)
                    );
                }
            }
        }

        foreach ($jobs as $job) {
            $this->_reply_via_mcp($job, $settings, (int)($job['vendor_id'] ?? 0), (string)($job['phone_number_id'] ?? ''));
        }

        http_response_code(200);
        header('Content-Type: application/json');
        echo '{"success":true}';
        exit;
    }

    private function _reply_via_mcp(array $job, array $settings, int $vendorId = 0, string $phoneNumberId = ''): void {
        $resolvedSettings = $settings;
        if ($vendorId > 0) {
            $resolvedSettings['vendor_id'] = $vendorId;
        }
        if ($phoneNumberId !== '') {
            $resolvedSettings['_wa_phone_number_id'] = $phoneNumberId;
        }
        $vid = $vendorId > 0 ? $vendorId : null;
        if (!sk_wa_cloud_is_ready($resolvedSettings, $vid)) {
            return;
        }
        $conv = $job['conversation'] ?? null;
        $parsed = $job['parsed'] ?? [];
        if (!$conv || trim((string)($parsed['text'] ?? $parsed['id'] ?? '')) === '') {
            return;
        }
        $wamid = trim((string)($job['wamid'] ?? ''));
        if ($wamid !== '') {
            try {
                require_once APPPATH . 'libraries/Whatsapp_cloud.php';
                (new Whatsapp_cloud($resolvedSettings))->show_typing($wamid);
            } catch (Throwable $e) {
                log_message('error', 'WhatsApp typing: ' . $e->getMessage());
            }
        }
        if (sk_wa_ai_is_ready($resolvedSettings)) {
            $this->_reply_via_ai($job, $resolvedSettings, $vendorId, (string)$conv['phone']);
            return;
        }
        if (!sk_wa_mcp_is_ready($resolvedSettings)) {
            return;
        }
        $resolvedCfg = sk_wa_cloud_config($resolvedSettings, $vid);
        $req = [
            'channel'          => 'whatsapp',
            'phone'            => (string)$conv['phone'],
            'name'             => (string)($conv['name'] ?? ''),
            'conversation_id'  => (int)$conv['id'],
            'phone_number_id'  => $phoneNumberId !== '' ? $phoneNumberId : (string)($resolvedCfg['phone_number_id'] ?? ''),
            'message'          => $parsed,
        ];
        $res = sk_wa_mcp_call($req, $resolvedSettings);
        if (empty($res['success']) && empty($res['data'])) {
            log_message('error', 'WhatsApp MCP call failed: ' . ($res['message'] ?? 'unknown'));
            return;
        }
        $specs = sk_wa_mcp_normalize_messages($res['data'] ?? []);
        if (!$specs) {
            return;
        }
        sk_wa_mcp_send_specs((string)$conv['phone'], $specs, $conv, $resolvedSettings);
    }

    /**
     * Customer → model (OpenAI or Gemini) → MCP tool / MySQL → natural reply → WhatsApp.
     */
    private function _reply_via_ai(array $job, array $settings, int $vendorId, string $to): void {
        $conv = $job['conversation'];
        $parsed = $job['parsed'] ?? [];
        $text = trim((string)($parsed['text'] ?? ''));
        if ($text === '') {
            return;
        }
        $history = [];
        $prior = $this->Sk_Whatsapp_cloud_model->list_messages((int)$conv['id']);
        $prior = array_slice($prior, 0, -1);
        foreach (array_slice($prior, -8) as $m) {
            $body = trim((string)($m['body'] ?? ''));
            if ($body === '') {
                continue;
            }
            $history[] = [
                'role'    => (($m['direction'] ?? '') === 'out') ? 'assistant' : 'user',
                'content' => $body,
            ];
        }
        $shopName = 'Shop';
        if ($vendorId > 0 && $this->db->table_exists('vendors')) {
            $vendor = $this->db->select('business_name, name')->where('id', $vendorId)->get('vendors')->row_array();
            $shopName = trim((string)($vendor['business_name'] ?? $vendor['name'] ?? '')) ?: $shopName;
        }
        $tenant = [
            'tenant_id'      => $vendorId > 0 ? $vendorId : 1,
            'tenant'         => $vendorId > 0 ? (string)$vendorId : '1',
            'shop_name'      => $shopName,
            'customer_phone' => $to,
        ];
        $chat = sk_wa_ai_chat($text, $tenant, $history, $settings);
        $reply = sk_wa_ai_clean_text((string)($chat['reply'] ?? ''));
        if ($reply === '') {
            log_message('error', 'WhatsApp AI returned an empty reply (' . ($chat['provider'] ?? '') . ').');
            $reply = 'I got your message. Tell me the product, or send your name and delivery address to continue.';
        }
        sk_wa_mcp_send_specs($to, [['type' => 'text', 'text' => $reply]], $conv, $settings);
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

    /** @return array<int, array{conversation:array,parsed:array,wamid:string,vendor_id:int,phone_number_id:string}> */
    private function _store_messages(array $messages, array $contacts, int $vendorId = 0, string $phoneNumberId = ''): array {
        $names = [];
        foreach ($contacts as $c) {
            $wa = (string)($c['wa_id'] ?? '');
            if ($wa !== '') {
                $names[$wa] = (string)($c['profile']['name'] ?? '');
            }
        }
        $jobs = [];
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
                'vendor_id'    => $vendorId > 0 ? $vendorId : null,
                'phone_number_id' => $phoneNumberId !== '' ? $phoneNumberId : null,
                'wamid'        => $wamid,
                'direction'    => 'in',
                'type'         => $storeType,
                'body'         => $body,
                'media_url'    => $mediaUrl !== '' ? $mediaUrl : ($mediaId !== '' ? $mediaId : null),
                'media_id'     => $mediaId !== '' ? $mediaId : null,
                'status'       => 'received',
                'raw_json'     => json_encode($m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $jobs[] = [
                'conversation'    => $conv,
                'parsed'          => $parsed,
                'wamid'           => $wamid,
                'vendor_id'       => $vendorId,
                'phone_number_id' => $phoneNumberId,
            ];
        }
        return $jobs;
    }
}
