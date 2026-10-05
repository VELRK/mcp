<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/admin/Sk_Base.php';

class Dashboard extends Sk_Base {

    public function __construct() {
        parent::__construct();
        $this->load->model('Sk_Dashboard_model');
    }

    public function index() {
        try {
            $this->_load_dashboard();
        } catch (Throwable $e) {
            log_message('error', 'Admin dashboard: '.$e->getMessage());
            $this->_render_empty_dashboard();
        }
    }

    private function _load_dashboard(): void {
        $vid = $this->current_vendor_id();
        $currency = sk_currency_symbol($this->Sk_Admin_model->get_settings());

        if ($vid) {
            $vendor = $this->Sk_Vendor_model->get_by_id($vid, false);
            $stats  = $this->Sk_Dashboard_model->vendor_stats($vid);
            $data['title']           = 'Vendor Dashboard';
            $data['is_vendor_view']  = true;
            $data['vendor']          = $vendor;
            $data['stats']           = $stats;
            $data['currency']        = $currency;
            $data['revenue_chart']   = $this->Sk_Dashboard_model->vendor_revenue_chart($vid, 30);
            $data['top_products']    = $this->Sk_Dashboard_model->vendor_top_products($vid, 8);
            $data['recent_orders']   = $this->Sk_Dashboard_model->vendor_recent_orders($vid, 8);
        } else {
            $stats = $this->Sk_Dashboard_model->platform_stats();
            $data['title']           = 'Dashboard - Talk AI Pilot Admin';
            $data['is_vendor_view']  = false;
            $data['stats']           = $stats;
            $data['currency']        = $currency;
            $data['total_orders']    = $stats['total_orders'];
            $data['pending_orders']  = $stats['pending_orders'];
            $data['total_revenue']   = $stats['total_revenue'];
            $data['monthly_revenue'] = $stats['monthly_revenue'];
            $data['total_products']  = $stats['total_products'];
            $data['total_customers']    = $stats['total_customers'];
            // Avg Order Value (simple average)
            $data['avg_order_value'] = $stats['total_orders'] ? $stats['total_revenue'] / $stats['total_orders'] : 0;
            // Active Sessions (last 15 minutes)
            $this->load->database();
            $this->db->where('timestamp >', time() - 900);
            $data['active_sessions'] = $this->db->count_all_results('ci_sessions');
            $data['recent_orders']   = $this->Sk_Order_model->recent_orders(8);
            $data['top_products']    = $this->Sk_Order_model->top_products(8);
            $data['revenue_chart']   = $this->Sk_Order_model->revenue_by_day(30);
            $data['vendor_counts']   = [
                'total'    => $stats['vendors'],
                'approved' => $stats['approved_vendors'],
                'pending'  => $stats['pending_vendors'],
            ];
            $data['new_customers']   = $this->Sk_Dashboard_model->new_customers_this_month();
            $data['refunds_total']   = $this->Sk_Dashboard_model->refunds_total();
            $data['status_counts']   = $this->Sk_Dashboard_model->order_status_counts(null);
            $data['category_sales']  = $this->Sk_Dashboard_model->sales_by_category(null, 6);
            $data['top_customers']   = $this->Sk_Dashboard_model->top_customers(null, 6);
        }

        $data['chart_pack'] = $this->Sk_Dashboard_model->chart_pack($vid ?: null);

        if ($vid) {
            $data['new_customers']  = 0;
            $data['refunds_total']  = 0;
            $data['status_counts']  = $this->Sk_Dashboard_model->order_status_counts($vid);
            $data['category_sales'] = $this->Sk_Dashboard_model->sales_by_category($vid, 6);
            $data['top_customers']  = $this->Sk_Dashboard_model->top_customers($vid, 6);
        }

        $this->render('dashboard', $data);
    }

    private function _render_empty_dashboard(): void {
        $currency = '₹';
        try {
            $currency = sk_currency_symbol($this->Sk_Admin_model->get_settings());
        } catch (Throwable $e) {
            // keep default
        }
        $this->render('dashboard', [
            'title'           => 'Dashboard - Talk AI Pilot Admin',
            'is_vendor_view'  => false,
            'currency'        => $currency,
            'total_orders'    => 0,
            'pending_orders'  => 0,
            'total_revenue'   => 0,
            'monthly_revenue' => 0,
            'total_products'  => 0,
            'total_customers' => 0,
            'recent_orders'   => [],
            'top_products'    => [],
            'revenue_chart'   => [],
            'vendor_counts'   => ['total' => 0, 'approved' => 0, 'pending' => 0],
            'new_customers'   => 0,
            'refunds_total'   => 0,
            'status_counts'   => [],
            'category_sales'  => [],
            'top_customers'   => [],
            'chart_pack'      => [
                'months' => [], 'revenue' => [], 'refunds' => [],
                'week_labels' => [], 'week_orders' => [], 'earnings_percent' => 0,
            ],
            'avg_order_value' => 0,
            'active_sessions' => 0,
        ]);
    }
}
