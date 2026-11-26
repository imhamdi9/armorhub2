<?php

/*
 | Market packs. A market is a country the platform operates in. Everything that differs between
 | countries lives here as data, so opening a new country means adding an entry (and, if it needs one,
 | a payment driver), not changing code.
 |
 |  vat.rate        percentage; 0 when the country has no VAT
 |  vat.inclusive   true when shelf prices already include VAT (the norm for consumers in KSA and Egypt)
 |  phone.*         dialling code, the national number pattern (digits only, no leading 0) and an example
 |  payments        providers worth enabling for this country (drivers are configured under Admin > Payments)
 */
return [

    // The market used for tenants that do not choose one. Null keeps the single global currency
    // (platform.currency) for installations that do not need markets.
    'default' => env('PLATFORM_DEFAULT_MARKET'),

    'markets' => [

        'SA' => [
            'name' => ['en' => 'Saudi Arabia', 'ar' => 'المملكة العربية السعودية'],
            'currency' => 'SAR',
            'currency_symbol' => ['en' => 'SAR', 'ar' => 'ر.س'],
            'decimals' => 2,
            'locale' => 'ar-SA',
            'languages' => ['ar', 'en'],
            'timezone' => 'Asia/Riyadh',
            'weekend' => ['friday', 'saturday'],
            'vat' => ['rate' => 15.0, 'inclusive' => true, 'label' => ['en' => 'VAT', 'ar' => 'ضريبة القيمة المضافة'],
                'id_label' => ['en' => 'VAT registration number', 'ar' => 'الرقم الضريبي'], 'id_pattern' => '/^3\d{13}3$/'],
            'phone' => ['dial' => '966', 'pattern' => '/^5\d{8}$/', 'example' => '5X XXX XXXX'],
            'cities' => ['Riyadh', 'Jeddah', 'Makkah', 'Madinah', 'Dammam', 'Khobar', 'Dhahran', 'Taif', 'Tabuk', 'Abha', 'Buraydah', 'Hail', 'Jazan', 'Najran'],
            'payments' => ['moyasar', 'tap', 'stripe'],
            'e_invoicing' => 'zatca',
        ],

        'EG' => [
            'name' => ['en' => 'Egypt', 'ar' => 'مصر'],
            'currency' => 'EGP',
            'currency_symbol' => ['en' => 'EGP', 'ar' => 'ج.م'],
            'decimals' => 2,
            'locale' => 'ar-EG',
            'languages' => ['ar', 'en'],
            'timezone' => 'Africa/Cairo',
            'weekend' => ['friday', 'saturday'],
            'vat' => ['rate' => 14.0, 'inclusive' => true, 'label' => ['en' => 'VAT', 'ar' => 'ضريبة القيمة المضافة'],
                'id_label' => ['en' => 'Tax registration number', 'ar' => 'رقم التسجيل الضريبي'], 'id_pattern' => '/^\d{9}$/'],
            'phone' => ['dial' => '20', 'pattern' => '/^1[0125]\d{8}$/', 'example' => '1X XXXX XXXX'],
            'cities' => ['Cairo', 'Giza', 'Alexandria', 'New Cairo', '6th of October', 'Sheikh Zayed', 'Mansoura', 'Tanta', 'Port Said', 'Suez', 'Hurghada', 'Sharm El-Sheikh', 'Luxor', 'Aswan'],
            'payments' => ['paymob', 'fawry', 'stripe'],
            'e_invoicing' => 'eta',
        ],

    ],
];
