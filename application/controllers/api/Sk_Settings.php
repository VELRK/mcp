<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/api/Sk_Base_Api.php';

class Sk_Settings extends Sk_Base_Api {

    public function index() {
        $cached = $this->get_cache('site_settings_v2', 300);
        if ($cached !== null) return $this->success($cached);

        $rows = $this->db->where_in('key', [
            'newsletter_popup_enabled', 'site_name', 'currency_symbol', 'currency_code',
            'top_bar_enabled', 'top_bar_text', 'whatsapp_enabled', 'whatsapp_number',
            'tax_rate', 'shipping_charge', 'free_shipping_above',
            'meta_title', 'meta_desc', 'meta_keywords', 'seo_og_image',
            'head_scripts', 'footer_scripts', 'google_analytics',
        ])->get('settings')->result_array();

        $map = [];
        foreach ($rows as $r) $map[$r['key']] = $r['value'];

        $map['newsletter_popup_enabled'] = isset($map['newsletter_popup_enabled'])
            ? (bool)(int)$map['newsletter_popup_enabled'] : true;
        $map['top_bar_enabled'] = isset($map['top_bar_enabled'])
            ? (bool)(int)$map['top_bar_enabled'] : true;
        $map['whatsapp_enabled'] = isset($map['whatsapp_enabled'])
            ? (bool)(int)$map['whatsapp_enabled'] : false;

        $map['tax_rate']            = isset($map['tax_rate']) ? (float)$map['tax_rate'] : 0;
        $map['shipping_charge']     = isset($map['shipping_charge']) ? (float)$map['shipping_charge'] : 50;
        $map['free_shipping_above'] = isset($map['free_shipping_above']) ? (float)$map['free_shipping_above'] : 999;
        $map['currency_symbol']     = sk_currency_symbol($map);
        $map['currency_code']       = sk_currency_code($map);

        $map['meta_description'] = $map['meta_desc'] ?? '';
        if (!empty($map['seo_og_image']) && !preg_match('#^https?://#i', $map['seo_og_image'])) {
            $map['seo_og_image'] = rtrim(base_url(), '/') . '/' . ltrim($map['seo_og_image'], '/');
        }

        $this->set_cache('site_settings_v2', $map);
        $this->success($map);
    }
}
