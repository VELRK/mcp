<?php
/**
 * Test Suite: WhatsApp Ecommerce Interactive Menu & 5-Button Sales Flow
 * 
 * Tests:
 * 1. Main Menu (Hi / Menu) -> Interactive List with 5 options
 * 2. Button 1 (browse_products) -> Categories interactive list & products
 * 3. Button 2 (offers_deals) -> Active discounts, deals & coupons
 * 4. Button 3 (search_product) -> Product search by keyword
 * 5. Button 4 (track_order) -> Order tracking with customer phone & order #
 * 6. Button 5 (talk_to_team) -> Human handoff & support response
 * 7. Security: Auth check & cross-tenant scoping
 */

define('BASEPATH', true);
define('ENVIRONMENT', 'development');
require_once __DIR__ . '/../config/database.php';
$dbConfig = $db['default'];
$mysqli = new mysqli($dbConfig['hostname'], $dbConfig['username'], $dbConfig['password'], $dbConfig['database']);
if ($mysqli->connect_error) {
    die("DB connection failed: " . $mysqli->connect_error . "\n");
}

// Load environment if available
if (is_file(__DIR__ . '/../helpers/sk_dotenv_helper.php')) {
    require_once __DIR__ . '/../helpers/sk_dotenv_helper.php';
    if (function_exists('sk_dotenv_load')) {
        sk_dotenv_load(dirname(__DIR__, 2) . '/.env');
    }
}

// 1. Resolve active WhatsApp account
$res = $mysqli->query("SELECT phone_number_id, vendor_id, status FROM vendor_whatsapp_accounts WHERE status = 'active' LIMIT 1");
$account = $res ? $res->fetch_assoc() : null;
if (!$account) {
    $res = $mysqli->query("SELECT phone_number_id, vendor_id, status FROM vendor_whatsapp_accounts LIMIT 1");
    $account = $res ? $res->fetch_assoc() : null;
}
$phoneNumberId = $account['phone_number_id'] ?? '999888777666';
$vendorId = (int)($account['vendor_id'] ?? 1);

$envKey = function_exists('sk_env') ? sk_env('META_BA_CONNECTOR_API_KEY', 'test_meta_connector_key_1234567890') : 'test_meta_connector_key_1234567890';
$envBase = function_exists('sk_env') ? sk_env('META_BA_CONNECTOR_BASE_URL', '') : '';

$cliBase = $argv[1] ?? '';
$cliKey = $argv[2] ?? '';

if ($cliBase !== '') {
    $baseUrl = rtrim($cliBase, '/');
} elseif ($envBase !== '') {
    $baseUrl = rtrim($envBase, '/') . '/shopkart-api/meta-agent/connectors/' . $phoneNumberId;
} else {
    $baseUrl = 'http://127.0.0.1:8088/shopkart-api/meta-agent/connectors/' . $phoneNumberId;
}

$apiKey = $cliKey !== '' ? $cliKey : $envKey;

function http_req($method, $url, $data = null, $token = null) {
    global $apiKey;
    if ($token === null) {
        $token = $apiKey;
    }
    $ch = curl_init($url);
    $headers = [
        'Accept: application/json',
        'X-Api-Key: ' . $token,
    ];
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        }
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($res, true) ?: $res, 'raw' => $res];
}

$failures = 0;
function test_assert($cond, $label, $extra = '') {
    global $failures;
    if ($cond) {
        echo "  [PASS] {$label}\n";
    } else {
        echo "  [FAIL] {$label}" . ($extra !== '' ? " ({$extra})" : '') . "\n";
        $failures++;
    }
}

echo "========================================================\n";
echo " WHATSAPP ECOMMERCE INTERACTIVE MENU & SALES FLOW TESTS\n";
echo "========================================================\n";
echo "Phone Number ID: {$phoneNumberId}\n";
echo "Vendor ID: {$vendorId}\n";
echo "Base URL: {$baseUrl}\n\n";

// ── TEST 1: Main Menu Trigger (Hi / Menu) ──────────────────────
echo "--- TEST 1: Main Menu & 5-Option Interactive List ---\n";
$res1 = http_req('GET', $baseUrl . '/get_main_menu');
test_assert($res1['code'] === 200, "GET /get_main_menu returned 200", "Got HTTP " . $res1['code']);
$body1 = $res1['body']['data'] ?? [];
test_assert(($body1['ui'] ?? '') === 'interactive_list', "UI component is 'interactive_list'");
$inter1 = $body1['interactive'] ?? [];
test_assert(($inter1['type'] ?? '') === 'list', "Interactive type is 'list'");
test_assert(!empty($inter1['action']['button']), "Interactive action button exists ('" . ($inter1['action']['button'] ?? '') . "')");
$rows1 = $inter1['action']['sections'][0]['rows'] ?? [];
test_assert(count($rows1) === 5, "Interactive list contains exactly 5 rows (Got " . count($rows1) . ")");
$rowIds = array_column($rows1, 'id');
test_assert(in_array('browse_products', $rowIds, true), "Option 1: 'browse_products' present");
test_assert(in_array('offers_deals', $rowIds, true), "Option 2: 'offers_deals' present");
test_assert(in_array('search_product', $rowIds, true), "Option 3: 'search_product' present");
test_assert(in_array('track_order', $rowIds, true), "Option 4: 'track_order' present");
test_assert(in_array('talk_to_team', $rowIds, true), "Option 5: 'talk_to_team' present");
test_assert(strpos($body1['message'] ?? '', '1. 🛍️ Browse products') !== false, "Fallback text contains numbered choices");

// ── TEST 2: Button 1 — Browse Products & Categories ───────────
echo "\n--- TEST 2: Button 1 — 🛍️ Browse Products & Categories ---\n";
$res2 = http_req('GET', $baseUrl . '/get_categories');
test_assert($res2['code'] === 200, "GET /get_categories returned 200");
$body2 = $res2['body']['data'] ?? [];
test_assert(($body2['ui'] ?? '') === 'interactive_list', "Categories UI is 'interactive_list'");
$cats = $body2['categories'] ?? [];
test_assert(count($cats) > 0, "Returned " . count($cats) . " active categories for vendor");
$inter2 = $body2['interactive'] ?? [];
test_assert(($inter2['action']['button'] ?? '') === 'View Categories', "Action button is 'View Categories'");

// ── TEST 3: Button 2 — Offers & Deals ─────────────────────────
echo "\n--- TEST 3: Button 2 — 🏷️ Offers & Deals ---\n";
$res3 = http_req('GET', $baseUrl . '/get_offers');
test_assert($res3['code'] === 200, "GET /get_offers returned 200");
$body3 = $res3['body']['data'] ?? [];
test_assert(isset($body3['count']), "Offers response has count field");
if ($body3['count'] > 0) {
    test_assert(!empty($body3['offers']), "Offers list is populated");
    test_assert(strpos($body3['message'] ?? '', '🎉') !== false, "Offer message contains formatted emoji text");
} else {
    test_assert(strpos($body3['message'] ?? '', 'No active offers right now') !== false, "Graceful fallback message when no active offers");
}

// ── TEST 4: Button 3 — Search for a Product ───────────────────
echo "\n--- TEST 4: Button 3 — 🔎 Search for a Product ---\n";
$res4 = http_req('POST', $baseUrl . '/search_products', ['query' => 'saree']);
test_assert($res4['code'] === 200, "POST /search_products returned 200");
$body4 = $res4['body']['data'] ?? [];
test_assert(isset($body4['count']), "Search response has count field (Found " . ($body4['count'] ?? 0) . " items)");
test_assert(!empty($body4['message']), "Search returns customer message text");

// ── TEST 5: Button 4 — Track My Order ─────────────────────────
echo "\n--- TEST 5: Button 4 — 📦 Track My Order ---\n";
// Case 5A: Non-existent order
$res5a = http_req('POST', $baseUrl . '/get_order_status', ['order_number' => 'NON_EXISTENT_99999']);
test_assert($res5a['code'] === 404, "Invalid order returns 404 not found");

// Case 5B: Lookup by customer phone
$res5b = http_req('POST', $baseUrl . '/get_order_status', ['phone' => '919876543210']);
test_assert($res5b['code'] === 200, "Phone order lookup returned 200");
$body5b = $res5b['body']['data'] ?? [];
test_assert(isset($body5b['count']), "Phone lookup returned valid count");
test_assert(!empty($body5b['message']), "Phone lookup returned friendly customer message");

// ── TEST 6: Button 5 — Talk to Our Team ───────────────────────
echo "\n--- TEST 6: Button 5 — 🙋 Talk to Our Team ---\n";
$res6 = http_req('POST', $baseUrl . '/handover_to_human', [
    'phone'  => '919876543210',
    'reason' => 'Customer clicked Talk to our team',
]);
test_assert($res6['code'] === 200, "POST /handover_to_human returned 200");
$body6 = $res6['body']['data'] ?? [];
test_assert(!empty($body6['handoff']), "Handoff flag is true");
test_assert(strpos($body6['message'] ?? '', 'connecting you with our team') !== false, "Polite waiting message returned");

// ── TEST 7: Security & Tenant Isolation ───────────────────────
echo "\n--- TEST 7: Security & Tenant Authorization ---\n";
$res7a = http_req('GET', $baseUrl . '/get_main_menu', null, 'wrong-api-key');
test_assert($res7a['code'] === 403, "Invalid API key rejected with 403");

$prefixBase = substr($baseUrl, 0, (int)strrpos($baseUrl, '/'));
$res7b = http_req('GET', $prefixBase . '/000000000000/get_main_menu');
test_assert($res7b['code'] === 404, "Unknown phone number rejected with 404");

echo "\n========================================================\n";
if ($failures === 0) {
    echo " ALL TESTS PASSED! WhatsApp Interactive Menu & Sales Flow Ready.\n";
} else {
    echo " {$failures} TEST(S) FAILED. Please review output above.\n";
}
echo "========================================================\n";
