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

    public function search_products($phoneNumberId = '') {
        $this->_require_connector_auth();
        $tenant = $this->_resolve_tenant((string)$phoneNumberId);
        $payload = $this->_payload();
        $query = trim((string)($payload['query'] ?? $payload['message'] ?? $payload['text'] ?? ''));
        if ($query === '') {
            $this->error('query is required.', 400);
        }
        $result = sk_mcp_tool_find_products($query, ['tenant' => $tenant['vendor_id']], 5);
        $products = $this->_meta_safe_products($result['results'] ?? []);
        // Vague catalog questions often parse to stop-words and return nothing; give Meta a sample.
        if (!$products) {
            foreach (['saree', 'silk', 'dress'] as $fallbackQ) {
                $fallback = sk_mcp_tool_find_products($fallbackQ, ['tenant' => $tenant['vendor_id']], 5);
                $products = $this->_meta_safe_products($fallback['results'] ?? []);
                if ($products) {
                    break;
                }
            }
        }
        if (!$products) {
            $listed = $this->Sk_Product_model->get_all(
                ['status' => 'active', 'vendor_id' => $tenant['vendor_id'], 'sort' => 'newest'],
                5,
                0
            );
            foreach (($listed['data'] ?? []) as $product) {
                $products[] = [
                    'id'        => (int)($product['id'] ?? 0),
                    'name'      => (string)($product['name'] ?? ''),
                    'sku'       => (string)($product['sku'] ?? ''),
                    'color'     => trim((string)($product['color'] ?? '')),
                    'available' => ((int)($product['stock'] ?? 0)) > 0,
                    'stock'     => (int)($product['stock'] ?? 0),
                    'price'     => (float)($product['effective_price'] ?? $product['price'] ?? 0),
                    'mrp'       => (float)($product['price'] ?? 0),
                    'currency'  => 'INR',
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

    /**
     * Flatten product rows for Meta Business Agent (avoid image paths / nested junk that can 500 agent_test).
     */
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

    public function check_stock($phoneNumberId = '') {
        $this->_require_connector_auth();
        $tenant = $this->_resolve_tenant((string)$phoneNumberId);
        $payload = $this->_payload();
        $productId = (int)($payload['product_id'] ?? 0);
        if ($productId <= 0) {
            $this->error('product_id is required.', 400);
        }
        $result = sk_ai_mcp_execute_tool(
            'check_stock',
            [
                'product_id' => $productId,
                'variant_id' => (int)($payload['variant_id'] ?? 0),
            ],
            ['tenant_id' => $tenant['vendor_id']]
        );
        if (empty($result['success'])) {
            $this->error($result['error']['message'] ?? 'Stock lookup failed.', 400, ['vendor_id' => $tenant['vendor_id']]);
        }
        $this->success([
            'vendor_id' => $tenant['vendor_id'],
            'stock'     => $result['data'],
        ], 'Stock check completed.');
    }

    public function get_order_status($phoneNumberId = '') {
        $this->_require_connector_auth();
        $tenant = $this->_resolve_tenant((string)$phoneNumberId);
        $payload = $this->_payload();
        $orderId = (int)($payload['order_id'] ?? $payload['id'] ?? 0);
        if ($orderId <= 0) {
            $this->error('order_id is required.', 400);
        }
        $result = sk_ai_mcp_execute_tool(
            'get_order_status',
            ['order_id' => $orderId],
            ['tenant_id' => $tenant['vendor_id']]
        );
        if (empty($result['success'])) {
            $this->error($result['error']['message'] ?? 'Order not found.', 404, ['vendor_id' => $tenant['vendor_id']]);
        }
        $this->success([
            'vendor_id' => $tenant['vendor_id'],
            'order'     => $result['data'],
        ], 'Order status retrieved.');
    }

    public function get_delivery_status($phoneNumberId = '') {
        $this->_require_connector_auth();
        $tenant = $this->_resolve_tenant((string)$phoneNumberId);
        $payload = $this->_payload();
        $orderId = (int)($payload['order_id'] ?? $payload['id'] ?? 0);
        if ($orderId <= 0) {
            $this->error('order_id is required.', 400);
        }
        $result = sk_ai_mcp_execute_tool(
            'get_delivery_status',
            ['order_id' => $orderId],
            ['tenant_id' => $tenant['vendor_id']]
        );
        if (empty($result['success'])) {
            $this->error($result['error']['message'] ?? 'Delivery status not found.', 404, ['vendor_id' => $tenant['vendor_id']]);
        }
        $this->success([
            'vendor_id' => $tenant['vendor_id'],
            'delivery'  => $result['data'],
        ], 'Delivery status retrieved.');
    }

    public function human_handoff($phoneNumberId = '') {
        $this->_require_connector_auth();
        $tenant = $this->_resolve_tenant((string)$phoneNumberId);
        $payload = $this->_payload();
        $phone = trim((string)($payload['phone'] ?? $payload['customer_phone'] ?? ''));
        $reason = trim((string)($payload['reason'] ?? 'Customer requested human support.'));
        if ($phone === '') {
            $this->error('phone is required.', 400);
        }
        $phone = function_exists('sk_wa_cloud_normalize_phone')
            ? sk_wa_cloud_normalize_phone($phone)
            : preg_replace('/\D+/', '', $phone);

        $conv = $this->Sk_Whatsapp_cloud_model->find_or_create_conversation(
            $phone,
            '',
            $tenant['vendor_id'],
            $tenant['phone_number_id']
        );
        $this->Sk_Vendor_meta_agent_model->set_conversation_owner((int)$conv['id'], 'human', $reason);

        // Bump unread so inbox surfaces the handoff.
        $this->db->where('id', (int)$conv['id'])->update('wa_cloud_conversations', [
            'unread'       => (int)($conv['unread'] ?? 0) + 1,
            'last_message' => 'Handoff: ' . mb_substr($reason, 0, 180),
            'last_direction' => 'in',
            'last_at'      => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        $this->success([
            'vendor_id'       => $tenant['vendor_id'],
            'conversation_id' => (int)$conv['id'],
            'phone'           => $phone,
            'thread_owner'    => 'human',
            'reason'          => $reason,
            'message'         => 'Customer handed off to the Talk AI Pilot inbox.',
        ], 'Handoff recorded.');
    }
}
