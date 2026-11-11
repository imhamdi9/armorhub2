<?php

return [

    // Public URL of the web app (used in password-reset emails).
    'web_url' => env('WEB_URL', 'http://localhost:3000'),

    'currency' => env('PLATFORM_CURRENCY', 'USD'),

    // Share of an order's total taken as commission, in percent.
    'commission_percentage' => (float) env('PLATFORM_COMMISSION_PERCENTAGE', 10),

    // How the commission is split. Must add up to 1.
    'commission_split' => [
        'platform' => 0.35,
        'company' => 0.25,
        'partner' => 0.40,
    ],

    // Share the browser session cookie across subdomains (app.example.com <-> api.example.com), e.g. ".example.com".
    'auth_cookie_domain' => env('AUTH_COOKIE_DOMAIN'),

    // Development/tests only: let admin-configured gateway URLs be http:// or private addresses.
    'allow_insecure_provider_urls' => (bool) env('PLATFORM_ALLOW_INSECURE_PROVIDER_URLS', false),

    // Which payment gateway charges orders. Only "simulated" ships with the app.
    'payment_gateway' => env('PLATFORM_PAYMENT_GATEWAY', 'simulated'),

    // The simulated gateway charges nobody; refuse it in production unless explicitly allowed.
    'allow_simulated_payments_in_production' => (bool) env('PLATFORM_ALLOW_SIMULATED_PAYMENTS', false),

    // Refuse passwords found in known breaches (needs outbound access to api.pwnedpasswords.com).
    'check_pwned_passwords' => (bool) env('PLATFORM_CHECK_PWNED_PASSWORDS', env('APP_ENV') === 'production'),

    // Radius in km used when looking for nearby partners with no same-city match.
    'partner_search_radius_km' => 300,
];
