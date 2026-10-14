<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

define('BASEPATH', '/var/www/mcp/system/');
define('APPPATH', '/var/www/mcp/application/');
define('VIEWPATH', '/var/www/mcp/application/views/');
define('ENVIRONMENT', 'development');

require_once '/var/www/mcp/application/config/database.php';
$dbCfg = $db['default'];
$m = new mysqli($dbCfg['hostname'], $dbCfg['username'], $dbCfg['password'], $dbCfg['database']);
if ($m->connect_error) {
    die("DB connection failed: " . $m->connect_error . "\n");
}
echo "DB Connected to " . $dbCfg['database'] . "\n";

$res = $m->query("SELECT id, name, status, vendor_id, price FROM products WHERE status = 'active' LIMIT 5");
echo "Active products:\n";
while ($r = $res->fetch_assoc()) {
    echo "  ID: {$r['id']}, Name: {$r['name']}, Vendor: {$r['vendor_id']}, Price: {$r['price']}\n";
}
