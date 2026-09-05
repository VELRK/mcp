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
        $data['title']   = 'Add Customer';
        $data['address'] = [];
        $this->render('customers/form', $data);
    }

    public function store() {
        $parsed = $this->_parse_customer_post();
        if ($parsed['error']) {
            $this->session->set_flashdata('error', $parsed['error']);
            redirect('admin/customers/add');
            return;
        }

        $id = $this->Sk_User_model->create([
            'name'     => $parsed['name'],
            'email'    => $parsed['email'],
            'phone'    => $parsed['phone'],
            'password' => bin2hex(random_bytes(8)),
            'status'   => $parsed['status'],
        ]);

        $this->_save_customer_address((int)$id, $parsed);

        $this->activity_log->log_admin('customers', 'create', $id, null, [
            'name'  => $parsed['name'],
            'email' => $parsed['email'],
            'phone' => $parsed['phone'],
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
        $addresses = $this->Sk_User_model->get_addresses($id);
        $data['title']    = 'Edit Customer';
        $data['customer'] = $customer;
        $data['address']  = $addresses[0] ?? [];
        $this->render('customers/form', $data);
    }

    public function update($id) {
        $customer = $this->Sk_User_model->get_by_id($id);
        if (!$customer) show_404();

        $parsed = $this->_parse_customer_post((int)$id);
        if ($parsed['error']) {
            $this->session->set_flashdata('error', $parsed['error']);
            redirect('admin/customers/edit/' . (int)$id);
            return;
        }

        $password = (string)$this->input->post('password', FALSE);
        if ($password !== '' && strlen($password) < 6) {
            $this->session->set_flashdata('error', 'Password must be at least 6 characters.');
            redirect('admin/customers/edit/' . (int)$id);
            return;
        }

        $update = [
            'name'   => $parsed['name'],
            'email'  => $parsed['email'],
            'phone'  => $parsed['phone'],
            'status' => $parsed['status'],
        ];
        if ($password !== '') {
            $update['password'] = $password;
        }

        $this->Sk_User_model->update($id, $update);
        $this->_save_customer_address((int)$id, $parsed, !empty($parsed['address_id']) ? (int)$parsed['address_id'] : null);
        $this->activity_log->log_admin('customers', 'update', (int)$id);
        $this->session->set_flashdata('success', 'Customer updated successfully.');
        redirect('admin/customers/view/' . (int)$id);
    }

    /**
     * Validate and normalize basic customer + optional address fields from POST.
     * Email optional; no password on create.
     */
    protected function _parse_customer_post(?int $excludeId = null): array {
        $name   = trim((string)$this->input->post('name', TRUE));
        $email  = trim((string)$this->input->post('email', TRUE));
        $phone  = trim((string)$this->input->post('phone', TRUE));
        $status = $this->input->post('status') ? 1 : 0;

        $out = [
            'error'      => null,
            'name'       => $name,
            'email'      => null,
            'phone'      => null,
            'status'     => $status,
            'address_id' => (int)$this->input->post('address_id'),
            'company_name'  => trim((string)$this->input->post('company_name', TRUE)),
            'address_label' => trim((string)$this->input->post('address_label', TRUE)) ?: 'Home',
            'line1'         => trim((string)$this->input->post('line1', TRUE)),
            'line2'         => trim((string)$this->input->post('line2', TRUE)),
            'city'          => trim((string)$this->input->post('city', TRUE)),
            'state'         => trim((string)$this->input->post('state', TRUE)),
            'pincode'       => trim((string)$this->input->post('pincode', TRUE)),
            'country'       => trim((string)$this->input->post('country', TRUE)) ?: 'Malaysia',
            'address_phone' => trim((string)$this->input->post('address_phone', TRUE)),
        ];

        if ($name === '') {
            $out['error'] = 'Name is required.';
            return $out;
        }

        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out['error'] = 'Please enter a valid email address.';
                return $out;
            }
            if ($this->Sk_User_model->email_exists($email, $excludeId)) {
                $out['error'] = 'This email is already used by another customer.';
                return $out;
            }
            $out['email'] = strtolower($email);
        }

        if ($phone !== '') {
            $this->load->helper('sk_isms');
            $settings = $this->Sk_Admin_model->get_settings();
            $normalized = sk_isms_normalize_phone($phone, $settings);
            if (!$normalized) {
                $out['error'] = sk_isms_phone_error();
                return $out;
            }
            if ($this->Sk_User_model->phone_exists($normalized, $excludeId)) {
                $out['error'] = 'This phone is already used by another customer.';
                return $out;
            }
            $out['phone'] = $normalized;
        }

        if ($out['email'] === null && $out['phone'] === null) {
            $out['error'] = 'Enter at least a phone number or email.';
            return $out;
        }

        return $out;
    }

    protected function _save_customer_address(int $userId, array $parsed, ?int $addressId = null): void {
        $line1 = $parsed['line1'] ?? '';
        if ($line1 === '') {
            return;
        }
        $addrPhone = $parsed['address_phone'] !== '' ? $parsed['address_phone'] : ($parsed['phone'] ?? '');
        $payload = [
            'user_id'      => $userId,
            'full_name'    => $parsed['name'],
            'company_name' => $parsed['company_name'] !== '' ? $parsed['company_name'] : null,
            'phone'        => $addrPhone !== '' ? $addrPhone : ($parsed['phone'] ?? ''),
            'line1'        => $line1,
            'line2'        => $parsed['line2'],
            'city'         => $parsed['city'],
            'state'        => $parsed['state'],
            'pincode'      => $parsed['pincode'],
            'country'      => $parsed['country'] ?: 'Malaysia',
            'label'        => $parsed['address_label'] ?: 'Home',
            'address_type' => 'shipping',
            'is_default'   => 1,
        ];
        if ($addressId) {
            $payload['id'] = $addressId;
        }
        $this->Sk_User_model->save_address($payload);
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
        fputcsv($out, ['name', 'phone', 'email', 'status']);
        fputcsv($out, ['Jane Doe', '60123456789', 'jane@example.com', '1']);
        fputcsv($out, ['John Smith', '60198765432', '', '1']);
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
        if (!isset($map['name'])) {
            fclose($handle);
            $this->session->set_flashdata('error', 'CSV must include a name column.');
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
            $email = isset($map['email']) ? strtolower(trim((string)($row[$map['email']] ?? ''))) : '';
            $phone = isset($map['phone']) ? trim((string)($row[$map['phone']] ?? '')) : '';
            $statusRaw = isset($map['status']) ? trim((string)($row[$map['status']] ?? '1')) : '1';

            if ($custName === '') {
                $skipped++;
                $errors[] = "Row {$rowNum}: name is required.";
                continue;
            }

            $emailVal = null;
            if ($email !== '') {
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
                $emailVal = $email;
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

            if ($emailVal === null && $phoneNorm === null) {
                $skipped++;
                $errors[] = "Row {$rowNum}: enter at least a phone or email.";
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
                    'email'    => $emailVal,
                    'phone'    => $phoneNorm,
                    'password' => '',
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
