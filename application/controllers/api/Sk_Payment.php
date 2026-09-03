<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/api/Sk_Base_Api.php';

class Sk_Payment extends Sk_Base_Api {

    /**
     * Step 1: Create a Razorpay order
     * POST /shopkart-api/payment/create-order
     * Body: { order_id: 123 }
     */
    public function create_order() {
        $this->auth_required();
        $this->load->helper(['sk_isms', 'sk_razorpay']);
        $data     = $this->body();
        $order_id = (int)($data['order_id'] ?? 0);

        $order = $this->Sk_Order_model->get_by_id($order_id, $this->user['user_id']);
        if (!$order) return $this->error('Order not found.', 404);
        if ($order['payment_status'] === 'paid') {
            return $this->error('Order already paid. Do not open Razorpay again — show order confirmation.');
        }
        if (strtolower((string)($order['payment_method'] ?? '')) === 'wallet') {
            return $this->error('This order was paid with wallet. No online payment is required.');
        }

        $walletPaid  = round((float)($order['wallet_amount'] ?? 0), 2);
        $payAmount   = round(max(0, (float)$order['total'] - $walletPaid), 2);
        if ($payAmount <= 0) {
            return $this->error('Nothing left to pay online for this order.');
        }

        $settings   = $this->get_settings();
        $key_id     = trim((string)($settings['razorpay_key_id']     ?? config_item('razorpay_key_id') ?? ''));
        $key_secret = trim((string)($settings['razorpay_key_secret'] ?? config_item('razorpay_key_secret') ?? ''));

        if ($key_id === '' || $key_secret === '') {
            return $this->error('Payment gateway not configured. Please contact support.', 503);
        }

        $user = $this->Sk_User_model->get_by_id($this->user['user_id']);
        $contact = sk_razorpay_contact($order['shipping_phone'] ?? '', $settings);
        if ($contact === '') {
            $contact = sk_razorpay_contact($user['phone'] ?? '', $settings);
        }
        if ($contact === '') {
            $contact = sk_razorpay_contact($order['billing_phone'] ?? '', $settings);
        }
        if ($contact === '') {
            return $this->error(
                'A valid Indian mobile number is required for online payment. Update your delivery address or profile phone.',
                422
            );
        }

        $amount_paise = (int)round($payAmount * 100);
        if ($amount_paise < 100) {
            return $this->error('Order amount is too small for online payment (minimum ' . sk_currency_symbol($settings) . '1.00).');
        }

        $currency = sk_currency_code($settings);

        $payload = json_encode([
            'amount'          => $amount_paise,
            'currency'        => $currency,
            'receipt'         => $order['order_number'],
            'payment_capture' => 1,
            'notes'           => [
                'shop_order_id'     => (string)$order_id,
                'shop_order_number' => (string)$order['order_number'],
            ],
        ]);

        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_USERPWD        => "$key_id:$key_secret",
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response  = curl_exec($ch);
        $curlErr   = curl_error($ch);
        $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $curlErr !== '') {
            log_message('error', 'Razorpay CURL Error: ' . $curlErr);
            return $this->error('Could not reach Razorpay. Please try again.', 502);
        }

        $rzp = json_decode($response, true);
        if (!is_array($rzp)) {
            log_message('error', 'Razorpay create order bad JSON: ' . $response);
            return $this->error('Payment gateway returned an invalid response. Please try again.');
        }

        if ($http_code !== 200 || empty($rzp['id'])) {
            $rzpMsg = $rzp['error']['description']
                ?? $rzp['error']['reason']
                ?? $rzp['message']
                ?? null;
            log_message('error', 'Razorpay create order failed HTTP ' . $http_code . ': ' . $response);
            $hint = $rzpMsg
                ? ('Razorpay: ' . $rzpMsg)
                : 'Failed to create payment order. Check Razorpay keys and Settings currency (' . $currency . ').';
            return $this->error($hint, 502);
        }

        $this->Sk_Order_model->save_payment([
            'order_id'          => $order_id,
            'razorpay_order_id' => $rzp['id'],
            'amount'            => $payAmount,
            'currency'          => $currency,
            'status'            => 'created',
        ]);

        $prefill = sk_razorpay_build_prefill(
            (string)($order['shipping_name'] ?? $user['name'] ?? ''),
            (string)($order['shipping_phone'] ?? $user['phone'] ?? ''),
            (string)($user['email'] ?? ''),
            $settings
        );

        $this->success([
            'razorpay_order_id' => $rzp['id'],
            'amount'            => $amount_paise,
            'pay_amount'        => $payAmount,
            'wallet_amount'     => $walletPaid,
            'order_total'       => (float)$order['total'],
            'currency'          => $currency,
            'key_id'            => $key_id,
            'order_number'      => $order['order_number'],
            'order_id'          => $order_id,
            'shop_order_id'     => $order_id,
            'prefill'           => $prefill,
            'next_step'         => 'payment_verify',
            'callback_url'      => sk_razorpay_callback_url(),
            'redirect'          => false,
        ], 'Payment order created.');
    }

    /**
     * Step 2: Verify Razorpay signature after payment
     * POST /shopkart-api/payment/verify
     * Body: { razorpay_order_id, razorpay_payment_id, razorpay_signature, order_id }
     */
    public function verify() {
        $this->auth_required();
        $this->load->helper('sk_razorpay');
        $data = $this->body();

        $rzp_order_id = trim((string)($data['razorpay_order_id'] ?? ''));
        $rzp_payment_id = trim((string)($data['razorpay_payment_id'] ?? $data['payment_id'] ?? ''));
        $rzp_signature = trim((string)($data['razorpay_signature'] ?? $data['signature'] ?? ''));
        $order_id = (int)($data['shop_order_id'] ?? $data['order_id'] ?? 0);

        if ($rzp_order_id === '' || $rzp_payment_id === '') {
            return $this->error('Missing payment verification data.');
        }

        if ($order_id <= 0) {
            $paymentRow = $this->Sk_Order_model->get_payment_by_rzp_order_id($rzp_order_id);
            $order_id = (int)($paymentRow['order_id'] ?? 0);
        }

        if ($order_id <= 0) {
            return $this->error('Order not found for this payment.');
        }

        $existing = $this->Sk_Order_model->get_by_id($order_id, $this->user['user_id']);
        if ($existing && ($existing['payment_status'] ?? '') === 'paid') {
            $msg = 'Payment successful! Your order is confirmed.';
            $payload = sk_razorpay_order_payment_response($existing, $msg);
            return $this->success($payload, $msg);
        }

        $settings = $this->get_settings();
        $result = sk_razorpay_finalize_order_payment(
            $order_id,
            $rzp_order_id,
            $rzp_payment_id,
            $rzp_signature,
            (int)$this->user['user_id'],
            $settings
        );

        if (empty($result['success'])) {
            if (!empty($result['pending'])) {
                $payload = $result['response'] ?? sk_razorpay_pending_order_response(
                    $result['order'] ?? $existing ?? [],
                    $result['message'] ?? sk_razorpay_pending_message()
                );
                return $this->success($payload, $result['message'] ?? sk_razorpay_pending_message());
            }
            $pay = $rzp_payment_id !== '' ? sk_razorpay_fetch_payment($rzp_payment_id, $settings) : null;
            $outcome = sk_razorpay_outcome_from_gateway($pay);
            $orderRow = $result['order'] ?? $existing ?? [];
            if ($outcome['kind'] === 'pending') {
                return $this->success(sk_razorpay_pending_order_response($orderRow, $outcome['message']), $outcome['message']);
            }
            if ($outcome['kind'] === 'failed') {
                return $this->error($outcome['message'], 200, sk_razorpay_failed_order_response($orderRow, $outcome));
            }
            return $this->error($result['message'] ?? 'Payment verification failed.', 400);
        }

        $payload = $result['response'] ?? sk_razorpay_order_payment_response(
            $result['order'] ?? [],
            $result['message'] ?? 'Payment successful! Your order is confirmed.'
        );
        $this->success($payload, $result['message'] ?? 'Payment successful! Your order is confirmed.');
    }

    /**
     * Check shop order payment status after Curlec closes (FPX / e-wallet / card).
     * GET /shopkart-api/payment/order-status?order_id=101&razorpay_order_id=...&razorpay_payment_id=...
     */
    public function order_payment_status() {
        $this->auth_required();
        $this->load->helper('sk_razorpay');

        $orderId = (int)($this->input->get('order_id') ?? $this->input->get('shop_order_id') ?? 0);
        $rzpOrderId = trim((string)($this->input->get('razorpay_order_id') ?? ''));
        $rzpPaymentId = trim((string)($this->input->get('razorpay_payment_id') ?? ''));

        if ($orderId <= 0 && $rzpOrderId === '') {
            return $this->error('Pass order_id or razorpay_order_id.');
        }

        if ($orderId <= 0 && $rzpOrderId !== '') {
            $paymentRow = $this->Sk_Order_model->get_payment_by_rzp_order_id($rzpOrderId);
            $orderId = (int)($paymentRow['order_id'] ?? 0);
        }

        if ($orderId <= 0) {
            return $this->error('Order not found.', 404);
        }

        $order = $this->Sk_Order_model->get_by_id($orderId, $this->user['user_id']);
        if (!$order) {
            return $this->error('Order not found.', 404);
        }

        if (($order['payment_status'] ?? '') === 'paid') {
            $msg = 'Payment successful! Your order is confirmed.';
            return $this->success(sk_razorpay_order_payment_response($order, $msg), $msg);
        }

        if ($rzpPaymentId !== '' && $rzpOrderId !== '') {
            $settings = $this->get_settings();
            $result = sk_razorpay_finalize_order_payment(
                $orderId,
                $rzpOrderId,
                $rzpPaymentId,
                '',
                (int)$this->user['user_id'],
                $settings
            );
            if (!empty($result['success'])) {
                $payload = $result['response'] ?? sk_razorpay_order_payment_response(
                    $result['order'] ?? [],
                    $result['message'] ?? 'Payment successful! Your order is confirmed.'
                );
                return $this->success($payload, $result['message'] ?? 'Payment successful! Your order is confirmed.');
            }
            if (!empty($result['pending'])) {
                $msg = $result['message'] ?? sk_razorpay_pending_message();
                return $this->success(
                    $result['response'] ?? sk_razorpay_pending_order_response($order, $msg),
                    $msg
                );
            }
        }

        if ($rzpPaymentId !== '') {
            $pay = sk_razorpay_fetch_payment($rzpPaymentId, $this->get_settings());
            $outcome = sk_razorpay_outcome_from_gateway($pay);
            if ($outcome['kind'] === 'pending') {
                return $this->success(sk_razorpay_pending_order_response($order, $outcome['message']), $outcome['message']);
            }
            if ($outcome['kind'] === 'failed') {
                return $this->error($outcome['message'], 200, sk_razorpay_failed_order_response($order, $outcome));
            }
        }

        return $this->success([
            'confirmed'      => false,
            'status'         => (string)($order['status'] ?? 'payment_attempt'),
            'payment_status' => (string)($order['payment_status'] ?? 'pending'),
            'order_id'       => $orderId,
            'order_number'   => (string)($order['order_number'] ?? ''),
        ], 'Payment pending. Complete Curlec checkout or call payment/verify.');
    }

    /**
     * Wallet top-up endpoints removed.
     */
    public function wallet_topup_verify() {
        return $this->error('Wallet top-up is no longer available.', 410);
    }

    public function wallet_topup_status() {
        return $this->error('Wallet top-up is no longer available.', 410);
    }

    /**
     * Curlec browser return after FPX / card 3DS / e-wallet (no JWT).
     * GET|POST /shopkart-api/payment/razorpay-return
     * Pass this as Razorpay checkout callback_url so "Back To Merchant" lands here.
     */
    public function razorpay_return() {
        $this->load->helper('sk_razorpay');
        $params = sk_razorpay_collect_callback_params();
        $settings = $this->get_settings();

        $rzpOrderId = $params['razorpay_order_id'];
        $rzpPaymentId = $params['razorpay_payment_id'];
        $rzpSignature = $params['razorpay_signature'];
        $errorDesc = $params['error_description'] ?: $params['error_reason'];

        if ($rzpOrderId !== '' && $rzpPaymentId === '') {
            $payments = sk_razorpay_fetch_order_payments($rzpOrderId, $settings);
            foreach ($payments as $pay) {
                $st = strtolower((string)($pay['status'] ?? ''));
                if (in_array($st, ['captured', 'authorized'], true)) {
                    $rzpPaymentId = (string)($pay['id'] ?? '');
                    break;
                }
            }
            if ($rzpPaymentId === '' && !empty($payments[0]['id'])) {
                $rzpPaymentId = (string)$payments[0]['id'];
            }
        }

        $homeUrl = rtrim(base_url(), '/') . '/';
        $ordersUrl = rtrim(base_url(), '/') . '/account-orders';
        $view = [
            'success'          => false,
            'pending'          => false,
            'message'          => $errorDesc ?: 'Payment was not completed. Please try again from My Orders.',
            'order_number'     => '',
            'orders_url'       => $ordersUrl,
            'home_url'         => $homeUrl,
            'cart_clear_lines' => [],
        ];

        if ($rzpOrderId === '' && $rzpPaymentId === '') {
            if (sk_razorpay_is_gateway_pending(null, $params['error_reason'], $errorDesc)) {
                $view['pending'] = true;
                $view['message'] = sk_razorpay_pending_message();
            }
            $this->load->view('payment/result', $view);
            return;
        }

        $this->load->model('Sk_Order_model');
        $paymentRow = $this->Sk_Order_model->get_payment_by_rzp_order_id($rzpOrderId);
        $shopOrderId = (int)($paymentRow['order_id'] ?? 0);

        if ($shopOrderId > 0 && $rzpPaymentId !== '') {
            $order = $this->Sk_Order_model->get_by_id($shopOrderId);
            $userId = $order ? (int)($order['user_id'] ?? 0) : null;
            $result = sk_razorpay_finalize_order_payment(
                $shopOrderId,
                $rzpOrderId,
                $rzpPaymentId,
                $rzpSignature,
                $userId ?: null,
                $settings
            );
            if (!empty($result['success'])) {
                $paid = $result['order'] ?? $order;
                $view['success'] = true;
                $view['order_number'] = (string)($paid['order_number'] ?? '');
                $view['message'] = 'Payment successful! Your order is confirmed.';
                $view['cart_clear_lines'] = $result['response']['cart_clear_lines']
                    ?? sk_cart_remove_items_for_paid_order((int)($paid['user_id'] ?? 0), $shopOrderId);
                $this->load->view('payment/result', $view);
                return;
            }
            $view['order_number'] = (string)($order['order_number'] ?? '');
            $gatewayPay = $rzpPaymentId !== '' ? sk_razorpay_fetch_payment($rzpPaymentId, $settings) : null;
            $outcome = sk_razorpay_outcome_from_gateway($gatewayPay, $params['error_reason'], $errorDesc, $params['error_code'] ?? '');
            if ($outcome['kind'] === 'pending' || !empty($result['pending'])) {
                $view['pending'] = true;
                $view['message'] = $outcome['message'] ?? sk_razorpay_pending_message();
            } else {
                $view['message'] = $outcome['message'] ?? ($result['message'] ?? $view['message']);
            }
            $this->load->view('payment/result', $view);
            return;
        }

        if ($shopOrderId > 0 && sk_razorpay_is_gateway_pending(null, $params['error_reason'], $errorDesc)) {
            $order = $this->Sk_Order_model->get_by_id($shopOrderId);
            $view['pending'] = true;
            $view['order_number'] = (string)($order['order_number'] ?? '');
            $view['message'] = sk_razorpay_pending_message();
            $this->load->view('payment/result', $view);
            return;
        }

        $this->load->view('payment/result', $view);
    }

    /**
     * Razorpay / Curlec webhook (payment.captured, order.paid).
     * POST /shopkart-api/payment/razorpay-webhook
     * Register this URL in Curlec Dashboard → Webhooks.
     */
    public function razorpay_webhook() {
        $this->load->helper('sk_razorpay');
        $raw = file_get_contents('php://input');
        $signature = (string)($this->input->get_request_header('X-Razorpay-Signature', true) ?: '');
        $settings = $this->get_settings();

        if (!sk_razorpay_webhook_signature_valid($raw, $signature, $settings)) {
            log_message('error', 'Razorpay webhook signature invalid');
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['success' => false, 'message' => 'Invalid signature']));
        }

        $event = json_decode($raw, true);
        if (!is_array($event)) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['success' => false, 'message' => 'Invalid payload']));
        }

        $outcome = sk_razorpay_handle_webhook_event($event, $settings);
        log_message('info', 'Razorpay webhook ' . ($event['event'] ?? '') . ': ' . json_encode($outcome));

        return $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode(['success' => true]));
    }

    /** ToyyibPay browser return (legacy) */
    public function toyyibpay_return() {
        $status = (int)($this->input->get('status_id') ?: 0);
        $q = ($status === 1) ? 'success' : 'failed';
        redirect(rtrim(base_url(), '/') . '/account-orders?pay=' . $q);
    }

    /** ToyyibPay server callback (legacy) */
    public function toyyibpay_callback() {
        echo 'OK';
    }
}
