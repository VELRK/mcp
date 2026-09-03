<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sk_Promo_model extends CI_Model {

    public function get_all($limit = 20, $offset = 0) {
        return $this->db->order_by('created_at', 'DESC')->limit($limit, $offset)->get('promo_codes')->result_array();
    }

    public function count_all() { return $this->db->count_all('promo_codes'); }

    public function get_by_id($id) {
        return $this->db->where('id', $id)->get('promo_codes')->row_array();
    }

    public function get_by_code($code) {
        return $this->db->where('code', strtoupper($code))
                        ->where('status', 1)
                        ->get('promo_codes')->row_array();
    }

    public function create($data) {
        $data['code']       = strtoupper($data['code']);
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('promo_codes', $data);
        return $this->db->insert_id();
    }

    public function update($id, $data) {
        $data['code'] = strtoupper($data['code']);
        $this->db->where('id', $id)->update('promo_codes', $data);
    }

    public function delete($id) {
        $this->db->where('id', $id)->delete('promo_codes');
    }

    public function validate($code, $user_id, $order_amount) {
        $promo = $this->get_by_code($code);
        if (!$promo) return ['valid' => false, 'message' => 'Invalid promo code.'];

        if ($promo['starts_at'] && $promo['starts_at'] > date('Y-m-d'))
            return ['valid' => false, 'message' => 'Promo code is not active yet.'];

        if ($promo['expires_at'] && $promo['expires_at'] < date('Y-m-d'))
            return ['valid' => false, 'message' => 'Promo code has expired.'];

        if ($promo['min_order'] > 0 && $order_amount < $promo['min_order'])
            return ['valid' => false, 'message' => 'Minimum order ' . sk_money($promo['min_order']) . ' required.'];

        if ($promo['usage_limit'] && $promo['usage_count'] >= $promo['usage_limit'])
            return ['valid' => false, 'message' => 'Promo code usage limit reached.'];

        $user_usage = $this->db->where(['promo_id' => $promo['id'], 'user_id' => $user_id])
                               ->count_all_results('promo_usage');
        if ($user_usage >= $promo['per_user_limit'])
            return ['valid' => false, 'message' => 'You have already used this promo code.'];

        $discount = $promo['type'] === 'percent'
            ? round($order_amount * $promo['value'] / 100, 2)
            : $promo['value'];

        if ($promo['max_discount'] && $discount > $promo['max_discount'])
            $discount = $promo['max_discount'];

        return ['valid' => true, 'discount' => $discount, 'promo' => $promo];
    }

    public function record_usage($promo_id, $user_id, $order_id = null) {
        $this->db->insert('promo_usage', [
            'promo_id' => $promo_id,
            'user_id'  => $user_id,
            'order_id' => $order_id,
            'used_at'  => date('Y-m-d H:i:s'),
        ]);
        $this->db->set('usage_count', 'usage_count + 1', FALSE)
                 ->where('id', $promo_id)->update('promo_codes');
    }
}
