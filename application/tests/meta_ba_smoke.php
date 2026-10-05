<?php
/**
 * CLI smoke checks for Meta Business Agent wiring.
 * Usage: php application/tests/meta_ba_smoke.php
 */
define('BASEPATH', true);
define('FCPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
define('APPPATH', FCPATH . 'application' . DIRECTORY_SEPARATOR);

require_once APPPATH . 'helpers/sk_dotenv_helper.php';
sk_dotenv_load(FCPATH . '.env');

$failures = 0;
function assert_true($cond, $label) {
    global $failures;
    if ($cond) {
        echo "[OK]   {$label}\n";
    } else {
        echo "[FAIL] {$label}\n";
        $failures++;
    }
}

assert_true(is_file(FCPATH . '.env.example'), '.env.example exists');
assert_true(is_file(FCPATH . '.gitignore'), '.gitignore exists');
assert_true(is_file(APPPATH . 'config/meta_business_agent.php'), 'meta_business_agent.php config exists');
assert_true(is_file(APPPATH . 'helpers/sk_meta_business_agent_helper.php'), 'Meta BA helper exists');
assert_true(is_file(APPPATH . 'models/Sk_Vendor_meta_agent_model.php'), 'vendor_meta_agents model exists');
assert_true(is_file(APPPATH . 'controllers/api/Sk_Meta_agent_connectors.php'), 'connector API controller exists');
assert_true(is_file(APPPATH . 'controllers/admin/Meta_agent.php'), 'admin Meta_agent controller exists');

$ht = file_get_contents(FCPATH . '.htaccess');
assert_true(strpos($ht, '.env') !== false, '.htaccess blocks .env');

$routes = file_get_contents(APPPATH . 'config/routes.php');
assert_true(strpos($routes, 'meta-agent/connectors') !== false, 'connector routes registered');
assert_true(strpos($routes, 'admin/meta/agent') !== false, 'admin meta agent route registered');
assert_true(strpos($routes, "whatsapp/mcp") === false, 'legacy whatsapp/mcp route removed');

$webhook = file_get_contents(APPPATH . 'controllers/api/Sk_Whatsapp_webhook.php');
assert_true(strpos($webhook, '_reply_via_mcp') === false, 'webhook no longer auto-replies via MCP');
assert_true(strpos($webhook, '_reply_via_ai') === false, 'webhook no longer auto-replies via OpenAI/Gemini');
assert_true(strpos($webhook, 'messaging_handovers') !== false, 'webhook handles messaging_handovers');
assert_true(strpos($webhook, 'standby') !== false, 'webhook handles standby');

$key = sk_env('META_BA_CONNECTOR_API_KEY', '');
assert_true($key !== '', 'META_BA_CONNECTOR_API_KEY present in .env (change default before production)');

// Auth helper logic without CI bootstrap
require_once APPPATH . 'helpers/sk_meta_business_agent_helper.php';

// Minimal stubs so helper config load does not explode outside CI
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
        if (!$ci) $ci = new CI_Fake();
        return $ci;
    }
    function site_url($uri = '') {
        return rtrim(sk_env('META_BA_CONNECTOR_BASE_URL', 'http://localhost:8080/ecomm'), '/') . '/' . ltrim($uri, '/');
    }
}

$cfg = sk_meta_ba_config_array();
assert_true(isset($cfg['api_version']) && $cfg['api_version'] === '2.0.0', 'API version defaults to 2.0.0');
assert_true(sk_meta_ba_connector_auth_ok($cfg['connector_api_key']) === true, 'connector auth accepts configured key');
assert_true(sk_meta_ba_connector_auth_ok('wrong-key') === false, 'connector auth rejects wrong key');
assert_true(sk_meta_ba_connector_auth_ok('') === false, 'connector auth rejects empty key');

$tools = sk_meta_ba_connector_tool_defs('PHONE123');
$names = array_map(static function ($t) { return $t['name']; }, $tools);
assert_true(in_array('search_products', $names, true), 'tool search_products defined');
assert_true(in_array('human_handoff', $names, true), 'tool human_handoff defined');
assert_true(!in_array('create_order', $names, true), 'unfinished create_order tool not exposed');

echo $failures === 0 ? "\nAll smoke checks passed.\n" : "\n{$failures} check(s) failed.\n";
exit($failures === 0 ? 0 : 1);
