<?php

// Sidebar entries for the workspace and tenant console. See App\Core\Navigation\MenuRegistry.

return [
    [
        'section' => 'workspace', 'order' => 10,
        'label' => 'ui.executive_dashboard', 'default' => 'Executive Dashboard', 'icon' => 'feather-home',
        'route' => 'dashboard', 'active_routes' => ['home'],
    ],
    [
        'section' => 'workspace', 'order' => 20, 'app' => 'admin',
        'label' => 'Tenant Console', 'icon' => 'feather-grid',
        'children' => [
            ['label' => 'Tenants', 'route' => 'platform.tenants.index', 'permission' => 'platform.tenants.manage'],
            ['label' => 'Plans', 'route' => 'platform.plans.index', 'permission' => 'platform.plans.manage'],
            ['label' => 'Add-on Prices', 'route' => 'platform.module-prices.index', 'permission' => 'platform.plans.manage'],
            ['label' => 'Currencies', 'route' => 'platform.currencies.index', 'permission' => 'platform.currencies.manage'],
            ['label' => 'Subscriptions', 'route' => 'platform.subscription.index', 'permission' => 'tenant.subscription.manage'],
            ['label' => 'Usage Limits', 'route' => 'platform.usage.index', 'permission' => 'platform.usage.view'],
            ['label' => 'Payment Gateway', 'route' => 'platform.payment-gateway.index', 'permission' => 'platform.payment_gateway.manage'],
            ['label' => 'Payment Terms', 'route' => 'platform.payment-terms.index'],
            ['label' => 'Notification Master', 'route' => 'platform.notification-rules.index'],
            ['label' => 'Email & SMTP Setup', 'route' => 'platform.emailSettings.index'],
            ['label' => 'WhatsApp Web Setup', 'route' => 'platform.whatsappSettings.index'],
            ['label' => 'GST & E-Invoice Setup', 'route' => 'platform.gstSettings.index'],
        ],
    ],
    [
        'section' => 'workspace', 'order' => 30,
        'label' => 'ui.approvals_center', 'default' => 'Approvals Center', 'icon' => 'feather-check-square',
        'children' => [
            ['label' => 'Pending'],
            ['label' => 'Delegated'],
            ['label' => 'Escalations'],
            ['label' => 'Workflow Rules'],
        ],
    ],
];
