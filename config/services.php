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

    'paytr' => [
        // PayTR credentials are stored in database per location (hl_accounts table)
        // These config values are only used as fallback for testing
        'merchant_id' => env('PAYTR_MERCHANT_ID', 'test_merchant'),
        'merchant_key' => env('PAYTR_MERCHANT_KEY', 'test_key'),
        'merchant_salt' => env('PAYTR_MERCHANT_SALT', 'test_salt'),
        'api_url' => env('PAYTR_API_URL', 'https://www.paytr.com'),
        'test_mode' => env('PAYTR_TEST_MODE', true),
    ],

    'highlevel' => [
        'client_id' => env('HIGHLEVEL_CLIENT_ID'),
        'client_secret' => env('HIGHLEVEL_CLIENT_SECRET'),
        'sso_key' => env('HIGHLEVEL_SSO_KEY'),
        'redirect_uri' => env('HIGHLEVEL_REDIRECT_URI'),
        'webhook_url' => env('HIGHLEVEL_WEBHOOK_URL'),
        'api_url' => env('HIGHLEVEL_API_URL', 'https://backend.leadconnectorhq.com'),
        'oauth_url' => env('HIGHLEVEL_OAUTH_URL', 'https://services.leadconnectorhq.com'),

        // Third-party payment provider configuration
        // PayTR is registered as a custom third-party provider (not white-label)
        'provider' => [
            'name' => env('HIGHLEVEL_PROVIDER_NAME', 'PayTR'),
            'description' => env('HIGHLEVEL_PROVIDER_DESCRIPTION', 'PayTR Payment Gateway for Turkey'),
            'image_url' => env('HIGHLEVEL_PROVIDER_IMAGE_URL', null),
            'query_url' => env('HIGHLEVEL_PROVIDER_QUERY_URL', null), // Backend verification endpoint
            'payments_url' => env('HIGHLEVEL_PROVIDER_PAYMENTS_URL', null), // Iframe payment page
            'supports_subscription' => env('HIGHLEVEL_PROVIDER_SUPPORTS_SUBSCRIPTION', true), // Supports recurring payments
        ],
    ],

];
