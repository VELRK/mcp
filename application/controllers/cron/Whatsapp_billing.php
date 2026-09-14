<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Whatsapp_billing extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->model('Sk_Vendor_whatsapp_account_model');
        $this->load->model('Sk_Vendor_whatsapp_message_model');
    }

    public function aggregate_daily($date = null) {
        if (!is_cli()) {
            echo "This endpoint is intended for CLI only.\n";
            return;
        }

        $date = $date ?: date('Y-m-d', strtotime('-1 day'));
        $start = $date . ' 00:00:00';
        $end = $date . ' 23:59:59';

        $this->Sk_Vendor_whatsapp_message_model->ensure_schema();

        $sql = "SELECT account_id, vendor_id, COUNT(*) AS messages_sent, COALESCE(SUM(cost),0) AS total_spent, MAX(currency) AS currency
                FROM vendor_whatsapp_messages
                WHERE sent_at >= ? AND sent_at <= ?
                GROUP BY account_id, vendor_id";
        $rows = $this->db->query($sql, [$start, $end])->result_array();

        $baseDir = FCPATH . 'uploads/wa_billing/' . $date;
        if (!is_dir($baseDir)) mkdir($baseDir, 0755, true);

        $summaryFile = $baseDir . '/summary_' . $date . '.csv';
        $out = fopen($summaryFile, 'w');
        fputcsv($out, ['account_id','vendor_id','display_phone','messages_sent','total_spent','currency','date']);

        foreach ($rows as $r) {
            $acc = $this->db->where('id', $r['account_id'])->get('vendor_whatsapp_accounts')->row_array();
            $display = $acc['display_phone'] ?? ($acc['phone_number_id'] ?? '');
            fputcsv($out, [
                $r['account_id'],
                $r['vendor_id'],
                $display,
                $r['messages_sent'],
                $r['total_spent'],
                $r['currency'],
                $date,
            ]);

            // also write per-account detail CSV
            $detailFile = $baseDir . '/account_' . $r['account_id'] . '.csv';
            $dq = $this->db->where('account_id', $r['account_id'])->where('sent_at >=', $start)->where('sent_at <=', $end)->order_by('sent_at','ASC')->get('vendor_whatsapp_messages');
            $dout = fopen($detailFile, 'w');
            fputcsv($dout, ['sent_at','recipient','message_type','cost','currency','status','message_id','meta']);
            foreach ($dq->result_array() as $drow) {
                fputcsv($dout, [
                    $drow['sent_at'] ?? '',
                    $drow['recipient'] ?? '',
                    $drow['message_type'] ?? '',
                    $drow['cost'] ?? '',
                    $drow['currency'] ?? '',
                    $drow['status'] ?? '',
                    $drow['message_id'] ?? '',
                    $drow['meta'] ?? '',
                ]);
            }
            fclose($dout);
        }

        fclose($out);
        echo "Aggregated billing for {$date} into {$baseDir}\n";
    }

}
