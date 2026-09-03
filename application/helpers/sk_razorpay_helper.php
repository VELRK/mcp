<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Shared Razorpay / Curlec helpers for checkout, wallet top-up, and webhooks.
 */

function sk_razorpay_key_secret(array $settings = null): string {
    if ($settings === null) {
        $CI =& get_instance();
        if (!isset($CI->Sk_Admin_model)) {
            $CI->load->model('Sk_Admin_model');
        }
        $settings = $CI->Sk_Admin_model->get_settings();
    }
    return trim((string)($settings['razorpay_key_secret'] ?? config_item('razorpay_key_secret') ?? ''));
}

function sk_razorpay_webhook_secret(array $settings = null): string {
    if ($settings === null) {
        $CI =& get_instance();
        if (!isset($CI->Sk_Admin_model)) {
            $CI->load->model('Sk_Admin_model');
        }
        $settings = $CI->Sk_Admin_model->get_settings();
    }
    $fromSettings = trim((string)($settings['razorpay_webhook_secret'] ?? ''));
    if ($fromSettings !== '') {
        return $fromSettings;
    }
    return trim((string)(config_item('razorpay_webhook_secret') ?? ''));
}

function sk_razorpay_payment_signature_valid(
    string $rzpOrderId,
    string $rzpPaymentId,
    string $signature,
    array $settings = null
): bool {
    $secret = sk_razorpay_key_secret($settings);
    if ($secret === '' || $signature === '') {
        return false;
    }
    $expected = hash_hmac('sha256', $rzpOrderId . '|' . $rzpPaymentId, $secret);
    return hash_equals($expected, $signature);
}

function sk_razorpay_webhook_signature_valid(string $rawBody, string $signature, array $settings = null): bool {
    $secret = sk_razorpay_webhook_secret($settings);
    if ($secret === '' || $signature === '') {
        return false;
    }
    $expected = hash_hmac('sha256', $rawBody, $secret);
    return hash_equals($expected, $signature);
}

/**
 * Fetch a Razorpay payment by id (server-side confirmation fallback).
 */
function sk_razorpay_fetch_payment(string $paymentId, array $settings = null): ?array {
    $paymentId = trim($paymentId);
    if ($paymentId === '') {
        return null;
    }

    if ($settings === null) {
        $CI =& get_instance();
        if (!isset($CI->Sk_Admin_model)) {
            $CI->load->model('Sk_Admin_model');
        }
        $settings = $CI->Sk_Admin_model->get_settings();
    }

    $keyId = trim((string)($settings['razorpay_key_id'] ?? config_item('razorpay_key_id') ?? ''));
    $keySecret = sk_razorpay_key_secret($settings);
    if ($keyId === '' || $keySecret === '') {
        return null;
    }

    $ch = curl_init('https://api.razorpay.com/v1/payments/' . rawurlencode($paymentId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $keyId . ':' . $keySecret,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !is_string($response) || $response === '') {
        log_message('error', 'Razorpay fetch payment failed HTTP ' . $httpCode . ': ' . (string)$response);
        return null;
    }

    $pay = json_decode($response, true);
    return is_array($pay) ? $pay : null;
}

/**
 * Touch 'n Go / FPX can sit in "issuer pending" — Curlec shows Failed but money may still settle.
 */
function sk_razorpay_is_gateway_pending(?array $pay = null, string $reason = '', string $description = ''): bool {
    $reason = strtolower(trim($reason));
    $description = strtolower(trim($description));
    $status = strtolower(trim((string)($pay['status'] ?? '')));
    $payReason = strtolower(trim((string)($pay['error_reason'] ?? $pay['reason'] ?? '')));
    $payDesc = strtolower(trim((string)($pay['error_description'] ?? $pay['description'] ?? '')));

    if ($reason === 'payment_pending_gateway' || $payReason === 'payment_pending_gateway') {
        return true;
    }
    if (in_array($status, ['created', 'pending', 'authenticated'], true)) {
        return true;
    }
    foreach ([$description, $payDesc] as $text) {
        if ($text === '') {
            continue;
        }
        if (strpos($text, 'taking more time than usual') !== false
            || strpos($text, 'being processed') !== false
            || strpos($text, 'notified shortly') !== false) {
            return true;
        }
    }
    return false;
}

function sk_razorpay_pending_order_response(array $order, string $message): array {
    return [
        'confirmed'      => false,
        'pending'        => true,
        'order_id'       => (int)($order['id'] ?? 0),
        'order_number'   => (string)($order['order_number'] ?? ''),
        'payment_status' => (string)($order['payment_status'] ?? 'pending'),
        'status'         => (string)($order['status'] ?? 'payment_attempt'),
        'total'          => (float)($order['total'] ?? 0),
        'order'          => $order,
        'message'        => $message,
    ];
}

function sk_razorpay_pending_message(): string {
    return 'Your payment is still processing with the bank / e-wallet (e.g. Touch ’n Go). '
        . 'Do not pay again. We will confirm the order automatically when Curlec captures the payment. '
        . 'Check My Orders in a few minutes.';
}

/**
 * Map Curlec/Razorpay error_reason + payment status to a shop-facing outcome.
 *
 * @return array{kind:string,reason:string,code:string,message:string,retry_allowed:bool}
 */
function sk_razorpay_outcome_from_gateway(?array $pay, string $reason = '', string $description = '', string $code = ''): array {
    $status = strtolower(trim((string)($pay['status'] ?? '')));
    $reason = strtolower(trim($reason !== '' ? $reason : (string)($pay['error_reason'] ?? $pay['reason'] ?? '')));
    $description = trim($description !== '' ? $description : (string)($pay['error_description'] ?? $pay['description'] ?? ''));
    $code = strtoupper(trim($code !== '' ? $code : (string)($pay['error_code'] ?? $pay['code'] ?? '')));

    if (in_array($status, ['captured', 'authorized'], true)) {
        return [
            'kind'           => 'captured',
            'reason'         => $reason,
            'code'           => $code,
            'message'        => 'Payment successful! Your order is confirmed.',
            'retry_allowed'  => false,
        ];
    }

    if (sk_razorpay_is_gateway_pending($pay, $reason, $description)) {
        return [
            'kind'           => 'pending',
            'reason'         => $reason !== '' ? $reason : 'payment_pending_gateway',
            'code'           => $code,
            'message'        => sk_razorpay_pending_message(),
            'retry_allowed'  => false,
        ];
    }

    $messages = [
        'card_declined'            => 'Card was declined by the bank. Try another card, or pay with FPX / Touch ’n Go. Your order is saved under My Orders.',
        'payment_session_expired'  => 'The bank payment session expired. Open My Orders and tap Complete payment to try FPX again.',
        'payment_timed_out'        => 'The bank took too long to respond. Please try again from My Orders.',
        'authentication_failed'    => 'Card / bank authentication failed. Try again or use another payment method.',
        'insufficient_funds'       => 'Insufficient funds. Use another card, FPX, or Touch ’n Go.',
        'invalid_amount'           => 'This amount cannot be charged on that method. Try FPX or another card.',
        'gateway_technical_error'  => 'The payment gateway had a technical error. Please try again in a few minutes from My Orders.',
        'payment_failed'           => 'Payment was not completed. Try again from My Orders.',
        'bad_request_error'        => 'Payment could not be completed. Try again from My Orders.',
    ];

    $mapped = $messages[$reason] ?? null;
    if ($mapped === null && $code === 'GATEWAY_ERROR') {
        $mapped = $messages['gateway_technical_error'];
        if ($reason === '') {
            $reason = 'gateway_technical_error';
        }
    }
    if ($mapped === null && $description !== '') {
        $mapped = $description . ' Your order is saved — complete payment from My Orders. If money was deducted, it is usually returned in 5–7 working days.';
    }
    if ($mapped === null) {
        $mapped = 'Payment was not completed. Try another method from My Orders. If money was deducted, it is usually returned in 5–7 working days.';
    }

    return [
        'kind'           => 'failed',
        'reason'         => $reason !== '' ? $reason : strtolower($code),
        'code'           => $code,
        'message'        => $mapped,
        'retry_allowed'  => true,
    ];
}

function sk_razorpay_failed_order_response(array $order, array $outcome): array {
    return [
        'confirmed'      => false,
        'pending'        => false,
        'failed'         => true,
        'retry_allowed'  => !empty($outcome['retry_allowed']),
        'error_reason'   => (string)($outcome['reason'] ?? ''),
        'error_code'     => (string)($outcome['code'] ?? ''),
        'order_id'       => (int)($order['id'] ?? 0),
        'order_number'   => (string)($order['order_number'] ?? ''),
        'payment_status' => (string)($order['payment_status'] ?? 'pending'),
        'status'         => (string)($order['status'] ?? 'payment_attempt'),
        'total'          => (float)($order['total'] ?? 0),
        'order'          => $order,
        'message'        => (string)($outcome['message'] ?? ''),
    ];
}

/**
 * Confirm captured Razorpay payment matches a shop order payment row.
 */
function sk_razorpay_order_payment_trusted(
    array $paymentRow,
    int $orderId,
    string $rzpOrderId,
    string $rzpPaymentId,
    array $settings = null
): bool {
    $pay = sk_razorpay_fetch_payment($rzpPaymentId, $settings);
    if (!$pay) {
        return false;
    }

    $status = strtolower((string)($pay['status'] ?? ''));
    if (!in_array($status, ['captured', 'authorized'], true)) {
        return false;
    }

    if ((string)($pay['order_id'] ?? '') !== $rzpOrderId) {
        return false;
    }

    if ($orderId > 0 && (int)($paymentRow['order_id'] ?? 0) !== $orderId) {
        return false;
    }

    $expectedSen = (int)round((float)($paymentRow['amount'] ?? 0) * 100);
    $paidSen = (int)($pay['amount'] ?? 0);
    if ($expectedSen > 0 && $paidSen !== $expectedSen) {
        return false;
    }

    return true;
}

/**
 * Build a consistent order payment success payload for mobile apps.
 */
function sk_razorpay_order_payment_response(array $order, string $message): array {
    $userId = (int)($order['user_id'] ?? 0);
    $orderId = (int)($order['id'] ?? 0);
    $cartClearLines = ($userId > 0 && $orderId > 0)
        ? sk_cart_remove_items_for_paid_order($userId, $orderId)
        : [];
    return [
        'confirmed'        => true,
        'order_id'         => $orderId,
        'order_number'     => (string)($order['order_number'] ?? ''),
        'payment_status'   => (string)($order['payment_status'] ?? 'paid'),
        'status'           => (string)($order['status'] ?? 'confirmed'),
        'total'            => (float)($order['total'] ?? 0),
        'order'            => $order,
        'cart_clear_lines' => $cartClearLines,
        'message'          => $message,
    ];
}

/**
 * After a paid order, drop those products from the user's cart table.
 *
 * @return list<array{product_id:int,variant_id:?int}>
 */
function sk_cart_remove_items_for_paid_order(int $userId, int $orderId): array {
    $CI =& get_instance();
    if ($userId <= 0 || $orderId <= 0) {
        return [];
    }
    if (!isset($CI->Sk_Order_model)) {
        $CI->load->model('Sk_Order_model');
    }
    $items = $CI->Sk_Order_model->get_items($orderId);
    if (!is_array($items) || $items === []) {
        return [];
    }

    $lines = [];
    $seen = [];
    foreach ($items as $item) {
        $pid = (int)($item['product_id'] ?? 0);
        if ($pid <= 0 || isset($seen[$pid])) {
            continue;
        }
        $seen[$pid] = true;
        $vid = isset($item['variant_id']) && $item['variant_id'] !== '' && $item['variant_id'] !== null
            ? (int)$item['variant_id']
            : null;
        $CI->db->where('user_id', $userId)->where('product_id', $pid)->delete('cart');
        $lines[] = ['product_id' => $pid, 'variant_id' => $vid];
    }
    return $lines;
}

/**
 * Build Curlec/Razorpay prefill for web + mobile SDKs.
 *
 * @return array{name:string,contact:string,contact_digits:string,contact_local:string,email?:string}
 */
function sk_razorpay_build_prefill(string $name, string $phone, string $email = '', array $settings = null): array {
    $CI =& get_instance();
    if (!function_exists('sk_razorpay_contact')) {
        $CI->load->helper('sk_isms');
    }

    $name = trim($name);
    if ($name === '' || preg_match('/^(User|SER|USR|CUST)\s*\d+$/i', $name)) {
        $name = 'Customer';
    }

    $contact = sk_razorpay_contact($phone, $settings);
    $digits = preg_replace('/\D/', '', $contact);
    if (strpos($digits, '60') === 0 && strlen($digits) >= 10) {
        $local = '0' . substr($digits, 2);
    } else {
        $local = $phone;
    }

    $prefill = [
        'name'            => $name,
        'contact'         => $contact,
        'contact_digits'  => $digits,
        'contact_local'   => $local,
    ];

    $safeEmail = sk_razorpay_prefill_email($email);
    if ($safeEmail !== '') {
        $prefill['email'] = $safeEmail;
    }

    return $prefill;
}

/**
 * Mark a shop order paid after Razorpay capture (verify API or webhook).
 *
 * @return array{success:bool,message:string,order?:array}
 */
function sk_razorpay_finalize_order_payment(
    int $orderId,
    string $rzpOrderId,
    string $rzpPaymentId,
    string $rzpSignature,
    ?int $userId = null,
    array $settings = null,
    bool $skipSignatureCheck = false
): array {
    $CI =& get_instance();
    $CI->load->model('Sk_Order_model');

    if ($orderId <= 0) {
        $paymentRow = $CI->Sk_Order_model->get_payment_by_rzp_order_id($rzpOrderId);
        $orderId = (int)($paymentRow['order_id'] ?? 0);
    }

    if ($orderId <= 0) {
        return ['success' => false, 'message' => 'Order not found for this payment.'];
    }

    $order = $CI->Sk_Order_model->get_by_id($orderId, $userId);
    if (!$order) {
        return ['success' => false, 'message' => 'Order not found.'];
    }

    if (($order['payment_status'] ?? '') === 'paid') {
        $order = $CI->Sk_Order_model->get_by_id($orderId, $userId);
        $msg = 'Payment successful! Your order is confirmed.';
        return [
            'success'  => true,
            'message'  => $msg,
            'order'    => $order,
            'response' => sk_razorpay_order_payment_response($order, $msg),
        ];
    }

    $paymentRow = $CI->Sk_Order_model->get_payment_by_rzp_order_id($rzpOrderId);
    if (!$paymentRow && $orderId > 0) {
        $paymentRow = $CI->Sk_Order_model->get_payment($orderId);
    }

    if (!$skipSignatureCheck) {
        $sigOk = ($rzpSignature !== '')
            && sk_razorpay_payment_signature_valid($rzpOrderId, $rzpPaymentId, $rzpSignature, $settings);
        if (!$sigOk) {
            if ($paymentRow && sk_razorpay_order_payment_trusted($paymentRow, $orderId, $rzpOrderId, $rzpPaymentId, $settings)) {
                $skipSignatureCheck = true;
                log_message('info', 'Order payment verified via Razorpay API fallback for order ' . $orderId);
            } else {
                log_message('error', 'Razorpay signature mismatch for order ' . $orderId);
                $pay = $rzpPaymentId !== '' ? sk_razorpay_fetch_payment($rzpPaymentId, $settings) : null;
                if (sk_razorpay_is_gateway_pending($pay)) {
                    if ($paymentRow && $rzpPaymentId !== '') {
                        $CI->Sk_Order_model->update_payment($rzpOrderId, [
                            'razorpay_payment_id' => $rzpPaymentId,
                            'status'              => 'pending',
                        ]);
                    }
                    $msg = sk_razorpay_pending_message();
                    return [
                        'success'  => false,
                        'pending'  => true,
                        'message'  => $msg,
                        'order'    => $order,
                        'response' => sk_razorpay_pending_order_response($order, $msg),
                    ];
                }
                return ['success' => false, 'message' => 'Payment verification failed. Invalid signature.'];
            }
        }
    }

    if ($paymentRow) {
        $CI->Sk_Order_model->update_payment($rzpOrderId, [
            'razorpay_payment_id' => $rzpPaymentId,
            'razorpay_signature'  => $rzpSignature,
            'status'              => 'captured',
        ]);
    } else {
        $CI->Sk_Order_model->save_payment([
            'order_id'            => $orderId,
            'razorpay_order_id'   => $rzpOrderId,
            'razorpay_payment_id' => $rzpPaymentId,
            'razorpay_signature'  => $rzpSignature,
            'amount'              => (float)($order['total'] ?? 0),
            'currency'            => strtoupper((string)($order['currency'] ?? sk_currency_code($settings))),
            'status'              => 'captured',
        ]);
    }

    $CI->Sk_Order_model->update_payment_status($orderId, 'paid');
    $CI->Sk_Order_model->update_status($orderId, 'confirmed');

    $order = $CI->Sk_Order_model->get_by_id($orderId, $userId);

    if (!isset($CI->Sk_Admin_model)) {
        $CI->load->model('Sk_Admin_model');
    }
    if ($settings === null) {
        $settings = $CI->Sk_Admin_model->get_settings();
    }

    $CI->load->helper(['sk_mailer', 'sk_invoice', 'sk_whatsapp']);
    sk_invoice_ensure_vendor_schema();
    if (empty($order['invoice_emailed_at'])) {
        sk_mail_order_invoice($order, $settings);
    }
    sk_whatsapp_notify_order_status($order, $order['status'] ?? 'confirmed', $settings);

    $msg = 'Payment successful! Your order is confirmed.';
    return [
        'success'  => true,
        'message'  => $msg,
        'order'    => $order,
        'response' => sk_razorpay_order_payment_response($order, $msg),
    ];
}

/**
 * Process Razorpay webhook payload (payment.captured / order.paid).
 */
function sk_razorpay_handle_webhook_event(array $event, array $settings = null): array {
    $eventName = (string)($event['event'] ?? '');
    $entity = null;
    $paymentId = '';
    $orderId = '';

    if ($eventName === 'payment.captured' || $eventName === 'payment.authorized') {
        $entity = $event['payload']['payment']['entity'] ?? null;
        $paymentId = (string)($entity['id'] ?? '');
        $orderId = (string)($entity['order_id'] ?? '');
    } elseif ($eventName === 'order.paid') {
        $entity = $event['payload']['order']['entity'] ?? null;
        $orderId = (string)($entity['id'] ?? '');
        $payments = $entity['payments'] ?? [];
        if (is_array($payments) && !empty($payments)) {
            $paymentId = (string)($payments[0] ?? '');
        }
    }

    if ($orderId === '' || $paymentId === '') {
        return ['handled' => false, 'message' => 'Ignored event (missing ids): ' . $eventName];
    }

    $CI =& get_instance();
    $CI->load->model('Sk_Order_model');
    $paymentRow = $CI->Sk_Order_model->get_payment_by_rzp_order_id($orderId);

    if ($paymentRow) {
        $shopOrderId = (int)($paymentRow['order_id'] ?? 0);
        $result = sk_razorpay_finalize_order_payment(
            $shopOrderId,
            $orderId,
            $paymentId,
            '',
            null,
            $settings,
            true
        );
        return ['handled' => true, 'type' => 'order', 'result' => $result];
    }

    return ['handled' => false, 'message' => 'No matching shop order for ' . $orderId];
}

/**
 * Browser return URL after Curlec FPX / card 3DS / e-wallet (no JWT).
 */
function sk_razorpay_callback_url(): string {
    $url = site_url('shopkart-api/payment/razorpay-return');
    if (stripos($url, 'http://2deal.my') === 0 || stripos($url, 'http://www.2deal.my') === 0) {
        $url = 'https://' . substr($url, strlen('http://'));
    }
    return $url;
}

/**
 * Collect Razorpay/Curlec redirect fields from GET, POST, or JSON.
 *
 * @return array{razorpay_order_id:string,razorpay_payment_id:string,razorpay_signature:string,error_code:string,error_description:string,error_reason:string}
 */
function sk_razorpay_collect_callback_params(): array {
    $CI =& get_instance();
    $json = json_decode((string)$CI->input->raw_input_stream, true);
    if (!is_array($json)) {
        $json = [];
    }

    $error = $CI->input->post('error');
    if (!is_array($error)) {
        $error = $CI->input->get('error');
    }
    if (!is_array($error)) {
        $error = is_array($json['error'] ?? null) ? $json['error'] : [];
    }

    $meta = is_array($error['metadata'] ?? null) ? $error['metadata'] : [];

    $rzpOrderId = trim((string)(
        $CI->input->post('razorpay_order_id')
        ?: $CI->input->get('razorpay_order_id')
        ?: ($json['razorpay_order_id'] ?? '')
        ?: ($meta['order_id'] ?? '')
    ));
    $rzpPaymentId = trim((string)(
        $CI->input->post('razorpay_payment_id')
        ?: $CI->input->get('razorpay_payment_id')
        ?: ($json['razorpay_payment_id'] ?? '')
        ?: ($meta['payment_id'] ?? '')
    ));
    $rzpSignature = trim((string)(
        $CI->input->post('razorpay_signature')
        ?: $CI->input->get('razorpay_signature')
        ?: ($json['razorpay_signature'] ?? '')
    ));

    return [
        'razorpay_order_id'   => $rzpOrderId,
        'razorpay_payment_id' => $rzpPaymentId,
        'razorpay_signature'  => $rzpSignature,
        'error_code'          => trim((string)($error['code'] ?? '')),
        'error_description'   => trim((string)($error['description'] ?? '')),
        'error_reason'        => trim((string)($error['reason'] ?? '')),
    ];
}

/**
 * Fetch payments for a Razorpay order (used when redirect omits payment_id).
 */
function sk_razorpay_fetch_order_payments(string $orderId, array $settings = null): array {
    $orderId = trim($orderId);
    if ($orderId === '') {
        return [];
    }
    if ($settings === null) {
        $CI =& get_instance();
        if (!isset($CI->Sk_Admin_model)) {
            $CI->load->model('Sk_Admin_model');
        }
        $settings = $CI->Sk_Admin_model->get_settings();
    }
    $keyId = trim((string)($settings['razorpay_key_id'] ?? config_item('razorpay_key_id') ?? ''));
    $keySecret = sk_razorpay_key_secret($settings);
    if ($keyId === '' || $keySecret === '') {
        return [];
    }

    $ch = curl_init('https://api.razorpay.com/v1/orders/' . rawurlencode($orderId) . '/payments');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $keyId . ':' . $keySecret,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200 || !is_string($response) || $response === '') {
        return [];
    }
    $decoded = json_decode($response, true);
    $items = $decoded['items'] ?? [];
    return is_array($items) ? $items : [];
}
