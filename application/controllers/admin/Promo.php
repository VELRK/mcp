<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/admin/Sk_Base.php';

class Promo extends Sk_Base {

    public function index() {
        $data['title']  = 'Promo Codes - Talk AI Pilot Admin';
        $data['promos'] = $this->Sk_Promo_model->get_all();
        $this->render('promo/list', $data);
    }

    public function store() {
        $data = [
            'code'           => $this->input->post('code', TRUE),
            'description'    => $this->input->post('description', TRUE),
            'type'           => $this->input->post('type'),
            'value'          => $this->input->post('value'),
            'min_order'      => $this->input->post('min_order') ?? 0,
            'max_discount'   => $this->input->post('max_discount') ?: null,
            'usage_limit'    => $this->input->post('usage_limit') ?: null,
            'per_user_limit' => $this->input->post('per_user_limit') ?? 1,
            'starts_at'      => $this->input->post('starts_at') ?: null,
            'expires_at'     => $this->input->post('expires_at') ?: null,
            'status'         => $this->input->post('status') ?? 1,
        ];
        $this->Sk_Promo_model->create($data);
        $this->json(['success' => true]);
    }

    public function edit($id) {
        $promo = $this->Sk_Promo_model->get_by_id($id);
        $this->json(['success' => true, 'data' => $promo]);
    }

    public function update($id) {
        $data = [
            'code'           => $this->input->post('code', TRUE),
            'description'    => $this->input->post('description', TRUE),
            'type'           => $this->input->post('type'),
            'value'          => $this->input->post('value'),
            'min_order'      => $this->input->post('min_order') ?? 0,
            'max_discount'   => $this->input->post('max_discount') ?: null,
            'usage_limit'    => $this->input->post('usage_limit') ?: null,
            'per_user_limit' => $this->input->post('per_user_limit') ?? 1,
            'starts_at'      => $this->input->post('starts_at') ?: null,
            'expires_at'     => $this->input->post('expires_at') ?: null,
            'status'         => $this->input->post('status') ?? 1,
        ];
        $this->Sk_Promo_model->update($id, $data);
        $this->json(['success' => true]);
    }

    public function delete($id) {
        $this->Sk_Promo_model->delete($id);
        $this->json(['success' => true]);
    }
}
