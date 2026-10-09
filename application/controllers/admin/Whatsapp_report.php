<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/admin/Sk_Base.php';

class Whatsapp_report extends Sk_Base {

    public function index() {
        $this->load->helper('sk_whatsapp');
        sk_whatsapp_ensure_log_schema();

        $status = trim((string)$this->input->get('status', TRUE));
        $search = trim((string)$this->input->get('search', TRUE));
        $from   = trim((string)$this->input->get('from', TRUE));
        $to     = trim((string)$this->input->get('to', TRUE));
        $page   = max(1, (int)$this->input->get('page'));
        $limit  = 25;
        $offset = ($page - 1) * $limit;

        $vendorId = (!$this->is_super_admin()) ? (int)($this->current_vendor_id() ?? 0) : 0;
        $applyFilters = function () use ($status, $search, $from, $to, $vendorId) {
            $this->db->like('channel', 'meta', 'after');
            if ($vendorId > 0 && $this->db->field_exists('vendor_id', 'whatsapp_logs')) {
                $this->db->where('vendor_id', $vendorId);
            }
            if (in_array($status, ['sent', 'delivered', 'read', 'failed'], true)) {
                $this->db->where('delivery_status', $status);
            }
            if ($search !== '') {
                $this->db->group_start()
                    ->like('order_number', $search)
                    ->or_like('phone', $search)
                    ->or_like('reason', $search)
                    ->or_like('template_name', $search)
                    ->group_end();
            }
            if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
                $this->db->where('created_at >=', $from . ' 00:00:00');
            }
            if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                $this->db->where('created_at <=', $to . ' 23:59:59');
            }
        };

        $applyFilters();
        $total = (int)$this->db->count_all_results('whatsapp_logs');

        $applyFilters();
        $rows = $this->db->order_by('id', 'DESC')->limit($limit, $offset)->get('whatsapp_logs')->result_array();

        // Summary counts (same date/search filters, ignore status filter for cards)
        $this->db->reset_query();
        $this->db->like('channel', 'meta', 'after');
        if ($vendorId > 0 && $this->db->field_exists('vendor_id', 'whatsapp_logs')) {
            $this->db->where('vendor_id', $vendorId);
        }
        if ($search !== '') {
            $this->db->group_start()
                ->like('order_number', $search)
                ->or_like('phone', $search)
                ->or_like('reason', $search)
                ->or_like('template_name', $search)
                ->group_end();
        }
        if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $this->db->where('created_at >=', $from . ' 00:00:00');
        }
        if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $this->db->where('created_at <=', $to . ' 23:59:59');
        }
        $summaryRows = $this->db->select('delivery_status, COUNT(*) AS cnt', false)
            ->group_by('delivery_status')
            ->get('whatsapp_logs')->result_array();
        $summary = ['sent' => 0, 'delivered' => 0, 'read' => 0, 'failed' => 0, 'total' => 0];
        foreach ($summaryRows as $s) {
            $k = $s['delivery_status'];
            $c = (int)$s['cnt'];
            if (isset($summary[$k])) {
                $summary[$k] = $c;
            }
            $summary['total'] += $c;
        }

        $data['title']   = 'WhatsApp Delivery Report';
        $data['logs']    = $rows;
        $data['summary'] = $summary;
        $data['total']   = $total;
        $data['page']    = $page;
        $data['limit']   = $limit;
        $data['status']  = $status;
        $data['search']  = $search;
        $data['from']    = $from;
        $data['to']      = $to;
        $this->render('whatsapp_report/index', $data);
    }

    public function view($id = 0) {
        $this->load->helper('sk_whatsapp');
        sk_whatsapp_ensure_log_schema();
        $row = $this->db->where('id', (int)$id)->get('whatsapp_logs')->row_array();
        if (!$row) {
            show_404();
        }
        $data['title'] = 'WhatsApp Log #' . (int)$id;
        $data['log']   = $row;
        $this->render('whatsapp_report/view', $data);
    }

    /**
     * Resend WhatsApp for a log row (uses same order + status_trigger).
     * GET/POST shopkart/whatsapp-report/resend/{id}
     */
    public function resend($id = 0) {
        $this->load->helper('sk_whatsapp');
        sk_whatsapp_ensure_log_schema();
        $this->load->model('Sk_Order_model');
        $this->load->model('Sk_Admin_model');

        $id = (int)$id;
        $log = $this->db->where('id', $id)->get('whatsapp_logs')->row_array();
        if (!$log) {
            $this->session->set_flashdata('error', 'WhatsApp log not found.');
            redirect('shopkart/whatsapp-report');
        }

        $orderId = (int)($log['order_id'] ?? 0);
        if ($orderId < 1) {
            $this->session->set_flashdata('error', 'Cannot resend: no order linked to this log.');
            redirect('shopkart/whatsapp-report/view/' . $id);
        }

        $order = $this->Sk_Order_model->get_by_id($orderId);
        if (!$order) {
            $this->session->set_flashdata('error', 'Order #' . $orderId . ' not found.');
            redirect('shopkart/whatsapp-report/view/' . $id);
        }

        // Prefer the status that was originally triggered; fall back to current order status
        $status = trim((string)($log['status_trigger'] ?? ''));
        $allowed = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'];
        if ($status === '' || !in_array($status, $allowed, true)) {
            $status = trim((string)($order['status'] ?? 'pending'));
        }
        if (!in_array($status, $allowed, true)) {
            $status = 'pending';
        }

        // Ensure name fields for template {{Customername}}
        if (empty($order['customer_name']) && !empty($order['shipping_name'])) {
            $order['customer_name'] = $order['shipping_name'];
        }

        // null settings → helper loads the shop Meta account and sends the order template.
        $result = sk_whatsapp_notify_order_status($order, $status, null);

        $ok = !empty($result['success']);
        $msg = (string)($result['message'] ?? ($ok ? 'Resent successfully.' : 'Resend failed.'));
        $via = (string)($result['via'] ?? '');
        if ($via !== '') {
            $msg .= ' (via ' . $via . ')';
        }

        if ($ok) {
            $this->session->set_flashdata('success', 'WhatsApp resent for order '
                . ($order['order_number'] ?? ('#'.$orderId)) . ': ' . $msg);
        } else {
            $this->session->set_flashdata('error', 'Resend failed: ' . $msg);
        }

        // Back to filtered list if referer was the report list
        $back = $this->input->get('back', TRUE);
        if ($back === 'view') {
            redirect('shopkart/whatsapp-report/view/' . $id);
        }
        redirect('shopkart/whatsapp-report' . ($this->input->server('QUERY_STRING') ? '?' . $this->input->server('QUERY_STRING') : ''));
    }
}
