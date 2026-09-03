<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sk_User_model extends CI_Model {

    public function create($data) {
        if (array_key_exists('email', $data)) {
            $email = is_string($data['email']) ? strtolower(trim($data['email'])) : '';
            $data['email'] = $email !== '' ? $email : null;
        }
        $data['password']   = password_hash($data['password'], PASSWORD_BCRYPT);
        $data['verify_token'] = bin2hex(random_bytes(32));
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('users', $data);
        return $this->db->insert_id();
    }

    public function get_by_email($email) {
        $email = strtolower(trim((string) $email));
        if ($email === '') {
            return null;
        }
        return $this->db->where('email', $email)->get('users')->row_array();
    }

    public function get_by_phone($phone) {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return null;
        }
        $row = $this->db->where('phone', $phone)->get('users')->row_array();
        if ($row) {
            return $row;
        }
        // Match common MY variants (60… / 0… / +60…)
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return null;
        }
        $variants = array_values(array_unique(array_filter([
            $digits,
            '+' . $digits,
            (strpos($digits, '60') === 0 && strlen($digits) > 2) ? ('0' . substr($digits, 2)) : null,
            (strpos($digits, '0') === 0) ? ('60' . substr($digits, 1)) : null,
        ])));
        if (!$variants) {
            return null;
        }
        return $this->db->where_in('phone', $variants)->get('users')->row_array();
    }

    public function email_exists($email, $exclude_id = null): bool {
        $email = strtolower(trim((string) $email));
        if ($email === '') {
            return false;
        }
        if ($exclude_id) {
            $this->db->where('id !=', (int) $exclude_id);
        }
        return (int) $this->db->where('email', $email)->count_all_results('users') > 0;
    }

    public function phone_exists($phone, $exclude_id = null): bool {
        $user = $this->get_by_phone($phone);
        if (!$user) {
            return false;
        }
        if ($exclude_id && (int) $user['id'] === (int) $exclude_id) {
            return false;
        }
        return true;
    }

    public function get_by_id($id) {
        return $this->db->where('id', $id)->get('users')->row_array();
    }

    public function verify_password($plain, $hash) {
        return password_verify($plain, $hash);
    }

    public function update($id, $data) {
        if (array_key_exists('email', $data)) {
            if ($data['email'] === null) {
                // keep null
            } elseif (is_string($data['email'])) {
                $email = strtolower(trim($data['email']));
                $data['email'] = $email !== '' ? $email : null;
            }
        }
        if (!empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        $this->db->where('id', $id)->update('users', $data);
        return $this->db->affected_rows();
    }

    /**
     * Phone-OTP users store email as NULL until they optionally add one at checkout.
     * Also clears legacy ph_*@shopkart.app placeholders when migrating.
     */
    public function ensure_otp_user_schema(): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        if (!$this->db->table_exists('users') || !$this->db->field_exists('email', 'users')) {
            return;
        }
        // Allow NULL so OTP accounts do not need fake emails
        $this->db->query('ALTER TABLE `users` MODIFY COLUMN `email` VARCHAR(191) NULL DEFAULT NULL');
    }

    public function update_last_login($id) {
        $this->db->where('id', $id)->update('users', ['last_login' => date('Y-m-d H:i:s')]);
    }

    public function ensure_deleted_at_column(): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        if ($this->db->field_exists('deleted_at', 'users')) {
            return;
        }
        $this->db->query('ALTER TABLE `users` ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL AFTER `status`');
    }

    /** Soft-delete account so email/phone can be reused. */
    public function soft_delete($id): bool {
        $this->ensure_deleted_at_column();
        $id = (int)$id;
        $user = $this->get_by_id($id);
        if (!$user) {
            return false;
        }
        $stamp = date('YmdHis');
        $data = [
            'status'        => 0,
            'deleted_at'    => date('Y-m-d H:i:s'),
            'email'         => 'deleted_' . $id . '_' . $stamp . '@deleted.local',
            'phone'         => 'del' . $id . $stamp,
            'reset_token'   => null,
            'reset_expires' => null,
            'password'      => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
        ];
        $this->db->where('id', $id)->update('users', $data);
        return true;
    }

    /** Generate a 6-digit email verification code (15 min expiry). */
    public function set_reset_code($email) {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->db->where('email', $email)->update('users', [
            'reset_token'   => $code,
            'reset_expires' => date('Y-m-d H:i:s', strtotime('+15 minutes')),
        ]);
        return $code;
    }

    /** Verify email code and issue a secure reset token (30 min expiry). */
    public function verify_reset_code($email, $code) {
        $user = $this->db->where('email', $email)
            ->where('reset_token', $code)
            ->where('reset_expires >', date('Y-m-d H:i:s'))
            ->get('users')->row_array();
        if (!$user) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $this->db->where('id', $user['id'])->update('users', [
            'reset_token'   => $token,
            'reset_expires' => date('Y-m-d H:i:s', strtotime('+30 minutes')),
        ]);
        return $token;
    }

    public function get_by_reset_token($token) {
        return $this->db->where('reset_token', $token)
                        ->where('reset_expires >', date('Y-m-d H:i:s'))
                        ->get('users')->row_array();
    }

    /** Reset password only after email code was verified (secure token). */
    public function reset_password_with_token($email, $token, $password) {
        if (strlen($token) <= 6 || ctype_digit($token)) {
            return false;
        }

        $user = $this->db->where('email', $email)
            ->where('reset_token', $token)
            ->where('reset_expires >', date('Y-m-d H:i:s'))
            ->get('users')->row_array();
        if (!$user) {
            return false;
        }

        $this->reset_password($user['id'], $password);
        return true;
    }

    public function reset_password($id, $password) {
        $this->db->where('id', $id)->update('users', [
            'password'      => password_hash($password, PASSWORD_BCRYPT),
            'reset_token'   => null,
            'reset_expires' => null,
        ]);
    }

    // Addresses
    public function ensure_address_schema(): void {
        static $done = false;
        if ($done) return;
        $done = true;
        if (!$this->db->field_exists('company_name', 'addresses')) {
            $this->db->query("ALTER TABLE `addresses` ADD COLUMN `company_name` VARCHAR(150) NULL DEFAULT NULL AFTER `full_name`");
        }
        if (!$this->db->field_exists('address_type', 'addresses')) {
            $this->db->query("ALTER TABLE `addresses` ADD COLUMN `address_type` VARCHAR(20) NOT NULL DEFAULT 'shipping' AFTER `label`");
        }
    }

    public function get_addresses($user_id) {
        $this->ensure_address_schema();
        return $this->db->where('user_id', $user_id)->order_by('is_default', 'DESC')->order_by('id', 'DESC')->get('addresses')->result_array();
    }

    public function get_address($id, $user_id) {
        $this->ensure_address_schema();
        return $this->db->where(['id' => $id, 'user_id' => $user_id])->get('addresses')->row_array();
    }

    public function save_address($data) {
        $this->ensure_address_schema();
        $id = !empty($data['id']) ? (int)$data['id'] : 0;
        $allowed = ['user_id','full_name','company_name','phone','line1','line2','city','state','pincode','country','label','is_default','address_type'];
        $row = array_intersect_key($data, array_flip($allowed));
        $row['address_type'] = in_array(($row['address_type'] ?? 'shipping'), ['shipping', 'billing'], true)
            ? $row['address_type'] : 'shipping';
        if (!empty($row['is_default'])) {
            $this->db->where('user_id', $row['user_id'])
                     ->where('address_type', $row['address_type'])
                     ->update('addresses', ['is_default' => 0]);
        }
        if ($id) {
            $this->db->where(['id' => $id, 'user_id' => $row['user_id']])->update('addresses', $row);
            return $id;
        }
        $this->db->insert('addresses', $row);
        return $this->db->insert_id();
    }

    /**
     * Save first shipping address for a user when their address book is empty.
     * Used after checkout / OTP so My Addresses matches the order delivery address.
     */
    public function ensure_default_shipping_address(int $user_id, array $addr) {
        $this->ensure_address_schema();
        $existing = $this->db->where('user_id', $user_id)
            ->limit(1)
            ->get('addresses')
            ->row_array();
        if ($existing) {
            return (int) $existing['id'];
        }
        $line1 = trim((string) ($addr['line1'] ?? $addr['shipping_line1'] ?? ''));
        $fullName = trim((string) ($addr['full_name'] ?? $addr['shipping_name'] ?? ''));
        $phone = trim((string) ($addr['phone'] ?? $addr['shipping_phone'] ?? ''));
        $city = trim((string) ($addr['city'] ?? $addr['shipping_city'] ?? ''));
        $state = trim((string) ($addr['state'] ?? $addr['shipping_state'] ?? ''));
        $pincode = trim((string) ($addr['pincode'] ?? $addr['shipping_pincode'] ?? ''));
        if ($line1 === '' || $fullName === '' || $phone === '' || $city === '' || $state === '' || $pincode === '') {
            return 0;
        }
        return (int) $this->save_address([
            'user_id'      => $user_id,
            'full_name'    => $fullName,
            'company_name' => trim((string) ($addr['company_name'] ?? '')) ?: null,
            'phone'        => $phone,
            'line1'        => $line1,
            'line2'        => trim((string) ($addr['line2'] ?? $addr['shipping_line2'] ?? '')),
            'city'         => $city,
            'state'        => $state,
            'pincode'      => $pincode,
            'country'      => trim((string) ($addr['country'] ?? $addr['shipping_country'] ?? 'Malaysia')) ?: 'Malaysia',
            'label'        => 'Home',
            'address_type' => 'shipping',
            'is_default'   => 1,
        ]);
    }

    /** If address book is empty, copy shipping details from the user's latest order. */
    public function backfill_address_from_latest_order(int $user_id): bool {
        $addrs = $this->get_addresses($user_id);
        if (!empty($addrs)) {
            return false;
        }
        $order = $this->db->where('user_id', $user_id)
            ->where('shipping_line1 !=', '')
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get('orders')
            ->row_array();
        if (!$order) {
            return false;
        }
        return $this->ensure_default_shipping_address($user_id, $order) > 0;
    }

    public function delete_address($id, $user_id) {
        return $this->db->where(['id' => $id, 'user_id' => $user_id])->delete('addresses');
    }

    // Wishlist
    public function get_wishlist($user_id) {
        $rows = $this->db->select('w.*, p.name, p.price, p.sale_price, p.thumbnail, p.slug, p.stock, p.status')
                        ->from('wishlist w')
                        ->join('products p', 'p.id = w.product_id')
                        ->where('w.user_id', $user_id)
                        ->get()->result_array();
        if (empty($rows)) {
            return $rows;
        }
        $this->load->model('Sk_Product_model');
        foreach ($rows as &$row) {
            $wishlistId = (int)($row['id'] ?? 0);
            $product = [
                'id'         => (int)($row['product_id'] ?? 0),
                'stock'      => (int)($row['stock'] ?? 0),
                'price'      => $row['price'] ?? null,
                'sale_price' => $row['sale_price'] ?? null,
                'thumbnail'  => $row['thumbnail'] ?? null,
            ];
            $this->Sk_Product_model->attach_variants($product);
            $row['id'] = $wishlistId;
            $row['stock'] = (int)($product['stock'] ?? 0);
            $row['in_stock'] = !empty($product['in_stock']);
            $row['is_out_of_stock'] = !empty($product['is_out_of_stock']);
            $row['variants'] = $product['variants'] ?? [];
            $row['default_variant_id'] = $product['default_variant_id'] ?? null;
            $row['unit_label'] = $product['unit_label'] ?? null;
            if (isset($product['price'])) {
                $row['price'] = $product['price'];
            }
            if (array_key_exists('sale_price', $product)) {
                $row['sale_price'] = $product['sale_price'];
            }
            if (!empty($product['thumbnail'])) {
                $row['thumbnail'] = $product['thumbnail'];
            }
        }
        unset($row);
        return $rows;
    }

    public function wishlist_toggle($user_id, $product_id) {
        $exists = $this->db->where(['user_id' => $user_id, 'product_id' => $product_id])
                           ->count_all_results('wishlist');
        if ($exists) {
            $this->db->where(['user_id' => $user_id, 'product_id' => $product_id])->delete('wishlist');
            return 'removed';
        } else {
            $this->db->insert('wishlist', ['user_id' => $user_id, 'product_id' => $product_id, 'created_at' => date('Y-m-d H:i:s')]);
            return 'added';
        }
    }

    // Admin
    public function get_all_admin($limit, $offset, $search = '') {
        if ($search) {
            $this->db->group_start()->like('name', $search)->or_like('email', $search)->group_end();
        }
        return $this->db->order_by('created_at', 'DESC')->limit($limit, $offset)->get('users')->result_array();
    }

    public function count_admin($search = '') {
        if ($search) {
            $this->db->group_start()->like('name', $search)->or_like('email', $search)->group_end();
        }
        return $this->db->count_all_results('users');
    }

    public function total_users()     { return $this->db->count_all('users'); }
    public function new_users_today() {
        return $this->db->where('DATE(created_at) = CURDATE()', null, false)->count_all_results('users');
    }

    /**
     * Permanently remove a customer and personal data from the database.
     * Order records are kept for reports/invoices; user_id is cleared and PII anonymized.
     * Orders are never deleted by this action.
     */
    public function hard_delete($id): array {
        $id = (int)$id;
        $user = $this->get_by_id($id);
        if (!$user) {
            return ['ok' => false, 'message' => 'Customer not found.'];
        }

        $orderCount = 0;
        if ($this->db->table_exists('orders') && $this->db->field_exists('user_id', 'orders')) {
            $orderCount = (int)$this->db->where('user_id', $id)->count_all_results('orders');
        }

        // Make sure history tables can unlink the customer without violating NOT NULL
        foreach ([
            'orders',
            'affiliate_enquiries',
            'contact_enquiries',
            'activity_logs',
            'jwt_blacklist',
            'promo_usage',
            'affiliate_commissions',
            'sk_notification_logs',
            'reviews',
        ] as $table) {
            $this->_ensure_user_id_nullable($table);
        }

        $this->db->trans_start();
        // Avoid orphan FK failures while unlinking history rows
        $this->db->query('SET FOREIGN_KEY_CHECKS=0');

        $unlinkError = $this->_hard_delete_user_related($id);
        if ($unlinkError !== null) {
            $this->db->query('SET FOREIGN_KEY_CHECKS=1');
            $this->db->trans_rollback();
            return ['ok' => false, 'message' => $unlinkError];
        }

        $deleted = $this->db->where('id', $id)->delete('users');
        $this->db->query('SET FOREIGN_KEY_CHECKS=1');
        $this->db->trans_complete();

        if ($deleted === false || !$this->db->trans_status()) {
            $dbErr = trim((string)$this->db->error()['message']);
            $msg = 'Could not delete customer.';
            if ($orderCount > 0) {
                $msg .= ' This customer has ' . $orderCount . ' order(s). Orders are kept; the account should unlink automatically.';
            }
            if ($dbErr !== '') {
                $msg .= ' Database: ' . $dbErr;
            } else {
                $msg .= ' A related record is still linked to this account.';
            }
            return ['ok' => false, 'message' => $msg];
        }

        $msg = 'Customer permanently deleted.';
        if ($orderCount > 0) {
            $msg .= ' ' . $orderCount . ' order(s) were kept for reports/invoices (customer link removed).';
        }
        return ['ok' => true, 'message' => $msg];
    }

    /**
     * @return string|null Error message, or null on success
     */
    private function _hard_delete_user_related(int $userId): ?string {
        if ($this->db->table_exists('reviews')) {
            $reviews = $this->db->select('id, product_id')
                ->where('user_id', $userId)
                ->get('reviews')->result_array();
            $productIds = [];
            foreach ($reviews as $rev) {
                $revId = (int)$rev['id'];
                $productIds[(int)$rev['product_id']] = true;
                if ($this->db->table_exists('review_media')) {
                    $media = $this->db->where('review_id', $revId)->get('review_media')->result_array();
                    foreach ($media as $m) {
                        $full = FCPATH . ($m['file_path'] ?? '');
                        if (!empty($m['file_path']) && is_file($full)) {
                            @unlink($full);
                        }
                    }
                    $this->db->where('review_id', $revId)->delete('review_media');
                }
            }
            $this->db->where('user_id', $userId)->delete('reviews');
            foreach (array_keys($productIds) as $productId) {
                $this->_recalc_product_rating((int)$productId);
            }
        }

        // Personal / wallet data — delete
        $this->_delete_rows_for_user('customer_wallet_transactions', $userId);
        $this->_delete_rows_for_user('customer_wallets', $userId);

        foreach ([
            'addresses',
            'wishlist',
            'cart',
            'sk_device_tokens',
            'jwt_blacklist',
            'promo_usage',
            'password_resets',
            'user_otps',
            'otps',
            'sk_otp_logs',
        ] as $table) {
            $this->_delete_rows_for_user($table, $userId);
        }

        if ($this->db->table_exists('carts')) {
            $cartIds = array_column(
                $this->db->select('id')->where('user_id', $userId)->get('carts')->result_array(),
                'id'
            );
            if ($cartIds && $this->db->table_exists('cart_items')) {
                $this->db->where_in('cart_id', $cartIds)->delete('cart_items');
            }
            $this->db->where('user_id', $userId)->delete('carts');
        }

        // Orders: keep rows, clear link + anonymize PII (never delete order history)
        if ($this->db->table_exists('orders') && $this->db->field_exists('user_id', 'orders')) {
            $anon = ['user_id' => null];
            foreach ([
                'shipping_name' => 'Deleted Customer',
                'shipping_phone' => null,
                'shipping_email' => null,
                'billing_name' => 'Deleted Customer',
                'billing_phone' => null,
                'guest_email' => null,
                'customer_email' => null,
                'customer_phone' => null,
                'customer_name' => 'Deleted Customer',
            ] as $col => $val) {
                if ($this->db->field_exists($col, 'orders')) {
                    $anon[$col] = $val;
                }
            }
            if ($this->db->where('user_id', $userId)->update('orders', $anon) === false) {
                $err = trim((string)($this->db->error()['message'] ?? ''));
                return 'Could not unlink customer orders'
                    . ($err !== '' ? (': ' . $err) : '.')
                    . ' Orders are kept; please contact support if this continues.';
            }
        }

        // Other history — keep if useful, otherwise clear user link
        foreach ([
            'affiliate_enquiries',
            'contact_enquiries',
            'activity_logs',
            'affiliate_commissions',
            'sk_notification_logs',
        ] as $table) {
            if (!$this->_nullify_user_id($table, $userId)) {
                // Fallback: delete non-critical history so account can still be removed
                if (in_array($table, ['activity_logs', 'sk_notification_logs'], true)) {
                    $this->_delete_rows_for_user($table, $userId);
                }
            }
        }

        return null;
    }

    private function _delete_rows_for_user(string $table, int $userId): void {
        if ($this->db->table_exists($table) && $this->db->field_exists('user_id', $table)) {
            $this->db->where('user_id', $userId)->delete($table);
        }
    }

    private function _nullify_user_id(string $table, int $userId): bool {
        if (!$this->db->table_exists($table) || !$this->db->field_exists('user_id', $table)) {
            return true;
        }
        $this->_ensure_user_id_nullable($table);
        $ok = $this->db->where('user_id', $userId)->update($table, ['user_id' => null]);
        return $ok !== false;
    }

    /** Allow unlinking history without deleting business records. */
    private function _ensure_user_id_nullable(string $table): void {
        if (!$this->db->table_exists($table) || !$this->db->field_exists('user_id', $table)) {
            return;
        }
        $col = $this->db->query("SHOW COLUMNS FROM `{$table}` LIKE 'user_id'")->row_array();
        if (!$col) {
            return;
        }
        if (strtoupper((string)($col['Null'] ?? '')) === 'YES') {
            return;
        }
        $type = (string)($col['Type'] ?? 'INT');
        // Keep unsigned / int shape; drop NOT NULL so we can unlink deleted customers
        $this->db->query("ALTER TABLE `{$table}` MODIFY COLUMN `user_id` {$type} NULL DEFAULT NULL");
    }

    private function _recalc_product_rating(int $productId): void {
        if (!$this->db->table_exists('products') || $productId <= 0) {
            return;
        }
        $row = $this->db
            ->select('AVG(rating) AS avg_r, COUNT(*) AS cnt')
            ->where('product_id', $productId)
            ->where('status', 'approved')
            ->get('reviews')->row_array();
        $this->db->where('id', $productId)->update('products', [
            'avg_rating'   => round((float)($row['avg_r'] ?? 0), 2),
            'review_count' => (int)($row['cnt'] ?? 0),
        ]);
    }
}
