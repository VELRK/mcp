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
        foreach ($products as $pIndex => $p) {
            $name = (string)($p['name'] ?? 'Product');
            $price = (string)($p['price'] ?? '0');
            $button = function_exists('mb_substr') ? mb_substr($name, 0, 20) : substr($name, 0, 20);
            $cards[] = [
                'name'         => $name,
                'body'         => $name . ' — ₹' . $price,
                'image_url'    => (string)($p['image_url'] ?? ''),
                'button_label' => $button !== '' ? $button : 'Shop now',
            ];
            unset($products[$pIndex]['product_url']);
        }
        $count = count($cards);
        $photoCards = [];
        $detailLines = [];
        foreach ($cards as $card) {
            $detailLines[] = (string)$card['body'];
            if ((string)$card['image_url'] !== '') {
                $photoCards[] = $card;
            }
        }
        $details = implode("\n", $detailLines);
        $photoCount = count($photoCards);
        if ($count === 1) {
            $cards[0]['button_label'] = 'Shop now';
            $galleryCount = count($products[0]['gallery'] ?? []);
            $summary = 'Exactly one product. The customer must see this line in the message: ' . $cards[0]['body'] . '. '
                . ($photoCount === 1
                    ? 'Send this one product photo with that line as the caption, then one Shop now reply button. Do not send the gallery yet. '
                    : 'There is no WhatsApp photo for this product. Write that line in the text, then one Shop now reply button. Do not send an empty card. ')
                . ($galleryCount > 1 ? ('This product has ' . $galleryCount . ' JPEG or PNG photos in gallery. Send those only when the customer asks for more images. ') : '')
                . 'Shop now adds this product to the cart. Never send only "See the details below." Shop now stays in this chat. Do not send a website link.';
        } elseif ($count > 1 && $photoCount >= 2) {
            $summary = 'Two or more products. Send the product-shop-cards carousel only for cards that have an image_url. Each card text must be that card body, which is the name and price. Button text is button_label. Also readable in this result: ' . str_replace("\n", ' | ', $details) . '. Never send only "See the details below." A carousel needs 2 to 10 cards.';
        } elseif ($count > 1) {
            $summary = 'Write these product lines in the chat so the customer can read them: ' . str_replace("\n", ' | ', $details) . '. '
                . ($photoCount === 1 ? 'Send one photo for the product that has an image_url, with its body as the caption. ' : '')
                . 'Do not send a carousel. Never send only "See the details below."';
        } else {
            $summary = 'No matching products in this shop.';
        }
        $products = array_values($products);
        $this->success([
            'query'    => $query,
            'count'    => $count,
            'ui'       => $count === 1 ? 'product-photo-card' : ($count > 1 ? 'product-shop-cards' : 'none'),
            'summary'  => $summary,
            'cards'    => $cards,
            'gallery_cards' => [],
            'products' => $products,
        ], 'Product search completed.');
    }

    /** Extra photos of one product. Cards are the same product, not a new catalog. */
    private function _send_gallery(string $query, array $product): void {
        $name = (string)($product['name'] ?? 'Product');
        $price = (string)($product['price'] ?? '0');
        $body = $name . ' — ₹' . $price;
        $gallery = [];
        foreach ((array)($product['gallery'] ?? []) as $url) {
            $url = trim((string)$url);
            if ($url !== '' && !in_array($url, $gallery, true)) {
                $gallery[] = $url;
            }
        }
        $gallery = array_slice($gallery, 0, 10);
        $cards = [];
        foreach ($gallery as $url) {
            $cards[] = [
                'name'         => $name,
                'body'         => $body,
                'image_url'    => $url,
                'button_label' => 'Shop now',
            ];
        }
        $count = count($cards);
        if ($count >= 2) {
            $ui = 'product-shop-cards';
            $summary = 'Gallery of one product. These cards are extra photos of the same product, not different products. Send the product-shop-cards carousel with one card per gallery photo, 2 to 10 cards. Each card image is that image_url. Each card text is: ' . $body . '. Button is Shop now. Tapping any card adds this one product to the cart. Never send only "See the details below."';
        } elseif ($count === 1) {
            $ui = 'product-photo-card';
            $summary = 'This product has one JPEG or PNG photo. Send that photo with the caption ' . $body . ', then Shop now. There are no extra gallery photos. Never send only "See the details below."';
        } else {
            $ui = 'none';
            $cards = [[
                'name'         => $name,
                'body'         => $body,
                'image_url'    => '',
                'button_label' => 'Shop now',
            ]];
            $summary = 'This product has no extra JPEG or PNG gallery photos. Write this line in the chat: ' . $body . '. Do not send an empty card. Never send only "See the details below."';
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
            $out[] = [
                'id'               => $id,
                'name'             => (string)($row['name'] ?? ''),
                'sku'              => (string)($row['sku'] ?? ''),
                'image_url'        => $image,
                'gallery'          => $gallery,
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
