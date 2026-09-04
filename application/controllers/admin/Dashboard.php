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
            $data['top_products']    = $this->Sk_Dashboard_model->vendor_top_products($vid, 5);
            $data['recent_orders']   = $this->Sk_Dashboard_model->vendor_recent_orders($vid, 8);
        } else {
            $stats = $this->Sk_Dashboard_model->platform_stats();
            $data['title']           = 'Dashboard - 2DEAL Admin';
            $data['is_vendor_view']  = false;
            $data['stats']           = $stats;
            $data['currency']        = $currency;
            $data['total_orders']    = $stats['total_orders'];
            $data['pending_orders']  = $stats['pending_orders'];
            $data['total_revenue']   = $stats['total_revenue'];
            $data['monthly_revenue'] = $stats['monthly_revenue'];
            $data['total_products']  = $stats['total_products'];
            $data['total_customers'] = $stats['total_customers'];
            $data['recent_orders']   = $this->Sk_Order_model->recent_orders(8);
            $data['top_products']    = $this->Sk_Order_model->top_products(5);
            $data['revenue_chart']   = $this->Sk_Order_model->revenue_by_day(30);
            $data['vendor_counts']   = [
                'total'    => $stats['vendors'],
                'approved' => $stats['approved_vendors'],
                'pending'  => $stats['pending_vendors'],
            ];
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
            'title'           => 'Dashboard - 2DEAL Admin',
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
        ]);
    }
}
