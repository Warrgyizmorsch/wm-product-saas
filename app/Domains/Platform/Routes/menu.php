<?php

// Sidebar entries for the workspace and tenant console. See App\Core\Navigation\MenuRegistry.

return [
    [
        'section' => 'workspace', 'order' => 10,
        'label' => 'ui.executive_dashboard', 'default' => 'Executive Dashboard', 'icon' => 'feather-home',
        'route' => 'dashboard', 'active_routes' => ['home'],
    ],
    [
        'section' => 'workspace', 'order' => 20,
        'label' => 'Tenant Console', 'icon' => 'feather-grid',
        'children' => [
            ['label' => 'Tenants', 'route' => 'platform.tenants.index', 'permission' => 'platform.tenants.manage'],
            ['label' => 'Plans', 'route' => 'platform.plans.index', 'permission' => 'platform.plans.manage'],
            ['label' => 'Currencies', 'route' => 'platform.currencies.index', 'permission' => 'platform.currencies.manage'],
            ['label' => 'Subscriptions', 'route' => 'platform.subscription.index', 'permission' => 'tenant.subscription.manage'],
            ['label' => 'Usage Limits', 'route' => 'platform.usage.index', 'permission' => 'platform.usage.view'],
            ['label' => 'Payment Gateway', 'route' => 'platform.payment-gateway.index', 'permission' => 'platform.payment_gateway.manage'],
            ['label' => 'Payment Terms', 'route' => 'platform.payment-terms.index'],
            ['label' => 'Email & SMTP Setup', 'route' => 'crm.emailSettings.index'],
            ['label' => 'WhatsApp Web Setup', 'route' => 'crm.whatsappSettings.index'],
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
