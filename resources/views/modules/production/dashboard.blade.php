@extends('layouts.duralux')

@section('title', 'Production Dashboard')
@section('page-title', 'Production Dashboard')
@section('breadcrumb', 'Production / Dashboard')

@php
    $rawRole = auth()->user()?->role;
    $roleDisplayName = is_object($rawRole) ? ($rawRole->name ?? 'Plant Manager') : ($rawRole ?? 'Plant Manager');
    $roleDisplayName = ucwords(str_replace(['_', '-'], ' ', (string) $roleDisplayName));

    $hour = date('H');
    $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');

    $worklistTabs = [
        [
            'id' => 'content-demand',
            'label' => 'Pending Demand (' . $pendingSalesOrderCount . ')',
            'active' => true,
            'icon' => 'feather-shopping-cart'
        ],
        [
            'id' => 'content-ready',
            'label' => 'Ready To Start (' . $readyToStartCount . ')',
            'active' => false,
            'icon' => 'feather-check-circle'
        ],
        [
            'id' => 'content-inprogress',
            'label' => 'Active In-Progress (' . $inProgressOrders->count() . ')',
            'active' => false,
            'icon' => 'feather-activity'
        ],
        [
            'id' => 'content-overdue',
            'label' => 'At-Risk / Overdue (' . $overdueOrdersCount . ')',
            'active' => false,
            'icon' => 'feather-alert-triangle'
        ],
    ];

    $totalActionExceptions = ($overdueOrdersCount ?? 0)
        + ($breakdownCount ?? 0)
        + ($materialBlockersCount ?? 0)
        + (($pendingQcCount ?? 0) + ($openNcrCount ?? 0))
        + ($vendorDelayedCount ?? 0)
        + ($pendingSalesOrderCount ?? 0);
@endphp

@push('styles')
    <style>
        .avatar-initials-dash {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 15px;
        }

        .dash-card-icon-avatar {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
            background-color: color-mix(in srgb, var(--bs-primary, #6337fa) 12%, transparent);
            color: var(--bs-primary, #6337fa);
        }

        /* Common Dashboard Card */
        .dash-card {
            background-color: #ffffff;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease-in-out;
        }

        .dash-card .card-header {
            background-color: transparent;
            border-bottom: 1px solid #e2e8f0;
        }

        /* Common Dashboard Tile */
        .dash-tile,
        .dash-tile-default,
        .action-tile-card,
        .machine-state-box,
        .quality-tile-box,
        .loss-tile-box,
        .maintenance-tile-box,
        .subcontract-pipeline-box {
            border-radius: 10px;
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease-in-out;
        }

        .dash-tile:hover,
        .dash-tile-default:hover,
        .action-tile-card:hover,
        .machine-state-box:hover,
        .quality-tile-box:hover,
        .loss-tile-box:hover,
        .maintenance-tile-box:hover,
        .subcontract-pipeline-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
        }

        .action-tile-card {
            min-height: 110px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 14px 12px;
        }

        .loss-tile-box {
            min-height: 106px;
            padding: 14px 10px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
        }

        .machine-state-box {
            min-height: 96px;
            padding: 13px 8px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
        }

        .quality-tile-box {
            min-height: 114px;
            padding: 16px 14px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .maintenance-tile-box {
            min-height: 110px;
            padding: 16px 12px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
        }

        .subcontract-pipeline-box {
            border-radius: 8px;
            min-height: 80px;
            padding: 12px 6px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
        }

        .hover-shadow {
            transition: all 0.2s ease-in-out;
        }

        .hover-shadow:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        }

        /* Subtle Nested Panel Strip */
        .dash-panel-subtle {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        /* Inner Nested Card */
        .dash-card-inner {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
        }

        /* Mini Status Tile */
        .dash-mini-tile {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        /* Worklist Table Head */
        .dash-table-head th {
            background-color: #f8fafc;
            color: #64748b;
            border-color: #e2e8f0;
        }

        /* Worklist Fixed Viewport & Unified Heights */
        .worklist-tab-content {
            height: 300px;
            min-height: 300px;
        }

        .tab-content>.worklist-tab-pane {
            height: 300px;
            min-height: 300px;
        }

        .tab-content>.worklist-tab-pane.active {
            display: flex;
            flex-direction: column;
        }

        .worklist-table-container {
            flex: 1 1 auto;
            overflow-y: auto;
            overflow-x: auto;
            min-height: 0;
            width: 100%;
        }

        .worklist-table-container thead th {
            position: sticky;
            top: 0;
            z-index: 5;
            background-color: #f8fafc;
            box-shadow: inset 0 -1px 0 #e2e8f0;
        }

        html.app-skin-dark .worklist-table-container thead th,
        body.app-skin-dark .worklist-table-container thead th {
            background-color: #1e293b;
            box-shadow: inset 0 -1px 0 #334155;
        }

        .worklist-table-container::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .worklist-table-container::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .worklist-table-container::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        .worklist-table-container::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        html.app-skin-dark .worklist-table-container::-webkit-scrollbar-track {
            background: #0f172a;
        }

        html.app-skin-dark .worklist-table-container::-webkit-scrollbar-thumb {
            background: #334155;
        }

        /* Active Running Work Center Card */
        .work-center-active {
            border-color: var(--bs-primary, #6337fa) !important;
            border-width: 1.5px !important;
            background: linear-gradient(180deg, rgba(99, 55, 250, 0.035) 0%, #ffffff 100%) !important;
            box-shadow: 0 4px 14px rgba(99, 55, 250, 0.1) !important;
            position: relative;
        }

        .work-center-active::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background-color: var(--bs-primary, #6337fa);
            border-top-left-radius: 8px;
            border-bottom-left-radius: 8px;
        }

        html.app-skin-dark .work-center-active,
        body.app-skin-dark .work-center-active {
            background: linear-gradient(180deg, rgba(99, 55, 250, 0.12) 0%, #111a2e 100%) !important;
            border-color: var(--bs-primary, #6337fa) !important;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.4) !important;
        }

        .ready-start-banner {
            background: linear-gradient(135deg, color-mix(in srgb, var(--bs-primary, #6337fa) 8%, transparent) 0%, color-mix(in srgb, var(--bs-primary, #6337fa) 3%, transparent) 100%);
            border-left: 5px solid var(--bs-primary, #6337fa);
            border-radius: 10px;
        }

        /* Soft primary badge and tints */
        .production-dashboard-wrapper .bg-soft-primary {
            background-color: color-mix(in srgb, var(--bs-primary, #6337fa) 12%, transparent) !important;
            color: var(--bs-primary, #6337fa) !important;
        }

        .production-dashboard-wrapper .text-primary {
            color: var(--bs-primary, #6337fa) !important;
        }

        .production-dashboard-wrapper .border-primary {
            border-color: var(--bs-primary, #6337fa) !important;
        }

        /* ── Production Flow Pipeline ── */
        .production-flow-container {
            display: flex;
            align-items: stretch;
            gap: 10px;
            overflow-x: auto;
            padding: 6px 4px;
            margin: -2px -4px;
        }

        .production-flow-step {
            flex: 1;
            min-width: 170px;
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 16px;
            position: relative;
            transition: all 0.2s ease-in-out;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .production-flow-step:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
            border-color: var(--bs-primary, #6337fa);
            z-index: 2;
        }

        .production-flow-step .step-number {
            font-size: 11px;
            font-weight: 700;
            color: var(--bs-primary, #6337fa);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .production-flow-step .step-title {
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
            margin-top: 2px;
            margin-bottom: 6px;
        }

        .production-flow-step .step-count {
            font-size: 22px;
            font-weight: 800;
            line-height: 1.1;
            color: #0f172a;
            font-family: var(--bs-font-monospace);
        }

        .production-flow-step .step-desc {
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
        }

        .production-flow-arrow {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 16px;
            flex-shrink: 0;
        }

        /* ── Expandable Production Areas ── */
        .production-area-card {
            background-color: #ffffff;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            margin-bottom: 12px;
            transition: all 0.2s ease-in-out;
            overflow: hidden;
        }

        .production-area-header {
            padding: 14px 18px;
            background-color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            user-select: none;
            transition: background-color 0.15s ease;
        }

        .production-area-header:hover {
            background-color: #f8fafc;
        }

        .production-area-header .collapse-chevron {
            transition: transform 0.2s ease-in-out;
            font-size: 16px;
            color: #64748b;
        }

        .production-area-header [data-bs-toggle="collapse"][aria-expanded="true"] .collapse-chevron,
        .production-area-header[aria-expanded="true"] .collapse-chevron {
            transform: rotate(180deg);
            color: var(--bs-primary, #6337fa);
        }

        .production-area-actions {
            display: flex;
            align-items: center;
        }

        html.app-skin-dark .production-area-actions,
        body.app-skin-dark .production-area-actions {
            border-color: #334155 !important;
        }

        .production-area-body {
            padding: 18px;
            border-top: 1px solid #e2e8f0;
            background-color: #fafbfd;
        }

        /* ── Dark Mode Overrides ── */
        html.app-skin-dark .production-dashboard-wrapper,
        html.app-skin-dark .production-dashboard-wrapper .card,
        html.app-skin-dark .dash-card,
        html.app-skin-dark .production-area-card,
        body.app-skin-dark .production-dashboard-wrapper,
        body.app-skin-dark .production-dashboard-wrapper .card,
        body.app-skin-dark .dash-card,
        body.app-skin-dark .production-area-card,
        [data-bs-theme="dark"] .production-dashboard-wrapper,
        [data-bs-theme="dark"] .production-dashboard-wrapper .card,
        [data-bs-theme="dark"] .dash-card {
            background-color: #0f172a !important;
            border-color: #1e293b !important;
            color: #cbd5e1 !important;
        }

        /* Production Flow Pipeline in Dark Mode */
        html.app-skin-dark .production-flow-step,
        body.app-skin-dark .production-flow-step,
        [data-bs-theme="dark"] .production-flow-step {
            background-color: #162038 !important;
            border-color: #283c50 !important;
            color: #cbd5e1 !important;
        }

        html.app-skin-dark .production-flow-step:hover,
        body.app-skin-dark .production-flow-step:hover,
        [data-bs-theme="dark"] .production-flow-step:hover {
            background-color: #1e293b !important;
            border-color: var(--bs-primary, #6337fa) !important;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.4) !important;
        }

        html.app-skin-dark .production-flow-step .step-number,
        body.app-skin-dark .production-flow-step .step-number,
        [data-bs-theme="dark"] .production-flow-step .step-number {
            color: #a5b4fc !important;
        }

        html.app-skin-dark .production-flow-step .step-title,
        body.app-skin-dark .production-flow-step .step-title,
        [data-bs-theme="dark"] .production-flow-step .step-title {
            color: #f1f5f9 !important;
        }

        html.app-skin-dark .production-flow-step .step-count,
        body.app-skin-dark .production-flow-step .step-count,
        [data-bs-theme="dark"] .production-flow-step .step-count {
            color: #ffffff !important;
        }

        html.app-skin-dark .production-flow-step .step-desc,
        body.app-skin-dark .production-flow-step .step-desc,
        [data-bs-theme="dark"] .production-flow-step .step-desc {
            color: #94a3b8 !important;
        }

        html.app-skin-dark .production-flow-arrow,
        body.app-skin-dark .production-flow-arrow,
        [data-bs-theme="dark"] .production-flow-arrow {
            color: #64748b !important;
        }

        html.app-skin-dark .production-area-header,
        body.app-skin-dark .production-area-header {
            background-color: #0f172a !important;
        }

        html.app-skin-dark .production-area-header:hover,
        body.app-skin-dark .production-area-header:hover {
            background-color: #1e293b !important;
        }

        html.app-skin-dark .production-area-body,
        body.app-skin-dark .production-area-body {
            background-color: #111a2e !important;
            border-color: #1e293b !important;
        }

        html.app-skin-dark .dash-card .card-header,
        html.app-skin-dark .production-dashboard-wrapper .card-header,
        body.app-skin-dark .dash-card .card-header,
        body.app-skin-dark .production-dashboard-wrapper .card-header,
        [data-bs-theme="dark"] .dash-card .card-header,
        [data-bs-theme="dark"] .production-dashboard-wrapper .card-header {
            background-color: #0f172a !important;
            border-color: #1e293b !important;
        }

        html.app-skin-dark .dash-tile,
        html.app-skin-dark .dash-tile-default,
        html.app-skin-dark .action-tile-card,
        html.app-skin-dark .machine-state-box,
        html.app-skin-dark .quality-tile-box,
        html.app-skin-dark .loss-tile-box,
        html.app-skin-dark .maintenance-tile-box,
        html.app-skin-dark .subcontract-pipeline-box,
        body.app-skin-dark .dash-tile,
        body.app-skin-dark .dash-tile-default,
        body.app-skin-dark .action-tile-card,
        body.app-skin-dark .machine-state-box,
        body.app-skin-dark .quality-tile-box,
        body.app-skin-dark .loss-tile-box,
        body.app-skin-dark .maintenance-tile-box,
        body.app-skin-dark .subcontract-pipeline-box {
            background-color: #162038 !important;
            border-color: #283c50 !important;
            color: #cbd5e1 !important;
        }

        html.app-skin-dark .dash-panel-subtle,
        body.app-skin-dark .dash-panel-subtle {
            background-color: #111a2e !important;
            border-color: #1e293b !important;
        }

        html.app-skin-dark .dash-card-inner,
        html.app-skin-dark .dash-mini-tile,
        body.app-skin-dark .dash-card-inner,
        body.app-skin-dark .dash-mini-tile {
            background-color: #162038 !important;
            border-color: #283c50 !important;
            color: #cbd5e1 !important;
        }

        html.app-skin-dark .text-dark,
        body.app-skin-dark .text-dark {
            color: #f1f5f9 !important;
        }

        html.app-skin-dark .dash-table-head th,
        body.app-skin-dark .dash-table-head th {
            background-color: #111a2e !important;
            color: #94a3b8 !important;
            border-color: #1e293b !important;
        }

        html.app-skin-dark .table td,
        body.app-skin-dark .table td {
            border-color: #1e293b !important;
            color: #cbd5e1 !important;
        }

        html.app-skin-dark .ready-start-banner,
        body.app-skin-dark .ready-start-banner {
            background: linear-gradient(135deg, rgba(99, 55, 250, 0.15) 0%, rgba(99, 55, 250, 0.05) 100%) !important;
            border-color: var(--bs-primary, #6337fa) !important;
        }

        /* Badge Dark Mode Overrides */
        html.app-skin-dark .badge.bg-soft-secondary,
        body.app-skin-dark .badge.bg-soft-secondary,
        [data-bs-theme="dark"] .badge.bg-soft-secondary,
        html.app-skin-dark .badge.bg-soft-dark,
        body.app-skin-dark .badge.bg-soft-dark,
        [data-bs-theme="dark"] .badge.bg-soft-dark,
        html.app-skin-dark .badge.bg-soft-light,
        body.app-skin-dark .badge.bg-soft-light,
        [data-bs-theme="dark"] .badge.bg-soft-light,
        html.app-skin-dark .badge.bg-light,
        body.app-skin-dark .badge.bg-light,
        [data-bs-theme="dark"] .badge.bg-light,
        html.app-skin-dark .badge.bg-secondary-subtle,
        body.app-skin-dark .badge.bg-secondary-subtle,
        [data-bs-theme="dark"] .badge.bg-secondary-subtle,
        html.app-skin-dark .erp-badge-draft,
        body.app-skin-dark .erp-badge-draft,
        [data-bs-theme="dark"] .erp-badge-draft {
            background-color: #1e293b !important;
            color: #cbd5e1 !important;
            border: 1px solid #334155 !important;
        }

        html.app-skin-dark .badge.bg-soft-secondary.text-secondary,
        body.app-skin-dark .badge.bg-soft-secondary.text-secondary,
        [data-bs-theme="dark"] .badge.bg-soft-secondary.text-secondary,
        html.app-skin-dark .badge.bg-soft-secondary.text-muted,
        body.app-skin-dark .badge.bg-soft-secondary.text-muted,
        [data-bs-theme="dark"] .badge.bg-soft-secondary.text-muted {
            color: #cbd5e1 !important;
        }

        /* Timeframe Pill Segmented Group */
        .timeframe-pill-group {
            background-color: #f1f5f9;
            border: 1px solid #e2e8f0;
            gap: 2px;
        }

        .timeframe-pill-group .btn {
            font-size: 12px;
            padding: 4px 14px;
            transition: all 0.15s ease-in-out;
            text-decoration: none;
            border: none;
        }

        .timeframe-pill-group a.text-muted:hover {
            color: var(--bs-primary, #6337fa) !important;
            background-color: rgba(255, 255, 255, 0.7);
        }

        html.app-skin-dark .timeframe-pill-group,
        body.app-skin-dark .timeframe-pill-group,
        [data-bs-theme="dark"] .timeframe-pill-group {
            background-color: #162038 !important;
            border-color: #283c50 !important;
        }

        html.app-skin-dark .timeframe-pill-group a.text-muted,
        body.app-skin-dark .timeframe-pill-group a.text-muted,
        [data-bs-theme="dark"] .timeframe-pill-group a.text-muted {
            color: #94a3b8 !important;
        }

        html.app-skin-dark .timeframe-pill-group a.text-muted:hover,
        body.app-skin-dark .timeframe-pill-group a.text-muted:hover,
        [data-bs-theme="dark"] .timeframe-pill-group a.text-muted:hover {
            color: #f1f5f9 !important;
            background-color: rgba(255, 255, 255, 0.08);
        }
    </style>
@endpush

@section('page-actions')
    <x-ui.button variant="primary" icon="feather-plus-circle" href="{{ route('production.orders.create') }}">
        Create Production Order
    </x-ui.button>
    <x-ui.button variant="light" icon="feather-monitor" href="{{ route('production.mes.dashboard') }}">
        Shop Floor
    </x-ui.button>
    <x-ui.button variant="light" icon="feather-bar-chart-2" href="{{ route('production.intelligence.reports.index') }}">
        Reports
    </x-ui.button>
@endsection

@section('content')
    <div class="production-dashboard-wrapper">


        {{-- ── SECTION 2: OPERATIONAL OVERVIEW & TIMEFRAME SNAPSHOT ─────────────── --}}
        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="fs-12 fw-bold text-dark text-uppercase tracking-wider">
                    <i class="feather-activity text-primary me-1"></i> Operational Overview
                </span>
                <span class="text-muted fs-12">&bull; Horizon: <span
                        class="badge bg-soft-primary text-primary fw-semibold">{{ ($timeframe ?? 'today') === 'week' ? 'This Week' : (($timeframe ?? 'today') === 'month' ? 'This Month' : 'Today') }}</span></span>
            </div>
            <div class="timeframe-pill-group d-inline-flex p-1 rounded-pill shadow-sm">
                <a href="{{ route('production.dashboard', ['timeframe' => 'today']) }}"
                    class="btn btn-xs rounded-pill px-3 py-1 fw-semibold {{ ($timeframe ?? 'today') === 'today' ? 'btn-primary shadow-sm' : 'text-muted' }}">
                    Today
                </a>
                <a href="{{ route('production.dashboard', ['timeframe' => 'week']) }}"
                    class="btn btn-xs rounded-pill px-3 py-1 fw-semibold {{ ($timeframe ?? 'today') === 'week' ? 'btn-primary shadow-sm' : 'text-muted' }}">
                    This Week
                </a>
                <a href="{{ route('production.dashboard', ['timeframe' => 'month']) }}"
                    class="btn btn-xs rounded-pill px-3 py-1 fw-semibold {{ ($timeframe ?? 'today') === 'month' ? 'btn-primary shadow-sm' : 'text-muted' }}">
                    This Month
                </a>
            </div>
        </div>

        <div class="row g-3">
            {{-- Card 1: Active Production Orders --}}
            <div class="col-xl-2 col-md-4 col-sm-6">
                <x-ui.stat-widget title="Active Orders" :value="number_format($totalActiveOrders)"
                    subtitle="Orders in progress" icon="feather-play-circle" color="primary" variant="compact">
                    <x-slot name="footer">
                        <span class="fw-semibold">{{ number_format($orderStatusCounts['total']) }}</span> Total Orders
                    </x-slot>
                </x-ui.stat-widget>
            </div>

            {{-- Card 2: Planned Today / Period --}}
            <div class="col-xl-2 col-md-4 col-sm-6">
                <x-ui.stat-widget
                    title="Planned {{ ($timeframe ?? 'today') === 'week' ? 'This Week' : (($timeframe ?? 'today') === 'month' ? 'This Month' : 'Today') }}"
                    :value="number_format($productionSummary['planned_quantity'] ?? 0)" subtitle="Scheduled units"
                    icon="feather-calendar" color="primary" variant="compact">
                    <x-slot name="footer">
                        Adherence: <span
                            class="fw-bold text-primary">{{ number_format($productionSummary['schedule_adherence'] ?? 100, 1) }}%</span>
                    </x-slot>
                </x-ui.stat-widget>
            </div>

            {{-- Card 3: In Progress --}}
            <div class="col-xl-2 col-md-4 col-sm-6">
                <x-ui.stat-widget title="In Progress" :value="number_format($orderStatusCounts['in_progress'] ?? 0)"
                    subtitle="On shop floor" icon="feather-activity" color="info" variant="compact">
                    <x-slot name="footer">
                        <span class="fw-semibold">{{ $orderStatusCounts['released'] ?? 0 }}</span> Released in queue
                    </x-slot>
                </x-ui.stat-widget>
            </div>

            {{-- Card 4: Completed Today / Period --}}
            <div class="col-xl-2 col-md-4 col-sm-6">
                <x-ui.stat-widget
                    title="Completed {{ ($timeframe ?? 'today') === 'week' ? 'This Week' : (($timeframe ?? 'today') === 'month' ? 'This Month' : 'Today') }}"
                    :value="number_format($mesCompletedTodayCount ?? 0)" subtitle="Operations finished"
                    icon="feather-check-circle" color="success" variant="compact">
                    <x-slot name="footer">
                        Yield: <span class="fw-semibold">{{ number_format($scrapStats['yield'] ?? 100, 1) }}%</span>
                    </x-slot>
                </x-ui.stat-widget>
            </div>

            {{-- Card 5: Pending QC --}}
            <div class="col-xl-2 col-md-4 col-sm-6">
                <x-ui.stat-widget title="Pending QC" :value="number_format($pendingQcCount ?? 0)"
                    subtitle="Waiting for quality check" icon="feather-shield" :color="($pendingQcCount ?? 0) > 0 ? 'warning' : 'primary'" variant="compact">
                    <x-slot name="footer">
                        <span class="fw-semibold">{{ $openNcrCount ?? 0 }}</span> Open NCRs
                    </x-slot>
                </x-ui.stat-widget>
            </div>

            {{-- Card 6: Material Blocked --}}
            <div class="col-xl-2 col-md-4 col-sm-6">
                <x-ui.stat-widget title="Material Blocked" :value="number_format($materialBlockersCount ?? 0)"
                    subtitle="Blocked by material issue" icon="feather-package" :color="($materialBlockersCount ?? 0) > 0 ? 'danger' : 'primary'" variant="compact">
                    <x-slot name="footer">
                        Pending Store Release
                    </x-slot>
                </x-ui.stat-widget>
            </div>
        </div>

        {{-- ── SECTION 3: UNIFIED ACTION CENTER ────────────────────────────────── --}}
        <div class="card dash-card border-0 shadow-sm rounded-3 mb-3">
            <div
                class="card-header py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="dash-card-icon-avatar bg-soft-primary text-primary">
                        <i class="feather-alert-triangle"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h6 class="card-title fw-bold text-dark mb-0 fs-14">Action Center</h6>
                            @if($totalActionExceptions > 0)
                                <span
                                    class="badge bg-soft-primary text-primary rounded-pill px-2.5 py-0.5 fs-11 fw-bold border border-primary border-opacity-10">
                                    {{ $totalActionExceptions }} Item(s) Need Attention
                                </span>
                            @else
                                <span
                                    class="badge bg-soft-primary text-primary rounded-pill px-2.5 py-0.5 fs-11 fw-bold border border-primary border-opacity-10">
                                    Everything is on track
                                </span>
                            @endif
                        </div>
                        <span class="fs-11 text-muted">Items that need your attention &bull; Critical Action Center</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <x-ui.button variant="primary" icon="feather-alert-octagon"
                        href="{{ route('production.planning-exceptions.index') }}">
                        Exception Engine
                    </x-ui.button>
                </div>
            </div>

            <div class="card-body p-0">
                @if($totalActionExceptions > 0)
                    <div class="table-responsive mb-0">
                        <table class="table table-hover align-middle mb-0 fs-13">
                            <thead class="dash-table-head fs-11 text-uppercase text-muted border-bottom">
                                <tr>
                                    <th class="ps-4" style="width: 220px;">Item</th>
                                    <th>Details</th>
                                    <th class="text-center" style="width: 110px;">Priority</th>
                                    <th class="text-center" style="width: 160px;">Status</th>
                                    <th class="text-center pe-4" style="width: 110px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- 1. Overdue Orders --}}
                                @if(($overdueOrdersCount ?? 0) > 0)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="feather-clock text-primary fs-15"></i>
                                                <span class="fw-semibold text-dark">Overdue Orders</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-dark">{{ number_format($overdueOrdersCount) }} production order(s)
                                                past planned delivery date.</span>
                                            @if($overdueOrdersList->first())
                                                <small class="text-muted ms-1 font-monospace">(e.g.
                                                    {{ $overdueOrdersList->first()->order_number }})</small>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-soft-primary text-primary fs-11 rounded-pill">High</span>
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="badge bg-soft-primary text-primary fs-11 rounded-pill font-monospace">Overdue</span>
                                        </td>
                                        <td class="text-center pe-4">
                                            <x-ui.button variant="primary" icon="feather-arrow-right" iconPosition="right"
                                                href="#worklist-tabs-section" onclick="activateTab('content-overdue-tab')">
                                                View
                                            </x-ui.button>
                                        </td>
                                    </tr>
                                @endif

                                {{-- 2. Machine Breakdown --}}
                                @if(($breakdownCount ?? 0) > 0)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="feather-alert-octagon text-primary fs-15"></i>
                                                <span class="fw-semibold text-dark">Machine Breakdown</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-dark">{{ number_format($breakdownCount) }} machine(s) currently halted
                                                due to equipment breakdown.</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-soft-primary text-primary fs-11 rounded-pill">Critical</span>
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="badge bg-soft-primary text-primary fs-11 rounded-pill font-monospace">Breakdown</span>
                                        </td>
                                        <td class="text-center pe-4">
                                            <x-ui.button variant="primary" icon="feather-arrow-right" iconPosition="right"
                                                href="{{ route('production.mes.machines.index', ['state' => 'Breakdown']) }}">
                                                View
                                            </x-ui.button>
                                        </td>
                                    </tr>
                                @endif

                                {{-- 3. Material Blockers --}}
                                @if(($materialBlockersCount ?? 0) > 0)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="feather-package text-primary fs-15"></i>
                                                <span class="fw-semibold text-dark">Material Blockers</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-dark">{{ number_format($materialBlockersCount) }} production order(s)
                                                waiting for store release / raw materials.</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-soft-primary text-primary fs-11 rounded-pill">High</span>
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="badge bg-soft-primary text-primary fs-11 rounded-pill font-monospace">Pending
                                                Store</span>
                                        </td>
                                        <td class="text-center pe-4">
                                            <x-ui.button variant="primary" icon="feather-arrow-right" iconPosition="right"
                                                href="{{ route('sales.material-requests.index') }}">
                                                View
                                            </x-ui.button>
                                        </td>
                                    </tr>
                                @endif

                                {{-- 4. Pending QC & NCR --}}
                                @if((($pendingQcCount ?? 0) + ($openNcrCount ?? 0)) > 0)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="feather-shield text-primary fs-15"></i>
                                                <span class="fw-semibold text-dark">Pending QC &amp; NCR</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-dark">{{ number_format($pendingQcCount) }} inspection(s) waiting audit
                                                &bull; {{ number_format($openNcrCount) }} active non-conformance(s).</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-soft-primary text-primary fs-11 rounded-pill">Medium</span>
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="badge bg-soft-primary text-primary fs-11 rounded-pill font-monospace">Waiting
                                                QC</span>
                                        </td>
                                        <td class="text-center pe-4">
                                            <x-ui.button variant="primary" icon="feather-arrow-right" iconPosition="right"
                                                href="{{ route('production.inspections.index') }}">
                                                View
                                            </x-ui.button>
                                        </td>
                                    </tr>
                                @endif

                                {{-- 5. Vendor Delays --}}
                                @if(($vendorDelayedCount ?? 0) > 0)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="feather-truck text-primary fs-15"></i>
                                                <span class="fw-semibold text-dark">Vendor Delays</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-dark">{{ number_format($vendorDelayedCount) }} outside subcontract
                                                operation(s) overdue beyond expected delivery.</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-soft-primary text-primary fs-11 rounded-pill">High</span>
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="badge bg-soft-primary text-primary fs-11 rounded-pill font-monospace">Delayed</span>
                                        </td>
                                        <td class="text-center pe-4">
                                            <x-ui.button variant="primary" icon="feather-arrow-right" iconPosition="right"
                                                href="{{ route('production.subcontract.analytics') }}">
                                                View
                                            </x-ui.button>
                                        </td>
                                    </tr>
                                @endif

                                {{-- 6. Pending Demand --}}
                                @if(($pendingSalesOrderCount ?? 0) > 0)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="feather-shopping-cart text-primary fs-15"></i>
                                                <span class="fw-semibold text-dark">Pending Demand</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-dark">{{ number_format($pendingSalesOrderCount) }} sales demand(s)
                                                awaiting production order creation.</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-soft-primary text-primary fs-11 rounded-pill">Medium</span>
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="badge bg-soft-primary text-primary fs-11 rounded-pill font-monospace">Draft</span>
                                        </td>
                                        <td class="text-center pe-4">
                                            <x-ui.button variant="primary" icon="feather-arrow-right" iconPosition="right"
                                                href="#worklist-tabs-section" onclick="activateTab('content-demand-tab')">
                                                View
                                            </x-ui.button>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4 px-3 my-2">
                        <div class="rounded-circle bg-soft-primary text-primary d-inline-flex align-items-center justify-content-center mb-2"
                            style="width: 44px; height: 44px;">
                            <i class="feather-check-circle fs-22"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1 fs-14">Everything is on track</h6>
                        <p class="text-muted fs-12 mb-0">No production exceptions require attention. All manufacturing lines and
                            orders are running normally.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- ── SECTION 4: PRODUCTION FLOW (5-Stage Horizontal Lifecycle) ────────── --}}
        <div class="card dash-card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header py-2.5 px-4 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="card-title fw-bold text-dark mb-0 fs-13">Production Flow</h6>
                    <span class="fs-11 text-muted">End-to-end manufacturing lifecycle pipeline</span>
                </div>
                <span class="fs-11 text-muted">Click any stage to drill down</span>
            </div>
            <div class="card-body p-3">
                <div class="production-flow-container">
                    {{-- Stage 1: Demand --}}
                    <a href="#worklist-tabs-section" onclick="activateTab('content-demand-tab')"
                        class="production-flow-step">
                        <div>
                            <span class="step-number">Stage 1</span>
                            <div class="step-title">Demand</div>
                        </div>
                        <div>
                            <div class="step-count">{{ number_format($pendingSalesOrderCount) }}</div>
                            <div class="step-desc">Pending demands</div>
                        </div>
                    </a>

                    <div class="production-flow-arrow"><i class="feather-chevron-right"></i></div>

                    {{-- Stage 2: Planning --}}
                    <a href="{{ route('production.plans.index') }}" class="production-flow-step">
                        <div>
                            <span class="step-number">Stage 2</span>
                            <div class="step-title">Planning</div>
                        </div>
                        <div>
                            <div class="step-count">{{ number_format(array_sum($planStatusCounts)) }}</div>
                            <div class="step-desc">Production plans</div>
                        </div>
                    </a>

                    <div class="production-flow-arrow"><i class="feather-chevron-right"></i></div>

                    {{-- Stage 3: Production Orders --}}
                    <a href="{{ route('production.orders.index') }}" class="production-flow-step">
                        <div>
                            <span class="step-number">Stage 3</span>
                            <div class="step-title">Production Orders</div>
                        </div>
                        <div>
                            <div class="step-count">{{ number_format($totalActiveOrders) }}</div>
                            <div class="step-desc">Active orders</div>
                        </div>
                    </a>

                    <div class="production-flow-arrow"><i class="feather-chevron-right"></i></div>

                    {{-- Stage 4: Shop Floor --}}
                    <a href="{{ route('production.mes.dashboard') }}" class="production-flow-step">
                        <div>
                            <span class="step-number">Stage 4</span>
                            <div class="step-title">Shop Floor</div>
                        </div>
                        <div>
                            <div class="step-count">{{ number_format($orderStatusCounts['in_progress'] ?? 0) }}</div>
                            <div class="step-desc">In progress</div>
                        </div>
                    </a>

                    <div class="production-flow-arrow"><i class="feather-chevron-right"></i></div>

                    {{-- Stage 5: Quality & Completion --}}
                    <a href="{{ route('production.quality.dashboard') }}" class="production-flow-step">
                        <div>
                            <span class="step-number">Stage 5</span>
                            <div class="step-title">Quality &amp; Completion</div>
                        </div>
                        <div>
                            <div class="step-count">{{ number_format($orderStatusCounts['completed'] ?? 0) }}</div>
                            <div class="step-desc">Completed orders</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        {{-- ── SECTION 5: PRODUCTION AREAS (6 Expandable Module Cards) ─────────── --}}
        <div class="mb-3">
            <div class="d-flex align-items-center justify-content-between mb-2 px-1">
                <h6 class="fw-bold text-dark mb-0 fs-14">Production Areas</h6>
                <span class="fs-11 text-muted">Click any area to expand detailed analytics</span>
            </div>

            {{-- ── 5.1 Production & Capacity ── --}}
            <div class="production-area-card shadow-sm">
                <div class="production-area-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center justify-content-between flex-grow-1 cursor-pointer py-1"
                        data-bs-toggle="collapse" data-bs-target="#area-capacity" aria-expanded="false" role="button">
                        <div class="d-flex align-items-center gap-3">
                            <div class="dash-card-icon-avatar bg-soft-primary text-primary">
                                <i class="feather-bar-chart-2"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark fs-14">Production &amp; Capacity</div>
                                @php
                                    $avgUtil = !empty($workCenterLoads) ? round(collect($workCenterLoads)->avg('utilization'), 1) : 0;
                                @endphp
                                <span class="fs-11 text-muted">
                                    Utilization <strong class="text-dark font-monospace">{{ $avgUtil }}%</strong> &bull;
                                    {{ count($workCenterLoads) }} work centers active &bull;
                                    Plant OEE: <strong
                                        class="text-dark font-monospace">{{ number_format($oeeKpi['current_value'] ?? 0, 1) }}%</strong>
                                </span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 pe-3">
                            <i class="feather-chevron-down collapse-chevron"></i>
                        </div>
                    </div>
                    <div class="production-area-actions border-start ps-3 flex-shrink-0" onclick="event.stopPropagation();">
                        <x-ui.button variant="light" icon="feather-arrow-right"
                            href="{{ route('production.mes.work-centers.index') }}" title="View Capacity"></x-ui.button>
                    </div>
                </div>
                <div class="collapse" id="area-capacity">
                    <div class="production-area-body">
                        {{-- Work-Center Capacity, Load & Bottleneck Pulse --}}
                        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                            <h6 class="fw-bold text-dark mb-0 fs-13">Work-Center Capacity &amp; Bottleneck Pulse</h6>
                            <div class="d-flex align-items-center gap-2">
                                <span
                                    class="badge bg-soft-primary text-primary fs-11 font-monospace rounded-pill px-2.5 py-1">
                                    Active Orders Pipeline: {{ $totalActiveOrders }} Active
                                </span>
                                <span
                                    class="badge bg-soft-primary text-primary fs-11 font-monospace rounded-pill px-2.5 py-1">
                                    Volume &amp; Adherence:
                                    {{ number_format($productionSummary['schedule_adherence'] ?? 100, 1) }}%
                                </span>
                                <span
                                    class="badge bg-soft-primary text-primary fs-11 font-monospace rounded-pill px-2.5 py-1">
                                    Plant OEE Score: {{ number_format($oeeKpi['current_value'] ?? 0, 1) }}%
                                </span>
                                <x-ui.button variant="light" icon="feather-layers"
                                    href="{{ route('production.mes.work-centers.index') }}">
                                    All Work Centers
                                </x-ui.button>
                            </div>
                        </div>

                        {{-- Bottleneck Spotlight --}}
                        @if(!empty($bottleneckWorkCenters) && $bottleneckWorkCenters->isNotEmpty())
                            <div class="dash-panel-subtle p-3 rounded-3 border mb-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-1.5 fs-13">
                                        <i class="feather-alert-octagon text-primary"></i>
                                        Bottleneck Spotlight: {{ $bottleneckWorkCenters->count() }} Work Center(s) Near or Above
                                        Target Capacity
                                    </h6>
                                    <small class="text-muted fs-11">Thresholds: Warning &ge;85%, Critical &gt;100%</small>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach($bottleneckWorkCenters as $bwc)
                                        <a href="{{ route('production.mes.work-centers.show', $bwc['id']) }}"
                                            class="badge bg-soft-primary text-primary p-2 text-decoration-none fs-11 d-inline-flex align-items-center gap-1.5 rounded-pill font-monospace">
                                            <i class="feather-alert-triangle text-primary"></i>
                                            {{ $bwc['name'] }} ({{ $bwc['code'] }}): {{ $bwc['utilization'] }}% Load
                                            ({{ $bwc['status'] }})
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div
                                class="dash-panel-subtle py-2.5 px-3 rounded-3 border mb-3 d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2 fs-12 text-muted">
                                    <i class="feather-check-circle fs-15 text-primary"></i>
                                    <span class="text-dark"><strong>Zero Bottlenecks Active!</strong> All work centers are
                                        operating within standard capacity limits (&lt;85% utilization).</span>
                                </div>
                                <span class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill">Capacity
                                    Balanced</span>
                            </div>
                        @endif

                        {{-- Work Center Load Cards Grid --}}
                        @if(!empty($workCenterLoads))
                            <div class="row g-3">
                                @foreach(array_slice($workCenterLoads, 0, 6) as $wcl)
                                    @php
                                        $isRunning = ($wcl['running_ops'] ?? 0) > 0;
                                    @endphp
                                    <div class="col-xl-4 col-md-6">
                                        <div
                                            class="dash-card-inner p-3 border rounded-3 h-100 hover-shadow {{ $isRunning ? 'work-center-active' : '' }}">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <div>
                                                    <div class="d-flex align-items-center gap-1.5">
                                                        @if($isRunning)
                                                            <span class="spinner-grow spinner-grow-sm text-primary flex-shrink-0"
                                                                style="width: 7px; height: 7px;" role="status"
                                                                title="Operations Currently Running"></span>
                                                        @endif
                                                        <a href="{{ route('production.mes.work-centers.show', $wcl['id']) }}"
                                                            class="fw-bold text-dark text-decoration-none fs-13">
                                                            {{ $wcl['name'] }}
                                                        </a>
                                                    </div>
                                                    <div class="fs-11 text-muted font-monospace">{{ $wcl['code'] }} &bull;
                                                        {{ $wcl['machine_count'] }} Machine(s)
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center gap-1">
                                                    @if($isRunning)
                                                        <span
                                                            class="badge bg-soft-success text-success border border-success-subtle fs-10 font-monospace rounded-pill d-inline-flex align-items-center gap-1 px-2 py-0.5 shadow-2xs">
                                                            <span class="spinner-grow spinner-grow-sm text-success"
                                                                style="width: 5px; height: 5px;" role="status"></span>
                                                            RUNNING
                                                        </span>
                                                    @endif
                                                    <span
                                                        class="badge {{ $wcl['status'] === 'Critical' ? 'bg-soft-danger text-danger' : ($wcl['status'] === 'Warning' ? 'bg-soft-warning text-warning' : 'bg-soft-primary text-primary') }} fs-11 font-monospace rounded-pill">
                                                        {{ $wcl['status'] }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-baseline justify-content-between mb-1">
                                                <span class="fs-12 text-muted">Planned / Available</span>
                                                <span class="fs-12 fw-bold font-monospace text-dark">
                                                    {{ $wcl['scheduled_hours'] }}h / {{ $wcl['capacity_hours'] }}h
                                                </span>
                                            </div>
                                            <div class="progress mb-2" style="height: 6px;">
                                                <div class="progress-bar {{ $isRunning ? 'bg-primary progress-bar-striped progress-bar-animated' : 'bg-primary' }}"
                                                    role="progressbar" style="width: {{ min(100, $wcl['utilization']) }}%"></div>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-between fs-11 text-muted">
                                                <span>Utilization: <strong
                                                        class="text-dark font-monospace">{{ $wcl['utilization'] }}%</strong></span>
                                                @if($isRunning)
                                                    <span
                                                        class="badge bg-soft-primary text-primary font-monospace px-2 py-0.5 rounded-pill d-inline-flex align-items-center gap-1">
                                                        <i class="feather-activity text-primary fs-11"></i>
                                                        <strong>{{ $wcl['running_ops'] }} Running</strong> | {{ $wcl['waiting_ops'] }}
                                                        Ready
                                                    </span>
                                                @else
                                                    <span><i class="feather-play text-muted me-1"></i>{{ $wcl['running_ops'] }} Running
                                                        | {{ $wcl['waiting_ops'] }} Ready</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-3 text-muted">
                                <i class="feather-inbox fs-24 d-block mb-1 opacity-50"></i>
                                <span>No active work centers configured for this tenant.</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ── 5.2 Materials & Inventory ── --}}
            <div class="production-area-card shadow-sm">
                <div class="production-area-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center justify-content-between flex-grow-1 cursor-pointer py-1"
                        data-bs-toggle="collapse" data-bs-target="#area-materials" aria-expanded="false" role="button">
                        <div class="d-flex align-items-center gap-3">
                            <div class="dash-card-icon-avatar bg-soft-primary text-primary">
                                <i class="feather-package"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark fs-14">Materials &amp; Inventory</div>
                                <span class="fs-11 text-muted">
                                    {{ $fullyIssuedCount }} ready to start &bull;
                                    {{ $materialBlockersCount }} material blocked &bull;
                                    {{ $requisitionSummary['total'] ?? 0 }} total requisitions
                                </span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 pe-3">
                            <i class="feather-chevron-down collapse-chevron"></i>
                        </div>
                    </div>
                    <div class="production-area-actions border-start ps-3 flex-shrink-0" onclick="event.stopPropagation();">
                        <x-ui.button variant="light" icon="feather-arrow-right"
                            href="{{ route('sales.material-requests.index') }}" title="Material Requests"></x-ui.button>
                    </div>
                </div>
                <div class="collapse" id="area-materials">
                    <div class="production-area-body">
                        {{-- Material Readiness Banner --}}
                        @if($fullyIssuedCount > 0)
                            @php $firstFullyOrder = $fullyIssuedOrders->first(); @endphp
                            <div
                                class="ready-start-banner p-3 mb-3 shadow-sm d-flex flex-wrap align-items-center justify-content-between gap-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="avatar-md bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center fs-20 flex-shrink-0"
                                        style="width: 44px; height: 44px;">
                                        <i class="feather-check-circle"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-dark mb-1 fs-13">
                                            <i class="feather-box me-1 text-primary"></i>
                                            @if($fullyIssuedCount === 1)
                                                Store Material Fully Issued for Order #{{ $firstFullyOrder->order_number }}!
                                            @else
                                                {{ $fullyIssuedCount }} Production Order(s) - Store Material Fully Issued!
                                            @endif
                                        </h6>
                                        <span class="fs-12 text-muted">
                                            Raw materials have been fully issued by warehouse store. Ready for scheduling and
                                            shop floor execution.
                                        </span>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <x-ui.button variant="primary" icon="feather-arrow-right-circle"
                                        onclick="activateTab('content-ready-tab')">
                                        View Ready Orders ({{ $fullyIssuedCount }})
                                    </x-ui.button>
                                </div>
                            </div>
                        @endif

                        {{-- Requisition Slips Track Grid --}}
                        <div class="row g-3">
                            <div class="col-md-3 col-sm-6">
                                <div class="p-3 dash-card-inner rounded-3 border text-center">
                                    <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">Fully Issued</span>
                                    <h4 class="fw-bold text-dark font-monospace fs-20 my-1">
                                        {{ number_format($requisitionSummary['fully_issued'] ?? 0) }}
                                    </h4>
                                    <small class="text-muted fs-11">Ready on shop floor</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="p-3 dash-card-inner rounded-3 border text-center">
                                    <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">Partially
                                        Issued</span>
                                    <h4 class="fw-bold text-dark font-monospace fs-20 my-1">
                                        {{ number_format($requisitionSummary['partially_issued'] ?? 0) }}
                                    </h4>
                                    <small class="text-muted fs-11">Partial store issue</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="p-3 dash-card-inner rounded-3 border text-center">
                                    <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">Pending Store</span>
                                    <h4 class="fw-bold text-dark font-monospace fs-20 my-1">
                                        {{ number_format($requisitionSummary['pending'] ?? 0) }}
                                    </h4>
                                    <small class="text-muted fs-11">Awaiting release</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="p-3 dash-card-inner rounded-3 border text-center">
                                    <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">Approved /
                                        Reserved</span>
                                    <h4 class="fw-bold text-dark font-monospace fs-20 my-1">
                                        {{ number_format($requisitionSummary['approved'] ?? 0) }}
                                    </h4>
                                    <small class="text-muted fs-11">Stock allocated</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── 5.3 Shop Floor ── --}}
            <div class="production-area-card shadow-sm">
                <div class="production-area-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center justify-content-between flex-grow-1 cursor-pointer py-1"
                        data-bs-toggle="collapse" data-bs-target="#area-shopfloor" aria-expanded="false" role="button">
                        <div class="d-flex align-items-center gap-3">
                            <div class="dash-card-icon-avatar bg-soft-primary text-primary">
                                <i class="feather-cpu"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark fs-14">Shop Floor</div>
                                <span class="fs-11 text-muted">
                                    {{ $machineStateCounts['running'] ?? 0 }} running &bull;
                                    {{ $machineStateCounts['idle'] ?? 0 }} idle &bull;
                                    {{ $machineStateCounts['breakdown'] ?? 0 }} breakdown &bull;
                                    {{ $mesRunningCount ?? 0 }} MES ops running
                                </span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 pe-3">
                            <i class="feather-chevron-down collapse-chevron"></i>
                        </div>
                    </div>
                    <div class="production-area-actions border-start ps-3 flex-shrink-0" onclick="event.stopPropagation();">
                        <x-ui.button variant="light" icon="feather-arrow-right"
                            href="{{ route('production.mes.dashboard') }}" title="Open Shop Floor"></x-ui.button>
                    </div>
                </div>
                <div class="collapse" id="area-shopfloor">
                    <div class="production-area-body">
                        {{-- Live Manufacturing Pulse & Andon Status --}}
                        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                            <h6 class="card-title fw-bold text-dark mb-0 fs-13">Live Manufacturing Pulse &amp; Andon Status
                            </h6>
                            <div class="d-flex align-items-center gap-2">
                                <span
                                    class="badge bg-soft-primary text-primary fs-11 font-monospace rounded-pill px-2.5 py-1">
                                    Shop Floor WIP: {{ number_format($wipSummary['total_items'] ?? 0) }} items
                                </span>
                                <span
                                    class="badge bg-soft-primary text-primary fs-11 font-monospace rounded-pill px-2.5 py-1">
                                    Active Tracking Jobs: {{ number_format($totalActiveOrders) }}
                                </span>
                                <x-ui.button variant="light" icon="feather-grid"
                                    href="{{ route('production.mes.machines.index') }}">
                                    Machines Directory
                                </x-ui.button>
                                <x-ui.button variant="primary" icon="feather-alert-triangle"
                                    href="{{ route('production.intelligence.andon') }}">
                                    Live Andon Board
                                </x-ui.button>
                            </div>
                        </div>

                        {{-- Machine States Grid --}}
                        <div class="row g-2 mb-3">
                            <div class="col-6 col-sm-4 col-md-2">
                                <a href="{{ route('production.mes.machines.index', ['state' => 'Running']) }}"
                                    class="text-decoration-none">
                                    <div
                                        class="dash-tile dash-tile-default machine-state-box border border-primary hover-shadow">
                                        <div class="d-flex align-items-center justify-content-center gap-1.5 mb-1">
                                            <span class="spinner-grow spinner-grow-sm text-primary"
                                                style="width: 8px; height: 8px;"></span>
                                            <span class="fs-11 fw-bold text-primary text-uppercase">Running</span>
                                        </div>
                                        <h3 class="fw-bold text-dark mb-0 font-monospace fs-18">
                                            {{ number_format($machineStateCounts['running'] ?? 0) }}
                                        </h3>
                                        <small class="text-muted fs-11">Active Line</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6 col-sm-4 col-md-2">
                                <a href="{{ route('production.mes.machines.index', ['state' => 'Idle']) }}"
                                    class="text-decoration-none">
                                    <div class="dash-tile dash-tile-default machine-state-box border hover-shadow">
                                        <span class="fs-11 fw-bold text-muted text-uppercase d-block mb-1">Idle</span>
                                        <h3 class="fw-bold text-dark mb-0 font-monospace fs-18">
                                            {{ number_format($machineStateCounts['idle'] ?? 0) }}
                                        </h3>
                                        <small class="text-muted fs-11">Awaiting Jobs</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6 col-sm-4 col-md-2">
                                <a href="{{ route('production.mes.machines.index', ['state' => 'Setup']) }}"
                                    class="text-decoration-none">
                                    <div class="dash-tile dash-tile-default machine-state-box border hover-shadow">
                                        <span class="fs-11 fw-bold text-muted text-uppercase d-block mb-1">Setup</span>
                                        <h3 class="fw-bold text-dark mb-0 font-monospace fs-18">
                                            {{ number_format($machineStateCounts['setup'] ?? 0) }}
                                        </h3>
                                        <small class="text-muted fs-11">Tooling / Change</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6 col-sm-4 col-md-2">
                                <a href="{{ route('production.mes.machines.index', ['state' => 'Breakdown']) }}"
                                    class="text-decoration-none">
                                    <div
                                        class="dash-tile dash-tile-default machine-state-box border {{ ($machineStateCounts['breakdown'] ?? 0) > 0 ? 'border-primary' : '' }} hover-shadow">
                                        <span class="fs-11 fw-bold text-muted text-uppercase d-block mb-1">Breakdown</span>
                                        <h3 class="fw-bold text-dark mb-0 font-monospace fs-18">
                                            {{ number_format($machineStateCounts['breakdown'] ?? 0) }}
                                        </h3>
                                        <small class="text-muted fs-11">Halted</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6 col-sm-4 col-md-2">
                                <a href="{{ route('production.mes.machines.index', ['state' => 'Maintenance']) }}"
                                    class="text-decoration-none">
                                    <div
                                        class="dash-tile dash-tile-default machine-state-box border {{ ($machineStateCounts['maintenance'] ?? 0) > 0 ? 'border-primary' : '' }} hover-shadow">
                                        <span
                                            class="fs-11 fw-bold text-muted text-uppercase d-block mb-1">Maintenance</span>
                                        <h3 class="fw-bold text-dark mb-0 font-monospace fs-18">
                                            {{ number_format($machineStateCounts['maintenance'] ?? 0) }}
                                        </h3>
                                        <small class="text-muted fs-11">PM Servicing</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6 col-sm-4 col-md-2">
                                <a href="{{ route('production.mes.machines.index', ['state' => 'Offline']) }}"
                                    class="text-decoration-none">
                                    <div class="dash-tile dash-tile-default machine-state-box border hover-shadow">
                                        <span class="fs-11 fw-bold text-muted text-uppercase d-block mb-1">Offline</span>
                                        <h3 class="fw-bold text-dark mb-0 font-monospace fs-18">
                                            {{ number_format($machineStateCounts['offline'] ?? 0) }}
                                        </h3>
                                        <small class="text-muted fs-11">Powered Down</small>
                                    </div>
                                </a>
                            </div>
                        </div>

                        {{-- Equipment Requiring Immediate Attention --}}
                        @if(!empty($attentionMachines) && $attentionMachines->isNotEmpty())
                            <div class="dash-panel-subtle p-3 rounded-3 border mb-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-1.5 fs-13">
                                        <i class="feather-alert-triangle text-primary"></i>
                                        Equipment Requiring Immediate Attention ({{ $attentionMachines->count() }})
                                    </h6>
                                    <x-ui.button variant="primary" icon="feather-alert-triangle"
                                        href="{{ route('production.intelligence.andon') }}">
                                        Open Full Andon Board
                                    </x-ui.button>
                                </div>
                                <div class="row g-2">
                                    @foreach($attentionMachines as $m)
                                        <div class="col-md-4 col-sm-6">
                                            <div class="p-2.5 dash-card-inner rounded-3 border h-100 shadow-2xs">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <a href="{{ route('production.mes.machines.show', $m['machine_id']) }}"
                                                        class="fw-bold text-dark font-monospace fs-12 text-decoration-none">
                                                        {{ $m['code'] }} - {{ $m['name'] }}
                                                    </a>
                                                    <span
                                                        class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill">
                                                        {{ $m['current_state'] }}
                                                    </span>
                                                </div>
                                                <div class="fs-11 text-muted mt-1">
                                                    <i class="feather-map-pin me-1 text-primary"></i>{{ $m['work_center_name'] }}
                                                    @if(!empty($m['operator_name']) && $m['operator_name'] !== '—')
                                                        | <i class="feather-user me-1 text-primary"></i>{{ $m['operator_name'] }}
                                                    @endif
                                                </div>
                                                @if(!empty($m['current_state_reason']) && $m['current_state_reason'] !== '—')
                                                    <div class="fs-11 text-muted mt-1">
                                                        <i class="feather-info me-1 text-primary"></i>{{ $m['current_state_reason'] }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Shop Floor MES Execution Pulse Strip --}}
                        <div
                            class="dash-panel-subtle p-3 rounded-3 border d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="dash-card-icon-avatar bg-soft-primary text-primary rounded-3">
                                    <i class="feather-activity"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark mb-0 fs-13">Shop Floor MES Execution Pulse</h6>
                                    <div class="d-flex flex-wrap gap-2 mt-1 fs-12">
                                        <span><strong
                                                class="text-dark font-monospace">{{ number_format($mesRunningCount ?? 0) }}</strong>
                                            Running Ops</span>
                                        <span class="text-muted">&bull;</span>
                                        <span><strong
                                                class="text-primary font-monospace">{{ number_format($mesReadyCount ?? 0) }}</strong>
                                            Ready in Queue</span>
                                        <span class="text-muted">&bull;</span>
                                        <span><strong
                                                class="text-dark font-monospace">{{ number_format($mesPausedCount ?? 0) }}</strong>
                                            Paused</span>
                                        <span class="text-muted">&bull;</span>
                                        <span><strong
                                                class="text-dark font-monospace">{{ number_format($mesCompletedTodayCount ?? 0) }}</strong>
                                            Completed Today</span>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <x-ui.button variant="primary" icon="feather-monitor"
                                    href="{{ route('production.mes.dashboard') }}">
                                    Open Shop Floor / MES
                                </x-ui.button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── 5.4 Quality Control ── --}}
            <div class="production-area-card shadow-sm">
                <div class="production-area-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center justify-content-between flex-grow-1 cursor-pointer py-1"
                        data-bs-toggle="collapse" data-bs-target="#area-quality" aria-expanded="false" role="button">
                        <div class="d-flex align-items-center gap-3">
                            <div class="dash-card-icon-avatar bg-soft-primary text-primary">
                                <i class="feather-check-circle"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark fs-14">Quality Control</div>
                                <span class="fs-11 text-muted">
                                    FPY <strong
                                        class="text-dark font-monospace">{{ number_format($qualityKpis['fpy'] ?? 100, 1) }}%</strong>
                                    &bull;
                                    {{ number_format($qualityKpis['totalInspections'] ?? 0) }} inspections &bull;
                                    {{ number_format($qualityKpis['ncrOpen'] ?? 0) }} open NCRs &bull;
                                    {{ number_format($qualityKpis['capaOpen'] ?? 0) }} active CAPAs
                                </span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 pe-3">
                            <i class="feather-chevron-down collapse-chevron"></i>
                        </div>
                    </div>
                    <div class="production-area-actions border-start ps-3 flex-shrink-0" onclick="event.stopPropagation();">
                        <x-ui.button variant="light" icon="feather-arrow-right"
                            href="{{ route('production.quality.dashboard') }}" title="Quality Center"></x-ui.button>
                    </div>
                </div>
                <div class="collapse" id="area-quality">
                    <div class="production-area-body">
                        {{-- Quality Intelligence & Defect Analytics --}}
                        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                            <h6 class="card-title fw-bold text-dark mb-0 fs-13">Quality Intelligence &amp; Defect Analytics
                            </h6>
                            <div class="d-flex align-items-center gap-2">
                                <x-ui.button variant="light" icon="feather-clipboard"
                                    href="{{ route('production.inspections.index') }}">
                                    All Inspections
                                </x-ui.button>
                                <x-ui.button variant="primary" icon="feather-award"
                                    href="{{ route('production.quality.dashboard') }}">
                                    Quality Dashboard
                                </x-ui.button>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            {{-- First Pass Yield (FPY) --}}
                            <div class="col-xl-3 col-sm-6">
                                <div class="dash-tile dash-tile-default quality-tile-box border h-100">
                                    <div class="d-flex align-items-center justify-content-between mb-1.5">
                                        <span class="fs-11 fw-bold text-muted text-uppercase">First Pass Yield</span>
                                        <span
                                            class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill">FPY</span>
                                    </div>
                                    <h2 class="fw-bold text-dark my-1 font-monospace fs-24">
                                        {{ number_format($qualityKpis['fpy'] ?? 100, 1) }}%
                                    </h2>
                                    <small class="text-muted fs-11">Final Stage Acceptance Rate</small>
                                </div>
                            </div>

                            {{-- Inspections Gate Track --}}
                            <div class="col-xl-3 col-sm-6">
                                <div class="dash-tile dash-tile-default quality-tile-box border h-100">
                                    <div class="d-flex align-items-center justify-content-between mb-1.5">
                                        <span class="fs-11 fw-bold text-muted text-uppercase">Inspections Gate</span>
                                        <span
                                            class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill">{{ number_format($qualityKpis['totalInspections'] ?? 0) }}
                                            Total</span>
                                    </div>
                                    <div class="d-flex align-items-baseline gap-3 my-1">
                                        <div>
                                            <span
                                                class="fs-18 fw-bold text-primary font-monospace">{{ number_format($qualityKpis['passedInspections'] ?? 0) }}</span>
                                            <small class="text-muted d-block fs-10">Passed</small>
                                        </div>
                                        <div class="border-start ps-3">
                                            <span
                                                class="fs-18 fw-bold text-dark font-monospace">{{ number_format($qualityKpis['failedInspections'] ?? 0) }}</span>
                                            <small class="text-muted d-block fs-10">Failed</small>
                                        </div>
                                        <div class="border-start ps-3">
                                            <span
                                                class="fs-18 fw-bold text-dark font-monospace">{{ number_format($qualityKpis['pendingInspections'] ?? 0) }}</span>
                                            <small class="text-muted d-block fs-10">Pending</small>
                                        </div>
                                    </div>
                                    <small class="text-muted fs-11">Receiving, in-line &amp; final audits</small>
                                </div>
                            </div>

                            {{-- Active NCRs & CAPAs --}}
                            <div class="col-xl-3 col-sm-6">
                                <div
                                    class="dash-tile dash-tile-default quality-tile-box border {{ ($qualityKpis['ncrOpen'] ?? 0) > 0 ? 'border-primary' : '' }} h-100">
                                    <div class="d-flex align-items-center justify-content-between mb-1.5">
                                        <span class="fs-11 fw-bold text-muted text-uppercase">Non-Conformances</span>
                                        @if(($qualityKpis['ncrOpen'] ?? 0) > 0)
                                            <span
                                                class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill">Action
                                                Required</span>
                                        @else
                                            <span
                                                class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill">Zero
                                                Open</span>
                                        @endif
                                    </div>
                                    <div class="d-flex align-items-baseline gap-3 my-1">
                                        <div>
                                            <span
                                                class="fs-18 fw-bold text-dark font-monospace">{{ number_format($qualityKpis['ncrOpen'] ?? 0) }}</span>
                                            <small class="text-muted d-block fs-10">Open NCRs
                                                ({{ $qualityKpis['ncrClosed'] ?? 0 }} Closed)</small>
                                        </div>
                                        <div class="border-start ps-3">
                                            <span
                                                class="fs-18 fw-bold text-primary font-monospace">{{ number_format($qualityKpis['capaOpen'] ?? 0) }}</span>
                                            <small class="text-muted d-block fs-10">Active CAPAs</small>
                                        </div>
                                    </div>
                                    <small class="text-muted fs-11">Corrective action loop</small>
                                </div>
                            </div>

                            {{-- Dispositions: Rework & Scrap --}}
                            <div class="col-xl-3 col-sm-6">
                                <div class="dash-tile dash-tile-default quality-tile-box border h-100">
                                    <div class="d-flex align-items-center justify-content-between mb-1.5">
                                        <span class="fs-11 fw-bold text-muted text-uppercase">Dispositions</span>
                                        <span
                                            class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill">Rework
                                            &amp; Scrap</span>
                                    </div>
                                    <div class="d-flex align-items-baseline gap-3 my-1">
                                        <div>
                                            <span
                                                class="fs-18 fw-bold text-dark font-monospace">{{ number_format($qualityKpis['reworkCount'] ?? 0) }}</span>
                                            <small class="text-muted d-block fs-10">Rework Orders</small>
                                        </div>
                                        <div class="border-start ps-3">
                                            <span
                                                class="fs-18 fw-bold text-dark font-monospace">{{ number_format($qualityKpis['scrapCount'] ?? 0) }}</span>
                                            <small class="text-muted d-block fs-10">Scrap Disposals</small>
                                        </div>
                                    </div>
                                    <small class="text-muted fs-11">Material resolution dispositions</small>
                                </div>
                            </div>
                        </div>

                        {{-- Top Defect Categories --}}
                        <div
                            class="dash-panel-subtle p-3 rounded-3 border d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span
                                    class="fs-11 fw-bold text-muted text-uppercase d-inline-flex align-items-center gap-1">
                                    <i class="feather-alert-octagon text-primary"></i> Top Defect Categories:
                                </span>
                                @if(!empty($topDefectCategories) && $topDefectCategories->isNotEmpty())
                                    <div class="d-flex flex-wrap gap-1.5">
                                        @foreach($topDefectCategories as $cat)
                                            <span class="dash-card-inner text-dark border shadow-xs fs-11 rounded-pill px-2.5 py-1">
                                                {{ $cat->category }}: <strong
                                                    class="text-primary font-monospace">{{ $cat->count }}</strong>
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span
                                        class="badge bg-soft-primary text-primary fs-11 font-monospace rounded-pill px-2.5 py-1">Zero
                                        Quality Non-Conformances active</span>
                                @endif
                            </div>
                            <div class="fs-11 text-muted">
                                Quality clearance guard active on all warehouse receipts
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── 5.5 Maintenance & Resources ── --}}
            <div class="production-area-card shadow-sm">
                <div class="production-area-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center justify-content-between flex-grow-1 cursor-pointer py-1"
                        data-bs-toggle="collapse" data-bs-target="#area-maintenance" aria-expanded="false" role="button">
                        <div class="d-flex align-items-center gap-3">
                            <div class="dash-card-icon-avatar bg-soft-primary text-primary">
                                <i class="feather-tool"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark fs-14">Maintenance &amp; Resources</div>
                                <span class="fs-11 text-muted">
                                    {{ $overduePmCount }} overdue PM &bull;
                                    {{ $duePmCount }} PM due (7d) &bull;
                                    {{ $openBreakdownWosCount }} breakdown WOs &bull;
                                    Subcontract OTD: <strong
                                        class="text-dark font-monospace">{{ number_format($subcontractDelivery['on_time_delivery_pct'] ?? 100, 1) }}%</strong>
                                </span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 pe-3">
                            <i class="feather-chevron-down collapse-chevron"></i>
                        </div>
                    </div>
                    <div class="production-area-actions border-start ps-3 flex-shrink-0" onclick="event.stopPropagation();">
                        <x-ui.button variant="light" icon="feather-arrow-right"
                            href="{{ route('production.maintenance.dashboard') }}" title="Maintenance Hub"></x-ui.button>
                    </div>
                </div>
                <div class="collapse" id="area-maintenance">
                    <div class="production-area-body">
                        {{-- Plant Maintenance & Subcontracting SLA --}}
                        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                            <h6 class="card-title fw-bold text-dark mb-0 fs-13">Plant Maintenance &amp; Subcontracting SLA
                            </h6>
                            <div class="d-flex align-items-center gap-2">
                                <x-ui.button variant="light" icon="feather-file-text"
                                    href="{{ route('production.maintenance.work-orders.index') }}">
                                    Work Orders
                                </x-ui.button>
                                <x-ui.button variant="light" icon="feather-tool"
                                    href="{{ route('production.maintenance.dashboard') }}">
                                    Maintenance Hub
                                </x-ui.button>
                                <x-ui.button variant="primary" icon="feather-truck"
                                    href="{{ route('production.subcontract.analytics') }}">
                                    Vendor Analytics
                                </x-ui.button>
                            </div>
                        </div>

                        {{-- Maintenance Tiles --}}
                        <div class="row g-3 mb-3">
                            <div class="col-md-3 col-sm-6">
                                <a href="{{ route('production.maintenance.schedules.index') }}"
                                    class="text-decoration-none">
                                    <div
                                        class="dash-tile dash-tile-default maintenance-tile-box border {{ $overduePmCount > 0 ? 'border-primary' : '' }} hover-shadow">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">Overdue PM</span>
                                        <h3 class="fw-bold text-dark my-1 font-monospace fs-22">
                                            {{ number_format($overduePmCount) }}
                                        </h3>
                                        <small class="text-muted fs-11">Preventive Due</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <a href="{{ route('production.maintenance.schedules.index') }}"
                                    class="text-decoration-none">
                                    <div class="dash-tile dash-tile-default maintenance-tile-box border hover-shadow">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">PM Due (7
                                            Days)</span>
                                        <h3 class="fw-bold text-dark my-1 font-monospace fs-22">
                                            {{ number_format($duePmCount) }}
                                        </h3>
                                        <small class="text-muted fs-11">Upcoming Service</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <a href="{{ route('production.maintenance.work-orders.index', ['type' => 'breakdown']) }}"
                                    class="text-decoration-none">
                                    <div
                                        class="dash-tile dash-tile-default maintenance-tile-box border {{ $openBreakdownWosCount > 0 ? 'border-primary' : '' }} hover-shadow">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">Breakdown
                                            WOs</span>
                                        <h3 class="fw-bold text-dark my-1 font-monospace fs-22">
                                            {{ number_format($openBreakdownWosCount) }}
                                        </h3>
                                        <small class="text-muted fs-11">Stoppage Orders</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <a href="{{ route('production.mes.machines.index', ['status' => 'under_maintenance']) }}"
                                    class="text-decoration-none">
                                    <div class="dash-tile dash-tile-default maintenance-tile-box border hover-shadow">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">Under
                                            Maintenance</span>
                                        <h3 class="fw-bold text-dark my-1 font-monospace fs-22">
                                            {{ number_format($machinesUnderMaintenanceCount) }}
                                        </h3>
                                        <small class="text-muted fs-11">Out of Service</small>
                                    </div>
                                </a>
                            </div>
                        </div>

                        {{-- Subcontracting SLA & External Operations Strip --}}
                        @if(!empty($subcontractMetrics))
                            <div class="pt-3 border-top">
                                <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span
                                            class="fs-11 fw-bold text-uppercase text-muted d-inline-flex align-items-center gap-1">
                                            <i class="feather-truck text-primary"></i> Vendor SLA &amp; External Operations:
                                        </span>
                                        <span
                                            class="badge bg-soft-primary text-primary fs-11 font-monospace rounded-pill px-2.5 py-1">
                                            OTD: {{ number_format($subcontractDelivery['on_time_delivery_pct'] ?? 100, 1) }}%
                                        </span>
                                        @if(($subcontractDelivery['avg_late_delay_days'] ?? 0) > 0)
                                            <span
                                                class="badge bg-soft-primary text-primary fs-11 font-monospace rounded-pill px-2.5 py-1">
                                                Avg Delay: +{{ number_format($subcontractDelivery['avg_late_delay_days'], 1) }}d
                                            </span>
                                        @endif
                                    </div>
                                    <span class="fs-11 text-muted">Outside subcontracting pipeline</span>
                                </div>
                                <div class="row g-2 text-center">
                                    <div class="col">
                                        <a href="{{ route('production.orders.index', ['filter' => 'subcontract_awaiting_pr']) }}"
                                            class="text-decoration-none">
                                            <div
                                                class="dash-tile dash-tile-default subcontract-pipeline-box border hover-shadow">
                                                <span class="fs-10 text-muted text-uppercase fw-bold d-block mb-0.5">Awaiting
                                                    PR</span>
                                                <h5 class="text-dark fw-bold mb-0 my-1 font-monospace fs-15">
                                                    {{ number_format($subcontractMetrics['awaiting_subcontract_pr'] ?? 0) }}
                                                </h5>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col">
                                        <a href="{{ route('purchase.orders.index', ['type' => 'subcontract', 'status' => 'draft']) }}"
                                            class="text-decoration-none">
                                            <div
                                                class="dash-tile dash-tile-default subcontract-pipeline-box border hover-shadow">
                                                <span class="fs-10 text-muted text-uppercase fw-bold d-block mb-0.5">PO
                                                    Awaiting</span>
                                                <h5 class="text-dark fw-bold mb-0 my-1 font-monospace fs-15">
                                                    {{ number_format($subcontractMetrics['po_awaiting_approval'] ?? 0) }}
                                                </h5>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col">
                                        <a href="{{ route('production.orders.index', ['filter' => 'subcontract_ready_dispatch']) }}"
                                            class="text-decoration-none">
                                            <div
                                                class="dash-tile dash-tile-default subcontract-pipeline-box border hover-shadow">
                                                <span class="fs-10 text-muted text-uppercase fw-bold d-block mb-0.5">Ready
                                                    Dispatch</span>
                                                <h5 class="text-dark fw-bold mb-0 my-1 font-monospace fs-15">
                                                    {{ number_format($subcontractMetrics['ready_for_dispatch'] ?? 0) }}
                                                </h5>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col">
                                        <a href="{{ route('production.orders.index', ['filter' => 'subcontract_at_vendor']) }}"
                                            class="text-decoration-none">
                                            <div
                                                class="dash-tile dash-tile-default subcontract-pipeline-box border hover-shadow">
                                                <span class="fs-10 text-muted text-uppercase fw-bold d-block mb-0.5">At
                                                    Vendor</span>
                                                <h5 class="text-dark fw-bold mb-0 my-1 font-monospace fs-15">
                                                    {{ number_format($subcontractMetrics['at_vendor'] ?? 0) }}
                                                </h5>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col">
                                        <a href="{{ route('production.orders.index', ['filter' => 'subcontract_delayed']) }}"
                                            class="text-decoration-none">
                                            <div
                                                class="dash-tile dash-tile-default subcontract-pipeline-box border hover-shadow">
                                                <span
                                                    class="fs-10 text-muted text-uppercase fw-bold d-block mb-0.5">Delayed</span>
                                                <h5 class="text-dark fw-bold mb-0 my-1 font-monospace fs-15">
                                                    {{ number_format($subcontractMetrics['vendor_delayed'] ?? 0) }}
                                                </h5>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col">
                                        <a href="{{ route('production.inspections.index', ['type' => 'subcontract']) }}"
                                            class="text-decoration-none">
                                            <div
                                                class="dash-tile dash-tile-default subcontract-pipeline-box border hover-shadow">
                                                <span class="fs-10 text-muted text-uppercase fw-bold d-block mb-0.5">QC
                                                    Pending</span>
                                                <h5 class="text-dark fw-bold mb-0 my-1 font-monospace fs-15">
                                                    {{ number_format($subcontractMetrics['subcontract_qc_pending'] ?? 0) }}
                                                </h5>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col">
                                        <a href="{{ route('production.rework.index') }}" class="text-decoration-none">
                                            <div
                                                class="dash-tile dash-tile-default subcontract-pipeline-box border hover-shadow">
                                                <span class="fs-10 text-muted text-uppercase fw-bold d-block mb-0.5">Vendor
                                                    Rework</span>
                                                <h5 class="text-dark fw-bold mb-0 my-1 font-monospace fs-15">
                                                    {{ number_format($subcontractMetrics['vendor_rework'] ?? 0) }}
                                                </h5>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ── 5.6 Planning & Reports ── --}}
            <div class="production-area-card shadow-sm">
                <div class="production-area-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center justify-content-between flex-grow-1 cursor-pointer py-1"
                        data-bs-toggle="collapse" data-bs-target="#area-planning" aria-expanded="false" role="button">
                        <div class="d-flex align-items-center gap-3">
                            <div class="dash-card-icon-avatar bg-soft-primary text-primary">
                                <i class="feather-trending-up"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark fs-14">Planning &amp; Reports</div>
                                <span class="fs-11 text-muted">
                                    Schedule adherence <strong
                                        class="text-dark font-monospace">{{ number_format($productionSummary['schedule_adherence'] ?? 100, 1) }}%</strong>
                                    &bull;
                                    Duration efficiency <strong
                                        class="text-dark font-monospace">{{ number_format($durationEfficiency, 1) }}%</strong>
                                    &bull;
                                    {{ $atRiskOrders->count() }} near-term risk
                                </span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 pe-3">
                            <i class="feather-chevron-down collapse-chevron"></i>
                        </div>
                    </div>
                    <div class="production-area-actions border-start ps-3 flex-shrink-0" onclick="event.stopPropagation();">
                        <x-ui.button variant="light" icon="feather-arrow-right"
                            href="{{ route('production.variances.index') }}" title="Variance Analysis"></x-ui.button>
                    </div>
                </div>
                <div class="collapse" id="area-planning">
                    <div class="production-area-body">
                        {{-- Execution Variance, Order Risk & Planning Pulse --}}
                        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                            <h6 class="card-title fw-bold text-dark mb-0 fs-13">Execution Variance, Order Risk &amp;
                                Planning Pulse</h6>
                            <div class="d-flex align-items-center gap-2">
                                <x-ui.button variant="light" icon="feather-activity"
                                    href="{{ route('production.variances.index') }}">
                                    Routing Variances
                                </x-ui.button>
                                <x-ui.button variant="light" icon="feather-calendar"
                                    href="{{ route('production.plans.index') }}">
                                    Master Plans
                                </x-ui.button>
                                <x-ui.button variant="primary" icon="feather-git-pull-request"
                                    href="{{ route('production.ecos.index') }}">
                                    ECO Hub
                                </x-ui.button>
                            </div>
                        </div>

                        {{-- Six Big Losses & Cycle Time Intelligence --}}
                        <div class="mb-3 p-3 rounded-3 dash-panel-subtle border">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="fs-11 fw-bold text-uppercase text-muted">TPM Six Big Losses
                                    Classification</span>
                                <small class="text-muted fs-11">Overall Downtime Rate: <strong
                                        class="text-dark font-monospace">{{ number_format($downtimeRate, 1) }}%</strong></small>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-2 col-sm-4 col-6">
                                    <div class="dash-tile dash-tile-default loss-tile-box border hover-shadow">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">Equipment
                                            Failure</span>
                                        <h4 class="fw-bold text-dark my-1 font-monospace fs-16">
                                            {{ number_format($sixBigLosses['equipment_failure_minutes'] ?? 0, 1) }}m
                                        </h4>
                                        <small class="text-muted fs-10">Breakdowns</small>
                                    </div>
                                </div>
                                <div class="col-md-2 col-sm-4 col-6">
                                    <div class="dash-tile dash-tile-default loss-tile-box border hover-shadow">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">Setup &amp;
                                            Adjust</span>
                                        <h4 class="fw-bold text-dark my-1 font-monospace fs-16">
                                            {{ number_format($sixBigLosses['setup_adjustment_minutes'] ?? 0, 1) }}m
                                        </h4>
                                        <small class="text-muted fs-10">Tooling/Change</small>
                                    </div>
                                </div>
                                <div class="col-md-2 col-sm-4 col-6">
                                    <div class="dash-tile dash-tile-default loss-tile-box border hover-shadow">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">Minor
                                            Stops</span>
                                        <h4 class="fw-bold text-dark my-1 font-monospace fs-16">
                                            {{ number_format($sixBigLosses['minor_stops_minutes'] ?? 0, 1) }}m
                                        </h4>
                                        <small class="text-muted fs-10">Idling &lt; 5m</small>
                                    </div>
                                </div>
                                <div class="col-md-2 col-sm-4 col-6">
                                    <div class="dash-tile dash-tile-default loss-tile-box border hover-shadow">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">Reduced
                                            Speed</span>
                                        <h4 class="fw-bold text-dark my-1 font-monospace fs-16">
                                            {{ number_format($sixBigLosses['reduced_speed_minutes'] ?? 0, 1) }}m
                                        </h4>
                                        <small class="text-muted fs-10">Slow Pace</small>
                                    </div>
                                </div>
                                <div class="col-md-2 col-sm-4 col-6">
                                    <div class="dash-tile dash-tile-default loss-tile-box border hover-shadow">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">Startup
                                            Rejects</span>
                                        <h4 class="fw-bold text-dark my-1 font-monospace fs-16">
                                            {{ number_format($sixBigLosses['startup_rejects_count'] ?? 0) }}
                                        </h4>
                                        <small class="text-muted fs-10">First-Run</small>
                                    </div>
                                </div>
                                <div class="col-md-2 col-sm-4 col-6">
                                    <div class="dash-tile dash-tile-default loss-tile-box border hover-shadow">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">Production
                                            Scrap</span>
                                        <h4 class="fw-bold text-dark my-1 font-monospace fs-16">
                                            {{ number_format($sixBigLosses['production_rejects_count'] ?? 0) }}
                                        </h4>
                                        <small class="text-muted fs-10">In-Process</small>
                                    </div>
                                </div>
                            </div>

                            {{-- Cycle Times & Asset Utilizations Strip --}}
                            <div class="row g-3 pt-3 mt-1 border-top">
                                <div class="col-md-6">
                                    <div
                                        class="p-3 rounded-3 dash-card-inner border h-100 d-flex flex-column justify-content-between">
                                        <span class="fs-11 fw-bold text-uppercase text-muted d-block mb-3">
                                            <i class="feather-clock me-1 text-primary"></i>Cycle Times &amp; Waiting
                                            Averages
                                        </span>
                                        <div class="row g-2 text-center">
                                            <div class="col-3 py-2">
                                                <small class="text-muted fs-11 d-block mb-1">Setup</small>
                                                <strong
                                                    class="font-monospace text-dark fs-14">{{ number_format($cycleTimes['avg_setup_time'] ?? 0, 1) }}m</strong>
                                            </div>
                                            <div class="col-3 border-start py-2">
                                                <small class="text-muted fs-11 d-block mb-1">Processing</small>
                                                <strong
                                                    class="font-monospace text-dark fs-14">{{ number_format($cycleTimes['avg_processing_time'] ?? 0, 1) }}m</strong>
                                            </div>
                                            <div class="col-3 border-start py-2">
                                                <small class="text-muted fs-11 d-block mb-1">Total Cycle</small>
                                                <strong
                                                    class="font-monospace text-primary fs-14">{{ number_format($cycleTimes['avg_cycle_time'] ?? 0, 1) }}m</strong>
                                            </div>
                                            <div class="col-3 border-start py-2">
                                                <small class="text-muted fs-11 d-block mb-1">Waiting</small>
                                                <strong
                                                    class="font-monospace text-dark fs-14">{{ number_format($cycleTimes['avg_waiting_time'] ?? 0, 1) }}m</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div
                                        class="p-3 rounded-3 dash-card-inner border h-100 d-flex flex-column justify-content-between">
                                        <span class="fs-11 fw-bold text-uppercase text-muted d-block mb-3">
                                            <i class="feather-cpu me-1 text-primary"></i>Resource Utilizations
                                        </span>
                                        <div class="row g-2 text-center">
                                            <div class="col-4 py-2">
                                                <small class="text-muted fs-11 d-block mb-1">Machines</small>
                                                <strong
                                                    class="font-monospace text-dark fs-14">{{ number_format($assetUtilizations['machine_utilization'] ?? 0, 1) }}%</strong>
                                            </div>
                                            <div class="col-4 border-start py-2">
                                                <small class="text-muted fs-11 d-block mb-1">Operators</small>
                                                <strong
                                                    class="font-monospace text-dark fs-14">{{ number_format($assetUtilizations['operator_utilization'] ?? 0, 1) }}%</strong>
                                            </div>
                                            <div class="col-4 border-start py-2">
                                                <small class="text-muted fs-11 d-block mb-1">Work Centers</small>
                                                <strong
                                                    class="font-monospace text-dark fs-14">{{ number_format($assetUtilizations['work_center_utilization'] ?? 0, 1) }}%</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            {{-- Operational Execution Variance --}}
                            <div class="col-xl-4 col-md-6 col-12">
                                <div
                                    class="p-3 rounded-3 border dash-panel-subtle h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span
                                                class="fs-11 fw-bold text-uppercase text-muted d-inline-flex align-items-center gap-1">
                                                <i class="feather-percent text-primary"></i> Execution Variance
                                                ({{ ucfirst($timeframe) }})
                                            </span>
                                            <span
                                                class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill px-2 py-0.5">
                                                {{ number_format($productionSummary['schedule_adherence'] ?? 100, 1) }}%
                                                Adherence
                                            </span>
                                        </div>

                                        {{-- Output Metrics --}}
                                        <div class="p-2.5 rounded-3 dash-card-inner border mb-2">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <span class="fs-11 text-muted">Output Units (Planned vs Actual)</span>
                                                <span class="fs-11 fw-bold font-monospace text-dark">
                                                    {{ $outputVariance >= 0 ? '+' . number_format($outputVariance) : number_format($outputVariance) }}
                                                    net
                                                </span>
                                            </div>
                                            <div class="d-flex align-items-baseline justify-content-between">
                                                <span class="fs-13 fw-bold font-monospace text-dark">
                                                    {{ number_format($productionSummary['actual_quantity'] ?? 0) }} <small
                                                        class="text-muted fw-normal">/
                                                        {{ number_format($productionSummary['planned_quantity'] ?? 0) }}</small>
                                                </span>
                                                <small class="text-muted fs-11">Yield: <strong
                                                        class="text-dark font-monospace">{{ number_format($scrapStats['yield'] ?? 100, 1) }}%</strong></small>
                                            </div>
                                        </div>

                                        {{-- Duration Metrics --}}
                                        <div class="p-2.5 rounded-3 dash-card-inner border">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <span class="fs-11 text-muted">Operation Hours (Actual vs Plan)</span>
                                                <span class="fs-11 fw-bold font-monospace text-dark">
                                                    {{ $durationVarianceHours > 0 ? '+' . $durationVarianceHours : $durationVarianceHours }}h
                                                    delta
                                                </span>
                                            </div>
                                            <div class="d-flex align-items-baseline justify-content-between">
                                                <span class="fs-13 fw-bold font-monospace text-dark">
                                                    {{ number_format($actualDurationHours, 1) }}h <small
                                                        class="text-muted fw-normal">/
                                                        {{ number_format($plannedDurationHours, 1) }}h</small>
                                                </span>
                                                <small class="text-muted fs-11">Efficiency: <strong
                                                        class="text-dark font-monospace">{{ number_format($durationEfficiency, 1) }}%</strong></small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="pt-2 mt-2 border-top text-end">
                                        <a href="{{ route('production.variances.index') }}"
                                            class="fs-11 text-primary text-decoration-none fw-semibold">
                                            Full Variance Analysis <i class="feather-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            {{-- Near-Term Order Completion Risk --}}
                            <div class="col-xl-5 col-md-6 col-12">
                                <div
                                    class="p-3 rounded-3 border dash-panel-subtle h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <span
                                                    class="fs-11 fw-bold text-uppercase text-muted d-inline-flex align-items-center gap-1">
                                                    <i class="feather-clock text-primary"></i> Near-Term Completion Risk
                                                    (&lt;72h)
                                                </span>
                                                <span
                                                    class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill px-2 py-0.5">
                                                    {{ $atRiskOrders->count() }} At Risk
                                                </span>
                                            </div>
                                            @if($qualityConstrainedOrdersCount > 0)
                                                <span
                                                    class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill px-2 py-0.5">
                                                    <i class="feather-shield me-1"></i>{{ $qualityConstrainedOrdersCount }}
                                                    Quality Hold
                                                </span>
                                            @endif
                                        </div>

                                        @if($atRiskOrders->count() > 0)
                                            <div class="table-responsive dash-card-inner rounded-3 border p-2 mb-2">
                                                <table
                                                    class="table table-sm table-borderless table-hover align-middle mb-0 fs-12">
                                                    <thead
                                                        class="dash-table-head text-muted border-bottom fs-10 text-uppercase">
                                                        <tr>
                                                            <th>Order &amp; Product</th>
                                                            <th class="text-center">Due / Countdown</th>
                                                            <th>Progress</th>
                                                            <th class="text-end">Current Op</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($atRiskOrders as $riskOrder)
                                                            @php
                                                                $targetQ = (float) $riskOrder->quantity_ordered;
                                                                $prodQ = (float) $riskOrder->quantity_produced;
                                                                $progPct = $targetQ > 0 ? round(($prodQ / $targetQ) * 100, 1) : 0;
                                                                $dueCarbon = \Illuminate\Support\Carbon::parse($riskOrder->end_date);
                                                                $hoursLeft = (int) now()->diffInHours($dueCarbon->copy()->endOfDay(), false);
                                                                $countdownLabel = $hoursLeft <= 24 ? 'Today' : ($hoursLeft <= 48 ? '1 Day' : '2 Days');
                                                                $activeOp = $riskOrder->operations->first();
                                                            @endphp
                                                            <tr class="border-bottom">
                                                                <td class="py-1">
                                                                    <a href="{{ route('production.orders.show', $riskOrder->id) }}"
                                                                        class="fw-bold text-dark text-decoration-none d-block font-monospace">
                                                                        {{ $riskOrder->order_number }}
                                                                    </a>
                                                                    <span class="text-muted fs-11 text-truncate d-inline-block"
                                                                        style="max-width: 130px;">
                                                                        {{ $riskOrder->product?->name ?? 'N/A' }}
                                                                    </span>
                                                                </td>
                                                                <td class="text-center py-1">
                                                                    <span
                                                                        class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill d-inline-block px-1.5">
                                                                        {{ $countdownLabel }}
                                                                    </span>
                                                                    <div class="fs-10 text-muted font-monospace">
                                                                        {{ $dueCarbon->format('M d') }}
                                                                    </div>
                                                                </td>
                                                                <td class="py-1" style="min-width: 100px;">
                                                                    <div
                                                                        class="d-flex align-items-center justify-content-between fs-11 mb-0.5">
                                                                        <span
                                                                            class="font-monospace fw-bold text-dark">{{ $progPct }}%</span>
                                                                        <span
                                                                            class="text-muted fs-10 font-monospace">{{ (int) $prodQ }}/{{ (int) $targetQ }}</span>
                                                                    </div>
                                                                    <div class="progress" style="height: 4px;">
                                                                        <div class="progress-bar bg-primary"
                                                                            style="width: {{ $progPct }}%"></div>
                                                                    </div>
                                                                </td>
                                                                <td class="text-end py-1">
                                                                    @if($activeOp)
                                                                        <span
                                                                            class="badge bg-soft-primary text-primary fs-10 text-truncate font-monospace rounded-pill px-1.5"
                                                                            style="max-width: 95px;">
                                                                            {{ $activeOp->name }}
                                                                        </span>
                                                                    @else
                                                                        <span
                                                                            class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill px-1.5">In
                                                                            Queue</span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @else
                                            <div class="text-center py-4 px-2 text-muted dash-card-inner rounded-3 border d-flex flex-column align-items-center justify-content-center"
                                                style="min-height: 140px;">
                                                <i class="feather-check-circle fs-20 text-primary mb-1"></i>
                                                <span class="fs-12 fw-semibold text-dark">No Near-Term Completion Risk</span>
                                                <div class="fs-11 text-muted">All active orders due within 72 hours have
                                                    achieved &ge; 50% target progress.</div>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="pt-2 mt-2 border-top text-end">
                                        <a href="{{ route('production.planning-exceptions.index') }}"
                                            class="fs-11 text-primary text-decoration-none fw-semibold">
                                            Full Exception Engine <i class="feather-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            {{-- Planning & ECO Pipeline Pulse --}}
                            <div class="col-xl-3 col-12">
                                <div
                                    class="p-3 rounded-3 border dash-panel-subtle h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <span class="fs-11 fw-bold text-uppercase text-muted d-block mb-2">
                                            <i class="feather-layers me-1 text-primary"></i>Planning &amp; ECO Pulse
                                        </span>

                                        {{-- Master Plans Strip --}}
                                        <div class="p-2 rounded-3 dash-card-inner border mb-2">
                                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                                <span class="fs-11 fw-bold text-dark"><i
                                                        class="feather-calendar me-1 text-primary"></i>Master Plans</span>
                                                <a href="{{ route('production.plans.index') }}"
                                                    class="fs-10 text-primary text-decoration-none font-monospace">View
                                                    All</a>
                                            </div>
                                            <div class="d-flex flex-wrap gap-1 text-center">
                                                <div class="flex-fill p-1 rounded dash-mini-tile border">
                                                    <small class="text-muted fs-10 d-block">Draft</small>
                                                    <strong
                                                        class="font-monospace text-dark fs-12">{{ $planStatusCounts['draft'] ?? 0 }}</strong>
                                                </div>
                                                <div class="flex-fill p-1 rounded dash-mini-tile border">
                                                    <small class="text-muted fs-10 d-block">Pending</small>
                                                    <strong
                                                        class="font-monospace text-dark fs-12">{{ $planStatusCounts['pending_approval'] ?? 0 }}</strong>
                                                </div>
                                                <div class="flex-fill p-1 rounded dash-mini-tile border">
                                                    <small class="text-muted fs-10 d-block">Approved</small>
                                                    <strong
                                                        class="font-monospace text-dark fs-12">{{ $planStatusCounts['approved'] ?? 0 }}</strong>
                                                </div>
                                                <div class="flex-fill p-1 rounded dash-mini-tile border">
                                                    <small class="text-muted fs-10 d-block">Released</small>
                                                    <strong
                                                        class="font-monospace text-primary fs-12">{{ $planStatusCounts['released'] ?? 0 }}</strong>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- ECO Pipeline Strip --}}
                                        <div class="p-2 rounded-3 dash-card-inner border">
                                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                                <span class="fs-11 fw-bold text-dark"><i
                                                        class="feather-git-pull-request me-1 text-primary"></i>ECO
                                                    Pipeline</span>
                                                <a href="{{ route('production.ecos.index') }}"
                                                    class="fs-10 text-primary text-decoration-none font-monospace">View
                                                    All</a>
                                            </div>
                                            <div class="d-flex flex-wrap gap-1 text-center">
                                                <div class="flex-fill p-1 rounded dash-mini-tile border">
                                                    <small class="text-muted fs-10 d-block">Draft</small>
                                                    <strong
                                                        class="font-monospace text-dark fs-12">{{ $ecoStatusCounts['draft'] ?? 0 }}</strong>
                                                </div>
                                                <div class="flex-fill p-1 rounded dash-mini-tile border">
                                                    <small class="text-muted fs-10 d-block">Review</small>
                                                    <strong
                                                        class="font-monospace text-dark fs-12">{{ $ecoStatusCounts['under_review'] ?? 0 }}</strong>
                                                </div>
                                                <div class="flex-fill p-1 rounded dash-mini-tile border">
                                                    <small class="text-muted fs-10 d-block">Approved</small>
                                                    <strong
                                                        class="font-monospace text-dark fs-12">{{ $ecoStatusCounts['approved'] ?? 0 }}</strong>
                                                </div>
                                                <div class="flex-fill p-1 rounded dash-mini-tile border">
                                                    <small class="text-muted fs-10 d-block">Released</small>
                                                    <strong
                                                        class="font-monospace text-primary fs-12">{{ $ecoStatusCounts['released'] ?? 0 }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="pt-2 mt-2 border-top d-flex align-items-center justify-content-between fs-11">
                                        <span class="text-muted">Engineering Changes</span>
                                        <a href="{{ route('production.ecos.index') }}"
                                            class="text-primary text-decoration-none fw-semibold">
                                            ECO Register <i class="feather-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── SECTION 6: OPERATIONAL WORKLISTS (Preserved Tabs & Full Tables) ─── --}}
        @php
            $worklistTabs = [
                [
                    'id' => 'content-demand',
                    'label' => 'Pending Demand',
                    'icon' => 'feather-inbox',
                    'badge' => $pendingSalesOrderCount,
                ],
                [
                    'id' => 'content-ready',
                    'label' => 'Ready To Start',
                    'icon' => 'feather-play',
                    'badge' => $readyToStartCount,
                ],
                [
                    'id' => 'content-inprogress',
                    'label' => 'Active In-Progress',
                    'icon' => 'feather-activity',
                    'badge' => $inProgressOrders->count(),
                ],
                [
                    'id' => 'content-overdue',
                    'label' => 'At-Risk / Overdue',
                    'icon' => 'feather-alert-triangle',
                    'badge' => $overdueOrdersCount,
                ],
            ];
        @endphp
        <div class="card dash-card border-0 shadow-sm rounded-3 mb-3 overflow-hidden" id="worklist-tabs-section">
            <div
                class="card-header pt-3 pb-0 px-4 border-bottom-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h6 class="card-title fw-bold text-dark mb-0 fs-14">Operational Worklists</h6>
                    <span class="fs-11 text-muted">Detailed task lists, orders in execution and pending demands</span>
                </div>
                <div>
                    <x-ui.horizontal-tabs id="operationalTabs" :tabs="$worklistTabs" />
                </div>
            </div>

            <div class="card-body p-0">
                <div class="tab-content worklist-tab-content" id="operationalTabsContent">

                    {{-- ── TAB 1: Pending Demand ────────────────────────────────── --}}
                    <div class="tab-pane fade show active worklist-tab-pane" id="content-demand" role="tabpanel"
                        aria-labelledby="content-demand-tab">
                        <div
                            class="px-4 py-2 dash-panel-subtle border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2 flex-shrink-0">
                            <div>
                                <h6 class="fw-bold text-dark mb-0 fs-13">Pending Sales Orders to Manufacture</h6>
                                <small class="text-muted d-block fs-11">Sales Orders / Material Requisitions awaiting
                                    Production Order conversion.</small>
                            </div>
                            <span class="badge bg-soft-primary text-primary fs-11 px-2.5 py-1 rounded-pill font-monospace">
                                {{ $pendingSalesOrderCount }} Pending Demand(s)
                            </span>
                        </div>
                        <div class="table-responsive worklist-table-container">
                            <table class="table table-hover align-middle mb-0 fs-13">
                                <thead class="dash-table-head text-muted border-bottom fs-11 text-uppercase">
                                    <tr>
                                        <th class="ps-4">Request # / SO Reference</th>
                                        <th>Product / SKU</th>
                                        <th class="text-end">Requested Qty</th>
                                        <th class="text-center">Required Date</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center pe-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($pendingRequests as $req)
                                        @php
                                            $so = $req->materialRequirementItem?->salesOrderItem?->salesOrder
                                                ?? $req->materialRequirementItem?->materialRequirement?->salesOrder;
                                            $customer = $so?->customer;
                                        @endphp
                                        <tr>
                                            <td class="ps-4">
                                                <span
                                                    class="fw-bold text-dark font-monospace">REQ-{{ str_pad($req->id, 5, '0', STR_PAD_LEFT) }}</span>
                                                @if($so)
                                                    <div class="fs-11 text-muted font-monospace">SO:
                                                        {{ $so->order_number ?? 'SO-' . $so->id }}
                                                    </div>
                                                    @if($customer)
                                                        <small class="text-muted d-block fs-11">{{ $customer->name }}</small>
                                                    @endif
                                                @else
                                                    <div class="fs-11 text-muted">Direct Demand</div>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark">{{ $req->product?->name ?? '—' }}</div>
                                                <small class="text-muted font-monospace fs-11">SKU:
                                                    {{ $req->product?->sku }}</small>
                                            </td>
                                            <td class="text-end fw-bold font-monospace text-dark">
                                                {{ number_format($req->quantity_requested, 2) }}
                                            </td>
                                            <td class="text-center font-monospace fs-12 text-muted">
                                                {{ $req->required_date ? \Carbon\Carbon::parse($req->required_date)->format('M d, Y') : '—' }}
                                            </td>
                                            <td class="text-center">
                                                <x-ui.status-badge :status="$req->status" />
                                            </td>
                                            <td class="text-center pe-4">
                                                <x-ui.button variant="primary" icon="feather-plus"
                                                    href="{{ route('production.orders.create', ['request_id' => $req->id]) }}">
                                                    Create Order
                                                </x-ui.button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4 border-0">
                                                <div class="py-3 d-flex flex-column align-items-center justify-content-center"
                                                    style="min-height: 200px;">
                                                    <div class="rounded-circle bg-soft-primary text-primary d-inline-flex align-items-center justify-content-center mb-2.5"
                                                        style="width: 48px; height: 48px;">
                                                        <i class="feather-check-circle fs-24"></i>
                                                    </div>
                                                    <h6 class="fw-semibold text-dark mb-1 fs-14">Pending Sales Orders to
                                                        Manufacture</h6>
                                                    <p class="text-muted fs-12 mb-0">All sales order demands have active
                                                        Production Orders created.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- ── TAB 2: Ready to Start ────────────────────────────────── --}}
                    <div class="tab-pane fade worklist-tab-pane" id="content-ready" role="tabpanel"
                        aria-labelledby="content-ready-tab">
                        <div class="table-responsive worklist-table-container">
                            <table class="table table-hover align-middle mb-0 fs-13">
                                <thead class="dash-table-head text-muted border-bottom fs-11 text-uppercase">
                                    <tr>
                                        <th class="ps-4">Order # / Mode</th>
                                        <th>Product / SKU</th>
                                        <th class="text-end">Target Qty</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Requisition Status</th>
                                        <th class="text-center">Target Date</th>
                                        <th class="text-center pe-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($readyToStartOrders as $order)
                                        @php
                                            $slips = $order->requisitionSlips;
                                            $hasFull = $slips->contains(fn($s) => in_array(strtolower($s->status), ['fully issued', 'completed', 'issued']));
                                            $hasPartial = $slips->contains(fn($s) => in_array(strtolower($s->status), ['partially issued', 'partial']));
                                        @endphp
                                        <tr>
                                            <td class="ps-4">
                                                <a href="{{ route('production.orders.show', $order->id) }}"
                                                    class="fw-bold text-dark font-monospace text-decoration-none">
                                                    {{ $order->order_number }}
                                                </a>
                                                <div class="fs-11 text-muted text-uppercase font-monospace">
                                                    {{ $order->production_mode }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark">{{ $order->product?->name ?? '—' }}</div>
                                                <small class="text-muted font-monospace fs-11">SKU:
                                                    {{ $order->product?->sku }}</small>
                                            </td>
                                            <td class="text-end fw-bold font-monospace text-dark">
                                                {{ number_format($order->quantity_ordered, 2) }}
                                            </td>
                                            <td class="text-center">
                                                <x-ui.status-badge :status="$order->status" />
                                            </td>
                                            <td class="text-center">
                                                @if($hasFull)
                                                    <span
                                                        class="badge bg-soft-primary text-primary fs-11 px-2.5 py-1 rounded-pill font-monospace">
                                                        <i class="feather-check-circle me-1"></i>Fully Issued
                                                    </span>
                                                @elseif($hasPartial)
                                                    <span
                                                        class="badge bg-soft-primary text-primary fs-11 px-2.5 py-1 rounded-pill font-monospace">
                                                        <i class="feather-clock me-1"></i>Partially Issued
                                                    </span>
                                                @else
                                                    <span
                                                        class="badge bg-soft-primary text-primary fs-11 px-2.5 py-1 rounded-pill font-monospace">
                                                        Pending Store
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-center font-monospace fs-12 text-muted">
                                                {{ $order->end_date ? \Carbon\Carbon::parse($order->end_date)->format('M d, Y') : '—' }}
                                            </td>
                                            <td class="text-center pe-4">
                                                <x-ui.button variant="primary" icon="feather-play"
                                                    href="{{ route('production.orders.show', ['order' => $order->id, 'tab' => 'vtab-operations']) }}">
                                                    Start Production
                                                </x-ui.button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-4 border-0">
                                                <div class="py-3 d-flex flex-column align-items-center justify-content-center"
                                                    style="min-height: 240px;">
                                                    <div class="rounded-circle bg-soft-primary text-primary d-inline-flex align-items-center justify-content-center mb-2.5"
                                                        style="width: 48px; height: 48px;">
                                                        <i class="feather-inbox fs-24"></i>
                                                    </div>
                                                    <h6 class="fw-semibold text-dark mb-1 fs-14">No Orders Ready to Start</h6>
                                                    <p class="text-muted fs-12 mb-0">No orders currently waiting in
                                                        Ready-to-Start status.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- ── TAB 3: Active In-Progress Execution ───────────────────── --}}
                    <div class="tab-pane fade worklist-tab-pane" id="content-inprogress" role="tabpanel"
                        aria-labelledby="content-inprogress-tab">
                        <div class="table-responsive worklist-table-container">
                            <table class="table table-hover align-middle mb-0 fs-13">
                                <thead class="dash-table-head text-muted border-bottom fs-11 text-uppercase">
                                    <tr>
                                        <th class="ps-4">Order # / Mode</th>
                                        <th>Product / SKU</th>
                                        <th class="text-end">Produced / Target</th>
                                        <th style="min-width: 130px;">Progress</th>
                                        <th>Active Operations</th>
                                        <th class="text-center pe-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($inProgressOrders as $order)
                                        @php
                                            $target = (float) $order->quantity_ordered;
                                            $produced = (float) $order->quantity_produced;
                                            $percent = $target > 0 ? min(100, round(($produced / $target) * 100)) : 0;
                                        @endphp
                                        <tr>
                                            <td class="ps-4">
                                                <a href="{{ route('production.orders.show', $order->id) }}"
                                                    class="fw-bold text-dark font-monospace text-decoration-none">
                                                    {{ $order->order_number }}
                                                </a>
                                                <div class="fs-11 text-muted text-uppercase font-monospace">
                                                    {{ $order->production_mode }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark">{{ $order->product?->name ?? '—' }}</div>
                                                <small class="text-muted font-monospace fs-11">SKU:
                                                    {{ $order->product?->sku }}</small>
                                            </td>
                                            <td class="text-end fw-bold font-monospace text-dark">
                                                {{ number_format($produced, 1) }} / {{ number_format($target, 1) }}
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress flex-grow-1" style="height: 6px;">
                                                        <div class="progress-bar bg-primary" role="progressbar"
                                                            style="width: {{ $percent }}%" aria-valuenow="{{ $percent }}"
                                                            aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                    <span class="fs-11 fw-bold font-monospace text-dark">{{ $percent }}%</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-1">
                                                    @forelse($order->operations->take(3) as $op)
                                                        <span
                                                            class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill">
                                                            {{ $op->sequence }}. {{ $op->name }}
                                                        </span>
                                                    @empty
                                                        <span class="text-muted fs-11">No operations defined</span>
                                                    @endforelse
                                                    @if($order->operations->count() > 3)
                                                        <span
                                                            class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill">+{{ $order->operations->count() - 3 }}
                                                            more</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="text-center pe-4">
                                                <div class="d-flex align-items-center justify-content-center gap-1.5">
                                                    <x-ui.button variant="primary" icon="feather-monitor"
                                                        href="{{ route('production.mes.dashboard') }}">
                                                        MES
                                                    </x-ui.button>
                                                    <x-ui.button variant="light" icon="feather-eye"
                                                        href="{{ route('production.orders.show', $order->id) }}">
                                                        Show
                                                    </x-ui.button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4 border-0">
                                                <div class="py-3 d-flex flex-column align-items-center justify-content-center"
                                                    style="min-height: 240px;">
                                                    <div class="rounded-circle bg-soft-primary text-primary d-inline-flex align-items-center justify-content-center mb-2.5"
                                                        style="width: 48px; height: 48px;">
                                                        <i class="feather-play-circle fs-24"></i>
                                                    </div>
                                                    <h6 class="fw-semibold text-dark mb-1 fs-14">No Active In-Progress Execution
                                                    </h6>
                                                    <p class="text-muted fs-12 mb-0">No production orders currently in Released
                                                        or In-Progress status.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- ── TAB 4: At-Risk / Overdue Orders ───────────────────────── --}}
                    <div class="tab-pane fade worklist-tab-pane" id="content-overdue" role="tabpanel"
                        aria-labelledby="content-overdue-tab">
                        <div class="table-responsive worklist-table-container">
                            <table class="table table-hover align-middle mb-0 fs-13">
                                <thead class="dash-table-head text-muted border-bottom fs-11 text-uppercase">
                                    <tr>
                                        <th class="ps-4">Order # / Mode</th>
                                        <th>Product / SKU</th>
                                        <th class="text-end">Target Qty</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Planned End Date</th>
                                        <th class="text-center">Overdue Days</th>
                                        <th class="text-center pe-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($overdueOrdersList as $order)
                                        @php
                                            $endDate = \Carbon\Carbon::parse($order->end_date);
                                            $daysOverdue = max(1, $endDate->diffInDays(now()));
                                        @endphp
                                        <tr>
                                            <td class="ps-4">
                                                <a href="{{ route('production.orders.show', $order->id) }}"
                                                    class="fw-bold text-dark font-monospace text-decoration-none">
                                                    {{ $order->order_number }}
                                                </a>
                                                <div class="fs-11 text-muted text-uppercase font-monospace">
                                                    {{ $order->production_mode }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark">{{ $order->product?->name ?? '—' }}</div>
                                                <small class="text-muted font-monospace fs-11">SKU:
                                                    {{ $order->product?->sku }}</small>
                                            </td>
                                            <td class="text-end fw-bold font-monospace text-dark">
                                                {{ number_format($order->quantity_ordered, 2) }}
                                            </td>
                                            <td class="text-center">
                                                <x-ui.status-badge :status="$order->status" />
                                            </td>
                                            <td class="text-center font-monospace fs-12 text-muted">
                                                <i
                                                    class="feather-calendar me-1 text-primary"></i>{{ $endDate->format('M d, Y') }}
                                            </td>
                                            <td class="text-center">
                                                <span
                                                    class="badge bg-soft-primary text-primary fs-11 px-2.5 py-1 rounded-pill font-monospace">
                                                    {{ $daysOverdue }} Day(s) Overdue
                                                </span>
                                            </td>
                                            <td class="text-center pe-4">
                                                <x-ui.button variant="primary" icon="feather-arrow-right"
                                                    href="{{ route('production.orders.show', $order->id) }}">
                                                    Expedite
                                                </x-ui.button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-4 border-0">
                                                <div class="py-3 d-flex flex-column align-items-center justify-content-center"
                                                    style="min-height: 240px;">
                                                    <div class="rounded-circle bg-soft-primary text-primary d-inline-flex align-items-center justify-content-center mb-2.5"
                                                        style="width: 48px; height: 48px;">
                                                        <i class="feather-check-circle fs-24"></i>
                                                    </div>
                                                    <h6 class="fw-semibold text-dark mb-1 fs-14">Zero Overdue Orders! All
                                                        production schedules are within target delivery dates.</h6>
                                                    <p class="text-muted fs-12 mb-0">Manufacturing throughput is adhering to
                                                        customer commitments.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- ── SECTION 7: RECENT ACTIVITY ───────────────────────────────────────── --}}
        <div class="card dash-card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="card-title fw-bold text-dark mb-0 fs-14">Recent Activity</h6>
                    <span class="fs-11 text-muted">Latest manufacturing and operational events</span>
                </div>
                <span class="fs-11 text-muted">Real-time event trail</span>
            </div>
            <div class="card-body p-0">
                @if(isset($recentOrders) && $recentOrders->isNotEmpty())
                    <div class="table-responsive mb-0">
                        <table class="table table-hover align-middle mb-0 fs-13">
                            <thead class="dash-table-head text-muted border-bottom fs-11 text-uppercase">
                                <tr>
                                    <th class="ps-4" style="width: 140px;">Time</th>
                                    <th style="width: 130px;">Type</th>
                                    <th>Description</th>
                                    <th style="width: 180px;">Reference</th>
                                    <th class="text-center pe-4" style="width: 130px;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentOrders->take(6) as $rec)
                                    <tr>
                                        <td class="ps-4 text-muted font-monospace fs-12">
                                            {{ $rec->created_at ? $rec->created_at->format('h:i A') : '—' }}
                                            <small
                                                class="d-block text-muted opacity-75 fs-10">{{ $rec->created_at ? $rec->created_at->format('M d') : '' }}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-soft-primary text-primary fs-11 rounded-pill">
                                                Production
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-dark fw-medium">Production order registered for
                                                {{ $rec->product?->name ?? 'Finished Goods' }}</span>
                                            <small class="text-muted d-block fs-11 font-monospace">Qty:
                                                {{ number_format($rec->quantity_ordered, 1) }} units</small>
                                        </td>
                                        <td>
                                            <a href="{{ route('production.orders.show', $rec->id) }}"
                                                class="fw-bold font-monospace text-dark text-decoration-none">
                                                {{ $rec->order_number }}
                                            </a>
                                        </td>
                                        <td class="text-center pe-4">
                                            <x-ui.status-badge :status="$rec->status" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4 px-3 text-muted">
                        <i class="feather-clock fs-22 d-block mb-1 opacity-50"></i>
                        <span class="fs-12">No recent production events recorded.</span>
                    </div>
                @endif
            </div>
        </div>

    </div>

    @push('scripts')
        <script>
            function activateTab(tabId) {
                const tabEl = document.getElementById(tabId);
                if (tabEl) {
                    const tab = new bootstrap.Tab(tabEl);
                    tab.show();
                    const container = document.getElementById('worklist-tabs-section');
                    if (container) {
                        container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                }
            }
        </script>
    @endpush
@endsection