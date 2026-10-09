<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * WhatsApp OTP through the Meta Cloud API (shop WhatsApp account).
 */
class Whatsapp_library {

    private $otp_template;
    private $ci;

    public function __construct($config = array())
    {
        $this->ci =& get_instance();
        $this->ci->config->load('whatsapp', TRUE);
        $fileCfg = $this->ci->config->item('whatsapp');
        if (!is_array($fileCfg)) {
            $fileCfg = [];
        }
        if (is_array($config) && !empty($config)) {
            $fileCfg = array_merge($fileCfg, $config);
        }
        $this->otp_template = trim((string)($fileCfg['otp_template'] ?? 'user_verified')) ?: 'user_verified';
    }

    public function get_config_status()
    {
        $this->ci->load->helper('sk_whatsapp_cloud');
        $settings = [];
        if (isset($this->ci->Sk_Admin_model)) {
            $settings = $this->ci->Sk_Admin_model->get_settings();
        }
        return [
            'provider'     => 'meta',
            'otp_template' => $this->otp_template,
            'cloud_ready'  => function_exists('sk_wa_cloud_is_ready') ? sk_wa_cloud_is_ready($settings) : false,
        ];
    }

    /**
     * Send OTP via the shop's approved Meta template.
     * @return array{success:bool,message?:string}
     */
    public function send_otp($phone, $otp)
    {
        $this->ci->load->helper(['sk_whatsapp', 'sk_whatsapp_cloud']);
        $settings = null;
        if (!isset($this->ci->Sk_Admin_model)) {
            $this->ci->load->model('Sk_Admin_model');
        }
        $settings = $this->ci->Sk_Admin_model->get_settings();
        $sent = sk_whatsapp_send_template((string)$phone, $this->otp_template, [(string)$otp], $settings);
        if (!empty($sent['success'])) {
            return ['success' => true, 'message' => 'OTP sent on WhatsApp.'];
        }
        return [
            'success' => false,
            'message' => (string)($sent['message'] ?? 'WhatsApp OTP was not sent. Approve the OTP template on this shop\'s WhatsApp account.'),
        ];
    }
}
