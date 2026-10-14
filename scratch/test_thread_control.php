<?php
define('BASEPATH', true);
define('FCPATH', '/var/www/mcp/');
define('APPPATH', '/var/www/mcp/application/');

require_once APPPATH . 'helpers/sk_dotenv_helper.php';
sk_dotenv_load(FCPATH . '.env');
require_once APPPATH . 'helpers/sk_meta_business_agent_helper.php';

$phoneId = '1389144424278347';
$userPhone = '917598933686';

echo "=== CHECKING THREAD CONTROL FOR {$userPhone} ===\n";
$res = sk_meta_ba_thread_control($phoneId, $userPhone, 'release');
echo "Release result:\n";
print_r($res);

// Also test sending message directly to verify Cloud API token and session
$token = sk_meta_ba_access_token($phoneId);
echo "\n=== SENDING TEST TEXT TO {$userPhone} TO CHECK CLOUD API DIRECT DELIVERY ===\n";
$ch = curl_init("https://graph.facebook.com/v21.0/{$phoneId}/messages");
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS     => json_encode([
        'messaging_product' => 'whatsapp',
        'recipient_type'    => 'individual',
        'to'                => $userPhone,
        'type'              => 'text',
        'text'              => [
            'body' => "Test delivery from TalkAI Pilot (Direct Cloud API check)",
        ],
    ]),
]);
$raw = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "Direct Send HTTP: {$http}\n";
echo "Direct Send Body: {$raw}\n";
