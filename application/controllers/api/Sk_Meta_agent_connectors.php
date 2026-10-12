<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/api/Sk_Base_Api.php';

/**
 * Authenticated commerce tools for Meta Business Agent connectors.
 * Tenant is resolved only from the WhatsApp phone_number_id in the URL.
 */
class Sk_Meta_agent_connectors extends Sk_Base_Api {

    public function __construct() {
        parent::__construct();
        $this->load->helper(['sk_meta_business_agent', 'sk_mcp_tool', 'sk_whatsapp_cloud']);
        $this->load->model(['Sk_Vendor_whatsapp_account_model', 'Sk_Vendor_meta_agent_model', 'Sk_Whatsapp_cloud_model', 'Sk_Product_model']);
        $this->Sk_Vendor_meta_agent_model->ensure_schema();
    }

    private function _require_connector_auth(): void {
        $token = sk_meta_ba_request_token_from_headers();
        if (!sk_meta_ba_connector_auth_ok($token)) {
            $this->error('Invalid or missing connector API key.', 403);
        }
    }

    /**
     * @return array{vendor_id:int,phone_number_id:string}
     */
    private function _resolve_tenant(string $phoneNumberId): array {
        $phoneNumberId = trim($phoneNumberId);
        if ($phoneNumberId === '') {
            $this->error('phone_number_id is required.', 400);
        }
        $acct = $this->Sk_Vendor_whatsapp_account_model->get_by_phone($phoneNumberId);
        if (!$acct || empty($acct['vendor_id'])) {
            $this->error('Unknown or inactive WhatsApp phone_number_id.', 404);
        }
        return [
            'vendor_id'       => (int)$acct['vendor_id'],
            'phone_number_id' => $phoneNumberId,
        ];
    }

    private function _payload(): array {
        $payload = $this->body();
        return is_array($payload) ? $payload : [];
    }

    /**
     * Slim JSON for Meta BA — avoid image paths / huge nested blobs.
     */
    private function _slim_success(array $result, string $okMessage = 'OK'): void {
        if (empty($result['success'])) {
            $err = is_array($result['error'] ?? null) ? $result['error'] : [];
            $this->error(
                (string)($err['message'] ?? 'Tool failed.'),
                400,
                [
                    'code' => (string)($err['code'] ?? 'TOOL_ERROR'),
                    'data' => $result['data'] ?? null,
                ]
            );
        }
        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        // Prefer a short summary string when present for faster Meta replies.
        $this->success($data, $okMessage);
    }

    private function _run_tool(string $phoneNumberId, string $tool): void {
        $this->_require_connector_auth();
        $tenant = $this->_resolve_tenant($phoneNumberId);
        $payload = $this->_payload();
        $payload['phone_number_id'] = $tenant['phone_number_id'];
        if (empty($payload['phone']) && empty($payload['customer_phone'])) {
            // Meta may bind WHATSAPP_PHONE_NUMBER into phone.
        }
        $result = sk_ai_mcp_execute_tool($tool, $payload, [
            'tenant_id' => $tenant['vendor_id'],
            'tenant' => $tenant['vendor_id'],
        ]);
        $this->_slim_success($result, $tool . ' completed.');
    }

    // Explicit methods so Meta path sync stays stable.
    public function identify_customer($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'identify_customer'); }
    public function create_customer($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'create_customer'); }
    public function update_customer($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'update_customer'); }
    public function get_customer($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'get_customer'); }
    public function get_product($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'get_product'); }
    public function get_product_details($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'get_product_details'); }
    public function get_product_price($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'get_product_price'); }
    public function check_stock($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'check_stock'); }
    public function calculate_order_total($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'calculate_order_total'); }
    public function create_order($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'create_order'); }
    public function get_order($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'get_order'); }
    public function create_payment_link($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'create_payment_link'); }
    public function get_payment_status($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'get_payment_status'); }
    public function generate_invoice($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'generate_invoice'); }
    public function handover_to_human($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'handover_to_human'); }
    public function human_handoff($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'human_handoff'); }
    public function get_tenant_config($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'get_tenant_config'); }
    public function save_conversation_state($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'save_conversation_state'); }
    public function get_order_status($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'get_order_status'); }
    public function get_delivery_status($phoneNumberId = '') { $this->_run_tool((string)$phoneNumberId, 'get_delivery_status'); }

    public function search_products($phoneNumberId = '') {
        $this->_require_connector_auth();
        $tenant = $this->_resolve_tenant((string)$phoneNumberId);
        $payload = $this->_payload();
        $query = trim((string)($payload['query'] ?? $payload['search'] ?? $payload['message'] ?? $payload['text'] ?? ''));
        if ($query === '') {
            $this->error('query is required.', 400);
        }
        $vendorId = (int)$tenant['vendor_id'];
        $galleryAsk = function_exists('sk_mcp_tool_is_gallery_request') && sk_mcp_tool_is_gallery_request($query);
        $searchQuery = $query;
        if ($galleryAsk && function_exists('sk_mcp_tool_strip_gallery_words')) {
            $stripped = sk_mcp_tool_strip_gallery_words($query);
            if ($stripped === '') {
                $this->success([
                    'query'    => $query,
                    'count'    => 0,
                    'ui'       => 'none',
                    'summary'  => 'The customer asked for more photos but did not name a product. Ask which product. Do not invent images.',
                    'cards'    => [],
                    'gallery_cards' => [],
                    'products' => [],
                ], 'Product search completed.');
            }
            $searchQuery = $stripped;
        }
        $result = sk_mcp_tool_find_products($searchQuery, ['tenant' => $vendorId], 8);
        $products = $this->_meta_safe_products($result['results'] ?? [], $vendorId);
        if ($galleryAsk && $products) {
            $this->_send_gallery($query, $products[0]);
            return;
        }
        $cards = [];
        $galleryCards = [];
        foreach ($products as $pIndex => $p) {
            $body = $this->_shop_card_body($p);
            $cards[] = [
                'name'          => (string)($p['name'] ?? 'Product'),
                'body'          => $body,
                'image_url'     => (string)($p['image_url'] ?? ''),
                'button_label'  => 'Add to cart',
                'needs_variant' => count($p['variants'] ?? []) > 0,
            ];
            unset($products[$pIndex]['product_url']);
        }
        $count = count($cards);
        $photoCards = [];
        foreach ($cards as $card) {
            if ((string)$card['image_url'] !== '') {
                $photoCards[] = $card;
            }
        }
        $photoCount = count($photoCards);
        $ui = 'none';
        if ($count === 1) {
            $galleryCards = $this->_gallery_cards($products[0]);
            $variantCount = count($products[0]['variants'] ?? []);
            $variantNote = $variantCount > 0
                ? 'Send the variant list in this same turn, under the photos. Button text is Variants. They must choose one before this product can be added. '
                : 'This product has no variants. Add to cart sets quantity to 1. ';
            if (count($galleryCards) >= 2) {
                $cards = $galleryCards;
                $ui = 'product-shop-cards';
                $summary = 'One product with a gallery. Send the product-shop-cards carousel now, one card per gallery photo, 2 to 10 cards. Every card is this same product. Card text is exactly that body: name, price, and stock. Button is Add to cart. ' . $variantNote . 'Quantity starts at 0. Do not create the order. Never send only "See the details below." Do not send a website link.';
            } else {
                $ui = 'product-photo-card';
                $summary = 'Exactly one product. The customer must see these lines: ' . str_replace("\n", ' | ', $cards[0]['body']) . '. '
                    . ($photoCount === 1
                        ? 'Send this one photo with that body as the caption, then one Add to cart button. '
                        : 'There is no WhatsApp photo. Write that body in the text, then one Add to cart button. ')
                    . $variantNote
                    . 'Quantity starts at 0. Add to cart sets quantity to 1. Do not create the order. Never send only "See the details below." Do not send a website link.';
            }
        } elseif ($count > 1 && $photoCount >= 2) {
            $ui = 'product-shop-cards';
            $summary = 'Two or more products. Send the product-shop-cards carousel, one card per product, primary image only. Card text is exactly that body: name, price, and stock. Button is Add to cart. Skip a card with an empty image_url and write that product as text. If the tapped product has variants, send the variant list before adding it. A carousel needs 2 to 10 cards. Never send only "See the details below."';
        } elseif ($count > 1) {
            $ui = $photoCount === 1 ? 'product-photo-card' : 'none';
            $summary = 'Write each product body in the chat. '
                . ($photoCount === 1 ? 'Send one photo for the product that has an image_url, with its body as the caption. ' : '')
                . 'Button is Add to cart. Do not send a carousel. Never send only "See the details below."';
        } else {
            $summary = 'No matching products in this shop.';
        }
        $products = array_values($products);
        $this->success([
            'query'    => $query,
            'count'    => $count,
            'ui'       => $ui,
            'summary'  => $summary,
            'cards'    => $cards,
            'gallery_cards' => $galleryCards,
            'products' => $products,
        ], 'Product search completed.');
    }

    /** Name, price, and stock on the card. The customer reads this without an extra tap. */
    private function _shop_card_body(array $product): string {
        $name = trim((string)($product['name'] ?? ''));
        if ($name === '') {
            $name = 'Product';
        }
        $price = (float)($product['price'] ?? 0);
        $priceText = abs($price - round($price)) < 0.001
            ? (string)(int)round($price)
            : number_format($price, 2, '.', '');
        $stock = !empty($product['available']) ? 'In stock' : 'Out of stock';
        return $name . "\n₹" . $priceText . "\n" . $stock;
    }

    /** @return array<int, array<string, mixed>> */
    private function _gallery_cards(array $product): array {
        $body = $this->_shop_card_body($product);
        $name = (string)($product['name'] ?? 'Product');
        $needsVariant = count($product['variants'] ?? []) > 0;
        $cards = [];
        foreach ((array)($product['gallery'] ?? []) as $url) {
            $url = trim((string)$url);
            if ($url === '') {
                continue;
            }
            $cards[] = [
                'name'          => $name,
                'body'          => $body,
                'image_url'     => $url,
                'button_label'  => 'Add to cart',
                'needs_variant' => $needsVariant,
            ];
            if (count($cards) >= 10) {
                break;
            }
        }
        return $cards;
    }

    /** Extra photos of one product. Cards are the same product, not a new catalog. */
    private function _send_gallery(string $query, array $product): void {
        $body = $this->_shop_card_body($product);
        $cards = $this->_gallery_cards($product);
        $count = count($cards);
        $variantNote = count($product['variants'] ?? []) > 0
            ? ' If variants exist and none is chosen, send the variant list and do not add yet.'
            : ' Add to cart sets quantity to 1.';
        if ($count >= 2) {
            $ui = 'product-shop-cards';
            $summary = 'Gallery of one product. These cards are extra photos of the same product, not different products. Send the product-shop-cards carousel with one card per gallery photo, 2 to 10 cards. Each card image is that image_url. Each card text is exactly the body: name, price, and stock. Button is Add to cart.' . $variantNote . ' Never send only "See the details below."';
        } elseif ($count === 1) {
            $ui = 'product-photo-card';
            $summary = 'This product has one JPEG or PNG photo. Send that photo with the caption as the body, then Add to cart. There are no extra gallery photos.' . $variantNote . ' Never send only "See the details below."';
        } else {
            $ui = 'none';
            $cards = [[
                'name'          => (string)($product['name'] ?? 'Product'),
                'body'          => $body,
                'image_url'     => '',
                'button_label'  => 'Add to cart',
                'needs_variant' => count($product['variants'] ?? []) > 0,
            ]];
            $summary = 'This product has no extra JPEG or PNG gallery photos. Write the body in the chat: name, price, and stock. Do not send an empty card.' . $variantNote . ' Never send only "See the details below."';
        }
        unset($product['product_url']);
        $this->success([
            'query'         => $query,
            'count'         => 1,
            'ui'            => $ui,
            'summary'       => $summary,
            'cards'         => $cards,
            'gallery_cards' => $cards,
            'products'      => [$product],
        ], 'Product gallery completed.');
    }

    private function _meta_safe_products(array $rows, int $vendorId = 0): array {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $rowVendor = (int)($row['vendor_id'] ?? 0);
            // A blank vendor_id is not this shop. Only rows owned by this vendor may be sent.
            if ($vendorId > 0 && $rowVendor !== $vendorId) {
                continue;
            }
            if (strtolower(trim((string)($row['status'] ?? 'active'))) !== 'active') {
                continue;
            }
            $gallery = [];
            $rawGallery = is_array($row['gallery'] ?? null) ? $row['gallery'] : [];
            if (!$rawGallery) {
                $fallback = trim((string)($row['image_url'] ?? $row['thumbnail'] ?? $row['image'] ?? ''));
                if ($fallback !== '') {
                    $rawGallery = [$fallback];
                }
            }
            foreach ($rawGallery as $path) {
                $url = sk_mcp_tool_whatsapp_image_url((string)$path);
                if ($url !== '' && !in_array($url, $gallery, true)) {
                    $gallery[] = $url;
                }
                if (count($gallery) >= 10) {
                    break;
                }
            }
            $image = $gallery[0] ?? '';
            $productUrl = site_url('shop/product/' . $id);
            if (stripos($productUrl, 'http://') === 0) {
                $productUrl = 'https://' . substr($productUrl, 7);
            }
            $variants = sk_mcp_tool_variant_choices(is_array($row['variants'] ?? null) ? $row['variants'] : []);
            $out[] = [
                'id'               => $id,
                'name'             => (string)($row['name'] ?? ''),
                'sku'              => (string)($row['sku'] ?? ''),
                'image_url'        => $image,
                'gallery'          => $gallery,
                'variants'         => $variants,
                'product_url'      => $productUrl,
                'color'            => (string)($row['color'] ?? ''),
                'length'           => (string)($row['length'] ?? $row['saree_length'] ?? ''),
                'blouse_included'  => !empty($row['blouse_included']),
                'pack_of'          => (string)($row['pack_of'] ?? ''),
                'available'        => !empty($row['available']) || ((int)($row['stock'] ?? 0)) > 0,
                'stock'            => (int)($row['stock'] ?? 0),
                'price'            => (float)($row['price'] ?? 0),
                'mrp'              => (float)($row['mrp'] ?? 0),
                'currency'         => (string)($row['currency'] ?? 'INR'),
            ];
        }
        return $out;
    }
}
