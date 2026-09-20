<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sk_Saas_Billing_model extends CI_Model {

    public function ensure_schema(): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        if (!$this->db->table_exists('saas_client_amounts')) {
            $this->db->query(
                "CREATE TABLE `saas_client_amounts` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `vendor_id` INT UNSIGNED NULL DEFAULT NULL,
                    `request_type` VARCHAR(64) NOT NULL DEFAULT 'ai_chat',
                    `unit_amount` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
                    `currency` VARCHAR(8) NOT NULL DEFAULT 'INR',
                    `label` VARCHAR(160) NULL DEFAULT NULL,
                    `status` TINYINT(1) NOT NULL DEFAULT 1,
                    `created_at` DATETIME NOT NULL,
                    `updated_at` DATETIME NOT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uniq_vendor_type` (`vendor_id`, `request_type`),
                    KEY `idx_type_status` (`request_type`, `status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        }

        if (!$this->db->table_exists('saas_ai_requests')) {
            $this->db->query(
                "CREATE TABLE `saas_ai_requests` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `request_code` VARCHAR(64) NOT NULL,
                    `vendor_id` INT UNSIGNED NOT NULL,
                    `request_type` VARCHAR(64) NOT NULL DEFAULT 'ai_chat',
                    `source` VARCHAR(24) NOT NULL DEFAULT 'api',
                    `status` VARCHAR(24) NOT NULL DEFAULT 'ok',
                    `units` DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
                    `unit_amount` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
                    `total_amount` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
                    `meta` MEDIUMTEXT NULL,
                    `external_id` VARCHAR(128) NULL DEFAULT NULL,
                    `billed_at` DATETIME NULL DEFAULT NULL,
                    `created_at` DATETIME NOT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uniq_request_code` (`request_code`),
                    UNIQUE KEY `uniq_source_external` (`source`, `external_id`),
                    KEY `idx_vendor_created` (`vendor_id`, `created_at`),
                    KEY `idx_billed` (`billed_at`),
                    KEY `idx_type` (`request_type`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        }

        if (!$this->db->table_exists('saas_daily_bills')) {
            $this->db->query(
                "CREATE TABLE `saas_daily_bills` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `vendor_id` INT UNSIGNED NOT NULL,
                    `bill_date` DATE NOT NULL,
                    `request_count` INT UNSIGNED NOT NULL DEFAULT 0,
                    `total_amount` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
                    `status` VARCHAR(16) NOT NULL DEFAULT 'final',
                    `created_at` DATETIME NOT NULL,
                    `updated_at` DATETIME NOT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uniq_vendor_date` (`vendor_id`, `bill_date`),
                    KEY `idx_bill_date` (`bill_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        }

        $this->_seed_default_amounts();
    }

    protected function _seed_default_amounts(): void {
        $now = date('Y-m-d H:i:s');
        foreach (['ai_chat' => 'AI chat request', 'whatsapp_ai' => 'WhatsApp AI reply', 'custom' => 'Custom AI request'] as $type => $label) {
            $exists = $this->db->where('vendor_id IS NULL', null, false)
                ->where('request_type', $type)
                ->count_all_results('saas_client_amounts');
            if ($exists) {
                continue;
            }
            $this->db->insert('saas_client_amounts', [
                'vendor_id'    => null,
                'request_type' => $type,
                'unit_amount'  => 0.0000,
                'currency'     => 'INR',
                'label'        => $label,
                'status'       => 1,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    /** Vendor override → global → 0 */
    public function resolve_unit_amount(string $requestType, ?int $vendorId = null): float {
        $this->ensure_schema();
        $requestType = $this->_normalize_type($requestType);

        if ($vendorId) {
            $row = $this->db->where('vendor_id', (int)$vendorId)
                ->where('request_type', $requestType)
                ->where('status', 1)
                ->get('saas_client_amounts')
                ->row_array();
            if ($row) {
                return (float)$row['unit_amount'];
            }
        }

        $global = $this->db->where('vendor_id IS NULL', null, false)
            ->where('request_type', $requestType)
            ->where('status', 1)
            ->get('saas_client_amounts')
            ->row_array();
        return $global ? (float)$global['unit_amount'] : 0.0;
    }

    public function log_request(array $data): array {
        $this->ensure_schema();

        $vendorId = (int)($data['vendor_id'] ?? 0);
        if ($vendorId < 1) {
            return ['ok' => false, 'message' => 'vendor_id is required.'];
        }

        $type = $this->_normalize_type((string)($data['request_type'] ?? 'ai_chat'));
        $source = in_array(($data['source'] ?? 'api'), ['api', 'whatsapp'], true) ? $data['source'] : 'api';
        $units = (float)($data['units'] ?? 1);
        if ($units <= 0) {
            $units = 1;
        }

        $code = trim((string)($data['request_code'] ?? ''));
        if ($code === '') {
            $code = $this->_generate_request_code();
        }

        $externalId = trim((string)($data['external_id'] ?? ''));
        if ($externalId !== '') {
            $dup = $this->db->where('source', $source)
                ->where('external_id', $externalId)
                ->get('saas_ai_requests')
                ->row_array();
            if ($dup) {
                return ['ok' => true, 'id' => (int)$dup['id'], 'request_code' => $dup['request_code'], 'duplicate' => true];
            }
        }

        $unitAmount = array_key_exists('unit_amount', $data)
            ? (float)$data['unit_amount']
            : $this->resolve_unit_amount($type, $vendorId);
        $total = round($unitAmount * $units, 4);

        $meta = $data['meta'] ?? null;
        if (is_array($meta)) {
            $meta = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $createdAt = !empty($data['created_at']) ? $data['created_at'] : date('Y-m-d H:i:s');

        $row = [
            'request_code' => $code,
            'vendor_id'    => $vendorId,
            'request_type' => $type,
            'source'       => $source,
            'status'       => trim((string)($data['status'] ?? 'ok')) ?: 'ok',
            'units'        => $units,
            'unit_amount'  => $unitAmount,
            'total_amount' => $total,
            'meta'         => $meta,
            'external_id'  => $externalId !== '' ? $externalId : null,
            'billed_at'    => null,
            'created_at'   => $createdAt,
        ];

        // Unique request_code collision → retry once with new code
        if ($this->db->where('request_code', $code)->count_all_results('saas_ai_requests') > 0) {
            $row['request_code'] = $this->_generate_request_code();
        }

        $this->db->insert('saas_ai_requests', $row);
        $id = (int)$this->db->insert_id();
        if (!$id) {
            $err = $this->db->error();
            return ['ok' => false, 'message' => $err['message'] ?: 'Insert failed.'];
        }

        return [
            'ok'           => true,
            'id'           => $id,
            'request_code' => $row['request_code'],
            'unit_amount'  => $unitAmount,
            'total_amount' => $total,
            'duplicate'    => false,
        ];
    }

    /**
     * Sync outbound WhatsApp cloud messages for a calendar day into saas_ai_requests.
     * Attribution: settings saas_default_vendor_id (required for platform WA).
     */
    public function sync_whatsapp_day(string $date, ?int $defaultVendorId = null): array {
        $this->ensure_schema();
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return ['ok' => false, 'message' => 'Invalid date.', 'synced' => 0, 'skipped' => 0];
        }

        if (!$defaultVendorId) {
            $this->load->model('Sk_Admin_model');
            $settings = $this->Sk_Admin_model->get_settings();
            $defaultVendorId = (int)($settings['saas_default_vendor_id'] ?? 0);
        }

        if ($defaultVendorId < 1) {
            return [
                'ok'      => true,
                'message' => 'Skipped WhatsApp sync: set saas_default_vendor_id in settings.',
                'synced'  => 0,
                'skipped' => 0,
            ];
        }

        if (!$this->db->table_exists('wa_cloud_messages')) {
            return ['ok' => true, 'message' => 'No wa_cloud_messages table.', 'synced' => 0, 'skipped' => 0];
        }

        $rows = $this->db->where('direction', 'out')
            ->where('created_at >=', $date . ' 00:00:00')
            ->where('created_at <=', $date . ' 23:59:59')
            ->order_by('id', 'ASC')
            ->get('wa_cloud_messages')
            ->result_array();

        $synced = 0;
        $skipped = 0;
        foreach ($rows as $m) {
            $external = trim((string)($m['wamid'] ?? ''));
            if ($external === '') {
                $external = 'wa_msg_' . (int)$m['id'];
            }

            $result = $this->log_request([
                'vendor_id'    => $defaultVendorId,
                'request_type' => 'whatsapp_ai',
                'source'       => 'whatsapp',
                'external_id'  => $external,
                'status'       => ($m['status'] ?? '') === 'failed' ? 'failed' : 'ok',
                'units'        => 1,
                'created_at'   => $m['created_at'],
                'meta'         => [
                    'wa_message_id'    => (int)$m['id'],
                    'conversation_id'  => (int)($m['conversation_id'] ?? 0),
                    'type'             => $m['type'] ?? 'text',
                    'body_preview'     => mb_substr((string)($m['body'] ?? ''), 0, 200),
                ],
            ]);

            if (!empty($result['ok'])) {
                if (!empty($result['duplicate'])) {
                    $skipped++;
                } else {
                    $synced++;
                }
            } else {
                $skipped++;
            }
        }

        return ['ok' => true, 'synced' => $synced, 'skipped' => $skipped, 'message' => "Synced {$synced}, skipped {$skipped}."];
    }

    /** Aggregate unbilled requests for a date into saas_daily_bills (idempotent). */
    public function rollup_daily_bills(string $date): array {
        $this->ensure_schema();
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return ['ok' => false, 'message' => 'Invalid date.', 'vendors' => 0];
        }

        $from = $date . ' 00:00:00';
        $to   = $date . ' 23:59:59';

        $groups = $this->db->select('vendor_id, COUNT(*) AS request_count, COALESCE(SUM(total_amount),0) AS total_amount', false)
            ->where('created_at >=', $from)
            ->where('created_at <=', $to)
            ->group_by('vendor_id')
            ->get('saas_ai_requests')
            ->result_array();

        $now = date('Y-m-d H:i:s');
        $vendors = 0;
        foreach ($groups as $g) {
            $vid = (int)$g['vendor_id'];
            $count = (int)$g['request_count'];
            $amount = round((float)$g['total_amount'], 4);

            $existing = $this->db->where('vendor_id', $vid)
                ->where('bill_date', $date)
                ->get('saas_daily_bills')
                ->row_array();

            if ($existing) {
                $this->db->where('id', (int)$existing['id'])->update('saas_daily_bills', [
                    'request_count' => $count,
                    'total_amount'  => $amount,
                    'status'        => 'final',
                    'updated_at'    => $now,
                ]);
            } else {
                $this->db->insert('saas_daily_bills', [
                    'vendor_id'     => $vid,
                    'bill_date'     => $date,
                    'request_count' => $count,
                    'total_amount'  => $amount,
                    'status'        => 'final',
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }

            $this->db->where('vendor_id', $vid)
                ->where('created_at >=', $from)
                ->where('created_at <=', $to)
                ->where('billed_at IS NULL', null, false)
                ->update('saas_ai_requests', ['billed_at' => $now]);

            $vendors++;
        }

        return ['ok' => true, 'vendors' => $vendors, 'message' => "Rolled up {$vendors} vendor bill(s) for {$date}."];
    }

    public function run_morning_job(?string $date = null, bool $syncWhatsapp = true): array {
        $billDate = $date ?: date('Y-m-d', strtotime('-1 day'));
        $wa = $syncWhatsapp
            ? $this->sync_whatsapp_day($billDate)
            : ['ok' => true, 'synced' => 0, 'skipped' => 0, 'message' => 'WhatsApp sync skipped.'];
        $bill = $this->rollup_daily_bills($billDate);
        return [
            'ok'       => !empty($bill['ok']),
            'bill_date'=> $billDate,
            'whatsapp' => $wa,
            'rollup'   => $bill,
        ];
    }

    public function month_total(?int $vendorId, ?string $month = null): array {
        $this->ensure_schema();
        $month = $month ?: date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $from = $month . '-01';
        $to = date('Y-m-t', strtotime($from));

        $this->db->select('COALESCE(SUM(total_amount),0) AS total_amount, COALESCE(SUM(request_count),0) AS request_count', false)
            ->where('bill_date >=', $from)
            ->where('bill_date <=', $to);
        if ($vendorId) {
            $this->db->where('vendor_id', (int)$vendorId);
        }
        $row = $this->db->get('saas_daily_bills')->row_array();

        // Include unbilled today (current month live)
        $today = date('Y-m-d');
        if (strpos($today, $month) === 0) {
            $this->db->select('COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS amt', false)
                ->where('created_at >=', $today . ' 00:00:00')
                ->where('created_at <=', $today . ' 23:59:59');
            if ($vendorId) {
                $this->db->where('vendor_id', (int)$vendorId);
            }
            $live = $this->db->get('saas_ai_requests')->row_array();
            $row['total_amount'] = (float)($row['total_amount'] ?? 0) + (float)($live['amt'] ?? 0);
            $row['request_count'] = (int)($row['request_count'] ?? 0) + (int)($live['cnt'] ?? 0);
        }

        return [
            'month'         => $month,
            'from'          => $from,
            'to'            => $to,
            'total_amount'  => round((float)($row['total_amount'] ?? 0), 4),
            'request_count' => (int)($row['request_count'] ?? 0),
        ];
    }

    public function day_bill_total(string $date, ?int $vendorId = null): array {
        $this->ensure_schema();
        $this->db->select('COALESCE(SUM(total_amount),0) AS total_amount, COALESCE(SUM(request_count),0) AS request_count', false)
            ->where('bill_date', $date);
        if ($vendorId) {
            $this->db->where('vendor_id', (int)$vendorId);
        }
        $row = $this->db->get('saas_daily_bills')->row_array();

        // If no bill row yet, sum live requests for that day
        if ((int)($row['request_count'] ?? 0) === 0) {
            $this->db->select('COUNT(*) AS request_count, COALESCE(SUM(total_amount),0) AS total_amount', false)
                ->where('created_at >=', $date . ' 00:00:00')
                ->where('created_at <=', $date . ' 23:59:59');
            if ($vendorId) {
                $this->db->where('vendor_id', (int)$vendorId);
            }
            $row = $this->db->get('saas_ai_requests')->row_array();
        }

        return [
            'date'          => $date,
            'total_amount'  => round((float)($row['total_amount'] ?? 0), 4),
            'request_count' => (int)($row['request_count'] ?? 0),
        ];
    }

    public function count_requests_today(?int $vendorId = null): int {
        $this->ensure_schema();
        $today = date('Y-m-d');
        $this->db->where('created_at >=', $today . ' 00:00:00')
            ->where('created_at <=', $today . ' 23:59:59');
        if ($vendorId) {
            $this->db->where('vendor_id', (int)$vendorId);
        }
        return (int)$this->db->count_all_results('saas_ai_requests');
    }

    public function list_daily_bills(array $filters, int $limit, int $offset): array {
        $this->ensure_schema();
        $this->_apply_daily_filters($filters);
        $total = (int)$this->db->count_all_results('saas_daily_bills');

        $this->_apply_daily_filters($filters);
        $rows = $this->db->order_by('bill_date', 'DESC')
            ->order_by('vendor_id', 'ASC')
            ->limit($limit, $offset)
            ->get('saas_daily_bills')
            ->result_array();

        return ['rows' => $rows, 'total' => $total];
    }

    protected function _apply_daily_filters(array $filters): void {
        if (!empty($filters['vendor_id'])) {
            $this->db->where('vendor_id', (int)$filters['vendor_id']);
        }
        if (!empty($filters['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_from'])) {
            $this->db->where('bill_date >=', $filters['date_from']);
        }
        if (!empty($filters['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_to'])) {
            $this->db->where('bill_date <=', $filters['date_to']);
        }
    }

    public function list_requests(array $filters, int $limit, int $offset): array {
        $this->ensure_schema();
        $this->_apply_request_filters($filters);
        $total = (int)$this->db->count_all_results('saas_ai_requests');

        $this->_apply_request_filters($filters);
        $rows = $this->db->order_by('id', 'DESC')
            ->limit($limit, $offset)
            ->get('saas_ai_requests')
            ->result_array();

        return ['rows' => $rows, 'total' => $total];
    }

    protected function _apply_request_filters(array $filters): void {
        if (!empty($filters['vendor_id'])) {
            $this->db->where('vendor_id', (int)$filters['vendor_id']);
        }
        if (!empty($filters['request_type'])) {
            $this->db->where('request_type', $this->_normalize_type($filters['request_type']));
        }
        if (!empty($filters['source'])) {
            $this->db->where('source', $filters['source']);
        }
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $this->db->group_start()
                ->like('request_code', $s)
                ->or_like('external_id', $s)
                ->group_end();
        }
        if (!empty($filters['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_from'])) {
            $this->db->where('created_at >=', $filters['date_from'] . ' 00:00:00');
        }
        if (!empty($filters['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_to'])) {
            $this->db->where('created_at <=', $filters['date_to'] . ' 23:59:59');
        }
    }

    public function get_request_by_code(string $code): ?array {
        $this->ensure_schema();
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        $row = $this->db->where('request_code', $code)->get('saas_ai_requests')->row_array();
        return $row ?: null;
    }

    public function list_amounts(?int $vendorId = null): array {
        $this->ensure_schema();
        if ($vendorId === null) {
            // All: globals + overrides
            return $this->db->order_by('vendor_id', 'ASC')
                ->order_by('request_type', 'ASC')
                ->get('saas_client_amounts')
                ->result_array();
        }
        return $this->db->group_start()
            ->where('vendor_id IS NULL', null, false)
            ->or_where('vendor_id', (int)$vendorId)
            ->group_end()
            ->order_by('vendor_id', 'ASC')
            ->order_by('request_type', 'ASC')
            ->get('saas_client_amounts')
            ->result_array();
    }

    public function get_amount(int $id): ?array {
        $this->ensure_schema();
        $row = $this->db->where('id', $id)->get('saas_client_amounts')->row_array();
        return $row ?: null;
    }

    public function save_amount(array $data, ?int $id = null): array {
        $this->ensure_schema();
        $type = $this->_normalize_type((string)($data['request_type'] ?? 'ai_chat'));
        $vendorId = isset($data['vendor_id']) && $data['vendor_id'] !== '' && $data['vendor_id'] !== null
            ? (int)$data['vendor_id']
            : null;
        if ($vendorId !== null && $vendorId < 1) {
            $vendorId = null;
        }

        $now = date('Y-m-d H:i:s');
        $row = [
            'vendor_id'    => $vendorId,
            'request_type' => $type,
            'unit_amount'  => round((float)($data['unit_amount'] ?? 0), 4),
            'currency'     => strtoupper(trim((string)($data['currency'] ?? 'INR'))) ?: 'INR',
            'label'        => trim((string)($data['label'] ?? '')) ?: null,
            'status'       => !empty($data['status']) ? 1 : 0,
            'updated_at'   => $now,
        ];

        if ($id) {
            $this->db->where('id', $id)->update('saas_client_amounts', $row);
            return ['ok' => true, 'id' => $id];
        }

        $row['created_at'] = $now;
        // Upsert on unique key
        $q = $this->db->where('request_type', $type);
        if ($vendorId === null) {
            $q->where('vendor_id IS NULL', null, false);
        } else {
            $q->where('vendor_id', $vendorId);
        }
        $existing = $q->get('saas_client_amounts')->row_array();
        if ($existing) {
            $this->db->where('id', (int)$existing['id'])->update('saas_client_amounts', $row);
            return ['ok' => true, 'id' => (int)$existing['id'], 'updated' => true];
        }

        $this->db->insert('saas_client_amounts', $row);
        return ['ok' => true, 'id' => (int)$this->db->insert_id()];
    }

    public function delete_amount(int $id): bool {
        $this->ensure_schema();
        return (bool)$this->db->where('id', $id)->delete('saas_client_amounts');
    }

    protected function _normalize_type(string $type): string {
        $type = strtolower(trim($type));
        $type = preg_replace('/[^a-z0-9_]+/', '_', $type);
        return $type !== '' ? $type : 'ai_chat';
    }

    protected function _generate_request_code(): string {
        return 'AI-' . strtoupper(bin2hex(random_bytes(4))) . '-' . date('ymdHis');
    }
}
