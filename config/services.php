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

    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'razorpay' => [
        'key' => env('RAZORPAY_KEY_ID'),
        'secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],

    // Exchange-rate feed (ECB reference rates). No API key required.
    'frankfurter' => [
        'url' => env('FRANKFURTER_URL', 'https://api.frankfurter.app'),
    ],

    // Bank Statement Parser & Tally Automation API — POST {url}/parse.
    // See HttpStatementExtractionProvider. Leave STATEMENT_EXTRACTION_API_URL
    // unset to have statement uploads fail with a clear "not configured" error.
    // 'key' is optional — this API doesn't require auth today.
    'statement_extraction' => [
        'url' => env('STATEMENT_EXTRACTION_API_URL'),
        'key' => env('STATEMENT_EXTRACTION_API_KEY'),
    ],

    // Real-Time Pusher Cloud WebSockets
    'pusher' => [
        'app_id'     => env('PUSHER_APP_ID'),
        'app_key'    => env('PUSHER_APP_KEY'),
        'app_secret' => env('PUSHER_APP_SECRET'),
        'cluster'    => env('PUSHER_APP_CLUSTER', 'ap2'),
    ],

];
