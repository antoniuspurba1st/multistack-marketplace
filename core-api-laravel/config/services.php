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

    'seller' => [
        'url' => env('SELLER_SERVICE_URL'),
        'timeout' => env('SELLER_SERVICE_TIMEOUT', 3),
        'retry_times' => env('SELLER_SERVICE_RETRY_TIMES', 3),
        'retry_backoff_ms' => env('SELLER_SERVICE_RETRY_BACKOFF_MS', 100),
        'circuit_breaker_threshold' => env('SELLER_SERVICE_CIRCUIT_THRESHOLD', 3),
        'circuit_breaker_seconds' => env('SELLER_SERVICE_CIRCUIT_SECONDS', 30),
        'product_cache_ttl_seconds' => env('SELLER_SERVICE_PRODUCT_CACHE_TTL', 300),
    ],

    'recommendation' => [
        'url' => env('RECOMMENDATION_SERVICE_URL', 'http://localhost:8002'),
    ],

];
