<?php
/**
 * Prune Meta connector tools to the slim desired set (no CI DB required).
 * Usage: php application/tests/meta_ba_prune_tools.php [phone_number_id]
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

$desired = [];
foreach (sk_meta_ba_connector_tool_defs($phone) as $t) {
    $desired[strtolower((string)$t['name'])] = $t;
}
echo 'desired=' . implode(',', array_keys($desired)) . "\n";

$c = sk_meta_ba_list_connectors($phone);
$items = $c['data']['data'] ?? $c['data']['connectors'] ?? $c['data'] ?? [];
if (!is_array($items)) {
    $items = [];
}

$connectorId = '';
foreach ($items as $row) {
    if (!is_array($row)) {
        continue;
    }
    $n = strtolower((string)($row['name'] ?? ''));
    if (strpos($n, 'talk_ai_pilot') !== false || strpos($n, 'talk ai pilot') !== false) {
        $connectorId = (string)($row['id'] ?? '');
        break;
    }
}
if ($connectorId === '' && $items) {
    $first = $items[0];
    if (is_array($first)) {
        $connectorId = (string)($first['id'] ?? '');
    }
}
if ($connectorId === '') {
    fwrite(STDERR, "No connector found\n");
    exit(1);
}
echo "connector={$connectorId}\n";

$t = sk_meta_ba_list_connector_tools($phone, $connectorId);
$tools = $t['data']['tools'] ?? sk_meta_ba_normalize_connector_tool_items($t['data'] ?? null);
if (!is_array($tools)) {
    $tools = [];
}

$existingByName = [];
foreach ($tools as $tool) {
    if (!is_array($tool) || empty($tool['name'])) {
        continue;
    }
    $name = strtolower((string)$tool['name']);
    $existingByName[$name] = $tool;
}

// Update or create desired
foreach ($desired as $name => $def) {
    $existing = $existingByName[$name] ?? null;
    if ($existing && !empty($existing['id'])) {
        $res = sk_meta_ba_update_connector_tool($phone, $connectorId, (string)$existing['id'], $def, null);
        echo ($res['ok'] ? 'updated' : 'update_fail') . " {$name}" . (!empty($res['error']) ? ' err=' . $res['error'] : '') . "\n";
    } else {
        $res = sk_meta_ba_create_connector_tool($phone, $connectorId, $def, null);
        echo ($res['ok'] ? 'created' : 'create_fail') . " {$name}" . (!empty($res['error']) ? ' err=' . $res['error'] : '') . "\n";
    }
}

// Delete extras
foreach ($existingByName as $name => $tool) {
    if (isset($desired[$name])) {
        continue;
    }
    $id = (string)($tool['id'] ?? '');
    if ($id === '') {
        continue;
    }
    $res = sk_meta_ba_delete_connector_tool($phone, $connectorId, $id, null);
    echo ($res['ok'] ? 'deleted' : 'delete_fail') . " {$name}" . (!empty($res['error']) ? ' err=' . $res['error'] : '') . "\n";
}

// Re-list
$t = sk_meta_ba_list_connector_tools($phone, $connectorId);
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
