<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Format DB datetime strings in app timezone (Asia/Kuala_Lumpur — Malaysia Time).
 * Treats naive MySQL DATETIME as already-local wall clock (written via date()).
 */
if (!function_exists('sk_app_timezone')) {
    function sk_app_timezone(): string {
        $tz = date_default_timezone_get();
        return $tz !== '' ? $tz : 'Asia/Kuala_Lumpur';
    }
}

if (!function_exists('sk_format_datetime')) {
    function sk_format_datetime($value, string $format = 'd M Y, h:i A'): string {
        if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') {
            return '—';
        }
        $tz = new DateTimeZone(sk_app_timezone());
        try {
            if ($value instanceof DateTimeInterface) {
                $dt = DateTimeImmutable::createFromInterface($value)->setTimezone($tz);
            } else {
                $raw = trim((string)$value);
                // ISO with Z / offset → convert into app TZ
                if (preg_match('/[Zz]|[+\-]\d{2}:?\d{2}$/', $raw)) {
                    $dt = new DateTimeImmutable($raw);
                    $dt = $dt->setTimezone($tz);
                } else {
                    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw, $tz)
                        ?: DateTimeImmutable::createFromFormat('Y-m-d', $raw, $tz)
                        ?: new DateTimeImmutable($raw, $tz);
                }
            }
            return $dt->format($format);
        } catch (Throwable $e) {
            $ts = strtotime((string)$value);
            return $ts ? date($format, $ts) : '—';
        }
    }
}

if (!function_exists('sk_format_date')) {
    function sk_format_date($value, string $format = 'd M Y'): string {
        return sk_format_datetime($value, $format);
    }
}

/**
 * ISO-8601 with Malaysia offset for API clients (e.g. 2026-08-01T15:30:00+08:00).
 */
if (!function_exists('sk_api_datetime')) {
    function sk_api_datetime($value): ?string {
        if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }
        $tz = new DateTimeZone(sk_app_timezone());
        try {
            $raw = trim((string)$value);
            if (preg_match('/[Zz]|[+\-]\d{2}:?\d{2}$/', $raw)) {
                $dt = (new DateTimeImmutable($raw))->setTimezone($tz);
            } else {
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw, $tz)
                    ?: DateTimeImmutable::createFromFormat('Y-m-d', $raw, $tz)
                    ?: new DateTimeImmutable($raw, $tz);
            }
            return $dt->format('c');
        } catch (Throwable $e) {
            return null;
        }
    }
}

/**
 * Treat a naive DB datetime as the app timezone and return the same moment in another zone.
 */
if (!function_exists('sk_shift_datetime')) {
    function sk_shift_datetime($value, string $timezone = 'Asia/Kolkata', string $format = 'Y-m-d H:i:s'): string {
        if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') {
            return '';
        }
        try {
            $from = new DateTimeZone(sk_app_timezone());
            $to = new DateTimeZone($timezone);
            $raw = trim((string)$value);
            if (preg_match('/[Zz]|[+\-]\d{2}:?\d{2}$/', $raw)) {
                $dt = new DateTimeImmutable($raw);
            } else {
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw, $from)
                    ?: DateTimeImmutable::createFromFormat('Y-m-d', $raw, $from)
                    ?: new DateTimeImmutable($raw, $from);
            }
            return $dt->setTimezone($to)->format($format);
        } catch (Throwable $e) {
            return trim((string)$value);
        }
    }
}

/** Attach created_at_iso + created_at_formatted onto rows that have created_at. */
if (!function_exists('sk_attach_api_dates')) {
    function sk_attach_api_dates(array $rows): array {
        foreach ($rows as &$row) {
            if (!is_array($row) || !array_key_exists('created_at', $row)) {
                continue;
            }
            $row['created_at_iso'] = sk_api_datetime($row['created_at']);
            $row['created_at_formatted'] = sk_format_datetime($row['created_at']);
        }
        unset($row);
        return $rows;
    }
}
