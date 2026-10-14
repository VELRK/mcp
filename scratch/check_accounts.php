<?php
define('BASEPATH', true);
require_once '/var/www/mcp/application/config/database.php';
$dbCfg = $db['default'];
$m = new mysqli($dbCfg['hostname'], $dbCfg['username'], $dbCfg['password'], $dbCfg['database']);

echo "=== VENDOR WHATSAPP ACCOUNTS ===\n";
$res = $m->query("SELECT id, vendor_id, phone_number_id, display_phone, status FROM vendor_whatsapp_accounts");
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['id']}, Vendor: {$r['vendor_id']}, Phone ID: {$r['phone_number_id']}, Phone: {$r['display_phone']}, Status: {$r['status']}\n";
}

echo "\n=== VENDOR META AGENTS ===\n";
$res = $m->query("SELECT * FROM vendor_meta_agents");
while ($r = $res->fetch_assoc()) {
    echo "Phone ID: {$r['phone_number_id']}, Vendor: {$r['vendor_id']}, Onboarded: {$r['onboarded']}, Status: {$r['status']}, Agent ID: {$r['agent_id']}\n";
}
