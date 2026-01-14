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

    'scraper' => [
        'url' => env('SCRAPER_URL', 'http://localhost:3000'),
        'timeout' => env('SCRAPER_TIMEOUT', 120),
        'discovery_batch_size' => env('SCRAPER_DISCOVERY_BATCH_SIZE', 5),
    ],

    'zenrows' => [
        'api_key' => env('ZENROWS_API_KEY'),
        'timeout' => env('ZENROWS_TIMEOUT', 90),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
        'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 4096),
        'timeout' => env('ANTHROPIC_TIMEOUT', 60),
    ],

    'enrichment' => [
        'batch_size' => env('AI_ENRICHMENT_BATCH_SIZE', 50),
        'enabled' => env('AI_ENRICHMENT_ENABLED', true),
    ],

    'dedup' => [
        'batch_size' => env('DEDUP_BATCH_SIZE', 100),
        'distance_threshold_meters' => env('DEDUP_DISTANCE_METERS', 1000),
        'auto_match_threshold' => 0.80,
        'review_threshold' => 0.55,
        'enabled' => env('DEDUP_ENABLED', true),
    ],

    'google' => [
        'maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
        'geocoding_enabled' => env('GOOGLE_GEOCODING_ENABLED', true),
    ],

];
