<?php
define('BASEPATH', true);
define('ENVIRONMENT', 'development');
require_once __DIR__ . '/../config/database.php';
$dbCfg = $db['default'];
$mysqli = new mysqli($dbCfg['hostname'], $dbCfg['username'], $dbCfg['password'], $dbCfg['database']);
if ($mysqli->connect_error) {
    die("DB connection failed: " . $mysqli->connect_error . "\n");
}

echo "=== ORDERS TABLE COLUMNS ===\n";
$res = $mysqli->query("SHOW COLUMNS FROM orders");
$cols = [];
while ($r = $res->fetch_assoc()) {
    $cols[] = $r['Field'] . ' (' . $r['Type'] . ')';
}
echo implode("\n", $cols) . "\n\n";

echo "=== ORDER_ITEMS TABLE COLUMNS ===\n";
$res2 = $mysqli->query("SHOW COLUMNS FROM order_items");
$cols2 = [];
while ($r2 = $res2->fetch_assoc()) {
    $cols2[] = $r2['Field'] . ' (' . $r2['Type'] . ')';
}
echo implode("\n", $cols2) . "\n\n";

echo "=== RAZORPAY SETTINGS ===\n";
$res3 = $mysqli->query("SELECT `key`, `value` FROM settings WHERE `key` LIKE '%razorpay%'");
while ($r3 = $res3->fetch_assoc()) {
    $val = $r3['value'];
    if (strlen($val) > 8) {
        $val = substr($val, 0, 4) . '...' . substr($val, -4);
    }
    echo $r3['key'] . ' => ' . $val . "\n";
}
