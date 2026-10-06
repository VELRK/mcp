<?php
/**
 * Time Meta agent_test vs connector search.
 * Usage: php application/tests/meta_ba_timing.php [phone_number_id]
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
$cfg = sk_meta_ba_config_array();
$base = rtrim($cfg['connector_base_url'], '/');
$key = $cfg['connector_api_key'];

echo "phone={$phone}\n";

// 1) Connector only
$url = $base . '/shopkart-api/meta-agent/connectors/' . rawurlencode($phone) . '/search_products';
$t0 = microtime(true);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Api-Key: ' . $key,
    ],
    CURLOPT_POSTFIELDS => json_encode(['query' => 'kanjivaram'], JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT => 30,
]);
$raw = curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$cerr = curl_error($ch);
curl_close($ch);
$ms = (int)round((microtime(true) - $t0) * 1000);
echo "connector_search http={$code} ms={$ms}" . ($cerr ? " err={$cerr}" : '') . "\n";

// 2) Meta agent_test Hi
foreach (['Hi', 'kanjivaram price'] as $msg) {
    $t0 = microtime(true);
    $res = sk_meta_ba_agent_test($phone, $msg, null, null);
    $ms = (int)round((microtime(true) - $t0) * 1000);
    $ok = !empty($res['ok']) ? 'ok' : 'fail';
    $reply = '';
    if (is_array($res['data'] ?? null)) {
        $reply = mb_substr(trim((string)($res['data']['agent_response'] ?? '')), 0, 80);
    }
    $err = (string)($res['error'] ?? '');
    echo "agent_test msg=" . json_encode($msg) . " {$ok} ms={$ms} http=" . (int)($res['http'] ?? 0);
    if ($err !== '') {
        echo " err={$err}";
    }
    if ($reply !== '') {
        echo " reply=" . json_encode($reply);
    }
    echo "\n";
}
