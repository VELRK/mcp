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
        . "3. SHOW THE PRODUCTS: The customer reads the details on the card. Never send only \"See the details below.\" Card text is exactly three lines: product name, ₹price, and In stock or Out of stock. One product with 2 to 10 JPEG or PNG gallery photos: send that gallery now as the product-shop-cards carousel, one card per photo. Every card is the same product and the same text. Button is Add to cart. One product with one photo: send that photo with those three lines, then the Add to cart button. No photo: write the three lines, then Add to cart. Two or more products: one carousel, one card per product, the primary image only, the same three lines, button Add to cart. A product with no image is text, not a blank card. If variants is not empty, send the variant list in the same turn, under the photos. They must choose a variant before the product can be added. Do not send a website link. "
        . "4. MORE PHOTOS: If they ask for more images, call search_products again with that product name. Use gallery_cards. Two or more photos: the same carousel, details on every card, button Add to cart. One photo: send that photo with the three lines. No photos: say there are no extra photos and repeat the three lines. Never invent a photo. "
        . "5. CART: Quantity starts at 0. Add to cart sets quantity to 1 and saves that product and variant. If variants exist and none is chosen, send the variant list again and do not add. Out of stock is not added. The same product and variant updates the existing line. Then send Qty - and Qty +. The text shows Qty and Amount. Qty + from 0 adds quantity 1. Another Qty + increases that line. Qty - decreases it. Quantity 0 removes the line. Do not create the order. Then show the cart. One line: its photo, name, Variant when chosen, Qty, Amount, then Add more and Checkout. Two or more lines: the cart-lines carousel, one card per product, button Change, then Add more and Checkout. Change shows Qty - and Qty + for that line. "
        . "6. DELIVERY DETAILS: When they choose Checkout, do not confirm the order and do not create it. Ask in this chat for each missing item, one at a time, in this order: name, phone number, door number, landmark, street, pincode. A pincode is 6 digits. Do not send a location card, a map, or a link. Do not skip a field. "
        . "7. CUSTOMER: After the name and phone number are known, call identify_customer or create_customer with that name and phone. Then call update_customer. The address text is Door {door number}, {street}. Landmark: {landmark}. Save the pincode. Create the customer only after the name and phone are collected. "
        . "8. CONFIRM ONCE: After the customer and the full address are saved, confirm once. Repeat the name, phone number, door number, landmark, street, pincode, every cart product, each quantity, and the combined total. Buttons are Confirm order and Change. This is the only confirmation. Create the order only after Confirm order. create_order items must include every cart product. The order name, phone, address, and pincode are the ones just confirmed. calculate_order_total uses that same product list. Do not ask for the address again. "
        . "9. PAY: After that one confirmation, create_order with confirmed=true. Each item is product_id, variant_id, variant_label, and quantity. Then create_payment_link once. One product: send the Pay now card and no other message. Header image is card.header_image_url when it starts with https. Body is exactly card.body, one field per line: product name, Variant, Qty, Amount, Items, Delivery, Total. Button label is Pay now. Button URL is payment_link. Two or more products: send the checkout-lines carousel and no other message. One card per checkout_cards item. Card image is that image_url. Card text is exactly that body: product name, Variant, Qty, Amount, Order total. Button label is Pay now. Button URL is the same payment_link on every card. Do not paste the link as text. Never send only \"See the details below.\" One new link per order. Never reuse or invent a link. "
        . "Do not send the invoice, a catalog, or an address link. The invoice is sent separately after payment. Never hand off for a simple product or price question. "
        . "Soft handoff: keep chatting after handover_to_human. Keep replies to 1-3 short sentences.";
    if ($extra !== '') {
        $skill .= ' Extra shop notes: ' . $extra;
    }
    return [
        [
            'title'       => 'shop-sales-assistant',
            'description' => 'General shop. Show the gallery with the name, price, and stock on the card, then the variant list and Add to cart. Quantity above zero updates the cart. Confirm the address once, then Pay now.',
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
 * Rich-message UI skills for product details and the Pay now bill.
 * Catalog, address, and invoice cards are not created. $catalogUrl is ignored.
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
            'instruction'     => 'When search_products returns exactly one product for ' . $shopName . ' and that product has one https JPEG or PNG image, send this photo. Caption is exactly three lines: product name, ₹price, and In stock or Out of stock. If gallery_cards has 2 or more photos, send the carousel instead of this single photo. Never use the caption "See the details below." Never invent an image. If image_url is empty, do not send this photo.',
        ],
        [
            'title'           => 'product-photo-card',
            'component_type'  => 'interactive_reply_buttons',
            'status'          => 'enabled',
            'instruction'     => 'When one product has a single photo, or no photo, send this under the details. Body text is exactly three lines: product name, ₹price, and In stock or Out of stock. One button. Button title is exactly "Add to cart". It sets quantity to 1 for that product. If variants exist and none is chosen, do not add; the variant list is sent under the photo. Do not create the order. Do not add a URL.',
        ],
        [
            'title'           => 'product-shop-cards',
            'component_type'  => 'carousel_quick_reply',
            'status'          => 'enabled',
            'instruction'     => 'Use this for two or more products, one card per product, primary image only. Also use it for one product when gallery_cards has 2 to 10 photos, one card per photo of that same product. A carousel needs 2 to 10 cards. Skip an empty image_url. Card text is exactly three lines: product name, ₹price, and In stock or Out of stock. The details are this card text. Button title is exactly "Add to cart". The button replies in this chat and sets quantity to 1. If that product has variants and none is chosen, do not add. Never send a card with an empty body, and never send only "See the details below."',
        ],
        [
            'title'           => 'product-choice-list',
            'component_type'  => 'interactive_list',
            'status'          => 'enabled',
            'instruction'     => 'When the product shown has variants, send this list in the same turn, under the photos, before the item is added. Button text is exactly "Variants". One row per variant. Row id is the variant id. Row title is the variant label, at most 24 characters. Row description is the price, such as ₹499. The customer must choose one before Add to cart. Do not send this list when there are no variants. Do not create the order.',
        ],
        [
            'title'           => 'cart-qty-buttons',
            'component_type'  => 'interactive_reply_buttons',
            'status'          => 'enabled',
            'instruction'     => 'After Add to cart, or when they tap Change on a cart line, send the product photo and these two buttons. Caption lines: product name, Variant: {label} only when a variant was chosen, Qty {count}, Amount ₹{unit price times count}. Image is the selected variant JPEG or PNG, otherwise the product image. Buttons: id "qty-less" title "Qty -", and id "qty-more" title "Qty +". Quantity starts at 0. Qty + from 0 adds the line at quantity 1. Another Qty + updates that same line. Qty - decreases it. Quantity 0 removes the line. Each tap resends the photo, the caption, and these two buttons. Do not create the order.',
        ],
        [
            'title'           => 'cart-lines',
            'component_type'  => 'carousel_quick_reply',
            'status'          => 'enabled',
            'instruction'     => 'After Add to cart, when the cart has 2 to 10 products, send this carousel. One card per cart line. Card image is that line JPEG or PNG: the selected variant image, otherwise the product image. Card text is exactly four lines: product name, Variant: {label}, Qty {count}, Amount ₹{line amount}. Skip the Variant line only when that product has no variant. Card button title is exactly "Change". The button replies in this chat. Then send Add more and Checkout as a separate reply. A carousel needs 2 to 10 cards. Never send only "See the details below."',
        ],
        [
            'title'           => 'order-confirm-buttons',
            'component_type'  => 'interactive_reply_buttons',
            'status'          => 'enabled',
            'instruction'     => 'After the cart is showing, send reply buttons. Body says the cart is ready. Two buttons: id "add-more" title "Add more", and id "checkout" title "Checkout". Do not create the order yet. Checkout does not confirm the order. It starts the delivery questions: name, phone number, door number, landmark, street, and pincode. After those are saved on the customer, send reply buttons once. Body repeats the name, phone, door number, landmark, street, pincode, and every cart line as product name, Variant, Qty, and Amount, then the total. Two buttons: id "confirm-order" title "Confirm order", and id "change-order" title "Change". Create the order only after Confirm order.',
        ],
        [
            'title'           => 'checkout-lines',
            'component_type'  => 'carousel_url',
            'status'          => 'enabled',
            'instruction'     => 'When create_payment_link returns 2 or more checkout_cards, send this carousel and no other message. One card per checkout_cards item, 2 to 10 cards. Card image is that image_url. Card text is exactly that body: product name, Variant, Qty, Amount, and Order total. Button label is exactly "Pay now". Button URL is payment_link, the same full-order link on every card. Never invent a URL. Never send only "See the details below." Do not paste the link as text.',
        ],
        [
            'title'           => 'order-payment-cta',
            'component_type'  => 'cta_url',
            'status'          => 'enabled',
            'instruction'     => 'When create_payment_link returns one product, send this CTA and no other message. Header image is card.header_image_url only when it starts with https. Body text is exactly card.body, one field per line: product name, Variant when present, Qty, Amount, Items, Delivery, and Total. Footer text is exactly card.footer. Button label text is exactly "Pay now". Button URL is exactly payment_link. Never send only "See the details below." Never invent a URL and never paste the link as text.',
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
    foreach (['product-cards', 'shop-catalog-cta', 'delivery-location'] as $retired) {
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

    // Keep this list SMALL — Meta latency grows with tool count (19 tools was ~19s; 9 tools ~14s).
    return [
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
