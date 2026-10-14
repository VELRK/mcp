<?php
define('BASEPATH', true);
define('FCPATH', '/var/www/mcp/');
define('APPPATH', '/var/www/mcp/application/');

require_once APPPATH . 'helpers/sk_dotenv_helper.php';
sk_dotenv_load(FCPATH . '.env');
require_once APPPATH . 'helpers/sk_meta_business_agent_helper.php';

$phoneId = '1389144424278347';
$token = sk_meta_ba_access_token($phoneId);

function graph_get($url, $token) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'data' => json_decode($res, true) ?: $res];
}

echo "=== 1. PHONE NUMBER DETAILS ===\n";
$res1 = graph_get("https://graph.facebook.com/v21.0/{$phoneId}?fields=verified_name,code_verification_status,display_phone_number,quality_rating,platform_type,throughput", $token);
print_r($res1);

echo "\n=== 2. BUSINESS AGENT AGENTS ENDPOINT ===\n";
$res2 = graph_get("https://api.facebook.com/{$phoneId}/agents", $token);
print_r($res2);

echo "\n=== 3. WHATSAPP BUSINESS ACCOUNT ID (WABA) ===\n";
$res3 = graph_get("https://graph.facebook.com/v21.0/{$phoneId}?fields=whatsapp_business_account", $token);
print_r($res3);
$wabaId = $res3['data']['whatsapp_business_account']['id'] ?? '';

if ($wabaId) {
    echo "\n=== 4. WABA CONNECTED APPS / SUBSCRIBED APPS ===\n";
    $res4 = graph_get("https://graph.facebook.com/v21.0/{$wabaId}/subscribed_apps", $token);
    print_r($res4);
}
