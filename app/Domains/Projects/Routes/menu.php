<?php

// Projects sidebar entries. See App\Core\Navigation\MenuRegistry.

return [
    [
        'section' => 'revenue_cycle', 'order' => 50,
        'label' => 'ui.projects', 'default' => 'Projects', 'icon' => 'feather-briefcase',
        'children' => [
            ['label' => 'ui.projects', 'default' => 'Projects', 'route' => 'projects.index', 'permission' => 'projects.projects.view'],
            ['label' => 'projects.milestones', 'default' => 'Milestones', 'route' => 'projects.milestones.index', 'permission' => 'projects.projects.view'],
            ['label' => 'Tasks'],
            ['label' => 'Timesheets'],
        ],
    ],
];
