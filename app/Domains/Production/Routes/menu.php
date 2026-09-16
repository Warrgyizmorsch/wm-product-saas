<?php

// Production sidebar entries. See App\Core\Navigation\MenuRegistry.

return [
    [
        'section' => 'production', 'order' => 10,
        'label' => 'Production Dashboard', 'icon' => 'feather-grid', 'route' => 'production.dashboard',
        'permission' => ['production.intelligence.view', 'production.order.create', 'production.order.update', 'production.mes.execute'],
    ],
    [
        'section' => 'production', 'order' => 20,
        'label' => 'Execution', 'icon' => 'feather-play-circle',
        'children' => [
            ['label' => 'Production Orders', 'route' => 'production.orders.index'],
            ['label' => 'Shop Floor (MES)', 'route' => 'production.mes.dashboard', 'permission' => 'production.mes.execute'],
            ['label' => 'Work-in-Progress (WIP)', 'route' => 'production.wip.index', 'permission' => 'production.mes.execute'],
            ['label' => 'Job Cards / Operations', 'route' => 'production.mes.operator.my-operations', 'permission' => 'production.mes.execute'],
            ['label' => 'Operator Dashboard', 'route' => 'production.mes.operator.dashboard', 'permission' => 'production.mes.execute'],
            ['label' => 'Barcode Scanner', 'route' => 'production.mes.scanner.index', 'permission' => 'production.mes.execute'],
            ['label' => 'Work Center Board', 'route' => 'production.mes.work-centers.index', 'permission' => 'production.mes.execute'],
            ['label' => 'Machine Board', 'route' => 'production.mes.machines.index', 'permission' => 'production.mes.execute'],
            ['label' => 'Production Timeline', 'route' => 'production.mes.timeline.index', 'permission' => 'production.mes.execute'],
            ['label' => 'Lot Traceability', 'route' => 'production.mes.traceability.index', 'permission' => 'production.mes.execute'],
            ['label' => 'Scan Logs', 'route' => 'production.scan-logs.index', 'permission' => 'production.mes.execute'],
        ],
    ],
    [
        'section' => 'production', 'order' => 30,
        'label' => 'Quality Management', 'icon' => 'feather-check-circle',
        'children' => [
            ['label' => 'Quality Dashboard', 'route' => 'production.quality.dashboard', 'permission' => 'production.quality.manage'],
            ['label' => 'Quality Plans', 'route' => 'production.quality-plans.index', 'permission' => 'production.quality.manage'],
            ['label' => 'Quality Inspections', 'route' => 'production.inspections.index', 'permission' => 'production.quality.manage'],
            ['label' => 'Deviations', 'route' => 'production.deviations.index', 'permission' => 'production.quality.manage'],
            ['label' => 'NCR', 'route' => 'production.ncrs.index', 'permission' => 'production.quality.manage'],
            ['label' => 'CAPA', 'route' => 'production.capas.index', 'permission' => 'production.quality.manage'],
            ['label' => 'Rework Orders', 'route' => 'production.rework.index', 'permission' => 'production.quality.manage'],
            ['label' => 'Scrap Disposals', 'route' => 'production.scrap.index', 'permission' => 'production.quality.manage'],
        ],
    ],
    [
        'section' => 'production', 'order' => 40,
        'label' => 'Subcontracting', 'icon' => 'feather-truck',
        'children' => [
            ['label' => 'Delivery Challans / Gate Passes', 'route' => 'production.subcontract.delivery-challans.index', 'permission' => 'production.mes.execute'],
            ['label' => 'Vendor SLA & Analytics', 'route' => 'production.subcontract.analytics'],
            ['label' => 'Subcontract Settings', 'route' => 'production.settings.index', 'permission' => 'production.work_center.manage'],
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
            ['label' => 'Operator Skills', 'route' => 'production.operator-skills.index', 'permission' => 'production.mes.execute'],
            ['label' => 'production.shifts_sidebar', 'default' => 'Shifts', 'route' => 'production.shifts.index', 'permission' => 'production.mes.execute'],
            ['label' => 'production.calendars_sidebar', 'default' => 'Calendars', 'route' => 'production.calendars.index', 'permission' => 'production.mes.execute'],
        ],
    ],
    [
        'section' => 'production', 'order' => 60,
        'label' => 'Performance', 'icon' => 'feather-bar-chart-2',
        'children' => [
            ['label' => 'Variance & Performance', 'route' => 'production.variances.index'],
            ['label' => 'Executive Dashboard', 'route' => 'production.intelligence.dashboard', 'permission' => 'production.intelligence.view'],
            ['label' => 'Live Andon Board', 'route' => 'production.intelligence.andon', 'permission' => 'production.intelligence.view'],
            ['label' => 'Manufacturing Reports', 'route' => 'production.intelligence.reports.index', 'permission' => 'production.intelligence.view'],
            ['label' => 'Production Alerts', 'route' => 'production.intelligence.alerts.index', 'permission' => 'production.intelligence.view'],
            ['label' => 'KPI Targets', 'route' => 'production.kpi-targets.index', 'permission' => 'production.intelligence.view'],
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
