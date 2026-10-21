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
        $this->load->helper(['sk_whatsapp_cloud', 'sk_whatsapp', 'sk_whatsapp_mcp', 'sk_meta_business_agent', 'sk_mcp_tool', 'sk_razorpay', 'sk_invoice', 'sk_currency']);
        $this->load->model(['Sk_Whatsapp_cloud_model', 'Sk_Admin_model', 'Sk_Vendor_meta_agent_model', 'Sk_Order_model', 'Sk_Product_model', 'Sk_User_model']);
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
                $this->_handle_inbound_customer_message($raw, $m, $conv, $peer, $body, $vendorId, $phoneNumberId, $settings);
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

        // Keep thread control with this App so Meta Cloud AI does NOT send Hindi messages
        if (function_exists('sk_meta_ba_thread_control') && $phoneNumberId !== '') {
            sk_meta_ba_thread_control($phoneNumberId, $peer, 'take');
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
        $msgType = (string)($m['type'] ?? $raw['type'] ?? '');

        // Native WhatsApp catalog cart (customer added products in the catalog and sent the order).
        if ($msgType === 'order' || !empty($raw['order']['product_items']) || !empty($m['order']['product_items'])) {
            $this->_handle_whatsapp_catalog_order($raw, $m, $peer, $vendorId, $waCloud, $shopName, $conv, $phoneNumberId);
            return;
        }

        $flowReply = $raw['interactive']['nfm_reply']['response_json'] ?? ($m['interactive']['nfm_reply']['response_json'] ?? null);
        if ($flowReply !== null || (($raw['interactive']['type'] ?? '') === 'nfm_reply')) {
            $this->_handle_flow_reply($peer, $vendorId, $waCloud, $shopName, $conv, (string)$flowReply, $phoneNumberId);
            return;
        }

        $agentState = !empty($conv['agent_state']) ? json_decode((string)$conv['agent_state'], true) : [];
        $isWaitingAddress = is_array($agentState) && (($agentState['step'] ?? '') === 'waiting_for_address');

        // 1. GREETING / MAIN MENU:
        $isGreeting = preg_match('/^(hi|hai|hello|hey|vanakkam|menu|main menu|start|help|வணக்கம்)(\s+(there|team|vel|bro|sir|mam|madam|all))?[\s!.,?]*$/iu', $trimBody)
            || in_array($interactiveId, ['main_menu', 'menu'], true);

        // If customer is currently entering delivery address, process the address text or location pin
        if ($isWaitingAddress && !$isGreeting && $interactiveId === '') {
            $this->_handle_inbound_address_entry($peer, $vendorId, $body, $raw, $waCloud, $shopName, $conv);
            return;
        }

        if ($isGreeting) {
            $this->load->helper('sk_wa_address_flow');
            $this->db->where('id', (int)$conv['id'])->update('wa_cloud_conversations', [
                'agent_state' => null,
                'handoff_at'  => null,
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
            $waCloud->send_interactive($peer, sk_wa_shop_welcome_interactive($vendorId, $shopName));
            return;
        }

        $step = is_array($agentState) ? (string)($agentState['step'] ?? '') : '';
        if ($interactiveId === '' && !$isGreeting) {
            if ($step === 'waiting_for_search' && $trimBody !== '') {
                $this->_reply_catalog_search($peer, $vendorId, $waCloud, $shopName, $phoneNumberId, $trimBody);
                return;
            }
            if ($step === 'waiting_for_team_message' && $trimBody !== '') {
                $this->_forward_team_message($peer, $vendorId, $waCloud, $shopName, $conv, $trimBody);
                return;
            }
            if ($step === 'team_chat' && $trimBody !== '') {
                $this->_forward_team_message($peer, $vendorId, $waCloud, $shopName, $conv, $trimBody, false);
                return;
            }
        }

        // 2. BUTTON 1 — 🛍️ BROWSE PRODUCTS / SAREES / CATALOG:
        $isBrowse = $interactiveId === 'browse_products'
            || $interactiveId === 'browse_sarees'
            || $trimBody === '1'
            || preg_match('/^(browse|browse sarees?|sarees?|sari|saris|catalog|view catalog|show sarees?|add sarees?|collections?|categories|category)$/iu', $trimBody);

        if ($isBrowse) {
            $this->_send_saree_catalog($peer, $vendorId, $waCloud, $shopName, $phoneNumberId);
            return;
        }

        // 3. BUTTON 2 — OFFERS & DEALS opens the same WhatsApp catalog.
        if ($interactiveId === 'offers_deals' || $trimBody === '2' || preg_match('/^(offers|deals|discounts|coupons)$/iu', $trimBody)) {
            $this->_send_saree_catalog($peer, $vendorId, $waCloud, $shopName, $phoneNumberId);
            return;
        }

        // 4. SEARCH A PRODUCT — WhatsApp Flow form, then catalog matches.
        if ($interactiveId === 'search_product' || $trimBody === '3') {
            $this->load->helper('sk_wa_address_flow');
            if (sk_wa_menu_flow_send($waCloud, $peer, $vendorId, $phoneNumberId, 'SEARCH')) {
                return;
            }
            $this->db->where('id', (int)$conv['id'])->update('wa_cloud_conversations', [
                'agent_state' => json_encode(['step' => 'waiting_for_search'], JSON_UNESCAPED_UNICODE),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
            $waCloud->send_text($peer, "Type the product name. Matching items open from the catalog.");
            return;
        }

        // 5. TRACK MY ORDER — WhatsApp Flow form, then order lookup.
        if ($interactiveId === 'track_order' || $trimBody === '4' || preg_match('/^(track|track order|where is my order)$/iu', $trimBody)) {
            $this->load->helper('sk_wa_address_flow');
            if (sk_wa_menu_flow_send($waCloud, $peer, $vendorId, $phoneNumberId, 'TRACK')) {
                return;
            }
            $this->_handle_track_order($peer, $vendorId, $waCloud, $shopName);
            return;
        }

        // 6. TALK TO OUR TEAM — WhatsApp Flow form, then notify the shop owner.
        if ($interactiveId === 'talk_to_team' || $trimBody === '5' || preg_match('/^(talk to team|human|support|agent)$/iu', $trimBody)) {
            $this->load->helper('sk_wa_address_flow');
            if (sk_wa_menu_flow_send($waCloud, $peer, $vendorId, $phoneNumberId, 'TALK')) {
                return;
            }
            $this->db->where('id', (int)$conv['id'])->update('wa_cloud_conversations', [
                'agent_state' => json_encode(['step' => 'waiting_for_team_message'], JSON_UNESCAPED_UNICODE),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
            $waCloud->send_text($peer, "Write your message. It is sent to the {$shopName} owner, and you can keep chatting here.");
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
                              ->limit(30)
                              ->get('products')
                              ->result_array();

            if (!empty($prods)) {
                if ($this->_send_catalog_product_list($peer, $vendorId, $phoneNumberId, $waCloud, $shopName, $prods, $catName)) {
                    return;
                }
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

        // 11b. MORE QUANTITY / VARIANT SELECTOR CLICKED (from product card buttons)
        if (strpos($interactiveId, 'more_qty_') === 0) {
            $parts = explode('_', substr($interactiveId, 9));
            $productId = (int)($parts[0] ?? 0);
            $variantId = (int)($parts[1] ?? 0);

            $prod = $this->db->where('id', $productId)->get('products')->row_array();
            $var = $variantId > 0 ? $this->db->where('id', $variantId)->get('product_variants')->row_array() : null;

            if ($prod) {
                $variants = $this->db->where('product_id', $productId)->where('status', 1)->get('product_variants')->result_array();
                if (count($variants) > 1 && $variantId === 0) {
                    $this->_send_variant_selector($prod, $variants, $peer, $waCloud, $shopName);
                } else {
                    $this->_send_quantity_selector($prod, $var, $peer, $vendorId, $waCloud, $shopName, $conv);
                }
                return;
            }
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

        // 12b. REMOVE ONE CART LINE (cart_remove_{product}_{variant})
        if (strpos($interactiveId, 'cart_remove_') === 0) {
            $parts = explode('_', substr($interactiveId, 12));
            $this->_remove_cart_item($peer, $vendorId, (int)($parts[0] ?? 0), (int)($parts[1] ?? 0), $waCloud, $shopName);
            return;
        }

        // 13. VIEW CART
        if (in_array($interactiveId, ['cart_view', 'cart'], true) || preg_match('/^(cart|bag|my cart|my bag|view cart|view bag)$/iu', $trimBody)) {
            $this->_send_cart_view($peer, $vendorId, $waCloud, $shopName);
            return;
        }

        // 14. CLEAR CART
        if ($interactiveId === 'cart_clear' || preg_match('/^(clear cart|empty cart|clear bag|clear my cart)$/iu', $trimBody)) {
            $this->db->where('session_id', 'wa_' . $peer)->where('vendor_id', $vendorId)->delete('cart');
            $this->_send_cart_view($peer, $vendorId, $waCloud, $shopName);
            return;
        }

        // 15. CHECKOUT / PLACE ORDER: ASK DELIVERY ADDRESS FIRST!
        if (in_array($interactiveId, ['cart_checkout', 'checkout', 'buy_now'], true) || preg_match('/^(checkout|buy now|place order|order now|order)$/iu', $trimBody)) {
            $this->_initiate_checkout_address_flow($peer, $vendorId, $waCloud, $shopName, $conv, $phoneNumberId);
            return;
        }

        // 15a. CONFIRM SAVED DELIVERY ADDRESS (e.g. addr_confirm_2, addr_proceed_2)
        if (strpos($interactiveId, 'addr_confirm_') === 0 || strpos($interactiveId, 'addr_proceed_') === 0) {
            $addrId = (int)str_replace(['addr_confirm_', 'addr_proceed_'], '', $interactiveId);
            $this->_send_checkout_payment_mode_options($peer, $vendorId, $waCloud, $shopName, $addrId);
            return;
        }

        // 15b. CHANGE / ENTER DELIVERY ADDRESS (addr_change)
        if ($interactiveId === 'addr_change') {
            $this->_prompt_for_delivery_address($peer, $vendorId, $waCloud, $shopName, $conv, $phoneNumberId, 'edit');
            return;
        }

        // 15c. PAYMENT MODE: CASH ON DELIVERY (COD)
        if (strpos($interactiveId, 'pay_cod') === 0 || preg_match('/^(cod|cash on delivery)$/iu', $trimBody)) {
            $addrId = 0;
            if (strpos($interactiveId, 'pay_cod_') === 0) {
                $addrId = (int)str_replace('pay_cod_', '', $interactiveId);
            }
            $this->_place_order_cod($peer, $vendorId, $waCloud, $shopName, $conv, $settings, $addrId);
            return;
        }

        // 15d. PAYMENT MODE: PAY ONLINE (RAZORPAY)
        if (strpos($interactiveId, 'pay_online') === 0 || preg_match('/^(pay online|online|razorpay|upi|pay)$/iu', $trimBody)) {
            $addrId = 0;
            if (strpos($interactiveId, 'pay_online_') === 0) {
                $addrId = (int)str_replace('pay_online_', '', $interactiveId);
            }
            $this->_place_order_online($peer, $vendorId, $waCloud, $shopName, $conv, $settings, $addrId);
            return;
        }

        // 15e. CONFIRM ONLINE PAYMENT COMPLETED ("I Have Paid")
        if (strpos($interactiveId, 'paid_') === 0 || preg_match('/^(i have paid|paid|payment done|paid online)$/iu', $trimBody)) {
            $orderId = (int)str_replace('paid_', '', $interactiveId);
            $this->_confirm_online_payment($orderId, $peer, $vendorId, $waCloud, $shopName, $conv, $settings);
            return;
        }

        // 15f. SWITCH EXISTING ORDER TO COD
        if (strpos($interactiveId, 'switch_cod_') === 0) {
            $orderId = (int)str_replace('switch_cod_', '', $interactiveId);
            $this->_switch_order_to_cod($orderId, $peer, $vendorId, $waCloud, $shopName, $conv);
            return;
        }

        // 16. Free text opens the WhatsApp catalog (or a catalog product list), not an AI chat.
        if (strlen($trimBody) >= 2) {
            $prods = $this->_search_catalog($body, $vendorId, 30);
            if (!empty($prods) && $this->_send_catalog_product_list($peer, $vendorId, $phoneNumberId, $waCloud, $shopName, $prods, $shopName)) {
                return;
            }
            if ($this->_send_native_whatsapp_catalog($peer, $vendorId, $phoneNumberId, $waCloud, $shopName)) {
                return;
            }
            if (!empty($prods)) {
                $footer = function_exists('sk_mcp_tool_delivery_footer') ? sk_mcp_tool_delivery_footer($vendorId) : 'Free delivery over ₹999';
                $interactive = function_exists('sk_mcp_tool_build_product_interactive_list')
                    ? sk_mcp_tool_build_product_interactive_list(array_slice($prods, 0, 10), $shopName, $footer)
                    : null;
                if ($interactive) {
                    $waCloud->send_interactive($peer, $interactive);
                    return;
                }
            }
        }

        // 17. Helpful Conversational Fallback (Never release to Meta AI to prevent Hindi handoff)
        $this->load->helper('sk_wa_address_flow');
        $interactive = sk_wa_shop_welcome_interactive($vendorId, $shopName);
        $waCloud->send_interactive($peer, $interactive);
        return;
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
     * Send rich product detail card with image, price, specs, and direct quantity buttons.
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

        $priceDisplay = $discountPct > 0 ? "💰 Rate: {$priceStr} ~{$origStr}~ ({$discountPct}% OFF)" : "💰 Rate: {$priceStr}";
        $caption = "🥻 *{$pName}*\n{$priceDisplay}\n{$specStr}📦 Stock: In Stock (Ready to dispatch)\n🚚 Delivery: FREE Delivery across India";

        $imgPath = function_exists('sk_mcp_tool_product_image_path') ? sk_mcp_tool_product_image_path($prod) : (string)($prod['thumbnail'] ?? '');
        $imgUrl = function_exists('sk_mcp_tool_whatsapp_image_url') ? sk_mcp_tool_whatsapp_image_url($imgPath, $prod) : '';
        if ($imgUrl === '') {
            $imgUrl = 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?auto=format&fit=crop&w=600&q=80';
        }

        // Fetch active variants for this product
        $variants = $this->db->where('product_id', $prodId)
                             ->where('status', 1)
                             ->order_by('is_default', 'DESC')
                             ->order_by('sort_order', 'ASC')
                             ->get('product_variants')
                             ->result_array();

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

        $cardText = "🥻 *{$pName}*\n"
                  . "{$priceDisplay}\n\n"
                  . ($specStr !== '' ? "{$specStr}\n" : '')
                  . "📦 *Stock:* In Stock (Ready to dispatch)\n"
                  . "🚚 *Delivery:* FREE Delivery across India\n\n"
                  . "👉 *Select quantity below to order:*";

        $cardSent = false;
        $interactiveCard = [
            'type'   => 'button',
            'header' => [
                'type'  => 'image',
                'image' => ['link' => $imgUrl],
            ],
            'body'   => [
                'text' => $cardText,
            ],
            'footer' => [
                'text' => '100% Authentic Handloom Quality',
            ],
            'action' => [
                'buttons' => [
                    [
                        'type'  => 'reply',
                        'reply' => [
                            'id'    => "qty_1_{$prodId}_{$defaultVarId}",
                            'title' => '🛒 Buy 1 Pc',
                        ],
                    ],
                    [
                        'type'  => 'reply',
                        'reply' => [
                            'id'    => "qty_2_{$prodId}_{$defaultVarId}",
                            'title' => '🛍️ Buy 2 Pcs',
                        ],
                    ],
                    [
                        'type'  => 'reply',
                        'reply' => [
                            'id'    => "more_qty_{$prodId}_{$defaultVarId}",
                            'title' => count($variants) > 1 ? '✨ Styles / Qty' : '🔢 More Qty',
                        ],
                    ],
                ],
            ],
        ];
        $res = $waCloud->send_interactive($peer, $interactiveCard);
        if (!empty($res['success'])) {
            $cardSent = true;
        }

        if (!$cardSent) {
            // Fallback: send interactive button card with text header so buttons are never lost!
            $textButtonCard = $interactiveCard;
            $textButtonCard['header'] = [
                'type' => 'text',
                'text' => function_exists('mb_substr') ? mb_substr("🥻 {$pName}", 0, 60) : "🥻 {$pName}",
            ];
            $tRes = $waCloud->send_interactive($peer, $textButtonCard);
            if (!empty($tRes['success'])) {
                $cardSent = true;
            } else {
                $waCloud->send_text($peer, $caption);
                if (count($variants) > 1) {
                    $this->_send_variant_selector($prod, $variants, $peer, $waCloud, $shopName);
                } else {
                    $defaultVar = !empty($variants) ? $variants[0] : null;
                    $this->_send_quantity_selector($prod, $defaultVar, $peer, $vendorId, $waCloud, $shopName, $conv);
                }
            }
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
        $rows[] = [
            'id'          => "cart_remove_{$prodId}_{$varId}",
            'title'       => 'Remove from cart',
            'description' => 'Take this item out of the cart',
        ];

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
                'text' => 'Select quantity to update cart',
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

        $pName = (string)$prod['name'];
        $vLabel = $var ? (string)($var['label'] ?? '') : '';
        $lineTot = function_exists('sk_mcp_tool_inr') ? sk_mcp_tool_inr($unitPrice * $qty) : "₹" . ($unitPrice * $qty);

        $varText = $vLabel !== '' ? " ({$vLabel})" : '';
        $waCloud->send_text($peer, "✅ *{$pName}*{$varText} is now {$qty} in your cart.\nSubtotal: {$lineTot}");
        $this->_send_cart_view($peer, $vendorId, $waCloud, $shopName);
    }

    /**
     * Remove one product from the WhatsApp cart and show what is left.
     */
    private function _remove_cart_item(string $peer, int $vendorId, int $productId, int $variantId, Whatsapp_cloud $waCloud, string $shopName): void {
        $this->db->where('session_id', 'wa_' . $peer)
                 ->where('vendor_id', $vendorId)
                 ->where('product_id', $productId);
        if ($variantId > 0) {
            $this->db->where('variant_id', $variantId);
        } else {
            $this->db->where('(variant_id IS NULL OR variant_id = 0)', null, false);
        }
        $this->db->delete('cart');
        $waCloud->send_text($peer, "Removed from your cart.");
        $this->_send_cart_view($peer, $vendorId, $waCloud, $shopName);
    }

    private function _wa_list_title(string $text, int $max = 24): string {
        $text = trim((string)preg_replace('/\s+/', ' ', $text));
        $len = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        if ($len <= $max) {
            return $text;
        }
        $cut = function_exists('mb_substr') ? mb_substr($text, 0, $max - 3) : substr($text, 0, $max - 3);
        return rtrim($cut) . '...';
    }

    /**
     * Show every item already in the cart, with add, update, and remove.
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
            $empty = $waCloud->send_interactive($peer, [
                'type'   => 'button',
                'header' => ['type' => 'text', 'text' => 'Your cart'],
                'body'   => ['text' => "Your cart is empty.\nAdd a saree to start an order."],
                'action' => [
                    'buttons' => [
                        ['type' => 'reply', 'reply' => ['id' => 'browse_products', 'title' => 'Add saree']],
                        ['type' => 'reply', 'reply' => ['id' => 'main_menu', 'title' => 'Main menu']],
                    ],
                ],
            ]);
            if (empty($empty['success'])) {
                $waCloud->send_text($peer, "Your cart is empty. Reply 1 or send saree to add one.");
            }
            return;
        }

        $totalItems = 0;
        $totalVal = 0.0;
        $lines = [];
        $itemRows = [];

        foreach ($cartItems as $idx => $ci) {
            $q = (int)$ci['quantity'];
            $price = $ci['var_price'] !== null && (float)$ci['var_price'] > 0
                ? (float)($ci['var_sale'] && (float)$ci['var_sale'] > 0 ? $ci['var_sale'] : $ci['var_price'])
                : (float)($ci['prod_sale'] && (float)$ci['prod_sale'] > 0 ? $ci['prod_sale'] : $ci['prod_price']);
            $itemTot = $price * $q;
            $totalItems += $q;
            $totalVal += $itemTot;

            $pName = (string)$ci['product_name'];
            $vLabel = trim((string)($ci['variant_label'] ?? ''));
            $shown = $vLabel !== '' ? $pName . ' ' . $vLabel : $pName;
            $priceStr = function_exists('sk_mcp_tool_inr') ? sk_mcp_tool_inr($price) : "₹{$price}";
            $totStr = function_exists('sk_mcp_tool_inr') ? sk_mcp_tool_inr($itemTot) : "₹{$itemTot}";
            $lines[] = ($idx + 1) . ". *{$pName}*" . ($vLabel !== '' ? " ({$vLabel})" : '') . "\n   Qty: {$q} × {$priceStr} = *{$totStr}*";

            $productId = (int)$ci['product_id'];
            $variantId = (int)($ci['variant_id'] ?? 0);
            $itemRows[] = [
                'id'          => "cart_change_qty_{$productId}_{$variantId}",
                'title'       => $this->_wa_list_title('Update ' . $shown),
                'description' => $this->_wa_list_title("Qty {$q} · tap to change", 72),
            ];
            $itemRows[] = [
                'id'          => "cart_remove_{$productId}_{$variantId}",
                'title'       => $this->_wa_list_title('Remove ' . $shown),
                'description' => 'Remove this item from the cart',
            ];
        }

        $cartTot = function_exists('sk_mcp_tool_inr') ? sk_mcp_tool_inr($totalVal) : "₹{$totalVal}";
        $itemsText = implode("\n\n", $lines);
        $body = "Your cart ({$totalItems} items)\n\n{$itemsText}\n\nCart total: {$cartTot}\nDelivery: Free\n\nAdd a saree, or update and remove items below.";
        if (function_exists('mb_strlen') && mb_strlen($body) > 1024) {
            $body = mb_substr($body, 0, 1020) . '...';
        } elseif (strlen($body) > 1024) {
            $body = substr($body, 0, 1020) . '...';
        }

        $actionRows = [
            [
                'id'          => 'browse_products',
                'title'       => 'Add saree',
                'description' => 'Open the catalog and add another saree',
            ],
            [
                'id'          => 'cart_checkout',
                'title'       => 'Place order',
                'description' => "Checkout now ({$cartTot})",
            ],
        ];
        $room = 10 - count($actionRows) - 1;
        if (count($itemRows) > $room) {
            $itemRows = array_slice($itemRows, 0, $room);
        }
        $actionRows[] = [
            'id'          => 'cart_clear',
            'title'       => 'Clear cart',
            'description' => 'Remove every item from the cart',
        ];

        $sections = [
            ['title' => 'Cart', 'rows' => $actionRows],
        ];
        if ($itemRows !== []) {
            $sections[] = ['title' => 'Update or remove', 'rows' => $itemRows];
        }

        $sent = $waCloud->send_interactive($peer, [
            'type'   => 'list',
            'header' => ['type' => 'text', 'text' => 'Your cart'],
            'body'   => ['text' => $body],
            'footer' => ['text' => 'Free delivery'],
            'action' => [
                'button'   => 'Cart actions',
                'sections' => $sections,
            ],
        ]);
        if (empty($sent['success'])) {
            $waCloud->send_text($peer, $body);
        }
    }

    /**
     * Open the Meta catalog linked to this WhatsApp number.
     * Customers browse photos, variants, and cart inside WhatsApp.
     */
    private function _vendor_whatsapp_catalog_id(int $vendorId, string $phoneNumberId): string {
        $this->load->model('Sk_Vendor_meta_agent_model');
        $this->Sk_Vendor_meta_agent_model->ensure_schema();
        $row = null;
        if ($phoneNumberId !== '') {
            $row = $this->Sk_Vendor_meta_agent_model->get_by_phone($phoneNumberId);
        }
        if (!$row && $vendorId > 0 && $this->db->table_exists('vendor_meta_agents')) {
            $row = $this->db->where('vendor_id', $vendorId)
                ->where('catalog_id IS NOT NULL', null, false)
                ->where('catalog_id !=', '')
                ->order_by('id', 'DESC')
                ->get('vendor_meta_agents')
                ->row_array();
        }
        return trim((string)($row['catalog_id'] ?? ''));
    }

    private function _send_native_whatsapp_catalog(string $peer, int $vendorId, string $phoneNumberId, Whatsapp_cloud $waCloud, string $shopName): bool {
        if ($this->_vendor_whatsapp_catalog_id($vendorId, $phoneNumberId) === '') {
            return false;
        }
        $interactive = [
            'type' => 'catalog_message',
            'body' => [
                'text' => "All products from {$shopName} are in the catalog.\nTap View catalog to see photos and prices, then add items and send the order.",
            ],
            'action' => [
                'name' => 'catalog_message',
            ],
        ];
        $res = $waCloud->send_interactive($peer, $interactive);
        return !empty($res['success']);
    }

    /**
     * @param list<array<string,mixed>> $prods
     */
    private function _send_catalog_product_list(string $peer, int $vendorId, string $phoneNumberId, Whatsapp_cloud $waCloud, string $shopName, array $prods, string $header, string $bodyText = ''): bool {
        $catalogId = $this->_vendor_whatsapp_catalog_id($vendorId, $phoneNumberId);
        if ($catalogId === '' || $prods === []) {
            return false;
        }
        $items = [];
        foreach ($prods as $p) {
            $pid = (int)($p['id'] ?? 0);
            if ($pid < 1) {
                continue;
            }
            $items[] = ['product_retailer_id' => 'product_' . $pid];
            if (count($items) >= 30) {
                break;
            }
        }
        if ($items === []) {
            return false;
        }
        $title = function_exists('mb_substr') ? mb_substr($header !== '' ? $header : $shopName, 0, 60) : substr($header !== '' ? $header : $shopName, 0, 60);
        $interactive = [
            'type'   => 'product_list',
            'header' => ['type' => 'text', 'text' => $title !== '' ? $title : 'All products'],
            'body'   => [
                'text' => $bodyText !== '' ? $bodyText : 'Pick a product. Add it to your cart and send the order.',
            ],
            'action' => [
                'catalog_id' => $catalogId,
                'sections'   => [[
                    'title'         => $header === 'All products' ? 'All products' : 'Products',
                    'product_items' => $items,
                ]],
            ],
        ];
        $res = $waCloud->send_interactive($peer, $interactive);
        return !empty($res['success']);
    }

    /**
     * @return array{product_id:int,variant_id:int}
     */
    private function _parse_catalog_retailer_id(string $retailerId, int $vendorId): array {
        $retailerId = trim($retailerId);
        if (preg_match('/^product_(\d+)_v_(\d+)$/', $retailerId, $m)) {
            return ['product_id' => (int)$m[1], 'variant_id' => (int)$m[2]];
        }
        if (preg_match('/^product_(\d+)$/', $retailerId, $m)) {
            return ['product_id' => (int)$m[1], 'variant_id' => 0];
        }
        if ($retailerId !== '' && $vendorId > 0) {
            $prod = $this->db->where('vendor_id', $vendorId)->where('sku', $retailerId)->get('products')->row_array();
            if ($prod) {
                return ['product_id' => (int)$prod['id'], 'variant_id' => 0];
            }
            $var = $this->db->where('sku', $retailerId)->get('product_variants')->row_array();
            if ($var && !empty($var['product_id'])) {
                return ['product_id' => (int)$var['product_id'], 'variant_id' => (int)($var['id'] ?? 0)];
            }
        }
        return ['product_id' => 0, 'variant_id' => 0];
    }

    /**
     * WhatsApp catalog "Send cart" arrives as type=order with product_items.
     */
    private function _handle_whatsapp_catalog_order(array $raw, array $m, string $peer, int $vendorId, Whatsapp_cloud $waCloud, string $shopName, array $conv, string $phoneNumberId = ''): void {
        $order = is_array($raw['order'] ?? null) ? $raw['order'] : [];
        if ($order === [] && is_array($m['order'] ?? null)) {
            $order = $m['order'];
        }
        $items = is_array($order['product_items'] ?? null) ? $order['product_items'] : [];
        $sessionId = 'wa_' . $peer;
        $now = date('Y-m-d H:i:s');
        $added = 0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $parsed = $this->_parse_catalog_retailer_id((string)($item['product_retailer_id'] ?? ''), $vendorId);
            if ($parsed['product_id'] < 1) {
                continue;
            }
            $prod = $this->db->where('id', $parsed['product_id'])->where('vendor_id', $vendorId)->get('products')->row_array();
            if (!$prod) {
                continue;
            }
            $qty = max(1, (int)($item['quantity'] ?? 1));
            $variantId = (int)$parsed['variant_id'];
            $this->db->where('session_id', $sessionId)
                     ->where('vendor_id', $vendorId)
                     ->where('product_id', $parsed['product_id']);
            if ($variantId > 0) {
                $this->db->where('variant_id', $variantId);
            } else {
                $this->db->where('(variant_id IS NULL OR variant_id = 0)', null, false);
            }
            $existing = $this->db->get('cart')->row_array();
            if ($existing) {
                $this->db->where('id', (int)$existing['id'])->update('cart', [
                    'quantity'   => (int)$existing['quantity'] + $qty,
                    'updated_at' => $now,
                ]);
            } else {
                $this->db->insert('cart', [
                    'vendor_id'  => $vendorId,
                    'session_id' => $sessionId,
                    'product_id' => $parsed['product_id'],
                    'variant_id' => $variantId > 0 ? $variantId : null,
                    'quantity'   => $qty,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $added++;
        }
        if ($added < 1) {
            $waCloud->send_text($peer, "We received the cart, but those catalog items are not linked to {$shopName} yet. Open the catalog again after products are synced.");
            return;
        }
        $this->_send_cart_view($peer, $vendorId, $waCloud, $shopName);
    }

    /**
     * Open the native WhatsApp catalog. Fall back to a text list if no catalog is linked.
     */
    private function _send_saree_catalog(string $peer, int $vendorId, Whatsapp_cloud $waCloud, string $shopName, string $phoneNumberId = ''): void {
        if ($this->_send_native_whatsapp_catalog($peer, $vendorId, $phoneNumberId, $waCloud, $shopName)) {
            return;
        }
        $prods = $this->db->where('vendor_id', $vendorId)
                          ->where('status', 'active')
                          ->order_by('id', 'ASC')
                          ->limit(30)
                          ->get('products')
                          ->result_array();
        if ($prods === []) {
            $waCloud->send_text($peer, "No products are available right now.");
            return;
        }
        if ($this->_send_catalog_product_list(
            $peer,
            $vendorId,
            $phoneNumberId,
            $waCloud,
            $shopName,
            $prods,
            'All products',
            "All products from {$shopName}. Tap one to see the price and add it to your cart."
        )) {
            return;
        }

        $prodRows = [];
        foreach (array_slice($prods, 0, 10) as $p) {
            $pName = trim((string)$p['name']);
            $salePrice = isset($p['sale_price']) && (float)$p['sale_price'] > 0 ? (float)$p['sale_price'] : (float)$p['price'];
            $priceStr = function_exists('sk_mcp_tool_inr') ? sk_mcp_tool_inr($salePrice) : "₹{$salePrice}";
            $prodRows[] = [
                'id'          => 'prod_' . $p['id'],
                'title'       => function_exists('mb_substr') ? mb_substr($pName, 0, 24) : substr($pName, 0, 24),
                'description' => function_exists('mb_substr') ? mb_substr($priceStr . ' · In stock', 0, 72) : substr($priceStr . ' · In stock', 0, 72),
            ];
        }
        $waCloud->send_interactive($peer, [
            'type'   => 'list',
            'header' => ['type' => 'text', 'text' => 'All products'],
            'body'   => ['text' => "All products from {$shopName}. Tap one to see details and add it to your cart."],
            'footer' => ['text' => 'Free delivery'],
            'action' => [
                'button'   => 'All products',
                'sections' => [[
                    'title' => 'All products',
                    'rows'  => $prodRows,
                ]],
            ],
        ]);
    }

    /**
     * Send variant selection drawer for products with multiple color/size variants.
     */
    private function _send_variant_selector(array $prod, array $variants, string $peer, Whatsapp_cloud $waCloud, string $shopName): void {
        $prodId = (int)$prod['id'];
        $pName = (string)$prod['name'];
        $origPrice = (float)($prod['price'] ?? 0);
        $salePrice = isset($prod['sale_price']) && (float)$prod['sale_price'] > 0 ? (float)$prod['sale_price'] : $origPrice;
        $priceStr = function_exists('sk_mcp_tool_inr') ? sk_mcp_tool_inr($salePrice) : "₹{$salePrice}";
        $defaultVarId = !empty($variants) ? (int)$variants[0]['id'] : 0;

        $secRows = [];
        foreach (array_slice($variants, 0, 8) as $var) {
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
                'text' => function_exists('mb_substr') ? mb_substr("{$pName} Variants", 0, 60) : "Choose Variant",
            ],
            'body'   => [
                'text' => "This saree is available in multiple styles & variants ✨\nPlease choose your preferred option below:",
            ],
            'footer' => [
                'text' => 'Tap Choose Variant to proceed',
            ],
            'action' => [
                'button'   => 'Choose Variant',
                'sections' => [
                    [
                        'title' => 'Available Options',
                        'rows'  => $secRows,
                    ],
                ],
            ],
        ];

        $waCloud->send_interactive($peer, $interactive);
    }

    /**
     * WhatsApp Flow completion. Menu forms and the address form share nfm_reply.
     */
    private function _handle_flow_reply(string $peer, int $vendorId, Whatsapp_cloud $waCloud, string $shopName, array $conv, string $responseJson, string $phoneNumberId = ''): void {
        $payload = json_decode($responseJson, true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $step = (string)($payload['step'] ?? '');
        if ($step === 'search') {
            $this->_reply_catalog_search($peer, $vendorId, $waCloud, $shopName, $phoneNumberId, trim((string)($payload['query'] ?? '')));
            return;
        }
        if ($step === 'track') {
            $this->_handle_track_order($peer, $vendorId, $waCloud, $shopName, trim((string)($payload['order_number'] ?? '')));
            return;
        }
        if ($step === 'human') {
            $message = trim((string)($payload['message'] ?? ''));
            if ($message === '') {
                $waCloud->send_text($peer, "Write a message for the {$shopName} team and send the form again.");
                return;
            }
            $this->_forward_team_message($peer, $vendorId, $waCloud, $shopName, $conv, $message);
            return;
        }
        $this->_handle_address_flow_reply($peer, $vendorId, $waCloud, $shopName, $conv, $responseJson);
    }

    private function _reply_catalog_search(string $peer, int $vendorId, Whatsapp_cloud $waCloud, string $shopName, string $phoneNumberId, string $query): void {
        $query = trim($query);
        if ($query === '') {
            $waCloud->send_text($peer, "Type a product name so I can search the {$shopName} catalog.");
            return;
        }
        $items = $this->_search_catalog($query, $vendorId, 10);
        if ($items === []) {
            $waCloud->send_text($peer, "No catalog item matches \"{$query}\". Open the catalog and browse, or try another name.");
            return;
        }
        if ($this->_send_catalog_product_list($peer, $vendorId, $phoneNumberId, $waCloud, $shopName, $items, $query)) {
            return;
        }
        $lines = [];
        foreach (array_slice($items, 0, 8) as $item) {
            $name = trim((string)($item['name'] ?? 'Product'));
            if ($name !== '') {
                $lines[] = '• ' . $name;
            }
        }
        $waCloud->send_text($peer, $lines !== []
            ? "Matches for \"{$query}\":\n" . implode("\n", $lines) . "\n\nOpen the catalog to order."
            : "No catalog item matches \"{$query}\".");
    }

    private function _handle_track_order(string $peer, int $vendorId, Whatsapp_cloud $waCloud, string $shopName, string $orderNumber = ''): void {
        $digits = preg_replace('/\D+/', '', $peer);
        $last10 = strlen($digits) >= 10 ? substr($digits, -10) : $digits;
        $number = ltrim(trim($orderNumber), '#');

        $this->db->select('o.*')->from('orders o');
        if ($number !== '') {
            $this->db->group_start()
                ->where('o.order_number', $number)
                ->or_where('o.order_number', '#' . $number)
            ->group_end();
        } else {
            $this->db->group_start()
                ->like('o.shipping_phone', $last10)
                ->or_like('o.billing_phone', $last10)
            ->group_end();
        }
        $orders = $this->db->order_by('o.id', 'DESC')->limit(8)->get()->result_array();

        $matched = [];
        foreach ($orders as $o) {
            if (function_exists('sk_ai_order_in_shop') && !sk_ai_order_in_shop($o, $vendorId)) {
                continue;
            }
            $matched[] = $o;
        }

        if ($matched === [] && $number !== '') {
            $waCloud->send_text($peer, "No order {$number} was found at {$shopName}. Check the number, or send hi and choose Track order again.");
            return;
        }
        if ($matched === []) {
            $waCloud->send_text($peer, "No recent order is linked to this WhatsApp number at {$shopName}. Send hi and choose Track order to enter an order number.");
            return;
        }

        $chunks = [];
        foreach (array_slice($matched, 0, 3) as $latest) {
            $oNum = (string)($latest['order_number'] ?? $latest['id']);
            $oStatus = ucfirst((string)($latest['status'] ?? 'pending'));
            $pStatus = ucfirst((string)($latest['payment_status'] ?? 'pending'));
            $oTot = function_exists('sk_mcp_tool_inr') ? sk_mcp_tool_inr((float)($latest['total'] ?? 0)) : '₹' . ($latest['total'] ?? 0);
            $trackNo = (string)($latest['tracking_number'] ?? $latest['awb_number'] ?? '');
            $courier = (string)($latest['courier_name'] ?? $latest['shipping_provider'] ?? '');
            $msg = "*Order #{$oNum}*\n"
                . "Status: {$oStatus}\n"
                . "Payment: {$pStatus}\n"
                . "Total: {$oTot}";
            if ($trackNo !== '') {
                $msg .= "\nTracking: {$trackNo}" . ($courier !== '' ? " ({$courier})" : '');
            }
            $chunks[] = $msg;
        }
        $waCloud->send_text($peer, implode("\n\n", $chunks) . "\n\nSend hi to open the menu.");
    }

    private function _forward_team_message(string $peer, int $vendorId, Whatsapp_cloud $waCloud, string $shopName, array $conv, string $message, bool $first = true): void {
        $this->load->helper('sk_whatsapp');
        $this->db->where('id', (int)$conv['id'])->update('wa_cloud_conversations', [
            'agent_state' => json_encode(['step' => 'team_chat'], JSON_UNESCAPED_UNICODE),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
        $note = "{$shopName} customer {$peer} wrote:\n{$message}";
        $phones = function_exists('sk_whatsapp_owner_phones') ? sk_whatsapp_owner_phones($vendorId) : [];
        $sent = 0;
        foreach ($phones as $phone) {
            $res = $waCloud->send_text($phone, $note);
            if (!empty($res['success'])) {
                $sent++;
            }
        }
        if (function_exists('sk_whatsapp_notify_handoff')) {
            sk_whatsapp_notify_handoff($vendorId, $peer, $message, $shopName);
        }
        if ($first) {
            $waCloud->send_text($peer, $sent > 0
                ? "Your message was sent to the {$shopName} owner. Keep writing here and each message is forwarded. Send hi to open the menu."
                : "Your message is saved for the {$shopName} team. Keep writing here. Send hi to open the menu.");
            return;
        }
        $waCloud->send_text($peer, "Sent to the {$shopName} owner.");
    }

    /**
     * WhatsApp Flow completion. The endpoint already saved the customer and address.
     */
    private function _handle_address_flow_reply(string $peer, int $vendorId, Whatsapp_cloud $waCloud, string $shopName, array $conv, string $responseJson): void {
        $payload = json_decode($responseJson, true);
        $addressId = (int)(is_array($payload) ? ($payload['address_id'] ?? 0) : 0);
        $addr = $addressId > 0 ? $this->db->where('id', $addressId)->get('addresses')->row_array() : null;
        if (!$addr) {
            $addr = $this->_get_latest_customer_address($peer);
            $addressId = (int)($addr['id'] ?? 0);
        }
        if (!$addr || $addressId < 1) {
            $waCloud->send_text($peer, "We could not read the saved address. Tap Edit address and submit the form again.");
            return;
        }
        $this->db->where('id', (int)$conv['id'])->update('wa_cloud_conversations', [
            'agent_state' => json_encode(['address_id' => $addressId], JSON_UNESCAPED_UNICODE),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
        $name = (string)($addr['full_name'] ?? '');
        $phone = (string)($addr['phone'] ?? $peer);
        $line1 = (string)($addr['line1'] ?? '');
        $city = (string)($addr['city'] ?? '');
        $state = (string)($addr['state'] ?? '');
        $pin = (string)($addr['pincode'] ?? '');
        $text = "Address saved for {$shopName}.\n\n"
            . "Name: {$name}\n"
            . "Phone: {$phone}\n"
            . "Address: {$line1}\n"
            . "City / State: {$city}, {$state}\n"
            . "Pincode: {$pin}\n\n"
            . "Tap Proceed to pay.";
        $waCloud->send_interactive($peer, [
            'type'   => 'button',
            'body'   => ['text' => $text],
            'action' => [
                'buttons' => [
                    ['type' => 'reply', 'reply' => ['id' => 'addr_proceed_' . $addressId, 'title' => 'Proceed to pay']],
                    ['type' => 'reply', 'reply' => ['id' => 'addr_change', 'title' => 'Edit address']],
                    ['type' => 'reply', 'reply' => ['id' => 'cart_view', 'title' => 'View cart']],
                ],
            ],
        ]);
    }

    /**
     * Step 1 of Checkout: Verify or ask for delivery address before sale.
     */
    private function _initiate_checkout_address_flow(string $peer, int $vendorId, Whatsapp_cloud $waCloud, string $shopName, array $conv, string $phoneNumberId = ''): void {
        $sessionId = 'wa_' . $peer;
        $cartCount = $this->db->where('session_id', $sessionId)->where('vendor_id', $vendorId)->count_all_results('cart');
        if ($cartCount === 0) {
            $this->_send_cart_view($peer, $vendorId, $waCloud, $shopName);
            return;
        }

        $addr = $this->_get_latest_customer_address($peer);
        if ($addr && !empty($addr['line1'])) {
            $addrId = (int)($addr['id'] ?? 0);
            $name = !empty($addr['full_name']) ? $addr['full_name'] : (!empty($conv['name']) ? $conv['name'] : 'Customer');
            $phone = !empty($addr['phone']) ? $addr['phone'] : $peer;
            $line1 = (string)$addr['line1'];
            $line2 = !empty($addr['line2']) ? ", " . $addr['line2'] : '';
            $city = (string)($addr['city'] ?? '');
            $state = (string)($addr['state'] ?? '');
            $pin = (string)($addr['pincode'] ?? '');

            $body = "📦 *Confirm Delivery Address*\n\n"
                  . "Please confirm where your saree order should be delivered:\n\n"
                  . "👤 *Recipient:* {$name}\n"
                  . "📞 *Contact:* +{$phone}\n"
                  . "🏠 *Address:* {$line1}{$line2}\n"
                  . ($city !== '' ? "🏙️ *City / State:* {$city}, {$state}\n" : '')
                  . ($pin !== '' ? "📮 *Pincode:* {$pin}\n" : '')
                  . "🚚 *Delivery:* FREE Express Delivery across India (₹0.00)\n\n"
                  . "Is this address correct for dispatch?";

            $interactive = [
                'type'   => 'button',
                'header' => [
                    'type' => 'text',
                    'text' => '📍 Confirm Delivery Address',
                ],
                'body'   => [
                    'text' => $body,
                ],
                'footer' => [
                    'text' => '100% Free Express Delivery',
                ],
                'action' => [
                    'buttons' => [
                        [
                            'type'  => 'reply',
                            'reply' => [
                                'id'    => "addr_confirm_{$addrId}",
                                'title' => '✅ Deliver Here',
                            ],
                        ],
                        [
                            'type'  => 'reply',
                            'reply' => [
                                'id'    => 'addr_change',
                                'title' => '✏️ Edit Address',
                            ],
                        ],
                        [
                            'type'  => 'reply',
                            'reply' => [
                                'id'    => 'cart_view',
                                'title' => '🛒 View cart',
                            ],
                        ],
                    ],
                ],
            ];
            $waCloud->send_interactive($peer, $interactive);
            return;
        }

        // If no saved address found, prompt customer to enter address
        $this->_prompt_for_delivery_address($peer, $vendorId, $waCloud, $shopName, $conv, $phoneNumberId, 'create');
    }

    /**
     * Open the WhatsApp address Flow. Fall back to typed address if the Flow is not published yet.
     */
    private function _prompt_for_delivery_address(string $peer, int $vendorId, Whatsapp_cloud $waCloud, string $shopName, array $conv, string $phoneNumberId = '', string $mode = 'create'): void {
        $this->db->where('id', (int)$conv['id'])->update('wa_cloud_conversations', [
            'agent_state' => json_encode([
                'step'      => 'waiting_for_address',
                'vendor_id' => $vendorId,
            ], JSON_UNESCAPED_UNICODE),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->load->helper('sk_wa_address_flow');
        $addressId = 0;
        if ($mode === 'edit') {
            $existing = $this->_get_latest_customer_address($peer);
            $addressId = (int)($existing['id'] ?? 0);
        }
        if (sk_wa_address_flow_send($waCloud, $peer, $vendorId, $phoneNumberId, $shopName, $mode, $addressId)) {
            return;
        }

        $promptText = "📍 *Enter Delivery Address*\n\n"
                    . "Please reply with your shipping address in this easy format:\n\n"
                    . "*Name, House/Flat No, Street/Road, City, State, Pincode*\n\n"
                    . "_Example:_\n"
                    . "*Priya Sharma, 14/2 Anna Salai, Opposite Temple, Madurai, Tamil Nadu, 625001*\n\n"
                    . "💡 *Tip:* You can also tap the 📎 Attachment icon and share your *Location Pin* 📍\n"
                    . "🚚 _Free Delivery across all locations in India._";

        $interactive = [
            'type'   => 'button',
            'header' => [
                'type' => 'text',
                'text' => 'Delivery Address Required',
            ],
            'body'   => [
                'text' => $promptText,
            ],
            'footer' => [
                'text' => 'Reply below with your address',
            ],
            'action' => [
                'buttons' => [
                    [
                        'type'  => 'reply',
                        'reply' => [
                            'id'    => 'cart_view',
                            'title' => '🛒 View cart',
                        ],
                    ],
                    [
                        'type'  => 'reply',
                        'reply' => [
                            'id'    => 'talk_to_team',
                            'title' => '🙋 Help / Support',
                        ],
                    ],
                ],
            ],
        ];

        $res = $waCloud->send_interactive($peer, $interactive);
        if (empty($res['success'])) {
            $waCloud->send_text($peer, $promptText);
        }
    }

    /**
     * Handle incoming address text or location pin from customer.
     */
    private function _handle_inbound_address_entry(string $peer, int $vendorId, string $body, array $raw, Whatsapp_cloud $waCloud, string $shopName, array $conv): void {
        // Handle WhatsApp Location Pin if provided
        $isLocation = (!empty($raw['type']) && $raw['type'] === 'location') || !empty($raw['location']);
        $locationData = $raw['location'] ?? null;

        $parsed = null;
        if ($isLocation && is_array($locationData)) {
            $lat = (string)($locationData['latitude'] ?? '');
            $lng = (string)($locationData['longitude'] ?? '');
            $locName = trim((string)($locationData['name'] ?? ''));
            $locAddr = trim((string)($locationData['address'] ?? ''));
            $fullLine = $locName !== '' ? "{$locName}, {$locAddr}" : ($locAddr !== '' ? $locAddr : "Location Pin: {$lat}, {$lng}");

            $parsed = $this->_parse_address_text($fullLine, $peer, $conv['name'] ?? null);
        } else {
            // Validate minimum length
            if (mb_strlen(trim($body)) < 5) {
                $waCloud->send_text($peer, "📍 *Please provide a complete delivery address*\n\nInclude your Street / Area, City, and 6-digit Pincode so our courier can deliver smoothly! 📦\n\n_Example:_\n*Priya Sharma, 12 Lake View, Anna Salai, Madurai, Tamil Nadu, 625001*");
                return;
            }

            $parsed = $this->_parse_address_text($body, $peer, $conv['name'] ?? null);
        }

        // Insert or update in addresses table
        $user = $this->db->where('phone', $peer)->or_like('phone', substr($peer, -10))->get('users')->row_array();
        $now = date('Y-m-d H:i:s');
        if (!$user) {
            $this->db->insert('users', [
                'name'       => $parsed['full_name'],
                'phone'      => $peer,
                'status'     => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $userId = (int)$this->db->insert_id();
        } else {
            $userId = (int)$user['id'];
        }

        $this->db->insert('addresses', [
            'vendor_id'    => $vendorId,
            'user_id'      => $userId,
            'label'        => 'Home',
            'full_name'    => $parsed['full_name'],
            'phone'        => $parsed['phone'],
            'line1'        => $parsed['line1'],
            'line2'        => $parsed['line2'] ?? '',
            'city'         => $parsed['city'],
            'state'        => $parsed['state'],
            'pincode'      => $parsed['pincode'],
            'country'      => 'India',
            'is_default'   => 1,
            'address_type' => 'shipping',
            'created_at'   => $now,
        ]);
        $addrId = (int)$this->db->insert_id();

        // Clear waiting_for_address state
        $this->db->where('id', (int)$conv['id'])->update('wa_cloud_conversations', [
            'agent_state' => json_encode([
                'address_id' => $addrId,
            ], JSON_UNESCAPED_UNICODE),
            'updated_at'  => $now,
        ]);

        $addrText = "✅ *Delivery Address Saved Successfully!*\n\n"
                  . "📦 *Shipping To:*\n"
                  . "👤 *Name:* {$parsed['full_name']}\n"
                  . "📞 *Phone:* +{$parsed['phone']}\n"
                  . "🏠 *Address:* {$parsed['line1']}\n"
                  . "🏙️ *City / State:* {$parsed['city']}, {$parsed['state']}\n"
                  . "📮 *Pincode:* {$parsed['pincode']}\n"
                  . "🚚 *Delivery:* FREE Express Delivery (₹0.00)\n\n"
                  . "👉 Tap *Proceed to Pay* below to choose payment mode:";

        $interactive = [
            'type'   => 'button',
            'header' => [
                'type' => 'text',
                'text' => 'Address Saved ✅',
            ],
            'body'   => [
                'text' => $addrText,
            ],
            'footer' => [
                'text' => '100% Free Express Delivery',
            ],
            'action' => [
                'buttons' => [
                    [
                        'type'  => 'reply',
                        'reply' => [
                            'id'    => "addr_proceed_{$addrId}",
                            'title' => '💳 Proceed to Pay',
                        ],
                    ],
                    [
                        'type'  => 'reply',
                        'reply' => [
                            'id'    => 'addr_change',
                            'title' => '✏️ Edit Address',
                        ],
                    ],
                    [
                        'type'  => 'reply',
                        'reply' => [
                            'id'    => 'cart_view',
                            'title' => '🛒 View cart',
                        ],
                    ],
                ],
            ],
        ];

        $res = $waCloud->send_interactive($peer, $interactive);
        if (empty($res['success'])) {
            $waCloud->send_text($peer, $addrText);
        }
    }

    /**
     * Parse structured Indian address fields from casual customer text.
     */
    private function _parse_address_text(string $text, string $peer, ?string $customerName = null): array {
        $raw = trim($text);

        $name = !empty($customerName) && strcasecmp($customerName, 'WhatsApp Customer') !== 0 ? $customerName : '';
        $phone = $peer;
        $pincode = '';
        $city = '';
        $state = '';
        $line1 = '';

        // 1. Extract 6-digit Indian pincode
        if (preg_match('/\b([1-9][0-9]{5})\b/', $raw, $pm)) {
            $pincode = $pm[1];
        }

        // 2. Extract phone if alternate phone provided, else use $peer
        if (preg_match('/(?:phone|mobile|mob|contact|ph|tel)?\s*[:=-]?\s*(?:\+?91[\s-]*)?([6-9]\d{9})\b/iu', $raw, $phm)) {
            $phone = $phm[1];
        }

        // 3. Extract explicit labeled fields if customer used key-value style
        if (preg_match('/(?:name|recipient)\s*[:=-]\s*([^\r\n,]+)/iu', $raw, $nm)) {
            $name = trim($nm[1]);
        }
        if (preg_match('/(?:city|town)\s*[:=-]\s*([^\r\n,]+)/iu', $raw, $cm)) {
            $city = trim($cm[1]);
        }
        if (preg_match('/(?:state)\s*[:=-]\s*([^\r\n,]+)/iu', $raw, $sm)) {
            $state = trim($sm[1]);
        }
        if (preg_match('/(?:address|addr|street|house|flat|road)\s*[:=-]\s*([^\r\n]+)/iu', $raw, $am)) {
            $line1 = trim($am[1]);
        }

        // 4. Clean tokens for positional extraction if fields remain missing
        $clean = preg_replace('/(?:phone|mobile|mob|contact|ph|tel)\s*[:=-]?\s*(?:\+?91[\s-]*)?[6-9]\d{9}\b/iu', '', $raw);
        if ($pincode !== '') {
            $clean = str_replace($pincode, '', $clean);
        }
        // Remove label prefixes
        $clean = preg_replace('/\b(?:name|recipient|address|addr|street|city|state|pin|pincode)\s*[:=-]/iu', '', $clean);
        $clean = trim(preg_replace('/[,;\-–—\s]+$/u', '', $clean));

        // Split by newlines or commas
        $lines = preg_split('/[\r\n,]+/', $clean);
        $cleanLines = [];
        foreach ($lines as $l) {
            $trimmed = trim(preg_replace('/^(?:name|address|addr|street|city|state|pin|pincode|phone|mobile|contact)\s*[:=-]\s*/iu', '', trim($l)));
            if ($trimmed !== '' && !preg_match('/^(?:phone|ph|mob|mobile|contact|tel|pin|pincode|name|address)\s*[:=-]?$/iu', $trimmed)) {
                $cleanLines[] = $trimmed;
            }
        }

        $knownStates = [
            'tamil nadu', 'tamilnadu', 'kerala', 'karnataka', 'andhra pradesh', 'telangana',
            'maharashtra', 'delhi', 'gujarat', 'rajasthan', 'uttar pradesh', 'west bengal',
            'madhya pradesh', 'bihar', 'punjab', 'haryana', 'odisha', 'assam'
        ];

        $addressParts = [];
        foreach ($cleanLines as $line) {
            $lower = strtolower($line);
            $matchedState = false;
            foreach ($knownStates as $ks) {
                if ($lower === $ks || strpos($lower, $ks) !== false) {
                    if ($state === '') {
                        $state = ucwords($ks);
                    }
                    $matchedState = true;
                    break;
                }
            }
            if (!$matchedState) {
                $addressParts[] = $line;
            }
        }

        // Positional name extraction if not already set
        if ($name === '' && !empty($addressParts) && !preg_match('/\d/', $addressParts[0]) && count(explode(' ', $addressParts[0])) <= 4) {
            $name = array_shift($addressParts);
        }
        if ($name === '') {
            $name = !empty($customerName) ? $customerName : 'Valued Customer';
        }

        // Positional city extraction if not already set
        if ($city === '' && count($addressParts) > 1) {
            $lastPart = end($addressParts);
            if (!preg_match('/\d/', $lastPart) && strlen($lastPart) < 30) {
                $city = array_pop($addressParts);
            }
        }

        if ($line1 === '') {
            $line1 = implode(', ', $addressParts);
        }
        if ($line1 === '') {
            $line1 = $clean !== '' ? $clean : 'Customer Address';
        }

        if ($city === '') {
            $city = 'Nagercoil';
        }
        if ($state === '') {
            $state = 'Tamil Nadu';
        }
        if ($pincode === '') {
            $pincode = '629501';
        }

        return [
            'full_name' => $name,
            'phone'     => $phone,
            'line1'     => $line1,
            'line2'     => '',
            'city'      => $city,
            'state'     => $state,
            'pincode'   => $pincode,
            'country'   => 'India',
        ];
    }

    /**
     * Find latest shipping address for this customer from addresses or orders table.
     */
    private function _get_latest_customer_address(string $peer): ?array {
        $digits = preg_replace('/\D+/', '', $peer);
        $last10 = strlen($digits) >= 10 ? substr($digits, -10) : $digits;

        // 1. Check addresses table
        $this->db->select('*')
                 ->from('addresses')
                 ->group_start()
                     ->where('phone', $peer)
                     ->or_like('phone', $last10)
                 ->group_end()
                 ->order_by('is_default', 'DESC')
                 ->order_by('id', 'DESC')
                 ->limit(1);
        $addr = $this->db->get()->row_array();
        if ($addr && !empty($addr['line1'])) {
            return $addr;
        }

        // 2. Check recent orders
        $this->db->select('shipping_name as full_name, shipping_phone as phone, shipping_line1 as line1, shipping_line2 as line2, shipping_city as city, shipping_state as state, shipping_pincode as pincode, shipping_country as country')
                 ->from('orders')
                 ->group_start()
                     ->where('shipping_phone', $peer)
                     ->or_like('shipping_phone', $last10)
                 ->group_end()
                 ->where('shipping_line1 IS NOT NULL', null, false)
                 ->where("shipping_line1 != ''", null, false)
                 ->where("shipping_line1 NOT LIKE '%via WhatsApp%'", null, false)
                 ->order_by('id', 'DESC')
                 ->limit(1);
        $orderAddr = $this->db->get()->row_array();
        if ($orderAddr && !empty($orderAddr['line1'])) {
            return $orderAddr;
        }

        return null;
    }

    /**
     * Send Checkout payment mode selection (Pay Online vs Cash on Delivery) with confirmed delivery address context.
     */
    private function _send_checkout_payment_mode_options(string $peer, int $vendorId, Whatsapp_cloud $waCloud, string $shopName, int $addrId = 0): void {
        $sessionId = 'wa_' . $peer;
        $cartItems = $this->db->select('c.*, p.name as product_name, p.price as prod_price, p.sale_price as prod_sale, pv.label as variant_label, pv.price as var_price, pv.sale_price as var_sale')
                              ->from('cart c')
                              ->join('products p', 'p.id = c.product_id')
                              ->join('product_variants pv', 'pv.id = c.variant_id', 'left')
                              ->where('c.session_id', $sessionId)
                              ->where('c.vendor_id', $vendorId)
                              ->get()->result_array();

        if (empty($cartItems)) {
            $waCloud->send_text($peer, "Your cart is empty 😊\nReply with *1* or send *saree* to browse our collections before placing an order!");
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

        $addr = null;
        if ($addrId > 0) {
            $addr = $this->db->where('id', $addrId)->get('addresses')->row_array();
        }
        if (!$addr) {
            $addr = $this->_get_latest_customer_address($peer);
        }

        $addrIdParam = $addr ? (int)$addr['id'] : 0;
        $addrSummary = '';
        if ($addr) {
            $name = !empty($addr['full_name']) ? $addr['full_name'] : 'Customer';
            $city = !empty($addr['city']) ? $addr['city'] : '';
            $pin = !empty($addr['pincode']) ? " - " . $addr['pincode'] : '';
            $addrSummary = "📍 *Delivering To:* {$name}, {$city}{$pin}\n";
        }

        $interactive = [
            'type'   => 'button',
            'header' => [
                'type' => 'text',
                'text' => function_exists('mb_substr') ? mb_substr("Checkout ({$cartTot})", 0, 60) : "Checkout",
            ],
            'body'   => [
                'text' => "🛍️ *Ready to Place Your Order!*\n\n"
                        . "• *Items in cart:* {$totalItems} piece(s)\n"
                        . "• *Subtotal:* {$cartTot}\n"
                        . "• *Shipping:* FREE Delivery across India 🚚\n"
                        . "• *Total Payable:* *{$cartTot}*\n"
                        . ($addrSummary !== '' ? "• {$addrSummary}" : '') . "\n"
                        . "📦 *Cash on Delivery (COD) is AVAILABLE!*\n\n"
                        . "Please choose how you would like to pay:",
            ],
            'footer' => [
                'text' => '100% Secure Checkout',
            ],
            'action' => [
                'buttons' => [
                    [
                        'type'  => 'reply',
                        'reply' => [
                            'id'    => "pay_online_{$addrIdParam}",
                            'title' => '💳 Pay Online',
                        ],
                    ],
                    [
                        'type'  => 'reply',
                        'reply' => [
                            'id'    => "pay_cod_{$addrIdParam}",
                            'title' => '💵 Pay COD',
                        ],
                    ],
                    [
                        'type'  => 'reply',
                        'reply' => [
                            'id'    => 'addr_change',
                            'title' => '📍 Change Address',
                        ],
                    ],
                ],
            ],
        ];

        $waCloud->send_interactive($peer, $interactive);
    }

    /**
     * Create database order in orders and order_items tables with confirmed shipping address.
     */
    private function _create_whatsapp_order(string $peer, int $vendorId, string $method, array $cartItems, float $totalVal, array $conv, int $addrId = 0): ?array {
        $user = $this->db->where('phone', $peer)->or_like('phone', substr($peer, -10))->get('users')->row_array();
        $now = date('Y-m-d H:i:s');
        if (!$user) {
            $this->db->insert('users', [
                'name'       => !empty($conv['customer_name']) ? $conv['customer_name'] : 'WhatsApp Customer',
                'phone'      => $peer,
                'status'     => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $userId = (int)$this->db->insert_id();
        } else {
            $userId = (int)$user['id'];
        }

        // Fetch shipping address
        $addr = null;
        if ($addrId > 0) {
            $addr = $this->db->where('id', $addrId)->get('addresses')->row_array();
        }
        if (!$addr) {
            $addr = $this->_get_latest_customer_address($peer);
        }

        $shipName = !empty($addr['full_name']) ? $addr['full_name'] : (!empty($conv['customer_name']) ? $conv['customer_name'] : 'WhatsApp Customer');
        $shipPhone = !empty($addr['phone']) ? $addr['phone'] : $peer;
        $shipLine1 = !empty($addr['line1']) ? $addr['line1'] : 'Address provided on WhatsApp';
        $shipLine2 = !empty($addr['line2']) ? $addr['line2'] : '';
        $shipCity = !empty($addr['city']) ? $addr['city'] : 'Nagercoil';
        $shipState = !empty($addr['state']) ? $addr['state'] : 'Tamil Nadu';
        $shipPin = !empty($addr['pincode']) ? $addr['pincode'] : '629501';
        $shipCountry = !empty($addr['country']) ? $addr['country'] : 'India';

        $isCod = ($method === 'cod');
        $orderData = [
            'user_id'          => $userId,
            'vendor_id'        => $vendorId,
            'subtotal'         => $totalVal,
            'shipping'         => 0.00,
            'shipping_detail'  => 'Free Delivery across India',
            'tax'              => 0.00,
            'discount'         => 0.00,
            'wallet_discount'  => 0.00,
            'promo_code'       => null,
            'total'            => $totalVal,
            'wallet_amount'    => 0.00,
            'order_source'     => 'whatsapp',
            'payment_method'   => $method,
            'payment_status'   => 'pending',
            'status'           => $isCod ? 'confirmed' : 'payment_attempt',
            'status_updated_at'=> $now,
            'confirmed_at'     => $isCod ? $now : null,
            'notes'            => 'Order placed via WhatsApp chat (' . ($isCod ? 'Cash on Delivery' : 'Razorpay Online') . ')',
            'shipping_name'    => $shipName,
            'shipping_phone'   => $shipPhone,
            'shipping_line1'   => $shipLine1,
            'shipping_line2'   => $shipLine2,
            'shipping_city'    => $shipCity,
            'shipping_state'   => $shipState,
            'shipping_pincode' => $shipPin,
            'shipping_country' => $shipCountry,
        ];

        $orderItems = [];
        foreach ($cartItems as $ci) {
            $q = (int)$ci['quantity'];
            $price = $ci['var_price'] !== null && (float)$ci['var_price'] > 0
                ? (float)($ci['var_sale'] && (float)$ci['var_sale'] > 0 ? $ci['var_sale'] : $ci['var_price'])
                : (float)($ci['prod_sale'] && (float)$ci['prod_sale'] > 0 ? $ci['prod_sale'] : $ci['prod_price']);
            $orderItems[] = [
                'vendor_id'     => $vendorId,
                'product_id'    => (int)$ci['product_id'],
                'variant_id'    => !empty($ci['variant_id']) ? (int)$ci['variant_id'] : null,
                'product_name'  => (string)$ci['product_name'],
                'product_sku'   => (string)($ci['sku'] ?? ('SKU-' . $ci['product_id'])),
                'variant_label' => (string)($ci['variant_label'] ?? ''),
                'thumbnail'     => (string)($ci['thumbnail'] ?? ''),
                'price'         => $price,
                'quantity'      => $q,
                'subtotal'      => round($price * $q, 2),
            ];
        }

        $orderId = $this->Sk_Order_model->create($orderData, $orderItems, true);
        if ($orderId > 0) {
            return $this->Sk_Order_model->get_by_id($orderId);
        }
        return null;
    }

    /**
     * Handle Cash on Delivery (COD) order placement with shipping address.
     */
    private function _place_order_cod(string $peer, int $vendorId, Whatsapp_cloud $waCloud, string $shopName, array $conv, array $settings, int $addrId = 0): void {
        $sessionId = 'wa_' . $peer;
        $cartItems = $this->db->select('c.*, p.name as product_name, p.price as prod_price, p.sale_price as prod_sale, pv.label as variant_label, pv.price as var_price, pv.sale_price as var_sale')
                              ->from('cart c')
                              ->join('products p', 'p.id = c.product_id')
                              ->join('product_variants pv', 'pv.id = c.variant_id', 'left')
                              ->where('c.session_id', $sessionId)
                              ->where('c.vendor_id', $vendorId)
                              ->get()->result_array();

        if (empty($cartItems)) {
            $waCloud->send_text($peer, "Your cart is empty 😊\nReply with *1* to browse our sarees!");
            return;
        }

        $totalVal = 0.0;
        foreach ($cartItems as $ci) {
            $q = (int)$ci['quantity'];
            $price = $ci['var_price'] !== null && (float)$ci['var_price'] > 0
                ? (float)($ci['var_sale'] && (float)$ci['var_sale'] > 0 ? $ci['var_sale'] : $ci['var_price'])
                : (float)($ci['prod_sale'] && (float)$ci['prod_sale'] > 0 ? $ci['prod_sale'] : $ci['prod_price']);
            $totalVal += ($price * $q);
        }

        $order = $this->_create_whatsapp_order($peer, $vendorId, 'cod', $cartItems, $totalVal, $conv, $addrId);
        if (!$order) {
            $waCloud->send_text($peer, "We couldn't process your order right now. Please try again or tap *Talk to our team* for assistance.");
            return;
        }

        // Empty cart
        $this->db->where('session_id', $sessionId)->delete('cart');

        // Send Invoice & Confirmation
        $this->_send_invoice_message($order, $cartItems, 'cod', $peer, $waCloud, $shopName, false);
    }

    /**
     * Handle Pay Online order placement (generate Razorpay payment link) with shipping address.
     */
    private function _place_order_online(string $peer, int $vendorId, Whatsapp_cloud $waCloud, string $shopName, array $conv, array $settings, int $addrId = 0): void {
        $sessionId = 'wa_' . $peer;
        $cartItems = $this->db->select('c.*, p.name as product_name, p.price as prod_price, p.sale_price as prod_sale, pv.label as variant_label, pv.price as var_price, pv.sale_price as var_sale')
                              ->from('cart c')
                              ->join('products p', 'p.id = c.product_id')
                              ->join('product_variants pv', 'pv.id = c.variant_id', 'left')
                              ->where('c.session_id', $sessionId)
                              ->where('c.vendor_id', $vendorId)
                              ->get()->result_array();

        if (empty($cartItems)) {
            $waCloud->send_text($peer, "Your cart is empty 😊\nReply with *1* to browse our sarees!");
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

        $order = $this->_create_whatsapp_order($peer, $vendorId, 'razorpay', $cartItems, $totalVal, $conv, $addrId);
        if (!$order) {
            $waCloud->send_text($peer, "We couldn't initiate your online order. Please try again or select Cash on Delivery.");
            return;
        }

        $orderId = (int)$order['id'];
        $orderNumber = (string)($order['order_number'] ?? $orderId);

        // Generate Razorpay Payment Link
        $rzpRes = sk_razorpay_create_payment_link(
            $totalVal,
            "{$shopName} Saree Order #{$orderNumber}",
            "ORD_{$orderId}_" . time(),
            [
                'order_id'  => (string)$orderId,
                'peer'      => $peer,
                'vendor_id' => (string)$vendorId,
            ],
            $settings
        );

        if (!empty($rzpRes['success']) && !empty($rzpRes['payment_link'])) {
            $paymentLink = $rzpRes['payment_link'];
            $this->db->where('id', $orderId)->update('orders', [
                'notes' => 'Razorpay Link: ' . $paymentLink . ' (ID: ' . ($rzpRes['id'] ?? '') . ')',
            ]);

            $interactive = [
                'type'   => 'button',
                'header' => [
                    'type' => 'text',
                    'text' => function_exists('mb_substr') ? mb_substr("Payment Link #{$orderNumber}", 0, 60) : "Payment Link",
                ],
                'body'   => [
                    'text' => "🛍️ *Order Reserved: #{$orderNumber}*\n\n"
                            . "Please tap the secure link below to pay with UPI, GPay, PhonePe, Paytm, or Card:\n\n"
                            . "👉 *{$paymentLink}*\n\n"
                            . "• *Amount Payable:* *{$cartTot}*\n"
                            . "• *Items in Order:* {$totalItems} piece(s)\n"
                            . "• *Link Valid For:* 30 minutes ⏳\n\n"
                            . "💡 *After completing your payment, tap '✅ I Have Paid' below to receive your Tax Invoice and receipt immediately!*",
                ],
                'footer' => [
                    'text' => '100% Secure Razorpay Payment',
                ],
                'action' => [
                    'buttons' => [
                        [
                            'type'  => 'reply',
                            'reply' => [
                                'id'    => "paid_{$orderId}",
                                'title' => '✅ I Have Paid',
                            ],
                        ],
                        [
                            'type'  => 'reply',
                            'reply' => [
                                'id'    => "switch_cod_{$orderId}",
                                'title' => '💵 Switch to COD',
                            ],
                        ],
                        [
                            'type'  => 'reply',
                            'reply' => [
                                'id'    => 'cart_view',
                                'title' => 'View cart',
                            ],
                        ],
                    ],
                ],
            ];

            $waCloud->send_interactive($peer, $interactive);
        } else {
            // Fallback to web checkout or COD
            $checkoutUrl = "https://talkaipilot.com/checkout?session=" . urlencode($sessionId);
            $interactive = [
                'type'   => 'button',
                'header' => [
                    'type' => 'text',
                    'text' => 'Complete Your Order',
                ],
                'body'   => [
                    'text' => "🛍️ *Order #{$orderNumber}*\n\n"
                            . "Pay online via web checkout below, or switch to Cash on Delivery:\n\n"
                            . "👉 {$checkoutUrl}\n\n"
                            . "• *Total Amount:* *{$cartTot}*",
                ],
                'footer' => [
                    'text' => 'Free delivery included',
                ],
                'action' => [
                    'buttons' => [
                        [
                            'type'  => 'reply',
                            'reply' => [
                                'id'    => "switch_cod_{$orderId}",
                                'title' => '💵 Switch to COD',
                            ],
                        ],
                        [
                            'type'  => 'reply',
                            'reply' => [
                                'id'    => 'main_menu',
                                'title' => '🏠 Main Menu',
                            ],
                        ],
                    ],
                ],
            ];
        }
    }

    /**
     * Confirm online payment received and send detailed Tax Invoice & Receipt.
     */
    private function _confirm_online_payment(int $orderId, string $peer, int $vendorId, Whatsapp_cloud $waCloud, string $shopName, array $conv, array $settings): void {
        $order = $this->Sk_Order_model->get_by_id($orderId);
        if (!$order) {
            // Find latest order for this phone
            $order = $this->db->where('shipping_phone', $peer)
                              ->order_by('id', 'DESC')
                              ->limit(1)
                              ->get('orders')
                              ->row_array();
            if ($order) {
                $orderId = (int)$order['id'];
                $order = $this->Sk_Order_model->get_by_id($orderId);
            }
        }

        if (!$order) {
            $waCloud->send_text($peer, "We couldn't locate your recent order record. Please reply with your Order ID or contact support.");
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->Sk_Order_model->update_status($orderId, 'confirmed');
        $this->Sk_Order_model->update_payment_status($orderId, 'paid');
        $this->db->where('id', $orderId)->update('orders', [
            'confirmed_at' => $now,
            'updated_at'   => $now,
        ]);

        // Insert into payments table
        $totalVal = (float)$order['total'];
        $currency = function_exists('sk_currency_code') ? sk_currency_code($settings) : 'INR';
        $this->Sk_Order_model->save_payment([
            'order_id'          => $orderId,
            'razorpay_order_id' => 'plink_wa_' . $orderId,
            'amount'            => $totalVal,
            'currency'          => $currency,
            'status'            => 'captured',
        ]);

        $this->db->where('session_id', 'wa_' . $peer)->where('vendor_id', $vendorId)->delete('cart');

        $items = $this->Sk_Order_model->get_items($orderId);
        $this->_send_invoice_message($order, $items, 'razorpay', $peer, $waCloud, $shopName, true);
    }

    /**
     * Switch an existing online pending order to Cash on Delivery.
     */
    private function _switch_order_to_cod(int $orderId, string $peer, int $vendorId, Whatsapp_cloud $waCloud, string $shopName, array $conv): void {
        $order = $this->Sk_Order_model->get_by_id($orderId);
        if (!$order) {
            $waCloud->send_text($peer, "Order record not found. Reply with *1* to browse sarees!");
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->where('id', $orderId)->update('orders', [
            'payment_method'    => 'cod',
            'status'            => 'confirmed',
            'payment_status'    => 'pending',
            'status_updated_at' => $now,
            'confirmed_at'      => $now,
            'updated_at'        => $now,
            'notes'             => 'Customer switched payment to Cash on Delivery (COD)',
        ]);

        $this->db->where('session_id', 'wa_' . $peer)->where('vendor_id', $vendorId)->delete('cart');
        $order = $this->Sk_Order_model->get_by_id($orderId);
        $items = $this->Sk_Order_model->get_items($orderId);
        $this->_send_invoice_message($order, $items, 'cod', $peer, $waCloud, $shopName, false);
    }

    /**
     * Send formatted Tax Invoice & Order Confirmation message on WhatsApp with action buttons.
     */
    private function _send_invoice_message(array $order, array $items, string $paymentMode, string $peer, Whatsapp_cloud $waCloud, string $shopName, bool $isPaid): void {
        $orderNo = (string)($order['order_number'] ?? $order['id']);
        $totalVal = (float)($order['total'] ?? 0);
        $cartTot = function_exists('sk_mcp_tool_inr') ? sk_mcp_tool_inr($totalVal) : "₹{$totalVal}";
        $custName = !empty($order['shipping_name']) ? $order['shipping_name'] : 'Valued Customer';
        $orderDate = date('d M Y, h:i A');

        $lines = [];
        foreach ($items as $idx => $it) {
            $pName = (string)($it['product_name'] ?? $it['name'] ?? 'Saree');
            $vLabel = !empty($it['variant_label']) ? " ({$it['variant_label']})" : '';
            $q = (int)($it['quantity'] ?? 1);
            $price = (float)($it['price'] ?? 0);
            $sub = $price * $q;
            $subStr = function_exists('sk_mcp_tool_inr') ? sk_mcp_tool_inr($sub) : "₹{$sub}";
            $lines[] = ($idx + 1) . ". *{$pName}*{$vLabel}\n   Qty: {$q} · Amount: *{$subStr}*";
        }
        $itemsBlock = implode("\n", $lines);

        $isCod = ($paymentMode === 'cod');
        $payTitle = $isCod ? "Cash on Delivery (COD)" : "Razorpay Online (UPI/Card)";
        $payStatusText = $isPaid ? "PAID & VERIFIED ✅" : ($isCod ? "Pay {$cartTot} on Delivery 📦" : "Payment Pending ⏳");

        $destLine = '';
        if (!empty($order['shipping_line1'])) {
            $destLine = "🚚 *DELIVERY ADDRESS:*\n"
                      . "👤 *Recipient:* " . ($order['shipping_name'] ?? $custName) . "\n"
                      . "📞 *Contact:* +" . ($order['shipping_phone'] ?? $peer) . "\n"
                      . "🏠 *Address:* " . $order['shipping_line1'] . (!empty($order['shipping_line2']) ? ", " . $order['shipping_line2'] : '') . "\n"
                      . "🏙️ *City / State:* " . ($order['shipping_city'] ?? '') . ", " . ($order['shipping_state'] ?? '') . " - " . ($order['shipping_pincode'] ?? '') . "\n"
                      . "🇮🇳 *Country:* " . ($order['shipping_country'] ?? 'India') . "\n\n";
        }

        $invoiceMsg = ($isPaid ? "🎉 *PAYMENT RECEIVED & ORDER CONFIRMED!*" : "🎉 *ORDER CONFIRMED!*") . "\n"
                    . "Thank you for shopping with *{$shopName}*!\n\n"
                    . "🧾 *TAX INVOICE / ORDER SUMMARY*\n"
                    . "━━━━━━━━━━━━━━━━━━━━\n"
                    . "🆔 *Order #:* {$orderNo}\n"
                    . "📅 *Date:* {$orderDate}\n"
                    . "👤 *Customer:* {$custName} (+{$peer})\n"
                    . "💳 *Payment Mode:* {$payTitle}\n"
                    . "💵 *Payment Status:* {$payStatusText}\n"
                    . "📦 *Order Status:* Confirmed (Preparing for dispatch)\n\n"
                    . ($destLine !== '' ? "{$destLine}" : '')
                    . "🛍️ *ITEMIZED DETAILS:*\n"
                    . "{$itemsBlock}\n"
                    . "━━━━━━━━━━━━━━━━━━━━\n"
                    . "• *Subtotal:* {$cartTot}\n"
                    . "• *Delivery:* FREE Delivery (₹0.00) 🚚\n"
                    . "• *Taxes (GST 5%):* Included in price\n"
                    . "💰 *" . ($isPaid ? "NET AMOUNT PAID" : "TOTAL AMOUNT DUE") . ":* *{$cartTot}*\n"
                    . "━━━━━━━━━━━━━━━━━━━━\n"
                    . "🚚 *Estimated Delivery:* 3 - 5 business days\n"
                    . "📦 A live courier tracking link will be sent to your WhatsApp as soon as your package is dispatched!\n\n"
                    . "Need any assistance? Reply *5* to chat with our team!";

        $waCloud->send_text($peer, $invoiceMsg);

        $interactive = [
            'type'   => 'button',
            'header' => [
                'type' => 'text',
                'text' => function_exists('mb_substr') ? mb_substr("Order #{$orderNo}", 0, 60) : "Order Confirmed",
            ],
            'body'   => [
                'text' => "Your order #{$orderNo} has been placed successfully! What would you like to do next?",
            ],
            'footer' => [
                'text' => 'Free delivery on all orders',
            ],
            'action' => [
                'buttons' => [
                    [
                        'type'  => 'reply',
                        'reply' => [
                            'id'    => 'track_order',
                            'title' => '📦 Track Order',
                        ],
                    ],
                    [
                        'type'  => 'reply',
                        'reply' => [
                            'id'    => 'browse_products',
                            'title' => '🛍️ Browse More',
                        ],
                    ],
                    [
                        'type'  => 'reply',
                        'reply' => [
                            'id'    => 'main_menu',
                            'title' => '🏠 Main Menu',
                        ],
                    ],
                ],
            ],
        ];

        $waCloud->send_interactive($peer, $interactive);
    }
}
