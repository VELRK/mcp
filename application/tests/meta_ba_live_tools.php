<?php
/**
 * List live Meta connector tools + skills for a phone.
 * Usage: php application/tests/meta_ba_live_tools.php [phone_number_id]
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
    class CI_Fake {
        public $config;
        public function __construct() { $this->config = new CI_Fake_Config(); }
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
echo "phone={$phone}\n";

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
    echo 'connector=' . $id . ' name=' . ($row['name'] ?? '') . "\n";
    $t = sk_meta_ba_list_connector_tools($phone, $id);
    $tools = $t['data']['tools'] ?? sk_meta_ba_normalize_connector_tool_items($t['data'] ?? null);
    if (!is_array($tools)) {
        $tools = [];
    }
    echo 'tool_count=' . count($tools) . "\n";
    foreach ($tools as $tool) {
        if (is_array($tool)) {
            echo ' - ' . ($tool['name'] ?? '?') . "\n";
        }
    }
}

$s = sk_meta_ba_list_skills($phone);
$skills = $s['data']['data'] ?? $s['data']['skills'] ?? $s['data'] ?? [];
if (!is_array($skills)) {
    $skills = [];
}
echo 'skill_count=' . count($skills) . "\n";
foreach ($skills as $sk) {
    if (is_array($sk)) {
        echo ' skill=' . ($sk['title'] ?? $sk['id'] ?? '?') . "\n";
    }
}
