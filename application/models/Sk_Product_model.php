<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sk_Product_model extends CI_Model {

    /** @var Sk_Product_variant_model|null */
    private $_variant_model = null;
    private static $schema_ready = false;

    /** Ensure columns used by admin product forms exist (idempotent). */
    public function ensure_schema(): void {
        if (self::$schema_ready) {
            return;
        }
        if (!$this->db->table_exists('products')) {
            return;
        }
        if (!$this->db->field_exists('payment_link', 'products')) {
            $this->db->query("ALTER TABLE `products`
                ADD COLUMN `payment_link` VARCHAR(500) NULL DEFAULT NULL AFTER `thumbnail`");
        }
        self::$schema_ready = true;
    }

    private function variant_model() {
        if ($this->_variant_model === null) {
            $this->load->model('Sk_Product_variant_model');
            $this->_variant_model = $this->Sk_Product_variant_model;
        }
        return $this->_variant_model;
    }

    // ── Get paginated product list with filters ──────────────────────────────
    public function get_all($filters = [], $limit = 20, $offset = 0) {
        $this->db->select('p.*, c.name as category_name, sc.name as subcategory_name, b.name as brand_name')
                 ->from('products p')
                 ->join('categories c', 'c.id = p.category_id', 'left')
                 ->join('subcategories sc', 'sc.id = p.subcategory_id', 'left')
                 ->join('brands b', 'b.id = p.brand_id', 'left');

        if (!empty($filters['category_id'])) {
            $this->db->where('p.category_id', $filters['category_id']);
        }
        if (!empty($filters['subcategory_id'])) {
            $this->db->where('p.subcategory_id', $filters['subcategory_id']);
        }
        if (!empty($filters['brand_id'])) {
            $this->db->where('p.brand_id', $filters['brand_id']);
        }
        if (!empty($filters['status'])) {
            $this->db->where('p.status', $filters['status']);
        } else {
            $this->db->where('p.status', 'active');
        }
        // Tenant / vendor scope — required for Meta BA + MCP catalog tools.
        if (!empty($filters['vendor_id'])) {
            $this->db->where('p.vendor_id', (int)$filters['vendor_id']);
        }
        if (!empty($filters['featured'])) {
            $this->db->where('p.featured', 1);
        }
        if (!empty($filters['nav_featured'])) {
            $this->db->where('p.nav_featured', 1);
        }
        if (!empty($filters['special_product'])) {
            $this->db->where('p.special_product', 1);
        }
        if (!empty($filters['hot_sale'])) {
            $this->db->where('p.hot_sale', 1);
        }
        if (!empty($filters['search'])) {
            $this->db->group_start()
                     ->like('p.name', $filters['search'])
                     ->or_like('p.sku', $filters['search'])
                     ->or_like('p.tags', $filters['search'])
                     ->or_like('p.saree_type', $filters['search'])
                     ->or_like('p.fabric', $filters['search'])
                     ->or_like('p.color', $filters['search'])
                     ->or_like('p.origin_state', $filters['search'])
                     ->group_end();
        }
        if (!empty($filters['min_price'])) {
            $this->db->where('p.price >=', $filters['min_price']);
        }
        if (!empty($filters['max_price'])) {
            $this->db->where('p.price <=', $filters['max_price']);
        }
        // Saree-specific filters
        if (!empty($filters['fabric']))      $this->db->where('p.fabric', $filters['fabric']);
        if (!empty($filters['saree_type']))  $this->db->where('p.saree_type', $filters['saree_type']);
        if (!empty($filters['occasion']))    $this->db->like('p.occasion', $filters['occasion']);
        if (!empty($filters['color']))       $this->db->like('p.color', $filters['color']);
        if (!empty($filters['work_type']))   $this->db->like('p.work_type', $filters['work_type']);
        if (!empty($filters['blouse_included'])) $this->db->where('p.blouse_included', 1);
        if (!empty($filters['origin_state'])) $this->db->where('p.origin_state', $filters['origin_state']);

        $sort_map = [
            'price_asc'   => 'p.price ASC',
            'price_desc'  => 'p.price DESC',
            'newest'      => 'p.created_at DESC',
            'popular'     => 'p.total_sold DESC',
            'rating'      => 'p.avg_rating DESC',
        ];
        $sort = $sort_map[$filters['sort'] ?? ''] ?? 'p.created_at DESC';
        $this->db->order_by($sort);

        $count_query = $this->db->get_compiled_select('', FALSE);
        $total = $this->db->query('SELECT COUNT(*) as cnt FROM (' . $count_query . ') sub')->row()->cnt;

        $this->db->limit($limit, $offset);
        $products = $this->db->get()->result_array();

        foreach ($products as &$p) {
            $p['images'] = $this->get_images($p['id']);
            $this->_decode_json_fields($p);
            $this->attach_variants($p);
            $this->apply_sale_timing($p);
        }

        return ['data' => $products, 'total' => $total];
    }

    public function get_by_id($id) {
        $product = $this->db->select('p.*, c.name as category_name, b.name as brand_name')
                            ->from('products p')
                            ->join('categories c', 'c.id = p.category_id', 'left')
                            ->join('brands b', 'b.id = p.brand_id', 'left')
                            ->where('p.id', $id)
                            ->get()->row_array();
        if ($product) {
            $product['images'] = $this->get_images($id);
            $product['videos'] = $this->get_videos($id);
            $this->_decode_json_fields($product);
            $this->attach_variants($product);
            $this->apply_sale_timing($product);
        }
        return $product;
    }

    public function get_by_slug($slug) {
        $product = $this->db->select('p.*, c.name as category_name, b.name as brand_name')
                            ->from('products p')
                            ->join('categories c', 'c.id = p.category_id', 'left')
                            ->join('brands b', 'b.id = p.brand_id', 'left')
                            ->where('p.slug', $slug)
                            ->where('p.status', 'active')
                            ->get()->row_array();
        if ($product) {
            $product['images'] = $this->get_images($product['id']);
            $product['videos'] = $this->get_videos($product['id']);
            $this->_decode_json_fields($product);
            $this->attach_variants($product);
            $this->apply_sale_timing($product);
        }
        return $product;
    }

    public function get_videos($product_id) {
        return $this->db->where('product_id', $product_id)
                        ->order_by('sort_order', 'ASC')
                        ->get('product_videos')->result_array();
    }

    public function _decode_json_fields(&$product) {
        foreach (['features', 'category_attributes', 'colors_json'] as $field) {
            if (!empty($product[$field]) && is_string($product[$field])) {
                $decoded = json_decode($product[$field], true);
                $product[$field] = $decoded !== null ? $decoded : $product[$field];
            }
        }
        if (!empty($product['sizes']) && is_string($product['sizes'])) {
            $product['sizes'] = array_map('trim', explode(',', $product['sizes']));
        }
    }

    public function get_images($product_id) {
        return $this->db->where('product_id', $product_id)
                        ->order_by('sort_order', 'ASC')
                        ->get('product_images')->result_array();
    }

    public function create($data) {
        $data['slug'] = $this->make_unique_slug($data['name'], 'products');
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('products', $data);
        return $this->db->insert_id();
    }

    public function update($id, $data) {
        // Never regenerate slug on update — changing slug breaks existing product URLs.
        // Slug is fixed at creation and stays stable for the product's lifetime.
        unset($data['slug']);
        $this->db->where('id', $id)->update('products', $data);
        return $this->db->affected_rows();
    }

    public function delete($id) {
        $id = (int)$id;
        // Disable FK checks so child rows don't block the parent delete
        $this->db->query('SET FOREIGN_KEY_CHECKS=0');
        // Remove all child rows
        $this->db->where('product_id', $id)->delete('product_images');
        if ($this->db->table_exists('product_variants')) {
            $this->db->where('product_id', $id)->delete('product_variants');
        }
        if ($this->db->table_exists('wishlist')) {
            $this->db->where('product_id', $id)->delete('wishlist');
        }
        if ($this->db->table_exists('cart')) {
            $this->db->where('product_id', $id)->delete('cart');
        }
        if ($this->db->table_exists('product_reviews')) {
            $this->db->where('product_id', $id)->delete('product_reviews');
        }
        if ($this->db->table_exists('sk_testimonials')) {
            $this->db->where('product_id', $id)->delete('sk_testimonials');
        }
        // Delete the product itself
        $this->db->where('id', $id)->delete('products');
        $affected = $this->db->affected_rows();
        $this->db->query('SET FOREIGN_KEY_CHECKS=1');
        return $affected;
    }

    public function save_images($product_id, $images) {
        foreach ($images as $img) {
            $this->db->insert('product_images', [
                'product_id' => $product_id,
                'image'      => $img['image'],
                'alt'        => $img['alt'] ?? '',
                'sort_order' => $img['sort_order'] ?? 0,
            ]);
        }
    }

    public function delete_image($image_id, $product_id) {
        return $this->db->where(['id' => $image_id, 'product_id' => $product_id])
                        ->delete('product_images');
    }

    public function reduce_stock($product_id, $qty, $variant_id = null) {
        $qty = (int)$qty;
        if ($qty <= 0 || !(int)$product_id) {
            return;
        }
        if ($variant_id && $this->variant_model()->schema_ready()) {
            $this->db->set('stock', 'GREATEST(stock - ' . $qty . ', 0)', FALSE)
                     ->where('id', (int)$variant_id)
                     ->where('product_id', (int)$product_id)
                     ->update('product_variants');
        }
        $this->db->set('stock', 'GREATEST(stock - ' . $qty . ', 0)', FALSE)
                 ->set('total_sold', 'total_sold + ' . $qty, FALSE)
                 ->where('id', (int)$product_id)
                 ->update('products');

        // Keep product stock aligned with variants when packs exist
        $this->sync_product_stock_from_variants((int)$product_id);
    }

    public function restore_stock($product_id, $qty, $variant_id = null) {
        $qty = (int)$qty;
        if ($qty <= 0 || !(int)$product_id) {
            return;
        }
        if ($variant_id && $this->variant_model()->schema_ready()) {
            $this->db->set('stock', 'stock + ' . $qty, FALSE)
                     ->where('id', (int)$variant_id)
                     ->where('product_id', (int)$product_id)
                     ->update('product_variants');
        }
        $this->db->set('stock', 'stock + ' . $qty, FALSE)
                 ->set('total_sold', 'GREATEST(total_sold - ' . $qty . ', 0)', FALSE)
                 ->where('id', (int)$product_id)
                 ->update('products');

        $this->sync_product_stock_from_variants((int)$product_id);
    }

    /**
     * If product has variants, set products.stock = sum of variant stocks.
     * Also clamps any leftover negatives to 0.
     */
    public function sync_product_stock_from_variants(int $product_id): void {
        if ($product_id <= 0 || !$this->variant_model()->schema_ready()) {
            return;
        }
        if (!$this->db->table_exists('product_variants')) {
            return;
        }
        $sum = $this->db->select_sum('stock')
            ->where('product_id', $product_id)
            ->get('product_variants')
            ->row();
        if ($sum === null) {
            return;
        }
        // Only sync when at least one variant row exists
        $count = (int)$this->db->where('product_id', $product_id)->count_all_results('product_variants');
        if ($count < 1) {
            // Still clamp product-level negatives
            $this->db->set('stock', 'GREATEST(stock, 0)', FALSE)
                     ->where('id', $product_id)
                     ->where('stock <', 0)
                     ->update('products');
            return;
        }
        $total = max(0, (int)($sum->stock ?? 0));
        $this->db->where('id', $product_id)->update('products', ['stock' => $total]);
    }

    /** Clamp all negative product/variant stocks to 0 (repair bad data). @return int rows touched */
    public function clamp_negative_stocks(): int {
        $n = 0;
        $this->db->set('stock', 0)->where('stock <', 0)->update('products');
        $n += (int)$this->db->affected_rows();
        if ($this->variant_model()->schema_ready() && $this->db->table_exists('product_variants')) {
            $this->db->set('stock', 0)->where('stock <', 0)->update('product_variants');
            $n += (int)$this->db->affected_rows();
            // Re-sync products that have variants
            $ids = $this->db->select('product_id')->group_by('product_id')->get('product_variants')->result_array();
            foreach ($ids as $row) {
                $this->sync_product_stock_from_variants((int)$row['product_id']);
            }
        }
        return $n;
    }

    /**
     * Product is sellable when any pack has stock (or product.stock when no variants).
     * Top-level stock = sum of variant stocks so list/card OOS is not driven by one empty pack.
     */
    public function apply_stock_availability(array &$product): void {
        $variants = $product['variants'] ?? null;
        if (is_array($variants) && !empty($variants)) {
            $total = 0;
            foreach ($variants as $v) {
                $total += max(0, (int)($v['stock'] ?? 0));
            }
            $product['stock'] = $total;
            $product['in_stock'] = $total > 0;
            $product['is_out_of_stock'] = $total <= 0;
            return;
        }
        $stock = max(0, (int)($product['stock'] ?? 0));
        $product['stock'] = $stock;
        $product['in_stock'] = $stock > 0;
        $product['is_out_of_stock'] = $stock <= 0;
    }

    public function attach_variants(&$product) {
        if (empty($product['id'])) return;
        $variants = $this->variant_model()->get_by_product($product['id']);
        $product['variants'] = $variants;

        if (!empty($variants)) {
            $default = null;
            foreach ($variants as $v) {
                if (!empty($v['is_default'])) { $default = $v; break; }
            }
            if (!$default) $default = $variants[0];

            // Display / quick-add: prefer an in-stock pack when configured default is empty
            $display = $default;
            if ((int)($default['stock'] ?? 0) <= 0) {
                foreach ($variants as $v) {
                    if ((int)($v['stock'] ?? 0) > 0) {
                        $display = $v;
                        break;
                    }
                }
            }

            foreach ($variants as &$v) {
                $v['is_default'] = ((int)$v['id'] === (int)$display['id']) ? 1 : 0;
            }
            unset($v);
            $product['variants'] = $variants;

            $product['default_variant_id'] = (int)$display['id'];
            $product['unit_label'] = $display['label'];
            $product['unit_name'] = $display['unit_name'] ?? '';
            $product['unit_symbol'] = $display['unit_symbol'] ?? '';
            $product['unit_value'] = $display['unit_value'] ?? 1;
            $product['price'] = (float)$display['price'];
            $product['sale_price'] = $display['sale_price'] ?? null;
            $product['default_variant_stock'] = (int)($display['stock'] ?? 0);
            if (!empty($display['sku'])) $product['sku'] = $display['sku'];
            // Keep products.thumbnail as the catalogue main image; pack photos stay on variants.
            if (empty($product['thumbnail']) && !empty($display['image'])) {
                $product['thumbnail'] = $display['image'];
            }
            $product['variant_count'] = count($variants);
            $this->apply_stock_availability($product);
        } else {
            $product['variants'] = [];
            $product['variant_count'] = 0;
            $this->apply_stock_availability($product);
        }
    }

    public function get_related($product_id, $category_id, $limit = 6) {
        $rows = $this->db->where('category_id', $category_id)
                        ->where('id !=', $product_id)
                        ->where('status', 'active')
                        ->limit($limit)
                        ->get('products')->result_array();
        foreach ($rows as &$row) {
            $this->_decode_json_fields($row);
            $this->attach_variants($row);
            $this->apply_sale_timing($row);
        }
        return $rows;
    }

    /**
     * Cart page suggestions: other products in the same category that share a
     * matching pack variant (unit_id + unit_value, or same label). Falls back
     * to same-category products when no variant match is found.
     *
     * @param array $seeds Each: category_id, product_id, unit_id?, unit_value?, label?
     * @param int[] $exclude_ids Product IDs already in cart
     * @param int $limit
     * @param bool $full_detail Reload each hit via get_by_id (images, videos, all variants)
     */
    public function get_cart_suggestions(array $seeds, array $exclude_ids = [], $limit = 12, $full_detail = false) {
        $limit = max(1, min(24, (int)$limit));
        $exclude_ids = array_values(array_unique(array_filter(array_map('intval', $exclude_ids))));
        $category_ids = [];
        $variant_keys = [];

        foreach ($seeds as $s) {
            $cid = (int)($s['category_id'] ?? 0);
            if ($cid > 0) {
                $category_ids[$cid] = $cid;
            }
            $uid = (int)($s['unit_id'] ?? 0);
            $uval = array_key_exists('unit_value', $s) && $s['unit_value'] !== null && $s['unit_value'] !== ''
                ? (float)$s['unit_value']
                : null;
            $label = trim((string)($s['label'] ?? $s['variant_label'] ?? $s['unit_label'] ?? ''));
            if ($uid > 0 && $uval !== null) {
                $variant_keys['uv:' . $uid . ':' . $uval] = ['unit_id' => $uid, 'unit_value' => $uval];
            }
            if ($label !== '') {
                $variant_keys['lb:' . mb_strtolower($label)] = ['label' => $label];
            }
        }

        $category_ids = array_values($category_ids);
        if (empty($category_ids)) {
            return [];
        }

        $matched = [];
        $prefer = array_values($variant_keys);

        if ($this->variant_model()->schema_ready() && !empty($prefer)) {
            $this->db->select('p.id, MIN(pv.id) as match_variant_id')
                     ->from('products p')
                     ->join('product_variants pv', 'pv.product_id = p.id AND pv.status = 1', 'inner')
                     ->where('p.status', 'active')
                     ->where_in('p.category_id', $category_ids);

            if (!empty($exclude_ids)) {
                $this->db->where_not_in('p.id', $exclude_ids);
            }

            $this->db->group_start();
            foreach ($prefer as $i => $key) {
                if ($i === 0) {
                    $this->db->group_start();
                } else {
                    $this->db->or_group_start();
                }
                if (isset($key['unit_id'])) {
                    $this->db->where('pv.unit_id', (int)$key['unit_id'])
                             ->where('pv.unit_value', (float)$key['unit_value']);
                } else {
                    $this->db->where('pv.label', $key['label']);
                }
                $this->db->group_end();
            }
            $this->db->group_end();

            $matched = $this->db->group_by('p.id')
                                ->order_by('p.total_sold', 'DESC')
                                ->order_by('p.created_at', 'DESC')
                                ->limit($limit)
                                ->get()->result_array();
        }

        $found_ids = array_map(static function ($r) { return (int)$r['id']; }, $matched);
        $need = $limit - count($matched);

        if ($need > 0) {
            $exclude_fill = array_values(array_unique(array_merge($exclude_ids, $found_ids)));
            $this->db->select('p.id')
                     ->from('products p')
                     ->where('p.status', 'active')
                     ->where_in('p.category_id', $category_ids);
            if (!empty($exclude_fill)) {
                $this->db->where_not_in('p.id', $exclude_fill);
            }
            $fill = $this->db->order_by('p.total_sold', 'DESC')
                             ->order_by('p.created_at', 'DESC')
                             ->limit($need)
                             ->get()->result_array();
            foreach ($fill as $f) {
                $matched[] = ['id' => (int)$f['id'], 'match_variant_id' => 0];
            }
        }

        $out = [];
        foreach ($matched as $hit) {
            $pid = (int)($hit['id'] ?? 0);
            $match_vid = isset($hit['match_variant_id']) ? (int)$hit['match_variant_id'] : 0;
            if ($pid < 1) {
                continue;
            }

            if ($full_detail) {
                $row = $this->get_by_id($pid);
                if (!$row || ($row['status'] ?? '') !== 'active') {
                    continue;
                }
            } else {
                $row = $this->db->select('p.*, c.name as category_name, sc.name as subcategory_name, b.name as brand_name')
                                ->from('products p')
                                ->join('categories c', 'c.id = p.category_id', 'left')
                                ->join('subcategories sc', 'sc.id = p.subcategory_id', 'left')
                                ->join('brands b', 'b.id = p.brand_id', 'left')
                                ->where('p.id', $pid)
                                ->get()->row_array();
                if (!$row) {
                    continue;
                }
                $row['images'] = $this->get_images($pid);
                $this->_decode_json_fields($row);
                $this->attach_variants($row);
                $this->apply_sale_timing($row);
            }

            $preferred = false;
            if ($match_vid > 0 && !empty($row['variants'])) {
                $this->_prefer_variant($row, $match_vid);
                $preferred = true;
            } elseif (!empty($prefer) && !empty($row['variants'])) {
                foreach ($row['variants'] as $v) {
                    foreach ($prefer as $key) {
                        $ok = isset($key['unit_id'])
                            ? ((int)($v['unit_id'] ?? 0) === (int)$key['unit_id']
                                && (float)($v['unit_value'] ?? 0) === (float)$key['unit_value'])
                            : (mb_strtolower(trim((string)($v['label'] ?? ''))) === mb_strtolower(trim($key['label'])));
                        if ($ok) {
                            $this->_prefer_variant($row, (int)$v['id']);
                            $preferred = true;
                            break 2;
                        }
                    }
                }
            }

            $this->apply_sale_timing($row);
            $row['suggestion_reason'] = $preferred ? 'same_variant' : 'same_category';
            $out[] = $row;
        }

        return $out;
    }

    /** Make a specific variant the product default for list/card display. */
    private function _prefer_variant(array &$product, $variant_id) {
        $variant_id = (int)$variant_id;
        if ($variant_id < 1 || empty($product['variants'])) return;
        $chosen = null;
        foreach ($product['variants'] as &$v) {
            $v['is_default'] = ((int)$v['id'] === $variant_id) ? 1 : 0;
            if ((int)$v['id'] === $variant_id) $chosen = $v;
        }
        unset($v);
        if (!$chosen) return;
        $product['default_variant_id'] = $variant_id;
        $product['unit_label'] = $chosen['label'] ?? ($product['unit_label'] ?? null);
        $product['unit_name'] = $chosen['unit_name'] ?? '';
        $product['unit_symbol'] = $chosen['unit_symbol'] ?? '';
        $product['unit_value'] = $chosen['unit_value'] ?? 1;
        $product['price'] = (float)$chosen['price'];
        $product['sale_price'] = $chosen['sale_price'] ?? null;
        $product['default_variant_stock'] = (int)($chosen['stock'] ?? 0);
        if (!empty($chosen['sku'])) $product['sku'] = $chosen['sku'];
        if (empty($product['thumbnail']) && !empty($chosen['image'])) {
            $product['thumbnail'] = $chosen['image'];
        }
        $product['matched_variant_id'] = $variant_id;
        // Keep product-level stock as total across packs (not the preferred pack alone)
        $this->apply_stock_availability($product);
    }

    private function apply_sale_timing(&$product) {
        $now        = date('Y-m-d H:i:s');
        $sale_price = isset($product['sale_price']) ? (float)$product['sale_price'] : null;
        $base_price = isset($product['price'])      ? (float)$product['price']       : 0.0;
        $start_at   = $product['sale_start_at'] ?? null;
        $end_at     = $product['sale_end_at']   ?? null;

        // sale_price is always valid unless it's outside an explicit time window
        $within_window = true;
        if (!empty($start_at) && $now < $start_at) $within_window = false;
        if (!empty($end_at)   && $now > $end_at)   $within_window = false;

        $price_valid = $sale_price !== null && $sale_price > 0 && $sale_price < $base_price;
        $sale_active = $price_valid && $within_window;

        // hot_sale only adds the "HOT SALE" badge / marquee — it does NOT gate the price
        $product['sale_active']    = $sale_active ? 1 : 0;
        $product['effective_price']= $sale_active ? $sale_price : $base_price;

        if (!$sale_active) {
            $product['sale_price'] = null;
        }
    }

    public function count_all_admin(array $filters = []): int {
        $this->db->from('products p');
        $this->_admin_where($filters);
        return (int)$this->db->count_all_results();
    }

    public function get_all_admin($limit, $offset, array $filters = []) {
        $this->db->select('p.*, c.name as category_name, sc.name as subcategory_name, v.business_name as vendor_name')
                 ->from('products p')
                 ->join('categories c', 'c.id = p.category_id', 'left')
                 ->join('subcategories sc', 'sc.id = p.subcategory_id', 'left')
                 ->join('vendors v', 'v.id = p.vendor_id', 'left');
        $this->_admin_where($filters);
        $this->db->order_by('p.created_at', 'DESC')->limit($limit, $offset);
        return $this->db->get()->result_array();
    }

    private function _admin_where(array $filters): void {
        $vendor_id = $filters['vendor_id'] ?? null;
        if ($vendor_id) {
            $this->db->where('p.vendor_id', (int)$vendor_id);
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $this->db->group_start()
                ->like('p.name', $search)
                ->or_like('p.sku', $search)
                ->group_end();
        }

        $category_id = (int)($filters['category_id'] ?? 0);
        if ($category_id > 0) {
            $this->db->where('p.category_id', $category_id);
        }

        $subcategory_id = (int)($filters['subcategory_id'] ?? 0);
        if ($subcategory_id > 0) {
            $this->db->where('p.subcategory_id', $subcategory_id);
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $this->db->where('p.status', $status);
        }

        if (!empty($filters['low_stock'])) {
            if ($this->db->field_exists('low_stock_alert', 'products')) {
                $this->db->where('p.stock <= IFNULL(p.low_stock_alert, 5)', null, false);
            } else {
                $this->db->where('p.stock <=', 5);
            }
        }

        $min_price = $filters['min_price'] ?? '';
        $max_price = $filters['max_price'] ?? '';
        $has_min = $min_price !== '' && $min_price !== null;
        $has_max = $max_price !== '' && $max_price !== null;
        if ($has_min || $has_max) {
            $min = $has_min ? (float)$min_price : null;
            $max = $has_max ? (float)$max_price : null;
            $mainParts = [];
            $varParts = [];
            if ($has_min) {
                $mainParts[] = "COALESCE(NULLIF(p.sale_price, 0), p.price) >= {$min}";
                $varParts[] = "COALESCE(NULLIF(pv.sale_price, 0), pv.price) >= {$min}";
            }
            if ($has_max) {
                $mainParts[] = "COALESCE(NULLIF(p.sale_price, 0), p.price) <= {$max}";
                $varParts[] = "COALESCE(NULLIF(pv.sale_price, 0), pv.price) <= {$max}";
            }
            $mainCond = implode(' AND ', $mainParts);
            $variantSql = $this->db->table_exists('product_variants')
                ? " OR EXISTS (SELECT 1 FROM product_variants pv WHERE pv.product_id = p.id AND " . implode(' AND ', $varParts) . ")"
                : '';
            $this->db->where("(({$mainCond}){$variantSql})", null, false);
        }
    }

    /**
     * Inventory list with stock-focused filters.
     * @return array{rows:array,total:int}
     */
    public function get_inventory_list(array $filters = [], int $limit = 20, int $offset = 0): array {
        $this->db->from('products p');
        $this->_inventory_where($filters);
        $total = (int)$this->db->count_all_results();

        $this->db->select('p.*, c.name as category_name, v.business_name as vendor_name')
            ->from('products p')
            ->join('categories c', 'c.id = p.category_id', 'left')
            ->join('vendors v', 'v.id = p.vendor_id', 'left');
        $this->_inventory_where($filters);
        $this->db->order_by('p.stock', 'ASC')->order_by('p.name', 'ASC')->limit($limit, $offset);
        $rows = $this->db->get()->result_array();
        foreach ($rows as &$row) {
            $this->attach_variants($row);
            // product.stock is now total available (sum of packs when variants exist)
            $row['product_stock'] = (int)($row['stock'] ?? 0);
            $row['variant_stock_total'] = (int)($row['stock'] ?? 0);
        }
        unset($row);
        return ['rows' => $rows, 'total' => $total];
    }

    private function _inventory_where(array $filters): void {
        $vendor_id = $filters['vendor_id'] ?? null;
        if ($vendor_id) {
            $this->db->where('p.vendor_id', (int)$vendor_id);
        }
        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $this->db->group_start()
                ->like('p.name', $search)
                ->or_like('p.sku', $search)
                ->group_end();
        }
        if (!empty($filters['low_only'])) {
            $this->db->where('p.stock <= p.low_stock_alert', null, false);
        }
        $categoryId = (int)($filters['category_id'] ?? 0);
        if ($categoryId > 0) {
            $this->db->where('p.category_id', $categoryId);
        }
    }

    private function make_unique_slug($name, $table, $exclude_id = null) {
        $slug = url_title(strtolower($name), '-', TRUE);
        $base = $slug;
        $i = 1;
        while (TRUE) {
            $this->db->where('slug', $slug);
            if ($exclude_id) $this->db->where('id !=', $exclude_id);
            $count = $this->db->count_all_results($table);
            if ($count === 0) break;
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    // Dashboard stats
    public function total_products(?int $vendor_id = null) {
        if ($vendor_id) $this->db->where('vendor_id', $vendor_id);
        return $this->db->count_all_results('products');
    }
    public function low_stock_count() {
        return $this->db->where('stock <=', $this->db->query('SELECT low_stock_alert FROM products LIMIT 1')->row()->low_stock_alert ?? 5)
                        ->count_all_results('products');
    }
}
