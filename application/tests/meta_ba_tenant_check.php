<?php
/**
 * Verify Meta connector search_products is tenant-scoped.
 * Usage: php application/tests/meta_ba_tenant_check.php [phone_number_id] [query]
 */
define('BASEPATH', true);
define('FCPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
define('APPPATH', FCPATH . 'application' . DIRECTORY_SEPARATOR);

require_once APPPATH . 'helpers/sk_dotenv_helper.php';
sk_dotenv_load(FCPATH . '.env');

$phone = trim((string)($argv[1] ?? '1389144424278347'));
$query = trim((string)($argv[2] ?? 'saree'));
$key = sk_env('META_BA_CONNECTOR_API_KEY', '');
$base = rtrim(sk_env('META_BA_CONNECTOR_BASE_URL', 'https://talkaipilot.com'), '/');

$dbCfg = [];
require APPPATH . 'config/database.php';
$c = $db['default'];
$m = new mysqli($c['hostname'], $c['username'], $c['password'], $c['database']);
$m->set_charset('utf8mb4');

$acct = $m->query(
    "SELECT vendor_id, phone_number_id, status
     FROM vendor_whatsapp_accounts
     WHERE phone_number_id='" . $m->real_escape_string($phone) . "' LIMIT 1"
)->fetch_assoc();
if (!$acct) {
    // Fallback table name used in some installs.
    $acct = $m->query(
        "SELECT vendor_id, phone_number_id, status
         FROM vendor_whatsapp_numbers
         WHERE phone_number_id='" . $m->real_escape_string($phone) . "' LIMIT 1"
    )->fetch_assoc();
}
if (!$acct) {
    fwrite(STDERR, "No WhatsApp account row for phone={$phone}\n");
    exit(1);
}
$vid = (int)$acct['vendor_id'];
echo "phone={$phone} vendor_id={$vid} status=" . ($acct['status'] ?? '') . "\n";

$url = $base . '/shopkart-api/meta-agent/connectors/' . rawurlencode($phone) . '/search_products';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Api-Key: ' . $key,
    ],
    CURLOPT_POSTFIELDS => json_encode(['query' => $query], JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT => 30,
]);
$body = curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "http={$code}\n";
$j = json_decode((string)$body, true);
$products = $j['data']['products'] ?? [];
echo "returned_count=" . count($products) . "\n";
echo "summary=" . substr((string)($j['data']['summary'] ?? ''), 0, 200) . "\n";

$ids = [];
foreach ($products as $p) {
    $id = (int)($p['id'] ?? 0);
    if ($id > 0) {
        $ids[] = $id;
    }
    echo ' api_product id=' . $id . ' name=' . ($p['name'] ?? '') . "\n";
}

$leaks = 0;
if ($ids) {
    $in = implode(',', array_map('intval', $ids));
    $q = $m->query("SELECT id, name, vendor_id, status FROM products WHERE id IN ({$in})");
    while ($r = $q->fetch_assoc()) {
        $ok = ((int)$r['vendor_id'] === $vid) ? 'OK' : 'LEAK';
        if ($ok === 'LEAK') {
            $leaks++;
        }
        echo $ok . ' db id=' . $r['id'] . ' vendor=' . $r['vendor_id'] . ' name=' . $r['name'] . "\n";
    }
}

$tot = $m->query("SELECT COUNT(*) c FROM products WHERE status='active'")->fetch_assoc();
$vt = $m->query("SELECT COUNT(*) c FROM products WHERE status='active' AND vendor_id={$vid}")->fetch_assoc();
echo 'active_all=' . $tot['c'] . ' active_tenant=' . $vt['c'] . "\n";
echo $leaks > 0 ? "RESULT=FAIL tenant_leak={$leaks}\n" : "RESULT=PASS tenant_ok\n";
exit($leaks > 0 ? 2 : 0);
