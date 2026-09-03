<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Askeva WhatsApp (backend.askeva.io) — order status utility messages.
 * Prefer free-form text; optional approved utility template fallback when session is closed.
 */

function sk_whatsapp_ensure_settings(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $CI =& get_instance();
    if (!isset($CI->db)) {
        $CI->load->database();
    }
    sk_whatsapp_ensure_log_schema();
    $CI->config->load('whatsapp', true);
    $fileCfg = $CI->config->item('whatsapp') ?: [];
    $defaults = [
        'askeva_whatsapp_enabled' => '1',
        'askeva_api_url'          => trim((string)($fileCfg['api_url'] ?? 'https://waadmin.syncr.in/v1/message/send-message'))
            ?: 'https://waadmin.syncr.in/v1/message/send-message',
        'askeva_api_token'        => trim((string)($fileCfg['api_key'] ?? '')),
        // Optional Meta-approved UTILITY template name (2 body params: order no, status). Leave empty to text-only.
        'askeva_order_template'   => '',
        'askeva_template_lang'    => 'en',
    ];
    $hasGroup = $CI->db->field_exists('group', 'settings');
    foreach ($defaults as $key => $value) {
        $exists = $CI->db->get_where('settings', ['key' => $key], 1)->row_array();
        if ($exists) {
            // Sync token/url from config when file has a newer non-empty value.
            if (in_array($key, ['askeva_api_token', 'askeva_api_url'], true)
                && trim((string)$value) !== ''
                && trim((string)($exists['value'] ?? '')) !== trim((string)$value)) {
                $CI->db->where('key', $key)->update('settings', ['value' => (string)$value]);
            }
            continue;
        }
        $row = ['key' => $key, 'value' => (string) $value];
        if ($hasGroup) {
            $row['group'] = 'whatsapp';
        }
        $CI->db->insert('settings', $row);
    }
}

function sk_whatsapp_config(array $settings = null): array {
    sk_whatsapp_ensure_settings();
    if ($settings === null) {
        $CI =& get_instance();
        $CI->load->model('Sk_Admin_model');
        $settings = $CI->Sk_Admin_model->get_settings();
    }
    $CI =& get_instance();
    $CI->config->load('whatsapp', true);
    $fileCfg = $CI->config->item('whatsapp');
    if (!is_array($fileCfg)) {
        $fileCfg = [];
    }

    // Direct-include fallback (same pattern as JT config) — fixes empty
    // status_templates / test_force_phone when CI section load fails on some hosts.
    if (empty($fileCfg['status_templates']) || !array_key_exists('test_force_phone', $fileCfg)) {
        $path = APPPATH . 'config/whatsapp.php';
        if (is_file($path)) {
            $config = [];
            include $path;
            if (!empty($config['whatsapp']) && is_array($config['whatsapp'])) {
                $fileCfg = array_merge($fileCfg, $config['whatsapp']);
            }
        }
    }

    // Prefer committed whatsapp.php token/URL so live matches git after pull
    // (DB settings often keep a stale Askeva token → Syncr "Template is not valid").
    $fileToken = trim((string)($fileCfg['api_key'] ?? ''));
    $dbToken   = trim((string)($settings['askeva_api_token'] ?? ''));
    $token     = $fileToken !== '' ? $fileToken : $dbToken;

    $fileUrl = trim((string)($fileCfg['api_url'] ?? ''));
    $dbUrl   = trim((string)($settings['askeva_api_url'] ?? ''));
    $url     = $fileUrl !== '' ? $fileUrl : ($dbUrl !== '' ? $dbUrl : 'https://waadmin.syncr.in/v1/message/send-message');

    // Hard defaults so confirmed/shipped/etc. always map even if config is stale on server
    $defaultTemplates = [
        'pending'    => 'order_received',
        'confirmed'  => 'order_confirmed',
        'processing' => 'order_ready_pickup',
        'shipped'    => 'order_shipped',
        'delivered'  => 'order_delivered',
        'cancelled'  => 'order_cancelled',
        'returned'   => 'order_returned',
    ];
    $statusTemplates = $fileCfg['status_templates'] ?? [];
    if (!is_array($statusTemplates)) {
        $statusTemplates = [];
    }
    $statusTemplates = array_merge($defaultTemplates, $statusTemplates);

    $fallbackTpl = trim((string)($fileCfg['fallback_template'] ?? ''));
    // Admin setting overrides fallback only (per-status names live in whatsapp.php).
    $settingsTpl = trim((string)($settings['askeva_order_template'] ?? ''));
    if ($settingsTpl !== '') {
        $fallbackTpl = $settingsTpl;
    }
    // Prefer file lang (templates are approved as "en"). Settings override only when non-empty
    // and matches a simple code — avoid en_US / en_GB breaking Syncr ("Template is not valid").
    $lang = trim((string)($fileCfg['template_lang'] ?? 'en')) ?: 'en';
    $settingsLang = strtolower(trim((string)($settings['askeva_template_lang'] ?? '')));
    if ($settingsLang !== '' && preg_match('/^[a-z]{2}$/', $settingsLang)) {
        $lang = $settingsLang;
    } elseif ($settingsLang !== '' && preg_match('/^([a-z]{2})[_-]/', $settingsLang, $m)) {
        $lang = $m[1]; // en_US → en
    }

    return [
        'enabled'           => ($settings['askeva_whatsapp_enabled'] ?? '1') !== '0',
        'url'               => $url ?: 'https://waadmin.syncr.in/v1/message/send-message',
        'token'             => $token,
        'template'          => $fallbackTpl,
        'status_templates'  => $statusTemplates,
        'lang'              => $lang,
        'param_mode'        => strtolower(trim((string)($fileCfg['template_param_mode'] ?? 'auto'))) ?: 'auto',
        'param_names'       => [
            'customer' => trim((string)(($fileCfg['template_param_names']['customer'] ?? 'Customername'))) ?: 'Customername',
            'order'    => trim((string)(($fileCfg['template_param_names']['order'] ?? 'OrderName'))) ?: 'OrderName',
        ],
        // TESTING override — all messages go here when set
        'test_force_phone'  => preg_replace('/\D+/', '', (string)($fileCfg['test_force_phone'] ?? '')),
    ];
}

/** Meta rejects template params with newlines/tabs; keep body vars single-line. */
function sk_whatsapp_sanitize_param(string $value): string {
    $value = str_replace(["\r", "\n", "\t"], ' ', $value);
    $value = preg_replace('/ {5,}/', '    ', $value);
    $value = trim($value);
    if ($value === '') {
        $value = '-';
    }
    if (strlen($value) > 1024) {
        $value = substr($value, 0, 1024);
    }
    return $value;
}

/** Resolve destination phone (applies test_force_phone when configured). */
function sk_whatsapp_destination_phone(string $to, array $settings = null): string {
    $cfg = sk_whatsapp_config($settings);
    $force = trim((string)($cfg['test_force_phone'] ?? ''));
    if ($force !== '') {
        return $force;
    }
    return sk_whatsapp_normalize_phone($to, $settings ?: []);
}

/**
 * Resolve template name + body values for a status.
 * Values are always [customer_name, order_number] in that order ({{1}}/{{2}} or named map built at send time).
 * @return array{name:string,values:string[]}|null
 */
function sk_whatsapp_template_for_status(string $status, array $order, array $cfg): ?array {
    $orderNo = (string)($order['order_number'] ?? ($order['id'] ?? ''));
    $name = trim((string)($order['customer_name'] ?? $order['shipping_name'] ?? 'Customer'));
    if ($name === '') {
        $name = 'Customer';
    }

    $map = $cfg['status_templates'] ?? [];
    $tplName = '';
    if (is_array($map) && !empty($map[$status])) {
        $tplName = trim((string)$map[$status]);
    }
    if ($tplName !== '') {
        return [
            'name'   => $tplName,
            // Keep order: {{1}}/Customername = name, {{2}}/OrderName = order no
            'values' => [$name, $orderNo],
        ];
    }
    $fallback = trim((string)($cfg['template'] ?? ''));
    if ($fallback !== '') {
        return [
            'name'   => $fallback,
            'values' => [$orderNo, sk_whatsapp_status_label($status)],
        ];
    }
    return null;
}

/** Normalize to digits with country code (MY default 60). */
function sk_whatsapp_normalize_phone(string $phone, array $settings = []): string {
    $phone = preg_replace('/\D+/', '', $phone);
    if ($phone === '') {
        return '';
    }
    // Strip leading 00
    if (strpos($phone, '00') === 0) {
        $phone = substr($phone, 2);
    }
    if ($phone[0] === '0') {
        $cc = preg_replace('/\D+/', '', (string)($settings['default_phone_country'] ?? '60'));
        if ($cc === '') {
            $cc = '60';
        }
        $phone = $cc . substr($phone, 1);
    }
    return $phone;
}

function sk_whatsapp_order_phone(array $order, array $settings = []): string {
    $info = sk_whatsapp_order_phone_info($order, $settings);
    return $info['phone'];
}

/** @return array{phone:string,source:string} */
function sk_whatsapp_order_phone_info(array $order, array $settings = []): array {
    $map = [
        'shipping' => $order['shipping_phone'] ?? '',
        'billing'  => $order['billing_phone'] ?? '',
        'customer' => $order['customer_phone'] ?? '',
    ];
    foreach ($map as $source => $p) {
        $n = sk_whatsapp_normalize_phone((string)$p, $settings);
        if (strlen($n) >= 10) {
            return ['phone' => $n, 'source' => $source];
        }
    }
    return ['phone' => '', 'source' => 'none'];
}

function sk_whatsapp_ensure_log_schema(): void {
    static $ready = false;
    if ($ready) {
        return;
    }
    $ready = true;
    $CI =& get_instance();
    if (!$CI->db->table_exists('whatsapp_logs')) {
        $CI->db->query("CREATE TABLE IF NOT EXISTS `whatsapp_logs` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `order_id` INT UNSIGNED NULL DEFAULT NULL,
            `order_number` VARCHAR(64) NULL DEFAULT NULL,
            `phone` VARCHAR(32) NULL DEFAULT NULL,
            `phone_source` VARCHAR(20) NULL DEFAULT NULL,
            `status_trigger` VARCHAR(40) NULL DEFAULT NULL,
            `channel` VARCHAR(20) NULL DEFAULT NULL,
            `delivery_status` VARCHAR(20) NOT NULL DEFAULT 'failed',
            `reason` VARCHAR(500) NULL DEFAULT NULL,
            `http_code` INT NULL DEFAULT NULL,
            `api_message` TEXT NULL,
            `api_response` MEDIUMTEXT NULL,
            `message_body` TEXT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_wa_order` (`order_id`),
            KEY `idx_wa_status` (`delivery_status`),
            KEY `idx_wa_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

function sk_whatsapp_log(array $row): void {
    $CI =& get_instance();
    sk_whatsapp_ensure_log_schema();
    $data = [
        'order_id'        => isset($row['order_id']) ? (int)$row['order_id'] : null,
        'order_number'    => isset($row['order_number']) ? substr((string)$row['order_number'], 0, 64) : null,
        'phone'           => isset($row['phone']) ? substr((string)$row['phone'], 0, 32) : null,
        'phone_source'    => isset($row['phone_source']) ? substr((string)$row['phone_source'], 0, 20) : null,
        'status_trigger'  => isset($row['status_trigger']) ? substr((string)$row['status_trigger'], 0, 40) : null,
        'channel'         => isset($row['channel']) ? substr((string)$row['channel'], 0, 20) : null,
        'delivery_status' => substr((string)($row['delivery_status'] ?? 'failed'), 0, 20),
        'reason'          => isset($row['reason']) ? substr((string)$row['reason'], 0, 500) : null,
        'http_code'       => isset($row['http_code']) ? (int)$row['http_code'] : null,
        'api_message'     => $row['api_message'] ?? null,
        'api_response'    => is_string($row['api_response'] ?? null)
            ? $row['api_response']
            : (isset($row['api_response']) ? json_encode($row['api_response'], JSON_UNESCAPED_UNICODE) : null),
        'message_body'    => $row['message_body'] ?? null,
        'created_at'      => date('Y-m-d H:i:s'),
    ];
    $CI->db->insert('whatsapp_logs', $data);
}

/**
 * Notify customer on order status change via WhatsApp.
 * Tries text first; if session closed and a utility template is set, falls back to template.
 * Every attempt is stored in whatsapp_logs for the delivery report.
 */
function sk_whatsapp_notify_order_status(array $order, string $status, array $settings = null): array {
    // Unpaid Razorpay attempts must not trigger order-received / confirm templates.
    if ($status === 'payment_attempt') {
        return ['success' => false, 'message' => 'Skipped: payment attempt (notify after payment).', 'via' => 'none'];
    }
    $CI =& get_instance();
    if ($settings === null) {
        $CI->load->model('Sk_Admin_model');
        $settings = $CI->Sk_Admin_model->get_settings();
    }
    sk_whatsapp_ensure_log_schema();

    $orderId = (int)($order['id'] ?? 0) ?: null;
    $orderNo = (string)($order['order_number'] ?? '');
    $baseLog = [
        'order_id'       => $orderId,
        'order_number'   => $orderNo,
        'status_trigger' => $status,
    ];

    $cfg = sk_whatsapp_config($settings);
    if (!$cfg['enabled']) {
        $result = ['success' => false, 'message' => 'WhatsApp notifications disabled in Settings.', 'via' => 'none'];
        sk_whatsapp_log($baseLog + [
            'phone' => null,
            'phone_source' => 'none',
            'channel' => 'none',
            'delivery_status' => 'skipped',
            'reason' => $result['message'],
            'api_message' => $result['message'],
        ]);
        return $result;
    }

    if ($cfg['token'] === '') {
        $result = ['success' => false, 'message' => 'Askeva API token not configured.', 'via' => 'none'];
        sk_whatsapp_log($baseLog + [
            'channel' => 'none',
            'delivery_status' => 'skipped',
            'reason' => $result['message'],
            'api_message' => $result['message'],
        ]);
        return $result;
    }

    $phoneInfo = sk_whatsapp_order_phone_info($order, $settings);
    $phone = $phoneInfo['phone'];
    $cfgForce = trim((string)($cfg['test_force_phone'] ?? ''));
    if ($cfgForce !== '') {
        // TESTING: always deliver to force phone even if order has no phone
        $phone = $cfgForce;
        $phoneInfo['source'] = 'test_force';
    }
    if ($phone === '') {
        $result = ['success' => false, 'message' => 'No customer phone on order (shipping/billing/registration all empty).', 'via' => 'none'];
        sk_whatsapp_log($baseLog + [
            'phone' => null,
            'phone_source' => 'none',
            'channel' => 'none',
            'delivery_status' => 'skipped',
            'reason' => $result['message'],
            'api_message' => $result['message'],
        ]);
        return $result;
    }

    $msg = sk_whatsapp_order_message($order, $status, $settings);

    // Prefer approved utility template (works outside 24h session — no open chat needed).
    // Free-text only when no template is mapped (text requires an open 24h customer session).
    $tpl = sk_whatsapp_template_for_status($status, $order, $cfg);
    if ($tpl !== null) {
        $result = sk_whatsapp_send_template($phone, $tpl['name'], $tpl['values'], $settings);
        $result['via'] = 'template:' . $tpl['name'];
        if (empty($result['success'])) {
            // Do not fall back to free text — it needs a 24h session and confuses the report.
            $result['message'] = 'Template "' . $tpl['name'] . '" failed: '
                . (string)($result['message'] ?? 'unknown');
        } else {
            $result['message'] = 'Sent via template "' . $tpl['name'] . '"'
                . ($cfgForce !== '' ? ' (test phone ' . $cfgForce . ')' : '');
        }
    } else {
        $result = sk_whatsapp_send_text($phone, $msg, $settings);
        $result['via'] = 'text';
        if (empty($result['success'])) {
            $result['message'] = (string)($result['message'] ?? 'text send failed')
                . ' — no WhatsApp utility template configured for status "' . $status
                . '" (see database/whatsapp_order_templates.txt). Templates do not need a 24h session.';
        }
    }

    $delivered = !empty($result['success']);
    sk_whatsapp_log($baseLog + [
        'phone'           => $phone,
        'phone_source'    => $phoneInfo['source'],
        'channel'         => $result['via'] ?? 'text',
        'delivery_status' => $delivered ? 'sent' : 'failed',
        'reason'          => $delivered
            ? ('Delivered via ' . ($result['via'] ?? 'text')
                . ($cfgForce !== '' ? ' to TEST phone' : (' to ' . $phoneInfo['source'] . ' phone')))
            : (string)($result['message'] ?? 'Send failed'),
        'http_code'       => $result['http'] ?? null,
        'api_message'     => $result['message'] ?? null,
        'api_response'    => $result['response'] ?? null,
        'message_body'    => $msg,
    ]);

    log_message('info', 'Askeva WA order ' . ($order['id'] ?? '?') . ' status=' . $status
        . ' via=' . ($result['via'] ?? '?') . ' ok=' . ($delivered ? '1' : '0')
        . ' to=' . $phone
        . ' msg=' . ($result['message'] ?? ''));

    return $result;
}


function sk_whatsapp_status_label(string $status): string {
    $map = [
        'payment_attempt' => 'Payment Attempt',
        'pending'    => 'Order Received',
        'confirmed'  => 'Order Confirmed',
        'processing' => 'Ready to Pick Up',
        'shipped'    => 'Shipped',
        'delivered'  => 'Delivered',
        'cancelled'  => 'Cancelled',
        'returned'   => 'Return Requested',
    ];
    return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function sk_whatsapp_order_message(array $order, string $status, array $settings = []): string {
    $site = $settings['site_name'] ?? '2DEAL';
    $orderNo = $order['order_number'] ?? ('#' . ($order['id'] ?? ''));
    $label = sk_whatsapp_status_label($status);
    $name = trim((string)($order['customer_name'] ?? $order['shipping_name'] ?? 'Customer'));
    $lines = [
        "{$site}: Hi {$name},",
        "Your order {$orderNo} is now: {$label}.",
    ];
    $awb = trim((string)($order['jt_bill_code'] ?? $order['tracking_number'] ?? ''));
    if ($awb !== '') {
        $lines[] = "Tracking / AWB: {$awb}";
    }
    $total = isset($order['total']) ? number_format((float)$order['total'], 2) : '';
    $cur = sk_currency_symbol($settings);
    if ($total !== '') {
        $lines[] = "Amount: {$cur}{$total}";
    }
    $lines[] = 'Thank you for shopping with 2DEAL.';
    return implode("\n", $lines);
}

/**
 * Low-level POST to Syncr/WAAdmin send-message (?token=...).
 * @return array{success:bool,http?:int,response?:mixed,message?:string}
 */
function sk_whatsapp_api_send(array $payload, array $cfg): array {
    if (empty($cfg['token'])) {
        return ['success' => false, 'message' => 'WhatsApp API token not configured.'];
    }
    $url = rtrim($cfg['url'], '?&');
    $url .= (strpos($url, '?') === false ? '?' : '&') . 'token=' . rawurlencode($cfg['token']);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        log_message('error', 'Syncr WA curl: ' . $err);
        return ['success' => false, 'http' => $code, 'message' => $err];
    }
    $json = json_decode((string)$raw, true);
    $ok = $code >= 200 && $code < 300;
    if (is_array($json) && (isset($json['error']) || (isset($json['status']) && $json['status'] === 'error'))) {
        $ok = false;
    }
    if (!$ok) {
        log_message('error', 'Syncr WA fail HTTP ' . $code . ': ' . $raw);
    }
    return [
        'success'  => $ok,
        'http'     => $code,
        'response' => $json !== null ? $json : $raw,
        'message'  => is_array($json) ? (string)($json['error'] ?? $json['message'] ?? ($ok ? 'sent' : 'failed')) : ($ok ? 'sent' : (string)$raw),
    ];
}

/** Send plain utility/session text. */
function sk_whatsapp_send_text(string $to, string $body, array $settings = null): array {
    $cfg = sk_whatsapp_config($settings);
    if (!$cfg['enabled']) {
        return ['success' => false, 'message' => 'WhatsApp notifications disabled.'];
    }
    $to = sk_whatsapp_destination_phone($to, $settings);
    if ($to === '') {
        return ['success' => false, 'message' => 'Invalid phone.'];
    }
    return sk_whatsapp_api_send([
        'to'   => $to,
        'type' => 'text',
        'text' => ['body' => $body],
    ], $cfg);
}

/**
 * Build body parameters for Syncr/Meta.
 * @param string[] $values ordered body texts
 * @param bool $named when true, attach parameter_name (Customername / OrderName)
 */
function sk_whatsapp_build_body_params(array $values, array $cfg, bool $named): array {
    $params = [];
    $keys = [
        trim((string)($cfg['param_names']['customer'] ?? 'Customername')) ?: 'Customername',
        trim((string)($cfg['param_names']['order'] ?? 'OrderName')) ?: 'OrderName',
    ];
    $i = 0;
    foreach ($values as $p) {
        $entry = [
            'type' => 'text',
            'text' => sk_whatsapp_sanitize_param((string)$p),
        ];
        if ($named) {
            $entry['parameter_name'] = $keys[$i] ?? ('var' . ($i + 1));
        }
        $params[] = $entry;
        $i++;
    }
    return $params;
}

/** Send approved utility template. $bodyValues = ordered list of body texts. */
function sk_whatsapp_send_template(string $to, string $templateName, array $bodyValues, array $settings = null): array {
    $cfg = sk_whatsapp_config($settings);
    if (!$cfg['enabled'] || $templateName === '') {
        return ['success' => false, 'message' => 'Template not configured.'];
    }
    $to = sk_whatsapp_destination_phone($to, $settings);
    if ($to === '') {
        return ['success' => false, 'message' => 'Invalid phone.'];
    }

    // Normalize to a 0-indexed list of strings (ignore accidental string keys).
    $values = [];
    foreach ($bodyValues as $p) {
        $values[] = (string)$p;
    }

    $mode = strtolower((string)($cfg['param_mode'] ?? 'auto'));
    if (!in_array($mode, ['positional', 'named', 'auto'], true)) {
        $mode = 'auto';
    }
    $attempts = $mode === 'named'
        ? [true]
        : ($mode === 'positional' ? [false] : [false, true]); // auto: positional then named

    $result = ['success' => false, 'message' => 'no attempt'];
    $lastParams = [];
    $usedNamed = false;
    foreach ($attempts as $named) {
        $usedNamed = $named;
        $params = sk_whatsapp_build_body_params($values, $cfg, $named);
        $lastParams = $params;
        $payload = [
            'to'       => $to,
            'type'     => 'template',
            'template' => [
                'language'   => ['policy' => 'deterministic', 'code' => $cfg['lang'] ?: 'en'],
                'name'       => $templateName,
                'components' => [[
                    'type'      => 'body',
                    'parameters' => $params,
                ]],
            ],
        ];
        $result = sk_whatsapp_api_send($payload, $cfg);
        if (!empty($result['success'])) {
            break;
        }
        $err = strtolower((string)($result['message'] ?? ''));
        // Retry other style only for format / validity style errors
        $retryable = (strpos($err, 'template is not valid') !== false)
            || (strpos($err, 'parameter name') !== false)
            || (strpos($err, 'invalid parameter') !== false)
            || (strpos($err, 'number of parameters') !== false);
        if (!$retryable) {
            break;
        }
    }

    if (empty($result['success'])) {
        $snap = [];
        foreach ($lastParams as $p) {
            $snap[] = (isset($p['parameter_name']) ? $p['parameter_name'] . '=' : '') . ($p['text'] ?? '');
        }
        $tokenTip = $cfg['token'] !== '' ? ('…' . substr($cfg['token'], -6)) : '(empty)';
        $result['message'] = (string)($result['message'] ?? 'failed')
            . ' [tpl=' . $templateName . ' lang=' . ($cfg['lang'] ?: 'en')
            . ' style=' . ($usedNamed ? 'named' : 'positional')
            . ' token=' . $tokenTip
            . ' params=' . json_encode($snap, JSON_UNESCAPED_UNICODE) . ']';
    }
    return $result;
}
