<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Per WhatsApp phone-number Meta Business Agent state (non-secret IDs / status).
 */
class Sk_Vendor_meta_agent_model extends CI_Model {

    protected $table = 'vendor_meta_agents';

    public function ensure_schema(): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        if (!$this->db->table_exists($this->table)) {
            $this->db->query("CREATE TABLE `{$this->table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `vendor_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `phone_number_id` VARCHAR(128) NOT NULL,
                `agent_id` VARCHAR(128) NULL,
                `connector_id` VARCHAR(128) NULL,
                `eligible` TINYINT(1) NOT NULL DEFAULT 0,
                `onboarded` TINYINT(1) NOT NULL DEFAULT 0,
                `agent_enabled` TINYINT(1) NOT NULL DEFAULT 0,
                `ai_audience` VARCHAR(32) NOT NULL DEFAULT 'ALLOWLISTED_ONLY',
                `sync_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
                `last_error` TEXT NULL,
                `eligibility_json` MEDIUMTEXT NULL,
                `last_synced_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_phone` (`phone_number_id`),
                KEY `idx_vendor` (`vendor_id`),
                KEY `idx_sync` (`sync_status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        // Thread ownership on conversations (Meta agent vs app).
        if ($this->db->table_exists('wa_cloud_conversations')) {
            if (!$this->db->field_exists('thread_owner', 'wa_cloud_conversations')) {
                $this->db->query("ALTER TABLE `wa_cloud_conversations`
                    ADD COLUMN `thread_owner` VARCHAR(24) NOT NULL DEFAULT 'meta_agent' AFTER `unread`");
            }
            if (!$this->db->field_exists('handoff_at', 'wa_cloud_conversations')) {
                $this->db->query("ALTER TABLE `wa_cloud_conversations`
                    ADD COLUMN `handoff_at` DATETIME NULL AFTER `thread_owner`");
            }
            if (!$this->db->field_exists('handoff_reason', 'wa_cloud_conversations')) {
                $this->db->query("ALTER TABLE `wa_cloud_conversations`
                    ADD COLUMN `handoff_reason` VARCHAR(500) NULL AFTER `handoff_at`");
            }
        }
    }

    public function get_by_phone(string $phoneNumberId): ?array {
        $phoneNumberId = trim($phoneNumberId);
        if ($phoneNumberId === '') {
            return null;
        }
        $this->ensure_schema();
        $row = $this->db->where('phone_number_id', $phoneNumberId)->get($this->table)->row_array();
        return $row ?: null;
    }

    public function list_for_vendor(int $vendorId): array {
        $this->ensure_schema();
        if ($vendorId > 0) {
            $this->db->where('vendor_id', $vendorId);
        }
        return $this->db->order_by('updated_at', 'DESC')->get($this->table)->result_array();
    }

    public function list_all(int $limit = 200): array {
        $this->ensure_schema();
        return $this->db->order_by('updated_at', 'DESC')->limit(max(1, $limit))->get($this->table)->result_array();
    }

    public function upsert(string $phoneNumberId, array $data): array {
        $phoneNumberId = trim($phoneNumberId);
        if ($phoneNumberId === '') {
            return ['ok' => false, 'message' => 'phone_number_id required'];
        }
        $this->ensure_schema();
        $now = date('Y-m-d H:i:s');
        $existing = $this->get_by_phone($phoneNumberId);
        $row = [
            'vendor_id'       => (int)($data['vendor_id'] ?? ($existing['vendor_id'] ?? 0)),
            'phone_number_id' => $phoneNumberId,
            'agent_id'        => array_key_exists('agent_id', $data)
                ? trim((string)$data['agent_id'])
                : ($existing['agent_id'] ?? null),
            'connector_id'    => array_key_exists('connector_id', $data)
                ? trim((string)$data['connector_id'])
                : ($existing['connector_id'] ?? null),
            'eligible'        => array_key_exists('eligible', $data)
                ? (!empty($data['eligible']) ? 1 : 0)
                : (int)($existing['eligible'] ?? 0),
            'onboarded'       => array_key_exists('onboarded', $data)
                ? (!empty($data['onboarded']) ? 1 : 0)
                : (int)($existing['onboarded'] ?? 0),
            'agent_enabled'   => array_key_exists('agent_enabled', $data)
                ? (!empty($data['agent_enabled']) ? 1 : 0)
                : (int)($existing['agent_enabled'] ?? 0),
            'ai_audience'     => array_key_exists('ai_audience', $data)
                ? trim((string)$data['ai_audience'])
                : ($existing['ai_audience'] ?? 'ALLOWLISTED_ONLY'),
            'sync_status'     => array_key_exists('sync_status', $data)
                ? trim((string)$data['sync_status'])
                : ($existing['sync_status'] ?? 'pending'),
            'last_error'      => array_key_exists('last_error', $data)
                ? (string)$data['last_error']
                : ($existing['last_error'] ?? null),
            'eligibility_json'=> array_key_exists('eligibility_json', $data)
                ? (is_string($data['eligibility_json'])
                    ? $data['eligibility_json']
                    : json_encode($data['eligibility_json']))
                : ($existing['eligibility_json'] ?? null),
            'last_synced_at'  => array_key_exists('last_synced_at', $data)
                ? $data['last_synced_at']
                : ($existing['last_synced_at'] ?? null),
            'updated_at'      => $now,
        ];
        if (!in_array($row['ai_audience'], ['ALLOWLISTED_ONLY', 'EVERYONE'], true)) {
            $row['ai_audience'] = 'ALLOWLISTED_ONLY';
        }

        if ($existing) {
            $this->db->where('id', (int)$existing['id'])->update($this->table, $row);
            $id = (int)$existing['id'];
        } else {
            $row['created_at'] = $now;
            $this->db->insert($this->table, $row);
            $id = (int)$this->db->insert_id();
        }
        return ['ok' => true, 'id' => $id, 'row' => $this->get_by_phone($phoneNumberId)];
    }

    public function set_error(string $phoneNumberId, string $error): void {
        $this->upsert($phoneNumberId, [
            'sync_status' => 'error',
            'last_error'  => $error,
        ]);
    }

    public function set_conversation_owner(int $conversationId, string $owner, string $reason = ''): void {
        $this->ensure_schema();
        if (!$this->db->table_exists('wa_cloud_conversations') || $conversationId < 1) {
            return;
        }
        $owner = in_array($owner, ['meta_agent', 'app', 'human'], true) ? $owner : 'meta_agent';
        $upd = [
            'thread_owner' => $owner,
            'updated_at'   => date('Y-m-d H:i:s'),
        ];
        if ($owner !== 'meta_agent') {
            $upd['handoff_at'] = date('Y-m-d H:i:s');
            if ($reason !== '') {
                $upd['handoff_reason'] = mb_substr($reason, 0, 500);
            }
        } else {
            $upd['handoff_at'] = null;
            $upd['handoff_reason'] = null;
        }
        $this->db->where('id', $conversationId)->update('wa_cloud_conversations', $upd);
    }
}
