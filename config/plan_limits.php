<?php

/*
|--------------------------------------------------------------------------
| Plan usage limits (Zoho-style tier caps)
|--------------------------------------------------------------------------
|
| Every countable resource a plan can cap. plans.limits maps these keys to a
| number; a missing or null key means unlimited. Users stay on the separate
| plans.max_users column (seats are billed), storage on max_storage_mb.
|
| 'model' is counted per tenant; 'period' => 'month' counts only rows whose
| 'date_column' falls in the current calendar month. UsageLimitService
| enforces each cap on the model's creating event, so every create path
| (screens, imports, API, listeners) is covered.
|
*/

return [

    'resources' => [
        'companies' => [
            'label' => 'Companies',
            'model' => \App\Domains\HRMS\Models\Company::class,
        ],
        'branches' => [
            'label' => 'Branches',
            'model' => \App\Domains\HRMS\Models\Branch::class,
        ],
        'warehouses' => [
            'label' => 'Warehouses',
            'model' => \App\Domains\Inventory\Models\Warehouse::class,
        ],
        'products' => [
            'label' => 'Products',
            'model' => \App\Domains\Inventory\Models\Product::class,
        ],
        'customers' => [
            'label' => 'Customers',
            'model' => \App\Domains\CRM\Models\Customer::class,
        ],
        'invoices_per_month' => [
            'label' => 'Sales invoices per month',
            'model' => \App\Domains\Sales\Models\Invoice::class,
            'period' => 'month',
            'date_column' => 'created_at',
        ],
    ],

];
