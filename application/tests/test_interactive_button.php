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

echo "Testing WhatsApp Interactive Button message with image header...\n";

// Image URL from shop
$imgUrl = "https://talkaipilot.com/assets/uploads/products/thumb_product_1720000000_saree.jpg";

$payload = [
    'messaging_product' => 'whatsapp',
    'recipient_type'    => 'individual',
    'to'                => $targetPhone,
    'type'              => 'interactive',
    'interactive'       => [
        'type'   => 'button',
        'header' => [
            'type'  => 'image',
            'image' => [
                'link' => 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?auto=format&fit=crop&w=600&q=80',
            ],
        ],
        'body'   => [
            'text' => "🥻 *Pure Kanjivaram Silk Saree*\n💰 *Rate: ₹2,499* ~₹3,499~ (28% OFF)\n\n🧵 *Fabric:* Pure Silk\n🎨 *Color:* Royal Blue & Gold Zari\n📏 *Length:* 6.3 Meters (With Blouse)\n\n👉 *Select quantity to order:*",
        ],
        'footer' => [
            'text' => '100% Authentic Handloom · Free Delivery',
        ],
        'action' => [
            'buttons' => [
                [
                    'type'  => 'reply',
                    'reply' => [
                        'id'    => 'qty_1_4_4',
                        'title' => '🛒 Buy 1 Pc',
                    ],
                ],
                [
                    'type'  => 'reply',
                    'reply' => [
                        'id'    => 'qty_2_4_4',
                        'title' => '🛍️ Buy 2 Pcs',
                    ],
                ],
                [
                    'type'  => 'reply',
                    'reply' => [
                        'id'    => 'more_qty_4_4',
                        'title' => '🔢 More Qty',
                    ],
                ],
            ],
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
