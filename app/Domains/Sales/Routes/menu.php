<?php

// Sales sidebar entries. See App\Core\Navigation\MenuRegistry.

return [
    [
        'section' => 'revenue_cycle', 'order' => 40,
        'label' => 'ui.sales', 'default' => 'Sales', 'icon' => 'feather-shopping-cart',
        'children' => [
            ['label' => 'Quotations', 'route' => 'crm.quotations.index', 'permission' => 'crm.quotations.view'],
            ['label' => 'Sales Orders', 'route' => 'sales.orders.index', 'permission' => 'sales.orders.view'],
            ['label' => 'Invoices', 'route' => 'sales.invoices.index', 'permission' => 'sales.invoices.view'],
            ['label' => 'Receipts (Payments)', 'route' => 'sales.payments.index', 'permission' => 'sales.payments.view'],
            ['label' => 'Sales Returns', 'route' => 'sales.returns.index', 'permission' => 'sales.returns.view'],
        ],
    ],
];
