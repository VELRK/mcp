<?php
/**
 * Sync Meta connector tools (create/update wanted, delete extras).
 * Usage: php application/tests/meta_ba_sync_tools.php [phone_number_id] [vendor_id]
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
    class CI_Fake_DB {
        public function query($sql) { return false; }
        public function get_where() { return $this; }
        public function row_array() { return null; }
        public function update() { return true; }
        public function insert() { return true; }
        public function insert_id() { return 0; }
    }
    class CI_Fake_Load {
        public function database() {}
        public function helper($h) {}
        public function model($m) {}
    }
    class CI_Fake {
        public $config;
        public $db;
        public $load;
        public function __construct() {
            $this->config = new CI_Fake_Config();
            $this->db = new CI_Fake_DB();
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
$vendorId = (int)($argv[2] ?? 0);

echo "syncing phone={$phone} vendor={$vendorId}\n";
$wanted = [];
foreach (sk_meta_ba_connector_tool_defs($phone) as $t) {
    $wanted[] = $t['name'];
}
echo 'wanted_count=' . count($wanted) . ' names=' . implode(',', $wanted) . "\n";

$res = sk_meta_ba_sync_connector_for_phone($phone, $vendorId, null);
echo 'ok=' . (!empty($res['ok']) ? '1' : '0') . "\n";
echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

// Re-list
$c = sk_meta_ba_list_connectors($phone);
$items = $c['data']['data'] ?? $c['data']['connectors'] ?? $c['data'] ?? [];
if (!is_array($items)) {
    $items = [];
}
foreach ($items as $row) {
    if (!is_array($row)) {
        continue;
    }
    $id = (string)($row['id'] ?? '');
    if ($id === '') {
        continue;
    }
    $t = sk_meta_ba_list_connector_tools($phone, $id);
    $tools = $t['data']['tools'] ?? sk_meta_ba_normalize_connector_tool_items($t['data'] ?? null);
    if (!is_array($tools)) {
        $tools = [];
    }
    echo 'after tool_count=' . count($tools) . "\n";
    foreach ($tools as $tool) {
        if (is_array($tool)) {
            echo ' - ' . ($tool['name'] ?? '?') . "\n";
        }
    }
}
