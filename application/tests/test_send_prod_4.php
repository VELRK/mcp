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

require_once __DIR__ . '/../helpers/sk_mcp_tool_helper.php';
require_once __DIR__ . '/../helpers/sk_meta_business_agent_helper.php';

$res = $mysqli->query("SELECT * FROM products WHERE id = 4");
$prod = $res->fetch_assoc();

$phoneId = '1389144424278347';
$token = sk_meta_ba_access_token($phoneId);
$targetPhone = '917598933686';

$imgPath = function_exists('sk_mcp_tool_product_image_path') ? sk_mcp_tool_product_image_path($prod) : (string)($prod['thumbnail'] ?? '');
$imgUrl = function_exists('sk_mcp_tool_whatsapp_image_url') ? sk_mcp_tool_whatsapp_image_url($imgPath, $prod) : '';
echo "imgPath: $imgPath\n";
echo "imgUrl: $imgUrl\n";

$pName = (string)$prod['name'];
$origPrice = (float)($prod['price'] ?? 0);
$salePrice = isset($prod['sale_price']) && (float)$prod['sale_price'] > 0 ? (float)$prod['sale_price'] : $origPrice;
$priceDisplay = "💰 Rate: ₹{$salePrice} ~₹{$origPrice}~ (23% OFF)";
$specStr = "🧵 Fabric: Cotton\n🎨 Color: Ivory\n📏 Length: 5.50m (With Blouse)\n";
$cardText = "🥻 *{$pName}*\n"
          . "{$priceDisplay}\n\n"
          . "{$specStr}"
          . "📦 *Stock:* In Stock (Ready to dispatch)\n"
          . "🚚 *Delivery:* FREE Delivery across India\n\n"
          . "👉 *Select quantity below to order:*";

$interactiveCard = [
    'type'   => 'button',
    'header' => [
        'type'  => 'image',
        'image' => ['link' => $imgUrl],
    ],
    'body'   => [
        'text' => $cardText,
    ],
    'footer' => [
        'text' => '100% Authentic Handloom Quality',
    ],
    'action' => [
        'buttons' => [
            [
                'type'  => 'reply',
                'reply' => [
                    'id'    => "qty_1_4_4",
                    'title' => '🛒 Buy 1 Pc',
                ],
            ],
            [
                'type'  => 'reply',
                'reply' => [
                    'id'    => "qty_2_4_4",
                    'title' => '🛍️ Buy 2 Pcs',
                ],
            ],
            [
                'type'  => 'reply',
                'reply' => [
                    'id'    => "more_qty_4_4",
                    'title' => '🔢 More Qty',
                ],
            ],
        ],
    ],
];

$payload = [
    'messaging_product' => 'whatsapp',
    'recipient_type'    => 'individual',
    'to'                => $targetPhone,
    'type'              => 'interactive',
    'interactive'       => $interactiveCard,
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

echo "Card Send HTTP Code: {$http}\n";
echo "Response: {$raw}\n";
