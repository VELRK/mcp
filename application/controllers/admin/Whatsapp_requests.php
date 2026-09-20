<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/admin/Sk_Base.php';

class Whatsapp_requests extends Sk_Base {

    public function __construct() {
        parent::__construct();
        $this->load->model('Sk_Wa_Provision_request_model');
        $this->load->model('Sk_Vendor_whatsapp_account_model');
        $this->load->helper('sk_whatsapp_cloud');
        sk_wa_cloud_ensure_schema();
    }

    // Vendor view: list requests and show form to create
    public function index() {
        $vid = $this->current_vendor_id();
        if (!$vid) {
            show_error('Only vendors may request WhatsApp numbers.', 403);
            return;
        }
        $requests = $this->Sk_Wa_Provision_request_model->get_for_vendor($vid);
        $data['title'] = 'WhatsApp Number Requests';
        $data['requests'] = $requests;
        $this->render('whatsapp_requests/index', $data);
    }

    public function submit() {
        $vid = $this->current_vendor_id();
        if (!$vid) {
            show_error('Only vendors may request WhatsApp numbers.', 403);
            return;
        }
        $phone = trim((string)$this->input->post('phone_number_id', TRUE) ?: '');
        $display = trim((string)$this->input->post('display_phone', TRUE) ?: '');
        $waba = trim((string)$this->input->post('waba_id', TRUE) ?: '');
        $business = trim((string)$this->input->post('business_id', TRUE) ?: '');
        $note = trim((string)$this->input->post('note', TRUE) ?: '');

        if ($phone === '' && $display === '') {
            $this->session->set_flashdata('error', 'Provide at least the phone number or display phone.');
            redirect('admin/whatsapp_requests');
            return;
        }

        $res = $this->Sk_Wa_Provision_request_model->create($vid, [
            'phone_number_id' => $phone,
            'display_phone' => $display,
            'waba_id' => $waba,
            'business_id' => $business,
            'note' => $note,
        ]);

        if (!empty($res['ok'])) {
            $this->activity_log->log_admin('wa_requests', 'create', $res['id'], null, ['vendor_id' => $vid]);
            $this->session->set_flashdata('success', 'WhatsApp provisioning request created. Admin will review shortly.');
        } else {
            $this->session->set_flashdata('error', 'Could not create request.');
        }
        redirect('admin/whatsapp_requests');
    }

    // Admin: pending requests + inactive numbers + per-row Embedded Login
    public function pending() {
        if (!$this->is_super_admin()) {
            show_error('Admin only', 403);
        }
        $rows = $this->Sk_Wa_Provision_request_model->get_pending();
        foreach ($rows as &$r) {
            $vendor = $this->Sk_Vendor_model->get_by_id((int)$r['vendor_id'], false);
            $r['vendor_name'] = $vendor
                ? (trim((string)($vendor['business_name'] ?? $vendor['owner_name'] ?? '')) ?: 'Vendor #' . (int)$r['vendor_id'])
                : 'Vendor #' . (int)$r['vendor_id'];
            $r['vendor_email'] = $vendor['email'] ?? '';
        }
        unset($r);

        $accounts = $this->Sk_Vendor_whatsapp_account_model->list_all(null, 200);
        foreach ($accounts as &$a) {
            $vendor = $this->Sk_Vendor_model->get_by_id((int)$a['vendor_id'], false);
            $a['vendor_name'] = $vendor
                ? (trim((string)($vendor['business_name'] ?? $vendor['owner_name'] ?? '')) ?: 'Vendor #' . (int)$a['vendor_id'])
                : 'Vendor #' . (int)$a['vendor_id'];
        }
        unset($a);

        $settings = $this->Sk_Admin_model->get_settings();
        $cfg = sk_wa_cloud_config($settings);
        $state = bin2hex(random_bytes(16));
        $this->session->set_userdata('wa_meta_oauth_state', $state);

        $selected = (int)$this->input->get('request_id');
        if ($selected < 1 && count($rows) === 1) {
            $selected = (int)$rows[0]['id'];
        }
        if ($selected > 0) {
            $this->session->set_userdata('wa_provision_request_id', $selected);
        }

        $data['title'] = 'WhatsApp Provision Requests';
        $data['requests'] = $rows;
        $data['pending_count'] = count($rows);
        $data['accounts'] = $accounts;
        $data['inactive_accounts'] = array_values(array_filter($accounts, static function ($a) {
            return ($a['status'] ?? '') === 'inactive';
        }));
        $data['active_accounts'] = array_values(array_filter($accounts, static function ($a) {
            return ($a['status'] ?? '') === 'active';
        }));
        $data['settings'] = $settings;
        $data['cfg'] = $cfg;
        $data['redirect_uri'] = sk_wa_meta_redirect_uri();
        $data['webhook_uri'] = sk_wa_meta_webhook_uri();
        $data['oauth_url'] = $this->_oauth_url($cfg, $state);
        $data['selected_request_id'] = $selected;
        $this->render('whatsapp_requests/pending', $data);
    }

    /**
     * Facebook / WA Embedded Signup code exchange for a pending provision request
     * or reconnect of an inactive vendor WhatsApp account.
     * POST: code, signup JSON, request_id and/or account_id
     */
    public function exchange() {
        if (!$this->is_super_admin()) {
            return $this->json(['ok' => false, 'error' => 'Admin only'], 403);
        }

        $requestId = (int)$this->input->post('request_id');
        if ($requestId < 1) {
            $requestId = (int)$this->session->userdata('wa_provision_request_id');
        }
        $accountId = (int)$this->input->post('account_id');
        $vendorIdPost = (int)$this->input->post('vendor_id');

        $req = null;
        $vendorId = 0;
        if ($requestId > 0) {
            $req = $this->Sk_Wa_Provision_request_model->get_by_id($requestId);
            if (!$req || ($req['status'] ?? '') !== 'pending') {
                return $this->json(['ok' => false, 'error' => 'Pending request not found. Click Embed Login on a pending row.'], 400);
            }
            $vendorId = (int)$req['vendor_id'];
        } elseif ($accountId > 0) {
            $acc = $this->Sk_Vendor_whatsapp_account_model->get_by_id($accountId);
            if (!$acc) {
                return $this->json(['ok' => false, 'error' => 'WhatsApp account not found.'], 400);
            }
            $vendorId = (int)$acc['vendor_id'];
            $req = [
                'id'         => 0,
                'vendor_id'  => $vendorId,
                'account_id' => $accountId,
            ];
        } elseif ($vendorIdPost > 0) {
            // Direct connect from vendor profile (no pending request).
            $vendor = $this->Sk_Vendor_model->get_by_id($vendorIdPost, false);
            if (!$vendor) {
                return $this->json(['ok' => false, 'error' => 'Vendor not found.'], 400);
            }
            $vendorId = $vendorIdPost;
            $req = [
                'id'        => 0,
                'vendor_id' => $vendorId,
            ];
        } else {
            return $this->json(['ok' => false, 'error' => 'Select a pending request, inactive number, or vendor first.'], 400);
        }

        $code = trim((string)$this->input->post('code', FALSE));
        $signup = $this->_signup_from_request();
        $signup['vendor_id'] = $vendorId;

        $result = $this->_finish_vendor_login($code, $signup, $req);
        if (!empty($result['ok'])) {
            $this->session->unset_userdata('wa_provision_request_id');
            if ($vendorId > 0) {
                $this->session->set_userdata('wa_ops_vendor_id', $vendorId);
            }
        }
        return $this->json($result, !empty($result['ok']) ? 200 : 400);
    }

    public function set_account_status($id = 0) {
        if (!$this->is_super_admin()) {
            show_error('Admin only', 403);
        }
        $id = (int)$id;
        $status = trim((string)$this->input->post('status', TRUE));
        if (!in_array($status, ['active', 'inactive'], true)) {
            $this->session->set_flashdata('error', 'Invalid status.');
            redirect('admin/whatsapp_requests/pending');
            return;
        }
        $acc = $this->Sk_Vendor_whatsapp_account_model->get_by_id($id);
        if (!$acc) {
            show_404();
        }
        $this->Sk_Vendor_whatsapp_account_model->set_status($id, $status);
        $this->activity_log->log_admin('wa_accounts', 'status_'.$status, $id, $acc, ['status' => $status]);
        $this->session->set_flashdata('success', 'Account marked ' . $status . '.');
        redirect('admin/whatsapp_requests/pending');
    }

    // Admin approve: connect and save account (manual fallback)
    public function approve($id = 0) {
        if (!$this->is_super_admin()) {
            show_error('Admin only', 403);
        }
        $id = (int)$id;
        $req = $this->Sk_Wa_Provision_request_model->get_by_id($id);
        if (!$req) {
            show_404();
        }

        if (strtoupper((string)$this->input->server('REQUEST_METHOD')) === 'POST') {
            $phone = trim((string)$this->input->post('phone_number_id', TRUE) ?: ($req['phone_number_id'] ?? ''));
            $waba = trim((string)$this->input->post('waba_id', TRUE) ?: ($req['waba_id'] ?? ''));
            $display = trim((string)$this->input->post('display_phone', TRUE) ?: ($req['display_phone'] ?? ''));
            $business = trim((string)$this->input->post('business_id', TRUE) ?: ($req['business_id'] ?? ''));
            $access = trim((string)$this->input->post('access_token', FALSE) ?: '');
            $refresh = trim((string)$this->input->post('refresh_token', FALSE) ?: '');
            $is_default = $this->input->post('is_default') ? 1 : 0;

            $saveRes = $this->Sk_Vendor_whatsapp_account_model->save_for_vendor((int)$req['vendor_id'], [
                'phone_number_id' => $phone,
                'waba_id' => $waba,
                'display_phone' => $display,
                'business_id' => $business,
                'access_token' => $access,
                'refresh_token' => $refresh,
                'is_default' => $is_default,
            ]);

            if (!empty($saveRes['ok'])) {
                $this->Sk_Wa_Provision_request_model->update_status($id, 'approved', $this->admin['id'], 'Connected by admin, account id ' . ($saveRes['id'] ?? ''));
                $this->activity_log->log_admin('wa_requests', 'approve', $id, $req, ['account' => $saveRes]);
                $this->session->set_flashdata('success', 'Provisioning approved and account saved.');
                redirect('admin/whatsapp_requests/pending');
                return;
            }
            $this->session->set_flashdata('error', 'Failed to save account.');
            redirect('admin/whatsapp_requests/pending');
            return;
        }

        // Prefer embedded login flow on pending page
        redirect('admin/whatsapp_requests/pending?request_id=' . $id . '#wa-embed-login');
    }

    public function reject($id = 0) {
        if (!$this->is_super_admin()) {
            show_error('Admin only', 403);
        }
        $id = (int)$id;
        $note = trim((string)$this->input->post('admin_note', TRUE) ?: 'Rejected by admin');
        $ok = $this->Sk_Wa_Provision_request_model->update_status($id, 'rejected', $this->admin['id'], $note);
        if ($ok) {
            $this->activity_log->log_admin('wa_requests', 'reject', $id, null, ['note' => $note]);
            $this->session->set_flashdata('success', 'Request rejected.');
        } else {
            $this->session->set_flashdata('error', 'Could not reject request.');
        }
        redirect('admin/whatsapp_requests/pending');
    }

    protected function _oauth_url(array $cfg, string $state): string {
        $query = [
            'client_id'     => $cfg['app_id'],
            'redirect_uri'  => sk_wa_meta_redirect_uri(),
            'state'         => $state,
            'response_type' => 'code',
            'scope'         => 'whatsapp_business_management,whatsapp_business_messaging,business_management',
        ];
        if ($cfg['config_id'] !== '') {
            $query['config_id'] = $cfg['config_id'];
            $query['override_default_response_type'] = 'true';
            $query['extras'] = json_encode([
                'setup'              => new stdClass(),
                'featureType'        => '',
                'sessionInfoVersion' => '3',
            ]);
        }
        return 'https://www.facebook.com/' . $cfg['api_version'] . '/dialog/oauth?' . http_build_query($query);
    }

    protected function _signup_from_request(): array {
        $raw = $this->input->post('signup', FALSE);
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        return [];
    }

    protected function _finish_vendor_login(string $code, array $signup, array $req): array {
        $settings = $this->Sk_Admin_model->get_settings();
        if ($code === '') {
            return ['ok' => false, 'error' => 'Facebook did not return an auth code.'];
        }

        // XCRM / Embedded Signup popup: omit redirect_uri
        $exchanged = sk_wa_meta_exchange_code($code, '', $settings);
        if (!$exchanged['ok']) {
            return ['ok' => false, 'error' => 'Token exchange failed: ' . $exchanged['error']];
        }

        $short = (string)($exchanged['data']['access_token'] ?? '');
        $long = sk_wa_meta_long_lived($short, $settings);
        $tokenData = $long['ok'] ? $long['data'] : $exchanged['data'];
        if (empty($tokenData['access_token'])) {
            $tokenData['access_token'] = $short;
        }

        $token = (string)$tokenData['access_token'];
        $assets = sk_wa_meta_discover_assets($token, $settings);
        $signup['vendor_id'] = (int)$req['vendor_id'];
        $saved = sk_wa_meta_save_connection($tokenData, $assets, $signup);

        if (!empty($saved['wa_cloud_waba_id'])) {
            sk_wa_meta_subscribe_waba($saved['wa_cloud_waba_id'], $token, $settings);
        }

        $phone = $saved['wa_cloud_phone_number_id'] ?? '';
        $waba = $saved['wa_cloud_waba_id'] ?? '';
        if ($phone === '') {
            return ['ok' => false, 'error' => 'Login succeeded but no WhatsApp phone number ID was returned. Complete Embedded Signup fully, then try again.'];
        }

        // Ensure vendor account row is active with token/phone/waba (save_connection already upserts).
        $this->Sk_Vendor_whatsapp_account_model->save_for_vendor((int)$req['vendor_id'], [
            'phone_number_id' => $phone,
            'waba_id'         => $waba,
            'display_phone'   => $saved['wa_cloud_display_phone'] ?? '',
            'business_id'     => $saved['wa_cloud_business_id'] ?? '',
            'access_token'    => $token,
            'refresh_token'   => $saved['wa_cloud_refresh_token'] ?? $token,
            'token_expires'   => $saved['wa_cloud_token_expires'] ?? '',
            'status'          => 'active',
            'is_default'      => 1,
        ]);

        if (!empty($req['id'])) {
            $this->Sk_Wa_Provision_request_model->update_status(
                (int)$req['id'],
                'approved',
                (int)($this->admin['id'] ?? 0),
                'Connected via WhatsApp embedded login. Phone ID: ' . $phone . ' WABA: ' . $waba
            );
            $this->activity_log->log_admin('wa_requests', 'approve_embed', (int)$req['id'], $req, [
                'phone_number_id' => $phone,
                'waba_id'         => $waba,
            ]);
        } else {
            $this->activity_log->log_admin('wa_accounts', 'reconnect_embed', (int)($req['account_id'] ?? 0), $req, [
                'phone_number_id' => $phone,
                'waba_id'         => $waba,
                'vendor_id'       => (int)$req['vendor_id'],
            ]);
        }

        return [
            'ok'      => true,
            'error'   => '',
            'message' => 'WhatsApp connected. Phone ID: ' . $phone . ' · WABA: ' . ($waba ?: '—'),
            'saved'   => [
                'phone_number_id' => $phone,
                'waba_id'         => $waba,
                'display_phone'   => $saved['wa_cloud_display_phone'] ?? '',
                'vendor_id'       => (int)$req['vendor_id'],
                'request_id'      => (int)($req['id'] ?? 0),
                'templates_url'   => site_url('admin/whatsapp/templates?vendor_id=' . (int)$req['vendor_id']),
            ],
        ];
    }
}
