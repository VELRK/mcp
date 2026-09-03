<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/api/Sk_Base_Api.php';

class Sk_Cart extends Sk_Base_Api {

    private function _cart_key() {
        $user = $this->sk_jwt->get_user_from_request();
        return $user
            ? ['user_id' => $user['user_id'], 'session_id' => null]
            : ['user_id' => null, 'session_id' => $this->input->get_request_header('X-Session-ID') ?? session_id()];
    }

    private function _resolve_variant($product, $variant_id = null) {
        $this->load->model('Sk_Product_variant_model');
        if ($variant_id) {
            $variant = $this->Sk_Product_variant_model->get_by_id((int)$variant_id);
            if (!$variant || (int)$variant['product_id'] !== (int)$product['id']) return null;
            return $variant;
        }
        if (!empty($product['variants'])) {
            foreach ($product['variants'] as $v) {
                if (!empty($v['is_default'])) return $v;
            }
            return $product['variants'][0];
        }
        return null;
    }

    private function _apply_cart_filters($key, $product_id, $variant_id = null) {
        $this->db->where(array_filter($key));
        $this->db->where('product_id', (int)$product_id);
        if ($this->db->field_exists('variant_id', 'cart')) {
            if ($variant_id) {
                $this->db->where('variant_id', (int)$variant_id);
            } else {
                $this->db->where('variant_id IS NULL', null, false);
            }
        }
    }

    private function _find_cart_row($key, $product_id, $variant_id = null) {
        $this->_apply_cart_filters($key, $product_id, $variant_id);
        return $this->db->get('cart')->row_array();
    }

    private function _stock_label(array $product, $variant = null): string {
        $name = (string)($product['name'] ?? 'Product');
        $label = is_array($variant) ? trim((string)($variant['label'] ?? '')) : '';
        return $label !== '' ? "{$name} ({$label})" : $name;
    }

    private function _stock_error(array $product, $variant, int $need, int $available) {
        $title = $this->_stock_label($product, $variant);
        if ($available <= 0) {
            $msg = "'{$title}' is out of stock. Please remove it from your cart.";
        } else {
            $msg = "Not enough stock for '{$title}'. Available: {$available}, requested: {$need}.";
        }
        return $this->error($msg, 400, [
            'stock_issues' => [[
                'product_id' => (int)($product['id'] ?? 0),
                'variant_id' => is_array($variant) ? (int)($variant['id'] ?? 0) ?: null : null,
                'name'       => $title,
                'available'  => max(0, $available),
                'requested'  => $need,
            ]],
        ]);
    }

    public function index() {
        $key   = $this->_cart_key();
        $items = $this->_get_cart_items($key);
        $summary = $this->_summary($items);
        $payload = [
            'items'   => $items,
            'summary' => $summary,
        ];

        // When cart is below free-delivery threshold, include same-variant / same-category suggestions
        $include = $this->input->get('include_suggestions');
        $wantSuggestions = ($include === null || $include === '' || $include === '1' || $include === 'true');
        if ($wantSuggestions && !empty($items) && empty($summary['free_delivery']['eligible'])) {
            $limit = min(24, max(1, (int)($this->input->get('suggestions_limit') ?? 12)));
            $payload['suggestions'] = $this->_build_suggestions($items, $limit);
        } else {
            $payload['suggestions'] = [
                'products' => [],
                'based_on' => [],
                'title'    => null,
                'reason'   => !empty($summary['free_delivery']['eligible'])
                    ? 'free_delivery_already_eligible'
                    : (empty($items) ? 'empty_cart' : 'skipped'),
            ];
        }

        $this->success($payload);
    }

    /**
     * GET /shopkart-api/cart/suggestions
     * Suggest other products in the same category that share the cart line's pack size.
     * Includes free_delivery context so mobile can show “add more for free delivery”.
     */
    public function suggestions() {
        $key   = $this->_cart_key();
        $items = $this->_get_cart_items($key);
        $limit = min(24, max(1, (int)($this->input->get('limit') ?? 12)));
        $summary = $this->_summary($items);
        $block = $this->_build_suggestions($items, $limit);
        $block['free_delivery'] = $summary['free_delivery'] ?? null;
        $this->success($block);
    }

    /**
     * Build same-variant (preferred) / same-category suggestion products with full detail.
     */
    private function _build_suggestions(array $items, int $limit = 12): array {
        if (empty($items)) {
            return [
                'products' => [],
                'based_on' => [],
                'title'    => null,
                'reason'   => 'empty_cart',
            ];
        }

        $exclude = [];
        $seeds = [];
        $based_on = [];
        foreach ($items as $item) {
            $pid = (int)($item['product_id'] ?? 0);
            if ($pid > 0) {
                $exclude[] = $pid;
            }
            $seed = [
                'category_id'   => (int)($item['category_id'] ?? 0),
                'product_id'    => $pid,
                'unit_id'       => isset($item['unit_id']) ? (int)$item['unit_id'] : 0,
                'unit_value'    => $item['unit_value'] ?? null,
                'variant_label' => $item['variant_label'] ?? ($item['unit_label'] ?? null),
            ];
            $seeds[] = $seed;
            $based_on[] = [
                'product_id'    => $pid,
                'product_name'  => $item['product_name'] ?? $item['name'] ?? null,
                'category_id'   => $seed['category_id'] ?: null,
                'category_name' => $item['category_name'] ?? null,
                'variant_id'    => $item['variant_id'] ?? null,
                'variant_label' => $seed['variant_label'],
                'unit_id'       => $seed['unit_id'] ?: null,
                'unit_value'    => $seed['unit_value'],
            ];
        }

        $products = $this->Sk_Product_model->get_cart_suggestions($seeds, $exclude, $limit, true);
        $pack = '';
        foreach ($based_on as $b) {
            if (!empty($b['variant_label'])) {
                $pack = (string)$b['variant_label'];
                break;
            }
        }

        return [
            'products' => $products,
            'based_on' => $based_on,
            'title'    => $pack !== ''
                ? ('More ' . $pack . ' packs in this category')
                : 'Similar products in this category',
            'reason'   => 'same_variant_same_category',
        ];
    }

    public function add() {
        $data        = $this->body();
        $product_id  = (int)($data['product_id'] ?? 0);
        $variant_id  = !empty($data['variant_id']) ? (int)$data['variant_id'] : null;
        $quantity    = max(1, (int)($data['quantity'] ?? 1));

        $product = $this->Sk_Product_model->get_by_id($product_id);
        if (!$product || $product['status'] !== 'active') return $this->error('Product not found.');

        $variant = $this->_resolve_variant($product, $variant_id);
        $stock = $variant ? (int)$variant['stock'] : (int)$product['stock'];
        if ($stock < $quantity) {
            return $this->_stock_error($product, $variant, $quantity, $stock);
        }

        $key = $this->_cart_key();
        $existing = $this->_find_cart_row($key, $product_id, $variant ? (int)$variant['id'] : null);

        if ($existing) {
            $new_qty = $existing['quantity'] + $quantity;
            if ($stock < $new_qty) {
                return $this->_stock_error($product, $variant, $new_qty, $stock);
            }
            $this->db->where('id', $existing['id'])->update('cart', ['quantity' => $new_qty]);
        } else {
            $insert = array_filter($key) + [
                'product_id' => $product_id,
                'quantity'   => $quantity,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            if ($this->db->field_exists('variant_id', 'cart') && $variant) {
                $insert['variant_id'] = (int)$variant['id'];
            }
            $this->db->insert('cart', $insert);
        }

        $items = $this->_get_cart_items($key);
        $this->success(['items' => $items, 'summary' => $this->_summary($items)], 'Added to cart.');
    }

    public function update() {
        $data        = $this->body();
        $product_id  = (int)($data['product_id'] ?? 0);
        $variant_id  = !empty($data['variant_id']) ? (int)$data['variant_id'] : null;
        $quantity    = (int)($data['quantity'] ?? 0);
        $key         = $this->_cart_key();

        $product = $this->Sk_Product_model->get_by_id($product_id);
        if (!$product) return $this->error('Product not found.');
        // Resolve omitted variant_id to default so we match the stored cart row
        $variant = $this->_resolve_variant($product, $variant_id);
        $resolvedVid = $variant ? (int)$variant['id'] : null;

        if ($quantity <= 0) {
            $this->_apply_cart_filters($key, $product_id, $resolvedVid);
            $this->db->delete('cart');
        } else {
            $stock = $variant ? (int)$variant['stock'] : (int)$product['stock'];
            if ($stock < $quantity) {
                return $this->_stock_error($product, $variant, $quantity, $stock);
            }
            $existing = $this->_find_cart_row($key, $product_id, $resolvedVid);
            if (!$existing) {
                return $this->error('Cart item not found.');
            }
            $this->db->where('id', $existing['id'])->update('cart', ['quantity' => $quantity]);
        }

        $items = $this->_get_cart_items($key);
        $this->success(['items' => $items, 'summary' => $this->_summary($items)]);
    }

    public function remove() {
        $data        = $this->body();
        $product_id  = (int)($data['product_id'] ?? 0);
        $variant_id  = !empty($data['variant_id']) ? (int)$data['variant_id'] : null;
        $key         = $this->_cart_key();

        $product = $this->Sk_Product_model->get_by_id($product_id);
        if ($product) {
            $variant = $this->_resolve_variant($product, $variant_id);
            $resolvedVid = $variant ? (int)$variant['id'] : null;
            $this->_apply_cart_filters($key, $product_id, $resolvedVid);
            $this->db->delete('cart');
            // Fallback: default-variant resolve can miss NULL / other variant rows.
            if ((int)$this->db->affected_rows() < 1) {
                $this->db->where(array_filter($key))->where('product_id', $product_id)->delete('cart');
            }
        } else {
            // Product removed from catalog — drop matching cart line(s)
            $this->db->where(array_filter($key))->where('product_id', $product_id);
            if ($this->db->field_exists('variant_id', 'cart') && $variant_id) {
                $this->db->where('variant_id', $variant_id);
            }
            $this->db->delete('cart');
            if ((int)$this->db->affected_rows() < 1) {
                $this->db->where(array_filter($key))->where('product_id', $product_id)->delete('cart');
            }
        }

        $items = $this->_get_cart_items($key);
        $this->success(['items' => $items, 'summary' => $this->_summary($items)], 'Removed from cart.');
    }

    public function clear() {
        $key = $this->_cart_key();
        $this->db->where(array_filter($key))->delete('cart');
        $this->success([], 'Cart cleared.');
    }

    /**
     * GET /shopkart-api/cart/products
     * Alias for mobile apps: returns cart line items with full product details + summary.
     * Same payload as GET /cart (without auto-suggestions unless requested).
     */
    public function products() {
        $key   = $this->_cart_key();
        $items = $this->_get_cart_items($key);
        $this->success([
            'items'   => $items,
            'summary' => $this->_summary($items),
        ]);
    }

    /**
     * POST /shopkart-api/cart/merge
     * JWT required. Merges guest X-Session-ID cart into the logged-in user cart.
     * Body optional: { session_id } — otherwise uses X-Session-ID header.
     */
    public function merge() {
        $user = $this->auth_required();
        $userId = (int)($user['user_id'] ?? 0);
        if ($userId < 1) {
            return $this->error('Login required.', 401);
        }

        $body = $this->body();
        $sessionId = trim((string)($body['session_id']
            ?? $this->input->get_request_header('X-Session-ID')
            ?? ''));
        if ($sessionId === '') {
            return $this->error('session_id or X-Session-ID required.');
        }

        $guestRows = $this->db->where('session_id', $sessionId)
            ->where('(user_id IS NULL OR user_id = 0)', null, false)
            ->get('cart')->result_array();

        $merged = 0;
        foreach ($guestRows as $row) {
            $productId = (int)$row['product_id'];
            $variantId = !empty($row['variant_id']) ? (int)$row['variant_id'] : null;
            $qty = max(1, (int)$row['quantity']);

            $userKey = ['user_id' => $userId, 'session_id' => null];
            $existing = $this->_find_cart_row($userKey, $productId, $variantId);
            if ($existing) {
                $this->db->where('id', (int)$existing['id'])
                    ->update('cart', ['quantity' => (int)$existing['quantity'] + $qty]);
                $this->db->where('id', (int)$row['id'])->delete('cart');
            } else {
                $this->db->where('id', (int)$row['id'])->update('cart', [
                    'user_id'    => $userId,
                    'session_id' => null,
                ]);
            }
            $merged++;
        }

        $items = $this->_get_cart_items(['user_id' => $userId, 'session_id' => null]);
        $this->success([
            'merged'  => $merged,
            'items'   => $items,
            'summary' => $this->_summary($items),
        ], $merged > 0 ? 'Guest cart merged.' : 'Nothing to merge.');
    }

    private function _get_cart_items($key) {
        $where = array_filter($key);
        $rows  = $this->db->where($where)->get('cart')->result_array();
        $items = [];
        foreach ($rows as $row) {
            $p = $this->Sk_Product_model->get_by_id($row['product_id']);
            if (!$p) continue;

            $variant = null;
            if (!empty($row['variant_id'])) {
                $variant = $this->_resolve_variant($p, (int)$row['variant_id']);
            } elseif (!empty($p['variants'])) {
                $variant = $this->_resolve_variant($p, null);
            }

            // get_by_id / attach_variants overwrites product stock & thumbnail with default variant
            $productName = (string)($p['name'] ?? '');
            $productThumb = null;
            $thumbRow = $this->db->select('thumbnail, stock')->where('id', (int)$p['id'])->get('products')->row_array();
            if ($thumbRow) {
                $productThumb = $thumbRow['thumbnail'] ?? null;
            }

            $price = $variant ? (float)$variant['price'] : (float)$p['price'];
            $sale  = $variant ? ($variant['sale_price'] ?? null) : ($p['sale_price'] ?? null);
            $effective = ($sale && $sale > 0 && $sale < $price) ? (float)$sale : $price;
            $label = $variant['label'] ?? ($p['unit_label'] ?? null);
            $thumb = (!empty($variant['image']) ? $variant['image'] : null) ?: $productThumb ?: ($p['thumbnail'] ?? null);
            $sku   = !empty($variant['sku']) ? $variant['sku'] : ($p['sku'] ?? null);

            $items[] = [
                'cart_id'         => $row['id'],
                'product_id'      => (int)$p['id'],
                'variant_id'      => isset($variant['id']) ? (int)$variant['id'] : null,
                'variant_label'   => $label,
                'unit_label'      => $label,
                'unit_id'         => isset($variant['unit_id']) ? (int)$variant['unit_id'] : null,
                'unit_name'       => $variant['unit_name'] ?? null,
                'unit_symbol'     => $variant['unit_symbol'] ?? null,
                'unit_value'      => isset($variant['unit_value']) ? (float)$variant['unit_value'] : null,
                'product_name'    => $productName,
                'name'            => $label ? ($productName . ' (' . $label . ')') : $productName,
                'category_id'     => isset($p['category_id']) ? (int)$p['category_id'] : null,
                'category_name'   => $p['category_name'] ?? null,
                'sku'             => $sku,
                'thumbnail'       => $thumb,
                'slug'            => $p['slug'] ?? null,
                'price'           => $price,
                'sale_price'      => $sale,
                'effective_price' => $effective,
                'stock'           => $variant ? (int)$variant['stock'] : (int)($thumbRow['stock'] ?? $p['stock'] ?? 0),
                'quantity'        => (int)$row['quantity'],
                'subtotal'        => round($effective * $row['quantity'], 2),
                'created_at'      => $row['created_at'] ?? null,
                'added_at'        => $row['created_at'] ?? null,
            ];
        }
        return $items;
    }

    private function _summary($items) {
        $subtotal = array_sum(array_column($items, 'subtotal'));
        $settings = $this->get_settings();
        $threshold = (float)($settings['free_shipping_above'] ?? 999);
        $shipCharge = (float)($settings['shipping_charge'] ?? 50);
        $eligible = $subtotal > 0 && $subtotal >= $threshold;
        // Empty cart: no shipping charge (avoid RM50 total with Subtotal RM0)
        $shipping = ($subtotal <= 0 || empty($items))
            ? 0
            : ($eligible ? 0 : $shipCharge);
        // Storefront does not charge/show GST
        $tax      = 0;
        $amountToFree = ($subtotal <= 0 || $eligible)
            ? 0.0
            : round(max(0, $threshold - $subtotal), 2);

        $summary = [
            'subtotal'     => round($subtotal, 2),
            'shipping'     => (float)$shipping,
            'tax'          => $tax,
            'discount'     => 0,
            'total'        => round($subtotal + $shipping + $tax, 2),
            'item_count'   => array_sum(array_column($items, 'quantity')),
            'free_delivery' => [
                'eligible'           => $eligible,
                'threshold'          => $threshold,
                'shipping_charge'    => $shipCharge,
                'amount_remaining'   => $amountToFree,
                'currency'           => sk_currency_symbol($settings),
                'message'            => empty($items)
                    ? null
                    : ($eligible
                        ? 'You qualify for free delivery.'
                        : ('Add ' . sk_currency_symbol($settings) . number_format($amountToFree, 2)
                            . ' more for free delivery.')),
            ],
        ];

        return $summary;
    }
}
