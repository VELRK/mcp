<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/api/Sk_Base_Api.php';

class Sk_Shipping extends Sk_Base_Api {

    /**
     * POST /shopkart-api/shipping/track
     * Body: { tracking_number } and/or { order_number }
     * Looks up a local order — no courier API.
     */
    public function track() {
        $data = $this->body();
        $tracking = trim((string)($data['tracking_number'] ?? $data['bill_code'] ?? $data['awb'] ?? ''));
        $orderNo  = trim((string)($data['order_number'] ?? ''));

        if ($tracking === '' && $orderNo === '') {
            return $this->error('Enter a tracking number or order number.');
        }

        $order = null;
        if ($tracking !== '') {
            $order = $this->Sk_Order_model->get_by_tracking($tracking);
        }
        if (!$order && $orderNo !== '') {
            $order = $this->db->where('order_number', $orderNo)->get('orders')->row_array();
            if ($order) {
                $order['items'] = $this->Sk_Order_model->get_items($order['id']);
            }
        }

        if (!$order) {
            return $this->error('Shipment not found. Check your tracking ID or order number.', 404);
        }

        $billCode = trim((string)($order['tracking_number'] ?? ''));
        if ($billCode === '' && $tracking !== '') {
            $billCode = $tracking;
        }

        return $this->success([
            'order_number'    => $order['order_number'] ?? null,
            'tracking_number' => $billCode ?: null,
            'order_status'    => $order['status'] ?? null,
            'courier'         => null,
            'courier_status'  => null,
            'processing_at'   => $order['processing_at'] ?? null,
            'shipped_at'      => $order['shipped_at'] ?? null,
            'delivered_at'    => $order['delivered_at'] ?? null,
            'tracks'          => [],
            'events'          => [],
            'has_tracking'    => $billCode !== '',
            'message'         => $billCode === ''
                ? 'No tracking ID yet for this order. It will appear once the shipment is created.'
                : 'Showing order status.',
        ]);
    }
}
