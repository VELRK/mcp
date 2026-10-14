<?php
define('BASEPATH', true);
require '/var/www/mcp/application/config/database.php';
$dbCfg = $db['default'];
$m = new mysqli($dbCfg['hostname'], $dbCfg['username'], $dbCfg['password'], $dbCfg['database']);
$m->set_charset('utf8mb4');

echo "=== CONVERSATION 4 STATE ===\n";
$res = $m->query("SELECT * FROM wa_cloud_conversations WHERE id = 4");
if ($res) {
    print_r($res->fetch_assoc());
}

echo "\n=== VENDOR WA ACCOUNTS ===\n";
$res = $m->query("SELECT id, vendor_id, phone_number_id, status FROM vendor_whatsapp_accounts");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        print_r($r);
    }
}

echo "\n=== VENDOR META AGENTS ===\n";
$res = $m->query("SELECT * FROM vendor_meta_agents");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        print_r($r);
    }
}

echo "\n=== VENDOR WA AI ===\n";
$res = $m->query("SELECT * FROM vendor_wa_ai");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        print_r($r);
    }
}

echo "\n=== LAST 10 NGINX ACCESS LOGS FOR WEBHOOK/META-AGENT ===\n";
system("grep -E 'webhook|meta-agent' /var/log/nginx/access.log | tail -n 10");
