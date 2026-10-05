<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/admin/Sk_Base.php';

class Login extends Sk_Base {

    public function __construct() {
        parent::__construct();
    }

    public function index() {
        if ($this->session->userdata('sk_admin_id') || $this->session->userdata('sk_vendor_login')) {
            redirect('admin/dashboard');
        }
        $data['title'] = 'Admin Login - Talk AI Pilot';
        $this->load->view('admin/login', $data);
    }

    public function submit() {
        $email    = $this->input->post('email', TRUE);
        $password = $this->input->post('password', TRUE);

        $admin = $this->Sk_Admin_model->get_by_email($email);

        if ($admin && $this->Sk_Admin_model->verify_password($password, $admin['password'])) {
            $this->session->set_userdata([
                'sk_admin_id'   => $admin['id'],
                'sk_admin_name' => $admin['name'],
                'sk_admin_role' => $admin['role'],
            ]);
            $this->Sk_Admin_model->update_last_login($admin['id']);
            redirect('admin/dashboard');
        } else {
            $this->session->set_flashdata('error', 'Invalid email or password.');
            redirect('admin/login');
        }
    }

    public function logout() {
        $this->session->unset_userdata(['sk_admin_id', 'sk_admin_name', 'sk_admin_role', 'sk_vendor_id', 'sk_vendor_login', 'sk_vendor_name', 'sk_vendor_email']);
        redirect('admin/login');
    }
}
