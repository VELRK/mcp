<?php
/**
 * Runs database/seed_local.sql (localhost only).
 *
 * Prefer importing the SQL file:
 *   mysql -uroot shopkart < database/seed_local.sql
 *
 * CLI:  php database/seed_local.php
 */
$isCli = PHP_SAPI === 'cli';
$host = (string)($_SERVER['HTTP_HOST'] ?? '');
if (!$isCli && !preg_match('/^(localhost|127\.0\.0\.1)(:|$)/i', $host)) {
    http_response_code(403);
    echo "This seeder runs on localhost only.\n";
    exit(1);
}

$sqlFile = __DIR__ . DIRECTORY_SEPARATOR . 'seed_local.sql';
if (!is_file($sqlFile)) {
    fwrite(STDERR, "Missing seed_local.sql\n");
    exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('localhost', 'root', '', 'shopkart');
$db->set_charset('utf8mb4');

$adminCount = (int)$db->query('SELECT COUNT(*) c FROM admins')->fetch_assoc()['c'];
if ($adminCount < 1) {
    fwrite(STDERR, "No admin login found. Aborting.\n");
    exit(1);
}

$sql = file_get_contents($sqlFile);
if ($db->multi_query($sql)) {
    do {
        if ($result = $db->store_result()) {
            $result->free();
        }
    } while ($db->more_results() && $db->next_result());
}

$pc = (int)$db->query('SELECT COUNT(*) c FROM products')->fetch_assoc()['c'];
$ic = (int)$db->query('SELECT COUNT(*) c FROM product_images')->fetch_assoc()['c'];
$nl = $isCli ? PHP_EOL : "<br>\n";
echo "Imported seed_local.sql{$nl}";
echo "Products: {$pc}  Images: {$ic}  Admin logins kept: {$adminCount}{$nl}";
$db->close();
