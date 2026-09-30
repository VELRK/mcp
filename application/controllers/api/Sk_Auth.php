<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/api/Sk_Base_Api.php';

class Sk_Auth extends Sk_Base_Api {

    public function register() {
        $data = $this->body();
        $name     = trim($data['name'] ?? '');
        $email    = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';
        $phoneRaw = trim($data['phone'] ?? '');

        if (!$name || !$email || !$password || $phoneRaw === '') {
            return $this->error('Name, email, phone and password are required.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Invalid email address.');
        }
        if (strlen($password) < 6) {
            return $this->error('Password must be at least 6 characters.');
        }
        if ($this->Sk_User_model->email_exists($email)) {
            return $this->error('Email already registered.');
        }

        $this->load->helper('sk_isms');
        $settings = $this->get_settings();
        $phone = sk_isms_normalize_phone($phoneRaw, $settings);
        if ($phone === '') {
            return $this->error(sk_isms_phone_error());
        }
        if ($this->Sk_User_model->phone_exists($phone)) {
            return $this->error('Phone number already registered.');
        }

        $user_id = $this->Sk_User_model->create([
            'name'  => $name,
            'email' => $email,
            'password' => $password,
            'phone' => $phone,
        ]);

        // Save address if provided
        $address = $data['address'] ?? null;
        if ($address && !empty($address['line1'])) {
            $this->Sk_User_model->ensure_address_schema();
            $this->Sk_User_model->save_address([
                'user_id'      => $user_id,
                'label'        => 'Home',
                'full_name'    => $name,
                'phone'        => $phone,
                'line1'        => $address['line1'],
                'line2'        => $address['line2'] ?? '',
                'city'         => $address['city'] ?? '',
                'state'        => $address['state'] ?? '',
                'pincode'      => $address['pincode'] ?? '',
                'country'      => 'India',
                'address_type' => 'shipping',
                'is_default'   => 1,
            ]);
        }

        $user = $this->Sk_User_model->get_by_id($user_id);
        $token = $this->sk_jwt->encode(['user_id' => $user_id, 'email' => $email]);

        $this->success([
            'token' => $token,
            'user'  => $this->_safe_user($user),
        ], 'Registration successful.', 201);
    }

    /** Public: check if email / phone already registered (register form). */
    public function check_availability() {
        $data = array_merge($this->input->get() ?: [], $this->body() ?: []);
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $phoneRaw = trim((string)($data['phone'] ?? ''));

        $out = [
            'email' => null,
            'phone' => null,
        ];

        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out['email'] = ['available' => false, 'message' => 'Invalid email address.'];
            } elseif ($this->Sk_User_model->email_exists($email)) {
                $out['email'] = ['available' => false, 'message' => 'Email already registered.'];
            } else {
                $out['email'] = ['available' => true, 'message' => 'Email is available.'];
            }
        }

        if ($phoneRaw !== '') {
            $this->load->helper('sk_isms');
            $normalized = sk_isms_normalize_phone($phoneRaw, $this->get_settings());
            if ($normalized === '') {
                $out['phone'] = ['available' => false, 'message' => sk_isms_phone_error()];
            } elseif ($this->Sk_User_model->phone_exists($normalized)) {
                $out['phone'] = ['available' => false, 'message' => 'Phone number already registered.'];
            } else {
                $out['phone'] = ['available' => true, 'message' => 'Phone number is available.'];
            }
        }

        if ($out['email'] === null && $out['phone'] === null) {
            return $this->error('Provide email and/or phone to check.');
        }

        $ok = true;
        if (is_array($out['email']) && empty($out['email']['available'])) {
            $ok = false;
        }
        if (is_array($out['phone']) && empty($out['phone']['available'])) {
            $ok = false;
        }

        $this->success($out, $ok ? 'Available.' : 'Already registered.', $ok ? 200 : 409);
    }

    public function login() {
        $data = $this->body();
        $email    = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';

        if (!$email || !$password) return $this->error('Email and password required.');

        $user = $this->Sk_User_model->get_by_email($email);
        if (!$user || !$this->Sk_User_model->verify_password($password, $user['password'])) {
            return $this->error('Invalid email or password.', 401);
        }
        if (!empty($user['deleted_at'])) {
            return $this->error('This account has been deleted.', 403);
        }
        if (!$user['status']) {
            return $this->error('Your account has been blocked.', 403);
        }

        $this->Sk_User_model->update_last_login($user['id']);
        $token = $this->sk_jwt->encode(['user_id' => $user['id'], 'email' => $user['email']]);

        $this->success([
            'token' => $token,
            'user'  => $this->_safe_user($user),
        ], 'Login successful.');
    }

    public function forgot_password() {
        $data  = $this->body();
        $email = trim($data['email'] ?? '');

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Valid email address is required.');
        }
        if (strpos($email, '@shopkart.app') !== false) {
            return $this->error('This account uses mobile login. Please sign in with OTP.');
        }

        $user = $this->Sk_User_model->get_by_email($email);
        if (!$user) {
            return $this->error('No account found with this email.');
        }
        if (!$user['status']) {
            return $this->error('Your account has been blocked.', 403);
        }

        $code = $this->Sk_User_model->set_reset_code($email);
        $this->load->helper('sk_mailer');
        $settings = $this->get_settings();
        $sent = sk_mail_password_reset_code($user, $code, $settings);

        if (!$sent) {
            if (ENVIRONMENT !== 'production') {
                return $this->success(
                    ['dev_code' => $code],
                    'SMTP not configured. Use verification code: ' . $code
                );
            }
            return $this->error('Unable to send verification email. Please try again later.', 500);
        }

        $this->success([], 'Verification code sent to your email.');
    }

    public function verify_reset_code() {
        $data  = $this->body();
        $email = trim($data['email'] ?? '');
        $code  = trim($data['code'] ?? '');

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Valid email address is required.');
        }
        if (!$code || !preg_match('/^\d{6}$/', $code)) {
            return $this->error('Enter the 6-digit verification code from your email.');
        }

        $token = $this->Sk_User_model->verify_reset_code($email, $code);
        if (!$token) {
            return $this->error('Invalid or expired verification code.', 401);
        }

        $this->success(['reset_token' => $token], 'Email verified. You can now set a new password.');
    }

    public function reset_password() {
        $data  = $this->body();
        $email = trim($data['email'] ?? '');
        $token = trim($data['reset_token'] ?? '');
        $password = $data['password'] ?? '';
        $confirm  = $data['password_confirmation'] ?? ($data['confirm_password'] ?? '');

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Valid email address is required.');
        }
        if (!$token) {
            return $this->error('Reset session expired. Please verify your email again.', 401);
        }
        if (!$password || strlen($password) < 6) {
            return $this->error('Password must be at least 6 characters.');
        }
        if ($password !== $confirm) {
            return $this->error('Passwords do not match.');
        }

        if (!$this->Sk_User_model->reset_password_with_token($email, $token, $password)) {
            return $this->error('Reset session expired or invalid. Please start again.', 401);
        }

        $this->success([], 'Password updated successfully. You can now sign in.');
    }

    public function otp_request() {
        $this->load->helper('sk_isms');
        sk_isms_ensure_schema();

        $data  = $this->body();
        $phone = trim($data['phone'] ?? '');
        $settings = $this->get_settings();
        $this->load->library('isms', $settings);
        $normalized = $this->isms->normalize_phone($phone);

        if (!$normalized || !$this->isms->parse_phone($phone)) {
            return $this->error('Enter a 10-digit Indian mobile number (e.g. 9876543210).');
        }

        if (sk_isms_is_test_phone($settings, $normalized)) {
            $normalized = sk_isms_canonical_test_phone($settings);
            $test = sk_isms_get_test_config($settings);
            $payload = ['phone' => $normalized, 'test_mode' => true];
            $msg = 'OTP sent to +' . $normalized . '.';
            if (ENVIRONMENT !== 'production') {
                $payload['test_otp'] = $test['otp'];
                $payload['dev_hint'] = 'Developer test: use OTP ' . $test['otp'];
                $msg .= ' Dev OTP: ' . $test['otp'];
            }
            return $this->success($payload, $msg);
        }

        if (!sk_isms_is_configured($settings)) {
            if (ENVIRONMENT !== 'production') {
                return $this->_isms_dev_test_response($normalized, $settings, 'iSMS not configured.');
            }
            return $this->error('SMS login is not available. Please contact support.', 503);
        }

        $result = $this->isms->request_otp($phone);
        if (!$result['success']) {
            if (ENVIRONMENT !== 'production' && $this->_isms_allow_dev_fallback($result['message'])) {
                return $this->_isms_dev_test_response($normalized, $settings, $result['message']);
            }
            $msg = $result['message'];
            if (ENVIRONMENT === 'production') {
                $hint = sk_isms_auth_failure_hint($result);
                if ($hint !== '') {
                    $msg .= ' ' . $hint;
                }
            }
            return $this->error($msg, 502);
        }

        sk_isms_save_session(
            $normalized,
            $result['sms_id'] ?? '',
            password_hash((string) ($result['otp'] ?? ''), PASSWORD_DEFAULT),
            $this->isms->get_otp_interval()
        );

        $this->success(['phone' => $normalized], $result['message']);
    }

    public function otp_verify() {
        $this->load->helper('sk_isms');
        sk_isms_ensure_schema();

        $data  = $this->body();
        $phone = trim($data['phone'] ?? '');
        $otp   = trim($data['otp']   ?? '');

        if (!$phone || !$otp) return $this->error('Phone and OTP required.');

        $settings = $this->get_settings();
        $this->load->library('isms', $settings);
        $normalized = $this->isms->normalize_phone($phone);
        if (!$normalized || !$this->isms->parse_phone($phone)) {
            return $this->error('Valid phone number required.');
        }

        if (sk_isms_is_test_phone($settings, $normalized)) {
            $test = sk_isms_get_test_config($settings);
            $validCodes = array_unique([$test['otp'], '1234', '123']);
            if (!in_array($otp, $validCodes, true)) {
                return $this->error('Invalid OTP. Please try again.', 401);
            }
            // Always use one canonical form so app/web do not create duplicate users/carts
            $normalized = sk_isms_canonical_test_phone($settings);
        } else {
            if (!sk_isms_verify_session_otp($normalized, $otp)) {
                return $this->error('Invalid or expired OTP. Please try again.', 401);
            }
            sk_isms_clear_session($normalized);
        }

        $user = $this->_find_user_by_phone($normalized, $settings);
        $nameInput = trim((string) ($data['name'] ?? ''));
        if (mb_strlen($nameInput) > 100) {
            $nameInput = mb_substr($nameInput, 0, 100);
        }
        $isNew = false;

        if (!$user) {
            // New user — auto-register with phone only (Flipkart/Amazon style OTP login).
            // Real name + optional email are collected on checkout / profile — never SER001 / User ####.
            $isNew = true;
            $this->Sk_User_model->ensure_otp_user_schema();
            $displayName = $nameInput !== '' ? $nameInput : '';
            $user_id = $this->Sk_User_model->create([
                'name'     => $displayName,
                'email'    => null,
                'password' => bin2hex(random_bytes(16)),
                'phone'    => $normalized,
                'status'   => 1,
            ]);
            $user = $this->Sk_User_model->get_by_id($user_id);
        } elseif ($nameInput !== '' && $this->_is_placeholder_user_name((string) ($user['name'] ?? ''))) {
            // Upgrade placeholder / empty name when the client finally sends a real name
            $this->Sk_User_model->update((int) $user['id'], ['name' => $nameInput]);
            $user = $this->Sk_User_model->get_by_id($user['id']);
        }

        if (!$user['status']) return $this->error('Your account has been blocked.', 403);
        if (!empty($user['deleted_at'])) return $this->error('This account has been deleted.', 403);

        // Keep DB phone on the canonical test number when aliases were used historically
        if (sk_isms_is_test_phone($settings, $normalized)
            && (string) ($user['phone'] ?? '') !== $normalized) {
            $this->Sk_User_model->update((int) $user['id'], ['phone' => $normalized]);
            $user['phone'] = $normalized;
        }

        $this->Sk_User_model->update_last_login($user['id']);
        $token = $this->sk_jwt->encode([
            'user_id' => $user['id'],
            'email'   => (string) ($user['email'] ?? ''),
        ]);

        $email = (string) ($user['email'] ?? '');
        $name  = trim((string) ($user['name'] ?? ''));
        $profileComplete = $name !== ''
            && !$this->_is_placeholder_user_name($name);
        $addrCount = 0;
        if (method_exists($this->Sk_User_model, 'get_addresses')) {
            $addrCount = count($this->Sk_User_model->get_addresses((int) $user['id']));
        }

        $this->success([
            'token'            => $token,
            'user'             => $this->_safe_user($user),
            'is_new'           => $isNew,
            'profile_complete' => $profileComplete,
            'has_address'      => $addrCount > 0,
        ], $isNew ? 'Account created. Login successful.' : 'Login successful.');
    }

    /**
     * POST /shopkart-api/logout
     * Auth required. Blacklists current Bearer token until it expires.
     */
    public function logout() {
        $this->auth_required();
        $token = $this->sk_jwt->get_token_from_request();
        if ($token) {
            $this->sk_jwt->blacklist($token, (int)($this->user['user_id'] ?? 0));
        }
        $this->success([], 'Logged out successfully.');
    }

    /**
     * POST /shopkart-api/user/delete-account
     * Auth required. Soft-deletes account (deleted_at + status=0) and blacklists token.
     * Body optional: { "password": "...", "confirm": true }
     */
    public function delete_account() {
        $this->auth_required();
        $userId = (int)($this->user['user_id'] ?? 0);
        $user = $this->Sk_User_model->get_by_id($userId);
        if (!$user || !empty($user['deleted_at'])) {
            return $this->error('Account not found.', 404);
        }

        $data = $this->body();
        $confirm = !empty($data['confirm']) || !empty($data['delete']) || (($data['confirm'] ?? '') === 'DELETE');
        // Password required for email/password accounts (not phone-OTP accounts without a real email)
        $emailLower = strtolower((string)($user['email'] ?? ''));
        $isOtpAccount = $emailLower === ''
            || strpos($emailLower, 'ph_') === 0
            || strpos($emailLower, '@shopkart.app') !== false
            || strpos($emailLower, '@2deal.app') !== false;
        $password = (string)($data['password'] ?? '');
        if (!$isOtpAccount) {
            if ($password === '') {
                return $this->error('Password is required to delete your account.');
            }
            if (!$this->Sk_User_model->verify_password($password, $user['password'])) {
                return $this->error('Incorrect password.', 401);
            }
        } elseif (!$confirm) {
            return $this->error('Send confirm=true to delete your account.');
        }

        $this->Sk_User_model->ensure_deleted_at_column();
        $this->Sk_User_model->soft_delete($userId);

        $token = $this->sk_jwt->get_token_from_request();
        if ($token) {
            $this->sk_jwt->blacklist($token, $userId);
        }

        $this->success([], 'Your account has been deleted.');
    }

    private function _safe_user($user) {
        unset($user['password'], $user['verify_token'], $user['reset_token'], $user['reset_expires']);
        if (array_key_exists('email', $user) && ($user['email'] === null || $user['email'] === '')) {
            $user['email'] = null;
        }
        return $user;
    }

    /** Auto-generated OTP names like "User 1982", "SER001" — not a real customer name. */
    private function _is_placeholder_user_name(string $name): bool {
        $name = trim($name);
        if ($name === '') {
            return true;
        }
        return (bool) preg_match('/^(User|SER|USR|CUST)\s*\d{1,8}$/i', $name);
    }

    /** Local dev only: accept fixed OTP when iSMS is misconfigured or unreachable. */
    private function _isms_allow_dev_fallback($message) {
        $msg = strtolower((string) $message);
        return strpos($msg, '-1001') !== false
            || strpos($msg, 'authentication failed') !== false
            || strpos($msg, 'not configured') !== false
            || strpos($msg, 'unable to reach isms') !== false;
    }

    private function _isms_dev_test_response($normalized, array $settings, $ismsError = '') {
        $test = sk_isms_get_test_config($settings);
        sk_isms_save_session(
            $normalized,
            'dev',
            password_hash((string) $test['otp'], PASSWORD_DEFAULT),
            max(1, (int) ($settings['isms_otp_interval'] ?? 5))
        );
        $payload = [
            'phone'        => $normalized,
            'test_mode'    => true,
            'dev_fallback' => true,
            'test_otp'     => $test['otp'],
            'dev_hint'     => 'Local dev: iSMS failed — use OTP ' . $test['otp'],
        ];
        $msg = 'OTP ready for +' . $normalized . ' (local dev mode). Use OTP ' . $test['otp'] . '.';
        if ($ismsError !== '') {
            log_message('error', 'iSMS dev fallback: ' . $ismsError);
        }
        return $this->success($payload, $msg);
    }

    private function _find_user_by_phone($normalized, array $settings = null) {
        $candidates = [preg_replace('/\D/', '', (string) $normalized)];
        if ($settings && sk_isms_is_test_phone($settings, $normalized)) {
            $candidates = array_merge($candidates, sk_isms_test_phone_aliases($settings));
        }
        $candidates = array_values(array_unique(array_filter($candidates)));

        $found = [];
        foreach ($candidates as $phone) {
            $user = $this->Sk_User_model->get_by_phone($phone);
            if ($user && empty($user['deleted_at'])) {
                $found[(int) $user['id']] = $user;
            }
        }
        if (!$found) {
            return null;
        }

        // Prefer the oldest account (keeps cart/orders when duplicates exist)
        uasort($found, static function ($a, $b) {
            return ((int) $a['id']) <=> ((int) $b['id']);
        });
        return reset($found) ?: null;
    }
}
