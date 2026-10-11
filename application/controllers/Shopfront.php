<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Public product card opened from the WhatsApp Shop now / View button.
 */
class Shopfront extends CI_Controller {

    public function product($id = 0) {
        $id = (int)$id;
        $this->load->model('Sk_Product_model');
        $product = $id > 0 ? $this->Sk_Product_model->get_by_id($id) : null;
        if (!$product || strtolower(trim((string)($product['status'] ?? ''))) !== 'active') {
            show_404();
            return;
        }
        $this->load->helper('sk_mcp_tool');
        $image = '';
        $path = sk_mcp_tool_product_image_path($product);
        if ($path !== '') {
            $image = preg_match('#^https?://#i', $path) ? $path : base_url(ltrim($path, '/'));
            if (stripos($image, 'http://') === 0) {
                $image = 'https://' . substr($image, 7);
            }
        }
        $price = (float)($product['effective_price'] ?? $product['sale_price'] ?? $product['price'] ?? 0);
        $this->load->view('shop/product_card', [
            'name'  => (string)($product['name'] ?? 'Product'),
            'price' => $price,
            'image' => $image,
            'mrp'   => (float)($product['mrp'] ?? $product['price'] ?? 0),
        ]);
    }
}
