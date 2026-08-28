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
        'mode' => env('OG_OCR_MODE', 'document'),
        'tesseract_path' => env('OG_TESSERACT_PATH', 'tesseract'),
        'tesseract_lang' => env('OG_TESSERACT_LANG', 'eng'),
    ],

    'vehicle' => [
        'expiry_alert_days' => (int) env('OG_VEHICLE_ALERT_DAYS', 30),
    ],

    'invoice' => [
        'disclaimer' => 'Should you find any discrepancy in this invoice, please notify us in writing within 7 days, otherwise this invoice is deemed correct. All cheques should be crossed and made payable to the company stated above.',
        'bank_accounts' => array_values(array_filter([
            env('OG_INVOICE_BANK_1') ? [
                'bank' => env('OG_INVOICE_BANK_1_NAME', 'CIMB Bank Berhad'),
                'account' => env('OG_INVOICE_BANK_1'),
            ] : null,
            env('OG_INVOICE_BANK_2') ? [
                'bank' => env('OG_INVOICE_BANK_2_NAME', 'Alliance Bank Malaysia Berhad'),
                'account' => env('OG_INVOICE_BANK_2'),
            ] : null,
        ])),
    ],
];
