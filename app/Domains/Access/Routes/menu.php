<?php

// Access control and platform administration sidebar entries. See App\Core\Navigation\MenuRegistry.

return [
    [
        'section' => 'platform_admin', 'order' => 10,
        'label' => 'ui.access_control', 'default' => 'Access Control', 'icon' => 'feather-shield',
        'children' => [
            ['label' => 'Users', 'route' => 'access.users.index', 'permission' => 'access.users.manage'],
            ['label' => 'Roles', 'route' => 'access.roles.index', 'permission' => 'access.roles.manage'],
            ['label' => 'Teams'],
            ['label' => 'Policies'],
        ],
    ],
    [
        'section' => 'platform_admin', 'order' => 20,
        'label' => 'Automation', 'icon' => 'feather-zap',
        'children' => [
            ['label' => 'Workflows'],
            ['label' => 'Alerts'],
            ['label' => 'Schedulers'],
            ['label' => 'Webhooks'],
        ],
    ],
    [
        'section' => 'platform_admin', 'order' => 30,
        'label' => 'Audit & Settings', 'icon' => 'feather-settings',
        'children' => [
            ['label' => 'Audit Logs', 'route' => 'access.audit-log.index', 'permission' => 'audit.logs.view'],
            ['label' => 'Localization'],
            ['label' => 'Currencies'],
            ['label' => 'System Settings'],
        ],
    ],
];
