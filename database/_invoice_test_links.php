<?php
/**
 * Print invoice test links for the latest order(s).
 * Usage: php database/_invoice_test_links.php
 */
$mysqli = @new mysqli('localhost', 'root', '', 'shopkart');
if ($mysqli->connect_error) {
    // try 127.0.0.1
    $mysqli = @new mysqli('127.0.0.1', 'root', '', 'shopkart');
}
if ($mysqli->connect_error) {
    fwrite(STDERR, "DB connect failed: {$mysqli->connect_error}\n");
    fwrite(STDERR, "Start MySQL in XAMPP, then re-run.\n");
    exit(1);
}

// Match sk_invoice_token_secret() as closely as possible
$configFile = dirname(__DIR__) . '/application/config/config.php';
$encKey = '';
if (is_file($configFile)) {
    $raw = file_get_contents($configFile);
    if (preg_match("/\\\$config\\['encryption_key'\\]\\s*=\\s*'([^']*)'/", $raw, $m)) {
        $encKey = $m[1];
    }
}
if ($encKey === '') {
    $site = '';
    $r = $mysqli->query("SELECT `value` FROM settings WHERE `key` = 'site_name' LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) {
        $site = (string)$row['value'];
    }
    $encKey = $site !== '' ? $site : 'Talk AI Pilot';
}
$secret = hash('sha256', 'invoice|' . $encKey);

function invoice_token(int $id, string $num, string $secret): string {
    return substr(hash_hmac('sha256', $id . '|' . $num, $secret), 0, 32);
}

// Local XAMPP often uses :8080 (IIS holds :80). Folder is deal1.
$host = getenv('INVOICE_BASE') ?: 'http://localhost:8080/deal1';
$q = $mysqli->query("SELECT id, order_number, user_id, status, payment_status, total, created_at
                     FROM orders
                     WHERE order_number IS NOT NULL AND order_number != ''
                     ORDER BY id DESC
                     LIMIT 5");
if (!$q || $q->num_rows === 0) {
    echo "No orders found in database `shopkart`.\n";
    exit(0);
}

echo "=== Invoice test links (latest orders) ===\n";
echo "Secret source: " . ($encKey !== '' ? "ok" : "empty") . "\n\n";

while ($o = $q->fetch_assoc()) {
    $id = (int)$o['id'];
    $num = (string)$o['order_number'];
    $token = invoice_token($id, $num, $secret);
    $dl = "{$host}/invoice/download/{$id}/{$token}";
    $view = "{$host}/invoice/view/{$id}/{$token}";
    $apiMeta = "{$host}/shopkart-api/order/{$id}/invoice";
    $apiDl = "{$host}/shopkart-api/order/{$id}/invoice/download";

    echo "Order #{$num} (id={$id}, user_id={$o['user_id']}, status={$o['status']}, pay={$o['payment_status']}, total={$o['total']})\n";
    echo "  Public PDF (open in browser, no login):\n    {$dl}\n";
    echo "  Public HTML view:\n    {$view}\n";
    echo "  Auth API meta (needs Bearer JWT of user_id={$o['user_id']}):\n    GET {$apiMeta}\n";
    echo "  Auth API PDF stream:\n    GET {$apiDl}\n\n";
}
