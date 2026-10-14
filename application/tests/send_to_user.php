<?php
define('BASEPATH', true);
define('FCPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
define('APPPATH', FCPATH . 'application' . DIRECTORY_SEPARATOR);

require_once APPPATH . 'helpers/sk_dotenv_helper.php';
sk_dotenv_load(FCPATH . '.env');
require_once APPPATH . 'helpers/sk_meta_business_agent_helper.php';

$phoneId = '1389144424278347';
$token = sk_meta_ba_access_token($phoneId);
$target = '917598933686';

echo "Sending to: {$target}\n";
echo "From Phone ID: {$phoneId}\n";

// 1. Send featured product image with caption
$imgUrl = 'https://talkaipilot.com/assets/uploads/products/products_6acc6eeb541a4.jpg';
$caption = "Kanjivaram Silk Saree - Gold Border - ₹3";

$payload1 = [
    'messaging_product' => 'whatsapp',
    'recipient_type'    => 'individual',
    'to'                => $target,
    'type'              => 'image',
    'image'             => [
        'link'    => $imgUrl,
        'caption' => $caption,
    ],
];

$ch = curl_init("https://graph.facebook.com/v21.0/{$phoneId}/messages");
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload1),
]);
$res1 = curl_exec($ch);
$code1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Step 1 (Image) HTTP Code: {$code1}\n";
echo "Response: {$res1}\n\n";

// 2. Send text details
$messageText = "Here are our available sarees:\n"
    . "1. *Kanjivaram Silk Saree - Gold Border* - ₹3 · Maroon\n"
    . "2. *Banarasi Silk Saree - Zari Weave* - ₹299 · Royal Blue\n"
    . "3. *Heritage Crepe Silk Saree* - ₹249 · Peach\n"
    . "4. *Handloom Cotton Saree - Temple Border* - ₹99 · Ivory\n"
    . "5. *Soft Cotton Casual Saree* - ₹89 · Mint\n\n"
    . "Which saree and quantity would you like to order?";

$payload2 = [
    'messaging_product' => 'whatsapp',
    'recipient_type'    => 'individual',
    'to'                => $target,
    'type'              => 'text',
    'text'              => [
        'preview_url' => false,
        'body'        => $messageText,
    ],
];

$ch = curl_init("https://graph.facebook.com/v21.0/{$phoneId}/messages");
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload2),
]);
$res2 = curl_exec($ch);
$code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Step 2 (Text) HTTP Code: {$code2}\n";
echo "Response: {$res2}\n";
