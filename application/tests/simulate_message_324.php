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

$res = $mysqli->query("SELECT * FROM wa_cloud_messages WHERE id = 324");
$row = $res->fetch_assoc();
echo "Message 324 row:\n";
echo "body: " . ($row['body'] ?? '') . "\n";
echo "raw_json: " . ($row['raw_json'] ?? '') . "\n";

$raw = json_decode($row['raw_json'], true);
print_r($raw);
