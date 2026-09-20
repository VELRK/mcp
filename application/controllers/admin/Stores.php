<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/admin/Sk_Base.php';

class Stores extends Sk_Base {

    public function __construct() {
        parent::__construct();
        $this->load->model(['Sk_Store_model', 'Sk_Vendor_model', 'Sk_Vendor_whatsapp_account_model']);
    }

    public function edit($vendor_id = null) {
        $this->load->helper('sk_invoice');
        sk_invoice_ensure_vendor_schema();
        $vendor_id = $this->_resolve_vendor_id($vendor_id);
        $vendor = $this->Sk_Vendor_model->get_by_id($vendor_id, false);
        if (!$vendor) show_404();

        $store = $this->Sk_Store_model->get_by_vendor($vendor_id);
        if (!$store) {
            $store = ['store_name' => $vendor['business_name'], 'store_timings' => $this->Sk_Store_model->default_timings(), 'social_links' => [], 'holiday_settings' => [], 'delivery_settings' => []];
        }

        $data['title']  = 'Store Settings — ' . $vendor['business_name'];
        $data['vendor'] = $vendor;
        $data['store']  = $store;
        $data['days']   = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
        $this->render('stores/edit', $data);
    }

    public function update($vendor_id = null) {
        $this->load->helper('sk_invoice');
        sk_invoice_ensure_vendor_schema();
        $vendor_id = $this->_resolve_vendor_id($vendor_id);
        $old = $this->Sk_Store_model->get_by_vendor($vendor_id);
        if (!$this->Sk_Vendor_model->get_by_id($vendor_id, false)) show_404();

        $logo   = $this->upload_file('logo', 'vendors');
        $banner = $this->upload_file('banner', 'vendors');

        $timings = [];
        foreach (['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day) {
            $timings[$day] = [
                'open'   => $this->input->post("timing_{$day}_open") ?: '09:00',
                'close'  => $this->input->post("timing_{$day}_close") ?: '18:00',
                'closed' => $this->input->post("timing_{$day}_closed") ? true : false,
            ];
        }

        $holidays = array_filter(array_map('trim', explode("\n", $this->input->post('holiday_dates') ?: '')));

        $social = [
            'facebook'  => $this->input->post('social_facebook', TRUE),
            'instagram' => $this->input->post('social_instagram', TRUE),
            'twitter'   => $this->input->post('social_twitter', TRUE),
            'youtube'   => $this->input->post('social_youtube', TRUE),
            'website'   => $this->input->post('social_website', TRUE),
        ];

        $delivery = [
            'free_shipping_above' => $this->input->post('free_shipping_above') ?: null,
            'flat_rate'           => $this->input->post('flat_rate') ?: null,
            'processing_days'     => (int)($this->input->post('processing_days') ?: 2),
            'cod_enabled'         => $this->input->post('cod_enabled') ? 1 : 0,
        ];

        $store_data = [
            'store_name'        => $this->input->post('store_name', TRUE),
            'description'     => $this->input->post('description'),
            'gst_vat'           => $this->input->post('gst_vat', TRUE),
            'pan_no'            => $this->input->post('pan_no', TRUE),
            'state_code'        => $this->input->post('state_code', TRUE),
            'invoice_prefix'    => $this->input->post('invoice_prefix', TRUE) ?: 'INV',
            'invoice_footer'    => $this->input->post('invoice_footer'),
            'business_reg_no' => $this->input->post('business_reg_no', TRUE),
            'contact_email'   => $this->input->post('contact_email', TRUE),
            'contact_phone'   => $this->input->post('contact_phone', TRUE),
            'pickup_line1'    => $this->input->post('pickup_line1', TRUE),
            'pickup_line2'    => $this->input->post('pickup_line2', TRUE),
            'pickup_city'     => $this->input->post('pickup_city', TRUE),
            'pickup_state'    => $this->input->post('pickup_state', TRUE),
            'pickup_pincode'  => $this->input->post('pickup_pincode', TRUE),
            'pickup_country'  => $this->input->post('pickup_country', TRUE) ?: 'India',
            'meta_title'      => $this->input->post('meta_title', TRUE),
            'meta_desc'       => $this->input->post('meta_desc', TRUE),
            'store_timings'     => $timings,
            'holiday_settings'  => ['dates' => $holidays],
            'social_links'      => $social,
            'delivery_settings' => $delivery,
        ];
        if ($logo)   $store_data['logo'] = $logo;
        if ($banner) $store_data['banner'] = $banner;

        $wa_phone_number_id = trim((string)$this->input->post('wa_phone_number_id', TRUE));
        $wa_waba_id = trim((string)$this->input->post('wa_waba_id', TRUE));
        $wa_display_phone = trim((string)$this->input->post('wa_display_phone', TRUE));
        $wa_business_id = trim((string)$this->input->post('wa_business_id', TRUE));

        $this->Sk_Store_model->update_store($vendor_id, $store_data);
        if ($wa_phone_number_id !== '' || $wa_waba_id !== '') {
            $this->Sk_Vendor_whatsapp_account_model->save_for_vendor($vendor_id, [
                'phone_number_id' => $wa_phone_number_id,
                'waba_id' => $wa_waba_id,
                'display_phone' => $wa_display_phone,
                'business_id' => $wa_business_id,
                'status' => 'active',
                'is_default' => 1,
            ]);
        }
        $this->activity_log->log_admin('stores', 'update', $vendor_id, $old, $store_data, $vendor_id);

        $this->session->set_flashdata('success', 'Store settings saved.');
        redirect('admin/stores/edit/' . $vendor_id);
    }

    protected function _resolve_vendor_id($vendor_id): int {
        if ($this->current_vendor_id()) {
            return $this->current_vendor_id();
        }
        if (!$vendor_id) {
            show_error('Vendor ID required.', 400);
        }
        return (int)$vendor_id;
    }
}
