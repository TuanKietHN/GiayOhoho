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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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

    'sepay' => [
        'merchant_id' => env('SEPAY_MERCHANT_ID'),
        'secret_key' => env('SEPAY_SECRET_KEY'),
        'environment' => env('SEPAY_ENV', 'sandbox'),
    ],

    'payos' => [
        'api_url' => env('PAYOS_API_URL', 'https://api-merchant.payos.vn/v2/payment-requests'),
        'client_id' => env('PAYOS_CLIENT_ID'),
        'api_key' => env('PAYOS_API_KEY'),
        'checksum_key' => env('PAYOS_CHECKSUM_KEY'),
        'return_url' => env('PAYOS_RETURN_URL', env('APP_URL').'/payment/payos/return'),
        'cancel_url' => env('PAYOS_CANCEL_URL', env('APP_URL').'/payment/payos/cancel'),
        'expiration_minutes' => (int) env('PAYOS_EXPIRATION_MINUTES', 30),
    ],

    'ghn' => [
        'enabled' => filter_var(env('GHN_ENABLED', false), FILTER_VALIDATE_BOOL),
        'environment' => env('GHN_ENVIRONMENT', 'dev'),
        'base_url' => env('GHN_BASE_URL', 'https://dev-online-gateway.ghn.vn'),
        'token' => env('GHN_TOKEN'),
        'shop_id' => env('GHN_SHOP_ID'),
        'client_id' => env('GHN_CLIENT_ID'),
        'quote_ttl_minutes' => (int) env('GHN_QUOTE_TTL_MINUTES', 30),
        'print_base_url' => env('GHN_PRINT_BASE_URL', 'https://dev-online-gateway.ghn.vn/a5/public-api/printA5'),
        'auto_create_order' => filter_var(env('GHN_AUTO_CREATE_ORDER', false), FILTER_VALIDATE_BOOL),
        'from' => [
            'name' => env('GHN_FROM_NAME', env('APP_NAME', 'OhGiay')),
            'phone' => env('GHN_FROM_PHONE'),
            'address' => env('GHN_FROM_ADDRESS'),
            'ward_name' => env('GHN_FROM_WARD_NAME'),
            'district_name' => env('GHN_FROM_DISTRICT_NAME'),
            'province_name' => env('GHN_FROM_PROVINCE_NAME'),
            'district_id' => env('GHN_FROM_DISTRICT_ID'),
            'ward_code' => env('GHN_FROM_WARD_CODE'),
        ],
        'return' => [
            'name' => env('GHN_RETURN_NAME'),
            'phone' => env('GHN_RETURN_PHONE'),
            'address' => env('GHN_RETURN_ADDRESS'),
            'district_id' => env('GHN_RETURN_DISTRICT_ID'),
            'ward_code' => env('GHN_RETURN_WARD_CODE'),
        ],
        'defaults' => [
            'service_type_id' => (int) env('GHN_DEFAULT_SERVICE_TYPE_ID', 2),
            'payment_type_id' => (int) env('GHN_DEFAULT_PAYMENT_TYPE_ID', 2),
            'required_note' => env('GHN_DEFAULT_REQUIRED_NOTE', 'KHONGCHOXEMHANG'),
            'cod_failed_amount' => (int) env('GHN_DEFAULT_COD_FAILED_AMOUNT', 0),
            'length_cm' => (int) env('GHN_DEFAULT_LENGTH_CM', 32),
            'width_cm' => (int) env('GHN_DEFAULT_WIDTH_CM', 22),
            'height_cm' => (int) env('GHN_DEFAULT_HEIGHT_CM', 12),
            'weight_grams' => (int) env('GHN_DEFAULT_WEIGHT_GRAMS', 1000),
            'insurance_value' => (int) env('GHN_DEFAULT_INSURANCE_VALUE', 0),
            'fallback_phone' => env('GHN_FALLBACK_PHONE', '0900000000'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'tokeninfo_url' => env('GOOGLE_TOKENINFO_URL', 'https://oauth2.googleapis.com/tokeninfo'),
    ],

    'auth' => [
        'refresh' => [
            'lifetime_seconds' => (int) env('REFRESH_TOKEN_TTL_SECONDS', 60 * 60 * 24 * 30),
            'cookie_name' => env('REFRESH_TOKEN_COOKIE', 'refresh_token'),
            'csrf_cookie_name' => env('REFRESH_TOKEN_CSRF_COOKIE', 'csrf_refresh_token'),
            'cookie_secure' => filter_var(env('REFRESH_TOKEN_COOKIE_SECURE', false), FILTER_VALIDATE_BOOL),
            'cookie_same_site' => env('REFRESH_TOKEN_COOKIE_SAME_SITE', 'lax'),
            'cookie_domain' => env('REFRESH_TOKEN_COOKIE_DOMAIN'),
            'cookie_path' => env('REFRESH_TOKEN_COOKIE_PATH', '/'),
        ],
    ],
];
