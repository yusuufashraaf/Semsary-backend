<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
        'secure' => true,
    ],
     'whatsapp' => [
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'phone_id' => env('WHATSAPP_PHONE_ID'),
        'template_name' => env('WHATSAPP_TEMPLATE_NAME'),
     ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env("GOOGLE_CALLBACK_URL"),
    ],
    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY'),
    ],

'paymob' => [
    'base_url'       => env('PAYMOB_BASE_URL', 'https://accept.paymob.com'),
    'api_key'        => env('PAYMOB_API_KEY'),
    'integration_id' => array_map('trim', explode(',', env('PAYMOB_INTEGRATION_ID', ''))),
    'hmac_secret'    => env('PAYMOB_HMAC_SECRET'),
],


'paypal' => [
    'client_id' => env('PAYPAL_CLIENT_ID'),
    'client_secret' => env('PAYPAL_CLIENT_SECRET'),
    'base_url' => env('PAYPAL_BASE_URL', 'https://api.sandbox.paypal.com'), 
],
];