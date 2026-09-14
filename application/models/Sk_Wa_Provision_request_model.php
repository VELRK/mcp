<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sk_Wa_Provision_request_model extends CI_Model {

    protected $table = 'wa_provision_requests';

    public function ensure_schema(): void {
        static $done = false;
        if ($done) return;
        $done = true;

        if (!$this->db->table_exists($this->table)) {
            $this->db->query("CREATE TABLE `{$this->table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `vendor_id` INT UNSIGNED NOT NULL,
                `phone_number_id` VARCHAR(128) NULL,
                `display_phone` VARCHAR(64) NULL,
                `waba_id` VARCHAR(128) NULL,
                `business_id` VARCHAR(128) NULL,
                `note` TEXT NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
                `admin_note` TEXT NULL,
                `admin_id` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_vendor_status` (`vendor_id`, `status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }

    public function create(int $vendor_id, array $data): array {
        $this->ensure_schema();
        $now = date('Y-m-d H:i:s');
        $row = [
            'vendor_id' => (int)$vendor_id,
            'phone_number_id' => trim((string)($data['phone_number_id'] ?? '')) ?: null,
            'display_phone' => trim((string)($data['display_phone'] ?? '')) ?: null,
            'waba_id' => trim((string)($data['waba_id'] ?? '')) ?: null,
            'business_id' => trim((string)($data['business_id'] ?? '')) ?: null,
            'note' => isset($data['note']) ? (string)$data['note'] : null,
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $this->db->insert($this->table, $row);
        return ['ok' => true, 'id' => (int)$this->db->insert_id(), 'row' => $row];
    }

    public function get_for_vendor(int $vendor_id): array {
        $this->ensure_schema();
        return $this->db->where('vendor_id', $vendor_id)->order_by('created_at','DESC')->get($this->table)->result_array();
    }

    public function get_pending(): array {
        $this->ensure_schema();
        return $this->db->where('status','pending')->order_by('created_at','ASC')->get($this->table)->result_array();
    }

    public function get_by_id(int $id): ?array {
        $this->ensure_schema();
        return $this->db->where('id', $id)->get($this->table)->row_array() ?: null;
    }

    public function update_status(int $id, string $status, ?int $admin_id = null, ?string $admin_note = null): bool {
        $this->ensure_schema();
        $upd = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')];
        if ($admin_id) $upd['admin_id'] = (int)$admin_id;
        if ($admin_note !== null) $upd['admin_note'] = $admin_note;
        $this->db->where('id', (int)$id)->update($this->table, $upd);
        return $this->db->affected_rows() > 0;
    }
}
