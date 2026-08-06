<?php

return [
    /*
    | Days after delivery before a non-returned CSN is marked missing.
    | Missing CSNs hold commission until the original signed CSN is returned.
    */
    'missing_csn_days' => (int) env('OG_MISSING_CSN_DAYS', 7),

    /*
    | Default commission rate (% of CSN freight) when no matching rule applies.
    */
    'default_commission_rate' => (float) env('OG_DEFAULT_COMMISSION_RATE', 10),

    'autocount' => [
        'enabled' => (bool) env('OG_AUTOCOUNT_ENABLED', true),
        'mode' => env('OG_AUTOCOUNT_MODE', 'simulate'), // simulate|live
        'simulate_failure_rate' => (float) env('OG_AUTOCOUNT_FAIL_RATE', 0),
    ],

    'myinvois' => [
        'enabled' => (bool) env('OG_MYINVOIS_ENABLED', true),
        'mode' => env('OG_MYINVOIS_MODE', 'simulate'),
        'auto_submit' => (bool) env('OG_MYINVOIS_AUTO_SUBMIT', false),
    ],

    'ocr' => [
        'mode' => env('OG_OCR_MODE', 'simulate'),
    ],

    'vehicle' => [
        'expiry_alert_days' => (int) env('OG_VEHICLE_ALERT_DAYS', 30),
    ],
];
