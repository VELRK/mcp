<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sk_Order_model extends CI_Model {

    public function ensure_order_source_schema(): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        if (!$this->db->field_exists('order_source', 'orders')) {
            $this->db->query(
                "ALTER TABLE `orders` ADD COLUMN `order_source` VARCHAR(16) NOT NULL DEFAULT 'unknown' AFTER `payment_method`"
            );
        }
    }

    public function create($data, $items) {
        $data['created_at'] = date('Y-m-d H:i:s');
        // Temporary unique value until we have insert_id for G2D10001-style number.
        $data['order_number'] = 'TMP' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 12));
        $this->db->insert('orders', $data);
        $order_id = (int)$this->db->insert_id();

        $order_number = $this->format_order_number($order_id);
        $this->db->where('id', $order_id)->update('orders', ['order_number' => $order_number]);

        foreach ($items as $item) {
            $item['order_id'] = $order_id;
            $this->db->insert('order_items', $item);
            $this->load->model('Sk_Product_model');
            $this->Sk_Product_model->reduce_stock(
                $item['product_id'],
                $item['quantity'],
                !empty($item['variant_id']) ? (int)$item['variant_id'] : null
            );
        }
        return $order_id;
    }

    public function get_by_id($id, $user_id = null) {
        $this->db->where('o.id', $id);
        if ($user_id) $this->db->where('o.user_id', $user_id);
        $order = $this->db->select('o.*, u.name as customer_name, u.email as customer_email')
                          ->from('orders o')
                          ->join('users u', 'u.id = o.user_id', 'left')
                          ->get()->row_array();
        if ($order) {
            $order['items'] = $this->get_items($id);
            $order['payment'] = $this->get_payment($id);
        }
        return $order;
    }

    public function get_items($order_id) {
        return $this->db->where('order_id', $order_id)->get('order_items')->result_array();
    }

    public function get_payment($order_id) {
        return $this->db->where('order_id', $order_id)->order_by('id', 'DESC')->limit(1)->get('payments')->row_array();
    }

    public function get_payment_by_rzp_order_id($razorpay_order_id) {
        $razorpay_order_id = trim((string)$razorpay_order_id);
        if ($razorpay_order_id === '') {
            return null;
        }
        return $this->db->where('razorpay_order_id', $razorpay_order_id)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get('payments')
            ->row_array();
    }

    public function get_user_orders($user_id, $limit = 10, $offset = 0) {
        return $this->db->where('user_id', $user_id)
                        ->where_not_in('status', ['abandoned', 'payment_attempt'])
                        ->order_by('created_at', 'DESC')
                        ->limit($limit, $offset)
                        ->get('orders')->result_array();
    }

    public function update_status($order_id, $status) {
        $order_id = (int)$order_id;
        $prev = $this->db->select('status')->where('id', $order_id)->get('orders')->row_array();
        $prevStatus = (string)($prev['status'] ?? '');

        $now = date('Y-m-d H:i:s');
        $data = [
            'status'            => $status,
            'status_updated_at' => $now,
        ];
        if ($status === 'confirmed') {
            $data['confirmed_at'] = $now;
        }
        if ($status === 'processing') {
            $data['processing_at'] = $now;
        }
        if ($status === 'shipped') {
            $data['shipped_at'] = $now;
        }
        if ($status === 'delivered') {
            $data['delivered_at'] = $now;
        }
        $this->db->where('id', $order_id)->update('orders', $data);

        // Cancel / return → put inventory back once (order confirmed had reduced stock)
        if (in_array($status, ['cancelled', 'returned'], true)
            && !in_array($prevStatus, ['cancelled', 'returned'], true)) {
            $this->restore_stock_for_order($order_id);
        }
    }

    public function get_by_tracking($tracking_number) {
        if ($tracking_number === '') {
            return null;
        }
        $order = $this->db->where('tracking_number', $tracking_number)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get('orders')->row_array();
        if (!$order) {
            return null;
        }
        $order['items'] = $this->get_items($order['id']);
        return $order;
    }

    public function update_payment_status($order_id, $status) {
        $this->db->where('id', $order_id)->update('orders', ['payment_status' => $status]);
    }

    public function save_payment($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('payments', $data);
        return $this->db->insert_id();
    }

    public function update_payment($razorpay_order_id, $data) {
        $this->db->where('razorpay_order_id', $razorpay_order_id)->update('payments', $data);
    }

    public function get_all_admin($limit, $offset, $filters = []) {
        $this->db->select('o.*, u.name as customer_name, u.email as customer_email')
                 ->from('orders o')
                 ->join('users u', 'u.id = o.user_id', 'left');
        $this->_apply_admin_filters($filters);
        $this->db->order_by('o.created_at', 'DESC')->limit($limit, $offset);
        return $this->db->get()->result_array();
    }

    public function count_admin($filters = []) {
        $this->db->from('orders o');
        $this->_apply_admin_filters($filters);
        return $this->db->count_all_results();
    }

    /**
     * Admin orders list filters.
     * tab=orders (default): all statuses except payment_attempt (shown as Abandoned).
     * tab=abandoned: only payment_attempt.
     */
    protected function _apply_admin_filters(array $filters): void {
        $tab = ($filters['tab'] ?? 'orders') === 'abandoned' ? 'abandoned' : 'orders';

        if ($tab === 'abandoned') {
            $this->db->where('o.status', 'payment_attempt');
        } else {
            // Main tab: never show abandoned / payment_attempt
            if (!empty($filters['status']) && $filters['status'] !== 'payment_attempt' && $filters['status'] !== 'abandoned') {
                $this->db->where('o.status', $filters['status']);
            } else {
                $this->db->where('o.status !=', 'payment_attempt');
            }
        }

        if (!empty($filters['payment_status'])) {
            $this->db->where('o.payment_status', $filters['payment_status']);
        }
        if (!empty($filters['order_source']) && in_array($filters['order_source'], ['web', 'app', 'unknown'], true)) {
            $this->db->where('o.order_source', $filters['order_source']);
        }
        if (!empty($filters['search'])) {
            $this->db->like('o.order_number', $filters['search']);
        }
    }

    /** Admin label for order status (payment_attempt → Abandoned). */
    public static function status_label(string $status): string {
        $map = [
            'payment_attempt' => 'Abandoned',
            'pending'         => 'Pending',
            'confirmed'       => 'Confirmed',
            'processing'      => 'Processing',
            'shipped'         => 'Shipped',
            'delivered'       => 'Delivered',
            'cancelled'       => 'Cancelled',
            'returned'        => 'Returned',
        ];
        return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    /** Admin label for checkout channel. */
    public static function source_label(?string $source): string {
        $map = [
            'web'     => 'Web',
            'app'     => 'App',
            'unknown' => 'Unknown',
        ];
        $key = strtolower(trim((string) $source));
        if ($key === '' || !isset($map[$key])) {
            return 'Unknown';
        }
        return $map[$key];
    }

    // Stats
    public function total_orders()   { return $this->db->count_all('orders'); }
    public function pending_orders() {
        return $this->db->where_in('status', ['pending', 'payment_attempt'])->count_all_results('orders');
    }
    public function total_revenue()  {
        $r = $this->db->select_sum('total')->where('payment_status', 'paid')->get('orders')->row();
        return $r->total ?? 0;
    }
    public function monthly_revenue() {
        $r = $this->db->select_sum('total')
                      ->where('payment_status', 'paid')
                      ->where('MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())', null, false)
                      ->get('orders')->row();
        return $r->total ?? 0;
    }
    public function revenue_by_day($days = 30) {
        $q = $this->db
            ->select('DATE(created_at) as date, SUM(total) as revenue, COUNT(*) as orders', false)
            ->where('payment_status', 'paid')
            ->where('created_at >=', date('Y-m-d', strtotime("-{$days} days")))
            ->group_by('DATE(created_at)')
            ->order_by('date', 'ASC')
            ->get('orders');
        $rows = ($q && is_object($q)) ? $q->result_array() : [];

        // Build a full date range so chart shows every day (zero for days with no orders)
        $map = [];
        foreach ($rows as $r) $map[$r['date']] = (float) $r['revenue'];

        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $result[] = ['date' => date('d M', strtotime($d)), 'revenue' => $map[$d] ?? 0];
        }
        return $result;
    }
    public function top_products($limit = 5) {
        $q = $this->db->select('oi.product_id, MAX(oi.product_name) as product_name, SUM(oi.quantity) as qty_sold, SUM(oi.subtotal) as revenue', false)
                        ->from('order_items oi')
                        ->join('orders o', 'o.id = oi.order_id')
                        ->where('o.payment_status', 'paid')
                        ->group_by('oi.product_id')
                        ->order_by('qty_sold', 'DESC')
                        ->limit($limit)
                        ->get();
        return ($q && is_object($q)) ? $q->result_array() : [];
    }

    /**
     * Orders that include a product (for inventory detail).
     * @return array{rows:array,total:int}
     */
    public function get_orders_for_product(int $productId, int $limit = 20, int $offset = 0): array {
        $productId = (int)$productId;
        $total = (int)$this->db->where('product_id', $productId)->count_all_results('order_items');

        $rows = $this->db->select('o.id, o.order_number, o.status, o.created_at, o.payment_status,
                oi.quantity, oi.variant_id, oi.variant_label, oi.price, oi.subtotal')
            ->from('order_items oi')
            ->join('orders o', 'o.id = oi.order_id')
            ->where('oi.product_id', $productId)
            ->order_by('o.created_at', 'DESC')
            ->limit($limit, $offset)
            ->get()->result_array();

        return ['rows' => $rows, 'total' => $total];
    }

    public function recent_orders($limit = 5) {
        $q = $this->db->select('o.*, u.name as customer_name')
                        ->from('orders o')
                        ->join('users u', 'u.id = o.user_id', 'left')
                        ->order_by('o.created_at', 'DESC')
                        ->limit($limit)->get();
        return ($q && is_object($q)) ? $q->result_array() : [];
    }

    /**
     * Storefront order number: G2D10001, G2D10002, …
     * (order id 1 → G2D10001)
     */
    private function format_order_number($order_id) {
        $id = max(1, (int)$order_id);
        return 'G2D' . (10000 + $id);
    }

    /**
     * True when the user has a qualifying order line for this product (paid, COD, or wallet).
     *
     * @return array{order_id:int,order_item_id:int}|null
     */
    public function user_purchased_product($user_id, $product_id) {
        $user_id = (int)$user_id;
        $product_id = (int)$product_id;
        if ($user_id <= 0 || $product_id <= 0) {
            return null;
        }

        $row = $this->db
            ->select('oi.id AS order_item_id, oi.order_id')
            ->from('order_items oi')
            ->join('orders o', 'o.id = oi.order_id')
            ->where('oi.product_id', $product_id)
            ->where('o.user_id', $user_id)
            ->where_not_in('o.status', ['cancelled', 'returned'])
            ->group_start()
                ->where('o.payment_status', 'paid')
                ->or_where('o.payment_method', 'cod')
            ->group_end()
            ->order_by('o.id', 'DESC')
            ->limit(1)
            ->get()
            ->row_array();

        if (!$row) {
            return null;
        }

        return [
            'order_id'      => (int)$row['order_id'],
            'order_item_id' => (int)$row['order_item_id'],
        ];
    }

    /**
     * Allow unpaid Razorpay checkout rows to use status=payment_attempt
     * (VARCHAR or ENUM extended at runtime).
     */
    public function ensure_payment_attempt_status(): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $row = $this->db->query(
            "SELECT DATA_TYPE, COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'status'"
        )->row_array();
        if (!$row) {
            return;
        }
        $dataType = strtolower((string)($row['DATA_TYPE'] ?? ''));
        $colType  = (string)($row['COLUMN_TYPE'] ?? '');
        if ($dataType === 'enum') {
            if (stripos($colType, "'payment_attempt'") === false) {
                // Prefer VARCHAR so future statuses need no ALTER ENUM.
                $this->db->query(
                    "ALTER TABLE `orders` MODIFY COLUMN `status` VARCHAR(32) NOT NULL DEFAULT 'pending'"
                );
            }
            return;
        }
        if ($dataType === 'varchar' || $dataType === 'char') {
            $len = 32;
            if (preg_match('/\((\d+)\)/', $colType, $m)) {
                $len = (int)$m[1];
            }
            if ($len < 20) {
                $this->db->query(
                    "ALTER TABLE `orders` MODIFY COLUMN `status` VARCHAR(32) NOT NULL DEFAULT 'pending'"
                );
            }
        }
    }

    /** Statuses customers may cancel (before shipment). */
    public function customer_cancellable_statuses(): array {
        return ['payment_attempt', 'pending', 'confirmed', 'processing'];
    }

    public function can_customer_cancel(array $order): ?string {
        if (($order['status'] ?? '') === 'cancelled') {
            return null;
        }
        if (!in_array($order['status'] ?? '', $this->customer_cancellable_statuses(), true)) {
            return 'This order can no longer be cancelled.';
        }
        return null;
    }

    public function restore_stock_for_order(int $orderId): void {
        $this->ensure_stock_restored_schema();
        $order = $this->db->select('id, stock_restored_at')->where('id', $orderId)->get('orders')->row_array();
        if (!$order) {
            return;
        }
        // Already restored (cancel + return / double call) — do not add stock twice
        if (!empty($order['stock_restored_at'])) {
            return;
        }

        $this->load->model('Sk_Product_model');
        foreach ($this->get_items($orderId) as $item) {
            $qty = (int)($item['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $this->Sk_Product_model->restore_stock(
                (int)$item['product_id'],
                $qty,
                !empty($item['variant_id']) ? (int)$item['variant_id'] : null
            );
        }
        $this->db->where('id', $orderId)->update('orders', [
            'stock_restored_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** One-time stock return flag so cancel/return never double-adds inventory. */
    public function ensure_stock_restored_schema(): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        if (!$this->db->field_exists('stock_restored_at', 'orders')) {
            $this->db->query('ALTER TABLE `orders` ADD COLUMN `stock_restored_at` DATETIME NULL DEFAULT NULL');
        }
    }

    /**
     * Cancel order with wallet / Razorpay refunds and stock restore.
     *
     * @return array{ok: bool, message: string}
     */
    public function cancel_order(int $orderId, ?int $userId = null, array $settings = [], bool $adminForce = false): array {
        $order = $this->get_by_id($orderId, $userId);
        if (!$order) {
            return ['ok' => false, 'message' => 'Order not found.'];
        }
        if (($order['status'] ?? '') === 'cancelled') {
            return ['ok' => true, 'message' => 'Order already cancelled.'];
        }

        if (!$adminForce) {
            $block = $this->can_customer_cancel($order);
            if ($block) {
                return ['ok' => false, 'message' => $block];
            }
        } elseif (in_array($order['status'] ?? '', ['delivered', 'returned'], true)) {
            return ['ok' => false, 'message' => 'Delivered or returned orders cannot be cancelled.'];
        }

        $onlineRefund = 0.0;
        $payment = $order['payment'] ?? $this->get_payment($orderId);
        $wasPaid = ($order['payment_status'] ?? '') === 'paid';
        if ($wasPaid && ($order['payment_method'] ?? '') === 'razorpay') {
            $onlineRefund = round(max(0, (float)$order['total']), 2);
        }

        if ($onlineRefund > 0) {
            $paymentId = $payment['razorpay_payment_id'] ?? '';
            if (!$paymentId) {
                return ['ok' => false, 'message' => 'Payment record missing. Contact support to cancel this order.'];
            }
            $refund = $this->_razorpay_refund($paymentId, $onlineRefund, $settings);
            if (!$refund['ok']) {
                return ['ok' => false, 'message' => $refund['message']];
            }
        }

        $this->restore_stock_for_order($orderId);

        $newPaymentStatus = $wasPaid ? 'refunded' : 'failed';
        $this->update_status($orderId, 'cancelled');
        // update_status also tries restore; stock_restored_at prevents double add
        $this->update_payment_status($orderId, $newPaymentStatus);

        $msg = 'Order cancelled.';
        if ($wasPaid) {
            $msg = 'Order cancelled and refund initiated.';
        }

        return ['ok' => true, 'message' => $msg];
    }

    /** @return array{ok: bool, message: string} */
    protected function _razorpay_refund(string $paymentId, float $amountRm, array $settings): array {
        if ($amountRm <= 0) {
            return ['ok' => true, 'message' => ''];
        }
        $keyId     = $settings['razorpay_key_id'] ?? config_item('razorpay_key_id');
        $keySecret = $settings['razorpay_key_secret'] ?? config_item('razorpay_key_secret');
        if (!$keyId || !$keySecret) {
            return ['ok' => false, 'message' => 'Payment gateway not configured for refund.'];
        }

        $currency = strtoupper(sk_currency_code($settings));
        $payload = json_encode([
            'amount'   => (int)round($amountRm * 100),
            'currency' => $currency,
            'notes'    => ['reason' => 'Order cancelled by customer'],
        ]);

        $ch = curl_init('https://api.razorpay.com/v1/payments/' . rawurlencode($paymentId) . '/refund');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_USERPWD        => $keyId . ':' . $keySecret,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $body = json_decode($response ?: '', true);
        if ($httpCode >= 200 && $httpCode < 300 && !empty($body['id'])) {
            return ['ok' => true, 'message' => ''];
        }

        $err = is_array($body) ? ($body['error']['description'] ?? $body['error']['reason'] ?? '') : '';
        log_message('error', 'Razorpay refund failed for payment ' . $paymentId . ': ' . $response);
        return ['ok' => false, 'message' => $err ?: 'Online payment refund failed. Please contact support.'];
    }
}
