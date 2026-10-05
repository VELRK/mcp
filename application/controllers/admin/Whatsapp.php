<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/admin/Sk_Base.php';

class Whatsapp extends Sk_Base {

    public function __construct() {
        parent::__construct();
        $this->load->helper(['sk_whatsapp_cloud', 'sk_meta_business_agent']);
        $this->load->model('Sk_Vendor_meta_agent_model');
        $this->Sk_Vendor_meta_agent_model->ensure_schema();
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
        foreach ($rows as &$row) {
            if (!empty($row['last_at'])) {
                $row['last_at'] = sk_shift_datetime($row['last_at'], 'Asia/Kolkata');
            }
        }
        unset($row);
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
        $msgs = $this->_hydrate_message_media($msgs, $conv);
        foreach ($msgs as &$msg) {
            if (!empty($msg['created_at'])) {
                $msg['created_at'] = sk_shift_datetime($msg['created_at'], 'Asia/Kolkata');
            }
        }
        unset($msg);
        return $this->json([
            'success' => true,
            'conversation' => $conv,
            'messages' => $msgs,
            'thread_owner' => (string)($conv['thread_owner'] ?? 'meta_agent'),
        ]);
    }

    public function start() {
        $phone = sk_wa_cloud_normalize_phone((string)$this->input->post('phone', TRUE));
        $name = trim((string)$this->input->post('name', TRUE));
        if (!preg_match('/^91[6-9]\d{9}$/', $phone)) {
            return $this->json(['success' => false, 'message' => 'Enter a 10-digit Indian mobile number.']);
        }
        $conv = $this->Sk_Whatsapp_cloud_model->find_or_create_conversation($phone, $name);
        return $this->json(['success' => true, 'conversation' => $conv]);
    }

    public function send() {
        $settings = $this->Sk_Admin_model->get_settings();
        $convId = (int)$this->input->post('conversation_id');
        $conv = $this->Sk_Whatsapp_cloud_model->get_conversation($convId);
        if (!$conv) {
            return $this->json(['success' => false, 'message' => 'Conversation not found.']);
        }
        $vid = (int)($conv['vendor_id'] ?? 0);
        if ($vid > 0) {
            $settings['vendor_id'] = $vid;
        }
        $phoneId = trim((string)($conv['phone_number_id'] ?? ''));
        if ($phoneId !== '') {
            $settings['_wa_phone_number_id'] = $phoneId;
        }
        if (!sk_wa_cloud_is_ready($settings, $vid > 0 ? $vid : null)) {
            return $this->json(['success' => false, 'message' => 'Connect Meta Cloud API in Settings → WhatsApp Cloud.']);
        }

        $type = trim((string)$this->input->post('type', TRUE));
        if (!in_array($type, ['text', 'image', 'video', 'audio', 'document', 'template'], true)) {
            $type = 'text';
        }
        $caption = trim((string)$this->input->post('body', FALSE));

        require_once APPPATH . 'libraries/Whatsapp_cloud.php';
        $this->whatsapp_cloud = new Whatsapp_cloud($settings);
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

        // Sending from the app takes thread control from Meta Business Agent.
        if ($ok) {
            $this->Sk_Vendor_meta_agent_model->set_conversation_owner($convId, 'app', 'admin_send');
        }

        return $this->json([
            'success' => $ok,
            'message' => $ok ? 'Sent.' : ($result['message'] ?? 'Send failed.'),
            'thread_owner' => $ok ? 'app' : ($conv['thread_owner'] ?? 'meta_agent'),
        ]);
    }

    /**
     * Take or release Meta Business Agent thread control for a conversation.
     */
    public function thread_control() {
        $convId = (int)$this->input->post('conversation_id');
        $action = strtolower(trim((string)$this->input->post('action', TRUE)));
        if (!in_array($action, ['take', 'release'], true)) {
            return $this->json(['success' => false, 'message' => 'action must be take or release.']);
        }
        $conv = $this->Sk_Whatsapp_cloud_model->get_conversation($convId);
        if (!$conv) {
            return $this->json(['success' => false, 'message' => 'Conversation not found.']);
        }
        $phoneId = trim((string)($conv['phone_number_id'] ?? ''));
        if ($phoneId === '') {
            $settings = $this->Sk_Admin_model->get_settings();
            $cfg = sk_wa_cloud_config($settings, (int)($conv['vendor_id'] ?? 0) ?: null);
            $phoneId = trim((string)($cfg['phone_number_id'] ?? ''));
        }
        if ($phoneId === '') {
            return $this->json(['success' => false, 'message' => 'Missing phone_number_id for this chat.']);
        }
        $settings = $this->Sk_Admin_model->get_settings();
        $res = sk_meta_ba_thread_control($phoneId, (string)$conv['phone'], $action, $settings);
        if (!empty($res['ok'])) {
            $owner = $action === 'release' ? 'meta_agent' : 'app';
            $this->Sk_Vendor_meta_agent_model->set_conversation_owner($convId, $owner, 'thread_control_' . $action);
        }
        return $this->json([
            'success' => !empty($res['ok']),
            'message' => $res['ok'] ? ('Thread ' . $action . ' completed.') : ($res['error'] ?? 'Thread control failed'),
            'thread_owner' => $action === 'release' ? 'meta_agent' : 'app',
            'data' => $res['data'],
        ], !empty($res['ok']) ? 200 : 400);
    }

    public function ai() {
        // Legacy OpenAI/Gemini WhatsApp AI page removed — Meta Business Agent owns replies.
        redirect('admin/meta/agent');
    }

    public function ai_save() {
        redirect('admin/meta/agent');
    }

    public function templates() {
        $vid = $this->_resolve_ops_vendor_id();
        $settings = $this->Sk_Admin_model->get_settings();
        if ($vid > 0) {
            $settings['vendor_id'] = $vid;
        }
        $cfg = sk_wa_cloud_config($settings, $vid > 0 ? $vid : null);
        $sync = $this->_pull_templates_from_meta($settings, $vid);
        $data['title'] = 'WhatsApp Templates';
        $data['templates'] = $this->Sk_Whatsapp_cloud_model->list_templates($vid > 0 ? $vid : null);
        $data['ready'] = sk_wa_cloud_is_ready($settings, $vid > 0 ? $vid : null);
        $data['cfg'] = $cfg;
        $data['vendor_id'] = $vid;
        $data['meta_sync'] = $sync;
        $this->render('whatsapp/templates', $data);
    }

    public function template_form($id = 0) {
        $vid = $this->_resolve_ops_vendor_id();
        $row = $id ? $this->Sk_Whatsapp_cloud_model->get_template((int)$id, $vid > 0 ? $vid : null) : null;
        if ($id && !$row) {
            show_404();
        }
        $settings = $this->Sk_Admin_model->get_settings();
        if ($vid > 0) {
            $settings['vendor_id'] = $vid;
        }
        $data['title'] = $row ? 'Edit template' : 'New template';
        $data['row'] = $row;
        $data['vendor_id'] = $vid;
        $data['ready'] = sk_wa_cloud_is_ready($settings, $vid > 0 ? $vid : null);
        $data['customer_modules'] = sk_wa_cloud_customer_modules();
        $this->render('whatsapp/template_form', $data);
    }

    public function template_save($id = 0) {
        $id = (int)$id;
        $vid = $this->_resolve_ops_vendor_id();
        $name = strtolower(trim((string)$this->input->post('name', TRUE)));
        $name = preg_replace('/[^a-z0-9_]+/', '_', $name);
        $kind = $this->input->post('kind', TRUE);
        if (!in_array($kind, ['text', 'image', 'video'], true)) {
            $kind = 'text';
        }
        if ($name === '') {
            $this->session->set_flashdata('error', 'Template name is required (letters, numbers, underscore).');
            redirect(($id ? 'shopkart/whatsapp/templates/edit/' . $id : 'shopkart/whatsapp/templates/add') . ($vid > 0 ? '?vendor_id=' . $vid : ''));
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
        if ($vid > 0) {
            $payload['vendor_id'] = $vid;
        }
        $existing = $id ? $this->Sk_Whatsapp_cloud_model->get_template($id, $vid > 0 ? $vid : null) : null;
        if ($kind !== 'text') {
            $file = $this->_store_upload($kind);
            if (empty($file['error']) && !empty($file['url'])) {
                $payload['media_url'] = $file['url'];
            } elseif (!$existing || empty($existing['media_url'])) {
                $this->session->set_flashdata('error', $file['error'] ?? 'Upload an image or video for this template.');
                redirect(($id ? 'shopkart/whatsapp/templates/edit/' . $id : 'shopkart/whatsapp/templates/add') . ($vid > 0 ? '?vendor_id=' . $vid : ''));
                return;
            }
        }
        $savedId = $this->Sk_Whatsapp_cloud_model->save_template($payload, $id);
        $push = (string)$this->input->post('push_meta') === '1';
        if ($push) {
            $msg = $this->_push_template_to_meta($savedId, $vid);
            if (!empty($msg['ok'])) {
                $this->session->set_flashdata('success', $msg['text']);
            } else {
                $this->session->set_flashdata('error', 'Template saved as a draft. ' . $msg['text']);
            }
        } else {
            $this->session->set_flashdata('success', 'Template saved locally.');
        }
        $this->_templates_redirect($vid);
    }

    public function template_delete($id = 0) {
        $vid = $this->_resolve_ops_vendor_id();
        $row = $this->Sk_Whatsapp_cloud_model->get_template((int)$id, $vid > 0 ? $vid : null);
        if (!$row) {
            show_404();
        }
        $settings = $this->Sk_Admin_model->get_settings();
        if ($vid > 0) {
            $settings['vendor_id'] = $vid;
        }
        if (sk_wa_cloud_is_ready($settings, $vid > 0 ? $vid : null) && !empty($row['meta_id'])) {
            if (isset($this->whatsapp_cloud)) {
                unset($this->whatsapp_cloud);
            }
            $this->load->library('Whatsapp_cloud', $settings);
            $this->whatsapp_cloud->delete_template($row['name']);
        }
        $this->Sk_Whatsapp_cloud_model->delete_template((int)$id, $vid > 0 ? $vid : null);
        $this->session->set_flashdata('success', 'Template deleted.');
        $this->_templates_redirect($vid);
    }

    public function template_sync() {
        $vid = $this->_resolve_ops_vendor_id();
        $settings = $this->Sk_Admin_model->get_settings();
        if ($vid > 0) {
            $settings['vendor_id'] = $vid;
        }
        $sync = $this->_pull_templates_from_meta($settings, $vid);
        if (empty($sync['ok'])) {
            $this->session->set_flashdata('error', $sync['error'] !== '' ? $sync['error'] : 'Could not sync templates.');
        } else {
            $this->session->set_flashdata('success', 'Synced ' . (int)$sync['count'] . ' template(s) from Meta.');
        }
        $this->_templates_redirect($vid);
    }

    /**
     * Refresh template rows from Meta, then the page reads wa_cloud_templates.
     *
     * @return array{ok:bool,count:int,error:string}
     */
    private function _pull_templates_from_meta(array $settings, int $vid): array {
        if (!sk_wa_cloud_is_ready($settings, $vid > 0 ? $vid : null)) {
            return ['ok' => false, 'count' => 0, 'error' => ''];
        }
        if (isset($this->whatsapp_cloud)) {
            unset($this->whatsapp_cloud);
        }
        $this->load->library('Whatsapp_cloud', $settings);
        $res = $this->whatsapp_cloud->list_templates();
        if (empty($res['success'])) {
            return ['ok' => false, 'count' => 0, 'error' => (string)($res['message'] ?? 'Could not load templates from Meta.')];
        }
        $n = 0;
        foreach ((array)($res['data']['data'] ?? []) as $remote) {
            if (is_array($remote) && $this->Sk_Whatsapp_cloud_model->upsert_meta_template($remote, $vid > 0 ? $vid : null)) {
                $n++;
            }
        }
        return ['ok' => true, 'count' => $n, 'error' => ''];
    }

    public function campaigns() {
        $data['title'] = 'WhatsApp Campaigns';
        $data['campaigns'] = $this->Sk_Whatsapp_cloud_model->list_campaigns();
        $data['ready'] = sk_wa_cloud_is_ready($this->Sk_Admin_model->get_settings());
        $this->render('whatsapp/campaigns', $data);
    }

    public function campaign_form() {
        $vid = $this->_resolve_ops_vendor_id();
        $settings = $this->Sk_Admin_model->get_settings();
        if ($vid > 0) {
            $settings['vendor_id'] = $vid;
        }
        $search = trim((string)$this->input->get('q', TRUE));
        $data['title'] = 'New WhatsApp campaign';
        $data['templates'] = $this->Sk_Whatsapp_cloud_model->list_templates($vid > 0 ? $vid : null);
        $data['customers'] = $this->Sk_Whatsapp_cloud_model->list_customers_with_phone($search, 250);
        $data['customer_count'] = $this->Sk_Whatsapp_cloud_model->count_customers_with_phone();
        $data['search'] = $search;
        $data['ready'] = sk_wa_cloud_is_ready($settings, $vid > 0 ? $vid : null);
        $data['vendor_id'] = $vid;
        $data['selected_template_id'] = (int)$this->input->get('template_id');
        $this->render('whatsapp/campaign_form', $data);
    }

    public function campaign_save() {
        $vid = $this->_resolve_ops_vendor_id();
        $name = trim((string)$this->input->post('name', TRUE));
        $templateId = (int)$this->input->post('template_id');
        $audience = (string)$this->input->post('audience', TRUE);
        $tpl = $this->Sk_Whatsapp_cloud_model->get_template($templateId, $vid > 0 ? $vid : null);
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
        $vid = $this->_resolve_ops_vendor_id();
        $msg = $this->_push_template_to_meta((int)$id, $vid);
        $this->session->set_flashdata($msg['ok'] ? 'success' : 'error', $msg['text']);
        redirect('shopkart/whatsapp/templates' . ($vid > 0 ? '?vendor_id=' . $vid : ''));
    }

    /**
     * Vendor scope for WA ops: query → session ops → logged-in vendor.
     */
    private function _resolve_ops_vendor_id(): int {
        // A logged-in vendor always uses their own numbers. A ?vendor_id= on the URL
        // must not point templates at a different vendor that has no account.
        $own = (int)($this->current_vendor_id() ?? 0);
        if ($own > 0 && !$this->is_super_admin()) {
            return $own;
        }
        $vid = (int)$this->input->get_post('vendor_id');
        if ($vid < 1) {
            $vid = (int)$this->session->userdata('wa_ops_vendor_id');
        }
        if ($vid < 1) {
            $vid = $own;
        }
        if ($vid > 0 && $this->is_super_admin()) {
            $this->session->set_userdata('wa_ops_vendor_id', $vid);
        }
        return $vid;
    }

    private function _templates_redirect(int $vid = 0): void {
        redirect('shopkart/whatsapp/templates' . ($vid > 0 ? '?vendor_id=' . $vid : ''));
    }

    private function _push_template_to_meta(int $id, ?int $vendorId = null): array {
        $vid = $vendorId !== null ? (int)$vendorId : $this->_resolve_ops_vendor_id();
        $row = $this->Sk_Whatsapp_cloud_model->get_template($id, $vid > 0 ? $vid : null);
        if (!$row) {
            return ['ok' => false, 'text' => 'Template not found.'];
        }
        $settings = $this->Sk_Admin_model->get_settings();
        if ($vid > 0) {
            $settings['vendor_id'] = $vid;
        }
        if (!sk_wa_cloud_is_ready($settings, $vid > 0 ? $vid : null)) {
            return ['ok' => false, 'text' => sk_wa_cloud_not_ready_reason($settings, $vid > 0 ? $vid : null)];
        }
        $cfg = sk_wa_cloud_config($settings, $vid > 0 ? $vid : null);
        if (trim((string)($cfg['waba_id'] ?? '')) === '') {
            return ['ok' => false, 'text' => 'WhatsApp Business Account ID is missing for this number.'];
        }
        // Fresh instance so constructor picks vendor_id from $settings.
        if (isset($this->whatsapp_cloud)) {
            unset($this->whatsapp_cloud);
        }
        $this->load->library('Whatsapp_cloud', $settings);
        $components = [];
        $variableMap = $row['variable_map'] ?? '';
        if ($row['kind'] === 'image' || $row['kind'] === 'video') {
            $fmt = $row['kind'] === 'video' ? 'VIDEO' : 'IMAGE';
            $file = sk_wa_cloud_local_media_path((string)($row['media_url'] ?? ''));
            if ($file === '') {
                return ['ok' => false, 'text' => 'Upload a header image or video before sending this template to Meta.'];
            }
            $uploaded = sk_wa_cloud_template_header_handle($file, $cfg);
            if (empty($uploaded['ok'])) {
                return ['ok' => false, 'text' => $uploaded['error'] ?: 'Header file upload failed.'];
            }
            $components[] = [
                'type'    => 'HEADER',
                'format'  => $fmt,
                'example' => ['header_handle' => [$uploaded['handle']]],
            ];
        } elseif (trim((string)$row['header_text']) !== '') {
            $header = ['type' => 'HEADER', 'format' => 'TEXT', 'text' => $row['header_text']];
            $headerSamples = sk_wa_cloud_example_samples((string)$row['header_text'], $variableMap);
            if ($headerSamples) {
                $header['example'] = ['header_text' => $headerSamples];
            }
            $components[] = $header;
        }
        $body = ['type' => 'BODY', 'text' => (string)$row['body_text']];
        $bodySamples = sk_wa_cloud_example_samples((string)$row['body_text'], $variableMap);
        if ($bodySamples) {
            $body['example'] = ['body_text' => [$bodySamples]];
        }
        $components[] = $body;
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
        $allowedMap = [
            'video'    => ['mp4', '3gp', 'mov'],
            'audio'    => ['mp3', 'ogg', 'm4a', 'aac', 'amr', 'opus'],
            'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'],
        ];
        $allowed = $allowedMap[$kind] ?? ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            return ['error' => 'Allowed: ' . implode(', ', $allowed)];
        }
        $safe = 'wa_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $dir . $safe;
        if (!move_uploaded_file($_FILES['media']['tmp_name'], $dest)) {
            return ['error' => 'Could not save upload.'];
        }
        $fallbackMime = [
            'video'    => 'video/mp4',
            'audio'    => 'audio/mpeg',
            'document' => 'application/pdf',
        ];
        $mime = (string)(@mime_content_type($dest) ?: ($fallbackMime[$kind] ?? 'image/jpeg'));
        return [
            'path' => $dest,
            'url'  => sk_wa_cloud_public_url($safe),
            'mime' => $mime,
        ];
    }

    /**
     * Turn stored Meta media ids into local image/video/audio/file URLs for the inbox.
     *
     * @param array<int,array<string,mixed>> $msgs
     * @return array<int,array<string,mixed>>
     */
    private function _hydrate_message_media(array $msgs, array $conv): array {
        $settings = $this->Sk_Admin_model->get_settings();
        $vid = (int)($conv['vendor_id'] ?? 0);
        if ($vid > 0) {
            $settings['vendor_id'] = $vid;
        }
        $phoneId = trim((string)($conv['phone_number_id'] ?? ''));
        if ($phoneId !== '') {
            $settings['_wa_phone_number_id'] = $phoneId;
        }
        $cfg = sk_wa_cloud_config($settings, $vid > 0 ? $vid : null);
        $token = (string)($cfg['access_token'] ?? '');
        $base = (string)($cfg['graph_base'] ?? '');
        $done = 0;
        foreach ($msgs as $i => $m) {
            if ($done >= 8 || $token === '') {
                break;
            }
            $type = (string)($m['type'] ?? '');
            $mediaId = trim((string)($m['media_id'] ?? ''));
            $url = trim((string)($m['media_url'] ?? ''));
            if ($mediaId === '' && ctype_digit($url)) {
                $mediaId = $url;
            }
            $rawType = '';
            if ($mediaId === '' && !empty($m['raw_json'])) {
                $raw = json_decode((string)$m['raw_json'], true);
                if (is_array($raw)) {
                    $rawType = (string)($raw['type'] ?? '');
                    $node = $raw[$rawType] ?? null;
                    if (is_array($node) && !empty($node['id'])) {
                        $mediaId = (string)$node['id'];
                        if (!in_array($type, ['image', 'video', 'audio', 'document', 'sticker'], true)) {
                            $type = $rawType;
                        }
                    }
                }
            }
            if (!in_array($type, ['image', 'video', 'audio', 'document', 'sticker'], true) || $mediaId === '') {
                continue;
            }
            if ($url !== '' && preg_match('#^https?://#i', $url)) {
                continue;
            }
            $saved = sk_wa_cloud_cache_media($mediaId, $token, $base);
            if ($saved === '') {
                continue;
            }
            $this->Sk_Whatsapp_cloud_model->update_message_media((int)$m['id'], $saved, $mediaId, $type);
            $msgs[$i]['media_url'] = $saved;
            $msgs[$i]['media_id'] = $mediaId;
            $msgs[$i]['type'] = $type;
            $done++;
        }
        return $msgs;
    }
}
