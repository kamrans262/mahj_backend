<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have a
    | conventional location for this type of information.
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

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET_KEY', env('STRIPE_SECRET')),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'google_maps' => [
        'server_key' => env('GOOGLE_MAPS_SERVER_KEY'),
        'geocoding_url' => env(
            'GOOGLE_MAPS_GEOCODING_URL',
            'https://maps.googleapis.com/maps/api/geocode/json'
        ),
        'places_text_search_url' => env(
            'GOOGLE_MAPS_PLACES_TEXT_SEARCH_URL',
            'https://maps.googleapis.com/maps/api/place/textsearch/json'
        ),
    ],

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'service_account_path' => env('FIREBASE_SERVICE_ACCOUNT_PATH'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
