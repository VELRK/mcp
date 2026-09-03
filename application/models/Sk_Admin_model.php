<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sk_Admin_model extends CI_Model {

    public function get_by_email($email) {
        return $this->db->where('email', $email)->where('status', 1)->get('admins')->row_array();
    }

    public function get_by_id($id) {
        return $this->db->where('id', $id)->get('admins')->row_array();
    }

    public function verify_password($plain, $hash) {
        return password_verify($plain, $hash);
    }

    public function update_last_login($id) {
        $this->db->where('id', $id)->update('admins', ['last_login' => date('Y-m-d H:i:s')]);
    }

    public function update($id, $data) {
        if (!empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        $this->db->where('id', $id)->update('admins', $data);
    }

    // Settings
    public function get_settings($group = null) {
        if ($group) $this->db->where('group', $group);
        $rows = $this->db->get('settings')->result_array();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }
        $this->_ensure_brand_name($settings);
        $this->_ensure_currency_symbol($settings);
        $this->_ensure_invoice_letterhead($settings);
        return $settings;
    }

    /**
     * One-time migration of legacy ShopKart / India demo contact details.
     * After golden_letterhead_seeded=1, never auto-overwrite phone/email/address again.
     */
    private function _ensure_invoice_letterhead(array &$settings): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        if (($settings['golden_letterhead_seeded'] ?? '') === '1') {
            return;
        }

        $defaults = [
            'company_legal_name' => 'GOLDEN 2 DEAL (M) SDN. BHD.',
            'gstin'              => '202101029427',
            'pan_no'             => '1429727-A',
            'site_email'         => 'golden2deal@gmail.com',
            'site_phone'         => '03-6242 2232',
            'site_address'       => "Lot No. 2A/9(B) Anzen Business Park, No 3-9, Jalan 4/37A , Kawasan Industri\nTaman Bukit Maluri, 52100 Kepong Kuala Lumpur.",
        ];

        $patch = ['golden_letterhead_seeded' => '1'];

        $legal = trim((string)($settings['company_legal_name'] ?? ''));
        if ($legal === '' || strcasecmp($legal, '2DEAL') === 0 || stripos($legal, 'shopkart') !== false) {
            $patch['company_legal_name'] = $defaults['company_legal_name'];
        }

        foreach (['gstin', 'pan_no'] as $k) {
            if (trim((string)($settings[$k] ?? '')) === '') {
                $patch[$k] = $defaults[$k];
            }
        }

        $email = strtolower(trim((string)($settings['site_email'] ?? '')));
        if ($email === '' || $this->_is_placeholder_email($email)) {
            $patch['site_email'] = $defaults['site_email'];
        }

        $phone = trim((string)($settings['site_phone'] ?? ''));
        if ($phone === '' || $this->_is_placeholder_phone($phone)) {
            $patch['site_phone'] = $defaults['site_phone'];
        }

        $address = trim((string)($settings['site_address'] ?? ''));
        if ($address === '' || $this->_is_placeholder_address($address)) {
            $patch['site_address'] = $defaults['site_address'];
        }

        $this->save_settings($patch);
        foreach ($patch as $k => $v) {
            $settings[$k] = $v;
        }
    }

    private function _is_placeholder_email(string $email): bool {
        if ($email === '') {
            return true;
        }
        foreach (['shopkart.com', 'shopkart.app', 'example.com', 'example.org', 'test.com'] as $domain) {
            if (substr($email, -strlen('@' . $domain)) === '@' . $domain) {
                return true;
            }
        }
        return false;
    }

    private function _is_placeholder_phone(string $phone): bool {
        $digits = preg_replace('/\D+/', '', $phone);
        // Only known India demo numbers — do not treat real MY phones as placeholders.
        return $digits === '' || $digits === '9876543210' || $digits === '919876543210';
    }

    private function _is_placeholder_address(string $address): bool {
        $a = strtolower($address);
        return $a === ''
            || strpos($a, 'mumbai') !== false
            || strpos($a, '123 main street') !== false;
    }

    /** Persist Indian Rupee from settings when empty or leftover RM/MYR. */
    private function _ensure_currency_symbol(array &$settings): void {
        static $done = false;
        $sym = trim((string)($settings['currency_symbol'] ?? ''));
        $code = strtoupper(trim((string)($settings['currency_code'] ?? '')));
        $patch = [];

        if ($sym === '' || strcasecmp($sym, 'RM') === 0 || strcasecmp($sym, 'MYR') === 0) {
            $patch['currency_symbol'] = '₹';
            $settings['currency_symbol'] = '₹';
        }
        if ($code === '' || $code === 'MYR' || $code === 'RM') {
            $patch['currency_code'] = 'INR';
            $settings['currency_code'] = 'INR';
        }
        if ($patch && !$done) {
            $done = true;
            $this->save_settings($patch);
            return;
        }
        $done = true;
    }

    /**
     * Rename legacy ShopKart / ShopKart Sarees labels to 2DEAL in settings DB
     * so email, WhatsApp, invoices, and storefront all use the new brand.
     */
    private function _ensure_brand_name(array &$settings): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $keys = ['site_name', 'smtp_from_name', 'meta_title', 'company_legal_name'];
        foreach ($keys as $key) {
            $val = trim((string)($settings[$key] ?? ''));
            if ($val === '') {
                if ($key === 'site_name' || $key === 'smtp_from_name') {
                    $this->save_settings([$key => '2DEAL']);
                    $settings[$key] = '2DEAL';
                }
                continue;
            }
            if (stripos($val, 'shopkart') === false) {
                continue;
            }
            // Exact old brand names
            if (preg_match('/^shopkart(\s+sarees)?$/i', $val)) {
                $new = '2DEAL';
            } else {
                // Keep surrounding copy, swap brand token
                $new = preg_replace('/shopkart\s+sarees/i', '2DEAL', $val);
                $new = preg_replace('/shopkart/i', '2DEAL', $new);
            }
            if ($new !== $val) {
                $this->save_settings([$key => $new]);
                $settings[$key] = $new;
            }
        }
    }

    public function get_setting($key) {
        $row = $this->db->where('key', $key)->get('settings')->row_array();
        return $row['value'] ?? null;
    }

    public function save_settings($data) {
        $hasGroup = $this->db->field_exists('group', 'settings');
        $hasUpdated = $this->db->field_exists('updated_at', 'settings');
        foreach ($data as $key => $value) {
            $key = (string) $key;
            $value = is_scalar($value) || $value === null ? (string) $value : json_encode($value);

            // Update by id and remove duplicate keys so phone cannot stick on an old row.
            $rows = $this->db->where('key', $key)->order_by('id', 'ASC')->get('settings')->result_array();
            if (!empty($rows)) {
                $update = ['value' => $value];
                if ($hasUpdated) {
                    $update['updated_at'] = date('Y-m-d H:i:s');
                }
                $this->db->where('id', (int)$rows[0]['id'])->update('settings', $update);
                if (count($rows) > 1) {
                    $dupIds = [];
                    for ($i = 1; $i < count($rows); $i++) {
                        $dupIds[] = (int)$rows[$i]['id'];
                    }
                    if ($dupIds) {
                        $this->db->where_in('id', $dupIds)->delete('settings');
                    }
                }
                continue;
            }

            $row = ['key' => $key, 'value' => $value];
            if ($hasGroup) {
                if (strpos($key, 'askeva_') === 0 || strpos($key, 'wa_cloud_') === 0) {
                    $row['group'] = 'whatsapp';
                } elseif (strpos($key, 'isms_') === 0) {
                    $row['group'] = 'sms';
                } else {
                    $row['group'] = 'general';
                }
            }
            if ($hasUpdated) {
                $row['updated_at'] = date('Y-m-d H:i:s');
            }
            $this->db->insert('settings', $row);
        }
    }

    // ── Categories ────────────────────────────────────────────
    public function get_categories($status = null) {
        if ($status !== null) $this->db->where('status', $status);
        return $this->db
            ->order_by('CASE WHEN sort_order = 0 THEN 999999 ELSE sort_order END ASC, name ASC', '', false)
            ->get('categories')->result_array();
    }

    public function get_category($id) {
        return $this->db->where('id', $id)->get('categories')->row_array();
    }

    public function save_category($data) {
        $data['slug'] = $this->make_unique_slug($data['name'], 'categories', $data['id'] ?? null);
        if (!empty($data['id'])) {
            $id = $data['id']; unset($data['id']);
            $this->db->where('id', $id)->update('categories', $data);
            return $id;
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('categories', $data);
        return $this->db->insert_id();
    }

    public function delete_category($id) {
        $this->db->where('id', $id)->delete('categories');
    }

    // ── Category Nav Products (navbar product cards) ─────────
    public function get_category_nav_products($category_id) {
        return $this->db
            ->select('cnp.product_id, cnp.sort_order, p.name, p.slug, p.thumbnail, p.price, p.sale_price')
            ->from('category_nav_products cnp')
            ->join('products p', 'p.id = cnp.product_id', 'left')
            ->where('cnp.category_id', $category_id)
            ->where('p.status', 'active')
            ->order_by('cnp.sort_order, cnp.id')
            ->get()->result_array();
    }

    public function save_category_nav_products($category_id, array $product_ids) {
        $this->db->where('category_id', $category_id)->delete('category_nav_products');
        foreach (array_values($product_ids) as $i => $pid) {
            $pid = (int)$pid;
            if (!$pid) continue;
            $this->db->insert('category_nav_products', [
                'category_id' => $category_id,
                'product_id'  => $pid,
                'sort_order'  => $i,
            ]);
        }
    }

    // ── Subcategories ─────────────────────────────────────────
    public function get_subcategories($category_id = null, $status = null) {
        if ($category_id !== null) $this->db->where('category_id', $category_id);
        if ($status !== null)      $this->db->where('status', $status);
        return $this->db
            ->order_by('CASE WHEN sort_order = 0 THEN 999999 ELSE sort_order END ASC, name ASC', '', false)
            ->get('subcategories')->result_array();
    }

    public function get_subcategory($id) {
        return $this->db->where('id', $id)->get('subcategories')->row_array();
    }

    public function save_subcategory($data) {
        $data['slug'] = $this->make_unique_slug($data['name'], 'subcategories', $data['id'] ?? null);
        if (!empty($data['id'])) {
            $id = $data['id']; unset($data['id']);
            $this->db->where('id', $id)->update('subcategories', $data);
            return $id;
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('subcategories', $data);
        return $this->db->insert_id();
    }

    public function delete_subcategory($id) {
        // Detach products first so a FK/RESTRICT constraint cannot block the delete.
        if ($this->db->field_exists('subcategory_id', 'products')) {
            $this->db->where('subcategory_id', $id)->update('products', ['subcategory_id' => null]);
        }
        $this->db->where('id', $id)->delete('subcategories');
        return $this->db->affected_rows() > 0;
    }

    // ── Mega Menu Titles ──────────────────────────────────────
    public function get_mega_menu_titles() {
        return $this->db->order_by('sort_order, title')->get('mega_menu_titles')->result_array();
    }

    public function save_mega_menu_title($data) {
        if (!empty($data['id'])) {
            $id = $data['id']; unset($data['id']);
            $this->db->where('id', $id)->update('mega_menu_titles', $data);
            return $id;
        }
        $this->db->insert('mega_menu_titles', $data);
        return $this->db->insert_id();
    }

    public function delete_mega_menu_title($id) {
        $this->db->where('id', $id)->delete('mega_menu_titles');
    }

    private function make_unique_slug($name, $table, $exclude_id = null) {
        $slug = url_title(strtolower($name), '-', TRUE);
        $base = $slug;
        $i = 1;
        while (TRUE) {
            $this->db->where('slug', $slug);
            if ($exclude_id) $this->db->where('id !=', $exclude_id);
            if ($this->db->count_all_results($table) === 0) break;
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    // Saree-specific lookups
    public function get_saree_styles() {
        return $this->db->where('status', 1)->order_by('name')->get('saree_styles')->result_array();
    }

    public static function fabric_options() {
        return ['Silk','Cotton','Chiffon','Georgette','Linen','Crepe','Net','Organza',
                'Satin','Velvet','Tussar Silk','Art Silk','Banarasi Silk','Kanjivaram Silk',
                'Raw Silk','Handloom Cotton','Khadi','Chanderi Silk','Modal','Poly Silk'];
    }

    public static function occasion_options() {
        return ['Wedding','Bridal','Festival','Party','Casual','Office / Formal',
                'Puja / Religious','Anniversary','Reception','Mehendi','Sangeet'];
    }

    public static function work_type_options() {
        return ['Zari Work','Embroidery','Printed','Plain','Woven','Sequence / Sequin',
                'Block Print','Kalamkari','Batik','Bandhani / Tie-Dye','Digital Print',
                'Handpainted','Mirror Work','Cutwork','Thread Work','Stone Work'];
    }

    public static function wash_care_options() {
        return ['Dry Clean Only','Hand Wash Cold','Machine Wash Gentle','Dry in Shade',
                'Do Not Bleach','Iron on Low Heat'];
    }

    public static function origin_states() {
        return ['Uttar Pradesh (Varanasi)','Tamil Nadu','Gujarat','Maharashtra','Karnataka',
                'West Bengal','Odisha','Rajasthan','Andhra Pradesh','Telangana',
                'Madhya Pradesh','Kerala','Punjab','Assam','Bihar'];
    }
}
