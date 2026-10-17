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
    if (function_exists('get_instance') && get_instance() !== null) {
        $CI =& get_instance();
        $CI->config->load('meta_business_agent', true);
        $cfg = [
            'enabled'             => (bool)$CI->config->item('meta_ba_enabled', 'meta_business_agent'),
            'api_base'            => (string)$CI->config->item('meta_ba_api_base', 'meta_business_agent'),
            'api_version'         => (string)$CI->config->item('meta_ba_api_version', 'meta_business_agent'),
            'system_user_token'   => (string)$CI->config->item('meta_ba_system_user_token', 'meta_business_agent'),
            'billable_account_id' => preg_replace('/\D+/', '', (string)$CI->config->item('meta_ba_billable_account_id', 'meta_business_agent')) ?: '',
            'connector_api_key'   => (string)$CI->config->item('meta_ba_connector_api_key', 'meta_business_agent'),
            'connector_base_url'  => (string)$CI->config->item('meta_ba_connector_base_url', 'meta_business_agent'),
            'default_audience'    => (string)$CI->config->item('meta_ba_default_audience', 'meta_business_agent'),
            'handoff_message'     => (string)$CI->config->item('meta_ba_handoff_message', 'meta_business_agent'),
            'handoff_release_control' => (bool)$CI->config->item('meta_ba_handoff_release_control', 'meta_business_agent'),
        ];
    } else {
        if (!function_exists('sk_env')) {
            require_once APPPATH . 'helpers/sk_dotenv_helper.php';
            sk_dotenv_load();
        }
        $cfg = [
            'enabled'             => sk_env_bool('META_BA_ENABLED', false),
            'api_base'            => rtrim(sk_env('META_BA_API_BASE', 'https://api.facebook.com'), '/'),
            'api_version'         => (string)(sk_env('META_BA_API_VERSION', '2.0.0') ?: '2.0.0'),
            'system_user_token'   => sk_env('META_BA_SYSTEM_USER_TOKEN', ''),
            'billable_account_id' => preg_replace('/\D+/', '', (string)sk_env('META_BA_BILLABLE_ACCOUNT_ID', '')) ?: '',
            'connector_api_key'   => sk_env('META_BA_CONNECTOR_API_KEY', ''),
            'connector_base_url'  => rtrim(sk_env('META_BA_CONNECTOR_BASE_URL', ''), '/'),
            'default_audience'    => strtoupper(sk_env('META_BA_DEFAULT_AUDIENCE', 'ALLOWLISTED_ONLY')) ?: 'ALLOWLISTED_ONLY',
            'handoff_message'     => sk_env('META_BA_HANDOFF_MESSAGE', 'A teammate has been notified.'),
            'handoff_release_control' => sk_env_bool('META_BA_HANDOFF_RELEASE_CONTROL', false),
        ];
    }
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
        $cfg['connector_base_url'] = function_exists('site_url') ? rtrim(site_url(), '/') : 'https://talkaipilot.com';
    }
    return $cfg;
}

function sk_meta_ba_is_platform_ready(): bool {
    $cfg = sk_meta_ba_config_array();
    // Tenant WhatsApp tokens are per-vendor in DB; .env only needs platform connector secrets.
    return $cfg['enabled'] && $cfg['connector_api_key'] !== '';
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
    $queryKey = (string)($CI->input->get('api_key', true) ?: $CI->input->get('token', true) ?: '');
    if ($queryKey !== '') {
        return trim($queryKey);
    }
    return '';
}

/**
 * Resolve access token for Meta BA Platform calls for a phone entity.
 *
 * SaaS order: each shop uses its own WhatsApp token from vendor_whatsapp_accounts.
 * META_BA_SYSTEM_USER_TOKEN is only a last-resort platform fallback (your own numbers),
 * never a shared token for all tenants.
 */
function sk_meta_ba_access_token(string $phoneNumberId = '', ?array $settings = null): string {
    $phoneNumberId = trim($phoneNumberId);
    if (function_exists('get_instance') && get_instance() !== null) {
        $CI =& get_instance();
        if (!isset($CI->Sk_Vendor_whatsapp_account_model)) {
            $CI->load->model('Sk_Vendor_whatsapp_account_model');
        }
        if ($phoneNumberId !== '') {
            $acct = $CI->Sk_Vendor_whatsapp_account_model->get_by_phone($phoneNumberId);
            $tok = trim((string)($acct['access_token'] ?? ''));
            if ($tok !== '') {
                return $tok;
            }
            // Some rows store the long-lived token in refresh_token after Meta OAuth.
            $tok = trim((string)($acct['refresh_token'] ?? ''));
            if ($tok !== '') {
                return $tok;
            }
        }
        if (!function_exists('sk_wa_cloud_config')) {
            $CI->load->helper('sk_whatsapp_cloud');
        }
        if ($settings === null || !isset($settings['_wa_phone_number_id'])) {
            $settings = is_array($settings) ? $settings : [];
            if ($phoneNumberId !== '') {
                $settings['_wa_phone_number_id'] = $phoneNumberId;
            }
        }
        $cloud = sk_wa_cloud_config($settings);
        if (!empty($cloud['access_token'])) {
            return trim((string)$cloud['access_token']);
        }
    }
    // Platform-owned numbers only — not per-tenant SaaS credentials.
    $cfg = sk_meta_ba_config_array();
    if ($cfg['system_user_token'] !== '') {
        return $cfg['system_user_token'];
    }
    $envTok = trim((string)(getenv('META_BA_ACCESS_TOKEN') ?: getenv('WHATSAPP_CLOUD_ACCESS_TOKEN') ?: ''));
    return $envTok;
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
 * Dig nested Meta payloads for a billable / Business Agent account id.
 */
function sk_meta_ba_extract_billable_account_id($node): string {
    if (!is_array($node)) {
        return '';
    }
    $keys = [
        'billable_account_id', 'ba_account_id', 'business_agent_account_id',
        'billing_account_id', 'account_id', 'entity_id',
    ];
    foreach ($keys as $key) {
        if (!isset($node[$key])) {
            continue;
        }
        $raw = is_scalar($node[$key]) ? (string)$node[$key] : '';
        $id = preg_replace('/\D+/', '', $raw) ?: '';
        if ($id !== '' && strlen($id) >= 5) {
            return $id;
        }
    }
    foreach ($node as $value) {
        if (is_array($value)) {
            $found = sk_meta_ba_extract_billable_account_id($value);
            if ($found !== '') {
                return $found;
            }
        }
    }
    return '';
}

/**
 * True when entity_id accepts business_agent_insights (HTTP 200).
 * Meta has no list API for Billing Hub BA account IDs — we probe candidates instead.
 */
function sk_meta_ba_probe_billable_account_id(string $entityId, string $token): bool {
    $entityId = preg_replace('/\D+/', '', $entityId) ?: '';
    if ($entityId === '' || $token === '') {
        return false;
    }
    $end = time();
    $start = $end - (2 * 86400);
    $res = sk_meta_ba_http(
        'GET',
        sk_meta_ba_query_url($entityId, 'business_agent_insights', [
            'start' => (string)$start,
            'end' => (string)$end,
            'granularity' => 'DAY',
            'metric_types' => ['billable_messages'],
        ]),
        null,
        $token,
        20,
        '3.0.0'
    );
    return !empty($res['ok']) && (int)($res['http'] ?? 0) >= 200 && (int)($res['http'] ?? 0) < 300;
}

/**
 * Fill waba_id / business_id on the WhatsApp account from Graph (uses Meta Agent system token).
 *
 * @return array{ok:bool,account:array,error:string}
 */
function sk_meta_ba_enrich_whatsapp_account(array $accountRow, ?array $settings = null): array {
    $out = ['ok' => false, 'account' => $accountRow, 'error' => ''];
    $phoneNumberId = trim((string)($accountRow['phone_number_id'] ?? ''));
    if ($phoneNumberId === '') {
        $out['error'] = 'Missing phone_number_id.';
        return $out;
    }
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '') {
        $out['error'] = 'Meta access token is missing.';
        return $out;
    }
    if (!function_exists('sk_wa_meta_graph')) {
        if (function_exists('get_instance') && get_instance() !== null) {
            get_instance()->load->helper('sk_whatsapp_cloud');
        }
    }
    if (!function_exists('sk_wa_meta_graph')) {
        $out['error'] = 'Graph helper unavailable.';
        return $out;
    }

    $needWaba = trim((string)($accountRow['waba_id'] ?? '')) === '';
    $needBiz = trim((string)($accountRow['business_id'] ?? '')) === '';
    $patch = [];
    if ($needWaba || $needBiz) {
        $phone = sk_wa_meta_graph(
            'GET',
            $phoneNumberId,
            ['fields' => 'id,display_phone_number,whatsapp_business_account{id,name,owner_business_info}'],
            null,
            $token,
            $settings
        );
        if (!empty($phone['ok']) && is_array($phone['data'] ?? null)) {
            $waba = is_array($phone['data']['whatsapp_business_account'] ?? null)
                ? $phone['data']['whatsapp_business_account']
                : [];
            $wabaId = trim((string)($waba['id'] ?? ''));
            $ownerBiz = $waba['owner_business_info'] ?? null;
            if (is_array($ownerBiz)) {
                $bizId = trim((string)($ownerBiz['id'] ?? ''));
            } else {
                $bizId = trim((string)($ownerBiz ?? ''));
            }
            $bizId = preg_replace('/\D+/', '', $bizId) ?: '';
            if ($needWaba && $wabaId !== '') {
                $patch['waba_id'] = $wabaId;
            }
            if ($needBiz && $bizId !== '') {
                $patch['business_id'] = $bizId;
            }
            $display = trim((string)($phone['data']['display_phone_number'] ?? ''));
            if ($display !== '' && trim((string)($accountRow['display_phone'] ?? '')) === '') {
                $patch['display_phone'] = $display;
            }
        }
    }
    if ($needBiz && empty($patch['business_id'])) {
        $biz = sk_wa_meta_graph('GET', 'me/businesses', ['fields' => 'id,name', 'limit' => '5'], null, $token, $settings);
        $first = is_array($biz['data']['data'][0] ?? null) ? $biz['data']['data'][0] : [];
        $bizId = trim((string)($first['id'] ?? ''));
        if ($bizId !== '') {
            $patch['business_id'] = $bizId;
        }
    }

    if ($patch && function_exists('get_instance') && get_instance() !== null) {
        $CI =& get_instance();
        $rowId = (int)($accountRow['id'] ?? 0);
        if ($rowId > 0 && isset($CI->db)) {
            $patch['updated_at'] = date('Y-m-d H:i:s');
            $CI->db->where('id', $rowId)->update('vendor_whatsapp_accounts', $patch);
            $accountRow = array_merge($accountRow, $patch);
        }
    }
    $out['ok'] = true;
    $out['account'] = $accountRow;
    return $out;
}

/**
 * Candidate IDs that may be the Billing Hub Business Agent account (per WhatsApp / business).
 *
 * @return list<string>
 */
function sk_meta_ba_candidate_billable_ids(string $phoneNumberId, ?array $accountRow = null, string $token = ''): array {
    $candidates = [];
    $push = static function (string $id) use (&$candidates): void {
        $id = preg_replace('/\D+/', '', $id) ?: '';
        if ($id !== '' && !in_array($id, $candidates, true)) {
            $candidates[] = $id;
        }
    };

    // SaaS order: per-account IDs first. Global .env is last-resort only.
    if (is_array($accountRow)) {
        $push((string)($accountRow['ba_account_id'] ?? ''));
        $push((string)($accountRow['business_id'] ?? ''));
        $push((string)($accountRow['waba_id'] ?? ''));
        $push((string)($accountRow['phone_number_id'] ?? ''));
    }
    $push($phoneNumberId);

    if (function_exists('get_instance') && get_instance() !== null) {
        $CI =& get_instance();
        if (!isset($CI->Sk_Vendor_meta_agent_model)) {
            $CI->load->model('Sk_Vendor_meta_agent_model');
        }
        $agent = $CI->Sk_Vendor_meta_agent_model->get_by_phone($phoneNumberId);
        if ($agent) {
            $push((string)($agent['agent_id'] ?? ''));
            $fromJson = sk_meta_ba_extract_billable_account_id(
                json_decode((string)($agent['eligibility_json'] ?? ''), true)
            );
            $push($fromJson);
        }
    }

    if ($token !== '') {
        if (!function_exists('sk_wa_meta_graph')) {
            if (function_exists('get_instance') && get_instance() !== null) {
                get_instance()->load->helper('sk_whatsapp_cloud');
            }
        }
        if (function_exists('sk_wa_meta_graph')) {
            $biz = sk_wa_meta_graph('GET', 'me/businesses', ['fields' => 'id,name', 'limit' => '25'], null, $token, null);
            $rows = is_array($biz['data']['data'] ?? null) ? $biz['data']['data'] : [];
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $push((string)($row['id'] ?? ''));
                }
            }
        }
    }

    $cfgId = (string)(sk_meta_ba_config_array()['billable_account_id'] ?? '');
    $push($cfgId);

    $priority = [];
    if (is_array($accountRow) && trim((string)($accountRow['ba_account_id'] ?? '')) !== '') {
        $priority[] = preg_replace('/\D+/', '', (string)$accountRow['ba_account_id']) ?: '';
    }
    if (is_array($accountRow) && trim((string)($accountRow['business_id'] ?? '')) !== '') {
        $priority[] = preg_replace('/\D+/', '', (string)$accountRow['business_id']) ?: '';
    }
    if (is_array($accountRow) && trim((string)($accountRow['waba_id'] ?? '')) !== '') {
        $priority[] = preg_replace('/\D+/', '', (string)$accountRow['waba_id']) ?: '';
    }
    $ordered = [];
    foreach (array_merge($priority, $candidates) as $id) {
        $id = preg_replace('/\D+/', '', (string)$id) ?: '';
        if ($id !== '' && !in_array($id, $ordered, true)) {
            $ordered[] = $id;
        }
    }
    return $ordered;
}

/**
 * Discover Meta Business Agent billable account ID and optionally persist it.
 *
 * Meta documents that this ID cannot be listed via API; we auto-probe known IDs
 * (env, business, WABA, phone, agent) and store the first that accepts insights.
 *
 * @return array{ok:bool,account_id:string,source:string,error:string,tried:list<string>}
 */
function sk_meta_ba_discover_billable_account_id(string $phoneNumberId, ?array $accountRow = null, ?array $settings = null): array {
    $phoneNumberId = trim($phoneNumberId);
    $out = ['ok' => false, 'account_id' => '', 'source' => '', 'error' => '', 'tried' => []];
    if ($phoneNumberId === '') {
        $out['error'] = 'Missing WhatsApp phone number id.';
        return $out;
    }
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '') {
        $out['error'] = 'Meta access token is missing.';
        return $out;
    }

    // Prefer IDs already present in agent settings / onboarding payloads.
    $settingsRes = sk_meta_ba_get_settings($phoneNumberId, null, $settings);
    if (!empty($settingsRes['ok'])) {
        $fromSettings = sk_meta_ba_extract_billable_account_id($settingsRes['data'] ?? null);
        if ($fromSettings !== '' && sk_meta_ba_probe_billable_account_id($fromSettings, $token)) {
            $out['ok'] = true;
            $out['account_id'] = $fromSettings;
            $out['source'] = 'agent_settings';
            return $out;
        }
    }

    $candidates = sk_meta_ba_candidate_billable_ids($phoneNumberId, $accountRow, $token);
    foreach ($candidates as $candidate) {
        $out['tried'][] = $candidate;
        if (!sk_meta_ba_probe_billable_account_id($candidate, $token)) {
            continue;
        }
        $out['ok'] = true;
        $out['account_id'] = $candidate;
        $cfgId = (string)(sk_meta_ba_config_array()['billable_account_id'] ?? '');
        if ($candidate === $cfgId) {
            $out['source'] = 'env';
        } elseif (is_array($accountRow) && $candidate === (preg_replace('/\D+/', '', (string)($accountRow['ba_account_id'] ?? '')) ?: '')) {
            $out['source'] = 'stored';
        } elseif ($candidate === $phoneNumberId) {
            $out['source'] = 'phone_number_id';
        } elseif (is_array($accountRow) && $candidate === (preg_replace('/\D+/', '', (string)($accountRow['waba_id'] ?? '')) ?: '')) {
            $out['source'] = 'waba_id';
        } elseif (is_array($accountRow) && $candidate === (preg_replace('/\D+/', '', (string)($accountRow['business_id'] ?? '')) ?: '')) {
            $out['source'] = 'business_id';
        } else {
            $out['source'] = 'probe';
        }
        return $out;
    }

    $out['error'] = 'Meta does not expose a Business Agent account list API. '
        . 'No candidate ID accepted usage insights for this WhatsApp number. '
        . 'Paste the Billing Hub Business Agent account ID once on billing; it is stored per vendor.';
    return $out;
}

/**
 * Resolve BA billable account for a vendor WhatsApp row and store it when found.
 * Uses META_BA_SYSTEM_USER_TOKEN (same as Admin → Meta Agent) when set.
 *
 * @return array{ok:bool,account_id:string,source:string,error:string,saved:bool,tried?:list<string>}
 */
function sk_meta_ba_ensure_billable_account_id(int $vendorId, bool $force = false, ?array $settings = null): array {
    $out = ['ok' => false, 'account_id' => '', 'source' => '', 'error' => '', 'saved' => false];
    if ($vendorId < 1 || !function_exists('get_instance') || get_instance() === null) {
        $out['error'] = 'Vendor is required.';
        return $out;
    }
    $CI =& get_instance();
    if (!isset($CI->Sk_Vendor_whatsapp_account_model)) {
        $CI->load->model('Sk_Vendor_whatsapp_account_model');
    }
    if ($settings === null && isset($CI->Sk_Admin_model)) {
        $settings = $CI->Sk_Admin_model->get_settings();
    } elseif ($settings === null) {
        $CI->load->model('Sk_Admin_model');
        $settings = $CI->Sk_Admin_model->get_settings();
    }
    $account = $CI->Sk_Vendor_whatsapp_account_model->resolve_for_vendor($vendorId);
    if (!$account || trim((string)($account['phone_number_id'] ?? '')) === '') {
        $out['error'] = 'This vendor has no WhatsApp phone number yet.';
        return $out;
    }
    $enriched = sk_meta_ba_enrich_whatsapp_account($account, $settings);
    if (!empty($enriched['account'])) {
        $account = $enriched['account'];
    }
    $stored = preg_replace('/\D+/', '', (string)($account['ba_account_id'] ?? '')) ?: '';
    $token = sk_meta_ba_access_token((string)$account['phone_number_id'], $settings);
    if (!$force && $stored !== '' && ($token === '' || sk_meta_ba_probe_billable_account_id($stored, $token))) {
        $out['ok'] = true;
        $out['account_id'] = $stored;
        $out['source'] = 'stored';
        return $out;
    }

    $found = sk_meta_ba_discover_billable_account_id((string)$account['phone_number_id'], $account, $settings);
    $out['tried'] = $found['tried'] ?? [];
    if (empty($found['ok']) || trim((string)$found['account_id']) === '') {
        $out['error'] = (string)($found['error'] ?? 'Could not discover Business Agent account ID.');
        return $out;
    }
    $accountId = (string)$found['account_id'];
    $saved = $CI->Sk_Vendor_whatsapp_account_model->set_ba_account_id($vendorId, $accountId);
    $out['ok'] = true;
    $out['account_id'] = $accountId;
    $out['source'] = (string)($found['source'] ?? 'probe');
    $out['saved'] = (bool)$saved;
    return $out;
}

/**
 * Business Agent Usage Insights API.
 * GET https://api.facebook.com/{entity_id}/business_agent_insights (X-API-Version 3.0.0)
 *
 * @param array{
 *   granularity?:string,
 *   channels?:list<string>,
 *   sender_ids?:list<string>,
 *   metric_types?:list<string>,
 *   dimensions?:list<string>,
 *   phone_number_id?:string,
 *   settings?:?array
 * } $opts
 * @return array{ok:bool,http:int,error:string,usage:list<array>,totals:array{billable_messages:int,billable_tokens:int,cost:float},raw:?array}
 */
function sk_meta_ba_usage_insights(string $billableAccountId, int $startTs, int $endTs, array $opts = []): array {
    $out = [
        'ok' => false,
        'http' => 0,
        'error' => '',
        'usage' => [],
        'totals' => ['billable_messages' => 0, 'billable_tokens' => 0, 'cost' => 0.0],
        'raw' => null,
    ];
    $billableAccountId = preg_replace('/\D+/', '', $billableAccountId) ?: '';
    if ($billableAccountId === '' || $startTs < 1 || $endTs <= $startTs) {
        $out['error'] = 'Billable account ID and a valid start/end range are required.';
        return $out;
    }
    $granularity = strtoupper(trim((string)($opts['granularity'] ?? 'DAY')));
    if (!in_array($granularity, ['HOUR', 'DAY', 'MONTH'], true)) {
        $granularity = 'DAY';
    }
    $channels = array_values(array_filter(array_map('strval', (array)($opts['channels'] ?? ['whatsapp']))));
    if (!$channels) {
        $channels = ['whatsapp'];
    }
    $senderIds = array_values(array_filter(array_map('strval', (array)($opts['sender_ids'] ?? []))));
    $metricTypes = array_values(array_filter(array_map('strval', (array)($opts['metric_types'] ?? [
        'billable_messages', 'billable_tokens', 'cost',
    ]))));
    $dimensions = array_values(array_filter(array_map('strval', (array)($opts['dimensions'] ?? [
        'channel', 'sender_id',
    ]))));
    $phoneNumberId = trim((string)($opts['phone_number_id'] ?? ''));
    $settings = is_array($opts['settings'] ?? null) ? $opts['settings'] : null;
    $token = trim((string)($opts['token'] ?? ''));
    if ($token === '') {
        $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    }
    if ($token === '') {
        $out['error'] = 'Meta access token is missing for this WhatsApp number.';
        return $out;
    }

    $query = [
        'start' => (string)$startTs,
        'end' => (string)$endTs,
        'granularity' => $granularity,
        'channels' => $channels,
        'metric_types' => $metricTypes,
    ];
    if ($dimensions) {
        $query['dimensions'] = $dimensions;
    }
    if ($senderIds) {
        $query['sender_ids'] = $senderIds;
    }

    $res = sk_meta_ba_http(
        'GET',
        sk_meta_ba_query_url($billableAccountId, 'business_agent_insights', $query),
        null,
        $token,
        30,
        '3.0.0'
    );
    $out['http'] = (int)($res['http'] ?? 0);
    $out['raw'] = is_array($res['data'] ?? null) ? $res['data'] : null;
    if (empty($res['ok'])) {
        $err = trim((string)($res['error'] ?? 'request failed'));
        if ($out['http'] === 403) {
            $out['error'] = 'Meta blocked exact cost for this shop: the WhatsApp token is not a system user '
                . 'of the business that owns billable account ' . $billableAccountId . '. '
                . 'Fix: open that business in Meta Business Settings → Users → System users, add/generate a '
                . 'token with business_management for THIS business (or re-run Embedded Signup for the shop), '
                . 'then save the new token on the WhatsApp number. '
                . ($err !== '' ? '(' . $err . ')' : '');
        } elseif ($out['http'] === 404) {
            $out['error'] = 'Billable account not found. Paste the Meta Business Agent account ID from Billing Hub → Accounts.';
        } else {
            $out['error'] = $err !== '' ? $err : ('HTTP ' . $out['http']);
        }
        return $out;
    }

    $rows = is_array($res['data']['usage'] ?? null) ? $res['data']['usage'] : [];
    $usage = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $item = [
            'start' => (int)($row['start'] ?? 0),
            'end' => (int)($row['end'] ?? 0),
            'channel' => (string)($row['channel'] ?? ''),
            'sender_id' => (string)($row['sender_id'] ?? ''),
            'billable_messages' => (int)($row['billable_messages'] ?? 0),
            'billable_tokens' => (int)($row['billable_tokens'] ?? 0),
            'cost' => (float)($row['cost'] ?? 0),
        ];
        $usage[] = $item;
        $out['totals']['billable_messages'] += $item['billable_messages'];
        $out['totals']['billable_tokens'] += $item['billable_tokens'];
        $out['totals']['cost'] += $item['cost'];
    }
    $out['totals']['cost'] = round($out['totals']['cost'], 4);
    $out['usage'] = $usage;
    $out['ok'] = true;
    return $out;
}

/**
 * Meta Business Agent usage, conversation, and tool-call figures for one vendor phone.
 *
 * @param array{granularity?:string,sender_ids?:list<string>,this_number_only?:bool} $opts
 * @return array<string, mixed>
 */
function sk_meta_ba_vendor_insights(string $phoneNumberId, string $billableAccountId, string $from, string $to, ?array $settings = null, array $opts = []): array {
    $empty = [
        'ok' => false,
        'error' => '',
        'usage' => [],
        'usage_totals' => ['billable_messages' => 0, 'billable_tokens' => 0, 'cost' => 0.0],
        'conversations' => ['ai_threads' => null, 'ai_handoffs' => null],
        'tools' => [],
        'notes' => [],
        'granularity' => 'DAY',
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
    $granularity = strtoupper(trim((string)($opts['granularity'] ?? 'DAY')));
    if (!in_array($granularity, ['HOUR', 'DAY', 'MONTH'], true)) {
        $granularity = 'DAY';
    }
    $empty['granularity'] = $granularity;

    if ($granularity === 'MONTH') {
        $from = date('Y-m-01', strtotime($from));
        $to = date('Y-m-t', strtotime($to));
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
        $report['notes'][] = 'Business Agent account ID is missing. Paste it from Billing Hub → Accounts → Meta Business Agent accounts (asset_id), then Save.';
    } else {
        $senderIds = array_values(array_filter(array_map('strval', (array)($opts['sender_ids'] ?? []))));
        if (!empty($opts['this_number_only']) && $phoneNumberId !== '' && !$senderIds) {
            $senderIds = [$phoneNumberId];
        }
        $usage = sk_meta_ba_usage_insights($usageEntity, (int)$startDt->getTimestamp(), (int)$endDt->getTimestamp(), [
            'granularity' => $granularity,
            'channels' => ['whatsapp'],
            'sender_ids' => $senderIds,
            'metric_types' => ['billable_messages', 'billable_tokens', 'cost'],
            'dimensions' => ['channel', 'sender_id'],
            'phone_number_id' => $phoneNumberId,
            'settings' => $settings,
            'token' => $token,
        ]);
        if (empty($usage['ok'])) {
            $report['notes'][] = 'Usage insights: ' . (string)($usage['error'] ?? 'request failed');
        } else {
            $report['usage'] = $usage['usage'];
            $report['usage_totals'] = $usage['totals'];
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
        . "Sell whatever get_categories, search_products, and get_offers return. "
        . "Sound like a real shopkeeper: warm, short, natural. Match the customer's language (Tamil/English/Tanglish). "
        . "Never mention AI, tools, databases, APIs, or internal IDs. Selling happens only in this WhatsApp chat. Products come only from search_products. Never send a website link or external URL. "
        . "Strictly follow this menu navigation and sales flow: "
        . "STEP 1 — GREETING & MAIN MENU: When a customer says 'Hi', 'Hello', 'Hey', 'Vanakkam', 'Menu', or starts a conversation without naming a specific product, call get_main_menu and send the interactive list 'main-menu-list' (button 'View Menu') with these 5 options:\n"
        . "- 🛍️ Browse products\n- 🏷️ Offers & Deals\n- 🔎 Search for a product\n- 📦 Track my order\n- 🙋 Talk to our team\n"
        . "In your text message, greet warmly and list the 5 numbered options as fallback so the customer can tap the menu or reply with a number (1-5). "
        . "STEP 2 — BROWSE CATEGORIES (BUTTON 1): When the customer selects 'browse_products', asks to browse products, asks what you sell, or asks for categories, call get_categories. In your message, ask: 'What would you like to shop for? 😊' and present the categories from get_categories using the interactive list ('View Categories') or numbered list. When the customer picks a category, call search_products for that category and show matching products with photo, name, price in ₹, fabric/color details, and live stock. Caption is '[Product Name] - [Price]'. In text, write out the product details and ask: 'Which item and quantity would you like to order?' "
        . "STEP 3 — OFFERS & DEALS (BUTTON 2): When the customer selects 'offers_deals' or asks for discounts, deals, or promo codes, call get_offers. If active offers exist, show the discounted products with original price, discount price, and verified savings, plus active coupons. If no active offers exist, reply: 'No active offers right now 😊 Would you like to browse our products?' and offer to browse categories or return to the main menu. "
        . "STEP 4 — SEARCH FOR A PRODUCT (BUTTON 3): When the customer selects 'search_product', ask: 'What product are you looking for? 🔎 (You can share name, color, style, or fabric!)'. If the customer types a product name or keyword directly at any time, do not force them back to the menu — call search_products immediately! Show matching products with photo and price. "
        . "STEP 5 — TRACK MY ORDER (BUTTON 4): When the customer selects 'track_order' or asks about their order, call get_order_status with their phone number or order number. If an order exists, display the order number, date, status, payment status, courier tracking if available, and total. If multiple orders exist, list them and ask which one they want to track. If no orders exist, explain kindly and offer to browse products or talk to the team. "
        . "STEP 6 — TALK TO OUR TEAM (BUTTON 5): When the customer selects 'talk_to_team' or asks to speak with support/human, call handover_to_human. Reply: 'Of course 😊 I am connecting you with our team. Please wait while our team reviews your message. A teammate will join shortly!' "
        . "STEP 7 — SELECT VARIANT & QUANTITY: When the customer specifies their choice and quantity (e.g. 'Maroon, 2 pieces'), check stock and reply:\n"
        . "'Sure! 😊 [Product Name] — [Variant]\nQuantity: [Qty]\nTotal: [Total Amount]\nWould you like to add this to your cart?' "
        . "STEP 8 — CART & CHECKOUT: Once the customer confirms, call add_cart_item, then call get_cart and show the cart summary (items, quantity, total). When they are ready to checkout, collect their delivery details in this chat, one at a time: name, phone number, door number, street, landmark, pincode (6 digits). Call create_customer and update_customer. "
        . "STEP 9 — PAYMENT & ORDER CONFIRMATION: Confirm the whole basket and delivery address once. Call create_order with confirmed=true. Then call create_payment_link once. Send the total and the payment_link as plain text. The invoice is sent only after payment. "
        . "Always support Tamil, Tanglish, and English. Keep replies short (1-3 sentences) and helpful.";
    if ($extra !== '') {
        $skill .= ' Extra shop notes: ' . $extra;
    }
    return [
        sk_meta_ba_cart_management_skill_def(),
        [
            'title'       => 'shop-sales-assistant',
            'description' => 'General shop. Greeting menu (Browse, Deals, Search, Track, Support), category browsing, product photo, variant and quantity confirmation, cart checkout, and payment link.',
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
            'title'           => 'main-menu-list',
            'component_type'  => 'interactive_list',
            'status'          => 'enabled',
            'instruction'     => 'When a customer sends a greeting (such as "Hi", "Hello", "Hey", "Vanakkam", "Menu") or asks for the main menu, or when get_main_menu is called, send an interactive list message. Button text: "View Menu". Header: "' . $shopName . '". Body: "Welcome to ' . $shopName . '! How can I help you today? Please choose an option below:". Sections: "Main Menu" with 5 rows: 1) id "browse_products", title "🛍️ Browse products", description "Explore categories & catalog"; 2) id "offers_deals", title "🏷️ Offers & Deals", description "Today\'s discounts & promo codes"; 3) id "search_product", title "🔎 Search a product", description "Find by name, style, or color"; 4) id "track_order", title "📦 Track my order", description "Check order & shipment status"; 5) id "talk_to_team", title "🙋 Talk to our team", description "Chat with our support team". In text, always include a warm greeting and the 5 numbered choices as a fallback.',
        ],
        [
            'title'           => 'category-list',
            'component_type'  => 'interactive_list',
            'status'          => 'enabled',
            'instruction'     => 'When get_categories is called or customer asks to browse categories, send an interactive list drawer. Button text: "View Categories". Sections contain the categories returned by get_categories with row id as category name or id, title as category name (up to 24 chars), and description with product count.',
        ],
        [
            'title'           => 'product-choice-list',
            'component_type'  => 'interactive_list',
            'status'          => 'enabled',
            'instruction'     => 'When search_products or get_offers returns multiple products or ui is interactive_list, send an interactive list drawer. Button text: "🛍️ View Products". Sections contain the product rows with row id as SKU, title as product name (up to 24 chars) and description with price and variant/color.',
        ],
        [
            'title'           => 'product-photo',
            'component_type'  => 'image',
            'status'          => 'enabled',
            'instruction'     => 'Send the single product photo from image_url with caption format "[Product Name] - [Price]". In text, always write out the full product names and prices from the catalog response.',
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
        'product-photo-card', 'product-shop-cards',
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

        $tool('get_main_menu', 'Returns the 5 interactive main menu options (Browse products, Offers & Deals, Search, Track order, Talk to team) with interactive list payload and text fallback. Call this on greetings (Hi, Hello, Menu) or when customer asks for the menu.', []),
        $tool('get_categories', 'Returns active product categories for this shop. Call this when customer wants to browse products, asks what categories are available, or selects Browse products.', []),
        $tool('get_offers', 'Returns active discounts, deals, and coupon codes for this shop. Call this when customer asks for offers, deals, discounts, or selects Offers & Deals.', []),
        $tool('search_products', 'Search products, prices, stock, and catalog. Returns active products in this shop. For catalog inquiries, returns the available products list and the featured photo. For single product inquiries, returns that product details and photo. Always write out the product names and prices as text inside this WhatsApp chat. Do not send external website links.', [
            'query'  => $bodyField('string', 'Customer words describing the product'),
            'search' => $bodyField('string', 'Alias for query'),
            'limit'  => $bodyField('integer', 'Max results (1-10)'),
        ]),
        $tool('get_order_status', 'Check status, delivery, and courier tracking of an order. Call this when customer selects Track my order or asks about their order status. Lookup by customer phone number or order number.', [
            'phone'        => $bodyField('string', 'Customer WhatsApp phone with country code', $waPhone),
            'order_number' => $bodyField('string', 'Order reference (e.g. #G2D1001)'),
            'order_id'     => $bodyField('integer', 'Order id if known'),
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
    $connectorId = '';
    if (function_exists('get_instance')) {
        $CI =& get_instance();
        if (isset($CI->load)) {
            $CI->load->model('Sk_Vendor_meta_agent_model');
            $row = $CI->Sk_Vendor_meta_agent_model->get_by_phone($phoneNumberId);
            $connectorId = trim((string)($row['connector_id'] ?? ''));
        }
    }
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
            if (($create['http'] ?? 0) === 409 || stripos((string)($create['error'] ?? ''), 'already exists') !== false) {
                $listed = sk_meta_ba_list_connectors($phoneNumberId, $settings);
                if ($listed['ok'] && is_array($listed['data'])) {
                    $items = $listed['data']['connectors'] ?? sk_meta_ba_normalize_connector_items($listed['data']);
                    foreach ($items as $item) {
                        if (!is_array($item)) continue;
                        $n = strtolower((string)($item['name'] ?? ''));
                        if (strpos($n, 'talk_ai_pilot') !== false || strpos($n, 'talk ai pilot') !== false) {
                            $connectorId = (string)($item['id'] ?? $item['connector_id'] ?? '');
                            break;
                        }
                    }
                }
            } else {
                return $create;
            }
        } else {
            $connectorId = (string)($create['data']['id'] ?? $create['data']['connector_id'] ?? '');
            if ($connectorId === '' && is_array($create['data']['data'] ?? null)) {
                $connectorId = (string)($create['data']['data']['id'] ?? $create['data']['data']['connector_id'] ?? '');
            }
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

    if (function_exists('get_instance')) {
        $CI = get_instance();
        if ($CI && isset($CI->load)) {
            $CI->load->model('Sk_Vendor_meta_agent_model');
            $CI->Sk_Vendor_meta_agent_model->upsert($phoneNumberId, [
                'vendor_id'      => $vendorId,
                'connector_id'   => $connectorId,
                'sync_status'    => 'synced',
                'last_error'     => '',
                'last_synced_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

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

