<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/admin/Sk_Base.php';

class Customers extends Sk_Base {

    public function index() {
        $filters = [
            'search'    => trim((string)$this->input->get('search', TRUE)),
            'status'    => $this->input->get('status', TRUE),
            'date_from' => trim((string)$this->input->get('date_from', TRUE)),
            'date_to'   => trim((string)$this->input->get('date_to', TRUE)),
        ];
        if ($filters['status'] === null) {
            $filters['status'] = '';
        }

        $page   = max(1, (int)$this->input->get('page'));
        $limit  = 100;
        $offset = ($page - 1) * $limit;

        $data['title']     = 'Customers - 2DEAL Admin';
        $data['customers'] = $this->Sk_User_model->get_all_admin($limit, $offset, $filters);
        $data['total']     = $this->Sk_User_model->count_admin($filters);
        $data['page']      = $page;
        $data['limit']     = $limit;
        $data['filters']   = $filters;
        $data['counts']    = $this->Sk_User_model->status_counts();
        $data['search']    = $filters['search'];
        $this->render('customers/list', $data);
    }

    public function add() {
        $data['title'] = 'Add Customer';
        $this->render('customers/form', $data);
    }

    public function store() {
        $name  = trim((string)$this->input->post('name', TRUE));
        $email = trim((string)$this->input->post('email', TRUE));
        $phone = trim((string)$this->input->post('phone', TRUE));
        $status = $this->input->post('status') ? 1 : 0;
        $password = (string)$this->input->post('password', FALSE);

        if ($name === '') {
            $this->session->set_flashdata('error', 'Name is required.');
            redirect('admin/customers/add');
            return;
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->session->set_flashdata('error', 'A valid email is required.');
            redirect('admin/customers/add');
            return;
        }
        if ($this->Sk_User_model->email_exists($email)) {
            $this->session->set_flashdata('error', 'This email is already used by another customer.');
            redirect('admin/customers/add');
            return;
        }
        if ($password === '') {
            $password = bin2hex(random_bytes(4));
        } elseif (strlen($password) < 6) {
            $this->session->set_flashdata('error', 'Password must be at least 6 characters.');
            redirect('admin/customers/add');
            return;
        }

        $phoneNorm = null;
        if ($phone !== '') {
            $this->load->helper('sk_isms');
            $settings = $this->Sk_Admin_model->get_settings();
            $normalized = sk_isms_normalize_phone($phone, $settings);
            if (!$normalized) {
                $this->session->set_flashdata('error', sk_isms_phone_error());
                redirect('admin/customers/add');
                return;
            }
            if ($this->Sk_User_model->phone_exists($normalized)) {
                $this->session->set_flashdata('error', 'This phone is already used by another customer.');
                redirect('admin/customers/add');
                return;
            }
            $phoneNorm = $normalized;
        }

        $id = $this->Sk_User_model->create([
            'name'     => $name,
            'email'    => $email,
            'phone'    => $phoneNorm,
            'password' => $password,
            'status'   => $status,
        ]);

        $this->activity_log->log_admin('customers', 'create', $id, null, [
            'name'  => $name,
            'email' => $email,
        ]);
        $this->session->set_flashdata('success', 'Customer created successfully.');
        redirect('admin/customers/view/' . (int)$id);
    }

    public function view($id) {
        $data['title']    = 'Customer Detail';
        $data['customer'] = $this->Sk_User_model->get_by_id($id);
        if (!$data['customer']) show_404();
        $data['orders']    = $this->Sk_Order_model->get_user_orders($id);
        $data['addresses'] = $this->Sk_User_model->get_addresses($id);
        $this->render('customers/view', $data);
    }

    public function edit($id) {
        $customer = $this->Sk_User_model->get_by_id($id);
        if (!$customer) show_404();
        $data['title']    = 'Edit Customer';
        $data['customer'] = $customer;
        $this->render('customers/form', $data);
    }

    public function update($id) {
        $customer = $this->Sk_User_model->get_by_id($id);
        if (!$customer) show_404();

        $name  = trim((string)$this->input->post('name', TRUE));
        $email = trim((string)$this->input->post('email', TRUE));
        $phone = trim((string)$this->input->post('phone', TRUE));
        $status = $this->input->post('status') ? 1 : 0;
        $password = (string)$this->input->post('password', FALSE);

        if ($name === '') {
            $this->session->set_flashdata('error', 'Name is required.');
            redirect('admin/customers/edit/' . (int)$id);
            return;
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->session->set_flashdata('error', 'A valid email is required.');
            redirect('admin/customers/edit/' . (int)$id);
            return;
        }

        $otherEmail = $this->Sk_User_model->get_by_email($email);
        if ($otherEmail && (int)$otherEmail['id'] !== (int)$id) {
            $this->session->set_flashdata('error', 'This email is already used by another customer.');
            redirect('admin/customers/edit/' . (int)$id);
            return;
        }

        if ($phone !== '') {
            $this->load->helper('sk_isms');
            $settings = $this->Sk_Admin_model->get_settings();
            $normalized = sk_isms_normalize_phone($phone, $settings);
            if (!$normalized) {
                $this->session->set_flashdata('error', sk_isms_phone_error());
                redirect('admin/customers/edit/' . (int)$id);
                return;
            }
            $phone = $normalized;
            $otherPhone = $this->Sk_User_model->get_by_phone($phone);
            if ($otherPhone && (int)$otherPhone['id'] !== (int)$id) {
                $this->session->set_flashdata('error', 'This phone is already used by another customer.');
                redirect('admin/customers/edit/' . (int)$id);
                return;
            }
        } else {
            $phone = null;
        }

        if ($password !== '' && strlen($password) < 6) {
            $this->session->set_flashdata('error', 'Password must be at least 6 characters.');
            redirect('admin/customers/edit/' . (int)$id);
            return;
        }

        $update = [
            'name'   => $name,
            'email'  => $email,
            'phone'  => $phone,
            'status' => $status,
        ];
        if ($password !== '') {
            $update['password'] = $password;
        }

        $this->Sk_User_model->update($id, $update);
        $this->activity_log->log_admin('customers', 'update', (int)$id);
        $this->session->set_flashdata('success', 'Customer updated successfully.');
        redirect('admin/customers/view/' . (int)$id);
    }

    public function toggle($id) {
        $user = $this->Sk_User_model->get_by_id($id);
        if (!$user) {
            return $this->json(['success' => false, 'message' => 'Customer not found.'], 404);
        }
        $new = $user['status'] ? 0 : 1;
        $this->Sk_User_model->update($id, ['status' => $new]);
        $this->json(['success' => true, 'status' => $new]);
    }

    public function delete($id) {
        $id = (int)$id;
        $customer = $this->Sk_User_model->get_by_id($id);
        if (!$customer) {
            return $this->json(['success' => false, 'message' => 'Customer not found.'], 404);
        }

        $result = $this->Sk_User_model->hard_delete($id);
        if (!$result['ok']) {
            return $this->json(['success' => false, 'message' => $result['message']]);
        }

        $this->activity_log->log_admin('customers', 'hard_delete', $id, [
            'name'  => $customer['name'],
            'email' => $customer['email'],
        ]);
        $this->json(['success' => true, 'message' => $result['message']]);
    }

    public function export() {
        $filters = [
            'search'    => trim((string)$this->input->get('search', TRUE)),
            'status'    => $this->input->get('status', TRUE),
            'date_from' => trim((string)$this->input->get('date_from', TRUE)),
            'date_to'   => trim((string)$this->input->get('date_to', TRUE)),
        ];
        if ($filters['status'] === null) {
            $filters['status'] = '';
        }

        $rows = $this->Sk_User_model->export_rows($filters);
        $this->activity_log->log_admin('customers', 'export', null, null, [
            'count'   => count($rows),
            'filters' => $filters,
        ]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="customers_' . date('Y-m-d_His') . '.csv"');
        $out = fopen('php://output', 'w');
        // UTF-8 BOM so Excel opens columns correctly
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['ID', 'Name', 'Email', 'Phone', 'Status', 'Joined', 'Last Login']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'],
                $r['name'],
                $r['email'],
                $r['phone'] ?? '',
                !empty($r['status']) ? 'Active' : 'Blocked',
                $r['created_at'] ?? '',
                $r['last_login'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    public function import_template() {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="customers_import_template.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['name', 'email', 'phone', 'password', 'status']);
        fputcsv($out, ['Jane Doe', 'jane@example.com', '60123456789', 'secret12', '1']);
        fputcsv($out, ['John Smith', 'john@example.com', '60198765432', '', '1']);
        fclose($out);
        exit;
    }

    public function import() {
        if ($this->input->method(TRUE) !== 'POST') {
            $data['title'] = 'Import Customers';
            $this->render('customers/import', $data);
            return;
        }

        if (empty($_FILES['import_file']['tmp_name']) || !is_uploaded_file($_FILES['import_file']['tmp_name'])) {
            $this->session->set_flashdata('error', 'Please choose a CSV file to upload.');
            redirect('admin/customers/import');
            return;
        }

        $name = (string)($_FILES['import_file']['name'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'], true)) {
            $this->session->set_flashdata('error', 'Only CSV files are supported. Save your Excel sheet as CSV (UTF-8) and upload again.');
            redirect('admin/customers/import');
            return;
        }

        $handle = fopen($_FILES['import_file']['tmp_name'], 'r');
        if (!$handle) {
            $this->session->set_flashdata('error', 'Could not read the uploaded file.');
            redirect('admin/customers/import');
            return;
        }

        // Strip UTF-8 BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            $this->session->set_flashdata('error', 'The CSV file is empty.');
            redirect('admin/customers/import');
            return;
        }

        $map = $this->_map_import_headers($header);
        if (!isset($map['name']) || !isset($map['email'])) {
            fclose($handle);
            $this->session->set_flashdata('error', 'CSV must include name and email columns.');
            redirect('admin/customers/import');
            return;
        }

        $this->load->helper('sk_isms');
        $settings = $this->Sk_Admin_model->get_settings();

        $created = 0;
        $skipped = 0;
        $errors  = [];
        $rowNum  = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if ($this->_csv_row_empty($row)) {
                continue;
            }

            $custName = trim((string)($row[$map['name']] ?? ''));
            $email = strtolower(trim((string)($row[$map['email']] ?? '')));
            $phone = isset($map['phone']) ? trim((string)($row[$map['phone']] ?? '')) : '';
            $password = isset($map['password']) ? trim((string)($row[$map['password']] ?? '')) : '';
            $statusRaw = isset($map['status']) ? trim((string)($row[$map['status']] ?? '1')) : '1';

            if ($custName === '' || $email === '') {
                $skipped++;
                $errors[] = "Row {$rowNum}: name and email are required.";
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                $errors[] = "Row {$rowNum}: invalid email ({$email}).";
                continue;
            }
            if ($this->Sk_User_model->email_exists($email)) {
                $skipped++;
                $errors[] = "Row {$rowNum}: email already exists ({$email}).";
                continue;
            }

            $phoneNorm = null;
            if ($phone !== '') {
                $normalized = sk_isms_normalize_phone($phone, $settings);
                if (!$normalized) {
                    $skipped++;
                    $errors[] = "Row {$rowNum}: invalid phone ({$phone}).";
                    continue;
                }
                if ($this->Sk_User_model->phone_exists($normalized)) {
                    $skipped++;
                    $errors[] = "Row {$rowNum}: phone already exists ({$phone}).";
                    continue;
                }
                $phoneNorm = $normalized;
            }

            if ($password === '') {
                $password = bin2hex(random_bytes(4));
            } elseif (strlen($password) < 6) {
                $skipped++;
                $errors[] = "Row {$rowNum}: password must be at least 6 characters.";
                continue;
            }

            $status = 1;
            $statusLower = strtolower($statusRaw);
            if (in_array($statusLower, ['0', 'blocked', 'inactive', 'no', 'false'], true)) {
                $status = 0;
            }

            try {
                $this->Sk_User_model->create([
                    'name'     => $custName,
                    'email'    => $email,
                    'phone'    => $phoneNorm,
                    'password' => $password,
                    'status'   => $status,
                ]);
                $created++;
            } catch (Exception $e) {
                $skipped++;
                $errors[] = "Row {$rowNum}: " . $e->getMessage();
            }
        }
        fclose($handle);

        $this->activity_log->log_admin('customers', 'import', null, null, [
            'created' => $created,
            'skipped' => $skipped,
        ]);

        $msg = "Import finished: {$created} created, {$skipped} skipped.";
        if ($errors) {
            $msg .= ' ' . implode(' ', array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $msg .= ' (+' . (count($errors) - 5) . ' more)';
            }
            $this->session->set_flashdata('error', $msg);
        } else {
            $this->session->set_flashdata('success', $msg);
        }
        redirect('admin/customers');
    }

    protected function _map_import_headers(array $header): array {
        $map = [];
        foreach ($header as $i => $col) {
            $key = strtolower(trim((string)$col));
            $key = preg_replace('/[^a-z0-9_]+/', '', $key);
            if (in_array($key, ['name', 'fullname', 'customername'], true)) {
                $map['name'] = $i;
            } elseif (in_array($key, ['email', 'emailaddress', 'mail'], true)) {
                $map['email'] = $i;
            } elseif (in_array($key, ['phone', 'mobile', 'phonenumber', 'tel'], true)) {
                $map['phone'] = $i;
            } elseif (in_array($key, ['password', 'pass', 'pwd'], true)) {
                $map['password'] = $i;
            } elseif (in_array($key, ['status', 'active', 'state'], true)) {
                $map['status'] = $i;
            }
        }
        return $map;
    }

    protected function _csv_row_empty(array $row): bool {
        foreach ($row as $cell) {
            if (trim((string)$cell) !== '') {
                return false;
            }
        }
        return true;
    }
}
