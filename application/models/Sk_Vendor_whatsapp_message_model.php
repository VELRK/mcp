<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sk_Vendor_whatsapp_message_model extends CI_Model {

    protected $table = 'vendor_whatsapp_messages';

    public function ensure_schema(): void {
        static $done = false;
        if ($done) return;
        $done = true;

        if (!$this->db->table_exists($this->table)) {
            $this->db->query("CREATE TABLE `{$this->table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `account_id` INT UNSIGNED NOT NULL,
                `vendor_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `message_id` VARCHAR(128) NULL,
                `recipient` VARCHAR(64) NULL,
                `message_type` VARCHAR(32) NULL,
                `cost` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
                `currency` VARCHAR(8) NULL,
                `status` VARCHAR(32) NULL,
                `meta` TEXT NULL,
                `sent_at` DATETIME NOT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_account` (`account_id`),
                KEY `idx_vendor` (`vendor_id`),
                KEY `idx_sent_at` (`sent_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }

    public function log(array $data): array {
        $this->ensure_schema();

        if (empty($data['account_id'])) {
            return ['ok' => false, 'message' => 'account_id required'];
        }

        $now = date('Y-m-d H:i:s');
        $row = [
            'account_id' => (int)$data['account_id'],
            'vendor_id' => (int)($data['vendor_id'] ?? 0),
            'message_id' => trim((string)($data['message_id'] ?? '')) ?: null,
            'recipient' => trim((string)($data['recipient'] ?? '')) ?: null,
            'message_type' => trim((string)($data['message_type'] ?? '')),
            'cost' => isset($data['cost']) ? (float)$data['cost'] : 0.0,
            'currency' => trim((string)($data['currency'] ?? 'USD')),
            'status' => trim((string)($data['status'] ?? 'sent')),
            'meta' => is_array($data['meta']) ? json_encode($data['meta']) : (string)($data['meta'] ?? ''),
            'sent_at' => $data['sent_at'] ? $data['sent_at'] : $now,
            'created_at' => $now,
        ];

        $this->db->insert($this->table, $row);
        $id = (int)$this->db->insert_id();
        return ['ok' => true, 'id' => $id];
    }

    public function get_report(int $account_id, ?string $from = null, ?string $to = null): array {
        $this->ensure_schema();
        $q = $this->db->where('account_id', $account_id);
        if ($from) {
            $q = $q->where('sent_at >=', $from);
        }
        if ($to) {
            $q = $q->where('sent_at <=', $to);
        }

        // Summary
        $summarySql = "SELECT COUNT(*) AS messages_sent, COALESCE(SUM(cost),0) AS total_spent, currency
                       FROM {$this->table}
                       WHERE account_id = ?";
        $params = [$account_id];
        if ($from) { $summarySql .= " AND sent_at >= ?"; $params[] = $from; }
        if ($to) { $summarySql .= " AND sent_at <= ?"; $params[] = $to; }
        $summary = $this->db->query($summarySql, $params)->row_array() ?: ['messages_sent' => 0, 'total_spent' => 0.0, 'currency' => null];

        // Breakdown by message_type
        $breakdownSql = "SELECT message_type, COUNT(*) AS count, COALESCE(SUM(cost),0) AS spent
                         FROM {$this->table} WHERE account_id = ?";
        $params2 = [$account_id];
        if ($from) { $breakdownSql .= " AND sent_at >= ?"; $params2[] = $from; }
        if ($to) { $breakdownSql .= " AND sent_at <= ?"; $params2[] = $to; }
        $breakdownSql .= " GROUP BY message_type";
        $breakdown = $this->db->query($breakdownSql, $params2)->result_array();

        // Recent details (limit 1000)
        $detailQ = $this->db->where('account_id', $account_id);
        if ($from) $detailQ = $detailQ->where('sent_at >=', $from);
        if ($to) $detailQ = $detailQ->where('sent_at <=', $to);
        $details = $detailQ->order_by('sent_at', 'DESC')->limit(1000)->get($this->table)->result_array();

        return [
            'summary' => $summary,
            'breakdown' => $breakdown,
            'details' => $details,
        ];
    }

}
