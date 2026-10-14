<?php
define('BASEPATH', true);
define('ENVIRONMENT', 'development');
require_once __DIR__ . '/../config/database.php';
$dbCfg = $db['default'];
$mysqli = new mysqli($dbCfg['hostname'], $dbCfg['username'], $dbCfg['password'], $dbCfg['database']);

$res = $mysqli->query("SELECT `key`, `value` FROM settings WHERE `key` IN ('razorpay_key_id', 'razorpay_key_secret', 'currency_code', 'currency_symbol')");
$settings = [];
while ($r = $res->fetch_assoc()) {
    $settings[$r['key']] = $r['value'];
}

$keyId = $settings['razorpay_key_id'] ?? '';
$keySecret = $settings['razorpay_key_secret'] ?? '';

echo "Key ID: " . $keyId . "\n";
echo "Secret: " . (strlen($keySecret) > 0 ? "Present" : "Missing") . "\n";

$payload = [
    'amount'          => 249900, // ₹2499 in paise
    'currency'        => 'INR',
    'accept_partial'  => false,
    'description'     => 'Vel Saree Order #TEST-1001 (1 Saree)',
    'reference_id'    => 'TEST_' . time(),
    'notify'          => ['sms' => false, 'email' => false, 'whatsapp' => false],
    'reminder_enable' => false,
];

$ch = curl_init('https://api.razorpay.com/v1/payment_links');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD        => $keyId . ':' . $keySecret,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 25,
]);
$response = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
if ($curlError) {
    echo "Curl Error: {$curlError}\n";
} else {
    $data = json_decode($response, true);
    if (!empty($data['short_url'])) {
        echo "SUCCESS! Payment Link: " . $data['short_url'] . "\n";
        echo "Payment ID: " . ($data['id'] ?? '') . "\n";
    } else {
        echo "Response: " . $response . "\n";
    }
}
