<?php
define('BASEPATH', true);
define('FCPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
define('APPPATH', FCPATH . 'application' . DIRECTORY_SEPARATOR);

require_once APPPATH . 'helpers/sk_meta_business_agent_helper.php';
require_once APPPATH . 'helpers/sk_meta_catalog_helper.php';

if (!function_exists('sk_meta_catalog_sync_vendor_products')) {
    fwrite(STDERR, "MISSING: sk_meta_catalog_sync_vendor_products\n");
    exit(1);
}

$permissionError = sk_meta_catalog_error_message('Listing product catalogs for the business', [
    'http' => 400,
    'error' => '(#100) Missing Permission',
    'data' => ['error' => ['code' => 100, 'message' => '(#100) Missing Permission']],
]);
if (strpos($permissionError, 'Listing product catalogs for the business') === false
    || strpos($permissionError, 'Meta code 100') === false
    || strpos($permissionError, 'required Meta permission') === false) {
    fwrite(STDERR, "FAIL: Meta permission error lacks operation or permission guidance\n");
    exit(1);
}

echo "OK\n";
