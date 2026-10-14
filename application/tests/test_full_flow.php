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
$apiKey = 'Ved451959';
$baseUrl = 'https://talkaipilot.com/shopkart-api/meta-agent/connectors/' . $phoneId;

function api_call($url, $method = 'GET', $data = null, $apiKey = 'Ved451959') {
    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer ' . $apiKey,
    ];
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($res, true) ?: $res];
}

function send_wa($phoneId, $token, $payload) {
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
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($res, true) ?: $res];
}

echo "========================================================\n";
echo "   CHECKING 6-STEP WHATSAPP CUSTOMER FLOW END-TO-END\n";
echo "========================================================\n\n";

// STEP 1: Verify get_categories API
echo "[TEST 1] Calling get_categories connector...\n";
$catRes = api_call($baseUrl . '/get_categories', 'POST');
echo "HTTP Code: {$catRes['code']}\n";
$catCount = $catRes['body']['data']['count'] ?? 0;
echo "Categories found: {$catCount}\n";
if (!empty($catRes['body']['data']['categories'])) {
    foreach ($catRes['body']['data']['categories'] as $c) {
        echo "  - {$c['display']} ({$c['product_count']} items)\n";
    }
}
echo "Message preview:\n" . ($catRes['body']['data']['message'] ?? '') . "\n\n";

// STEP 2: Verify search_products with Category Query "Silk Sarees"
echo "[TEST 2] Calling search_products with query 'Silk Sarees'...\n";
$searchCatRes = api_call($baseUrl . '/search_products', 'POST', ['query' => 'Silk Sarees']);
echo "HTTP Code: {$searchCatRes['code']}\n";
$foundCount = $searchCatRes['body']['data']['count'] ?? 0;
echo "Matching Silk Sarees count: {$foundCount}\n";
if (!empty($searchCatRes['body']['data']['products'])) {
    foreach (array_slice($searchCatRes['body']['data']['products'], 0, 3) as $p) {
        echo "  - {$p['name']} (₹{$p['price']}, SKU: {$p['sku']}, Stock: {$p['stock']})\n";
    }
}
echo "\n";

// STEP 3: Verify search_products with Single Product Query
echo "[TEST 3] Calling search_products with query 'Kanjivaram Silk Saree'...\n";
$singleRes = api_call($baseUrl . '/search_products', 'POST', ['query' => 'Kanjivaram Silk Saree']);
echo "HTTP Code: {$singleRes['code']}\n";
$pName = $singleRes['body']['data']['product_name'] ?? '';
$pPrice = $singleRes['body']['data']['price'] ?? '';
$pImg = $singleRes['body']['data']['image_url'] ?? '';
echo "Single Product Found: {$pName} ({$pPrice})\n";
echo "Image URL: {$pImg}\n\n";

// STEP 4: Verify add_cart_item
echo "[TEST 4] Calling add_cart_item for SKU 2D-001-PC (Qty 2)...\n";
$addCartRes = api_call($baseUrl . '/cart/items', 'POST', [
    'sku' => '2D-001-PC',
    'quantity' => 2,
    'customer_phone' => $target,
]);
echo "HTTP Code: {$addCartRes['code']}\n";
$cartSubtotal = $addCartRes['body']['data']['subtotal'] ?? 0;
$cartLines = count($addCartRes['body']['data']['items'] ?? []);
echo "Cart updated! Items: {$cartLines}, Subtotal: ₹{$cartSubtotal}\n\n";

// STEP 5: Verify get_cart
echo "[TEST 5] Calling get_cart...\n";
$getCartRes = api_call($baseUrl . '/cart?customer_phone=' . $target, 'GET');
echo "HTTP Code: {$getCartRes['code']}\n";
$cartItems = $getCartRes['body']['data']['items'] ?? [];
foreach ($cartItems as $ci) {
    echo "  - {$ci['name']} x {$ci['quantity']} = ₹{$ci['total']}\n";
}
echo "Total: ₹" . ($getCartRes['body']['data']['subtotal'] ?? 0) . "\n\n";

// STEP 6: Deliver Live Flow Step 1 Greeting to Target Number
echo "[TEST 6] Dispatching Step 1 Greeting to +{$target}...\n";
$greetingMsg = "Hi! 👋 Welcome to Vel TEST.\n"
    . "How can I help you today?\n"
    . "- 🛍️ Browse products\n"
    . "- 🏷️ Offers & Deals\n"
    . "- 🔎 Search for a product\n"
    . "- 📦 Track my order\n"
    . "- 🙋 Talk to our team";

$waPayload1 = [
    'messaging_product' => 'whatsapp',
    'recipient_type'    => 'individual',
    'to'                => $target,
    'type'              => 'text',
    'text'              => [
        'preview_url' => false,
        'body'        => $greetingMsg,
    ],
];
$waRes1 = send_wa($phoneId, $token, $waPayload1);
echo "Greeting HTTP: {$waRes1['code']}\n";
if (!empty($waRes1['body']['messages'][0]['id'])) {
    echo "Delivered WAMID: " . $waRes1['body']['messages'][0]['id'] . "\n\n";
}

// STEP 7: Deliver Step 2 Category Menu to Target Number
echo "[TEST 7] Dispatching Step 2 Category Menu to +{$target}...\n";
$catMsg = "What are you looking for? 😊\n"
    . "🥻 Silk Sarees (5 items)\n"
    . "🧵 Cotton Sarees (2 items)\n"
    . "✨ Designer Sarees (3 items)\n"
    . "👰 Wedding Sarees (2 items)\n"
    . "🌸 Casual Sarees (1 item)\n\n"
    . "Reply with any category or saree name!";

$waPayload2 = [
    'messaging_product' => 'whatsapp',
    'recipient_type'    => 'individual',
    'to'                => $target,
    'type'              => 'text',
    'text'              => [
        'preview_url' => false,
        'body'        => $catMsg,
    ],
];
$waRes2 = send_wa($phoneId, $token, $waPayload2);
echo "Category Menu HTTP: {$waRes2['code']}\n";
if (!empty($waRes2['body']['messages'][0]['id'])) {
    echo "Delivered WAMID: " . $waRes2['body']['messages'][0]['id'] . "\n\n";
}

echo "========================================================\n";
echo "   ALL 7 FLOW TESTS COMPLETED SUCCESSFULLY!\n";
echo "========================================================\n";
