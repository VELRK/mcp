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

    // Multi-item Cart Management Tools for Meta Business Agent
    public function get_cart($phoneNumberId = '') {
        $this->_require_connector_auth();
        $tenant = $this->_resolve_tenant((string)$phoneNumberId);
        $vendorId = (int)$tenant['vendor_id'];
        $session = $this->_resolve_cart_session($vendorId);
        $cart = $this->_build_cart_response($session, $vendorId);
        $this->success($cart, 'Cart retrieved.');
    }

    public function add_cart_item($phoneNumberId = '') {
        $this->_require_connector_auth();
        $tenant = $this->_resolve_tenant((string)$phoneNumberId);
        $vendorId = (int)$tenant['vendor_id'];
        $payload = $this->_payload();

        // 1. Quantity validation: must be positive integer to ADD
        if (!isset($payload['quantity']) || !is_numeric($payload['quantity'])) {
            $this->error('quantity is required and must be an integer.', 400);
        }
        $addQty = (int)$payload['quantity'];
        if ($addQty < 1) {
            $this->error('quantity to add must be at least 1 unit.', 400);
        }

        // 2. SKU / product identifier
        $sku = trim((string)($payload['sku'] ?? $payload['product_sku'] ?? $payload['product_id'] ?? ''));
        if ($sku === '') {
            $this->error('sku is required.', 400);
        }

        // 3. Find product / variant in this catalog
        $found = $this->_find_product_by_sku($sku, $vendorId);
        if (!$found) {
            $this->error("Product with SKU '{$sku}' not found in this catalog.", 404, [
                'code' => 'PRODUCT_NOT_FOUND',
                'sku'  => $sku,
            ]);
        }
        $product = $found['product'];
        $variant = $found['variant'];
        $productId = (int)$product['id'];
        $variantId = $variant ? (int)$variant['id'] : null;

        // 4. Live inventory / stock check
        $stock = (int)($variant ? ($variant['stock'] ?? 0) : ($product['stock'] ?? 0));
        if ($stock <= 0) {
            $this->error("'{$found['name']}' is out of stock.", 400, [
                'code'            => 'OUT_OF_STOCK',
                'available_stock' => 0,
                'name'            => $found['name'],
                'sku'             => $sku,
            ]);
        }

        // 5. Read current cart state before write
        $session = $this->_resolve_cart_session($vendorId);
        $existingRow = $this->_find_cart_line($session, $vendorId, $productId, $variantId);
        $currentLineQty = (int)($existingRow['quantity'] ?? 0);
        $targetLineQty = $currentLineQty + $addQty;

        $maxPerLine = $this->_get_max_per_line($vendorId, $product);
        $effectiveLimit = min($maxPerLine, $stock);

        // Per-line limit check & headroom calculation
        if ($targetLineQty > $effectiveLimit) {
            $headroom = max(0, $effectiveLimit - $currentLineQty);
            $msg = "I can't go that high — there's a limit of {$effectiveLimit} per order and you have {$currentLineQty}."
                . ($headroom > 0 ? " Shall I take you up to {$effectiveLimit}?" : "");
            $this->error($msg, 400, [
                'code'               => 'LIMIT_EXCEEDED',
                'limit_type'         => 'per_line',
                'limit'              => $effectiveLimit,
                'current_quantity'   => $currentLineQty,
                'requested_quantity' => $addQty,
                'headroom'           => $headroom,
                'available_stock'    => $stock,
            ]);
        }

        // Basket total limit check & headroom calculation
        $maxBasket = $this->_get_max_basket_items($vendorId);
        $currentBasketTotal = $this->_get_cart_item_count($session, $vendorId);
        if (($currentBasketTotal + $addQty) > $maxBasket) {
            $headroom = max(0, $maxBasket - $currentBasketTotal);
            $msg = "Basket limit of {$maxBasket} items reached. You currently have {$currentBasketTotal} items."
                . ($headroom > 0 ? " You can add at most {$headroom} more item(s)." : "");
            $this->error($msg, 400, [
                'code'               => 'LIMIT_EXCEEDED',
                'limit_type'         => 'basket_total',
                'limit'              => $maxBasket,
                'current_quantity'   => $currentBasketTotal,
                'requested_quantity' => $addQty,
                'headroom'           => $headroom,
            ]);
        }

        // 6. All-or-nothing write
        $now = date('Y-m-d H:i:s');
        if ($existingRow) {
            $this->db->where('id', (int)$existingRow['id'])->update('cart', [
                'quantity'   => $targetLineQty,
                'updated_at' => $now,
            ]);
        } else {
            $this->db->insert('cart', [
                'user_id'    => $session['user_id'],
                'session_id' => $session['session_id'],
                'vendor_id'  => $vendorId,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity'   => $targetLineQty,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->_touch_cart_session($session, $vendorId);

        // 7. Read cart back fresh and report
        $cart = $this->_build_cart_response($session, $vendorId);
        $this->success($cart, 'Item added to basket.');
    }

    public function update_cart_item($phoneNumberId = '', $lineId = '') {
        $this->_require_connector_auth();
        $tenant = $this->_resolve_tenant((string)$phoneNumberId);
        $vendorId = (int)$tenant['vendor_id'];
        $payload = $this->_payload();

        // 1. Resolve line_id from path parameter or body
        $lineId = trim((string)($lineId !== '' ? $lineId : ($payload['line_id'] ?? $this->input->get('line_id') ?? '')));
        if ($lineId === '') {
            $this->error('line_id is required.', 400);
        }

        // 2. Quantity validation
        if (!isset($payload['quantity']) || !is_numeric($payload['quantity'])) {
            $this->error('quantity is required and must be an integer.', 400);
        }
        $targetQty = (int)$payload['quantity'];

        // Reject zero quantity as per Meta documentation:
        // "Setting a quantity of zero is rejected; use remove_cart_item instead."
        if ($targetQty <= 0) {
            $this->error('Setting a quantity of zero is rejected; use remove_cart_item instead.', 400, [
                'code' => 'ZERO_QUANTITY_REJECTED',
            ]);
        }

        // 3. Read current cart state and find line
        $session = $this->_resolve_cart_session($vendorId);
        $line = $this->_get_cart_line_by_id($lineId, $session, $vendorId);
        if (!$line) {
            $this->error("Cart line '{$lineId}' not found. Call get_cart immediately before and take line_id from that response.", 404, [
                'code'    => 'LINE_NOT_FOUND',
                'line_id' => $lineId,
            ]);
        }

        $currentLineQty = (int)$line['quantity'];
        $productId = (int)$line['product_id'];
        $variantId = !empty($line['variant_id']) ? (int)$line['variant_id'] : null;

        $product = $this->Sk_Product_model->get_by_id($productId);
        if (!$product) {
            $this->error('Product no longer available.', 404);
        }
        $stock = (int)$product['stock'];
        if ($variantId && !empty($product['variants'])) {
            foreach ($product['variants'] as $v) {
                if ((int)$v['id'] === $variantId) {
                    $stock = (int)($v['stock'] ?? 0);
                    break;
                }
            }
        }

        $maxPerLine = $this->_get_max_per_line($vendorId, $product);
        $effectiveLimit = min($maxPerLine, $stock);

        // Per-line limit check & headroom
        if ($targetQty > $effectiveLimit) {
            $headroom = max(0, $effectiveLimit - $currentLineQty);
            $msg = "I can't go that high — there's a limit of {$effectiveLimit} per order and you have {$currentLineQty}."
                . ($headroom > 0 ? " Shall I take you up to {$effectiveLimit}?" : "");
            $this->error($msg, 400, [
                'code'               => 'LIMIT_EXCEEDED',
                'limit_type'         => 'per_line',
                'limit'              => $effectiveLimit,
                'current_quantity'   => $currentLineQty,
                'requested_quantity' => $targetQty,
                'headroom'           => $headroom,
                'available_stock'    => $stock,
            ]);
        }

        // Basket total limit check & headroom
        $maxBasket = $this->_get_max_basket_items($vendorId);
        $currentBasketTotal = $this->_get_cart_item_count($session, $vendorId);
        $diff = $targetQty - $currentLineQty;
        if (($currentBasketTotal + $diff) > $maxBasket) {
            $headroom = max(0, $maxBasket - $currentBasketTotal);
            $msg = "Basket limit of {$maxBasket} items exceeded. You currently have {$currentBasketTotal} items."
                . ($headroom > 0 ? " You can add at most {$headroom} more item(s)." : "");
            $this->error($msg, 400, [
                'code'               => 'LIMIT_EXCEEDED',
                'limit_type'         => 'basket_total',
                'limit'              => $maxBasket,
                'current_quantity'   => $currentBasketTotal,
                'requested_quantity' => $targetQty,
                'headroom'           => $headroom,
            ]);
        }

        // 4. Update the line
        $this->db->where('id', (int)$line['id'])->update('cart', [
            'quantity'   => $targetQty,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->_touch_cart_session($session, $vendorId);

        // 5. Read cart back fresh and report
        $cart = $this->_build_cart_response($session, $vendorId);
        $this->success($cart, 'Cart line updated.');
    }

    public function remove_cart_item($phoneNumberId = '', $lineId = '') {
        $this->_require_connector_auth();
        $tenant = $this->_resolve_tenant((string)$phoneNumberId);
        $vendorId = (int)$tenant['vendor_id'];
        $payload = $this->_payload();

        $lineId = trim((string)($lineId !== '' ? $lineId : ($payload['line_id'] ?? $this->input->get('line_id') ?? '')));
        if ($lineId === '') {
            $this->error('line_id is required.', 400);
        }

        $session = $this->_resolve_cart_session($vendorId);
        $line = $this->_get_cart_line_by_id($lineId, $session, $vendorId);
        if (!$line) {
            $this->error("Cart line '{$lineId}' not found. Call get_cart immediately before and take line_id from that response.", 404, [
                'code'    => 'LINE_NOT_FOUND',
                'line_id' => $lineId,
            ]);
        }

        // Delete line
        $this->db->where('id', (int)$line['id'])->delete('cart');

        $this->_touch_cart_session($session, $vendorId);

        // Read cart back fresh and report
        $cart = $this->_build_cart_response($session, $vendorId);
        $this->success($cart, 'Cart item removed.');
    }

    public function apply_discount_code($phoneNumberId = '') {
        $this->_require_connector_auth();
        $tenant = $this->_resolve_tenant((string)$phoneNumberId);
        $vendorId = (int)$tenant['vendor_id'];
        $payload = $this->_payload();

        // Must preserve code character for character as the shopper typed it
        $rawCode = (string)($payload['code'] ?? $payload['discount_code'] ?? $this->input->get('code') ?? '');
        if ($rawCode === '') {
            $this->error('code is required.', 400);
        }

        $session = $this->_resolve_cart_session($vendorId);

        // Read basket subtotal
        $cart = $this->_build_cart_response($session, $vendorId, false);
        $subtotal = (float)($cart['subtotal'] ?? 0);
        if ($subtotal <= 0 || empty($cart['lines'])) {
            $this->error("Your basket is empty. Add items before applying discount code '{$rawCode}'.", 400, [
                'code'          => 'EMPTY_BASKET',
                'discount_code' => $rawCode,
            ]);
        }

        // Validate promo code against promo_codes table
        $promo = $this->db->where('code', strtoupper(trim($rawCode)))
            ->get('promo_codes')
            ->row_array();

        if (!$promo) {
            $this->error("Discount code '{$rawCode}' is not valid.", 400, [
                'code'          => 'INVALID_CODE',
                'discount_code' => $rawCode,
            ]);
        }

        if (empty($promo['status'])) {
            $this->error("Discount code '{$rawCode}' is not active.", 400, [
                'code'          => 'CODE_INACTIVE',
                'discount_code' => $rawCode,
            ]);
        }

        $today = date('Y-m-d');
        if (!empty($promo['starts_at']) && $promo['starts_at'] > $today) {
            $this->error("Discount code '{$rawCode}' is not active yet.", 400, [
                'code'          => 'CODE_NOT_STARTED',
                'discount_code' => $rawCode,
            ]);
        }

        if (!empty($promo['expires_at']) && $promo['expires_at'] < $today) {
            $this->error("Discount code '{$rawCode}' has expired.", 400, [
                'code'          => 'CODE_EXPIRED',
                'discount_code' => $rawCode,
            ]);
        }

        if (($promo['scope'] ?? 'vendor') === 'vendor' && !empty($promo['vendor_id']) && (int)$promo['vendor_id'] !== $vendorId) {
            $this->error("Discount code '{$rawCode}' is not valid for this shop.", 400, [
                'code'          => 'VENDOR_MISMATCH',
                'discount_code' => $rawCode,
            ]);
        }

        if (!empty($promo['min_order']) && $subtotal < (float)$promo['min_order']) {
            $minReq = number_format((float)$promo['min_order'], 2);
            $this->error("Minimum order of ₹{$minReq} required to use code '{$rawCode}'.", 400, [
                'code'          => 'MIN_ORDER_NOT_MET',
                'discount_code' => $rawCode,
                'min_order'     => (float)$promo['min_order'],
                'current_total' => $subtotal,
            ]);
        }

        if (!empty($promo['usage_limit']) && (int)$promo['usage_count'] >= (int)$promo['usage_limit']) {
            $this->error("Usage limit reached for discount code '{$rawCode}'.", 400, [
                'code'          => 'USAGE_LIMIT_REACHED',
                'discount_code' => $rawCode,
            ]);
        }

        // Calculate discount
        $discount = ($promo['type'] === 'percent')
            ? round($subtotal * (float)$promo['value'] / 100, 2)
            : (float)$promo['value'];
        if (!empty($promo['max_discount']) && (float)$promo['max_discount'] > 0 && $discount > (float)$promo['max_discount']) {
            $discount = (float)$promo['max_discount'];
        }
        $discount = min($discount, $subtotal);

        // Save into cart_sessions
        $this->_set_cart_session_discount($session, $vendorId, $rawCode, $discount);

        // Read cart back fresh and report
        $cart = $this->_build_cart_response($session, $vendorId);
        $this->success($cart, "Discount code '{$rawCode}' applied successfully.");
    }

    public function create_order($phoneNumberId = '') {
        $this->_require_connector_auth();
        $tenant = $this->_resolve_tenant((string)$phoneNumberId);
        $vendorId = (int)$tenant['vendor_id'];
        $payload = $this->_payload();
        $payload['phone_number_id'] = $tenant['phone_number_id'];

        $session = $this->_resolve_cart_session($vendorId);
        if (empty($payload['session_id'])) {
            $payload['session_id'] = $session['session_id'];
        }

        // If items are omitted or empty, auto-populate from conversational cart!
        $itemsIn = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        if (is_string($payload['items'] ?? null)) {
            $itemsIn = json_decode((string)$payload['items'], true) ?: [];
        }
        if (empty($itemsIn)) {
            $cart = $this->_build_cart_response($session, $vendorId);
            if (!empty($cart['lines'])) {
                $cartItems = [];
                foreach ($cart['lines'] as $line) {
                    $cartItems[] = [
                        'product_id'    => (int)$line['product_id'],
                        'variant_id'    => !empty($line['variant_id']) ? (int)$line['variant_id'] : null,
                        'variant_label' => $line['variant_label'] ?? null,
                        'quantity'      => (int)$line['quantity'],
                    ];
                }
                $payload['items'] = $cartItems;
            }
        }

        $result = sk_ai_mcp_execute_tool('create_order', $payload, [
            'tenant_id' => $vendorId,
            'tenant'    => $vendorId,
        ]);

        // When order is successfully created, clear the conversational cart for this session
        if (!empty($result['success'])) {
            if (!empty($session['user_id'])) {
                $this->db->where('vendor_id', $vendorId)
                    ->group_start()
                    ->where('session_id', $session['session_id'])
                    ->or_where('user_id', (int)$session['user_id'])
                    ->group_end()
                    ->delete('cart');
            } else {
                $this->db->where('vendor_id', $vendorId)
                    ->where('session_id', $session['session_id'])
                    ->delete('cart');
            }
            $this->db->where('vendor_id', $vendorId)
                ->where('session_id', $session['session_id'])
                ->delete('cart_sessions');
        }

        $this->_slim_success($result, 'create_order completed.');
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
        $vendorId = (int)$tenant['vendor_id'];
        $galleryAsk = function_exists('sk_mcp_tool_is_gallery_request') && sk_mcp_tool_is_gallery_request($query);
        $searchQuery = $query;
        if ($galleryAsk && function_exists('sk_mcp_tool_strip_gallery_words')) {
            $stripped = sk_mcp_tool_strip_gallery_words($query);
            if ($stripped === '') {
                $this->success([
                    'query'    => $query,
                    'count'    => 0,
                    'ui'       => 'none',
                    'summary'  => 'The customer asked for more photos but did not name a product. Ask which product. Do not invent images.',
                    'cards'    => [],
                    'gallery_cards' => [],
                    'products' => [],
                ], 'Product search completed.');
            }
            $searchQuery = $stripped;
        }
        $result = sk_mcp_tool_find_products($searchQuery, ['tenant' => $vendorId], 10);
        $products = $this->_meta_safe_products($result['results'] ?? [], $vendorId);
        if ($galleryAsk && $products) {
            $this->_send_gallery($query, $products[0]);
            return;
        }
        $footer = sk_mcp_tool_delivery_footer($vendorId);
        $cards = [];
        $galleryCards = [];
        foreach ($products as $pIndex => $p) {
            $cards[] = $this->_shop_card($p, $footer);
            unset($products[$pIndex]['product_url']);
        }
        $count = count($cards);
        $photoCards = [];
        foreach ($cards as $card) {
            if ((string)$card['image_url'] !== '') {
                $photoCards[] = $card;
            }
        }
        $photoCount = count($photoCards);
        $ui = 'none';
        if ($count === 1) {
            $galleryCards = $this->_gallery_cards($products[0], $footer);
            $ui = $photoCount === 1 ? 'product-photo' : 'none';
            $summary = 'Send the branded product card image from image_url. The card visually displays the photo, title, price, available sizes, stepper, and Add to Cart. In text, reply in a friendly tone (e.g. "Iruku bro 😊 Here are some options for you:") and ask how many units or which size they want. When they confirm adding it, call get_cart then add_cart_item.';
        } elseif ($count > 1) {
            $ui = 'product-photo';
            $summary = 'Product list. Send the branded product card image from image_url for each product (up to 5). Each card visually displays the photo, title, price, and sizes. In text, reply friendly and ask which item and how many they want to add to their basket.';
        } else {
            $summary = 'No matching products in this shop.';
        }
        $products = array_values($products);
        $sheet = $count === 1 ? ($cards[0] ?? []) : [];
        $this->success([
            'query'    => $query,
            'count'    => $count,
            'ui'       => $ui,
            'summary'  => $summary,
            'detail'   => (string)($sheet['detail'] ?? ''),
            'footer'   => (string)($sheet['footer'] ?? $footer),
            'cards'    => $cards,
            'gallery_cards' => $galleryCards,
            'products' => $products,
        ], 'Product search completed.');
    }

    /** Short lines for the photo: name, code and stock, price. */
    private function _shop_card_body(array $product): string {
        $name = trim((string)($product['name'] ?? ''));
        if ($name === '') {
            $name = 'Product';
        }
        if (function_exists('mb_substr')) {
            $name = mb_substr($name, 0, 80);
        }
        return $name . "\n" . sk_mcp_tool_inr((float)($product['price'] ?? 0));
    }

    /** Full product sheet under the photo. */
    private function _shop_detail_body(array $product): string {
        $lines = [$this->_shop_card_body($product)];
        $description = trim((string)($product['description'] ?? ''));
        if ($description !== '') {
            $lines[] = '';
            $lines[] = $description;
        }
        $specs = trim((string)($product['specs'] ?? ''));
        if ($specs !== '') {
            $lines[] = $specs;
        }
        $text = implode("\n", $lines);
        if (strlen($text) > 1000) {
            $text = substr($text, 0, 997) . '...';
        }
        return $text;
    }

    /** @return array<string, mixed> */
    private function _shop_card(array $product, string $footer, string $button = ''): array {
        $name = trim((string)($product['name'] ?? ''));
        if ($name === '') {
            $name = 'Product';
        }
        $cardImage = function_exists('sk_meta_generate_product_card_image')
            ? sk_meta_generate_product_card_image($product)
            : '';
        return [
            'name'          => $name,
            'body'          => $this->_shop_card_body($product),
            'detail'        => $this->_shop_detail_body($product),
            'footer'        => $footer,
            'image_url'     => $cardImage ?: (string)($product['image_url'] ?? ''),
            'raw_image_url' => (string)($product['image_url'] ?? ''),
            'card_image_url'=> $cardImage,
            'button_label'  => $button ?: 'Add to Cart',
            'needs_variant' => count($product['variants'] ?? []) > 0,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function _gallery_cards(array $product, string $footer = ''): array {
        $card = $this->_shop_card($product, $footer);
        $cards = [];
        foreach ((array)($product['gallery'] ?? []) as $url) {
            $url = trim((string)$url);
            if ($url === '') {
                continue;
            }
            $card['image_url'] = $url;
            $cards[] = $card;
            if (count($cards) >= 10) {
                break;
            }
        }
        return $cards;
    }

    /** Extra photos of one product. Cards are the same product, not a new catalog. */
    private function _send_gallery(string $query, array $product): void {
        $footer = sk_mcp_tool_delivery_footer((int)($product['vendor_id'] ?? 0));
        $sheet = $this->_shop_card($product, $footer);
        $cards = $this->_gallery_cards($product, $footer);
        $count = count($cards);
        $variantNote = count($product['variants'] ?? []) > 0
            ? ' If they want it, ask which variant in text.'
            : '';
        if ($count >= 1) {
            $ui = 'product-photo';
            $summary = 'Send each photo as its own image. Caption is the name and the price. No carousel, no buttons, and no detail card.' . $variantNote;
        } else {
            $ui = 'none';
            $sheet['image_url'] = '';
            $cards = [$sheet];
            $summary = 'No extra photos. Say that in one sentence. Do not send an empty card.' . $variantNote;
        }
        unset($product['product_url']);
        $this->success([
            'query'         => $query,
            'count'         => 1,
            'ui'            => $ui,
            'summary'       => $summary,
            'detail'        => (string)$sheet['detail'],
            'footer'        => $footer,
            'cards'         => $cards,
            'gallery_cards' => $cards,
            'products'      => [$product],
        ], 'Product gallery completed.');
    }

    private function _meta_safe_products(array $rows, int $vendorId = 0): array {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $rowVendor = (int)($row['vendor_id'] ?? 0);
            // A blank vendor_id is not this shop. Only rows owned by this vendor may be sent.
            if ($vendorId > 0 && $rowVendor !== $vendorId) {
                continue;
            }
            if (strtolower(trim((string)($row['status'] ?? 'active'))) !== 'active') {
                continue;
            }
            $gallery = [];
            $rawGallery = is_array($row['gallery'] ?? null) ? $row['gallery'] : [];
            if (!$rawGallery) {
                $fallback = trim((string)($row['image_url'] ?? $row['thumbnail'] ?? $row['image'] ?? ''));
                if ($fallback !== '') {
                    $rawGallery = [$fallback];
                }
            }
            foreach ($rawGallery as $path) {
                $url = sk_mcp_tool_whatsapp_image_url((string)$path);
                if ($url !== '' && !in_array($url, $gallery, true)) {
                    $gallery[] = $url;
                }
                if (count($gallery) >= 10) {
                    break;
                }
            }
            $image = $gallery[0] ?? '';
            $productUrl = site_url('shop/product/' . $id);
            if (stripos($productUrl, 'http://') === 0) {
                $productUrl = 'https://' . substr($productUrl, 7);
            }
            $variants = sk_mcp_tool_variant_choices(is_array($row['variants'] ?? null) ? $row['variants'] : []);
            $out[] = [
                'id'               => $id,
                'vendor_id'        => $rowVendor,
                'name'             => (string)($row['name'] ?? ''),
                'sku'              => (string)($row['sku'] ?? ''),
                'image_url'        => $image,
                'gallery'          => $gallery,
                'variants'         => $variants,
                'product_url'      => $productUrl,
                'color'            => (string)($row['color'] ?? ''),
                'length'           => (string)($row['length'] ?? $row['saree_length'] ?? ''),
                'blouse_included'  => !empty($row['blouse_included']),
                'pack_of'          => (string)($row['pack_of'] ?? ''),
                'available'        => !empty($row['available']) || ((int)($row['stock'] ?? 0)) > 0,
                'stock'            => (int)($row['stock'] ?? 0),
                'price'            => (float)($row['price'] ?? 0),
                'mrp'              => (float)($row['mrp'] ?? 0),
                'description'      => (string)($row['description'] ?? ''),
                'specs'            => (string)($row['specs'] ?? ''),
                'currency'         => (string)($row['currency'] ?? 'INR'),
            ];
        }
        return $out;
    }

    // ── Cart Session & Persistence Helpers ─────────────────────────────

    /**
     * Resolve cart session context from request:
     * - Authorization: Bearer <jwt_or_session>
     * - X-Session-ID header
     * - session_id parameter/query
     * - phone / customer_phone parameter
     * @return array{session_id: string, user_id: ?int, phone: ?string}
     */
    private function _resolve_cart_session(int $vendorId): array {
        $sessionId = '';
        $userId = null;
        $phone = null;

        // 1. Check user token / JWT
        $jwtPayload = $this->sk_jwt->get_payload_from_request();
        if ($jwtPayload && !empty($jwtPayload['user_id'])) {
            $userId = (int)$jwtPayload['user_id'];
            $sessionId = 'usr_' . $userId;
        }

        // 2. Check X-Session-ID header
        if ($sessionId === '') {
            $hdrSession = trim((string)$this->input->get_request_header('X-Session-ID', true));
            if ($hdrSession !== '') {
                $sessionId = $hdrSession;
            }
        }

        // 3. Check Authorization Bearer if not a valid JWT (might be raw session token)
        if ($sessionId === '') {
            $auth = (string)$this->input->get_request_header('Authorization', true);
            if (stripos($auth, 'Bearer ') === 0) {
                $rawToken = trim(substr($auth, 7));
                $cfg = sk_meta_ba_config_array();
                if ($rawToken !== '' && $rawToken !== (string)$cfg['connector_api_key']) {
                    $sessionId = $rawToken;
                }
            }
        }

        // 4. Check payload/query session_id
        $payload = $this->_payload();
        if ($sessionId === '') {
            $bodySession = trim((string)($payload['session_id'] ?? $this->input->get('session_id') ?? ''));
            if ($bodySession !== '') {
                $sessionId = $bodySession;
            }
        }

        // 5. Check phone if available
        $rawPhone = trim((string)($payload['phone'] ?? $payload['customer_phone'] ?? $this->input->get('phone') ?? ''));
        if ($rawPhone !== '') {
            $phone = preg_replace('/\D+/', '', $rawPhone);
            if ($sessionId === '' && $phone !== '') {
                $sessionId = 'wa_' . $phone;
            }
        }

        // 6. Fallback if still empty: derive a stable default guest session
        if ($sessionId === '') {
            $ip = $this->input->ip_address();
            $sessionId = 'sess_ba_' . md5($ip . '_' . $vendorId);
        }

        // Link user_id if customer exists with matching phone
        if (!$userId && $phone) {
            $cust = $this->db->where('phone', $phone)->get('users')->row_array();
            if ($cust && !empty($cust['id'])) {
                $userId = (int)$cust['id'];
            }
        }

        return [
            'session_id' => $sessionId,
            'user_id'    => $userId,
            'phone'      => $phone,
        ];
    }

    private function _find_product_by_sku(string $sku, int $vendorId): ?array {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        // 1. Check product_variants by sku
        $variant = $this->db->where('sku', $sku)
            ->where('status', 1)
            ->get('product_variants')
            ->row_array();
        if ($variant && !empty($variant['product_id'])) {
            $prod = $this->Sk_Product_model->get_by_id((int)$variant['product_id']);
            if ($prod && sk_ai_product_is_active($prod)) {
                if ($vendorId <= 0 || (int)($prod['vendor_id'] ?? 0) === $vendorId) {
                    return [
                        'product' => $prod,
                        'variant' => $variant,
                        'name'    => (string)$prod['name'] . ' (' . (string)($variant['label'] ?? '') . ')',
                        'sku'     => $sku,
                    ];
                }
            }
        }

        // 2. Check products by sku or ID
        $prod = $this->db->where('sku', $sku)->get('products')->row_array();
        if (!$prod && is_numeric($sku)) {
            $prod = $this->db->where('id', (int)$sku)->get('products')->row_array();
        }
        if ($prod && sk_ai_product_is_active($prod)) {
            if ($vendorId <= 0 || (int)($prod['vendor_id'] ?? 0) === $vendorId) {
                $fullProd = $this->Sk_Product_model->get_by_id((int)$prod['id']);
                $variant = !empty($fullProd['variants']) ? $fullProd['variants'][0] : null;
                return [
                    'product' => $fullProd ?: $prod,
                    'variant' => $variant,
                    'name'    => (string)$prod['name'],
                    'sku'     => (string)($prod['sku'] ?? $sku),
                ];
            }
        }

        return null;
    }

    private function _get_cart_line_by_id(string $lineId, array $session, int $vendorId): ?array {
        $id = (int)$lineId;
        if ($id <= 0) {
            return null;
        }
        $this->db->where('id', $id);
        if ($vendorId > 0) {
            $this->db->where('vendor_id', $vendorId);
        }
        if (!empty($session['user_id'])) {
            $this->db->group_start()
                ->where('session_id', $session['session_id'])
                ->or_where('user_id', (int)$session['user_id'])
                ->group_end();
        } else {
            $this->db->where('session_id', $session['session_id']);
        }
        return $this->db->get('cart')->row_array() ?: null;
    }

    private function _find_cart_line(array $session, int $vendorId, int $productId, ?int $variantId): ?array {
        $this->db->where('product_id', $productId);
        if ($vendorId > 0) {
            $this->db->where('vendor_id', $vendorId);
        }
        if ($variantId) {
            $this->db->where('variant_id', $variantId);
        } else {
            $this->db->where('(variant_id IS NULL OR variant_id = 0)', null, false);
        }
        if (!empty($session['user_id'])) {
            $this->db->group_start()
                ->where('session_id', $session['session_id'])
                ->or_where('user_id', (int)$session['user_id'])
                ->group_end();
        } else {
            $this->db->where('session_id', $session['session_id']);
        }
        return $this->db->get('cart')->row_array() ?: null;
    }

    private function _get_cart_item_count(array $session, int $vendorId): int {
        $this->db->select_sum('quantity', 'total_qty');
        if ($vendorId > 0) {
            $this->db->where('vendor_id', $vendorId);
        }
        if (!empty($session['user_id'])) {
            $this->db->group_start()
                ->where('session_id', $session['session_id'])
                ->or_where('user_id', (int)$session['user_id'])
                ->group_end();
        } else {
            $this->db->where('session_id', $session['session_id']);
        }
        $row = $this->db->get('cart')->row_array();
        return (int)($row['total_qty'] ?? 0);
    }

    private function _touch_cart_session(array $session, int $vendorId): void {
        $now = date('Y-m-d H:i:s');
        $row = $this->db->where('session_id', $session['session_id'])
            ->where('vendor_id', $vendorId)
            ->get('cart_sessions')
            ->row_array();
        if ($row) {
            $upd = ['updated_at' => $now];
            if (!empty($session['user_id']) && empty($row['user_id'])) {
                $upd['user_id'] = $session['user_id'];
            }
            if (!empty($session['phone']) && empty($row['customer_phone'])) {
                $upd['customer_phone'] = $session['phone'];
            }
            $this->db->where('id', (int)$row['id'])->update('cart_sessions', $upd);
        } else {
            $this->db->insert('cart_sessions', [
                'session_id'      => $session['session_id'],
                'vendor_id'       => $vendorId,
                'user_id'         => $session['user_id'],
                'customer_phone'  => $session['phone'],
                'discount_code'   => null,
                'discount_amount' => 0.00,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }
    }

    private function _set_cart_session_discount(array $session, int $vendorId, string $code, float $discount): void {
        $now = date('Y-m-d H:i:s');
        $row = $this->db->where('session_id', $session['session_id'])
            ->where('vendor_id', $vendorId)
            ->get('cart_sessions')
            ->row_array();
        if ($row) {
            $this->db->where('id', (int)$row['id'])->update('cart_sessions', [
                'discount_code'   => $code,
                'discount_amount' => round($discount, 2),
                'updated_at'      => $now,
            ]);
        } else {
            $this->db->insert('cart_sessions', [
                'session_id'      => $session['session_id'],
                'vendor_id'       => $vendorId,
                'user_id'         => $session['user_id'],
                'customer_phone'  => $session['phone'],
                'discount_code'   => $code,
                'discount_amount' => round($discount, 2),
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }
    }

    private function _get_max_per_line(int $vendorId, ?array $product = null): int {
        $settings = $this->get_settings();
        $limit = (int)($settings['cart_max_per_line'] ?? 12);
        if ($limit < 1) {
            $limit = 12;
        }
        if ($product && !empty($product['max_order_qty']) && (int)$product['max_order_qty'] > 0) {
            $limit = min($limit, (int)$product['max_order_qty']);
        }
        return $limit;
    }

    private function _get_max_basket_items(int $vendorId): int {
        $settings = $this->get_settings();
        $limit = (int)($settings['cart_max_basket_items'] ?? 50);
        return $limit > 0 ? $limit : 50;
    }

    private function _build_cart_response(array $session, int $vendorId, bool $applyDiscount = true): array {
        $this->db->where('vendor_id', $vendorId);
        if (!empty($session['user_id'])) {
            $this->db->group_start()
                ->where('session_id', $session['session_id'])
                ->or_where('user_id', (int)$session['user_id'])
                ->group_end();
        } else {
            $this->db->where('session_id', $session['session_id']);
        }
        $rows = $this->db->order_by('id', 'ASC')->get('cart')->result_array();

        $lines = [];
        $subtotal = 0.0;
        $itemCount = 0;

        foreach ($rows as $row) {
            $prod = $this->Sk_Product_model->get_by_id((int)$row['product_id']);
            if (!$prod) {
                continue;
            }
            $variant = null;
            if (!empty($row['variant_id']) && !empty($prod['variants'])) {
                foreach ($prod['variants'] as $v) {
                    if ((int)$v['id'] === (int)$row['variant_id']) {
                        $variant = $v;
                        break;
                    }
                }
            }
            $productName = (string)$prod['name'];
            if ($variant && !empty($variant['label'])) {
                $productName .= ' (' . $variant['label'] . ')';
            }
            $sku = trim((string)($variant['sku'] ?? ''));
            if ($sku === '') {
                $sku = trim((string)($prod['sku'] ?? ''));
            }
            if ($sku === '') {
                $sku = 'SKU-' . $prod['id'];
            }

            $price = $variant ? (float)($variant['price'] ?? 0) : (float)($prod['price'] ?? 0);
            $sale  = $variant ? ($variant['sale_price'] ?? null) : ($prod['sale_price'] ?? null);
            $effective = ($sale !== null && (float)$sale > 0 && (float)$sale < $price) ? (float)$sale : $price;
            $quantity = (int)$row['quantity'];
            $lineTotal = round($effective * $quantity, 2);
            $stock = (int)($variant ? ($variant['stock'] ?? 0) : ($prod['stock'] ?? 0));

            $image = '';
            if ($variant && !empty($variant['image'])) {
                $image = sk_mcp_tool_whatsapp_image_url((string)$variant['image']);
            }
            if ($image === '' && !empty($prod['thumbnail'])) {
                $image = sk_mcp_tool_whatsapp_image_url((string)$prod['thumbnail']);
            }

            $lines[] = [
                'line_id'      => (string)$row['id'],
                'product_id'   => (int)$prod['id'],
                'product_name' => $productName,
                'name'         => $productName,
                'sku'          => $sku,
                'unit_price'   => $effective,
                'price'        => $effective,
                'quantity'     => $quantity,
                'line_total'   => $lineTotal,
                'subtotal'     => $lineTotal,
                'stock'        => $stock,
                'available'    => $stock >= $quantity,
                'variant_id'   => $variant ? (int)$variant['id'] : null,
                'variant_label'=> $variant['label'] ?? null,
                'image_url'    => $image,
            ];

            $subtotal += $lineTotal;
            $itemCount += $quantity;
        }

        $discount = 0.0;
        $discountCode = null;

        if ($applyDiscount && $subtotal > 0) {
            $sessRow = $this->db->where('session_id', $session['session_id'])
                ->where('vendor_id', $vendorId)
                ->get('cart_sessions')
                ->row_array();
            if ($sessRow && !empty($sessRow['discount_code'])) {
                $rawCode = (string)$sessRow['discount_code'];
                $promo = $this->db->where('code', strtoupper(trim($rawCode)))
                    ->where('status', 1)
                    ->get('promo_codes')
                    ->row_array();
                $today = date('Y-m-d');
                if ($promo
                    && (empty($promo['starts_at']) || $promo['starts_at'] <= $today)
                    && (empty($promo['expires_at']) || $promo['expires_at'] >= $today)
                    && (empty($promo['min_order']) || $subtotal >= (float)$promo['min_order'])
                    && (($promo['scope'] ?? 'vendor') !== 'vendor' || empty($promo['vendor_id']) || (int)$promo['vendor_id'] === $vendorId)
                ) {
                    $discountCode = $rawCode;
                    $discount = ($promo['type'] === 'percent')
                        ? round($subtotal * (float)$promo['value'] / 100, 2)
                        : (float)$promo['value'];
                    if (!empty($promo['max_discount']) && (float)$promo['max_discount'] > 0 && $discount > (float)$promo['max_discount']) {
                        $discount = (float)$promo['max_discount'];
                    }
                    $discount = min($discount, $subtotal);
                } else {
                    $this->db->where('id', (int)$sessRow['id'])->update('cart_sessions', [
                        'discount_amount' => 0.00,
                    ]);
                }
            }
        }

        $total = round(max(0, $subtotal - $discount), 2);
        $maxPerLine = $this->_get_max_per_line($vendorId);
        $maxBasket = $this->_get_max_basket_items($vendorId);

        return [
            'lines'           => $lines,
            'items'           => $lines,
            'item_count'      => $itemCount,
            'subtotal'        => round($subtotal, 2),
            'discount'        => round($discount, 2),
            'discount_code'   => $discountCode,
            'total'           => $total,
            'currency'        => 'INR',
            'currency_symbol' => '₹',
            'limits'          => [
                'max_per_line'     => $maxPerLine,
                'max_basket_items' => $maxBasket,
            ],
            'notes'           => 'Delivery is added at checkout.',
        ];
    }
}
