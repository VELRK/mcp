<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/admin/Sk_Base.php';

class Vendors extends Sk_Base {

    public function __construct() {
        parent::__construct();
        $this->load->model('Sk_Vendor_model');
        // Allow exiting vendor impersonation without super-admin gate (session is scoped while impersonating).
        if ($this->router->fetch_method() !== 'stop_impersonate') {
            $this->vendor_context->require_super_admin();
        }
    }

    public function index() {
        $filters = [
            'search' => $this->input->get('search', TRUE),
            'status' => $this->input->get('status', TRUE),
        ];
        $page   = max(1, (int)($this->input->get('page') ?? 1));
        $limit  = 15;
        $offset = ($page - 1) * $limit;

        $result = $this->Sk_Vendor_model->get_all($filters, $limit, $offset);

        $data['title']   = 'Vendors - 2DEAL Admin';
        $data['vendors'] = $result['rows'];
        $data['total']   = $result['total'];
        $data['page']    = $page;
        $data['limit']   = $limit;
        $data['offset']  = $offset;
        $data['filters'] = $filters;
        $data['counts']  = $this->Sk_Vendor_model->status_counts();
        $this->render('vendors/list', $data);
    }

    public function add() {
        $data['title'] = 'Add Vendor';
        $this->render('vendors/form', $data);
    }

    public function store() {
        $this->form_validation->set_rules('business_name', 'Business Name', 'required|trim');
        $this->form_validation->set_rules('owner_name', 'Owner Name', 'required|trim');
        $this->form_validation->set_rules('email', 'Email', 'required|valid_email|is_unique[vendors.email]');
        $this->form_validation->set_rules('commission_rate', 'Commission Rate', 'numeric');

        if ($this->form_validation->run() === FALSE) {
            $this->session->set_flashdata('error', validation_errors());
            redirect('admin/vendors/add');
        }

        $vendor_data = [
            'business_name'   => $this->input->post('business_name', TRUE),
            'owner_name'      => $this->input->post('owner_name', TRUE),
            'email'           => $this->input->post('email', TRUE),
            'phone'           => $this->input->post('phone', TRUE),
            'password'        => $this->input->post('password') ?: bin2hex(random_bytes(8)),
            'commission_rate' => $this->input->post('commission_rate') ?: 10,
            'status'          => $this->input->post('status') ?: 'pending',
            'verification_status' => $this->input->post('verification_status') ?: 'unverified',
            'subscription_plan' => $this->input->post('subscription_plan') ?: 'basic',
            'notes'           => $this->input->post('notes'),
            'created_by'      => $this->admin['id'],
        ];

        $store_data = [
            'store_name'      => $this->input->post('store_name', TRUE) ?: $vendor_data['business_name'],
            'description'     => $this->input->post('description'),
            'gst_vat'         => $this->input->post('gst_vat', TRUE),
            'business_reg_no' => $this->input->post('business_reg_no', TRUE),
            'contact_email'   => $this->input->post('contact_email', TRUE),
            'contact_phone'   => $this->input->post('contact_phone', TRUE),
        ];

        $id = $this->Sk_Vendor_model->create($vendor_data, $store_data);
        $this->activity_log->log_admin('vendors', 'create', $id, null, $vendor_data);

        $this->session->set_flashdata('success', 'Vendor created successfully.');
        redirect('admin/vendors/view/' . $id);
    }

    public function reset_password($id) {
        $id = (int)$id;
        $vendor = $this->Sk_Vendor_model->get_by_id($id, false);
        if (!$vendor) show_404();

        $newPassword = $this->input->post('new_password', TRUE) ?: 'password';
        $this->Sk_Vendor_model->update($id, ['password' => $newPassword]);
        $this->activity_log->log_admin('vendors', 'reset_password', $id);

        $this->session->set_flashdata('success', 'Vendor password reset to: ' . $newPassword);
        redirect('admin/vendors/view/' . $id);
    }

    public function edit($id) {
        $vendor = $this->Sk_Vendor_model->get_by_id((int)$id);
        if (!$vendor) show_404();

        $data['title']  = 'Edit Vendor';
        $data['vendor'] = $vendor;
        $this->render('vendors/form', $data);
    }

    public function update($id) {
        $id = (int)$id;
        $old = $this->Sk_Vendor_model->get_by_id($id, false);
        if (!$old) show_404();

        $this->form_validation->set_rules('business_name', 'Business Name', 'required|trim');
        $this->form_validation->set_rules('owner_name', 'Owner Name', 'required|trim');
        $this->form_validation->set_rules('email', 'Email', 'required|valid_email');
        $this->form_validation->set_rules('commission_rate', 'Commission Rate', 'numeric');

        if ($this->form_validation->run() === FALSE) {
            $this->session->set_flashdata('error', validation_errors());
            redirect('admin/vendors/edit/' . $id);
        }

        $email = $this->input->post('email', TRUE);
        if ($email !== $old['email']) {
            $exists = $this->Sk_Vendor_model->get_by_email($email);
            if ($exists && (int)$exists['id'] !== $id) {
                $this->session->set_flashdata('error', 'Email already in use.');
                redirect('admin/vendors/edit/' . $id);
            }
        }

        $vendor_data = [
            'business_name'         => $this->input->post('business_name', TRUE),
            'owner_name'            => $this->input->post('owner_name', TRUE),
            'email'                 => $email,
            'phone'                 => $this->input->post('phone', TRUE),
            'password'              => $this->input->post('password'),
            'commission_rate'       => $this->input->post('commission_rate') ?: 10,
            'status'                => $this->input->post('status'),
            'verification_status'   => $this->input->post('verification_status'),
            'subscription_plan'     => $this->input->post('subscription_plan'),
            'notes'                 => $this->input->post('notes'),
            'updated_by'            => $this->admin['id'],
        ];

        $store_data = [
            'store_name'      => $this->input->post('store_name', TRUE),
            'description'     => $this->input->post('description'),
            'gst_vat'         => $this->input->post('gst_vat', TRUE),
            'business_reg_no' => $this->input->post('business_reg_no', TRUE),
            'contact_email'   => $this->input->post('contact_email', TRUE),
            'contact_phone'   => $this->input->post('contact_phone', TRUE),
            'pickup_line1'    => $this->input->post('pickup_line1', TRUE),
            'pickup_city'     => $this->input->post('pickup_city', TRUE),
            'pickup_state'    => $this->input->post('pickup_state', TRUE),
            'pickup_pincode'  => $this->input->post('pickup_pincode', TRUE),
            'meta_title'      => $this->input->post('meta_title', TRUE),
            'meta_desc'       => $this->input->post('meta_desc', TRUE),
        ];

        $this->Sk_Vendor_model->update($id, $vendor_data, $store_data);
        $this->activity_log->log_admin('vendors', 'update', $id, $old, $vendor_data);

        $this->session->set_flashdata('success', 'Vendor updated.');
        redirect('admin/vendors/view/' . $id);
    }

    public function view($id) {
        $vendor = $this->Sk_Vendor_model->get_by_id((int)$id);
        if (!$vendor) show_404();

        $data['title']  = $vendor['business_name'] . ' - Vendor';
        $data['vendor'] = $vendor;
        $this->render('vendors/view', $data);
    }

    public function delete($id) {
        $old = $this->Sk_Vendor_model->get_by_id((int)$id, false);
        if (!$old) show_404();

        $this->Sk_Vendor_model->soft_delete((int)$id, $this->admin['id']);
        $this->activity_log->log_admin('vendors', 'delete', (int)$id, $old, null);

        $this->session->set_flashdata('success', 'Vendor deleted.');
        redirect('admin/vendors');
    }

    public function approve($id) {
        $old = $this->Sk_Vendor_model->get_by_id((int)$id, false);
        if (!$old) show_404();

        $this->Sk_Vendor_model->approve((int)$id, $this->admin['id']);
        $this->activity_log->log_admin('vendors', 'approve', (int)$id, $old, ['status' => 'approved']);

        $this->session->set_flashdata('success', 'Vendor approved.');
        redirect('admin/vendors/view/' . $id);
    }

    public function reject($id) {
        $old = $this->Sk_Vendor_model->get_by_id((int)$id, false);
        if (!$old) show_404();

        $reason = $this->input->post('reason', TRUE) ?: 'Not specified';
        $this->Sk_Vendor_model->reject((int)$id, $this->admin['id'], $reason);
        $this->activity_log->log_admin('vendors', 'reject', (int)$id, $old, ['status' => 'rejected', 'reason' => $reason]);

        $this->session->set_flashdata('success', 'Vendor rejected.');
        redirect('admin/vendors/view/' . $id);
    }

    public function suspend($id) {
        $this->Sk_Vendor_model->suspend((int)$id);
        $this->activity_log->log_admin('vendors', 'suspend', (int)$id);
        $this->session->set_flashdata('success', 'Vendor suspended.');
        redirect('admin/vendors/view/' . $id);
    }

    public function activate($id) {
        $this->Sk_Vendor_model->activate((int)$id);
        $this->activity_log->log_admin('vendors', 'activate', (int)$id);
        $this->session->set_flashdata('success', 'Vendor activated.');
        redirect('admin/vendors/view/' . $id);
    }

    public function whatsapp_accounts($vendor_id) {
        $vendor_id = (int)$vendor_id;
        if ($vendor_id < 1) {
            $this->json(['success' => false, 'message' => 'Vendor not found.'], 400);
            return;
        }

        $this->load->model('Sk_Vendor_whatsapp_account_model');
        $accounts = $this->Sk_Vendor_whatsapp_account_model->get_for_vendor($vendor_id);
        $active = $this->Sk_Vendor_whatsapp_account_model->resolve_for_vendor($vendor_id);

        $this->json([
            'success' => true,
            'vendor_id' => $vendor_id,
            'count' => count($accounts),
            'active' => $active,
            'accounts' => $accounts,
        ]);
    }

    public function add_whatsapp_account($vendor_id) {
        $vendor_id = (int)$vendor_id;
        $phoneNumberId = trim((string)$this->input->post('wa_phone_number_id', TRUE));
        $wabaId = trim((string)$this->input->post('wa_waba_id', TRUE));
        $displayPhone = trim((string)$this->input->post('wa_display_phone', TRUE));
        $businessId = trim((string)$this->input->post('wa_business_id', TRUE));

        if ($vendor_id < 1 || ($phoneNumberId === '' && $wabaId === '')) {
            $this->session->set_flashdata('error', 'Phone Number ID or WABA ID is required.');
            redirect('admin/vendors/view/' . $vendor_id);
        }

        $this->load->model('Sk_Vendor_whatsapp_account_model');
        $result = $this->Sk_Vendor_whatsapp_account_model->save_for_vendor($vendor_id, [
            'phone_number_id' => $phoneNumberId,
            'waba_id' => $wabaId,
            'display_phone' => $displayPhone,
            'business_id' => $businessId,
            'status' => 'active',
            'is_default' => !empty($this->input->post('wa_make_default')) ? 1 : 0,
        ]);

        if (empty($result['ok'])) {
            $this->session->set_flashdata('error', $result['message'] ?? 'Could not save WhatsApp account.');
        } else {
            $this->session->set_flashdata('success', 'WhatsApp account saved.');
        }

        redirect('admin/vendors/view/' . $vendor_id);
    }

    public function set_whatsapp_account_default($vendor_id, $account_id = null) {
        $vendor_id = (int)$vendor_id;
        $account_id = (int)($this->input->post('wa_account_id') ?: $account_id);
        if ($vendor_id < 1 || $account_id < 1) {
            $this->session->set_flashdata('error', 'Invalid WhatsApp account selected.');
            redirect('admin/vendors/view/' . $vendor_id);
        }

        $this->load->model('Sk_Vendor_whatsapp_account_model');
        $ok = $this->Sk_Vendor_whatsapp_account_model->set_default_for_vendor($vendor_id, $account_id);
        if (!$ok) {
            $this->session->set_flashdata('error', 'Could not set the active WhatsApp number.');
        } else {
            $this->session->set_flashdata('success', 'Default WhatsApp account updated.');
        }

        redirect('admin/vendors/view/' . $vendor_id);
    }

    public function whatsapp_report($account_id = 0) {
        $account_id = (int)$account_id;
        if ($account_id < 1) {
            show_404();
            return;
        }

        $this->load->model('Sk_Vendor_whatsapp_message_model');
        $acc = $this->db->where('id', $account_id)->get('vendor_whatsapp_accounts')->row_array();
        if (!$acc) show_404();

        $from = $this->input->get('from', TRUE) ?: null;
        $to = $this->input->get('to', TRUE) ?: null;

        $report = $this->Sk_Vendor_whatsapp_message_model->get_report($account_id, $from, $to);

        $data['title'] = 'WhatsApp Report - ' . ($acc['display_phone'] ?: $acc['phone_number_id']);
        $data['account'] = $acc;
        $data['report'] = $report;
        $data['from'] = $from;
        $data['to'] = $to;

        // CSV export
        $export = $this->input->get('export', TRUE);
        if ($export === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="wa_report_account_' . $account_id . '_' . date('Y-m-d') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['sent_at','recipient','message_type','cost','currency','status','message_id','meta']);
            foreach ($report['details'] as $d) {
                fputcsv($out, [
                    $d['sent_at'] ?? '',
                    $d['recipient'] ?? '',
                    $d['message_type'] ?? '',
                    isset($d['cost']) ? (string)$d['cost'] : '',
                    $d['currency'] ?? '',
                    $d['status'] ?? '',
                    $d['message_id'] ?? '',
                    $d['meta'] ?? '',
                ]);
            }
            fclose($out);
            exit;
        }

        $this->render('vendors/wa_report', $data);
    }

    public function login_as($id) {
        $vendor = $this->Sk_Vendor_model->get_by_id((int)$id, false);
        if (!$vendor || $vendor['status'] !== 'approved') {
            $this->session->set_flashdata('error', 'Cannot login as this vendor.');
            redirect('admin/vendors');
        }
        $this->vendor_context->impersonate((int)$id);
        $this->activity_log->log_admin('vendors', 'login_as', (int)$id);
        $this->session->set_flashdata('success', 'Viewing as vendor: ' . $vendor['business_name']);
        redirect('admin/dashboard');
    }

    public function stop_impersonate() {
        $this->vendor_context->stop_impersonate();
        $this->activity_log->log_admin('vendors', 'stop_impersonate');
        $this->session->set_flashdata('success', 'Returned to admin view.');
        redirect('admin/dashboard');
    }

    public function export() {
        $format = $this->input->get('format') ?: 'csv';
        $rows   = $this->Sk_Vendor_model->export_rows([
            'search' => $this->input->get('search', TRUE),
            'status' => $this->input->get('status', TRUE),
        ]);
        $this->activity_log->log_admin('vendors', 'export', null, null, ['format' => $format, 'count' => count($rows)]);

        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="vendors_' . date('Y-m-d') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Business', 'Owner', 'Email', 'Phone', 'Status', 'Commission', 'Created']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['id'], $r['business_name'], $r['owner_name'], $r['email'], $r['phone'], $r['status'], $r['commission_rate'], $r['created_at']]);
            }
            fclose($out);
            exit;
        }
        redirect('admin/vendors');
    }
}
