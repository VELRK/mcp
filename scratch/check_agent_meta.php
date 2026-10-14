<?php
define('BASEPATH', true);
define('FCPATH', '/var/www/mcp/');
define('APPPATH', '/var/www/mcp/application/');

require_once APPPATH . 'helpers/sk_dotenv_helper.php';
sk_dotenv_load(FCPATH . '.env');
require_once APPPATH . 'helpers/sk_meta_business_agent_helper.php';

$phoneId = '1389144424278347';
$token = sk_meta_ba_access_token($phoneId);

echo "Token prefix: " . substr($token, 0, 15) . "...\n";

echo "=== AGENT SETTINGS ===\n";
$settings = sk_meta_ba_get_settings($phoneId);
print_r($settings);

echo "\n=== AGENT SKILLS ===\n";
$skills = sk_meta_ba_list_skills($phoneId);
print_r($skills);

echo "\n=== AGENT CONNECTORS ===\n";
$connectors = sk_meta_ba_list_connectors($phoneId);
print_r($connectors);

echo "\n=== UI SKILLS ===\n";
$ui = sk_meta_ba_list_ui_skills($phoneId);
print_r($ui);
