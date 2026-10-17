<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/admin/Sk_Base.php';

/**
 * WhatsApp usage and ShopKart service estimates.
 * Amounts come from saas_client_amounts. They are not Meta invoices and do not include ads.
 */
class Whatsapp_billing extends Sk_Base {

    public function index() {
        $range = $this->_range();
        $vendorId = $this->_scoped_vendor_id();
        $this->load->helper('sk_whatsapp_cloud');
        $this->load->model('Sk_Saas_Billing_model');
        sk_wa_cloud_ensure_schema();
        $this->Sk_Saas_Billing_model->ensure_schema();

        $unit = $this->Sk_Saas_Billing_model->resolve_unit_amount('whatsapp_ai', $vendorId > 0 ? $vendorId : null);
        $currency = 'INR';
        $amountRow = $this->db->where('request_type', 'whatsapp_ai')->where('status', 1)->order_by('vendor_id', 'ASC')->get('saas_client_amounts')->row_array();
        if ($amountRow && !empty($amountRow['currency'])) {
            $currency = (string)$amountRow['currency'];
        }

        $data = [
            'title'        => 'WhatsApp billing',
            'range'        => $range,
            'vendor_id'    => $vendorId,
            'is_super'     => $this->is_super_admin(),
            'vendors'      => $this->is_super_admin() ? $this->_vendor_options() : [],
            'unit_amount'  => $unit,
            'currency'     => $currency,
            'summary'      => $this->_summary($range, $vendorId, $unit),
            'monthly'      => $this->_monthly($range, $vendorId, $unit),
            'templates'    => $this->_templates($range, $vendorId, $unit),
            'campaigns'    => $this->_campaigns($range, $vendorId, $unit),
            'messages'     => [],
            'total'        => 0,
            'page'         => max(1, (int)$this->input->get('page')),
            'limit'        => 25,
            'ads_connected'=> false,
            'meta_invoice' => false,
        ];
        $data['offset'] = ($data['page'] - 1) * $data['limit'];
        $listed = $this->_messages($range, $vendorId, $data['limit'], $data['offset']);
        $data['messages'] = $listed['rows'];
        $data['total'] = $listed['total'];
        $data['meta_report'] = $this->_meta_report($range, $vendorId);
        $this->render('whatsapp/billing', $data);
    }

    public function save_account() {
        $vendorId = $this->_scoped_vendor_id();
        if ($this->is_super_admin()) {
            $posted = (int)$this->input->post('vendor_id');
            if ($posted > 0) {
                $vendorId = $posted;
            }
        }
        $accountId = trim((string)$this->input->post('ba_account_id'));
        $this->load->model(['Sk_Vendor_whatsapp_account_model', 'Sk_Admin_model']);
        $this->load->helper('sk_meta_business_agent');
        $settings = $this->Sk_Admin_model->get_settings();
        $auto = (string)$this->input->post('auto_detect') === '1' || $accountId === '';
        if ($auto && $vendorId > 0) {
            $found = sk_meta_ba_ensure_billable_account_id($vendorId, true, $settings);
            $this->session->set_flashdata(!empty($found['ok']) ? 'success' : 'error', !empty($found['ok'])
                ? ('Business Agent account ID auto-saved: ' . $found['account_id'] . ' (' . ($found['source'] ?? 'probe') . ').')
                : ((string)($found['error'] ?? 'Auto-detect failed.')));
        } else {
            $ok = $vendorId > 0 && $this->Sk_Vendor_whatsapp_account_model->set_ba_account_id($vendorId, $accountId);
            $this->session->set_flashdata($ok ? 'success' : 'error', $ok
                ? 'Meta Business Agent account ID saved.'
                : 'Choose a vendor that already has a WhatsApp number, then save the account ID.');
        }
        $qs = $vendorId > 0 ? ('?vendor_id=' . $vendorId) : '';
        $back = $this->input->post('return') === 'tokens' ? 'admin/whatsapp/tokens' : 'admin/whatsapp/billing';
        redirect($back . $qs);
    }

    /** Force Meta API probe + store Business Agent billable account ID. */
    public function sync_account() {
        $vendorId = $this->_scoped_vendor_id();
        if ($this->is_super_admin()) {
            $posted = (int)($this->input->post('vendor_id') ?: $this->input->get('vendor_id'));
            if ($posted > 0) {
                $vendorId = $posted;
            }
        }
        $this->load->helper('sk_meta_business_agent');
        $this->load->model('Sk_Admin_model');
        $settings = $this->Sk_Admin_model->get_settings();
        $found = $vendorId > 0
            ? sk_meta_ba_ensure_billable_account_id($vendorId, true, $settings)
            : ['ok' => false, 'error' => 'Choose a vendor first.'];
        $this->session->set_flashdata(!empty($found['ok']) ? 'success' : 'error', !empty($found['ok'])
            ? ('Business Agent account ID auto-saved: ' . $found['account_id'] . ' (' . ($found['source'] ?? 'probe') . ').')
            : ((string)($found['error'] ?? 'Auto-detect failed.')));
        $qs = $vendorId > 0 ? ('?vendor_id=' . $vendorId) : '';
        $back = $this->input->get_post('return') === 'tokens' ? 'admin/whatsapp/tokens' : 'admin/whatsapp/billing';
        redirect($back . $qs);
    }

    /**
     * Meta Business Agent token meter: $2 USD per 1,000,000 tokens.
     */
    public function tokens() {
        $range = $this->_range();
        $vendorId = $this->_scoped_vendor_id();
        $report = $this->_meta_report($range, $vendorId);
        $tokens = (int)($report['usage_totals']['billable_tokens'] ?? 0);
        $rate = 2.0;
        $days = [];
        foreach ((array)($report['usage'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $day = !empty($row['start']) ? date('Y-m-d', (int)$row['start']) : '—';
            if (!isset($days[$day])) {
                $days[$day] = ['day' => $day, 'tokens' => 0, 'messages' => 0, 'cost' => 0.0];
            }
            $days[$day]['tokens'] += (int)($row['billable_tokens'] ?? 0);
            $days[$day]['messages'] += (int)($row['billable_messages'] ?? 0);
            $days[$day]['cost'] += (float)($row['cost'] ?? 0);
        }
        ksort($days);
        $this->render('whatsapp/tokens', [
            'title' => 'Token usage',
            'range' => $range,
            'vendor_id' => $vendorId,
            'is_super' => $this->is_super_admin(),
            'vendors' => $this->is_super_admin() ? $this->_vendor_options() : [],
            'meta_report' => $report,
            'token_rate' => $rate,
            'token_estimate' => round($tokens / 1000000 * $rate, 4),
            'token_days' => array_values($days),
        ]);
    }

    public function export_csv() {
        $range = $this->_range();
        $vendorId = $this->_scoped_vendor_id();
        $this->load->helper('sk_whatsapp_cloud');
        sk_wa_cloud_ensure_schema();
        $listed = $this->_messages($range, $vendorId, 5000, 0);
        $filename = 'whatsapp-usage-' . $range['from'] . '-to-' . $range['to'] . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['created_at', 'vendor_id', 'direction', 'status', 'template', 'wamid', 'phone_masked', 'cost_source']);
        foreach ($listed['rows'] as $row) {
            fputcsv($out, [
                $row['created_at'],
                $row['vendor_id'],
                $row['direction'],
                $row['status'],
                $row['template_name'],
                $row['wamid'],
                $row['phone_masked'],
                'shopkart_estimate_not_meta_invoice',
            ]);
        }
        fclose($out);
        exit;
    }

    /** Vendor users are locked to their session vendor. Admins may filter. */
    protected function _scoped_vendor_id(): int {
        $own = $this->current_vendor_id();
        if ($own) {
            return (int)$own;
        }
        if (!$this->is_super_admin()) {
            return 0;
        }
        return max(0, (int)$this->input->get('vendor_id'));
    }

    protected function _range(): array {
        $preset = trim((string)$this->input->get('preset', TRUE));
        $from = trim((string)$this->input->get('from', TRUE));
        $to = trim((string)$this->input->get('to', TRUE));
        $today = date('Y-m-d');
        if (!in_array($preset, ['today', 'yesterday', '7d', '30d', 'month', 'prev_month', 'custom'], true)) {
            $preset = 'month';
        }
        if ($preset === 'today') {
            $from = $to = $today;
        } elseif ($preset === 'yesterday') {
            $from = $to = date('Y-m-d', strtotime('-1 day'));
        } elseif ($preset === '7d') {
            $from = date('Y-m-d', strtotime('-6 days'));
            $to = $today;
        } elseif ($preset === '30d') {
            $from = date('Y-m-d', strtotime('-29 days'));
            $to = $today;
        } elseif ($preset === 'prev_month') {
            $from = date('Y-m-01', strtotime('first day of last month'));
            $to = date('Y-m-t', strtotime('last day of last month'));
        } elseif ($preset === 'custom'
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)
            && $from <= $to) {
            // keep posted dates
        } else {
            $preset = 'month';
            $from = date('Y-m-01');
            $to = $today;
        }
        return ['preset' => $preset, 'from' => $from, 'to' => $to];
    }

    protected function _apply_message_filters(array $range, int $vendorId): void {
        $this->db->where('m.created_at >=', $range['from'] . ' 00:00:00');
        $this->db->where('m.created_at <=', $range['to'] . ' 23:59:59');
        if ($vendorId > 0) {
            $this->db->where('m.vendor_id', $vendorId);
        } elseif (!$this->is_super_admin()) {
            $this->db->where('m.vendor_id', 0);
        }
    }

    protected function _meta_report(array $range, int $vendorId): array {
        $report = [
            'ready' => false,
            'error' => $vendorId > 0 ? '' : 'Choose one vendor to load the Meta Business Agent report.',
            'phone_number_id' => '',
            'ba_account_id' => '',
            'usage' => [],
            'usage_totals' => ['billable_messages' => 0, 'billable_tokens' => 0, 'cost' => 0.0],
            'conversations' => ['ai_threads' => null, 'ai_handoffs' => null],
            'tools' => [],
            'notes' => [],
        ];
        if ($vendorId < 1) {
            return $report;
        }
        $this->load->model(['Sk_Vendor_whatsapp_account_model', 'Sk_Admin_model']);
        $this->load->helper('sk_meta_business_agent');
        $settings = $this->Sk_Admin_model->get_settings();
        $account = $this->Sk_Vendor_whatsapp_account_model->resolve_for_vendor($vendorId);
        if (!$account || trim((string)($account['phone_number_id'] ?? '')) === '') {
            $report['error'] = 'This vendor has no WhatsApp phone number yet.';
            return $report;
        }
        $report['phone_number_id'] = (string)$account['phone_number_id'];
        $report['ba_account_id'] = (string)($account['ba_account_id'] ?? '');
        $report['ba_account_source'] = '';
        // Auto-discover + persist per vendor using Meta Agent system token / WA token.
        if (trim($report['ba_account_id']) === '') {
            $ensured = sk_meta_ba_ensure_billable_account_id($vendorId, false, $settings);
            if (!empty($ensured['ok']) && trim((string)($ensured['account_id'] ?? '')) !== '') {
                $report['ba_account_id'] = (string)$ensured['account_id'];
                $report['ba_account_source'] = (string)($ensured['source'] ?? 'probe');
                if (!empty($ensured['saved'])) {
                    $report['notes'][] = 'Business Agent account ID was detected via API and saved automatically.';
                }
            } elseif (!empty($ensured['error'])) {
                $report['notes'][] = (string)$ensured['error'];
            }
        }
        $live = sk_meta_ba_vendor_insights(
            $report['phone_number_id'],
            $report['ba_account_id'],
            $range['from'],
            $range['to'],
            $settings
        );
        $report['ready'] = !empty($live['ok']);
        $report['usage'] = $live['usage'] ?? [];
        $report['usage_totals'] = $live['usage_totals'] ?? $report['usage_totals'];
        $report['conversations'] = $live['conversations'] ?? $report['conversations'];
        $report['tools'] = $live['tools'] ?? [];
        $report['notes'] = array_values(array_unique(array_merge($report['notes'], $live['notes'] ?? [])));
        $report['error'] = (string)($live['error'] ?? '');
        return $report;
    }

    protected function _summary(array $range, int $vendorId, float $unit): array {
        if (!$this->db->table_exists('wa_cloud_messages')) {
            return $this->_empty_summary();
        }
        $this->db->from('wa_cloud_messages m');
        $this->_apply_message_filters($range, $vendorId);
        $rows = $this->db->select('m.direction, m.status, COUNT(*) AS cnt', false)
            ->group_by('m.direction, m.status')
            ->get()->result_array();
        $summary = $this->_empty_summary();
        foreach ($rows as $row) {
            $count = (int)$row['cnt'];
            $summary['submitted'] += $count;
            $direction = (string)$row['direction'];
            $status = (string)$row['status'];
            if ($direction === 'in') {
                $summary['inbound'] += $count;
                continue;
            }
            $summary['outbound'] += $count;
            if ($status === 'failed') {
                $summary['failed'] += $count;
            } elseif ($status === 'read') {
                $summary['read'] += $count;
                $summary['delivered'] += $count;
                $summary['sent'] += $count;
            } elseif ($status === 'delivered') {
                $summary['delivered'] += $count;
                $summary['sent'] += $count;
            } elseif (in_array($status, ['sent', 'accepted'], true)) {
                $summary['sent'] += $count;
            }
        }
        // ShopKart bills outbound messages that were not failed. This is an estimate, not a Meta charge.
        $summary['estimated'] = round($summary['outbound'] > $summary['failed']
            ? ($summary['outbound'] - $summary['failed']) * $unit
            : 0, 4);
        $summary['actual_meta'] = null;
        $summary['ad_spend'] = null;
        return $summary;
    }

    protected function _empty_summary(): array {
        return [
            'submitted' => 0, 'inbound' => 0, 'outbound' => 0, 'sent' => 0,
            'delivered' => 0, 'read' => 0, 'failed' => 0, 'estimated' => 0,
            'actual_meta' => null, 'ad_spend' => null,
        ];
    }

    protected function _monthly(array $range, int $vendorId, float $unit): array {
        if (!$this->db->table_exists('wa_cloud_messages')) {
            return [];
        }
        $this->db->from('wa_cloud_messages m');
        $this->_apply_message_filters($range, $vendorId);
        $this->db->where('m.direction', 'out');
        $rows = $this->db->select("DATE_FORMAT(m.created_at, '%Y-%m') AS bill_month, m.status, COUNT(*) AS cnt", false)
            ->group_by("DATE_FORMAT(m.created_at, '%Y-%m'), m.status", false)
            ->order_by('bill_month', 'DESC')
            ->get()->result_array();
        $byMonth = [];
        foreach ($rows as $row) {
            $month = (string)$row['bill_month'];
            if (!isset($byMonth[$month])) {
                $byMonth[$month] = ['month' => $month, 'outbound' => 0, 'failed' => 0, 'estimated' => 0.0, 'actual_meta' => null, 'ad_spend' => null];
            }
            $count = (int)$row['cnt'];
            $byMonth[$month]['outbound'] += $count;
            if ((string)$row['status'] === 'failed') {
                $byMonth[$month]['failed'] += $count;
            }
        }
        foreach ($byMonth as &$monthRow) {
            $billable = max(0, $monthRow['outbound'] - $monthRow['failed']);
            $monthRow['estimated'] = round($billable * $unit, 4);
        }
        unset($monthRow);
        return array_values($byMonth);
    }

    protected function _templates(array $range, int $vendorId, float $unit): array {
        if (!$this->db->table_exists('wa_cloud_messages')) {
            return [];
        }
        $this->db->from('wa_cloud_messages m');
        $this->_apply_message_filters($range, $vendorId);
        $this->db->where('m.direction', 'out');
        $this->db->where('m.template_name IS NOT NULL', null, false);
        $this->db->where('m.template_name !=', '');
        $rows = $this->db->select('m.template_name, m.status, COUNT(*) AS cnt', false)
            ->group_by('m.template_name, m.status')
            ->get()->result_array();
        $byName = [];
        foreach ($rows as $row) {
            $name = (string)$row['template_name'];
            if (!isset($byName[$name])) {
                $byName[$name] = ['name' => $name, 'submitted' => 0, 'failed' => 0, 'estimated' => 0.0];
            }
            $count = (int)$row['cnt'];
            $byName[$name]['submitted'] += $count;
            if ((string)$row['status'] === 'failed') {
                $byName[$name]['failed'] += $count;
            }
        }
        foreach ($byName as &$tpl) {
            $tpl['estimated'] = round(max(0, $tpl['submitted'] - $tpl['failed']) * $unit, 4);
        }
        unset($tpl);
        usort($byName, static function ($a, $b) {
            return $b['submitted'] <=> $a['submitted'];
        });
        return array_slice($byName, 0, 20);
    }

    protected function _campaigns(array $range, int $vendorId, float $unit): array {
        if (!$this->db->table_exists('wa_cloud_campaigns') || !$this->db->table_exists('wa_cloud_templates')) {
            return [];
        }
        $this->db->select('c.id, c.name, c.status, c.sent, c.delivered, c.read_count, c.failed, c.created_at, t.name AS template_name, t.vendor_id')
            ->from('wa_cloud_campaigns c')
            ->join('wa_cloud_templates t', 't.id = c.template_id', 'left')
            ->where('c.created_at >=', $range['from'] . ' 00:00:00')
            ->where('c.created_at <=', $range['to'] . ' 23:59:59');
        if ($vendorId > 0) {
            $this->db->where('t.vendor_id', $vendorId);
        } elseif (!$this->is_super_admin()) {
            $this->db->where('t.vendor_id', 0);
        }
        $rows = $this->db->order_by('c.id', 'DESC')->limit(50)->get()->result_array();
        foreach ($rows as &$row) {
            $sent = (int)($row['sent'] ?? 0);
            $failed = (int)($row['failed'] ?? 0);
            $row['estimated'] = round(max(0, $sent - $failed) * $unit, 4);
            $row['ad_spend'] = null;
            $row['orders'] = null;
        }
        unset($row);
        return $rows;
    }

    protected function _messages(array $range, int $vendorId, int $limit, int $offset): array {
        if (!$this->db->table_exists('wa_cloud_messages')) {
            return ['rows' => [], 'total' => 0];
        }
        $this->db->from('wa_cloud_messages m');
        $this->_apply_message_filters($range, $vendorId);
        $total = (int)$this->db->count_all_results();

        $this->db->select('m.id, m.created_at, m.vendor_id, m.wamid, m.direction, m.template_name, m.status, m.phone_number_id, c.phone')
            ->from('wa_cloud_messages m')
            ->join('wa_cloud_conversations c', 'c.id = m.conversation_id', 'left');
        $this->_apply_message_filters($range, $vendorId);
        $rows = $this->db->order_by('m.id', 'DESC')->limit($limit, $offset)->get()->result_array();
        foreach ($rows as &$row) {
            $row['phone_masked'] = $this->_mask_phone((string)($row['phone'] ?? ''));
            $row['billable_label'] = ((string)$row['direction'] === 'out' && (string)$row['status'] !== 'failed')
                ? 'estimated'
                : 'not_billable_here';
            unset($row['phone']);
        }
        unset($row);
        return ['rows' => $rows, 'total' => $total];
    }

    protected function _mask_phone(string $phone): string {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return '';
        }
        $tail = substr($digits, -4);
        return str_repeat('*', max(0, strlen($digits) - 4)) . $tail;
    }

    protected function _vendor_options(): array {
        if (!$this->db->table_exists('vendors')) {
            return [];
        }
        return $this->db->select('id, business_name')->order_by('business_name', 'ASC')->limit(500)->get('vendors')->result_array();
    }
}
