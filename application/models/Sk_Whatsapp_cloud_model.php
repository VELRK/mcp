<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sk_Whatsapp_cloud_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->helper('sk_whatsapp_cloud');
        sk_wa_cloud_ensure_schema();
    }

    public function list_templates(): array {
        return $this->db->order_by('updated_at', 'DESC')->get('wa_cloud_templates')->result_array();
    }

    public function get_template(int $id): ?array {
        $row = $this->db->where('id', $id)->get('wa_cloud_templates')->row_array();
        return $row ?: null;
    }

    public function save_template(array $data, int $id = 0): int {
        $now = date('Y-m-d H:i:s');
        $data['updated_at'] = $now;
        if ($id > 0) {
            $this->db->where('id', $id)->update('wa_cloud_templates', $data);
            return $id;
        }
        $data['created_at'] = $now;
        $this->db->insert('wa_cloud_templates', $data);
        return (int)$this->db->insert_id();
    }

    public function delete_template(int $id): bool {
        $this->db->where('id', $id)->delete('wa_cloud_templates');
        return $this->db->affected_rows() > 0;
    }

    public function upsert_meta_template(array $remote): int {
        $name = trim((string)($remote['name'] ?? ''));
        $lang = trim((string)($remote['language'] ?? 'en'));
        if ($name === '') {
            return 0;
        }
        $kind = 'text';
        $body = '';
        $header = '';
        $footer = '';
        foreach ((array)($remote['components'] ?? []) as $c) {
            $type = strtoupper((string)($c['type'] ?? ''));
            if ($type === 'BODY') {
                $body = (string)($c['text'] ?? '');
            } elseif ($type === 'HEADER') {
                $fmt = strtoupper((string)($c['format'] ?? 'TEXT'));
                if ($fmt === 'IMAGE') {
                    $kind = 'image';
                } elseif ($fmt === 'VIDEO') {
                    $kind = 'video';
                } else {
                    $header = (string)($c['text'] ?? '');
                }
            } elseif ($type === 'FOOTER') {
                $footer = (string)($c['text'] ?? '');
            }
        }
        $existing = $this->db->where('name', $name)->where('language', $lang)->get('wa_cloud_templates')->row_array();
        $payload = [
            'name'         => $name,
            'language'     => $lang,
            'category'     => (string)($remote['category'] ?? 'UTILITY'),
            'kind'         => $kind,
            'body_text'    => $body,
            'header_text'  => $header,
            'footer_text'  => $footer,
            'meta_id'      => (string)($remote['id'] ?? ''),
            'status'       => (string)($remote['status'] ?? 'PENDING'),
            'meta_payload' => json_encode($remote, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        return $this->save_template($payload, $existing ? (int)$existing['id'] : 0);
    }

    public function list_conversations(string $search = '', int $limit = 80): array {
        if ($search !== '') {
            $this->db->group_start()->like('phone', $search)->or_like('name', $search)->group_end();
        }
        return $this->db->order_by('last_at', 'DESC', false)
            ->order_by('id', 'DESC')
            ->limit($limit)
            ->get('wa_cloud_conversations')
            ->result_array();
    }

    public function get_conversation(int $id): ?array {
        $row = $this->db->where('id', $id)->get('wa_cloud_conversations')->row_array();
        return $row ?: null;
    }

    public function find_or_create_conversation(string $phone, string $name = ''): array {
        $phone = sk_wa_cloud_normalize_phone($phone);
        $row = $this->db->where('phone', $phone)->get('wa_cloud_conversations')->row_array();
        $now = date('Y-m-d H:i:s');
        if ($row) {
            if ($name !== '' && trim((string)($row['name'] ?? '')) === '') {
                $this->db->where('id', $row['id'])->update('wa_cloud_conversations', [
                    'name'       => $name,
                    'updated_at' => $now,
                ]);
                $row['name'] = $name;
            }
            return $row;
        }
        $this->db->insert('wa_cloud_conversations', [
            'phone'      => $phone,
            'name'       => $name,
            'unread'     => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $this->db->where('id', (int)$this->db->insert_id())->get('wa_cloud_conversations')->row_array();
    }

    public function add_message(int $conversationId, array $data): int {
        $now = date('Y-m-d H:i:s');
        $data['conversation_id'] = $conversationId;
        $data['created_at'] = $data['created_at'] ?? $now;
        $this->db->insert('wa_cloud_messages', $data);
        $id = (int)$this->db->insert_id();

        $preview = trim((string)($data['body'] ?? ''));
        if ($preview === '') {
            $preview = ucfirst((string)($data['type'] ?? 'message'));
        }
        $preview = mb_substr($preview, 0, 180);
        $dir = (string)($data['direction'] ?? 'out');
        $update = [
            'last_message'   => $preview,
            'last_direction' => $dir,
            'last_at'        => $now,
            'updated_at'     => $now,
        ];
        if ($dir === 'in') {
            $this->db->set('unread', 'unread+1', false);
        }
        $this->db->where('id', $conversationId)->update('wa_cloud_conversations', $update);
        return $id;
    }

    public function list_messages(int $conversationId, int $afterId = 0): array {
        if ($afterId > 0) {
            $this->db->where('id >', $afterId);
        }
        return $this->db->where('conversation_id', $conversationId)
            ->order_by('id', 'ASC')
            ->limit(200)
            ->get('wa_cloud_messages')
            ->result_array();
    }

    public function mark_read(int $conversationId): void {
        $this->db->where('id', $conversationId)->update('wa_cloud_conversations', [
            'unread'     => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function find_by_wamid(string $wamid): ?array {
        if ($wamid === '') {
            return null;
        }
        $row = $this->db->where('wamid', $wamid)->get('wa_cloud_messages')->row_array();
        return $row ?: null;
    }

    public function update_message_status(string $wamid, string $status, string $error = ''): void {
        if ($wamid === '') {
            return;
        }
        $upd = ['status' => $status];
        if ($error !== '') {
            $upd['error_text'] = $error;
        }
        $this->db->where('wamid', $wamid)->update('wa_cloud_messages', $upd);
        $this->update_campaign_recipient_by_wamid($wamid, $status, $error);
    }

    public function list_campaigns(): array {
        if (!$this->db->table_exists('wa_cloud_campaigns')) {
            return [];
        }
        return $this->db->select('c.*, t.name AS template_name, t.language AS template_language, t.status AS template_status')
            ->from('wa_cloud_campaigns c')
            ->join('wa_cloud_templates t', 't.id = c.template_id', 'left')
            ->order_by('c.id', 'DESC')
            ->get()
            ->result_array();
    }

    public function get_campaign(int $id): ?array {
        if ($id < 1 || !$this->db->table_exists('wa_cloud_campaigns')) {
            return null;
        }
        $row = $this->db->select('c.*, t.name AS template_name, t.language AS template_language, t.kind AS template_kind, t.body_text, t.variable_map, t.status AS template_status')
            ->from('wa_cloud_campaigns c')
            ->join('wa_cloud_templates t', 't.id = c.template_id', 'left')
            ->where('c.id', $id)
            ->get()
            ->row_array();
        return $row ?: null;
    }

    public function create_campaign(string $name, int $templateId, array $recipients): int {
        $now = date('Y-m-d H:i:s');
        $total = count($recipients);
        $this->db->insert('wa_cloud_campaigns', [
            'name'        => $name,
            'template_id' => $templateId,
            'status'      => 'draft',
            'total'       => $total,
            'queued'      => $total,
            'sent'        => 0,
            'delivered'   => 0,
            'read_count'  => 0,
            'failed'      => 0,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
        $id = (int)$this->db->insert_id();
        foreach ($recipients as $r) {
            $this->db->insert('wa_cloud_campaign_recipients', [
                'campaign_id'    => $id,
                'user_id'        => (int)($r['user_id'] ?? 0) ?: null,
                'phone'          => (string)($r['phone'] ?? ''),
                'name'           => (string)($r['name'] ?? ''),
                'status'         => 'queued',
                'variables_json' => isset($r['variables']) ? json_encode($r['variables'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'updated_at'     => $now,
            ]);
        }
        return $id;
    }

    public function list_recipients(int $campaignId, int $limit = 200, int $offset = 0, string $status = ''): array {
        if ($status !== '') {
            $this->db->where('status', $status);
        }
        return $this->db->where('campaign_id', $campaignId)
            ->order_by('id', 'ASC')
            ->limit($limit, $offset)
            ->get('wa_cloud_campaign_recipients')
            ->result_array();
    }

    public function count_recipients(int $campaignId, string $status = ''): int {
        if ($status !== '') {
            $this->db->where('status', $status);
        }
        return (int)$this->db->where('campaign_id', $campaignId)->count_all_results('wa_cloud_campaign_recipients');
    }

    public function next_queued_recipients(int $campaignId, int $limit = 25): array {
        return $this->db->where('campaign_id', $campaignId)
            ->where('status', 'queued')
            ->order_by('id', 'ASC')
            ->limit($limit)
            ->get('wa_cloud_campaign_recipients')
            ->result_array();
    }

    public function update_recipient(int $id, array $data): void {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', $id)->update('wa_cloud_campaign_recipients', $data);
    }

    public function mark_campaign_sending(int $id): void {
        $now = date('Y-m-d H:i:s');
        $row = $this->get_campaign($id);
        $upd = ['status' => 'sending', 'updated_at' => $now];
        if ($row && empty($row['started_at'])) {
            $upd['started_at'] = $now;
        }
        $this->db->where('id', $id)->update('wa_cloud_campaigns', $upd);
    }

    public function update_campaign_recipient_by_wamid(string $wamid, string $status, string $error = ''): void {
        if ($wamid === '' || !$this->db->table_exists('wa_cloud_campaign_recipients')) {
            return;
        }
        $row = $this->db->where('wamid', $wamid)->get('wa_cloud_campaign_recipients')->row_array();
        if (!$row) {
            return;
        }
        $allowed = ['sent', 'delivered', 'read', 'failed'];
        if (!in_array($status, $allowed, true)) {
            return;
        }
        $upd = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')];
        if ($error !== '') {
            $upd['error_text'] = $error;
        }
        $this->db->where('id', (int)$row['id'])->update('wa_cloud_campaign_recipients', $upd);
        $this->recount_campaign((int)$row['campaign_id']);
    }

    public function recount_campaign(int $id): void {
        if ($id < 1 || !$this->db->table_exists('wa_cloud_campaign_recipients')) {
            return;
        }
        $rows = $this->db->select('status, COUNT(*) AS n', false)
            ->where('campaign_id', $id)
            ->group_by('status')
            ->get('wa_cloud_campaign_recipients')
            ->result_array();
        $counts = [
            'queued'    => 0,
            'sent'      => 0,
            'delivered' => 0,
            'read'      => 0,
            'failed'    => 0,
        ];
        $total = 0;
        foreach ($rows as $r) {
            $st = (string)($r['status'] ?? '');
            $n = (int)($r['n'] ?? 0);
            $total += $n;
            if (isset($counts[$st])) {
                $counts[$st] = $n;
            }
        }
        $sentOrBetter = $counts['sent'] + $counts['delivered'] + $counts['read'];
        $deliveredOrBetter = $counts['delivered'] + $counts['read'];
        $now = date('Y-m-d H:i:s');
        $status = 'sending';
        $finished = null;
        if ($counts['queued'] === 0) {
            $status = $counts['failed'] === $total && $total > 0 ? 'failed' : 'sent';
            $finished = $now;
        }
        $existing = $this->db->select('status, started_at, finished_at')->where('id', $id)->get('wa_cloud_campaigns')->row_array();
        if ($existing && in_array((string)$existing['status'], ['draft', 'cancelled'], true) && $counts['queued'] === $total) {
            $status = (string)$existing['status'];
            $finished = null;
        } elseif ($counts['queued'] === 0 && !empty($existing['finished_at'])) {
            $finished = $existing['finished_at'];
        }
        $this->db->where('id', $id)->update('wa_cloud_campaigns', [
            'total'       => $total,
            'queued'      => $counts['queued'],
            'sent'        => $sentOrBetter,
            'delivered'   => $deliveredOrBetter,
            'read_count'  => $counts['read'],
            'failed'      => $counts['failed'],
            'status'      => $status,
            'updated_at'  => $now,
            'finished_at' => $finished,
        ]);
    }

    public function list_customers_with_phone(string $search = '', int $limit = 250): array {
        $this->_apply_customer_phone_scope($search);
        return $this->db->select('id, name, email, phone')
            ->order_by('name', 'ASC')
            ->limit($limit)
            ->get('users')
            ->result_array();
    }

    public function count_customers_with_phone(string $search = ''): int {
        $this->_apply_customer_phone_scope($search);
        return (int)$this->db->count_all_results('users');
    }

    public function list_customer_ids_with_phone(): array {
        $this->_apply_customer_phone_scope('');
        $rows = $this->db->select('id')->get('users')->result_array();
        return array_map(static function ($r) {
            return (int)$r['id'];
        }, $rows);
    }

    public function get_customers_by_ids(array $ids): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $this->_apply_customer_phone_scope('');
        return $this->db->select('id, name, email, phone')
            ->where_in('id', $ids)
            ->get('users')
            ->result_array();
    }

    private function _apply_customer_phone_scope(string $search): void {
        $this->db->where('phone IS NOT NULL', null, false);
        $this->db->where('phone !=', '');
        if ($this->db->field_exists('deleted_at', 'users')) {
            $this->db->where('deleted_at IS NULL', null, false);
        }
        $search = trim($search);
        if ($search !== '') {
            $this->db->group_start()
                ->like('name', $search)
                ->or_like('email', $search)
                ->or_like('phone', $search)
                ->group_end();
        }
    }
}
