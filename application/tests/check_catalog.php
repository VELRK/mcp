<?php
define('BASEPATH', true);
define('FCPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
define('APPPATH', FCPATH . 'application' . DIRECTORY_SEPARATOR);

require_once APPPATH . 'helpers/sk_dotenv_helper.php';
sk_dotenv_load(FCPATH . '.env');
require_once APPPATH . 'helpers/sk_meta_business_agent_helper.php';

$phoneId = '1389144424278347';
$token = sk_meta_ba_access_token($phoneId);

echo "Token prefix: " . substr($token, 0, 15) . "...\n";

// 1. Get phone number & WABA details
$ch = curl_init("https://graph.facebook.com/v21.0/{$phoneId}?fields=id,display_phone_number,whatsapp_business_account");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
]);
$res1 = curl_exec($ch);
curl_close($ch);
echo "Phone info: " . $res1 . "\n\n";

$data1 = json_decode($res1, true);
$wabaId = $data1['whatsapp_business_account']['id'] ?? '';

if ($wabaId !== '') {
    echo "Found WABA ID: {$wabaId}\n";
    // 2. Check linked product catalogs
    $ch = curl_init("https://graph.facebook.com/v21.0/{$wabaId}/product_catalogs");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
    ]);
    $res2 = curl_exec($ch);
    curl_close($ch);
    echo "Product Catalogs: " . $res2 . "\n\n";

    // 3. Check commerce settings on WABA / phone
    $ch = curl_init("https://graph.facebook.com/v21.0/{$phoneId}/whatsapp_commerce_settings");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
    ]);
    $res3 = curl_exec($ch);
    curl_close($ch);
    echo "Commerce Settings: " . $res3 . "\n";
}
