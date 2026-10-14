<?php
define('BASEPATH', true);
define('FCPATH', '/var/www/mcp/');
define('APPPATH', '/var/www/mcp/application/');

require_once APPPATH . 'helpers/sk_dotenv_helper.php';
sk_dotenv_load(FCPATH . '.env');
require_once APPPATH . 'helpers/sk_meta_business_agent_helper.php';

$phoneId = '1389144424278347';
$userPhone = '917598933686';
$token = sk_meta_ba_access_token($phoneId);

$interactive = [
    'type'   => 'list',
    'header' => [
        'type' => 'text',
        'text' => 'Vel TEST',
    ],
    'body'   => [
        'text' => "Welcome to Vel TEST! 👋\nHow can I help you today? Please choose an option below:",
    ],
    'footer' => [
        'text' => 'Select an option to proceed',
    ],
    'action' => [
        'button'   => 'View Menu',
        'sections' => [
            [
                'title' => 'Main Menu',
                'rows'  => [
                    [
                        'id'          => 'browse_products',
                        'title'       => '🛍️ Browse products',
                        'description' => 'Explore collections & categories',
                    ],
                    [
                        'id'          => 'offers_deals',
                        'title'       => '🏷️ Offers & Deals',
                        'description' => "Today's discounts & promo codes",
                    ],
                    [
                        'id'          => 'search_product',
                        'title'       => '🔎 Search a product',
                        'description' => 'Find by name, style, or color',
                    ],
                    [
                        'id'          => 'track_order',
                        'title'       => '📦 Track my order',
                        'description' => 'Check order & shipment status',
                    ],
                    [
                        'id'          => 'talk_to_team',
                        'title'       => '🙋 Talk to our team',
                        'description' => 'Chat with our support team',
                    ],
                ],
            ],
        ],
    ],
];

echo "Sending Main Menu Interactive List to {$userPhone}...\n";
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
        'type'              => 'interactive',
        'interactive'       => $interactive,
    ]),
]);
$raw = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP: {$http}\n";
echo "Response: {$raw}\n";

// Release thread control
echo "Releasing thread control to agent...\n";
$rel = sk_meta_ba_thread_control($phoneId, $userPhone, 'release');
print_r($rel);
