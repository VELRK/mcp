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

echo "\n=== CONVERSATION #4 ===\n";
$cres = $mysqli->query("SELECT * FROM wa_cloud_conversations WHERE id = 4");
if ($cres && $row = $cres->fetch_assoc()) {
    foreach ($row as $k => $v) {
        echo "$k => $v\n";
    }
}
exit(0);
