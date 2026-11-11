<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Comma-separated list in production, e.g. https://app.example.com
    // Explicit origins only (never "*"): the browser session cookie is sent with credentials.
    'allowed_origins' => array_values(array_unique(array_filter(array_map('trim', array_merge(
        [env('WEB_URL', 'http://localhost:3000')],
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
    )), fn ($o) => $o !== '' && $o !== '*'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
