<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Public product card opened from the WhatsApp Shop now / View button or web links.
 */
class Shopfront extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->helper(['url', 'sk_mcp_tool']);
        $this->load->model('Sk_Product_model');
    }

    public function product($id = 0) {
        $id = (int)$id;
        $product = $id > 0 ? $this->Sk_Product_model->get_by_id($id) : null;
        if (!$product || strtolower(trim((string)($product['status'] ?? ''))) !== 'active') {
            show_404();
            return;
        }

        // Main image
        $image = '';
        $path = sk_mcp_tool_product_image_path($product);
        if ($path !== '') {
            $image = preg_match('#^https?://#i', $path) ? $path : base_url(ltrim($path, '/'));
            if (stripos($image, 'http://') === 0) {
                $image = 'https://' . substr($image, 7);
            }
        }

        // Gallery images
        $gallery = [];
        if ($image !== '') {
            $gallery[] = $image;
        }
        foreach ((array)($product['images'] ?? []) as $img) {
            $src = is_array($img) ? ($img['image'] ?? $img['url'] ?? '') : (string)$img;
            if ($src !== '') {
                $url = preg_match('#^https?://#i', $src) ? $src : base_url(ltrim($src, '/'));
                if (stripos($url, 'http://') === 0) {
                    $url = 'https://' . substr($url, 7);
                }
                if (!in_array($url, $gallery, true)) {
                    $gallery[] = $url;
                }
            }
        }

        // Pricing
        $price = (float)($product['effective_price'] ?? $product['sale_price'] ?? $product['price'] ?? 0);
        $mrp = (float)($product['mrp'] ?? $product['price'] ?? 0);
        if ($mrp < $price) {
            $mrp = $price;
        }

        // Stock & Availability
        $stock = (int)($product['stock'] ?? 0);
        $inStock = $stock > 0;

        // Variants (Size / Color / Packs)
        $variants = is_array($product['variants'] ?? null) ? $product['variants'] : [];
        $cleanVariants = [];
        foreach ($variants as $v) {
            $vPrice = (float)($v['effective_price'] ?? $v['sale_price'] ?? $v['price'] ?? $price);
            $vMrp = (float)($v['mrp'] ?? $mrp);
            $cleanVariants[] = [
                'id'         => (int)($v['id'] ?? 0),
                'label'      => (string)($v['label'] ?? $v['unit_name'] ?? $v['name'] ?? ''),
                'unit_name'  => (string)($v['unit_name'] ?? $v['label'] ?? ''),
                'unit_type'  => (string)($v['unit_type'] ?? 'size'),
                'price'      => $vPrice,
                'mrp'        => $vMrp,
                'stock'      => (int)($v['stock'] ?? $stock),
                'sku'        => (string)($v['sku'] ?? $product['sku'] ?? ''),
                'is_default' => !empty($v['is_default']),
            ];
        }

        // Fallback default sizes if product has no variants in DB
        if (empty($cleanVariants)) {
            $defaultSizes = ['S', 'M', 'L', 'XL'];
            foreach ($defaultSizes as $idx => $s) {
                $cleanVariants[] = [
                    'id'         => 0,
                    'label'      => $s,
                    'unit_name'  => $s,
                    'unit_type'  => 'size',
                    'price'      => $price,
                    'mrp'        => $mrp,
                    'stock'      => $stock,
                    'sku'        => ($product['sku'] ?? 'PROD') . '-' . $s,
                    'is_default' => $idx === 1 ? 1 : 0, // M is default
                ];
            }
        }

        // Vendor & WhatsApp click-to-chat
        $vendorId = (int)($product['vendor_id'] ?? 0);
        $shopName = 'Talk AI Pilot Shop';
        if ($vendorId > 0 && $this->db->table_exists('vendors')) {
            $vendorRow = $this->db->where('id', $vendorId)->get('vendors')->row_array();
            if ($vendorRow) {
                $shopName = trim((string)($vendorRow['business_name'] ?? $vendorRow['name'] ?? $shopName));
            }
        }

        $waPhone = '';
        if ($this->db->table_exists('vendor_whatsapp_accounts')) {
            if ($vendorId > 0) {
                $this->db->where('vendor_id', $vendorId);
            }
            $waRow = $this->db->where('status', 'active')->order_by('is_default', 'DESC')->get('vendor_whatsapp_accounts')->row_array();
            if ($waRow) {
                $waPhone = preg_replace('/\D+/', '', (string)($waRow['display_phone'] ?? $waRow['phone_number_id'] ?? ''));
            }
        }

        $this->load->view('shop/product_card', [
            'id'          => $id,
            'name'        => (string)($product['name'] ?? 'Product'),
            'sku'         => (string)($product['sku'] ?? ''),
            'price'       => $price,
            'mrp'         => $mrp,
            'stock'       => $stock,
            'in_stock'    => $inStock,
            'image'       => $image,
            'gallery'     => $gallery,
            'description' => (string)($product['description'] ?? ''),
            'specs'       => (string)($product['specs'] ?? ''),
            'category'    => (string)($product['category_name'] ?? ''),
            'variants'    => $cleanVariants,
            'shop_name'   => $shopName,
            'wa_phone'    => $waPhone,
        ]);
    }
}
