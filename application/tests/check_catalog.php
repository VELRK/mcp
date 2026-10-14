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

function meta_get($url, $tok) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $tok],
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($res, true) ?: $res];
}

echo "=== WABA DETAILS ===\n";
$waba = meta_get("https://graph.facebook.com/v21.0/{$wabaId}?fields=id,name,currency,business,primary_business_location", $token);
echo json_encode($waba, JSON_PRETTY_PRINT) . "\n\n";

$businessId = $waba['body']['business']['id'] ?? '';
if ($businessId !== '') {
    echo "=== BUSINESS OWNED CATALOGS ({$businessId}) ===\n";
    $catalogs = meta_get("https://graph.facebook.com/v21.0/{$businessId}/owned_product_catalogs?fields=id,name,product_count,feed_count", $token);
    echo json_encode($catalogs, JSON_PRETTY_PRINT) . "\n\n";

    $clientCatalogs = meta_get("https://graph.facebook.com/v21.0/{$businessId}/client_product_catalogs?fields=id,name,product_count", $token);
    echo "=== CLIENT CATALOGS ===\n";
    echo json_encode($clientCatalogs, JSON_PRETTY_PRINT) . "\n\n";
}

echo "=== LINKED WABA CATALOGS ===\n";
$linked = meta_get("https://graph.facebook.com/v21.0/{$wabaId}/product_catalogs", $token);
echo json_encode($linked, JSON_PRETTY_PRINT) . "\n\n";
