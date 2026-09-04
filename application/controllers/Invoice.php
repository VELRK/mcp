<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Public invoice download (signed link from order emails).
 * GET /invoice/download/{orderId}/{token}  → PDF
 * GET /invoice/view/{orderId}/{token}      → HTML print view
 */
class Invoice extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->model(['Sk_Order_model', 'Sk_Admin_model']);
        $this->load->helper(['sk_invoice', 'sk_invoice_pdf']);
        sk_invoice_ensure_vendor_schema();
    }

    public function download($orderId = 0, $token = '') {
        $order = $this->_load_authorized((int)$orderId, (string)$token);
        $settings = $this->Sk_Admin_model->get_settings();
        $invoice = sk_invoice_build($order, $settings);
        $pdf = sk_invoice_build_pdf($invoice);
        $filename = 'invoice-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $invoice['order_number'] ?? (string)$orderId) . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $pdf;
        exit;
    }

    public function view($orderId = 0, $token = '') {
        $order = $this->_load_authorized((int)$orderId, (string)$token);
        $settings = $this->Sk_Admin_model->get_settings();
        $invoice = sk_invoice_build($order, $settings);
        $pdfUrl = sk_invoice_public_url($order);
        $html = sk_invoice_render_html($invoice, false);
        // Inject download PDF button + auto-open PDF option
        $bar = "<div class='no-print' style='text-align:center;padding:12px;background:#0f172a;'>"
            . "<a href='" . htmlspecialchars($pdfUrl) . "' style='display:inline-block;background:#f59e0b;color:#fff;text-decoration:none;padding:10px 22px;border-radius:6px;font-weight:700;margin-right:8px;'>Download PDF</a>"
            . "<button onclick='window.print()' style='background:#64748b;color:#fff;border:none;padding:10px 22px;border-radius:6px;cursor:pointer;font-weight:600;'>Print</button>"
            . '</div>';
        $html = preg_replace('/<body[^>]*>/', '$0' . $bar, $html, 1);
        echo $html;
    }

    private function _load_authorized(int $orderId, string $token): array {
        if ($orderId < 1 || $token === '') {
            show_error('Invalid invoice link.', 404);
        }
        $order = $this->Sk_Order_model->get_by_id($orderId);
        if (!$order) {
            show_error('Invoice not found.', 404);
        }
        if (!sk_invoice_verify_token($orderId, (string)$order['order_number'], $token)) {
            show_error('Invalid or expired invoice link.', 403);
        }
        return $order;
    }
}
