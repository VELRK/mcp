<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'talkaipilot.com';
$_SERVER['SERVER_NAME'] = 'talkaipilot.com';
$_SERVER['REQUEST_URI'] = '/shop/product/1';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

chdir('/var/www/mcp');
ob_start();
try {
    require '/var/www/mcp/index.php';
} catch (\Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString();
}
$out = ob_get_clean();
echo "OUTPUT (" . strlen($out) . "):\n" . substr($out, 0, 1000) . "\n";
