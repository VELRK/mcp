<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/admin/Sk_Base.php';

class Products extends Sk_Base {

    public function __construct() {
        parent::__construct();
        $this->load->model(['Sk_Variant_unit_model', 'Sk_Product_variant_model']);
    }

    public function index() {
        $data = $this->_product_list_page_data();
        $this->render('products/list', $data);
    }

    public function filter() {
        $data = $this->_product_list_page_data();
        $rows_html = $this->load->view('admin/products/_list_rows', $data, true);
        $pagination_html = $this->load->view('admin/products/_list_pagination', $data, true);
        $this->json([
            'success'         => true,
            'rows_html'       => $rows_html,
            'pagination_html' => $pagination_html,
            'total'           => (int)$data['total'],
            'page'            => (int)$data['page'],
        ]);
    }

    private function _product_list_filters(): array {
        $search = $this->input->get('search', TRUE);
        $vendor_id = $this->current_vendor_id() ?: ((int)$this->input->get('vendor_id') ?: null);
        return [
            'search'         => $search,
            'vendor_id'      => $vendor_id,
            'category_id'    => (int)$this->input->get('category_id'),
            'subcategory_id' => (int)$this->input->get('subcategory_id'),
            'status'         => $this->input->get('status', TRUE),
            'low_stock'      => $this->input->get('low_stock') ? 1 : 0,
            'min_price'      => $this->input->get('min_price'),
            'max_price'      => $this->input->get('max_price'),
        ];
    }

    private function _product_list_page_data(): array {
        $filters = $this->_product_list_filters();
        $page = max(1, (int)($this->input->get('page') ?? 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;
        $vendor_id = $filters['vendor_id'];
        $vendor_logged_in = (bool)$this->session->userdata('sk_vendor_login');
        $impersonating = (bool)$this->session->userdata('sk_vendor_id')
            && (bool)$this->session->userdata('sk_admin_id')
            && !$vendor_logged_in;

        $products = $this->Sk_Product_model->get_all_admin($limit, $offset, $filters);
        foreach ($products as &$prod) {
            $prod['variants'] = $this->Sk_Product_variant_model->get_by_product($prod['id'], false);
        }
        unset($prod);

        $data = [
            'title'           => 'Products - 2DEAL Admin',
            'products'        => $products,
            'total'           => $this->Sk_Product_model->count_all_admin($filters),
            'page'            => $page,
            'limit'           => $limit,
            'search'          => $filters['search'],
            'vendor_id'       => $vendor_id,
            'filters'         => $filters,
            'categories'      => $this->Sk_Admin_model->get_categories(null, 1),
            'subcategories'   => $this->Sk_Admin_model->get_subcategories(null, 1),
            'impersonating'   => $impersonating,
            'show_vendor_col' => empty($vendor_id) && !$impersonating,
            'settings'        => $this->Sk_Admin_model->get_settings(),
        ];
        if ($this->is_super_admin()) {
            $data['vendors'] = $this->Sk_Vendor_model->get_all(['status' => 'approved'], 200, 0)['rows'];
        }
        return $data;
    }

    public function add() {
        $data['title']        = 'Add Product';
        $data['categories']   = $this->Sk_Admin_model->get_categories(null, 1);
        $data['brands']       = $this->db->get('brands')->result_array();
        $data['variant_units']= $this->Sk_Variant_unit_model->get_all_active();
        $data['product_variants'] = [];
        $data['saree_styles'] = $this->Sk_Admin_model->get_saree_styles();
        $data['fabrics']      = Sk_Admin_model::fabric_options();
        $data['occasions']    = Sk_Admin_model::occasion_options();
        $data['work_types']   = Sk_Admin_model::work_type_options();
        $data['wash_cares']   = Sk_Admin_model::wash_care_options();
        $data['origin_states']= Sk_Admin_model::origin_states();
        $data['extra_js']     = $this->_summernote_init_js();
        $data['vendor_id']    = $this->current_vendor_id();
        if ($this->is_super_admin()) {
            $data['vendors'] = $this->Sk_Vendor_model->get_all(['status' => 'approved'], 200, 0)['rows'];
        }
        $this->render('products/add', $data);
    }

    public function store() {
        $this->form_validation->set_rules('name',       'Product Name', 'required|trim');
        $this->form_validation->set_rules('category_id','Category',     'required|integer');
        $this->form_validation->set_rules('payment_link','Payment Link','required|trim');

        if ($this->form_validation->run() === FALSE) {
            $this->session->set_flashdata('error', validation_errors());
            redirect('admin/products/add');
        }

        $thumbnail = $this->upload_file('thumbnail', 'products');

        $data = [
            'name'             => $this->input->post('name', TRUE),
            'category_id'      => $this->input->post('category_id'),
            'subcategory_id'   => $this->input->post('subcategory_id') ?: null,
            'brand_id'         => $this->input->post('brand_id') ?: null,
            'sku'              => $this->input->post('sku', TRUE),
            'description'      => $this->input->post('description'),
            'short_desc'       => $this->input->post('short_desc', TRUE),
            'price'            => ($this->input->post('price') !== null && $this->input->post('price') !== '')
                ? $this->input->post('price')
                : 0,
            'sale_price'       => $this->input->post('sale_price') ?: null,
            'hot_sale'         => $this->input->post('hot_sale') ? 1 : 0,
            'sale_start_at'    => $this->_normalize_datetime_input($this->input->post('sale_start_at')),
            'sale_end_at'      => $this->_normalize_datetime_input($this->input->post('sale_end_at')),
            'stock'            => ($this->input->post('stock') !== null && $this->input->post('stock') !== '')
                ? $this->input->post('stock')
                : 0,
            'weight'           => $this->input->post('weight') ?: null,
            'featured'         => $this->input->post('featured') ? 1 : 0,
            'nav_featured'     => $this->input->post('nav_featured') ? 1 : 0,
            'special_product'  => $this->input->post('special_product') ? 1 : 0,
            'status'           => $this->input->post('status'),
            'meta_title'       => $this->input->post('meta_title', TRUE),
            'meta_desc'        => $this->input->post('meta_desc', TRUE),
            'meta_keywords'    => $this->input->post('meta_keywords', TRUE),
            'og_image'         => $this->input->post('og_image', TRUE),
            'tags'             => $this->input->post('tags', TRUE),
            'thumbnail'        => $thumbnail,
            'payment_link'     => $this->input->post('payment_link', TRUE) ?: null,
            // Saree attributes
            'saree_type'       => $this->input->post('saree_type', TRUE) ?: null,
            'fabric'           => $this->input->post('fabric', TRUE) ?: null,
            'occasion'         => $this->input->post('occasion', TRUE) ?: null,
            'work_type'        => $this->input->post('work_type', TRUE) ?: null,
            'color'            => $this->input->post('color', TRUE) ?: null,
            'color_hex'        => $this->input->post('color_hex', TRUE) ?: null,
            'color2'           => $this->input->post('color2', TRUE) ?: null,
            'saree_length'     => $this->input->post('saree_length') ?: 5.50,
            'blouse_included'  => $this->input->post('blouse_included') ? 1 : 0,
            'blouse_length'    => $this->input->post('blouse_length') ?: 0.80,
            'set_contains'        => $this->input->post('set_contains', TRUE) ?: null,
            'border_type'         => $this->input->post('border_type', TRUE) ?: null,
            'transparency'        => $this->input->post('transparency') ?: 'opaque',
            'wash_care'           => $this->input->post('wash_care', TRUE) ?: null,
            'origin_state'        => $this->input->post('origin_state', TRUE) ?: null,
            'weave_type'          => $this->input->post('weave_type', TRUE) ?: null,
            'net_weight'          => $this->input->post('net_weight') ?: null,
            'zari_type'           => $this->input->post('zari_type', TRUE) ?: null,
            'suitable_for'        => $this->input->post('suitable_for', TRUE) ?: null,
            'return_policy'       => $this->input->post('return_policy') ?: null,
            'shipping_info'       => $this->input->post('shipping_info') ?: null,
            // ── New catalogue fields ─────────────────────────────────────
            'subtitle'            => $this->input->post('subtitle', TRUE) ?: null,
            'model_name'          => $this->input->post('model_name', TRUE) ?: null,
            'listing_status'      => $this->input->post('listing_status') ?: 'ACTIVE',
            'min_order_qty'       => (int)($this->input->post('min_order_qty') ?: 1),
            'procurement_type'    => $this->input->post('procurement_type', TRUE) ?: 'IN_STOCK',
            'procurement_sla'     => (int)($this->input->post('procurement_sla') ?: 2),
            'package_length'      => $this->input->post('package_length') ?: null,
            'package_breadth'     => $this->input->post('package_breadth') ?: null,
            'package_height'      => $this->input->post('package_height') ?: null,
            'hsn_code'            => $this->input->post('hsn_code', TRUE) ?: null,
            'tax_code'            => $this->input->post('tax_code', TRUE) ?: null,
            'manufacturer_name'   => $this->input->post('manufacturer_name', TRUE) ?: null,
            'manufacturer_address'=> $this->input->post('manufacturer_address', TRUE) ?: null,
            'style_code'          => $this->input->post('style_code', TRUE) ?: null,
            'ean'                 => $this->input->post('ean', TRUE) ?: null,
            'sizes'               => $this->input->post('sizes', TRUE) ?: null,
            'brand_color'         => $this->input->post('brand_color', TRUE) ?: null,
            'pattern'             => $this->input->post('pattern', TRUE) ?: null,
            'fit_type'            => $this->input->post('fit_type', TRUE) ?: null,
            'neck_type'           => $this->input->post('neck_type', TRUE) ?: null,
            'sleeve_length'       => $this->input->post('sleeve_length', TRUE) ?: null,
            'length_type'         => $this->input->post('length_type', TRUE) ?: null,
            'pack_of'             => (int)($this->input->post('pack_of') ?: 1),
            'pattern_type'        => $this->input->post('pattern_type', TRUE) ?: null,
            'pattern_coverage'    => $this->input->post('pattern_coverage', TRUE) ?: null,
            'ornamentation'       => $this->input->post('ornamentation', TRUE) ?: null,
            'features'            => $this->_encode_features($this->input->post('features', TRUE)),
            'category_attributes' => $this->_encode_json($this->input->post('category_attributes', TRUE)),
            'colors_json'         => $this->_build_colors_json(),
            'vendor_id'           => $this->resolve_vendor_id_for_write((int)$this->input->post('vendor_id')) ?: 1,
            'created_by'          => $this->admin['id'],
        ];
        // Set color/color2 from first two color variants
        $cv = $this->input->post('color_variants') ?? [];
        if (!empty($cv[0]['name'])) { $data['color'] = $cv[0]['name']; $data['color_hex'] = $cv[0]['hex'] ?? ''; }
        if (!empty($cv[1]['name'])) $data['color2'] = $cv[1]['name'];

        $product_id = $this->Sk_Product_model->create($data);

        $this->_save_product_variants($product_id, $data, $thumbnail);

        // Additional images
        if (!empty($_FILES['images']['name'][0])) {
            $this->_save_product_images($product_id);
        }

        $this->_clear_product_api_cache();
        $this->session->set_flashdata('success', 'Product created successfully.');
        redirect('admin/products');
    }

    public function edit($id) {
        $data['title']         = 'Edit Product';
        $data['product']       = $this->Sk_Product_model->get_by_id($id);
        $this->assert_product_vendor_access($data['product']);
        // attach_variants swaps thumbnail to the default pack photo — Main Image must show products.thumbnail.
        $rawRow = $this->db->select('thumbnail, price, sale_price, stock, weight, hot_sale, sale_start_at, sale_end_at, payment_link')
            ->where('id', (int)$id)->get('products')->row_array() ?: [];
        if (!empty($rawRow['thumbnail'])) {
            $data['product']['thumbnail'] = $rawRow['thumbnail'];
        }
        foreach (['price', 'sale_price', 'stock', 'weight', 'hot_sale', 'sale_start_at', 'sale_end_at', 'payment_link'] as $field) {
            if (array_key_exists($field, $rawRow)) {
                $data['product'][$field] = $rawRow[$field];
            }
        }
        $data['categories']    = $this->Sk_Admin_model->get_categories(null, 1);
        $data['subcategories'] = $this->Sk_Admin_model->get_subcategories($data['product']['category_id'], 1);
        $data['brands']        = $this->db->get('brands')->result_array();
        $data['variant_units'] = $this->Sk_Variant_unit_model->get_all_active();
        $data['product_variants'] = $this->Sk_Product_variant_model->get_by_product($id, false);
        $data['saree_styles']  = $this->Sk_Admin_model->get_saree_styles();
        $data['fabrics']       = Sk_Admin_model::fabric_options();
        $data['occasions']     = Sk_Admin_model::occasion_options();
        $data['work_types']    = Sk_Admin_model::work_type_options();
        $data['wash_cares']    = Sk_Admin_model::wash_care_options();
        $data['origin_states'] = Sk_Admin_model::origin_states();
        $data['extra_js']      = $this->_summernote_init_js();
        $this->render('products/edit', $data);
    }

    public function subcategories_by_category($category_id) {
        $subs = $this->Sk_Admin_model->get_subcategories((int)$category_id, 1);
        $this->json($subs);
    }

    public function update($id) {
        $product = $this->Sk_Product_model->get_by_id($id);
        $this->assert_product_vendor_access($product);
        $rawRow = $this->db->select('thumbnail, price, sale_price, stock, weight, hot_sale, sale_start_at, sale_end_at')
            ->where('id', (int)$id)->get('products')->row_array() ?: [];
        // Do not use attach_variants overlay as the keep-current fallback.
        $product['thumbnail'] = $rawRow['thumbnail'] ?? ($product['thumbnail'] ?? null);
        $product['price'] = $rawRow['price'] ?? ($product['price'] ?? 0);
        $product['sale_price'] = $rawRow['sale_price'] ?? ($product['sale_price'] ?? null);
        $product['stock'] = $rawRow['stock'] ?? ($product['stock'] ?? 0);
        $product['weight'] = $rawRow['weight'] ?? ($product['weight'] ?? null);
        $product['hot_sale'] = $rawRow['hot_sale'] ?? ($product['hot_sale'] ?? 0);
        $product['sale_start_at'] = $rawRow['sale_start_at'] ?? ($product['sale_start_at'] ?? null);
        $product['sale_end_at'] = $rawRow['sale_end_at'] ?? ($product['sale_end_at'] ?? null);

        $hadThumbUpload = !empty($_FILES['thumbnail']['name']);
        $uploadedThumb = $this->upload_file('thumbnail', 'products');
        $thumbUploadFailed = $hadThumbUpload && !$uploadedThumb;
        $thumbnail = $uploadedThumb ?? $product['thumbnail'];

        $postedPrice = array_key_exists('price', $_POST) ? $this->input->post('price') : false;
        $postedStock = array_key_exists('stock', $_POST) ? $this->input->post('stock') : false;

        $payment_link = trim((string)$this->input->post('payment_link', TRUE));
        if ($payment_link === '') {
            $this->session->set_flashdata('error', 'Payment Link is required.');
            redirect('admin/products/edit/' . $id);
            return;
        }

        $data = [
            'name'             => $this->input->post('name', TRUE),
            'category_id'      => $this->input->post('category_id'),
            'subcategory_id'   => $this->input->post('subcategory_id') ?: null,
            'brand_id'         => $this->input->post('brand_id') ?: null,
            'sku'              => $this->input->post('sku', TRUE),
            'description'      => $this->input->post('description'),
            'short_desc'       => $this->input->post('short_desc', TRUE),
            'price'            => ($postedPrice !== false && $postedPrice !== null && $postedPrice !== '') ? $postedPrice : $product['price'],
            'sale_price'       => (array_key_exists('sale_price', $_POST) && $this->input->post('sale_price') !== '')
                ? $this->input->post('sale_price')
                : ($product['sale_price'] ?? null),
            'hot_sale'         => $this->input->post('hot_sale') ? 1 : 0,
            'sale_start_at'    => array_key_exists('sale_start_at', $_POST)
                ? $this->_normalize_datetime_input($this->input->post('sale_start_at'))
                : ($product['sale_start_at'] ?? null),
            'sale_end_at'      => array_key_exists('sale_end_at', $_POST)
                ? $this->_normalize_datetime_input($this->input->post('sale_end_at'))
                : ($product['sale_end_at'] ?? null),
            'stock'            => ($postedStock !== false && $postedStock !== null && $postedStock !== '') ? $postedStock : $product['stock'],
            'weight'           => (array_key_exists('weight', $_POST) && $this->input->post('weight') !== '')
                ? $this->input->post('weight')
                : ($product['weight'] ?? null),
            'featured'         => $this->input->post('featured') ? 1 : 0,
            'nav_featured'     => $this->input->post('nav_featured') ? 1 : 0,
            'special_product'  => $this->input->post('special_product') ? 1 : 0,
            'status'           => $this->input->post('status'),
            'meta_title'       => $this->input->post('meta_title', TRUE),
            'meta_desc'        => $this->input->post('meta_desc', TRUE),
            'meta_keywords'    => $this->input->post('meta_keywords', TRUE),
            'og_image'         => $this->input->post('og_image', TRUE),
            'tags'             => $this->input->post('tags', TRUE),
            'thumbnail'        => $thumbnail,
            'payment_link'     => $payment_link ?: null,
            // Saree attributes
            'saree_type'       => $this->input->post('saree_type', TRUE) ?: null,
            'fabric'           => $this->input->post('fabric', TRUE) ?: null,
            'occasion'         => $this->input->post('occasion', TRUE) ?: null,
            'work_type'        => $this->input->post('work_type', TRUE) ?: null,
            'color'            => $this->input->post('color', TRUE) ?: null,
            'color_hex'        => $this->input->post('color_hex', TRUE) ?: null,
            'color2'           => $this->input->post('color2', TRUE) ?: null,
            'saree_length'     => $this->input->post('saree_length') ?: 5.50,
            'blouse_included'  => $this->input->post('blouse_included') ? 1 : 0,
            'blouse_length'    => $this->input->post('blouse_length') ?: 0.80,
            'set_contains'        => $this->input->post('set_contains', TRUE) ?: null,
            'border_type'         => $this->input->post('border_type', TRUE) ?: null,
            'transparency'        => $this->input->post('transparency') ?: 'opaque',
            'wash_care'           => $this->input->post('wash_care', TRUE) ?: null,
            'origin_state'        => $this->input->post('origin_state', TRUE) ?: null,
            'weave_type'          => $this->input->post('weave_type', TRUE) ?: null,
            'net_weight'          => $this->input->post('net_weight') ?: null,
            'zari_type'           => $this->input->post('zari_type', TRUE) ?: null,
            'suitable_for'        => $this->input->post('suitable_for', TRUE) ?: null,
            'return_policy'       => $this->input->post('return_policy') ?: null,
            'shipping_info'       => $this->input->post('shipping_info') ?: null,
            // ── New catalogue fields ─────────────────────────────────────
            'subtitle'            => $this->input->post('subtitle', TRUE) ?: null,
            'model_name'          => $this->input->post('model_name', TRUE) ?: null,
            'listing_status'      => $this->input->post('listing_status') ?: 'ACTIVE',
            'min_order_qty'       => (int)($this->input->post('min_order_qty') ?: 1),
            'procurement_type'    => $this->input->post('procurement_type', TRUE) ?: 'IN_STOCK',
            'procurement_sla'     => (int)($this->input->post('procurement_sla') ?: 2),
            'package_length'      => $this->input->post('package_length') ?: null,
            'package_breadth'     => $this->input->post('package_breadth') ?: null,
            'package_height'      => $this->input->post('package_height') ?: null,
            'hsn_code'            => $this->input->post('hsn_code', TRUE) ?: null,
            'tax_code'            => $this->input->post('tax_code', TRUE) ?: null,
            'manufacturer_name'   => $this->input->post('manufacturer_name', TRUE) ?: null,
            'manufacturer_address'=> $this->input->post('manufacturer_address', TRUE) ?: null,
            'style_code'          => $this->input->post('style_code', TRUE) ?: null,
            'ean'                 => $this->input->post('ean', TRUE) ?: null,
            'sizes'               => $this->input->post('sizes', TRUE) ?: null,
            'brand_color'         => $this->input->post('brand_color', TRUE) ?: null,
            'pattern'             => $this->input->post('pattern', TRUE) ?: null,
            'fit_type'            => $this->input->post('fit_type', TRUE) ?: null,
            'neck_type'           => $this->input->post('neck_type', TRUE) ?: null,
            'sleeve_length'       => $this->input->post('sleeve_length', TRUE) ?: null,
            'length_type'         => $this->input->post('length_type', TRUE) ?: null,
            'pack_of'             => (int)($this->input->post('pack_of') ?: 1),
            'pattern_type'        => $this->input->post('pattern_type', TRUE) ?: null,
            'pattern_coverage'    => $this->input->post('pattern_coverage', TRUE) ?: null,
            'ornamentation'       => $this->input->post('ornamentation', TRUE) ?: null,
            'features'            => $this->_encode_features($this->input->post('features', TRUE)),
            'category_attributes' => $this->_encode_json($this->input->post('category_attributes', TRUE)),
            'colors_json'         => $this->_build_colors_json($product['colors_json'] ?? []),
        ];
        $cv = $this->input->post('color_variants') ?? [];
        if (!empty($cv[0]['name'])) { $data['color'] = $cv[0]['name']; $data['color_hex'] = $cv[0]['hex'] ?? ''; }
        if (!empty($cv[1]['name'])) $data['color2'] = $cv[1]['name'];

        $this->Sk_Product_model->update($id, $data);

        $variantUploadFailed = $this->_save_product_variants($id, $product, $uploadedThumb);

        if (!empty($_FILES['images']['name'][0])) {
            $this->_save_product_images($id);
        }

        $this->_clear_product_api_cache();
        if ($thumbUploadFailed || $variantUploadFailed) {
            redirect('admin/products/edit/' . $id);
            return;
        }
        $this->session->set_flashdata('success', 'Product updated successfully.');
        redirect('admin/products/edit/' . $id);
    }

    public function delete($id) {
        $product = $this->Sk_Product_model->get_by_id($id);
        $this->assert_product_vendor_access($product);
        $this->Sk_Product_model->delete($id);
        $this->_clear_product_api_cache();
        $this->json(['success' => true]);
    }

    public function toggle($id) {
        $product = $this->Sk_Product_model->get_by_id($id);
        if (!$product) $this->json(['success' => false]);
        $this->assert_product_vendor_access($product);
        $new = $product['status'] === 'active' ? 'inactive' : 'active';
        $this->Sk_Product_model->update($id, ['status' => $new]);
        $this->_clear_product_api_cache();
        $this->json(['success' => true, 'status' => $new]);
    }

    public function delete_image($image_id, $product_id) {
        $product = $this->Sk_Product_model->get_by_id($product_id);
        $this->assert_product_vendor_access($product);
        $this->Sk_Product_model->delete_image((int)$image_id, (int)$product_id);
        $this->json(['success' => true]);
    }

    private function _save_product_variants($product_id, $product = null, $newMainThumb = null) {
        $rows = array_values($this->input->post('product_variants') ?? []);
        $parsed = [];
        $postedPrice = array_key_exists('price', $_POST) ? $this->input->post('price') : false;
        $postedStock = array_key_exists('stock', $_POST) ? $this->input->post('stock') : false;
        $main_price = ($postedPrice !== false && $postedPrice !== null && $postedPrice !== '')
            ? (float)$postedPrice
            : (float)($product['price'] ?? 0);
        $postedSale = array_key_exists('sale_price', $_POST) ? $this->input->post('sale_price') : false;
        $main_sale  = ($postedSale !== false && $postedSale !== null && $postedSale !== '')
            ? $postedSale
            : ($product['sale_price'] ?? null);
        $main_stock = ($postedStock !== false && $postedStock !== null && $postedStock !== '')
            ? (int)$postedStock
            : (int)($product['stock'] ?? 0);
        $main_sku   = $this->input->post('sku', TRUE);
        $upload_failed = false;

        $existing_images = [];
        foreach ($this->Sk_Product_variant_model->get_by_product($product_id, false) as $ev) {
            if (empty($ev['image'])) continue;
            $existing_images[$this->_variant_image_key($ev['unit_id'], $ev['unit_value'])] = $ev['image'];
        }

        foreach ($rows as $seq => $row) {
            $unit_id = (int)($row['unit_id'] ?? 0);
            if (!$unit_id) continue;

            $unit_value = (float)($row['unit_value'] ?? 1);
            $image = trim($row['existing_image'] ?? '');
            if ($image === '') {
                $image = trim($existing_images[$this->_variant_image_key($unit_id, $unit_value)] ?? '');
            }

            $uploaded = $this->_upload_variant_image($seq);
            if ($uploaded) {
                $image = $uploaded;
            } elseif ($this->_variant_upload_attempted($seq)) {
                $upload_failed = true;
            }

            $price = ($row['price'] ?? '') !== '' ? (float)$row['price'] : $main_price;
            $sale  = ($row['sale_price'] ?? '') !== '' ? (float)$row['sale_price'] : ($main_sale !== null ? (float)$main_sale : null);
            $stock = ($row['stock'] ?? '') !== '' ? (int)$row['stock'] : $main_stock;

            $parsed[] = [
                'unit_id'     => $unit_id,
                'unit_value'  => $unit_value,
                'label'       => trim($row['label'] ?? ''),
                'price'       => $price,
                'sale_price'  => $sale,
                'stock'       => $stock,
                'sku'         => ($row['sku'] ?? '') !== '' ? $row['sku'] : $main_sku,
                'image'       => $image !== '' ? $image : null,
                'is_default'  => !empty($row['is_default']),
            ];
        }

        if ($upload_failed) {
            $this->session->set_flashdata('error', 'One or more variant images failed to upload. Use JPG, PNG, GIF or WebP under 8MB.');
        }

        if (empty($parsed)) {
            $default_unit = (int)$this->input->post('default_unit_id');
            if ($default_unit) {
                $parsed[] = [
                    'unit_id'     => $default_unit,
                    'unit_value'  => (float)($this->input->post('default_unit_value') ?: 1),
                    'label'       => '',
                    'price'       => $main_price,
                    'sale_price'  => $main_sale,
                    'stock'       => $main_stock,
                    'sku'         => $main_sku,
                    'image'       => null,
                    'is_default'  => true,
                ];
            }
        }

        $this->Sk_Product_variant_model->replace_for_product((int)$product_id, $parsed);
        $this->Sk_Product_model->sync_product_stock_from_variants((int)$product_id);

        // Main image and pack images stay independent. Never copy a variant photo onto products.thumbnail.
        if ($newMainThumb) {
            $this->db->where('id', (int)$product_id)->update('products', ['thumbnail' => $newMainThumb]);
        }

        return $upload_failed;
    }

    private function _variant_image_key($unit_id, $unit_value) {
        return (int)$unit_id . ':' . rtrim(rtrim(number_format((float)$unit_value, 3, '.', ''), '0'), '.');
    }

    private function _upload_variant_image($seq) {
        if (empty($_FILES['variant_images']) || !is_array($_FILES['variant_images']['name'] ?? null)) {
            return null;
        }
        if (!isset($_FILES['variant_images']['name'][$seq])) {
            return null;
        }
        if ((int)$_FILES['variant_images']['error'][$seq] !== UPLOAD_ERR_OK) {
            return null;
        }
        if (trim((string)$_FILES['variant_images']['name'][$seq]) === '') {
            return null;
        }

        $_FILES['variant_image_single'] = [
            'name'     => $_FILES['variant_images']['name'][$seq],
            'type'     => $_FILES['variant_images']['type'][$seq],
            'tmp_name' => $_FILES['variant_images']['tmp_name'][$seq],
            'error'    => $_FILES['variant_images']['error'][$seq],
            'size'     => $_FILES['variant_images']['size'][$seq],
        ];

        return $this->upload_file('variant_image_single', 'products');
    }

    private function _variant_upload_attempted($seq) {
        if (empty($_FILES['variant_images']) || !isset($_FILES['variant_images']['error'][$seq])) {
            return false;
        }
        $err = (int)$_FILES['variant_images']['error'][$seq];
        return $err !== UPLOAD_ERR_OK && $err !== UPLOAD_ERR_NO_FILE;
    }

    private function _clear_product_api_cache() {
        $dir = APPPATH . 'cache/api/';
        if (!is_dir($dir)) return;
        foreach (glob($dir . 'product*.json') as $f) @unlink($f);
        foreach (glob($dir . 'products*.json') as $f) @unlink($f);
    }

    private function _save_product_images($product_id) {
        $total = count($_FILES['images']['name']);
        for ($i = 0; $i < $total; $i++) {
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $_FILES['image_single'] = [
                'name'     => $_FILES['images']['name'][$i],
                'type'     => $_FILES['images']['type'][$i],
                'tmp_name' => $_FILES['images']['tmp_name'][$i],
                'error'    => $_FILES['images']['error'][$i],
                'size'     => $_FILES['images']['size'][$i],
            ];
            $img = $this->upload_file('image_single', 'products');
            if ($img) {
                $this->Sk_Product_model->save_images($product_id, [['image' => $img, 'sort_order' => $i]]);
            }
        }
    }

    private function _normalize_datetime_input($value) {
        $value = trim((string)$value);
        if ($value === '') return null;
        $ts = strtotime($value);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    /** Convert newline-separated feature text to JSON array. */
    private function _encode_features($text) {
        if (!$text) return null;
        $lines = array_filter(array_map('trim', explode("\n", $text)));
        return $lines ? json_encode(array_values($lines)) : null;
    }

    /** Build colors_json from posted color_variants + uploaded swatch images. */
    private function _build_colors_json($existing_colors = []) {
        $variants = $this->input->post('color_variants') ?? [];
        if (empty($variants)) return null;

        $total_files = count($_FILES['color_variant_images']['name'] ?? []);
        $colors = [];
        $file_idx = 0;

        foreach ($variants as $i => $v) {
            $name = trim($v['name'] ?? '');
            if ($name === '') { $file_idx++; continue; }

            $hex   = $v['hex']  ?? '';
            $image = $v['existing_image'] ?? ($existing_colors[$i]['image'] ?? '');

            // Upload new swatch image if provided
            if ($file_idx < $total_files && $_FILES['color_variant_images']['error'][$file_idx] === UPLOAD_ERR_OK) {
                $_FILES['cv_img_single'] = [
                    'name'     => $_FILES['color_variant_images']['name'][$file_idx],
                    'type'     => $_FILES['color_variant_images']['type'][$file_idx],
                    'tmp_name' => $_FILES['color_variant_images']['tmp_name'][$file_idx],
                    'error'    => $_FILES['color_variant_images']['error'][$file_idx],
                    'size'     => $_FILES['color_variant_images']['size'][$file_idx],
                ];
                $uploaded = $this->upload_file('cv_img_single', 'products');
                if ($uploaded) $image = $uploaded;
            }
            $file_idx++;

            $colors[] = ['name' => $name, 'hex' => $hex, 'image' => $image];
        }

        return $colors ? json_encode($colors, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
    }

    /** Store arbitrary text as JSON (pass-through if already valid JSON). */
    private function _encode_json($text) {
        if (!$text) return null;
        json_decode($text);
        return json_last_error() === JSON_ERROR_NONE ? $text : null;
    }

    private function _summernote_init_js() {
        return <<<'JS'
<script>
(function() {
  /* ── Register font families ── */
  var Font = Quill.import('formats/font');
  Font.whitelist = ['serif', 'monospace', 'sans-serif'];
  Quill.register(Font, true);

  /* ── Register inline font sizes via style attribute ── */
  var SizeStyle = Quill.import('attributors/style/size');
  SizeStyle.whitelist = ['10px','12px','14px','16px','18px','20px','24px','28px','32px','36px','48px'];
  Quill.register(SizeStyle, true);

  var SIZES  = ['10px','12px','14px','16px','18px','20px','24px','28px','32px','36px','48px'];
  var FONTS  = ['sans-serif','serif','monospace'];

  var toolbarFull = [
    [{ font: FONTS }, { size: SIZES }],
    [{ header: [1, 2, 3, 4, false] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ color: [] }, { background: [] }],
    [{ list: 'ordered' }, { list: 'bullet' }],
    [{ align: [] }],
    ['blockquote', 'code-block'],
    ['link', 'image'],
    ['clean']
  ];

  var toolbarCompact = [
    [{ font: FONTS }, { size: SIZES }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ color: [] }, { background: [] }],
    [{ list: 'ordered' }, { list: 'bullet' }],
    ['link', 'image'],
    ['clean']
  ];

  function imageHandler(quill) {
    var fileInput = document.createElement('input');
    fileInput.setAttribute('type', 'file');
    fileInput.setAttribute('accept', 'image/*');
    fileInput.click();
    fileInput.onchange = function() {
      var file = fileInput.files[0];
      if (!file) return;
      var reader = new FileReader();
      reader.onload = function(e) {
        var range = quill.getSelection(true);
        quill.insertEmbed(range ? range.index : 0, 'image', e.target.result, Quill.sources.USER);
      };
      reader.readAsDataURL(file);
    };
  }

  function makeEditor(wrapperId, inputName, toolbar, largeClass) {
    var wrapper = document.getElementById(wrapperId);
    if (!wrapper) return;
    if (largeClass) wrapper.classList.add('ql-editor-lg');

    var input = document.querySelector('input[name="' + inputName + '"]');
    var initial = input ? input.value : '';

    var quill = new Quill(wrapper, {
      theme: 'snow',
      modules: {
        toolbar: {
          container: toolbar,
          handlers: {
            image: function() { imageHandler(quill); }
          }
        }
      }
    });

    /* Pre-load existing HTML */
    if (initial && initial.trim()) {
      quill.clipboard.dangerouslyPasteHTML(0, initial);
    }

    /* Sync to hidden input on every keystroke */
    quill.on('text-change', function() {
      if (input) input.value = wrapper.querySelector('.ql-editor').innerHTML;
    });

    /* Final sync on form submit */
    var form = wrapper.closest('form');
    if (form && !form._qlBound) {
      form._qlBound = true;
      form.addEventListener('submit', function() {
        document.querySelectorAll('[id^="quill-"]').forEach(function(el) {
          var name = el.id.replace('quill-', '').replace(/-/g, '_');
          var inp  = document.querySelector('input[name="' + name + '"]');
          var ed   = el.querySelector('.ql-editor');
          if (inp && ed) inp.value = ed.innerHTML;
        });
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function() {
    makeEditor('quill-short-desc',    'short_desc',    toolbarCompact, false);
    makeEditor('quill-description',   'description',   toolbarFull,    true);
    makeEditor('quill-return-policy', 'return_policy', toolbarCompact, false);
    makeEditor('quill-shipping-info', 'shipping_info', toolbarCompact, false);

    /* ── Color variant rows ── */
    var colorList  = document.getElementById('color-variants-list');
    var countInput = document.getElementById('color_variant_count');
    var addBtn     = document.getElementById('add-color-variant');

    function getNextIndex() {
      var rows = colorList ? colorList.querySelectorAll('.color-variant-row') : [];
      return rows.length;
    }

    if (addBtn && colorList) {
      addBtn.addEventListener('click', function() {
        var idx = getNextIndex();
        var row = document.createElement('div');
        row.className = 'color-variant-row d-flex gap-2 align-items-end mb-2';
        row.innerHTML =
          '<div style="flex:2"><input type="text" name="color_variants['+idx+'][name]" class="form-control form-control-sm" placeholder="Color name"></div>' +
          '<div style="flex:0 0 44px"><input type="color" name="color_variants['+idx+'][hex]" class="form-control form-control-color form-control-sm w-100" value="#cccccc"></div>' +
          '<div style="flex:3"><input type="file" name="color_variant_images[]" class="form-control form-control-sm" accept="image/*"></div>' +
          '<button type="button" class="btn btn-sm btn-outline-danger remove-color-row" style="flex:0 0 auto"><i class="bi bi-trash"></i></button>';
        colorList.appendChild(row);
        if (countInput) countInput.value = getNextIndex();
      });
      colorList.addEventListener('click', function(e) {
        var btn = e.target.closest('.remove-color-row');
        if (btn) { btn.closest('.color-variant-row').remove(); if (countInput) countInput.value = getNextIndex(); }
      });
    }
  });
})();
</script>
JS;
    }

    public function create_razorpay_payment_link() {
        $amount = (float)($this->input->post('amount') ?? 0);
        $product_name = trim((string)$this->input->post('product_name', TRUE));
        $product_id = (int)$this->input->post('product_id');

        if ($amount <= 0) {
            $this->json(['success' => false, 'message' => 'Please provide a valid payment amount greater than 0.'], 400);
            return;
        }

        $settings = $this->Sk_Admin_model->get_settings();
        $keyId = trim((string)($settings['razorpay_key_id'] ?? ''));
        $keySecret = trim((string)($settings['razorpay_key_secret'] ?? ''));

        if ($keyId === '' || $keySecret === '') {
            $this->config->load('ecommerce', true);
            $cfg = $this->config->item('ecommerce');
            if (empty($keyId)) {
                $keyId = trim((string)($cfg['razorpay_key_id'] ?? ''));
            }
            if (empty($keySecret)) {
                $keySecret = trim((string)($cfg['razorpay_key_secret'] ?? ''));
            }
        }

        if ($keyId === '' || $keySecret === '') {
            $this->json([
                'success' => false,
                'message' => 'Razorpay API credentials (Key ID and Secret) are missing. Please configure them in Settings.'
            ], 400);
            return;
        }

        $currency = trim((string)($settings['currency'] ?? 'INR'));
        if ($currency === 'RM' || $currency === 'MYR') {
            $currency = 'MYR';
        } elseif (empty($currency) || $currency === '₹') {
            $currency = 'INR';
        }

        $amountInPaise = (int)round($amount * 100);
        $description = !empty($product_name) ? 'Payment for ' . $product_name : 'Product Payment';
        $description = mb_substr($description, 0, 250);

        $payload = [
            'amount'          => $amountInPaise,
            'currency'        => $currency,
            'accept_partial'  => false,
            'description'     => $description,
            'reference_id'    => 'prod_' . ($product_id > 0 ? $product_id : 'new') . '_' . time(),
            'notify'          => [
                'sms'      => false,
                'email'    => false,
                'whatsapp' => false,
            ],
            'reminder_enable' => false,
            'notes'           => [
                'product_id'   => (string)$product_id,
                'product_name' => mb_substr($product_name, 0, 50),
            ],
        ];

        $ch = curl_init('https://api.razorpay.com/v1/payment_links');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $keyId . ':' . $keySecret,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 25,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $this->json(['success' => false, 'message' => 'Network error connecting to Razorpay: ' . $curlError], 500);
            return;
        }

        $res = json_decode((string)$response, true);
        if ($httpCode >= 200 && $httpCode < 300 && !empty($res['short_url'])) {
            $this->json([
                'success'      => true,
                'payment_link' => $res['short_url'],
                'plink_id'     => $res['id'] ?? '',
                'amount'       => $amount,
                'currency'     => $currency,
                'message'      => 'Razorpay payment link created successfully!',
            ]);
            return;
        }

        $errMsg = $res['error']['description'] ?? ($res['message'] ?? 'Failed to create Razorpay payment link. HTTP ' . $httpCode);
        $this->json(['success' => false, 'message' => $errMsg], 400);
    }
}
