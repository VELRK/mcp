<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/admin/Sk_Base.php';

class Dashboard extends Sk_Base {

    public function __construct() {
        parent::__construct();
        $this->load->model('Sk_Dashboard_model');
    }

    public function index() {
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
}
