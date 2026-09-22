<?php

// Production sidebar entries. See App\Core\Navigation\MenuRegistry.

return [
    [
        'section' => 'production', 'order' => 10,
        'label' => 'production.production_dashboard', 'default' => 'Production Dashboard', 'icon' => 'feather-grid', 'route' => 'production.dashboard',
        'permission' => ['production.intelligence.view', 'production.order.create', 'production.order.update', 'production.mes.execute'],
    ],
    [
        'section' => 'production', 'order' => 20,
        'label' => 'production.execution', 'default' => 'Execution', 'icon' => 'feather-play-circle',
        'children' => [
            ['label' => 'production.production_orders', 'default' => 'Production Orders', 'route' => 'production.orders.index'],
            ['label' => 'production.shop_floor_mes', 'default' => 'Shop Floor (MES)', 'route' => 'production.mes.dashboard', 'permission' => 'production.mes.execute'],
            ['label' => 'production.work_in_progress_wip', 'default' => 'Work-in-Progress (WIP)', 'route' => 'production.wip.index', 'permission' => 'production.mes.execute'],
            ['label' => 'production.job_cards_operations', 'default' => 'Job Cards / Operations', 'route' => 'production.mes.operator.my-operations', 'permission' => 'production.mes.execute'],
            ['label' => 'production.operator_dashboard', 'default' => 'Operator Dashboard', 'route' => 'production.mes.operator.dashboard', 'permission' => 'production.mes.execute'],
            ['label' => 'production.barcode_scanner', 'default' => 'Barcode Scanner', 'route' => 'production.mes.scanner.index', 'permission' => 'production.mes.execute'],
            ['label' => 'production.work_center_board', 'default' => 'Work Center Board', 'route' => 'production.mes.work-centers.index', 'permission' => 'production.mes.execute'],
            ['label' => 'production.machine_board', 'default' => 'Machine Board', 'route' => 'production.mes.machines.index', 'permission' => 'production.mes.execute'],
            ['label' => 'production.production_timeline', 'default' => 'Production Timeline', 'route' => 'production.mes.timeline.index', 'permission' => 'production.mes.execute'],
            ['label' => 'production.lot_traceability', 'default' => 'Lot Traceability', 'route' => 'production.mes.traceability.index', 'permission' => 'production.mes.execute'],
            ['label' => 'production.scan_logs', 'default' => 'Scan Logs', 'route' => 'production.scan-logs.index', 'permission' => 'production.mes.execute'],
        ],
    ],
    [
        'section' => 'production', 'order' => 30,
        'label' => 'production.quality_management', 'default' => 'Quality Management', 'icon' => 'feather-check-circle',
        'children' => [
            ['label' => 'production.quality_dashboard', 'default' => 'Quality Dashboard', 'route' => 'production.quality.dashboard', 'permission' => 'production.quality.manage'],
            ['label' => 'production.quality_plans', 'default' => 'Quality Plans', 'route' => 'production.quality-plans.index', 'permission' => 'production.quality.manage'],
            ['label' => 'production.quality_inspections', 'default' => 'Quality Inspections', 'route' => 'production.inspections.index', 'permission' => 'production.quality.manage'],
            ['label' => 'production.deviations', 'default' => 'Deviations', 'route' => 'production.deviations.index', 'permission' => 'production.quality.manage'],
            ['label' => 'production.ncr', 'default' => 'NCR', 'route' => 'production.ncrs.index', 'permission' => 'production.quality.manage'],
            ['label' => 'production.capa', 'default' => 'CAPA', 'route' => 'production.capas.index', 'permission' => 'production.quality.manage'],
            ['label' => 'production.rework_orders', 'default' => 'Rework Orders', 'route' => 'production.rework.index', 'permission' => 'production.quality.manage'],
            ['label' => 'production.scrap_disposals', 'default' => 'Scrap Disposals', 'route' => 'production.scrap.index', 'permission' => 'production.quality.manage'],
        ],
    ],
    [
        'section' => 'production', 'order' => 40,
        'label' => 'production.subcontracting', 'default' => 'Subcontracting', 'icon' => 'feather-truck',
        'children' => [
            ['label' => 'production.delivery_challans_gate_passes', 'default' => 'Delivery Challans / Gate Passes', 'route' => 'production.subcontract.delivery-challans.index', 'permission' => 'production.mes.execute'],
            ['label' => 'production.vendor_sla_analytics', 'default' => 'Vendor SLA & Analytics', 'route' => 'production.subcontract.analytics'],
            ['label' => 'production.subcontract_settings', 'default' => 'Subcontract Settings', 'route' => 'production.settings.index', 'permission' => 'production.work_center.manage'],
        ],
    ],
    [
        'section' => 'production', 'order' => 50,
        'label' => 'production.engineering', 'default' => 'Engineering', 'icon' => 'feather-settings',
        'children' => [
            ['label' => 'production.bom', 'default' => 'Bills of Materials', 'route' => 'production.boms.index'],
            ['label' => 'production.routing', 'default' => 'Routing', 'route' => 'production.routing.index'],
            ['label' => 'production.eco_engineering_changes', 'default' => 'ECO / Engineering Changes', 'route' => 'production.ecos.index'],
            ['label' => 'production.work_centers', 'default' => 'Work Centers', 'route' => 'production.work-centers.index'],
            ['label' => 'production.machines', 'default' => 'Machines', 'route' => 'production.machines.index'],
            ['label' => 'production.operator_skills', 'default' => 'Operator Skills', 'route' => 'production.operator-skills.index', 'permission' => 'production.mes.execute'],
            ['label' => 'production.shifts_sidebar', 'default' => 'Shifts', 'route' => 'production.shifts.index', 'permission' => 'production.mes.execute'],
            ['label' => 'production.calendars_sidebar', 'default' => 'Calendars', 'route' => 'production.calendars.index', 'permission' => 'production.mes.execute'],
        ],
    ],
    [
        'section' => 'production', 'order' => 60,
        'label' => 'production.performance', 'default' => 'Performance', 'icon' => 'feather-bar-chart-2',
        'children' => [
            ['label' => 'production.variance_and_performance', 'default' => 'Variance & Performance', 'route' => 'production.variances.index'],
            ['label' => 'production.executive_dashboard', 'default' => 'Executive Dashboard', 'route' => 'production.intelligence.dashboard', 'permission' => 'production.intelligence.view'],
            ['label' => 'production.live_andon_board', 'default' => 'Live Andon Board', 'route' => 'production.intelligence.andon', 'permission' => 'production.intelligence.view'],
            ['label' => 'production.manufacturing_reports', 'default' => 'Manufacturing Reports', 'route' => 'production.intelligence.reports.index', 'permission' => 'production.intelligence.view'],
            ['label' => 'production.production_alerts', 'default' => 'Production Alerts', 'route' => 'production.intelligence.alerts.index', 'permission' => 'production.intelligence.view'],
            ['label' => 'production.kpi_targets', 'default' => 'KPI Targets', 'route' => 'production.kpi-targets.index', 'permission' => 'production.intelligence.view'],
        ],
    ],
    [
        'section' => 'production', 'order' => 70,
        'label' => 'production.machine_maintenance', 'default' => 'Machine Maintenance', 'icon' => 'feather-tool',
        'children' => [
            ['label' => 'production.maintenance_dashboard', 'default' => 'Maintenance Dashboard', 'route' => 'production.maintenance.dashboard'],
            ['label' => 'production.work_orders', 'default' => 'Work Orders', 'route' => 'production.maintenance.work-orders.index'],
            ['label' => 'production.pm_schedules', 'default' => 'PM Schedules', 'route' => 'production.maintenance.schedules.index'],
        ],
    ],
    [
        'section' => 'production', 'order' => 80,
        'label' => 'production.advanced_planning', 'default' => 'Advanced Planning', 'icon' => 'feather-cpu',
        'children' => [
            ['label' => 'production.production_plans', 'default' => 'Production Plans', 'route' => 'production.plans.index'],
            ['label' => 'production.production_schedules', 'default' => 'Production Schedules', 'route' => 'production.schedules.index'],
            ['label' => 'production.calendar_schedule_view', 'default' => 'Calendar Schedule View', 'route' => 'production.schedules.calendar'],
            ['label' => 'production.capacity_planning', 'default' => 'Capacity Planning', 'route' => 'production.capacity.index'],
            ['label' => 'production.planning_scenarios', 'default' => 'Planning Scenarios / What-if', 'route' => 'production.schedules.scenarios.index'],
            ['label' => 'production.planning_exceptions', 'default' => 'Planning Exceptions / At-Risk', 'route' => 'production.planning-exceptions.index'],
        ],
    ],
];
