<?php

// Sidebar of the /admin platform console, read by App\Domains\Platform\Support\AdminMenu.
// Each section is a caption; an item shows when its route exists and the user holds its permission.

return [
    [
        'label' => 'Overview',
        'items' => [
            ['label' => 'Dashboard', 'icon' => 'feather-activity', 'route' => 'admin.dashboard', 'permission' => 'platform.console.access'],
        ],
    ],
    [
        'label' => 'Sales',
        'items' => [
            ['label' => 'Demo Requests', 'icon' => 'feather-phone-call', 'route' => 'platform.demo-requests.index', 'permission' => 'platform.leads.manage'],
        ],
    ],
    [
        'label' => 'Customers',
        'items' => [
            ['label' => 'Tenants', 'icon' => 'feather-briefcase', 'route' => 'platform.tenants.index', 'permission' => 'platform.tenants.manage'],
            ['label' => 'Usage Limits', 'icon' => 'feather-bar-chart-2', 'route' => 'platform.usage.index', 'permission' => 'platform.usage.view'],
        ],
    ],
    [
        'label' => 'Billing',
        'items' => [
            ['label' => 'Plans', 'icon' => 'feather-layers', 'route' => 'platform.plans.index', 'permission' => 'platform.plans.manage'],
            ['label' => 'Add-on Prices', 'icon' => 'feather-package', 'route' => 'platform.module-prices.index', 'permission' => 'platform.plans.manage'],
            ['label' => 'Payment Gateway', 'icon' => 'feather-credit-card', 'route' => 'platform.payment-gateway.index', 'permission' => 'platform.payment_gateway.manage'],
            ['label' => 'Currencies', 'icon' => 'feather-dollar-sign', 'route' => 'platform.currencies.index', 'permission' => 'platform.currencies.manage'],
        ],
    ],
    [
        'label' => 'System',
        'items' => [
            ['label' => 'Audit Log', 'icon' => 'feather-shield', 'route' => 'admin.audit-logs.index', 'permission' => 'platform.audit.view'],
        ],
    ],
];
