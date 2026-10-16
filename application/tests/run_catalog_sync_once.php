<?php
/**
 * One-shot Meta catalog sync for a vendor (CLI).
 * php application/tests/run_catalog_sync_once.php [vendor_id]
 */
define('BASEPATH', dirname(__DIR__, 2) . '/system/');
define('APPPATH', dirname(__DIR__) . '/');
define('FCPATH', dirname(__DIR__, 2) . '/');
define('ENVIRONMENT', 'production');

require APPPATH . 'helpers/sk_dotenv_helper.php';
sk_dotenv_load(FCPATH . '.env');

if (!function_exists('base_url')) {
    function base_url($uri = '') {
        $base = rtrim((string)(sk_env('APP_URL', 'https://talkaipilot.com')), '/') . '/';
        return $base . ltrim((string)$uri, '/');
    }
}

class StubConfig {
    public function load($a, $b = false) {}
    public function item($key, $group = '') {
        $map = [
            'meta_ba_enabled' => true,
            'meta_ba_api_base' => 'https://api.facebook.com',
            'meta_ba_api_version' => '2.0.0',
            'meta_ba_system_user_token' => sk_env('META_BA_SYSTEM_USER_TOKEN', ''),
            'meta_ba_connector_api_key' => sk_env('META_BA_CONNECTOR_API_KEY', ''),
            'meta_ba_connector_base_url' => sk_env('META_BA_CONNECTOR_BASE_URL', ''),
            'meta_ba_default_audience' => 'ALLOWLISTED_ONLY',
            'meta_ba_handoff_message' => '',
            'meta_ba_handoff_release_control' => false,
            'base_url' => sk_env('APP_URL', 'https://talkaipilot.com/'),
        ];
        return $map[$key] ?? '';
    }
}
class FakeCI {
    public $db;
    public $load;
    public $config;
    public $Sk_Vendor_whatsapp_account_model;
    public $Sk_Vendor_model;
    public $Sk_Vendor_meta_agent_model;
    public $Sk_Product_variant_model;
    public function __construct() {
        $this->config = new StubConfig();
    }
}
function &get_instance() {
    static $ci;
    if (!$ci) {
        $ci = new FakeCI();
    }
    return $ci;
}

$host = sk_env('DB_HOST', '127.0.0.1');
$user = sk_env('DB_USER', 'root');
$pass = sk_env('DB_PASS', '');
$name = sk_env('DB_NAME', 'shopkart');
$dbFile = APPPATH . 'config/database.php';
if (is_file($dbFile)) {
    $db = [];
    // database.php guards on BASEPATH; already defined.
    include $dbFile;
    if (!empty($db['default'])) {
        if ($host === '' || $host === '127.0.0.1' || $host === 'localhost') {
            $cfgHost = (string)($db['default']['hostname'] ?? '');
            if ($cfgHost !== '') {
                $host = $cfgHost;
            }
        }
        if ($user === 'root' && (string)sk_env('DB_USER', '') === '') {
            $user = (string)($db['default']['username'] ?? $user);
        }
        if ((string)sk_env('DB_PASS', '') === '' && (string)sk_env('DB_PASSWORD', '') === '') {
            $pass = (string)($db['default']['password'] ?? $pass);
        }
        if ($name === 'shopkart' && (string)sk_env('DB_NAME', '') === '') {
            $name = (string)($db['default']['database'] ?? $name);
        }
    }
}
// Prefer explicit env overrides.
if (sk_env('DB_HOST', '') !== '') {
    $host = sk_env('DB_HOST', $host);
}
if (sk_env('DB_USER', '') !== '') {
    $user = sk_env('DB_USER', $user);
}
if (sk_env('DB_PASS', '') !== '' || sk_env('DB_PASSWORD', '') !== '') {
    $pass = sk_env('DB_PASS', sk_env('DB_PASSWORD', $pass));
}
if (sk_env('DB_NAME', '') !== '') {
    $name = sk_env('DB_NAME', $name);
}

mysqli_report(MYSQLI_REPORT_OFF);
$mysqli = @new mysqli($host, $user, $pass, $name);
if ($mysqli->connect_error) {
    // Socket auth as root often fails; try unix_socket + app user from .env again with 127.0.0.1
    $mysqli = @new mysqli('127.0.0.1', $user, $pass, $name);
}
if ($mysqli->connect_error) {
    fwrite(STDERR, 'DB fail: ' . $mysqli->connect_error . " (host=$host user=$user db=$name)\n");
    exit(1);
}

class TinyDB {
    private $mysqli;
    private $table = '';
    private $wheres = [];
    private $order = '';
    private $limit = '';
    private $select = '*';
    private $join = '';
    public function __construct($mysqli) { $this->mysqli = $mysqli; }
    public function reset() {
        $this->table = '';
        $this->wheres = [];
        $this->order = '';
        $this->limit = '';
        $this->select = '*';
        $this->join = '';
        return $this;
    }
    public function from($t) { $this->table = $t; return $this; }
    public function select($s) { $this->select = $s; return $this; }
    public function join($t, $on, $type = '') {
        $type = strtoupper(trim($type));
        $this->join .= ' ' . ($type !== '' ? $type . ' ' : '') . 'JOIN ' . $t . ' ON ' . $on;
        return $this;
    }
    public function where($k, $v = null) {
        if (is_array($k)) {
            foreach ($k as $kk => $vv) {
                $this->wheres[] = $this->mysqli->real_escape_string($kk) . "='" . $this->mysqli->real_escape_string((string)$vv) . "'";
            }
        } else {
            $this->wheres[] = $this->mysqli->real_escape_string($k) . "='" . $this->mysqli->real_escape_string((string)$v) . "'";
        }
        return $this;
    }
    public function order_by($c, $d = 'ASC') {
        $sep = $this->order === '' ? ' ORDER BY ' : ', ';
        $this->order .= $sep . $c . ' ' . $d;
        return $this;
    }
    public function limit($n, $o = 0) {
        $this->limit = ' LIMIT ' . (int)$o . ',' . (int)$n;
        return $this;
    }
    public function field_exists($field, $table) {
        $t = $this->mysqli->real_escape_string($table);
        $f = $this->mysqli->real_escape_string($field);
        $r = $this->mysqli->query("SHOW COLUMNS FROM `{$t}` LIKE '{$f}'");
        return $r && $r->num_rows > 0;
    }
    public function table_exists($table) {
        $t = $this->mysqli->real_escape_string($table);
        $r = $this->mysqli->query("SHOW TABLES LIKE '{$t}'");
        return $r && $r->num_rows > 0;
    }
    private $setData = [];
    public function set($k, $v = null) {
        if (is_array($k)) {
            $this->setData = array_merge($this->setData, $k);
        } else {
            $this->setData[$k] = $v;
        }
        return $this;
    }
    public function update($table = null, $data = null) {
        // Best-effort; catalog sync must not fail on store.
        $this->setData = [];
        $this->reset();
        return true;
    }
    public function insert($table = null, $data = null) {
        $this->setData = [];
        $this->reset();
        return true;
    }
    public function insert_id() { return 0; }
    public function affected_rows() { return 0; }
    public function get($table = null) {
        if ($table) {
            $this->table = $table;
        }
        $sql = 'SELECT ' . $this->select . ' FROM ' . $this->table . $this->join;
        if ($this->wheres) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }
        $sql .= $this->order . $this->limit;
        $res = $this->mysqli->query($sql);
        $rows = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        $self = $this;
        return new class($rows, $self) {
            private $rows;
            private $db;
            public function __construct($rows, $db) {
                $this->rows = $rows;
                $this->db = $db;
            }
            public function result_array() {
                $this->db->reset();
                return $this->rows;
            }
            public function row_array() {
                $this->db->reset();
                return $this->rows[0] ?? null;
            }
        };
    }
}

$CI =& get_instance();
$CI->db = new TinyDB($mysqli);
$CI->load = new class {
    public function model($m) {}
    public function helper($h) {}
};

class Sk_Vendor_whatsapp_account_model {
    public function resolve_for_vendor($vendorId) {
        $CI =& get_instance();
        return $CI->db->from('vendor_whatsapp_accounts')->where('vendor_id', $vendorId)->where('status', 'active')->order_by('id', 'DESC')->limit(1)->get()->row_array();
    }
    public function get_by_phone($phone, $any = false) {
        $CI =& get_instance();
        $CI->db->from('vendor_whatsapp_accounts')->where('phone_number_id', $phone);
        if (!$any) {
            $CI->db->where('status', 'active');
        }
        return $CI->db->order_by('id', 'DESC')->limit(1)->get()->row_array();
    }
    public function save_for_vendor($vendorId, $data) {
        return true;
    }
}
class Sk_Vendor_model {
    public function get_by_id($id) {
        $CI =& get_instance();
        return $CI->db->from('vendors')->where('id', $id)->limit(1)->get()->row_array();
    }
}
class Sk_Vendor_meta_agent_model {
    public function ensure_schema() {}
    public function get_by_phone($phone) {
        $CI =& get_instance();
        return $CI->db->from('vendor_meta_agents')->where('phone_number_id', $phone)->order_by('id', 'DESC')->limit(1)->get()->row_array();
    }
}
class Sk_Product_variant_model_stub {
    public function get_by_product($product_id, $active_only = true) {
        $CI =& get_instance();
        if (!$CI->db->table_exists('product_variants')) {
            return [];
        }
        $CI->db->select('pv.*, vu.name as unit_name, vu.slug as unit_slug, vu.symbol as unit_symbol, vu.unit_type')
            ->from('product_variants pv')
            ->join('variant_units vu', 'vu.id = pv.unit_id', 'left')
            ->where('pv.product_id', (int)$product_id);
        if ($active_only) {
            $CI->db->where('pv.status', 1);
        }
        $rows = $CI->db->order_by('pv.is_default', 'DESC')->order_by('pv.sort_order', 'ASC')->order_by('pv.id', 'ASC')->get()->result_array();
        foreach ($rows as &$row) {
            if (empty($row['label'])) {
                $row['label'] = trim(($row['unit_value'] ?? '') . ' ' . ($row['unit_symbol'] ?? $row['unit_name'] ?? ''));
            }
            $base = (float)($row['price'] ?? 0);
            $sale = isset($row['sale_price']) && $row['sale_price'] !== null && $row['sale_price'] !== ''
                ? (float)$row['sale_price'] : null;
            $row['effective_price'] = ($sale !== null && $sale > 0 && $sale < $base) ? $sale : $base;
            if (!empty($row['image'])) {
                $img = trim($row['image']);
                $row['image_url'] = (strpos($img, 'http://') === 0 || strpos($img, 'https://') === 0)
                    ? $img
                    : base_url($img);
            }
        }
        unset($row);
        return $rows;
    }
}

$CI->Sk_Vendor_whatsapp_account_model = new Sk_Vendor_whatsapp_account_model();
$CI->Sk_Vendor_model = new Sk_Vendor_model();
$CI->Sk_Vendor_meta_agent_model = new Sk_Vendor_meta_agent_model();
$CI->Sk_Product_variant_model = new Sk_Product_variant_model_stub();

require APPPATH . 'helpers/sk_meta_business_agent_helper.php';
require APPPATH . 'helpers/sk_meta_catalog_helper.php';

$vendorId = isset($argv[1]) ? (int)$argv[1] : 2;
$result = sk_meta_catalog_sync_vendor_products($vendorId);
echo json_encode([
    'ok' => $result['ok'] ?? false,
    'catalog_id' => $result['catalog_id'] ?? '',
    'catalog_name' => $result['catalog_name'] ?? '',
    'kept' => $result['kept'] ?? count($result['synced'] ?? []),
    'removed' => $result['removed'] ?? count($result['deleted'] ?? []),
    'waba_linked' => $result['waba_linked'] ?? false,
    'error' => $result['error'] ?? '',
    'synced' => array_map(static function ($r) {
        return [
            'product_id' => $r['product_id'] ?? 0,
            'variant_id' => $r['variant_id'] ?? 0,
            'retailer_id' => $r['retailer_id'] ?? '',
            'ok' => $r['ok'] ?? false,
            'error' => $r['error'] ?? '',
        ];
    }, $result['synced'] ?? []),
    'deleted' => array_map(static function ($r) {
        return ['retailer_id' => $r['retailer_id'] ?? '', 'ok' => $r['ok'] ?? false];
    }, $result['deleted'] ?? []),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
