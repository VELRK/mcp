<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/api/Sk_Base_Api.php';

/**
 * WhatsApp Flow data endpoint for the delivery address form.
 * Meta posts an encrypted body. The reply is encrypted with the same AES key.
 */
class Sk_Whatsapp_flow extends Sk_Base_Api {

    public function address() {
        $this->load->helper('sk_wa_address_flow');
        $raw = (string)file_get_contents('php://input');
        $body = json_decode($raw, true);
        if (!is_array($body)) {
            $this->_plain(400, json_encode(['error' => 'Expected JSON.']));
        }
        $decoded = sk_wa_flow_decrypt_request($body);
        if (empty($decoded['ok'])) {
            $this->_plain(421, json_encode(['error' => (string)($decoded['error'] ?? 'decryption failed')]));
        }
        $response = sk_wa_address_flow_handle(is_array($decoded['data'] ?? null) ? $decoded['data'] : []);
        $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $encrypted = sk_wa_flow_encrypt_response((string)$json, (string)$decoded['aes'], (string)$decoded['iv']);
        if ($encrypted === '') {
            $this->_plain(500, json_encode(['error' => 'Could not encrypt the Flow response.']));
        }
        $this->_plain(200, $encrypted);
    }

    private function _plain(int $status, string $body): void {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        echo $body;
        exit;
    }
}
