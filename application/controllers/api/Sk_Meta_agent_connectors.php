<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/api/Sk_Base_Api.php';

/**
 * Authenticated commerce tools for Meta Business Agent connectors.
 * Tenant is resolved only from the WhatsApp phone_number_id in the URL.
 */
class Sk_Meta_agent_connectors extends Sk_Base_Api {

    public function __construct() {
        parent::__construct();
        $this->load->helper(['sk_meta_business_agent', 'sk_mcp_tool', 'sk_whatsapp_cloud']);
        $this->load->model(['Sk_Vendor_whatsapp_account_model', 'Sk_Vendor_meta_agent_model', 'Sk_Whatsapp_cloud_model', 'Sk_Product_model']);
        $this->Sk_Vendor_meta_agent_model->ensure_schema();
    }

    private function _require_connector_auth(): void {
        $token = sk_meta_ba_request_token_from_headers();
        if (!sk_meta_ba_connector_auth_ok($token)) {
            $this->error('Invalid or missing connector API key.', 403);
        }
    }

    /**
     * @return array{vendor_id:int,phone_number_id:string}
     */
    private function _resolve_tenant(string $phoneNumberId): array {
        $phoneNumberId = trim($phoneNumberId);
        if ($phoneNumberId === '') {
            $this->error('phone_number_id is required.', 400);
        }
        $acct = $this->Sk_Vendor_whatsapp_account_model->get_by_phone($phoneNumberId);
        if (!$acct || empty($acct['vendor_id'])) {
            $this->error('Unknown or inactive WhatsApp phone_number_id.', 404);
        }
        return [
            'vendor_id'       => (int)$acct['vendor_id'],
            'phone_number_id' => $phoneNumberId,
        ];
    }

    private function _payload(): array {
        $payload = $this->body();
        return is_array($payload) ? $payload : [];
    }

    /**
     * Slim JSON for Meta BA — avoid image paths / huge nested blobs.
     */
    private function _slim_success(array $result, string $okMessage = 'OK'): void {
        if (empty($result['success'])) {
            $err = is_array($result['error'] ?? null) ? $result['error'] : [];
            $this->error(
                (string)($err['message'] ?? 'Tool failed.'),
                400,
                [
                    'code' => (string)($err['code'] ?? 'TOOL_ERROR'),
                    'data' => $result['data'] ?? null,
                ]
            );
        }
        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        // Prefer a short summary string when present for faster Meta replies.
        $this->success($data, $okMessage);
    }

    private function _run_tool(string $phoneNumberId, string $tool): void {
        $this->_require_connector_auth();
        $tenant = $this->_resolve_tenant($phoneNumberId);
        $payload = $this->_payload();
        $payload['phone_number_id'] = $tenant['phone_number_id'];
        if (empty($payload['phone']) && empty($payload['customer_phone'])) {
            // Meta may bind WHATSAPP_PHONE_NUMBER into phone.
        }
        $result = sk_ai_mcp_execute_tool($tool, $payload, [
            'tenant_id' => $tenant['vendor_id'],
            'tenant' => $tenant['vendor_id'],
        ]);
        $this->_slim_success($result, $tool . ' completed.');
    }

    // Explicit methods so Meta path sync stays stable.
    public function identify_customer($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'identify_customer'); }
    public function create_customer($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'create_customer'); }
    public function update_customer($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'update_customer'); }
    public function get_customer($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'get_customer'); }
    public function get_product($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'get_product'); }
    public function get_product_details($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'get_product_details'); }
    public function get_product_price($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'get_product_price'); }
    public function check_stock($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'check_stock'); }
    public function calculate_order_total($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'calculate_order_total'); }
    public function create_order($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'create_order'); }
    public function get_order($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'get_order'); }
    public function create_payment_link($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'create_payment_link'); }
    public function get_payment_status($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'get_payment_status'); }
    public function generate_invoice($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'generate_invoice'); }
    public function handover_to_human($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'handover_to_human'); }
    public function human_handoff($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'human_handoff'); }
    public function get_tenant_config($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'get_tenant_config'); }
    public function save_conversation_state($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'save_conversation_state'); }
    public function get_order_status($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'get_order_status'); }
    public function get_delivery_status($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'get_delivery_status'); }

    public function search_products($phoneNumberId = '') {
        $this->_require_connector_auth();
        $tenant = $this->_resolve_tenant((string)$phoneNumberId);
        $payload = $this->_payload();
        $query = trim((string)($payload['query'] ?? $payload['search'] ?? $payload['message'] ?? $payload['text'] ?? ''));
        if ($query === '') {
            $this->error('query is required.', 400);
        }
        $result = sk_mcp_tool_find_products($query, ['tenant' => $tenant['vendor_id']], 3);
        $products = $this->_meta_safe_products($result['results'] ?? []);
        // One fast catalog fallback only — avoid multi-query delays that push Meta past 10s.
        if (!$products) {
            $listed = $this->Sk_Product_model->get_all(
                ['status' => 'active', 'vendor_id' => $tenant['vendor_id'], 'sort' => 'newest'],
                3,
                0
            );
            foreach (($listed['data'] ?? []) as $product) {
                $products[] = [
                    'id'               => (int)($product['id'] ?? 0),
                    'name'             => (string)($product['name'] ?? ''),
                    'sku'              => (string)($product['sku'] ?? ''),
                    'color'            => trim((string)($product['color'] ?? '')),
                    'length'           => trim((string)($product['saree_length'] ?? '')),
                    'blouse_included'  => !empty($product['blouse_included']),
                    'pack_of'          => trim((string)($product['pack_of'] ?? '')),
                    'available'        => ((int)($product['stock'] ?? 0)) > 0,
                    'stock'            => (int)($product['stock'] ?? 0),
                    'price'            => (float)($product['effective_price'] ?? $product['price'] ?? 0),
                    'mrp'              => (float)($product['price'] ?? 0),
                    'currency'         => 'INR',
                ];
            }
        }
        $lines = [];
        foreach ($products as $p) {
            $extra = [];
            if (!empty($p['color'])) {
                $extra[] = (string)$p['color'];
            }
            if (!empty($p['length'])) {
                $extra[] = (string)$p['length'] . 'm';
            }
            if (!empty($p['blouse_included'])) {
                $extra[] = 'blouse included';
            }
            $lines[] = sprintf(
                '%s — ₹%s%s, stock %d',
                (string)($p['name'] ?? 'Product'),
                (string)($p['price'] ?? '0'),
                $extra ? (', ' . implode(', ', $extra)) : '',
                (int)($p['stock'] ?? 0)
            );
        }
        $this->success([
            'query'    => $query,
            'count'    => count($products),
            'summary'  => $products
                ? ("Found " . count($products) . " products:\n- " . implode("\n- ", $lines))
                : 'No matching products in the catalog.',
            'products' => $products,
        ], 'Product search completed.');
    }

    private function _meta_safe_products(array $rows): array {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'id'               => (int)($row['id'] ?? 0),
                'name'             => (string)($row['name'] ?? ''),
                'sku'              => (string)($row['sku'] ?? ''),
                'color'            => (string)($row['color'] ?? ''),
                'length'           => (string)($row['length'] ?? $row['saree_length'] ?? ''),
                'blouse_included'  => !empty($row['blouse_included']),
                'pack_of'          => (string)($row['pack_of'] ?? ''),
                'available'        => !empty($row['available']) || ((int)($row['stock'] ?? 0)) > 0,
                'stock'            => (int)($row['stock'] ?? 0),
                'price'            => (float)($row['price'] ?? 0),
                'mrp'              => (float)($row['mrp'] ?? 0),
                'currency'         => (string)($row['currency'] ?? 'INR'),
            ];
        }
        return $out;
    }
}
