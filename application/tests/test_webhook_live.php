<?php
define('BASEPATH', true);
define('ENVIRONMENT', 'development');
define('FCPATH', '/var/www/mcp/');
define('APPPATH', '/var/www/mcp/application/');
require_once __DIR__ . '/../helpers/sk_dotenv_helper.php';
sk_dotenv_load(FCPATH . '.env');
require_once __DIR__ . '/../config/database.php';
$dbCfg = $db['default'];
$mysqli = new mysqli($dbCfg['hostname'], $dbCfg['username'], $dbCfg['password'], $dbCfg['database']);

$acc = $mysqli->query("SELECT id, vendor_id, phone_number_id, display_phone, status FROM vendor_whatsapp_accounts WHERE phone_number_id = '1389144424278347'")->fetch_assoc();
echo "vendor_whatsapp_accounts for 1389144424278347:\n";
print_r($acc);

// 1. Prepare simulated inbound message for "browse sarees"
$payload = [
    'object' => 'whatsapp_business_account',
    'entry'  => [
        [
            'id'      => '2076326679671502',
            'changes' => [
                [
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata'          => [
                            'display_phone_number' => '917010971651',
                            'phone_number_id'      => '1389144424278347',
                        ],
                        'contacts'          => [
                            [
                                'profile' => ['name' => 'Vel Customer'],
                                'wa_id'   => '917598933686',
                            ],
                        ],
                        'messages'          => [
                            [
                                'from'      => '917598933686',
                                'id'        => 'wamid.TEST_INBOUND_' . time(),
                                'timestamp' => (string)time(),
                                'text'      => ['body' => 'browse sarees'],
                                'type'      => 'text',
                            ],
                        ],
                    ],
                    'field' => 'messages',
                ],
            ],
        ],
    ],
];

require_once __DIR__ . '/../helpers/sk_whatsapp_cloud_helper.php';
$appSecret = getenv('META_APP_SECRET') ?: ($mysqli->query("SELECT value FROM settings WHERE `key` = 'meta_app_secret'")->fetch_assoc()['value'] ?? '');
if (!$appSecret) {
    $appSecret = $mysqli->query("SELECT value FROM settings WHERE `key` = 'wa_cloud_app_secret'")->fetch_assoc()['value'] ?? '';
}

$rawJson = json_encode($payload);
$sig = 'sha256=' . hash_hmac('sha256', $rawJson, $appSecret);

$ch = curl_init('https://talkaipilot.com/shopkart-api/whatsapp/webhook');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-Hub-Signature-256: ' . $sig,
    ],
    CURLOPT_POSTFIELDS     => $rawJson,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$res = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Inbound 'browse sarees' POST HTTP: {$http}\n";
echo "Response: {$res}\n";

// Check latest messages logged in DB
$res = $mysqli->query("SELECT id, conversation_id, direction, type, body, status, created_at FROM wa_cloud_messages ORDER BY id DESC LIMIT 5");
echo "Latest messages in DB:\n";
while ($row = $res->fetch_assoc()) {
    echo "#{$row['id']} [{$row['direction']}|{$row['type']}] {$row['body']} ({$row['created_at']})\n";
}
