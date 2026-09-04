<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/api/Sk_Base_Api.php';

/**
 * Contact / support enquiries (mobile app + website).
 *
 * POST /shopkart-api/contact
 * GET  /shopkart-api/contact          (own list: JWT or ?email=)
 * GET  /shopkart-api/contact/(:num)
 */
class Sk_Contact extends Sk_Base_Api {

    public function __construct() {
        parent::__construct();
        $this->_ensure_schema();
    }

    /** POST — submit contact enquiry (auth optional). */
    public function store() {
        $data = $this->body();

        $name    = trim((string)($data['name'] ?? ''));
        $email   = strtolower(trim((string)($data['email'] ?? '')));
        $phone   = trim((string)($data['phone'] ?? $data['mobile'] ?? ''));
        $subject = trim((string)($data['subject'] ?? $data['title'] ?? ''));
        $message = trim((string)($data['message'] ?? $data['details'] ?? ''));
        $source  = strtolower(trim((string)($data['source'] ?? $data['platform'] ?? 'app')));
        if (!in_array($source, ['app', 'web', 'mobile', 'ios', 'android'], true)) {
            $source = 'app';
        }
        if ($source === 'mobile' || $source === 'ios' || $source === 'android') {
            $source = 'app';
        }

        $userId = null;
        $jwt = $this->sk_jwt->get_user_from_request();
        if ($jwt && !empty($jwt['user_id'])) {
            $userId = (int)$jwt['user_id'];
            $user = $this->Sk_User_model->get_by_id($userId);
            if ($user) {
                if ($name === '') {
                    $name = trim((string)($user['name'] ?? ''));
                }
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $email = strtolower(trim((string)($user['email'] ?? '')));
                }
                if ($phone === '') {
                    $phone = trim((string)($user['phone'] ?? ''));
                }
            }
        }

        if ($name === '') {
            return $this->error('Name is required.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('A valid email is required.');
        }
        if ($message === '') {
            return $this->error('Message is required.');
        }

        $row = [
            'user_id'    => $userId ?: null,
            'name'       => $name,
            'email'      => $email,
            'phone'      => $phone !== '' ? substr($phone, 0, 40) : null,
            'subject'    => $subject !== '' ? substr($subject, 0, 200) : null,
            'message'    => $message,
            'source'     => $source,
            'status'     => 'new',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $this->db->insert('contact_enquiries', $row);
        $id = (int)$this->db->insert_id();

        $mailBody = $message;
        if ($subject !== '') {
            $mailBody = "Subject: {$subject}\n\n" . $mailBody;
        }
        if ($phone !== '') {
            $mailBody = "Phone: {$phone}\n" . $mailBody;
        }
        $mailBody .= "\n\n— Source: " . $source . ($userId ? " (user #{$userId})" : '');

        $this->load->helper('sk_mailer');
        $mail = sk_mail_contact_enquiry($name, $email, $mailBody);
        if (empty($mail['user']) && ENVIRONMENT === 'production') {
            log_message('error', 'Contact enquiry email failed for ' . $email);
        }

        $this->success([
            'id'      => $id,
            'status'  => 'new',
            'source'  => $source,
            'subject' => $subject !== '' ? $subject : null,
        ], 'Thank you! We will get back to you shortly.', 201);
    }

    /**
     * GET — list own enquiries.
     * Auth JWT: that user's rows. Public: require ?email=
     */
    public function index() {
        $jwt = $this->sk_jwt->get_user_from_request();
        $email = strtolower(trim((string)$this->input->get('email', TRUE)));
        $status = trim((string)$this->input->get('status', TRUE));
        $limit = min(50, max(1, (int)($this->input->get('limit') ?? 20)));
        $page  = max(1, (int)($this->input->get('page') ?? 1));
        $offset = ($page - 1) * $limit;

        $applyScope = function () use ($jwt, $email, $status) {
            if ($jwt && !empty($jwt['user_id'])) {
                $user = $this->Sk_User_model->get_by_id((int)$jwt['user_id']);
                $this->db->group_start()
                    ->where('user_id', (int)$jwt['user_id']);
                if ($user && !empty($user['email'])) {
                    $this->db->or_where('email', strtolower($user['email']));
                }
                $this->db->group_end();
            } elseif ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->db->where('email', $email);
            } else {
                return false;
            }
            if (in_array($status, ['new', 'read', 'replied', 'closed'], true)) {
                $this->db->where('status', $status);
            }
            return true;
        };

        if (!$applyScope()) {
            return $this->error('Login or provide email to list contact enquiries.', 401);
        }
        $total = (int)$this->db->count_all_results('contact_enquiries');

        if (!$applyScope()) {
            return $this->error('Login or provide email to list contact enquiries.', 401);
        }
        $rows = $this->db->order_by('id', 'DESC')->limit($limit, $offset)
            ->get('contact_enquiries')->result_array();

        $this->success([
            'contacts'    => array_map([$this, '_public_row'], $rows),
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => $limit > 0 ? (int)ceil($total / $limit) : 0,
        ]);
    }

    /** GET /contact/:id */
    public function show($id = 0) {
        $id = (int)$id;
        $row = $this->db->where('id', $id)->get('contact_enquiries')->row_array();
        if (!$row) {
            return $this->error('Contact enquiry not found.', 404);
        }

        $jwt = $this->sk_jwt->get_user_from_request();
        $email = strtolower(trim((string)$this->input->get('email', TRUE)));
        $allowed = false;
        if ($jwt && !empty($jwt['user_id'])) {
            if ((int)($row['user_id'] ?? 0) === (int)$jwt['user_id']) {
                $allowed = true;
            } else {
                $user = $this->Sk_User_model->get_by_id((int)$jwt['user_id']);
                if ($user && strtolower((string)$user['email']) === strtolower((string)$row['email'])) {
                    $allowed = true;
                }
            }
        } elseif ($email !== '' && strtolower($email) === strtolower((string)$row['email'])) {
            $allowed = true;
        }

        if (!$allowed) {
            return $this->error('Not allowed to view this enquiry.', 403);
        }

        $this->success($this->_public_row($row));
    }

    private function _public_row(array $row): array {
        return [
            'id'         => (int)$row['id'],
            'name'       => $row['name'],
            'email'      => $row['email'],
            'phone'      => $row['phone'] ?? null,
            'subject'    => $row['subject'] ?? null,
            'message'    => $row['message'],
            'source'     => $row['source'] ?? 'app',
            'status'     => $row['status'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function _ensure_schema(): void {
        ensure_contact_enquiries_table();
    }
}
