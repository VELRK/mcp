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

require_once __DIR__ . '/../helpers/sk_mcp_tool_helper.php';
require_once __DIR__ . '/../helpers/sk_meta_business_agent_helper.php';

$res = $mysqli->query("SELECT * FROM products WHERE id = 4");
$prod = $res->fetch_assoc();
echo "Product 4:\n";
print_r($prod);

$imgPath = function_exists('sk_mcp_tool_product_image_path') ? sk_mcp_tool_product_image_path($prod) : (string)($prod['thumbnail'] ?? '');
$imgUrl = function_exists('sk_mcp_tool_whatsapp_image_url') && $imgPath !== '' ? sk_mcp_tool_whatsapp_image_url($imgPath) : '';
echo "imgPath: $imgPath\n";
echo "imgUrl: $imgUrl\n";

// Test if imgUrl is reachable
if ($imgUrl !== '') {
    $ch = curl_init($imgUrl);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "Image HTTP status: $httpCode\n";
}
