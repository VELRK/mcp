<?php
define('BASEPATH', true);
define('FCPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
define('APPPATH', FCPATH . 'application' . DIRECTORY_SEPARATOR);

require_once FCPATH . 'index.php';

$CI =& get_instance();
$CI->load->helper(['sk_meta_business_agent', 'sk_mcp_tool']);
$CI->load->model('Sk_Product_model');

$res = sk_mcp_tool_find_products('Tell me kanjivaram saree details', ['tenant' => 2], 10);
echo "Query: Tell me kanjivaram saree details\n";
echo "Count: " . ($res['count'] ?? 0) . "\n";
echo "Parsed: " . json_encode($res['parsed'] ?? []) . "\n";
if (empty($res['results'])) {
    echo "Results empty!\n";
} else {
    foreach ($res['results'] as $r) {
        echo " - " . $r['name'] . " (Score: " . $r['match_score'] . ")\n";
    }
}
