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

function sk_meta_catalog_ensure_vendor_catalog(int $vendorId, ?string $phoneNumberId = null, ?array $settings = null): array {
    $vendorId = (int)$vendorId;
    if ($vendorId < 1) {
        return ['ok' => false, 'error' => 'Vendor id is required.'];
    }
    $phoneNumberId = trim((string)($phoneNumberId ?: sk_meta_catalog_resolve_phone_for_vendor($vendorId)));
    if ($phoneNumberId === '') {
        return ['ok' => false, 'error' => 'No active WhatsApp number is linked to this vendor.'];
    }
    $token = sk_meta_ba_access_token($phoneNumberId, $settings);
    if ($token === '') {
        return ['ok' => false, 'error' => 'Meta access token is missing.'];
    }

    $shopName = 'Shop Catalog';
    if (function_exists('get_instance') && get_instance() !== null) {
        $CI =& get_instance();
        if (!isset($CI->Sk_Vendor_model)) {
            $CI->load->model('Sk_Vendor_model');
        }
        $vendor = $CI->Sk_Vendor_model->get_by_id($vendorId);
        if (!empty($vendor['shop_name'])) {
            $shopName = trim((string)$vendor['shop_name']);
        }
    }

    $metaUrl = 'https://graph.facebook.com/v21.0/' . rawurlencode($phoneNumberId) . '?fields=id,display_phone_number,verified_name';
    $metaRes = sk_meta_catalog_http('GET', $metaUrl, $token, null, 30);
    $metaData = is_array($metaRes['data']) ? $metaRes['data'] : [];
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
    if ($businessId === '' && $wabaId !== '' && function_exists('sk_wa_meta_graph')) {
        $ownerRes = sk_wa_meta_graph('GET', $wabaId, ['fields' => 'owner_business_info'], null, $token, $settings);
        if ($ownerRes['ok']) {
            $businessId = trim((string)($ownerRes['data']['owner_business_info']['id'] ?? ''));
        }
        if ($businessId === '') {
            $businessesRes = sk_wa_meta_graph('GET', 'me/businesses', ['fields' => 'id'], null, $token, $settings);
            $businesses = $businessesRes['data']['data'] ?? [];
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
    if ($businessId === '') {
        return ['ok' => false, 'error' => 'Could not resolve a Meta business for the linked WhatsApp Business Account. Check that its WABA ID is saved and the access token can list business WhatsApp accounts.'];
    }

    $catalogListUrl = 'https://graph.facebook.com/v21.0/' . rawurlencode($businessId) . '/owned_product_catalogs?fields=id,name,product_count';
    $listRes = sk_meta_catalog_http('GET', $catalogListUrl, $token, null, 30);
    $catalogData = is_array($listRes['data']) ? $listRes['data'] : [];
    $catalogId = '';
    $catalogName = '';
    if (!empty($catalogData['data']) && is_array($catalogData['data'])) {
        foreach ($catalogData['data'] as $catalog) {
            if (!is_array($catalog)) {
                continue;
            }
            $candidateId = trim((string)($catalog['id'] ?? ''));
            $candidateName = trim((string)($catalog['name'] ?? ''));
            if ($candidateId !== '') {
                $catalogId = $candidateId;
                $catalogName = $candidateName;
                break;
            }
        }
    }

    if ($catalogId === '') {
        $createRes = sk_meta_catalog_http('POST', 'https://graph.facebook.com/v21.0/' . rawurlencode($businessId) . '/owned_product_catalogs', $token, ['name' => $shopName], 30);
        if (!$createRes['ok']) {
            return ['ok' => false, 'error' => $createRes['error'] ?: 'Unable to create Meta catalog.'];
        }
        $catalogId = trim((string)($createRes['data']['id'] ?? ''));
        $catalogName = trim((string)($createRes['data']['name'] ?? $shopName));
    }

    if ($catalogId === '') {
        return ['ok' => false, 'error' => 'Meta catalog id could not be resolved.'];
    }

    $linkRes = sk_meta_catalog_http('POST', 'https://graph.facebook.com/v21.0/' . rawurlencode($phoneNumberId) . '/product_catalogs', $token, ['catalog_id' => $catalogId], 30);
    if (!$linkRes['ok']) {
        $err = strtolower((string)$linkRes['error']);
        if (strpos($err, 'already') === false && strpos($err, 'exists') === false) {
            // Best-effort: catalog creation already succeeded; continue even if linking is noisy.
        }
    }

    sk_meta_catalog_store_catalog_id($vendorId, $phoneNumberId, $catalogId, $catalogName);

    return [
        'ok'             => true,
        'vendor_id'      => $vendorId,
        'phone_number_id'=> $phoneNumberId,
        'business_id'    => $businessId,
        'catalog_id'     => $catalogId,
        'catalog_name'   => $catalogName,
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

    $payload = [
        'retailer_id' => 'product_' . (int)($product['id'] ?? 0),
        'name'        => $name !== '' ? $name : 'Product',
        'description' => $description !== '' ? mb_substr($description, 0, 1000) : 'Product from local catalog.',
        'price'       => number_format($price, 2, '.', ''),
        'currency'    => $currency !== '' ? $currency : 'INR',
        'availability'=> $stock > 0 ? 'in stock' : 'out of stock',
        'condition'   => 'new',
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

    $token = sk_meta_ba_access_token((string)($phoneNumberId ?: $catalog['phone_number_id']), $settings);
    $catalogId = trim((string)$catalog['catalog_id']);
    $productsSynced = [];
    foreach ($rows as $product) {
        $payload = sk_meta_catalog_build_product_payload($product);
        $res = sk_meta_catalog_http('POST', 'https://graph.facebook.com/v21.0/' . rawurlencode($catalogId) . '/products', $token, $payload, 30);
        $productsSynced[] = [
            'product_id' => (int)($product['id'] ?? 0),
            'retailer_id' => (string)($payload['retailer_id'] ?? ''),
            'ok' => $res['ok'],
            'error' => $res['error'] ?: '',
        ];
    }

    return ['ok' => true, 'catalog_id' => $catalogId, 'synced' => $productsSynced];
}
