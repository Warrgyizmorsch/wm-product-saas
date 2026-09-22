@extends('layouts.duralux')

@section('title', __('production.production_dashboard'))
@section('page-title', __('production.production_dashboard'))
@section('breadcrumb', __('production.production_dashboard'))

@php
    $rawRole = auth()->user()?->role;
    $roleDisplayName = is_object($rawRole) ? ($rawRole->name ?? 'Plant Manager') : ($rawRole ?? 'Plant Manager');
    $roleDisplayName = ucwords(str_replace(['_', '-'], ' ', (string) $roleDisplayName));

    $hour = date('H');
    $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');

    $worklistTabs = [
        [
            'id' => 'content-demand',
            'label' => __('production.pending_demand') . ' (' . $pendingSalesOrderCount . ')',
            'active' => true,
            'icon' => 'feather-shopping-cart'
        ],
        [
            'id' => 'content-ready',
            'label' => __('production.ready_to_start') . ' (' . $readyToStartCount . ')',
            'active' => false,
            'icon' => 'feather-check-circle'
        ],
        [
            'id' => 'content-inprogress',
            'label' => __('production.active_in_progress') . ' (' . $inProgressOrders->count() . ')',
            'active' => false,
            'icon' => 'feather-activity'
        ],
        [
            'id' => 'content-overdue',
            'label' => __('production.at_risk_overdue') . ' (' . $overdueOrdersCount . ')',
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
    <x-ui.button variant="primary" icon="feather-plus-circle" href="{{ route('production.orders.create') }}">{{ __('production.create_production_order') }}</x-ui.button>
    <x-ui.button variant="light" icon="feather-monitor" href="{{ route('production.mes.dashboard') }}">{{ __('production.shop_floor') }}</x-ui.button>
    <x-ui.button variant="light" icon="feather-bar-chart-2" href="{{ route('production.intelligence.reports.index') }}">{{ __('production.reports') }}</x-ui.button>
@endsection

@section('content')
    <div class="production-dashboard-wrapper">


        {{-- ── SECTION 2: OPERATIONAL OVERVIEW & TIMEFRAME SNAPSHOT ─────────────── --}}
        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="fs-12 fw-bold text-dark text-uppercase tracking-wider">
                    <i class="feather-activity text-primary me-1"></i> {{ __('production.operational_overview') }}
                </span>
                <span class="text-muted fs-12">&bull; {{ __('production.horizon') }} <span
                        class="badge bg-soft-primary text-primary fw-semibold">{{ ($timeframe ?? 'today') === 'week' ? __('production.this_week') : (($timeframe ?? 'today') === 'month' ? __('production.this_month') : __('production.today')) }}</span></span>
            </div>
            <div class="timeframe-pill-group d-inline-flex p-1 rounded-pill shadow-sm">
                <a href="{{ route('production.dashboard', ['timeframe' => 'today']) }}"
                    class="btn btn-xs rounded-pill px-3 py-1 fw-semibold {{ ($timeframe ?? 'today') === 'today' ? 'btn-primary shadow-sm' : 'text-muted' }}">
                    {{ __('production.today') }}
                </a>
                <a href="{{ route('production.dashboard', ['timeframe' => 'week']) }}"
                    class="btn btn-xs rounded-pill px-3 py-1 fw-semibold {{ ($timeframe ?? 'today') === 'week' ? 'btn-primary shadow-sm' : 'text-muted' }}">
                    {{ __('production.this_week') }}
                </a>
                <a href="{{ route('production.dashboard', ['timeframe' => 'month']) }}"
                    class="btn btn-xs rounded-pill px-3 py-1 fw-semibold {{ ($timeframe ?? 'today') === 'month' ? 'btn-primary shadow-sm' : 'text-muted' }}">
                    {{ __('production.this_month') }}
                </a>
            </div>
        </div>

        <div class="row g-3">
            {{-- Card 1: Active Production Orders --}}
            <div class="col-xl-2 col-md-4 col-sm-6">
                <x-ui.stat-widget title="{{ __('production.active_orders') }}" :value="number_format($totalActiveOrders)"
                    subtitle="{{ __('production.orders_in_progress') }}" icon="feather-play-circle" color="primary" variant="compact">
                    <x-slot name="footer">
                        <span class="fw-semibold">{{ number_format($orderStatusCounts['total']) }}</span> {{ __('production.total_orders') }}
                    </x-slot>
                </x-ui.stat-widget>
            </div>

            {{-- Card 2: Planned Today / Period --}}
            <div class="col-xl-2 col-md-4 col-sm-6">
                <x-ui.stat-widget
                    title="{{ __('production.planned') }} {{ ($timeframe ?? 'today') === 'week' ? __('production.this_week') : (($timeframe ?? 'today') === 'month' ? __('production.this_month') : __('production.today')) }}"
                    :value="number_format($productionSummary['planned_quantity'] ?? 0)" subtitle="{{ __('production.scheduled_units') }}"
                    icon="feather-calendar" color="primary" variant="compact">
                    <x-slot name="footer">
                        {{ __('production.adherence') }}: <span
                            class="fw-bold text-primary">{{ number_format($productionSummary['schedule_adherence'] ?? 100, 1) }}%</span>
                    </x-slot>
                </x-ui.stat-widget>
            </div>

            {{-- Card 3: In Progress --}}
            <div class="col-xl-2 col-md-4 col-sm-6">
                <x-ui.stat-widget title="{{ __('production.in_progress') }}" :value="number_format($orderStatusCounts['in_progress'] ?? 0)"
                    subtitle="{{ __('production.on_shop_floor') }}" icon="feather-activity" color="info" variant="compact">
                    <x-slot name="footer">
                        <span class="fw-semibold">{{ $orderStatusCounts['released'] ?? 0 }}</span> {{ __('production.released_in_queue') }}
                    </x-slot>
                </x-ui.stat-widget>
            </div>

            {{-- Card 4: Completed Today / Period --}}
            <div class="col-xl-2 col-md-4 col-sm-6">
                <x-ui.stat-widget
                    title="{{ __('production.completed') }} {{ ($timeframe ?? 'today') === 'week' ? __('production.this_week') : (($timeframe ?? 'today') === 'month' ? __('production.this_month') : __('production.today')) }}"
                    :value="number_format($mesCompletedTodayCount ?? 0)" subtitle="{{ __('production.operations_finished') }}"
                    icon="feather-check-circle" color="success" variant="compact">
                    <x-slot name="footer">
                        {{ __('production.yield') }}: <span class="fw-semibold">{{ number_format($scrapStats['yield'] ?? 100, 1) }}%</span>
                    </x-slot>
                </x-ui.stat-widget>
            </div>

            {{-- Card 5: Pending QC --}}
            <div class="col-xl-2 col-md-4 col-sm-6">
                <x-ui.stat-widget title="{{ __('production.pending_qc') }}" :value="number_format($pendingQcCount ?? 0)"
                    subtitle="{{ __('production.waiting_for_quality_check') }}" icon="feather-shield" :color="($pendingQcCount ?? 0) > 0 ? 'warning' : 'primary'" variant="compact">
                    <x-slot name="footer">
                        <span class="fw-semibold">{{ $openNcrCount ?? 0 }}</span> {{ __('production.open_ncrs') }}
                    </x-slot>
                </x-ui.stat-widget>
            </div>

            {{-- Card 6: Material Blocked --}}
            <div class="col-xl-2 col-md-4 col-sm-6">
                <x-ui.stat-widget title="{{ __('production.material_blocked') }}" :value="number_format($materialBlockersCount ?? 0)"
                    subtitle="{{ __('production.blocked_by_material_issue') }}" icon="feather-package" :color="($materialBlockersCount ?? 0) > 0 ? 'danger' : 'primary'" variant="compact">
                    <x-slot name="footer">
                        {{ __('production.pending_store_release') }}
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
                            <h6 class="card-title fw-bold text-dark mb-0 fs-14">{{ __('production.action_center') }}</h6>
                            @if($totalActionExceptions > 0)
                                <span
                                    class="badge bg-soft-primary text-primary rounded-pill px-2.5 py-0.5 fs-11 fw-bold border border-primary border-opacity-10">
                                    {{ $totalActionExceptions }} {{ __('production.items_need_attention_short') }}
                                </span>
                            @else
                                <span
                                    class="badge bg-soft-primary text-primary rounded-pill px-2.5 py-0.5 fs-11 fw-bold border border-primary border-opacity-10">
                                    {{ __('production.everything_on_track') }}
                                </span>
                            @endif
                        </div>
                        <span class="fs-11 text-muted">{{ __('production.items_need_attention') }}</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <x-ui.button variant="primary" icon="feather-alert-octagon" href="{{ route('production.planning-exceptions.index') }}">{{ __('production.exception_engine') }}</x-ui.button>
                </div>
            </div>

            <div class="card-body p-0">
                @if($totalActionExceptions > 0)
                    <div class="table-responsive mb-0">
                        <table class="table table-hover align-middle mb-0 fs-13">
                            <thead class="dash-table-head fs-11 text-uppercase text-muted border-bottom">
                                <tr>
                                    <th class="ps-4" style="width: 220px;">{{ __('production.item') }}</th>
                                    <th>{{ __('production.details') }}</th>
                                    <th class="text-center" style="width: 110px;">{{ __('production.priority') }}</th>
                                    <th class="text-center" style="width: 160px;">{{ __('production.status') }}</th>
                                    <th class="text-center pe-4" style="width: 110px;">{{ __('production.action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- 1. Overdue Orders --}}
                                @if(($overdueOrdersCount ?? 0) > 0)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="feather-clock text-primary fs-15"></i>
                                                <span class="fw-semibold text-dark">{{ __('production.overdue_orders') }}</span>
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
                                            <span class="badge bg-soft-primary text-primary fs-11 rounded-pill">{{ __('production.priority_high') }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="badge bg-soft-primary text-primary fs-11 rounded-pill font-monospace">{{ __('production.overdue') }}</span>
                                        </td>
                                        <td class="text-center pe-4">
                                            <x-ui.button variant="primary" icon="feather-arrow-right" iconPosition="right"
                                                href="#worklist-tabs-section" onclick="activateTab('content-overdue-tab')">{{ __('production.view') }}</x-ui.button>
                                        </td>
                                    </tr>
                                @endif

                                {{-- 2. Machine Breakdown --}}
                                @if(($breakdownCount ?? 0) > 0)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="feather-alert-octagon text-primary fs-15"></i>
                                                <span class="fw-semibold text-dark">{{ __('production.machine_breakdown') }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-dark">{{ number_format($breakdownCount) }} machine(s) currently halted
                                                due to equipment breakdown.</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-soft-primary text-primary fs-11 rounded-pill">{{ __('production.critical') }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="badge bg-soft-primary text-primary fs-11 rounded-pill font-monospace">{{ __('production.breakdown') }}</span>
                                        </td>
                                        <td class="text-center pe-4">
                                            <x-ui.button variant="primary" icon="feather-arrow-right" iconPosition="right"
                                                href="{{ route('production.mes.machines.index', ['state' => 'Breakdown']) }}">
                                                {{ __('production.view') }}
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
                                                <span class="fw-semibold text-dark">{{ __('production.material_blockers') }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-dark">{{ number_format($materialBlockersCount) }} production order(s)
                                                waiting for store release / raw materials.</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-soft-primary text-primary fs-11 rounded-pill">{{ __('production.priority_high') }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="badge bg-soft-primary text-primary fs-11 rounded-pill font-monospace">{{ __('production.pending_store') }}</span>
                                        </td>
                                        <td class="text-center pe-4">
                                            <x-ui.button variant="primary" icon="feather-arrow-right" iconPosition="right"
                                                href="{{ route('sales.material-requests.index') }}">{{ __('production.view') }}</x-ui.button>
                                        </td>
                                    </tr>
                                @endif

                                {{-- 4. Pending QC & NCR --}}
                                @if((($pendingQcCount ?? 0) + ($openNcrCount ?? 0)) > 0)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="feather-shield text-primary fs-15"></i>
                                                <span class="fw-semibold text-dark">{{ __('production.pending_qc_ncr') }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-dark">{{ number_format($pendingQcCount) }} inspection(s) waiting audit
                                                &bull; {{ number_format($openNcrCount) }} active non-conformance(s).</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-soft-primary text-primary fs-11 rounded-pill">{{ __('production.priority_medium') }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="badge bg-soft-primary text-primary fs-11 rounded-pill font-monospace">{{ __('production.waiting_qc') }}</span>
                                        </td>
                                        <td class="text-center pe-4">
                                            <x-ui.button variant="primary" icon="feather-arrow-right" iconPosition="right"
                                                href="{{ route('production.inspections.index') }}">{{ __('production.view') }}</x-ui.button>
                                        </td>
                                    </tr>
                                @endif

                                {{-- 5. Vendor Delays --}}
                                @if(($vendorDelayedCount ?? 0) > 0)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="feather-truck text-primary fs-15"></i>
                                                <span class="fw-semibold text-dark">{{ __('production.vendor_delays') }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-dark">{{ number_format($vendorDelayedCount) }} outside subcontract
                                                operation(s) overdue beyond expected delivery.</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-soft-primary text-primary fs-11 rounded-pill">{{ __('production.priority_high') }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="badge bg-soft-primary text-primary fs-11 rounded-pill font-monospace">{{ __('production.delayed') }}</span>
                                        </td>
                                        <td class="text-center pe-4">
                                            <x-ui.button variant="primary" icon="feather-arrow-right" iconPosition="right"
                                                href="{{ route('production.subcontract.analytics') }}">{{ __('production.view') }}</x-ui.button>
                                        </td>
                                    </tr>
                                @endif

                                {{-- 6. Pending Demand --}}
                                @if(($pendingSalesOrderCount ?? 0) > 0)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="feather-shopping-cart text-primary fs-15"></i>
                                                <span class="fw-semibold text-dark">{{ __('production.pending_demand') }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-dark">{{ number_format($pendingSalesOrderCount) }} sales demand(s)
                                                awaiting production order creation.</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-soft-primary text-primary fs-11 rounded-pill">{{ __('production.priority_medium') }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="badge bg-soft-primary text-primary fs-11 rounded-pill font-monospace">{{ __('production.draft') }}</span>
                                        </td>
                                        <td class="text-center pe-4">
                                            <x-ui.button variant="primary" icon="feather-arrow-right" iconPosition="right"
                                                href="#worklist-tabs-section" onclick="activateTab('content-demand-tab')">{{ __('production.view') }}</x-ui.button>
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
                        <h6 class="fw-bold text-dark mb-1 fs-14">{{ __('production.everything_on_track') }}</h6>
                        <p class="text-muted fs-12 mb-0">{{ __('production.no_exceptions_require_attention') }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- ── SECTION 4: PRODUCTION FLOW (5-Stage Horizontal Lifecycle) ────────── --}}
        <div class="card dash-card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header py-2.5 px-4 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="card-title fw-bold text-dark mb-0 fs-13">{{ __('production.production_flow') }}</h6>
                    <span class="fs-11 text-muted">{{ __('production.mfg_lifecycle_pipeline') }}</span>
                </div>
                <span class="fs-11 text-muted">{{ __('production.click_stage_drilldown') }}</span>
            </div>
                <span class="fs-11 text-muted">{{ __('production.click_stage_drilldown') }}</span>
            </div>
            <div class="card-body p-3">
                <div class="production-flow-container">
                    {{-- Stage 1: Demand --}}
                    <a href="#worklist-tabs-section" onclick="activateTab('content-demand-tab')"
                        class="production-flow-step">
                        <div>
                            <span class="step-number">{{ __('production.stage_1') }}</span>
                            <div class="step-title">{{ __('production.demand') }}</div>
                        </div>
                        <div>
                            <div class="step-count">{{ number_format($pendingSalesOrderCount) }}</div>
                            <div class="step-desc">{{ __('production.pending_demands') }}</div>
                        </div>
                    </a>

                    <div class="production-flow-arrow"><i class="feather-chevron-right"></i></div>

                    {{-- Stage 2: Planning --}}
                    <a href="{{ route('production.plans.index') }}" class="production-flow-step">
                        <div>
                            <span class="step-number">{{ __('production.stage_2') }}</span>
                            <div class="step-title">{{ __('production.planning') }}</div>
                        </div>
                        <div>
                            <div class="step-count">{{ number_format(array_sum($planStatusCounts)) }}</div>
                            <div class="step-desc">{{ __('production.production_plans') }}</div>
                        </div>
                    </a>

                    <div class="production-flow-arrow"><i class="feather-chevron-right"></i></div>

                    {{-- Stage 3: Production Orders --}}
                    <a href="{{ route('production.orders.index') }}" class="production-flow-step">
                        <div>
                            <span class="step-number">{{ __('production.stage_3') }}</span>
                            <div class="step-title">{{ __('production.step_production_orders') }}</div>
                        </div>
                        <div>
                            <div class="step-count">{{ number_format($totalActiveOrders) }}</div>
                            <div class="step-desc">{{ __('production.active_orders') }}</div>
                        </div>
                    </a>

                    <div class="production-flow-arrow"><i class="feather-chevron-right"></i></div>

                    {{-- Stage 4: Shop Floor --}}
                    <a href="{{ route('production.mes.dashboard') }}" class="production-flow-step">
                        <div>
                            <span class="step-number">{{ __('production.stage_4') }}</span>
                            <div class="step-title">{{ __('production.shop_floor') }}</div>
                        </div>
                        <div>
                            <div class="step-count">{{ number_format($orderStatusCounts['in_progress'] ?? 0) }}</div>
                            <div class="step-desc">{{ __('production.in_progress') }}</div>
                        </div>
                    </a>

                    <div class="production-flow-arrow"><i class="feather-chevron-right"></i></div>

                    {{-- Stage 5: Quality & Completion --}}
                    <a href="{{ route('production.quality.dashboard') }}" class="production-flow-step">
                        <div>
                            <span class="step-number">{{ __('production.stage_5') }}</span>
                            <div class="step-title">{{ __('production.quality_and_completion') }}</div>
                        </div>
                        <div>
                            <div class="step-count">{{ number_format($orderStatusCounts['completed'] ?? 0) }}</div>
                            <div class="step-desc">{{ __('production.completed_orders') }}</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        {{-- ── SECTION 5: PRODUCTION AREAS (6 Expandable Module Cards) ─────────── --}}
        <div class="mb-3">
            <div class="d-flex align-items-center justify-content-between mb-2 px-1">
                <h6 class="fw-bold text-dark mb-0 fs-14">{{ __('production.production_areas') }}</h6>
                <span class="fs-11 text-muted">{{ __('production.click_area_expand') }}</span>
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
                                <div class="fw-bold text-dark fs-14">{{ __('production.production_and_capacity') }}</div>
                                @php
                                    $avgUtil = !empty($workCenterLoads) ? round(collect($workCenterLoads)->avg('utilization'), 1) : 0;
                                @endphp
                                <span class="fs-11 text-muted">
                                    {{ __('production.utilization') }} <strong class="text-dark font-monospace">{{ $avgUtil }}%</strong> &bull;
                                    {{ count($workCenterLoads) }} {{ __('production.active') }} &bull;
                                    {{ __('production.plant_oee_score') }}: <strong
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
                            href="{{ route('production.mes.work-centers.index') }}" title="{{ __('production.view') }}"></x-ui.button>
                    </div>
                </div>
                <div class="collapse" id="area-capacity">
                    <div class="production-area-body">
                        {{-- Work-Center Capacity, Load & Bottleneck Pulse --}}
                        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                            <h6 class="fw-bold text-dark mb-0 fs-13">{{ __('production.wc_capacity_bottleneck_pulse') }}</h6>
                            <div class="d-flex align-items-center gap-2">
                                <span
                                    class="badge bg-soft-primary text-primary fs-11 font-monospace rounded-pill px-2.5 py-1">
                                    {{ __('production.active_orders_pipeline') }}: {{ $totalActiveOrders }} {{ __('production.active') }}
                                </span>
                                <span
                                    class="badge bg-soft-primary text-primary fs-11 font-monospace rounded-pill px-2.5 py-1">
                                    {{ __('production.volume_and_adherence') }}:
                                    {{ number_format($productionSummary['schedule_adherence'] ?? 100, 1) }}%
                                </span>
                                <span
                                    class="badge bg-soft-primary text-primary fs-11 font-monospace rounded-pill px-2.5 py-1">
                                    {{ __('production.plant_oee_score') }}: {{ number_format($oeeKpi['current_value'] ?? 0, 1) }}%
                                </span>
                                <x-ui.button variant="light" icon="feather-layers"
                                    href="{{ route('production.mes.work-centers.index') }}">
                                    {{ __('production.all_work_centers') }}
                                </x-ui.button>
                            </div>
                        </div>

                        {{-- Bottleneck Spotlight --}}
                        @if(!empty($bottleneckWorkCenters) && $bottleneckWorkCenters->isNotEmpty())
                            <div class="dash-panel-subtle p-3 rounded-3 border mb-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-1.5 fs-13">
                                        <i class="feather-alert-octagon text-primary"></i>
                                        {{ __('production.bottleneck_spotlight', ['count' => $bottleneckWorkCenters->count()]) }}
                                    </h6>
                                    <small class="text-muted fs-11">{{ __('production.capacity_thresholds_note') }}</small>
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
                                <div class="d-flex align-items-center gap-2 fs-12 text-muted"><i class="feather-check-circle fs-15 text-primary"></i><span class="text-dark"><strong>{{ __('production.zero_bottlenecks_active') }}</strong> {{ __('production.all_work_centers_standard_limits') }}</span></div><span class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill">{{ __('production.capacity_balanced') }}</span>
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
                                                                title="{{ __('production.running') }}"></span>
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
                                                            {{ __('production.running') }}
                                                        </span>
                                                    @endif
                                                    <span
                                                        class="badge {{ $wcl['status'] === 'Critical' ? 'bg-soft-danger text-danger' : ($wcl['status'] === 'Warning' ? 'bg-soft-warning text-warning' : 'bg-soft-primary text-primary') }} fs-11 font-monospace rounded-pill">
                                                        {{ $wcl['status'] }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-baseline justify-content-between mb-1">
                                                <span class="fs-12 text-muted">{{ __('production.planned_vs_available') }}</span>
                                                <span class="fs-12 fw-bold font-monospace text-dark">
                                                     {{ $wcl['scheduled_hours'] }}h / {{ $wcl['capacity_hours'] }}h
                                                </span>
                                            </div>
                                            <div class="progress mb-2" style="height: 6px;">
                                                <div class="progress-bar {{ $isRunning ? 'bg-primary progress-bar-striped progress-bar-animated' : 'bg-primary' }}"
                                                    role="progressbar" style="width: {{ min(100, $wcl['utilization']) }}%"></div>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-between fs-11 text-muted">
                                                <span>{{ __('production.utilization_prefix') }} <strong
                                                        class="text-dark font-monospace">{{ $wcl['utilization'] }}%</strong></span>
                                                @if($isRunning)
                                                    <span
                                                        class="badge bg-soft-primary text-primary font-monospace px-2 py-0.5 rounded-pill d-inline-flex align-items-center gap-1">
                                                        <i class="feather-activity text-primary fs-11"></i>
                                                        <strong>{{ $wcl['running_ops'] }} {{ __('production.running') }}</strong> | {{ $wcl['waiting_ops'] }}
                                                        {{ __('production.ready') }}
                                                    </span>
                                                @else
                                                    <span><i class="feather-play text-muted me-1"></i>{{ $wcl['running_ops'] }} {{ __('production.running') }}
                                                        | {{ $wcl['waiting_ops'] }} {{ __('production.ready') }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-3 text-muted">
                                <i class="feather-inbox fs-24 d-block mb-1 opacity-50"></i>
                                <span>{{ __('production.no_active_work_centers_configured') }}</span>
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
                                <div class="fw-bold text-dark fs-14">{{ __('production.materials_and_inventory') }}</div>
                                <span class="fs-11 text-muted">
                                    {{ $fullyIssuedCount }} {{ __('production.ready_to_start') }} &bull;
                                    {{ $materialBlockersCount }} {{ __('production.material_blocked') }} &bull;
                                    {{ $requisitionSummary['total'] ?? 0 }} {{ __('production.total_requisitions') }}
                                </span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 pe-3">
                            <i class="feather-chevron-down collapse-chevron"></i>
                        </div>
                    </div>
                    <div class="production-area-actions border-start ps-3 flex-shrink-0" onclick="event.stopPropagation();">
                        <x-ui.button variant="light" icon="feather-arrow-right"
                            href="{{ route('sales.material-requests.index') }}" title="{{ __('production.materials_and_inventory') }}"></x-ui.button>
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
                                    <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">{{ __('production.fully_issued') }}</span>
                                    <h4 class="fw-bold text-dark font-monospace fs-20 my-1">
                                        {{ number_format($requisitionSummary['fully_issued'] ?? 0) }}
                                    </h4>
                                    <small class="text-muted fs-11">{{ __('production.ready_on_shop_floor') }}</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="p-3 dash-card-inner rounded-3 border text-center">
                                    <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">{{ __('production.partially_issued') }}</span>
                                    <h4 class="fw-bold text-dark font-monospace fs-20 my-1">
                                        {{ number_format($requisitionSummary['partially_issued'] ?? 0) }}
                                    </h4>
                                    <small class="text-muted fs-11">{{ __('production.partial_store_issue') }}</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="p-3 dash-card-inner rounded-3 border text-center">
                                    <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">{{ __('production.pending_store') }}</span>
                                    <h4 class="fw-bold text-dark font-monospace fs-20 my-1">
                                        {{ number_format($requisitionSummary['pending'] ?? 0) }}
                                    </h4>
                                    <small class="text-muted fs-11">{{ __('production.awaiting_release') }}</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="p-3 dash-card-inner rounded-3 border text-center">
                                    <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">{{ __('production.approved') }} / {{ __('production.reserved') }}</span>
                                    <h4 class="fw-bold text-dark font-monospace fs-20 my-1">
                                        {{ number_format($requisitionSummary['approved'] ?? 0) }}
                                    </h4>
                                    <small class="text-muted fs-11">{{ __('production.stock_allocated') }}</small>
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
                                <div class="fw-bold text-dark fs-14">{{ __('production.shop_floor') }}</div>
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
                            href="{{ route('production.mes.dashboard') }}" title="{{ __('production.open_shop_floor') }}"></x-ui.button>
                    </div>
                </div>
                <div class="collapse" id="area-shopfloor">
                    <div class="production-area-body">
                        {{-- Live Manufacturing Pulse & Andon Status --}}
                        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                            <h6 class="card-title fw-bold text-dark mb-0 fs-13">{{ __('production.live_manufacturing_pulse_andon') }}
                            </h6>
                            <div class="d-flex align-items-center gap-2">
                                <span
                                    class="badge bg-soft-primary text-primary fs-11 font-monospace rounded-pill px-2.5 py-1">
                                    {{ __('production.shop_floor_wip') }}: {{ number_format($wipSummary['total_items'] ?? 0) }} {{ __('production.items') }}
                                </span>
                                <span
                                    class="badge bg-soft-primary text-primary fs-11 font-monospace rounded-pill px-2.5 py-1">
                                    {{ __('production.active_tracking_jobs') }}: {{ number_format($totalActiveOrders) }}
                                </span>
                                <x-ui.button variant="light" icon="feather-grid"
                                    href="{{ route('production.mes.machines.index') }}">
                                    {{ __('production.all_machines') }}
                                </x-ui.button>
                                <x-ui.button variant="primary" icon="feather-alert-triangle"
                                    href="{{ route('production.intelligence.andon') }}">
                                    {{ __('production.live_andon_board') }}
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
                                            <span class="fs-11 fw-bold text-primary text-uppercase">{{ __('production.running') }}</span>
                                        </div>
                                        <h3 class="fw-bold text-dark mb-0 font-monospace fs-18">
                                             {{ number_format($machineStateCounts['running'] ?? 0) }}
                                        </h3>
                                        <small class="text-muted fs-11">{{ __('production.active_line') }}</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6 col-sm-4 col-md-2">
                                <a href="{{ route('production.mes.machines.index', ['state' => 'Idle']) }}"
                                    class="text-decoration-none">
                                    <div class="dash-tile dash-tile-default machine-state-box border hover-shadow">
                                        <span class="fs-11 fw-bold text-muted text-uppercase d-block mb-1">{{ __('production.idle') }}</span>
                                        <h3 class="fw-bold text-dark mb-0 font-monospace fs-18">
                                            {{ number_format($machineStateCounts['idle'] ?? 0) }}
                                        </h3>
                                        <small class="text-muted fs-11">{{ __('production.awaiting_jobs') }}</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6 col-sm-4 col-md-2">
                                <a href="{{ route('production.mes.machines.index', ['state' => 'Setup']) }}"
                                    class="text-decoration-none">
                                    <div class="dash-tile dash-tile-default machine-state-box border hover-shadow">
                                        <span class="fs-11 fw-bold text-muted text-uppercase d-block mb-1">{{ __('production.setup') }}</span>
                                        <h3 class="fw-bold text-dark mb-0 font-monospace fs-18">
                                            {{ number_format($machineStateCounts['setup'] ?? 0) }}
                                        </h3>
                                        <small class="text-muted fs-11">{{ __('production.tooling_change') }}</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6 col-sm-4 col-md-2">
                                <a href="{{ route('production.mes.machines.index', ['state' => 'Breakdown']) }}"
                                    class="text-decoration-none">
                                    <div
                                        class="dash-tile dash-tile-default machine-state-box border {{ ($machineStateCounts['breakdown'] ?? 0) > 0 ? 'border-primary' : '' }} hover-shadow">
                                        <span class="fs-11 fw-bold text-muted text-uppercase d-block mb-1">{{ __('production.breakdown') }}</span>
                                        <h3 class="fw-bold text-dark mb-0 font-monospace fs-18">
                                            {{ number_format($machineStateCounts['breakdown'] ?? 0) }}
                                        </h3>
                                        <small class="text-muted fs-11">{{ __('production.halted') }}</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6 col-sm-4 col-md-2">
                                <a href="{{ route('production.mes.machines.index', ['state' => 'Maintenance']) }}"
                                    class="text-decoration-none">
                                    <div
                                        class="dash-tile dash-tile-default machine-state-box border {{ ($machineStateCounts['maintenance'] ?? 0) > 0 ? 'border-primary' : '' }} hover-shadow">
                                        <span
                                            class="fs-11 fw-bold text-muted text-uppercase d-block mb-1">{{ __('production.maintenance_machines') }}</span>
                                        <h3 class="fw-bold text-dark mb-0 font-monospace fs-18">
                                            {{ number_format($machineStateCounts['maintenance'] ?? 0) }}
                                        </h3>
                                        <small class="text-muted fs-11">{{ __('production.pm_servicing') }}</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6 col-sm-4 col-md-2">
                                <a href="{{ route('production.mes.machines.index', ['state' => 'Offline']) }}"
                                    class="text-decoration-none">
                                    <div class="dash-tile dash-tile-default machine-state-box border hover-shadow">
                                        <span class="fs-11 fw-bold text-muted text-uppercase d-block mb-1">{{ __('production.offline_machines') }}</span>
                                        <h3 class="fw-bold text-dark mb-0 font-monospace fs-18">
                                            {{ number_format($machineStateCounts['offline'] ?? 0) }}
                                        </h3>
                                        <small class="text-muted fs-11">{{ __('production.powered_down') }}</small>
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
                                        {{ __('production.equipment_requiring_immediate_attention') }} ({{ $attentionMachines->count() }})
                                    </h6>
                                    <x-ui.button variant="primary" icon="feather-alert-triangle"
                                        href="{{ route('production.intelligence.andon') }}">
                                        {{ __('production.open_live_board') }}
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
                                    <h6 class="fw-bold text-dark mb-0 fs-13">{{ __('production.shop_floor_mes_execution_pulse') }}</h6>
                                    <div class="d-flex flex-wrap gap-2 mt-1 fs-12">
                                        <span><strong
                                                class="text-dark font-monospace">{{ number_format($mesRunningCount ?? 0) }}</strong>
                                            {{ __('production.running_ops') }}</span>
                                        <span class="text-muted">&bull;</span>
                                        <span><strong
                                                class="text-primary font-monospace">{{ number_format($mesReadyCount ?? 0) }}</strong>
                                            {{ __('production.ready_in_queue') }}</span>
                                        <span class="text-muted">&bull;</span>
                                        <span><strong
                                                class="text-dark font-monospace">{{ number_format($mesPausedCount ?? 0) }}</strong>
                                            {{ __('production.pause') }}</span>
                                        <span class="text-muted">&bull;</span>
                                        <span><strong
                                                class="text-dark font-monospace">{{ number_format($mesCompletedTodayCount ?? 0) }}</strong>
                                            {{ __('production.done_today') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <x-ui.button variant="primary" icon="feather-monitor"
                                    href="{{ route('production.mes.dashboard') }}">{{ __('production.open_shop_floor_mes') }}</x-ui.button>
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
                                <div class="fw-bold text-dark fs-14">{{ __('production.step_quality') }}</div>
                                <span class="fs-11 text-muted">
                                    {{ __('production.fpy') }} <strong
                                        class="text-dark font-monospace">{{ number_format($qualityKpis['fpy'] ?? 100, 1) }}%</strong>
                                    &bull;
                                    {{ number_format($qualityKpis['totalInspections'] ?? 0) }} {{ strtolower(__('production.all_inspections')) }} &bull;
                                    {{ number_format($qualityKpis['ncrOpen'] ?? 0) }} {{ __('production.open_ncrs') }} &bull;
                                    {{ number_format($qualityKpis['capaOpen'] ?? 0) }} {{ __('production.active_capas') }}
                                </span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 pe-3">
                            <i class="feather-chevron-down collapse-chevron"></i>
                        </div>
                    </div>
                    <div class="production-area-actions border-start ps-3 flex-shrink-0" onclick="event.stopPropagation();">
                        <x-ui.button variant="light" icon="feather-arrow-right"
                            href="{{ route('production.quality.dashboard') }}" title="{{ __('production.quality_center') }}"></x-ui.button>
                    </div>
                </div>
                <div class="collapse" id="area-quality">
                    <div class="production-area-body">
                        {{-- Quality Intelligence & Defect Analytics --}}
                        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                            <h6 class="card-title fw-bold text-dark mb-0 fs-13">{{ __('production.quality_intelligence_defect_analytics') }}
                            </h6>
                            <div class="d-flex align-items-center gap-2">
                                <x-ui.button variant="light" icon="feather-clipboard"
                                    href="{{ route('production.inspections.index') }}">{{ __('production.all_inspections') }}</x-ui.button>
                                <x-ui.button variant="primary" icon="feather-award"
                                    href="{{ route('production.quality.dashboard') }}">
                                    {{ __('production.quality_dashboard') }}
                                </x-ui.button>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            {{-- First Pass Yield (FPY) --}}
                            <div class="col-xl-3 col-sm-6">
                                <div class="dash-tile dash-tile-default quality-tile-box border h-100">
                                    <div class="d-flex align-items-center justify-content-between mb-1.5">
                                        <span class="fs-11 fw-bold text-muted text-uppercase">{{ __('production.first_pass_yield') }}</span>
                                        <span
                                            class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill">{{ __('production.fpy') }}</span>
                                    </div>
                                    <h2 class="fw-bold text-dark my-1 font-monospace fs-24">
                                        {{ number_format($qualityKpis['fpy'] ?? 100, 1) }}%
                                    </h2>
                                    <small class="text-muted fs-11">{{ __('production.final_stage_acceptance_rate') }}</small>
                                </div>
                            </div>

                            {{-- Inspections Gate Track --}}
                            <div class="col-xl-3 col-sm-6">
                                <div class="dash-tile dash-tile-default quality-tile-box border h-100">
                                    <div class="d-flex align-items-center justify-content-between mb-1.5">
                                        <span class="fs-11 fw-bold text-muted text-uppercase">{{ __('production.inspections_gate') }}</span>
                                        <span
                                            class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill">{{ number_format($qualityKpis['totalInspections'] ?? 0) }}
                                            {{ __('production.total') }}</span>
                                    </div>
                                    <div class="d-flex align-items-baseline gap-3 my-1">
                                        <div>
                                            <span
                                                class="fs-18 fw-bold text-primary font-monospace">{{ number_format($qualityKpis['passedInspections'] ?? 0) }}</span>
                                            <small class="text-muted d-block fs-10">{{ __('production.passed') }}</small>
                                        </div>
                                        <div class="border-start ps-3">
                                            <span
                                                class="fs-18 fw-bold text-dark font-monospace">{{ number_format($qualityKpis['failedInspections'] ?? 0) }}</span>
                                            <small class="text-muted d-block fs-10">{{ __('production.failed') }}</small>
                                        </div>
                                        <div class="border-start ps-3">
                                            <span
                                                class="fs-18 fw-bold text-dark font-monospace">{{ number_format($qualityKpis['pendingInspections'] ?? 0) }}</span>
                                            <small class="text-muted d-block fs-10">{{ __('production.pending') }}</small>
                                        </div>
                                    </div>
                                    <small class="text-muted fs-11">{{ __('production.receiving_inline_final_audits') }}</small>
                                </div>
                            </div>

                            {{-- Active NCRs & CAPAs --}}
                            <div class="col-xl-3 col-sm-6">
                                <div
                                    class="dash-tile dash-tile-default quality-tile-box border {{ ($qualityKpis['ncrOpen'] ?? 0) > 0 ? 'border-primary' : '' }} h-100">
                                    <div class="d-flex align-items-center justify-content-between mb-1.5">
                                        <span class="fs-11 fw-bold text-muted text-uppercase">{{ __('production.non_conformances') }}</span>
                                        @if(($qualityKpis['ncrOpen'] ?? 0) > 0)
                                            <span
                                                class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill">{{ __('production.action_required') }}</span>
                                        @else
                                            <span
                                                class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill">{{ __('production.zero_open') }}</span>
                                        @endif
                                    </div>
                                    <div class="d-flex align-items-baseline gap-3 my-1">
                                        <div>
                                            <span
                                                class="fs-18 fw-bold text-dark font-monospace">{{ number_format($qualityKpis['ncrOpen'] ?? 0) }}</span>
                                            <small class="text-muted d-block fs-10">{{ __('production.open_ncrs') }}
                                                ({{ $qualityKpis['ncrClosed'] ?? 0 }} {{ __('production.closed') }})</small>
                                        </div>
                                        <div class="border-start ps-3">
                                            <span
                                                class="fs-18 fw-bold text-primary font-monospace">{{ number_format($qualityKpis['capaOpen'] ?? 0) }}</span>
                                            <small class="text-muted d-block fs-10">{{ __('production.active_capas') }}</small>
                                        </div>
                                    </div>
                                    <small class="text-muted fs-11">{{ __('production.corrective_action_loop') }}</small>
                                </div>
                            </div>

                            {{-- Dispositions: Rework & Scrap --}}
                            <div class="col-xl-3 col-sm-6">
                                <div class="dash-tile dash-tile-default quality-tile-box border h-100">
                                    <div class="d-flex align-items-center justify-content-between mb-1.5">
                                        <span class="fs-11 fw-bold text-muted text-uppercase">{{ __('production.dispositions') }}</span>
                                        <span
                                            class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill">{{ __('production.scrap_rework') }}</span>
                                    </div>
                                    <div class="d-flex align-items-baseline gap-3 my-1">
                                        <div>
                                            <span
                                                class="fs-18 fw-bold text-dark font-monospace">{{ number_format($qualityKpis['reworkCount'] ?? 0) }}</span>
                                            <small class="text-muted d-block fs-10">{{ __('production.rework_orders') }}</small>
                                        </div>
                                        <div class="border-start ps-3">
                                            <span
                                                class="fs-18 fw-bold text-dark font-monospace">{{ number_format($qualityKpis['scrapCount'] ?? 0) }}</span>
                                            <small class="text-muted d-block fs-10">{{ __('production.scrap_disposals') }}</small>
                                        </div>
                                    </div>
                                    <small class="text-muted fs-11">{{ __('production.material_resolution_dispositions') }}</small>
                                </div>
                            </div>
                        </div>

                        {{-- Top Defect Categories --}}
                        <div
                            class="dash-panel-subtle p-3 rounded-3 border d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span
                                    class="fs-11 fw-bold text-muted text-uppercase d-inline-flex align-items-center gap-1">
                                    <i class="feather-alert-octagon text-primary"></i> {{ __('production.top_defect_categories') }}:
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
                                        class="badge bg-soft-primary text-primary fs-11 font-monospace rounded-pill px-2.5 py-1">{{ __('production.zero_quality_ncrs_active') }}</span>
                                @endif
                            </div>
                            <div class="fs-11 text-muted">
                                {{ __('production.quality_clearance_guard_desc') }}
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
                                <div class="fw-bold text-dark fs-14">{{ __('production.maintenance_and_resources') }}</div>
                                <span class="fs-11 text-muted">
                                    {{ $overduePmCount }} {{ __('production.overdue_pm') }} &bull;
                                    {{ $duePmCount }} {{ __('production.pm_due_7_days') }} &bull;
                                    {{ $openBreakdownWosCount }} {{ __('production.breakdown_wos') }} &bull;
                                    {{ __('production.subcontract_otd') }}: <strong
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
                            href="{{ route('production.maintenance.dashboard') }}" title="{{ __('production.maintenance_hub') }}"></x-ui.button>
                    </div>
                </div>
                <div class="collapse" id="area-maintenance">
                    <div class="production-area-body">
                        {{-- Plant Maintenance & Subcontracting SLA --}}
                        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                            <h6 class="card-title fw-bold text-dark mb-0 fs-13">{{ __('production.plant_maintenance_subcontract_sla') }}</h6>
                            <div class="d-flex align-items-center gap-2">
                                <x-ui.button variant="light" icon="feather-file-text"
                                    href="{{ route('production.maintenance.work-orders.index') }}">{{ __('production.work_orders') }}</x-ui.button>
                                <x-ui.button variant="light" icon="feather-tool"
                                    href="{{ route('production.maintenance.dashboard') }}">{{ __('production.maintenance_hub') }}</x-ui.button>
                                <x-ui.button variant="primary" icon="feather-truck"
                                    href="{{ route('production.subcontract.analytics') }}">{{ __('production.vendor_analytics') }} </x-ui.button>
                            </div>
                        </div>

                        {{-- Maintenance Tiles --}}
                        <div class="row g-3 mb-3">
                            <div class="col-md-3 col-sm-6">
                                <a href="{{ route('production.maintenance.schedules.index') }}"
                                    class="text-decoration-none">
                                    <div
                                        class="dash-tile dash-tile-default maintenance-tile-box border {{ $overduePmCount > 0 ? 'border-primary' : '' }} hover-shadow">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">{{ __('production.overdue_pm') }}</span>
                                        <h3 class="fw-bold text-dark my-1 font-monospace fs-22">
                                            {{ number_format($overduePmCount) }}
                                        </h3>
                                        <small class="text-muted fs-11">{{ __('production.preventive_due') }}</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <a href="{{ route('production.maintenance.schedules.index') }}"
                                    class="text-decoration-none">
                                    <div class="dash-tile dash-tile-default maintenance-tile-box border hover-shadow">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">{{ __('production.pm_due_7_days') }}</span>
                                        <h3 class="fw-bold text-dark my-1 font-monospace fs-22">
                                            {{ number_format($duePmCount) }}
                                        </h3>
                                        <small class="text-muted fs-11">{{ __('production.upcoming_service') }}</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <a href="{{ route('production.maintenance.work-orders.index', ['type' => 'breakdown']) }}"
                                    class="text-decoration-none">
                                    <div
                                        class="dash-tile dash-tile-default maintenance-tile-box border {{ $openBreakdownWosCount > 0 ? 'border-primary' : '' }} hover-shadow">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">{{ __('production.breakdown_wos') }}</span>
                                        <h3 class="fw-bold text-dark my-1 font-monospace fs-22">
                                            {{ number_format($openBreakdownWosCount) }}
                                        </h3>
                                        <small class="text-muted fs-11">{{ __('production.stoppage_orders') }}</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <a href="{{ route('production.mes.machines.index', ['status' => 'under_maintenance']) }}"
                                    class="text-decoration-none">
                                    <div class="dash-tile dash-tile-default maintenance-tile-box border hover-shadow">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">{{ __('production.under_maintenance') }}</span>
                                        <h3 class="fw-bold text-dark my-1 font-monospace fs-22">
                                            {{ number_format($machinesUnderMaintenanceCount) }}
                                        </h3>
                                        <small class="text-muted fs-11">{{ __('production.out_of_service') }}</small>
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
                                            <i class="feather-truck text-primary"></i> {{ __('production.multi_model_subcontracting_vendor_metrics') }}</span>
                                        <span
                                            class="badge bg-soft-primary text-primary fs-11 font-monospace rounded-pill px-2.5 py-1">
                                            {{ __('production.otd_prefix') }} {{ number_format($subcontractDelivery['on_time_delivery_pct'] ?? 100, 1) }}%
                                        </span>
                                        @if(($subcontractDelivery['avg_late_delay_days'] ?? 0) > 0)
                                            <span
                                                class="badge bg-soft-primary text-primary fs-11 font-monospace rounded-pill px-2.5 py-1">
                                                {{ __('production.avg_delay') }}: +{{ number_format($subcontractDelivery['avg_late_delay_days'], 1) }}d
                                            </span>
                                        @endif
                                    </div>
                                    <span class="fs-11 text-muted">{{ __('production.outside_subcontracting_pipeline') }}</span>
                                </div>
                                <div class="row g-2 text-center">
                                    <div class="col">
                                        <a href="{{ route('production.orders.index', ['filter' => 'subcontract_awaiting_pr']) }}"
                                            class="text-decoration-none">
                                            <div
                                                class="dash-tile dash-tile-default subcontract-pipeline-box border hover-shadow">
                                                <span class="fs-10 text-muted text-uppercase fw-bold d-block mb-0.5">{{ __('production.awaiting_pr') }}</span>
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
                                                <span class="fs-10 text-muted text-uppercase fw-bold d-block mb-0.5">{{ __('production.po_awaiting') }}</span>
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
                                                <span class="fs-10 text-muted text-uppercase fw-bold d-block mb-0.5">{{ __('production.ready_dispatch') }}</span>
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
                                                <span class="fs-10 text-muted text-uppercase fw-bold d-block mb-0.5">{{ __('production.at_vendor') }}</span>
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
                                                    class="fs-10 text-muted text-uppercase fw-bold d-block mb-0.5">{{ __('production.vendor_delayed') }}</span>
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
                                                <span class="fs-10 text-muted text-uppercase fw-bold d-block mb-0.5">{{ __('production.qc_pending') }}</span>
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
                                                <span class="fs-10 text-muted text-uppercase fw-bold d-block mb-0.5">{{ __('production.vendor_rework') }}</span>
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
                                <div class="fw-bold text-dark fs-14">{{ __('production.planning_and_reports') }}</div>
                                <span class="fs-11 text-muted">
                                    {{ __('production.adherence') }} <strong
                                        class="text-dark font-monospace">{{ number_format($productionSummary['schedule_adherence'] ?? 100, 1) }}%</strong>
                                    &bull;
                                    {{ __('production.duration_efficiency') }} <strong
                                        class="text-dark font-monospace">{{ number_format($durationEfficiency, 1) }}%</strong>
                                    &bull;
                                    {{ $atRiskOrders->count() }} {{ __('production.near_term_risk') }}
                                </span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 pe-3">
                            <i class="feather-chevron-down collapse-chevron"></i>
                        </div>
                    </div>
                    <div class="production-area-actions border-start ps-3 flex-shrink-0" onclick="event.stopPropagation();">
                        <x-ui.button variant="light" icon="feather-arrow-right"
                            href="{{ route('production.variances.index') }}" title="{{ __('production.variance_analysis') }}"></x-ui.button>
                    </div>
                </div>
                <div class="collapse" id="area-planning">
                    <div class="production-area-body">
                        {{-- Execution Variance, Order Risk & Planning Pulse --}}
                        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                            <h6 class="card-title fw-bold text-dark mb-0 fs-13">{{ __('production.execution_variance_order_risk_pulse') }}</h6>
                            <div class="d-flex align-items-center gap-2">
                                <x-ui.button variant="light" icon="feather-activity"
                                    href="{{ route('production.variances.index') }}">{{ __('production.routing_variances') }}</x-ui.button>
                                <x-ui.button variant="light" icon="feather-calendar"
                                    href="{{ route('production.plans.index') }}">
                                    {{ __('production.master_plans') }}
                                </x-ui.button>
                                <x-ui.button variant="primary" icon="feather-git-pull-request"
                                    href="{{ route('production.ecos.index') }}">{{ __('production.eco_hub') }}</x-ui.button>
                            </div>
                        </div>

                        {{-- Six Big Losses & Cycle Time Intelligence --}}
                        <div class="mb-3 p-3 rounded-3 dash-panel-subtle border">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="fs-11 fw-bold text-uppercase text-muted">{{ __('production.tpm_six_big_losses') }}</span>
                                <small class="text-muted fs-11">{{ __('production.overall_downtime_rate') }}: <strong
                                        class="text-dark font-monospace">{{ number_format($downtimeRate, 1) }}%</strong></small>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-2 col-sm-4 col-6">
                                    <div class="dash-tile dash-tile-default loss-tile-box border hover-shadow">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">{{ __('production.equipment_failure') }}</span>
                                        <h4 class="fw-bold text-dark my-1 font-monospace fs-16">
                                            {{ number_format($sixBigLosses['equipment_failure_minutes'] ?? 0, 1) }}m
                                        </h4>
                                        <small class="text-muted fs-10">{{ __('production.breakdowns') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-2 col-sm-4 col-6">
                                    <div class="dash-tile dash-tile-default loss-tile-box border hover-shadow">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">{{ __('production.setup_and_adjust') }}</span>
                                        <h4 class="fw-bold text-dark my-1 font-monospace fs-16">
                                            {{ number_format($sixBigLosses['setup_adjustment_minutes'] ?? 0, 1) }}m
                                        </h4>
                                        <small class="text-muted fs-10">{{ __('production.tooling_change') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-2 col-sm-4 col-6">
                                    <div class="dash-tile dash-tile-default loss-tile-box border hover-shadow">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">{{ __('production.minor_stops') }}</span>
                                        <h4 class="fw-bold text-dark my-1 font-monospace fs-16">
                                            {{ number_format($sixBigLosses['minor_stops_minutes'] ?? 0, 1) }}m
                                        </h4>
                                        <small class="text-muted fs-10">{{ __('production.minor_stops') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-2 col-sm-4 col-6">
                                    <div class="dash-tile dash-tile-default loss-tile-box border hover-shadow">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">{{ __('production.reduced_speed') }}</span>
                                        <h4 class="fw-bold text-dark my-1 font-monospace fs-16">
                                            {{ number_format($sixBigLosses['reduced_speed_minutes'] ?? 0, 1) }}m
                                        </h4>
                                        <small class="text-muted fs-10">{{ __('production.slow_pace') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-2 col-sm-4 col-6">
                                    <div class="dash-tile dash-tile-default loss-tile-box border hover-shadow">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">{{ __('production.startup_rejects') }}</span>
                                        <h4 class="fw-bold text-dark my-1 font-monospace fs-16">
                                            {{ number_format($sixBigLosses['startup_rejects_count'] ?? 0) }}
                                        </h4>
                                        <small class="text-muted fs-10">{{ __('production.first_run') }}</small>
                                    </div>
                                </div>
                                <div class="col-md-2 col-sm-4 col-6">
                                    <div class="dash-tile dash-tile-default loss-tile-box border hover-shadow">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">{{ __('production.production_scrap') }}</span>
                                        <h4 class="fw-bold text-dark my-1 font-monospace fs-16">
                                            {{ number_format($sixBigLosses['production_rejects_count'] ?? 0) }}
                                        </h4>
                                        <small class="text-muted fs-10">{{ __('production.in_process') }}</small>
                                    </div>
                                </div>
                            </div>

                            {{-- Cycle Times & Asset Utilizations Strip --}}
                            <div class="row g-3 pt-3 mt-1 border-top">
                                <div class="col-md-6">
                                    <div
                                        class="p-3 rounded-3 dash-card-inner border h-100 d-flex flex-column justify-content-between">
                                        <span class="fs-11 fw-bold text-uppercase text-muted d-block mb-3">
                                            <i class="feather-clock me-1 text-primary"></i>{{ __('production.cycle_times_waiting_averages') }}</span>
                                        <div class="row g-2 text-center">
                                            <div class="col-3 py-2">
                                                <small class="text-muted fs-11 d-block mb-1">{{ __('production.setup') }}</small>
                                                <strong
                                                    class="font-monospace text-dark fs-14">{{ number_format($cycleTimes['avg_setup_time'] ?? 0, 1) }}m</strong>
                                            </div>
                                            <div class="col-3 border-start py-2">
                                                <small class="text-muted fs-11 d-block mb-1">{{ __('production.processing') }}</small>
                                                <strong
                                                    class="font-monospace text-dark fs-14">{{ number_format($cycleTimes['avg_processing_time'] ?? 0, 1) }}m</strong>
                                            </div>
                                            <div class="col-3 border-start py-2">
                                                <small class="text-muted fs-11 d-block mb-1">{{ __('production.total_cycle') }}</small>
                                                <strong
                                                    class="font-monospace text-primary fs-14">{{ number_format($cycleTimes['avg_cycle_time'] ?? 0, 1) }}m</strong>
                                            </div>
                                            <div class="col-3 border-start py-2">
                                                <small class="text-muted fs-11 d-block mb-1">{{ __('production.waiting') }}</small>
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
                                            <i class="feather-cpu me-1 text-primary"></i>{{ __('production.resource_utilizations') }}</span>
                                        <div class="row g-2 text-center">
                                            <div class="col-4 py-2">
                                                <small class="text-muted fs-11 d-block mb-1">{{ __('production.all_machines') }}</small>
                                                <strong
                                                    class="font-monospace text-dark fs-14">{{ number_format($assetUtilizations['machine_utilization'] ?? 0, 1) }}%</strong>
                                            </div>
                                            <div class="col-4 border-start py-2">
                                                <small class="text-muted fs-11 d-block mb-1">{{ __('production.operators') }}</small>
                                                <strong
                                                    class="font-monospace text-dark fs-14">{{ number_format($assetUtilizations['operator_utilization'] ?? 0, 1) }}%</strong>
                                            </div>
                                            <div class="col-4 border-start py-2">
                                                <small class="text-muted fs-11 d-block mb-1">{{ __('production.step_work_centers') }}</small>
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
                                                <i class="feather-percent text-primary"></i> {{ __('production.execution_variance') }} ({{ ($timeframe ?? 'today') === 'week' ? __('production.this_week') : (($timeframe ?? 'today') === 'month' ? __('production.this_month') : __('production.today')) }})
                                            </span>
                                            <span
                                                class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill px-2 py-0.5">
                                                {{ number_format($productionSummary['schedule_adherence'] ?? 100, 1) }}%
                                                {{ __('production.adherence') }}
                                            </span>
                                        </div>

                                        {{-- Output Metrics --}}
                                        <div class="p-2.5 rounded-3 dash-card-inner border mb-2">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <span class="fs-11 text-muted">{{ __('production.output_units_planned_vs_actual') }}</span>
                                                <span class="fs-11 fw-bold font-monospace text-dark">
                                                    {{ $outputVariance >= 0 ? '+' . number_format($outputVariance) : number_format($outputVariance) }}
                                                    {{ __('production.net') }}
                                                </span>
                                            </div>
                                            <div class="d-flex align-items-baseline justify-content-between">
                                                <span class="fs-13 fw-bold font-monospace text-dark">
                                                    {{ number_format($productionSummary['actual_quantity'] ?? 0) }} <small
                                                        class="text-muted fw-normal">/
                                                        {{ number_format($productionSummary['planned_quantity'] ?? 0) }}</small>
                                                </span>
                                                <small class="text-muted fs-11">{{ __('production.yield') }}: <strong
                                                        class="text-dark font-monospace">{{ number_format($scrapStats['yield'] ?? 100, 1) }}%</strong></small>
                                            </div>
                                        </div>

                                        {{-- Duration Metrics --}}
                                        <div class="p-2.5 rounded-3 dash-card-inner border">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <span class="fs-11 text-muted">{{ __('production.operation_hours_actual_vs_plan') }}</span>
                                                <span class="fs-11 fw-bold font-monospace text-dark">
                                                    {{ $durationVarianceHours > 0 ? '+' . $durationVarianceHours : $durationVarianceHours }}h
                                                    {{ __('production.delta') }}
                                                </span>
                                            </div>
                                            <div class="d-flex align-items-baseline justify-content-between">
                                                <span class="fs-13 fw-bold font-monospace text-dark">
                                                    {{ number_format($actualDurationHours, 1) }}h <small
                                                        class="text-muted fw-normal">/
                                                        {{ number_format($plannedDurationHours, 1) }}h</small>
                                                </span>
                                                <small class="text-muted fs-11">{{ __('production.efficiency_prefix') }} <strong
                                                        class="text-dark font-monospace">{{ number_format($durationEfficiency, 1) }}%</strong></small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="pt-2 mt-2 border-top text-end">
                                        <a href="{{ route('production.variances.index') }}"
                                            class="fs-11 text-primary text-decoration-none fw-semibold">
                                            {{ __('production.full_variance_analysis') }} <i class="feather-arrow-right ms-1"></i>
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
                                                    <i class="feather-clock text-primary"></i> {{ __('production.near_term_completion_risk') }}</span>
                                                <span
                                                    class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill px-2 py-0.5">
                                                    {{ $atRiskOrders->count() }} {{ __('production.at_risk') }}
                                                </span>
                                            </div>
                                            @if($qualityConstrainedOrdersCount > 0)
                                                <span
                                                    class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill px-2 py-0.5">
                                                    <i class="feather-shield me-1"></i>{{ $qualityConstrainedOrdersCount }} {{ __('production.quality_hold') }}
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
                                                            <th>{{ __('production.order_and_product') }}</th>
                                                            <th class="text-center">{{ __('production.due_countdown') }}</th>
                                                            <th>{{ __('production.progress') }}</th>
                                                            <th class="text-end">{{ __('production.current_op') }}</th>
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
                                                                $countdownLabel = $hoursLeft <= 24 ? __('production.today') : ($hoursLeft <= 48 ? '1 ' . __('production.day') : '2 ' . __('production.day'));
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
                                                                            class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill px-1.5">{{ __('production.in_queue') }}</span>
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
                                                <span class="fs-12 fw-semibold text-dark">{{ __('production.no_near_term_completion_risk') }}</span>
                                                <div class="fs-11 text-muted">{{ __('production.all_orders_achieved_progress') }}</div>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="pt-2 mt-2 border-top text-end">
                                        <a href="{{ route('production.planning-exceptions.index') }}"
                                            class="fs-11 text-primary text-decoration-none fw-semibold">
                                            {{ __('production.full_exception_engine') }} <i class="feather-arrow-right ms-1"></i>
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
                                            <i class="feather-layers me-1 text-primary"></i>{{ __('production.planning_and_eco_pulse') }}</span>

                                        {{-- Master Plans Strip --}}
                                        <div class="p-2 rounded-3 dash-card-inner border mb-2">
                                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                                <span class="fs-11 fw-bold text-dark"><i class="feather-calendar me-1 text-primary"></i>{{ __('production.master_plans') }}</span>
                                                <a href="{{ route('production.plans.index') }}"
                                                    class="fs-10 text-primary text-decoration-none font-monospace">{{ __('production.view_all') }}</a>
                                            </div>
                                            <div class="d-flex flex-wrap gap-1 text-center">
                                                <div class="flex-fill p-1 rounded dash-mini-tile border">
                                                    <small class="text-muted fs-10 d-block">{{ __('production.draft') }}</small>
                                                    <strong
                                                        class="font-monospace text-dark fs-12">{{ $planStatusCounts['draft'] ?? 0 }}</strong>
                                                </div>
                                                <div class="flex-fill p-1 rounded dash-mini-tile border">
                                                    <small class="text-muted fs-10 d-block">{{ __('production.pending') }}</small>
                                                    <strong
                                                        class="font-monospace text-dark fs-12">{{ $planStatusCounts['pending_approval'] ?? 0 }}</strong>
                                                </div>
                                                <div class="flex-fill p-1 rounded dash-mini-tile border">
                                                    <small class="text-muted fs-10 d-block">{{ __('production.approved') }}</small>
                                                    <strong
                                                        class="font-monospace text-dark fs-12">{{ $planStatusCounts['approved'] ?? 0 }}</strong>
                                                </div>
                                                <div class="flex-fill p-1 rounded dash-mini-tile border">
                                                    <small class="text-muted fs-10 d-block">{{ __('production.released') }}</small>
                                                    <strong
                                                        class="font-monospace text-primary fs-12">{{ $planStatusCounts['released'] ?? 0 }}</strong>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- ECO Pipeline Strip --}}
                                        <div class="p-2 rounded-3 dash-card-inner border">
                                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                                <span class="fs-11 fw-bold text-dark"><i class="feather-git-pull-request me-1 text-primary"></i>{{ __('production.eco_pipeline') }}</span>
                                                <a href="{{ route('production.ecos.index') }}"
                                                    class="fs-10 text-primary text-decoration-none font-monospace">{{ __('production.view_all') }}</a>
                                            </div>
                                            <div class="d-flex flex-wrap gap-1 text-center">
                                                <div class="flex-fill p-1 rounded dash-mini-tile border">
                                                    <small class="text-muted fs-10 d-block">{{ __('production.draft') }}</small>
                                                    <strong
                                                        class="font-monospace text-dark fs-12">{{ $ecoStatusCounts['draft'] ?? 0 }}</strong>
                                                </div>
                                                <div class="flex-fill p-1 rounded dash-mini-tile border">
                                                    <small class="text-muted fs-10 d-block">{{ __('production.review') }}</small>
                                                    <strong
                                                        class="font-monospace text-dark fs-12">{{ $ecoStatusCounts['under_review'] ?? 0 }}</strong>
                                                </div>
                                                <div class="flex-fill p-1 rounded dash-mini-tile border">
                                                    <small class="text-muted fs-10 d-block">{{ __('production.approved') }}</small>
                                                    <strong
                                                        class="font-monospace text-dark fs-12">{{ $ecoStatusCounts['approved'] ?? 0 }}</strong>
                                                </div>
                                                <div class="flex-fill p-1 rounded dash-mini-tile border">
                                                    <small class="text-muted fs-10 d-block">{{ __('production.released') }}</small>
                                                    <strong
                                                        class="font-monospace text-primary fs-12">{{ $ecoStatusCounts['released'] ?? 0 }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="pt-2 mt-2 border-top d-flex align-items-center justify-content-between fs-11">
                                        <span class="text-muted">{{ __('production.engineering_changes') }}</span>
                                        <a href="{{ route('production.ecos.index') }}"
                                            class="text-primary text-decoration-none fw-semibold">
                                            {{ __('production.eco_register') }} <i class="feather-arrow-right ms-1"></i>
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
                    'label' => __('production.pending_demand'),
                    'icon' => 'feather-inbox',
                    'badge' => $pendingSalesOrderCount,
                ],
                [
                    'id' => 'content-ready',
                    'label' => __('production.ready_to_start'),
                    'icon' => 'feather-play',
                    'badge' => $readyToStartCount,
                ],
                [
                    'id' => 'content-inprogress',
                    'label' => __('production.active_in_progress'),
                    'icon' => 'feather-activity',
                    'badge' => $inProgressOrders->count(),
                ],
                [
                    'id' => 'content-overdue',
                    'label' => __('production.at_risk_overdue'),
                    'icon' => 'feather-alert-triangle',
                    'badge' => $overdueOrdersCount,
                ],
            ];
        @endphp
        <div class="card dash-card border-0 shadow-sm rounded-3 mb-3 overflow-hidden" id="worklist-tabs-section">
            <div
                class="card-header pt-3 pb-0 px-4 border-bottom-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h6 class="card-title fw-bold text-dark mb-0 fs-14">{{ __('production.operational_worklists') }}</h6>
                    <span class="fs-11 text-muted">{{ __('production.operational_worklists_desc') }}</span>
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
                                <h6 class="fw-bold text-dark mb-0 fs-13">{{ __('production.pending_sales_orders_to_manufacture') }}</h6>
                                <small class="text-muted d-block fs-11">{{ __('production.sales_orders_awaiting_conversion') }}</small>
                            </div>
                            <span class="badge bg-soft-primary text-primary fs-11 px-2.5 py-1 rounded-pill font-monospace">
                                {{ __('production.pending_demands_badge', ['count' => $pendingSalesOrderCount]) }}
                            </span>
                        </div>
                        <div class="table-responsive worklist-table-container">
                            <table class="table table-hover align-middle mb-0 fs-13">
                                <thead class="dash-table-head text-muted border-bottom fs-11 text-uppercase">
                                    <tr>
                                        <th class="ps-4">{{ __('production.request_so_reference') }}</th>
                                        <th>{{ __('production.product_sku') }}</th>
                                        <th class="text-end">{{ __('production.requested_qty') }}</th>
                                        <th class="text-center">{{ __('production.required_date') }}</th>
                                        <th class="text-center">{{ __('production.status') }}</th>
                                        <th class="text-center pe-4">{{ __('production.action') }}</th>
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
                                                    <div class="fs-11 text-muted">{{ __('production.direct_demand') }}</div>
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
                                                    {{ __('production.create_order') }}
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
                                                    <h6 class="fw-semibold text-dark mb-1 fs-14">{{ __('production.pending_sales_orders_to_manufacture') }}</h6>
                                                    <p class="text-muted fs-12 mb-0">{{ __('production.all_sales_demands_active') }}</p>
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
                                        <th class="ps-4">{{ __('production.order_mode') }}</th>
                                        <th>{{ __('production.product_sku') }}</th>
                                        <th class="text-end">{{ __('production.target_qty') }}</th>
                                        <th class="text-center">{{ __('production.status') }}</th>
                                        <th class="text-center">{{ __('production.requisition_status') }}</th>
                                        <th class="text-center">{{ __('production.target_date') }}</th>
                                        <th class="text-center pe-4">{{ __('production.action') }}</th>
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
                                                        <i class="feather-check-circle me-1"></i>{{ __('production.fully_issued') }}
                                                    </span>
                                                @elseif($hasPartial)
                                                    <span
                                                        class="badge bg-soft-primary text-primary fs-11 px-2.5 py-1 rounded-pill font-monospace">
                                                        <i class="feather-clock me-1"></i>{{ __('production.partially_issued') }}
                                                    </span>
                                                @else
                                                    <span
                                                        class="badge bg-soft-primary text-primary fs-11 px-2.5 py-1 rounded-pill font-monospace">
                                                        {{ __('production.pending_store') }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-center font-monospace fs-12 text-muted">
                                                {{ $order->end_date ? \Carbon\Carbon::parse($order->end_date)->format('M d, Y') : '—' }}
                                            </td>
                                            <td class="text-center pe-4">
                                                <x-ui.button variant="primary" icon="feather-play"
                                                    href="{{ route('production.orders.show', ['order' => $order->id, 'tab' => 'vtab-operations']) }}">
                                                    {{ __('production.start_production') }}
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
                                                    <h6 class="fw-semibold text-dark mb-1 fs-14">{{ __('production.no_orders_ready_to_start') }}</h6>
                                                    <p class="text-muted fs-12 mb-0">{{ __('production.no_orders_waiting_ready_to_start') }}</p>
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
                                        <th class="ps-4">{{ __('production.order_mode') }}</th>
                                        <th>{{ __('production.product_sku') }}</th>
                                        <th class="text-end">{{ __('production.produced_target') }}</th>
                                        <th style="min-width: 130px;">{{ __('production.progress') }}</th>
                                        <th>{{ __('production.active_operations') }}</th>
                                        <th class="text-center pe-4">{{ __('production.action') }}</th>
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
                                                        <span class="text-muted fs-11">{{ __('production.no_operations_defined') }}</span>
                                                    @endforelse
                                                    @if($order->operations->count() > 3)
                                                        <span
                                                            class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill">{{ __('production.more_count', ['count' => $order->operations->count() - 3]) }}</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="text-center pe-4">
                                                <div class="d-flex align-items-center justify-content-center gap-1.5">
                                                    <x-ui.button variant="primary" icon="feather-monitor"
                                                        href="{{ route('production.mes.dashboard') }}">
                                                        {{ __('production.mes') }}
                                                    </x-ui.button>
                                                    <x-ui.button variant="light" icon="feather-eye"
                                                        href="{{ route('production.orders.show', $order->id) }}">
                                                        {{ __('production.show') }}
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
                                                    <h6 class="fw-semibold text-dark mb-1 fs-14">{{ __('production.no_active_in_progress_execution') }}
                                                    </h6>
                                                    <p class="text-muted fs-12 mb-0">{{ __('production.no_orders_released_or_in_progress') }}</p>
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
                                        <th class="ps-4">{{ __('production.order_mode') }}</th>
                                        <th>{{ __('production.product_sku') }}</th>
                                        <th class="text-end">{{ __('production.target_qty') }}</th>
                                        <th class="text-center">{{ __('production.status') }}</th>
                                        <th class="text-center">{{ __('production.planned_end_date') }}</th>
                                        <th class="text-center">{{ __('production.overdue_days') }}</th>
                                        <th class="text-center pe-4">{{ __('production.action') }}</th>
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
                                                    {{ $daysOverdue }} {{ __('production.days_overdue') }}
                                                </span>
                                            </td>
                                            <td class="text-center pe-4">
                                                <x-ui.button variant="primary" icon="feather-arrow-right"
                                                    href="{{ route('production.orders.show', $order->id) }}">
                                                    {{ __('production.expedite') }}
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
                                                    <h6 class="fw-semibold text-dark mb-1 fs-14">{{ __('production.zero_overdue_orders') }}</h6>
                                                    <p class="text-muted fs-12 mb-0">{{ __('production.mfg_throughput_adhering') }}</p>
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
                    <h6 class="card-title fw-bold text-dark mb-0 fs-14">{{ __('production.recent_activity') }}</h6>
                    <span class="fs-11 text-muted">{{ __('production.latest_mfg_operational_events') }}</span>
                </div>
                <span class="fs-11 text-muted">{{ __('production.realtime_event_trail') }}</span>
            </div>
            <div class="card-body p-0">
                @if(isset($recentOrders) && $recentOrders->isNotEmpty())
                    <div class="table-responsive mb-0">
                        <table class="table table-hover align-middle mb-0 fs-13">
                            <thead class="dash-table-head text-muted border-bottom fs-11 text-uppercase">
                                <tr>
                                    <th class="ps-4" style="width: 140px;">{{ __('production.time') }}</th>
                                    <th style="width: 130px;">{{ __('production.type') }}</th>
                                    <th>{{ __('production.description') }}</th>
                                    <th style="width: 180px;">{{ __('production.reference') }}</th>
                                    <th class="text-center pe-4" style="width: 130px;">{{ __('production.status') }}</th>
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
                                                {{ __('production.production') }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-dark fw-medium">{{ __('production.production_order_registered_for') }}
                                                {{ $rec->product?->name ?? __('production.finished_good') }}</span>
                                            <small class="text-muted d-block fs-11 font-monospace">{{ __('production.qty') }}:
                                                {{ number_format($rec->quantity_ordered, 1) }} {{ __('production.units') }}</small>
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
                        <span class="fs-12">{{ __('production.no_recent_production_events') }}</span>
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