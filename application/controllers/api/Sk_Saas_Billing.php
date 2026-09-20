<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/api/Sk_Base_Api.php';

/**
 * SaaS AI usage ingest API.
 * POST /shopkart-api/saas/ai-requests
 * Auth: Authorization Bearer {saas_billing_token|wa_mcp_token} or X-MCP-Token / X-SaaS-Token
 */
class Sk_Saas_Billing extends Sk_Base_Api {

    public function __construct() {
        parent::__construct();
        $this->load->model(['Sk_Admin_model', 'Sk_Saas_Billing_model']);
        $this->Sk_Saas_Billing_model->ensure_schema();
    }

    public function ai_requests() {
        if (strtoupper((string)$this->input->method(TRUE)) !== 'POST') {
            return $this->error('Method not allowed.', 405);
        }

        $settings = $this->Sk_Admin_model->get_settings();
        if (!$this->_auth_ok($settings)) {
            return $this->error('Invalid or missing billing token.', 403);
        }

        $raw = json_decode((string)$this->input->raw_input_stream, true);
        if (!is_array($raw)) {
            $raw = $this->input->post() ?: [];
        }

        // Batch support: { "requests": [ {...}, ... ] }
        if (!empty($raw['requests']) && is_array($raw['requests'])) {
            $out = [];
            foreach ($raw['requests'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $out[] = $this->_log_one($item, $settings);
            }
            return $this->success(['items' => $out], 'Batch processed.');
        }

        $result = $this->_log_one($raw, $settings);
        if (empty($result['ok'])) {
            return $this->error($result['message'] ?? 'Failed to log request.', 400, $result);
        }
        return $this->success($result, !empty($result['duplicate']) ? 'Already logged.' : 'Logged.');
    }

    protected function _log_one(array $raw, array $settings): array {
        $vendorId = (int)($raw['vendor_id'] ?? 0);
        if ($vendorId < 1) {
            $vendorId = (int)($settings['saas_default_vendor_id'] ?? 0);
        }

        return $this->Sk_Saas_Billing_model->log_request([
            'vendor_id'    => $vendorId,
            'request_type' => $raw['request_type'] ?? 'ai_chat',
            'request_code' => $raw['request_code'] ?? '',
            'source'       => $raw['source'] ?? 'api',
            'status'       => $raw['status'] ?? 'ok',
            'units'        => $raw['units'] ?? 1,
            'external_id'  => $raw['external_id'] ?? '',
            'meta'         => $raw['meta'] ?? null,
            'created_at'   => $raw['created_at'] ?? null,
        ]);
    }

    protected function _auth_ok(array $settings): bool {
        $token = $this->_request_token();
        if ($token === '') {
            return false;
        }
        $saas = trim((string)($settings['saas_billing_token'] ?? ''));
        $mcp  = trim((string)($settings['wa_mcp_token'] ?? ''));
        if ($saas !== '' && hash_equals($saas, $token)) {
            return true;
        }
        if ($mcp !== '' && hash_equals($mcp, $token)) {
            return true;
        }
        // Dev convenience: if neither token configured, allow (same pattern as empty MCP gate elsewhere)
        return $saas === '' && $mcp === '';
    }

    protected function _request_token(): string {
        $auth = (string)$this->input->get_request_header('Authorization', true);
        if (stripos($auth, 'Bearer ') === 0) {
            return trim(substr($auth, 7));
        }
        foreach (['X-SaaS-Token', 'X-MCP-Token'] as $h) {
            $v = trim((string)$this->input->get_request_header($h, true));
            if ($v !== '') {
                return $v;
            }
        }
        return trim((string)$this->input->get('token', TRUE));
    }
}
