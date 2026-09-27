<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * WhatsApp reply brain:
 * Customer text → OpenAI Responses or Gemini → MCP tool (MySQL) → natural reply.
 */

function sk_wa_ai_config(?array $settings = null): array {
    $CI =& get_instance();
    if ($settings === null) {
        if (!isset($CI->Sk_Admin_model)) {
            $CI->load->model('Sk_Admin_model');
        }
        $settings = $CI->Sk_Admin_model->get_settings();
    }
    $provider = strtolower(trim((string)($settings['wa_ai_provider'] ?? 'openai')));
    if (!in_array($provider, ['openai', 'gemini'], true)) {
        $provider = 'openai';
    }
    $enabled = !empty($settings['wa_ai_enabled']) && $settings['wa_ai_enabled'] !== '0';
    return [
        'enabled'       => $enabled,
        'provider'      => $provider,
        'openai_key'    => trim((string)($settings['wa_ai_openai_key'] ?? '')),
        'openai_model'  => trim((string)($settings['wa_ai_openai_model'] ?? 'gpt-4.1-mini')) ?: 'gpt-4.1-mini',
        'gemini_key'    => trim((string)($settings['wa_ai_gemini_key'] ?? '')),
        'gemini_model'  => trim((string)($settings['wa_ai_gemini_model'] ?? 'gemini-2.0-flash')) ?: 'gemini-2.0-flash',
    ];
}

function sk_wa_ai_is_ready(?array $settings = null): bool {
    $cfg = sk_wa_ai_config($settings);
    if (empty($cfg['enabled'])) {
        return false;
    }
    if ($cfg['provider'] === 'gemini') {
        return $cfg['gemini_key'] !== '';
    }
    return $cfg['openai_key'] !== '';
}

function sk_wa_ai_tools(): array {
    $props = [
        'query'      => ['type' => 'string', 'description' => 'Customer words: product name, color, size'],
        'product_id' => ['type' => 'integer', 'description' => 'Known product id'],
        'order_id'   => ['type' => 'integer', 'description' => 'Order id'],
        'phone'      => ['type' => 'string', 'description' => 'Customer phone'],
    ];
    $defs = [
        ['name' => 'search_products', 'description' => 'Search this shop catalog in MySQL by name, color, or size.', 'properties' => ['query']],
        ['name' => 'check_stock', 'description' => 'Check product stock in MySQL. Use when the customer asks if an item is available.', 'properties' => ['query', 'product_id']],
        ['name' => 'get_product', 'description' => 'Get one product price and variants by id.', 'properties' => ['product_id']],
        ['name' => 'get_order_status', 'description' => 'Look up an order status by order id.', 'properties' => ['order_id']],
        ['name' => 'human_handoff', 'description' => 'Hand the chat to a human when the customer asks for a person.', 'properties' => ['query', 'phone']],
    ];
    $out = [];
    foreach ($defs as $d) {
        $schemaProps = [];
        foreach ($d['properties'] as $p) {
            $schemaProps[$p] = $props[$p];
        }
        $out[] = [
            'name'        => $d['name'],
            'description' => $d['description'],
            'parameters'  => [
                'type'       => 'object',
                'properties' => $schemaProps,
                'additionalProperties' => false,
            ],
        ];
    }
    return $out;
}

function sk_wa_ai_instructions(array $tenant): string {
    $shop = trim((string)($tenant['shop_name'] ?? 'the shop'));
    return 'You are the WhatsApp shop assistant for ' . $shop . '. '
        . 'Reply in the customer language, short and natural (1-3 sentences). '
        . 'When they ask about a product, price, size, color, or availability, call search_products or check_stock before answering. '
        . 'Never invent stock or prices. Use only tool results. Prices are INR.';
}

/**
 * @param array<int,array{role:string,content:string}> $history
 * @return array{reply:string,tool:?string,tool_result:?array,provider:string}
 */
function sk_wa_ai_chat(string $text, array $tenant = [], array $history = [], ?array $settings = null): array {
    $cfg = sk_wa_ai_config($settings);
    $empty = ['reply' => '', 'tool' => null, 'tool_result' => null, 'provider' => $cfg['provider']];
    $text = trim($text);
    if ($text === '' || !sk_wa_ai_is_ready($settings)) {
        return $empty;
    }
    $CI =& get_instance();
    $CI->load->helper('sk_mcp_tool');
    if ($cfg['provider'] === 'gemini') {
        return sk_wa_ai_gemini_loop($text, $tenant, $history, $cfg);
    }
    return sk_wa_ai_openai_loop($text, $tenant, $history, $cfg);
}

function sk_wa_ai_run_tool(string $name, $args, array $tenant): array {
    $params = is_array($args) ? $args : [];
    if (is_string($args) && $args !== '') {
        $decoded = json_decode($args, true);
        $params = is_array($decoded) ? $decoded : [];
    }
    return sk_ai_mcp_execute_tool($name, $params, $tenant);
}

function sk_wa_ai_openai_loop(string $text, array $tenant, array $history, array $cfg): array {
    $tools = [];
    foreach (sk_wa_ai_tools() as $t) {
        $tools[] = [
            'type'        => 'function',
            'name'        => $t['name'],
            'description' => $t['description'],
            'parameters'  => $t['parameters'],
        ];
    }
    $input = [];
    foreach (array_slice($history, -8) as $h) {
        $role = ($h['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
        $content = trim((string)($h['content'] ?? ''));
        if ($content === '') {
            continue;
        }
        $input[] = ['role' => $role, 'content' => $content];
    }
    $input[] = ['role' => 'user', 'content' => $text];

    $body = [
        'model'        => $cfg['openai_model'],
        'instructions' => sk_wa_ai_instructions($tenant),
        'input'        => $input,
        'tools'        => $tools,
    ];
    $lastTool = null;
    $lastResult = null;
    $reply = '';

    for ($i = 0; $i < 4; $i++) {
        $res = sk_wa_ai_http('POST', 'https://api.openai.com/v1/responses', $body, [
            'Authorization: Bearer ' . $cfg['openai_key'],
            'Content-Type: application/json',
        ]);
        if (empty($res['ok'])) {
            log_message('error', 'WhatsApp OpenAI: ' . ($res['error'] ?? 'failed'));
            break;
        }
        $data = $res['data'];
        $calls = [];
        foreach ((array)($data['output'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (($item['type'] ?? '') === 'function_call') {
                $calls[] = $item;
            }
            if (($item['type'] ?? '') === 'message') {
                foreach ((array)($item['content'] ?? []) as $part) {
                    if (is_array($part) && ($part['type'] ?? '') === 'output_text') {
                        $reply = trim((string)($part['text'] ?? ''));
                    }
                }
            }
        }
        if (!$calls) {
            break;
        }
        $follow = [];
        foreach ($calls as $call) {
            $name = (string)($call['name'] ?? '');
            $out = sk_wa_ai_run_tool($name, $call['arguments'] ?? '{}', $tenant);
            $lastTool = $name;
            $lastResult = $out;
            $follow[] = [
                'type'    => 'function_call_output',
                'call_id' => (string)($call['call_id'] ?? $call['id'] ?? ''),
                'output'  => json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
        }
        $body = [
            'model'                => $cfg['openai_model'],
            'previous_response_id' => (string)($data['id'] ?? ''),
            'input'                => $follow,
            'tools'                => $tools,
        ];
        $reply = '';
    }

    if ($reply === '' && $lastResult) {
        $reply = sk_wa_ai_fallback_reply($lastResult);
    }
    return ['reply' => $reply, 'tool' => $lastTool, 'tool_result' => $lastResult, 'provider' => 'openai'];
}

function sk_wa_ai_gemini_loop(string $text, array $tenant, array $history, array $cfg): array {
    $decls = [];
    foreach (sk_wa_ai_tools() as $t) {
        $decls[] = [
            'name'        => $t['name'],
            'description' => $t['description'],
            'parameters'  => $t['parameters'],
        ];
    }
    $contents = [];
    foreach (array_slice($history, -8) as $h) {
        $content = trim((string)($h['content'] ?? ''));
        if ($content === '') {
            continue;
        }
        $contents[] = [
            'role'  => (($h['role'] ?? '') === 'assistant') ? 'model' : 'user',
            'parts' => [['text' => $content]],
        ];
    }
    $contents[] = ['role' => 'user', 'parts' => [['text' => $text]]];

    $model = rawurlencode($cfg['gemini_model']);
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . rawurlencode($cfg['gemini_key']);
    $lastTool = null;
    $lastResult = null;
    $reply = '';

    for ($i = 0; $i < 4; $i++) {
        $res = sk_wa_ai_http('POST', $url, [
            'systemInstruction' => ['parts' => [['text' => sk_wa_ai_instructions($tenant)]]],
            'contents'          => $contents,
            'tools'             => [['functionDeclarations' => $decls]],
        ], ['Content-Type: application/json']);
        if (empty($res['ok'])) {
            log_message('error', 'WhatsApp Gemini: ' . ($res['error'] ?? 'failed'));
            break;
        }
        $parts = $res['data']['candidates'][0]['content']['parts'] ?? [];
        $fnParts = [];
        $textParts = [];
        foreach ((array)$parts as $part) {
            if (!is_array($part)) {
                continue;
            }
            if (!empty($part['functionCall']['name'])) {
                $fnParts[] = $part;
            } elseif (isset($part['text'])) {
                $textParts[] = (string)$part['text'];
            }
        }
        if ($textParts) {
            $reply = trim(implode("\n", $textParts));
        }
        if (!$fnParts) {
            break;
        }
        $contents[] = ['role' => 'model', 'parts' => $fnParts];
        $responseParts = [];
        foreach ($fnParts as $part) {
            $name = (string)$part['functionCall']['name'];
            $args = $part['functionCall']['args'] ?? [];
            $out = sk_wa_ai_run_tool($name, $args, $tenant);
            $lastTool = $name;
            $lastResult = $out;
            $responseParts[] = [
                'functionResponse' => [
                    'name'     => $name,
                    'response' => $out,
                ],
            ];
        }
        $contents[] = ['role' => 'user', 'parts' => $responseParts];
        $reply = '';
    }

    if ($reply === '' && $lastResult) {
        $reply = sk_wa_ai_fallback_reply($lastResult);
    }
    return ['reply' => $reply, 'tool' => $lastTool, 'tool_result' => $lastResult, 'provider' => 'gemini'];
}

function sk_wa_ai_fallback_reply(array $toolResult): string {
    $products = $toolResult['data']['products'] ?? [];
    if (is_array($products) && !empty($products[0]['name'])) {
        $p = $products[0];
        $stock = (int)($p['stock'] ?? 0);
        return trim((string)$p['name']) . ($stock > 0 ? ' is in stock (' . $stock . ').' : ' is out of stock.')
            . ' Price ₹' . number_format((float)($p['price'] ?? 0), 0) . '.';
    }
    if (isset($toolResult['data']['stock'])) {
        $n = (int)$toolResult['data']['stock'];
        return $n > 0 ? ('Yes, we have ' . $n . ' in stock.') : 'That item is currently out of stock.';
    }
    if (!empty($toolResult['error']['message'])) {
        return 'I could not find that in the shop. Try another name or size.';
    }
    return 'How else can I help with products or orders?';
}

function sk_wa_ai_http(string $method, string $url, array $body, array $headers): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        return ['ok' => false, 'error' => $err ?: 'curl failed', 'data' => []];
    }
    $data = json_decode((string)$raw, true);
    if ($code < 200 || $code >= 300) {
        $msg = is_array($data) ? (string)($data['error']['message'] ?? $data['error']['status'] ?? '') : '';
        return ['ok' => false, 'error' => $msg !== '' ? $msg : ('HTTP ' . $code), 'data' => is_array($data) ? $data : []];
    }
    return ['ok' => true, 'error' => '', 'data' => is_array($data) ? $data : []];
}
