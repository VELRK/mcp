<?php
define('BASEPATH', true);
define('ENVIRONMENT', 'development');
require_once __DIR__ . '/../config/database.php';
$dbCfg = $db['default'];
$mysqli = new mysqli($dbCfg['hostname'], $dbCfg['username'], $dbCfg['password'], $dbCfg['database']);
if ($mysqli->connect_error) {
    die("DB connection failed: " . $mysqli->connect_error . "\n");
}

$res = $mysqli->query("SELECT id, conversation_id, direction, type, body, status, created_at FROM wa_cloud_messages ORDER BY id DESC LIMIT 20");
echo "=== RECENT WA CLOUD MESSAGES ===\n";
while ($r = $res->fetch_assoc()) {
    $txt = str_replace(["\r", "\n"], ' ', $r['body'] ?? '');
    if (mb_strlen($txt) > 80) $txt = mb_substr($txt, 0, 77) . '...';
    echo sprintf("[%d] %s | #%s | %s | %s | %s | %s\n", $r['id'], $r['created_at'], $r['conversation_id'], $r['direction'], $r['type'], $r['status'], $txt);
}

echo "\n=== ADDRESSES COLUMNS ===\n";
$ares = $mysqli->query("SHOW COLUMNS FROM addresses");
while ($ares && $row = $ares->fetch_assoc()) {
    echo $row['Field'] . ' (' . $row['Type'] . ")\n";
}

echo "\n=== SAMPLE ADDRESSES ===\n";
$sres = $mysqli->query("SELECT * FROM addresses LIMIT 5");
while ($sres && $row = $sres->fetch_assoc()) {
    print_r($row);
}
exit(0);
exit(0);
