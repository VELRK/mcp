<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/admin/Sk_Base.php';

class Saas_billing extends Sk_Base {

    public function __construct() {
        parent::__construct();
        $this->load->model('Sk_Saas_Billing_model');
        $this->load->helper('sk_currency');
        $this->Sk_Saas_Billing_model->ensure_schema();
    }

    public function index() {
        $vendorScope = $this->_scope_vendor_id();
        $filterVendor = $this->is_super_admin()
            ? (int)$this->input->get('vendor_id')
            : $vendorScope;
        if ($filterVendor < 1) {
            $filterVendor = null;
        }

        $month = trim((string)$this->input->get('month', TRUE));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $monthTot = $this->Sk_Saas_Billing_model->month_total($filterVendor, $month);
        $yest = $this->Sk_Saas_Billing_model->day_bill_total($yesterday, $filterVendor);

        $data['title'] = 'SaaS Billing';
        $data['is_master'] = $this->is_super_admin();
        $data['show_amounts'] = true; // vendors see own bill totals
        $data['currency'] = sk_currency_symbol($this->Sk_Admin_model->get_settings());
        $data['month'] = $month;
        $data['month_total'] = $monthTot;
        $data['yesterday'] = $yest;
        $data['today_count'] = $this->Sk_Saas_Billing_model->count_requests_today($filterVendor);
        $data['vendor_id'] = $filterVendor;
        $data['vendors'] = $this->is_super_admin() ? $this->_vendor_options() : [];

        $daily = $this->Sk_Saas_Billing_model->list_daily_bills([
            'vendor_id' => $filterVendor,
            'date_from' => $month . '-01',
            'date_to'   => date('Y-m-t', strtotime($month . '-01')),
        ], 31, 0);
        $data['daily_rows'] = $daily['rows'];

        $this->render('saas_billing/index', $data);
    }

    public function daily() {
        $vendorScope = $this->_scope_vendor_id();
        $filters = [
            'vendor_id' => $this->is_super_admin() ? (int)$this->input->get('vendor_id') : $vendorScope,
            'date_from' => trim((string)$this->input->get('date_from', TRUE)),
            'date_to'   => trim((string)$this->input->get('date_to', TRUE)),
        ];
        if (empty($filters['vendor_id'])) {
            $filters['vendor_id'] = null;
        }
        if ($filters['date_from'] === '') {
            $filters['date_from'] = date('Y-m-01');
        }
        if ($filters['date_to'] === '') {
            $filters['date_to'] = date('Y-m-d');
        }

        $page = max(1, (int)$this->input->get('page'));
        $limit = 50;
        $offset = ($page - 1) * $limit;
        $result = $this->Sk_Saas_Billing_model->list_daily_bills($filters, $limit, $offset);

        $data['title'] = 'Daily Bills';
        $data['is_master'] = $this->is_super_admin();
        $data['show_amounts'] = true; // vendors see own daily charges
        $data['currency'] = sk_currency_symbol($this->Sk_Admin_model->get_settings());
        $data['rows'] = $result['rows'];
        $data['total'] = $result['total'];
        $data['page'] = $page;
        $data['limit'] = $limit;
        $data['filters'] = $filters;
        $data['vendors'] = $this->is_super_admin() ? $this->_vendor_options() : [];
        $data['vendor_names'] = $this->_vendor_name_map();
        $this->render('saas_billing/daily', $data);
    }

    public function requests() {
        $vendorScope = $this->_scope_vendor_id();
        $filters = [
            'vendor_id'    => $this->is_super_admin() ? (int)$this->input->get('vendor_id') : $vendorScope,
            'request_type' => trim((string)$this->input->get('request_type', TRUE)),
            'source'       => trim((string)$this->input->get('source', TRUE)),
            'search'       => trim((string)$this->input->get('search', TRUE)),
            'date_from'    => trim((string)$this->input->get('date_from', TRUE)),
            'date_to'      => trim((string)$this->input->get('date_to', TRUE)),
        ];
        if (empty($filters['vendor_id'])) {
            $filters['vendor_id'] = null;
        }

        $page = max(1, (int)$this->input->get('page'));
        $limit = 50;
        $offset = ($page - 1) * $limit;
        $result = $this->Sk_Saas_Billing_model->list_requests($filters, $limit, $offset);

        $data['title'] = 'AI Requests';
        $data['is_master'] = $this->is_super_admin();
        $data['show_amounts'] = $this->is_super_admin();
        $data['currency'] = sk_currency_symbol($this->Sk_Admin_model->get_settings());
        $data['rows'] = $result['rows'];
        $data['total'] = $result['total'];
        $data['page'] = $page;
        $data['limit'] = $limit;
        $data['filters'] = $filters;
        $data['vendors'] = $this->is_super_admin() ? $this->_vendor_options() : [];
        $data['vendor_names'] = $this->_vendor_name_map();
        $this->render('saas_billing/requests', $data);
    }

    public function request_view($code = '') {
        $code = rawurldecode((string)$code);
        $row = $this->Sk_Saas_Billing_model->get_request_by_code($code);
        if (!$row) {
            show_404();
        }

        $scope = $this->_scope_vendor_id();
        if ($scope && (int)$row['vendor_id'] !== (int)$scope) {
            show_error('Access denied.', 403);
        }

        $meta = $row['meta'] ?? '';
        $metaDecoded = null;
        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);
            $metaDecoded = is_array($decoded) ? $decoded : $meta;
        }

        $data['title'] = 'AI Request ' . $row['request_code'];
        $data['is_master'] = $this->is_super_admin();
        $data['show_amounts'] = $this->is_super_admin();
        $data['currency'] = sk_currency_symbol($this->Sk_Admin_model->get_settings());
        $data['row'] = $row;
        $data['meta'] = $metaDecoded;
        $data['vendor_name'] = $this->_vendor_name((int)$row['vendor_id']);
        $this->render('saas_billing/request_view', $data);
    }

    public function amounts() {
        $this->vendor_context->require_super_admin();

        $data['title'] = 'Client Amounts';
        $data['is_master'] = true;
        $data['currency'] = sk_currency_symbol($this->Sk_Admin_model->get_settings());
        $data['rows'] = $this->Sk_Saas_Billing_model->list_amounts();
        $data['vendors'] = $this->_vendor_options();
        $data['vendor_names'] = $this->_vendor_name_map();
        $this->render('saas_billing/amounts', $data);
    }

    public function amount_save() {
        $this->vendor_context->require_super_admin();
        if (strtoupper((string)$this->input->method(TRUE)) !== 'POST') {
            redirect('admin/saas-billing/amounts');
            return;
        }

        $id = (int)$this->input->post('id');
        $result = $this->Sk_Saas_Billing_model->save_amount([
            'vendor_id'    => $this->input->post('vendor_id'),
            'request_type' => $this->input->post('request_type', TRUE),
            'unit_amount'  => $this->input->post('unit_amount'),
            'currency'     => $this->input->post('currency', TRUE),
            'label'        => $this->input->post('label', TRUE),
            'status'       => $this->input->post('status') ? 1 : 0,
        ], $id ?: null);

        if (!empty($result['ok'])) {
            $this->activity_log->log_admin('saas_billing', $id ? 'amount_update' : 'amount_create', (int)$result['id']);
            $this->session->set_flashdata('success', 'Client amount saved.');
        } else {
            $this->session->set_flashdata('error', 'Could not save client amount.');
        }
        redirect('admin/saas-billing/amounts');
    }

    public function amount_delete($id) {
        $this->vendor_context->require_super_admin();
        $id = (int)$id;
        if ($id > 0) {
            $this->Sk_Saas_Billing_model->delete_amount($id);
            $this->activity_log->log_admin('saas_billing', 'amount_delete', $id);
            $this->session->set_flashdata('success', 'Client amount removed.');
        }
        redirect('admin/saas-billing/amounts');
    }

    /** Manual run of morning job (master only). */
    public function run_job() {
        $this->vendor_context->require_super_admin();
        $date = trim((string)$this->input->get('date', TRUE));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = null;
        }
        $result = $this->Sk_Saas_Billing_model->run_morning_job($date, true);
        $msg = ($result['rollup']['message'] ?? '') . ' ' . ($result['whatsapp']['message'] ?? '');
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', trim($msg));
        redirect('admin/saas-billing');
    }

    protected function _scope_vendor_id(): ?int {
        $vid = $this->current_vendor_id();
        return $vid ? (int)$vid : null;
    }

    protected function _vendor_options(): array {
        if (!$this->db->table_exists('vendors')) {
            return [];
        }
        return $this->db->select('id, business_name, email')
            ->order_by('business_name', 'ASC')
            ->get('vendors')
            ->result_array();
    }

    protected function _vendor_name_map(): array {
        $map = [];
        foreach ($this->_vendor_options() as $v) {
            $map[(int)$v['id']] = $v['business_name'] ?: ('Vendor #' . $v['id']);
        }
        return $map;
    }

    protected function _vendor_name(int $id): string {
        $map = $this->_vendor_name_map();
        return $map[$id] ?? ('Vendor #' . $id);
    }
}
