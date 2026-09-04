<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sk_Base extends CI_Controller {

    protected $admin;
    /** @var Sk_Vendor_context */
    protected $vendor_context;
    /** @var Sk_Activity_log */
    protected $activity_log;

    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->model(['Sk_Admin_model', 'Sk_Product_model', 'Sk_Order_model', 'Sk_User_model', 'Sk_Promo_model', 'Sk_Vendor_model']);
        $this->load->library(['session', 'form_validation', 'upload', 'pagination', 'Sk_Activity_log', 'Sk_Vendor_context']);
        $this->load->helper(['url', 'form', 'text', 'date']);

        $this->vendor_context = $this->sk_vendor_context;
        $this->activity_log   = $this->sk_activity_log;

        if (get_class($this) !== 'Login' && get_class($this) !== 'Vendor_login') {
            $this->_require_admin();
        }
    }

    protected function _require_admin() {
        if ($this->session->userdata('sk_vendor_login')) {
            $vid = (int)$this->session->userdata('sk_vendor_id');
            if (!$vid) {
                $this->_deny_admin('admin/vendor/login');
            }
            $vendor = $this->Sk_Vendor_model->get_by_id($vid, false);
            if (!$vendor || $vendor['status'] !== 'approved') {
                $this->session->unset_userdata(['sk_vendor_login', 'sk_vendor_id', 'sk_vendor_name', 'sk_vendor_email']);
                $this->_deny_admin('admin/vendor/login');
            }
            $shopName = $this->_vendor_shop_display_name($vid);
            $this->admin = [
                'id'         => 0,
                'name'       => $shopName,
                'shop_name'  => $shopName,
                'owner_name' => $vendor['owner_name'],
                'email'      => $vendor['email'],
                'role'       => 'vendor',
            ];
            return;
        }

        $admin_id = $this->session->userdata('sk_admin_id');
        if (!$admin_id) {
            $this->_deny_admin('admin/login');
        }
        $this->admin = $this->Sk_Admin_model->get_by_id($admin_id);
        if (!$this->admin) {
            $this->session->sess_destroy();
            $this->_deny_admin('admin/login');
        }
    }

    /** Redirect for normal pages; JSON 401 for AJAX so jQuery does not show a fake "Network error". */
    protected function _deny_admin(string $loginPath) {
        if ($this->_is_ajax_request()) {
            $this->json([
                'success' => false,
                'message' => 'Session expired. Please log in again.',
                'login'   => site_url($loginPath),
            ], 401);
        }
        redirect($loginPath);
    }

    protected function _is_ajax_request(): bool {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }
        // jQuery $.post / fetch Accept header often used for admin JSON APIs
        $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
        if (stripos($accept, 'application/json') !== false) {
            return true;
        }
        return false;
    }

    /** Shop/store name for vendor UI; falls back to site name from settings. */
    protected function _vendor_shop_display_name(int $vendor_id): string {
        $store = $this->db->select('store_name')->where('vendor_id', $vendor_id)->get('vendor_stores')->row_array();
        $name = trim((string)($store['store_name'] ?? ''));
        if ($name !== '' && strcasecmp($name, 'Default Store') !== 0) {
            return $name;
        }
        if (!isset($this->Sk_Admin_model)) {
            $this->load->model('Sk_Admin_model');
        }
        $settings = $this->Sk_Admin_model->get_settings();
        $site = trim((string)($settings['company_legal_name'] ?? $settings['site_name'] ?? ''));
        if ($site !== '') {
            return $site;
        }
        $vendor = $this->db->select('business_name, name')->where('id', $vendor_id)->get('vendors')->row_array();
        $biz = trim((string)($vendor['business_name'] ?? $vendor['name'] ?? ''));
        return $biz !== '' ? $biz : 'Store';
    }

    protected function is_super_admin(): bool {
        return $this->vendor_context->is_super_admin();
    }

    protected function current_vendor_id(): ?int {
        return $this->vendor_context->vendor_id();
    }

    /** Vendor ID for writes: scoped vendor or super-admin selection. */
    protected function resolve_vendor_id_for_write(?int $posted = null): ?int {
        if ($this->current_vendor_id()) {
            return $this->current_vendor_id();
        }
        if ($posted) return (int)$posted;
        return null;
    }

    protected function assert_product_vendor_access(?array $product): void {
        if (!$product) show_404();
        $vid = $this->current_vendor_id();
        if (!$vid) {
            return;
        }
        $productVendor = (int)($product['vendor_id'] ?? 0);
        // Unassigned products (vendor_id NULL/0) are editable by scoped vendors —
        // they were usually created by super-admin before marketplace assignment.
        if ($productVendor === 0) {
            return;
        }
        if ($productVendor !== $vid) {
            show_error(
                'Access denied: this product belongs to another vendor (product vendor_id='
                . $productVendor . ', your vendor_id=' . $vid
                . '). Log in as main Admin (not Vendor) or exit vendor impersonation, then try again.',
                403
            );
        }
    }

    protected function render($view, $data = []) {
        $data['admin']           = $this->admin;
        $data['settings']        = $this->Sk_Admin_model->get_settings();
        $data['vendor_context']  = $this->vendor_context;
        $data['vendor_logged_in']= (bool)$this->session->userdata('sk_vendor_login');
        $data['impersonating']   = (bool)$this->session->userdata('sk_vendor_id')
            && (bool)$this->session->userdata('sk_admin_id')
            && !$data['vendor_logged_in'];
        $this->load->helper(['sk_whatsapp_cloud', 'sk_whatsapp_mcp']);
        $data['sidebar_wa'] = function_exists('sk_wa_sidebar_stats') ? sk_wa_sidebar_stats() : [
            'sent_today' => 0, 'sent_total' => 0, 'unread' => 0,
            'mcp_enabled' => false, 'mcp_open' => false, 'mcp_label' => 'Closed',
        ];
        $this->load->view('admin/layout/header', $data);
        $this->load->view('admin/layout/sidebar', $data);
        $this->load->view('admin/' . $view, $data);
        $this->load->view('admin/layout/footer', $data);
    }

    protected function json($data, $status = 200) {
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
        $encoded = json_encode($data, $flags);
        if ($encoded === false) {
            $encoded = json_encode([
                'success' => false,
                'message' => 'Failed to encode JSON response.',
            ]);
        }
        echo $encoded;
        exit;
    }

    protected function upload_file($field, $dir = 'products') {
        if (empty($_FILES[$field]['name'])) {
            return null;
        }
        if ((int)($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $code = (int)$_FILES[$field]['error'];
            $msg = 'Image upload failed.';
            if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
                $msg = 'Image is too large. Use JPG, PNG, GIF or WebP under 8MB.';
            } elseif ($code === UPLOAD_ERR_PARTIAL) {
                $msg = 'Image upload was incomplete. Please try again.';
            }
            $this->session->set_flashdata('error', $msg);
            return null;
        }

        $path = FCPATH . 'assets/uploads/' . $dir . '/';
        if (!is_dir($path)) mkdir($path, 0755, true);

        $config = [
            'upload_path'   => $path,
            'allowed_types' => 'jpg|jpeg|png|gif|webp',
            'max_size'      => 8192,
            'file_name'     => uniqid($dir . '_'),
        ];
        $this->load->library('upload', $config);
        $this->upload->initialize($config);

        if ($this->upload->do_upload($field)) {
            return 'assets/uploads/' . $dir . '/' . $this->upload->data('file_name');
        }
        $err = strip_tags((string)$this->upload->display_errors('', ''));
        $this->session->set_flashdata(
            'error',
            $err !== '' ? $err : 'Image upload failed. Use JPG, PNG, GIF or WebP under 8MB.'
        );
        return null;
    }
}
