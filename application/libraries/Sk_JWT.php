<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * JWT helpers: encode/decode + logout blacklist.
 */
class Sk_JWT {

    private $secret;
    private $expire;

    public function __construct() {
        $CI =& get_instance();
        $this->secret = $CI->config->item('jwt_secret') ?? 'ShopKart_JWT_S3cr3t_2024!';
        $this->expire = $CI->config->item('jwt_expire')  ?? 86400;
    }

    public function encode($payload) {
        $header  = $this->base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload['iat'] = time();
        $payload['exp'] = time() + $this->expire;
        $body      = $this->base64_encode(json_encode($payload));
        $signature = $this->base64_encode(hash_hmac('sha256', "$header.$body", $this->secret, true));
        return "$header.$body.$signature";
    }

    public function decode($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $body, $sig] = $parts;
        $expected = $this->base64_encode(hash_hmac('sha256', "$header.$body", $this->secret, true));
        if (!hash_equals($expected, $sig)) return null;

        $payload = json_decode($this->base64_decode($body), true);
        if (!$payload || empty($payload['exp']) || $payload['exp'] < time()) return null;
        if ($this->is_blacklisted($token)) return null;
        return $payload;
    }

    public function get_token_from_request() {
        $CI =& get_instance();
        $auth = $CI->input->get_request_header('Authorization', TRUE);
        // Apache CGI/FastCGI often drops Authorization; fall back to env / alt header
        if (!$auth) {
            $auth = $_SERVER['HTTP_AUTHORIZATION']
                ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
                ?? null;
        }
        if (!$auth) {
            $alt = $CI->input->get_request_header('X-Auth-Token', TRUE);
            if ($alt) {
                return preg_match('/Bearer\s(\S+)/i', $alt, $m) ? $m[1] : trim($alt);
            }
        }
        if (!$auth || !preg_match('/Bearer\s(\S+)/i', $auth, $m)) return null;
        return $m[1];
    }

    public function get_payload_from_request() {
        $token = $this->get_token_from_request();
        if (!$token) return null;
        return $this->decode($token);
    }

    public function get_user_from_request() {
        $payload = $this->get_payload_from_request();
        if (!$payload || empty($payload['user_id'])) {
            return null;
        }

        // Reject tokens for deleted / blocked / missing users (avoids empty cart after account merge)
        $CI =& get_instance();
        $CI->load->model('Sk_User_model');
        $user = $CI->Sk_User_model->get_by_id((int) $payload['user_id']);
        if (!$user || empty($user['status']) || !empty($user['deleted_at'])) {
            return null;
        }

        return $payload;
    }

    /** Store token hash until exp so logout invalidates it. */
    public function blacklist($token, $userId = null): bool {
        $payload = $this->decode_ignore_blacklist($token);
        if (!$payload || empty($payload['exp'])) {
            return false;
        }
        $this->ensure_blacklist_schema();
        $CI =& get_instance();
        $hash = hash('sha256', $token);
        $exists = $CI->db->where('token_hash', $hash)->count_all_results('jwt_blacklist') > 0;
        if ($exists) {
            return true;
        }
        $CI->db->insert('jwt_blacklist', [
            'token_hash' => $hash,
            'user_id'    => $userId ? (int)$userId : (!empty($payload['user_id']) ? (int)$payload['user_id'] : null),
            'expires_at' => (int)$payload['exp'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        // Opportunistic cleanup of expired rows
        if (random_int(1, 20) === 1) {
            $CI->db->where('expires_at <', time())->delete('jwt_blacklist');
        }
        return true;
    }

    public function is_blacklisted($token): bool {
        $CI =& get_instance();
        if (!$CI->db->table_exists('jwt_blacklist')) {
            return false;
        }
        $hash = hash('sha256', $token);
        $row = $CI->db->where('token_hash', $hash)
            ->where('expires_at >=', time())
            ->limit(1)
            ->get('jwt_blacklist')->row_array();
        return !empty($row);
    }

    private function decode_ignore_blacklist($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        [$header, $body, $sig] = $parts;
        $expected = $this->base64_encode(hash_hmac('sha256', "$header.$body", $this->secret, true));
        if (!hash_equals($expected, $sig)) return null;
        $payload = json_decode($this->base64_decode($body), true);
        if (!$payload || empty($payload['exp'])) return null;
        return $payload;
    }

    public function ensure_blacklist_schema(): void {
        static $done = false;
        if ($done) return;
        $done = true;
        $CI =& get_instance();
        if ($CI->db->table_exists('jwt_blacklist')) {
            return;
        }
        $CI->db->query("CREATE TABLE IF NOT EXISTS `jwt_blacklist` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `token_hash` CHAR(64) NOT NULL,
            `user_id` INT UNSIGNED NULL DEFAULT NULL,
            `expires_at` INT UNSIGNED NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_jwt_hash` (`token_hash`),
            KEY `idx_jwt_expires` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function base64_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64_decode($data) {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
}
