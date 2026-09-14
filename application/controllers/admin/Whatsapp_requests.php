<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/admin/Sk_Base.php';

class Whatsapp_requests extends Sk_Base {

    public function __construct() {
        parent::__construct();
        $this->load->model('Sk_Wa_Provision_request_model');
        $this->load->model('Sk_Vendor_whatsapp_account_model');
    }

    // Vendor view: list requests and show form to create
    public function index() {
        $vid = $this->current_vendor_id();
        if (!$vid) {
            show_error('Only vendors may request WhatsApp numbers.', 403);
            return;
        }
        $requests = $this->Sk_Wa_Provision_request_model->get_for_vendor($vid);
        $data['title'] = 'WhatsApp Number Requests';
        $data['requests'] = $requests;
        $this->render('whatsapp_requests/index', $data);
    }

    public function submit() {
        $vid = $this->current_vendor_id();
        if (!$vid) {
            show_error('Only vendors may request WhatsApp numbers.', 403);
            return;
        }
        $phone = trim((string)$this->input->post('phone_number_id', TRUE) ?: '');
        $display = trim((string)$this->input->post('display_phone', TRUE) ?: '');
        $waba = trim((string)$this->input->post('waba_id', TRUE) ?: '');
        $business = trim((string)$this->input->post('business_id', TRUE) ?: '');
        $note = trim((string)$this->input->post('note', TRUE) ?: '');

        if ($phone === '' && $display === '') {
            $this->session->set_flashdata('error', 'Provide at least the phone number or display phone.');
            redirect('admin/whatsapp_requests');
            return;
        }

        $res = $this->Sk_Wa_Provision_request_model->create($vid, [
            'phone_number_id' => $phone,
            'display_phone' => $display,
            'waba_id' => $waba,
            'business_id' => $business,
            'note' => $note,
        ]);

        if (!empty($res['ok'])) {
            $this->activity_log->log_admin('wa_requests', 'create', $res['id'], null, ['vendor_id' => $vid]);
            $this->session->set_flashdata('success', 'WhatsApp provisioning request created. Admin will review shortly.');
        } else {
            $this->session->set_flashdata('error', 'Could not create request.');
        }
        redirect('admin/whatsapp_requests');
    }

    // Admin: list pending requests
    public function pending() {
        if (!$this->is_super_admin()) show_error('Admin only', 403);
        $rows = $this->Sk_Wa_Provision_request_model->get_pending();
        // Attach vendor display name for admin convenience
        foreach ($rows as &$r) {
            $vendor = $this->Sk_Vendor_model->get_by_id((int)$r['vendor_id'], false);
            $r['vendor_name'] = $vendor ? (trim((string)($vendor['business_name'] ?? $vendor['owner_name'] ?? '')) ?: 'Vendor #' . (int)$r['vendor_id']) : 'Vendor #' . (int)$r['vendor_id'];
            $r['vendor_email'] = $vendor['email'] ?? '';
        }
        unset($r);
        $data['title'] = 'WhatsApp Provision Requests';
        $data['requests'] = $rows;
        $this->render('whatsapp_requests/pending', $data);
    }

    // Admin approve: connect and save account
    public function approve($id = 0) {
        if (!$this->is_super_admin()) show_error('Admin only', 403);
        $id = (int)$id;
        $req = $this->Sk_Wa_Provision_request_model->get_by_id($id);
        if (!$req) show_404();

        if (strtoupper((string)$this->input->server('REQUEST_METHOD')) === 'POST') {
            $phone = trim((string)$this->input->post('phone_number_id', TRUE) ?: ($req['phone_number_id'] ?? ''));
            $waba = trim((string)$this->input->post('waba_id', TRUE) ?: ($req['waba_id'] ?? ''));
            $display = trim((string)$this->input->post('display_phone', TRUE) ?: ($req['display_phone'] ?? ''));
            $business = trim((string)$this->input->post('business_id', TRUE) ?: ($req['business_id'] ?? ''));
            $access = trim((string)$this->input->post('access_token', FALSE) ?: '');
            $refresh = trim((string)$this->input->post('refresh_token', FALSE) ?: '');
            $app_id = trim((string)$this->input->post('app_id', TRUE) ?: '');
            $is_default = $this->input->post('is_default') ? 1 : 0;

            $saveRes = $this->Sk_Vendor_whatsapp_account_model->save_for_vendor((int)$req['vendor_id'], [
                'phone_number_id' => $phone,
                'waba_id' => $waba,
                'display_phone' => $display,
                'business_id' => $business,
                'access_token' => $access,
                'refresh_token' => $refresh,
                'is_default' => $is_default,
            ]);

            if (!empty($saveRes['ok'])) {
                $this->Sk_Wa_Provision_request_model->update_status($id, 'approved', $this->admin['id'], 'Connected by admin, account id ' . ($saveRes['id'] ?? ''));
                $this->activity_log->log_admin('wa_requests', 'approve', $id, $req, ['account' => $saveRes]);
                $this->session->set_flashdata('success', 'Provisioning approved and account saved.');
                redirect('admin/whatsapp_requests/pending');
                return;
            }
            $this->session->set_flashdata('error', 'Failed to save account.');
            redirect('admin/whatsapp_requests/pending');
            return;
        }

        $data['title'] = 'Approve WhatsApp Request';
        $data['request'] = $req;
        $this->render('whatsapp_requests/approve', $data);
    }

    public function reject($id = 0) {
        if (!$this->is_super_admin()) show_error('Admin only', 403);
        $id = (int)$id;
        $note = trim((string)$this->input->post('admin_note', TRUE) ?: 'Rejected by admin');
        $ok = $this->Sk_Wa_Provision_request_model->update_status($id, 'rejected', $this->admin['id'], $note);
        if ($ok) {
            $this->activity_log->log_admin('wa_requests', 'reject', $id, null, ['note' => $note]);
            $this->session->set_flashdata('success', 'Request rejected.');
        } else {
            $this->session->set_flashdata('error', 'Could not reject request.');
        }
        redirect('admin/whatsapp_requests/pending');
    }
}
