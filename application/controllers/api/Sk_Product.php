<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/api/Sk_Base_Api.php';

class Sk_Product extends Sk_Base_Api {

    public function index() {
        $key    = 'products_' . md5(json_encode($_GET));
        $cached = $this->get_cache($key, 60);
        if ($cached !== null) return $this->success($cached);

        $category_id   = $this->input->get('category_id');
        $category_slug = $this->input->get('category_slug', TRUE);
        if (!$category_id && $category_slug) {
            $cat = $this->db->where('slug', $category_slug)->get('categories')->row_array();
            if ($cat) $category_id = $cat['id'];
        }

        $filters = [
            'category_id'    => $category_id,
            'subcategory_id' => $this->input->get('subcategory_id'),
            'brand_id'       => $this->input->get('brand_id'),
            'search'         => $this->input->get('q', TRUE),
            'featured'       => $this->input->get('featured'),
            'nav_featured'   => $this->input->get('nav_featured'),
            'special_product'=> $this->input->get('special_product'),
            'hot_sale'       => $this->input->get('hot_sale'),
            'min_price'      => $this->input->get('min_price'),
            'max_price'      => $this->input->get('max_price'),
            'sort'           => $this->input->get('sort'),
            'status'         => 'active',
            'fabric'         => $this->input->get('fabric', TRUE),
            'saree_type'     => $this->input->get('saree_type', TRUE),
            'occasion'       => $this->input->get('occasion', TRUE),
        ];
        $limit  = min((int)($this->input->get('limit') ?? 20), 100);
        $page   = max(1, (int)($this->input->get('page') ?? 1));
        $offset = ($page - 1) * $limit;

        $result = $this->Sk_Product_model->get_all($filters, $limit, $offset);

        $data = [
            'products'    => $result['data'],
            'total'       => $result['total'],
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => ceil($result['total'] / $limit),
        ];
        $this->set_cache($key, $data);
        $this->success($data);
    }

    public function show($id) {
        $key    = 'product_' . $id;
        $cached = $this->get_cache($key, 60);
        if ($cached !== null) return $this->success($cached);

        $product = is_numeric($id)
            ? $this->Sk_Product_model->get_by_id($id)
            : $this->Sk_Product_model->get_by_slug($id);
        if (!$product || $product['status'] !== 'active') {
            return $this->error('Product not found.', 404);
        }
        $product['related'] = $this->Sk_Product_model->get_related($product['id'], $product['category_id']);

        $this->load->model('Sk_Seo_model');
        $product['seo'] = $this->Sk_Seo_model->format_entity($product, 'product', $this->Sk_Seo_model->get_global_seo());

        $this->set_cache($key, $product);
        $this->success($product);
    }

    public function search() {
        $q = $this->input->get('q', TRUE);
        if (!$q) return $this->error('Search query required.');
        $result = $this->Sk_Product_model->get_all(['search' => $q, 'status' => 'active'], 20, 0);
        $this->success($result['data']);
    }

    /** GET /shopkart-api/products/recommended — Curated for you (special_product). */
    public function recommended() {
        $this->_list_preset(['special_product' => 1], 'newest');
    }

    /** GET /shopkart-api/products/offers — Active product offers from sale_price or hot_sale. */
    public function offers() {
        $limit  = min((int)($this->input->get('limit') ?? 12), 100);
        $page   = max(1, (int)($this->input->get('page') ?? 1));
        $offset = ($page - 1) * $limit;

        $cacheKey = 'products_offers_' . md5(json_encode([$limit, $page]));
        $cached = $this->get_cache($cacheKey, 60);
        if ($cached !== null) {
            return $this->success($cached);
        }

        $filters = ['status' => 'active', 'hot_sale' => 1, 'sort' => 'newest'];
        $result = $this->Sk_Product_model->get_all($filters, $limit, $offset);
        if (empty($result['data'])) {
            $saleFilters = ['status' => 'active', 'sort' => 'newest'];
            $saleResult = $this->Sk_Product_model->get_all($saleFilters, $limit, $offset);
            $result['data'] = array_values(array_filter($saleResult['data'], function ($p) {
                return !empty($p['sale_active']) || !empty($p['sale_price']);
            }));
            $result['total'] = count($result['data']);
        }

        $data = [
            'products'    => $result['data'],
            'total'       => $result['total'],
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => $limit > 0 ? (int)ceil($result['total'] / $limit) : 0,
            'type'        => 'product_offer',
        ];
        $this->set_cache($cacheKey, $data);
        $this->success($data);
    }

    /** GET /shopkart-api/products/combo-offers — Combined bundles built from active product offers. */
    public function combo_offers() {
        $limit  = min((int)($this->input->get('limit') ?? 6), 50);
        $page   = max(1, (int)($this->input->get('page') ?? 1));
        $offset = ($page - 1) * $limit;

        $cacheKey = 'products_combo_offers_' . md5(json_encode([$limit, $page]));
        $cached = $this->get_cache($cacheKey, 60);
        if ($cached !== null) {
            return $this->success($cached);
        }

        $filters = ['status' => 'active', 'sort' => 'newest'];
        $result = $this->Sk_Product_model->get_all($filters, max($limit * 3, $limit), $offset);
        $items = array_values(array_filter($result['data'], static function ($p) {
            return !empty($p['sale_active']) || !empty($p['sale_price']) || !empty($p['hot_sale']) || !empty($p['special_product']);
        }));

        $combo = [];
        foreach (array_slice($items, 0, max(1, $limit)) as $idx => $product) {
            $combo[] = [
                'id' => 'combo-' . ($product['id'] ?? $idx),
                'name' => ($product['name'] ?? 'Combo Offer') . ' Offer',
                'description' => 'Bundle savings from selected offer products.',
                'products' => [
                    ['product_id' => (int)($product['id'] ?? 0), 'name' => $product['name'] ?? 'Product', 'price' => (float)($product['effective_price'] ?? $product['price'] ?? 0)]
                ],
                'offer_price' => (float)($product['effective_price'] ?? $product['price'] ?? 0),
                'original_price' => (float)($product['price'] ?? 0),
                'discount' => (float)(($product['price'] ?? 0) - ($product['effective_price'] ?? $product['price'] ?? 0)),
                'type' => 'combo',
            ];
        }

        $data = [
            'offers'      => $combo,
            'total'       => count($combo),
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => $limit > 0 ? (int)ceil(count($combo) / $limit) : 0,
            'type'        => 'combo_offer',
        ];
        $this->set_cache($cacheKey, $data);
        $this->success($data);
    }

    /** GET /shopkart-api/products/new-arrivals — New Arrival (featured). */
    public function new_arrivals() {
        $this->_list_preset(['featured' => 1], 'newest');
    }

    /** GET /shopkart-api/products/top-selling */
    public function top_selling() {
        $this->_list_preset([], 'popular');
    }

    private function _list_preset(array $extraFilters, string $defaultSort) {
        $limit  = min((int)($this->input->get('limit') ?? 20), 100);
        $page   = max(1, (int)($this->input->get('page') ?? 1));
        $offset = ($page - 1) * $limit;
        $sort   = $this->input->get('sort') ?: $defaultSort;

        $filters = array_merge([
            'status' => 'active',
            'sort'   => $sort,
        ], $extraFilters);

        $cacheKey = 'products_preset_' . md5(json_encode([$filters, $limit, $page]));
        $cached = $this->get_cache($cacheKey, 60);
        if ($cached !== null) {
            return $this->success($cached);
        }

        $result = $this->Sk_Product_model->get_all($filters, $limit, $offset);

        $data = [
            'products'    => $result['data'],
            'total'       => $result['total'],
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => $limit > 0 ? (int)ceil($result['total'] / $limit) : 0,
        ];
        $this->set_cache($cacheKey, $data);
        $this->success($data);
    }
}
