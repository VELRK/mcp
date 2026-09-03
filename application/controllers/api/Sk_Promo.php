<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/api/Sk_Base_Api.php';

class Sk_Promo extends Sk_Base_Api {

    public function apply() {
        $this->auth_required();
        $data         = $this->body();
        $code         = $data['code']         ?? '';
        $order_amount = (float)($data['order_amount'] ?? 0);

        if (!$code) return $this->error('Promo code required.');

        $result = $this->Sk_Promo_model->validate($code, $this->user['user_id'], $order_amount);

        if (!$result['valid']) {
            return $this->error($result['message']);
        }

        $this->success([
            'discount' => $result['discount'],
            'code'     => strtoupper($code),
            'type'     => $result['promo']['type'],
            'value'    => $result['promo']['value'],
            'source'   => 'promo',
        ], 'Promo code applied! You save ' . sk_money($result['discount']) . '.');
    }
}
