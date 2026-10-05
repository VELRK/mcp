<?php
// Safe to load from index.php before CodeIgniter defines BASEPATH.

/**
 * Load KEY=VALUE pairs from a .env file into getenv/$_ENV/$_SERVER (once).
 */
function sk_dotenv_load(?string $path = null): void {
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    if ($path === null) {
        $path = FCPATH . '.env';
    }
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '') {
            continue;
        }
        $len = strlen($value);
        if ($len >= 2) {
            $q = $value[0];
            if (($q === '"' || $q === "'") && substr($value, -1) === $q) {
                $value = substr($value, 1, -1);
            }
        }
        if (getenv($key) !== false) {
            continue;
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function sk_env(string $key, string $default = ''): string {
    $val = getenv($key);
    if ($val === false || $val === null) {
        $val = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
    return is_string($val) ? trim($val) : $default;
}

function sk_env_bool(string $key, bool $default = false): bool {
    $raw = strtolower(sk_env($key, $default ? '1' : '0'));
    return in_array($raw, ['1', 'true', 'yes', 'on'], true);
}
