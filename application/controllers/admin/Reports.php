<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/admin/Sk_Base.php';

class Reports extends Sk_Base {

    public function index() {
        $from = $this->input->get('from') ?? date('Y-m-01');
        $to   = $this->input->get('to')   ?? date('Y-m-d');

        $data['title']        = 'Reports - Talk AI Pilot Admin';
        $data['from']         = $from;
        $data['to']           = $to;
        $data['revenue']      = $this->_revenue_in_range($from, $to);
        $data['order_count']  = $this->_order_count_in_range($from, $to);
        $data['top_products'] = $this->_top_products_in_range($from, $to, 10);
        $data['by_status']    = $this->_orders_by_status($from, $to);
        $data['by_payment']   = $this->_orders_by_payment($from, $to);
        $data['by_day']       = $this->_revenue_by_day($from, $to);
        $this->render('reports/index', $data);
    }

    public function export() {
        $from = $this->input->get('from') ?? date('Y-m-01');
        $to   = $this->input->get('to')   ?? date('Y-m-d');

        $from = preg_replace('/[^0-9\-]/', '', (string)$from) ?: date('Y-m-01');
        $to   = preg_replace('/[^0-9\-]/', '', (string)$to) ?: date('Y-m-d');

        $orders = $this->db->select('o.order_number, u.name as customer, o.total, o.status, o.payment_status, o.created_at')
                           ->from('orders o')
                           ->join('users u', 'u.id = o.user_id', 'left')
                           ->where("DATE(o.created_at) BETWEEN " . $this->db->escape($from) . " AND " . $this->db->escape($to), null, false)
                           ->order_by('o.created_at', 'DESC')
                           ->get()->result_array();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="orders_' . $from . '_to_' . $to . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Excel
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Order #', 'Customer', 'Total', 'Status', 'Payment', 'Date']);
        foreach ($orders as $o) {
            fputcsv($out, [$o['order_number'], $o['customer'], $o['total'], $o['status'], $o['payment_status'], $o['created_at']]);
        }
        fclose($out);
        exit;
    }

    private function _revenue_in_range($from, $to) {
        $r = $this->db->select_sum('total')
                      ->where("DATE(created_at) BETWEEN '$from' AND '$to'", null, false)
                      ->where('payment_status', 'paid')
                      ->get('orders')->row();
        return $r->total ?? 0;
    }

    private function _order_count_in_range($from, $to) {
        return $this->db->where("DATE(created_at) BETWEEN '$from' AND '$to'", null, false)
                        ->count_all_results('orders');
    }

    private function _top_products_in_range($from, $to, $limit = 10) {
        return $this->db->select('oi.product_name, SUM(oi.quantity) as qty_sold, SUM(oi.subtotal) as revenue')
                        ->from('order_items oi')
                        ->join('orders o', 'o.id = oi.order_id')
                        ->where("DATE(o.created_at) BETWEEN '$from' AND '$to'", null, false)
                        ->where('o.payment_status', 'paid')
                        ->group_by('oi.product_id')
                        ->order_by('qty_sold', 'DESC')
                        ->limit($limit)->get()->result_array();
    }

    private function _orders_by_payment($from, $to) {
        $rows = $this->db->select('payment_status, COUNT(*) as count')
                         ->where("DATE(created_at) BETWEEN '$from' AND '$to'", null, false)
                         ->group_by('payment_status')->get('orders')->result_array();
        if (!empty($rows)) return $rows;
        return $this->db->select('payment_status, COUNT(*) as count')
                        ->group_by('payment_status')->get('orders')->result_array();
    }

    private function _orders_by_status($from, $to) {
        // Try date range first; if empty fall back to all-time
        $rows = $this->db->select('status, COUNT(*) as count')
                         ->where("DATE(created_at) BETWEEN '$from' AND '$to'", null, false)
                         ->group_by('status')
                         ->get('orders')->result_array();
        if (!empty($rows)) return $rows;

        return $this->db->select('status, COUNT(*) as count')
                        ->group_by('status')
                        ->get('orders')->result_array();
    }

    private function _revenue_by_day($from, $to) {
        $rows = $this->db
            ->select('DATE(created_at) as date, SUM(total) as revenue')
            ->where("DATE(created_at) BETWEEN '$from' AND '$to'", null, false)
            ->group_by('DATE(created_at)')
            ->order_by('date', 'ASC')
            ->get('orders')->result_array();

        // Fill every day in the range with 0 for missing days
        $map = [];
        foreach ($rows as $r) $map[$r['date']] = (float) $r['revenue'];

        $result = [];
        $current = strtotime($from);
        $end     = strtotime($to);
        while ($current <= $end) {
            $d = date('Y-m-d', $current);
            $result[] = ['date' => date('d M', $current), 'revenue' => $map[$d] ?? 0];
            $current  = strtotime('+1 day', $current);
        }
        return $result;
    }
}
