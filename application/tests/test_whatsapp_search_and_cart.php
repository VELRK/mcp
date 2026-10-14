<?php
/**
 * Test: WhatsApp Product Search, Variant Selection, Quantity Update, and Cart
 */

define('BASEPATH', true);
define('ENVIRONMENT', 'development');
require_once __DIR__ . '/../config/database.php';
$dbCfg = $db['default'];
$mysqli = new mysqli($dbCfg['hostname'], $dbCfg['username'], $dbCfg['password'], $dbCfg['database']);
if ($mysqli->connect_error) {
    die("DB connection failed: " . $mysqli->connect_error . "\n");
}

echo "========================================================\n";
echo " WHATSAPP SEARCH, VARIANT & CART FLOW TESTS\n";
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

// 1. Test search cleaning logic
function clean_test_query(string $query): string {
    $noisePhrases = [
        'do you have', 'do u have', 'do you got', 'have you got', 'u have', 'you have',
        'is there', 'are there', 'can you show', 'can u show', 'show me', 'show',
        'give me', 'send me', 'tell me about', 'tell me', 'looking for', 'look for',
        'i want', 'i need', 'we want', 'we need', 'please show', 'please send',
        'what do you have', 'what do u have', 'any', 'available', 'avail',
        'in stock', 'stock', 'collection', 'catalog', 'options', 'designs', 'models',
        'hi', 'hai', 'hello', 'hey', 'please', 'pls', 'plz', 'vanakkam'
    ];
    $cleaned = trim(mb_strtolower($query, 'UTF-8'));
    foreach ($noisePhrases as $np) {
        $cleaned = preg_replace('/\b' . preg_quote($np, '/') . '\b/iu', ' ', $cleaned);
    }
    $cleaned = trim(preg_replace('/[?!.,\-_#@%^&*()+=~`]/u', ' ', $cleaned));
    $cleaned = trim(preg_replace('/\s+/u', ' ', $cleaned));
    $withoutSaree = trim((string)preg_replace('/\b(saree|sarees|cloth|clothes|dress|item|items|product|products)\b/iu', ' ', $cleaned));
    $withoutSaree = trim(preg_replace('/\s+/u', ' ', $withoutSaree));
    return $withoutSaree !== '' ? $withoutSaree : $cleaned;
}

echo "--- TEST 1: Conversational Query Cleaning ---\n";
$c1 = clean_test_query('Cotton saree do u have');
assert_test($c1 === 'cotton', "Clean 'Cotton saree do u have'", "Got '{$c1}'");

$c2 = clean_test_query('Kanjivaram saree do u have');
assert_test($c2 === 'kanjivaram', "Clean 'Kanjivaram saree do u have'", "Got '{$c2}'");

$c3 = clean_test_query('Handloom do u have');
assert_test($c3 === 'handloom', "Clean 'Handloom do u have'", "Got '{$c3}'");

$c4 = clean_test_query('can you show me blue silk saree please');
assert_test(strpos($c4, 'blue') !== false && strpos($c4, 'silk') !== false, "Clean 'can you show me blue silk saree please'", "Got '{$c4}'");

echo "\n--- TEST 2: Database Catalog Search Matching ---\n";
function search_db($mysqli, $term, $vendorId = 2) {
    $esc = $mysqli->real_escape_string($term);
    $sql = "SELECT p.id, p.name, p.sku, p.price, p.fabric, p.color FROM products p 
            LEFT JOIN categories c ON c.id = p.category_id 
            WHERE p.vendor_id = {$vendorId} AND p.status = 'active' 
              AND (p.name LIKE '%{$esc}%' OR p.fabric LIKE '%{$esc}%' OR p.color LIKE '%{$esc}%' OR c.name LIKE '%{$esc}%' OR p.sku LIKE '%{$esc}%')";
    return $mysqli->query($sql)->fetch_all(MYSQLI_ASSOC);
}

$cottonResults = search_db($mysqli, 'cotton');
assert_test(count($cottonResults) >= 2, "Search 'cotton' matches active sarees", "Found " . count($cottonResults));

$kanjivaramResults = search_db($mysqli, 'kanjivaram');
assert_test(count($kanjivaramResults) >= 1, "Search 'kanjivaram' matches active sarees", "Found " . count($kanjivaramResults));

$handloomResults = search_db($mysqli, 'handloom');
assert_test(count($handloomResults) >= 1, "Search 'handloom' matches active sarees", "Found " . count($handloomResults));

echo "\n--- TEST 3: Product Variants Verification ---\n";
$varRes = $mysqli->query("SELECT * FROM product_variants WHERE product_id = 4 AND status = 1");
$vars = $varRes ? $varRes->fetch_all(MYSQLI_ASSOC) : [];
assert_test(count($vars) >= 1, "Product #4 has active variant(s) in product_variants", "Found " . count($vars));

echo "\n--- TEST 4: Cart Schema & Upsert Verification ---\n";
$testSession = 'wa_test_verification_session';
$mysqli->query("DELETE FROM cart WHERE session_id = '{$testSession}'");

$mysqli->query("INSERT INTO cart (vendor_id, session_id, product_id, variant_id, quantity, created_at, updated_at) 
                VALUES (2, '{$testSession}', 4, 4, 2, NOW(), NOW())");
$cartRow = $mysqli->query("SELECT * FROM cart WHERE session_id = '{$testSession}'")->fetch_assoc();
assert_test($cartRow && (int)$cartRow['quantity'] === 2, "Cart insert quantity = 2 succeeds");

// Update quantity
$mysqli->query("UPDATE cart SET quantity = 3 WHERE session_id = '{$testSession}' AND product_id = 4");
$updatedCart = $mysqli->query("SELECT * FROM cart WHERE session_id = '{$testSession}'")->fetch_assoc();
assert_test($updatedCart && (int)$updatedCart['quantity'] === 3, "Cart quantity update to 3 succeeds");

// Cleanup
$mysqli->query("DELETE FROM cart WHERE session_id = '{$testSession}'");

echo "\n========================================================\n";
echo " RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "========================================================\n";
