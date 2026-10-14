<?php
define('BASEPATH', true);
define('FCPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
define('APPPATH', FCPATH . 'application' . DIRECTORY_SEPARATOR);

require_once APPPATH . 'helpers/sk_dotenv_helper.php';
sk_dotenv_load(FCPATH . '.env');
require_once APPPATH . 'helpers/sk_meta_business_agent_helper.php';

$phoneId = '1389144424278347';
$token = sk_meta_ba_access_token($phoneId);
$wabaId = '2076326679671502';

function meta_req($method, $url, $tok, $body = null) {
    $ch = curl_init($url);
    $headers = ['Authorization: Bearer ' . $tok];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($res, true) ?: $res];
}

echo "=== 1. DEBUG TOKEN (SCOPES) ===\n";
$debug = meta_req('GET', "https://graph.facebook.com/v21.0/debug_token?input_token={$token}", $token);
echo json_encode($debug, JSON_PRETTY_PRINT) . "\n\n";

echo "=== 2. WABA BUSINESS / SHARING ===\n";
$waba = meta_req('GET', "https://graph.facebook.com/v21.0/{$wabaId}?fields=id,name,currency,owner_business_info,account_review_status", $token);
echo json_encode($waba, JSON_PRETTY_PRINT) . "\n\n";

$businessId = $waba['body']['owner_business_info']['id'] ?? '';
echo "Owner Business ID: {$businessId}\n\n";

if ($businessId !== '') {
    echo "=== 3. CHECK CATALOGS ON BUSINESS ===\n";
    $cats = meta_req('GET', "https://graph.facebook.com/v21.0/{$businessId}/owned_product_catalogs?fields=id,name,product_count", $token);
    echo json_encode($cats, JSON_PRETTY_PRINT) . "\n\n";
    
    // If no catalog, let's see if we can create one
    if (empty($cats['body']['data'])) {
        echo "=== 4. ATTEMPT CREATE CATALOG ON BUSINESS ===\n";
        $createCat = meta_req('POST', "https://graph.facebook.com/v21.0/{$businessId}/owned_product_catalogs", $token, [
            'name' => 'Vel Saree Collection',
        ]);
        echo json_encode($createCat, JSON_PRETTY_PRINT) . "\n\n";
        
        $newCatId = $createCat['body']['id'] ?? '';
        if ($newCatId !== '') {
            echo "Created Catalog ID: {$newCatId}\n";
            echo "=== 5. LINK CATALOG TO WABA ===\n";
            $link = meta_req('POST', "https://graph.facebook.com/v21.0/{$wabaId}/product_catalogs", $token, [
                'catalog_id' => $newCatId,
            ]);
            echo json_encode($link, JSON_PRETTY_PRINT) . "\n\n";
        }
    }
}
