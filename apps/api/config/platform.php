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

    // Radius in km used when looking for nearby partners with no same-city match.
    'partner_search_radius_km' => 300,
];
