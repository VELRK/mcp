<?php
define('BASEPATH', true);
define('FCPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
define('APPPATH', FCPATH . 'application' . DIRECTORY_SEPARATOR);

require_once APPPATH . 'helpers/sk_dotenv_helper.php';
sk_dotenv_load(FCPATH . '.env');
require_once APPPATH . 'helpers/sk_meta_business_agent_helper.php';

$phone = '1389144424278347';
$skills = sk_meta_ba_list_skills($phone);
echo "=== AGENT SKILLS ===\n";
echo json_encode($skills, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

$ui = sk_meta_ba_list_all_ui_skills($phone);
echo "=== UI SKILLS ===\n";
echo json_encode($ui, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

$connectors = sk_meta_ba_list_connectors($phone);
echo "=== CONNECTORS ===\n";
echo json_encode($connectors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

$settings = sk_meta_ba_get_settings($phone);
echo "=== SETTINGS ===\n";
echo json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

if (!empty($connectors['data']['connectors'][0]['id'])) {
    $cId = $connectors['data']['connectors'][0]['id'];
    $tools = sk_meta_ba_list_connector_tools($phone, $cId);
    echo "=== TOOLS FOR CONNECTOR $cId ===\n";
    foreach ($tools['data'] ?? [] as $t) {
        if (($t['name'] ?? '') === 'search_products') {
            echo json_encode($t, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        }
    }
}

