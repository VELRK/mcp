<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Meta Business Agent Platform configuration.
 * Secrets come from .env / server environment — never hardcode tokens here.
 */
if (!function_exists('sk_env')) {
    require_once APPPATH . 'helpers/sk_dotenv_helper.php';
    sk_dotenv_load();
}

$config['meta_ba_enabled'] = sk_env_bool('META_BA_ENABLED', false);
$config['meta_ba_api_base'] = rtrim(sk_env('META_BA_API_BASE', 'https://api.facebook.com'), '/');
$config['meta_ba_api_version'] = sk_env('META_BA_API_VERSION', '2.0.0') ?: '2.0.0';
$config['meta_ba_system_user_token'] = sk_env('META_BA_SYSTEM_USER_TOKEN', '');
$config['meta_ba_connector_api_key'] = sk_env('META_BA_CONNECTOR_API_KEY', '');
$config['meta_ba_connector_base_url'] = rtrim(sk_env('META_BA_CONNECTOR_BASE_URL', ''), '/');
$config['meta_ba_default_audience'] = strtoupper(sk_env('META_BA_DEFAULT_AUDIENCE', 'ALLOWLISTED_ONLY')) ?: 'ALLOWLISTED_ONLY';
$config['meta_ba_handoff_message'] = sk_env(
    'META_BA_HANDOFF_MESSAGE',
    'A human teammate will continue this chat shortly.'
);

if (!in_array($config['meta_ba_default_audience'], ['ALLOWLISTED_ONLY', 'EVERYONE'], true)) {
    $config['meta_ba_default_audience'] = 'ALLOWLISTED_ONLY';
}
