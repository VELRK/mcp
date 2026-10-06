<?php
/**
 * Sync slim sales skill + prune tools, then time.
 * Usage: php application/tests/meta_ba_fast_push.php [phone] [shopName]
 */
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
            if ($use_sections) {
                $this->items[$file] = $config;
            } else {
                $this->items = array_merge($this->items, $config);
            }
        }
        public function item($key, $index = '') {
            if ($index !== '') {
                return $this->items[$index][$key] ?? null;
            }
            return $this->items[$key] ?? null;
        }
    }
    class CI_Fake_Load {
        public function helper($h) {}
        public function database() {}
    }
    class CI_Fake {
        public $config;
        public $load;
        public function __construct() {
            $this->config = new CI_Fake_Config();
            $this->load = new CI_Fake_Load();
        }
    }
    function &get_instance() {
        static $ci;
        if (!$ci) {
            $ci = new CI_Fake();
        }
        return $ci;
    }
    function site_url($uri = '') {
        return rtrim(sk_env('META_BA_CONNECTOR_BASE_URL', 'http://localhost'), '/') . '/' . ltrim($uri, '/');
    }
}

require_once APPPATH . 'helpers/sk_meta_business_agent_helper.php';

$phone = trim((string)($argv[1] ?? '1389144424278347'));
$shop = trim((string)($argv[2] ?? 'Vel TEST'));

echo "=== prune tools ===\n";
passthru('php ' . escapeshellarg(__DIR__ . '/meta_ba_prune_tools.php') . ' ' . escapeshellarg($phone));

echo "=== sync skills ===\n";
$sk = sk_meta_ba_sync_sales_skills($phone, $shop, null);
echo 'skills_ok=' . (!empty($sk['ok']) ? '1' : '0') . ' err=' . ($sk['error'] ?? '') . "\n";
if (!empty($sk['data'])) {
    echo json_encode($sk['data'], JSON_UNESCAPED_UNICODE) . "\n";
}

echo "=== timing ===\n";
passthru('php ' . escapeshellarg(__DIR__ . '/meta_ba_timing.php') . ' ' . escapeshellarg($phone));
