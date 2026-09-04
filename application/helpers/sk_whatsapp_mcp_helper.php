<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MCP → WhatsApp Cloud: convert structured MCP replies into Graph API messages
 * (text, reply buttons, list menu, CTA URL, image).
 */

function sk_wa_mcp_config(?array $settings = null): array {
    if ($settings === null) {
        $CI =& get_instance();
        $CI->load->model('Sk_Admin_model');
        $settings = $CI->Sk_Admin_model->get_settings();
    }
    return [
        'enabled' => !empty($settings['wa_mcp_enabled']) && $settings['wa_mcp_enabled'] !== '0',
        'url'     => rtrim(trim((string)($settings['wa_mcp_url'] ?? '')), '/'),
        'token'   => trim((string)($settings['wa_mcp_token'] ?? '')),
        'timeout' => max(5, (int)($settings['wa_mcp_timeout'] ?? 12)),
    ];
}

function sk_wa_mcp_is_ready(?array $settings = null): bool {
    $cfg = sk_wa_mcp_config($settings);
    return $cfg['enabled'] && $cfg['url'] !== '';
}

/** Parse inbound Cloud webhook message into a compact MCP payload. */
function sk_wa_mcp_parse_inbound(array $m): array {
    $type = (string)($m['type'] ?? 'text');
    $out = [
        'type'  => $type,
        'text'  => '',
        'id'    => '',
        'title' => '',
    ];
    if ($type === 'text') {
        $out['text'] = trim((string)($m['text']['body'] ?? ''));
    } elseif ($type === 'interactive') {
        $inter = is_array($m['interactive'] ?? null) ? $m['interactive'] : [];
        $itype = (string)($inter['type'] ?? '');
        if ($itype === 'button_reply') {
            $out['type'] = 'button_reply';
            $out['id'] = (string)($inter['button_reply']['id'] ?? '');
            $out['title'] = (string)($inter['button_reply']['title'] ?? '');
            $out['text'] = $out['title'] !== '' ? $out['title'] : $out['id'];
        } elseif ($itype === 'list_reply') {
            $out['type'] = 'list_reply';
            $out['id'] = (string)($inter['list_reply']['id'] ?? '');
            $out['title'] = (string)($inter['list_reply']['title'] ?? '');
            $out['text'] = $out['title'] !== '' ? $out['title'] : $out['id'];
        } else {
            $out['text'] = $itype !== '' ? $itype : 'interactive';
        }
    } elseif ($type === 'button') {
        $out['type'] = 'button_reply';
        $out['id'] = (string)($m['button']['payload'] ?? '');
        $out['title'] = (string)($m['button']['text'] ?? '');
        $out['text'] = $out['title'] !== '' ? $out['title'] : $out['id'];
    } elseif ($type === 'image') {
        $out['text'] = trim((string)($m['image']['caption'] ?? 'Image'));
    } elseif ($type === 'video') {
        $out['text'] = trim((string)($m['video']['caption'] ?? 'Video'));
    } else {
        $out['text'] = ucfirst($type);
    }
    return $out;
}

function sk_wa_mcp_call(array $payload, ?array $settings = null): array {
    $cfg = sk_wa_mcp_config($settings);
    if ($cfg['url'] === '') {
        return ['success' => false, 'message' => 'MCP URL is not set.', 'data' => null];
    }
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($cfg['token'] !== '') {
        $headers[] = 'Authorization: Bearer ' . $cfg['token'];
        $headers[] = 'X-MCP-Token: ' . $cfg['token'];
    }
    $ch = curl_init($cfg['url']);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT        => $cfg['timeout'],
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err) {
        return ['success' => false, 'message' => $err, 'data' => null, 'http' => $code];
    }
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($decoded)) {
        $text = trim((string)$raw);
        if ($text !== '') {
            return ['success' => true, 'data' => ['type' => 'text', 'text' => $text], 'http' => $code];
        }
        return ['success' => false, 'message' => 'MCP returned empty or invalid JSON.', 'data' => null, 'http' => $code];
    }
    return ['success' => $code >= 200 && $code < 300, 'data' => $decoded, 'http' => $code, 'message' => 'OK'];
}

/**
 * Normalize any MCP payload into a list of message specs:
 * {type: text|buttons|list|cta|image, ...}
 */
function sk_wa_mcp_normalize_messages($raw): array {
    if (is_string($raw) && trim($raw) !== '') {
        return [['type' => 'text', 'text' => trim($raw)]];
    }
    if (!is_array($raw)) {
        return [];
    }
    if (isset($raw['messages']) && is_array($raw['messages'])) {
        $out = [];
        foreach ($raw['messages'] as $m) {
            $out = array_merge($out, sk_wa_mcp_normalize_messages($m));
        }
        return $out;
    }
    if (isset($raw['data']) && is_array($raw['data'])) {
        $inner = sk_wa_mcp_normalize_messages($raw['data']);
        if ($inner) {
            return $inner;
        }
    }
    if (isset($raw['content']) && is_array($raw['content'])) {
        $out = [];
        foreach ($raw['content'] as $block) {
            if (!is_array($block)) {
                continue;
            }
            if (($block['type'] ?? '') === 'text' && isset($block['text'])) {
                $out[] = ['type' => 'text', 'text' => (string)$block['text']];
            } else {
                $out = array_merge($out, sk_wa_mcp_normalize_messages($block));
            }
        }
        if ($out) {
            return $out;
        }
    }

    $type = strtolower((string)($raw['type'] ?? ''));
    if ($type === '' && isset($raw['text']) && !isset($raw['body']) && !isset($raw['buttons']) && !isset($raw['sections'])) {
        $type = 'text';
    }
    if ($type === 'button') {
        $type = 'buttons';
    }
    if ($type === 'cta_url' || $type === 'link') {
        $type = 'cta';
    }
    if ($type === 'product_list' || $type === 'menu') {
        $type = 'list';
    }

    if ($type === 'text' || ($type === '' && !empty($raw['text']))) {
        $text = trim((string)($raw['text'] ?? $raw['body'] ?? ''));
        return $text !== '' ? [['type' => 'text', 'text' => $text]] : [];
    }
    if ($type === 'image') {
        $link = trim((string)($raw['url'] ?? $raw['link'] ?? $raw['image'] ?? ''));
        if ($link === '') {
            return [];
        }
        return [[
            'type'    => 'image',
            'url'     => $link,
            'caption' => trim((string)($raw['caption'] ?? $raw['text'] ?? $raw['body'] ?? '')),
        ]];
    }
    if ($type === 'buttons') {
        $buttons = $raw['buttons'] ?? $raw['action']['buttons'] ?? [];
        $mapped = [];
        foreach ((array)$buttons as $b) {
            if (!is_array($b)) {
                continue;
            }
            $id = (string)($b['id'] ?? $b['reply']['id'] ?? '');
            $title = (string)($b['title'] ?? $b['reply']['title'] ?? '');
            if ($id === '' && $title === '') {
                continue;
            }
            if ($id === '') {
                $id = substr(preg_replace('/[^a-z0-9_]+/i', '_', strtolower($title)), 0, 200);
            }
            $mapped[] = [
                'id'    => mb_substr($id, 0, 256),
                'title' => mb_substr($title !== '' ? $title : $id, 0, 20),
            ];
            if (count($mapped) >= 3) {
                break;
            }
        }
        $body = trim((string)($raw['body'] ?? $raw['text'] ?? ''));
        if ($body === '' || !$mapped) {
            return $body !== '' ? [['type' => 'text', 'text' => $body]] : [];
        }
        return [[
            'type'    => 'buttons',
            'header'  => trim((string)($raw['header'] ?? '')),
            'body'    => mb_substr($body, 0, 1024),
            'footer'  => trim((string)($raw['footer'] ?? '')),
            'buttons' => $mapped,
        ]];
    }
    if ($type === 'list') {
        $sections = $raw['sections'] ?? [];
        if (!$sections && !empty($raw['rows']) && is_array($raw['rows'])) {
            $sections = [['title' => (string)($raw['section_title'] ?? 'Menu'), 'rows' => $raw['rows']]];
        }
        if (!$sections && !empty($raw['items']) && is_array($raw['items'])) {
            $sections = [['title' => (string)($raw['section_title'] ?? 'Options'), 'rows' => $raw['items']]];
        }
        $normSections = [];
        $rowCount = 0;
        foreach ((array)$sections as $sec) {
            if (!is_array($sec)) {
                continue;
            }
            $rows = [];
            foreach ((array)($sec['rows'] ?? []) as $row) {
                if (!is_array($row) || $rowCount >= 10) {
                    continue;
                }
                $id = (string)($row['id'] ?? '');
                $title = (string)($row['title'] ?? $row['name'] ?? '');
                if ($id === '' && $title === '') {
                    continue;
                }
                if ($id === '') {
                    $id = substr(preg_replace('/[^a-z0-9_]+/i', '_', strtolower($title)), 0, 200);
                }
                $rows[] = [
                    'id'          => mb_substr($id, 0, 200),
                    'title'       => mb_substr($title !== '' ? $title : $id, 0, 24),
                    'description' => mb_substr((string)($row['description'] ?? $row['desc'] ?? ''), 0, 72),
                ];
                $rowCount++;
            }
            if ($rows) {
                $normSections[] = [
                    'title' => mb_substr((string)($sec['title'] ?? 'Menu'), 0, 24),
                    'rows'  => $rows,
                ];
            }
        }
        $body = trim((string)($raw['body'] ?? $raw['text'] ?? 'Choose an option'));
        if (!$normSections) {
            return [['type' => 'text', 'text' => $body]];
        }
        $btn = trim((string)($raw['button'] ?? $raw['button_text'] ?? 'Menu'));
        return [[
            'type'     => 'list',
            'header'   => trim((string)($raw['header'] ?? '')),
            'body'     => mb_substr($body, 0, 1024),
            'footer'   => trim((string)($raw['footer'] ?? '')),
            'button'   => mb_substr($btn !== '' ? $btn : 'Menu', 0, 20),
            'sections' => $normSections,
        ]];
    }
    if ($type === 'cta') {
        $url = trim((string)($raw['url'] ?? $raw['link'] ?? ''));
        $body = trim((string)($raw['body'] ?? $raw['text'] ?? ''));
        if ($url === '' || $body === '') {
            return $body !== '' ? [['type' => 'text', 'text' => $body]] : [];
        }
        return [[
            'type'         => 'cta',
            'header'       => trim((string)($raw['header'] ?? '')),
            'body'         => mb_substr($body, 0, 1024),
            'footer'       => trim((string)($raw['footer'] ?? '')),
            'display_text' => mb_substr((string)($raw['display_text'] ?? $raw['button'] ?? 'Open'), 0, 20),
            'url'          => $url,
        ]];
    }

    if (!empty($raw['text'])) {
        return [['type' => 'text', 'text' => trim((string)$raw['text'])]];
    }
    return [];
}

function sk_wa_mcp_cloud_payload(string $to, array $spec): ?array {
    $to = preg_replace('/\D+/', '', $to);
    $type = (string)($spec['type'] ?? 'text');
    $base = [
        'messaging_product' => 'whatsapp',
        'recipient_type'    => 'individual',
        'to'                => $to,
    ];
    if ($type === 'text') {
        $text = trim((string)($spec['text'] ?? ''));
        if ($text === '') {
            return null;
        }
        $base['type'] = 'text';
        $base['text'] = ['preview_url' => true, 'body' => mb_substr($text, 0, 4096)];
        return $base;
    }
    if ($type === 'image') {
        $url = trim((string)($spec['url'] ?? ''));
        if ($url === '') {
            return null;
        }
        $img = ['link' => $url];
        $cap = trim((string)($spec['caption'] ?? ''));
        if ($cap !== '') {
            $img['caption'] = mb_substr($cap, 0, 1024);
        }
        $base['type'] = 'image';
        $base['image'] = $img;
        return $base;
    }

    $interactive = [];
    if ($type === 'buttons') {
        $btns = [];
        foreach ((array)($spec['buttons'] ?? []) as $b) {
            $btns[] = [
                'type'  => 'reply',
                'reply' => [
                    'id'    => (string)$b['id'],
                    'title' => (string)$b['title'],
                ],
            ];
        }
        $interactive = [
            'type'   => 'button',
            'body'   => ['text' => (string)$spec['body']],
            'action' => ['buttons' => $btns],
        ];
    } elseif ($type === 'list') {
        $interactive = [
            'type'   => 'list',
            'body'   => ['text' => (string)$spec['body']],
            'action' => [
                'button'   => (string)$spec['button'],
                'sections' => $spec['sections'],
            ],
        ];
    } elseif ($type === 'cta') {
        $interactive = [
            'type'   => 'cta_url',
            'body'   => ['text' => (string)$spec['body']],
            'action' => [
                'name'       => 'cta_url',
                'parameters' => [
                    'display_text' => (string)$spec['display_text'],
                    'url'          => (string)$spec['url'],
                ],
            ],
        ];
    } else {
        return null;
    }
    if (!empty($spec['header'])) {
        $interactive['header'] = ['type' => 'text', 'text' => mb_substr((string)$spec['header'], 0, 60)];
    }
    if (!empty($spec['footer'])) {
        $interactive['footer'] = ['text' => mb_substr((string)$spec['footer'], 0, 60)];
    }
    $base['type'] = 'interactive';
    $base['interactive'] = $interactive;
    return $base;
}

function sk_wa_mcp_preview(array $spec): string {
    $type = (string)($spec['type'] ?? 'text');
    if ($type === 'text') {
        return (string)($spec['text'] ?? '');
    }
    if ($type === 'image') {
        return trim((string)($spec['caption'] ?? 'Image'));
    }
    if ($type === 'buttons') {
        return (string)($spec['body'] ?? 'Buttons');
    }
    if ($type === 'list') {
        return (string)($spec['body'] ?? 'Menu');
    }
    if ($type === 'cta') {
        return (string)($spec['body'] ?? 'Link');
    }
    return ucfirst($type);
}

/**
 * Send every MCP message spec via Cloud API and store in the inbox thread.
 */
function sk_wa_mcp_send_specs(string $to, array $specs, array $conversation, ?array $settings = null): int {
    if (!$specs) {
        return 0;
    }
    $CI =& get_instance();
    $CI->load->library('Whatsapp_cloud', $settings ?: []);
    $CI->load->model('Sk_Whatsapp_cloud_model');
    $sent = 0;
    foreach ($specs as $spec) {
        if (!is_array($spec)) {
            continue;
        }
        $payload = sk_wa_mcp_cloud_payload($to, $spec);
        if (!$payload) {
            continue;
        }
        $result = $CI->whatsapp_cloud->send_payload($payload);
        $wamid = '';
        if (!empty($result['data']['messages'][0]['id'])) {
            $wamid = (string)$result['data']['messages'][0]['id'];
        }
        $ok = !empty($result['success']);
        $CI->Sk_Whatsapp_cloud_model->add_message((int)$conversation['id'], [
            'wamid'     => $wamid ?: null,
            'direction' => 'out',
            'type'      => ($spec['type'] ?? '') === 'image' ? 'image' : 'text',
            'body'      => sk_wa_mcp_preview($spec),
            'media_url' => $spec['url'] ?? null,
            'status'    => $ok ? 'sent' : 'failed',
            'error_text'=> $ok ? null : ($result['message'] ?? 'Send failed'),
            'raw_json'  => json_encode(['mcp' => $spec, 'graph' => $result['data'] ?? $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        if ($ok) {
            $sent++;
        }
    }
    return $sent;
}

/** Sidebar: WhatsApp messages sent today + whether MCP is open. */
function sk_wa_sidebar_stats(): array {
    static $memo = null;
    if (is_array($memo)) {
        return $memo;
    }

    $sentToday = 0;
    $sentTotal = 0;
    $unread = 0;
    $CI =& get_instance();
    try {
        if (!isset($CI->db)) {
            $CI->load->database();
        }
        if ($CI->db->table_exists('wa_cloud_messages')) {
            $sentToday = (int)$CI->db->where('direction', 'out')
                ->where('created_at >=', date('Y-m-d 00:00:00'))
                ->count_all_results('wa_cloud_messages');
            $sentTotal = (int)$CI->db->where('direction', 'out')->count_all_results('wa_cloud_messages');
        }
        if ($CI->db->table_exists('wa_cloud_conversations') && $CI->db->field_exists('unread', 'wa_cloud_conversations')) {
            $row = $CI->db->select_sum('unread')->get('wa_cloud_conversations')->row();
            $unread = (int)($row->unread ?? 0);
        }
    } catch (Throwable $e) {
        // keep zeros
    }

    $settings = [];
    try {
        $CI->load->model('Sk_Admin_model');
        $settings = $CI->Sk_Admin_model->get_settings();
    } catch (Throwable $e) {
        $settings = [];
    }

    $cfg = function_exists('sk_wa_mcp_config') ? sk_wa_mcp_config($settings) : ['enabled' => false, 'url' => ''];
    $enabled = !empty($cfg['enabled']) && trim((string)($cfg['url'] ?? '')) !== '';
    $open = $enabled && sk_wa_mcp_is_online($cfg);

    $memo = [
        'sent_today'  => $sentToday,
        'sent_total'  => $sentTotal,
        'unread'      => $unread,
        'mcp_enabled' => $enabled,
        'mcp_open'    => $open,
        'mcp_label'   => $open ? 'Open' : 'Closed',
    ];
    return $memo;
}

/** Fast reachability check; cached 60s so the sidebar does not ping every click. */
function sk_wa_mcp_is_online(array $cfg): bool {
    $CI =& get_instance();
    $cached = $CI->session->userdata('sk_mcp_online');
    if (is_array($cached) && (int)($cached['exp'] ?? 0) > time()) {
        return !empty($cached['ok']);
    }

    $ok = false;
    $url = trim((string)($cfg['url'] ?? ''));
    if ($url !== '') {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => true,
            CURLOPT_TIMEOUT        => 2,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        curl_exec($ch);
        $err = curl_errno($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        // Any HTTP answer means the MCP host is up (POST-only URLs often return 404/405).
        $ok = ($err === 0 && $code > 0);
        if (!$ok && $err !== 0) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 2,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPGET        => true,
            ]);
            curl_exec($ch);
            $err = curl_errno($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $ok = ($err === 0 && $code > 0);
        }
    }

    $CI->session->set_userdata('sk_mcp_online', ['ok' => $ok, 'exp' => time() + 60]);
    return $ok;
}
