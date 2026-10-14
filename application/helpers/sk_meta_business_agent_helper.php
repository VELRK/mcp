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
        'handoff_release_control' => (bool)$CI->config->item('meta_ba_handoff_release_control', 'meta_business_agent'),
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
    $hdr = (string)$CI->input->get_request_header('X-Api-Key', true);
    if ($hdr !== '') {
        return trim($hdr);
    }
    $hdr = (string)$CI->input->get_request_header('X-MCP-Token', true);
    if ($hdr !== '') {
        return trim($hdr);
    }
    $auth = (string)$CI->input->get_request_header('Authorization', true);
    if (stripos($auth, 'Bearer ') === 0) {
        return trim(substr($auth, 7));
    }
    return '';
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

function sk_meta_ba_http(string $method, string $url, $body = null, string $token = '', int $timeout = 30, string $apiVersion = ''): array {
    $version = trim($apiVersion) !== '' ? trim($apiVersion) : sk_meta_ba_config_array()['api_version'];
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'X-API-Version: ' . $version,
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
    if ($code === 429) {
        return 'Meta Business Agent API rate limit (1000 requests/hour per resource on this number). Wait for the hour to reset, then retry.';
    }
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

function sk_meta_ba_query_url(string $entityId, string $path, array $query): string {
    $parts = [];
    foreach ($query as $key => $value) {
        if (is_array($value)) {
            foreach ($value as $item) {
                $parts[] = rawurlencode((string)$key) . '=' . rawurlencode((string)$item);
            }
            continue;
        }
        $parts[] = rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
    }
    $url = sk_meta_ba_entity_url($entityId, $path);
    return $parts ? ($url . '?' . implode('&', $parts)) : $url;
}

/**
 * Meta Business Agent usage, conversation, and tool-call figures for one vendor phone.
 *
 * @return array<string, mixed>
 */
function sk_meta_ba_vendor_insights(string $phoneNumberId, string $billableAccountId, string $from, string $to, ?array $settings = null): array {
    $empty = [
        'ok' => false,
        'error' => '',
        'usage' => [],
        'usage_totals' => ['billable_messages' => 0, 'billable_tokens' => 0, 'cost' => 0.0],
        'conversations' => ['ai_threads' => null, 'ai_handoffs' => null],
        'tools' => [],
        'notes' => [],
    ];
    $phoneNumberId = trim($phoneNumberId);
    $billableAccountId = trim($billableAccountId);
    if ($phoneNumberId === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $empty['error'] = 'Choose a vendor with a WhatsApp phone number.';
        return $empty;
    }
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '') {
        $empty['error'] = 'Meta access token is missing.';
        return $empty;
    }

    $tzName = date_default_timezone_get() ?: 'UTC';
    try {
        $tz = new DateTimeZone($tzName);
    } catch (Exception $e) {
        $tz = new DateTimeZone('UTC');
    }
    $startDt = DateTime::createFromFormat('Y-m-d H:i:s', $from . ' 00:00:00', $tz);
    $endDt = DateTime::createFromFormat('Y-m-d H:i:s', $to . ' 00:00:00', $tz);
    if (!$startDt || !$endDt) {
        $empty['error'] = 'Invalid date range.';
        return $empty;
    }
    $endDt->modify('+1 day');
    $usageFrom = $from;
    $spanDays = (int)$startDt->diff(DateTime::createFromFormat('Y-m-d H:i:s', $to . ' 00:00:00', $tz))->days;
    if ($spanDays > 89) {
        $usageFromDt = clone $endDt;
        $usageFromDt->modify('-90 days');
        $usageFrom = $usageFromDt->format('Y-m-d');
        $startDt = DateTime::createFromFormat('Y-m-d H:i:s', $usageFrom . ' 00:00:00', $tz);
        $empty['notes'][] = 'Meta usage and conversations are limited to 90 days, so this report starts on ' . $usageFrom . '.';
    }

    $report = $empty;
    $report['ok'] = true;
    $usageEntity = $billableAccountId !== '' ? $billableAccountId : '';
    if ($usageEntity === '') {
        $report['notes'][] = 'Paste the Meta Business Agent account ID from Billing Hub to load billable messages, tokens, and charges.';
    } else {
        $usage = sk_meta_ba_http(
            'GET',
            sk_meta_ba_query_url($usageEntity, 'business_agent_insights', [
                'start' => (string)$startDt->getTimestamp(),
                'end' => (string)$endDt->getTimestamp(),
                'granularity' => 'DAY',
                'channels' => ['whatsapp'],
                'metric_types' => ['billable_messages', 'billable_tokens', 'cost'],
                'dimensions' => ['channel', 'sender_id'],
            ]),
            null,
            $token,
            30,
            '3.0.0'
        );
        if (empty($usage['ok'])) {
            $report['notes'][] = 'Usage insights: ' . (string)($usage['error'] ?? 'request failed');
        } else {
            $rows = is_array($usage['data']['usage'] ?? null) ? $usage['data']['usage'] : [];
            $report['usage'] = $rows;
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $report['usage_totals']['billable_messages'] += (int)($row['billable_messages'] ?? 0);
                $report['usage_totals']['billable_tokens'] += (int)($row['billable_tokens'] ?? 0);
                $report['usage_totals']['cost'] += (float)($row['cost'] ?? 0);
            }
            $report['usage_totals']['cost'] = round($report['usage_totals']['cost'], 4);
        }
    }

    $convTo = $to;
    $convFrom = $usageFrom;
    $conv = sk_meta_ba_http(
        'GET',
        sk_meta_ba_query_url($phoneNumberId, 'insights/conversations', [
            'start_date' => $convFrom,
            'end_date' => $convTo,
            'metrics' => ['ai_threads', 'ai_handoffs'],
        ]),
        null,
        $token,
        30,
        '2.0.0'
    );
    if (empty($conv['ok'])) {
        $report['notes'][] = 'Conversation insights: ' . (string)($conv['error'] ?? 'request failed');
    } else {
        $row = is_array($conv['data']['data'][0] ?? null) ? $conv['data']['data'][0] : [];
        $report['conversations']['ai_threads'] = isset($row['ai_threads']['count']) ? (int)$row['ai_threads']['count'] : null;
        $report['conversations']['ai_handoffs'] = isset($row['ai_handoffs']['count']) ? (int)$row['ai_handoffs']['count'] : null;
    }

    $toolFrom = $from;
    if ($spanDays > 29) {
        $toolFromDt = DateTime::createFromFormat('Y-m-d H:i:s', $to . ' 00:00:00', $tz);
        if ($toolFromDt) {
            $toolFromDt->modify('-29 days');
            $toolFrom = $toolFromDt->format('Y-m-d');
            $report['notes'][] = 'Tool call insights cover at most 30 days, from ' . $toolFrom . '.';
        }
    }
    $tools = sk_meta_ba_http(
        'GET',
        sk_meta_ba_query_url($phoneNumberId, 'insights/tool_calls', [
            'start_date' => $toolFrom,
            'end_date' => $to,
        ]),
        null,
        $token,
        30,
        '2.0.0'
    );
    if (empty($tools['ok'])) {
        $report['notes'][] = 'Tool call insights: ' . (string)($tools['error'] ?? 'request failed');
    } else {
        $report['tools'] = is_array($tools['data']['data'] ?? null) ? $tools['data']['data'] : [];
    }
    return $report;
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

/**
 * @return array<int, array<string, mixed>>
 */
function sk_meta_ba_normalize_skill_items($decoded): array {
    if (!is_array($decoded)) {
        return [];
    }
    if (isset($decoded[0]) && is_array($decoded[0])) {
        return array_values($decoded);
    }
    foreach (['data', 'skills', 'items'] as $key) {
        if (isset($decoded[$key]) && is_array($decoded[$key])) {
            $inner = $decoded[$key];
            if (isset($inner[0]) || array_key_exists(0, $inner)) {
                return array_values($inner);
            }
        }
    }
    if (isset($decoded['id'], $decoded['skill'])) {
        return [$decoded];
    }
    return [];
}

function sk_meta_ba_resolve_agent_id(string $phoneNumberId): string {
    if ($phoneNumberId === '') {
        return '';
    }
    try {
        $CI =& get_instance();
        if (!isset($CI->Sk_Vendor_meta_agent_model)) {
            $CI->load->model('Sk_Vendor_meta_agent_model');
        }
        $row = $CI->Sk_Vendor_meta_agent_model->get_by_phone($phoneNumberId);
        return trim((string)($row['agent_id'] ?? ''));
    } catch (Throwable $e) {
        return '';
    }
}

function sk_meta_ba_skills_url(string $phoneNumberId, string $skillId = '', ?string $agentId = null): string {
    $path = 'agent_config/skills';
    if ($skillId !== '') {
        $path .= '/' . rawurlencode($skillId);
    }
    $url = sk_meta_ba_entity_url($phoneNumberId, $path);
    $agentId = $agentId ?? sk_meta_ba_resolve_agent_id($phoneNumberId);
    if ($agentId !== '') {
        $url .= (strpos($url, '?') !== false ? '&' : '?') . 'agent_id=' . rawurlencode($agentId);
    }
    return $url;
}

/** Prepare title/description/skill per Meta BizAIOmniChannelSkillsRequest limits. */
function sk_meta_ba_prepare_skill_body(array $def): array {
    $title = strtolower(trim((string)($def['title'] ?? 'shop-sales-assistant')));
    $title = preg_replace('/[^a-z0-9-]+/', '-', $title) ?? $title;
    $title = trim($title, '-');
    if ($title === '') {
        $title = 'shop-sales-assistant';
    }
    if (strlen($title) > 64) {
        $title = substr($title, 0, 64);
        $title = rtrim($title, '-');
    }
    $description = mb_substr(trim((string)($def['description'] ?? '')), 0, 1024);
    $skill = mb_substr(trim((string)($def['skill'] ?? '')), 0, 20000);
    return [
        'title'       => $title,
        'description' => $description,
        'skill'       => $skill,
    ];
}

function sk_meta_ba_list_skills(string $phoneNumberId, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '') {
        return ['ok' => false, 'error' => 'Missing phone_number_id or access token.', 'data' => null];
    }
    $res = sk_meta_ba_http('GET', sk_meta_ba_skills_url($phoneNumberId), null, $token);
    if (!$res['ok']) {
        return $res;
    }
    $items = sk_meta_ba_normalize_skill_items($res['data']);
    return [
        'ok'    => true,
        'http'  => $res['http'],
        'error' => '',
        'data'  => ['skills' => $items, 'count' => count($items)],
        'raw'   => $res['raw'] ?? '',
    ];
}

function sk_meta_ba_get_skill(string $phoneNumberId, string $skillId, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '' || $skillId === '') {
        return ['ok' => false, 'error' => 'Missing skill context.', 'data' => null];
    }
    return sk_meta_ba_http('GET', sk_meta_ba_skills_url($phoneNumberId, $skillId), null, $token);
}

function sk_meta_ba_create_skill(string $phoneNumberId, array $body, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '') {
        return ['ok' => false, 'error' => 'Missing phone_number_id or access token.', 'data' => null];
    }
    $body = sk_meta_ba_prepare_skill_body($body);
    return sk_meta_ba_http('POST', sk_meta_ba_skills_url($phoneNumberId), $body, $token);
}

function sk_meta_ba_update_skill(string $phoneNumberId, string $skillId, array $body, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '' || $skillId === '') {
        return ['ok' => false, 'error' => 'Missing skill context.', 'data' => null];
    }
    $body = sk_meta_ba_prepare_skill_body($body);
    return sk_meta_ba_http('PUT', sk_meta_ba_skills_url($phoneNumberId, $skillId), $body, $token);
}

function sk_meta_ba_delete_skill(string $phoneNumberId, string $skillId, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '' || $skillId === '') {
        return ['ok' => false, 'error' => 'Missing skill context.', 'data' => null];
    }
    return sk_meta_ba_http('DELETE', sk_meta_ba_skills_url($phoneNumberId, $skillId), null, $token);
}

/**
 * Push / refresh behavioral skills (Meta no longer uses agent_instructions for this).
 * @see https://developers.facebook.com/documentation/meta-business-agent/reference/configure/agent-skills
 */
function sk_meta_ba_upsert_instructions(string $phoneNumberId, string $instructions, ?array $settings = null): array {
    return sk_meta_ba_sync_sales_skills($phoneNumberId, $instructions, $settings);
}

function sk_meta_ba_cart_management_skill_def(): array {
    return [
        'title'       => 'cart-management',
        'description' => 'Applied whenever a shopper adds something to their basket, changes a quantity, removes an item, asks what is in the basket, or asks for the total.',
        'skill'       => 'Call get_cart before every change and again after it, and describe the basket only from the most recent read. Never state a quantity, price, or total from an earlier turn. Ask how many the shopper wants before adding anything, and never assume one. Confirm which item they mean when their words match more than one product, and never guess between two candidates. Take line identifiers from the current get_cart response only. When a change is rejected because of a limit, tell the shopper the limit and what is currently in the basket, and make no partial change. When the shopper asks for the total, read the cart and report the figure it returns rather than adding the items up yourself. Read the whole basket back for confirmation before checkout and wait for an explicit yes. Do not start checkout, take payment details, or promise a delivery date in this conversation.',
    ];
}

function sk_meta_ba_sales_skill_defs(string $shopName, string $extraInstructions = ''): array {
    $shopName = trim($shopName) !== '' ? trim($shopName) : 'our shop';
    $extra = trim($extraInstructions);
    $skill = "You are the WhatsApp sales manager for {$shopName}. "
        . "This is a general ecommerce shop. Sell whatever search_products returns. Do not assume a product category. "
        . "Sound like a real shopkeeper: warm, short, natural. Match the customer's language (Tamil/English/Tanglish). "
        . "Never mention AI, tools, databases, APIs, or internal IDs. Selling happens only in this WhatsApp chat. Products come only from search_products. There is no website and no catalog page. Never send a catalog link, a product page, or any other website link. "
        . "Follow this order. "
        . "1. GREETING: If they only say hi/hello/hey/vanakkam, reply in ONE short sentence. Call NO tools. "
        . "2. PRODUCTS: On any product, price, or stock question, call search_products exactly once. The tool result is the whole catalog. Offer only those products. If it is empty, say so in one sentence. Never add a product from memory or another shop. Never invent facts. Infer typos such as donu have = do you have and amount/prise = price. "
        . "3. SHOW THE PRODUCTS: Send the clean product image. Caption is the name and the price. In text, always write out the full product name, fabric, color, specifications, and price directly in your message, and ask which size and quantity they want to add to their cart in this chat. Do not send a website link or external URL. Everything happens inside this WhatsApp chat. "
        . "4. MORE PHOTOS: If they ask for more images, call search_products again with that product name. Send each extra JPEG or PNG as its own image. No carousel and no buttons. No photos: say there are no extra photos. Never invent a photo. "
        . "5. BASKET & CART: Always call get_cart before any change and again after it. Never assume quantity 1. When adding an item, ask how many and which variant first, then call add_cart_item with sku and quantity. When setting a total, call update_cart_item with the line_id from get_cart. To remove an item, call remove_cart_item with line_id. If a discount code is given, call apply_discount_code with the code verbatim. If a change is rejected for limit or stock, report the exact limit and headroom without guessing. "
        . "6. DELIVERY DETAILS: When they say buy or checkout, confirm the basket once in plain text. Ask in this chat for each missing item, one at a time, in this order: name, phone number, door number, landmark, street, pincode. A pincode is 6 digits. Do not send a location card, a map, or a link. Do not skip a field. "
        . "7. CUSTOMER: After the name and phone number are known, call identify_customer or create_customer with that name and phone. Then call update_customer. The address text is Door {door number}, {street}. Landmark: {landmark}. Save the pincode. Create the customer only after the name and phone are collected. "
        . "8. CONFIRM ONCE: Read the whole basket back from get_cart for confirmation before checkout: every item with quantity, price, line total, subtotal, discount, item count, and note that delivery is added at checkout. Wait for an explicit yes before creating an order. Ask them to reply Confirm. Do not send confirm buttons. create_order items includes every cart product. Do not ask for the address again. "
        . "9. PAY: After that one confirmation, create_order with confirmed=true. Then create_payment_link once. Send the total and the payment_link as plain text. Do not send a Pay card, a checkout carousel, or any other designed message. One new link per order. Never reuse or invent a link. "
        . "The invoice is sent only after payment. Do not send the invoice before the payment link. Never hand off for a simple product or price question. "
        . "Soft handoff: keep chatting after handover_to_human. Keep replies to 1-3 short sentences.";
    if ($extra !== '') {
        $skill .= ' Extra shop notes: ' . $extra;
    }
    return [
        sk_meta_ba_cart_management_skill_def(),
        [
            'title'       => 'shop-sales-assistant',
            'description' => 'General shop. Show only the product image. Quantity is typed as text. Confirm the address once, send the payment link as text, and the invoice follows payment.',
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
    $managedTitles = [];
    foreach ($defs as $def) {
        $managedTitles[strtolower((string)$def['title'])] = true;
    }

    $listed = sk_meta_ba_list_skills($phoneNumberId, $settings);
    $items = [];
    if (!empty($listed['ok']) && is_array($listed['data'])) {
        $items = $listed['data']['skills'] ?? sk_meta_ba_normalize_skill_items($listed['data']);
    }
    $byTitle = [];
    $extraSkills = [];
    foreach ($items as $item) {
        if (!is_array($item) || empty($item['title'])) {
            continue;
        }
        $t = strtolower((string)$item['title']);
        if (!isset($byTitle[$t])) {
            $byTitle[$t] = $item;
        }
        if (!isset($managedTitles[$t])) {
            $extraSkills[] = [
                'id'     => (string)($item['id'] ?? ''),
                'title'  => (string)$item['title'],
                'status' => (string)($item['status'] ?? ''),
            ];
        }
    }

    $results = [];
    $failed = [];
    $warnings = [];
    if ($extraSkills) {
        $warnings[] = count($extraSkills) . ' other skill(s) on this number — Meta may apply conflicting rules. Prefer one consolidated shop-sales-assistant skill.';
    }
    foreach ($defs as $def) {
        $key = strtolower((string)$def['title']);
        $existing = $byTitle[$key] ?? null;
        if ($existing && !empty($existing['id'])) {
            $res = sk_meta_ba_update_skill($phoneNumberId, (string)$existing['id'], $def, $settings);
        } else {
            $res = sk_meta_ba_create_skill($phoneNumberId, $def, $settings);
        }
        $status = '';
        if (!empty($res['data']) && is_array($res['data'])) {
            $status = (string)($res['data']['status'] ?? '');
        }
        if ($status === 'blocked') {
            $warnings[] = $def['title'] . ' is blocked by Meta review — edit skill text (avoid sensitive PII) and sync again.';
        } elseif ($status === 'pending_review') {
            $warnings[] = $def['title'] . ' is pending_review — agent may not apply it until Meta approves.';
        }
        $results[] = [
            'title'  => $def['title'],
            'ok'     => !empty($res['ok']),
            'status' => $status,
            'error'  => $res['error'] ?? '',
            'http'   => $res['http'] ?? null,
            'data'   => $res['data'] ?? null,
        ];
        if (empty($res['ok'])) {
            $failed[] = $def['title'] . ': ' . ($res['error'] ?? 'failed');
        }
    }

    $payload = ['skills' => $results, 'listed_count' => count($items)];
    if ($extraSkills) {
        $payload['other_skills'] = $extraSkills;
    }
    if ($warnings) {
        $payload['warnings'] = $warnings;
    }

    return [
        'ok'    => !$failed,
        'error' => $failed ? ('Skill sync failed — ' . implode('; ', $failed)) : '',
        'data'  => $payload,
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function sk_meta_ba_normalize_connector_items($decoded): array {
    if (!is_array($decoded)) {
        return [];
    }
    if (isset($decoded[0]) && is_array($decoded[0])) {
        return array_values($decoded);
    }
    if (isset($decoded['data']) && is_array($decoded['data'])) {
        $inner = $decoded['data'];
        if (isset($inner[0]) || array_key_exists(0, $inner)) {
            return array_values($inner);
        }
    }
    if (isset($decoded['id'], $decoded['name'])) {
        return [$decoded];
    }
    return [];
}

/**
 * @return array<int, array<string, mixed>>
 */
function sk_meta_ba_normalize_connector_tool_items($decoded): array {
    return sk_meta_ba_normalize_connector_items($decoded);
}

function sk_meta_ba_connector_api_key_config(string $apiKey): array {
    return [
        'headers' => [
            [
                'field_name' => 'X-Api-Key',
                'value'      => $apiKey,
                'prefix'     => '',
            ],
        ],
    ];
}

function sk_meta_ba_list_connectors(string $phoneNumberId, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '') {
        return ['ok' => false, 'error' => 'Missing phone_number_id or access token.', 'data' => null];
    }
    $res = sk_meta_ba_http('GET', sk_meta_ba_entity_url($phoneNumberId, 'agent_connectors'), null, $token);
    if (!$res['ok']) {
        return $res;
    }
    $items = sk_meta_ba_normalize_connector_items($res['data']);
    return [
        'ok'    => true,
        'http'  => $res['http'],
        'error' => '',
        'data'  => ['connectors' => $items, 'count' => count($items)],
        'raw'   => $res['raw'] ?? '',
    ];
}

function sk_meta_ba_get_connector(string $phoneNumberId, string $connectorId, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '' || $connectorId === '') {
        return ['ok' => false, 'error' => 'Missing connector context.', 'data' => null];
    }
    $url = sk_meta_ba_entity_url($phoneNumberId, 'agent_connectors/' . rawurlencode($connectorId));
    return sk_meta_ba_http('GET', $url, null, $token);
}

function sk_meta_ba_update_connector(string $phoneNumberId, string $connectorId, array $body, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '' || $connectorId === '') {
        return ['ok' => false, 'error' => 'Missing connector context.', 'data' => null];
    }
    $url = sk_meta_ba_entity_url($phoneNumberId, 'agent_connectors/' . rawurlencode($connectorId));
    return sk_meta_ba_http('PUT', $url, $body, $token);
}

function sk_meta_ba_delete_connector(string $phoneNumberId, string $connectorId, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '' || $connectorId === '') {
        return ['ok' => false, 'error' => 'Missing connector context.', 'data' => null];
    }
    $url = sk_meta_ba_entity_url($phoneNumberId, 'agent_connectors/' . rawurlencode($connectorId));
    return sk_meta_ba_http('DELETE', $url, null, $token);
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
    return sk_meta_ba_http('POST', $url, [
        'api_key_config' => sk_meta_ba_connector_api_key_config($apiKey),
    ], $token);
}

function sk_meta_ba_connector_logs(
    string $phoneNumberId,
    string $connectorId,
    array $query = [],
    ?array $settings = null
): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '' || $connectorId === '') {
        return ['ok' => false, 'error' => 'Missing connector context.', 'data' => null];
    }
    $url = sk_meta_ba_entity_url($phoneNumberId, 'agent_connectors/' . rawurlencode($connectorId) . '/logs');
    $allowed = ['start_time', 'end_time', 'limit', 'tool_id', 'include_stats', 'summary_only', 'top_n'];
    $qs = [];
    foreach ($allowed as $key) {
        if (array_key_exists($key, $query) && $query[$key] !== '' && $query[$key] !== null) {
            $qs[$key] = $query[$key];
        }
    }
    if ($qs) {
        $url .= '?' . http_build_query($qs);
    }
    return sk_meta_ba_http('GET', $url, null, $token);
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
    $res = sk_meta_ba_http('GET', $url, null, $token);
    if (!$res['ok']) {
        return $res;
    }
    $items = sk_meta_ba_normalize_connector_tool_items($res['data']);
    return [
        'ok'    => true,
        'http'  => $res['http'],
        'error' => '',
        'data'  => ['tools' => $items, 'count' => count($items)],
        'raw'   => $res['raw'] ?? '',
    ];
}

function sk_meta_ba_get_connector_tool(
    string $phoneNumberId,
    string $connectorId,
    string $toolId,
    ?array $settings = null
): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '' || $connectorId === '' || $toolId === '') {
        return ['ok' => false, 'error' => 'Missing tool context.', 'data' => null];
    }
    $url = sk_meta_ba_entity_url($phoneNumberId, 'agent_connectors/' . rawurlencode($connectorId) . '/tools/' . rawurlencode($toolId));
    return sk_meta_ba_http('GET', $url, null, $token);
}

function sk_meta_ba_update_connector_tool(
    string $phoneNumberId,
    string $connectorId,
    string $toolId,
    array $tool,
    ?array $settings = null
): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '' || $connectorId === '' || $toolId === '') {
        return ['ok' => false, 'error' => 'Missing tool context.', 'data' => null];
    }
    $url = sk_meta_ba_entity_url($phoneNumberId, 'agent_connectors/' . rawurlencode($connectorId) . '/tools/' . rawurlencode($toolId));
    return sk_meta_ba_http('PUT', $url, $tool, $token);
}

function sk_meta_ba_delete_connector_tool(
    string $phoneNumberId,
    string $connectorId,
    string $toolId,
    ?array $settings = null
): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '' || $connectorId === '' || $toolId === '') {
        return ['ok' => false, 'error' => 'Missing tool context.', 'data' => null];
    }
    $url = sk_meta_ba_entity_url($phoneNumberId, 'agent_connectors/' . rawurlencode($connectorId) . '/tools/' . rawurlencode($toolId));
    return sk_meta_ba_http('DELETE', $url, null, $token);
}

function sk_meta_ba_run_connector_tool(
    string $phoneNumberId,
    string $connectorId,
    string $toolId,
    $input = null,
    ?array $settings = null
): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '' || $connectorId === '' || $toolId === '') {
        return ['ok' => false, 'error' => 'Missing tool context.', 'data' => null];
    }
    $url = sk_meta_ba_entity_url($phoneNumberId, 'agent_connectors/' . rawurlencode($connectorId) . '/tools/' . rawurlencode($toolId) . '/run');
    $body = ['input' => $input === null ? '{}' : (is_string($input) ? $input : json_encode($input, JSON_UNESCAPED_UNICODE))];
    return sk_meta_ba_http('POST', $url, $body, $token, 60);
}

function sk_meta_ba_ui_skills_url(string $phoneNumberId, string $instructionId = '', array $query = []): string {
    $path = 'agent-ui-skills';
    if ($instructionId !== '') {
        $path .= '/' . rawurlencode($instructionId);
    }
    $url = sk_meta_ba_entity_url($phoneNumberId, $path);
    $qs = [];
    foreach (['before', 'after', 'limit'] as $key) {
        if (isset($query[$key]) && $query[$key] !== '' && $query[$key] !== null) {
            $qs[$key] = $query[$key];
        }
    }
    if ($qs) {
        $url .= '?' . http_build_query($qs);
    }
    return $url;
}

function sk_meta_ba_list_ui_skills_page(string $phoneNumberId, array $query = [], ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '') {
        return ['ok' => false, 'error' => 'Missing phone_number_id or access token.', 'data' => null];
    }
    return sk_meta_ba_http('GET', sk_meta_ba_ui_skills_url($phoneNumberId, '', $query), null, $token);
}

function sk_meta_ba_list_all_ui_skills(string $phoneNumberId, ?array $settings = null, int $pageLimit = 100): array {
    $all = [];
    $after = null;
    $paging = null;
    for ($page = 0; $page < 50; $page++) {
        $query = ['limit' => max(1, min(100, $pageLimit))];
        if ($after !== null && $after !== '') {
            $query['after'] = $after;
        }
        $res = sk_meta_ba_list_ui_skills_page($phoneNumberId, $query, $settings);
        if (!$res['ok']) {
            if ($all) {
                return [
                    'ok'    => true,
                    'http'  => 200,
                    'error' => '',
                    'data'  => ['ui_skills' => $all, 'count' => count($all), 'partial' => true, 'page_error' => $res['error'] ?? ''],
                    'raw'   => '',
                ];
            }
            return $res;
        }
        $chunk = [];
        if (is_array($res['data'])) {
            $chunk = $res['data']['data'] ?? [];
            if (!is_array($chunk)) {
                $chunk = [];
            }
            $paging = $res['data']['paging'] ?? null;
        }
        foreach ($chunk as $row) {
            if (is_array($row)) {
                $all[] = $row;
            }
        }
        $next = is_array($paging) ? trim((string)($paging['next'] ?? '')) : '';
        $after = is_array($paging['cursors'] ?? null) ? trim((string)($paging['cursors']['after'] ?? '')) : '';
        if ($next === '') {
            break;
        }
        if ($chunk === [] && $after === '') {
            break;
        }
    }
    return [
        'ok'    => true,
        'http'  => 200,
        'error' => '',
        'data'  => ['ui_skills' => $all, 'count' => count($all)],
        'raw'   => '',
    ];
}

function sk_meta_ba_get_ui_skill(string $phoneNumberId, string $instructionId, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '' || $instructionId === '') {
        return ['ok' => false, 'error' => 'Missing UI skill context.', 'data' => null];
    }
    return sk_meta_ba_http('GET', sk_meta_ba_ui_skills_url($phoneNumberId, $instructionId), null, $token);
}

function sk_meta_ba_ui_component_types(): array {
    return [
        'carousel_quick_reply',
        'carousel_url',
        'cta_url',
        'flow',
        'image',
        'interactive_list',
        'interactive_reply_buttons',
        'location',
        'location_request',
    ];
}

/**
 * Build a create body for POST /{phone_number_id}/agent-ui-skills.
 * flow_id is required for flow and is omitted for every other type.
 *
 * @return array{ok:bool,error:string,body:?array}
 */
function sk_meta_ba_prepare_ui_skill_create(array $in): array {
    $title = trim((string)($in['title'] ?? ''));
    $type = trim((string)($in['component_type'] ?? ''));
    $status = trim((string)($in['status'] ?? ''));
    $instruction = trim((string)($in['instruction'] ?? ''));
    $errors = [];
    if ($title === '') {
        $errors[] = 'Title is required.';
    }
    if (!in_array($type, sk_meta_ba_ui_component_types(), true)) {
        $errors[] = 'Choose a supported component type.';
    }
    if (!in_array($status, ['enabled', 'disabled'], true)) {
        $errors[] = 'Status must be enabled or disabled.';
    }
    if ($instruction === '') {
        $errors[] = 'Instruction is required. Say when to send the component and the values to fill in.';
    }
    $body = [
        'title'          => $title,
        'component_type' => $type,
        'status'         => $status,
        'instruction'    => $instruction,
    ];
    $flowRaw = trim((string)($in['flow_id'] ?? ''));
    if ($type === 'flow') {
        if ($flowRaw === '' || !preg_match('/^\d+$/', $flowRaw) || (int)$flowRaw < 1) {
            $errors[] = 'flow_id is required when the component type is flow.';
        } else {
            $body['flow_id'] = (int)$flowRaw;
        }
    } elseif ($flowRaw !== '') {
        $errors[] = 'flow_id is only allowed when the component type is flow.';
    }
    if ($errors) {
        return ['ok' => false, 'error' => implode(' ', $errors), 'body' => null];
    }
    return ['ok' => true, 'error' => '', 'body' => $body];
}

/**
 * Build an update body. Meta accepts title, status, and instruction only.
 *
 * @return array{ok:bool,error:string,body:?array}
 */
function sk_meta_ba_prepare_ui_skill_update(array $in): array {
    $body = [];
    if (array_key_exists('title', $in)) {
        $title = trim((string)$in['title']);
        if ($title === '') {
            return ['ok' => false, 'error' => 'Title cannot be empty.', 'body' => null];
        }
        $body['title'] = $title;
    }
    if (array_key_exists('status', $in) && trim((string)$in['status']) !== '') {
        $status = trim((string)$in['status']);
        if (!in_array($status, ['enabled', 'disabled'], true)) {
            return ['ok' => false, 'error' => 'Status must be enabled or disabled.', 'body' => null];
        }
        $body['status'] = $status;
    }
    if (array_key_exists('instruction', $in)) {
        $instruction = trim((string)$in['instruction']);
        if ($instruction === '') {
            return ['ok' => false, 'error' => 'Instruction cannot be empty.', 'body' => null];
        }
        $body['instruction'] = $instruction;
    }
    if (!$body) {
        return ['ok' => false, 'error' => 'Nothing to update.', 'body' => null];
    }
    return ['ok' => true, 'error' => '', 'body' => $body];
}

function sk_meta_ba_create_ui_skill(string $phoneNumberId, array $body, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '') {
        return ['ok' => false, 'error' => 'Missing phone_number_id or access token.', 'data' => null];
    }
    $prepared = sk_meta_ba_prepare_ui_skill_create($body);
    if (empty($prepared['ok'])) {
        return ['ok' => false, 'error' => $prepared['error'], 'data' => null];
    }
    return sk_meta_ba_http('POST', sk_meta_ba_ui_skills_url($phoneNumberId), $prepared['body'], $token);
}

function sk_meta_ba_update_ui_skill(string $phoneNumberId, string $instructionId, array $body, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '' || $instructionId === '') {
        return ['ok' => false, 'error' => 'Missing UI skill context.', 'data' => null];
    }
    $prepared = sk_meta_ba_prepare_ui_skill_update($body);
    if (empty($prepared['ok'])) {
        return ['ok' => false, 'error' => $prepared['error'], 'data' => null];
    }
    return sk_meta_ba_http('PUT', sk_meta_ba_ui_skills_url($phoneNumberId, $instructionId), $prepared['body'], $token);
}

function sk_meta_ba_delete_ui_skill(string $phoneNumberId, string $instructionId, ?array $settings = null): array {
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '' || $phoneNumberId === '' || $instructionId === '') {
        return ['ok' => false, 'error' => 'Missing UI skill context.', 'data' => null];
    }
    return sk_meta_ba_http('DELETE', sk_meta_ba_ui_skills_url($phoneNumberId, $instructionId), null, $token);
}

/**
 * The only product UI skill is the image. Quantity, cart, checkout, and payment stay as text.
 * The invoice is sent after payment and is not a UI skill. $catalogUrl is ignored.
 *
 * @return array<int, array<string, mixed>>
 */
function sk_meta_ba_shop_ui_skill_defs(string $shopName, string $catalogUrl = ''): array {
    $shopName = trim($shopName) !== '' ? trim($shopName) : 'Shop';
    $defs = [
        [
            'title'           => 'product-photo',
            'component_type'  => 'image',
            'status'          => 'enabled',
            'instruction'     => 'For each product from search_products at ' . $shopName . ', send the product photo. Caption MUST be the product name and price (e.g. "Kanjivaram Silk Saree - Gold Border - ₹3"). In text, describe the product details, available sizes, and ask for quantity. If image_url is empty, do not send an image.',
        ],
    ];
    return $defs;
}

function sk_meta_ba_sync_shop_ui_skills(string $phoneNumberId, string $shopName, string $catalogUrl = '', ?array $settings = null): array {
    $defs = sk_meta_ba_shop_ui_skill_defs($shopName, $catalogUrl);
    if (!$defs) {
        return [
            'ok'    => true,
            'error' => '',
            'data'  => ['ui_skills' => [], 'skipped' => 'No catalog URL configured for UI CTA skill.'],
        ];
    }
    $listed = sk_meta_ba_list_all_ui_skills($phoneNumberId, $settings);
    $byTitle = [];
    if (!empty($listed['ok']) && is_array($listed['data']['ui_skills'] ?? null)) {
        foreach ($listed['data']['ui_skills'] as $item) {
            if (!is_array($item) || empty($item['title'])) {
                continue;
            }
            $t = strtolower((string)$item['title']);
            if (!isset($byTitle[$t])) {
                $byTitle[$t] = $item;
            }
        }
    }
    foreach ([
        'product-cards', 'shop-catalog-cta', 'delivery-location',
        'product-photo-card', 'product-shop-cards', 'product-choice-list',
        'cart-qty-buttons', 'cart-lines', 'order-confirm-buttons',
        'checkout-lines', 'order-payment-cta',
    ] as $retired) {
        if (!empty($byTitle[$retired]['id'])) {
            sk_meta_ba_delete_ui_skill($phoneNumberId, (string)$byTitle[$retired]['id'], $settings);
        }
    }
    $results = [];
    $failed = [];
    foreach ($defs as $def) {
        $key = strtolower((string)$def['title']);
        $existing = $byTitle[$key] ?? null;
        $existingType = strtolower((string)($existing['component_type'] ?? ''));
        $wantedType = strtolower((string)($def['component_type'] ?? ''));
        if ($existing && $existingType !== '' && $wantedType !== '' && $existingType !== $wantedType && !empty($existing['id'])) {
            sk_meta_ba_delete_ui_skill($phoneNumberId, (string)$existing['id'], $settings);
            $existing = null;
        }
        if ($existing && !empty($existing['id'])) {
            $res = sk_meta_ba_update_ui_skill($phoneNumberId, (string)$existing['id'], [
                'title'       => $def['title'],
                'status'      => $def['status'],
                'instruction' => $def['instruction'],
            ], $settings);
        } else {
            $res = sk_meta_ba_create_ui_skill($phoneNumberId, $def, $settings);
        }
        $results[] = [
            'title' => $def['title'],
            'ok'    => !empty($res['ok']),
            'error' => $res['error'] ?? '',
            'data'  => $res['data'] ?? null,
        ];
        if (empty($res['ok'])) {
            $failed[] = $def['title'] . ': ' . ($res['error'] ?? 'failed');
        }
    }
    return [
        'ok'    => !$failed,
        'error' => $failed ? ('UI skill sync failed — ' . implode('; ', $failed)) : '',
        'data'  => ['ui_skills' => $results],
    ];
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

function sk_meta_ba_connector_tool_defs(string $phoneNumberId, ?bool $userAuthRequired = null): array {
    // Paths are relative to the connector base_url.
    unset($phoneNumberId);
    $cfg = sk_meta_ba_config_array();
    // Only set user_auth_required to true if the connector actually has user auth injection configured.
    // Standard API_KEY connectors must have user_auth_required: false, otherwise Meta rejects tool creation with:
    // "user_auth_required is true, but the connector is missing user auth injection config".
    $cartAuth = $userAuthRequired !== null ? (bool)$userAuthRequired : !empty($cfg['user_auth_enabled']);

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
    $customTool = static function (
        string $name,
        string $description,
        string $method = 'POST',
        string $path = '',
        array $params = [],
        array $required = [],
        array $pathParams = [],
        bool $userAuthRequired = false,
        array $queryParams = []
    ) use ($bodyField): array {
        $reqDef = [
            'method' => strtoupper($method),
            'path'   => $path !== '' ? $path : ('/' . $name),
        ];
        if (!empty($pathParams)) {
            $reqDef['path_parameters'] = $pathParams;
        }
        if (!empty($queryParams)) {
            $reqDef['query_parameters'] = $queryParams;
        }
        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true) && (!empty($params) || !empty($required))) {
            $paramsNode = $params;
            if ($paramsNode === []) {
                $paramsNode = new stdClass();
            }
            $reqDef['body'] = [
                'content_type' => 'application/json',
                'params'       => $paramsNode,
                'required'     => $required,
            ];
        }
        return [
            'name'               => $name,
            'description'        => $description,
            'user_auth_required' => $userAuthRequired,
            'request_definition' => $reqDef,
        ];
    };
    $tool = static function (string $name, string $description, array $params, array $required = []) use ($customTool): array {
        return $customTool($name, $description, 'POST', '/' . $name, $params, $required, [], false);
    };

    return [
        // ── Multi-item Cart Management Tools ───────────────────────────
        $customTool(
            'get_cart',
            'Returns the current basket: every line with its line ID, product name, SKU, unit price, quantity, and line total, plus the basket subtotal, any discount applied, and the number of items. Call this before every change and again after every change. This is the only source of truth for what is in the basket; never describe the basket from memory.',
            'GET',
            '/cart',
            [],
            [],
            [],
            $cartAuth,
            [
                'customer_phone' => $bodyField('string', 'Customer phone', $waPhone),
            ]
        ),
        $customTool(
            'add_cart_item',
            'Adds units of one product to the basket and returns the updated basket. The quantity is the number of units to ADD, not the total the shopper should end up with; to set a total, use update_cart_item instead. Call get_cart first. Only call this after the shopper has stated a quantity and, where several products matched, confirmed which one they mean. The whole call is rejected if it would breach a limit, so nothing is partially added.',
            'POST',
            '/cart/items',
            [
                'sku'            => $bodyField('string', 'The SKU of the product to add, exactly as it appears in the catalog.'),
                'quantity'       => $bodyField('integer', 'How many units to add to the existing quantity. Never send the desired total here.'),
                'customer_phone' => $bodyField('string', 'Customer phone', $waPhone),
            ],
            ['sku', 'quantity'],
            [],
            $cartAuth
        ),
        $customTool(
            'update_cart_item',
            'Sets one basket line to a specific quantity and returns the updated basket. The quantity is the TOTAL the line should end up at, not an increment. Use this whenever the shopper says make it, change it to, or I only want. Call get_cart immediately before and take line_id from that response. Setting a quantity of zero is rejected; use remove_cart_item instead.',
            'PUT',
            '/cart/items/{line_id}',
            [
                'quantity'       => $bodyField('integer', 'The total quantity this line should have after the change.'),
                'customer_phone' => $bodyField('string', 'Customer phone', $waPhone),
            ],
            ['quantity'],
            [
                'line_id' => [
                    'type'        => 'string',
                    'description' => 'The line to change, taken from the get_cart response in this turn. Never reuse a line ID from an earlier turn.',
                ],
            ],
            $cartAuth
        ),
        $customTool(
            'remove_cart_item',
            'Removes one line from the basket entirely and returns the updated basket. Call get_cart immediately before and take line_id from that response. When the shopper words could match more than one line, ask which before removing. Never remove more than one line in a single turn without confirming each.',
            'DELETE',
            '/cart/items/{line_id}',
            [],
            [],
            [
                'line_id' => [
                    'type'        => 'string',
                    'description' => 'The line to remove, taken from the get_cart response in this turn.',
                ],
            ],
            $cartAuth,
            [
                'customer_phone' => $bodyField('string', 'Customer phone', $waPhone),
            ]
        ),
        $customTool(
            'apply_discount_code',
            'Applies a discount code to the basket and returns the updated basket with the discount shown, or a reason the code was not accepted. Send the code exactly as the shopper typed it, without changing its case or removing characters. Report the reason the service gives; do not explain why a code might have failed or suggest other codes.',
            'POST',
            '/cart/discount',
            [
                'code'           => $bodyField('string', 'The discount code the shopper gave, character for character as they typed it.'),
                'customer_phone' => $bodyField('string', 'Customer phone', $waPhone),
            ],
            ['code'],
            [],
            $cartAuth
        ),

        $tool('search_products', 'ONLY tool for product/price/stock, and again when they ask for more photos. Never for hi/hello. Returns this shop\'s active products. One product is one photo plus Shop now. gallery_cards are extra photos of that product. Two or more products are a reply carousel. Do not send a website link or a text list.', [
            'query'  => $bodyField('string', 'Customer words describing the product'),
            'search' => $bodyField('string', 'Alias for query'),
            'limit'  => $bodyField('integer', 'Max results (1-3)'),
        ]),
        $tool('identify_customer', 'Find customer by phone when starting an order. Not for greetings.', [
            'phone' => $bodyField('string', 'Customer WhatsApp phone with country code', $waPhone),
            'email' => $bodyField('string', 'Optional email'),
        ]),
        $tool('create_customer', 'Create the customer only after name and phone number are collected. Save the delivery address next with update_customer.', [
            'phone' => $bodyField('string', 'Customer WhatsApp phone with country code', $waPhone),
            'name'  => $bodyField('string', 'Customer name'),
            'email' => $bodyField('string', 'Optional email'),
        ], ['phone']),
        $tool('update_customer', 'Save the delivery address after the customer exists. address is Door {door number}, {street}. Landmark: {landmark}. pincode is 6 digits.', [
            'customer_id' => $bodyField('integer', 'Customer id'),
            'name'        => $bodyField('string', 'Customer name'),
            'email'       => $bodyField('string', 'Email'),
            'address'     => $bodyField('string', 'Delivery address line'),
            'city'        => $bodyField('string', 'City'),
            'state'       => $bodyField('string', 'State'),
            'pincode'     => $bodyField('string', 'Pincode'),
            'phone'       => $bodyField('string', 'Phone', $waPhone),
        ], ['customer_id']),
        $tool('calculate_order_total', 'Compute the combined total for every product in the cart before create_order.', [
            'items' => $bodyField('string', 'JSON array of {product_id, quantity}'),
        ], ['items']),
        $tool('create_order', 'Create one order only after the address was confirmed once. confirmed must be true. items includes every cart line as product_id, variant_id, variant_label, and quantity. name, phone, address, and pincode are the confirmed delivery details.', [
            'confirmed'        => $bodyField('boolean', 'Must be true'),
            'items'            => $bodyField('string', 'JSON array of {product_id, variant_id, variant_label, quantity}'),
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
        $tool('create_payment_link', 'Create one Razorpay payment link after create_order. One product uses card.body with the photo, variant, Qty, Amount, and Pay now. Two or more products use checkout_cards, one card per product, each with its image, variant, amount, and the same Pay now link. Do not paste the link as text.', [
            'order_id' => $bodyField('integer', 'Order id'),
        ], ['order_id']),
        $tool('handover_to_human', 'Notify inbox (soft handoff). Keep chatting after. Not for greetings or product asks.', [
            'phone'    => $bodyField('string', 'Customer phone', $waPhone),
            'reason'   => $bodyField('string', 'Why handoff is needed'),
            'priority' => $bodyField('string', 'normal|high'),
            'summary'  => $bodyField('string', 'Short summary for the teammate'),
        ]),
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
                'api_key' => sk_meta_ba_connector_api_key_config($cfg['connector_api_key']),
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
                $items = $listed['data']['connectors'] ?? sk_meta_ba_normalize_connector_items($listed['data']);
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
        if ($connectorId === '') {
            return ['ok' => false, 'error' => 'Connector created but no connector_id returned.', 'data' => $create['data']];
        }
    }

    if ($connectorId === '') {
        $listed = sk_meta_ba_list_connectors($phoneNumberId, $settings);
        if ($listed['ok'] && is_array($listed['data'])) {
            $items = $listed['data']['connectors'] ?? sk_meta_ba_normalize_connector_items($listed['data']);
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
    if ($connectorId === '') {
        return ['ok' => false, 'error' => 'No connector_id for this number. Run Sync tools to create the connector.', 'data' => null];
    }

    sk_meta_ba_upsert_connector_api_key($phoneNumberId, $connectorId, $cfg['connector_api_key'], $settings);

    $existingTools = sk_meta_ba_list_connector_tools($phoneNumberId, $connectorId, $settings);
    $existingByName = [];
    if ($existingTools['ok'] && is_array($existingTools['data'])) {
        $items = $existingTools['data']['tools'] ?? sk_meta_ba_normalize_connector_tool_items($existingTools['data']);
        foreach ($items as $t) {
            if (is_array($t) && !empty($t['name'])) {
                $existingByName[strtolower((string)$t['name'])] = $t;
            }
        }
    }

    $createdTools = [];
    $failed = [];
    $desiredNames = [];
    foreach (sk_meta_ba_connector_tool_defs($phoneNumberId) as $tool) {
        $name = strtolower((string)$tool['name']);
        $desiredNames[$name] = true;
        $existing = $existingByName[$name] ?? null;
        if ($existing && !empty($existing['id'])) {
            $res = sk_meta_ba_update_connector_tool(
                $phoneNumberId,
                $connectorId,
                (string)$existing['id'],
                $tool,
                $settings
            );
            $createdTools[] = [
                'name'   => $tool['name'],
                'ok'     => !empty($res['ok']),
                'action' => 'updated',
                'error'  => $res['error'] ?? '',
                'data'   => $res['data'] ?? null,
            ];
        } else {
            $res = sk_meta_ba_create_connector_tool($phoneNumberId, $connectorId, $tool, $settings);
            $createdTools[] = [
                'name'   => $tool['name'],
                'ok'     => !empty($res['ok']),
                'action' => 'created',
                'error'  => $res['error'] ?? '',
                'data'   => $res['data'] ?? null,
            ];
        }
        if (empty($res['ok'])) {
            $failed[] = $tool['name'] . ': ' . ($res['error'] ?? 'failed');
        }
    }

    // Remove extra tools left on Meta — large tool lists slow agent replies.
    $deletedTools = [];
    foreach ($existingByName as $name => $row) {
        if (isset($desiredNames[$name]) || empty($row['id'])) {
            continue;
        }
        $del = sk_meta_ba_delete_connector_tool($phoneNumberId, $connectorId, (string)$row['id'], $settings);
        $deletedTools[] = [
            'name' => $name,
            'ok'   => !empty($del['ok']),
            'error'=> $del['error'] ?? '',
        ];
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
                'tools_deleted' => $deletedTools,
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
            'tools_deleted' => $deletedTools,
        ],
    ];
}

function sk_meta_ba_default_instructions(string $shopName = 'our shop'): string {
    $shopName = trim($shopName) !== '' ? trim($shopName) : 'our shop';
    return $shopName;
}

/**
 * Dynamically generates a high-resolution, branded product card image
 * matching the exact WhatsApp card mockup (Photo, Title, Price, Sizes, Stepper, Add to Cart).
 */
function sk_meta_generate_product_card_image(array $product, ?int $variantId = null): string {
    $defaultImage = (string)($product['image_url'] ?? $product['image'] ?? $product['thumbnail'] ?? '');
    if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
        return $defaultImage;
    }

    $productId = (int)($product['id'] ?? 0);
    if ($productId <= 0) {
        return $defaultImage;
    }

    $title = trim((string)($product['name'] ?? 'Product'));
    $price = (float)($product['effective_price'] ?? $product['price'] ?? 0);
    if ($price <= 0) {
        $price = (float)($product['price'] ?? 0);
    }
    $priceText = '₹' . number_format($price, 0);

    $color = trim((string)($product['color'] ?? ''));
    $availText = 'Available' . ($color !== '' ? '  ·  ' . $color : '');

    // Sizes
    $sizes = [];
    if (!empty($product['variants']) && is_array($product['variants'])) {
        foreach ($product['variants'] as $v) {
            $lbl = trim((string)($v['label'] ?? ''));
            if ($lbl !== '' && !in_array($lbl, $sizes, true)) {
                $sizes[] = $lbl;
            }
        }
    }
    if (empty($sizes) && !empty($product['sizes'])) {
        $parts = preg_split('/[,\s\/]+/', (string)$product['sizes']);
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '' && !in_array($p, $sizes, true)) {
                $sizes[] = $p;
            }
        }
    }
    if (empty($sizes)) {
        $sizes = ['M', 'L', 'XL'];
    }
    $sizes = array_slice($sizes, 0, 4);

    $activeSize = count($sizes) >= 2 ? $sizes[1] : $sizes[0];

    // Determine local photo path on disk
    $photoPath = '';
    $rawPhoto = trim((string)($product['thumbnail'] ?? $product['image'] ?? ''));
    if ($rawPhoto !== '') {
        $candidate = defined('FCPATH') ? (FCPATH . ltrim($rawPhoto, '/\\')) : $rawPhoto;
        if (file_exists($candidate)) {
            $photoPath = $candidate;
        }
    }
    if ($photoPath === '' && !empty($product['image_url'])) {
        $parsed = parse_url($product['image_url']);
        if (!empty($parsed['path'])) {
            $local = defined('FCPATH') ? (FCPATH . ltrim($parsed['path'], '/\\')) : '';
            if ($local !== '' && file_exists($local)) {
                $photoPath = $local;
            }
        }
    }
    // Fallback sample image if none exists
    if ($photoPath === '' && defined('FCPATH')) {
        $fallback = FCPATH . 'assets/aquiry/images/apps/ecommrece/jacket.png';
        if (file_exists($fallback)) {
            $photoPath = $fallback;
        }
    }

    $mtime = !empty($product['updated_at']) ? strtotime($product['updated_at']) : (file_exists($photoPath) ? filemtime($photoPath) : 1);
    $cacheName = "card_{$productId}_" . ($variantId ?: 0) . "_{$mtime}.png";
    $cacheRel = 'assets/uploads/product_cards/' . $cacheName;
    $cacheFull = defined('FCPATH') ? (FCPATH . $cacheRel) : $cacheRel;

    if (file_exists($cacheFull) && filesize($cacheFull) > 1000) {
        $url = function_exists('base_url') ? base_url($cacheRel) : ('/' . $cacheRel);
        return function_exists('sk_mcp_tool_whatsapp_image_url') ? sk_mcp_tool_whatsapp_image_url($url) : $url;
    }

    $cacheDir = dirname($cacheFull);
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }

    $fontBold = defined('FCPATH') ? (FCPATH . 'assets/fonts/font-bold.ttf') : 'assets/fonts/font-bold.ttf';
    $fontRegular = defined('FCPATH') ? (FCPATH . 'assets/fonts/font-regular.ttf') : 'assets/fonts/font-regular.ttf';
    $hasTTF = file_exists($fontBold) && function_exists('imagettftext');

    $W = 820;
    $H = 500;
    $img = imagecreatetruecolor($W, $H);
    imagealphablending($img, true);
    imagesavealpha($img, true);

    $bgChat = imagecolorallocate($img, 248, 250, 252);
    imagefilledrectangle($img, 0, 0, $W, $H, $bgChat);

    $cardBg = imagecolorallocate($img, 255, 255, 255);
    $cardBorder = imagecolorallocate($img, 226, 232, 240);
    $photoBg = imagecolorallocate($img, 241, 245, 249);
    $textDark = imagecolorallocate($img, 15, 23, 42);
    $textGreen = imagecolorallocate($img, 5, 150, 105);
    $btnGreen = imagecolorallocate($img, 4, 120, 87);
    $textMuted = imagecolorallocate($img, 100, 116, 139);
    $pillBorder = imagecolorallocate($img, 203, 213, 225);
    $stepperBg = imagecolorallocate($img, 241, 245, 249);
    $white = imagecolorallocate($img, 255, 255, 255);

    $drawRounded = static function ($im, $x, $y, $w, $h, $r, $col, $filled = true) {
        if ($filled) {
            imagefilledrectangle($im, $x + $r, $y, $x + $w - $r, $y + $h, $col);
            imagefilledrectangle($im, $x, $y + $r, $x + $w, $y + $h - $r, $col);
            imagefilledellipse($im, $x + $r, $y + $r, $r * 2, $r * 2, $col);
            imagefilledellipse($im, $x + $w - $r, $y + $r, $r * 2, $r * 2, $col);
            imagefilledellipse($im, $x + $r, $y + $h - $r, $r * 2, $r * 2, $col);
            imagefilledellipse($im, $x + $w - $r, $y + $h - $r, $r * 2, $r * 2, $col);
        } else {
            imageline($im, $x + $r, $y, $x + $w - $r, $y, $col);
            imageline($im, $x + $r, $y + $h, $x + $w - $r, $y + $h, $col);
            imageline($im, $x, $y + $r, $x, $y + $h - $r, $col);
            imageline($im, $x + $w, $y + $r, $x + $w, $y + $h - $r, $col);
            imagearc($im, $x + $r, $y + $r, $r * 2, $r * 2, 180, 270, $col);
            imagearc($im, $x + $w - $r, $y + $r, $r * 2, $r * 2, 270, 360, $col);
            imagearc($im, $x + $r, $y + $h - $r, $r * 2, $r * 2, 90, 180, $col);
            imagearc($im, $x + $w - $r, $y + $h - $r, $r * 2, $r * 2, 0, 90, $col);
        }
    };

    // Outer card
    $cardX = 16; $cardY = 16; $cardW = $W - 32; $cardH = $H - 32;
    $drawRounded($img, $cardX, $cardY, $cardW, $cardH, 24, $cardBg, true);
    $drawRounded($img, $cardX, $cardY, $cardW, $cardH, 24, $cardBorder, false);

    // Left photo box
    $pBoxX = $cardX + 24; $pBoxY = $cardY + 24; $pBoxW = 320; $pBoxH = $cardH - 48;
    $drawRounded($img, $pBoxX, $pBoxY, $pBoxW, $pBoxH, 20, $photoBg, true);

    if ($photoPath !== '' && file_exists($photoPath)) {
        $info = @getimagesize($photoPath);
        if ($info) {
            $src = null;
            if ($info[2] === IMAGETYPE_JPEG) $src = @imagecreatefromjpeg($photoPath);
            elseif ($info[2] === IMAGETYPE_PNG) $src = @imagecreatefrompng($photoPath);
            elseif ($info[2] === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($photoPath);

            if ($src) {
                $sw = imagesx($src);
                $sh = imagesy($src);
                $maxW = $pBoxW - 24;
                $maxH = $pBoxH - 24;
                $scale = min($maxW / max(1, $sw), $maxH / max(1, $sh));
                $dw = (int)($sw * $scale);
                $dh = (int)($sh * $scale);
                $dx = $pBoxX + (int)(($pBoxW - $dw) / 2);
                $dy = $pBoxY + (int)(($pBoxH - $dh) / 2);
                imagecopyresampled($img, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
                imagedestroy($src);
            }
        }
    }

    // Right side content
    $contentX = $pBoxX + $pBoxW + 36;
    $currY = $cardY + 54;

    if ($hasTTF) {
        if (function_exists('mb_substr') && mb_strlen($title) > 28) {
            $title = mb_substr($title, 0, 26) . '...';
        }
        imagettftext($img, 20, 0, $contentX, $currY, $textDark, $fontBold, $title);

        $currY += 46;
        imagettftext($img, 26, 0, $contentX, $currY, $textGreen, $fontBold, $priceText);

        $currY += 34;
        imagettftext($img, 13, 0, $contentX, $currY, $textMuted, $fontRegular, $availText);

        $currY += 46;
        imagettftext($img, 13, 0, $contentX, $currY, $textDark, $fontBold, 'Size');

        // Size pills
        $pillY = $currY + 12;
        $pillX = $contentX;
        $pillW = 56;
        $pillH = 40;
        $pillR = 18;

        foreach ($sizes as $s) {
            $isActive = (strcasecmp($s, $activeSize) === 0);
            if ($isActive) {
                $drawRounded($img, $pillX, $pillY, $pillW, $pillH, $pillR, $btnGreen, true);
                $textColor = $white;
            } else {
                $drawRounded($img, $pillX, $pillY, $pillW, $pillH, $pillR, $cardBg, true);
                $drawRounded($img, $pillX, $pillY, $pillW, $pillH, $pillR, $pillBorder, false);
                $textColor = $textDark;
            }
            $box = imagettfbbox(13, 0, $fontBold, $s);
            $tw = abs($box[4] - $box[0]);
            $th = abs($box[5] - $box[1]);
            $tx = $pillX + (int)(($pillW - $tw) / 2);
            $ty = $pillY + (int)(($pillH + $th) / 2) - 2;
            imagettftext($img, 13, 0, $tx, $ty, $textColor, $fontBold, $s);

            $pillX += $pillW + 14;
        }

        // Stepper: [ - ] 1 [ + ]
        $stepY = $pillY + $pillH + 20;
        $stepW = 160;
        $stepH = 42;
        $stepR = 21;
        $stepX = $contentX + 30;
        $drawRounded($img, $stepX, $stepY, $stepW, $stepH, $stepR, $stepperBg, true);

        imagettftext($img, 17, 0, $stepX + 22, $stepY + 28, $btnGreen, $fontBold, '−');
        imagettftext($img, 15, 0, $stepX + 74, $stepY + 28, $textDark, $fontBold, '1');
        imagettftext($img, 17, 0, $stepX + 124, $stepY + 29, $btnGreen, $fontBold, '+');

        // Add to Cart Button
        $btnY = $stepY + $stepH + 18;
        $btnW = 260;
        $btnH = 50;
        $btnR = 25;
        $btnX = $contentX + (int)(($W - $contentX - 40 - $btnW) / 2);
        if ($btnX < $contentX) $btnX = $contentX;

        $drawRounded($img, $btnX, $btnY, $btnW, $btnH, $btnR, $btnGreen, true);

        $box = imagettfbbox(14, 0, $fontBold, 'Add to Cart');
        $tw = abs($box[4] - $box[0]);
        $cartX = $btnX + (int)(($btnW - $tw - 30) / 2);

        // Shopping cart icon
        imageline($img, $cartX, $btnY + 20, $cartX + 6, $btnY + 20, $white);
        imageline($img, $cartX + 6, $btnY + 20, $cartX + 11, $btnY + 32, $white);
        imageline($img, $cartX + 11, $btnY + 32, $cartX + 24, $btnY + 32, $white);
        imageline($img, $cartX + 24, $btnY + 32, $cartX + 28, $btnY + 22, $white);
        imageline($img, $cartX + 8, $btnY + 22, $cartX + 28, $btnY + 22, $white);
        imagefilledellipse($img, $cartX + 13, $btnY + 36, 4, 4, $white);
        imagefilledellipse($img, $cartX + 22, $btnY + 36, 4, 4, $white);

        imagettftext($img, 14, 0, $cartX + 34, $btnY + 32, $white, $fontBold, 'Add to Cart');
    }

    imagepng($img, $cacheFull);
    imagedestroy($img);

    $url = function_exists('base_url') ? base_url($cacheRel) : ('/' . $cacheRel);
    return function_exists('sk_mcp_tool_whatsapp_image_url') ? sk_mcp_tool_whatsapp_image_url($url) : $url;
}

