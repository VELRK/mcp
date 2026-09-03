<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sk_Vendor_model extends CI_Model {

    protected $table = 'vendors';

    public function get_all(array $filters = [], int $limit = 20, int $offset = 0): array {
        $this->_build_list_query($filters);
        $total = $this->db->count_all_results();

        $this->_build_list_query($filters);
        $rows = $this->db->select('v.*, vs.store_name, vs.logo')
                          ->order_by('v.created_at', 'DESC')
                          ->limit($limit, $offset)
                          ->get()
                          ->result_array();

        return ['rows' => $rows, 'total' => $total];
    }

    protected function _build_list_query(array $filters): void {
        $this->db->from('vendors v')
                 ->join('vendor_stores vs', 'vs.vendor_id = v.id', 'left');
        $this->_apply_filters($filters);
        $this->db->where('v.deleted_at IS NULL', null, false);
    }

    protected function _apply_filters(array $filters): void {
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $this->db->group_start()
                     ->like('v.business_name', $s)
                     ->or_like('v.owner_name', $s)
                     ->or_like('v.email', $s)
                     ->or_like('v.phone', $s)
                     ->or_like('v.slug', $s)
                     ->group_end();
        }
        if (!empty($filters['status'])) {
            $this->db->where('v.status', $filters['status']);
        }
        if (!empty($filters['verification_status'])) {
            $this->db->where('v.verification_status', $filters['verification_status']);
        }
    }

    public function get_by_id(int $id, bool $with_relations = true): ?array {
        $vendor = $this->db->where('id', $id)->where('deleted_at IS NULL', null, false)->get($this->table)->row_array();
        if (!$vendor || !$with_relations) {
            return $vendor ?: null;
        }
        $vendor['store'] = $this->db->where('vendor_id', $id)->get('vendor_stores')->row_array();
        $vendor['documents'] = $this->db->where('vendor_id', $id)->order_by('created_at', 'DESC')->get('vendor_documents')->result_array();
        return $vendor;
    }

    public function get_by_email(string $email): ?array {
        return $this->db->where('email', $email)->where('deleted_at IS NULL', null, false)->get($this->table)->row_array() ?: null;
    }

    public function verify_password(string $plain, string $hash): bool {
        return password_verify($plain, $hash);
    }

    /** Columns for email-verified password change / forgot password. */
    public function ensure_password_reset_schema(): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        if (!$this->db->field_exists('reset_token', $this->table)) {
            $this->db->query(
                "ALTER TABLE `{$this->table}` ADD COLUMN `reset_token` VARCHAR(64) NULL DEFAULT NULL AFTER `password`"
            );
        }
        if (!$this->db->field_exists('reset_expires', $this->table)) {
            $this->db->query(
                "ALTER TABLE `{$this->table}` ADD COLUMN `reset_expires` DATETIME NULL DEFAULT NULL AFTER `reset_token`"
            );
        }
    }

    /** 6-digit code for password change / forgot (15 min). */
    public function set_reset_code(string $email): ?string {
        $this->ensure_password_reset_schema();
        $vendor = $this->get_by_email($email);
        if (!$vendor || ($vendor['status'] ?? '') !== 'approved') {
            return null;
        }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->db->where('id', (int) $vendor['id'])->update($this->table, [
            'reset_token'   => $code,
            'reset_expires' => date('Y-m-d H:i:s', strtotime('+15 minutes')),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
        return $code;
    }

    /**
     * Validate 6-digit email code; swap to a longer reset token (30 min).
     * @return string|null reset token for reset_password_with_token
     */
    public function verify_reset_code(string $email, string $code): ?string {
        $this->ensure_password_reset_schema();
        if (!preg_match('/^\d{6}$/', $code)) {
            return null;
        }
        $vendor = $this->db->where('email', $email)
            ->where('reset_token', $code)
            ->where('reset_expires >', date('Y-m-d H:i:s'))
            ->where('deleted_at IS NULL', null, false)
            ->get($this->table)->row_array();
        if (!$vendor) {
            return null;
        }
        $token = bin2hex(random_bytes(32));
        $this->db->where('id', (int) $vendor['id'])->update($this->table, [
            'reset_token'   => $token,
            'reset_expires' => date('Y-m-d H:i:s', strtotime('+30 minutes')),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
        return $token;
    }

    public function reset_password_with_token(string $email, string $token, string $password): bool {
        $this->ensure_password_reset_schema();
        if (strlen($token) <= 6 || ctype_digit($token)) {
            return false;
        }
        if (strlen($password) < 6) {
            return false;
        }
        $vendor = $this->db->where('email', $email)
            ->where('reset_token', $token)
            ->where('reset_expires >', date('Y-m-d H:i:s'))
            ->where('deleted_at IS NULL', null, false)
            ->get($this->table)->row_array();
        if (!$vendor) {
            return false;
        }
        $this->db->where('id', (int) $vendor['id'])->update($this->table, [
            'password'      => password_hash($password, PASSWORD_BCRYPT),
            'reset_token'   => null,
            'reset_expires' => null,
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
        return true;
    }

    public function send_password_change_code_email(array $vendor, string $code): bool {
        $this->load->model('Sk_Admin_model');
        $this->load->helper('sk_mailer');
        $settings = $this->Sk_Admin_model->get_settings();
        $name = trim((string) ($vendor['owner_name'] ?? $vendor['business_name'] ?? 'Vendor'));
        return sk_mail_password_reset_code(
            ['email' => $vendor['email'], 'name' => $name],
            $code,
            $settings,
            'Vendor Portal'
        );
    }

    public function create(array $data, ?array $store = null): int {
        if (empty($data['uuid'])) {
            $data['uuid'] = $this->_uuid();
        }
        if (empty($data['slug'])) {
            $data['slug'] = $this->make_unique_slug($data['business_name']);
        }
        if (!empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->table, $data);
        $id = (int)$this->db->insert_id();

        $this->db->insert('vendor_wallets', ['vendor_id' => $id, 'balance' => 0.00]);
        $store_data = $store ?? ['store_name' => $data['business_name']];
        $store_data['vendor_id'] = $id;
        $this->db->insert('vendor_stores', $store_data);

        return $id;
    }

    public function update(int $id, array $data, ?array $store = null): bool {
        if (isset($data['password']) && $data['password'] === '') {
            unset($data['password']);
        } elseif (!empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        if (!empty($data['business_name']) && empty($data['slug'])) {
            $data['slug'] = $this->make_unique_slug($data['business_name'], $id);
        }
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', $id)->update($this->table, $data);

        if ($store !== null) {
            $exists = $this->db->where('vendor_id', $id)->count_all_results('vendor_stores');
            if ($exists) {
                $this->db->where('vendor_id', $id)->update('vendor_stores', $store);
            } else {
                $store['vendor_id'] = $id;
                $this->db->insert('vendor_stores', $store);
            }
        }
        return true;
    }

    public function soft_delete(int $id, ?int $deleted_by = null): bool {
        return (bool)$this->db->where('id', $id)->update($this->table, [
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $deleted_by,
            'status'     => 'inactive',
        ]);
    }

    public function approve(int $id, int $admin_id): bool {
        return (bool)$this->db->where('id', $id)->update($this->table, [
            'status'       => 'approved',
            'approved_at'  => date('Y-m-d H:i:s'),
            'approved_by'  => $admin_id,
            'rejected_at'  => null,
            'rejected_by'  => null,
            'rejection_reason' => null,
        ]);
    }

    public function reject(int $id, int $admin_id, string $reason = ''): bool {
        return (bool)$this->db->where('id', $id)->update($this->table, [
            'status'           => 'rejected',
            'rejected_at'      => date('Y-m-d H:i:s'),
            'rejected_by'      => $admin_id,
            'rejection_reason' => $reason,
        ]);
    }

    public function suspend(int $id): bool {
        return (bool)$this->db->where('id', $id)->update($this->table, ['status' => 'suspended']);
    }

    public function activate(int $id): bool {
        return (bool)$this->db->where('id', $id)->update($this->table, ['status' => 'approved']);
    }

    public function status_counts(): array {
        $rows = $this->db->select('status, COUNT(*) as cnt')
                          ->where('deleted_at IS NULL', null, false)
                          ->group_by('status')
                          ->get($this->table)->result_array();
        $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'suspended' => 0, 'inactive' => 0, 'total' => 0];
        foreach ($rows as $r) {
            $counts[$r['status']] = (int)$r['cnt'];
            $counts['total'] += (int)$r['cnt'];
        }
        return $counts;
    }

    public function make_unique_slug(string $name, ?int $exclude_id = null): string {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        if ($slug === '') $slug = 'vendor';
        $base = $slug;
        $i = 1;
        while (true) {
            $this->db->where('slug', $slug);
            if ($exclude_id) $this->db->where('id !=', $exclude_id);
            if ($this->db->count_all_results($this->table) === 0) {
                return $slug;
            }
            $slug = $base . '-' . $i++;
        }
    }

    public function export_rows(array $filters = []): array {
        $result = $this->get_all($filters, 10000, 0);
        return $result['rows'];
    }

    protected function _uuid(): string {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s-%s-%s-%s-%s', str_split(bin2hex($data), 4));
    }
}
