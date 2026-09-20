<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sk_Vendor_whatsapp_account_model extends CI_Model {

    protected $table = 'vendor_whatsapp_accounts';

    public function ensure_schema(): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        if (!$this->db->table_exists($this->table)) {
            $this->db->query("CREATE TABLE `{$this->table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `vendor_id` INT UNSIGNED NOT NULL,
                `phone_number_id` VARCHAR(128) NOT NULL,
                `waba_id` VARCHAR(128) NULL,
                `display_phone` VARCHAR(64) NULL,
                `business_id` VARCHAR(128) NULL,
                `access_token` TEXT NULL,
                `refresh_token` TEXT NULL,
                `token_expires` DATETIME NULL,
                `status` VARCHAR(24) NOT NULL DEFAULT 'active',
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_vendor_phone` (`vendor_id`, `phone_number_id`),
                UNIQUE KEY `uniq_phone` (`phone_number_id`),
                KEY `idx_vendor_status` (`vendor_id`, `status`),
                KEY `idx_default` (`vendor_id`, `is_default`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        foreach (['vendor_id', 'phone_number_id', 'waba_id', 'status', 'is_default', 'token_expires'] as $col) {
            if (!$this->db->field_exists($col, $this->table)) {
                $this->db->query("ALTER TABLE `{$this->table}` ADD COLUMN `{$col}` " . (
                    in_array($col, ['status', 'waba_id', 'display_phone', 'business_id', 'access_token', 'refresh_token', 'token_expires'], true)
                        ? 'VARCHAR(128) NULL' : (in_array($col, ['is_default'], true) ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'INT UNSIGNED NOT NULL DEFAULT 0')
                ) . "");
            }
        }
    }

    public function get_for_vendor(int $vendor_id): array {
        $this->ensure_schema();
        return $this->db->where('vendor_id', $vendor_id)
            ->order_by('is_default', 'DESC')
            ->order_by('id', 'ASC')
            ->get($this->table)
            ->result_array();
    }

    /** All vendor WhatsApp numbers for admin (active + inactive). */
    public function list_all(?string $status = null, int $limit = 200): array {
        $this->ensure_schema();
        if ($status !== null && $status !== '') {
            $this->db->where('status', $status);
        }
        return $this->db->order_by('updated_at', 'DESC')
            ->limit(max(1, $limit))
            ->get($this->table)
            ->result_array();
    }

    public function get_by_id(int $id): ?array {
        $this->ensure_schema();
        return $this->db->where('id', (int)$id)->get($this->table)->row_array() ?: null;
    }

    public function set_status(int $account_id, string $status): bool {
        $this->ensure_schema();
        $status = in_array($status, ['active', 'inactive'], true) ? $status : 'inactive';
        $this->db->where('id', (int)$account_id)->update($this->table, [
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->db->affected_rows() >= 0;
    }

    public function get_by_phone(string $phoneNumberId): ?array {
        $phoneNumberId = trim($phoneNumberId);
        if ($phoneNumberId === '') {
            return null;
        }
        $this->ensure_schema();
        return $this->db->where('phone_number_id', $phoneNumberId)
            ->where('status', 'active')
            ->get($this->table)
            ->row_array() ?: null;
    }

    public function resolve_for_vendor(int $vendor_id, ?string $phoneNumberId = null): ?array {
        $this->ensure_schema();
        $vendor_id = (int)$vendor_id;
        if ($vendor_id < 1) {
            return null;
        }
        if ($phoneNumberId !== null && trim($phoneNumberId) !== '') {
            $row = $this->db->where('vendor_id', $vendor_id)
                ->where('phone_number_id', trim($phoneNumberId))
                ->where('status', 'active')
                ->get($this->table)
                ->row_array();
            if ($row) {
                return $row;
            }
        }

        return $this->db->where('vendor_id', $vendor_id)
            ->where('status', 'active')
            ->order_by('is_default', 'DESC')
            ->order_by('id', 'ASC')
            ->get($this->table)
            ->row_array() ?: null;
    }

    public function save_for_vendor(int $vendor_id, array $data): array {
        $this->ensure_schema();
        $vendor_id = (int)$vendor_id;
        $phoneNumberId = trim((string)($data['phone_number_id'] ?? ''));
        $wabaId = trim((string)($data['waba_id'] ?? ''));
        if ($vendor_id < 1 || $phoneNumberId === '') {
            return ['ok' => false, 'message' => 'vendor_id and phone_number_id are required.'];
        }

        $now = date('Y-m-d H:i:s');
        $existing = $this->db->where('vendor_id', $vendor_id)
            ->where('phone_number_id', $phoneNumberId)
            ->get($this->table)
            ->row_array();

        $accessToken = trim((string)($data['access_token'] ?? ''));
        $refreshToken = trim((string)($data['refresh_token'] ?? ''));
        // Never wipe stored tokens when caller omits them (e.g. manual phone/WABA save).
        if ($existing) {
            if ($accessToken === '' && !empty($existing['access_token'])) {
                $accessToken = (string)$existing['access_token'];
            }
            if ($refreshToken === '' && !empty($existing['refresh_token'])) {
                $refreshToken = (string)$existing['refresh_token'];
            }
        }

        $row = [
            'vendor_id' => $vendor_id,
            'phone_number_id' => $phoneNumberId,
            'waba_id' => $wabaId !== '' ? $wabaId : ($existing['waba_id'] ?? ''),
            'display_phone' => trim((string)($data['display_phone'] ?? ($existing['display_phone'] ?? ''))),
            'business_id' => trim((string)($data['business_id'] ?? ($existing['business_id'] ?? ''))),
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_expires' => trim((string)($data['token_expires'] ?? ($existing['token_expires'] ?? ''))),
            'status' => trim((string)($data['status'] ?? 'active')) ?: 'active',
            'is_default' => array_key_exists('is_default', $data)
                ? (!empty($data['is_default']) ? 1 : 0)
                : (int)($existing['is_default'] ?? 0),
            'updated_at' => $now,
        ];

        if ($existing) {
            $this->db->where('id', (int)$existing['id'])->update($this->table, $row);
            $id = (int)$existing['id'];
        } else {
            $row['created_at'] = $now;
            $this->db->insert($this->table, $row);
            $id = (int)$this->db->insert_id();
        }

        if (!empty($row['is_default'])) {
            $this->db->where('vendor_id', $vendor_id)->where('id !=', $id)->update($this->table, ['is_default' => 0]);
        }

        return ['ok' => true, 'id' => $id, 'row' => $this->db->where('id', $id)->get($this->table)->row_array()];
    }

    public function set_default_for_vendor(int $vendor_id, int $account_id): bool {
        $this->ensure_schema();
        $vendor_id = (int)$vendor_id;
        $account_id = (int)$account_id;
        if ($vendor_id < 1 || $account_id < 1) {
            return false;
        }

        $exists = $this->db->where('id', $account_id)->where('vendor_id', $vendor_id)->get($this->table)->row_array();
        if (!$exists) {
            return false;
        }

        $this->db->where('vendor_id', $vendor_id)->update($this->table, ['is_default' => 0]);
        $this->db->where('id', $account_id)->where('vendor_id', $vendor_id)->update($this->table, ['is_default' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
        return $this->db->affected_rows() > 0 || $this->db->where('id', $account_id)->where('vendor_id', $vendor_id)->where('is_default', 1)->count_all_results($this->table) > 0;
    }
}
