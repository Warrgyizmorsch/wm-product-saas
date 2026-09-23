<?php

// Sales sidebar entries. See App\Core\Navigation\MenuRegistry.

return [
    [
        'section' => 'supply_chain', 'order' => 5, 'app' => 'sales',
        'label' => 'ui.sales', 'default' => 'Sales', 'icon' => 'feather-shopping-cart',
        'children' => [
            ['label' => 'crm.quotations', 'default' => 'Quotations', 'route' => 'crm.quotations.index', 'permission' => 'crm.quotations.view'],
            ['label' => 'crm.sales_orders', 'default' => 'Sales Orders', 'route' => 'sales.orders.index', 'permission' => 'sales.orders.view'],
            ['label' => 'crm.invoices', 'default' => 'Invoices', 'route' => 'sales.invoices.index', 'permission' => 'sales.invoices.view'],
            ['label' => 'crm.receipts_payments', 'default' => 'Receipts (Payments)', 'route' => 'sales.payments.index', 'permission' => 'sales.payments.view'],
            ['label' => 'crm.sales_returns', 'default' => 'Sales Returns', 'route' => 'sales.returns.index', 'permission' => 'sales.returns.view'],
            ['label' => 'crm.sales_settings', 'default' => 'Sales Settings', 'route' => 'sales.settings.index', 'permission' => 'sales.orders.view'],
        ],
    ],
];

