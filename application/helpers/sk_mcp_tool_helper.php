<?php
defined('BASEPATH') OR exit('No direct script access allowed');

function sk_mcp_tool_resolve_tenant($tenant = null): array {
    $CI =& get_instance();
    if (!isset($CI->Sk_Admin_model)) {
        $CI->load->model('Sk_Admin_model');
    }
    $settings = $CI->Sk_Admin_model->get_settings();

    $defaultTenant = (int)($settings['saas_default_vendor_id'] ?? $settings['tenant_id'] ?? $settings['vendor_id'] ?? 1);
    $raw = trim((string)($tenant ?? $_SERVER['HTTP_X_TENANT'] ?? $_SERVER['HTTP_X_STORE'] ?? ''));
    $resolved = $raw !== '' ? $raw : (string)$defaultTenant;
    $tenantId = (int)preg_replace('/[^0-9]/', '', $resolved);
    if ($tenantId <= 0) {
        $tenantId = $defaultTenant;
    }

    return [
        'tenant' => (string)$tenantId,
        'tenant_id' => $tenantId,
        'store' => (string)$tenantId,
        'resolved' => (string)$tenantId,
        'is_default' => $tenantId === $defaultTenant,
        'source' => $tenant !== null ? 'payload' : 'trusted_settings',
    ];
}

function sk_mcp_tool_parse_query(?string $query): array {
    $raw = trim((string)$query);
    if ($raw === '') {
        return [
            'query' => '',
            'terms' => [],
            'product_name' => '',
            'color' => '',
            'size' => '',
            'intent' => 'search',
        ];
    }

    $normalized = preg_replace('/[?!.]+$/u', '', $raw);
    $normalized = trim((string)$normalized);
    $lower = mb_strtolower($normalized, 'UTF-8');

    $tokens = preg_split('/[\s\-_/,:;()\[\]{}]+/u', $lower, -1, PREG_SPLIT_NO_EMPTY);
    if ($tokens === false || empty($tokens)) {
        $tokens = [$lower];
    }

    $sizeAliases = [
        'xxs' => 'XXS', 'xs' => 'XS', 's' => 'S', 'm' => 'M', 'l' => 'L', 'xl' => 'XL',
        'xxl' => 'XXL', 'xxxl' => 'XXXL', 'one size' => 'One Size', 'free size' => 'Free Size',
        'os' => 'OS', 'size 1' => '1', 'size 2' => '2', 'size 3' => '3', 'size 4' => '4',
        'size 5' => '5', 'size 6' => '6', 'size 7' => '7', 'size 8' => '8', 'size 9' => '9',
    ];
    $colorAliases = [
        'black' => 'Black', 'white' => 'White', 'blue' => 'Blue', 'red' => 'Red', 'green' => 'Green',
        'yellow' => 'Yellow', 'pink' => 'Pink', 'purple' => 'Purple', 'orange' => 'Orange', 'brown' => 'Brown',
        'grey' => 'Grey', 'gray' => 'Gray', 'navy' => 'Navy', 'beige' => 'Beige', 'gold' => 'Gold',
        'silver' => 'Silver', 'olive' => 'Olive', 'maroon' => 'Maroon', 'teal' => 'Teal', 'cream' => 'Cream',
        'wine' => 'Wine', 'indigo' => 'Indigo', 'peach' => 'Peach', 'charcoal' => 'Charcoal',
    ];

    $size = '';
    foreach ($tokens as $token) {
        $tokenKey = trim($token);
        if (isset($sizeAliases[$tokenKey])) {
            $size = $sizeAliases[$tokenKey];
            break;
        }
    }

    $color = '';
    foreach ($tokens as $token) {
        $tokenKey = trim($token);
        if (isset($colorAliases[$tokenKey])) {
            $color = $colorAliases[$tokenKey];
            break;
        }
    }

    $filtered = [];
    foreach ($tokens as $token) {
        $token = trim($token);
        if ($token === '') {
            continue;
        }
        $ignore = array_key_exists($token, $sizeAliases) || array_key_exists($token, $colorAliases);
        if ($ignore) {
            continue;
        }
        if (in_array($token, ['size', 'color', 'available', 'price', 'stock', 'item', 'product', 'shirt', 'dress', 'saree', 'top', 'jeans'], true)) {
            continue;
        }
        $filtered[] = $token;
    }

    $productName = trim(implode(' ', $filtered));
    if ($productName === '') {
        $productName = trim(preg_replace('/\b(size|color|available|price|stock|item|product)\b/ui', '', $lower));
    }

    return [
        'query' => $normalized,
        'terms' => $tokens,
        'product_name' => trim((string)$productName),
        'color' => $color,
        'size' => $size,
        'intent' => 'search',
    ];
}

function sk_mcp_tool_find_products(?string $query, ?array $tenant = null, int $limit = 5): array {
    $CI =& get_instance();
    if (!isset($CI->Sk_Product_model)) {
        $CI->load->model('Sk_Product_model');
    }

    $parsed = sk_mcp_tool_parse_query($query);
    $tenantInfo = sk_mcp_tool_resolve_tenant($tenant['tenant'] ?? null);
    $searchBase = trim($parsed['product_name'] !== '' ? $parsed['product_name'] : $parsed['query']);
    $search = trim($searchBase . ' ' . ($parsed['color'] !== '' ? $parsed['color'] : ''));
    $search = preg_replace('/\s+/', ' ', $search);

    $tenantId = (int)($tenant['tenant_id'] ?? $tenant['tenant'] ?? 0);
    $filters = ['status' => 'active', 'search' => $search, 'sort' => 'newest'];
    if ($tenantId > 0) {
        $filters['vendor_id'] = $tenantId;
    }

    $result = $search !== ''
        ? $CI->Sk_Product_model->get_all($filters, max(1, min(20, $limit)), 0)
        : ['data' => []];

    $items = is_array($result['data'] ?? null) ? $result['data'] : [];
    $matchRows = [];

    foreach ($items as $product) {
        $productName = trim((string)($product['name'] ?? ''));
        $tags = trim((string)($product['tags'] ?? ''));
        $description = trim((string)($product['description'] ?? ''));
        $colorText = trim((string)($product['color'] ?? ''));
        $haystack = mb_strtolower(($productName . ' ' . $tags . ' ' . $description . ' ' . $colorText), 'UTF-8');

        $score = 0;
        if ($parsed['product_name'] !== '') {
            $score += stripos($haystack, mb_strtolower($parsed['product_name'], 'UTF-8')) !== false ? 10 : 0;
        }
        if ($parsed['color'] !== '' && stripos($haystack, mb_strtolower($parsed['color'], 'UTF-8')) !== false) {
            $score += 20;
        }

        $sizeMatch = false;
        $candidateSize = $parsed['size'];
        if ($candidateSize !== '') {
            $variantLabels = [];
            foreach (($product['variants'] ?? []) as $variant) {
                if (!empty($variant['label'])) {
                    $variantLabels[] = mb_strtolower((string)$variant['label'], 'UTF-8');
                }
                if (!empty($variant['sku'])) {
                    $variantLabels[] = mb_strtolower((string)$variant['sku'], 'UTF-8');
                }
            }
            if (!empty($variantLabels)) {
                foreach ($variantLabels as $label) {
                    if (stripos($label, mb_strtolower($candidateSize, 'UTF-8')) !== false) {
                        $sizeMatch = true;
                        break;
                    }
                }
            }
        }
        if ($candidateSize !== '' && $sizeMatch) {
            $score += 25;
        }

        if ($score <= 0) {
            continue;
        }

        $stocks = array_map(static function ($v) {
            return (int)($v['stock'] ?? 0);
        }, $product['variants'] ?? []);
        $availableStock = !empty($stocks) ? max($stocks) : (int)($product['stock'] ?? 0);
        $displayPrice = isset($product['effective_price']) ? (float)$product['effective_price'] : ((float)($product['price'] ?? 0));

        $matchRows[] = [
            'id' => (int)($product['id'] ?? 0),
            'name' => (string)($product['name'] ?? ''),
            'sku' => (string)($product['sku'] ?? ''),
            'color' => $parsed['color'] !== '' ? $parsed['color'] : trim((string)($product['color'] ?? '')),
            'size' => $candidateSize !== '' && $sizeMatch ? $candidateSize : (string)($product['unit_label'] ?? ''),
            'available' => $availableStock > 0,
            'stock' => $availableStock,
            'price' => $displayPrice,
            'currency' => 'INR',
            'match_score' => $score,
            'image' => !empty($product['images'][0]['image']) ? $product['images'][0]['image'] : '',
        ];
    }

    usort($matchRows, static function ($a, $b) {
        return ($b['match_score'] ?? 0) <=> ($a['match_score'] ?? 0);
    });

    $results = array_slice($matchRows, 0, max(1, min(10, $limit)));

    return [
        'tenant' => $tenantInfo,
        'query' => $parsed['query'],
        'parsed' => $parsed,
        'count' => count($results),
        'results' => $results,
    ];
}

function sk_ai_normalize_whatsapp_message(array $payload): array {
    $messageId = trim((string)($payload['message_id'] ?? $payload['messageId'] ?? $payload['id'] ?? ''));
    $phoneNumberId = trim((string)($payload['phone_number_id'] ?? $payload['phoneNumberId'] ?? $payload['phone_number'] ?? ''));
    $customerPhone = trim((string)($payload['customer_phone'] ?? $payload['customerPhone'] ?? $payload['from'] ?? ''));
    $messageType = strtolower((string)($payload['message_type'] ?? $payload['type'] ?? 'text'));
    $text = trim((string)($payload['text'] ?? $payload['body'] ?? $payload['message'] ?? ''));
    if ($messageType === '') {
        $messageType = $text !== '' ? 'text' : 'unknown';
    }
    if ($customerPhone !== '' && !preg_match('/^\+?\d{8,15}$/', $customerPhone)) {
        $customerPhone = preg_replace('/[^0-9]/', '', $customerPhone);
    }

    $out = [
        'message_id' => $messageId,
        'phone_number_id' => $phoneNumberId,
        'customer_phone' => $customerPhone,
        'message_type' => $messageType,
        'text' => $text,
        'media_id' => $payload['media_id'] ?? $payload['mediaId'] ?? null,
        'timestamp' => $payload['timestamp'] ?? date('c'),
    ];

    if ($out['customer_phone'] === '' && !empty($payload['customer']['phone'])) {
        $out['customer_phone'] = trim((string)$payload['customer']['phone']);
    }
    if ($out['message_id'] === '' && !empty($payload['meta']['message_id'])) {
        $out['message_id'] = trim((string)$payload['meta']['message_id']);
    }

    return $out;
}

function sk_ai_tenant_resolve(array $message = [], ?array $settings = null): array {
    $CI =& get_instance();
    if (!isset($CI->Sk_Admin_model)) {
        $CI->load->model('Sk_Admin_model');
    }
    $settings = $settings ?? $CI->Sk_Admin_model->get_settings();

    $phoneNumberId = trim((string)($message['phone_number_id'] ?? ''));
    $customerPhone = trim((string)($message['customer_phone'] ?? ''));

    $tenantId = (int)($settings['saas_default_vendor_id'] ?? $settings['tenant_id'] ?? $settings['shop_id'] ?? $settings['vendor_id'] ?? 1);
    $shopName = trim((string)($settings['shop_name'] ?? $settings['business_name'] ?? 'Default Shop'));

    if ($phoneNumberId !== '' && !empty($settings['wa_cloud_phone_number_id'])) {
        if ((string)$settings['wa_cloud_phone_number_id'] === $phoneNumberId) {
            $tenantId = (int)($settings['saas_default_vendor_id'] ?? $settings['tenant_id'] ?? $settings['vendor_id'] ?? $tenantId);
            $shopName = trim((string)($settings['shop_name'] ?? $shopName));
        }
    }

    return [
        'tenant_id' => $tenantId,
        'shop_name' => $shopName,
        'phone_number_id' => $phoneNumberId,
        'customer_phone' => $customerPhone,
        'source' => $phoneNumberId !== '' ? 'trusted_phone_number_id' : 'default_tenant',
    ];
}

function sk_ai_mcp_tool_registry(): array {
    return [
        'search_products',
        'get_product',
        'get_product_variants',
        'check_stock',
        'get_customer',
        'save_customer',
        'calculate_cart',
        'calculate_shipping',
        'calculate_tax',
        'create_cart',
        'add_to_cart',
        'update_cart',
        'create_order',
        'get_order',
        'get_order_status',
        'create_payment_link',
        'get_payment_status',
        'generate_invoice',
        'get_delivery_status',
        'human_handoff',
    ];
}

function sk_ai_get_customer_by_phone(string $phone, ?int $tenantId = null): array {
    $phone = trim((string)$phone);
    if ($phone === '') {
        return ['success' => false, 'data' => null, 'error' => ['code' => 'PHONE_REQUIRED', 'message' => 'Phone number is required.']];
    }

    $CI =& get_instance();
    if (!isset($CI->Sk_User_model)) {
        $CI->load->model('Sk_User_model');
    }

    $user = $CI->Sk_User_model->get_by_phone($phone);
    if (!$user) {
        return ['success' => false, 'data' => null, 'error' => ['code' => 'CUSTOMER_NOT_FOUND', 'message' => 'Customer not found for this phone number.']];
    }

    $addresses = $CI->Sk_User_model->get_addresses((int)$user['id']);
    $defaultAddress = null;
    foreach ($addresses as $addr) {
        if (!empty($addr['is_default'])) {
            $defaultAddress = $addr;
            break;
        }
    }
    if ($defaultAddress === null && !empty($addresses)) {
        $defaultAddress = $addresses[0];
    }

    return [
        'success' => true,
        'data' => [
            'tenant_id' => $tenantId ?? 0,
            'customer_id' => (int)$user['id'],
            'name' => trim((string)($user['name'] ?? $user['full_name'] ?? '')),
            'phone' => trim((string)($user['phone'] ?? $phone)),
            'email' => trim((string)($user['email'] ?? '')),
            'status' => (int)($user['status'] ?? 1),
            'address' => $defaultAddress,
            'addresses' => $addresses,
        ],
        'error' => null,
    ];
}

function sk_ai_save_customer(array $data, ?int $tenantId = null): array {
    $CI =& get_instance();
    if (!isset($CI->Sk_User_model)) {
        $CI->load->model('Sk_User_model');
    }

    $phone = trim((string)($data['phone'] ?? $data['customer_phone'] ?? ''));
    $name = trim((string)($data['name'] ?? $data['full_name'] ?? ''));
    if ($phone === '') {
        return ['success' => false, 'data' => null, 'error' => ['code' => 'PHONE_REQUIRED', 'message' => 'Phone number is required.']];
    }

    $existing = $CI->Sk_User_model->get_by_phone($phone);
    if ($existing) {
        $update = [];
        if ($name !== '') {
            $update['name'] = $name;
        }
        if (!empty($data['email'])) {
            $update['email'] = trim((string)$data['email']);
        }
        if (!empty($data['status'])) {
            $update['status'] = (int)$data['status'];
        }
        if (!empty($update)) {
            $CI->Sk_User_model->update((int)$existing['id'], $update);
        }
        $customer = $CI->Sk_User_model->get_by_id((int)$existing['id']);
        return ['success' => true, 'data' => ['tenant_id' => $tenantId ?? 0, 'customer_id' => (int)$customer['id'], 'name' => trim((string)($customer['name'] ?? $name)), 'phone' => $phone], 'error' => null];
    }

    $newId = $CI->Sk_User_model->create([
        'name' => $name !== '' ? $name : 'Customer',
        'email' => !empty($data['email']) ? trim((string)$data['email']) : null,
        'phone' => $phone,
        'status' => 1,
    ]);

    if (!empty($data['address'])) {
        $addr = $data['address'];
        $CI->Sk_User_model->save_address([
            'user_id' => (int)$newId,
            'full_name' => $name !== '' ? $name : 'Customer',
            'phone' => $phone,
            'line1' => trim((string)($addr['line1'] ?? $addr['address_line1'] ?? '')),
            'line2' => trim((string)($addr['line2'] ?? $addr['address_line2'] ?? '')),
            'city' => trim((string)($addr['city'] ?? '')),
            'state' => trim((string)($addr['state'] ?? '')),
            'pincode' => trim((string)($addr['pincode'] ?? $addr['postal_code'] ?? '')),
            'country' => trim((string)($addr['country'] ?? 'Malaysia')) ?: 'Malaysia',
            'label' => 'Home',
            'address_type' => 'shipping',
            'is_default' => 1,
        ]);
    }

    return ['success' => true, 'data' => ['tenant_id' => $tenantId ?? 0, 'customer_id' => (int)$newId, 'name' => $name !== '' ? $name : 'Customer', 'phone' => $phone], 'error' => null];
}

function sk_ai_get_product_variants(int $productId, ?int $tenantId = null): array {
    if ($productId <= 0) {
        return ['success' => false, 'data' => null, 'error' => ['code' => 'INVALID_PRODUCT', 'message' => 'product_id is required.']];
    }

    $CI =& get_instance();
    if (!isset($CI->Sk_Product_model)) {
        $CI->load->model('Sk_Product_model');
    }
    $product = $CI->Sk_Product_model->get_by_id($productId);
    if (!$product) {
        return ['success' => false, 'data' => null, 'error' => ['code' => 'PRODUCT_NOT_FOUND', 'message' => 'Product not found.']];
    }

    $variants = is_array($product['variants'] ?? null) ? $product['variants'] : [];
    return [
        'success' => true,
        'data' => [
            'tenant_id' => $tenantId ?? 0,
            'product_id' => (int)($product['id'] ?? $productId),
            'name' => (string)($product['name'] ?? ''),
            'variants' => $variants,
            'count' => count($variants),
        ],
        'error' => null,
    ];
}

function sk_ai_calculate_cart(array $items, ?int $tenantId = null): array {
    $lines = is_array($items) ? $items : [];
    if (empty($lines)) {
        return ['success' => true, 'data' => ['tenant_id' => $tenantId ?? 0, 'subtotal' => 0.0, 'shipping' => 0.0, 'tax' => 0.0, 'total' => 0.0, 'items' => []], 'error' => null];
    }

    $CI =& get_instance();
    if (!isset($CI->Sk_Product_model)) {
        $CI->load->model('Sk_Product_model');
    }

    $subtotal = 0.0;
    foreach ($lines as $line) {
        $productId = (int)($line['product_id'] ?? 0);
        $qty = max(1, (int)($line['quantity'] ?? 1));
        if ($productId <= 0) {
            continue;
        }
        $product = $CI->Sk_Product_model->get_by_id($productId);
        if (!$product) {
            continue;
        }
        $variantId = !empty($line['variant_id']) ? (int)$line['variant_id'] : null;
        $variant = null;
        if ($variantId) {
            foreach (($product['variants'] ?? []) as $v) {
                if ((int)($v['id'] ?? 0) === $variantId) {
                    $variant = $v;
                    break;
                }
            }
        }
        $unitPrice = $variant
            ? ((float)($variant['effective_price'] ?? $variant['sale_price'] ?? $variant['price'] ?? 0))
            : ((float)($product['effective_price'] ?? $product['sale_price'] ?? $product['price'] ?? 0));
        $subtotal += $unitPrice * $qty;
    }

    $settings = [];
    if (isset($CI->Sk_Admin_model)) {
        $settings = $CI->Sk_Admin_model->get_settings();
    }
    $shipping = 0.0;
    $freeAbove = (float)($settings['free_shipping_above'] ?? 0);
    if ($freeAbove <= 0 || $subtotal < $freeAbove) {
        $shipping = (float)($settings['shipping_charge'] ?? 0);
    }
    $tax = 0.0;
    $total = $subtotal + $shipping + $tax;

    return [
        'success' => true,
        'data' => [
            'tenant_id' => $tenantId ?? 0,
            'subtotal' => round($subtotal, 2),
            'shipping' => round($shipping, 2),
            'tax' => round($tax, 2),
            'total' => round($total, 2),
            'items' => $lines,
        ],
        'error' => null,
    ];
}

function sk_ai_get_order_status(int $orderId, ?int $tenantId = null): array {
    if ($orderId <= 0) {
        return ['success' => false, 'data' => null, 'error' => ['code' => 'INVALID_ORDER', 'message' => 'order_id is required.']];
    }

    $CI =& get_instance();
    if (!isset($CI->Sk_Order_model)) {
        $CI->load->model('Sk_Order_model');
    }
    $order = $CI->Sk_Order_model->get_by_id($orderId);
    if (!$order) {
        return ['success' => false, 'data' => null, 'error' => ['code' => 'ORDER_NOT_FOUND', 'message' => 'Order not found.']];
    }

    return [
        'success' => true,
        'data' => [
            'tenant_id' => $tenantId ?? 0,
            'order_id' => (int)$order['id'],
            'order_number' => (string)($order['order_number'] ?? ''),
            'status' => (string)($order['status'] ?? 'pending'),
            'payment_status' => (string)($order['payment_status'] ?? 'pending'),
            'total' => (float)($order['total'] ?? 0),
            'shipping_phone' => (string)($order['shipping_phone'] ?? ''),
        ],
        'error' => null,
    ];
}

function sk_ai_generate_invoice(int $orderId, ?int $tenantId = null): array {
    if ($orderId <= 0) {
        return ['success' => false, 'data' => null, 'error' => ['code' => 'INVALID_ORDER', 'message' => 'order_id is required.']];
    }

    $CI =& get_instance();
    if (!isset($CI->Sk_Order_model)) {
        $CI->load->model('Sk_Order_model');
    }
    $order = $CI->Sk_Order_model->get_by_id($orderId);
    if (!$order) {
        return ['success' => false, 'data' => null, 'error' => ['code' => 'ORDER_NOT_FOUND', 'message' => 'Order not found.']];
    }

    $CI->load->helper(['sk_invoice', 'sk_invoice_pdf']);
    $settings = $CI->Sk_Admin_model->get_settings();
    $invoice = sk_invoice_build($order, $settings);

    return [
        'success' => true,
        'data' => [
            'tenant_id' => $tenantId ?? 0,
            'order_id' => (int)$order['id'],
            'order_number' => (string)($invoice['order_number'] ?? $order['order_number'] ?? ''),
            'invoice_no' => (string)($invoice['invoice_no'] ?? ''),
            'total' => (float)($invoice['total'] ?? $order['total'] ?? 0),
            'view_url' => site_url('invoice/view/' . (int)$order['id'] . '/' . sk_invoice_public_token((int)$order['id'], (string)($order['order_number'] ?? ''))),
            'download_url' => site_url('invoice/download/' . (int)$order['id'] . '/' . sk_invoice_public_token((int)$order['id'], (string)($order['order_number'] ?? ''))),
        ],
        'error' => null,
    ];
}

function sk_ai_get_delivery_status(int $orderId, ?int $tenantId = null): array {
    if ($orderId <= 0) {
        return ['success' => false, 'data' => null, 'error' => ['code' => 'INVALID_ORDER', 'message' => 'order_id is required.']];
    }

    $status = sk_ai_get_order_status($orderId, $tenantId);
    if (!$status['success']) {
        return $status;
    }
    $state = (string)($status['data']['status'] ?? 'pending');
    $delivery = in_array($state, ['shipped', 'delivered'], true) ? $state : 'pending';
    return [
        'success' => true,
        'data' => [
            'tenant_id' => $tenantId ?? 0,
            'order_id' => (int)$orderId,
            'status' => $delivery,
            'tracking_status' => $delivery,
            'message' => $delivery === 'pending' ? 'Order is still being processed.' : ('Order is ' . $delivery . '.'),
        ],
        'error' => null,
    ];
}

function sk_ai_human_handoff(array $context = [], ?int $tenantId = null): array {
    $data = is_array($context) ? $context : [];
    return [
        'success' => true,
        'data' => [
            'tenant_id' => $tenantId ?? 0,
            'handoff_to' => 'human_agent',
            'reason' => trim((string)($data['reason'] ?? 'Customer requested human support.')),
            'customer_phone' => trim((string)($data['customer_phone'] ?? $data['phone'] ?? '')),
            'message' => 'The customer has been routed to a human support agent.',
        ],
        'error' => null,
    ];
}

function sk_ai_mcp_execute_tool(string $tool, array $params = [], array $tenant = []): array {
    $tool = trim($tool);
    if ($tool === '') {
        return ['success' => false, 'data' => null, 'error' => ['code' => 'MCP_TOOL_REQUIRED', 'message' => 'Tool name is required.']];
    }

    $allowed = sk_ai_mcp_tool_registry();
    if (!in_array($tool, $allowed, true)) {
        return ['success' => false, 'data' => null, 'error' => ['code' => 'MCP_TOOL_NOT_FOUND', 'message' => 'Tool is not registered.']];
    }

    $tenantId = (int)($tenant['tenant_id'] ?? $tenant['tenant'] ?? 1);
    $params = is_array($params) ? $params : [];

    if ($tool === 'get_customer') {
        $phone = trim((string)($params['phone'] ?? $params['customer_phone'] ?? ''));
        return sk_ai_get_customer_by_phone($phone, $tenantId);
    }

    if ($tool === 'save_customer') {
        return sk_ai_save_customer($params, $tenantId);
    }

    if ($tool === 'search_products') {
        $query = trim((string)($params['query'] ?? $params['text'] ?? ''));
        $parsed = sk_mcp_tool_parse_query($query !== '' ? $query : '');
        $results = sk_mcp_tool_find_products($query, ['tenant' => $tenantId], 5);
        $matches = $results['results'] ?? [];
        return [
            'success' => !empty($matches),
            'data' => [
                'tenant_id' => $tenantId,
                'query' => $query,
                'parsed' => $parsed,
                'products' => $matches,
            ],
            'error' => empty($matches) ? ['code' => 'PRODUCT_NOT_FOUND', 'message' => 'No matching products found for the current tenant.'] : null,
        ];
    }

    if ($tool === 'check_stock') {
        $productId = (int)($params['product_id'] ?? 0);
        $variantId = (int)($params['variant_id'] ?? 0);
        if ($productId <= 0) {
            return ['success' => false, 'data' => null, 'error' => ['code' => 'INVALID_PRODUCT', 'message' => 'product_id is required.']];
        }

        $CI =& get_instance();
        if (!isset($CI->Sk_Product_model)) {
            $CI->load->model('Sk_Product_model');
        }
        $product = $CI->Sk_Product_model->get_by_id($productId);
        $stock = 0;
        $available = false;
        if ($product) {
            $variants = $product['variants'] ?? [];
            if ($variantId > 0) {
                foreach ($variants as $variant) {
                    if ((int)($variant['id'] ?? 0) === $variantId) {
                        $stock = (int)($variant['stock'] ?? 0);
                        break;
                    }
                }
            }
            if ($stock <= 0) {
                $stock = max(0, (int)($product['stock'] ?? 0));
            }
            $available = $stock > 0;
        }

        return [
            'success' => true,
            'data' => [
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'available' => $available,
                'stock' => $stock,
            ],
            'error' => null,
        ];
    }

    if ($tool === 'get_product') {
        $productId = (int)($params['product_id'] ?? 0);
        if ($productId <= 0) {
            return ['success' => false, 'data' => null, 'error' => ['code' => 'INVALID_PRODUCT', 'message' => 'product_id is required.']];
        }

        $CI =& get_instance();
        if (!isset($CI->Sk_Product_model)) {
            $CI->load->model('Sk_Product_model');
        }
        $product = $CI->Sk_Product_model->get_by_id($productId);
        if (!$product) {
            return ['success' => false, 'data' => null, 'error' => ['code' => 'PRODUCT_NOT_FOUND', 'message' => 'Product not found.']];
        }

        $image = '';
        if (!empty($product['images'][0]['image'])) {
            $image = (string)$product['images'][0]['image'];
        }

        return [
            'success' => true,
            'data' => [
                'tenant_id' => $tenantId,
                'product_id' => (int)($product['id'] ?? 0),
                'name' => (string)($product['name'] ?? ''),
                'price' => (float)($product['effective_price'] ?? $product['price'] ?? 0),
                'image_url' => $image,
                'variants' => $product['variants'] ?? [],
            ],
            'error' => null,
        ];
    }

    if ($tool === 'get_product_variants') {
        $productId = (int)($params['product_id'] ?? 0);
        return sk_ai_get_product_variants($productId, $tenantId);
    }

    if ($tool === 'calculate_cart') {
        return sk_ai_calculate_cart(is_array($params['items'] ?? $params['cart'] ?? []) ? ($params['items'] ?? $params['cart']) : [], $tenantId);
    }

    if ($tool === 'get_order_status') {
        $orderId = (int)($params['order_id'] ?? 0);
        return sk_ai_get_order_status($orderId, $tenantId);
    }

    if ($tool === 'generate_invoice') {
        $orderId = (int)($params['order_id'] ?? 0);
        return sk_ai_generate_invoice($orderId, $tenantId);
    }

    if ($tool === 'get_delivery_status') {
        $orderId = (int)($params['order_id'] ?? 0);
        return sk_ai_get_delivery_status($orderId, $tenantId);
    }

    if ($tool === 'human_handoff') {
        return sk_ai_human_handoff($params, $tenantId);
    }

    return [
        'success' => true,
        'data' => ['tenant_id' => $tenantId, 'tool' => $tool, 'params' => $params],
        'error' => null,
    ];
}

function sk_ai_build_talkai_response(array $processed): array {
    $tenant = is_array($processed['tenant'] ?? null) ? $processed['tenant'] : [];
    $tool = $processed['tool'] ?? null;
    $toolResult = is_array($processed['tool_result'] ?? null) ? $processed['tool_result'] : [];
    $facts = [];

    if (!empty($toolResult['data']['products'])) {
        foreach (array_slice($toolResult['data']['products'], 0, 3) as $product) {
            $facts[] = [
                'type' => 'product',
                'name' => trim((string)($product['name'] ?? '')),
                'price' => (float)($product['price'] ?? 0),
                'available' => !empty($product['available']) || ((int)($product['stock'] ?? 0) > 0),
                'size' => trim((string)($product['size'] ?? '')),
                'currency' => trim((string)($product['currency'] ?? 'INR')),
            ];
        }
    }

    if (!empty($toolResult['data']['customer'])) {
        $customer = $toolResult['data']['customer'];
        $facts[] = [
            'type' => 'customer',
            'customer_id' => (int)($customer['customer_id'] ?? 0),
            'name' => trim((string)($customer['name'] ?? '')),
            'phone' => trim((string)($customer['phone'] ?? '')),
        ];
    }

    if (!empty($toolResult['data']['order'])) {
        $order = $toolResult['data']['order'];
        $facts[] = [
            'type' => 'order',
            'order_id' => (int)($order['order_id'] ?? 0),
            'status' => trim((string)($order['status'] ?? 'pending')),
            'payment_status' => trim((string)($order['payment_status'] ?? 'pending')),
        ];
    }

    return [
        'success' => true,
        'status' => 'ok',
        'reply' => (string)($processed['reply'] ?? ''),
        'intent' => (string)($processed['intent'] ?? 'general'),
        'tool' => $tool,
        'tool_calls' => $tool ? [$tool] : [],
        'facts' => $facts,
        'message_id' => (string)($processed['message_id'] ?? ''),
        'customer_phone' => (string)($processed['customer_phone'] ?? ''),
        'tenant' => $tenant,
        'raw_tool_result' => is_array($toolResult) ? $toolResult : [],
    ];
}

function sk_ai_process_whatsapp_message(array $payload, array $tenant = []): array {
    $message = sk_ai_normalize_whatsapp_message($payload);
    $tenant = $tenant ?: sk_ai_tenant_resolve($message);
    $text = trim((string)($message['text'] ?? ''));

    $result = [
        'message_id' => $message['message_id'],
        'phone_number_id' => $message['phone_number_id'],
        'customer_phone' => $message['customer_phone'],
        'tenant' => $tenant,
        'intent' => 'general',
        'tool' => null,
        'tool_result' => null,
        'reply' => '',
    ];

    if ($text === '') {
        $result['reply'] = 'I can help with products, stock, and orders. Please send your product query.';
        return $result;
    }

    $lower = mb_strtolower($text, 'UTF-8');
    $intent = 'general';
    if (preg_match('/(shirt|dress|top|jeans|kurta|saree|product|stock|price|available|order|buy|photo|image)/ui', $text)) {
        $intent = 'product_search';
    }

    $result['intent'] = $intent;

    if ($intent === 'product_search') {
        $toolResult = sk_ai_mcp_execute_tool('search_products', ['query' => $text], $tenant);
        $result['tool'] = 'search_products';
        $result['tool_result'] = $toolResult;

        if (!empty($toolResult['data']['products'])) {
            $best = $toolResult['data']['products'][0];
            $result['reply'] = 'Yes bro 😊 ' . trim((string)($best['name'] ?? 'product')) . ' available. ' . ($best['size'] !== '' ? 'Size ' . $best['size'] . ' ' : '') . '₹' . number_format((float)($best['price'] ?? 0), 0) . '. Which size do you want?';
        } else {
            $result['reply'] = 'Sorry bro 😊 I could not find that item in this shop. I can check a similar product.';
        }
    }

    return $result;
}
