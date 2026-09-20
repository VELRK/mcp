<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/api/Sk_Base_Api.php';

class Sk_Ai_Whatsapp extends Sk_Base_Api {

    public function __construct() {
        parent::__construct();
        $this->load->helper('sk_mcp_tool');
    }

    public function message() {
        $payload = $this->body();
        if (empty($payload)) {
            $payload = $this->input->post() ?: $this->input->get(null, true);
        }

        $normalized = sk_ai_normalize_whatsapp_message($payload);
        $tenant = sk_ai_tenant_resolve($normalized);
        $processed = sk_ai_process_whatsapp_message($normalized, $tenant);
        $talkai = sk_ai_build_talkai_response($processed);

        $this->success([
            'message_id' => $processed['message_id'],
            'customer_phone' => $processed['customer_phone'],
            'tenant' => $processed['tenant'],
            'intent' => $processed['intent'],
            'tool' => $processed['tool'],
            'tool_result' => $processed['tool_result'],
            'reply' => $processed['reply'],
            'normalized' => $normalized,
            'talkai' => $talkai,
        ], 'WhatsApp AI message processed.');
    }
}
