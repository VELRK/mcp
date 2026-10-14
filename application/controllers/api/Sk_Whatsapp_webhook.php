<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/api/Sk_Base_Api.php';

/**
 * Meta WhatsApp Cloud webhook.
 * With Meta Business Agent enabled, this app is standby: store history only;
 * Meta Agent is the automatic responder. Handovers update thread ownership.
 *
 * Standby payloads nest under value.standby.messages / value.standby.message_echoes
 * (AI replies). Those must be persisted for the inbox tenant (phone_number_id → vendor).
 */
class Sk_Whatsapp_webhook extends Sk_Base_Api {

    public function __construct() {
        parent::__construct();
        $this->load->helper(['sk_whatsapp_cloud', 'sk_whatsapp', 'sk_whatsapp_mcp', 'sk_meta_business_agent', 'sk_mcp_tool']);
        $this->load->model(['Sk_Whatsapp_cloud_model', 'Sk_Admin_model', 'Sk_Vendor_meta_agent_model']);
        $this->load->library('Whatsapp_cloud');
        sk_wa_cloud_ensure_schema();
        $this->Sk_Vendor_meta_agent_model->ensure_schema();
    }

    /** GET verify + POST incoming (Meta Cloud). */
    public function index() {
        $method = strtoupper((string)$this->input->method(true));
        if ($method === 'GET') {
            return $this->_verify();
        }
        return $this->_ingest();
    }

    private function _verify() {
        $cfg = sk_wa_cloud_config($this->Sk_Admin_model->get_settings());
        $mode = (string)$this->input->get('hub_mode', FALSE);
        if ($mode === '') {
            $mode = (string)$this->input->get('hub.mode', FALSE);
        }
        $token = (string)$this->input->get('hub_verify_token', FALSE);
        if ($token === '') {
            $token = (string)$this->input->get('hub.verify_token', FALSE);
        }
        $challenge = (string)$this->input->get('hub_challenge', FALSE);
        if ($challenge === '') {
            $challenge = (string)$this->input->get('hub.challenge', FALSE);
        }
        if ($mode === 'subscribe' && $token !== '' && hash_equals($cfg['verify_token'], $token)) {
            header('Content-Type: text/plain; charset=UTF-8');
            echo $challenge;
            exit;
        }
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    private function _ingest() {
        $raw = (string)$this->input->raw_input_stream;
        $settings = $this->Sk_Admin_model->get_settings();
        $cfg = sk_wa_cloud_config($settings);
        if ($cfg['app_secret'] !== '') {
            $sig = (string)$this->input->get_request_header('X-Hub-Signature-256', true);
            $expect = 'sha256=' . hash_hmac('sha256', $raw, $cfg['app_secret']);
            if ($sig === '' || !hash_equals($expect, $sig)) {
                log_message('error', 'WhatsApp Cloud webhook signature mismatch');
                http_response_code(403);
                echo 'Invalid signature';
                exit;
            }
        }

        $payload = json_decode($raw, true);
        if (is_array($payload)) {
            foreach ((array)($payload['entry'] ?? []) as $entry) {
                foreach ((array)($entry['changes'] ?? []) as $change) {
                    $field = (string)($change['field'] ?? '');
                    $value = $change['value'] ?? [];
                    if (!is_array($value)) {
                        continue;
                    }

                    // Meta BA standby nests messages/echoes under value.standby.
                    $value = $this->_normalize_change_value($field, $value);

                    $phoneNumberId = trim((string)(
                        $value['metadata']['phone_number_id']
                        ?? $value['recipient']['phone_number_id']
                        ?? ''
                    ));
                    $vendorMatch = $phoneNumberId !== '' ? sk_wa_cloud_resolve_vendor_from_phone($phoneNumberId, $settings) : null;
                    $vendorId = $vendorMatch ? (int)$vendorMatch['vendor_id'] : 0;

                    if ($field === 'messaging_handovers') {
                        $this->_store_handovers($value, $vendorId, $phoneNumberId);
                        continue;
                    }

                    $this->_store_statuses((array)($value['statuses'] ?? []));

                    $forceOwner = ($field === 'standby') ? 'meta_agent' : null;
                    $this->_store_messages(
                        (array)($value['messages'] ?? []),
                        (array)($value['contacts'] ?? []),
                        $vendorId,
                        $phoneNumberId,
                        $settings,
                        'in',
                        $forceOwner
                    );
                    // AI / business app outbound echoes (standby.message_echoes, smb_message_echoes).
                    $this->_store_messages(
                        (array)($value['message_echoes'] ?? []),
                        (array)($value['contacts'] ?? []),
                        $vendorId,
                        $phoneNumberId,
                        $settings,
                        'out',
                        $forceOwner
                    );
                }
            }
        }

        // No local AI / MCP auto-reply — Meta Business Agent is the primary responder.
        http_response_code(200);
        header('Content-Type: application/json');
        echo '{"success":true}';
        exit;
    }

    /**
     * Flatten field-specific nesting so messages/echoes sit on value.* like Cloud API messages.
     */
    private function _normalize_change_value(string $field, array $value): array {
        if ($field === 'standby' && !empty($value['standby']) && is_array($value['standby'])) {
            $nested = $value['standby'];
            foreach (['messages', 'message_echoes', 'contacts', 'statuses'] as $k) {
                if (empty($nested[$k]) || !is_array($nested[$k])) {
                    continue;
                }
                $existing = isset($value[$k]) && is_array($value[$k]) ? $value[$k] : [];
                $value[$k] = array_merge($existing, $nested[$k]);
            }
        }

        // smb_message_echoes: business-app outbound; treat any messages[] as echoes.
        if ($field === 'smb_message_echoes'
            && empty($value['message_echoes'])
            && !empty($value['messages'])
            && is_array($value['messages'])
        ) {
            $value['message_echoes'] = $value['messages'];
            unset($value['messages']);
        }

        return $value;
    }

    private function _store_handovers(array $value, int $vendorId, string $phoneNumberId): void {
        $to = '';
        if (!empty($value['contacts'][0]['wa_id'])) {
            $to = sk_wa_cloud_normalize_phone((string)$value['contacts'][0]['wa_id']);
        } elseif (!empty($value['sender']['phone_number'])) {
            $to = sk_wa_cloud_normalize_phone((string)$value['sender']['phone_number']);
        } elseif (!empty($value['recipient_id'])) {
            $to = sk_wa_cloud_normalize_phone((string)$value['recipient_id']);
        } elseif (!empty($value['from'])) {
            $to = sk_wa_cloud_normalize_phone((string)$value['from']);
        }

        if ($phoneNumberId === '' && !empty($value['recipient']['phone_number_id'])) {
            $phoneNumberId = trim((string)$value['recipient']['phone_number_id']);
            if ($vendorId < 1 && $phoneNumberId !== '') {
                $match = sk_wa_cloud_resolve_vendor_from_phone($phoneNumberId);
                $vendorId = $match ? (int)$match['vendor_id'] : 0;
            }
        }

        $prevRole = strtolower((string)($value['control_passed']['previous_owner_app_role'] ?? ''));
        $type = strtolower((string)($value['type'] ?? ''));
        $newOwnerRaw = strtolower((string)(
            $value['new_owner']['app_id']
            ?? $value['new_thread_owner']
            ?? $value['thread_owner']
            ?? $value['new_owner']
            ?? ''
        ));

        $owner = 'meta_agent';
        if ($type === 'control_passed' || $prevRole === 'meta_business_agent') {
            // Agent passed control to the app / human.
            $owner = 'app';
        } elseif ($newOwnerRaw !== '') {
            if (strpos($newOwnerRaw, 'ai') !== false || strpos($newOwnerRaw, 'agent') !== false) {
                $owner = 'meta_agent';
            } else {
                $owner = 'app';
            }
        }
        if (!empty($value['handover']) || !empty($value['passed_control']) || !empty($value['requested'])) {
            if (!empty($value['requested']) || (isset($value['passed_control']['new_owner']['app']))) {
                $owner = 'human';
            }
        }

        if ($to !== '') {
            $conv = $this->Sk_Whatsapp_cloud_model->find_or_create_conversation(
                $to,
                (string)($value['contacts'][0]['profile']['name'] ?? ''),
                $vendorId > 0 ? $vendorId : null,
                $phoneNumberId !== '' ? $phoneNumberId : null
            );
            $this->Sk_Vendor_meta_agent_model->set_conversation_owner(
                (int)$conv['id'],
                $owner,
                'messaging_handovers'
            );
            if ($vendorId > 0 && in_array($owner, ['app', 'human'], true)) {
                sk_whatsapp_notify_handoff(
                    $vendorId,
                    $to,
                    'WhatsApp handed the chat to the shop',
                    (string)($value['contacts'][0]['profile']['name'] ?? '')
                );
            }
        }

        log_message('info', 'WhatsApp messaging_handovers: ' . json_encode([
            'phone_number_id' => $phoneNumberId,
            'vendor_id' => $vendorId,
            'to' => $to,
            'owner' => $owner,
        ]));
    }

    private function _store_statuses(array $statuses): void {
        foreach ($statuses as $st) {
            if (!is_array($st)) {
                continue;
            }
            $wamid = (string)($st['id'] ?? '');
            $status = (string)($st['status'] ?? '');
            $err = '';
            if (!empty($st['errors'][0]['title'])) {
                $err = (string)$st['errors'][0]['title'];
            }
            $this->Sk_Whatsapp_cloud_model->update_message_status($wamid, $status, $err);
            if (function_exists('sk_whatsapp_apply_meta_status')) {
                sk_whatsapp_apply_meta_status($wamid, $status, $err);
            }
        }
    }

    /**
     * Persist inbound customer messages or outbound AI/business echoes.
     *
     * @param string $direction 'in' | 'out'
     */
    private function _store_messages(
        array $messages,
        array $contacts,
        int $vendorId,
        string $phoneNumberId,
        array $settings,
        string $direction = 'in',
        ?string $forceOwner = null
    ): void {
        $names = [];
        foreach ($contacts as $c) {
            $wa = (string)($c['wa_id'] ?? '');
            if ($wa !== '') {
                $names[sk_wa_cloud_normalize_phone($wa)] = (string)($c['profile']['name'] ?? '');
            }
        }

        foreach ($messages as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $m = $this->_normalize_message_item($raw, $direction);
            if ($m === null) {
                continue;
            }

            $peer = sk_wa_cloud_normalize_phone((string)($m['_peer'] ?? ''));
            $wamid = (string)($m['id'] ?? '');
            if ($peer === '' || $wamid === '' || $this->Sk_Whatsapp_cloud_model->find_by_wamid($wamid)) {
                continue;
            }

            $parsed = sk_wa_mcp_parse_inbound($m);
            $type = (string)($m['type'] ?? 'text');
            $mediaTypes = ['image', 'video', 'audio', 'document', 'sticker'];
            $mediaId = '';
            $filename = '';
            if (in_array($type, $mediaTypes, true) && is_array($m[$type] ?? null)) {
                $mediaId = (string)($m[$type]['id'] ?? '');
                $filename = trim((string)($m[$type]['filename'] ?? ''));
            }
            $body = $parsed['text'] !== '' ? $parsed['text'] : ($filename !== '' ? $filename : ucfirst($type));
            $mediaUrl = '';
            if ($mediaId !== '') {
                $resolved = $settings;
                if ($vendorId > 0) {
                    $resolved['vendor_id'] = $vendorId;
                }
                if ($phoneNumberId !== '') {
                    $resolved['_wa_phone_number_id'] = $phoneNumberId;
                }
                $cfg = sk_wa_cloud_config($resolved, $vendorId > 0 ? $vendorId : null);
                $mediaUrl = sk_wa_cloud_cache_media($mediaId, (string)($cfg['access_token'] ?? ''), (string)($cfg['graph_base'] ?? ''));
            }
            $storeType = in_array($type, array_merge(['text'], $mediaTypes), true) ? $type : 'text';
            $conv = $this->Sk_Whatsapp_cloud_model->find_or_create_conversation(
                $peer,
                $names[$peer] ?? '',
                $vendorId > 0 ? $vendorId : null,
                $phoneNumberId !== '' ? $phoneNumberId : null
            );
            $this->Sk_Whatsapp_cloud_model->add_message((int)$conv['id'], [
                'vendor_id'       => $vendorId > 0 ? $vendorId : null,
                'phone_number_id' => $phoneNumberId !== '' ? $phoneNumberId : null,
                'wamid'           => $wamid,
                'direction'       => $direction === 'out' ? 'out' : 'in',
                'type'            => $storeType,
                'body'            => $body,
                'media_url'       => $mediaUrl !== '' ? $mediaUrl : ($mediaId !== '' ? $mediaId : null),
                'media_id'        => $mediaId !== '' ? $mediaId : null,
                'status'          => $direction === 'out' ? 'sent' : 'received',
                'raw_json'        => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            if ($forceOwner !== null) {
                $this->Sk_Vendor_meta_agent_model->set_conversation_owner((int)$conv['id'], $forceOwner, 'standby');
            } elseif ($direction === 'out' && $this->_is_bizai_echo($raw, $m)) {
                $this->Sk_Vendor_meta_agent_model->set_conversation_owner((int)$conv['id'], 'meta_agent', 'message_echo');
            }
            if ($direction === 'in' && $vendorId > 0) {
                if (!empty($conv['handoff_at'])) {
                    sk_whatsapp_notify_handoff($vendorId, $peer, $body, $names[$peer] ?? '', true);
                } else {
                    $this->_handle_inbound_customer_message($raw, $m, $conv, $peer, $body, $vendorId, $phoneNumberId, $settings);
                }
            }
        }
    }

    /**
     * Normalize Cloud messages and nested standby echoes into a common message shape.
     * Echo shape A: { from, to, id, type, text }
     * Echo shape B: { id, timestamp, message: { to, type, text, biz_opaque_callback_data } }
     */
    private function _normalize_message_item(array $raw, string $direction): ?array {
        $m = $raw;
        if (!empty($raw['message']) && is_array($raw['message'])) {
            $inner = $raw['message'];
            $m = array_merge($inner, [
                'id' => (string)($raw['id'] ?? $inner['id'] ?? ''),
                'timestamp' => (string)($raw['timestamp'] ?? $inner['timestamp'] ?? ''),
            ]);
        }

        $wamid = (string)($m['id'] ?? $raw['id'] ?? '');
        if ($wamid === '') {
            return null;
        }
        $m['id'] = $wamid;

        if ($direction === 'out') {
            $peer = (string)($m['to'] ?? $raw['to'] ?? $m['recipient'] ?? '');
        } else {
            $peer = (string)($m['from'] ?? $raw['from'] ?? '');
        }
        $peer = sk_wa_cloud_normalize_phone($peer);
        if ($peer === '') {
            return null;
        }
        $m['_peer'] = $peer;
        if (empty($m['type'])) {
            $m['type'] = 'text';
        }
        return $m;
    }

    private function _is_bizai_echo(array $raw, array $m): bool {
        $opaque = $m['biz_opaque_callback_data'] ?? $raw['biz_opaque_callback_data'] ?? null;
        if (is_string($opaque)) {
            $decoded = json_decode($opaque, true);
            $opaque = is_array($decoded) ? $decoded : null;
        }
        if (is_array($opaque) && strtolower((string)($opaque['originator'] ?? '')) === 'bizai') {
            return true;
        }
        return false;
    }

    /**
     * Handle incoming customer messages when the App is the active thread handler on WhatsApp.
     * Implements the 5-option interactive menu, button actions, and auto-releases thread to agent.
     */
    private function _handle_inbound_customer_message(
        array $raw,
        array $m,
        array $conv,
        string $peer,
        string $body,
        int $vendorId,
        string $phoneNumberId,
        array $settings
    ): void {
        if ($peer === '' || $vendorId <= 0) {
            return;
        }

        // Initialize Cloud API client
        $resolved = $settings;
        $resolved['vendor_id'] = $vendorId;
        if ($phoneNumberId !== '') {
            $resolved['_wa_phone_number_id'] = $phoneNumberId;
        }
        $waCloud = new Whatsapp_cloud($resolved);

        $shopName = 'Vel Saree';
        if (function_exists('sk_whatsapp_shop_name')) {
            $biz = sk_whatsapp_shop_name($vendorId);
            if ($biz !== '' && strcasecmp($biz, 'Shop') !== 0) {
                $shopName = $biz;
            }
        }

        // Extract interactive list/button selection ID if present
        $interactiveId = '';
        if (!empty($raw['interactive']['list_reply']['id'])) {
            $interactiveId = trim((string)$raw['interactive']['list_reply']['id']);
        } elseif (!empty($raw['interactive']['button_reply']['id'])) {
            $interactiveId = trim((string)$raw['interactive']['button_reply']['id']);
        }

        $trimBody = trim(mb_strtolower($body));

        // 1. GREETING / MAIN MENU:
        $isGreeting = preg_match('/^(hi|hai|hello|hey|vanakkam|menu|main menu|start|help|வணக்கம்)(\s+(there|team|vel|bro|sir|mam|madam|all))?[\s!.,?]*$/iu', $trimBody)
            || in_array($interactiveId, ['main_menu', 'menu'], true);

        if ($isGreeting) {
            $headerText = function_exists('mb_substr') ? mb_substr($shopName, 0, 60) : substr($shopName, 0, 60);
            $interactive = [
                'type'   => 'list',
                'header' => [
                    'type' => 'text',
                    'text' => $headerText,
                ],
                'body'   => [
                    'text' => "Welcome to {$shopName}! 👋\nHow can I help you today? Please choose an option below:",
                ],
                'footer' => [
                    'text' => 'Select an option to proceed',
                ],
                'action' => [
                    'button'   => 'View Menu',
                    'sections' => [
                        [
                            'title' => 'Main Menu',
                            'rows'  => [
                                [
                                    'id'          => 'browse_products',
                                    'title'       => '🛍️ Browse products',
                                    'description' => 'Explore collections & categories',
                                ],
                                [
                                    'id'          => 'offers_deals',
                                    'title'       => '🏷️ Offers & Deals',
                                    'description' => "Today's discounts & promo codes",
                                ],
                                [
                                    'id'          => 'search_product',
                                    'title'       => '🔎 Search a product',
                                    'description' => 'Find by name, style, or color',
                                ],
                                [
                                    'id'          => 'track_order',
                                    'title'       => '📦 Track my order',
                                    'description' => 'Check order & shipment status',
                                ],
                                [
                                    'id'          => 'talk_to_team',
                                    'title'       => '🙋 Talk to our team',
                                    'description' => 'Chat with our support team',
                                ],
                            ],
                        ],
                    ],
                ],
            ];
            $waCloud->send_interactive($peer, $interactive);
            return;
        }

        // 2. BUTTON 1 — 🛍️ BROWSE PRODUCTS / CATEGORIES:
        if ($interactiveId === 'browse_products' || $trimBody === '1' || preg_match('/^(browse|categories|category|collections)$/iu', $trimBody)) {
            $sql = "SELECT c.id, c.name, COUNT(p.id) as product_count 
                    FROM categories c 
                    INNER JOIN products p ON p.category_id = c.id 
                    WHERE p.vendor_id = ? AND p.status = 'active' 
                    GROUP BY c.id HAVING product_count > 0 
                    ORDER BY c.name ASC";
            $rows = $this->db->query($sql, [$vendorId])->result_array();
            if (empty($rows)) {
                $rows = $this->db->query("SELECT id, name FROM categories ORDER BY name ASC LIMIT 10")->result_array();
            }

            $icons = ['🌸', '🧵', '✨', '🥻', '👰', '🪡', '💎', '🎨', '🌟', '👗'];
            $secRows = [];
            foreach (array_slice($rows, 0, 10) as $i => $cat) {
                $icon = $icons[$i % count($icons)];
                $name = trim((string)($cat['name'] ?? 'Collection'));
                $cnt = (int)($cat['product_count'] ?? 0);
                $secRows[] = [
                    'id'          => 'cat_' . $cat['id'],
                    'title'       => function_exists('mb_substr') ? mb_substr("{$icon} {$name}", 0, 24) : substr("{$icon} {$name}", 0, 24),
                    'description' => $cnt > 0 ? "{$cnt} items available" : "View collection",
                ];
            }

            $interactive = [
                'type'   => 'list',
                'header' => [
                    'type' => 'text',
                    'text' => function_exists('mb_substr') ? mb_substr("{$shopName} Categories", 0, 60) : substr("{$shopName} Categories", 0, 60),
                ],
                'body'   => [
                    'text' => "What would you like to shop for? 😊\nTap below to explore our categories:",
                ],
                'footer' => [
                    'text' => 'Select a category to view items',
                ],
                'action' => [
                    'button'   => 'View Categories',
                    'sections' => [
                        [
                            'title' => 'Product Categories',
                            'rows'  => $secRows,
                        ],
                    ],
                ],
            ];
            $waCloud->send_interactive($peer, $interactive);
            return;
        }

        // 3. BUTTON 2 — 🏷️ OFFERS & DEALS:
        if ($interactiveId === 'offers_deals' || $trimBody === '2' || preg_match('/^(offers|deals|discounts|coupons)$/iu', $trimBody)) {
            $now = date('Y-m-d H:i:s');
            $sql = "SELECT p.*, c.name as category_name 
                    FROM products p 
                    LEFT JOIN categories c ON c.id = p.category_id 
                    WHERE p.vendor_id = ? AND p.status = 'active' 
                      AND p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price
                      AND (p.sale_start_at IS NULL OR p.sale_start_at <= ?)
                      AND (p.sale_end_at IS NULL OR p.sale_end_at >= ?)
                    ORDER BY (p.price - p.sale_price) DESC 
                    LIMIT 8";
            $discounted = $this->db->query($sql, [$vendorId, $now, $now])->result_array();
            if (empty($discounted)) {
                $sqlHot = "SELECT p.*, c.name as category_name 
                           FROM products p 
                           LEFT JOIN categories c ON c.id = p.category_id 
                           WHERE p.vendor_id = ? AND p.status = 'active' 
                             AND (p.hot_sale = 1 OR p.special_product = 1 OR p.featured = 1)
                           ORDER BY p.id DESC LIMIT 5";
                $discounted = $this->db->query($sqlHot, [$vendorId])->result_array();
            }

            if (!empty($discounted)) {
                $rows = [];
                foreach ($discounted as $idx => $p) {
                    $origPrice = (float)($p['price'] ?? 0);
                    $salePrice = isset($p['sale_price']) && (float)$p['sale_price'] > 0 ? (float)$p['sale_price'] : $origPrice;
                    $savings = max(0, $origPrice - $salePrice);
                    $pct = $origPrice > 0 && $savings > 0 ? round(($savings / $origPrice) * 100) : 0;
                    $pName = trim((string)($p['name'] ?? 'Product'));
                    $priceStr = function_exists('sk_mcp_tool_inr') ? sk_mcp_tool_inr($salePrice) : "₹{$salePrice}";
                    $origStr = function_exists('sk_mcp_tool_inr') ? sk_mcp_tool_inr($origPrice) : "₹{$origPrice}";

                    $desc = $pct > 0 ? "{$priceStr} (Was {$origStr}, {$pct}% OFF)" : $priceStr;
                    $rows[] = [
                        'id'          => (string)($p['sku'] ?? ('prod_' . $p['id'])),
                        'title'       => function_exists('mb_substr') ? mb_substr($pName, 0, 24) : substr($pName, 0, 24),
                        'description' => function_exists('mb_substr') ? mb_substr($desc, 0, 72) : substr($desc, 0, 72),
                    ];
                }

                $interactive = [
                    'type'   => 'list',
                    'header' => [
                        'type' => 'text',
                        'text' => function_exists('mb_substr') ? mb_substr("{$shopName} Offers", 0, 60) : substr("{$shopName} Offers", 0, 60),
                    ],
                    'body'   => [
                        'text' => "🎉 Here are today's special discounts and offers at {$shopName}!\nTap below to explore deals:",
                    ],
                    'footer' => [
                        'text' => 'Limited time offers',
                    ],
                    'action' => [
                        'button'   => 'View Offers',
                        'sections' => [
                            [
                                'title' => "Today's Deals",
                                'rows'  => array_slice($rows, 0, 10),
                            ],
                        ],
                    ],
                ];
                $waCloud->send_interactive($peer, $interactive);
            } else {
                $waCloud->send_text($peer, "No active offers right now 😊 Would you like to browse our products?\nReply with *1* to browse or send a saree name to search!");
            }
            return;
        }

        // 4. BUTTON 3 — 🔎 SEARCH A PRODUCT:
        if ($interactiveId === 'search_product' || $trimBody === '3') {
            $waCloud->send_text($peer, "What product or saree are you looking for? 🔎\n(You can share name, color, style, or fabric like *Kanjivaram*, *Cotton*, *Crepe*, or *Red*)");
            return;
        }

        // 5. BUTTON 4 — 📦 TRACK MY ORDER:
        if ($interactiveId === 'track_order' || $trimBody === '4' || preg_match('/^(track|track order|where is my order)$/iu', $trimBody)) {
            $digits = preg_replace('/\D+/', '', $peer);
            $last10 = strlen($digits) >= 10 ? substr($digits, -10) : $digits;

            $this->db->select('o.*')
                     ->from('orders o')
                     ->group_start()
                       ->like('o.shipping_phone', $last10)
                       ->or_like('o.billing_phone', $last10)
                     ->group_end()
                     ->order_by('o.id', 'DESC')
                     ->limit(3);
            $orders = $this->db->get()->result_array();

            $matched = [];
            foreach ($orders as $o) {
                if (function_exists('sk_ai_order_in_shop') && sk_ai_order_in_shop($o, $vendorId)) {
                    $matched[] = $o;
                }
            }

            if (!empty($matched)) {
                $latest = $matched[0];
                $oNum = (string)($latest['order_number'] ?? $latest['id']);
                $oStatus = ucfirst((string)($latest['status'] ?? 'pending'));
                $pStatus = ucfirst((string)($latest['payment_status'] ?? 'pending'));
                $oTot = function_exists('sk_mcp_tool_inr') ? sk_mcp_tool_inr((float)($latest['total'] ?? 0)) : "₹" . ($latest['total'] ?? 0);
                $trackNo = (string)($latest['tracking_number'] ?? $latest['awb_number'] ?? '');
                $courier = (string)($latest['courier_name'] ?? $latest['shipping_provider'] ?? '');

                $msg = "📦 *Order Status for #{$oNum}*\n\n"
                    . "• *Status:* {$oStatus}\n"
                    . "• *Payment:* {$pStatus}\n"
                    . "• *Total:* {$oTot}\n";
                if ($trackNo !== '') {
                    $msg .= "• *Tracking:* {$trackNo} ({$courier})\n";
                }
                $msg .= "\nHow else can we help you today?";
                $waCloud->send_text($peer, $msg);
            } else {
                $waCloud->send_text($peer, "We couldn't find any recent orders linked to +{$digits} at {$shopName} 😊\nIf you have an order number, please reply with it (e.g. *#G2D1001*), or reply *1* to browse our products!");
            }
            return;
        }

        // 6. BUTTON 5 — 🙋 TALK TO OUR TEAM:
        if ($interactiveId === 'talk_to_team' || $trimBody === '5' || preg_match('/^(talk to team|human|support|agent)$/iu', $trimBody)) {
            if (function_exists('sk_ai_human_handoff')) {
                sk_ai_human_handoff(['phone' => $peer, 'reason' => 'Customer clicked Talk to our team'], $vendorId);
            }
            $waCloud->send_text($peer, "Of course 😊 I am connecting you with our team.\nPlease wait while our team reviews your message. A teammate will join this chat shortly!");
            return;
        }

        // 7. CATEGORY SELECTED (e.g. cat_1, cat_2)
        if (strpos($interactiveId, 'cat_') === 0) {
            $catId = (int)substr($interactiveId, 4);
            $cat = $this->db->where('id', $catId)->get('categories')->row_array();
            $catName = (string)($cat['name'] ?? 'Category');

            $prods = $this->db->where('vendor_id', $vendorId)
                              ->where('category_id', $catId)
                              ->where('status', 'active')
                              ->limit(10)
                              ->get('products')
                              ->result_array();

            if (!empty($prods)) {
                $footer = function_exists('sk_mcp_tool_delivery_footer') ? sk_mcp_tool_delivery_footer($vendorId) : 'Free delivery over ₹999';
                $interactive = function_exists('sk_mcp_tool_build_product_interactive_list')
                    ? sk_mcp_tool_build_product_interactive_list($prods, $catName, $footer)
                    : null;
                if ($interactive) {
                    $waCloud->send_interactive($peer, $interactive);
                    return;
                }
            }
        }

        // 8. PRODUCT SKU SELECTED (e.g. 2D-001-PC)
        if (strpos($interactiveId, '2D-') === 0 || strpos($interactiveId, 'prod_') === 0) {
            $sku = $interactiveId;
            $prod = $this->db->where('vendor_id', $vendorId)
                             ->where('sku', $sku)
                             ->get('products')
                             ->row_array();
            if (!$prod && is_numeric(str_replace('prod_', '', $sku))) {
                $prod = $this->db->where('vendor_id', $vendorId)
                                 ->where('id', (int)str_replace('prod_', '', $sku))
                                 ->get('products')
                                 ->row_array();
            }
            if ($prod) {
                $pName = (string)$prod['name'];
                $pPrice = function_exists('sk_mcp_tool_inr') ? sk_mcp_tool_inr((float)$prod['price']) : "₹" . $prod['price'];
                $fabric = !empty($prod['fabric']) ? "Fabric: " . $prod['fabric'] : '';
                $color = !empty($prod['color']) ? "Color: " . $prod['color'] : '';
                $details = array_filter([$fabric, $color]);
                $detailStr = !empty($details) ? implode(" · ", $details) . "\n" : '';
                $caption = "🥻 *{$pName}*\n💰 Price: {$pPrice}\n{$detailStr}\nWhich saree and quantity would you like to order?";

                $imgPath = function_exists('sk_mcp_tool_product_image_path') ? sk_mcp_tool_product_image_path($prod) : (string)($prod['thumbnail'] ?? '');
                $imgUrl = function_exists('sk_mcp_tool_whatsapp_image_url') && $imgPath !== '' ? sk_mcp_tool_whatsapp_image_url($imgPath) : '';

                if ($imgUrl !== '') {
                    $waCloud->send_media($peer, 'image', $imgUrl, $caption);
                } else {
                    $waCloud->send_text($peer, $caption);
                }
                return;
            }
        }

        // 9. KEYWORD SEARCH (e.g. user typed "kanjivaram", "silk saree", etc.)
        if (function_exists('sk_mcp_tool_find_products') && strlen($trimBody) >= 2) {
            $found = sk_mcp_tool_find_products($body, ['tenant' => $vendorId], 10);
            if (!empty($found['products'])) {
                $prods = $found['products'];
                $footer = function_exists('sk_mcp_tool_delivery_footer') ? sk_mcp_tool_delivery_footer($vendorId) : 'Free delivery over ₹999';
                $interactive = function_exists('sk_mcp_tool_build_product_interactive_list')
                    ? sk_mcp_tool_build_product_interactive_list($prods, $shopName, $footer)
                    : null;
                if ($interactive) {
                    $waCloud->send_interactive($peer, $interactive);
                    return;
                }
            }
        }

        // 10. Fallback: release thread control to Meta Business Agent
        if (function_exists('sk_meta_ba_thread_control')) {
            sk_meta_ba_thread_control($phoneNumberId, $peer, 'release');
        }
    }
}
