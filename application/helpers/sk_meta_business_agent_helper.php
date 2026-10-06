<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Meta Business Agent Platform client + local config helpers.
 */

function sk_meta_ba_config_array(): array {
    static $cfg = null;
    if (is_array($cfg)) {
        return $cfg;
    }
    $CI =& get_instance();
    $CI->config->load('meta_business_agent', true);
    $cfg = [
        'enabled'             => (bool)$CI->config->item('meta_ba_enabled', 'meta_business_agent'),
        'api_base'            => (string)$CI->config->item('meta_ba_api_base', 'meta_business_agent'),
        'api_version'         => (string)$CI->config->item('meta_ba_api_version', 'meta_business_agent'),
        'system_user_token'   => (string)$CI->config->item('meta_ba_system_user_token', 'meta_business_agent'),
        'connector_api_key'   => (string)$CI->config->item('meta_ba_connector_api_key', 'meta_business_agent'),
        'connector_base_url'  => (string)$CI->config->item('meta_ba_connector_base_url', 'meta_business_agent'),
        'default_audience'    => (string)$CI->config->item('meta_ba_default_audience', 'meta_business_agent'),
        'handoff_message'     => (string)$CI->config->item('meta_ba_handoff_message', 'meta_business_agent'),
    ];
    if ($cfg['api_base'] === '') {
        $cfg['api_base'] = 'https://api.facebook.com';
    }
    if ($cfg['api_version'] === '') {
        $cfg['api_version'] = '2.0.0';
    }
    if ($cfg['default_audience'] === '') {
        $cfg['default_audience'] = 'ALLOWLISTED_ONLY';
    }
    if ($cfg['connector_base_url'] === '') {
        $cfg['connector_base_url'] = rtrim(site_url(), '/');
    }
    return $cfg;
}

function sk_meta_ba_is_platform_ready(): bool {
    $cfg = sk_meta_ba_config_array();
    return $cfg['enabled']
        && $cfg['system_user_token'] !== ''
        && $cfg['connector_api_key'] !== '';
}

function sk_meta_ba_connector_auth_ok(?string $provided): bool {
    $cfg = sk_meta_ba_config_array();
    $expected = trim((string)$cfg['connector_api_key']);
    if ($expected === '') {
        return false;
    }
    $provided = trim((string)$provided);
    return $provided !== '' && hash_equals($expected, $provided);
}

function sk_meta_ba_request_token_from_headers(): string {
    $CI =& get_instance();
    $auth = (string)$CI->input->get_request_header('Authorization', true);
    if (stripos($auth, 'Bearer ') === 0) {
        return trim(substr($auth, 7));
    }
    $hdr = (string)$CI->input->get_request_header('X-Api-Key', true);
    if ($hdr !== '') {
        return trim($hdr);
    }
    return trim((string)$CI->input->get_request_header('X-MCP-Token', true));
}

/**
 * Resolve access token for Meta BA Platform calls for a phone entity.
 */
function sk_meta_ba_access_token(string $phoneNumberId = '', ?array $settings = null): string {
    $cfg = sk_meta_ba_config_array();
    if ($cfg['system_user_token'] !== '') {
        return $cfg['system_user_token'];
    }
    $CI =& get_instance();
    if (!isset($CI->Sk_Vendor_whatsapp_account_model)) {
        $CI->load->model('Sk_Vendor_whatsapp_account_model');
    }
    $phoneNumberId = trim($phoneNumberId);
    if ($phoneNumberId !== '') {
        $acct = $CI->Sk_Vendor_whatsapp_account_model->get_by_phone($phoneNumberId);
        $tok = trim((string)($acct['access_token'] ?? ''));
        if ($tok !== '') {
            return $tok;
        }
    }
    if (!function_exists('sk_wa_cloud_config')) {
        $CI->load->helper('sk_whatsapp_cloud');
    }
    $cloud = sk_wa_cloud_config($settings);
    return trim((string)($cloud['access_token'] ?? ''));
}

function sk_meta_ba_http(string $method, string $url, $body = null, string $token = '', int $timeout = 30): array {
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'X-API-Version: ' . sk_meta_ba_config_array()['api_version'],
    ];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(15, max(5, (int)$timeout)),
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
    ];
    if ($body !== null) {
        // Meta schemas expect a JSON object (or null). PHP json_encode([]) is "[]", which fails.
        if (is_array($body) && $body === []) {
            $opts[CURLOPT_POSTFIELDS] = '{}';
        } elseif (is_object($body) && $body instanceof stdClass && get_object_vars($body) === []) {
            $opts[CURLOPT_POSTFIELDS] = '{}';
        } else {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err) {
        return ['ok' => false, 'http' => $code, 'error' => $err, 'data' => null, 'raw' => ''];
    }
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    $ok = $code >= 200 && $code < 300;
    $error = '';
    if (!$ok) {
        if (is_array($decoded)) {
            $error = (string)(
                $decoded['error']['message']
                ?? $decoded['detail']
                ?? $decoded['title']
                ?? $decoded['message']
                ?? $decoded['error']
                ?? ''
            );
            if (is_array($decoded['error'] ?? null) && $error === '') {
                $error = (string)($decoded['error']['error_user_msg'] ?? $decoded['error']['type'] ?? '');
            }
        }
        if ($error === '') {
            $error = 'HTTP ' . $code;
        }
    }
    $data = is_array($decoded) ? $decoded : null;
    return [
        'ok'    => $ok,
        'http'  => $code,
        'error' => $error,
        'data'  => $data,
        'raw'   => (string)$raw,
    ];
}

/** User-facing message for a failed sk_meta_ba_http() response. */
function sk_meta_ba_human_error(array $res): string {
    $code = (int)($res['http'] ?? 0);
    $err = trim((string)($res['error'] ?? ''));
    if ($err !== '' && $err !== 'HTTP ' . $code) {
        return $code > 0 ? "Meta API (HTTP {$code}): {$err}" : $err;
    }
    $raw = trim((string)($res['raw'] ?? ''));
    if ($raw !== '' && $raw[0] !== '<') {
        $excerpt = mb_substr($raw, 0, 400);
        return "Meta API HTTP {$code}: {$excerpt}";
    }
    if ($code >= 500) {
        return "Meta API HTTP {$code} (server error). Wait a minute and retry; for Test with product questions, run Sync tools first.";
    }
    return "Meta API HTTP {$code}.";
}

/** Extra context for admin JSON when Meta calls fail. */
function sk_meta_ba_error_data(array $res): array {
    $out = [
        'meta_http' => (int)($res['http'] ?? 0),
    ];
    if (is_array($res['data'] ?? null)) {
        $out['meta'] = $res['data'];
    } elseif (trim((string)($res['raw'] ?? '')) !== '') {
        $out['meta_raw'] = mb_substr((string)$res['raw'], 0, 2000);
    }
    return $out;
}

function sk_meta_ba_entity_url(string $phoneNumberId, string $path = ''): string {
    $base = sk_meta_ba_config_array()['api_base'];
    $phoneNumberId = trim($phoneNumberId);
    $path = ltrim($path, '/');
    return $base . '/' . rawurlencode($phoneNumberId) . ($path !== '' ? '/' . $path : '');
}

function sk_meta_ba_check_eligibility(string $phoneNumberId, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '') {
        return ['ok' => false, 'error' => 'Missing phone_number_id or access token.', 'data' => null];
    }
    return sk_meta_ba_http('GET', sk_meta_ba_entity_url($phoneNumberId, 'agent_eligibility'), null, $token);
}

function sk_meta_ba_onboard(string $phoneNumberId, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '') {
        return ['ok' => false, 'error' => 'Missing phone_number_id or access token.', 'data' => null];
    }
    // WhatsApp onboarding expects an empty JSON object "{}", not "[]".
    return sk_meta_ba_http('POST', sk_meta_ba_entity_url($phoneNumberId, 'agent_onboarding'), new stdClass(), $token);
}

function sk_meta_ba_get_settings(string $phoneNumberId, ?string $agentId = null, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '') {
        return ['ok' => false, 'error' => 'Missing phone_number_id or access token.', 'data' => null];
    }
    $url = sk_meta_ba_entity_url($phoneNumberId, 'agent_config/settings');
    if ($agentId) {
        $url .= '?agent_id=' . rawurlencode($agentId);
    }
    return sk_meta_ba_http('GET', $url, null, $token);
}

function sk_meta_ba_put_settings(string $phoneNumberId, array $body, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '') {
        return ['ok' => false, 'error' => 'Missing phone_number_id or access token.', 'data' => null];
    }
    return sk_meta_ba_http('PUT', sk_meta_ba_entity_url($phoneNumberId, 'agent_config/settings'), $body, $token);
}

function sk_meta_ba_upsert_business_info(string $phoneNumberId, array $info, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '') {
        return ['ok' => false, 'error' => 'Missing phone_number_id or access token.', 'data' => null];
    }
    return sk_meta_ba_http('POST', sk_meta_ba_entity_url($phoneNumberId, 'agent_knowledge/business_info'), $info, $token);
}

function sk_meta_ba_list_skills(string $phoneNumberId, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '') {
        return ['ok' => false, 'error' => 'Missing phone_number_id or access token.', 'data' => null];
    }
    return sk_meta_ba_http('GET', sk_meta_ba_entity_url($phoneNumberId, 'agent_config/skills'), null, $token);
}

function sk_meta_ba_create_skill(string $phoneNumberId, array $body, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '') {
        return ['ok' => false, 'error' => 'Missing phone_number_id or access token.', 'data' => null];
    }
    return sk_meta_ba_http('POST', sk_meta_ba_entity_url($phoneNumberId, 'agent_config/skills'), $body, $token);
}

function sk_meta_ba_update_skill(string $phoneNumberId, string $skillId, array $body, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '' || $skillId === '') {
        return ['ok' => false, 'error' => 'Missing skill context.', 'data' => null];
    }
    $url = sk_meta_ba_entity_url($phoneNumberId, 'agent_config/skills/' . rawurlencode($skillId));
    return sk_meta_ba_http('PUT', $url, $body, $token);
}

/**
 * Push / refresh behavioral skills (Meta no longer uses agent_instructions for this).
 * @see https://developers.facebook.com/documentation/meta-business-agent/reference/configure/agent-skills
 */
function sk_meta_ba_upsert_instructions(string $phoneNumberId, string $instructions, ?array $settings = null): array {
    return sk_meta_ba_sync_sales_skills($phoneNumberId, $instructions, $settings);
}

function sk_meta_ba_sales_skill_defs(string $shopName, string $extraInstructions = ''): array {
    $shopName = trim($shopName) !== '' ? trim($shopName) : 'our shop';
    $extra = trim($extraInstructions);
    $skill = "You are the WhatsApp sales manager for {$shopName}. "
        . "Sound like a real shopkeeper: warm, short, natural. Match the customer's language (Tamil/English/Tanglish). "
        . "Never mention AI, tools, databases, APIs, or internal IDs. "
        . "Infer typos: donu/dono have = do you have; amount/prise/rate = price; kanjivaram/kanchipuram = Kanjivaram saree. "
        . "PRODUCT: For product/price/stock/details call search_products once (or get_product_details/check_stock/get_product_price for a known id). "
        . "Never invent products, prices, stock, discounts, or payment URLs. Use tool summary fields only. "
        . "SALES FLOW: help decide → confirm product/qty → collect name + delivery address → identify_customer/create_customer/update_customer → "
        . "calculate_order_total → create_order with confirmed=true → handover_to_human for approval. "
        . "Do NOT call create_payment_link until a human has confirmed the order. Screenshots are not payment proof — use get_payment_status. "
        . "generate_invoice only after verified paid payment. "
        . "Never hand off for simple product/price questions. Hand off when the customer asks for a human, or for refunds/disputes/damage/complaints. "
        . "Keep WhatsApp replies to 1-3 short sentences. Ask only for the next missing detail. "
        . "Use save_conversation_state to remember selected product, qty, and missing fields.";
    if ($extra !== '') {
        $skill .= ' Extra shop notes: ' . $extra;
    }
    return [
        [
            'title'       => 'shop-sales-assistant',
            'description' => 'Apply on every shopping message: products, price, stock, order, payment, delivery, typos, Tamil/English chat.',
            'skill'       => $skill,
        ],
    ];
}

function sk_meta_ba_sync_sales_skills(string $phoneNumberId, string $shopOrInstructions = '', ?array $settings = null): array {
    $shopName = $shopOrInstructions;
    // If a long instruction string was passed (legacy callers), treat it as shop name fallback + note.
    if (strlen($shopOrInstructions) > 80) {
        $shopName = 'our shop';
    }
    $defs = sk_meta_ba_sales_skill_defs($shopName, strlen($shopOrInstructions) > 80 ? $shopOrInstructions : '');
    $listed = sk_meta_ba_list_skills($phoneNumberId, $settings);
    $byTitle = [];
    if (!empty($listed['ok']) && is_array($listed['data'])) {
        $items = $listed['data'];
        if (isset($items['data']) && is_array($items['data'])) {
            $items = $items['data'];
        }
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['title'])) {
                continue;
            }
            $byTitle[strtolower((string)$item['title'])] = $item;
        }
    }

    $results = [];
    $failed = [];
    foreach ($defs as $def) {
        $key = strtolower($def['title']);
        $existing = $byTitle[$key] ?? null;
        if ($existing && !empty($existing['id'])) {
            $res = sk_meta_ba_update_skill($phoneNumberId, (string)$existing['id'], $def, $settings);
        } else {
            $res = sk_meta_ba_create_skill($phoneNumberId, $def, $settings);
        }
        $results[] = [
            'title' => $def['title'],
            'ok'    => !empty($res['ok']),
            'error' => $res['error'] ?? '',
            'http'  => $res['http'] ?? null,
            'data'  => $res['data'] ?? null,
        ];
        if (empty($res['ok'])) {
            $failed[] = $def['title'] . ': ' . ($res['error'] ?? 'failed');
        }
    }

    return [
        'ok'    => !$failed,
        'error' => $failed ? ('Skill sync failed — ' . implode('; ', $failed)) : '',
        'data'  => ['skills' => $results],
    ];
}

function sk_meta_ba_list_connectors(string $phoneNumberId, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '') {
        return ['ok' => false, 'error' => 'Missing phone_number_id or access token.', 'data' => null];
    }
    return sk_meta_ba_http('GET', sk_meta_ba_entity_url($phoneNumberId, 'agent_connectors'), null, $token);
}

function sk_meta_ba_create_connector(string $phoneNumberId, array $body, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '') {
        return ['ok' => false, 'error' => 'Missing phone_number_id or access token.', 'data' => null];
    }
    return sk_meta_ba_http('POST', sk_meta_ba_entity_url($phoneNumberId, 'agent_connectors'), $body, $token);
}

function sk_meta_ba_upsert_connector_api_key(string $phoneNumberId, string $connectorId, string $apiKey, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '' || $connectorId === '') {
        return ['ok' => false, 'error' => 'Missing connector credentials.', 'data' => null];
    }
    $url = sk_meta_ba_entity_url($phoneNumberId, 'agent_connectors/' . rawurlencode($connectorId) . '/upsertApiKey');
    return sk_meta_ba_http('POST', $url, ['api_key' => $apiKey], $token);
}

function sk_meta_ba_create_connector_tool(string $phoneNumberId, string $connectorId, array $tool, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '' || $connectorId === '') {
        return ['ok' => false, 'error' => 'Missing connector tool context.', 'data' => null];
    }
    $url = sk_meta_ba_entity_url($phoneNumberId, 'agent_connectors/' . rawurlencode($connectorId) . '/tools');
    return sk_meta_ba_http('POST', $url, $tool, $token);
}

function sk_meta_ba_list_connector_tools(string $phoneNumberId, string $connectorId, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '' || $connectorId === '') {
        return ['ok' => false, 'error' => 'Missing connector context.', 'data' => null];
    }
    $url = sk_meta_ba_entity_url($phoneNumberId, 'agent_connectors/' . rawurlencode($connectorId) . '/tools');
    return sk_meta_ba_http('GET', $url, null, $token);
}

function sk_meta_ba_agent_test(string $phoneNumberId, string $message, ?string $conversationId = null, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '') {
        return ['ok' => false, 'error' => 'Missing phone_number_id or access token.', 'data' => null];
    }
    $body = ['user_msg' => $message];
    if ($conversationId) {
        $body['conversation_id'] = $conversationId;
    }
    return sk_meta_ba_http('POST', sk_meta_ba_entity_url($phoneNumberId, 'agent_test'), $body, $token, 90);
}

/**
 * Thread control via Cloud API style endpoint used by Meta Business Agent.
 */
function sk_meta_ba_thread_control(string $phoneNumberId, string $to, string $action = 'release', ?array $settings = null): array {
    $action = strtolower(trim($action));
    if (!in_array($action, ['release', 'take', 'pass'], true)) {
        return ['ok' => false, 'error' => 'Invalid thread control action.', 'data' => null];
    }
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    $toDigits = preg_replace('/\D+/', '', $to) ?? '';
    if ($token === '' || $phoneNumberId === '' || $toDigits === '') {
        return ['ok' => false, 'error' => 'Missing phone_number_id, access token, or consumer phone.', 'data' => null];
    }
    $cfg = sk_meta_ba_config_array();
    // Cloud thread_control is Graph-style — do NOT send X-API-Version (Meta rejects 2.0.0 there).
    $url = rtrim($cfg['api_base'], '/') . '/business/whatsapp/phone_numbers/'
        . rawurlencode($phoneNumberId) . '/thread_control';
    $body = [
        'messaging_product' => 'whatsapp',
        'action'            => $action === 'pass' ? 'release' : $action,
        'to'                => $toDigits,
    ];
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);
    $raw = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    $data = json_decode((string)$raw, true);
    if ($cerr !== '') {
        return ['ok' => false, 'error' => $cerr, 'http' => $http, 'data' => $data];
    }
    $ok = $http >= 200 && $http < 300;
    $err = '';
    if (!$ok) {
        if (is_array($data)) {
            $err = (string)($data['error']['message'] ?? $data['detail'] ?? $data['title'] ?? 'Thread control failed');
        } else {
            $err = 'Thread control HTTP ' . $http;
        }
    }
    return ['ok' => $ok, 'error' => $err, 'http' => $http, 'data' => $data];
}

function sk_meta_ba_connector_tool_defs(string $phoneNumberId): array {
    // Paths are relative to the connector base_url.
    unset($phoneNumberId);
    $bodyField = static function (string $type, string $desc, ?array $binding = null): array {
        $node = [
            'type'        => $type,
            'description' => $desc,
        ];
        if ($binding !== null) {
            $node['binding'] = $binding;
        }
        return $node;
    };
    $waPhone = [
        'kind'  => 'macro',
        'macro' => 'WHATSAPP_PHONE_NUMBER',
    ];
    $tool = static function (string $name, string $description, array $params, array $required = []) use ($bodyField): array {
        $paramsNode = $params;
        if ($paramsNode === []) {
            $paramsNode = new stdClass();
        }
        return [
            'name' => $name,
            'description' => $description,
            'user_auth_required' => false,
            'request_definition' => [
                'method' => 'POST',
                'path'   => '/' . $name,
                'body'   => [
                    'content_type' => 'application/json',
                    'params' => $paramsNode,
                    'required' => $required,
                ],
            ],
        ];
    };

    return [
        $tool('identify_customer', 'Find customer by phone for this shop only.', [
            'phone' => $bodyField('string', 'Customer WhatsApp phone with country code', $waPhone),
            'email' => $bodyField('string', 'Optional email'),
        ]),
        $tool('create_customer', 'Create customer if missing. Idempotent on phone.', [
            'phone' => $bodyField('string', 'Customer WhatsApp phone with country code', $waPhone),
            'name'  => $bodyField('string', 'Customer name'),
            'email' => $bodyField('string', 'Optional email'),
        ], ['phone']),
        $tool('update_customer', 'Update current-shop customer fields / delivery address.', [
            'customer_id' => $bodyField('integer', 'Customer id'),
            'name'        => $bodyField('string', 'Customer name'),
            'email'       => $bodyField('string', 'Email'),
            'address'     => $bodyField('string', 'Delivery address line'),
            'city'        => $bodyField('string', 'City'),
            'state'       => $bodyField('string', 'State'),
            'pincode'     => $bodyField('string', 'Pincode'),
            'phone'       => $bodyField('string', 'Phone', $waPhone),
        ], ['customer_id']),
        $tool('search_products', 'Search this shop catalog. Never invent products.', [
            'query'  => $bodyField('string', 'Customer words describing the product'),
            'search' => $bodyField('string', 'Alias for query'),
            'limit'  => $bodyField('integer', 'Max results (1-10)'),
        ]),
        $tool('get_product_details', 'Full product details from catalog for a known product_id.', [
            'product_id' => $bodyField('string', 'Product id from search_products'),
        ], ['product_id']),
        $tool('get_product_price', 'Current selling price from catalog.', [
            'product_id' => $bodyField('string', 'Product id'),
        ], ['product_id']),
        $tool('check_stock', 'Current stock/availability for a product.', [
            'product_id' => $bodyField('string', 'Product id'),
            'quantity'   => $bodyField('integer', 'Desired quantity'),
        ], ['product_id']),
        $tool('calculate_order_total', 'Compute totals from real product prices.', [
            'items' => $bodyField('string', 'JSON array of {product_id, quantity}'),
        ], ['items']),
        $tool('create_order', 'Create order only after customer confirmation. confirmed must be true. Pending human approval.', [
            'confirmed'        => $bodyField('boolean', 'Must be true'),
            'items'            => $bodyField('string', 'JSON array of {product_id, quantity}'),
            'customer_id'      => $bodyField('integer', 'Customer id if known'),
            'phone'            => $bodyField('string', 'Customer phone', $waPhone),
            'name'             => $bodyField('string', 'Customer name'),
            'address'          => $bodyField('string', 'Delivery address'),
            'city'             => $bodyField('string', 'City'),
            'state'            => $bodyField('string', 'State'),
            'pincode'          => $bodyField('string', 'Pincode'),
            'idempotency_key'  => $bodyField('string', 'Optional idempotency key'),
        ], ['confirmed', 'items']),
        $tool('get_order', 'Get order by id or order_number.', [
            'order_id'     => $bodyField('integer', 'Order id'),
            'order_number' => $bodyField('string', 'Order number'),
        ]),
        $tool('create_payment_link', 'Create/return a real payment link for an approved order. Never invent URLs.', [
            'order_id' => $bodyField('integer', 'Order id'),
        ], ['order_id']),
        $tool('get_payment_status', 'Provider/DB payment status. Screenshots are not proof.', [
            'order_id'   => $bodyField('integer', 'Order id'),
            'payment_id' => $bodyField('integer', 'Payment id'),
        ]),
        $tool('generate_invoice', 'Invoice only after verified paid payment.', [
            'order_id'   => $bodyField('integer', 'Order id'),
            'payment_id' => $bodyField('integer', 'Optional payment id'),
        ], ['order_id']),
        $tool('handover_to_human', 'Create human handover / route chat to inbox.', [
            'phone'    => $bodyField('string', 'Customer phone', $waPhone),
            'reason'   => $bodyField('string', 'Why handoff is needed'),
            'priority' => $bodyField('string', 'normal|high'),
            'summary'  => $bodyField('string', 'Short summary for the teammate'),
        ]),
        $tool('save_conversation_state', 'Persist sales flow state JSON for this chat.', [
            'phone' => $bodyField('string', 'Customer phone', $waPhone),
            'state' => $bodyField('string', 'JSON object of sales state'),
        ], ['state']),
        $tool('get_tenant_config', 'Current shop public business rules only.', []),
    ];
}

/**
 * Full sync: ensure connector + tools exist and store IDs on vendor_meta_agents.
 */
function sk_meta_ba_sync_connector_for_phone(string $phoneNumberId, int $vendorId = 0, ?array $settings = null): array {
    $cfg = sk_meta_ba_config_array();
    if (!sk_meta_ba_is_platform_ready()) {
        return ['ok' => false, 'error' => 'Meta Business Agent is not configured (.env META_BA_*).', 'data' => null];
    }
    if (trim((string)$cfg['connector_api_key']) === '') {
        return ['ok' => false, 'error' => 'META_BA_CONNECTOR_API_KEY is missing.', 'data' => null];
    }
    $CI =& get_instance();
    $CI->load->model('Sk_Vendor_meta_agent_model');
    $row = $CI->Sk_Vendor_meta_agent_model->get_by_phone($phoneNumberId);
    $connectorId = trim((string)($row['connector_id'] ?? ''));
    $baseUrl = rtrim($cfg['connector_base_url'], '/') . '/shopkart-api/meta-agent/connectors/' . $phoneNumberId;

    if ($connectorId === '') {
        $create = sk_meta_ba_create_connector($phoneNumberId, [
            'name'        => 'talk_ai_pilot_commerce',
            'description' => 'Shop catalog, customers, orders, payment links, and human handoff for this WhatsApp number.',
            'base_url'    => $baseUrl,
            'auth_type'   => 'API_KEY',
            'auth_config' => [
                'api_key' => [
                    'headers' => [
                        [
                            'field_name' => 'X-Api-Key',
                            'value'      => $cfg['connector_api_key'],
                            'prefix'     => '',
                        ],
                    ],
                ],
            ],
            'requires_certificate' => false,
        ], $settings);
        if (!$create['ok']) {
            return $create;
        }
        $connectorId = (string)($create['data']['id'] ?? $create['data']['connector_id'] ?? '');
        if ($connectorId === '' && is_array($create['data']['data'] ?? null)) {
            $connectorId = (string)($create['data']['data']['id'] ?? $create['data']['data']['connector_id'] ?? '');
        }
        if ($connectorId === '') {
            // Try list and pick matching name
            $listed = sk_meta_ba_list_connectors($phoneNumberId, $settings);
            if ($listed['ok'] && is_array($listed['data'])) {
                $items = $listed['data']['data'] ?? $listed['data'];
                if (is_array($items)) {
                    foreach ($items as $item) {
                        if (!is_array($item)) {
                            continue;
                        }
                        $n = strtolower((string)($item['name'] ?? ''));
                        if (strpos($n, 'talk_ai_pilot') !== false || strpos($n, 'talk ai pilot') !== false) {
                            $connectorId = (string)($item['id'] ?? $item['connector_id'] ?? '');
                            break;
                        }
                    }
                }
            }
        }
        if ($connectorId === '') {
            return ['ok' => false, 'error' => 'Connector created but no connector_id returned.', 'data' => $create['data']];
        }
        // Keep API key registration in sync with Meta (auth_config was already set at create).
        sk_meta_ba_upsert_connector_api_key($phoneNumberId, $connectorId, $cfg['connector_api_key'], $settings);
    }

    $existingTools = sk_meta_ba_list_connector_tools($phoneNumberId, $connectorId, $settings);
    $existingNames = [];
    if ($existingTools['ok'] && is_array($existingTools['data'])) {
        $items = $existingTools['data']['data'] ?? $existingTools['data'];
        if (is_array($items)) {
            foreach ($items as $t) {
                if (is_array($t) && !empty($t['name'])) {
                    $existingNames[strtolower((string)$t['name'])] = true;
                }
            }
        }
    }

    $createdTools = [];
    $failed = [];
    foreach (sk_meta_ba_connector_tool_defs($phoneNumberId) as $tool) {
        $name = strtolower((string)$tool['name']);
        if (isset($existingNames[$name])) {
            $createdTools[] = ['name' => $tool['name'], 'ok' => true, 'error' => 'exists'];
            continue;
        }
        $res = sk_meta_ba_create_connector_tool($phoneNumberId, $connectorId, $tool, $settings);
        $createdTools[] = [
            'name'  => $tool['name'],
            'ok'    => !empty($res['ok']),
            'error' => $res['error'] ?? '',
            'data'  => $res['data'] ?? null,
        ];
        if (empty($res['ok'])) {
            $failed[] = $tool['name'] . ': ' . ($res['error'] ?? 'failed');
        }
    }

    if ($failed) {
        $err = 'Tool sync failed — ' . implode('; ', $failed);
        $CI->Sk_Vendor_meta_agent_model->upsert($phoneNumberId, [
            'vendor_id'      => $vendorId,
            'connector_id'   => $connectorId,
            'sync_status'    => 'error',
            'last_error'     => $err,
            'last_synced_at' => date('Y-m-d H:i:s'),
        ]);
        return [
            'ok'    => false,
            'error' => $err,
            'data'  => [
                'connector_id'  => $connectorId,
                'tools_created' => $createdTools,
            ],
        ];
    }

    $CI->Sk_Vendor_meta_agent_model->upsert($phoneNumberId, [
        'vendor_id'      => $vendorId,
        'connector_id'   => $connectorId,
        'sync_status'    => 'synced',
        'last_error'     => '',
        'last_synced_at' => date('Y-m-d H:i:s'),
    ]);

    return [
        'ok'    => true,
        'error' => '',
        'data'  => [
            'connector_id'  => $connectorId,
            'tools_created' => $createdTools,
        ],
    ];
}

function sk_meta_ba_default_instructions(string $shopName = 'our shop'): string {
    $shopName = trim($shopName) !== '' ? trim($shopName) : 'our shop';
    return $shopName;
}
