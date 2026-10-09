<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Scheduled jobs (CLI or HTTP with key).
 *
 * Linux crontab (every day 06:00):
 *   0 6 * * * cd /path/to/mcp && php index.php cron saas_daily_bills
 *
 * Windows Task Scheduler:
 *   Program: C:\xampp\php\php.exe
 *   Arguments: C:\xampp\htdocs\mcp\index.php cron saas_daily_bills
 *
 * HTTP (optional):
 *   GET /cron/saas_daily_bills?key={saas_cron_key}
 */
class Cron extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->model(['Sk_Admin_model', 'Sk_Saas_Billing_model']);
    }

    /**
     * Morning job: optional WhatsApp sync for yesterday + daily bill rollup.
     * CLI: php index.php cron saas_daily_bills [YYYY-MM-DD] [0|1 sync_wa]
     */
    public function saas_daily_bills($date = null, $syncWa = '1') {
        if (!$this->_authorized()) {
            $this->_out(['ok' => false, 'message' => 'Unauthorized.'], 403);
            return;
        }

        if ($date === null || $date === '' || $date === 'yesterday') {
            $date = null; // model uses yesterday
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date)) {
            // HTTP query override
            $q = $this->input->get('date', TRUE);
            $date = (is_string($q) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $q)) ? $q : null;
        }

        $sync = !in_array((string)$syncWa, ['0', 'false', 'no'], true);
        if ($this->input->get('sync_wa') !== null) {
            $sync = !in_array((string)$this->input->get('sync_wa'), ['0', 'false', 'no'], true);
        }

        $result = $this->Sk_Saas_Billing_model->run_morning_job($date, $sync);
        $this->_out($result, !empty($result['ok']) ? 200 : 500);
    }

    /**
     * Delete rejected shop order templates and submit the current Meta-safe copy.
     * CLI: php index.php cron recreate_order_templates [vendor_id]
     */
    public function recreate_order_templates($vendorId = 0) {
        if (!$this->_authorized()) {
            $this->_out(['ok' => false, 'message' => 'Unauthorized.'], 403);
            return;
        }
        $this->load->helper('sk_whatsapp_cloud');
        $vendorId = (int)$vendorId;
        $ids = [];
        if ($vendorId > 0) {
            $ids[] = $vendorId;
        } elseif ($this->db->table_exists('vendors')) {
            foreach ($this->db->select('id')->get('vendors')->result_array() as $row) {
                $ids[] = (int)$row['id'];
            }
        }
        $results = [];
        foreach ($ids as $id) {
            $seed = sk_wa_ecomm_seed_vendor($id, true);
            $results[] = [
                'vendor_id' => $id,
                'created'   => (int)$seed['created'],
                'pushed'    => (int)$seed['pushed'],
                'errors'    => $seed['errors'],
            ];
        }
        $this->_out(['ok' => true, 'results' => $results]);
    }

    protected function _authorized(): bool {
        if (is_cli()) {
            return true;
        }
        $settings = $this->Sk_Admin_model->get_settings();
        $key = trim((string)($settings['saas_cron_key'] ?? ''));
        if ($key === '') {
            return false;
        }
        $provided = trim((string)$this->input->get_request_header('X-Cron-Key', true));
        if ($provided === '') {
            $provided = trim((string)$this->input->get('key', TRUE));
        }
        return $provided !== '' && hash_equals($key, $provided);
    }

    protected function _out(array $payload, int $status = 200): void {
        if (is_cli()) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            return;
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
    }
}
