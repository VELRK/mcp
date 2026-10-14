<?php
define('BASEPATH', true);
require '/var/www/mcp/application/config/database.php';
$dbCfg = $db['default'];
$m = new mysqli($dbCfg['hostname'], $dbCfg['username'], $dbCfg['password'], $dbCfg['database']);
if ($m->connect_error) {
    die("Connection failed: " . $m->connect_error . "\n");
}
$m->set_charset('utf8mb4');

echo "=== VENDORS ===\n";
$res = $m->query("SELECT * FROM vendors LIMIT 5");
while ($r = $res->fetch_assoc()) {
    echo json_encode($r) . "\n";
}

echo "\n=== VENDOR WHATSAPP ACCOUNTS ===\n";
$res = $m->query("SELECT id, vendor_id, phone_number_id, display_phone, status FROM vendor_whatsapp_accounts");
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['id']}, Vendor: {$r['vendor_id']}, Phone ID: {$r['phone_number_id']}, Phone: {$r['display_phone']}, Status: {$r['status']}\n";
}

echo "\n=== PRODUCTS COUNT PER VENDOR ===\n";
$res = $m->query("SELECT vendor_id, status, COUNT(*) as cnt FROM products GROUP BY vendor_id, status");
while ($r = $res->fetch_assoc()) {
    echo "Vendor: '{$r['vendor_id']}', Status: {$r['status']}, Count: {$r['cnt']}\n";
}

echo "\n=== ALL PRODUCTS SAMPLE ===\n";
$res = $m->query("SELECT id, vendor_id, name, sku, price, stock, status FROM products LIMIT 15");
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['id']}, Vendor: '{$r['vendor_id']}', Name: {$r['name']}, SKU: '{$r['sku']}', Price: {$r['price']}, Stock: {$r['stock']}, Status: {$r['status']}\n";
}

echo "\n=== PRODUCT VARIANTS SAMPLE ===\n";
$res = $m->query("SELECT id, product_id, sku, price, stock, is_default FROM product_variants LIMIT 10");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        echo "Variant ID: {$r['id']}, Prod ID: {$r['product_id']}, SKU: '{$r['sku']}', Price: {$r['price']}, Stock: {$r['stock']}, Default: {$r['is_default']}\n";
    }
} else {
    echo "product_variants table error or empty\n";
}
