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

$res = $mysqli->query("SELECT id, wamid, direction, type, body, status, created_at FROM wa_cloud_messages ORDER BY id DESC LIMIT 15");
echo "=== LAST 15 MESSAGES IN wa_cloud_messages ===\n";
while ($r = $res->fetch_assoc()) {
    echo sprintf("[%d] %s | %s | %s | %s | %s\n", $r['id'], $r['created_at'], $r['direction'], $r['wamid'], $r['status'], $r['body']);
}
