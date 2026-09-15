<?php

// Sales sidebar entries. See App\Core\Navigation\MenuRegistry.

return [
    [
        'section' => 'revenue_cycle', 'order' => 40,
        'label' => 'ui.sales', 'default' => 'Sales', 'icon' => 'feather-shopping-cart',
        'children' => [
            ['label' => 'Quotations', 'route' => 'crm.quotations.index'],
            ['label' => 'Sales Orders', 'route' => 'sales.orders.index'],
            ['label' => 'Invoices', 'route' => 'sales.invoices.index'],
            ['label' => 'Receipts (Payments)', 'route' => 'sales.payments.index'],
            ['label' => 'Sales Returns', 'route' => 'sales.returns.index'],
        ],
    ],
];
