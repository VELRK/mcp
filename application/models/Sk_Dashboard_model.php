<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sk_Dashboard_model extends CI_Model {

    public function platform_stats(): array {
        return [
            'vendors'          => $this->db->where('deleted_at IS NULL', null, false)->count_all_results('vendors'),
            'approved_vendors' => $this->db->where('status', 'approved')->where('deleted_at IS NULL', null, false)->count_all_results('vendors'),
            'pending_vendors'  => $this->db->where('status', 'pending')->where('deleted_at IS NULL', null, false)->count_all_results('vendors'),
            'total_products'   => $this->db->count_all('products'),
            'total_orders'     => $this->db->count_all('orders'),
            'pending_orders'   => $this->db->where_in('status', ['pending', 'payment_attempt'])->count_all_results('orders'),
            'total_customers'  => $this->db->count_all('users'),
            'total_revenue'    => (float)($this->db->select_sum('total')->where('payment_status', 'paid')->get('orders')->row()->total ?? 0),
            'monthly_revenue'  => (float)($this->db->select_sum('total')
                ->where('payment_status', 'paid')
                ->where('MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())', null, false)
                ->get('orders')->row()->total ?? 0),
        ];
    }

    public function vendor_stats(int $vendor_id): array {
        $this->load->helper('sk_vendor_dashboard');
        sk_vendor_dashboard_ensure_schema();
        sk_vendor_backfill_order_item_vendors($vendor_id);

        $products = $this->db->where('vendor_id', $vendor_id)->count_all_results('products');
        $active_products = $this->db->where('vendor_id', $vendor_id)->where('status', 'active')->count_all_results('products');
        $low_stock = $this->db->where('vendor_id', $vendor_id)
                               ->where('stock <= low_stock_alert', null, false)
                               ->count_all_results('products');

        $vendorScope = function () use ($vendor_id) {
            $this->db->group_start()
                     ->where('oi.vendor_id', $vendor_id)
                     ->or_where('p.vendor_id', $vendor_id)
                     ->group_end();
        };

        $this->db->select_sum('oi.subtotal', 'total')
                 ->from('order_items oi')
                 ->join('orders o', 'o.id = oi.order_id')
                 ->join('products p', 'p.id = oi.product_id', 'left');
        $vendorScope();
        $revenue = (float)($this->db->where('o.payment_status', 'paid')->get()->row()->total ?? 0);

        $this->db->select('COUNT(DISTINCT oi.order_id) as cnt', false)
                 ->from('order_items oi')
                 ->join('products p', 'p.id = oi.product_id', 'left');
        $vendorScope();
        $orders = (int)($this->db->get()->row()->cnt ?? 0);

        $this->db->select('COUNT(DISTINCT oi.order_id) as cnt', false)
                 ->from('order_items oi')
                 ->join('orders o', 'o.id = oi.order_id')
                 ->join('products p', 'p.id = oi.product_id', 'left');
        $vendorScope();
        $pending = (int)($this->db->where_in('o.status', ['pending', 'payment_attempt'])->get()->row()->cnt ?? 0);

        $this->db->select_sum('oi.subtotal', 'total')
                 ->from('order_items oi')
                 ->join('orders o', 'o.id = oi.order_id')
                 ->join('products p', 'p.id = oi.product_id', 'left');
        $vendorScope();
        $monthly_revenue = (float)($this->db->where('o.payment_status', 'paid')
            ->where('MONTH(o.created_at) = MONTH(NOW()) AND YEAR(o.created_at) = YEAR(NOW())', null, false)
            ->get()->row()->total ?? 0);

        return [
            'products'         => (int)$products,
            'active_products'  => (int)$active_products,
            'low_stock'        => (int)$low_stock,
            'orders'           => (int)$orders,
            'pending_orders'   => (int)$pending,
            'revenue'          => (float)$revenue,
            'monthly_revenue'  => (float)$monthly_revenue,
        ];
    }

    public function vendor_revenue_chart(int $vendor_id, int $days = 30): array {
        $q = $this->db->select('DATE(o.created_at) as date, SUM(oi.subtotal) as revenue', false)
                         ->from('order_items oi')
                         ->join('orders o', 'o.id = oi.order_id')
                         ->join('products p', 'p.id = oi.product_id', 'left')
                         ->group_start()
                             ->where('oi.vendor_id', $vendor_id)
                             ->or_where('p.vendor_id', $vendor_id)
                         ->group_end()
                         ->where('o.payment_status', 'paid')
                         ->where('o.created_at >=', date('Y-m-d', strtotime("-{$days} days")))
                         ->group_by('DATE(o.created_at)')
                         ->order_by('date', 'ASC')
                         ->get();
        $rows = ($q && is_object($q)) ? $q->result_array() : [];

        $map = [];
        foreach ($rows as $r) $map[$r['date']] = (float)$r['revenue'];

        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $result[] = ['date' => date('d M', strtotime($d)), 'revenue' => $map[$d] ?? 0];
        }
        return $result;
    }

    public function vendor_top_products(int $vendor_id, int $limit = 5): array {
        $q = $this->db->select('oi.product_id, MAX(oi.product_name) as product_name, SUM(oi.quantity) as qty_sold, SUM(oi.subtotal) as revenue', false)
                        ->from('order_items oi')
                        ->join('orders o', 'o.id = oi.order_id')
                        ->join('products p', 'p.id = oi.product_id', 'left')
                        ->group_start()
                            ->where('oi.vendor_id', $vendor_id)
                            ->or_where('p.vendor_id', $vendor_id)
                        ->group_end()
                        ->where('o.payment_status', 'paid')
                        ->group_by('oi.product_id')
                        ->order_by('revenue', 'DESC')
                        ->limit($limit)
                        ->get();
        return ($q && is_object($q)) ? $q->result_array() : [];
    }

    public function vendor_recent_orders(int $vendor_id, int $limit = 8): array {
        $q = $this->db->select('o.id, o.order_number, o.status, o.payment_status, o.created_at, o.total, u.name as customer_name, SUM(oi.subtotal) as vendor_total', false)
                        ->from('order_items oi')
                        ->join('orders o', 'o.id = oi.order_id')
                        ->join('users u', 'u.id = o.user_id', 'left')
                        ->join('products p', 'p.id = oi.product_id', 'left')
                        ->group_start()
                            ->where('oi.vendor_id', $vendor_id)
                            ->or_where('p.vendor_id', $vendor_id)
                        ->group_end()
                        ->group_by('o.id')
                        ->order_by('o.created_at', 'DESC')
                        ->limit($limit)
                        ->get();
        return ($q && is_object($q)) ? $q->result_array() : [];
    }
}
