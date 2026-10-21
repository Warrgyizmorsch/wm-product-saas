<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Base Currency
    |--------------------------------------------------------------------------
    |
    | Base currency used for database storage. All amounts stored in database
    | are normalized to this base currency (INR).
    |
    */

    'base' => 'INR',

    /*
    |--------------------------------------------------------------------------
    | Supported Currencies & Exchange Rates (Relative to INR Base)
    |--------------------------------------------------------------------------
    */

    'currencies' => [
        'INR' => [
            'code' => 'INR',
            'name' => 'Indian Rupee',
            'symbol' => '₹',
            'rate' => 1.0000,
            'position' => 'prefix', // ₹100.00
        ],
        'USD' => [
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'rate' => 0.011765, // 1 INR ≈ 0.011765 USD ($1 ≈ ₹85)
            'position' => 'prefix', // $1.18
        ],
        'BGN' => [
            'code' => 'BGN',
            'name' => 'Bulgarian Lev',
            'symbol' => 'лв',
            'rate' => 0.021176, // 1 INR ≈ 0.021176 BGN (1 BGN ≈ 47.22 INR)
            'position' => 'suffix', // 2.12 лв
        ],
    ],
];
