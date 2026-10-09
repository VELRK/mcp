<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Order status WhatsApp messages. Delivery uses the shop's Meta Cloud templates
 * (order created, updated, cancelled, delivered).
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
}

function sk_whatsapp_config(array $settings = null): array {
    sk_whatsapp_ensure_settings();
    unset($settings);
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
    $lang = trim((string)($fileCfg['template_lang'] ?? 'en')) ?: 'en';

    return [
        'enabled'           => true,
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

/** Normalize to digits with India country code 91. A 10-digit mobile is stored as 91xxxxxxxxxx. */
function sk_whatsapp_normalize_phone(string $phone, array $settings = []): string {
    $phone = preg_replace('/\D+/', '', $phone);
    if ($phone === '') {
        return '';
    }
    if (strpos($phone, '00') === 0) {
        $phone = substr($phone, 2);
    }
    if (strlen($phone) === 12 && strpos($phone, '91') === 0) {
        return $phone;
    }
    if (strlen($phone) === 11 && $phone[0] === '0' && preg_match('/^0[6-9]\d{9}$/', $phone)) {
        return '91' . substr($phone, 1);
    }
    if (strlen($phone) === 10 && preg_match('/^[6-9]\d{9}$/', $phone)) {
        return '91' . $phone;
    }
    if ($phone[0] === '0') {
        $cc = preg_replace('/\D+/', '', (string)($settings['default_phone_country'] ?? '91'));
        if ($cc === '' || $cc === '60') {
            $cc = '91';
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
            `vendor_id` INT UNSIGNED NULL DEFAULT NULL,
            `template_name` VARCHAR(128) NULL DEFAULT NULL,
            `wamid` VARCHAR(128) NULL DEFAULT NULL,
            `channel` VARCHAR(64) NULL DEFAULT NULL,
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
            KEY `idx_wa_created` (`created_at`),
            KEY `idx_wa_wamid` (`wamid`),
            KEY `idx_wa_vendor` (`vendor_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    if ($CI->db->field_exists('channel', 'whatsapp_logs')) {
        $CI->db->query("ALTER TABLE `whatsapp_logs` MODIFY `channel` VARCHAR(64) NULL DEFAULT NULL");
    }
    if (!$CI->db->field_exists('vendor_id', 'whatsapp_logs')) {
        $CI->db->query("ALTER TABLE `whatsapp_logs` ADD COLUMN `vendor_id` INT UNSIGNED NULL DEFAULT NULL, ADD KEY `idx_wa_vendor` (`vendor_id`)");
    }
    if (!$CI->db->field_exists('template_name', 'whatsapp_logs')) {
        $CI->db->query("ALTER TABLE `whatsapp_logs` ADD COLUMN `template_name` VARCHAR(128) NULL DEFAULT NULL");
    }
    if (!$CI->db->field_exists('wamid', 'whatsapp_logs')) {
        $CI->db->query("ALTER TABLE `whatsapp_logs` ADD COLUMN `wamid` VARCHAR(128) NULL DEFAULT NULL, ADD KEY `idx_wa_wamid` (`wamid`)");
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
        'vendor_id'       => isset($row['vendor_id']) ? (int)$row['vendor_id'] : null,
        'template_name'   => isset($row['template_name']) ? substr((string)$row['template_name'], 0, 128) : null,
        'wamid'           => isset($row['wamid']) ? substr((string)$row['wamid'], 0, 128) : null,
        'channel'         => isset($row['channel']) ? substr((string)$row['channel'], 0, 64) : null,
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
 * Move a Meta template send from sent → delivered → read, or mark it failed.
 * Called from the WhatsApp webhook status callback.
 */
function sk_whatsapp_apply_meta_status(string $wamid, string $status, string $error = ''): void {
    $wamid = trim($wamid);
    $status = strtolower(trim($status));
    if ($wamid === '' || !in_array($status, ['sent', 'delivered', 'read', 'failed'], true)) {
        return;
    }
    $CI =& get_instance();
    sk_whatsapp_ensure_log_schema();
    if (!$CI->db->field_exists('wamid', 'whatsapp_logs')) {
        return;
    }
    $row = $CI->db->where('wamid', $wamid)->limit(1)->get('whatsapp_logs')->row_array();
    if (!$row) {
        return;
    }
    $current = strtolower((string)($row['delivery_status'] ?? ''));
    $rank = ['sent' => 1, 'delivered' => 2, 'read' => 3];
    if ($status !== 'failed' && isset($rank[$current], $rank[$status]) && $rank[$current] >= $rank[$status]) {
        return;
    }
    if ($status === 'sent' && $current === 'failed') {
        return;
    }
    $upd = ['delivery_status' => $status];
    if ($status === 'failed' && $error !== '') {
        $upd['reason'] = substr($error, 0, 500);
        $upd['api_message'] = $error;
    } elseif ($status === 'delivered') {
        $upd['reason'] = 'Delivered on WhatsApp';
    } elseif ($status === 'read') {
        $upd['reason'] = 'Read by the customer';
    }
    $CI->db->where('id', (int)$row['id'])->update('whatsapp_logs', $upd);
}

function sk_whatsapp_order_vendor_id(array $order): int {
    $direct = (int)($order['vendor_id'] ?? 0);
    if ($direct > 0) {
        return $direct;
    }
    $CI =& get_instance();
    $orderId = (int)($order['id'] ?? 0);
    if ($orderId < 1) {
        return 0;
    }
    if ($CI->db->table_exists('order_items') && $CI->db->field_exists('vendor_id', 'order_items')) {
        $row = $CI->db->select('vendor_id')
            ->where('order_id', $orderId)
            ->where('vendor_id >', 0)
            ->limit(1)
            ->get('order_items')
            ->row_array();
        if (!empty($row['vendor_id'])) {
            return (int)$row['vendor_id'];
        }
    }
    if ($CI->db->table_exists('order_items') && $CI->db->table_exists('products') && $CI->db->field_exists('vendor_id', 'products')) {
        $row = $CI->db->select('p.vendor_id')
            ->from('order_items oi')
            ->join('products p', 'p.id = oi.product_id', 'inner')
            ->where('oi.order_id', $orderId)
            ->where('p.vendor_id >', 0)
            ->limit(1)
            ->get()
            ->row_array();
        if (!empty($row['vendor_id'])) {
            return (int)$row['vendor_id'];
        }
    }
    return 0;
}

function sk_whatsapp_shop_name(int $vendorId, array $settings = []): string {
    $CI =& get_instance();
    if ($vendorId > 0 && $CI->db->table_exists('vendor_stores')) {
        $store = $CI->db->select('store_name')->where('vendor_id', $vendorId)->get('vendor_stores')->row_array();
        $name = trim((string)($store['store_name'] ?? ''));
        if ($name !== '' && strcasecmp($name, 'Default Store') !== 0) {
            return $name;
        }
    }
    if ($vendorId > 0 && $CI->db->table_exists('vendors')) {
        $vendor = $CI->db->select('business_name')->where('id', $vendorId)->get('vendors')->row_array();
        $biz = trim((string)($vendor['business_name'] ?? ''));
        if ($biz !== '') {
            return $biz;
        }
    }
    return trim((string)($settings['site_name'] ?? $settings['company_legal_name'] ?? 'Shop'));
}

/**
 * Notify the customer through the shop's Meta order template.
 * The attempt is stored in whatsapp_logs for the delivery report.
 */
function sk_whatsapp_notify_order_status(array $order, string $status, array $settings = null): array {
    if ($status === 'payment_attempt') {
        return ['success' => false, 'message' => 'Skipped: payment attempt (notify after payment).', 'via' => 'none'];
    }
    $CI =& get_instance();
    $CI->load->helper('sk_whatsapp_cloud');
    if ($settings === null) {
        $CI->load->model('Sk_Admin_model');
        $settings = $CI->Sk_Admin_model->get_settings();
    }
    sk_whatsapp_ensure_log_schema();
    sk_wa_cloud_ensure_schema();

    $orderId = (int)($order['id'] ?? 0) ?: null;
    $orderNo = (string)($order['order_number'] ?? '');
    $baseLog = [
        'order_id'       => $orderId,
        'order_number'   => $orderNo,
        'status_trigger' => $status,
    ];
    $fail = static function (array $base, string $message, string $phone = '', string $source = 'none') {
        sk_whatsapp_log($base + [
            'phone'           => $phone !== '' ? $phone : null,
            'phone_source'    => $source,
            'channel'         => 'meta',
            'delivery_status' => 'failed',
            'reason'          => $message,
            'api_message'     => $message,
        ]);
        return ['success' => false, 'message' => $message, 'via' => 'meta'];
    };

    $event = sk_wa_ecomm_event_for_status($status);
    if ($event === '') {
        return $fail($baseLog, 'No order template for status "' . $status . '".');
    }

    $vendorId = sk_whatsapp_order_vendor_id($order);
    if ($vendorId < 1) {
        return $fail($baseLog, 'Order has no shop, so the Meta template cannot be chosen.');
    }
    $baseLog['vendor_id'] = $vendorId;
    sk_wa_ecomm_seed_vendor($vendorId, false);
    sk_wa_admin_seed_vendor($vendorId, false);
    if (!isset($CI->Sk_Whatsapp_cloud_model)) {
        $CI->load->model('Sk_Whatsapp_cloud_model');
    }
    $adminEvent = $event === 'order_created' ? 'order_placed' : ($event === 'order_cancelled' ? 'order_cancel_alert' : '');
    $adminTpl = $adminEvent !== '' ? $CI->Sk_Whatsapp_cloud_model->find_template_by_event($vendorId, $adminEvent) : null;
    $useAdmin = $adminTpl && strtoupper((string)($adminTpl['status'] ?? '')) === 'APPROVED';
    $tpl = $useAdmin ? $adminTpl : $CI->Sk_Whatsapp_cloud_model->find_template_by_event($vendorId, $event);
    if (!$tpl) {
        return $fail($baseLog, 'Order template "' . $event . '" is missing for this shop.');
    }
    $baseLog['template_name'] = (string)$tpl['name'];

    $phoneInfo = sk_whatsapp_order_phone_info($order, $settings);
    $phone = $phoneInfo['phone'];
    if ($phone === '') {
        return $fail($baseLog, 'No customer phone on this order.');
    }

    $settings['vendor_id'] = $vendorId;
    if (!sk_wa_cloud_is_ready($settings, $vendorId)) {
        return $fail($baseLog, sk_wa_cloud_not_ready_reason($settings, $vendorId), $phone, $phoneInfo['source']);
    }
    $metaStatus = strtoupper((string)($tpl['status'] ?? ''));
    if ($metaStatus !== 'APPROVED') {
        return $fail(
            $baseLog,
            'Template "' . $tpl['name'] . '" is ' . ($metaStatus !== '' ? $metaStatus : 'not approved') . ' on Meta.',
            $phone,
            $phoneInfo['source']
        );
    }

    $shop = sk_whatsapp_shop_name($vendorId, $settings);
    $CI->load->helper('sk_currency');
    $totalRaw = $order['total'] ?? '';
    $total = ($totalRaw !== '' && $totalRaw !== null && function_exists('sk_money'))
        ? sk_money($totalRaw, $settings)
        : (string)$totalRaw;
    $context = [
        'name'         => trim((string)($order['customer_name'] ?? $order['shipping_name'] ?? 'Customer')),
        'order_number' => $orderNo !== '' ? $orderNo : ('#' . (int)($order['id'] ?? 0)),
        'order_status' => sk_whatsapp_status_label($status),
        'order_total'  => $total !== '' ? $total : '-',
        'shop_name'    => $shop,
        'site_name'    => $shop,
    ];
    $built = sk_wa_cloud_send_components($tpl, $context);
    $msg = sk_whatsapp_order_message($order, $status, $settings);

    if (isset($CI->whatsapp_cloud)) {
        unset($CI->whatsapp_cloud);
    }
    $CI->load->library('Whatsapp_cloud', $settings);
    $sent = $CI->whatsapp_cloud->send_template(
        $phone,
        (string)$tpl['name'],
        (string)($tpl['language'] ?: 'en'),
        $built['components']
    );
    $ok = !empty($sent['success']);
    $wamid = (string)($sent['data']['messages'][0]['id'] ?? '');
    $message = $ok
        ? ('Sent via Meta template "' . $tpl['name'] . '"')
        : ('Meta template "' . $tpl['name'] . '" failed: ' . (string)($sent['message'] ?? 'send failed'));
    sk_whatsapp_log($baseLog + [
        'phone'           => $phone,
        'phone_source'    => $phoneInfo['source'],
        'channel'         => 'meta',
        'template_name'   => (string)$tpl['name'],
        'wamid'           => $wamid !== '' ? $wamid : null,
        'delivery_status' => $ok ? 'sent' : 'failed',
        'reason'          => $message,
        'http_code'       => $sent['http'] ?? null,
        'api_message'     => $sent['message'] ?? $message,
        'api_response'    => $sent['data'] ?? $sent,
        'message_body'    => $msg,
    ]);
    log_message('info', 'Meta WA order ' . ($order['id'] ?? '?') . ' status=' . $status
        . ' tpl=' . $tpl['name'] . ' ok=' . ($ok ? '1' : '0') . ' to=' . $phone);

    sk_whatsapp_fanout_order_alerts($order, $status, $settings, $phone, (string)$tpl['name']);

    return ['success' => $ok, 'message' => $message, 'via' => 'meta:' . $tpl['name'], 'response' => $sent['data'] ?? null];
}

/**
 * Extra admin templates for the same order event.
 * Customer already received $sentTemplate. Owners get that message too when it
 * is an admin template, plus the owner-only alert.
 */
function sk_whatsapp_fanout_order_alerts(array $order, string $status, array $settings, string $customerPhone, string $sentTemplate): void {
    $vendorId = sk_whatsapp_order_vendor_id($order);
    if ($vendorId < 1) {
        return;
    }
    $event = sk_wa_ecomm_event_for_status($status);
    $context = sk_whatsapp_alert_context($order, $settings);
    $owners = sk_whatsapp_owner_phones($vendorId);
    $customer = sk_whatsapp_unique_phones([$customerPhone]);
    $log = [
        'order_id'       => (int)($order['id'] ?? 0) ?: null,
        'order_number'   => (string)($order['order_number'] ?? ''),
        'status_trigger' => $status,
        'vendor_id'      => $vendorId,
    ];
    if ($event === 'order_created') {
        $placedTo = $sentTemplate === 'order_placed' ? $owners : array_merge($customer, $owners);
        sk_whatsapp_send_shop_event($vendorId, 'order_placed', $context, $placedTo, $settings, $log, $sentTemplate === 'order_placed' ? $customerPhone : '');
        sk_whatsapp_send_shop_event($vendorId, 'new_order_admin_alert', $context, $owners, $settings, $log);
    } elseif ($event === 'order_cancelled') {
        $cancelTo = $sentTemplate === 'order_cancelled' ? $owners : array_merge($customer, $owners);
        sk_whatsapp_send_shop_event($vendorId, 'order_cancel_alert', $context, $cancelTo, $settings, $log, $sentTemplate === 'order_cancelled' ? $customerPhone : '');
    }
}

function sk_whatsapp_unique_phones(array $phones): array {
    $CI =& get_instance();
    $CI->load->helper('sk_whatsapp_cloud');
    $out = [];
    foreach ($phones as $phone) {
        $norm = sk_wa_cloud_normalize_phone((string)$phone);
        if ($norm !== '') {
            $out[$norm] = $norm;
        }
    }
    return array_values($out);
}

function sk_whatsapp_owner_phones(int $vendorId): array {
    $CI =& get_instance();
    $phones = [];
    if ($vendorId > 0 && $CI->db->table_exists('vendor_stores')) {
        $store = $CI->db->select('contact_phone')->where('vendor_id', $vendorId)->get('vendor_stores')->row_array();
        $phones[] = (string)($store['contact_phone'] ?? '');
    }
    if ($vendorId > 0 && $CI->db->table_exists('vendors')) {
        $vendor = $CI->db->select('phone')->where('id', $vendorId)->get('vendors')->row_array();
        $phones[] = (string)($vendor['phone'] ?? '');
    }
    return sk_whatsapp_unique_phones($phones);
}

function sk_whatsapp_platform_phones(array $settings): array {
    return sk_whatsapp_unique_phones([(string)($settings['site_phone'] ?? '')]);
}

function sk_whatsapp_alert_context(array $order, array $settings, array $extra = []): array {
    $CI =& get_instance();
    $CI->load->helper('sk_currency');
    $vendorId = sk_whatsapp_order_vendor_id($order);
    $shop = sk_whatsapp_shop_name($vendorId, $settings);
    if ($shop === '') {
        $shop = 'the shop';
    }
    $name = trim((string)($order['customer_name'] ?? $order['shipping_name'] ?? ''));
    if ($name === '') {
        $name = 'Customer';
    }
    $orderNo = trim((string)($order['order_number'] ?? ''));
    if ($orderNo === '') {
        $orderNo = '#' . (int)($order['id'] ?? 0);
    }
    $totalRaw = $order['total'] ?? '';
    $total = ($totalRaw !== '' && $totalRaw !== null && function_exists('sk_money'))
        ? sk_money($totalRaw, $settings)
        : (string)$totalRaw;
    if ($total === '') {
        $total = '0';
    }
    $items = [];
    foreach (($order['items'] ?? []) as $item) {
        $label = trim((string)($item['product_name'] ?? 'Item'));
        $qty = (int)($item['quantity'] ?? 1);
        if ($label !== '') {
            $items[] = $label . ' x' . $qty;
        }
    }
    $itemSummary = $items ? implode(', ', $items) : 'items in this order';
    if (function_exists('mb_substr')) {
        $itemSummary = mb_substr($itemSummary, 0, 180);
    }
    $invoiceNo = trim((string)($extra['invoice_no'] ?? $orderNo));
    $refund = trim((string)($order['refund_status'] ?? ''));
    if ($refund === '' && strtolower((string)($order['payment_status'] ?? '')) === 'refunded') {
        $refund = 'initiated';
    }
    $refund = $refund !== '' ? ucfirst($refund) : 'Updated';
    return array_merge([
        'name'           => $name,
        'customer_name' => $name,
        'customer_phone'=> trim((string)($order['shipping_phone'] ?? 'the customer')),
        'order_number'  => $orderNo,
        'order_total'   => $total,
        'order_status'  => sk_whatsapp_status_label((string)($order['status'] ?? '')),
        'shop_name'     => $shop,
        'site_name'     => $shop,
        'item_summary'  => $itemSummary,
        'invoice_no'    => $invoiceNo !== '' ? $invoiceNo : $orderNo,
        'refund_status' => $refund,
        'reason'        => 'the customer needs help',
        'admin_name'    => 'Admin',
        'owner_name'    => 'there',
        'display_phone' => 'the requested number',
        'enroll_status' => 'pending',
    ], $extra);
}

function sk_whatsapp_send_shop_event(int $vendorId, string $eventKey, array $context, array $phones, array $settings = null, array $logBase = [], string $skipPhone = ''): int {
    if ($vendorId < 1 || $eventKey === '') {
        return 0;
    }
    $CI =& get_instance();
    $CI->load->helper('sk_whatsapp_cloud');
    if ($settings === null) {
        $CI->load->model('Sk_Admin_model');
        $settings = $CI->Sk_Admin_model->get_settings();
    }
    sk_whatsapp_ensure_log_schema();
    sk_wa_admin_seed_vendor($vendorId, false);
    if (!isset($CI->Sk_Whatsapp_cloud_model)) {
        $CI->load->model('Sk_Whatsapp_cloud_model');
    }
    $tpl = $CI->Sk_Whatsapp_cloud_model->find_template_by_event($vendorId, $eventKey);
    if (!$tpl || strtoupper((string)($tpl['status'] ?? '')) !== 'APPROVED') {
        return 0;
    }
    $phones = sk_whatsapp_unique_phones($phones);
    $skip = sk_wa_cloud_normalize_phone($skipPhone);
    $settings['vendor_id'] = $vendorId;
    if (!sk_wa_cloud_is_ready($settings, $vendorId)) {
        return 0;
    }
    if ($eventKey === 'human_reply_alert' && sk_whatsapp_event_sent_recently($vendorId, (string)$tpl['name'], 15)) {
        return 0;
    }
    $built = sk_wa_cloud_send_components($tpl, $context);
    if (isset($CI->whatsapp_cloud)) {
        unset($CI->whatsapp_cloud);
    }
    $CI->load->library('Whatsapp_cloud', $settings);
    $sentCount = 0;
    foreach ($phones as $phone) {
        if ($skip !== '' && $phone === $skip) {
            continue;
        }
        $sent = $CI->whatsapp_cloud->send_template(
            $phone,
            (string)$tpl['name'],
            (string)($tpl['language'] ?: 'en'),
            $built['components']
        );
        $ok = !empty($sent['success']);
        if ($ok) {
            $sentCount++;
        }
        $wamid = (string)($sent['data']['messages'][0]['id'] ?? '');
        $message = $ok
            ? ('Sent via Meta template "' . $tpl['name'] . '"')
            : ('Meta template "' . $tpl['name'] . '" failed: ' . (string)($sent['message'] ?? 'send failed'));
        sk_whatsapp_log($logBase + [
            'vendor_id'       => $vendorId,
            'phone'           => $phone,
            'phone_source'    => 'alert',
            'channel'         => 'meta',
            'template_name'   => (string)$tpl['name'],
            'wamid'           => $wamid !== '' ? $wamid : null,
            'delivery_status' => $ok ? 'sent' : 'failed',
            'reason'          => $message,
            'http_code'       => $sent['http'] ?? null,
            'api_message'     => $sent['message'] ?? $message,
            'api_response'    => $sent['data'] ?? $sent,
            'status_trigger'  => $logBase['status_trigger'] ?? $eventKey,
        ]);
    }
    return $sentCount;
}

function sk_whatsapp_event_sent_recently(int $vendorId, string $template, int $minutes): bool {
    $CI =& get_instance();
    if (!$CI->db->table_exists('whatsapp_logs') || $template === '') {
        return false;
    }
    $since = date('Y-m-d H:i:s', time() - ($minutes * 60));
    $row = $CI->db->select('id')
        ->where('vendor_id', $vendorId)
        ->where('template_name', $template)
        ->where('created_at >=', $since)
        ->limit(1)
        ->get('whatsapp_logs')
        ->row_array();
    return !empty($row);
}

function sk_whatsapp_notify_payment_result(array $order, bool $paid, array $settings = null): void {
    $vendorId = sk_whatsapp_order_vendor_id($order);
    if ($vendorId < 1) {
        return;
    }
    if ($settings === null) {
        $CI =& get_instance();
        $CI->load->model('Sk_Admin_model');
        $settings = $CI->Sk_Admin_model->get_settings();
    }
    $context = sk_whatsapp_alert_context($order, $settings);
    $log = [
        'order_id'       => (int)($order['id'] ?? 0) ?: null,
        'order_number'   => (string)($order['order_number'] ?? ''),
        'status_trigger' => $paid ? 'payment_success' : 'payment_failed',
        'vendor_id'      => $vendorId,
    ];
    if ($paid) {
        $phones = array_merge(
            sk_whatsapp_unique_phones([(string)($order['shipping_phone'] ?? '')]),
            sk_whatsapp_owner_phones($vendorId)
        );
        sk_whatsapp_send_shop_event($vendorId, 'payment_success', $context, $phones, $settings, $log);
        return;
    }
    sk_whatsapp_send_shop_event($vendorId, 'payment_failed_admin_alert', $context, sk_whatsapp_owner_phones($vendorId), $settings, $log);
}

function sk_whatsapp_notify_invoice(array $order, array $settings = null, string $invoiceNo = ''): void {
    $vendorId = sk_whatsapp_order_vendor_id($order);
    if ($vendorId < 1) {
        return;
    }
    if ($settings === null) {
        $CI =& get_instance();
        $CI->load->model('Sk_Admin_model');
        $settings = $CI->Sk_Admin_model->get_settings();
    }
    if (sk_whatsapp_event_sent_recently($vendorId, 'invoice_created', 60)) {
        return;
    }
    $context = sk_whatsapp_alert_context($order, $settings, ['invoice_no' => $invoiceNo]);
    $log = [
        'order_id'       => (int)($order['id'] ?? 0) ?: null,
        'order_number'   => (string)($order['order_number'] ?? ''),
        'status_trigger' => 'invoice_created',
        'vendor_id'      => $vendorId,
    ];
    sk_whatsapp_send_shop_event(
        $vendorId,
        'invoice_created',
        $context,
        sk_whatsapp_unique_phones([(string)($order['shipping_phone'] ?? '')]),
        $settings,
        $log
    );
    sk_whatsapp_send_shop_event($vendorId, 'invoice_shared_admin_alert', $context, sk_whatsapp_owner_phones($vendorId), $settings, $log);
}

function sk_whatsapp_notify_refund(array $order, array $settings = null): void {
    $vendorId = sk_whatsapp_order_vendor_id($order);
    if ($vendorId < 1) {
        return;
    }
    if ($settings === null || $settings === []) {
        $CI =& get_instance();
        $CI->load->model('Sk_Admin_model');
        $settings = $CI->Sk_Admin_model->get_settings();
    }
    $context = sk_whatsapp_alert_context($order, $settings);
    $log = [
        'order_id'       => (int)($order['id'] ?? 0) ?: null,
        'order_number'   => (string)($order['order_number'] ?? ''),
        'status_trigger' => 'refund_status_update',
        'vendor_id'      => $vendorId,
    ];
    $phones = array_merge(
        sk_whatsapp_unique_phones([(string)($order['shipping_phone'] ?? '')]),
        sk_whatsapp_owner_phones($vendorId)
    );
    sk_whatsapp_send_shop_event($vendorId, 'refund_status_update', $context, $phones, $settings, $log);
}

function sk_whatsapp_notify_handoff(int $vendorId, string $customerPhone, string $reason, string $customerName = '', bool $reply = false): void {
    if ($vendorId < 1) {
        return;
    }
    $CI =& get_instance();
    $CI->load->model('Sk_Admin_model');
    $settings = $CI->Sk_Admin_model->get_settings();
    $name = trim($customerName) !== '' ? trim($customerName) : 'Customer';
    $note = trim($reason) !== '' ? trim($reason) : 'the customer needs help';
    if (function_exists('mb_substr')) {
        $note = mb_substr($note, 0, 180);
    }
    $context = sk_whatsapp_alert_context([
        'customer_name' => $name,
        'shipping_phone'=> $customerPhone,
        'vendor_id'     => $vendorId,
    ], $settings, [
        'reason'         => $note,
        'customer_phone' => $customerPhone !== '' ? $customerPhone : 'the customer',
        'customer_name'  => $name,
        'name'           => $name,
    ]);
    $event = $reply ? 'human_reply_alert' : 'human_handoff_alert';
    sk_whatsapp_send_shop_event($vendorId, $event, $context, sk_whatsapp_owner_phones($vendorId), $settings, [
        'vendor_id'      => $vendorId,
        'status_trigger' => $event,
        'order_number'   => '',
    ]);
}

function sk_whatsapp_notify_enrollment(int $vendorId, string $status, string $displayPhone, array $settings = null): void {
    if ($vendorId < 1) {
        return;
    }
    $CI =& get_instance();
    if ($settings === null) {
        $CI->load->model('Sk_Admin_model');
        $settings = $CI->Sk_Admin_model->get_settings();
    }
    $shop = sk_whatsapp_shop_name($vendorId, $settings);
    $phoneLabel = trim($displayPhone) !== '' ? trim($displayPhone) : 'a new number';
    $statusLabel = ucfirst(strtolower($status));
    if (!in_array(strtolower($status), ['approved', 'rejected', 'pending'], true)) {
        $statusLabel = 'Pending';
    }
    $context = sk_whatsapp_alert_context(['vendor_id' => $vendorId], $settings, [
        'shop_name'     => $shop !== '' ? $shop : 'the shop',
        'display_phone' => $phoneLabel,
        'enroll_status' => $statusLabel,
        'admin_name'    => 'Admin',
        'owner_name'    => 'there',
    ]);
    if (strtolower($status) === 'request') {
        sk_whatsapp_send_shop_event($vendorId, 'whatsapp_number_enrollment_request', $context, sk_whatsapp_platform_phones($settings), $settings, [
            'vendor_id'      => $vendorId,
            'status_trigger' => 'whatsapp_number_enrollment_request',
        ]);
        return;
    }
    sk_whatsapp_send_shop_event($vendorId, 'whatsapp_number_enrollment_status', $context, sk_whatsapp_owner_phones($vendorId), $settings, [
        'vendor_id'      => $vendorId,
        'status_trigger' => 'whatsapp_number_enrollment_status',
    ]);
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
    $site = $settings['site_name'] ?? 'Talk AI Pilot';
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
    $lines[] = 'Thank you for shopping with Talk AI Pilot.';
    return implode("\n", $lines);
}

/** Send a session text through the shop's Meta Cloud API. */
function sk_whatsapp_send_text(string $to, string $body, array $settings = null): array {
    $CI =& get_instance();
    $CI->load->helper('sk_whatsapp_cloud');
    if ($settings === null) {
        $CI->load->model('Sk_Admin_model');
        $settings = $CI->Sk_Admin_model->get_settings();
    }
    $to = sk_wa_cloud_normalize_phone($to);
    if ($to === '') {
        return ['success' => false, 'message' => 'Invalid phone.'];
    }
    if (!sk_wa_cloud_is_ready($settings)) {
        return ['success' => false, 'message' => sk_wa_cloud_not_ready_reason($settings)];
    }
    if (isset($CI->whatsapp_cloud)) {
        unset($CI->whatsapp_cloud);
    }
    $CI->load->library('Whatsapp_cloud', $settings);
    return $CI->whatsapp_cloud->send_text($to, $body);
}

/**
 * Build Meta template body parameters.
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

/** Send an approved template through the shop's Meta Cloud API. */
function sk_whatsapp_send_template(string $to, string $templateName, array $bodyValues, array $settings = null): array {
    $CI =& get_instance();
    $CI->load->helper('sk_whatsapp_cloud');
    if ($settings === null) {
        $CI->load->model('Sk_Admin_model');
        $settings = $CI->Sk_Admin_model->get_settings();
    }
    $templateName = trim($templateName);
    $to = sk_wa_cloud_normalize_phone($to);
    if ($templateName === '') {
        return ['success' => false, 'message' => 'Template not configured.'];
    }
    if ($to === '') {
        return ['success' => false, 'message' => 'Invalid phone.'];
    }
    if (!sk_wa_cloud_is_ready($settings)) {
        return ['success' => false, 'message' => sk_wa_cloud_not_ready_reason($settings)];
    }
    $params = [];
    foreach ($bodyValues as $p) {
        $params[] = ['type' => 'text', 'text' => sk_whatsapp_sanitize_param((string)$p)];
    }
    $components = $params ? [['type' => 'body', 'parameters' => $params]] : [];
    if (isset($CI->whatsapp_cloud)) {
        unset($CI->whatsapp_cloud);
    }
    $CI->load->library('Whatsapp_cloud', $settings);
    $lang = 'en';
    $CI->load->model('Sk_Whatsapp_cloud_model');
    $vid = (int)($settings['vendor_id'] ?? 0);
    $row = $vid > 0 ? $CI->Sk_Whatsapp_cloud_model->find_template_by_name($vid, $templateName) : null;
    if (!empty($row['language'])) {
        $lang = (string)$row['language'];
    }
    return $CI->whatsapp_cloud->send_template($to, $templateName, $lang, $components);
}
