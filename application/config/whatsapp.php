<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| WhatsApp / Syncr (waadmin) Configuration
|--------------------------------------------------------------------------
| Order status + OTP use Syncr send-message API:
|   POST https://waadmin.syncr.in/v1/message/send-message?token=...
|
| Token can also be set in Admin → Settings (askeva_api_token — same field).
| Create templates from: database/whatsapp_order_templates.txt
| Template names below must match Meta/Syncr exactly.
*/

$envProvider = getenv('WHATSAPP_PROVIDER') ?: getenv('SYNCR_PROVIDER') ?: 'syncr';
$envApiKey = getenv('WHATSAPP_API_KEY') ?: getenv('SYNCR_API_KEY') ?: '';
$envApiUrl = getenv('WHATSAPP_API_URL') ?: getenv('SYNCR_API_URL') ?: 'https://waadmin.syncr.in/v1/message/send-message';
$envFromNumber = getenv('WHATSAPP_FROM_NUMBER') ?: '';
$envDevMode = getenv('WHATSAPP_DEVELOPMENT_MODE') ?: getenv('SYNCR_DEVELOPMENT_MODE') ?: '0';
$envLang = getenv('WHATSAPP_TEMPLATE_LANG') ?: 'en';
$envTestPhone = getenv('WHATSAPP_TEST_FORCE_PHONE') ?: '';

$config['whatsapp']['provider'] = $envProvider;
$config['whatsapp']['api_key']  = $envApiKey;
$config['whatsapp']['api_url']  = $envApiUrl;
$config['whatsapp']['from_number'] = $envFromNumber;
$config['whatsapp']['development_mode'] = filter_var($envDevMode, FILTER_VALIDATE_BOOLEAN);
$config['whatsapp']['template_lang'] = $envLang;

/*
| Parameter style for template body vars:
|   positional — {{1}}, {{2}} (Syncr/Meta classic; preferred)
|   named      — {{Customername}}, {{OrderName}} + parameter_name in API
|   auto       — try positional, then named if Syncr rejects
*/
$config['whatsapp']['template_param_mode'] = 'auto';

/* Used only when mode is named (or auto fallback to named). */
$config['whatsapp']['template_param_names'] = [
    'customer' => 'Customername',
    'order'    => 'OrderName',
];

/*
| Per-status UTILITY templates (Customername + OrderName).
*/
$config['whatsapp']['status_templates'] = [
    'pending'    => 'order_received',
    'confirmed'  => 'order_confirmed',
    'processing' => 'order_ready_pickup',
    'shipped'    => 'order_shipped',
    'delivered'  => 'order_delivered',
    'cancelled'  => 'order_cancelled',
    'returned'   => 'order_returned',
];

/* Optional single fallback. Leave empty if unused. */
$config['whatsapp']['fallback_template'] = '';

/*
| TESTING: force every WhatsApp send to this number (digits only, country code included).
| Set empty string '' to send to the real customer phone again.
*/
$config['whatsapp']['test_force_phone'] = $envTestPhone;
