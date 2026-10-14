<?php
define('BASEPATH', true);
define('FCPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
define('APPPATH', FCPATH . 'application' . DIRECTORY_SEPARATOR);

require_once APPPATH . 'helpers/sk_dotenv_helper.php';
sk_dotenv_load(FCPATH . '.env');
require_once APPPATH . 'helpers/sk_meta_business_agent_helper.php';
require_once APPPATH . 'helpers/sk_mcp_tool_helper.php';

$phoneId = '1389144424278347';
$token = sk_meta_ba_access_token($phoneId);
$target = '917598933686';

echo "=== WhatsApp Interactive List (Option B) Dispatch Test ===\n";
echo "Target Number: +{$target}\n";
echo "Business Phone ID: {$phoneId}\n\n";

// Saree product list for Option B interactive list
$products = [
    [
        'id'    => 1,
        'sku'   => '2D-001-PC',
        'name'  => 'Kanjivaram Silk Saree',
        'price' => 3,
        'color' => 'Maroon Gold Zari',
    ],
    [
        'id'    => 2,
        'sku'   => '2D-002-PC',
        'name'  => 'Banarasi Silk Saree',
        'price' => 299,
        'color' => 'Royal Blue Weave',
    ],
    [
        'id'    => 3,
        'sku'   => '2D-003-PC',
        'name'  => 'Heritage Crepe Saree',
        'price' => 249,
        'color' => 'Pure Silk Peach',
    ],
    [
        'id'    => 8,
        'sku'   => '2D-008-PC',
        'name'  => 'Bridal Red Silk Saree',
        'price' => 449,
        'color' => 'Heavy Bridal Red',
    ],
    [
        'id'    => 9,
        'sku'   => '2D-009-PC',
        'name'  => 'Wedding Tissue Saree',
        'price' => 429,
        'color' => 'Gold Tissue Silk',
    ],
    [
        'id'    => 4,
        'sku'   => '2D-004-PC',
        'name'  => 'Handloom Cotton Saree',
        'price' => 99,
        'color' => 'Ivory Temple Border',
    ],
    [
        'id'    => 5,
        'sku'   => '2D-005-PC',
        'name'  => 'Soft Cotton Casual',
        'price' => 89,
        'color' => 'Mint Green Daily',
    ],
    [
        'id'    => 6,
        'sku'   => '2D-006-PC',
        'name'  => 'Designer Georgette',
        'price' => 179,
        'color' => 'Emerald Green',
    ],
    [
        'id'    => 7,
        'sku'   => '2D-007-PC',
        'name'  => 'Printed Designer Saree',
        'price' => 159,
        'color' => 'Multi-color Festive',
    ],
    [
        'id'    => 10,
        'sku'   => '2D-010-PC',
        'name'  => 'Everyday Casual Saree',
        'price' => 69,
        'color' => 'Beige Lightweight',
    ],
];

// Build Option B interactive list payload
$interactive = sk_mcp_tool_build_product_interactive_list($products, 'Vel TEST', 'Free delivery over ₹999');

$payload = [
    'messaging_product' => 'whatsapp',
    'recipient_type'    => 'individual',
    'to'                => $target,
    'type'              => 'interactive',
    'interactive'       => $interactive,
];

echo "Sending Interactive List Payload:\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

$ch = curl_init("https://graph.facebook.com/v21.0/{$phoneId}/messages");
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload),
]);
$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status Code: {$httpCode}\n";
echo "WhatsApp API Response: {$res}\n";

$decoded = json_decode($res, true);
if ($httpCode === 200 && !empty($decoded['messages'][0]['id'])) {
    echo "\n>>> SUCCESS: WhatsApp Interactive List delivered! Message ID: " . $decoded['messages'][0]['id'] . "\n";
} else {
    echo "\n>>> ERROR: Failed to send interactive list message.\n";
}
