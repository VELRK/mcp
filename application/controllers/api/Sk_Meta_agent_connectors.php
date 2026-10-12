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
        $result = sk_mcp_tool_find_products($searchQuery, ['tenant' => $vendorId], 10);
        $products = $this->_meta_safe_products($result['results'] ?? [], $vendorId);
        if ($galleryAsk && $products) {
            $this->_send_gallery($query, $products[0]);
            return;
        }
        $footer = sk_mcp_tool_delivery_footer($vendorId);
        $cards = [];
        $galleryCards = [];
        foreach ($products as $pIndex => $p) {
            $cards[] = $this->_shop_card($p, $footer, 'Add to cart');
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
            $galleryCards = $this->_gallery_cards($products[0], $footer);
            $variantCount = count($products[0]['variants'] ?? []);
            $variantNote = $variantCount > 0
                ? 'Send the variant list under the photos. They must choose one before Add to cart or Buy. '
                : 'This product has no variants. ';
            $sheet = 'Then send one reply with body exactly detail, footer exactly footer, and two buttons. First title is Add to cart. Second title is Buy, below Add to cart. Add to cart sets quantity to 1 and keeps shopping. Buy sets quantity to 1, then the delivery questions, one confirmation, and only then the payment link. Do not send an invoice before the payment link. ';
            if (count($galleryCards) >= 2) {
                $cards = $galleryCards;
                $ui = 'product-shop-cards';
                $summary = 'One product. Send the gallery carousel first, one card per photo. Card text is the short body: name, code and stock, price. Button is Add to cart. ' . $sheet . $variantNote . 'Never send only "See the details below." Do not send a website link. Do not invent delivery days or fabric details.';
            } else {
                $ui = 'product-photo-card';
                $summary = 'Exactly one product. '
                    . ($photoCount === 1
                        ? 'Send the photo first. Caption is the short body: name, code and stock, price. '
                        : 'There is no photo. ')
                    . $sheet . $variantNote . 'Never send only "See the details below." Do not send a website link. Do not invent delivery days or fabric details.';
            }
        } elseif ($count > 1) {
            $ui = 'product-photo-card';
            $summary = 'Product list. Send every card as its own sheet, in order, up to 10. Same design for each. Photo caption is that card body: name, code and stock, price. Then one reply whose body is that card detail and whose footer is footer. Two buttons: Add to cart, then Buy below it. Add to cart sets quantity 1 and keeps shopping. Buy sets quantity 1 for that product, then delivery questions, one confirmation, then the payment link. Do not send an invoice before the payment link. If that product has variants, send the variant list before either button. Do not put different products in one carousel. Never send only "See the details below."';
        } else {
            $summary = 'No matching products in this shop.';
        }
        $products = array_values($products);
        $sheet = $count === 1 ? ($cards[0] ?? []) : [];
        $this->success([
            'query'    => $query,
            'count'    => $count,
            'ui'       => $ui,
            'summary'  => $summary,
            'detail'   => (string)($sheet['detail'] ?? ''),
            'footer'   => (string)($sheet['footer'] ?? $footer),
            'cards'    => $cards,
            'gallery_cards' => $galleryCards,
            'products' => $products,
        ], 'Product search completed.');
    }

    /** Short lines for the photo: name, code and stock, price. */
    private function _shop_card_body(array $product): string {
        $name = trim((string)($product['name'] ?? ''));
        if ($name === '') {
            $name = 'Product';
        }
        if (function_exists('mb_substr')) {
            $name = mb_substr($name, 0, 80);
        }
        $sku = trim((string)($product['sku'] ?? ''));
        $stock = !empty($product['available']) ? 'in stock' : 'out of stock';
        $meta = $sku !== '' ? ($sku . ' · ' . $stock) : $stock;
        return $name . "\n" . $meta . "\n" . sk_mcp_tool_inr((float)($product['price'] ?? 0));
    }

    /** Full product sheet under the photo. */
    private function _shop_detail_body(array $product): string {
        $lines = [$this->_shop_card_body($product)];
        $description = trim((string)($product['description'] ?? ''));
        if ($description !== '') {
            $lines[] = '';
            $lines[] = $description;
        }
        $specs = trim((string)($product['specs'] ?? ''));
        if ($specs !== '') {
            $lines[] = $specs;
        }
        $text = implode("\n", $lines);
        if (strlen($text) > 1000) {
            $text = substr($text, 0, 997) . '...';
        }
        return $text;
    }

    /** @return array<string, mixed> */
    private function _shop_card(array $product, string $footer, string $button = ''): array {
        $name = trim((string)($product['name'] ?? ''));
        if ($name === '') {
            $name = 'Product';
        }
        if ($button === '') {
            $button = function_exists('mb_substr') ? mb_substr($name, 0, 20) : substr($name, 0, 20);
            if ($button === '') {
                $button = 'Add to cart';
            }
        }
        return [
            'name'          => $name,
            'body'          => $this->_shop_card_body($product),
            'detail'        => $this->_shop_detail_body($product),
            'footer'        => $footer,
            'image_url'     => (string)($product['image_url'] ?? ''),
            'button_label'  => $button,
            'needs_variant' => count($product['variants'] ?? []) > 0,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function _gallery_cards(array $product, string $footer = ''): array {
        $card = $this->_shop_card($product, $footer, 'Add to cart');
        $cards = [];
        foreach ((array)($product['gallery'] ?? []) as $url) {
            $url = trim((string)$url);
            if ($url === '') {
                continue;
            }
            $card['image_url'] = $url;
            $cards[] = $card;
            if (count($cards) >= 10) {
                break;
            }
        }
        return $cards;
    }

    /** Extra photos of one product. Cards are the same product, not a new catalog. */
    private function _send_gallery(string $query, array $product): void {
        $footer = sk_mcp_tool_delivery_footer((int)($product['vendor_id'] ?? 0));
        $sheet = $this->_shop_card($product, $footer, 'Add to cart');
        $cards = $this->_gallery_cards($product, $footer);
        $count = count($cards);
        $variantNote = count($product['variants'] ?? []) > 0
            ? ' If variants exist and none is chosen, send the variant list and do not add yet.'
            : '';
        if ($count >= 2) {
            $ui = 'product-shop-cards';
            $summary = 'Gallery of one product. Send the carousel, one card per photo. Card text is the short body. Button is Add to cart. Then send detail as the reply body, footer as the footer, and two buttons: Add to cart, then Buy below it. Buy starts confirmation and then the payment link. Do not send an invoice before the payment link.' . $variantNote . ' Never send only "See the details below."';
        } elseif ($count === 1) {
            $ui = 'product-photo-card';
            $cards = [$sheet];
            $summary = 'One photo. Send it with the short body as the caption, then the detail reply with footer and two buttons: Add to cart, then Buy below it. Do not send an invoice before the payment link.' . $variantNote . ' Never send only "See the details below."';
        } else {
            $ui = 'none';
            $sheet['image_url'] = '';
            $cards = [$sheet];
            $summary = 'No extra photos. Send the detail reply with Add to cart and Buy below it. Do not send an invoice before the payment link. Do not send an empty card.' . $variantNote;
        }
        unset($product['product_url']);
        $this->success([
            'query'         => $query,
            'count'         => 1,
            'ui'            => $ui,
            'summary'       => $summary,
            'detail'        => (string)$sheet['detail'],
            'footer'        => $footer,
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
                'vendor_id'        => $rowVendor,
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
                'description'      => (string)($row['description'] ?? ''),
                'specs'            => (string)($row['specs'] ?? ''),
                'currency'         => (string)($row['currency'] ?? 'INR'),
            ];
        }
        return $out;
    }
}
