<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Controller extends CI_Controller {

    public function __construct() {
        parent::__construct();
        // Load common libraries or helpers here
        $this->load->helper('url');
        $this->load->helper('security');
    }

    /**
     * Standard JSON response output
     */
    protected function json_response($status, $message, $data = [], $http_code = 200) {
        $response = [
            'status'  => $status,
            'message' => $message,
            'data'    => $data
        ];
        
        return $this->output
            ->set_content_type('application/json')
            ->set_status_header($http_code)
            ->set_output(json_encode($response));
    }
}

/**
 * Base controller for all Admin/SaaS Dashboard routes.
 * Handles Authentication and Authorization (RBAC).
 */
class AdminController extends MY_Controller {

    protected $user_id;
    protected $tenant_id; // For SaaS multi-tenancy

    public function __construct() {
        parent::__construct();
        $this->check_auth();
    }

    private function check_auth() {
        // Placeholder for authentication logic
        // if (!$this->session->userdata('logged_in')) {
        //     redirect('login');
        // }
        
        // Mock data for now
        $this->user_id = 1;
        $this->tenant_id = 1; 
    }
}

/**
 * Base controller for REST APIs (WhatsApp Webhooks, AI integrations, Mobile Apps)
 */
class ApiController extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->validate_api_key();
    }

    private function validate_api_key() {
        // Placeholder for API Key Validation
        $headers = $this->input->request_headers();
        $api_key = isset($headers['X-API-KEY']) ? $headers['X-API-KEY'] : null;

        // if (empty($api_key) || !$this->is_valid_key($api_key)) {
        //     $this->json_response(false, 'Unauthorized', null, 401);
        //     exit;
        // }
    }
}
