<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sk_Base_Api extends CI_Controller {

    protected $user = null;

    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->model(['Sk_Product_model','Sk_Order_model','Sk_User_model','Sk_Promo_model','Sk_Admin_model']);
        $this->load->library('Sk_JWT');
        $this->load->helper(['url']);
        // CORS
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token, X-Requested-With, X-Session-ID');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
    }

    protected function success($data = [], $message = 'Success', $status = 200) {
        $this->_json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    protected function error($message = 'Error', $status = 400, $data = []) {
        $this->_json(['success' => false, 'message' => $message, 'data' => $data], $status);
    }

    private function _json($payload, $status) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        $encoded = json_encode($payload, $flags);
        if ($encoded === false) {
            $encoded = json_encode([
                'success' => false,
                'message' => 'Failed to encode JSON response.',
                'data'    => [],
            ]);
        }
        echo $encoded;
        exit;
    }

    /**
     * Read from file cache. Returns the decoded data array, or null on miss.
     * TTLs: categories/banners 120 s, products/reviews 60 s, settings 300 s.
     */
    protected function get_cache(string $key, int $ttl) {
        $file = $this->_cache_file($key);
        if (file_exists($file) && (time() - filemtime($file)) < $ttl) {
            $raw = file_get_contents($file);
            if ($raw !== false) return json_decode($raw, true);
        }
        return null;
    }

    /** Write data to file cache. */
    protected function set_cache(string $key, $data): void {
        $dir = APPPATH . 'cache/api/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        @file_put_contents($this->_cache_file($key), json_encode($data), LOCK_EX);
    }

    /** Drop a cached API payload (e.g. after review approval). */
    protected function delete_cache(string $key): void {
        $file = $this->_cache_file($key);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private function _cache_file(string $key): string {
        $safe = preg_replace('/[^a-z0-9_-]/', '_', strtolower($key));
        return APPPATH . 'cache/api/' . $safe . '.json';
    }

    protected function auth_required() {
        $this->user = $this->sk_jwt->get_user_from_request();
        if (!$this->user) $this->error('Unauthorized. Please login.', 401);
        return $this->user;
    }

    protected function body() {
        return json_decode($this->input->raw_input_stream, true) ?? [];
    }

    protected function get_settings() {
        return $this->Sk_Admin_model->get_settings();
    }
}
