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

function sk_meta_ba_http(string $method, string $url, array $body = null, string $token = '', int $timeout = 30): array {
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
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
            $error = (string)($decoded['error']['message'] ?? $decoded['message'] ?? $decoded['error'] ?? '');
        }
        if ($error === '') {
            $error = 'HTTP ' . $code;
        }
    }
    return [
        'ok'    => $ok,
        'http'  => $code,
        'error' => $error,
        'data'  => is_array($decoded) ? $decoded : null,
        'raw'   => (string)$raw,
    ];
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
    return sk_meta_ba_http('POST', sk_meta_ba_entity_url($phoneNumberId, 'agent_onboarding'), [], $token);
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

function sk_meta_ba_upsert_instructions(string $phoneNumberId, string $instructions, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '') {
        return ['ok' => false, 'error' => 'Missing phone_number_id or access token.', 'data' => null];
    }
    return sk_meta_ba_http(
        'POST',
        sk_meta_ba_entity_url($phoneNumberId, 'agent_instructions'),
        ['instructions' => $instructions],
        $token
    );
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
    $body = ['message' => $message];
    if ($conversationId) {
        $body['conversation_id'] = $conversationId;
    }
    return sk_meta_ba_http('POST', sk_meta_ba_entity_url($phoneNumberId, 'agent_test'), $body, $token);
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
    if ($token === '' || $phoneNumberId === '' || trim($to) === '') {
        return ['ok' => false, 'error' => 'Missing phone_number_id, access token, or consumer phone.', 'data' => null];
    }
    $cfg = sk_meta_ba_config_array();
    // Prefer documented Cloud-style path; fall back to entity-relative if needed by callers.
    $url = rtrim($cfg['api_base'], '/') . '/business/whatsapp/phone_numbers/'
        . rawurlencode($phoneNumberId) . '/thread_control';
    $body = [
        'messaging_product' => 'whatsapp',
        'action'            => $action,
        'to'                => preg_replace('/\D+/', '', $to),
    ];
    return sk_meta_ba_http('POST', $url, $body, $token);
}

function sk_meta_ba_connector_tool_defs(string $phoneNumberId): array {
    $cfg = sk_meta_ba_config_array();
    $base = rtrim($cfg['connector_base_url'], '/') . '/shopkart-api/meta-agent/connectors/' . rawurlencode($phoneNumberId);
    $stringParam = static function (string $desc, bool $required = true): array {
        return [
            'type'        => 'string',
            'description' => $desc,
            'required'    => $required,
        ];
    };
    $intParam = static function (string $desc, bool $required = true): array {
        return [
            'type'        => 'integer',
            'description' => $desc,
            'required'    => $required,
        ];
    };

    return [
        [
            'name'        => 'search_products',
            'description' => 'Search this shop catalog by product name, color, or size when a customer asks what is available.',
            'user_auth_required' => false,
            'request_definition' => [
                'method' => 'POST',
                'path'   => $base . '/search_products',
                'body'   => [
                    'params' => [
                        'query' => $stringParam('Customer words describing the product'),
                    ],
                ],
            ],
        ],
        [
            'name'        => 'check_stock',
            'description' => 'Check whether a known product_id is in stock for this shop.',
            'user_auth_required' => false,
            'request_definition' => [
                'method' => 'POST',
                'path'   => $base . '/check_stock',
                'body'   => [
                    'params' => [
                        'product_id' => $intParam('Product id from search_products'),
                        'variant_id' => $intParam('Optional variant id', false),
                    ],
                ],
            ],
        ],
        [
            'name'        => 'get_order_status',
            'description' => 'Look up order status by order_id for this shop.',
            'user_auth_required' => false,
            'request_definition' => [
                'method' => 'POST',
                'path'   => $base . '/get_order_status',
                'body'   => [
                    'params' => [
                        'order_id' => $intParam('Numeric order id'),
                    ],
                ],
            ],
        ],
        [
            'name'        => 'get_delivery_status',
            'description' => 'Look up delivery/shipment status by order_id for this shop.',
            'user_auth_required' => false,
            'request_definition' => [
                'method' => 'POST',
                'path'   => $base . '/get_delivery_status',
                'body'   => [
                    'params' => [
                        'order_id' => $intParam('Numeric order id'),
                    ],
                ],
            ],
        ],
        [
            'name'        => 'human_handoff',
            'description' => 'Hand the WhatsApp conversation to a human teammate in the Talk AI Pilot inbox.',
            'user_auth_required' => false,
            'request_definition' => [
                'method' => 'POST',
                'path'   => $base . '/human_handoff',
                'body'   => [
                    'params' => [
                        'phone'  => $stringParam('Customer WhatsApp phone with country code'),
                        'reason' => $stringParam('Why handoff is needed', false),
                    ],
                ],
            ],
        ],
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
    $CI =& get_instance();
    $CI->load->model('Sk_Vendor_meta_agent_model');
    $row = $CI->Sk_Vendor_meta_agent_model->get_by_phone($phoneNumberId);
    $connectorId = trim((string)($row['connector_id'] ?? ''));

    if ($connectorId === '') {
        $create = sk_meta_ba_create_connector($phoneNumberId, [
            'name'        => 'Talk AI Pilot Commerce',
            'description' => 'Shop catalog, stock, order status, delivery, and human handoff for this WhatsApp number.',
            'base_url'    => rtrim($cfg['connector_base_url'], '/') . '/shopkart-api/meta-agent/connectors/' . $phoneNumberId,
            'auth_type'   => 'API_KEY',
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
                        if (stripos((string)($item['name'] ?? ''), 'Talk AI Pilot') !== false) {
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
    foreach (sk_meta_ba_connector_tool_defs($phoneNumberId) as $tool) {
        $name = strtolower((string)$tool['name']);
        if (isset($existingNames[$name])) {
            continue;
        }
        $res = sk_meta_ba_create_connector_tool($phoneNumberId, $connectorId, $tool, $settings);
        $createdTools[] = [
            'name' => $tool['name'],
            'ok'   => !empty($res['ok']),
            'error'=> $res['error'] ?? '',
        ];
    }

    $CI->Sk_Vendor_meta_agent_model->upsert($phoneNumberId, [
        'vendor_id'    => $vendorId,
        'connector_id' => $connectorId,
        'sync_status'  => 'synced',
        'last_error'   => '',
        'last_synced_at' => date('Y-m-d H:i:s'),
    ]);

    return [
        'ok'   => true,
        'error'=> '',
        'data' => [
            'connector_id'  => $connectorId,
            'tools_created' => $createdTools,
        ],
    ];
}

function sk_meta_ba_default_instructions(string $shopName = 'our shop'): string {
    $shopName = trim($shopName) !== '' ? trim($shopName) : 'our shop';
    return "You are the WhatsApp sales assistant for {$shopName}. "
        . "Be concise, friendly, and accurate. Use connector tools for products, stock, orders, and delivery. "
        . "Never invent prices or stock. If the customer asks for a human, call human_handoff. "
        . "Respond in the customer's language.";
}
