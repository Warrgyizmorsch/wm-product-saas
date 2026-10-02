<?php

// Projects sidebar entries. See App\Core\Navigation\MenuRegistry.

return [
    [
        'section' => 'revenue_cycle', 'order' => 50,
        'label' => 'ui.projects', 'default' => 'Projects', 'icon' => 'feather-briefcase',
        'children' => [
            ['label' => 'projects.executive_dashboard', 'default' => 'Executive Dashboard', 'route' => 'projects.dashboard', 'permission' => ['projects.dashboard.view', 'projects.projects.view']],
            ['label' => 'ui.projects', 'default' => 'Projects', 'route' => 'projects.index', 'permission' => 'projects.projects.view'],
            ['label' => 'projects.milestones', 'default' => 'Milestones', 'route' => 'projects.milestones.index', 'permission' => 'projects.projects.view'],
            ['label' => 'projects.tasks', 'default' => 'Tasks', 'route' => 'projects.tasks.index', 'permission' => 'projects.projects.view'],
            ['label' => 'projects.timesheets', 'default' => 'Timesheets', 'route' => 'projects.timesheets.approval', 'permission' => 'projects.projects.view'],
            ['label' => 'projects.reports', 'default' => 'Reports', 'route' => 'projects.reports.index', 'permission' => ['projects.reports.view', 'projects.projects.view']],
        ],
    ],
];

