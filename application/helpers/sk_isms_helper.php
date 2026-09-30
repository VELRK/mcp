<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * iSMS OTP settings + otp_sessions table (no CLI required).
 */
function sk_isms_ensure_schema() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $CI =& get_instance();
    if (!isset($CI->db)) {
        $CI->load->database();
    }

    if (!$CI->db->table_exists('otp_sessions')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `otp_sessions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `phone` VARCHAR(20) NOT NULL,
            `sms_id` VARCHAR(32) NOT NULL DEFAULT '',
            `uuid` VARCHAR(64) NOT NULL DEFAULT '',
            `otp_hash` VARCHAR(255) NOT NULL DEFAULT '',
            `provider` VARCHAR(20) NOT NULL DEFAULT 'isms',
            `created_at` DATETIME NOT NULL,
            `expires_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_otp_phone` (`phone`),
            KEY `idx_otp_expires` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } elseif (!$CI->db->field_exists('otp_hash', 'otp_sessions')) {
        $CI->db->query("ALTER TABLE `otp_sessions` ADD COLUMN `otp_hash` VARCHAR(255) NOT NULL DEFAULT '' AFTER `uuid`");
    }

    $defaults = [
        'isms_enabled'       => '0',
        'isms_username'      => '',
        'isms_password'      => '',
        'isms_api_key'       => '',
        'isms_sender_id'     => 'GOLDEN2DEAL',
        'isms_message'       => 'Your OTP is %OTP%. Valid for 5 minutes.',
        'isms_country_code'  => '60',
        'isms_otp_interval'  => '5',
        'isms_test_otp'      => '1234',
        'isms_test_phone'    => '60180000000',
    ];

    $hasGroup = $CI->db->field_exists('group', 'settings');
    foreach ($defaults as $key => $value) {
        $exists = (int) $CI->db->where('key', $key)->count_all_results('settings');
        if ($exists) {
            continue;
        }
        $row = ['key' => $key, 'value' => $value];
        if ($hasGroup) {
            $row['group'] = 'sms';
        }
        $CI->db->insert('settings', $row);
    }

    if ($CI->db->table_exists('settings')) {
        $CI->db->where('key', 'isms_test_otp')->where('value', '123456')
            ->update('settings', ['value' => '1234']);
        $CI->db->where('key', 'isms_sender_id')->where('value', '')->update('settings', ['value' => 'GOLDEN2DEAL']);
        $CI->db->where('key', 'isms_username')->where('value', '2DEAL')->update('settings', ['value' => '2DEAL1']);
        $CI->db->where('key', 'isms_test_phone')->where('value', '601800000000')->update('settings', ['value' => '60180000000']);
    }
}

function sk_isms_test_defaults() {
    return [
        'phone' => '60180000000',
        'otp'   => '1234',
    ];
}

/**
 * @return array{phone:string,otp:string,display_phone:string}
 */
function sk_isms_get_test_config(array $settings = null) {
    $defaults = sk_isms_test_defaults();
    if ($settings === null) {
        $CI =& get_instance();
        if (!isset($CI->Sk_Admin_model)) {
            $CI->load->model('Sk_Admin_model');
        }
        $settings = $CI->Sk_Admin_model->get_settings();
    }
    $phone = preg_replace('/\D/', '', trim($settings['isms_test_phone'] ?? ''));
    if ($phone === '') {
        $phone = $defaults['phone'];
    }
    $otp = trim($settings['isms_test_otp'] ?? '') ?: $defaults['otp'];
    $local = $phone;
    if (strpos($local, '91') === 0 && strlen($local) === 12) {
        $local = substr($local, 2);
    } elseif (strpos($local, '60') === 0) {
        $local = '0' . substr($local, 2);
    }
    return [
        'phone'          => $phone,
        'otp'            => $otp,
        'display_phone'  => $local,
    ];
}

/**
 * Spellings of the configured developer test mobile only.
 * Do NOT keep old hardcoded numbers here — changing Settings → test phone
 * must not reopen a previous test account (e.g. …004 vs …008).
 * @return string[]
 */
function sk_isms_test_phone_aliases(array $settings) {
    $CI =& get_instance();
    $CI->load->library('isms', $settings);
    $test = sk_isms_get_test_config($settings);
    $canonical = preg_replace('/\D/', '', (string) ($test['phone'] ?? ''));
    if ($canonical === '') {
        $canonical = '60180000000';
    }

    $aliases = [
        $CI->isms->normalize_phone($canonical),
        $canonical,
    ];

    if (strpos($canonical, '91') === 0 && strlen($canonical) === 12) {
        $national = substr($canonical, 2);
        $aliases[] = $national;
        $aliases[] = $CI->isms->normalize_phone($national);
        $aliases[] = $CI->isms->normalize_phone('0' . $national);
    }

    // Local / national forms of the SAME number
    if (strpos($canonical, '60') === 0) {
        $national = substr($canonical, 2);
        $aliases[] = $CI->isms->normalize_phone('0' . $national);
        $aliases[] = $CI->isms->normalize_phone($national);
    }

    // Extra trailing-0 spelling of the SAME number only (legacy duplicate)
    $aliases[] = $CI->isms->normalize_phone($canonical . '0');
    if (strlen($canonical) > 11 && substr($canonical, -1) === '0') {
        $aliases[] = $CI->isms->normalize_phone(substr($canonical, 0, -1));
    }

    return array_values(array_unique(array_filter($aliases)));
}

/** Canonical test phone stored in settings (default 60180000000). */
function sk_isms_canonical_test_phone(array $settings) {
    $test = sk_isms_get_test_config($settings);
    $phone = preg_replace('/\D/', '', (string) ($test['phone'] ?? ''));
    return $phone !== '' ? $phone : '60180000000';
}

function sk_isms_is_test_phone(array $settings, $normalized_phone) {
    if ($normalized_phone === '') {
        return false;
    }
    return in_array($normalized_phone, sk_isms_test_phone_aliases($settings), true);
}

/**
 * TEMP: hardcoded iSMS sub-account on production until admin settings are verified.
 * Remove sk_isms_production_override() once OTP works via Admin → Settings.
 */
function sk_isms_production_override() {
    if (!defined('ENVIRONMENT') || ENVIRONMENT !== 'production') {
        return null;
    }
    return [
        'isms_enabled'   => '1',
        'isms_username'  => '2DEAL1',
        'isms_sender_id' => 'GOLDEN2DEAL',
    ];
}

function sk_isms_effective_settings(array $settings) {
    $override = sk_isms_production_override();
    return $override ? array_merge($settings, $override) : $settings;
}

function sk_isms_is_configured(array $settings) {
    $settings = sk_isms_effective_settings($settings);
    $hasSecret = trim($settings['isms_password'] ?? '') !== ''
        || trim($settings['isms_api_key'] ?? '') !== '';
    return !empty($settings['isms_enabled']) && $settings['isms_enabled'] !== '0'
        && trim($settings['isms_username'] ?? '') !== ''
        && $hasSecret;
}

/** @return string[] Unique non-empty API pwd values to try (portal password, then API key). */
function sk_isms_auth_secrets(array $settings) {
    $secrets = [];
    foreach (['isms_password', 'isms_api_key'] as $field) {
        $value = sk_isms_clean_credential($settings[$field] ?? '', false);
        if ($value !== '' && !in_array($value, $secrets, true)) {
            $secrets[] = $value;
        }
    }
    return $secrets;
}

/** Normalize stored/API credentials (fix legacy HTML entity encoding). */
function sk_isms_clean_credential($value, $trim = true) {
    $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = str_replace("\0", '', $value);
    return $trim ? trim($value) : $value;
}

/** Mask username for admin diagnostics (show first/last chars only). */
function sk_isms_mask_username($username) {
    $username = (string) $username;
    $len = strlen($username);
    if ($len <= 2) {
        return str_repeat('*', $len);
    }
    if ($len <= 6) {
        return substr($username, 0, 1) . str_repeat('*', max(1, $len - 2)) . substr($username, -1);
    }
    return substr($username, 0, 3) . str_repeat('*', $len - 5) . substr($username, -2);
}

/** Outbound IP seen by the internet (for iSMS IP whitelist requests). */
function sk_isms_server_outbound_ip() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = '';
    if (!function_exists('curl_init')) {
        return $cached;
    }
    $ch = curl_init('https://api.ipify.org?format=text');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $ip = trim((string) curl_exec($ch));
    curl_close($ch);
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        $cached = $ip;
    }
    return $cached;
}

function sk_isms_auth_failure_hint(array $result = []) {
    $hints = [];
    $code = (int) ($result['code'] ?? 0);
    $outIp = sk_isms_server_outbound_ip();
    if ($outIp !== '') {
        $hints[] = 'Server outbound IP: ' . $outIp . ' — ask iSMS support to whitelist this IP for API/SMS.';
    }
    if ($code === -1001 || stripos((string) ($result['message'] ?? ''), 'authentication failed') !== false) {
        $hints[] = 'Use sub-account API login: un=2DEAL1, pwd=(sub-account password). Main portal email login does not work for API.';
        $hints[] = 'Confirm the sub-account is saved in iSMS portal (Profile → Sub Accounts) and has SMS credits.';
    }
    return implode(' ', $hints);
}

function sk_isms_save_session($phone, $sms_id, $otp_hash, $interval_minutes = 5) {
    $CI =& get_instance();
    sk_isms_ensure_schema();

    $now = date('Y-m-d H:i:s');
    $expires = date('Y-m-d H:i:s', time() + ((int) $interval_minutes * 60));

    $CI->db->where('phone', $phone)->delete('otp_sessions');

    $CI->db->insert('otp_sessions', [
        'phone'      => $phone,
        'sms_id'     => (string) $sms_id,
        'uuid'       => '',
        'otp_hash'   => (string) $otp_hash,
        'provider'   => 'isms',
        'created_at' => $now,
        'expires_at' => $expires,
    ]);
}

/**
 * @return array{sms_id:string,otp_hash:string}|null
 */
function sk_isms_get_session($phone) {
    $CI =& get_instance();
    sk_isms_ensure_schema();

    $row = $CI->db->where('phone', $phone)
        ->where('expires_at >=', date('Y-m-d H:i:s'))
        ->order_by('id', 'DESC')
        ->limit(1)
        ->get('otp_sessions')
        ->row_array();

    if (!$row) {
        return null;
    }

    return [
        'sms_id'   => (string) ($row['sms_id'] ?? ''),
        'otp_hash' => (string) ($row['otp_hash'] ?? ''),
    ];
}

function sk_isms_verify_session_otp($phone, $code) {
    $session = sk_isms_get_session($phone);
    if (!$session || $session['otp_hash'] === '') {
        return false;
    }
    $code = preg_replace('/\D/', '', (string) $code);
    return $code !== '' && password_verify($code, $session['otp_hash']);
}

function sk_isms_clear_session($phone) {
    $CI =& get_instance();
    if (!$CI->db->table_exists('otp_sessions')) {
        return;
    }
    $CI->db->where('phone', $phone)->delete('otp_sessions');
}

function sk_isms_phone_error() {
    return 'Enter a 10-digit Indian mobile number (e.g. 9876543210).';
}

/**
 * @return array{country_code:string,mobile:string,normalized:string}|null
 */
function sk_isms_parse_phone($phone, array $settings = null) {
    $CI =& get_instance();
    if ($settings === null) {
        if (!isset($CI->Sk_Admin_model)) {
            $CI->load->model('Sk_Admin_model');
        }
        $settings = $CI->Sk_Admin_model->get_settings();
    }
    $CI->load->library('isms', $settings);
    return $CI->isms->parse_phone($phone);
}

function sk_isms_normalize_phone($phone, array $settings = null) {
    $parsed = sk_isms_parse_phone($phone, $settings);
    return $parsed ? $parsed['normalized'] : '';
}

/**
 * Razorpay prefill contact: +91 and a 10-digit Indian mobile.
 * Returns empty string when the number is missing or invalid.
 */
function sk_razorpay_contact($phone, array $settings = null) {
    $normalized = sk_isms_normalize_phone($phone, $settings);
    if ($normalized === '' || !preg_match('/^91[6-9]\d{9}$/', $normalized)) {
        return '';
    }
    return '+' . $normalized;
}

/**
 * Email safe to pass into Razorpay Curlec checkout prefill.
 */
function sk_razorpay_prefill_email($email) {
    $email = trim((string)$email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return '';
    }
    if (stripos($email, '@shopkart.app') !== false || stripos($email, 'ph_') === 0) {
        return '';
    }
    return $email;
}
