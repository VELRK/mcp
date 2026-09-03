<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/api/Sk_Base_Api.php';

class Sk_Order extends Sk_Base_Api {

    public function checkout() {
        $this->auth_required();
        $data = $this->body();

        // Validate address
        $addr = $data['address'] ?? null;
        if (!$addr || empty($addr['full_name']) || empty($addr['line1'])) {
            return $this->error('Shipping address is required.');
        }
        $fullNameCheck = trim((string) ($addr['full_name'] ?? ''));
        if ($fullNameCheck === '' || preg_match('/^(User|SER|USR|CUST)\s*\d{1,8}$/i', $fullNameCheck)) {
            return $this->error('Please enter your real full name (not a generated code like SER001).');
        }
        $addr['full_name'] = $fullNameCheck;
        $addr['company_name'] = trim((string) ($addr['company_name'] ?? '')) ?: '';

        // Optional email (top-level or nested) — unique when provided
        $emailRaw = $data['email'] ?? ($data['customer_email'] ?? ($addr['email'] ?? null));
        if ($emailRaw !== null && trim((string) $emailRaw) !== '') {
            $emailCheck = strtolower(trim((string) $emailRaw));
            if (!filter_var($emailCheck, FILTER_VALIDATE_EMAIL)) {
                return $this->error('Invalid email address.');
            }
            $this->Sk_User_model->ensure_otp_user_schema();
            if ($this->Sk_User_model->email_exists($emailCheck, (int) $this->user['user_id'])) {
                return $this->error('This email is already in use.');
            }
            $data['email'] = $emailCheck;
            $addr['email'] = $emailCheck;
        }

        $settings = $this->get_settings();
        $this->load->helper('sk_isms');
        $shippingPhone = sk_isms_normalize_phone($addr['phone'] ?? '', $settings);
        if ($shippingPhone === '') {
            return $this->error(sk_isms_phone_error());
        }
        $addr['phone'] = $shippingPhone;

        // Build cart
        $user_id = $this->user['user_id'];
        $items   = $this->db->where('user_id', $user_id)->get('cart')->result_array();
        if (empty($items)) return $this->error('Cart is empty.');

        $subtotal = 0;
        $order_items = [];
        $stock_issues = [];

        foreach ($items as $item) {
            $p = $this->Sk_Product_model->get_by_id($item['product_id']);
            if (!$p || $p['status'] !== 'active') {
                $name = $p['name'] ?? 'Product';
                return $this->error("Product '{$name}' is no longer available.");
            }

            $variant = null;
            $variant_id = !empty($item['variant_id']) ? (int)$item['variant_id'] : null;
            if ($variant_id) {
                $this->load->model('Sk_Product_variant_model');
                $variant = $this->Sk_Product_variant_model->get_by_id($variant_id);
                if (!$variant || (int)$variant['product_id'] !== (int)$p['id']) {
                    return $this->error("Invalid variant for '{$p['name']}'.");
                }
            } elseif (!empty($p['variants'])) {
                foreach ($p['variants'] as $v) {
                    if (!empty($v['is_default'])) { $variant = $v; break; }
                }
                if (!$variant) $variant = $p['variants'][0];
            }

            $stock = $variant ? (int)$variant['stock'] : (int)$p['stock'];
            $need  = (int)$item['quantity'];
            if ($stock < $need) {
                $label = trim((string)($variant['label'] ?? ''));
                $title = $label !== '' ? ($p['name'] . ' (' . $label . ')') : $p['name'];
                $stock_issues[] = [
                    'product_id' => (int)$p['id'],
                    'variant_id' => $variant ? ((int)($variant['id'] ?? 0) ?: null) : null,
                    'name'       => $title,
                    'available'  => max(0, $stock),
                    'requested'  => $need,
                ];
                continue;
            }

            $price = $variant
                ? ($variant['effective_price'] ?? $variant['sale_price'] ?? $variant['price'])
                : ($p['effective_price'] ?? $p['sale_price'] ?? $p['price']);
            $sub      = round($price * $item['quantity'], 2);
            $subtotal += $sub;

            $variant_label = $variant['label'] ?? ($p['unit_label'] ?? null);
            $line = [
                'product_id'   => $p['id'],
                'product_name' => $p['name'] . ($variant_label ? ' (' . $variant_label . ')' : ''),
                'product_sku'  => $variant['sku'] ?? $p['sku'],
                'thumbnail'    => $p['thumbnail'],
                'price'        => $price,
                'quantity'     => $item['quantity'],
                'subtotal'     => $sub,
            ];
            if ($this->db->field_exists('variant_id', 'order_items')) {
                $line['variant_id'] = $variant['id'] ?? null;
            }
            if ($this->db->field_exists('variant_label', 'order_items')) {
                $line['variant_label'] = $variant_label;
            }
            if ($this->db->field_exists('vendor_id', 'order_items') && !empty($p['vendor_id'])) {
                $line['vendor_id'] = (int)$p['vendor_id'];
            }
            $order_items[] = $line;
        }

        if (!empty($stock_issues)) {
            $parts = [];
            foreach ($stock_issues as $s) {
                if ((int)$s['available'] <= 0) {
                    $parts[] = "'{$s['name']}' is out of stock";
                } else {
                    $parts[] = "'{$s['name']}' (available {$s['available']}, requested {$s['requested']})";
                }
            }
            $msg = count($stock_issues) === 1
                ? ('Cannot place order: ' . $parts[0] . '.')
                : ('Cannot place order — stock issues: ' . implode('; ', $parts) . '.');
            return $this->error($msg, 400, ['stock_issues' => $stock_issues]);
        }

        // Promo — regular coupon
        $discount = 0;
        $promo_code = null;
        $check = null;

        if (!empty($data['promo_code'])) {
            $code = $data['promo_code'];
            $check = $this->Sk_Promo_model->validate($code, $user_id, $subtotal);
            if ($check['valid']) {
                $discount   = $check['discount'];
                $promo_code = strtoupper(trim($code));
            }
        }

        $payment_method = strtolower(trim((string)($data['payment_method'] ?? 'razorpay')));
        if ($payment_method === 'wallet') {
            return $this->error('Wallet payments are no longer available. Please pay with Razorpay or COD.');
        }
        if (!in_array($payment_method, ['razorpay', 'cod'], true)) {
            $payment_method = 'razorpay';
        }

        $code_discount = $discount;

        $goodsAfterPromo = max(0, $subtotal - $code_discount);
        $shipping = ($goodsAfterPromo <= 0)
            ? 0
            : ($goodsAfterPromo >= ($settings['free_shipping_above'] ?? 999) ? 0 : ($settings['shipping_charge'] ?? 50));
        $taxable_amount = max(0, $subtotal - $discount);
        // Storefront does not charge/show GST
        $tax      = 0;
        $total    = round($taxable_amount + $shipping + $tax, 2);

        $gateway_amount = $total;
        $is_paid_now    = false;
        $is_cod         = $payment_method === 'cod';
        $confirm_now    = $is_cod;
        $is_razorpay_due = !$confirm_now && $payment_method === 'razorpay';

        $this->_ensure_order_wallet_schema();
        $this->_ensure_order_discount_schema();
        $this->_ensure_order_source_schema();
        $this->Sk_Order_model->ensure_payment_attempt_status();
        $this->load->helper('sk_vendor_dashboard');
        sk_vendor_dashboard_ensure_schema();
        $now = date('Y-m-d H:i:s');
        $order_data = [
            'user_id'          => $user_id,
            'subtotal'         => $subtotal,
            'shipping'         => $shipping,
            'tax'              => $tax,
            'discount'         => $discount,
            'wallet_discount'  => 0,
            'promo_code'       => $promo_code,
            'total'            => $total,
            'wallet_amount'    => 0,
            'order_source'     => $this->_resolve_order_source($data),
            'payment_method'   => $payment_method,
            'payment_status'   => $is_paid_now ? 'paid' : 'pending',
            'status'           => $confirm_now ? 'confirmed' : ($is_razorpay_due ? 'payment_attempt' : 'pending'),
            'status_updated_at'=> $now,
            'confirmed_at'     => $confirm_now ? $now : null,
            'notes'            => trim($data['note'] ?? $data['notes'] ?? '') ?: null,
            'shipping_name'    => $addr['full_name'],
            'shipping_phone'   => $shippingPhone,
            'shipping_line1'   => $addr['line1'],
            'shipping_line2'   => $addr['line2'] ?? '',
            'shipping_city'    => $addr['city'],
            'shipping_state'   => $addr['state'],
            'shipping_pincode' => $addr['pincode'],
            'shipping_country' => $addr['country'] ?? ($settings['default_country'] ?? 'India'),
        ];

        // Billing address (optional) — checkbox "same as shipping" sends billing_same=true
        $billingSame = !empty($data['billing_same']) || empty($data['billing_address']);
        $bill = $billingSame ? $addr : ($data['billing_address'] ?? $addr);
        $billingPhone = $billingSame
            ? $shippingPhone
            : sk_isms_normalize_phone($bill['phone'] ?? '', $settings);
        if (!$billingSame && $billingPhone === '') {
            return $this->error('A valid mobile number is required for billing.');
        }
        $this->_ensure_order_billing_schema();
        $order_data['billing_name']     = $bill['full_name'] ?? $addr['full_name'];
        $order_data['billing_company']  = trim($bill['company_name'] ?? '') ?: null;
        $order_data['billing_phone']    = $billingPhone ?: $shippingPhone;
        $order_data['billing_line1']    = $bill['line1'] ?? $addr['line1'];
        $order_data['billing_line2']    = $bill['line2'] ?? ($addr['line2'] ?? '');
        $order_data['billing_city']     = $bill['city'] ?? $addr['city'];
        $order_data['billing_state']    = $bill['state'] ?? $addr['state'];
        $order_data['billing_pincode']  = $bill['pincode'] ?? $addr['pincode'];
        $order_data['billing_country']  = $bill['country'] ?? ($addr['country'] ?? 'India');

        // Keep My Addresses in sync for first-time checkout (OTP / new accounts)
        $this->Sk_User_model->ensure_default_shipping_address($user_id, [
            'full_name'    => $addr['full_name'],
            'phone'        => $shippingPhone,
            'line1'        => $addr['line1'],
            'line2'        => $addr['line2'] ?? '',
            'city'         => $addr['city'],
            'state'        => $addr['state'],
            'pincode'      => $addr['pincode'],
            'country'      => $addr['country'] ?? ($settings['default_country'] ?? 'India'),
            'company_name' => $addr['company_name'] ?? '',
        ]);

        // Persist real name (+ optional unique email) from checkout onto the user account
        $this->_sync_user_from_checkout($user_id, $addr, $data);

        $order_id = $this->Sk_Order_model->create($order_data, $order_items);

        // Record promo usage
        if ($promo_code && !empty($check['valid']) && !empty($check['promo'])) {
            $this->Sk_Promo_model->record_usage($check['promo']['id'], $user_id, $order_id);
        }

        // Clear paid products from cart (COD / fully paid). App must also drop these from local cache.
        $this->load->helper('sk_razorpay');
        $cartClearLines = [];
        if ($confirm_now) {
            $cartClearLines = sk_cart_remove_items_for_paid_order($user_id, $order_id);
        }
        $this->db->where('user_id', $user_id)->delete('cart');

        $order = $this->Sk_Order_model->get_by_id($order_id, $user_id);

        // Email + WhatsApp only when order is paid now or COD.
        // Unpaid Razorpay (payment_attempt): notify after payment/verify only.
        $this->load->helper(['sk_mailer', 'sk_invoice', 'sk_whatsapp']);
        sk_invoice_ensure_vendor_schema();
        $settings = $this->get_settings();
        if ($payment_method === 'cod' || $is_paid_now) {
            sk_mail_order_invoice($order, $settings);
            $waStatus = ($order['status'] ?? '') ?: 'confirmed';
            sk_whatsapp_notify_order_status($order, $waStatus, $settings);
        }

        $confirmMsg = $is_razorpay_due
            ? 'Order saved. Complete Razorpay payment to confirm.'
            : 'Order placed successfully.';

        $this->success([
            'order' => $order,
            'confirmed' => $confirm_now,
            'cart_clear_lines' => $cartClearLines,
            'payment' => [
                'requires_gateway' => $is_razorpay_due,
                'gateway_amount'   => $gateway_amount,
                'next_step'        => $is_razorpay_due ? 'create_payment_order' : 'complete',
            ],
        ], $confirmMsg, 200);
    }

    public function index() {
        $this->auth_required();
        $page   = max(1, (int)($this->input->get('page') ?? 1));
        $limit  = 10;
        $offset = ($page - 1) * $limit;
        $orders = $this->Sk_Order_model->get_user_orders($this->user['user_id'], $limit, $offset);
        // Attach items to each order for frontend display
        foreach ($orders as &$o) {
            $o['items'] = $this->Sk_Order_model->get_items($o['id']);
        }
        unset($o);
        $this->success($orders);
    }

    public function show($id) {
        $this->auth_required();
        $order = $this->Sk_Order_model->get_by_id($id, $this->user['user_id']);
        if (!$order) return $this->error('Order not found.', 404);
        $this->success($order);
    }

    public function cancel($id) {
        $this->auth_required();
        $order = $this->Sk_Order_model->get_by_id((int)$id, $this->user['user_id']);
        if (!$order) {
            return $this->error('Order not found.', 404);
        }

        $this->_ensure_order_wallet_schema();

        $result = $this->Sk_Order_model->cancel_order(
            (int)$id,
            (int)$this->user['user_id'],
            $this->get_settings(),
            false
        );
        if (!$result['ok']) {
            return $this->error($result['message'], 400);
        }

        $fresh = $this->Sk_Order_model->get_by_id((int)$id, $this->user['user_id']);
        if ($fresh) {
            $this->load->helper(['sk_mailer', 'sk_whatsapp']);
            $settings = $this->get_settings();
            sk_mail_order_status($fresh, 'cancelled', $settings);
            sk_whatsapp_notify_order_status($fresh, 'cancelled', $settings);
        }

        $this->success([], $result['message']);
    }

    /** Authenticated invoice links for the logged-in customer. */
    public function invoice($id) {
        $this->auth_required();
        $order = $this->Sk_Order_model->get_by_id((int)$id, $this->user['user_id']);
        if (!$order) {
            return $this->error('Order not found.', 404);
        }
        $this->load->helper(['sk_invoice', 'sk_invoice_pdf']);
        $token = sk_invoice_public_token((int)$order['id'], (string)$order['order_number']);
        $this->success([
            'order_id'       => (int)$order['id'],
            'order_number'   => $order['order_number'],
            'download_url'   => site_url('invoice/download/' . (int)$order['id'] . '/' . $token),
            'view_url'       => site_url('invoice/view/' . (int)$order['id'] . '/' . $token),
            'api_download'   => site_url('shopkart-api/order/' . (int)$order['id'] . '/invoice/download'),
        ]);
    }

    /** Stream PDF invoice for the logged-in order owner (Bearer auth). */
    public function invoice_download($id) {
        $this->auth_required();
        $order = $this->Sk_Order_model->get_by_id((int)$id, $this->user['user_id']);
        if (!$order) {
            return $this->error('Order not found.', 404);
        }
        $this->load->helper(['sk_invoice', 'sk_invoice_pdf']);
        sk_invoice_ensure_vendor_schema();
        $settings = $this->get_settings();
        $invoice = sk_invoice_build($order, $settings);
        $pdf = sk_invoice_build_pdf($invoice);
        $filename = 'invoice-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $invoice['order_number'] ?? (string)$id) . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $pdf;
        exit;
    }

    private function _ensure_order_wallet_schema(): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        if (!$this->db->field_exists('wallet_amount', 'orders')) {
            $this->db->query('ALTER TABLE `orders` ADD COLUMN `wallet_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `total`');
        }
    }

    private function _ensure_order_discount_schema(): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        if (!$this->db->field_exists('affiliate_discount', 'orders')) {
            $this->db->query('ALTER TABLE `orders` ADD COLUMN `affiliate_discount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `discount`');
        }
        if (!$this->db->field_exists('wallet_discount', 'orders')) {
            $this->db->query('ALTER TABLE `orders` ADD COLUMN `wallet_discount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `affiliate_discount`');
        }
    }

    private function _ensure_order_billing_schema(): void {
        static $done = false;
        if ($done) return;
        $done = true;
        $cols = [
            'billing_name' => "VARCHAR(150) NULL",
            'billing_company' => "VARCHAR(150) NULL",
            'billing_phone' => "VARCHAR(30) NULL",
            'billing_line1' => "VARCHAR(255) NULL",
            'billing_line2' => "VARCHAR(255) NULL",
            'billing_city' => "VARCHAR(100) NULL",
            'billing_state' => "VARCHAR(100) NULL",
            'billing_pincode' => "VARCHAR(20) NULL",
            'billing_country' => "VARCHAR(80) NULL DEFAULT 'Malaysia'",
        ];
        foreach ($cols as $col => $def) {
            if (!$this->db->field_exists($col, 'orders')) {
                $this->db->query("ALTER TABLE `orders` ADD COLUMN `{$col}` {$def}");
            }
        }
    }

    private function _ensure_order_source_schema(): void {
        $this->Sk_Order_model->ensure_order_source_schema();
    }

    /**
     * Resolve checkout channel: web | app | unknown.
     * Body order_source/platform, or headers X-Order-Source / X-Client-Platform.
     */
    private function _resolve_order_source(array $data): string {
        $raw = $data['order_source'] ?? ($data['platform'] ?? null);
        if ($raw === null || trim((string) $raw) === '') {
            $raw = $this->input->get_request_header('X-Order-Source', true);
        }
        if ($raw === null || trim((string) $raw) === '') {
            $raw = $this->input->get_request_header('X-Client-Platform', true);
        }
        $v = strtolower(trim((string) $raw));
        if (in_array($v, ['web', 'website'], true)) {
            return 'web';
        }
        if (in_array($v, ['app', 'ios', 'android', 'mobile'], true)) {
            return 'app';
        }
        return 'unknown';
    }

    /**
     * Save checkout delivery name (and optional email) onto the user row.
     * Replaces empty / SER001 / User #### placeholders with the real name from the cart form.
     */
    private function _sync_user_from_checkout(int $user_id, array $addr, array $data): void {
        $user = $this->Sk_User_model->get_by_id($user_id);
        if (!$user) {
            return;
        }
        $this->Sk_User_model->ensure_otp_user_schema();
        $update = [];

        $fullName = trim((string) ($addr['full_name'] ?? ''));
        $curName = trim((string) ($user['name'] ?? ''));
        $isPlaceholderName = $curName === ''
            || (bool) preg_match('/^(User|SER|USR|CUST)\s*\d{1,8}$/i', $curName);
        if ($fullName !== '' && ($isPlaceholderName || strcasecmp($curName, $fullName) !== 0)) {
            if (mb_strlen($fullName) > 100) {
                $fullName = mb_substr($fullName, 0, 100);
            }
            $update['name'] = $fullName;
        }

        // Optional email from checkout body or nested address
        $emailRaw = $data['email'] ?? ($data['customer_email'] ?? ($addr['email'] ?? null));
        if ($emailRaw !== null) {
            $newEmail = strtolower(trim((string) $emailRaw));
            $curEmail = strtolower(trim((string) ($user['email'] ?? '')));
            $isPlaceholderEmail = $curEmail === ''
                || strpos($curEmail, 'ph_') === 0
                || strpos($curEmail, '@shopkart.app') !== false
                || strpos($curEmail, '@2deal.app') !== false;

            if ($newEmail === '') {
                if ($isPlaceholderEmail) {
                    $update['email'] = null;
                }
            } elseif (filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                if (!$this->Sk_User_model->email_exists($newEmail, $user_id)) {
                    if ($isPlaceholderEmail || $curEmail !== $newEmail) {
                        $update['email'] = $newEmail;
                    }
                }
            }
        }

        if ($update) {
            $this->Sk_User_model->update($user_id, $update);
        }
    }
}
