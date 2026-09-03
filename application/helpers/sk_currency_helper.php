<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Application currency from admin settings.
 * Default: Indian Rupee (₹ / INR).
 */
if (!function_exists('sk_currency_settings')) {
    function sk_currency_settings(?array $settings = null): array {
        if (is_array($settings)) {
            return $settings;
        }
        $CI =& get_instance();
        if (isset($CI->sk_settings) && is_array($CI->sk_settings)) {
            return $CI->sk_settings;
        }
        if (method_exists($CI, 'get_settings')) {
            $loaded = $CI->get_settings();
            return is_array($loaded) ? $loaded : [];
        }
        return [];
    }
}

if (!function_exists('sk_currency_symbol')) {
    function sk_currency_symbol(?array $settings = null): string {
        $settings = sk_currency_settings($settings);
        $sym = trim((string)($settings['currency_symbol'] ?? ''));
        return $sym !== '' ? $sym : '₹';
    }
}

if (!function_exists('sk_currency_code')) {
    function sk_currency_code(?array $settings = null): string {
        $settings = sk_currency_settings($settings);
        $code = strtoupper(trim((string)($settings['currency_code'] ?? '')));
        if ($code === '' || $code === 'RM') {
            return $code === 'RM' ? 'MYR' : 'INR';
        }
        return $code;
    }
}

if (!function_exists('sk_money')) {
    /** Format amount with currency symbol from settings, e.g. ₹1,234.00 */
    function sk_money($amount, ?array $settings = null, int $decimals = 2): string {
        return sk_currency_symbol($settings) . number_format((float)$amount, $decimals);
    }
}
