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
            } else {
                $waCloud->send_text($peer, "No products currently available in {$catName} 😊\nReply with *1* to browse other collections!");
                return;
            }
        }

        // 8. PRODUCT SELECTED (e.g. 2D-004, 2D-001-PC, prod_4, select_prod_4)
        if (strpos($interactiveId, '2D-') === 0 || strpos($interactiveId, 'prod_') === 0 || strpos($interactiveId, 'select_prod_') === 0) {
            $sku = $interactiveId;
            $cleanId = str_replace(['select_prod_', 'prod_'], '', $sku);
            $prod = null;
            if (is_numeric($cleanId)) {
                $prod = $this->db->where('vendor_id', $vendorId)
                                 ->where('id', (int)$cleanId)
                                 ->get('products')
                                 ->row_array();
            }
            if (!$prod) {
                $prod = $this->db->where('vendor_id', $vendorId)
                                 ->where('sku', $sku)
                                 ->get('products')
                                 ->row_array();
            }
            if (!$prod) {
                // Check if SKU matches a variant
                $var = $this->db->where('sku', $sku)->get('product_variants')->row_array();
                if ($var && !empty($var['product_id'])) {
                    $prod = $this->db->where('vendor_id', $vendorId)
                                     ->where('id', (int)$var['product_id'])
                                     ->get('products')
                                     ->row_array();
                }
            }

            if ($prod) {
                $this->_send_product_details($prod, $peer, $vendorId, $waCloud, $shopName, $conv);
                return;
            }
        }

        // 9. VARIANT SELECTED (e.g. var_2_1 -> variant_id 2, product_id 1)
        if (strpos($interactiveId, 'var_') === 0) {
            $parts = explode('_', substr($interactiveId, 4));
            $variantId = (int)($parts[0] ?? 0);
            $productId = (int)($parts[1] ?? 0);

            $var = $this->db->where('id', $variantId)->get('product_variants')->row_array();
            $prod = $this->db->where('id', $productId)->get('products')->row_array();

            if ($prod && $var) {
                $pName = (string)$prod['name'];
                $vLabel = (string)($var['label'] ?? 'Standard');
                $price = (float)($var['sale_price'] && (float)$var['sale_price'] > 0 ? $var['sale_price'] : $var['price']);
                $priceStr = function_exists('sk_mcp_tool_inr') ? sk_mcp_tool_inr($price) : "₹{$price}";

                // Update conversation agent state
                $this->db->where('id', (int)$conv['id'])->update('wa_cloud_conversations', [
                    'agent_state' => json_encode([
                        'product_id'    => (int)$prod['id'],
                        'variant_id'    => (int)$var['id'],
                        'variant_label' => $vLabel,
                        'price'         => $price,
                    ], JSON_UNESCAPED_UNICODE),
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]);

                $waCloud->send_text($peer, "Selected: *{$pName}* ({$vLabel}) · {$priceStr} ✅");
                $this->_send_quantity_selector($prod, $var, $peer, $vendorId, $waCloud, $shopName, $conv);
                return;
            }
        }

        // 10. CHANGE QUANTITY CLICKED (e.g. cart_change_qty_4_4)
        if (strpos($interactiveId, 'cart_change_qty_') === 0) {
            $parts = explode('_', substr($interactiveId, 16));
            $productId = (int)($parts[0] ?? 0);
            $variantId = (int)($parts[1] ?? 0);

            $prod = $this->db->where('id', $productId)->get('products')->row_array();
            $var = $variantId > 0 ? $this->db->where('id', $variantId)->get('product_variants')->row_array() : null;

            if ($prod) {
                $this->_send_quantity_selector($prod, $var, $peer, $vendorId, $waCloud, $shopName, $conv);
                return;
            }
        }

        // 11. QUANTITY SELECTED (e.g. qty_2_4_4 -> qty 2, prod 4, var 4)
        if (strpos($interactiveId, 'qty_') === 0) {
            $parts = explode('_', substr($interactiveId, 4));
            $qty = max(1, (int)($parts[0] ?? 1));
            $productId = (int)($parts[1] ?? 0);
            $variantId = (int)($parts[2] ?? 0);

            $this->_handle_cart_quantity_update($peer, $vendorId, $productId, $variantId, $qty, $waCloud, $shopName, $conv);
            return;
        }

        // 12. TEXT-BASED QUANTITY INPUT (e.g. user typed "2", "qty 2", "3 pcs")
        if (preg_match('/^(?:qty\s*)?([1-9]|10)(?:\s*(?:pcs?|pieces?))?$/iu', $trimBody, $mQty)) {
            $qty = (int)$mQty[1];
            $state = !empty($conv['agent_state']) ? json_decode((string)$conv['agent_state'], true) : [];
            if (!empty($state['product_id'])) {
                $productId = (int)$state['product_id'];
                $variantId = (int)($state['variant_id'] ?? 0);
                $this->_handle_cart_quantity_update($peer, $vendorId, $productId, $variantId, $qty, $waCloud, $shopName, $conv);
                return;
            }
        }

        // 13. VIEW CART / SHOPPING BAG:
        if (in_array($interactiveId, ['cart_view', 'cart'], true) || preg_match('/^(cart|bag|my cart|my bag|view cart|view bag)$/iu', $trimBody)) {
            $this->_send_cart_view($peer, $vendorId, $waCloud, $shopName);
            return;
        }

        // 14. CLEAR CART / EMPTY BAG:
        if ($interactiveId === 'cart_clear' || preg_match('/^(clear cart|empty cart|clear bag)$/iu', $trimBody)) {
            $this->db->where('session_id', 'wa_' . $peer)->delete('cart');
            $waCloud->send_text($peer, "🗑️ Your shopping bag has been emptied.\nReply with *1* or tap View Menu to browse our sarees!");
            return;
        }

        // 15. CHECKOUT / PLACE ORDER:
        if (in_array($interactiveId, ['cart_checkout', 'checkout', 'buy_now'], true) || preg_match('/^(checkout|buy now|place order|order now|pay)$/iu', $trimBody)) {
            $this->_send_checkout_card($peer, $vendorId, $waCloud, $shopName);
            return;
        }

        // 16. ROBUST PRODUCT SEARCH (e.g. user typed "Cotton saree do u have", "Kanjivaram saree do u have", "Handloom do u have", "silk", "blue", etc.)
        if (strlen($trimBody) >= 2) {
            $prods = $this->_search_catalog($body, $vendorId, 10);
            if (!empty($prods)) {
                $footer = function_exists('sk_mcp_tool_delivery_footer') ? sk_mcp_tool_delivery_footer($vendorId) : 'Free delivery over ₹999';
                $interactive = function_exists('sk_mcp_tool_build_product_interactive_list')
                    ? sk_mcp_tool_build_product_interactive_list($prods, $shopName, $footer)
                    : null;
                if ($interactive) {
                    $waCloud->send_interactive($peer, $interactive);
                    return;
                }
            } else {
                // If specific search had no matches, show friendly message with popular sarees
                $fallbackProds = $this->_search_catalog('', $vendorId, 8);
                if (!empty($fallbackProds)) {
                    $waCloud->send_text($peer, "We couldn't find exact matches for *{$body}* at {$shopName} 😊\nHere are our most popular saree collections you might love:");
                    $footer = function_exists('sk_mcp_tool_delivery_footer') ? sk_mcp_tool_delivery_footer($vendorId) : 'Free delivery over ₹999';
                    $interactive = function_exists('sk_mcp_tool_build_product_interactive_list')
                        ? sk_mcp_tool_build_product_interactive_list($fallbackProds, $shopName, $footer)
                        : null;
                    if ($interactive) {
                        $waCloud->send_interactive($peer, $interactive);
                        return;
                    }
                }
            }
        }

        // 17. Fallback: release thread control to Meta Business Agent
        if (function_exists('sk_meta_ba_thread_control')) {
            sk_meta_ba_thread_control($phoneNumberId, $peer, 'release');
        }
    }

    /**
     * Smart multi-attribute catalog search across name, category, fabric, color, tags, description, and SKU.
     * Cleans conversational noise words like 'do you have', 'show me', 'is there', etc.
     */
    private function _search_catalog(string $query, int $vendorId, int $limit = 10): array {
        $q = trim(mb_strtolower($query, 'UTF-8'));
        if ($q === '') {
            return $this->db->where('vendor_id', $vendorId)
                            ->where('status', 'active')
                            ->order_by('id', 'ASC')
                            ->limit($limit)
                            ->get('products')
                            ->result_array();
        }

        // Strip conversational filler phrases and noise words
        $noisePhrases = [
            'do you have', 'do u have', 'do you got', 'have you got', 'u have', 'you have',
            'is there', 'are there', 'can you show', 'can u show', 'show me', 'show',
            'give me', 'send me', 'tell me about', 'tell me', 'looking for', 'look for',
            'i want', 'i need', 'we want', 'we need', 'please show', 'please send',
            'what do you have', 'what do u have', 'any', 'available', 'avail',
            'in stock', 'stock', 'collection', 'catalog', 'options', 'designs', 'models',
            'hi', 'hai', 'hello', 'hey', 'please', 'pls', 'plz', 'vanakkam'
        ];
        
        $cleaned = $q;
        foreach ($noisePhrases as $np) {
            $cleaned = preg_replace('/\b' . preg_quote($np, '/') . '\b/iu', ' ', $cleaned);
        }
        $cleaned = trim(preg_replace('/[?!.,\-_#@%^&*()+=~`]/u', ' ', $cleaned));
        $cleaned = trim(preg_replace('/\s+/u', ' ', $cleaned));

        // If after cleaning words like 'saree' / 'sarees' remain alone, return top catalog
        $withoutSaree = trim((string)preg_replace('/\b(saree|sarees|cloth|clothes|dress|item|items|product|products)\b/iu', ' ', $cleaned));
        $withoutSaree = trim(preg_replace('/\s+/u', ' ', $withoutSaree));
        if ($withoutSaree !== '') {
            $searchTerm = $withoutSaree;
        } else {
            $searchTerm = $cleaned;
        }

        if ($searchTerm === '') {
            return $this->db->where('vendor_id', $vendorId)
                            ->where('status', 'active')
                            ->order_by('id', 'ASC')
                            ->limit($limit)
                            ->get('products')
                            ->result_array();
        }

        $terms = array_filter(explode(' ', $searchTerm), static function($t) {
            return mb_strlen($t, 'UTF-8') >= 2;
        });

        $results = [];
        if (!empty($terms)) {
            $fullTerm = implode(' ', $terms);

            // 1. Primary Match: match full phrase
            $this->db->select('p.*, c.name as category_name')
                     ->from('products p')
                     ->join('categories c', 'c.id = p.category_id', 'left')
                     ->where('p.vendor_id', $vendorId)
                     ->where('p.status', 'active')
                     ->group_start()
                         ->like('p.name', $fullTerm)
                         ->or_like('p.fabric', $fullTerm)
                         ->or_like('p.color', $fullTerm)
                         ->or_like('p.tags', $fullTerm)
                         ->or_like('p.description', $fullTerm)
                         ->or_like('p.sku', $fullTerm)
                         ->or_like('c.name', $fullTerm)
                     ->group_end()
                     ->limit($limit);
            $results = $this->db->get()->result_array();

            // 2. Secondary Match: match individual terms with OR logic
            if (empty($results) && count($terms) > 1) {
                $this->db->select('p.*, c.name as category_name')
                         ->from('products p')
                         ->join('categories c', 'c.id = p.category_id', 'left')
                         ->where('p.vendor_id', $vendorId)
                         ->where('p.status', 'active')
                         ->group_start();
                foreach ($terms as $t) {
                    if (mb_strlen($t, 'UTF-8') < 3) continue;
                    $this->db->or_like('p.name', $t)
                             ->or_like('p.fabric', $t)
                             ->or_like('p.color', $t)
                             ->or_like('p.tags', $t)
                             ->or_like('p.description', $t)
                             ->or_like('c.name', $t);
                }
                $this->db->group_end()->limit($limit);
                $results = $this->db->get()->result_array();
            }
        }

        return $results;
    }

    /**
     * Send rich product detail card with image, price, specs, and variant/quantity selector.
     */
    private function _send_product_details(array $prod, string $peer, int $vendorId, Whatsapp_cloud $waCloud, string $shopName, array $conv): void {
        $prodId = (int)$prod['id'];
        $pName = (string)$prod['name'];
        $origPrice = (float)($prod['price'] ?? 0);
        $salePrice = isset($prod['sale_price']) && (float)$prod['sale_price'] > 0 ? (float)$prod['sale_price'] : $origPrice;
        $priceStr = function_exists('sk_mcp_tool_inr') ? sk_mcp_tool_inr($salePrice) : "₹{$salePrice}";
        $origStr = function_exists('sk_mcp_tool_inr') ? sk_mcp_tool_inr($origPrice) : "₹{$origPrice}";
        $discountPct = ($origPrice > $salePrice && $origPrice > 0) ? round((($origPrice - $salePrice) / $origPrice) * 100) : 0;

        $fabric = !empty($prod['fabric']) ? "🧵 Fabric: " . $prod['fabric'] : '';
        $color = !empty($prod['color']) ? "🎨 Color: " . $prod['color'] : '';
        $length = !empty($prod['saree_length']) ? "📏 Length: " . $prod['saree_length'] : '';
        $blouse = !empty($prod['blouse_included']) ? "👚 Blouse: Included" : '';
        $specs = array_filter([$fabric, $color, $length, $blouse]);
        $specStr = !empty($specs) ? implode("\n", $specs) . "\n" : '';

        $priceDisplay = $discountPct > 0 ? "💰 Price: {$priceStr} ~{$origStr}~ ({$discountPct}% OFF)" : "💰 Price: {$priceStr}";
        $caption = "🥻 *{$pName}*\n{$priceDisplay}\n{$specStr}📦 Stock: In Stock (Ready to dispatch)";

        $imgPath = function_exists('sk_mcp_tool_product_image_path') ? sk_mcp_tool_product_image_path($prod) : (string)($prod['thumbnail'] ?? '');
        $imgUrl = function_exists('sk_mcp_tool_whatsapp_image_url') && $imgPath !== '' ? sk_mcp_tool_whatsapp_image_url($imgPath) : '';

        if ($imgUrl !== '') {
            $waCloud->send_media($peer, 'image', $imgUrl, $caption);
        } else {
            $waCloud->send_text($peer, $caption);
        }

        // Fetch active variants for this product
        $variants = $this->db->where('product_id', $prodId)
                             ->where('status', 1)
                             ->order_by('is_default', 'DESC')
                             ->order_by('sort_order', 'ASC')
                             ->get('product_variants')
                             ->result_array();

        // Save active product in conversation agent_state
        $defaultVarId = !empty($variants) ? (int)$variants[0]['id'] : 0;
        $this->db->where('id', (int)$conv['id'])->update('wa_cloud_conversations', [
            'agent_state' => json_encode([
                'product_id'    => $prodId,
                'variant_id'    => $defaultVarId,
                'product_name'  => $pName,
                'price'         => $salePrice,
            ], JSON_UNESCAPED_UNICODE),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        if (count($variants) > 1) {
            // Send Variant Selector List
            $secRows = [];
            foreach (array_slice($variants, 0, 9) as $var) {
                $vPrice = (float)($var['sale_price'] && (float)$var['sale_price'] > 0 ? $var['sale_price'] : $var['price']);
                $vPriceStr = function_exists('sk_mcp_tool_inr') ? sk_mcp_tool_inr($vPrice) : "₹{$vPrice}";
                $label = trim((string)($var['label'] ?? 'Standard'));
                $secRows[] = [
                    'id'          => 'var_' . $var['id'] . '_' . $prodId,
                    'title'       => function_exists('mb_substr') ? mb_substr($label, 0, 24) : substr($label, 0, 24),
                    'description' => "{$vPriceStr} · In Stock",
                ];
            }
            $secRows[] = [
                'id'          => 'qty_1_' . $prodId . '_' . $defaultVarId,
                'title'       => '🛒 Buy Default (Qty: 1)',
                'description' => "{$priceStr} · Quick Order",
            ];

            $interactive = [
                'type'   => 'list',
                'header' => [
                    'type' => 'text',
                    'text' => function_exists('mb_substr') ? mb_substr("{$pName} Variants", 0, 60) : substr("{$pName} Variants", 0, 60),
                ],
                'body'   => [
                    'text' => "This saree is available in multiple variants ✨\nPlease choose your preferred style or variant below:",
                ],
                'footer' => [
                    'text' => 'Tap Choose Variant to proceed',
                ],
                'action' => [
                    'button'   => 'Choose Variant',
                    'sections' => [
                        [
                            'title' => 'Available Variants',
                            'rows'  => $secRows,
                        ],
                    ],
                ],
            ];
            $waCloud->send_interactive($peer, $interactive);
        } else {
            // Single variant or default — prompt for Quantity immediately!
            $defaultVar = !empty($variants) ? $variants[0] : null;
            $this->_send_quantity_selector($prod, $defaultVar, $peer, $vendorId, $waCloud, $shopName, $conv);
        }
    }

    /**
     * Send native Quantity Selection Drawer (1 to 5 pieces).
     */
    private function _send_quantity_selector(array $prod, ?array $variant, string $peer, int $vendorId, Whatsapp_cloud $waCloud, string $shopName, array $conv): void {
        $prodId = (int)$prod['id'];
        $varId = $variant ? (int)$variant['id'] : 0;
        $pName = (string)$prod['name'];
        $unitPrice = $variant && (float)$variant['price'] > 0
            ? (float)($variant['sale_price'] && (float)$variant['sale_price'] > 0 ? $variant['sale_price'] : $variant['price'])
            : (float)($prod['sale_price'] && (float)$prod['sale_price'] > 0 ? $prod['sale_price'] : $prod['price']);

        $vLabel = $variant ? trim((string)($variant['label'] ?? '')) : '';
        $header = function_exists('mb_substr') ? mb_substr("Select Quantity", 0, 60) : "Select Quantity";
        $body = "How many pieces of *{$pName}*" . ($vLabel !== '' ? " ({$vLabel})" : '') . " would you like to order?\nPlease select below:";

        $rows = [];
        $qtyLabels = [
            1 => ['title' => '1️⃣ 1 Piece', 'tag' => 'Free delivery eligible'],
            2 => ['title' => '2️⃣ 2 Pieces', 'tag' => 'Best value bundle'],
            3 => ['title' => '3️⃣ 3 Pieces', 'tag' => 'Popular choice'],
            4 => ['title' => '4️⃣ 4 Pieces', 'tag' => 'Festive pack'],
            5 => ['title' => '5️⃣ 5 Pieces', 'tag' => 'Bulk pack'],
        ];

        foreach ($qtyLabels as $count => $info) {
            $subTot = $unitPrice * $count;
            $subTotStr = function_exists('sk_mcp_tool_inr') ? sk_mcp_tool_inr($subTot) : "₹{$subTot}";
            $rows[] = [
                'id'          => "qty_{$count}_{$prodId}_{$varId}",
                'title'       => $info['title'],
                'description' => "{$subTotStr} · {$info['tag']}",
            ];
        }

        $interactive = [
            'type'   => 'list',
            'header' => [
                'type' => 'text',
                'text' => $header,
            ],
            'body'   => [
                'text' => $body,
            ],
            'footer' => [
                'text' => 'Select quantity to update bag',
            ],
            'action' => [
                'button'   => 'Select Quantity',
                'sections' => [
                    [
                        'title' => 'Choose Quantity',
                        'rows'  => $rows,
                    ],
                ],
            ],
        ];

        $waCloud->send_interactive($peer, $interactive);
    }

    /**
     * Upsert item in database-backed cart and send rich cart summary card with actions.
     */
    private function _handle_cart_quantity_update(string $peer, int $vendorId, int $productId, int $variantId, int $qty, Whatsapp_cloud $waCloud, string $shopName, array $conv): void {
        $sessionId = 'wa_' . $peer;
        $prod = $this->db->where('id', $productId)->get('products')->row_array();
        if (!$prod) {
            $waCloud->send_text($peer, "Product not found. Reply with *1* to browse sarees!");
            return;
        }

        $var = $variantId > 0 ? $this->db->where('id', $variantId)->get('product_variants')->row_array() : null;
        $unitPrice = $var && (float)$var['price'] > 0
            ? (float)($var['sale_price'] && (float)$var['sale_price'] > 0 ? $var['sale_price'] : $var['price'])
            : (float)($prod['sale_price'] && (float)$prod['sale_price'] > 0 ? $prod['sale_price'] : $prod['price']);

        $now = date('Y-m-d H:i:s');

        // Upsert into cart table
        $this->db->where('session_id', $sessionId)
                 ->where('product_id', $productId);
        if ($variantId > 0) {
            $this->db->where('variant_id', $variantId);
        } else {
            $this->db->where('(variant_id IS NULL OR variant_id = 0)', null, false);
        }
        $existing = $this->db->get('cart')->row_array();

        if ($existing) {
            $this->db->where('id', (int)$existing['id'])->update('cart', [
                'quantity'   => $qty,
                'updated_at' => $now,
            ]);
        } else {
            $this->db->insert('cart', [
                'vendor_id'  => $vendorId,
                'session_id' => $sessionId,
                'product_id' => $productId,
                'variant_id' => $variantId > 0 ? $variantId : null,
                'quantity'   => $qty,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Update conversation agent state
        $this->db->where('id', (int)$conv['id'])->update('wa_cloud_conversations', [
            'agent_state' => json_encode([
                'product_id'    => $productId,
                'variant_id'    => $variantId,
                'product_name'  => (string)$prod['name'],
                'variant_label' => (string)($var['label'] ?? ''),
                'quantity'      => $qty,
                'price'         => $unitPrice,
            ], JSON_UNESCAPED_UNICODE),
            'updated_at'  => $now,
        ]);

        // Compute full cart items & total
        $cartItems = $this->db->select('c.*, p.name as product_name, p.price as prod_price, p.sale_price as prod_sale, pv.label as variant_label, pv.price as var_price, pv.sale_price as var_sale')
                              ->from('cart c')
                              ->join('products p', 'p.id = c.product_id')
                              ->join('product_variants pv', 'pv.id = c.variant_id', 'left')
                              ->where('c.session_id', $sessionId)
                              ->where('c.vendor_id', $vendorId)
                              ->get()->result_array();

        $totalItems = 0;
        $totalVal = 0.0;
        foreach ($cartItems as $ci) {
            $q = (int)$ci['quantity'];
            $price = $ci['var_price'] !== null && (float)$ci['var_price'] > 0
                ? (float)($ci['var_sale'] && (float)$ci['var_sale'] > 0 ? $ci['var_sale'] : $ci['var_price'])
                : (float)($ci['prod_sale'] && (float)$ci['prod_sale'] > 0 ? $ci['prod_sale'] : $ci['prod_price']);
            $totalItems += $q;
            $totalVal += ($price * $q);
        }

        $pName = (string)$prod['name'];
        $vLabel = $var ? (string)($var['label'] ?? '') : '';
        $lineTot = function_exists('sk_mcp_tool_inr') ? sk_mcp_tool_inr($unitPrice * $qty) : "₹" . ($unitPrice * $qty);
        $cartTot = function_exists('sk_mcp_tool_inr') ? sk_mcp_tool_inr($totalVal) : "₹{$totalVal}";

        $varText = $vLabel !== '' ? "• *Variant:* {$vLabel}\n" : '';
        $msg = "✅ *Shopping Bag Updated!*\n\n"
             . "🛍️ *{$pName}*\n"
             . $varText
             . "• *Quantity:* {$qty} piece(s)\n"
             . "• *Subtotal:* {$lineTot}\n\n"
             . "🛒 *Your Bag Summary ({$totalItems} item" . ($totalItems > 1 ? 's' : '') . "):*\n"
             . "• *Bag Total:* {$cartTot}\n"
             . "• *Delivery:* FREE Delivery 🚚\n\n"
             . "What would you like to do next?";

        $waCloud->send_text($peer, $msg);

        // Send Next Steps interactive list
        $interactive = [
            'type'   => 'list',
            'header' => [
                'type' => 'text',
                'text' => function_exists('mb_substr') ? mb_substr("{$shopName} Bag", 0, 60) : "Shopping Bag",
            ],
            'body'   => [
                'text' => "Your bag total is {$cartTot} ({$totalItems} items). Please select an action below:",
            ],
            'footer' => [
                'text' => 'Free delivery applied',
            ],
            'action' => [
                'button'   => 'Bag Options',
                'sections' => [
                    [
                        'title' => 'Actions',
                        'rows'  => [
                            [
                                'id'          => 'cart_checkout',
                                'title'       => '💳 Place Order & Pay',
                                'description' => "Complete order ({$cartTot})",
                            ],
                            [
                                'id'          => 'cart_view',
                                'title'       => '🛒 View Shopping Bag',
                                'description' => "Review {$totalItems} item(s) in bag",
                            ],
                            [
                                'id'          => "cart_change_qty_{$productId}_{$variantId}",
                                'title'       => '🔄 Change Quantity',
                                'description' => "Update pieces for {$pName}",
                            ],
                            [
                                'id'          => 'browse_products',
                                'title'       => '➕ Add More Sarees',
                                'description' => 'Explore more collections',
                            ],
                            [
                                'id'          => 'main_menu',
                                'title'       => '🏠 Main Menu',
                                'description' => 'Return to main options',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $waCloud->send_interactive($peer, $interactive);
    }

    /**
     * Send full itemized view of customer's shopping bag with actions.
     */
    private function _send_cart_view(string $peer, int $vendorId, Whatsapp_cloud $waCloud, string $shopName): void {
        $sessionId = 'wa_' . $peer;
        $cartItems = $this->db->select('c.*, p.name as product_name, p.price as prod_price, p.sale_price as prod_sale, pv.label as variant_label, pv.price as var_price, pv.sale_price as var_sale')
                              ->from('cart c')
                              ->join('products p', 'p.id = c.product_id')
                              ->join('product_variants pv', 'pv.id = c.variant_id', 'left')
                              ->where('c.session_id', $sessionId)
                              ->where('c.vendor_id', $vendorId)
                              ->get()->result_array();

        if (empty($cartItems)) {
            $waCloud->send_text($peer, "Your shopping bag is empty 😊\nReply with *1* to browse our sarees or send a saree name to search!");
            return;
        }

        $totalItems = 0;
        $totalVal = 0.0;
        $lines = [];

        foreach ($cartItems as $idx => $ci) {
            $q = (int)$ci['quantity'];
            $price = $ci['var_price'] !== null && (float)$ci['var_price'] > 0
                ? (float)($ci['var_sale'] && (float)$ci['var_sale'] > 0 ? $ci['var_sale'] : $ci['var_price'])
                : (float)($ci['prod_sale'] && (float)$ci['prod_sale'] > 0 ? $ci['prod_sale'] : $ci['prod_price']);
            $itemTot = $price * $q;
            $totalItems += $q;
            $totalVal += $itemTot;

            $pName = (string)$ci['product_name'];
            $vLabel = !empty($ci['variant_label']) ? " ({$ci['variant_label']})" : '';
            $priceStr = function_exists('sk_mcp_tool_inr') ? sk_mcp_tool_inr($price) : "₹{$price}";
            $totStr = function_exists('sk_mcp_tool_inr') ? sk_mcp_tool_inr($itemTot) : "₹{$itemTot}";

            $lines[] = ($idx + 1) . ". *{$pName}*{$vLabel}\n   Qty: {$q} × {$priceStr} = *{$totStr}*";
        }

        $cartTot = function_exists('sk_mcp_tool_inr') ? sk_mcp_tool_inr($totalVal) : "₹{$totalVal}";
        $itemsText = implode("\n\n", $lines);

        $msg = "🛒 *Your Shopping Bag ({$totalItems} items)*\n\n"
             . "{$itemsText}\n\n"
             . "--------------------------------\n"
             . "💰 *Bag Total: {$cartTot}*\n"
             . "🚚 *Delivery: FREE Delivery*\n";

        $waCloud->send_text($peer, $msg);

        $interactive = [
            'type'   => 'list',
            'header' => [
                'type' => 'text',
                'text' => 'Shopping Bag Actions',
            ],
            'body'   => [
                'text' => "Total payable is {$cartTot}. Choose an action below:",
            ],
            'footer' => [
                'text' => 'Free delivery on all orders',
            ],
            'action' => [
                'button'   => 'Bag Actions',
                'sections' => [
                    [
                        'title' => 'Actions',
                        'rows'  => [
                            [
                                'id'          => 'cart_checkout',
                                'title'       => '💳 Place Order & Pay',
                                'description' => "Checkout now ({$cartTot})",
                            ],
                            [
                                'id'          => 'browse_products',
                                'title'       => '➕ Add More Sarees',
                                'description' => 'Browse more collections',
                            ],
                            [
                                'id'          => 'cart_clear',
                                'title'       => '🗑️ Clear Shopping Bag',
                                'description' => 'Remove all items from bag',
                            ],
                            [
                                'id'          => 'main_menu',
                                'title'       => '🏠 Main Menu',
                                'description' => 'Return to main options',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $waCloud->send_interactive($peer, $interactive);
    }

    /**
     * Send checkout payment card with link and delivery guidance.
     */
    private function _send_checkout_card(string $peer, int $vendorId, Whatsapp_cloud $waCloud, string $shopName): void {
        $sessionId = 'wa_' . $peer;
        $cartItems = $this->db->select('c.*, p.name as product_name, p.price as prod_price, p.sale_price as prod_sale, pv.label as variant_label, pv.price as var_price, pv.sale_price as var_sale')
                              ->from('cart c')
                              ->join('products p', 'p.id = c.product_id')
                              ->join('product_variants pv', 'pv.id = c.variant_id', 'left')
                              ->where('c.session_id', $sessionId)
                              ->where('c.vendor_id', $vendorId)
                              ->get()->result_array();

        if (empty($cartItems)) {
            $waCloud->send_text($peer, "Your shopping bag is empty 😊\nReply with *1* to browse sarees before checking out!");
            return;
        }

        $totalItems = 0;
        $totalVal = 0.0;
        foreach ($cartItems as $ci) {
            $q = (int)$ci['quantity'];
            $price = $ci['var_price'] !== null && (float)$ci['var_price'] > 0
                ? (float)($ci['var_sale'] && (float)$ci['var_sale'] > 0 ? $ci['var_sale'] : $ci['var_price'])
                : (float)($ci['prod_sale'] && (float)$ci['prod_sale'] > 0 ? $ci['prod_sale'] : $ci['prod_price']);
            $totalItems += $q;
            $totalVal += ($price * $q);
        }

        $cartTot = function_exists('sk_mcp_tool_inr') ? sk_mcp_tool_inr($totalVal) : "₹{$totalVal}";
        $checkoutUrl = "https://talkaipilot.com/checkout?session=" . urlencode($sessionId);

        $msg = "🛍️ *Ready to Place Your Order!*\n\n"
             . "• *Items in Bag:* {$totalItems}\n"
             . "• *Total Payable:* {$cartTot}\n"
             . "• *Shipping:* FREE Delivery\n\n"
             . "💳 *Complete Your Purchase:*\n"
             . "Tap the secure checkout link below to enter your delivery address & pay:\n"
             . "👉 {$checkoutUrl}\n\n"
             . "✨ *Payment Methods Accepted:*\n"
             . "• Google Pay / PhonePe / Paytm / UPI\n"
             . "• Credit / Debit Cards & Net Banking\n"
             . "• Cash on Delivery (COD) available\n\n"
             . "Once confirmed, your saree will be dispatched with live tracking!";

        $waCloud->send_text($peer, $msg);
    }
}
