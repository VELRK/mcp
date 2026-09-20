<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Vendor_login extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->model('Sk_Vendor_model');
        $this->load->library('session');
        $this->load->helper(['url', 'form']);
    }

    public function index() {
        if ($this->session->userdata('sk_vendor_login')) {
            redirect('admin/dashboard');
        }
        if ($this->session->userdata('sk_admin_id')) {
            redirect('admin/dashboard');
        }
        $data['title'] = 'Vendor Login - 2DEAL';
        $this->load->view('admin/vendor_login', $data);
    }

    public function submit() {
        $email    = strtolower(trim((string) ($this->input->post('email', TRUE) ?? '')));
        $password = (string) ($this->input->post('password', TRUE) ?? '');

        if ($email === '' || $password === '') {
            $this->session->set_flashdata('error', 'Enter both email and password.');
            redirect('admin/vendor/login');
        }

        $vendor = $this->Sk_Vendor_model->get_by_email($email);

        if (!$vendor) {
            $this->session->set_flashdata('error', 'Invalid email or password.');
            redirect('admin/vendor/login');
        }

        if (($vendor['status'] ?? '') !== 'approved') {
            $this->session->set_flashdata('error', 'Your vendor account is not approved yet. Status: ' . ($vendor['status'] ?? 'unknown'));
            redirect('admin/vendor/login');
        }

        if (empty($vendor['password'])) {
            $this->session->set_flashdata('error', 'No password set. Use Forgot password or contact admin.');
            redirect('admin/vendor/login');
        }

        if (!$this->Sk_Vendor_model->verify_password($password, (string) $vendor['password'])) {
            $this->session->set_flashdata('error', 'Invalid email or password.');
            redirect('admin/vendor/login');
        }

        $store = $this->db->select('store_name')->where('vendor_id', (int)$vendor['id'])->get('vendor_stores')->row_array();
        $shopName = trim((string)($store['store_name'] ?? ''));
        if ($shopName === '') {
            $shopName = 'Default Store';
        }

        $this->session->set_userdata([
            'sk_vendor_login' => true,
            'sk_vendor_id'    => (int)$vendor['id'],
            'sk_vendor_name'  => $shopName,
            'sk_vendor_email' => $vendor['email'],
        ]);

        redirect('admin/dashboard');
    }

    public function forgot_password() {
        if ($this->session->userdata('sk_vendor_login')) {
            redirect('admin/vendor/account/password');
        }
        $data['title'] = 'Vendor Forgot Password - 2DEAL';
        $this->load->view('admin/vendor_forgot_password', $data);
    }

    /** Step 1: email → send 6-digit code */
    public function forgot_submit() {
        $email = strtolower(trim((string) $this->input->post('email', TRUE)));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->session->set_flashdata('error', 'Enter a valid vendor email.');
            redirect('admin/vendor/forgot-password');
        }

        $this->Sk_Vendor_model->ensure_password_reset_schema();
        $vendor = $this->Sk_Vendor_model->get_by_email($email);

        // Always show success-ish message to avoid email enumeration
        if (!$vendor || ($vendor['status'] ?? '') !== 'approved') {
            $this->session->set_flashdata('success', 'If that email is registered as an approved vendor, a code has been sent.');
            redirect('admin/vendor/forgot-password');
        }

        $code = $this->Sk_Vendor_model->set_reset_code($email);
        $sent = $code ? $this->Sk_Vendor_model->send_password_change_code_email($vendor, $code) : false;

        $this->session->set_userdata('sk_vendor_reset_email', $email);

        if (!$sent && ENVIRONMENT !== 'production' && $code) {
            $this->session->set_flashdata('success', 'Verification code (dev): ' . $code);
        } elseif (!$sent) {
            $this->session->set_flashdata('error', 'Could not send email. Check SMTP settings or contact admin.');
            redirect('admin/vendor/forgot-password');
        } else {
            $this->session->set_flashdata('success', 'Verification code sent to your email.');
        }
        redirect('admin/vendor/reset-password');
    }

    public function reset_password() {
        if ($this->session->userdata('sk_vendor_login')) {
            redirect('admin/vendor/account/password');
        }
        $email = $this->session->userdata('sk_vendor_reset_email');
        if (!$email) {
            redirect('admin/vendor/forgot-password');
        }
        $data['title'] = 'Vendor Reset Password - 2DEAL';
        $data['email'] = $email;
        $this->load->view('admin/vendor_reset_password', $data);
    }

    /** Step 2: code + new password */
    public function reset_submit() {
        $email = strtolower(trim((string) ($this->input->post('email', TRUE) ?: $this->session->userdata('sk_vendor_reset_email'))));
        $code = trim((string) $this->input->post('code', TRUE));
        $pass = (string) $this->input->post('password', TRUE);
        $confirm = (string) $this->input->post('password_confirm', TRUE);

        if ($email === '') {
            $this->session->set_flashdata('error', 'Start again with your vendor email.');
            redirect('admin/vendor/forgot-password');
        }

        if (!preg_match('/^\d{6}$/', $code)) {
            $this->session->set_flashdata('error', 'Enter the 6-digit email verification code.');
            $this->session->set_userdata('sk_vendor_reset_email', $email);
            redirect('admin/vendor/reset-password');
        }
        if (strlen($pass) < 6 || $pass !== $confirm) {
            $this->session->set_flashdata('error', 'Password must be 6+ characters and match confirmation.');
            $this->session->set_userdata('sk_vendor_reset_email', $email);
            redirect('admin/vendor/reset-password');
        }

        $this->Sk_Vendor_model->ensure_password_reset_schema();
        $token = $this->Sk_Vendor_model->verify_reset_code($email, $code);
        if (!$token || !$this->Sk_Vendor_model->reset_password_with_token($email, $token, $pass)) {
            $this->session->set_flashdata('error', 'Invalid or expired code. Request a new one.');
            $this->session->set_userdata('sk_vendor_reset_email', $email);
            redirect('admin/vendor/forgot-password');
        }

        $this->session->unset_userdata('sk_vendor_reset_email');
        $this->session->set_flashdata('success', 'Password updated. You can sign in now.');
        redirect('admin/vendor/login');
    }

    public function logout() {
        $this->session->unset_userdata(['sk_vendor_login', 'sk_vendor_id', 'sk_vendor_name', 'sk_vendor_email', 'sk_vendor_reset_email']);
        redirect('admin/vendor/login');
    }
}
