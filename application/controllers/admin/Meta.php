<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/admin/Sk_Base.php';

class Meta extends Sk_Base {

    public function __construct()
    {
        parent::__construct();
        $this->load->helper('sk_whatsapp_cloud');
        sk_wa_cloud_ensure_schema();
    }

    public function index()
    {
        $settings = $this->Sk_Admin_model->get_settings();
        $cfg = sk_wa_cloud_config($settings);
        $state = bin2hex(random_bytes(16));
        $this->session->set_userdata('wa_meta_oauth_state', $state);

        $data['title'] = 'Connect Facebook / WhatsApp';
        $data['settings'] = $settings;
        $data['cfg'] = $cfg;
        $data['redirect_uri'] = sk_wa_meta_redirect_uri();
        $data['webhook_uri'] = sk_wa_meta_webhook_uri();
        $data['oauth_url'] = $this->_oauth_url($cfg, $state);
        $data['connected'] = sk_wa_cloud_is_ready($settings);
        $this->render('meta/connect', $data);
    }

    public function connect()
    {
        $settings = $this->Sk_Admin_model->get_settings();
        $cfg = sk_wa_cloud_config($settings);
        if ($cfg['app_id'] === '' || $cfg['app_secret'] === '') {
            $this->session->set_flashdata('error', 'Save Facebook App ID and App Secret on this page first.');
            redirect('admin/meta');
            return;
        }
        $state = bin2hex(random_bytes(16));
        $this->session->set_userdata('wa_meta_oauth_state', $state);
        redirect($this->_oauth_url($cfg, $state));
    }

    public function callback()
    {
        $error = trim((string) $this->input->get('error_description', TRUE));
        if ($error === '') {
            $error = trim((string) $this->input->get('error', TRUE));
        }
        if ($error !== '') {
            $this->session->set_flashdata('error', 'Facebook login cancelled: ' . $error);
            redirect('admin/meta');
            return;
        }

        $state = (string) $this->input->get('state', TRUE);
        $expect = (string) $this->session->userdata('wa_meta_oauth_state');
        if ($expect === '' || $state === '' || !hash_equals($expect, $state)) {
            $this->session->set_flashdata('error', 'Facebook login state did not match. Try Connect again.');
            redirect('admin/meta');
            return;
        }

        $code = trim((string) $this->input->get('code', FALSE));
        $signup = $this->_signup_from_request();
        $provisionId = (int) $this->session->userdata('wa_provision_request_id');
        if ($provisionId > 0) {
            $this->load->model('Sk_Wa_Provision_request_model');
            $req = $this->Sk_Wa_Provision_request_model->get_by_id($provisionId);
            if ($req && ($req['status'] ?? '') === 'pending') {
                $signup['vendor_id'] = (int) $req['vendor_id'];
            }
        }
        $result = $this->_finish_login($code, $signup);
        if (!$result['ok']) {
            $this->session->set_flashdata('error', $result['error']);
            redirect($provisionId > 0 ? 'admin/whatsapp_requests/pending' : 'admin/meta');
            return;
        }

        if ($provisionId > 0) {
            $this->load->model('Sk_Wa_Provision_request_model');
            $req = $this->Sk_Wa_Provision_request_model->get_by_id($provisionId);
            if ($req && ($req['status'] ?? '') === 'pending') {
                $phone = $result['saved']['phone_number_id'] ?? '';
                $this->Sk_Wa_Provision_request_model->update_status(
                    $provisionId,
                    'approved',
                    (int)($this->admin['id'] ?? 0),
                    'Connected via Facebook OAuth. Phone ID: ' . $phone
                );
            }
            $this->session->unset_userdata('wa_provision_request_id');
            $this->session->unset_userdata('wa_meta_oauth_state');
            $this->session->set_flashdata('success', $result['message']);
            redirect('admin/whatsapp_requests/pending');
            return;
        }

        $this->session->unset_userdata('wa_meta_oauth_state');
        $this->session->set_flashdata('success', $result['message']);
        redirect('admin/meta');
    }

    public function exchange()
    {
        $code = trim((string) $this->input->post('code', FALSE));
        $signup = $this->_signup_from_request();
        $result = $this->_finish_login($code, $signup);
        $this->json($result, $result['ok'] ? 200 : 400);
    }

    /** Save Meta app credentials used for OAuth (phone/WABA stay dynamic from login). */
    public function save_app()
    {
        if (strtoupper((string)$this->input->server('REQUEST_METHOD')) !== 'POST') {
            redirect('admin/meta');
            return;
        }
        $data = [
            'wa_cloud_app_id'      => trim((string)$this->input->post('wa_cloud_app_id', TRUE)) ?: '1775381900284261',
            'wa_cloud_config_id'   => trim((string)$this->input->post('wa_cloud_config_id', TRUE)) ?: '1661379532355089',
            'wa_cloud_api_version' => trim((string)$this->input->post('wa_cloud_api_version', TRUE)) ?: 'v21.0',
            'wa_cloud_enabled'     => '1',
        ];
        $secret = trim((string)$this->input->post('wa_cloud_app_secret', FALSE));
        if ($secret !== '') {
            $data['wa_cloud_app_secret'] = $secret;
        }
        $this->Sk_Admin_model->save_settings($data);
        $this->session->set_flashdata('success', 'Meta app credentials saved. Phone / WABA still come from Facebook login.');
        redirect('admin/meta');
    }

    private function _oauth_url(array $cfg, string $state): string
    {
        $query = array(
            'client_id'     => $cfg['app_id'],
            'redirect_uri'  => sk_wa_meta_redirect_uri(),
            'state'         => $state,
            'response_type' => 'code',
            'scope'         => 'whatsapp_business_management,whatsapp_business_messaging,business_management',
        );
        if ($cfg['config_id'] !== '') {
            $query['config_id'] = $cfg['config_id'];
            $query['override_default_response_type'] = 'true';
            $query['extras'] = json_encode(array(
                'setup'              => new stdClass(),
                'featureType'        => '',
                'sessionInfoVersion' => '3',
            ));
        }
        return 'https://www.facebook.com/' . $cfg['api_version'] . '/dialog/oauth?' . http_build_query($query);
    }

    private function _signup_from_request(): array
    {
        $raw = $this->input->post('signup', FALSE);
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : array();
        }
        if (is_array($raw)) {
            return $raw;
        }
        return array();
    }

    private function _finish_login(string $code, array $signup): array
    {
        $settings = $this->Sk_Admin_model->get_settings();
        if ($code === '') {
            return array('ok' => false, 'error' => 'Facebook did not return an auth code.');
        }

        $exchanged = sk_wa_meta_exchange_code($code, sk_wa_meta_redirect_uri(), $settings);
        if (!$exchanged['ok']) {
            return array('ok' => false, 'error' => 'Token exchange failed: ' . $exchanged['error']);
        }

        $short = (string) ($exchanged['data']['access_token'] ?? '');
        $long = sk_wa_meta_long_lived($short, $settings);
        $tokenData = $long['ok'] ? $long['data'] : $exchanged['data'];
        if (empty($tokenData['access_token'])) {
            $tokenData['access_token'] = $short;
        }

        $token = (string) $tokenData['access_token'];
        $assets = sk_wa_meta_discover_assets($token, $settings);
        $saved = sk_wa_meta_save_connection($tokenData, $assets, $signup);

        if (!empty($saved['wa_cloud_waba_id'])) {
            sk_wa_meta_subscribe_waba($saved['wa_cloud_waba_id'], $token, $settings);
        }

        $phone = $saved['wa_cloud_phone_number_id'] !== '' ? $saved['wa_cloud_phone_number_id'] : 'not returned yet';
        $message = 'Facebook connected. Phone ID: ' . $phone
            . '. Add this webhook in Meta: ' . sk_wa_meta_webhook_uri();

        return array(
            'ok'      => true,
            'error'   => '',
            'message' => $message,
            'saved'   => array(
                'phone_number_id' => $saved['wa_cloud_phone_number_id'],
                'waba_id'         => $saved['wa_cloud_waba_id'],
                'display_phone'   => $saved['wa_cloud_display_phone'],
                'token_expires'   => $saved['wa_cloud_token_expires'],
                'webhook'         => sk_wa_meta_webhook_uri(),
            ),
        );
    }
}
