<?php

// Sidebar entries for the workspace and tenant console. See App\Core\Navigation\MenuRegistry.

return [
    [
        'section' => 'workspace',
        'order' => 10,
        'label' => 'ui.executive_dashboard',
        'default' => 'Executive Dashboard',
        'icon' => 'feather-home',
        'route' => 'dashboard',
        'active_routes' => ['home'],
    ],
    [
        'section' => 'workspace',
        'order' => 20,
        'app' => 'admin',
        'label' => 'Tenant Console',
        'icon' => 'feather-grid',
        'children' => [
            // Tenants, plans, prices, usage and the gateway live in the /admin console.
            ['label' => 'Platform Console', 'route' => 'admin.dashboard', 'permission' => 'platform.console.access'],
            ['label' => 'Subscriptions', 'route' => 'platform.subscription.index', 'permission' => 'tenant.subscription.manage'],
            ['label' => 'Payment Terms', 'route' => 'platform.payment-terms.index', 'permission' => 'tenant.settings.manage'],
            ['label' => 'Notification Master', 'route' => 'platform.notification-rules.index', 'permission' => 'tenant.settings.manage'],
            ['label' => 'Email & SMTP Setup', 'route' => 'platform.emailSettings.index', 'permission' => 'tenant.settings.manage'],
            ['label' => 'WhatsApp Web Setup', 'route' => 'platform.whatsappSettings.index', 'permission' => 'tenant.settings.manage'],
            ['label' => 'GST & E-Invoice Setup', 'route' => 'platform.gstSettings.index', 'permission' => 'tenant.settings.manage'],
            ['label' => 'Meta Lead Ads Setup', 'route' => 'platform.metaSettings.index', 'permission' => 'tenant.settings.manage'],
            ['label' => 'Twilio Telephony Setup', 'route' => 'platform.twilioSettings.index', 'permission' => 'tenant.settings.manage'],
        ],
    ],
    [
        'section' => 'workspace',
        'order' => 30,
        'label' => 'ui.approvals_center',
        'default' => 'Approvals Center',
        'icon' => 'feather-check-square',
        'children' => [
            ['label' => 'Pending'],
            ['label' => 'Delegated'],
            ['label' => 'Escalations'],
            ['label' => 'Workflow Rules'],
        ],
    ],
];
