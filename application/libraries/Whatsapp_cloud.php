<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Whatsapp_cloud {

    /** @var array */
    private $cfg;

    public function __construct($settings = []) {
        $CI =& get_instance();
        $CI->load->helper('sk_whatsapp_cloud');
        $this->cfg = sk_wa_cloud_config(is_array($settings) ? $settings : null);
    }

    public function is_ready(): bool {
        return $this->cfg['enabled']
            && $this->cfg['access_token'] !== ''
            && $this->cfg['phone_number_id'] !== ''
            && $this->cfg['waba_id'] !== '';
    }

    public function config(): array {
        return $this->cfg;
    }

    public function list_templates(): array {
        $waba = $this->cfg['waba_id'];
        if ($waba === '') {
            return ['success' => false, 'message' => 'WhatsApp Business Account ID is missing.', 'data' => []];
        }
        $qs = http_build_query([
            'fields' => 'id,name,language,status,category,components',
            'limit'  => 100,
        ]);
        return $this->request('GET', $waba . '/message_templates?' . $qs);
    }

    public function create_template(array $payload): array {
        $waba = $this->cfg['waba_id'];
        if ($waba === '') {
            return ['success' => false, 'message' => 'WhatsApp Business Account ID is missing.'];
        }
        return $this->request('POST', $waba . '/message_templates', $payload);
    }

    public function delete_template(string $name): array {
        $waba = $this->cfg['waba_id'];
        if ($waba === '' || $name === '') {
            return ['success' => false, 'message' => 'Template name or WABA ID missing.'];
        }
        return $this->request('DELETE', $waba . '/message_templates?name=' . rawurlencode($name));
    }

    public function send_text(string $to, string $text): array {
        return $this->send_message([
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'text',
            'text'              => ['preview_url' => false, 'body' => $text],
        ]);
    }

    public function send_media(string $to, string $type, string $link, string $caption = ''): array {
        $type = in_array($type, ['image', 'video', 'document', 'audio'], true) ? $type : 'image';
        $media = ['link' => $link];
        if ($caption !== '' && in_array($type, ['image', 'video', 'document'], true)) {
            $media['caption'] = $caption;
        }
        return $this->send_message([
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => $type,
            $type               => $media,
        ]);
    }

    public function send_template(string $to, string $name, string $language, array $components = []): array {
        $tpl = [
            'name'     => $name,
            'language' => ['code' => $language],
        ];
        if ($components) {
            $tpl['components'] = $components;
        }
        return $this->send_message([
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'template',
            'template'          => $tpl,
        ]);
    }

    public function upload_media(string $absPath, string $mime): array {
        $phoneId = $this->cfg['phone_number_id'];
        if ($phoneId === '' || !is_file($absPath)) {
            return ['success' => false, 'message' => 'Media file or Phone Number ID missing.'];
        }
        $url = $this->cfg['graph_base'] . '/' . $phoneId . '/media';
        $ch = curl_init($url);
        $cfile = new CURLFile($absPath, $mime, basename($absPath));
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $this->cfg['access_token']],
            CURLOPT_POSTFIELDS     => [
                'messaging_product' => 'whatsapp',
                'type'              => $mime,
                'file'              => $cfile,
            ],
            CURLOPT_TIMEOUT        => 90,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if ($err) {
            return ['success' => false, 'message' => $err, 'http' => $code];
        }
        $id = is_array($decoded) ? (string)($decoded['id'] ?? '') : '';
        if ($id === '') {
            return [
                'success' => false,
                'message' => $this->error_message($decoded) ?: 'Media upload failed.',
                'raw'     => $decoded,
                'http'    => $code,
            ];
        }
        return ['success' => true, 'id' => $id, 'raw' => $decoded];
    }

    public function send_payload(array $payload): array {
        return $this->send_message($payload);
    }

    public function send_interactive(string $to, array $interactive): array {
        return $this->send_message([
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'interactive',
            'interactive'       => $interactive,
        ]);
    }

    public function send_media_id(string $to, string $type, string $mediaId, string $caption = ''): array {
        $type = in_array($type, ['image', 'video', 'document', 'audio'], true) ? $type : 'image';
        $media = ['id' => $mediaId];
        if ($caption !== '' && in_array($type, ['image', 'video', 'document'], true)) {
            $media['caption'] = $caption;
        }
        return $this->send_message([
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => $type,
            $type               => $media,
        ]);
    }

    private function send_message(array $payload): array {
        $phoneId = $this->cfg['phone_number_id'];
        if ($phoneId === '') {
            return ['success' => false, 'message' => 'Phone Number ID is missing.'];
        }
        return $this->request('POST', $phoneId . '/messages', $payload);
    }

    private function request(string $method, string $path, ?array $body = null): array {
        if ($this->cfg['access_token'] === '') {
            return ['success' => false, 'message' => 'Meta access token is missing.'];
        }
        $url = $this->cfg['graph_base'] . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $this->cfg['access_token'],
            'Content-Type: application/json',
        ];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 45,
        ]);
        if ($body !== null && $method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if ($err) {
            return ['success' => false, 'message' => $err, 'http' => $code];
        }
        $ok = $code >= 200 && $code < 300 && empty($decoded['error']);
        return [
            'success' => $ok,
            'message' => $ok ? 'OK' : ($this->error_message($decoded) ?: ('HTTP ' . $code)),
            'data'    => $decoded,
            'http'    => $code,
        ];
    }

    private function error_message($decoded): string {
        if (!is_array($decoded)) {
            return '';
        }
        $err = $decoded['error'] ?? null;
        if (is_array($err)) {
            $msg = trim((string)($err['error_user_msg'] ?? $err['message'] ?? ''));
            $code = (string)($err['code'] ?? '');
            return $code !== '' ? $msg . ' (#' . $code . ')' : $msg;
        }
        return '';
    }
}
