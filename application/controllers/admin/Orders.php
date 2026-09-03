<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/admin/Sk_Base.php';

class Orders extends Sk_Base {

    public function __construct() {
        parent::__construct();
        $this->load->helper('sk_invoice');
        sk_invoice_ensure_vendor_schema();
    }

    public function index() {
        $this->Sk_Order_model->ensure_order_source_schema();
        $page   = max(1, (int)$this->input->get('page'));
        $limit  = 15;
        $offset = ($page - 1) * $limit;
        $tab    = $this->input->get('tab', TRUE);
        if ($tab !== 'abandoned') {
            $tab = 'orders';
        }
        $statusFilter = $this->input->get('status', TRUE);
        // Abandoned lives on its own tab — ignore payment_attempt filter on main tab
        if ($tab === 'orders' && in_array($statusFilter, ['payment_attempt', 'abandoned'], true)) {
            $statusFilter = '';
        }
        $sourceFilter = strtolower(trim((string) $this->input->get('order_source', TRUE)));
        if (!in_array($sourceFilter, ['web', 'app', 'unknown'], true)) {
            $sourceFilter = '';
        }
        $filters = [
            'tab'            => $tab,
            'status'         => $statusFilter,
            'payment_status' => $this->input->get('payment_status', TRUE),
            'order_source'   => $sourceFilter,
            'search'         => $this->input->get('search', TRUE),
        ];

        $data['title']   = 'Orders - 2DEAL Admin';
        $data['orders']  = $this->Sk_Order_model->get_all_admin($limit, $offset, $filters);
        $data['total']   = $this->Sk_Order_model->count_admin($filters);
        $data['page']    = $page;
        $data['limit']   = $limit;
        $data['filters'] = $filters;
        $data['tab']     = $tab;
        // Counts for tab badges (search/payment filters apply; status tab is fixed)
        $baseCounts = [
            'payment_status' => $filters['payment_status'],
            'order_source'   => $filters['order_source'],
            'search'         => $filters['search'],
        ];
        $data['count_orders'] = $this->Sk_Order_model->count_admin($baseCounts + ['tab' => 'orders']);
        $data['count_abandoned'] = $this->Sk_Order_model->count_admin($baseCounts + ['tab' => 'abandoned']);
        $this->render('orders/list', $data);
    }

    public function view($id) {
        $this->Sk_Order_model->ensure_order_source_schema();
        $data['title'] = 'Order Detail';
        $data['order'] = $this->Sk_Order_model->get_by_id($id);
        if (!$data['order']) show_404();

        $this->render('orders/view', $data);
    }

    public function update_status($id) {
        $this->Sk_Order_model->ensure_payment_attempt_status();
        $allowed = ['payment_attempt','pending','confirmed','processing','shipped','delivered','cancelled','returned'];
        $status  = $this->input->post('status', TRUE);
        if (!in_array($status, $allowed)) return $this->json(['success' => false, 'message' => 'Invalid status.']);

        $tracking = $this->input->post('tracking_number', TRUE);
        $orderBefore = $this->Sk_Order_model->get_by_id($id);
        if (!$orderBefore) {
            return $this->json(['success' => false, 'message' => 'Order not found.']);
        }

        if ($status === 'cancelled') {
            if ($orderBefore['status'] !== 'cancelled') {
                $settings = $this->Sk_Admin_model->get_settings();
                $result = $this->Sk_Order_model->cancel_order((int)$id, null, $settings, true);
                if (!$result['ok']) {
                    return $this->json(['success' => false, 'message' => $result['message']]);
                }
            }
            $order = $this->Sk_Order_model->get_by_id($id);
            if ($order) {
                $this->load->helper(['sk_mailer', 'sk_whatsapp']);
                $settings = $this->Sk_Admin_model->get_settings();
                if ($tracking) {
                    $order['tracking_number'] = $tracking;
                }
                sk_mail_order_status($order, $status, $settings);
                sk_whatsapp_notify_order_status($order, $status, $settings);
            }
            return $this->json(['success' => true, 'message' => 'Order cancelled.']);
        }

        $this->Sk_Order_model->update_status($id, $status);
        if ($tracking) {
            $this->db->where('id', $id)->update('orders', ['tracking_number' => $tracking]);
        }
        $order = $this->Sk_Order_model->get_by_id($id);
        if ($order) {
            $this->load->helper(['sk_mailer', 'sk_whatsapp']);
            $settings = $this->Sk_Admin_model->get_settings();
            if ($tracking) $order['tracking_number'] = $tracking;
            sk_mail_order_status($order, $status, $settings);
            sk_whatsapp_notify_order_status($order, $status, $settings);
        }
        $this->json(['success' => true, 'message' => 'Order status updated.']);
    }

    public function invoice($id) {
        $order = $this->Sk_Order_model->get_by_id($id);
        if (!$order) show_404();
        $settings = $this->Sk_Admin_model->get_settings();
        $invoice = sk_invoice_build($order, $settings);
        echo sk_invoice_render_html($invoice, false);
    }

    public function send_invoice($id) {
        $order = $this->Sk_Order_model->get_by_id($id);
        if (!$order) {
            return $this->json(['success' => false, 'message' => 'Order not found.']);
        }
        $settings = $this->Sk_Admin_model->get_settings();
        $this->load->helper('sk_mailer');
        $sent = sk_mail_order_invoice($order, $settings);
        if ($sent) {
            return $this->json(['success' => true, 'message' => 'Tax invoice emailed to ' . ($order['customer_email'] ?? 'customer') . '.']);
        }
        return $this->json(['success' => false, 'message' => 'Could not send invoice. Check SMTP settings and customer email.']);
    }
}
