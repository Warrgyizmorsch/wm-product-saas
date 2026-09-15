<?php

// Production sidebar entries. See App\Core\Navigation\MenuRegistry.

return [
    [
        'section' => 'production', 'order' => 10,
        'label' => 'Production Dashboard', 'icon' => 'feather-grid', 'route' => 'production.dashboard',
    ],
    [
        'section' => 'production', 'order' => 20,
        'label' => 'Execution', 'icon' => 'feather-play-circle',
        'children' => [
            ['label' => 'Production Orders', 'route' => 'production.orders.index'],
            ['label' => 'Shop Floor (MES)', 'route' => 'production.mes.dashboard'],
            ['label' => 'Work-in-Progress (WIP)', 'route' => 'production.wip.index'],
            ['label' => 'Job Cards / Operations', 'route' => 'production.mes.operator.my-operations'],
        ],
    ],
    [
        'section' => 'production', 'order' => 30,
        'label' => 'Quality Management', 'icon' => 'feather-check-circle',
        'children' => [
            ['label' => 'Quality Dashboard', 'route' => 'production.quality.dashboard'],
            ['label' => 'Quality Inspections', 'route' => 'production.inspections.index'],
            ['label' => 'NCR', 'route' => 'production.ncrs.index'],
            ['label' => 'CAPA', 'route' => 'production.capas.index'],
            ['label' => 'Rework Orders', 'route' => 'production.rework.index'],
            ['label' => 'Scrap Disposals', 'route' => 'production.scrap.index'],
        ],
    ],
    [
        'section' => 'production', 'order' => 40,
        'label' => 'Subcontracting', 'icon' => 'feather-truck',
        'children' => [
            ['label' => 'Delivery Challans / Gate Passes', 'route' => 'production.subcontract.delivery-challans.index'],
            ['label' => 'Vendor SLA & Analytics', 'route' => 'production.subcontract.analytics'],
            ['label' => 'Subcontract Settings', 'route' => 'production.settings.index'],
        ],
    ],
    [
        'section' => 'production', 'order' => 50,
        'label' => 'Engineering', 'icon' => 'feather-settings',
        'children' => [
            ['label' => 'production.bom', 'default' => 'Bills of Materials', 'route' => 'production.boms.index'],
            ['label' => 'production.routing', 'default' => 'Routing', 'route' => 'production.routing.index'],
            ['label' => 'ECO / Engineering Changes', 'route' => 'production.ecos.index'],
            ['label' => 'production.work_centers', 'default' => 'Work Centers', 'route' => 'production.work-centers.index'],
            ['label' => 'production.machines', 'default' => 'Machines', 'route' => 'production.machines.index'],
            ['label' => 'Operator Skills', 'route' => 'production.operator-skills.index'],
            ['label' => 'production.shifts_sidebar', 'default' => 'Shifts', 'route' => 'production.shifts.index'],
            ['label' => 'production.calendars_sidebar', 'default' => 'Calendars', 'route' => 'production.calendars.index'],
        ],
    ],
    [
        'section' => 'production', 'order' => 60,
        'label' => 'Performance', 'icon' => 'feather-bar-chart-2',
        'children' => [
            ['label' => 'Variance & Performance', 'route' => 'production.variances.index'],
            ['label' => 'Executive Dashboard', 'route' => 'production.intelligence.dashboard'],
            ['label' => 'Live Andon Board', 'route' => 'production.intelligence.andon'],
            ['label' => 'Manufacturing Reports', 'route' => 'production.intelligence.reports.index'],
        ],
    ],
    [
        'section' => 'production', 'order' => 70,
        'label' => 'Machine Maintenance', 'icon' => 'feather-tool',
        'children' => [
            ['label' => 'Maintenance Dashboard', 'route' => 'production.maintenance.dashboard'],
            ['label' => 'Work Orders', 'route' => 'production.maintenance.work-orders.index'],
            ['label' => 'PM Schedules', 'route' => 'production.maintenance.schedules.index'],
        ],
    ],
    [
        'section' => 'production', 'order' => 80,
        'label' => 'Advanced Planning', 'icon' => 'feather-cpu',
        'children' => [
            ['label' => 'Production Plans', 'route' => 'production.plans.index'],
            ['label' => 'Production Schedules', 'route' => 'production.schedules.index'],
            ['label' => 'Calendar Schedule View', 'route' => 'production.schedules.calendar'],
            ['label' => 'Capacity Planning', 'route' => 'production.capacity.index'],
            ['label' => 'Planning Scenarios / What-if', 'route' => 'production.schedules.scenarios.index'],
            ['label' => 'Planning Exceptions / At-Risk', 'route' => 'production.planning-exceptions.index'],
        ],
    ],
];
