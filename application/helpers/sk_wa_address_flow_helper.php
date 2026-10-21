<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * WhatsApp Flow for delivery address, plus per-shop chat steps.
 * The Flow endpoint checks that email and phone are not already used
 * by a different customer before saving.
 */

function sk_wa_shop_rules_defaults(): array {
    return [
        'product_label' => 'products',
        'welcome_text'  => '',
        'steps' => [
            ['key' => 'catalog', 'title' => 'Browse Products', 'enabled' => 1],
            ['key' => 'offers',  'title' => 'Offers & Deals', 'enabled' => 1],
            ['key' => 'search',  'title' => 'Search Product', 'enabled' => 1],
            ['key' => 'track',   'title' => 'Track Order', 'enabled' => 1],
            ['key' => 'human',   'title' => 'Talk to Team', 'enabled' => 1],
            ['key' => 'address', 'title' => 'Delivery address', 'enabled' => 1],
            ['key' => 'payment', 'title' => 'Payment', 'enabled' => 1],
        ],
    ];
}

function sk_wa_shop_rules_ensure_schema(): void {
    $CI =& get_instance();
    if (!$CI->db->table_exists('vendor_wa_bot_rules')) {
        $CI->db->query("CREATE TABLE `vendor_wa_bot_rules` (
            `vendor_id` INT UNSIGNED NOT NULL,
            `product_label` VARCHAR(40) NOT NULL DEFAULT 'products',
            `welcome_text` VARCHAR(500) NOT NULL DEFAULT '',
            `steps_json` TEXT NULL,
            `address_flow_id` VARCHAR(64) NULL,
            `menu_flow_id` VARCHAR(255) NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`vendor_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return;
    }
    if (!$CI->db->field_exists('menu_flow_id', 'vendor_wa_bot_rules')) {
        $CI->db->query("ALTER TABLE `vendor_wa_bot_rules` ADD COLUMN `menu_flow_id` VARCHAR(255) NULL");
        return;
    }
    foreach ($CI->db->field_data('vendor_wa_bot_rules') as $field) {
        if (($field->name ?? '') === 'menu_flow_id' && (int)($field->max_length ?? 0) < 255) {
            $CI->db->query("ALTER TABLE `vendor_wa_bot_rules` MODIFY `menu_flow_id` VARCHAR(255) NULL");
            break;
        }
    }
}

function sk_wa_shop_rules(int $vendorId): array {
    $defaults = sk_wa_shop_rules_defaults();
    if ($vendorId < 1) {
        return $defaults;
    }
    sk_wa_shop_rules_ensure_schema();
    $CI =& get_instance();
    $row = $CI->db->where('vendor_id', $vendorId)->get('vendor_wa_bot_rules')->row_array();
    if (!$row) {
        return $defaults;
    }
    $saved = json_decode((string)($row['steps_json'] ?? ''), true);
    $byKey = [];
    if (is_array($saved)) {
        foreach ($saved as $step) {
            if (is_array($step) && !empty($step['key'])) {
                $byKey[(string)$step['key']] = $step;
            }
        }
    }
    $steps = [];
    foreach ($defaults['steps'] as $step) {
        $incoming = $byKey[$step['key']] ?? null;
        if (is_array($incoming)) {
            $title = trim((string)($incoming['title'] ?? $step['title']));
            $steps[] = [
                'key'     => $step['key'],
                'title'   => $title !== '' ? $title : $step['title'],
                'enabled' => !empty($incoming['enabled']) ? 1 : 0,
            ];
        } else {
            $steps[] = $step;
        }
    }
    $label = trim((string)($row['product_label'] ?? ''));
    return [
        'product_label'   => $label !== '' ? $label : 'products',
        'welcome_text'    => trim((string)($row['welcome_text'] ?? '')),
        'steps'           => $steps,
        'address_flow_id' => trim((string)($row['address_flow_id'] ?? '')),
        'menu_flow_id'    => trim((string)($row['menu_flow_id'] ?? '')),
    ];
}

function sk_wa_shop_rules_save(int $vendorId, array $rules): void {
    if ($vendorId < 1) {
        return;
    }
    sk_wa_shop_rules_ensure_schema();
    $defaults = sk_wa_shop_rules_defaults();
    $label = trim((string)($rules['product_label'] ?? 'products'));
    $label = function_exists('mb_substr') ? mb_substr($label, 0, 40) : substr($label, 0, 40);
    if ($label === '') {
        $label = 'products';
    }
    $welcome = trim((string)($rules['welcome_text'] ?? ''));
    $welcome = function_exists('mb_substr') ? mb_substr($welcome, 0, 500) : substr($welcome, 0, 500);
    $byKey = [];
    foreach ((array)($rules['steps'] ?? []) as $step) {
        if (!is_array($step) || empty($step['key'])) {
            continue;
        }
        $byKey[(string)$step['key']] = $step;
    }
    $steps = [];
    foreach ($defaults['steps'] as $step) {
        $key = $step['key'];
        $incoming = $byKey[$key] ?? [];
        $title = trim((string)($incoming['title'] ?? $step['title']));
        $title = function_exists('mb_substr') ? mb_substr($title, 0, 24) : substr($title, 0, 24);
        $steps[] = [
            'key'     => $key,
            'title'   => $title !== '' ? $title : $step['title'],
            'enabled' => !empty($incoming['enabled']) ? 1 : 0,
        ];
    }
    $CI =& get_instance();
    $now = date('Y-m-d H:i:s');
    $payload = [
        'product_label' => $label,
        'welcome_text'  => $welcome,
        'steps_json'    => json_encode($steps, JSON_UNESCAPED_UNICODE),
        'updated_at'    => $now,
    ];
    $existing = $CI->db->where('vendor_id', $vendorId)->get('vendor_wa_bot_rules')->row_array();
    if ($existing) {
        $CI->db->where('vendor_id', $vendorId)->update('vendor_wa_bot_rules', $payload);
    } else {
        $payload['vendor_id'] = $vendorId;
        $CI->db->insert('vendor_wa_bot_rules', $payload);
    }
}

function sk_wa_shop_welcome_interactive(int $vendorId, string $shopName): array {
    $rules = sk_wa_shop_rules($vendorId);
    $text = $rules['welcome_text'] !== ''
        ? $rules['welcome_text']
        : "Welcome to {$shopName}.\nChoose an option.";
    $ids = [
        'catalog' => ['id' => 'browse_products', 'title' => 'Browse Products', 'description' => 'See all products'],
        'offers'  => ['id' => 'offers_deals', 'title' => 'Offers & Deals', 'description' => 'Today\'s discounts'],
        'search'  => ['id' => 'search_product', 'title' => 'Search Product', 'description' => 'Find a product by name'],
        'track'   => ['id' => 'track_order', 'title' => 'Track Order', 'description' => 'Check an order'],
        'human'   => ['id' => 'talk_to_team', 'title' => 'Talk to Team', 'description' => 'Message the shop team'],
    ];
    $rows = [];
    foreach ($rules['steps'] as $step) {
        $key = (string)($step['key'] ?? '');
        if (!isset($ids[$key]) || empty($step['enabled'])) {
            continue;
        }
        $rows[] = [
            'id'          => $ids[$key]['id'],
            'title'       => $ids[$key]['title'],
            'description' => $ids[$key]['description'],
        ];
    }
    if ($rows === []) {
        $rows[] = ['id' => 'browse_products', 'title' => 'Browse Products', 'description' => 'See all products'];
    }
    $header = function_exists('mb_substr') ? mb_substr($shopName, 0, 60) : substr($shopName, 0, 60);
    return [
        'type'   => 'list',
        'header' => ['type' => 'text', 'text' => $header !== '' ? $header : 'Shop'],
        'body'   => ['text' => $text],
        'footer' => ['text' => 'Choose an option'],
        'action' => [
            'button'   => 'Main menu',
            'sections' => [[
                'title' => 'Main menu',
                'rows'  => $rows,
            ]],
        ],
    ];
}

function sk_wa_address_flow_json(): array {
    $field = static function (string $name, string $label, bool $required, string $input = 'text'): array {
        $node = [
            'type'       => 'TextInput',
            'name'       => $name,
            'label'      => $label,
            'required'   => $required,
            'input-type' => $input,
        ];
        if ($name === 'pincode') {
            $node['min-chars'] = 4;
            $node['max-chars'] = 6;
            $node['helper-text'] = 'Required';
        } elseif ($required) {
            $node['helper-text'] = 'Required';
        }
        return $node;
    };
    return [
        'version'          => '7.0',
        'data_api_version' => '3.0',
        'routing_model'    => ['ADDRESS' => ['SUCCESS']],
        'screens'          => [
            [
                'id'    => 'ADDRESS',
                'title' => 'Delivery address',
                'data'  => [
                    'error_message' => ['type' => 'string', '__example__' => ''],
                    'full_name'     => ['type' => 'string', '__example__' => ''],
                    'phone'         => ['type' => 'string', '__example__' => ''],
                    'email'         => ['type' => 'string', '__example__' => ''],
                    'line1'         => ['type' => 'string', '__example__' => ''],
                    'line2'         => ['type' => 'string', '__example__' => ''],
                    'city'          => ['type' => 'string', '__example__' => ''],
                    'state'         => ['type' => 'string', '__example__' => ''],
                    'pincode'       => ['type' => 'string', '__example__' => ''],
                ],
                'layout' => [
                    'type'     => 'SingleColumnLayout',
                    'children' => [[
                    'type'     => 'Form',
                    'name'     => 'address_form',
                    'init-values' => [
                        'full_name' => '${data.full_name}',
                        'phone'     => '${data.phone}',
                        'email'     => '${data.email}',
                        'line1'     => '${data.line1}',
                        'line2'     => '${data.line2}',
                        'city'      => '${data.city}',
                        'state'     => '${data.state}',
                        'pincode'   => '${data.pincode}',
                    ],
                    'children' => [
                            ['type' => 'TextBody', 'text' => '${data.error_message}'],
                            $field('full_name', 'Full name', true),
                            $field('phone', 'Phone', true, 'phone'),
                            $field('email', 'Email', false, 'email'),
                            $field('line1', 'Address line 1', true),
                            $field('line2', 'Address line 2', false),
                            $field('city', 'City', true),
                            $field('state', 'State', true),
                            $field('pincode', 'Pincode', true),
                            [
                                'type'            => 'Footer',
                                'label'           => 'Save address',
                                'on-click-action' => [
                                    'name'    => 'data_exchange',
                                    'payload' => [
                                        'full_name' => '${form.full_name}',
                                        'phone'     => '${form.phone}',
                                        'email'     => '${form.email}',
                                        'line1'     => '${form.line1}',
                                        'line2'     => '${form.line2}',
                                        'city'      => '${form.city}',
                                        'state'     => '${form.state}',
                                        'pincode'   => '${form.pincode}',
                                    ],
                                ],
                            ],
                        ],
                    ]],
                ],
            ],
            [
                'id'       => 'SUCCESS',
                'title'    => 'Address saved',
                'terminal' => true,
                'success'  => true,
                'data'     => [
                    'address_id' => ['type' => 'string', '__example__' => '1'],
                ],
                'layout' => [
                    'type'     => 'SingleColumnLayout',
                    'children' => [
                        ['type' => 'TextHeading', 'text' => 'Address saved'],
                        ['type' => 'TextBody', 'text' => 'Continue to choose payment in the chat.'],
                        [
                            'type'            => 'Footer',
                            'label'           => 'Done',
                            'on-click-action' => [
                                'name'    => 'complete',
                                'payload' => [
                                    'address_id' => '${data.address_id}',
                                    'saved'      => '1',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

function sk_wa_flow_private_key() {
    $path = APPPATH . 'config/wa_flow_private.pem';
    if (!is_file($path)) {
        if (getenv('OPENSSL_CONF') === false || getenv('OPENSSL_CONF') === '') {
            foreach (['/etc/ssl/openssl.cnf', '/usr/lib/ssl/openssl.cnf'] as $cnf) {
                if (is_file($cnf)) {
                    putenv('OPENSSL_CONF=' . $cnf);
                    break;
                }
            }
        }
        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($res === false) {
            return null;
        }
        $pem = '';
        openssl_pkey_export($res, $pem);
        if ($pem === '') {
            return null;
        }
        if (@file_put_contents($path, $pem) === false) {
            return null;
        }
        @chmod($path, 0640);
    }
    $pem = (string)@file_get_contents($path);
    $key = openssl_pkey_get_private($pem);
    return $key ?: null;
}

function sk_wa_flow_public_key_pem(): string {
    $key = sk_wa_flow_private_key();
    if (!$key) {
        return '';
    }
    $details = openssl_pkey_get_details($key);
    return trim((string)($details['key'] ?? ''));
}

function sk_wa_flow_mgf1(string $seed, int $length): string {
    $out = '';
    $counter = 0;
    while (strlen($out) < $length) {
        $out .= hash('sha256', $seed . pack('N', $counter), true);
        $counter++;
    }
    return substr($out, 0, $length);
}

function sk_wa_flow_xor(string $a, string $b): string {
    $out = '';
    $n = min(strlen($a), strlen($b));
    for ($i = 0; $i < $n; $i++) {
        $out .= $a[$i] ^ $b[$i];
    }
    return $out;
}

function sk_wa_flow_oaep_unpad(string $em): string {
    $hLen = 32;
    if (strlen($em) < (2 * $hLen + 2) || ord($em[0]) !== 0) {
        return '';
    }
    $maskedSeed = substr($em, 1, $hLen);
    $maskedDb = substr($em, 1 + $hLen);
    $seed = sk_wa_flow_xor($maskedSeed, sk_wa_flow_mgf1($maskedDb, $hLen));
    $db = sk_wa_flow_xor($maskedDb, sk_wa_flow_mgf1($seed, strlen($maskedDb)));
    $lHash = hash('sha256', '', true);
    if (!hash_equals($lHash, substr($db, 0, $hLen))) {
        return '';
    }
    $rest = substr($db, $hLen);
    $sep = strpos($rest, "\x01");
    if ($sep === false) {
        return '';
    }
    return substr($rest, $sep + 1);
}

function sk_wa_flow_rsa_decrypt(string $ciphertext, $privateKey): string {
    $em = '';
    if (!openssl_private_decrypt($ciphertext, $em, $privateKey, OPENSSL_NO_PADDING)) {
        return '';
    }
    $details = openssl_pkey_get_details($privateKey);
    $keyBytes = (int)((($details['bits'] ?? 2048)) / 8);
    if ($keyBytes > 0 && strlen($em) < $keyBytes) {
        $em = str_repeat("\0", $keyBytes - strlen($em)) . $em;
    }
    return sk_wa_flow_oaep_unpad($em);
}

/**
 * @return array{ok:bool,data?:array,aes?:string,iv?:string,error?:string}
 */
function sk_wa_flow_decrypt_request(array $body): array {
    $private = sk_wa_flow_private_key();
    if (!$private) {
        return ['ok' => false, 'error' => 'Flow private key is missing.'];
    }
    $aesCipher = base64_decode((string)($body['encrypted_aes_key'] ?? ''), true);
    $flowCipher = base64_decode((string)($body['encrypted_flow_data'] ?? ''), true);
    $iv = base64_decode((string)($body['initial_vector'] ?? ''), true);
    if ($aesCipher === false || $flowCipher === false || $iv === false || strlen($flowCipher) < 17) {
        return ['ok' => false, 'error' => 'Encrypted payload is incomplete.'];
    }
    $aesKey = sk_wa_flow_rsa_decrypt($aesCipher, $private);
    if ($aesKey === '' || strlen($aesKey) !== 16) {
        return ['ok' => false, 'error' => 'AES key decrypt failed.'];
    }
    $tag = substr($flowCipher, -16);
    $cipher = substr($flowCipher, 0, -16);
    $plain = openssl_decrypt($cipher, 'aes-128-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag);
    if ($plain === false) {
        return ['ok' => false, 'error' => 'Flow payload decrypt failed.'];
    }
    $data = json_decode($plain, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Flow payload is not JSON.'];
    }
    return ['ok' => true, 'data' => $data, 'aes' => $aesKey, 'iv' => $iv];
}

function sk_wa_flow_encrypt_response(string $json, string $aesKey, string $iv): string {
    $flipped = '';
    $n = strlen($iv);
    for ($i = 0; $i < $n; $i++) {
        $flipped .= chr(ord($iv[$i]) ^ 0xFF);
    }
    $tag = '';
    $cipher = openssl_encrypt($json, 'aes-128-gcm', $aesKey, OPENSSL_RAW_DATA, $flipped, $tag);
    if ($cipher === false) {
        return '';
    }
    return base64_encode($cipher . $tag);
}

function sk_wa_flow_token_secret(): string {
    $CI =& get_instance();
    $CI->load->helper('sk_whatsapp_cloud');
    $cfg = sk_wa_cloud_config();
    $secret = trim((string)($cfg['app_secret'] ?? ''));
    if ($secret === '') {
        $secret = 'wa-flow-address';
    }
    return $secret;
}

function sk_wa_address_flow_token(int $vendorId, string $peer, string $mode, int $addressId = 0): string {
    $mode = $mode === 'edit' ? 'edit' : 'create';
    $payload = $vendorId . '|' . preg_replace('/\D+/', '', $peer) . '|' . $mode . '|' . max(0, $addressId);
    $sig = substr(hash_hmac('sha256', $payload, sk_wa_flow_token_secret()), 0, 20);
    return $payload . '|' . $sig;
}

function sk_wa_address_flow_token_parse(string $token): array {
    $parts = explode('|', $token);
    if (count($parts) !== 5) {
        return [];
    }
    [$vendorId, $peer, $mode, $addressId, $sig] = $parts;
    $payload = $vendorId . '|' . $peer . '|' . $mode . '|' . $addressId;
    $expect = substr(hash_hmac('sha256', $payload, sk_wa_flow_token_secret()), 0, 20);
    if (!hash_equals($expect, $sig)) {
        return [];
    }
    return [
        'vendor_id'  => (int)$vendorId,
        'peer'       => preg_replace('/\D+/', '', $peer) ?? '',
        'mode'       => $mode === 'edit' ? 'edit' : 'create',
        'address_id' => (int)$addressId,
    ];
}

function sk_wa_address_flow_endpoint_url(): string {
    return function_exists('site_url') ? site_url('shopkart-api/whatsapp/flow/address') : '';
}

function sk_wa_address_flow_store_id(int $vendorId, string $flowId): void {
    if ($vendorId < 1 || $flowId === '') {
        return;
    }
    sk_wa_shop_rules_ensure_schema();
    $CI =& get_instance();
    $now = date('Y-m-d H:i:s');
    $existing = $CI->db->where('vendor_id', $vendorId)->get('vendor_wa_bot_rules')->row_array();
    if ($existing) {
        $CI->db->where('vendor_id', $vendorId)->update('vendor_wa_bot_rules', [
            'address_flow_id' => $flowId,
            'updated_at'      => $now,
        ]);
        return;
    }
    $defaults = sk_wa_shop_rules_defaults();
    $CI->db->insert('vendor_wa_bot_rules', [
        'vendor_id'       => $vendorId,
        'product_label'   => $defaults['product_label'],
        'welcome_text'    => '',
        'steps_json'      => json_encode($defaults['steps'], JSON_UNESCAPED_UNICODE),
        'address_flow_id' => $flowId,
        'updated_at'      => $now,
    ]);
}

function sk_wa_address_flow_ensure(int $vendorId, string $phoneNumberId): array {
    $rules = sk_wa_shop_rules($vendorId);
    if ($rules['address_flow_id'] !== '') {
        return ['ok' => true, 'flow_id' => $rules['address_flow_id']];
    }
    $CI =& get_instance();
    $CI->load->helper('sk_whatsapp_cloud');
    $CI->load->model('Sk_Admin_model');
    $settings = $CI->Sk_Admin_model->get_settings();
    $settings['vendor_id'] = $vendorId;
    if ($phoneNumberId !== '') {
        $settings['_wa_phone_number_id'] = $phoneNumberId;
    }
    $cfg = sk_wa_cloud_config($settings, $vendorId);
    $token = trim((string)($cfg['access_token'] ?? ''));
    $waba = trim((string)($cfg['waba_id'] ?? ''));
    $phoneId = $phoneNumberId !== '' ? $phoneNumberId : trim((string)($cfg['phone_number_id'] ?? ''));
    $endpoint = sk_wa_address_flow_endpoint_url();
    if ($token === '' || $waba === '' || $phoneId === '' || stripos($endpoint, 'https://') !== 0) {
        return ['ok' => false, 'error' => 'WhatsApp Flow needs an HTTPS endpoint, WABA id, and access token.'];
    }
    $public = sk_wa_flow_public_key_pem();
    if ($public === '') {
        return ['ok' => false, 'error' => 'Could not create the Flow encryption key.'];
    }
    $keyRes = sk_wa_meta_graph('POST', $phoneId . '/whatsapp_business_encryption', [], [
        'business_public_key' => $public,
    ], $token, $settings);
    if (empty($keyRes['ok'])) {
        $err = (string)($keyRes['error'] ?? '');
        if ($err !== '' && stripos($err, 'already') === false) {
            return ['ok' => false, 'error' => $err];
        }
    }
    $create = sk_wa_meta_graph('POST', $waba . '/flows', [], json_encode([
        'name'         => 'Shop delivery address ' . $vendorId,
        'categories'   => ['OTHER'],
        'flow_json'    => json_encode(sk_wa_address_flow_json(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'endpoint_uri' => $endpoint,
        'publish'      => true,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $token, $settings);
    $flowId = trim((string)($create['data']['id'] ?? ''));
    if ($flowId === '') {
        $err = trim((string)($create['error'] ?? ''));
        return ['ok' => false, 'error' => $err !== '' ? $err : 'Meta did not return a Flow id.'];
    }
    sk_wa_address_flow_store_id($vendorId, $flowId);
    return ['ok' => true, 'flow_id' => $flowId];
}

function sk_wa_address_flow_prefill(string $peer, int $addressId = 0): array {
    $CI =& get_instance();
    $blank = [
        'error_message' => '',
        'full_name'     => '',
        'phone'         => $peer,
        'email'         => '',
        'line1'         => '',
        'line2'         => '',
        'city'          => '',
        'state'         => '',
        'pincode'       => '',
    ];
    $CI->load->model('Sk_User_model');
    $user = $CI->Sk_User_model->get_by_phone($peer);
    if ($user) {
        $blank['full_name'] = (string)($user['name'] ?? '');
        $blank['email'] = (string)($user['email'] ?? '');
        if (!empty($user['phone'])) {
            $blank['phone'] = (string)$user['phone'];
        }
    }
    $addr = null;
    if ($addressId > 0) {
        $addr = $CI->db->where('id', $addressId)->get('addresses')->row_array();
    }
    if (!$addr) {
        $digits = preg_replace('/\D+/', '', $peer);
        $last10 = strlen($digits) >= 10 ? substr($digits, -10) : $digits;
        $addr = $CI->db->group_start()
            ->where('phone', $peer)
            ->or_like('phone', $last10)
            ->group_end()
            ->order_by('id', 'DESC')
            ->get('addresses')
            ->row_array();
    }
    if ($addr) {
        foreach (['full_name', 'phone', 'line1', 'line2', 'city', 'state', 'pincode'] as $col) {
            if (trim((string)($addr[$col] ?? '')) !== '') {
                $blank[$col] = (string)$addr[$col];
            }
        }
    }
    return $blank;
}

/**
 * @return array{ok:bool,error?:string,screen?:string,data?:array}
 */
function sk_wa_address_flow_save(array $ctx, array $input): array {
    $peer = (string)($ctx['peer'] ?? '');
    $vendorId = (int)($ctx['vendor_id'] ?? 0);
    $fullName = trim((string)($input['full_name'] ?? ''));
    $phone = preg_replace('/\D+/', '', (string)($input['phone'] ?? '')) ?: '';
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $line1 = trim((string)($input['line1'] ?? ''));
    $line2 = trim((string)($input['line2'] ?? ''));
    $city = trim((string)($input['city'] ?? ''));
    $state = trim((string)($input['state'] ?? ''));
    $pincode = preg_replace('/\D+/', '', (string)($input['pincode'] ?? '')) ?: '';
    $data = [
        'error_message' => '',
        'full_name'     => $fullName,
        'phone'         => $phone,
        'email'         => $email,
        'line1'         => $line1,
        'line2'         => $line2,
        'city'          => $city,
        'state'         => $state,
        'pincode'       => $pincode,
    ];
    if ($fullName === '' || $phone === '' || $line1 === '' || $city === '' || $state === '' || strlen($pincode) < 4) {
        $data['error_message'] = 'Enter name, phone, address, city, state, and pincode.';
        return ['ok' => false, 'screen' => 'ADDRESS', 'data' => $data];
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $data['error_message'] = 'Enter a valid email, or leave it blank.';
        return ['ok' => false, 'screen' => 'ADDRESS', 'data' => $data];
    }
    $CI =& get_instance();
    $CI->load->model('Sk_User_model');
    $user = $peer !== '' ? $CI->Sk_User_model->get_by_phone($peer) : null;
    $userId = (int)($user['id'] ?? 0);
    if ($email !== '' && $CI->Sk_User_model->email_exists($email, $userId > 0 ? $userId : null)) {
        $data['error_message'] = 'This email is already used by another customer.';
        return ['ok' => false, 'screen' => 'ADDRESS', 'data' => $data];
    }
    if ($CI->Sk_User_model->phone_exists($phone, $userId > 0 ? $userId : null)) {
        $waDigits = preg_replace('/\D+/', '', $peer) ?: '';
        $sameWa = $waDigits !== '' && substr($waDigits, -10) === substr($phone, -10);
        if (!$sameWa) {
            $data['error_message'] = 'This phone number is already used by another customer.';
            return ['ok' => false, 'screen' => 'ADDRESS', 'data' => $data];
        }
    }
    $now = date('Y-m-d H:i:s');
    if ($userId < 1) {
        $userId = (int)$CI->Sk_User_model->create([
            'name'   => $fullName,
            'phone'  => $peer !== '' ? $peer : $phone,
            'email'  => $email !== '' ? $email : null,
            'status' => 'active',
        ]);
    } else {
        $update = ['name' => $fullName, 'updated_at' => $now];
        if ($email !== '') {
            $update['email'] = $email;
        }
        $CI->Sk_User_model->update($userId, $update);
    }
    if ($userId < 1) {
        $data['error_message'] = 'Could not save the customer. Try again.';
        return ['ok' => false, 'screen' => 'ADDRESS', 'data' => $data];
    }
    $CI->db->insert('addresses', [
        'vendor_id'    => $vendorId,
        'user_id'      => $userId,
        'label'        => 'Home',
        'full_name'    => $fullName,
        'phone'        => $phone,
        'line1'        => $line1,
        'line2'        => $line2,
        'city'         => $city,
        'state'        => $state,
        'pincode'      => $pincode,
        'country'      => 'India',
        'is_default'   => 1,
        'address_type' => 'shipping',
        'created_at'   => $now,
    ]);
    $addressId = (int)$CI->db->insert_id();
    return [
        'ok'     => true,
        'screen' => 'SUCCESS',
        'data'   => ['address_id' => (string)$addressId],
    ];
}

function sk_wa_address_flow_handle(array $payload): array {
    $action = strtolower((string)($payload['action'] ?? ''));
    if ($action === 'ping') {
        return ['data' => ['status' => 'active']];
    }
    $token = sk_wa_address_flow_token_parse((string)($payload['flow_token'] ?? ''));
    if ($token === []) {
        $data = sk_wa_address_flow_prefill('', 0);
        $data['error_message'] = 'This address form expired. Ask the shop to send it again.';
        return [
            'screen' => 'ADDRESS',
            'data'   => $data,
        ];
    }
    if ($action === 'init' || $action === 'back') {
        return [
            'screen' => 'ADDRESS',
            'data'   => sk_wa_address_flow_prefill($token['peer'], (int)$token['address_id']),
        ];
    }
    $input = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
    $saved = sk_wa_address_flow_save($token, $input);
    return [
        'screen' => (string)($saved['screen'] ?? 'ADDRESS'),
        'data'   => is_array($saved['data'] ?? null) ? $saved['data'] : [],
    ];
}

function sk_wa_address_flow_send($waCloud, string $peer, int $vendorId, string $phoneNumberId, string $shopName, string $mode = 'create', int $addressId = 0): bool {
    $ensured = sk_wa_address_flow_ensure($vendorId, $phoneNumberId);
    if (empty($ensured['ok']) || empty($ensured['flow_id'])) {
        return false;
    }
    $mode = $mode === 'edit' ? 'edit' : 'create';
    $prefill = sk_wa_address_flow_prefill($peer, $addressId);
    $body = $mode === 'edit'
        ? "Update the delivery address for {$shopName}. We will check that the email and phone are not already used."
        : "Enter the delivery address for {$shopName}. We will check that the email and phone are not already used.";
    $interactive = [
        'type'   => 'flow',
        'header' => ['type' => 'text', 'text' => $mode === 'edit' ? 'Edit address' : 'Delivery address'],
        'body'   => ['text' => $body],
        'footer' => ['text' => function_exists('mb_substr') ? mb_substr($shopName, 0, 60) : $shopName],
        'action' => [
            'name'       => 'flow',
            'parameters' => [
                'flow_message_version' => '3',
                'flow_token'           => sk_wa_address_flow_token($vendorId, $peer, $mode, $addressId),
                'flow_id'              => (string)$ensured['flow_id'],
                'flow_cta'             => $mode === 'edit' ? 'Edit address' : 'Enter address',
                'flow_action'          => 'navigate',
                'flow_action_payload'  => [
                    'screen' => 'ADDRESS',
                    'data'   => $prefill,
                ],
            ],
        ],
    ];
    $res = $waCloud->send_interactive($peer, $interactive);
    return !empty($res['success']);
}

function sk_wa_menu_flow_specs(): array {
    return [
        'SEARCH' => ['title' => 'Search product', 'field' => 'query', 'label' => 'Product name', 'input' => 'text', 'button' => 'Search', 'step' => 'search'],
        'TRACK'  => ['title' => 'Track order', 'field' => 'order_number', 'label' => 'Order number', 'input' => 'text', 'button' => 'Track', 'step' => 'track'],
        'TALK'   => ['title' => 'Talk to team', 'field' => 'message', 'label' => 'Message to the shop', 'input' => 'area', 'button' => 'Send', 'step' => 'human'],
    ];
}

function sk_wa_menu_flow_json(string $screen): array {
    $specs = sk_wa_menu_flow_specs();
    $spec = $specs[$screen];
    $inputNode = [
        'type'     => $spec['input'] === 'area' ? 'TextArea' : 'TextInput',
        'name'     => $spec['field'],
        'label'    => $spec['label'],
        'required' => true,
    ];
    if ($spec['input'] !== 'area') {
        $inputNode['input-type'] = 'text';
    }
    return [
        'version'       => '7.0',
        'routing_model' => [$screen => []],
        'screens'       => [[
            'id'       => $screen,
            'title'    => $spec['title'],
            'terminal' => true,
            'success'  => true,
            'layout'   => [
                'type'     => 'SingleColumnLayout',
                'children' => [[
                    'type'     => 'Form',
                    'name'     => 'form',
                    'children' => [
                        $inputNode,
                        [
                            'type'            => 'Footer',
                            'label'           => $spec['button'],
                            'on-click-action' => [
                                'name'    => 'complete',
                                'payload' => [
                                    'step'          => $spec['step'],
                                    $spec['field']  => '${form.' . $spec['field'] . '}',
                                ],
                            ],
                        ],
                    ],
                ]],
            ],
        ]],
    ];
}

function sk_wa_menu_flow_ids(int $vendorId): array {
    $rules = sk_wa_shop_rules($vendorId);
    $raw = trim((string)($rules['menu_flow_id'] ?? ''));
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $ids = [];
    foreach (['SEARCH', 'TRACK', 'TALK'] as $screen) {
        $id = trim((string)($decoded[$screen] ?? ''));
        if ($id !== '') {
            $ids[$screen] = $id;
        }
    }
    return $ids;
}

function sk_wa_menu_flow_store_ids(int $vendorId, array $ids): void {
    if ($vendorId < 1) {
        return;
    }
    sk_wa_shop_rules_ensure_schema();
    $CI =& get_instance();
    $stored = json_encode($ids, JSON_UNESCAPED_UNICODE);
    $existing = $CI->db->where('vendor_id', $vendorId)->get('vendor_wa_bot_rules')->row_array();
    $now = date('Y-m-d H:i:s');
    if ($existing) {
        $CI->db->where('vendor_id', $vendorId)->update('vendor_wa_bot_rules', [
            'menu_flow_id' => $stored,
            'updated_at'   => $now,
        ]);
        return;
    }
    $defaults = sk_wa_shop_rules_defaults();
    $CI->db->insert('vendor_wa_bot_rules', [
        'vendor_id'     => $vendorId,
        'product_label' => $defaults['product_label'],
        'welcome_text'  => '',
        'steps_json'    => json_encode($defaults['steps'], JSON_UNESCAPED_UNICODE),
        'menu_flow_id'  => $stored,
        'updated_at'    => $now,
    ]);
}

function sk_wa_menu_flow_ensure(int $vendorId, string $phoneNumberId, string $screen): array {
    $ids = sk_wa_menu_flow_ids($vendorId);
    if (!empty($ids[$screen])) {
        return ['ok' => true, 'flow_id' => $ids[$screen]];
    }
    $CI =& get_instance();
    $CI->load->helper('sk_whatsapp_cloud');
    $CI->load->model('Sk_Admin_model');
    $settings = $CI->Sk_Admin_model->get_settings();
    $settings['vendor_id'] = $vendorId;
    if ($phoneNumberId !== '') {
        $settings['_wa_phone_number_id'] = $phoneNumberId;
    }
    $cfg = sk_wa_cloud_config($settings, $vendorId);
    $token = trim((string)($cfg['access_token'] ?? ''));
    $waba = trim((string)($cfg['waba_id'] ?? ''));
    if ($token === '' || $waba === '') {
        return ['ok' => false, 'error' => 'WhatsApp token or WABA id is missing.'];
    }
    $create = sk_wa_meta_graph('POST', $waba . '/flows', [], json_encode([
        'name'       => 'Shop ' . strtolower($screen) . ' ' . $vendorId,
        'categories' => ['OTHER'],
        'flow_json'  => json_encode(sk_wa_menu_flow_json($screen), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'publish'    => true,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $token, $settings);
    $flowId = trim((string)($create['data']['id'] ?? ''));
    if ($flowId === '') {
        $err = trim((string)($create['error'] ?? ''));
        return ['ok' => false, 'error' => $err !== '' ? $err : 'Meta did not return a menu Flow id.'];
    }
    $ids[$screen] = $flowId;
    sk_wa_menu_flow_store_ids($vendorId, $ids);
    return ['ok' => true, 'flow_id' => $flowId];
}

function sk_wa_menu_flow_send($waCloud, string $peer, int $vendorId, string $phoneNumberId, string $screen): bool {
    $screen = strtoupper($screen);
    if (!in_array($screen, ['SEARCH', 'TRACK', 'TALK'], true)) {
        return false;
    }
    $ensured = sk_wa_menu_flow_ensure($vendorId, $phoneNumberId, $screen);
    if (empty($ensured['ok']) || empty($ensured['flow_id'])) {
        return false;
    }
    $copy = [
        'SEARCH' => ['Search product', 'Enter the product name. The catalog opens with matches.', 'Search'],
        'TRACK'  => ['Track order', 'Enter the order number from the shop.', 'Track'],
        'TALK'   => ['Talk to team', 'Write your message. It is sent to the shop owner, then this chat stays open.', 'Write message'],
    ];
    [$header, $body, $cta] = $copy[$screen];
    $res = $waCloud->send_interactive($peer, [
        'type'   => 'flow',
        'header' => ['type' => 'text', 'text' => $header],
        'body'   => ['text' => $body],
        'action' => [
            'name'       => 'flow',
            'parameters' => [
                'flow_message_version' => '3',
                'flow_token'           => 'menu-' . $vendorId . '-' . preg_replace('/\D+/', '', $peer),
                'flow_id'              => (string)$ensured['flow_id'],
                'flow_cta'             => $cta,
                'flow_action'          => 'navigate',
                'flow_action_payload'  => ['screen' => $screen],
            ],
        ],
    ]);
    return !empty($res['success']);
}
