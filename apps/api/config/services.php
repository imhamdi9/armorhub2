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

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        // Only changed to point tests at a local fake of Stripe.
        'api_base' => env('STRIPE_API_BASE', 'https://api.stripe.com'),
    ],

    // Continue with Google, Facebook or Apple: the public ids from each provider's developer console. A button shows only when its id is set.
    'google' => ['client_id' => env('GOOGLE_CLIENT_ID'), 'mobile_client_ids' => env('GOOGLE_MOBILE_CLIENT_IDS')],
    'facebook' => ['app_id' => env('FACEBOOK_APP_ID'), 'app_secret' => env('FACEBOOK_APP_SECRET')],
    // The phone apps sign in with their own ids (Google: the iOS and Android client ids; Apple: the app's bundle id), comma separated.
    'apple' => ['client_id' => env('APPLE_CLIENT_ID'), 'mobile_client_ids' => env('APPLE_MOBILE_CLIENT_IDS')],

    // Push notifications to the mobile apps, through Expo. Off until enabled (the access token is optional: it only adds a layer of security).
    'expo_push' => ['enabled' => env('EXPO_PUSH_ENABLED', false), 'access_token' => env('EXPO_ACCESS_TOKEN')],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
