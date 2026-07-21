<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Base Currency
    |--------------------------------------------------------------------------
    |
    | Base currency used for database storage. All amounts stored in database
    | are normalized to this base currency (USD).
    |
    */

    'base' => 'USD',

    /*
    |--------------------------------------------------------------------------
    | Supported Currencies & Exchange Rates (Relative to USD Base)
    |--------------------------------------------------------------------------
    */

    'currencies' => [
        'USD' => [
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'rate' => 1.0000,
            'position' => 'prefix', // $100.00
        ],
        'INR' => [
            'code' => 'INR',
            'name' => 'Indian Rupee',
            'symbol' => '₹',
            'rate' => 83.5000,
            'position' => 'prefix', // ₹8,350.00
        ],
        'BGN' => [
            'code' => 'BGN',
            'name' => 'Bulgarian Lev',
            'symbol' => 'лв',
            'rate' => 1.8000,
            'position' => 'suffix', // 180.00 лв
        ],
    ],
];
