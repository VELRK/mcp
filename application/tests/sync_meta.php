<?php
/**
 * One-off sync script for Meta BA skills and UI skills.
 * Usage: php application/tests/sync_meta.php [phone_number_id]
 */
define('BASEPATH', true);
define('FCPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
define('APPPATH', FCPATH . 'application' . DIRECTORY_SEPARATOR);

require_once APPPATH . 'helpers/sk_dotenv_helper.php';
sk_dotenv_load(FCPATH . '.env');
require_once APPPATH . 'helpers/sk_meta_business_agent_helper.php';

$phone = $argv[1] ?? '1389144424278347';
$shop = $argv[2] ?? 'Vel TEST';

echo "Syncing UI skills for {$phone} ({$shop})...\n";
$uiRes = sk_meta_ba_sync_shop_ui_skills($phone, $shop);
echo "UI Skills Result: " . json_encode($uiRes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

echo "Syncing Sales skills for {$phone} ({$shop})...\n";
$salesRes = sk_meta_ba_sync_sales_skills($phone, $shop);
echo "Sales Skills Result: " . json_encode($salesRes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

echo "Updating agent settings (never_say_phrases) for {$phone}...\n";
$setRes = sk_meta_ba_put_settings($phone, [
    'never_say_phrases' => [
        'See the details below.',
        'See the details below',
        'see the details below',
    ]
]);
echo "Settings Result: " . json_encode($setRes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
