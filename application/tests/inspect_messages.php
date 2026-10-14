<?php
define('BASEPATH', true);
define('ENVIRONMENT', 'development');
require_once __DIR__ . '/../config/database.php';
$dbCfg = $db['default'];
$mysqli = new mysqli($dbCfg['hostname'], $dbCfg['username'], $dbCfg['password'], $dbCfg['database']);
if ($mysqli->connect_error) {
    die("DB connection failed: " . $mysqli->connect_error . "\n");
}

$res = $mysqli->query("SELECT id, sender, recipient, message_type, message_text, created_at FROM wa_cloud_messages ORDER BY id DESC LIMIT 20");
echo "=== RECENT WA CLOUD MESSAGES ===\n";
while ($r = $res->fetch_assoc()) {
    $txt = str_replace(["\r", "\n"], ' ', $r['message_text']);
    if (mb_strlen($txt) > 80) $txt = mb_substr($txt, 0, 77) . '...';
    echo sprintf("[%d] %s | %s -> %s | %s | %s\n", $r['id'], $r['created_at'], $r['sender'], $r['recipient'], $r['message_type'], $txt);
}

echo "\n=== RECENT ORDERS ===\n";
$ores = $mysqli->query("SELECT id, order_number, user_id, total_amount, payment_method, payment_status, status, created_at FROM orders ORDER BY id DESC LIMIT 5");
while ($o = $ores->fetch_assoc()) {
    echo sprintf("#%d (%s) - ₹%s - %s - %s - %s | %s\n", $o['id'], $o['order_number'], $o['total_amount'], $o['payment_method'], $o['payment_status'], $o['status'], $o['created_at']);
}
