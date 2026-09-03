<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/admin/Sk_Base.php';

class Whatsapp extends Sk_Base {

    public function __construct() {
        parent::__construct();
        $this->load->helper('sk_whatsapp_cloud');
        $this->load->model('Sk_Whatsapp_cloud_model');
        sk_wa_cloud_ensure_schema();
    }

    public function index() {
        $data['title'] = 'WhatsApp Inbox';
        $data['ready'] = sk_wa_cloud_is_ready($this->Sk_Admin_model->get_settings());
        $data['templates'] = $this->Sk_Whatsapp_cloud_model->list_templates();
        $data['webhook_url'] = site_url('shopkart-api/whatsapp/webhook');
        $this->render('whatsapp/inbox', $data);
    }

    public function conversations() {
        $search = trim((string)$this->input->get('q', TRUE));
        $rows = $this->Sk_Whatsapp_cloud_model->list_conversations($search);
        return $this->json(['success' => true, 'conversations' => $rows]);
    }

    public function thread($id = 0) {
        $id = (int)$id;
        $conv = $this->Sk_Whatsapp_cloud_model->get_conversation($id);
        if (!$conv) {
            return $this->json(['success' => false, 'message' => 'Conversation not found.'], 404);
        }
        $after = (int)$this->input->get('after');
        if ($after < 1) {
            $this->Sk_Whatsapp_cloud_model->mark_read($id);
        }
        $msgs = $this->Sk_Whatsapp_cloud_model->list_messages($id, $after);
        return $this->json(['success' => true, 'conversation' => $conv, 'messages' => $msgs]);
    }

    public function start() {
        $phone = sk_wa_cloud_normalize_phone((string)$this->input->post('phone', TRUE));
        $name = trim((string)$this->input->post('name', TRUE));
        if (strlen($phone) < 8) {
            return $this->json(['success' => false, 'message' => 'Enter a valid phone with country code.']);
        }
        $conv = $this->Sk_Whatsapp_cloud_model->find_or_create_conversation($phone, $name);
        return $this->json(['success' => true, 'conversation' => $conv]);
    }

    public function send() {
        $settings = $this->Sk_Admin_model->get_settings();
        if (!sk_wa_cloud_is_ready($settings)) {
            return $this->json(['success' => false, 'message' => 'Connect Meta Cloud API in Settings → WhatsApp Cloud.']);
        }
        $convId = (int)$this->input->post('conversation_id');
        $conv = $this->Sk_Whatsapp_cloud_model->get_conversation($convId);
        if (!$conv) {
            return $this->json(['success' => false, 'message' => 'Conversation not found.']);
        }

        $type = trim((string)$this->input->post('type', TRUE));
        if (!in_array($type, ['text', 'image', 'video', 'template'], true)) {
            $type = 'text';
        }
        $caption = trim((string)$this->input->post('body', FALSE));

        $this->load->library('Whatsapp_cloud', $settings);
        $result = ['success' => false, 'message' => 'Nothing to send.'];
        $mediaUrl = '';
        $mediaId = '';
        $tplName = '';

        if ($type === 'text') {
            if ($caption === '') {
                return $this->json(['success' => false, 'message' => 'Type a message.']);
            }
            $result = $this->whatsapp_cloud->send_text($conv['phone'], $caption);
        } elseif ($type === 'template') {
            $tplId = (int)$this->input->post('template_id');
            $tpl = $this->Sk_Whatsapp_cloud_model->get_template($tplId);
            if (!$tpl) {
                return $this->json(['success' => false, 'message' => 'Template not found.']);
            }
            $tplName = $tpl['name'];
            $user = $this->Sk_User_model->get_by_phone((string)$conv['phone']);
            $ctx = sk_wa_cloud_load_customer_context($user ?: [
                'name'  => (string)($conv['name'] ?? ''),
                'phone' => (string)$conv['phone'],
            ], $settings);
            $built = sk_wa_cloud_send_components($tpl, $ctx);
            $result = $this->whatsapp_cloud->send_template(
                $conv['phone'],
                $tpl['name'],
                $tpl['language'],
                $built['components']
            );
            if ($caption === '') {
                $caption = 'Template: ' . $tpl['name'];
            }
        } else {
            $file = $this->_store_upload($type);
            if (!empty($file['error'])) {
                return $this->json(['success' => false, 'message' => $file['error']]);
            }
            $mediaUrl = $file['url'];
            $abs = $file['path'];
            $mime = $file['mime'];
            $up = $this->whatsapp_cloud->upload_media($abs, $mime);
            if (!empty($up['success']) && !empty($up['id'])) {
                $mediaId = $up['id'];
                $result = $this->whatsapp_cloud->send_media_id($conv['phone'], $type, $mediaId, $caption);
            } else {
                $result = $this->whatsapp_cloud->send_media($conv['phone'], $type, $mediaUrl, $caption);
            }
        }

        $wamid = '';
        if (!empty($result['data']['messages'][0]['id'])) {
            $wamid = (string)$result['data']['messages'][0]['id'];
        }
        $ok = !empty($result['success']);
        $this->Sk_Whatsapp_cloud_model->add_message($convId, [
            'wamid'         => $wamid ?: null,
            'direction'     => 'out',
            'type'          => $type,
            'body'          => $caption,
            'media_url'     => $mediaUrl ?: null,
            'media_id'      => $mediaId ?: null,
            'template_name' => $tplName ?: null,
            'status'        => $ok ? 'sent' : 'failed',
            'error_text'    => $ok ? null : ($result['message'] ?? 'Send failed'),
            'raw_json'      => json_encode($result['data'] ?? $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return $this->json([
            'success' => $ok,
            'message' => $ok ? 'Sent.' : ($result['message'] ?? 'Send failed.'),
        ]);
    }

    public function templates() {
        $data['title'] = 'WhatsApp Templates';
        $data['templates'] = $this->Sk_Whatsapp_cloud_model->list_templates();
        $data['ready'] = sk_wa_cloud_is_ready($this->Sk_Admin_model->get_settings());
        $this->render('whatsapp/templates', $data);
    }

    public function template_form($id = 0) {
        $row = $id ? $this->Sk_Whatsapp_cloud_model->get_template((int)$id) : null;
        if ($id && !$row) {
            show_404();
        }
        $data['title'] = $row ? 'Edit template' : 'New template';
        $data['row'] = $row;
        $data['customer_modules'] = sk_wa_cloud_customer_modules();
        $this->render('whatsapp/template_form', $data);
    }

    public function template_save($id = 0) {
        $id = (int)$id;
        $name = strtolower(trim((string)$this->input->post('name', TRUE)));
        $name = preg_replace('/[^a-z0-9_]+/', '_', $name);
        $kind = $this->input->post('kind', TRUE);
        if (!in_array($kind, ['text', 'image', 'video'], true)) {
            $kind = 'text';
        }
        if ($name === '') {
            $this->session->set_flashdata('error', 'Template name is required (letters, numbers, underscore).');
            redirect($id ? 'admin/whatsapp/templates/edit/' . $id : 'admin/whatsapp/templates/add');
            return;
        }
        $payload = [
            'name'        => $name,
            'language'    => trim((string)$this->input->post('language', TRUE)) ?: 'en',
            'category'    => strtoupper(trim((string)$this->input->post('category', TRUE)) ?: 'UTILITY'),
            'kind'        => $kind,
            'body_text'   => trim((string)$this->input->post('body_text', FALSE)),
            'header_text' => trim((string)$this->input->post('header_text', TRUE)),
            'footer_text' => trim((string)$this->input->post('footer_text', TRUE)),
            'status'       => 'DRAFT',
            'variable_map' => json_encode(
                sk_wa_cloud_decode_variable_map($this->input->post('variable_map', FALSE)),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
        ];
        $existing = $id ? $this->Sk_Whatsapp_cloud_model->get_template($id) : null;
        if ($kind !== 'text') {
            $file = $this->_store_upload($kind);
            if (empty($file['error']) && !empty($file['url'])) {
                $payload['media_url'] = $file['url'];
            } elseif (!$existing || empty($existing['media_url'])) {
                $this->session->set_flashdata('error', $file['error'] ?? 'Upload an image or video for this template.');
                redirect($id ? 'admin/whatsapp/templates/edit/' . $id : 'admin/whatsapp/templates/add');
                return;
            }
        }
        $savedId = $this->Sk_Whatsapp_cloud_model->save_template($payload, $id);
        $push = (string)$this->input->post('push_meta') === '1';
        if ($push) {
            $msg = $this->_push_template_to_meta($savedId);
            $this->session->set_flashdata($msg['ok'] ? 'success' : 'error', $msg['text']);
        } else {
            $this->session->set_flashdata('success', 'Template saved locally.');
        }
        redirect('admin/whatsapp/templates');
    }

    public function template_delete($id = 0) {
        $row = $this->Sk_Whatsapp_cloud_model->get_template((int)$id);
        if (!$row) {
            show_404();
        }
        $settings = $this->Sk_Admin_model->get_settings();
        if (sk_wa_cloud_is_ready($settings) && !empty($row['meta_id'])) {
            $this->load->library('Whatsapp_cloud', $settings);
            $this->whatsapp_cloud->delete_template($row['name']);
        }
        $this->Sk_Whatsapp_cloud_model->delete_template((int)$id);
        $this->session->set_flashdata('success', 'Template deleted.');
        redirect('admin/whatsapp/templates');
    }

    public function template_sync() {
        $settings = $this->Sk_Admin_model->get_settings();
        if (!sk_wa_cloud_is_ready($settings)) {
            $this->session->set_flashdata('error', 'Connect Meta Cloud API first.');
            redirect('admin/whatsapp/templates');
            return;
        }
        $this->load->library('Whatsapp_cloud', $settings);
        $res = $this->whatsapp_cloud->list_templates();
        if (empty($res['success'])) {
            $this->session->set_flashdata('error', $res['message'] ?? 'Could not sync templates.');
            redirect('admin/whatsapp/templates');
            return;
        }
        $n = 0;
        foreach ((array)($res['data']['data'] ?? []) as $remote) {
            if (is_array($remote) && $this->Sk_Whatsapp_cloud_model->upsert_meta_template($remote)) {
                $n++;
            }
        }
        $this->session->set_flashdata('success', 'Synced ' . $n . ' template(s) from Meta.');
        redirect('admin/whatsapp/templates');
    }

    public function campaigns() {
        $data['title'] = 'WhatsApp Campaigns';
        $data['campaigns'] = $this->Sk_Whatsapp_cloud_model->list_campaigns();
        $data['ready'] = sk_wa_cloud_is_ready($this->Sk_Admin_model->get_settings());
        $this->render('whatsapp/campaigns', $data);
    }

    public function campaign_form() {
        $settings = $this->Sk_Admin_model->get_settings();
        $search = trim((string)$this->input->get('q', TRUE));
        $data['title'] = 'New WhatsApp campaign';
        $data['templates'] = $this->Sk_Whatsapp_cloud_model->list_templates();
        $data['customers'] = $this->Sk_Whatsapp_cloud_model->list_customers_with_phone($search, 250);
        $data['customer_count'] = $this->Sk_Whatsapp_cloud_model->count_customers_with_phone();
        $data['search'] = $search;
        $data['ready'] = sk_wa_cloud_is_ready($settings);
        $data['selected_template_id'] = (int)$this->input->get('template_id');
        $this->render('whatsapp/campaign_form', $data);
    }

    public function campaign_save() {
        $name = trim((string)$this->input->post('name', TRUE));
        $templateId = (int)$this->input->post('template_id');
        $audience = (string)$this->input->post('audience', TRUE);
        $tpl = $this->Sk_Whatsapp_cloud_model->get_template($templateId);
        if ($name === '' || !$tpl) {
            $this->session->set_flashdata('error', 'Campaign name and an approved template are required.');
            redirect('admin/whatsapp/campaigns/add');
            return;
        }
        $settings = $this->Sk_Admin_model->get_settings();
        $this->load->helper('sk_whatsapp');
        if ($audience === 'all') {
            $users = $this->Sk_Whatsapp_cloud_model->get_customers_by_ids(
                $this->Sk_Whatsapp_cloud_model->list_customer_ids_with_phone()
            );
        } else {
            $ids = $this->input->post('customer_ids');
            $users = $this->Sk_Whatsapp_cloud_model->get_customers_by_ids(is_array($ids) ? $ids : []);
        }
        $recipients = [];
        $seen = [];
        foreach ($users as $user) {
            $phone = sk_whatsapp_normalize_phone((string)($user['phone'] ?? ''), $settings);
            if (strlen($phone) < 8 || isset($seen[$phone])) {
                continue;
            }
            $seen[$phone] = true;
            $ctx = sk_wa_cloud_load_customer_context($user, $settings);
            $built = sk_wa_cloud_send_components($tpl, $ctx);
            $recipients[] = [
                'user_id'   => (int)$user['id'],
                'phone'     => $phone,
                'name'      => (string)($user['name'] ?? ''),
                'variables' => $built['resolved'],
            ];
        }
        if (!$recipients) {
            $this->session->set_flashdata('error', 'No customers with a valid phone number were selected.');
            redirect('admin/whatsapp/campaigns/add');
            return;
        }
        $id = $this->Sk_Whatsapp_cloud_model->create_campaign($name, $templateId, $recipients);
        $sendNow = (string)$this->input->post('send_now') === '1';
        if ($sendNow) {
            redirect('admin/whatsapp/campaigns/view/' . $id . '?send=1');
            return;
        }
        $this->session->set_flashdata('success', 'Campaign saved with ' . count($recipients) . ' recipient(s).');
        redirect('admin/whatsapp/campaigns/view/' . $id);
    }

    public function campaign_view($id = 0) {
        $campaign = $this->Sk_Whatsapp_cloud_model->get_campaign((int)$id);
        if (!$campaign) {
            show_404();
        }
        $status = trim((string)$this->input->get('status', TRUE));
        $data['title'] = 'Campaign tracking';
        $data['campaign'] = $campaign;
        $data['template'] = $this->Sk_Whatsapp_cloud_model->get_template((int)$campaign['template_id']);
        $data['recipients'] = $this->Sk_Whatsapp_cloud_model->list_recipients((int)$id, 300, 0, $status);
        $data['filter_status'] = $status;
        $data['modules'] = sk_wa_cloud_customer_modules();
        $data['ready'] = sk_wa_cloud_is_ready($this->Sk_Admin_model->get_settings());
        $this->render('whatsapp/campaign_view', $data);
    }

    public function campaign_send($id = 0) {
        $id = (int)$id;
        $campaign = $this->Sk_Whatsapp_cloud_model->get_campaign($id);
        if (!$campaign) {
            return $this->json(['success' => false, 'message' => 'Campaign not found.'], 404);
        }
        $settings = $this->Sk_Admin_model->get_settings();
        if (!sk_wa_cloud_is_ready($settings)) {
            return $this->json(['success' => false, 'message' => 'Connect Meta Cloud API in Settings → WhatsApp Cloud.']);
        }
        $tpl = $this->Sk_Whatsapp_cloud_model->get_template((int)$campaign['template_id']);
        if (!$tpl) {
            return $this->json(['success' => false, 'message' => 'Template is missing.']);
        }
        @set_time_limit(90);
        $this->load->library('Whatsapp_cloud', $settings);
        $this->Sk_Whatsapp_cloud_model->mark_campaign_sending($id);
        $batch = $this->Sk_Whatsapp_cloud_model->next_queued_recipients($id, 20);
        $processed = 0;
        $okN = 0;
        $failN = 0;
        foreach ($batch as $row) {
            $user = !empty($row['user_id']) ? $this->Sk_User_model->get_by_id((int)$row['user_id']) : null;
            $ctx = sk_wa_cloud_load_customer_context($user ?: [
                'name'  => (string)($row['name'] ?? ''),
                'phone' => (string)$row['phone'],
            ], $settings);
            $built = sk_wa_cloud_send_components($tpl, $ctx);
            $result = $this->whatsapp_cloud->send_template(
                (string)$row['phone'],
                $tpl['name'],
                $tpl['language'] ?: 'en',
                $built['components']
            );
            $wamid = '';
            if (!empty($result['data']['messages'][0]['id'])) {
                $wamid = (string)$result['data']['messages'][0]['id'];
            }
            $ok = !empty($result['success']);
            $this->Sk_Whatsapp_cloud_model->update_recipient((int)$row['id'], [
                'status'         => $ok ? 'sent' : 'failed',
                'wamid'          => $wamid ?: null,
                'error_text'     => $ok ? null : ($result['message'] ?? 'Send failed'),
                'variables_json' => json_encode($built['resolved'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'sent_at'        => date('Y-m-d H:i:s'),
            ]);
            $conv = $this->Sk_Whatsapp_cloud_model->find_or_create_conversation((string)$row['phone'], (string)($row['name'] ?? ''));
            $this->Sk_Whatsapp_cloud_model->add_message((int)$conv['id'], [
                'wamid'         => $wamid ?: null,
                'direction'     => 'out',
                'type'          => 'template',
                'body'          => 'Campaign: ' . $campaign['name'],
                'template_name' => $tpl['name'],
                'status'        => $ok ? 'sent' : 'failed',
                'error_text'    => $ok ? null : ($result['message'] ?? 'Send failed'),
                'raw_json'      => json_encode($result['data'] ?? $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $processed++;
            if ($ok) {
                $okN++;
            } else {
                $failN++;
            }
            usleep(150000);
        }
        $this->Sk_Whatsapp_cloud_model->recount_campaign($id);
        $fresh = $this->Sk_Whatsapp_cloud_model->get_campaign($id);
        return $this->json([
            'success'    => true,
            'processed'  => $processed,
            'sent'       => $okN,
            'failed'     => $failN,
            'remaining'  => (int)($fresh['queued'] ?? 0),
            'campaign'   => $fresh,
            'done'       => (int)($fresh['queued'] ?? 0) === 0,
        ]);
    }

    public function campaign_stats($id = 0) {
        $campaign = $this->Sk_Whatsapp_cloud_model->get_campaign((int)$id);
        if (!$campaign) {
            return $this->json(['success' => false, 'message' => 'Campaign not found.'], 404);
        }
        return $this->json([
            'success'    => true,
            'campaign'   => $campaign,
            'recipients' => $this->Sk_Whatsapp_cloud_model->list_recipients((int)$id, 300),
        ]);
    }

    public function template_push($id = 0) {
        $msg = $this->_push_template_to_meta((int)$id);
        $this->session->set_flashdata($msg['ok'] ? 'success' : 'error', $msg['text']);
        redirect('admin/whatsapp/templates');
    }

    private function _push_template_to_meta(int $id): array {
        $row = $this->Sk_Whatsapp_cloud_model->get_template($id);
        if (!$row) {
            return ['ok' => false, 'text' => 'Template not found.'];
        }
        $settings = $this->Sk_Admin_model->get_settings();
        if (!sk_wa_cloud_is_ready($settings)) {
            return ['ok' => false, 'text' => 'Connect Meta Cloud API first.'];
        }
        $this->load->library('Whatsapp_cloud', $settings);
        $components = [];
        if ($row['kind'] === 'image' || $row['kind'] === 'video') {
            $fmt = $row['kind'] === 'video' ? 'VIDEO' : 'IMAGE';
            $header = ['type' => 'HEADER', 'format' => $fmt];
            if (!empty($row['media_url'])) {
                $header['example'] = ['header_handle' => [$row['media_url']]];
            }
            $components[] = $header;
        } elseif (trim((string)$row['header_text']) !== '') {
            $components[] = ['type' => 'HEADER', 'format' => 'TEXT', 'text' => $row['header_text']];
        }
        $components[] = ['type' => 'BODY', 'text' => (string)$row['body_text']];
        if (trim((string)$row['footer_text']) !== '') {
            $components[] = ['type' => 'FOOTER', 'text' => $row['footer_text']];
        }
        $res = $this->whatsapp_cloud->create_template([
            'name'       => $row['name'],
            'language'   => $row['language'],
            'category'   => $row['category'] ?: 'UTILITY',
            'components' => $components,
        ]);
        if (!empty($res['success'])) {
            $this->Sk_Whatsapp_cloud_model->save_template([
                'meta_id'      => (string)($res['data']['id'] ?? $row['meta_id']),
                'status'       => (string)($res['data']['status'] ?? 'PENDING'),
                'meta_payload' => json_encode($res['data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ], $id);
            return ['ok' => true, 'text' => 'Submitted to Meta. Status: ' . ($res['data']['status'] ?? 'PENDING')];
        }
        return ['ok' => false, 'text' => $res['message'] ?? 'Meta rejected the template.'];
    }

    private function _store_upload(string $kind): array {
        if (empty($_FILES['media']['name'])) {
            return ['error' => 'Choose a file.'];
        }
        $dir = sk_wa_cloud_upload_dir();
        $ext = strtolower(pathinfo((string)$_FILES['media']['name'], PATHINFO_EXTENSION));
        $allowed = $kind === 'video'
            ? ['mp4', '3gp', 'mov']
            : ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            return ['error' => 'Allowed: ' . implode(', ', $allowed)];
        }
        $safe = 'wa_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $dir . $safe;
        if (!move_uploaded_file($_FILES['media']['tmp_name'], $dest)) {
            return ['error' => 'Could not save upload.'];
        }
        $mime = (string)(@mime_content_type($dest) ?: ($kind === 'video' ? 'video/mp4' : 'image/jpeg'));
        return [
            'path' => $dest,
            'url'  => sk_wa_cloud_public_url($safe),
            'mime' => $mime,
        ];
    }
}
