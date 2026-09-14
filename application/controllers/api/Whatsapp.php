<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Whatsapp extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Sk_Vendor_whatsapp_account_model');
        $this->load->model('Sk_Vendor_whatsapp_message_model');
        $this->load->model('Sk_Vendor_model');
        $this->load->database();
    }

    private function json($data, $status = 200) {
        $this->output->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data));
    }

    // Provision endpoint: auto-create/update a vendor whatsapp account
    public function provision() {
        $input = json_decode($this->input->raw_input_stream, true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $vendor_id = isset($input['vendor_id']) ? (int)$input['vendor_id'] : 0;
        $phone = trim((string)($input['phone_number_id'] ?? ''));

        // Resolve vendor via trusted metadata if vendor_id not provided
        if ($vendor_id < 1) {
            // Try find by existing phone mapping
            if ($phone !== '') {
                $existing = $this->Sk_Vendor_whatsapp_account_model->get_by_phone($phone);
                if (!empty($existing) && !empty($existing['vendor_id'])) {
                    $vendor_id = (int)$existing['vendor_id'];
                }
            }

            // Try find by business_id
            if ($vendor_id < 1 && !empty($input['business_id'])) {
                $businessId = trim((string)$input['business_id']);
                if ($businessId !== '') {
                    $row = $this->db->where('business_id', $businessId)->get('vendor_whatsapp_accounts')->row_array();
                    if (!empty($row) && !empty($row['vendor_id'])) {
                        $vendor_id = (int)$row['vendor_id'];
                    }
                }
            }
        }

        if ($vendor_id < 1) {
            $this->json(['success' => false, 'message' => 'Could not resolve vendor. Provide vendor_id or ensure phone_number_id/business_id is already associated.'], 400);
            return;
        }

        $vendor = $this->Sk_Vendor_model->get_by_id($vendor_id, false);
        if (!$vendor) {
            $this->json(['success' => false, 'message' => 'vendor not found'], 404);
            return;
        }

        $data = [
            'phone_number_id' => $phone,
            'waba_id' => trim((string)($input['waba_id'] ?? '')),
            'display_phone' => trim((string)($input['display_phone'] ?? '')),
            'business_id' => trim((string)($input['business_id'] ?? '')),
            'access_token' => trim((string)($input['access_token'] ?? '')),
            'refresh_token' => trim((string)($input['refresh_token'] ?? '')),
            'token_expires' => trim((string)($input['token_expires'] ?? '')),
            'status' => trim((string)($input['status'] ?? 'active')),
            'is_default' => !empty($input['is_default']) ? 1 : 0,
        ];

        $res = $this->Sk_Vendor_whatsapp_account_model->save_for_vendor($vendor_id, $data);
        if (empty($res['ok'])) {
            $this->json(['success' => false, 'message' => $res['message'] ?? 'Could not save account'], 500);
            return;
        }

        $this->json(['success' => true, 'account' => $res['row']]);
    }

    // Log a single message send for billing/reporting
    public function log_message() {
        $input = json_decode($this->input->raw_input_stream, true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $account_id = isset($input['account_id']) ? (int)$input['account_id'] : 0;
        $phone = trim((string)($input['phone_number_id'] ?? ''));

        if ($account_id < 1 && $phone === '') {
            $this->json(['success' => false, 'message' => 'account_id or phone_number_id is required'], 400);
            return;
        }

        if ($account_id < 1) {
            $acc = $this->Sk_Vendor_whatsapp_account_model->get_by_phone($phone);
            if (!$acc) {
                $this->json(['success' => false, 'message' => 'account not found for phone_number_id'], 404);
                return;
            }
            $account_id = (int)$acc['id'];
        }

        $log = [
            'account_id' => $account_id,
            'vendor_id' => isset($input['vendor_id']) ? (int)$input['vendor_id'] : 0,
            'message_id' => trim((string)($input['message_id'] ?? '')),
            'recipient' => trim((string)($input['recipient'] ?? '')),
            'message_type' => trim((string)($input['message_type'] ?? 'text')),
            'cost' => isset($input['cost']) ? (float)$input['cost'] : 0.0,
            'currency' => trim((string)($input['currency'] ?? 'USD')),
            'status' => trim((string)($input['status'] ?? 'sent')),
            'meta' => isset($input['meta']) ? $input['meta'] : null,
            'sent_at' => trim((string)($input['sent_at'] ?? date('Y-m-d H:i:s'))),
        ];

        $res = $this->Sk_Vendor_whatsapp_message_model->log($log);
        if (empty($res['ok'])) {
            $this->json(['success' => false, 'message' => $res['message'] ?? 'Could not log message'], 500);
            return;
        }

        $this->json(['success' => true, 'id' => $res['id']]);
    }

    // Get account report (summary + details)
    public function account_report($account_id = 0) {
        $account_id = (int)$account_id;
        if ($account_id < 1) {
            $this->json(['success' => false, 'message' => 'account_id required in URL'], 400);
            return;
        }

        $from = $this->input->get('from', TRUE) ?: null;
        $to = $this->input->get('to', TRUE) ?: null;

        $report = $this->Sk_Vendor_whatsapp_message_model->get_report($account_id, $from, $to);
        $acc = $this->db->where('id', $account_id)->get('vendor_whatsapp_accounts')->row_array();

        $this->json(['success' => true, 'account' => $acc, 'report' => $report]);
    }

}
