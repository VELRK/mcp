<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/admin/Sk_Base.php';

/**
 * Admin: Contact enquiries from website + mobile app.
 * shopkart/contacts
 */
class Contacts extends Sk_Base {

    public function __construct() {
        parent::__construct();
        $this->_ensure_schema();
    }

    public function index() {
        $status = trim((string)$this->input->get('status', TRUE));
        $source = trim((string)$this->input->get('source', TRUE));
        $search = trim((string)$this->input->get('search', TRUE));

        if ($this->db->table_exists('contact_enquiries')) {
            if ($status !== '' && in_array($status, ['new', 'read', 'replied', 'closed'], true)) {
                $this->db->where('status', $status);
            }
            if ($source !== '' && in_array($source, ['app', 'web'], true)) {
                $this->db->where('source', $source);
            }
            if ($search !== '') {
                $this->db->group_start()
                    ->like('name', $search)
                    ->or_like('email', $search)
                    ->or_like('phone', $search)
                    ->or_like('business_name', $search)
                    ->or_like('industry', $search)
                    ->or_like('subject', $search)
                    ->or_like('message', $search)
                    ->group_end();
            }
            $contacts = $this->db->order_by('created_at', 'DESC')->get('contact_enquiries')->result_array();
        } else {
            $contacts = [];
        }

        $newCount = 0;
        if ($this->db->table_exists('contact_enquiries')) {
            $newCount = (int)$this->db->where('status', 'new')->count_all_results('contact_enquiries');
        }

        $data['title']    = 'Contact Enquiries';
        $data['contacts'] = $contacts;
        $data['new_count'] = $newCount;
        $data['filters']  = ['status' => $status, 'source' => $source, 'search' => $search];
        $this->render('contacts/list', $data);
    }

    public function mark_read($id = 0) {
        $status = trim((string)($this->input->post('status') ?: 'read'));
        if (!in_array($status, ['new', 'read', 'replied', 'closed'], true)) {
            $status = 'read';
        }
        $this->db->where('id', (int)$id)->update('contact_enquiries', [
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->json(['success' => true, 'status' => $status]);
    }

    public function delete($id = 0) {
        $this->db->where('id', (int)$id)->delete('contact_enquiries');
        $this->json(['success' => true]);
    }

    public function view($id = 0) {
        $row = $this->db->where('id', (int)$id)->get('contact_enquiries')->row_array();
        if (!$row) {
            show_404();
        }
        if (($row['status'] ?? '') === 'new') {
            $this->db->where('id', (int)$id)->update('contact_enquiries', [
                'status'     => 'read',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $row['status'] = 'read';
        }
        $data['title']   = 'Contact Enquiry #' . (int)$id;
        $data['contact'] = $row;
        $this->render('contacts/view', $data);
    }

    private function _ensure_schema(): void {
        ensure_contact_enquiries_table();
    }
}
