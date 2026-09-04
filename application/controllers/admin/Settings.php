<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/admin/Sk_Base.php';

class Settings extends Sk_Base {

    public function index() {
        $this->load->helper(['sk_invoice', 'sk_isms', 'sk_whatsapp', 'sk_whatsapp_cloud']);
        sk_invoice_ensure_vendor_schema();
        sk_isms_ensure_schema();
        sk_whatsapp_ensure_settings();
        sk_wa_cloud_ensure_schema();
        $data['title']    = 'Settings - 2DEAL Admin';
        $data['settings'] = $this->Sk_Admin_model->get_settings();
        $this->render('settings/index', $data);
    }

    public function update() {
        if (strtoupper((string)$this->input->server('REQUEST_METHOD')) !== 'POST') {
            redirect('admin/settings');
            return;
        }
        $this->load->helper(['sk_isms', 'sk_whatsapp', 'sk_whatsapp_cloud']);
        sk_whatsapp_ensure_settings();
        sk_wa_cloud_ensure_schema();
        $fields = [
            'site_name', 'site_email', 'site_phone', 'site_address',
            'currency', 'currency_symbol', 'currency_code', 'tax_rate', 'shipping_charge',
            'free_shipping_above', 'razorpay_key_id', 'razorpay_key_secret',
            'razorpay_webhook_secret', 'razorpay_mode', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass',
            'smtp_from_name', 'admin_email', 'meta_title', 'meta_desc', 'meta_keywords', 'seo_og_image',
            'head_scripts', 'footer_scripts', 'google_analytics', 'top_bar_text',
            'whatsapp_number',
            'askeva_api_url', 'askeva_api_token', 'askeva_order_template', 'askeva_template_lang',
            'wa_cloud_phone_number_id', 'wa_cloud_waba_id', 'wa_cloud_access_token',
            'wa_cloud_app_id', 'wa_cloud_config_id',
            'wa_cloud_app_secret', 'wa_cloud_verify_token', 'wa_cloud_api_version',
            'wa_mcp_url', 'wa_mcp_token', 'wa_mcp_timeout',
            'company_legal_name', 'gstin', 'pan_no', 'state_code', 'invoice_prefix', 'invoice_footer',
            'isms_username', 'isms_password', 'isms_api_key', 'isms_sender_id', 'isms_message',
            'isms_country_code', 'isms_otp_interval', 'isms_test_otp', 'isms_test_phone',
        ];
        $raw_fields = [
            'isms_password', 'isms_api_key', 'smtp_pass', 'razorpay_key_secret',
            'razorpay_webhook_secret',
            'askeva_api_token', 'wa_cloud_access_token', 'wa_cloud_app_secret',
            'wa_mcp_token',
        ];
        $preserve_if_empty = $raw_fields;

        $data = [];
        foreach ($fields as $f) {
            $val = in_array($f, $raw_fields, true)
                ? $this->input->post($f, FALSE)
                : $this->input->post($f, TRUE);
            if ($val === null) {
                continue;
            }
            if (in_array($f, $preserve_if_empty, true) && trim((string) $val) === '') {
                continue;
            }
            if ($f === 'isms_username') {
                $data[$f] = sk_isms_clean_credential($val);
                continue;
            }
            if ($f === 'isms_password' || $f === 'isms_api_key') {
                $data[$f] = sk_isms_clean_credential($val, false);
                continue;
            }
            $data[$f] = is_string($val) ? trim($val) : $val;
        }
        if (isset($data['currency_code'])) {
            $data['currency_code'] = strtoupper(preg_replace('/[^A-Za-z]/', '', $data['currency_code']) ?: 'INR');
        }
        $data['payment_gateway'] = 'razorpay';
        // Checkbox: absent when unchecked, present with value "1" when checked
        $data['newsletter_popup_enabled'] = $this->input->post('newsletter_popup_enabled') ? '1' : '0';
        $data['top_bar_enabled'] = $this->input->post('top_bar_enabled') ? '1' : '0';
        $data['whatsapp_enabled'] = $this->input->post('whatsapp_enabled') ? '1' : '0';
        $data['askeva_whatsapp_enabled'] = $this->input->post('askeva_whatsapp_enabled') ? '1' : '0';
        $data['isms_enabled'] = $this->input->post('isms_enabled') ? '1' : '0';
        $settingsTab = trim((string)$this->input->post('settings_tab'));
        if ($settingsTab === 'wacloud' || $this->input->post('wa_cloud_phone_number_id') !== null) {
            $data['wa_cloud_enabled'] = $this->input->post('wa_cloud_enabled') ? '1' : '0';
            $data['wa_mcp_enabled'] = $this->input->post('wa_mcp_enabled') ? '1' : '0';
        }

        // Always persist Askeva text fields when present (including empty template).
        foreach (['askeva_api_url', 'askeva_order_template', 'askeva_template_lang'] as $askevaField) {
            $posted = $this->input->post($askevaField, TRUE);
            if ($posted !== null) {
                $data[$askevaField] = trim((string) $posted);
            }
        }
        $tokenPosted = $this->input->post('askeva_api_token', FALSE);
        if ($tokenPosted !== null && trim((string) $tokenPosted) !== '') {
            $data['askeva_api_token'] = trim((string) $tokenPosted);
        }

        // Persist phone/email/address from POST (also read raw $_POST in case XSS filter drops value).
        foreach (['site_email', 'site_phone', 'site_address'] as $contactField) {
            $posted = $this->input->post($contactField, FALSE);
            if ($posted === null && array_key_exists($contactField, $_POST)) {
                $posted = $_POST[$contactField];
            }
            if ($posted !== null) {
                $data[$contactField] = trim(is_string($posted) ? $posted : (string) $posted);
            }
        }
        // User edited contact — do not let one-time letterhead seed overwrite later.
        if (isset($data['site_phone']) || isset($data['site_email']) || isset($data['site_address'])) {
            $data['golden_letterhead_seeded'] = '1';
        }

        // logo upload
        $logo = $this->upload_file('site_logo', 'settings');
        if ($logo) $data['site_logo'] = $logo;

        $og = $this->upload_file('seo_og_image_file', 'seo');
        if ($og) $data['seo_og_image'] = $og;

        $this->Sk_Admin_model->save_settings($data);
        // Bust storefront settings/SEO API cache so site_name updates immediately
        $cacheDir = APPPATH . 'cache/api/';
        foreach (['site_settings', 'site_settings_v2', 'seo_global'] as $cacheKey) {
            $file = $cacheDir . preg_replace('/[^a-z0-9_-]/', '_', strtolower($cacheKey)) . '.json';
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $phoneSaved = trim((string)($data['site_phone'] ?? ''));
        $msg = 'Settings saved successfully.';
        if ($phoneSaved !== '') {
            $msg .= ' Phone: ' . $phoneSaved;
        }
        $this->session->set_flashdata('success', $msg);
        $tab = trim((string)$this->input->post('settings_tab'));
        if ($tab !== '' && preg_match('/^[a-z0-9_-]+$/i', $tab)) {
            redirect('admin/settings?tab=' . rawurlencode($tab));
            return;
        }
        redirect('admin/settings');
    }

    public function test_isms() {
        $this->load->helper('sk_isms');
        sk_isms_ensure_schema();

        $settings = $this->Sk_Admin_model->get_settings();
        $posted_user = sk_isms_clean_credential($this->input->post('isms_username', FALSE));
        $posted_pass = sk_isms_clean_credential($this->input->post('isms_password', FALSE), false);
        $posted_key  = sk_isms_clean_credential($this->input->post('isms_api_key', FALSE), false);

        $save = [];
        if ($posted_user !== '') {
            $save['isms_username'] = $posted_user;
            $settings['isms_username'] = $posted_user;
        }
        if ($posted_pass !== '') {
            $save['isms_password'] = $posted_pass;
            $settings['isms_password'] = $posted_pass;
        }
        if ($posted_key !== '') {
            $save['isms_api_key'] = $posted_key;
            $settings['isms_api_key'] = $posted_key;
        }
        if (!empty($save)) {
            $this->Sk_Admin_model->save_settings($save);
        }

        $this->load->library('isms', $settings);
        $diag = $this->isms->credential_diagnostics();
        $result = $this->isms->check_balance(false);

        if ($result['success']) {
            $this->session->set_flashdata('success', 'iSMS connection OK. ' . $result['message']);
        } else {
            $details = [];
            if (!$diag['secret_saved']) {
                $details[] = 'No password or API key saved — re-enter credentials and click Test again.';
            }
            if ($diag['looks_like_email']) {
                $details[] = 'Username looks like an email. iSMS API needs your account username from the portal profile (e.g. 2Deal), not your email.';
            }
            if ($diag['secret_saved']) {
                $details[] = 'Stored username: ' . sk_isms_mask_username($diag['username'])
                    . ' (length ' . strlen($diag['username']) . '), password length: ' . $diag['password_len']
                    . ', API key length: ' . $diag['api_key_len'] . '.';
            }
            $hint = sk_isms_auth_failure_hint($result);
            if ($hint !== '') {
                $details[] = $hint;
            }
            $msg = 'iSMS test failed: ' . $result['message'];
            if (!empty($details)) {
                $msg .= ' ' . implode(' ', $details);
            }
            $this->session->set_flashdata('error', $msg);
        }
        redirect('admin/settings?tab=sms');
    }

    public function test_smtp() {
        $settings = $this->Sk_Admin_model->get_settings();
        $posted = [
            'smtp_host'      => trim($this->input->post('smtp_host', TRUE) ?? ''),
            'smtp_port'      => trim($this->input->post('smtp_port', TRUE) ?? ''),
            'smtp_user'      => trim($this->input->post('smtp_user', TRUE) ?? ''),
            'smtp_pass'      => $this->input->post('smtp_pass', FALSE),
            'smtp_from_name' => trim($this->input->post('smtp_from_name', TRUE) ?? ''),
            'site_email'     => trim($this->input->post('site_email', TRUE) ?? ''),
            'admin_email'    => trim($this->input->post('admin_email', TRUE) ?? ''),
        ];
        foreach ($posted as $key => $val) {
            if ($key === 'smtp_pass') {
                if (trim((string)$val) !== '') {
                    $settings[$key] = $val;
                }
                continue;
            }
            if ($val !== '') {
                $settings[$key] = $val;
            }
        }

        $this->load->helper('sk_mailer');
        $status = sk_mailer_config_status($settings);
        if (!$status['ok']) {
            $this->session->set_flashdata('error', 'SMTP not ready: ' . implode(' ', $status['issues']));
            redirect('admin/settings?tab=email');
        }

        $to = trim($settings['admin_email'] ?? '') ?: trim($settings['site_email'] ?? $this->admin['email'] ?? '');
        $site = $settings['site_name'] ?? '2DEAL';
        $sent = sk_send_mail(
            $to,
            $this->admin['name'] ?? 'Admin',
            'SMTP test – ' . $site,
            '<p>This is a test email from ' . htmlspecialchars($site) . ' at ' . date('Y-m-d H:i:s') . '.</p><p>Admin inbox: ' . htmlspecialchars(sk_mailer_notify_email($settings)) . '</p>'
        );

        if ($sent) {
            $this->session->set_flashdata('success', 'SMTP test sent to ' . $to . '. Check inbox and spam folder.');
        } else {
            $detail = sk_mailer_last_error();
            $this->session->set_flashdata('error', 'SMTP test failed.' . ($detail ? ' ' . $detail : ''));
        }
        redirect('admin/settings?tab=email');
    }

    public function save_isms() {
        $this->load->helper('sk_isms');
        sk_isms_ensure_schema();

        $username = sk_isms_clean_credential($this->input->post('isms_username', FALSE));
        $password = sk_isms_clean_credential($this->input->post('isms_password', FALSE), false);
        $api_key  = sk_isms_clean_credential($this->input->post('isms_api_key', FALSE), false);
        if ($username === '') {
            $this->session->set_flashdata('error', 'Enter iSMS username, then save credentials.');
            redirect('admin/settings?tab=sms');
        }
        $existing = $this->Sk_Admin_model->get_settings();
        $hasSecret = $password !== '' || $api_key !== ''
            || trim($existing['isms_password'] ?? '') !== ''
            || trim($existing['isms_api_key'] ?? '') !== '';
        if (!$hasSecret) {
            $this->session->set_flashdata('error', 'Enter either portal password or API key, then save credentials.');
            redirect('admin/settings?tab=sms');
        }

        $data = [
            'isms_username' => $username,
            'isms_enabled'  => $this->input->post('isms_enabled') ? '1' : '0',
        ];
        if ($password !== '') {
            $data['isms_password'] = $password;
        }
        if ($api_key !== '') {
            $data['isms_api_key'] = $api_key;
        }
        foreach (['isms_sender_id', 'isms_message', 'isms_country_code', 'isms_otp_interval', 'isms_test_otp', 'isms_test_phone'] as $field) {
            $val = $this->input->post($field, $field === 'isms_message' ? FALSE : TRUE);
            if ($val !== null && $val !== '') {
                $data[$field] = is_string($val) ? trim($val) : $val;
            }
        }

        $this->Sk_Admin_model->save_settings($data);
        $this->session->set_flashdata('success', 'iSMS credentials saved. Click "Test iSMS connection" to verify.');
        redirect('admin/settings?tab=sms');
    }
}
