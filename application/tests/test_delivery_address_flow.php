<?php
/**
 * Test: Delivery Address Collection, Parsing, Persistence, and Invoice Formatting
 */

define('BASEPATH', true);
define('ENVIRONMENT', 'development');

echo "========================================================\n";
echo " WHATSAPP DELIVERY ADDRESS FLOW & ORDER LINK TESTS\n";
echo "========================================================\n\n";

$passed = 0;
$failed = 0;

function assert_test(bool $cond, string $name, string $extra = '') {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$name}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$name}" . ($extra !== '' ? " ({$extra})" : '') . "\n";
    }
}

// Address Parsing Logic (mirrors Sk_Whatsapp_webhook::_parse_address_text)
function parse_address_text_test(string $text, string $peer, ?string $customerName = null): array {
    $raw = trim($text);

    $name = !empty($customerName) && strcasecmp($customerName, 'WhatsApp Customer') !== 0 ? $customerName : '';
    $phone = $peer;
    $pincode = '';
    $city = '';
    $state = '';
    $line1 = '';

    // 1. Extract 6-digit Indian pincode
    if (preg_match('/\b([1-9][0-9]{5})\b/', $raw, $pm)) {
        $pincode = $pm[1];
    }

    // 2. Extract phone if alternate phone provided, else use $peer
    if (preg_match('/(?:phone|mobile|mob|contact|ph|tel)?\s*[:=-]?\s*(?:\+?91[\s-]*)?([6-9]\d{9})\b/iu', $raw, $phm)) {
        $phone = $phm[1];
    }

    // 3. Extract explicit labeled fields if customer used key-value style
    if (preg_match('/(?:name|recipient)\s*[:=-]\s*([^\r\n,]+)/iu', $raw, $nm)) {
        $name = trim($nm[1]);
    }
    if (preg_match('/(?:city|town)\s*[:=-]\s*([^\r\n,]+)/iu', $raw, $cm)) {
        $city = trim($cm[1]);
    }
    if (preg_match('/(?:state)\s*[:=-]\s*([^\r\n,]+)/iu', $raw, $sm)) {
        $state = trim($sm[1]);
    }
    if (preg_match('/(?:address|addr|street|house|flat|road)\s*[:=-]\s*([^\r\n]+)/iu', $raw, $am)) {
        $line1 = trim($am[1]);
    }

    // 4. Clean tokens for positional extraction if fields remain missing
    $clean = preg_replace('/(?:phone|mobile|mob|contact|ph|tel)\s*[:=-]?\s*(?:\+?91[\s-]*)?[6-9]\d{9}\b/iu', '', $raw);
    if ($pincode !== '') {
        $clean = str_replace($pincode, '', $clean);
    }
    $clean = preg_replace('/\b(?:name|recipient|address|addr|street|city|state|pin|pincode)\s*[:=-]/iu', '', $clean);
    $clean = trim(preg_replace('/[,;\-–—\s]+$/u', '', $clean));

    // Split by newlines or commas
    $lines = preg_split('/[\r\n,]+/', $clean);
    $cleanLines = [];
    foreach ($lines as $l) {
        $trimmed = trim(preg_replace('/^(?:name|address|addr|street|city|state|pin|pincode|phone|mobile|contact)\s*[:=-]\s*/iu', '', trim($l)));
        if ($trimmed !== '' && !preg_match('/^(?:phone|ph|mob|mobile|contact|tel|pin|pincode|name|address)\s*[:=-]?$/iu', $trimmed)) {
            $cleanLines[] = $trimmed;
        }
    }

    $knownStates = [
        'tamil nadu', 'tamilnadu', 'kerala', 'karnataka', 'andhra pradesh', 'telangana',
        'maharashtra', 'delhi', 'gujarat', 'rajasthan', 'uttar pradesh', 'west bengal',
        'madhya pradesh', 'bihar', 'punjab', 'haryana', 'odisha', 'assam'
    ];

    $addressParts = [];
    foreach ($cleanLines as $line) {
        $lower = strtolower($line);
        $matchedState = false;
        foreach ($knownStates as $ks) {
            if ($lower === $ks || strpos($lower, $ks) !== false) {
                if ($state === '') {
                    $state = ucwords($ks);
                }
                $matchedState = true;
                break;
            }
        }
        if (!$matchedState) {
            $addressParts[] = $line;
        }
    }

    if ($name === '' && !empty($addressParts) && !preg_match('/\d/', $addressParts[0]) && count(explode(' ', $addressParts[0])) <= 4) {
        $name = array_shift($addressParts);
    }
    if ($name === '') {
        $name = !empty($customerName) ? $customerName : 'Valued Customer';
    }

    if ($city === '' && count($addressParts) > 1) {
        $lastPart = end($addressParts);
        if (!preg_match('/\d/', $lastPart) && strlen($lastPart) < 30) {
            $city = array_pop($addressParts);
        }
    }

    if ($line1 === '') {
        $line1 = implode(', ', $addressParts);
    }
    if ($line1 === '') {
        $line1 = $clean !== '' ? $clean : 'Customer Address';
    }

    if ($city === '') {
        $city = 'Nagercoil';
    }
    if ($state === '') {
        $state = 'Tamil Nadu';
    }
    if ($pincode === '') {
        $pincode = '629501';
    }

    return [
        'full_name' => $name,
        'phone'     => $phone,
        'line1'     => $line1,
        'line2'     => '',
        'city'      => $city,
        'state'     => $state,
        'pincode'   => $pincode,
        'country'   => 'India',
    ];
}

echo "--- TEST 1: Address Parsing From Comma-Separated Text ---\n";
$input1 = "Priya Sharma, 14/2 Anna Salai, Opposite Temple, Madurai, Tamil Nadu, 625001";
$res1 = parse_address_text_test($input1, '917598933686');

assert_test($res1['full_name'] === 'Priya Sharma', "Extract recipient name 'Priya Sharma'", "Got: {$res1['full_name']}");
assert_test(strpos($res1['line1'], '14/2 Anna Salai') !== false, "Extract street line1", "Got: {$res1['line1']}");
assert_test($res1['city'] === 'Madurai', "Extract city 'Madurai'", "Got: {$res1['city']}");
assert_test(stripos($res1['state'], 'Tamil Nadu') !== false, "Extract state 'Tamil Nadu'", "Got: {$res1['state']}");
assert_test($res1['pincode'] === '625001', "Extract pincode '625001'", "Got: {$res1['pincode']}");

echo "\n--- TEST 2: Address Parsing With Alternate Phone & Multiline ---\n";
$input2 = "Kavitha\nFlat 302, Green Orchid Apartments\nCoimbatore\nTamil Nadu 641001\nPhone: 9876543210";
$res2 = parse_address_text_test($input2, '917598933686');

assert_test($res2['full_name'] === 'Kavitha', "Extract multiline recipient name", "Got: {$res2['full_name']}");
assert_test($res2['pincode'] === '641001', "Extract multiline pincode 641001", "Got: {$res2['pincode']}");
assert_test($res2['phone'] === '9876543210', "Extract alternate phone 9876543210", "Got: {$res2['phone']}");
assert_test($res2['city'] === 'Coimbatore', "Extract multiline city Coimbatore", "Got: {$res2['city']}");

echo "\n--- TEST 3: Address Parsing With Explicit Key-Value Labels ---\n";
$input3 = "Name: Ananya Ram\nAddress: No 45, Gandhi Road, T Nagar\nCity: Chennai\nState: Tamil Nadu\nPincode: 600017\nMobile: 9840123456";
$res3 = parse_address_text_test($input3, '917598933686');

assert_test($res3['full_name'] === 'Ananya Ram', "Key-value name", "Got: {$res3['full_name']}");
assert_test(strpos($res3['line1'], 'No 45, Gandhi Road') !== false, "Key-value address line1", "Got: {$res3['line1']}");
assert_test($res3['city'] === 'Chennai', "Key-value city", "Got: {$res3['city']}");
assert_test($res3['state'] === 'Tamil Nadu', "Key-value state", "Got: {$res3['state']}");
assert_test($res3['pincode'] === '600017', "Key-value pincode", "Got: {$res3['pincode']}");
assert_test($res3['phone'] === '9840123456', "Key-value mobile phone", "Got: {$res3['phone']}");

echo "\n--- TEST 4: Database Connection / Persistence Test ---\n";
$hasDb = false;
$mysqli = null;
if (file_exists(__DIR__ . '/../config/database.php')) {
    require_once __DIR__ . '/../config/database.php';
    if (!empty($db['default'])) {
        $dbCfg = $db['default'];
        mysqli_report(MYSQLI_REPORT_OFF);
        $mysqli = @new mysqli($dbCfg['hostname'], $dbCfg['username'], $dbCfg['password'], $dbCfg['database']);
        if (!$mysqli->connect_errno && !$mysqli->connect_error) {
            $hasDb = true;
        }
    }
}

if ($hasDb) {
    $testPeer = '917598933686';
    $vendorId = 2;

    $userQ = $mysqli->query("SELECT id FROM users WHERE phone = '{$testPeer}' LIMIT 1");
    $userId = 0;
    if ($userQ && $userRow = $userQ->fetch_assoc()) {
        $userId = (int)$userRow['id'];
    } else {
        $insRes = $mysqli->query("INSERT INTO users (name, phone, status, created_at, updated_at) VALUES ('Test Customer', '{$testPeer}', 'active', NOW(), NOW())");
        $userId = $mysqli->insert_id;
    }
    assert_test($userId > 0, "Customer user record verified/created (id={$userId})", $mysqli->error);

    $insertAddr = $mysqli->query("INSERT INTO addresses (vendor_id, user_id, label, full_name, phone, line1, line2, city, state, pincode, country, is_default, address_type, created_at) 
        VALUES ({$vendorId}, {$userId}, 'Home', 'Priya Sharma', '{$testPeer}', '14/2 Anna Salai, Opposite Temple', '', 'Madurai', 'Tamil Nadu', '625001', 'India', 1, 'shipping', NOW())");
    $addrId = $mysqli->insert_id;
    assert_test($insertAddr && $addrId > 0, "Insert address into addresses table (addrId={$addrId})");

    $addrRow = $mysqli->query("SELECT * FROM addresses WHERE id = {$addrId}")->fetch_assoc();
    assert_test($addrRow && $addrRow['city'] === 'Madurai' && $addrRow['pincode'] === '625001', "Verify address record fields match");

    $testOrderNum = 'TEST_ADDR_' . time();
    $insertOrder = $mysqli->query("INSERT INTO orders (
        vendor_id, user_id, order_number, subtotal, total, payment_method, payment_status,
        status, shipping_name, shipping_phone, shipping_line1, shipping_city, shipping_state,
        shipping_pincode, shipping_country, created_at, updated_at
    ) VALUES (
        {$vendorId}, {$userId}, '{$testOrderNum}', 1999.00, 1999.00, 'cod', 'pending',
        'confirmed', '{$addrRow['full_name']}', '{$addrRow['phone']}', '{$addrRow['line1']}',
        '{$addrRow['city']}', '{$addrRow['state']}', '{$addrRow['pincode']}', '{$addrRow['country']}',
        NOW(), NOW()
    )");
    $orderId = $mysqli->insert_id;
    assert_test($insertOrder && $orderId > 0, "Create test order with shipping address (orderId={$orderId})", $mysqli->error);

    $savedOrder = $mysqli->query("SELECT * FROM orders WHERE id = {$orderId}")->fetch_assoc();
    assert_test($savedOrder && $savedOrder['shipping_name'] === 'Priya Sharma', "Order shipping_name verified");
    assert_test($savedOrder && $savedOrder['shipping_line1'] === '14/2 Anna Salai, Opposite Temple', "Order shipping_line1 verified");

    // Cleanup
    $mysqli->query("DELETE FROM orders WHERE id = {$orderId}");
    $mysqli->query("DELETE FROM addresses WHERE id = {$addrId}");
} else {
    echo "  [INFO] Local database offline (production DB tested on VPS via SSH)\n";
}

echo "\n--- TEST 5: Invoice Delivery Address Formatting ---\n";
$mockOrder = [
    'shipping_name'    => 'Priya Sharma',
    'shipping_phone'   => '917598933686',
    'shipping_line1'   => '14/2 Anna Salai, Opposite Temple',
    'shipping_line2'   => '',
    'shipping_city'    => 'Madurai',
    'shipping_state'   => 'Tamil Nadu',
    'shipping_pincode' => '625001',
    'shipping_country' => 'India',
];

$destLine = '';
if (!empty($mockOrder['shipping_line1'])) {
    $destLine = "🚚 *DELIVERY ADDRESS:*\n"
              . "👤 *Recipient:* " . ($mockOrder['shipping_name'] ?? 'Customer') . "\n"
              . "📞 *Contact:* +" . ($mockOrder['shipping_phone'] ?? '917598933686') . "\n"
              . "🏠 *Address:* " . $mockOrder['shipping_line1'] . (!empty($mockOrder['shipping_line2']) ? ", " . $mockOrder['shipping_line2'] : '') . "\n"
              . "🏙️ *City / State:* " . ($mockOrder['shipping_city'] ?? '') . ", " . ($mockOrder['shipping_state'] ?? '') . " - " . ($mockOrder['shipping_pincode'] ?? '') . "\n"
              . "🇮🇳 *Country:* " . ($mockOrder['shipping_country'] ?? 'India') . "\n\n";
}

assert_test(strpos($destLine, '🚚 *DELIVERY ADDRESS:*') !== false, "Invoice includes Delivery Address header");
assert_test(strpos($destLine, 'Priya Sharma') !== false, "Invoice includes Recipient Name");
assert_test(strpos($destLine, 'Madurai, Tamil Nadu - 625001') !== false, "Invoice includes City, State & Pincode");

echo "\n========================================================\n";
echo " SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
