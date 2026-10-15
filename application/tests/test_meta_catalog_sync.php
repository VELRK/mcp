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

echo "OK\n";
