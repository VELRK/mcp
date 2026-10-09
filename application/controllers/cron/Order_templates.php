<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CLI: php index.php cron/order_templates recreate 2
 * Deletes rejected order templates and submits the current Meta-safe copy.
 */
class Order_templates extends CI_Controller {

    public function recreate($vendorId = 0) {
        if (!is_cli()) {
            show_404();
            return;
        }
        $this->load->database();
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
        foreach ($ids as $id) {
            $seed = sk_wa_ecomm_seed_vendor($id, true);
            echo 'vendor ' . $id
                . ' created=' . (int)$seed['created']
                . ' pushed=' . (int)$seed['pushed']
                . ' errors=' . implode(' | ', $seed['errors'])
                . PHP_EOL;
        }
    }
}
