<?php
/**
 * End-to-end HTTP integration tests for Meta Business Agent multi-item cart management.
 */

// Connect directly to MySQL using database.php config
define('BASEPATH', true);
define('ENVIRONMENT', 'development');
require_once __DIR__ . '/../config/database.php';
$dbConfig = $db['default'];
$mysqli = new mysqli($dbConfig['hostname'], $dbConfig['username'], $dbConfig['password'], $dbConfig['database']);
if ($mysqli->connect_error) {
    die("DB connection failed: " . $mysqli->connect_error . "\n");
}

// 1. Find active WhatsApp account
$res = $mysqli->query("SELECT phone_number_id, vendor_id, status FROM vendor_whatsapp_accounts WHERE status = 'active' LIMIT 1");
$account = $res ? $res->fetch_assoc() : null;
if (!$account) {
    $res = $mysqli->query("SELECT phone_number_id, vendor_id, status FROM vendor_whatsapp_accounts LIMIT 1");
    $account = $res ? $res->fetch_assoc() : null;
    if ($account) {
        $mysqli->query("UPDATE vendor_whatsapp_accounts SET status = 'active' WHERE phone_number_id = '{$account['phone_number_id']}'");
    } else {
        $mysqli->query("INSERT INTO vendor_whatsapp_accounts (vendor_id, phone_number_id, display_phone, status, created_at) VALUES (1, '999888777666', '+919998887776', 'active', NOW())");
        $account = ['phone_number_id' => '999888777666', 'vendor_id' => 1];
    }
}
$phoneNumberId = $account['phone_number_id'];
$vendorId = (int)$account['vendor_id'];

// 2. Find an active product for this vendor
$res = $mysqli->query("SELECT id, name, sku, price, stock FROM products WHERE vendor_id = {$vendorId} AND status = 'active' AND stock > 5 LIMIT 1");
$product = $res ? $res->fetch_assoc() : null;
if (!$product) {
    // Pick any active product and update its vendor_id
    $res = $mysqli->query("SELECT id, name, sku, price, stock FROM products WHERE status = 'active' AND stock > 5 LIMIT 1");
    $product = $res ? $res->fetch_assoc() : null;
    if ($product) {
        $mysqli->query("UPDATE products SET vendor_id = {$vendorId} WHERE id = {$product['id']}");
    }
}
if (!$product) {
    die("No active products with stock > 5 found.\n");
}

$sku = trim((string)$product['sku']);
if ($sku === '') {
    $sku = 'TEST-SKU-' . $product['id'];
    $mysqli->query("UPDATE products SET sku = '{$sku}' WHERE id = {$product['id']}");
}

echo "Testing with:\n";
echo "  Phone Number ID: {$phoneNumberId}\n";
echo "  Vendor ID: {$vendorId}\n";
echo "  Product ID: {$product['id']}, SKU: '{$sku}', Price: {$product['price']}, Stock: {$product['stock']}\n";

$baseUrl = 'http://127.0.0.1:8088/shopkart-api/meta-agent/connectors/' . $phoneNumberId;
$apiKey = 'test_meta_connector_key_1234567890';
$sessionId = 'e2e_meta_cart_session_' . time();

function http_req($method, $url, $data = null, $headers = []) {
    $ch = curl_init($url);
    $allHeaders = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    foreach ($headers as $k => $v) {
        $allHeaders[] = "{$k}: {$v}";
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [
        'status' => $httpCode,
        'raw' => $raw,
        'json' => json_decode((string)$raw, true),
    ];
}

$commonHeaders = [
    'X-Api-Key' => $apiKey,
    'X-Session-ID' => $sessionId,
];

$passCount = 0;
function test_assert($cond, $label) {
    global $passCount;
    if ($cond) {
        echo "[PASS] {$label}\n";
        $passCount++;
    } else {
        echo "[FAIL] {$label}\n";
        exit(1);
    }
}

echo "\n--- TEST 1: GET /cart (Empty basket) ---\n";
$res = http_req('GET', $baseUrl . '/cart', null, $commonHeaders);
test_assert($res['status'] === 200, 'Status is 200 OK');
test_assert(isset($res['json']['data']['lines']) && empty($res['json']['data']['lines']), 'Lines is empty array');
test_assert(isset($res['json']['data']['item_count']) && $res['json']['data']['item_count'] === 0, 'item_count is 0');
test_assert(isset($res['json']['data']['subtotal']) && $res['json']['data']['subtotal'] == 0, 'subtotal is 0');
test_assert(isset($res['json']['data']['notes']) && strpos($res['json']['data']['notes'], 'Delivery is added at checkout') !== false, 'Notes mentions delivery added at checkout');

echo "\n--- TEST 2: POST /cart/items (Reject quantity 0) ---\n";
$res = http_req('POST', $baseUrl . '/cart/items', ['sku' => $sku, 'quantity' => 0], $commonHeaders);
test_assert($res['status'] === 400, 'Status is 400 Bad Request');
test_assert(strpos($res['json']['message'] ?? '', 'at least 1') !== false, 'Message requires at least 1 unit');

echo "\n--- TEST 3: POST /cart/items (Add 2 units) ---\n";
$res = http_req('POST', $baseUrl . '/cart/items', ['sku' => $sku, 'quantity' => 2], $commonHeaders);
test_assert($res['status'] === 200, 'Status is 200 OK');
test_assert(count($res['json']['data']['lines']) === 1, 'Basket has 1 line');
test_assert($res['json']['data']['item_count'] === 2, 'item_count is 2');
echo "Returned Line: " . json_encode($res['json']['data']['lines'][0]) . "\n";
test_assert(!empty($res['json']['data']['lines'][0]['sku']), 'Line SKU is not empty: ' . $res['json']['data']['lines'][0]['sku']);
$lineId = $res['json']['data']['lines'][0]['line_id'];
test_assert(!empty($lineId), "line_id is present and stable ({$lineId})");
$unitPrice = (float)$res['json']['data']['lines'][0]['unit_price'];
$expectedLineTotal = round($unitPrice * 2, 2);
test_assert(abs($res['json']['data']['lines'][0]['line_total'] - $expectedLineTotal) < 0.01, 'line_total is computed correctly by server (' . $expectedLineTotal . ')');

echo "\n--- TEST 4: PUT /cart/items/{line_id} (Update quantity to 5) ---\n";
$res = http_req('PUT', $baseUrl . '/cart/items/' . $lineId, ['quantity' => 5], $commonHeaders);
test_assert($res['status'] === 200, 'Status is 200 OK');
test_assert($res['json']['data']['item_count'] === 5, 'item_count is updated to 5');
test_assert($res['json']['data']['lines'][0]['quantity'] === 5, 'line quantity is updated to 5');

echo "\n--- TEST 5: PUT /cart/items/{line_id} (Reject quantity 0 with specific instruction) ---\n";
$res = http_req('PUT', $baseUrl . '/cart/items/' . $lineId, ['quantity' => 0], $commonHeaders);
test_assert($res['status'] === 400, 'Status is 400 Bad Request');
test_assert(strpos($res['json']['message'] ?? '', 'Setting a quantity of zero is rejected; use remove_cart_item instead') !== false, 'Rejects zero and points to remove_cart_item');

echo "\n--- TEST 6: Limit check & headroom calculation ---\n";
$res = http_req('POST', $baseUrl . '/cart/items', ['sku' => $sku, 'quantity' => 9999], $commonHeaders);
test_assert($res['status'] === 400, 'Status is 400 Bad Request for breach of limit');
$errData = $res['json']['errors']['data'] ?? $res['json']['data'] ?? [];
test_assert(isset($errData['headroom']), 'headroom is calculated and returned in response');
test_assert(isset($errData['current_quantity']), 'current_quantity is returned in response');
// Verify all-or-nothing: cart quantity remains 5, not partially modified
$cartCheck = http_req('GET', $baseUrl . '/cart', null, $commonHeaders);
test_assert($cartCheck['json']['data']['item_count'] === 5, 'All-or-nothing verified: cart remains at 5');

echo "\n--- TEST 7: POST /cart/discount (Apply invalid code) ---\n";
$res = http_req('POST', $baseUrl . '/cart/discount', ['code' => 'BADCODE999'], $commonHeaders);
test_assert($res['status'] === 400, 'Status is 400 Bad Request');
test_assert(stripos($res['json']['message'] ?? '', 'not valid') !== false || stripos($res['json']['message'] ?? '', 'invalid') !== false, 'Service reason reported without speculation: ' . ($res['json']['message'] ?? ''));

echo "\n--- TEST 8: DELETE /cart/items/{line_id} (Remove item) ---\n";
$res = http_req('DELETE', $baseUrl . '/cart/items/' . $lineId, null, $commonHeaders);
test_assert($res['status'] === 200, 'Status is 200 OK');
test_assert(empty($res['json']['data']['lines']), 'Lines is now empty');
test_assert($res['json']['data']['item_count'] === 0, 'item_count is now 0');
test_assert($res['json']['data']['subtotal'] == 0, 'subtotal is now 0');

echo "\n--- TEST 9: Auth verification ---\n";
// Calling without X-Api-Key
$unauth = http_req('GET', $baseUrl . '/cart', null, ['X-Session-ID' => $sessionId]);
test_assert($unauth['status'] === 403, 'Unauthorized request without API key returns 403');

echo "\n============================================\n";
echo "SUCCESS: ALL {$passCount} E2E HTTP TESTS PASSED!\n";
echo "============================================\n";
