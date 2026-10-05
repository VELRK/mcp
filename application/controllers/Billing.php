<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Billing extends AdminController {

    public function __construct() {
        parent::__construct();
    }

    public function index() {
        try {
            return $this->json_response(true, 'Billing loaded successfully', ['tenant_id' => $this->tenant_id]);
        } catch (Exception $e) {
            log_message('error', 'Error in Billing::index - ' . $e->getMessage());
            return $this->json_response(false, 'Internal Server Error', null, 500);
        }
    }
}
