<?php
defined('BASEPATH') OR exit('No direct script access allowed');

function sk_wa_cloud_ensure_schema(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $CI =& get_instance();
    if (!isset($CI->db)) {
        $CI->load->database();
    }

    if (!$CI->db->table_exists('wa_cloud_templates')) {
        $CI->db->query("CREATE TABLE `wa_cloud_templates` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(512) NOT NULL,
            `language` VARCHAR(16) NOT NULL DEFAULT 'en',
            `category` VARCHAR(32) NOT NULL DEFAULT 'UTILITY',
            `kind` VARCHAR(16) NOT NULL DEFAULT 'text',
            `body_text` TEXT NULL,
            `header_text` VARCHAR(60) NULL,
            `footer_text` VARCHAR(60) NULL,
            `media_url` VARCHAR(512) NULL,
            `media_handle` VARCHAR(255) NULL,
            `meta_id` VARCHAR(64) NULL,
            `status` VARCHAR(32) NOT NULL DEFAULT 'DRAFT',
            `meta_payload` MEDIUMTEXT NULL,
            `variable_map` TEXT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_name_lang` (`name`(191), `language`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    if ($CI->db->table_exists('wa_cloud_templates') && !$CI->db->field_exists('variable_map', 'wa_cloud_templates')) {
        $CI->db->query("ALTER TABLE `wa_cloud_templates` ADD COLUMN `variable_map` TEXT NULL AFTER `meta_payload`");
    }

    if (!$CI->db->table_exists('wa_cloud_campaigns')) {
        $CI->db->query("CREATE TABLE `wa_cloud_campaigns` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(190) NOT NULL,
            `template_id` INT UNSIGNED NOT NULL,
            `status` VARCHAR(24) NOT NULL DEFAULT 'draft',
            `total` INT UNSIGNED NOT NULL DEFAULT 0,
            `queued` INT UNSIGNED NOT NULL DEFAULT 0,
            `sent` INT UNSIGNED NOT NULL DEFAULT 0,
            `delivered` INT UNSIGNED NOT NULL DEFAULT 0,
            `read_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `failed` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            `started_at` DATETIME NULL,
            `finished_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_tpl` (`template_id`),
            KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    if (!$CI->db->table_exists('wa_cloud_campaign_recipients')) {
        $CI->db->query("CREATE TABLE `wa_cloud_campaign_recipients` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `campaign_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NULL,
            `phone` VARCHAR(32) NOT NULL,
            `name` VARCHAR(160) NULL,
            `status` VARCHAR(24) NOT NULL DEFAULT 'queued',
            `wamid` VARCHAR(128) NULL,
            `error_text` VARCHAR(500) NULL,
            `variables_json` TEXT NULL,
            `sent_at` DATETIME NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_camp_status` (`campaign_id`, `status`),
            KEY `idx_wamid` (`wamid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    if (!$CI->db->table_exists('wa_cloud_conversations')) {
        $CI->db->query("CREATE TABLE `wa_cloud_conversations` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `phone` VARCHAR(32) NOT NULL,
            `name` VARCHAR(160) NULL,
            `last_message` VARCHAR(500) NULL,
            `last_direction` VARCHAR(16) NULL,
            `last_at` DATETIME NULL,
            `unread` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_phone` (`phone`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    if (!$CI->db->table_exists('wa_cloud_messages')) {
        $CI->db->query("CREATE TABLE `wa_cloud_messages` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `conversation_id` INT UNSIGNED NOT NULL,
            `wamid` VARCHAR(128) NULL,
            `direction` VARCHAR(8) NOT NULL,
            `type` VARCHAR(16) NOT NULL DEFAULT 'text',
            `body` MEDIUMTEXT NULL,
            `media_url` VARCHAR(512) NULL,
            `media_id` VARCHAR(128) NULL,
            `template_name` VARCHAR(512) NULL,
            `status` VARCHAR(24) NOT NULL DEFAULT 'queued',
            `error_text` VARCHAR(500) NULL,
            `raw_json` MEDIUMTEXT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_conv` (`conversation_id`, `id`),
            KEY `idx_wamid` (`wamid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    $defaults = [
        'wa_cloud_enabled'         => '0',
        'wa_cloud_access_token'    => '',
        'wa_cloud_phone_number_id' => '',
        'wa_cloud_waba_id'         => '',
        'wa_cloud_app_secret'      => '',
        'wa_cloud_verify_token'    => '2deal-wa-verify',
        'wa_cloud_api_version'     => 'v21.0',
        'wa_mcp_enabled'           => '0',
        'wa_mcp_url'               => '',
        'wa_mcp_token'             => '',
        'wa_mcp_timeout'           => '12',
    ];
    $hasGroup = $CI->db->field_exists('group', 'settings');
    foreach ($defaults as $key => $value) {
        if ($CI->db->get_where('settings', ['key' => $key], 1)->row_array()) {
            continue;
        }
        $row = ['key' => $key, 'value' => (string)$value];
        if ($hasGroup) {
            $row['group'] = 'whatsapp';
        }
        $CI->db->insert('settings', $row);
    }
}

function sk_wa_cloud_config(?array $settings = null): array {
    sk_wa_cloud_ensure_schema();
    if ($settings === null) {
        $CI =& get_instance();
        $CI->load->model('Sk_Admin_model');
        $settings = $CI->Sk_Admin_model->get_settings();
    }
    $version = trim((string)($settings['wa_cloud_api_version'] ?? 'v21.0')) ?: 'v21.0';
    if ($version[0] !== 'v') {
        $version = 'v' . $version;
    }
    return [
        'enabled'         => !empty($settings['wa_cloud_enabled']) && $settings['wa_cloud_enabled'] !== '0',
        'access_token'    => trim((string)($settings['wa_cloud_access_token'] ?? '')),
        'phone_number_id' => trim((string)($settings['wa_cloud_phone_number_id'] ?? '')),
        'waba_id'         => trim((string)($settings['wa_cloud_waba_id'] ?? '')),
        'app_secret'      => trim((string)($settings['wa_cloud_app_secret'] ?? '')),
        'verify_token'    => trim((string)($settings['wa_cloud_verify_token'] ?? '2deal-wa-verify')),
        'api_version'     => $version,
        'graph_base'      => 'https://graph.facebook.com/' . $version,
    ];
}

function sk_wa_cloud_is_ready(?array $settings = null): bool {
    $cfg = sk_wa_cloud_config($settings);
    return $cfg['enabled']
        && $cfg['access_token'] !== ''
        && $cfg['phone_number_id'] !== ''
        && $cfg['waba_id'] !== '';
}

function sk_wa_cloud_normalize_phone(string $phone): string {
    $digits = preg_replace('/\D+/', '', $phone);
    return $digits !== null ? $digits : '';
}

function sk_wa_cloud_upload_dir(): string {
    $dir = FCPATH . 'assets/uploads/whatsapp/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function sk_wa_cloud_public_url(string $filename): string {
    return base_url('assets/uploads/whatsapp/' . ltrim($filename, '/'));
}

/** Customer detail modules that can be mapped onto {{1}}, {{2}} template slots. */
function sk_wa_cloud_customer_modules(): array {
    return [
        'name'              => ['label' => 'Full name', 'group' => 'Customer'],
        'first_name'        => ['label' => 'First name', 'group' => 'Customer'],
        'last_name'         => ['label' => 'Last name', 'group' => 'Customer'],
        'email'             => ['label' => 'Email', 'group' => 'Customer'],
        'phone'             => ['label' => 'Phone', 'group' => 'Customer'],
        'company'           => ['label' => 'Company', 'group' => 'Address'],
        'address_line1'     => ['label' => 'Address line 1', 'group' => 'Address'],
        'address_line2'     => ['label' => 'Address line 2', 'group' => 'Address'],
        'city'              => ['label' => 'City', 'group' => 'Address'],
        'state'             => ['label' => 'State', 'group' => 'Address'],
        'pincode'           => ['label' => 'Postcode', 'group' => 'Address'],
        'country'           => ['label' => 'Country', 'group' => 'Address'],
        'full_address'      => ['label' => 'Full address', 'group' => 'Address'],
        'last_order_number' => ['label' => 'Last order no.', 'group' => 'Orders'],
        'last_order_total'  => ['label' => 'Last order total', 'group' => 'Orders'],
        'last_order_status' => ['label' => 'Last order status', 'group' => 'Orders'],
        'site_name'         => ['label' => 'Store name', 'group' => 'Store'],
    ];
}

function sk_wa_cloud_decode_variable_map($raw): array {
    if (is_array($raw)) {
        return $raw;
    }
    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $out = [];
    foreach ($decoded as $k => $v) {
        $idx = (string)(int)$k;
        if ($idx === '0' && (string)$k !== '0') {
            continue;
        }
        $field = preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string)$v)));
        if ($field !== '') {
            $out[$idx] = $field;
        }
    }
    return $out;
}

function sk_wa_cloud_placeholder_indexes(string $text): array {
    if (!preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $text, $m)) {
        return [];
    }
    $nums = array_map('intval', $m[1]);
    $nums = array_values(array_unique(array_filter($nums, static function ($n) {
        return $n > 0;
    })));
    sort($nums, SORT_NUMERIC);
    return $nums;
}

function sk_wa_cloud_param_text(string $value): string {
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    if ($value === '') {
        return '-';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, 1024);
    }
    return substr($value, 0, 1024);
}

function sk_wa_cloud_split_name(string $name): array {
    $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
    if ($name === '') {
        return ['', ''];
    }
    $parts = explode(' ', $name, 2);
    return [$parts[0], $parts[1] ?? ''];
}

function sk_wa_cloud_load_customer_context(?array $user, ?array $settings = null): array {
    $CI =& get_instance();
    if ($settings === null && isset($CI->Sk_Admin_model)) {
        $settings = $CI->Sk_Admin_model->get_settings();
    }
    $settings = is_array($settings) ? $settings : [];
    $user = is_array($user) ? $user : [];
    $userId = (int)($user['id'] ?? 0);
    $addr = [];
    if ($userId > 0 && $CI->db->table_exists('addresses')) {
        $addr = $CI->db->where('user_id', $userId)
            ->order_by('is_default', 'DESC')
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get('addresses')
            ->row_array() ?: [];
    }
    $order = [];
    if ($userId > 0 && $CI->db->table_exists('orders')) {
        $q = $CI->db->where('user_id', $userId);
        if ($CI->db->field_exists('status', 'orders')) {
            $q->where_not_in('status', ['abandoned', 'payment_attempt']);
        }
        $order = $q->order_by('created_at', 'DESC')->limit(1)->get('orders')->row_array() ?: [];
    }
    $name = trim((string)($user['name'] ?? ''));
    [$first, $last] = sk_wa_cloud_split_name($name);
    $line1 = trim((string)($addr['line1'] ?? ''));
    $line2 = trim((string)($addr['line2'] ?? ''));
    $city = trim((string)($addr['city'] ?? ''));
    $state = trim((string)($addr['state'] ?? ''));
    $pin = trim((string)($addr['pincode'] ?? ''));
    $country = trim((string)($addr['country'] ?? ''));
    $fullAddr = implode(', ', array_filter([$line1, $line2, $city, $state, $pin, $country]));
    $total = $order['total'] ?? $order['grand_total'] ?? '';
    $orderTotal = '';
    if ($total !== '' && $total !== null) {
        $CI->load->helper('sk_currency');
        $orderTotal = function_exists('sk_money') ? sk_money($total, $settings) : number_format((float)$total, 2);
    }
    return [
        'name'              => $name,
        'first_name'        => $first,
        'last_name'         => $last,
        'email'             => trim((string)($user['email'] ?? '')),
        'phone'             => trim((string)($user['phone'] ?? '')),
        'company'           => trim((string)($addr['company_name'] ?? $user['company_name'] ?? '')),
        'address_line1'     => $line1,
        'address_line2'     => $line2,
        'city'              => $city,
        'state'             => $state,
        'pincode'           => $pin,
        'country'           => $country,
        'full_address'      => $fullAddr,
        'last_order_number' => trim((string)($order['order_number'] ?? '')),
        'last_order_total'  => $orderTotal,
        'last_order_status' => trim((string)($order['status'] ?? '')),
        'site_name'         => trim((string)($settings['site_name'] ?? $settings['company_legal_name'] ?? '2DEAL')),
    ];
}

function sk_wa_cloud_context_value(array $context, string $field): string {
    return trim((string)($context[$field] ?? ''));
}

/**
 * Build Meta send components + resolved {{n}} values from the template map.
 *
 * @return array{components: array, resolved: array}
 */
function sk_wa_cloud_send_components(array $template, array $context): array {
    $map = sk_wa_cloud_decode_variable_map($template['variable_map'] ?? '');
    $indexes = sk_wa_cloud_placeholder_indexes((string)($template['body_text'] ?? ''));
    $max = $indexes ? max($indexes) : 0;
    $resolved = [];
    $params = [];
    for ($i = 1; $i <= $max; $i++) {
        $key = (string)$i;
        $field = (string)($map[$key] ?? '');
        $val = $field !== '' ? sk_wa_cloud_context_value($context, $field) : '';
        $val = sk_wa_cloud_param_text($val);
        $resolved[$key] = $val;
        $params[] = ['type' => 'text', 'text' => $val];
    }
    $components = [];
    $kind = (string)($template['kind'] ?? 'text');
    if (($kind === 'image' || $kind === 'video') && !empty($template['media_url'])) {
        $components[] = [
            'type'       => 'header',
            'parameters' => [[
                'type' => $kind,
                $kind  => ['link' => $template['media_url']],
            ]],
        ];
    }
    if ($params) {
        $components[] = [
            'type'       => 'body',
            'parameters' => $params,
        ];
    }
    return ['components' => $components, 'resolved' => $resolved];
}
