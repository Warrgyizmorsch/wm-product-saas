<?php

// Visitor Management sidebar entries. See App\Core\Navigation\MenuRegistry.

return [
    [
        'section' => 'revenue_cycle',
        'order'   => 45,
        'app'     => 'visitor',
        'module'  => 'visitor',
        'label'   => 'visitor.visitor_management',
        'default' => 'Visitor Management',
        'icon'    => 'feather-user-check',
        'children' => [
            [
                'label'      => 'visitor.gate_desk',
                'default'    => 'Gate Desk (Visitor Logs)',
                'route'      => 'visitor.index',
                'permission' => 'visitor.passes.view',
            ],
            [
                'label'      => 'visitor.my_approvals',
                'default'    => 'My Approvals',
                'route'      => 'visitor.approvals.index',
                'permission' => 'visitor.passes.view',
            ],
        ],
    ],
];
