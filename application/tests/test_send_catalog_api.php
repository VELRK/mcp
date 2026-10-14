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

require_once __DIR__ . '/../helpers/sk_meta_business_agent_helper.php';
$phoneId = '1389144424278347';
$token = sk_meta_ba_access_token($phoneId);
$targetPhone = '917598933686';

echo "1. Checking button string length:\n";
$btn = '🛍️ Open Saree Catalog';
echo "mb_strlen: " . mb_strlen($btn, 'UTF-8') . " | strlen: " . strlen($btn) . "\n";

$prods = [];
$res = $mysqli->query("SELECT id, name, price, sale_price, fabric FROM products WHERE vendor_id = 2 AND status = 'active' ORDER BY id ASC LIMIT 7");
while ($r = $res->fetch_assoc()) $prods[] = $r;

$prodRows = [];
foreach ($prods as $p) {
    $pName = trim((string)$p['name']);
    $salePrice = isset($p['sale_price']) && (float)$p['sale_price'] > 0 ? (float)$p['sale_price'] : (float)$p['price'];
    $desc = "₹{$salePrice} · In Stock";

    $prodRows[] = [
        'id'          => 'prod_' . $p['id'],
        'title'       => mb_substr("🥻 {$pName}", 0, 24, 'UTF-8'),
        'description' => mb_substr($desc, 0, 72, 'UTF-8'),
    ];
}

$sections = [
    [
        'title' => '🌟 Saree Catalog',
        'rows'  => $prodRows,
    ],
];

$payload = [
    'messaging_product' => 'whatsapp',
    'recipient_type'    => 'individual',
    'to'                => $targetPhone,
    'type'              => 'interactive',
    'interactive'       => [
        'type'   => 'list',
        'header' => [
            'type' => 'text',
            'text' => 'Vel Saree Catalog',
        ],
        'body'   => [
            'text' => "Welcome to our handcrafted saree collection! ✨\nTap below to open the catalog, view rates, specifications, and place your order:",
        ],
        'footer' => [
            'text' => 'Free delivery across India 🚚',
        ],
        'action' => [
            'button'   => $btn,
            'sections' => $sections,
        ],
    ],
];

$ch = curl_init("https://graph.facebook.com/v21.0/{$phoneId}/messages");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload),
]);
$raw = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$http}\n";
echo "Response: {$raw}\n";
