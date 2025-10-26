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
        // Only keep the base API URL here
        'api_url' => env('PAYTR_API_URL', 'https://www.paytr.com'),
    ],

    'highlevel' => [
        'client_id' => env('HIGHLEVEL_CLIENT_ID'),
        'client_secret' => env('HIGHLEVEL_CLIENT_SECRET'),
        'sso_key' => env('HIGHLEVEL_SSO_KEY'),
        'redirect_uri' => env('HIGHLEVEL_REDIRECT_URI'),
        'webhook_url' => env('HIGHLEVEL_WEBHOOK_URL'),
        'api_url' => env('HIGHLEVEL_API_URL', 'https://backend.leadconnectorhq.com'),
        'oauth_url' => env('HIGHLEVEL_OAUTH_URL', 'https://marketplace.gohighlevel.com'),
    ],

];
