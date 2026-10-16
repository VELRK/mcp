<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Minimal Meta Product Catalog sync for the local ecommerce catalog.
 *
 * Flow:
 * 1) Resolve an active WhatsApp vendor account for the vendor.
 * 2) Ensure a Meta product catalog exists and is linked to that WABA.
 * 3) Upsert products to that catalog from the local product table.
 * 4) Or delete rows when products are removed.
 *
 * This is intentionally small and safe: it keeps the catalog sourced from the
 * application database and does not bypass the existing ecommerce logic.
 */

if (!function_exists('sk_meta_ba_http')) {
    require_once APPPATH . 'helpers/sk_meta_business_agent_helper.php';
}

function sk_meta_catalog_http(string $method, string $url, string $token, $body = null, int $timeout = 30): array {
    $method = strtoupper($method);
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
    ];
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(15, max(5, $timeout)),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => $method,
    ];
    if ($body !== null) {
        $bodyJson = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $opts[CURLOPT_POSTFIELDS] = $bodyJson;
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err !== '') {
        return ['ok' => false, 'http' => $http, 'error' => $err, 'data' => null, 'raw' => ''];
    }
    $decoded = json_decode((string)$raw, true);
    $ok = $http >= 200 && $http < 300;
    $error = '';
    if (!$ok && is_array($decoded)) {
        $error = (string)($decoded['error']['message'] ?? $decoded['error']['type'] ?? $decoded['message'] ?? $decoded['detail'] ?? 'Meta catalog request failed');
    }
    return [
        'ok'    => $ok,
        'http'  => $http,
        'error' => $error,
        'data'  => is_array($decoded) ? $decoded : null,
        'raw'   => (string)$raw,
    ];
}

function sk_meta_catalog_error_message(string $operation, array $response): string {
    $message = trim((string)($response['error'] ?? 'Meta request failed.'));
    $metaError = is_array($response['data']['error'] ?? null) ? $response['data']['error'] : [];
    $code = trim((string)($metaError['code'] ?? ''));
    $subcode = trim((string)($metaError['error_subcode'] ?? ''));
    $userMsg = trim((string)($metaError['error_user_msg'] ?? $metaError['error_user_title'] ?? ''));
    if ($message === '' && !empty($metaError['message'])) {
        $message = trim((string)$metaError['message']);
    }
    $http = (int)($response['http'] ?? 0);
    $details = [];
    if ($http > 0) {
        $details[] = 'HTTP ' . $http;
    }
    if ($code !== '') {
        $details[] = 'Meta code ' . $code . ($subcode !== '' ? '/' . $subcode : '');
    }
    $error = $operation . ' failed' . ($details ? ' (' . implode(', ', $details) . ')' : '') . ': ' . ($message !== '' ? $message : 'Meta request failed.');
    if ($userMsg !== '' && stripos($error, $userMsg) === false) {
        $error .= ' ' . $userMsg;
    }
    if ($code === '10' || $code === '200' || stripos($message, 'permission') !== false || stripos($userMsg, 'admin') !== false) {
        $error .= ' Token scopes like catalog_management are not enough — the token user must be an Admin of that Business Manager.';
    }
    return $error;
}

function sk_meta_catalog_resolve_phone_for_vendor(int $vendorId): ?string {
    if ($vendorId < 1) {
        return null;
    }
    if (function_exists('get_instance') && get_instance() !== null) {
        $CI =& get_instance();
        if (!isset($CI->Sk_Vendor_whatsapp_account_model)) {
            $CI->load->model('Sk_Vendor_whatsapp_account_model');
        }
        $result = $CI->Sk_Vendor_whatsapp_account_model->resolve_for_vendor($vendorId);
        $phone = trim((string)($result['phone_number_id'] ?? ''));
        if ($phone !== '') {
            return $phone;
        }
    }
    return null;
}

/**
 * Tokens for catalog create/list need catalog_management (+ business asset access).
 * Prefer the Meta system-user token; fall back to the vendor WhatsApp Cloud token.
 *
 * @return list<string>
 */
function sk_meta_catalog_candidate_tokens(int $vendorId, string $phoneNumberId, ?array $settings = null): array {
    $tokens = [];
    $system = '';
    if (function_exists('sk_meta_ba_access_token')) {
        $system = trim((string)sk_meta_ba_access_token($phoneNumberId, $settings));
    }
    if ($system !== '') {
        $tokens[] = $system;
    }
    $vendorId = (int)$vendorId;
    $phoneNumberId = trim($phoneNumberId);
    if ($vendorId > 0 && $phoneNumberId !== '' && function_exists('get_instance') && get_instance() !== null) {
        $CI =& get_instance();
        if (!isset($CI->Sk_Vendor_whatsapp_account_model)) {
            $CI->load->model('Sk_Vendor_whatsapp_account_model');
        }
        $account = $CI->Sk_Vendor_whatsapp_account_model->get_by_phone($phoneNumberId, true);
        if ((int)($account['vendor_id'] ?? 0) === $vendorId) {
            $vendorTok = trim((string)($account['access_token'] ?? ''));
            if ($vendorTok !== '' && !in_array($vendorTok, $tokens, true)) {
                $tokens[] = $vendorTok;
            }
        }
    }
    return $tokens;
}

function sk_meta_catalog_access_token(int $vendorId, string $phoneNumberId, ?array $settings = null): string {
    $tokens = sk_meta_catalog_candidate_tokens($vendorId, $phoneNumberId, $settings);
    return $tokens[0] ?? '';
}

function sk_meta_catalog_permission_hint(string $operationError = ''): string {
    $hint = ' Fix in Meta Business Manager: open the WhatsApp number’s owning business → Users → System users'
        . ' → assign this system user as Admin (or Catalog admin) on that business, then Sync again.'
        . ' Or as a business Admin create one Product Catalog in Commerce Manager, link it to WhatsApp, then Sync will update products.';
    return trim($operationError . $hint);
}

function sk_meta_catalog_store_catalog_id(int $vendorId, string $phoneNumberId, string $catalogId, ?string $catalogName = null): void {
    if ($vendorId < 1 || trim($phoneNumberId) === '' || trim($catalogId) === '') {
        return;
    }
    if (function_exists('get_instance') && get_instance() !== null) {
        $CI =& get_instance();
        if (!isset($CI->Sk_Vendor_meta_agent_model)) {
            $CI->load->model('Sk_Vendor_meta_agent_model');
        }
        $CI->Sk_Vendor_meta_agent_model->ensure_schema();
        $row = $CI->Sk_Vendor_meta_agent_model->get_by_phone($phoneNumberId);
        $payload = [
            'catalog_id' => trim($catalogId),
            'catalog_name' => trim((string)($catalogName !== null ? $catalogName : ($row['catalog_name'] ?? ''))),
            'last_synced_at' => date('Y-m-d H:i:s'),
            'sync_status' => 'active',
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($row) {
            $CI->db->where('id', (int)$row['id'])->update('vendor_meta_agents', $payload);
        } else {
            $CI->db->insert('vendor_meta_agents', [
                'vendor_id'       => $vendorId,
                'phone_number_id' => $phoneNumberId,
                'catalog_id'      => trim($catalogId),
                'catalog_name'    => trim((string)($catalogName ?? '')),
                'sync_status'     => 'active',
                'onboarded'      => 1,
                'eligible'        => 1,
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
                'last_synced_at'  => date('Y-m-d H:i:s'),
            ]);
        }
    }
}

function sk_meta_catalog_pick_first_catalog(array $listResponse, string $preferredName = ''): array {
    $rows = $listResponse['data']['data'] ?? [];
    if (!is_array($rows)) {
        return ['id' => '', 'name' => ''];
    }
    $preferredName = trim($preferredName);
    $fallback = ['id' => '', 'name' => ''];
    foreach ($rows as $catalog) {
        if (!is_array($catalog)) {
            continue;
        }
        $candidateId = trim((string)($catalog['id'] ?? ''));
        if ($candidateId === '') {
            continue;
        }
        $candidateName = trim((string)($catalog['name'] ?? ''));
        $picked = ['id' => $candidateId, 'name' => $candidateName];
        if ($fallback['id'] === '') {
            $fallback = $picked;
        }
        if ($preferredName !== '' && strcasecmp($candidateName, $preferredName) === 0) {
            return $picked;
        }
    }
    return $fallback;
}

/** Catalogs assigned to the current token user (system user), e.g. VelAi Catalog. */
function sk_meta_catalog_list_assigned(string $token): array {
    $token = trim($token);
    if ($token === '') {
        return ['ok' => false, 'http' => 0, 'error' => 'Missing token', 'data' => null];
    }
    $meRes = sk_meta_catalog_http('GET', 'https://graph.facebook.com/v21.0/me?fields=id', $token, null, 20);
    $meId = trim((string)($meRes['data']['id'] ?? ''));
    if ($meId === '') {
        return $meRes['ok'] ? ['ok' => false, 'http' => $meRes['http'], 'error' => 'Could not resolve token user id', 'data' => $meRes['data']] : $meRes;
    }
    return sk_meta_catalog_http(
        'GET',
        'https://graph.facebook.com/v21.0/' . rawurlencode($meId) . '/assigned_product_catalogs?fields=id,name,product_count,vertical',
        $token,
        null,
        30
    );
}

function sk_meta_catalog_ensure_vendor_catalog(int $vendorId, ?string $phoneNumberId = null, ?array $settings = null): array {
    $vendorId = (int)$vendorId;
    if ($vendorId < 1) {
        return ['ok' => false, 'error' => 'Vendor id is required.'];
    }
    $phoneNumberId = trim((string)($phoneNumberId ?: sk_meta_catalog_resolve_phone_for_vendor($vendorId)));
    if ($phoneNumberId === '') {
        return ['ok' => false, 'error' => 'No active WhatsApp number is linked to this vendor.'];
    }
    $tokens = sk_meta_catalog_candidate_tokens($vendorId, $phoneNumberId, $settings);
    if (!$tokens) {
        return ['ok' => false, 'error' => 'Meta access token is missing.'];
    }
    $token = $tokens[0];

    $shopName = 'Shop Catalog';
    $storedCatalogId = '';
    $storedCatalogName = '';
    if (function_exists('get_instance') && get_instance() !== null) {
        $CI =& get_instance();
        if (!isset($CI->Sk_Vendor_model)) {
            $CI->load->model('Sk_Vendor_model');
        }
        $vendor = $CI->Sk_Vendor_model->get_by_id($vendorId);
        $shopName = trim((string)($vendor['business_name'] ?? $vendor['shop_name'] ?? $vendor['owner_name'] ?? ''));
        if ($shopName === '') {
            $shopName = 'Shop Catalog';
        }
        if (!isset($CI->Sk_Vendor_meta_agent_model)) {
            $CI->load->model('Sk_Vendor_meta_agent_model');
        }
        $CI->Sk_Vendor_meta_agent_model->ensure_schema();
        $metaRow = $CI->Sk_Vendor_meta_agent_model->get_by_phone($phoneNumberId);
        if ((int)($metaRow['vendor_id'] ?? 0) === $vendorId) {
            $storedCatalogId = trim((string)($metaRow['catalog_id'] ?? ''));
            $storedCatalogName = trim((string)($metaRow['catalog_name'] ?? ''));
        }
    }

    $metaUrl = 'https://graph.facebook.com/v21.0/' . rawurlencode($phoneNumberId) . '?fields=id,display_phone_number,verified_name';
    $metaRes = sk_meta_catalog_http('GET', $metaUrl, $token, null, 30);
    if (!$metaRes['ok'] && count($tokens) > 1) {
        $token = $tokens[1];
        $metaRes = sk_meta_catalog_http('GET', $metaUrl, $token, null, 30);
    }
    if (!$metaRes['ok']) {
        return ['ok' => false, 'error' => $metaRes['error'] ?: 'Unable to read WhatsApp account metadata.'];
    }
    $businessId = '';
    $account = null;
    $cloudConfig = [];
    if (function_exists('get_instance') && get_instance() !== null) {
        $CI =& get_instance();
        if (!isset($CI->Sk_Vendor_whatsapp_account_model)) {
            $CI->load->model('Sk_Vendor_whatsapp_account_model');
        }
        $candidate = $CI->Sk_Vendor_whatsapp_account_model->get_by_phone($phoneNumberId, true);
        if ((int)($candidate['vendor_id'] ?? 0) === $vendorId) {
            $account = $candidate;
        }
        $businessId = trim((string)($account['business_id'] ?? ''));
        if ($businessId === '') {
            if (!function_exists('sk_wa_cloud_config')) {
                $CI->load->helper('sk_whatsapp_cloud');
            }
            $cloudConfig = sk_wa_cloud_config($settings, $vendorId);
            $businessId = trim((string)($cloudConfig['business_id'] ?? ''));
        }
    }
    $wabaId = trim((string)($account['waba_id'] ?? $cloudConfig['waba_id'] ?? ''));
    $businessLookupError = '';
    if ($businessId === '' && $wabaId !== '' && function_exists('sk_wa_meta_graph')) {
        $ownerRes = sk_wa_meta_graph('GET', $wabaId, ['fields' => 'owner_business_info'], null, $token, $settings);
        if ($ownerRes['ok']) {
            $businessId = trim((string)($ownerRes['data']['owner_business_info']['id'] ?? ''));
        } else {
            $businessLookupError = sk_meta_catalog_error_message('Reading WhatsApp Business Account ownership', $ownerRes);
        }
        if ($businessId === '') {
            $businessesRes = sk_wa_meta_graph('GET', 'me/businesses', ['fields' => 'id'], null, $token, $settings);
            $businesses = $businessesRes['data']['data'] ?? [];
            if (!$businessesRes['ok']) {
                $businessLookupError = sk_meta_catalog_error_message('Listing businesses available to the access token', $businessesRes);
            }
            if ($businessesRes['ok'] && is_array($businesses)) {
                foreach ($businesses as $business) {
                    $candidateBusinessId = trim((string)($business['id'] ?? ''));
                    if ($candidateBusinessId === '') {
                        continue;
                    }
                    foreach (['owned_whatsapp_business_accounts', 'client_whatsapp_business_accounts'] as $edge) {
                        $wabasRes = sk_wa_meta_graph('GET', $candidateBusinessId . '/' . $edge, ['fields' => 'id'], null, $token, $settings);
                        $wabas = $wabasRes['data']['data'] ?? [];
                        if (!$wabasRes['ok'] || !is_array($wabas)) {
                            if (!$wabasRes['ok'] && $businessLookupError === '') {
                                $businessLookupError = sk_meta_catalog_error_message('Checking WhatsApp accounts for an accessible business', $wabasRes);
                            }
                            continue;
                        }
                        foreach ($wabas as $waba) {
                            if (trim((string)($waba['id'] ?? '')) === $wabaId) {
                                $businessId = $candidateBusinessId;
                                break 3;
                            }
                        }
                    }
                }
            }
        }
        if ($businessId !== '' && is_array($account)) {
            $CI->Sk_Vendor_whatsapp_account_model->save_for_vendor($vendorId, [
                'phone_number_id' => $phoneNumberId,
                'waba_id' => $wabaId,
                'business_id' => $businessId,
            ]);
        }
    }
    $catalogId = $storedCatalogId;
    $catalogName = $storedCatalogName;

    // 1) Catalogs already linked to this WABA.
    if ($catalogId === '' && $wabaId !== '') {
        $wabaListRes = sk_meta_catalog_http(
            'GET',
            'https://graph.facebook.com/v21.0/' . rawurlencode($wabaId) . '/product_catalogs?fields=id,name,product_count',
            $token,
            null,
            30
        );
        if ($wabaListRes['ok']) {
            $picked = sk_meta_catalog_pick_first_catalog($wabaListRes, $shopName);
            $catalogId = $picked['id'];
            $catalogName = $picked['name'];
        }
    }

    // 2) Catalogs assigned to this system user (e.g. VelAi Catalog on Sona Chips Factory).
    if ($catalogId === '') {
        foreach ($tokens as $candidateToken) {
            $assignedRes = sk_meta_catalog_list_assigned($candidateToken);
            if (!$assignedRes['ok']) {
                continue;
            }
            $picked = sk_meta_catalog_pick_first_catalog($assignedRes, $shopName);
            if ($picked['id'] === '') {
                $picked = sk_meta_catalog_pick_first_catalog($assignedRes);
            }
            if ($picked['id'] !== '') {
                $token = $candidateToken;
                $catalogId = $picked['id'];
                $catalogName = $picked['name'];
                break;
            }
        }
    }

    // 3) Catalogs owned by the WABA owner business.
    if ($catalogId === '' && $businessId !== '') {
        $catalogListUrl = 'https://graph.facebook.com/v21.0/' . rawurlencode($businessId) . '/owned_product_catalogs?fields=id,name,product_count';
        $listRes = sk_meta_catalog_http('GET', $catalogListUrl, $token, null, 30);
        if (!$listRes['ok'] && count($tokens) > 1) {
            foreach ($tokens as $candidateToken) {
                if ($candidateToken === $token) {
                    continue;
                }
                $altList = sk_meta_catalog_http('GET', $catalogListUrl, $candidateToken, null, 30);
                if ($altList['ok']) {
                    $token = $candidateToken;
                    $listRes = $altList;
                    break;
                }
            }
        }
        if ($listRes['ok']) {
            $picked = sk_meta_catalog_pick_first_catalog($listRes, $shopName);
            $catalogId = $picked['id'];
            $catalogName = $picked['name'];
        }
    }

    // 4) No catalog yet → create on the WABA owner business (needs BM Admin there).
    if ($catalogId === '') {
        if ($businessId === '') {
            return ['ok' => false, 'error' => $businessLookupError !== '' ? $businessLookupError : 'No Meta catalog is assigned to this token, and the WhatsApp business id could not be resolved to create one.'];
        }
        $createRes = ['ok' => false, 'http' => 0, 'error' => '', 'data' => null];
        $createPayloads = [
            ['name' => $shopName, 'vertical' => 'commerce'],
            ['name' => $shopName],
        ];
        foreach ($tokens as $candidateToken) {
            foreach ($createPayloads as $payload) {
                $createRes = sk_meta_catalog_http(
                    'POST',
                    'https://graph.facebook.com/v21.0/' . rawurlencode($businessId) . '/owned_product_catalogs',
                    $candidateToken,
                    $payload,
                    30
                );
                if ($createRes['ok']) {
                    $token = $candidateToken;
                    break 2;
                }
            }
        }
        if (!$createRes['ok']) {
            return [
                'ok' => false,
                'error' => sk_meta_catalog_permission_hint(sk_meta_catalog_error_message('Creating a product catalog', $createRes)),
            ];
        }
        $catalogId = trim((string)($createRes['data']['id'] ?? ''));
        $catalogName = trim((string)($createRes['data']['name'] ?? $shopName));
    }

    if ($catalogId === '') {
        return ['ok' => false, 'error' => 'Meta catalog id could not be resolved.'];
    }

    // Attach catalog to this WhatsApp number's WABA.
    // If the catalog lives on another BM (e.g. Sona → Intellectworks WABA), share it as an agency asset first.
    $linkOk = false;
    $linkWarning = '';
    if ($wabaId !== '') {
        $already = sk_meta_catalog_http(
            'GET',
            'https://graph.facebook.com/v21.0/' . rawurlencode($wabaId) . '/product_catalogs?fields=id,name',
            $token,
            null,
            20
        );
        if ($already['ok']) {
            foreach (($already['data']['data'] ?? []) as $linked) {
                if (trim((string)($linked['id'] ?? '')) === $catalogId) {
                    $linkOk = true;
                    break;
                }
            }
        }

        if (!$linkOk && $businessId !== '') {
            foreach ($tokens as $candidateToken) {
                $shareRes = sk_meta_catalog_http(
                    'POST',
                    'https://graph.facebook.com/v21.0/' . rawurlencode($catalogId) . '/agencies',
                    $candidateToken,
                    [
                        'business' => $businessId,
                        'permitted_tasks' => ['MANAGE', 'ADVERTISE'],
                    ],
                    30
                );
                // success, or already shared / already has access
                $shareErr = strtolower((string)($shareRes['error'] ?? '') . ' ' . (string)($shareRes['data']['error']['message'] ?? ''));
                if ($shareRes['ok']
                    || strpos($shareErr, 'already') !== false
                    || strpos($shareErr, 'duplicate') !== false
                    || strpos($shareErr, 'exist') !== false
                ) {
                    $token = $candidateToken;
                    break;
                }
            }
        }

        if (!$linkOk) {
            $linkRes = ['ok' => false, 'error' => '', 'data' => null, 'http' => 0];
            foreach ($tokens as $candidateToken) {
                $linkRes = sk_meta_catalog_http(
                    'POST',
                    'https://graph.facebook.com/v21.0/' . rawurlencode($wabaId) . '/product_catalogs',
                    $candidateToken,
                    ['catalog_id' => $catalogId],
                    30
                );
                if ($linkRes['ok']) {
                    $linkOk = true;
                    $token = $candidateToken;
                    break;
                }
                $err = strtolower((string)$linkRes['error'] . ' ' . (string)($linkRes['data']['error']['error_user_msg'] ?? ''));
                if (strpos($err, 'already') !== false || strpos($err, 'exists') !== false) {
                    $linkOk = true;
                    break;
                }
            }
            if (!$linkOk) {
                $linkWarning = trim((string)($linkRes['data']['error']['error_user_msg'] ?? $linkRes['error'] ?? 'Could not link catalog to WhatsApp number.'));
            }
        }
    } else {
        $linkWarning = 'WhatsApp Business Account id is missing for this number.';
    }

    sk_meta_catalog_store_catalog_id($vendorId, $phoneNumberId, $catalogId, $catalogName);

    return [
        'ok'              => true,
        'vendor_id'       => $vendorId,
        'phone_number_id' => $phoneNumberId,
        'business_id'     => $businessId,
        'catalog_id'      => $catalogId,
        'catalog_name'    => $catalogName,
        'waba_linked'     => $linkOk,
        'link_warning'    => $linkOk ? '' : $linkWarning,
    ];
}

function sk_meta_catalog_build_product_payload(array $product): array {
    $name = trim((string)($product['name'] ?? ''));
    $description = trim((string)($product['short_desc'] ?? $product['description'] ?? ''));
    $price = (float)($product['price'] ?? 0);
    $currency = strtoupper(trim((string)($product['currency'] ?? 'INR')));
    $imageUrl = trim((string)($product['thumbnail'] ?? $product['image_url'] ?? ''));
    if ($imageUrl !== '' && !preg_match('#^https?://#i', $imageUrl)) {
        if (function_exists('base_url')) {
            $imageUrl = rtrim(base_url(), '/') . '/' . ltrim($imageUrl, '/');
        }
    }
    $stock = max(0, (int)($product['stock'] ?? 0));

    // Meta Product Catalog price is an integer in the currency's smallest unit (paise for INR).
    $priceMinor = (int)round(max(0, $price) * 100);
    $payload = [
        'retailer_id' => 'product_' . (int)($product['id'] ?? 0),
        'name'        => $name !== '' ? $name : 'Product',
        'description' => $description !== '' ? mb_substr($description, 0, 1000) : 'Product from local catalog.',
        'price'       => $priceMinor,
        'currency'    => $currency !== '' ? $currency : 'INR',
        'availability'=> $stock > 0 ? 'in stock' : 'out of stock',
        'condition'   => 'new',
        'url'         => function_exists('base_url') ? rtrim(base_url(), '/') . '/' : 'https://talkaipilot.com/',
    ];
    if ($imageUrl !== '') {
        $payload['image_url'] = $imageUrl;
    }
    return $payload;
}

function sk_meta_catalog_delete_product(string $catalogId, string $productId, string $token): array {
    $catalogId = trim($catalogId);
    $productId = trim($productId);
    if ($catalogId === '' || $productId === '') {
        return ['ok' => false, 'error' => 'catalog id and product id are required'];
    }
    $url = 'https://graph.facebook.com/v21.0/' . rawurlencode($catalogId) . '/products?retailer_id=' . rawurlencode($productId);
    return sk_meta_catalog_http('DELETE', $url, $token, null, 30);
}

function sk_meta_catalog_sync_vendor_products(int $vendorId, ?string $phoneNumberId = null, ?array $settings = null): array {
    $vendorId = (int)$vendorId;
    if ($vendorId < 1) {
        return ['ok' => false, 'error' => 'Vendor id is required.'];
    }
    $catalog = sk_meta_catalog_ensure_vendor_catalog($vendorId, $phoneNumberId, $settings);
    if (!$catalog['ok']) {
        return ['ok' => false, 'error' => $catalog['error']];
    }

    if (function_exists('get_instance') && get_instance() !== null) {
        $CI =& get_instance();
        $CI->load->model('Sk_Product_model');
        $thisProductQuery = $CI->db->where('vendor_id', $vendorId)->or_where('vendor_id', 0)->get('products');
        $rows = $thisProductQuery->result_array();
    } else {
        $rows = [];
    }

    $token = sk_meta_catalog_access_token($vendorId, (string)($phoneNumberId ?: $catalog['phone_number_id']), $settings);
    $catalogId = trim((string)$catalog['catalog_id']);
    $productsSynced = [];
    foreach ($rows as $product) {
        $payload = sk_meta_catalog_build_product_payload($product);
        $res = sk_meta_catalog_http('POST', 'https://graph.facebook.com/v21.0/' . rawurlencode($catalogId) . '/products', $token, $payload, 30);
        $productsSynced[] = [
            'product_id' => (int)($product['id'] ?? 0),
            'retailer_id' => (string)($payload['retailer_id'] ?? ''),
            'ok' => $res['ok'],
            'error' => $res['ok'] ? '' : sk_meta_catalog_error_message('Uploading product ' . (string)$payload['retailer_id'], $res),
        ];
    }

    $failedProducts = array_values(array_filter($productsSynced, static function (array $product): bool {
        return empty($product['ok']);
    }));
    if ($failedProducts) {
        $failureSummary = [];
        foreach (array_slice($failedProducts, 0, 3) as $failedProduct) {
            $failureSummary[] = ($failedProduct['retailer_id'] ?: 'Product ' . $failedProduct['product_id']) . ': ' . ($failedProduct['error'] ?: 'Meta rejected the product.');
        }
        $remaining = count($failedProducts) - count($failureSummary);
        return [
            'ok' => false,
            'error' => count($failedProducts) . ' of ' . count($productsSynced) . ' products failed to sync. ' . implode(' | ', $failureSummary) . ($remaining > 0 ? ' | ' . $remaining . ' additional product failures.' : ''),
            'catalog_id' => $catalogId,
            'catalog_name' => (string)($catalog['catalog_name'] ?? ''),
            'link_warning' => (string)($catalog['link_warning'] ?? ''),
            'synced' => $productsSynced,
        ];
    }

    return [
        'ok' => true,
        'catalog_id' => $catalogId,
        'catalog_name' => (string)($catalog['catalog_name'] ?? ''),
        'waba_linked' => !empty($catalog['waba_linked']),
        'link_warning' => (string)($catalog['link_warning'] ?? ''),
        'synced' => $productsSynced,
    ];
}
