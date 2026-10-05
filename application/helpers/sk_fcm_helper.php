<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Firebase Cloud Messaging (HTTP v1) helpers for Talk AI Pilot.
 */

function sk_fcm_ensure_schema(): void {
    $CI =& get_instance();
    if ($CI->db->table_exists('sk_device_tokens')
        && $CI->db->table_exists('sk_notifications')
        && $CI->db->table_exists('sk_notification_logs')) {
        return;
    }
    $sqlFile = FCPATH . 'database/sk_notifications.sql';
    if (!is_file($sqlFile)) {
        return;
    }
    $sql = file_get_contents($sqlFile);
    if ($sql === false || $sql === '') {
        return;
    }
    // Strip line comments then run each statement
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', (string)$sql))) as $stmt) {
        if ($stmt === '' || stripos($stmt, 'CREATE TABLE') === false) {
            continue;
        }
        try {
            $CI->db->query($stmt);
        } catch (Throwable $e) {
            log_message('error', 'sk_fcm_ensure_schema: ' . $e->getMessage());
        }
    }
}

function sk_fcm_config(): array {
    $CI =& get_instance();
    $CI->config->load('firebase', true);
    $cfg = $CI->config->item('firebase');
    if (!is_array($cfg)) {
        $cfg = [];
    }
    // CI use_sections nests $config['firebase'] under the file name again.
    if (isset($cfg['firebase']) && is_array($cfg['firebase']) && !isset($cfg['client_email'])) {
        $cfg = $cfg['firebase'];
    }
    // Direct-include fallback (same pattern as WhatsApp) when section load is empty/wrong.
    if (empty($cfg['client_email']) || empty($cfg['private_key'])) {
        $path = APPPATH . 'config/firebase.php';
        if (is_file($path)) {
            $config = [];
            include $path;
            if (!empty($config['firebase']) && is_array($config['firebase'])) {
                $cfg = $config['firebase'];
            }
        }
    }
    return is_array($cfg) ? $cfg : [];
}

function sk_fcm_web_config(): array {
    $CI =& get_instance();
    $CI->config->load('firebase_web', true);
    $cfg = $CI->config->item('firebase_web');
    if (!is_array($cfg)) {
        $cfg = [];
    }
    if (isset($cfg['firebase_web']) && is_array($cfg['firebase_web']) && !isset($cfg['apiKey'])) {
        $cfg = $cfg['firebase_web'];
    }
    if (empty($cfg['apiKey'])) {
        $path = APPPATH . 'config/firebase_web.php';
        if (is_file($path)) {
            $config = [];
            include $path;
            if (!empty($config['firebase_web']) && is_array($config['firebase_web'])) {
                $cfg = $config['firebase_web'];
            }
        }
    }
    return is_array($cfg) ? $cfg : [];
}

function sk_fcm_is_configured(): bool {
    $cfg = sk_fcm_config();
    $email = trim((string)($cfg['client_email'] ?? ''));
    $key   = trim((string)($cfg['private_key'] ?? ''));
    $pid   = trim((string)($cfg['project_id'] ?? ''));
    if ($email === '' || $key === '' || $pid === '') {
        return false;
    }
    if (stripos($key, 'YOUR_KEY_HERE') !== false || stripos($email, 'xxxxx') !== false) {
        return false;
    }
    return true;
}

/**
 * Normalize media fields from admin / API input.
 *
 * @return array{media_type:string,image_url:?string,video_url:?string}
 */
function sk_fcm_normalize_media(string $mediaType, ?string $imageUrl, ?string $videoUrl): array {
    $mediaType = in_array($mediaType, ['none', 'image', 'video', 'both'], true) ? $mediaType : 'none';
    $imageUrl  = $imageUrl !== null ? trim($imageUrl) : '';
    $videoUrl  = $videoUrl !== null ? trim($videoUrl) : '';

    if ($mediaType === 'none') {
        $imageUrl = '';
        $videoUrl = '';
    } elseif ($mediaType === 'image') {
        $videoUrl = '';
    } elseif ($mediaType === 'video') {
        $imageUrl = '';
    }

    return [
        'media_type' => $mediaType,
        'image_url'  => $imageUrl !== '' ? $imageUrl : null,
        'video_url'  => $videoUrl !== '' ? $videoUrl : null,
    ];
}

function sk_fcm_absolute_url(?string $path): ?string {
    if ($path === null || trim($path) === '') {
        return null;
    }
    $path = trim($path);
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return rtrim(base_url(), '/') . '/' . ltrim($path, '/');
}

/**
 * Build FCM HTTP v1 message body for one token.
 */
function sk_fcm_build_message(string $token, array $notif): array {
    $media = sk_fcm_normalize_media(
        (string)($notif['media_type'] ?? 'none'),
        $notif['image_url'] ?? null,
        $notif['video_url'] ?? null
    );
    $imageAbs = sk_fcm_absolute_url($media['image_url']);
    $videoAbs = sk_fcm_absolute_url($media['video_url']);
    $clickAbs = sk_fcm_absolute_url($notif['click_url'] ?? null);

    $title = (string)($notif['title'] ?? '');
    $body  = (string)($notif['body'] ?? '');
    $nid   = (string)(int)($notif['id'] ?? 0);

    $notification = [
        'title' => $title,
        'body'  => $body,
    ];
    if ($imageAbs) {
        $notification['image'] = $imageAbs;
    }

    $data = [
        'notification_id' => $nid,
        'media_type'      => $media['media_type'],
        'title'           => $title,
        'body'            => $body,
        'image_url'       => $imageAbs ?: '',
        'video_url'       => $videoAbs ?: '',
        'click_url'       => $clickAbs ?: '',
    ];

    $message = [
        'token'        => $token,
        'notification' => $notification,
        'data'         => $data,
        'android'      => [
            'priority'     => 'high',
            'notification' => [
                'sound'        => 'default',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ],
        ],
        'apns' => [
            'payload' => [
                'aps' => [
                    'sound' => 'default',
                    'mutable-content' => 1,
                ],
            ],
        ],
    ];

    if ($imageAbs) {
        $message['android']['notification']['image'] = $imageAbs;
        $message['apns']['fcm_options'] = ['image' => $imageAbs];
    }

    return ['message' => $message];
}

/**
 * OAuth2 access token for FCM using service account JWT.
 */
function sk_fcm_access_token(): array {
    if (!sk_fcm_is_configured()) {
        return [
            'error' => 'Firebase Admin service account is not configured. Paste deal-bc4c4 credentials into application/config/firebase.php (see firebase.php.example).',
        ];
    }

    $cfg = sk_fcm_config();
    $now = time();
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claim = [
        'iss'   => $cfg['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud'   => $cfg['token_uri'] ?? 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ];

    $segments = sk_fcm_b64(json_encode($header)) . '.' . sk_fcm_b64(json_encode($claim));
    $key = openssl_pkey_get_private($cfg['private_key']);
    if ($key === false) {
        return ['error' => 'Invalid Firebase private_key in firebase.php.'];
    }
    $signature = '';
    if (!openssl_sign($segments, $signature, $key, OPENSSL_ALGO_SHA256)) {
        return ['error' => 'Failed to sign Firebase JWT.'];
    }
    $jwt = $segments . '.' . sk_fcm_b64($signature);

    $tokenUri = $cfg['token_uri'] ?? 'https://oauth2.googleapis.com/token';
    $ch = curl_init($tokenUri);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 30,
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $err !== '') {
        return ['error' => 'Could not reach Google OAuth: ' . $err];
    }
    $json = json_decode($raw, true);
    if ($code >= 400 || empty($json['access_token'])) {
        $msg = $json['error_description'] ?? $json['error'] ?? $raw;
        return ['error' => 'OAuth token failed: ' . $msg];
    }
    return ['access_token' => $json['access_token']];
}

function sk_fcm_b64(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Send one FCM message.
 *
 * @return array{success:bool,response?:mixed,error?:string,http_code?:int}
 */
function sk_fcm_send_token(string $token, array $notif, ?string $accessToken = null): array {
    $token = trim($token);
    if ($token === '') {
        return ['success' => false, 'error' => 'Empty device token.'];
    }

    if ($accessToken === null) {
        $auth = sk_fcm_access_token();
        if (!empty($auth['error'])) {
            return ['success' => false, 'error' => $auth['error']];
        }
        $accessToken = $auth['access_token'];
    }

    $cfg = sk_fcm_config();
    $projectId = trim((string)($cfg['project_id'] ?? ''));
    $url = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode($projectId) . '/messages:send';
    $payload = json_encode(sk_fcm_build_message($token, $notif));

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 30,
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $err !== '') {
        return ['success' => false, 'error' => 'FCM curl error: ' . $err, 'http_code' => $code];
    }
    $json = json_decode($raw, true);
    if ($code >= 200 && $code < 300) {
        return ['success' => true, 'response' => $json, 'http_code' => $code];
    }
    $msg = $json['error']['message'] ?? $raw;
    return ['success' => false, 'error' => (string)$msg, 'response' => $json, 'http_code' => $code];
}

/**
 * Send to many tokens; logs each result.
 *
 * @param array<int,array{token:string,user_id?:int|null}> $targets
 * @return array{ok:int,fail:int,error?:string,logs:array}
 */
function sk_fcm_send_many(array $notif, array $targets): array {
    sk_fcm_ensure_schema();
    $auth = sk_fcm_access_token();
    if (!empty($auth['error'])) {
        return ['ok' => 0, 'fail' => 0, 'error' => $auth['error'], 'logs' => []];
    }
    $access = $auth['access_token'];
    $CI =& get_instance();
    $ok = 0;
    $fail = 0;
    $logs = [];
    $nid = (int)($notif['id'] ?? 0);

    foreach ($targets as $row) {
        $tok = is_array($row) ? (string)($row['token'] ?? '') : (string)$row;
        $uid = is_array($row) ? ($row['user_id'] ?? null) : null;
        $res = sk_fcm_send_token($tok, $notif, $access);
        $success = !empty($res['success']);
        if ($success) {
            $ok++;
        } else {
            $fail++;
        }
        $logRow = [
            'notification_id' => $nid,
            'user_id'         => $uid ? (int)$uid : null,
            'token'           => $tok,
            'success'         => $success ? 1 : 0,
            'response'        => json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at'      => date('Y-m-d H:i:s'),
        ];
        if ($CI->db->table_exists('sk_notification_logs')) {
            $CI->db->insert('sk_notification_logs', $logRow);
        }
        $logs[] = $logRow;
    }

    return ['ok' => $ok, 'fail' => $fail, 'logs' => $logs];
}
