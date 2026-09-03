<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/api/Sk_Base_Api.php';

class Sk_Whatsapp_webhook extends Sk_Base_Api {

    public function __construct() {
        parent::__construct();
        $this->load->helper(['sk_whatsapp_cloud', 'sk_whatsapp_mcp']);
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
                    $this->_store_statuses((array)($value['statuses'] ?? []));
                    $jobs = array_merge(
                        $jobs,
                        $this->_store_messages((array)($value['messages'] ?? []), (array)($value['contacts'] ?? []))
                    );
                }
            }
        }

        foreach ($jobs as $job) {
            $this->_reply_via_mcp($job, $settings);
        }

        http_response_code(200);
        header('Content-Type: application/json');
        echo '{"success":true}';
        exit;
    }

    private function _reply_via_mcp(array $job, array $settings): void {
        if (!sk_wa_mcp_is_ready($settings) || !sk_wa_cloud_is_ready($settings)) {
            return;
        }
        $conv = $job['conversation'] ?? null;
        $parsed = $job['parsed'] ?? [];
        if (!$conv || trim((string)($parsed['text'] ?? $parsed['id'] ?? '')) === '') {
            return;
        }
        $req = [
            'channel'          => 'whatsapp',
            'phone'            => (string)$conv['phone'],
            'name'             => (string)($conv['name'] ?? ''),
            'conversation_id'  => (int)$conv['id'],
            'phone_number_id'  => (string)($settings['wa_cloud_phone_number_id'] ?? ''),
            'message'          => $parsed,
        ];
        $res = sk_wa_mcp_call($req, $settings);
        if (empty($res['success']) && empty($res['data'])) {
            log_message('error', 'WhatsApp MCP call failed: ' . ($res['message'] ?? 'unknown'));
            return;
        }
        $specs = sk_wa_mcp_normalize_messages($res['data'] ?? []);
        if (!$specs) {
            return;
        }
        sk_wa_mcp_send_specs((string)$conv['phone'], $specs, $conv, $settings);
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

    /** @return array<int, array{conversation:array,parsed:array}> */
    private function _store_messages(array $messages, array $contacts): array {
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
            $body = $parsed['text'] !== '' ? $parsed['text'] : ucfirst($type);
            $mediaUrl = '';
            if ($type === 'image') {
                $mediaUrl = (string)($m['image']['id'] ?? '');
            } elseif ($type === 'video') {
                $mediaUrl = (string)($m['video']['id'] ?? '');
            }
            $storeType = in_array($type, ['text', 'image', 'video'], true) ? $type : 'text';
            $conv = $this->Sk_Whatsapp_cloud_model->find_or_create_conversation($from, $names[$from] ?? '');
            $this->Sk_Whatsapp_cloud_model->add_message((int)$conv['id'], [
                'wamid'     => $wamid,
                'direction' => 'in',
                'type'      => $storeType,
                'body'      => $body,
                'media_url' => $mediaUrl ?: null,
                'media_id'  => $mediaUrl ?: null,
                'status'    => 'received',
                'raw_json'  => json_encode($m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $jobs[] = ['conversation' => $conv, 'parsed' => $parsed];
        }
        return $jobs;
    }
}
