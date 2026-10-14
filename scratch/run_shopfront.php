<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Mock request to Shopfront/product/1
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'talkaipilot.com';
$_SERVER['REQUEST_URI'] = '/shop/product/1';

define('BASEPATH', '/var/www/mcp/system/');
define('APPPATH', '/var/www/mcp/application/');
define('VIEWPATH', '/var/www/mcp/application/views/');
define('ENVIRONMENT', 'development');

require '/var/www/mcp/application/config/database.php';
// Let's invoke Shopfront by creating an instance or calling its method
$_GET['id'] = 1;

// Let's test loading CI framework
ob_start();
require '/var/www/mcp/system/core/CodeIgniter.php';
$output = ob_get_clean();
echo "RESULT: " . substr($output, 0, 500) . "\n";
