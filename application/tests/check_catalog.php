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

echo "=== ME ===\n";
echo json_encode(meta_get("https://graph.facebook.com/v21.0/me?fields=id,name", $token), JSON_PRETTY_PRINT) . "\n\n";

echo "=== ME BUSINESSES ===\n";
echo json_encode(meta_get("https://graph.facebook.com/v21.0/me/businesses?fields=id,name", $token), JSON_PRETTY_PRINT) . "\n\n";

echo "=== ME PRODUCT CATALOGS ===\n";
echo json_encode(meta_get("https://graph.facebook.com/v21.0/me/product_catalogs?fields=id,name", $token), JSON_PRETTY_PRINT) . "\n\n";

echo "=== WABA COMMERCE SETTINGS ===\n";
echo json_encode(meta_get("https://graph.facebook.com/v21.0/{$wabaId}/whatsapp_commerce_settings", $token), JSON_PRETTY_PRINT) . "\n\n";
