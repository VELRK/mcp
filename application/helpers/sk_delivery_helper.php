<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Shop delivery charges.
 * A matching state zone wins. Otherwise the shop flat rate and free-shipping
 * threshold are used. Platform settings are the fallback when a shop has none.
 */

function sk_delivery_parse_zones(string $text): array {
    $zones = [];
    foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        if (count($parts) < 2 || $parts[0] === '') {
            continue;
        }
        $zones[] = [
            'state'      => $parts[0],
            'charge'     => round(max(0, (float)$parts[1]), 2),
            'free_above' => (isset($parts[2]) && $parts[2] !== '') ? round(max(0, (float)$parts[2]), 2) : null,
        ];
    }
    return $zones;
}

function sk_delivery_zones_text(array $zones): string {
    $lines = [];
    foreach ($zones as $zone) {
        if (!is_array($zone) || trim((string)($zone['state'] ?? '')) === '') {
            continue;
        }
        $line = trim((string)$zone['state']) . ' | ' . (float)($zone['charge'] ?? 0);
        if (isset($zone['free_above']) && $zone['free_above'] !== null && $zone['free_above'] !== '') {
            $line .= ' | ' . (float)$zone['free_above'];
        }
        $lines[] = $line;
    }
    return implode("\n", $lines);
}

function sk_delivery_vendor_rules(int $vendorId): array {
    $rules = [
        'flat_rate'           => null,
        'free_shipping_above' => null,
        'processing_days'     => 2,
        'cod_enabled'         => 1,
        'zones'               => [],
    ];
    if ($vendorId < 1) {
        return $rules;
    }
    $CI =& get_instance();
    if (!$CI->db->table_exists('vendor_stores')) {
        return $rules;
    }
    $row = $CI->db->select('delivery_settings')->where('vendor_id', $vendorId)->get('vendor_stores')->row_array();
    $raw = $row['delivery_settings'] ?? [];
    if (is_string($raw)) {
        $raw = json_decode($raw, true) ?: [];
    }
    if (!is_array($raw)) {
        return $rules;
    }
    if (array_key_exists('flat_rate', $raw) && $raw['flat_rate'] !== null && $raw['flat_rate'] !== '') {
        $rules['flat_rate'] = round(max(0, (float)$raw['flat_rate']), 2);
    }
    if (array_key_exists('free_shipping_above', $raw) && $raw['free_shipping_above'] !== null && $raw['free_shipping_above'] !== '') {
        $rules['free_shipping_above'] = round(max(0, (float)$raw['free_shipping_above']), 2);
    }
    if (isset($raw['processing_days'])) {
        $rules['processing_days'] = max(0, (int)$raw['processing_days']);
    }
    if (array_key_exists('cod_enabled', $raw)) {
        $rules['cod_enabled'] = !empty($raw['cod_enabled']) ? 1 : 0;
    }
    if (!empty($raw['zones']) && is_array($raw['zones'])) {
        $rules['zones'] = $raw['zones'];
    }
    return $rules;
}

function sk_delivery_match_zone(array $zones, string $state): ?array {
    $state = strtolower(trim($state));
    if ($state === '') {
        return null;
    }
    foreach ($zones as $zone) {
        if (!is_array($zone)) {
            continue;
        }
        $name = strtolower(trim((string)($zone['state'] ?? '')));
        if ($name === '') {
            continue;
        }
        if ($name === $state || strpos($state, $name) !== false || strpos($name, $state) !== false) {
            return $zone;
        }
    }
    return null;
}

/**
 * @return array{shipping:float,free:bool,threshold:float,flat_rate:float,amount_remaining:float,message:string,cod_enabled:bool,source:string,zone:string,vendor_id:int}
 */
function sk_delivery_quote(float $goods, array $settings, int $vendorId = 0, string $state = ''): array {
    $rules = sk_delivery_vendor_rules($vendorId);
    $flat = $rules['flat_rate'];
    if ($flat === null) {
        $flat = round(max(0, (float)($settings['shipping_charge'] ?? 0)), 2);
    }
    $freeAbove = $rules['free_shipping_above'];
    if ($freeAbove === null) {
        $freeAbove = round(max(0, (float)($settings['free_shipping_above'] ?? 0)), 2);
    }
    $source = $rules['flat_rate'] !== null || $rules['free_shipping_above'] !== null ? 'shop' : 'platform';
    $zoneName = '';
    $zone = sk_delivery_match_zone($rules['zones'], $state);
    if ($zone) {
        $flat = round(max(0, (float)($zone['charge'] ?? $flat)), 2);
        if (isset($zone['free_above']) && $zone['free_above'] !== null && $zone['free_above'] !== '') {
            $freeAbove = round(max(0, (float)$zone['free_above']), 2);
        }
        $source = 'zone';
        $zoneName = (string)($zone['state'] ?? '');
    }

    $goods = round(max(0, $goods), 2);
    $free = $goods <= 0 || ($freeAbove > 0 && $goods >= $freeAbove);
    $shipping = $free ? 0.0 : $flat;
    $remaining = ($goods <= 0 || $free || $freeAbove <= 0) ? 0.0 : round(max(0, $freeAbove - $goods), 2);
    $symbol = function_exists('sk_currency_symbol') ? sk_currency_symbol($settings) : '';
    if ($goods <= 0) {
        $message = '';
    } elseif ($free) {
        $message = 'You qualify for free delivery.';
    } elseif ($freeAbove > 0) {
        $message = 'Add ' . $symbol . number_format($remaining, 2) . ' more for free delivery.';
    } else {
        $message = 'Delivery charge ' . $symbol . number_format($shipping, 2) . '.';
    }

    return [
        'shipping'         => round($shipping, 2),
        'free'             => $free && $goods > 0,
        'threshold'        => $freeAbove,
        'flat_rate'        => $flat,
        'amount_remaining' => $remaining,
        'message'          => $message,
        'cod_enabled'      => (bool)$rules['cod_enabled'],
        'source'           => $source,
        'zone'             => $zoneName,
        'vendor_id'        => $vendorId,
    ];
}

/**
 * Quote one charge per shop, then add them.
 * $groups is vendor_id => goods amount after discount.
 *
 * @param array<int,float> $groups
 */
function sk_delivery_quote_groups(array $groups, array $settings, string $state = ''): array {
    if (!$groups) {
        $groups = [0 => 0.0];
    }
    $shipping = 0.0;
    $threshold = 0.0;
    $flat = 0.0;
    $remaining = 0.0;
    $cod = true;
    $parts = [];
    $messages = [];
    foreach ($groups as $vendorId => $goods) {
        $quote = sk_delivery_quote((float)$goods, $settings, (int)$vendorId, $state);
        $shipping += $quote['shipping'];
        $threshold = max($threshold, $quote['threshold']);
        $flat += $quote['flat_rate'];
        $remaining += $quote['amount_remaining'];
        if (!$quote['cod_enabled']) {
            $cod = false;
        }
        $label = $quote['zone'] !== '' ? $quote['zone'] : $quote['source'];
        $parts[] = $label . ' ' . number_format($quote['shipping'], 2);
        if ($quote['message'] !== '') {
            $messages[] = $quote['message'];
        }
    }
    $shipping = round($shipping, 2);
    $goodsTotal = round(array_sum($groups), 2);
    return [
        'shipping'         => $shipping,
        'free'             => $goodsTotal > 0 && $shipping <= 0,
        'threshold'        => $threshold,
        'flat_rate'        => round($flat, 2),
        'amount_remaining' => round($remaining, 2),
        'message'          => $messages ? $messages[0] : '',
        'cod_enabled'      => $cod,
        'source'           => count($groups) > 1 ? 'shops' : 'shop',
        'zone'             => '',
        'vendor_id'        => (int)array_key_first($groups),
        'detail'           => implode('; ', $parts),
    ];
}
