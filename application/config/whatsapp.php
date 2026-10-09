<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| WhatsApp (Meta Cloud API)
|--------------------------------------------------------------------------
| Order status, inbox, campaigns, and OTP go through the shop's Meta
| WhatsApp account (Graph API). Credentials live on the vendor WhatsApp
| account and Admin → Facebook / WhatsApp login — not in this file.
*/

$envLang = getenv('WHATSAPP_TEMPLATE_LANG') ?: 'en';
$envTestPhone = getenv('WHATSAPP_TEST_FORCE_PHONE') ?: '';

$config['whatsapp']['template_lang'] = $envLang;
$config['whatsapp']['template_param_mode'] = 'positional';
$config['whatsapp']['template_param_names'] = [
    'customer' => 'Customername',
    'order'    => 'OrderName',
];

/*
| Optional OTP template name on the shop's Meta account.
| Body {{1}} is the code. Create and approve it under Admin → Templates.
*/
$config['whatsapp']['otp_template'] = getenv('WHATSAPP_OTP_TEMPLATE') ?: 'user_verified';

/*
| TESTING: force every WhatsApp send that uses sk_whatsapp_destination_phone
| to this number (digits only, country code included).
| Set empty string '' to send to the real customer phone.
*/
$config['whatsapp']['test_force_phone'] = $envTestPhone;
