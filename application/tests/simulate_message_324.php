<?php
define('BASEPATH', true);
define('ENVIRONMENT', 'development');
define('FCPATH', '/var/www/mcp/');
define('APPPATH', '/var/www/mcp/application/');
require_once __DIR__ . '/../helpers/sk_dotenv_helper.php';
sk_dotenv_load(FCPATH . '.env');
require_once __DIR__ . '/../config/database.php';
$dbCfg = $db['default'];
$mysqli = new mysqli($dbCfg['hostname'], $dbCfg['username'], $dbCfg['password'], $dbCfg['database']);

$res = $mysqli->query("SELECT id, wamid, direction, type, body, raw_json, status, created_at FROM wa_cloud_messages WHERE id >= 325 ORDER BY id ASC");
echo "=== MESSAGES >= 325 ===\n";
while ($r = $res->fetch_assoc()) {
    echo sprintf("[%d] %s | %s | %s | %s\n", $r['id'], $r['created_at'], $r['direction'], $r['type'], $r['body']);
    $decoded = json_decode($r['raw_json'] ?? '', true);
    if (!empty($decoded['interactive'])) {
        echo "  Interactive: " . json_encode($decoded['interactive']) . "\n";
    } elseif (!empty($decoded['text'])) {
        echo "  Text: " . json_encode($decoded['text']) . "\n";
    }
}
