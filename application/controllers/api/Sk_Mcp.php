<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/api/Sk_Base_Api.php';

class Sk_Mcp extends Sk_Base_Api {

    public function __construct() {
        parent::__construct();
        $this->load->helper('sk_mcp_tool');
    }

    public function tool() {
        $payload = $this->body();
        if (empty($payload)) {
            $payload = $this->input->get(null, true);
        }

        $query = trim((string)($payload['query'] ?? $payload['message'] ?? $payload['text'] ?? $this->input->get('q', true)));
        if ($query === '') {
            return $this->error('Missing product query.', 400, ['expected' => 'query|message|text']);
        }

        $tenant = sk_mcp_tool_resolve_tenant($payload['tenant'] ?? $payload['tenant_id'] ?? null);
        $data = sk_mcp_tool_find_products($query, $tenant, 5);

        if (empty($data['results'])) {
            return $this->error('No matching product found.', 404, $data);
        }

        $best = $data['results'][0];
        $this->success([
            'tenant' => $data['tenant'],
            'query' => $data['query'],
            'parsed' => $data['parsed'],
            'matches' => $data['results'],
            'best_match' => [
                'name' => $best['name'],
                'size' => $best['size'],
                'available' => $best['available'],
                'price' => $best['price'],
                'currency' => $best['currency'],
            ],
        ], 'Product lookup completed.');
    }

    public function resolve_product() {
        $this->tool();
    }

    public function product_search() {
        $this->tool();
    }

    public function search_products() {
        $payload = $this->body();
        if (empty($payload)) {
            $payload = $this->input->get(null, true);
        }
        $query = trim((string)($payload['query'] ?? $payload['message'] ?? $payload['text'] ?? $this->input->get('q', true)));
        if ($query === '') {
            return $this->error('Missing query.', 400, ['expected' => 'query']);
        }

        $tenant = sk_mcp_tool_resolve_tenant($payload['tenant'] ?? $payload['tenant_id'] ?? null);
        $result = sk_mcp_tool_find_products($query, ['tenant' => $tenant['tenant']], 5);
        if (empty($result['results'])) {
            return $this->error('No products found for the current tenant.', 404, ['tenant' => $tenant, 'parsed' => $result['parsed']]);
        }

        $this->success([
            'success' => true,
            'tenant' => $tenant,
            'query' => $result['query'],
            'parsed' => $result['parsed'],
            'products' => $result['results'],
        ], 'Product search completed.');
    }

    public function check_stock() {
        $payload = $this->body();
        if (empty($payload)) {
            $payload = $this->input->get(null, true);
        }

        $productId = (int)($payload['product_id'] ?? 0);
        $variantId = (int)($payload['variant_id'] ?? 0);
        if ($productId <= 0) {
            return $this->error('product_id is required.', 400, ['expected' => 'product_id']);
        }

        $tenant = sk_mcp_tool_resolve_tenant($payload['tenant'] ?? $payload['tenant_id'] ?? null);
        $result = sk_ai_mcp_execute_tool('check_stock', ['product_id' => $productId, 'variant_id' => $variantId], ['tenant_id' => (int)$tenant['tenant']]);

        if (!$result['success']) {
            return $this->error($result['error']['message'] ?? 'Stock lookup failed.', 400, ['tenant' => $tenant]);
        }

        $this->success([
            'success' => true,
            'tenant' => $tenant,
            'data' => $result['data'],
            'error' => null,
        ], 'Stock check completed.');
    }

    public function get_customer() {
        $payload = $this->body();
        if (empty($payload)) {
            $payload = $this->input->get(null, true);
        }

        $phone = trim((string)($payload['phone'] ?? $payload['customer_phone'] ?? ''));
        if ($phone === '') {
            return $this->error('phone is required.', 400, ['expected' => 'phone|customer_phone']);
        }

        $tenant = sk_mcp_tool_resolve_tenant($payload['tenant'] ?? $payload['tenant_id'] ?? null);
        $result = sk_ai_mcp_execute_tool('get_customer', ['phone' => $phone], ['tenant_id' => (int)$tenant['tenant']]);

        if (!$result['success']) {
            return $this->error($result['error']['message'] ?? 'Customer lookup failed.', 404, ['tenant' => $tenant]);
        }

        $this->success([
            'success' => true,
            'tenant' => $tenant,
            'customer' => $result['data'],
            'error' => null,
        ], 'Customer lookup completed.');
    }

    public function save_customer() {
        $payload = $this->body();
        if (empty($payload)) {
            $payload = $this->input->get(null, true);
        }

        if (empty($payload['phone']) && empty($payload['customer_phone'])) {
            return $this->error('phone is required.', 400, ['expected' => 'phone|customer_phone']);
        }

        $tenant = sk_mcp_tool_resolve_tenant($payload['tenant'] ?? $payload['tenant_id'] ?? null);
        $result = sk_ai_mcp_execute_tool('save_customer', $payload, ['tenant_id' => (int)$tenant['tenant']]);

        if (!$result['success']) {
            return $this->error($result['error']['message'] ?? 'Customer save failed.', 400, ['tenant' => $tenant]);
        }

        $this->success([
            'success' => true,
            'tenant' => $tenant,
            'customer' => $result['data'],
            'error' => null,
        ], 'Customer saved.');
    }

    public function get_order_status() {
        $payload = $this->body();
        if (empty($payload)) {
            $payload = $this->input->get(null, true);
        }

        $orderId = (int)($payload['order_id'] ?? $payload['id'] ?? 0);
        if ($orderId <= 0) {
            return $this->error('order_id is required.', 400, ['expected' => 'order_id']);
        }

        $tenant = sk_mcp_tool_resolve_tenant($payload['tenant'] ?? $payload['tenant_id'] ?? null);
        $result = sk_ai_mcp_execute_tool('get_order_status', ['order_id' => $orderId], ['tenant_id' => (int)$tenant['tenant']]);

        if (!$result['success']) {
            return $this->error($result['error']['message'] ?? 'Order lookup failed.', 404, ['tenant' => $tenant]);
        }

        $this->success([
            'success' => true,
            'tenant' => $tenant,
            'order' => $result['data'],
            'error' => null,
        ], 'Order status retrieved.');
    }

    public function generate_invoice() {
        $payload = $this->body();
        if (empty($payload)) {
            $payload = $this->input->get(null, true);
        }

        $orderId = (int)($payload['order_id'] ?? $payload['id'] ?? 0);
        if ($orderId <= 0) {
            return $this->error('order_id is required.', 400, ['expected' => 'order_id']);
        }

        $tenant = sk_mcp_tool_resolve_tenant($payload['tenant'] ?? $payload['tenant_id'] ?? null);
        $result = sk_ai_mcp_execute_tool('generate_invoice', ['order_id' => $orderId], ['tenant_id' => (int)$tenant['tenant']]);

        if (!$result['success']) {
            return $this->error($result['error']['message'] ?? 'Invoice generation failed.', 404, ['tenant' => $tenant]);
        }

        $this->success([
            'success' => true,
            'tenant' => $tenant,
            'invoice' => $result['data'],
            'error' => null,
        ], 'Invoice generated.');
    }

    public function get_delivery_status() {
        $payload = $this->body();
        if (empty($payload)) {
            $payload = $this->input->get(null, true);
        }

        $orderId = (int)($payload['order_id'] ?? $payload['id'] ?? 0);
        if ($orderId <= 0) {
            return $this->error('order_id is required.', 400, ['expected' => 'order_id']);
        }

        $tenant = sk_mcp_tool_resolve_tenant($payload['tenant'] ?? $payload['tenant_id'] ?? null);
        $result = sk_ai_mcp_execute_tool('get_delivery_status', ['order_id' => $orderId], ['tenant_id' => (int)$tenant['tenant']]);

        if (!$result['success']) {
            return $this->error($result['error']['message'] ?? 'Delivery status lookup failed.', 404, ['tenant' => $tenant]);
        }

        $this->success([
            'success' => true,
            'tenant' => $tenant,
            'delivery' => $result['data'],
            'error' => null,
        ], 'Delivery status retrieved.');
    }

    public function human_handoff() {
        $payload = $this->body();
        if (empty($payload)) {
            $payload = $this->input->get(null, true);
        }

        $tenant = sk_mcp_tool_resolve_tenant($payload['tenant'] ?? $payload['tenant_id'] ?? null);
        $result = sk_ai_mcp_execute_tool('human_handoff', $payload, ['tenant_id' => (int)$tenant['tenant']]);

        if (!$result['success']) {
            return $this->error($result['error']['message'] ?? 'Unable to hand off to a human agent.', 400, ['tenant' => $tenant]);
        }

        $this->success([
            'success' => true,
            'tenant' => $tenant,
            'handoff' => $result['data'],
            'error' => null,
        ], 'Customer handed off to a human agent.');
    }
}
