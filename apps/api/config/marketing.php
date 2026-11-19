<?php

/** Where conversions are sent. The addresses can be overridden by environment (a local test server, a regional endpoint). */
return [
    'endpoints' => [
        'meta' => env('MARKETING_META_URL', 'https://graph.facebook.com/v21.0'),
        'tiktok' => env('MARKETING_TIKTOK_URL', 'https://business-api.tiktok.com/open_api/v1.3/event/track/'),
        'google' => env('MARKETING_GOOGLE_URL', 'https://www.google-analytics.com/mp/collect'),
        'tiktok_report' => env('MARKETING_TIKTOK_REPORT_URL', 'https://business-api.tiktok.com/open_api/v1.3/report/integrated/get/'),
    ],
    'timeout' => 8,
    // Attribution keys a browser may send; anything else is dropped.
    'attribution_keys' => ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'fbclid', 'ttclid', 'gclid', 'landing', 'referrer', 'first_seen', 'promo'],
    'context_keys' => ['fbp', 'fbc', 'ttp', 'ga_client_id', 'source_url'],
];
