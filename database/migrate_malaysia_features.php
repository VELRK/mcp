<?php
/**
 * Migration: affiliate invite, billing address, MYR wallet settings
 * Usage: php database/migrate_malaysia_features.php
 */
$root = dirname(__DIR__);
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
require_once $root . '/index.php';

$CI =& get_instance();
$CI->load->database();

function col_exists($db, $table, $column) {
    $q = $db->query("SHOW COLUMNS FROM `{$table}` LIKE " . $db->escape($column));
    return $q && $q->num_rows() > 0;
}

function add_col($db, $table, $column, $def) {
    if (col_exists($db, $table, $column)) {
        echo "SKIP: {$table}.{$column}\n";
        return;
    }
    $db->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$def}");
    echo "OK: {$table}.{$column}\n";
}

add_col($CI->db, 'affiliates', 'must_set_password', "TINYINT(1) NOT NULL DEFAULT 0 AFTER `password`");
add_col($CI->db, 'affiliates', 'invite_token', "VARCHAR(64) NULL DEFAULT NULL AFTER `must_set_password`");
add_col($CI->db, 'affiliates', 'invite_expires', "DATETIME NULL DEFAULT NULL AFTER `invite_token`");

add_col($CI->db, 'addresses', 'company_name', "VARCHAR(150) NULL DEFAULT NULL AFTER `full_name`");
add_col($CI->db, 'addresses', 'address_type', "VARCHAR(20) NOT NULL DEFAULT 'shipping' AFTER `label`");

$after = col_exists($CI->db, 'orders', 'shipping_country') ? 'AFTER `shipping_country`' : '';
add_col($CI->db, 'orders', 'billing_name', "VARCHAR(150) NULL DEFAULT NULL {$after}");
add_col($CI->db, 'orders', 'billing_company', "VARCHAR(150) NULL DEFAULT NULL AFTER `billing_name`");
add_col($CI->db, 'orders', 'billing_phone', "VARCHAR(30) NULL DEFAULT NULL AFTER `billing_company`");
add_col($CI->db, 'orders', 'billing_line1', "VARCHAR(255) NULL DEFAULT NULL AFTER `billing_phone`");
add_col($CI->db, 'orders', 'billing_line2', "VARCHAR(255) NULL DEFAULT NULL AFTER `billing_line1`");
add_col($CI->db, 'orders', 'billing_city', "VARCHAR(100) NULL DEFAULT NULL AFTER `billing_line2`");
add_col($CI->db, 'orders', 'billing_state', "VARCHAR(100) NULL DEFAULT NULL AFTER `billing_city`");
add_col($CI->db, 'orders', 'billing_pincode', "VARCHAR(20) NULL DEFAULT NULL AFTER `billing_state`");
add_col($CI->db, 'orders', 'billing_country', "VARCHAR(80) NULL DEFAULT 'India' AFTER `billing_pincode`");

$defaults = [
    'currency_symbol' => '₹',
    'currency_code' => 'INR',
    'default_country' => 'India',
    'payment_gateway' => 'toyyibpay',
    'toyyibpay_secret_key' => '',
    'toyyibpay_category_code' => '',
    'toyyibpay_sandbox' => '1',
];
foreach ($defaults as $k => $v) {
    $exists = $CI->db->where('key', $k)->count_all_results('settings');
    if ($exists) {
        echo "SKIP setting: {$k}\n";
        continue;
    }
    $CI->db->insert('settings', ['key' => $k, 'value' => $v]);
    echo "OK setting: {$k}\n";
}

echo "Migration complete.\n";
