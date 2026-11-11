<?php

return [

    'currency' => env('PLATFORM_CURRENCY', 'USD'),

    // Share of an order's total taken as commission, in percent.
    'commission_percentage' => (float) env('PLATFORM_COMMISSION_PERCENTAGE', 10),

    // How the commission is split. Must add up to 1.
    'commission_split' => [
        'platform' => 0.35,
        'company' => 0.25,
        'partner' => 0.40,
    ],

    // Which payment gateway charges orders. Only "simulated" ships with the app.
    'payment_gateway' => env('PLATFORM_PAYMENT_GATEWAY', 'simulated'),

    // The simulated gateway charges nobody; refuse it in production unless explicitly allowed.
    'allow_simulated_payments_in_production' => (bool) env('PLATFORM_ALLOW_SIMULATED_PAYMENTS', false),

    // Radius in km used when looking for nearby partners with no same-city match.
    'partner_search_radius_km' => 300,
];
