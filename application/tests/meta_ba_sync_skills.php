<?php
define('BASEPATH', true);
define('FCPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
define('APPPATH', FCPATH . 'application' . DIRECTORY_SEPARATOR);
require_once APPPATH . 'helpers/sk_dotenv_helper.php';
sk_dotenv_load(FCPATH . '.env');
if (!function_exists('get_instance')) {
    class CI_Fake_Config {
        public $items = [];
        public function load($file, $use_sections = false) {
            $path = APPPATH . 'config/' . $file . '.php';
            $config = [];
            include $path;
            if ($use_sections) { $this->items[$file] = $config; }
            else { $this->items = array_merge($this->items, $config); }
        }
        public function item($key, $index = '') {
            return $index !== '' ? ($this->items[$index][$key] ?? null) : ($this->items[$key] ?? null);
        }
    }
    class CI_Fake_Load { public function helper($h) {} }
    class CI_Fake {
        public $config; public $load;
        public function __construct() { $this->config = new CI_Fake_Config(); $this->load = new CI_Fake_Load(); }
    }
    function &get_instance() { static $ci; if (!$ci) { $ci = new CI_Fake(); } return $ci; }
    function site_url($uri = '') {
        return rtrim(sk_env('META_BA_CONNECTOR_BASE_URL', 'http://localhost'), '/') . '/' . ltrim($uri, '/');
    }
}
require_once APPPATH . 'helpers/sk_meta_business_agent_helper.php';
$phone = $argv[1] ?? '1389144424278347';
$shop = $argv[2] ?? 'Vel TEST';
$r = sk_meta_ba_sync_sales_skills($phone, $shop, null);
echo 'ok=' . (!empty($r['ok']) ? '1' : '0') . ' err=' . ($r['error'] ?? '') . "\n";
