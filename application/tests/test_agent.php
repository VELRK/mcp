<?php
define('BASEPATH', true);
define('FCPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
define('APPPATH', FCPATH . 'application' . DIRECTORY_SEPARATOR);

require_once APPPATH . 'helpers/sk_dotenv_helper.php';
sk_dotenv_load(FCPATH . '.env');
require_once APPPATH . 'helpers/sk_meta_business_agent_helper.php';

$phoneId = '1389144424278347';
$query = $argv[1] ?? 'Show saree collection';

echo "Testing Meta Business Agent with: '{$query}'...\n";
$res = sk_meta_ba_agent_test($phoneId, $query);
echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
