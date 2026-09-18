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
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
            background-color: color-mix(in srgb, var(--bs-primary, #3454d1) 12%, transparent);
            color: var(--bs-primary, #3454d1);
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

        /* Common Dashboard Tile (Action, Machine State, Quality, Loss, Maintenance, Subcontract) */
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
            min-height: 116px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 16px 14px;
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

        .ready-start-banner {
            background: linear-gradient(135deg, color-mix(in srgb, var(--bs-primary, #3454d1) 8%, transparent) 0%, color-mix(in srgb, var(--bs-primary, #3454d1) 3%, transparent) 100%);
            border-left: 5px solid var(--bs-primary, #3454d1);
            border-radius: 10px;
        }

        /* Soft primary badge and tints */
        .production-dashboard-wrapper .bg-soft-primary {
            background-color: color-mix(in srgb, var(--bs-primary, #3454d1) 12%, transparent) !important;
            color: var(--bs-primary, #3454d1) !important;
        }
        .production-dashboard-wrapper .text-primary {
            color: var(--bs-primary, #3454d1) !important;
        }
        .production-dashboard-wrapper .border-primary {
            border-color: var(--bs-primary, #3454d1) !important;
        }

        /* --------------------------------------------------------------------------
           DARK MODE OVERRIDES (html.app-skin-dark, body.app-skin-dark, [data-bs-theme="dark"])
           -------------------------------------------------------------------------- */
        html.app-skin-dark .production-dashboard-wrapper,
        html.app-skin-dark .production-dashboard-wrapper .card,
        html.app-skin-dark .dash-card,
        body.app-skin-dark .production-dashboard-wrapper,
        body.app-skin-dark .production-dashboard-wrapper .card,
        body.app-skin-dark .dash-card,
        [data-bs-theme="dark"] .production-dashboard-wrapper,
        [data-bs-theme="dark"] .production-dashboard-wrapper .card,
        [data-bs-theme="dark"] .dash-card {
            background-color: #0f172a !important;
            border-color: #1e293b !important;
            color: #cbd5e1 !important;
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

        /* All Dashboard Tiles in Dark Mode */
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
        body.app-skin-dark .subcontract-pipeline-box,
        [data-bs-theme="dark"] .dash-tile,
        [data-bs-theme="dark"] .dash-tile-default,
        [data-bs-theme="dark"] .action-tile-card,
        [data-bs-theme="dark"] .machine-state-box,
        [data-bs-theme="dark"] .quality-tile-box,
        [data-bs-theme="dark"] .loss-tile-box,
        [data-bs-theme="dark"] .maintenance-tile-box,
        [data-bs-theme="dark"] .subcontract-pipeline-box {
            background-color: #131c31 !important;
            border-color: #1e2a42 !important;
            color: #cbd5e1 !important;
        }

        html.app-skin-dark .dash-tile:hover,
        html.app-skin-dark .dash-tile-default:hover,
        html.app-skin-dark .action-tile-card:hover,
        html.app-skin-dark .machine-state-box:hover,
        html.app-skin-dark .quality-tile-box:hover,
        html.app-skin-dark .loss-tile-box:hover,
        html.app-skin-dark .maintenance-tile-box:hover,
        html.app-skin-dark .subcontract-pipeline-box:hover,
        body.app-skin-dark .dash-tile:hover,
        body.app-skin-dark .dash-tile-default:hover,
        body.app-skin-dark .action-tile-card:hover,
        body.app-skin-dark .machine-state-box:hover,
        body.app-skin-dark .quality-tile-box:hover,
        body.app-skin-dark .loss-tile-box:hover,
        body.app-skin-dark .maintenance-tile-box:hover,
        body.app-skin-dark .subcontract-pipeline-box:hover,
        [data-bs-theme="dark"] .dash-tile:hover,
        [data-bs-theme="dark"] .dash-tile-default:hover,
        [data-bs-theme="dark"] .action-tile-card:hover,
        [data-bs-theme="dark"] .machine-state-box:hover,
        [data-bs-theme="dark"] .quality-tile-box:hover,
        [data-bs-theme="dark"] .loss-tile-box:hover,
        [data-bs-theme="dark"] .maintenance-tile-box:hover,
        [data-bs-theme="dark"] .subcontract-pipeline-box:hover {
            background-color: #182440 !important;
            border-color: #263859 !important;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.4) !important;
        }

        /* Subtle Nested Panels in Dark Mode */
        html.app-skin-dark .dash-panel-subtle,
        html.app-skin-dark .production-dashboard-wrapper .dash-panel-subtle,
        body.app-skin-dark .dash-panel-subtle,
        body.app-skin-dark .production-dashboard-wrapper .dash-panel-subtle,
        [data-bs-theme="dark"] .dash-panel-subtle,
        [data-bs-theme="dark"] .production-dashboard-wrapper .dash-panel-subtle {
            background-color: #111a2d !important;
            border-color: #1e293b !important;
            color: #cbd5e1 !important;
        }

        /* Inner Nested Cards in Dark Mode */
        html.app-skin-dark .dash-card-inner,
        html.app-skin-dark .production-dashboard-wrapper .dash-card-inner,
        body.app-skin-dark .dash-card-inner,
        body.app-skin-dark .production-dashboard-wrapper .dash-card-inner,
        [data-bs-theme="dark"] .dash-card-inner,
        [data-bs-theme="dark"] .production-dashboard-wrapper .dash-card-inner {
            background-color: #151f36 !important;
            border-color: #22304d !important;
            color: #cbd5e1 !important;
        }

        /* Mini Status Tiles in Dark Mode */
        html.app-skin-dark .dash-mini-tile,
        html.app-skin-dark .production-dashboard-wrapper .dash-mini-tile,
        body.app-skin-dark .dash-mini-tile,
        body.app-skin-dark .production-dashboard-wrapper .dash-mini-tile,
        [data-bs-theme="dark"] .dash-mini-tile,
        [data-bs-theme="dark"] .production-dashboard-wrapper .dash-mini-tile {
            background-color: #111a2d !important;
            border-color: #1e293b !important;
            color: #cbd5e1 !important;
        }

        /* Catch-all for any nested bg-light or bg-white in dashboard */
        html.app-skin-dark .production-dashboard-wrapper .bg-light,
        html.app-skin-dark .production-dashboard-wrapper .bg-white,
        body.app-skin-dark .production-dashboard-wrapper .bg-light,
        body.app-skin-dark .production-dashboard-wrapper .bg-white,
        [data-bs-theme="dark"] .production-dashboard-wrapper .bg-light,
        [data-bs-theme="dark"] .production-dashboard-wrapper .bg-white {
            background-color: #131c31 !important;
            border-color: #1e293b !important;
            color: #cbd5e1 !important;
        }

        /* Material Readiness Banner in Dark Mode */
        html.app-skin-dark .ready-start-banner,
        body.app-skin-dark .ready-start-banner,
        [data-bs-theme="dark"] .ready-start-banner {
            background: linear-gradient(135deg, color-mix(in srgb, var(--bs-primary, #3454d1) 18%, transparent) 0%, color-mix(in srgb, var(--bs-primary, #3454d1) 5%, transparent) 100%) !important;
            border-left: 5px solid var(--bs-primary, #3454d1) !important;
            border-top: 1px solid color-mix(in srgb, var(--bs-primary, #3454d1) 25%, transparent) !important;
            border-right: 1px solid color-mix(in srgb, var(--bs-primary, #3454d1) 25%, transparent) !important;
            border-bottom: 1px solid color-mix(in srgb, var(--bs-primary, #3454d1) 25%, transparent) !important;
        }

        /* Table Head and Rows in Dark Mode */
        html.app-skin-dark .production-dashboard-wrapper table thead.dash-table-head th,
        html.app-skin-dark .production-dashboard-wrapper table thead th,
        body.app-skin-dark .production-dashboard-wrapper table thead.dash-table-head th,
        body.app-skin-dark .production-dashboard-wrapper table thead th,
        [data-bs-theme="dark"] .production-dashboard-wrapper table thead.dash-table-head th,
        [data-bs-theme="dark"] .production-dashboard-wrapper table thead th {
            background-color: #131c31 !important;
            color: #94a3b8 !important;
            border-color: #1e293b !important;
        }

        html.app-skin-dark .production-dashboard-wrapper table tbody td,
        body.app-skin-dark .production-dashboard-wrapper table tbody td,
        [data-bs-theme="dark"] .production-dashboard-wrapper table tbody td {
            background-color: transparent !important;
            border-color: #1e293b !important;
            color: #cbd5e1 !important;
        }

        html.app-skin-dark .production-dashboard-wrapper table tbody tr:hover td,
        body.app-skin-dark .production-dashboard-wrapper table tbody tr:hover td,
        [data-bs-theme="dark"] .production-dashboard-wrapper table tbody tr:hover td {
            background-color: rgba(255, 255, 255, 0.03) !important;
        }

        /* Typography Overrides inside Dashboard */
        html.app-skin-dark .production-dashboard-wrapper .text-dark,
        body.app-skin-dark .production-dashboard-wrapper .text-dark,
        [data-bs-theme="dark"] .production-dashboard-wrapper .text-dark {
            color: #f1f5f9 !important;
        }

        html.app-skin-dark .production-dashboard-wrapper .text-muted,
        body.app-skin-dark .production-dashboard-wrapper .text-muted,
        [data-bs-theme="dark"] .production-dashboard-wrapper .text-muted {
            color: #94a3b8 !important;
        }

        html.app-skin-dark .production-dashboard-wrapper .text-secondary,
        body.app-skin-dark .production-dashboard-wrapper .text-secondary,
        [data-bs-theme="dark"] .production-dashboard-wrapper .text-secondary {
            color: #cbd5e1 !important;
        }

        html.app-skin-dark .production-dashboard-wrapper .border,
        html.app-skin-dark .production-dashboard-wrapper .border-top,
        html.app-skin-dark .production-dashboard-wrapper .border-bottom,
        html.app-skin-dark .production-dashboard-wrapper .border-start,
        html.app-skin-dark .production-dashboard-wrapper .border-end,
        body.app-skin-dark .production-dashboard-wrapper .border,
        body.app-skin-dark .production-dashboard-wrapper .border-top,
        body.app-skin-dark .production-dashboard-wrapper .border-bottom,
        body.app-skin-dark .production-dashboard-wrapper .border-start,
        body.app-skin-dark .production-dashboard-wrapper .border-end,
        [data-bs-theme="dark"] .production-dashboard-wrapper .border,
        [data-bs-theme="dark"] .production-dashboard-wrapper .border-top,
        [data-bs-theme="dark"] .production-dashboard-wrapper .border-bottom,
        [data-bs-theme="dark"] .production-dashboard-wrapper .border-start,
        [data-bs-theme="dark"] .production-dashboard-wrapper .border-end {
            border-color: #1e293b !important;
        }
    </style>
@endpush

@section('content')
    <div class="production-dashboard-wrapper">
        {{-- ── 1. Top Executive Welcome Card (HRMS Style) ────────────────────────── --}}
        <div class="card dash-card border-0 shadow-sm mb-3 rounded-3">
            <div class="card-body p-3 px-4">
                <div class="row align-items-center">
                    <div class="col-lg-7">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-initials-dash bg-soft-primary text-primary shadow-sm border border-primary border-opacity-10 fw-bolder fs-16 flex-shrink-0" style="width: 56px; height: 56px; border-radius: 50%;">
                                {{ strtoupper(substr(auth()->user()?->name ?? 'PM', 0, 2)) }}
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-3 flex-wrap mb-2">
                                    <h5 class="fw-bold mb-0 text-dark fs-18" style="line-height: 1.3;">
                                        {{ $greeting }}, {{ auth()->user()?->name ?? 'Plant Manager' }}! 👋
                                    </h5>
                                    <span class="badge bg-soft-primary text-primary px-2.5 py-1 fs-11 fw-bold rounded-pill border border-primary border-opacity-20 d-inline-flex align-items-center gap-1.5">
                                        <i class="feather-shield text-primary"></i> Role: {{ $roleDisplayName }}
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-3 flex-wrap fs-12 text-muted">
                                    <span class="fw-semibold text-secondary d-inline-flex align-items-center gap-1.5">
                                        <i class="feather-cpu text-muted"></i>Manufacturing &amp; MES Operations
                                    </span>
                                    <span class="text-muted opacity-50">&bull;</span>
                                    <span class="d-inline-flex align-items-center gap-1.5">
                                        <i class="feather-box text-muted"></i>Plant Execution
                                    </span>
                                    <span class="text-muted opacity-50">&bull;</span>
                                    <span class="text-dark d-inline-flex align-items-center gap-1.5">
                                        <i class="feather-calendar text-primary"></i>{{ date('l, d F Y') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
                        <div class="d-inline-flex align-items-center justify-content-end gap-2 flex-wrap">
                            {{-- Timeframe Filter Buttons --}}
                            <div class="btn-group shadow-sm me-1" role="group" aria-label="Dashboard Timeframe">
                                <a href="{{ route('production.dashboard', ['timeframe' => 'today']) }}"
                                   class="btn btn-sm {{ ($timeframe ?? 'today') === 'today' ? 'btn-primary' : 'btn-light border' }} fs-12">
                                    Today
                                </a>
                                <a href="{{ route('production.dashboard', ['timeframe' => 'week']) }}"
                                   class="btn btn-sm {{ ($timeframe ?? 'today') === 'week' ? 'btn-primary' : 'btn-light border' }} fs-12">
                                    This Week
                                </a>
                                <a href="{{ route('production.dashboard', ['timeframe' => 'month']) }}"
                                   class="btn btn-sm {{ ($timeframe ?? 'today') === 'month' ? 'btn-primary' : 'btn-light border' }} fs-12">
                                    This Month
                                </a>
                            </div>

                            <x-ui.button variant="primary" icon="feather-plus-circle" href="{{ route('production.orders.create') }}">
                                Create Order
                            </x-ui.button>
                            <x-ui.button variant="light" icon="feather-calendar" class="border" href="{{ route('production.schedules.index') }}">
                                Scheduling
                            </x-ui.button>
                            <x-ui.button variant="light" icon="feather-monitor" class="border" href="{{ route('production.mes.dashboard') }}">
                                Shop Floor
                            </x-ui.button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── 2. Row of 4 Hero KPI Cards (Standardized Primary Design) ───────── --}}
        <div class="row g-3 mb-3">
            {{-- KPI 1: Active Production Orders Pipeline --}}
            <div class="col-xl-3 col-md-6">
                <div class="card dash-card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-body p-3.5">
                        <div class="d-flex align-items-center justify-content-between mb-2.5">
                            <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-3">
                                <i class="feather-play-circle fs-5"></i>
                            </div>
                            <span class="badge bg-soft-primary text-primary fw-bold px-2 py-0.5 fs-11">Pipeline</span>
                        </div>
                        <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">Active Orders Pipeline</span>
                        <div class="d-flex align-items-baseline gap-2 mb-2">
                            <h3 class="fw-bolder text-dark mb-0 fs-24 font-monospace">{{ number_format($totalActiveOrders) }}</h3>
                            <span class="text-muted fs-12 fw-normal">/ {{ number_format($orderStatusCounts['total']) }} Total</span>
                        </div>
                        <div class="row g-0 pt-2.5 mt-2 border-top text-center">
                            <div class="col-4 border-end pe-1">
                                <span class="text-muted d-block fs-10 text-uppercase fw-semibold mb-0.5">Draft</span>
                                <strong class="text-dark fs-13 font-monospace">{{ $orderStatusCounts['draft'] }}</strong>
                            </div>
                            <div class="col-4 border-end px-1">
                                <span class="text-muted d-block fs-10 text-uppercase fw-semibold mb-0.5">Released</span>
                                <strong class="text-primary fs-13 font-monospace">{{ $orderStatusCounts['released'] }}</strong>
                            </div>
                            <div class="col-4 ps-1">
                                <span class="text-muted d-block fs-10 text-uppercase fw-semibold mb-0.5">In Progress</span>
                                <strong class="text-dark fs-13 font-monospace">{{ $orderStatusCounts['in_progress'] }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- KPI 2: Production Volume & Adherence --}}
            <div class="col-xl-3 col-md-6">
                <div class="card dash-card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-body p-3.5">
                        <div class="d-flex align-items-center justify-content-between mb-2.5">
                            <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-3">
                                <i class="feather-check-circle fs-5"></i>
                            </div>
                            <span class="badge bg-soft-primary text-primary fw-bold px-2 py-0.5 fs-11">
                                {{ $productionSummary['schedule_adherence'] ?? 100 }}% Adherence
                            </span>
                        </div>
                        <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">Volume &amp; Adherence</span>
                        <div class="d-flex align-items-baseline gap-2 mb-2">
                            <h3 class="fw-bolder text-dark mb-0 fs-24 font-monospace">{{ number_format($productionSummary['actual_quantity'] ?? 0) }}</h3>
                            <span class="text-muted fs-12 fw-normal">/ {{ number_format($productionSummary['planned_quantity'] ?? 0) }} Target</span>
                        </div>
                        <div class="row g-0 pt-2.5 mt-2 border-top text-center">
                            <div class="col-4 border-end pe-1">
                                <span class="text-muted d-block fs-10 text-uppercase fw-semibold mb-0.5">Planned</span>
                                <strong class="text-dark fs-13 font-monospace">{{ number_format($productionSummary['planned_quantity'] ?? 0) }}</strong>
                            </div>
                            <div class="col-4 border-end px-1">
                                <span class="text-muted d-block fs-10 text-uppercase fw-semibold mb-0.5">Produced</span>
                                <strong class="text-dark fs-13 font-monospace">{{ number_format($productionSummary['actual_quantity'] ?? 0) }}</strong>
                            </div>
                            <div class="col-4 ps-1">
                                <span class="text-muted d-block fs-10 text-uppercase fw-semibold mb-0.5">Adherence</span>
                                <strong class="text-primary fs-13 font-monospace">{{ $productionSummary['schedule_adherence'] ?? 100 }}%</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- KPI 3: Plant OEE Score --}}
            <div class="col-xl-3 col-md-6">
                <div class="card dash-card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-body p-3.5">
                        <div class="d-flex align-items-center justify-content-between mb-2.5">
                            <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-3">
                                <i class="feather-activity fs-5"></i>
                            </div>
                            <span class="badge bg-soft-primary text-primary fw-bold px-2 py-0.5 fs-11">
                                {{ $oeeKpi['status'] ?? 'On Target' }}
                            </span>
                        </div>
                        <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">Plant OEE Score</span>
                        <div class="d-flex align-items-baseline gap-2 mb-2">
                            <h3 class="fw-bolder text-dark mb-0 fs-24 font-monospace">{{ number_format((float) ($oeeKpi['current_value'] ?? 0), 1) }}%</h3>
                            <span class="text-muted fs-12 fw-normal ms-1">Target {{ $oeeKpi['target_value'] ?? 85 }}%</span>
                        </div>
                        <div class="row g-0 pt-2.5 mt-2 border-top text-center">
                            <div class="col-4 border-end pe-1">
                                <span class="text-muted d-block fs-10 text-uppercase fw-semibold mb-0.5">Target</span>
                                <strong class="text-dark fs-13 font-monospace">{{ $oeeKpi['target_value'] ?? 85 }}%</strong>
                            </div>
                            <div class="col-4 border-end px-1">
                                <span class="text-muted d-block fs-10 text-uppercase fw-semibold mb-0.5">Variance</span>
                                <strong class="text-dark fs-13 font-monospace">
                                    {{ ($oeeKpi['variance'] ?? 0) >= 0 ? '+' : '' }}{{ $oeeKpi['variance'] ?? 0 }}%
                                </strong>
                            </div>
                            <div class="col-4 ps-1">
                                <span class="text-muted d-block fs-10 text-uppercase fw-semibold mb-0.5">Yield</span>
                                <strong class="text-primary fs-13 font-monospace">{{ number_format((float) ($qualityKpis['fpy'] ?? 100), 1) }}%</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- KPI 4: Shop Floor WIP (Operational Tracking) --}}
            <div class="col-xl-3 col-md-6">
                <div class="card dash-card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-body p-3.5">
                        <div class="d-flex align-items-center justify-content-between mb-2.5">
                            <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-3">
                                <i class="feather-layers fs-5"></i>
                            </div>
                            <span class="badge bg-soft-primary text-primary fw-bold px-2 py-0.5 fs-11">
                                WIP Tracking
                            </span>
                        </div>
                        <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">Shop Floor WIP</span>
                        <div class="d-flex align-items-baseline gap-2 mb-2">
                            <h3 class="fw-bolder text-dark mb-0 fs-24 font-monospace">
                                {{ number_format($wipSummary['total_wip_jobs'] ?? 0) }}
                            </h3>
                            <span class="text-muted fs-12 fw-normal ms-1">Active Tracking Jobs</span>
                        </div>
                        <div class="row g-0 pt-2.5 mt-2 border-top text-center">
                            <div class="col-4 border-end pe-1">
                                <span class="text-muted d-block fs-10 text-uppercase fw-semibold mb-0.5">Available</span>
                                <strong class="text-dark fs-13 font-monospace">{{ number_format($wipSummary['total_available_qty'] ?? 0) }}</strong>
                            </div>
                            <div class="col-4 border-end px-1">
                                <span class="text-muted d-block fs-10 text-uppercase fw-semibold mb-0.5">Total Qty</span>
                                <strong class="text-dark fs-13 font-monospace">{{ number_format($wipSummary['total_wip_qty'] ?? 0) }}</strong>
                            </div>
                            <div class="col-4 ps-1">
                                <span class="text-muted d-block fs-10 text-uppercase fw-semibold mb-0.5">Scrapped</span>
                                <strong class="text-dark fs-13 font-monospace">{{ number_format($wipSummary['total_scrap_qty'] ?? 0) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── 3. Critical Action Center (Real-Time Red Flags & Exceptions) ─────── --}}
        <div class="card dash-card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="dash-card-icon-avatar bg-soft-primary text-primary">
                        <i class="feather-alert-triangle"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h6 class="card-title fw-bold text-dark mb-0 fs-14">Critical Action Center</h6>
                            @if($totalCriticalExceptions > 0)
                                <span class="badge bg-soft-primary text-primary rounded-pill px-2.5 py-0.5 fs-11 fw-bold border border-primary border-opacity-10">
                                    {{ $totalCriticalExceptions }} Active Item(s)
                                </span>
                            @else
                                <span class="badge bg-soft-primary text-primary rounded-pill px-2.5 py-0.5 fs-11 fw-bold border border-primary border-opacity-10">
                                    All Operations On Track
                                </span>
                            @endif
                        </div>
                        <span class="fs-11 text-muted">Immediate manufacturing and shop-floor exception resolution worklist.</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('production.planning-exceptions.index') }}" class="btn btn-sm btn-outline-primary fw-semibold fs-12 d-inline-flex align-items-center gap-1.5 px-3 py-1.5 rounded-pill">
                        <i class="feather-alert-octagon fs-13"></i>
                        <span>Exception Engine</span>
                        <i class="feather-arrow-right fs-12"></i>
                    </a>
                </div>
            </div>
            <div class="card-body p-3">
                <div class="row g-3">
                    {{-- Alert 1: Overdue Production Orders --}}
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <a href="#operationalTabs" onclick="activateTab('content-overdue-tab')" class="text-decoration-none">
                            <div class="dash-tile dash-tile-default action-tile-card {{ $overdueOrdersCount > 0 ? 'border-primary' : '' }} h-100">
                                <div class="d-flex align-items-center justify-content-between mb-1.5">
                                    <span class="fs-11 fw-bold text-uppercase text-muted">Overdue Orders</span>
                                    <i class="feather-clock {{ $overdueOrdersCount > 0 ? 'text-primary' : 'text-muted' }} fs-14"></i>
                                </div>
                                <h3 class="fw-bold my-1 font-monospace text-dark fs-22">
                                    {{ number_format($overdueOrdersCount) }}
                                </h3>
                                <small class="text-muted fs-11">Past planned completion</small>
                            </div>
                        </a>
                    </div>

                    {{-- Alert 2: Machine Breakdowns --}}
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <a href="{{ route('production.mes.dashboard') }}" class="text-decoration-none">
                            <div class="dash-tile dash-tile-default action-tile-card {{ $breakdownCount > 0 ? 'border-primary' : '' }} h-100">
                                <div class="d-flex align-items-center justify-content-between mb-1.5">
                                    <span class="fs-11 fw-bold text-uppercase text-muted">Machine Breakdown</span>
                                    <i class="feather-alert-octagon {{ $breakdownCount > 0 ? 'text-primary' : 'text-muted' }} fs-14"></i>
                                </div>
                                <h3 class="fw-bold my-1 font-monospace text-dark fs-22">
                                    {{ number_format($breakdownCount) }}
                                </h3>
                                <small class="text-muted fs-11">Halted equipment</small>
                            </div>
                        </a>
                    </div>

                    {{-- Alert 3: Material Blockers --}}
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <a href="{{ route('sales.material-requests.index') }}" class="text-decoration-none">
                            <div class="dash-tile dash-tile-default action-tile-card {{ $materialBlockersCount > 0 ? 'border-primary' : '' }} h-100">
                                <div class="d-flex align-items-center justify-content-between mb-1.5">
                                    <span class="fs-11 fw-bold text-uppercase text-muted">Material Blockers</span>
                                    <i class="feather-package {{ $materialBlockersCount > 0 ? 'text-primary' : 'text-muted' }} fs-14"></i>
                                </div>
                                <h3 class="fw-bold my-1 font-monospace text-dark fs-22">
                                    {{ number_format($materialBlockersCount) }}
                                </h3>
                                <small class="text-muted fs-11">Pending store release</small>
                            </div>
                        </a>
                    </div>

                    {{-- Alert 4: Quality Holds & Pending QC --}}
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <a href="{{ route('production.inspections.index') }}" class="text-decoration-none">
                            <div class="dash-tile dash-tile-default action-tile-card {{ ($pendingQcCount + $openNcrCount) > 0 ? 'border-primary' : '' }} h-100">
                                <div class="d-flex align-items-center justify-content-between mb-1.5">
                                    <span class="fs-11 fw-bold text-uppercase text-muted">Pending QC &amp; NCR</span>
                                    <i class="feather-shield {{ ($pendingQcCount + $openNcrCount) > 0 ? 'text-primary' : 'text-muted' }} fs-14"></i>
                                </div>
                                <h3 class="fw-bold my-1 font-monospace text-dark fs-22">
                                    {{ number_format($pendingQcCount) }} <span class="fs-12 fw-normal text-muted">/ {{ $openNcrCount }} NCR</span>
                                </h3>
                                <small class="text-muted fs-11">Awaiting inspection audit</small>
                            </div>
                        </a>
                    </div>

                    {{-- Alert 5: Subcontracting Delays --}}
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <a href="{{ route('production.subcontract.analytics') }}" class="text-decoration-none">
                            <div class="dash-tile dash-tile-default action-tile-card {{ $vendorDelayedCount > 0 ? 'border-primary' : '' }} h-100">
                                <div class="d-flex align-items-center justify-content-between mb-1.5">
                                    <span class="fs-11 fw-bold text-uppercase text-muted">Vendor Delayed</span>
                                    <i class="feather-truck {{ $vendorDelayedCount > 0 ? 'text-primary' : 'text-muted' }} fs-14"></i>
                                </div>
                                <h3 class="fw-bold my-1 font-monospace text-dark fs-22">
                                    {{ number_format($vendorDelayedCount) }}
                                </h3>
                                <small class="text-muted fs-11">Outside ops overdue</small>
                            </div>
                        </a>
                    </div>

                    {{-- Alert 6: Pending Sales Demands --}}
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <a href="#operationalTabs" onclick="activateTab('content-demand-tab')" class="text-decoration-none">
                            <div class="dash-tile dash-tile-default action-tile-card {{ $pendingSalesOrderCount > 0 ? 'border-primary' : '' }} h-100">
                                <div class="d-flex align-items-center justify-content-between mb-1.5">
                                    <span class="fs-11 fw-bold text-uppercase text-muted">Pending Demand</span>
                                    <i class="feather-shopping-cart {{ $pendingSalesOrderCount > 0 ? 'text-primary' : 'text-muted' }} fs-14"></i>
                                </div>
                                <h3 class="fw-bold my-1 font-monospace text-dark fs-22">
                                    {{ number_format($pendingSalesOrderCount) }}
                                </h3>
                                <small class="text-muted fs-11">Demands needing orders</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── 4. Live Manufacturing Pulse (Machine State, Andon & MES) ────────── --}}
        <div class="card dash-card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="dash-card-icon-avatar bg-soft-primary text-primary">
                        <i class="feather-cpu"></i>
                    </div>
                    <div>
                        <h6 class="card-title fw-bold text-dark mb-0 fs-14">Live Manufacturing Pulse &amp; Andon Status</h6>
                        <span class="fs-11 text-muted">Real-time machine operating states, stoppage alarms &amp; shop floor execution.</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <x-ui.button variant="light" icon="feather-grid" class="border" href="{{ route('production.mes.machines.index') }}">
                        Machines Directory
                    </x-ui.button>
                    <x-ui.button variant="primary" icon="feather-alert-triangle" href="{{ route('production.intelligence.andon') }}">
                        Live Andon Board
                    </x-ui.button>
                </div>
            </div>
            <div class="card-body p-3">
                {{-- Machine States Grid --}}
                <div class="row g-2 mb-3">
                    <div class="col-6 col-sm-4 col-md-2">
                        <a href="{{ route('production.mes.machines.index', ['state' => 'Running']) }}" class="text-decoration-none">
                            <div class="dash-tile dash-tile-default machine-state-box border border-primary hover-shadow">
                                <div class="d-flex align-items-center justify-content-center gap-1.5 mb-1">
                                    <span class="spinner-grow spinner-grow-sm text-primary" style="width: 8px; height: 8px;"></span>
                                    <span class="fs-11 fw-bold text-primary text-uppercase">Running</span>
                                </div>
                                <h3 class="fw-bold text-dark mb-0 font-monospace fs-18">{{ number_format($machineStateCounts['running'] ?? 0) }}</h3>
                                <small class="text-muted fs-11">Active Line</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-sm-4 col-md-2">
                        <a href="{{ route('production.mes.machines.index', ['state' => 'Idle']) }}" class="text-decoration-none">
                            <div class="dash-tile dash-tile-default machine-state-box border hover-shadow">
                                <span class="fs-11 fw-bold text-muted text-uppercase d-block mb-1">Idle</span>
                                <h3 class="fw-bold text-dark mb-0 font-monospace fs-18">{{ number_format($machineStateCounts['idle'] ?? 0) }}</h3>
                                <small class="text-muted fs-11">Awaiting Jobs</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-sm-4 col-md-2">
                        <a href="{{ route('production.mes.machines.index', ['state' => 'Setup']) }}" class="text-decoration-none">
                            <div class="dash-tile dash-tile-default machine-state-box border hover-shadow">
                                <span class="fs-11 fw-bold text-muted text-uppercase d-block mb-1">Setup</span>
                                <h3 class="fw-bold text-dark mb-0 font-monospace fs-18">{{ number_format($machineStateCounts['setup'] ?? 0) }}</h3>
                                <small class="text-muted fs-11">Tooling / Change</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-sm-4 col-md-2">
                        <a href="{{ route('production.mes.machines.index', ['state' => 'Breakdown']) }}" class="text-decoration-none">
                            <div class="dash-tile dash-tile-default machine-state-box border {{ ($machineStateCounts['breakdown'] ?? 0) > 0 ? 'border-primary' : '' }} hover-shadow">
                                <div class="d-flex align-items-center justify-content-center gap-1 mb-1">
                                    <span class="fs-11 fw-bold text-muted text-uppercase">Breakdown</span>
                                </div>
                                <h3 class="fw-bold text-dark mb-0 font-monospace fs-18">{{ number_format($machineStateCounts['breakdown'] ?? 0) }}</h3>
                                <small class="text-muted fs-11">Halted</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-sm-4 col-md-2">
                        <a href="{{ route('production.mes.machines.index', ['state' => 'Maintenance']) }}" class="text-decoration-none">
                            <div class="dash-tile dash-tile-default machine-state-box border {{ ($machineStateCounts['maintenance'] ?? 0) > 0 ? 'border-primary' : '' }} hover-shadow">
                                <span class="fs-11 fw-bold text-muted text-uppercase d-block mb-1">Maintenance</span>
                                <h3 class="fw-bold text-dark mb-0 font-monospace fs-18">{{ number_format($machineStateCounts['maintenance'] ?? 0) }}</h3>
                                <small class="text-muted fs-11">PM Servicing</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-sm-4 col-md-2">
                        <a href="{{ route('production.mes.machines.index', ['state' => 'Offline']) }}" class="text-decoration-none">
                            <div class="dash-tile dash-tile-default machine-state-box border hover-shadow">
                                <span class="fs-11 fw-bold text-muted text-uppercase d-block mb-1">Offline</span>
                                <h3 class="fw-bold text-dark mb-0 font-monospace fs-18">{{ number_format($machineStateCounts['offline'] ?? 0) }}</h3>
                                <small class="text-muted fs-11">Powered Down</small>
                            </div>
                        </a>
                    </div>
                </div>

                {{-- Andon Exceptions & Live Alerts --}}
                @if(!empty($attentionMachines) && $attentionMachines->isNotEmpty())
                    <div class="dash-panel-subtle p-3 rounded-3 border mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-1.5 fs-13">
                                <i class="feather-alert-triangle text-primary"></i>
                                Equipment Requiring Immediate Attention ({{ $attentionMachines->count() }})
                            </h6>
                            <a href="{{ route('production.intelligence.andon') }}" class="btn btn-sm btn-outline-primary py-0.5 px-2 fs-11 rounded-pill">
                                Open Full Andon Board
                            </a>
                        </div>
                        <div class="row g-2">
                            @foreach($attentionMachines as $m)
                                <div class="col-md-4 col-sm-6">
                                    <div class="p-2.5 dash-card-inner rounded-3 border h-100 shadow-2xs">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <a href="{{ route('production.mes.machines.show', $m['machine_id']) }}" class="fw-bold text-dark font-monospace fs-12 text-decoration-none">
                                                {{ $m['code'] }} - {{ $m['name'] }}
                                            </a>
                                            <span class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill">
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

                {{-- Live MES / Shop-Floor Pulse Strip --}}
                <div class="dash-panel-subtle p-3 rounded-3 border d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="dash-card-icon-avatar bg-soft-primary text-primary rounded-3">
                            <i class="feather-activity"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-0 fs-13">Shop Floor MES Execution Pulse</h6>
                            <div class="d-flex flex-wrap gap-2 mt-1 fs-12">
                                <span><strong class="text-dark font-monospace">{{ number_format($mesRunningCount ?? 0) }}</strong> Running Ops</span>
                                <span class="text-muted">&bull;</span>
                                <span><strong class="text-primary font-monospace">{{ number_format($mesReadyCount ?? 0) }}</strong> Ready in Queue</span>
                                <span class="text-muted">&bull;</span>
                                <span><strong class="text-dark font-monospace">{{ number_format($mesPausedCount ?? 0) }}</strong> Paused</span>
                                <span class="text-muted">&bull;</span>
                                <span><strong class="text-dark font-monospace">{{ number_format($mesCompletedTodayCount ?? 0) }}</strong> Completed Today</span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <x-ui.button variant="primary" icon="feather-monitor" href="{{ route('production.mes.dashboard') }}">
                            Open Shop Floor / MES
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── 5. Work-Center Capacity, Load & Bottleneck Pulse ───────────────── --}}
        <div class="card dash-card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="dash-card-icon-avatar bg-soft-primary text-primary">
                        <i class="feather-bar-chart-2"></i>
                    </div>
                    <div>
                        <h6 class="card-title fw-bold text-dark mb-0 fs-14">Work-Center Capacity &amp; Bottleneck Pulse</h6>
                        <span class="fs-11 text-muted">Shift capacity utilization, planned machine load &amp; constrained station spotlight.</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <x-ui.button variant="light" icon="feather-layers" class="border" href="{{ route('production.mes.work-centers.index') }}">
                        All Work Centers
                    </x-ui.button>
                </div>
            </div>
            <div class="card-body p-3">
                {{-- Bottleneck Spotlight (If any center is >= 85% or >100%) --}}
                @if(!empty($bottleneckWorkCenters) && $bottleneckWorkCenters->isNotEmpty())
                    <div class="dash-panel-subtle p-3 rounded-3 border mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-1.5 fs-13">
                                <i class="feather-alert-octagon text-primary"></i>
                                Bottleneck Spotlight: {{ $bottleneckWorkCenters->count() }} Work Center(s) Near or Above Target Capacity
                            </h6>
                            <small class="text-muted fs-11">Thresholds: Warning &ge;85%, Critical &gt;100%</small>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($bottleneckWorkCenters as $bwc)
                                <a href="{{ route('production.mes.work-centers.show', $bwc['id']) }}" class="badge bg-soft-primary text-primary p-2 text-decoration-none fs-11 d-inline-flex align-items-center gap-1.5 rounded-pill font-monospace">
                                    <i class="feather-alert-triangle text-primary"></i>
                                    {{ $bwc['name'] }} ({{ $bwc['code'] }}): {{ $bwc['utilization'] }}% Load ({{ $bwc['status'] }})
                                </a>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="dash-panel-subtle py-2.5 px-3 rounded-3 border mb-3 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2 fs-12 text-muted">
                            <i class="feather-check-circle fs-15 text-primary"></i>
                            <span class="text-dark"><strong>Zero Bottlenecks Active!</strong> All work centers are operating within standard capacity limits (&lt;85% utilization).</span>
                        </div>
                        <span class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill">Capacity Balanced</span>
                    </div>
                @endif

                {{-- Work Center Load Cards Grid --}}
                @if(!empty($workCenterLoads))
                    <div class="row g-3">
                        @foreach(array_slice($workCenterLoads, 0, 6) as $wcl)
                            <div class="col-xl-4 col-md-6">
                                <div class="dash-card-inner p-3 border rounded-3 h-100 hover-shadow">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div>
                                            <a href="{{ route('production.mes.work-centers.show', $wcl['id']) }}" class="fw-bold text-dark text-decoration-none fs-13">
                                                {{ $wcl['name'] }}
                                            </a>
                                            <div class="fs-11 text-muted font-monospace">{{ $wcl['code'] }} &bull; {{ $wcl['machine_count'] }} Machine(s)</div>
                                        </div>
                                        <span class="badge bg-soft-primary text-primary fs-11 font-monospace rounded-pill">
                                            {{ $wcl['status'] }}
                                        </span>
                                    </div>
                                    <div class="d-flex align-items-baseline justify-content-between mb-1">
                                        <span class="fs-12 text-muted">Planned / Available</span>
                                        <span class="fs-12 fw-bold font-monospace text-dark">
                                             {{ $wcl['scheduled_hours'] }}h / {{ $wcl['capacity_hours'] }}h
                                        </span>
                                    </div>
                                    <div class="progress mb-2" style="height: 6px;">
                                        <div class="progress-bar bg-primary"
                                             role="progressbar"
                                             style="width: {{ min(100, $wcl['utilization']) }}%"></div>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between fs-11 text-muted">
                                        <span>Utilization: <strong class="text-dark font-monospace">{{ $wcl['utilization'] }}%</strong></span>
                                        <span><i class="feather-play text-primary me-1"></i>{{ $wcl['running_ops'] }} Running | {{ $wcl['waiting_ops'] }} Ready</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="feather-inbox fs-24 d-block mb-1 opacity-50"></i>
                        <span>No active work centers configured for this tenant.</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- ── 6. Quality Intelligence & Defect Analytics (Phase 3A) ─────────── --}}
        <div class="card dash-card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="dash-card-icon-avatar bg-soft-primary text-primary">
                        <i class="feather-check-circle"></i>
                    </div>
                    <div>
                        <h6 class="card-title fw-bold text-dark mb-0 fs-14">Quality Intelligence &amp; Defect Analytics</h6>
                        <span class="fs-11 text-muted">Final stage yield, inspection gates, active non-conformances &amp; corrective actions.</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <x-ui.button variant="light" icon="feather-clipboard" class="border" href="{{ route('production.inspections.index') }}">
                        All Inspections
                    </x-ui.button>
                    <x-ui.button variant="primary" icon="feather-award" href="{{ route('production.quality.dashboard') }}">
                        Quality Dashboard
                    </x-ui.button>
                </div>
            </div>
            <div class="card-body p-3">
                <div class="row g-3 mb-3">
                    {{-- First Pass Yield (FPY) --}}
                    <div class="col-xl-3 col-sm-6">
                        <div class="dash-tile dash-tile-default quality-tile-box border h-100">
                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                <span class="fs-11 fw-bold text-muted text-uppercase">First Pass Yield</span>
                                <span class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill">FPY</span>
                            </div>
                            <h2 class="fw-bold text-dark my-1 font-monospace fs-24">{{ number_format($qualityKpis['fpy'] ?? 100, 1) }}%</h2>
                            <small class="text-muted fs-11">Final Stage Acceptance Rate</small>
                        </div>
                    </div>

                    {{-- Inspections Gate Track --}}
                    <div class="col-xl-3 col-sm-6">
                        <div class="dash-tile dash-tile-default quality-tile-box border h-100">
                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                <span class="fs-11 fw-bold text-muted text-uppercase">Inspections Gate</span>
                                <span class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill">{{ number_format($qualityKpis['totalInspections'] ?? 0) }} Total</span>
                            </div>
                            <div class="d-flex align-items-baseline gap-3 my-1">
                                <div>
                                    <span class="fs-18 fw-bold text-primary font-monospace">{{ number_format($qualityKpis['passedInspections'] ?? 0) }}</span>
                                    <small class="text-muted d-block fs-10">Passed</small>
                                </div>
                                <div class="border-start ps-3">
                                    <span class="fs-18 fw-bold text-dark font-monospace">{{ number_format($qualityKpis['failedInspections'] ?? 0) }}</span>
                                    <small class="text-muted d-block fs-10">Failed</small>
                                </div>
                                <div class="border-start ps-3">
                                    <span class="fs-18 fw-bold text-dark font-monospace">{{ number_format($qualityKpis['pendingInspections'] ?? 0) }}</span>
                                    <small class="text-muted d-block fs-10">Pending</small>
                                </div>
                            </div>
                            <small class="text-muted fs-11">Receiving, in-line &amp; final audits</small>
                        </div>
                    </div>

                    {{-- Active NCRs & CAPAs --}}
                    <div class="col-xl-3 col-sm-6">
                        <div class="dash-tile dash-tile-default quality-tile-box border {{ ($qualityKpis['ncrOpen'] ?? 0) > 0 ? 'border-primary' : '' }} h-100">
                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                <span class="fs-11 fw-bold text-muted text-uppercase">Non-Conformances</span>
                                @if(($qualityKpis['ncrOpen'] ?? 0) > 0)
                                    <span class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill">Action Required</span>
                                @else
                                    <span class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill">Zero Open</span>
                                @endif
                            </div>
                            <div class="d-flex align-items-baseline gap-3 my-1">
                                <div>
                                    <span class="fs-18 fw-bold text-dark font-monospace">{{ number_format($qualityKpis['ncrOpen'] ?? 0) }}</span>
                                    <small class="text-muted d-block fs-10">Open NCRs ({{ $qualityKpis['ncrClosed'] ?? 0 }} Closed)</small>
                                </div>
                                <div class="border-start ps-3">
                                    <span class="fs-18 fw-bold text-primary font-monospace">{{ number_format($qualityKpis['capaOpen'] ?? 0) }}</span>
                                    <small class="text-muted d-block fs-10">Active CAPAs ({{ $qualityKpis['capaClosed'] ?? 0 }} Verified)</small>
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
                                <span class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill">Rework &amp; Scrap</span>
                            </div>
                            <div class="d-flex align-items-baseline gap-3 my-1">
                                <div>
                                    <span class="fs-18 fw-bold text-dark font-monospace">{{ number_format($qualityKpis['reworkCount'] ?? 0) }}</span>
                                    <small class="text-muted d-block fs-10">Rework Orders</small>
                                </div>
                                <div class="border-start ps-3">
                                    <span class="fs-18 fw-bold text-dark font-monospace">{{ number_format($qualityKpis['scrapCount'] ?? 0) }}</span>
                                    <small class="text-muted d-block fs-10">Scrap Disposals</small>
                                </div>
                            </div>
                            <small class="text-muted fs-11">Material resolution dispositions</small>
                        </div>
                    </div>
                </div>

                {{-- Top Defect Reasons Bar --}}
                <div class="dash-panel-subtle p-3 rounded-3 border d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="fs-11 fw-bold text-muted text-uppercase d-inline-flex align-items-center gap-1">
                            <i class="feather-alert-octagon text-primary"></i> Top Defect Categories:
                        </span>
                        @if(!empty($topDefectCategories) && $topDefectCategories->isNotEmpty())
                            <div class="d-flex flex-wrap gap-1.5">
                                @foreach($topDefectCategories as $cat)
                                    <span class="dash-card-inner text-dark border shadow-xs fs-11 rounded-pill px-2.5 py-1">
                                        {{ $cat->category }}: <strong class="text-primary font-monospace">{{ $cat->count }}</strong>
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <span class="badge bg-soft-primary text-primary fs-11 font-monospace rounded-pill px-2.5 py-1">Zero Quality Non-Conformances active</span>
                        @endif
                    </div>
                    <div class="fs-11 text-muted">
                        Quality clearance guard active on all warehouse receipts
                    </div>
                </div>
            </div>
        </div>

        {{-- ── 7. Six Big Losses & Cycle Time Intelligence (Phase 3B) ─────────── --}}
        <div class="card dash-card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="dash-card-icon-avatar bg-soft-primary text-primary">
                        <i class="feather-pie-chart"></i>
                    </div>
                    <div>
                        <h6 class="card-title fw-bold text-dark mb-0 fs-14">Six Big Losses &amp; Cycle Time Intelligence</h6>
                        <span class="fs-11 text-muted">TPM downtime classification, cycle duration variances &amp; resource utilization.</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-soft-primary text-primary fs-11 font-monospace rounded-pill px-2.5 py-1">{{ ucfirst($timeframe) }} Window</span>
                    <x-ui.button variant="light" icon="feather-activity" class="border" href="{{ route('production.intelligence.dashboard') }}">
                        OEE Analytics
                    </x-ui.button>
                </div>
            </div>
            <div class="card-body p-3">
                {{-- Six Big Losses Grid --}}
                <div class="mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-2.5">
                        <span class="fs-11 fw-bold text-uppercase text-muted">TPM Six Big Losses Classification</span>
                        <small class="text-muted fs-11">Overall Downtime Rate: <strong class="text-dark font-monospace">{{ number_format($downtimeRate, 1) }}%</strong></small>
                    </div>
                    <div class="row g-2">
                        {{-- 1. Equipment Failure (Availability) --}}
                        <div class="col-md-2 col-sm-4 col-6">
                            <div class="dash-tile dash-tile-default loss-tile-box border hover-shadow">
                                <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">Equipment Failure</span>
                                <h4 class="fw-bold text-dark my-1 font-monospace fs-18">{{ number_format($sixBigLosses['equipment_failure_minutes'] ?? 0, 1) }}<small class="fs-11 fw-normal text-muted">m</small></h4>
                                <small class="text-muted fs-11">Breakdowns</small>
                            </div>
                        </div>
                        {{-- 2. Setup & Adjustment (Availability) --}}
                        <div class="col-md-2 col-sm-4 col-6">
                            <div class="dash-tile dash-tile-default loss-tile-box border hover-shadow">
                                <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">Setup &amp; Adjust</span>
                                <h4 class="fw-bold text-dark my-1 font-monospace fs-18">{{ number_format($sixBigLosses['setup_adjustment_minutes'] ?? 0, 1) }}<small class="fs-11 fw-normal text-muted">m</small></h4>
                                <small class="text-muted fs-11">Tooling/Change</small>
                            </div>
                        </div>
                        {{-- 3. Minor Stops (Performance) --}}
                        <div class="col-md-2 col-sm-4 col-6">
                            <div class="dash-tile dash-tile-default loss-tile-box border hover-shadow">
                                <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">Minor Stops</span>
                                <h4 class="fw-bold text-dark my-1 font-monospace fs-18">{{ number_format($sixBigLosses['minor_stops_minutes'] ?? 0, 1) }}<small class="fs-11 fw-normal text-muted">m</small></h4>
                                <small class="text-muted fs-11">Idling &lt; 5m</small>
                            </div>
                        </div>
                        {{-- 4. Reduced Speed (Performance) --}}
                        <div class="col-md-2 col-sm-4 col-6">
                            <div class="dash-tile dash-tile-default loss-tile-box border hover-shadow">
                                <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">Reduced Speed</span>
                                <h4 class="fw-bold text-dark my-1 font-monospace fs-18">{{ number_format($sixBigLosses['reduced_speed_minutes'] ?? 0, 1) }}<small class="fs-11 fw-normal text-muted">m</small></h4>
                                <small class="text-muted fs-11">Slow Pace</small>
                            </div>
                        </div>
                        {{-- 5. Startup Rejects (Quality) --}}
                        <div class="col-md-2 col-sm-4 col-6">
                            <div class="dash-tile dash-tile-default loss-tile-box border hover-shadow">
                                <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">Startup Rejects</span>
                                <h4 class="fw-bold text-dark my-1 font-monospace fs-18">{{ number_format($sixBigLosses['startup_rejects_count'] ?? 0) }}</h4>
                                <small class="text-muted fs-11">First-Run</small>
                            </div>
                        </div>
                        {{-- 6. Production Rejects (Quality) --}}
                        <div class="col-md-2 col-sm-4 col-6">
                            <div class="dash-tile dash-tile-default loss-tile-box border hover-shadow">
                                <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">Production Scrap</span>
                                <h4 class="fw-bold text-dark my-1 font-monospace fs-18">{{ number_format($sixBigLosses['production_rejects_count'] ?? 0) }}</h4>
                                <small class="text-muted fs-11">In-Process</small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Cycle Times & Asset Utilizations Strip --}}
                <div class="row g-3 pt-3.5 border-top">
                    <div class="col-md-6">
                        <div class="p-3 rounded-3 dash-panel-subtle border h-100 d-flex flex-column justify-content-between" style="min-height: 135px;">
                            <span class="fs-11 fw-bold text-uppercase text-muted d-block mb-3">
                                <i class="feather-clock me-1 text-primary"></i>Cycle Times &amp; Waiting Averages
                            </span>
                            <div class="row g-2 text-center">
                                <div class="col-3 py-2">
                                    <small class="text-muted fs-11 d-block mb-1">Setup</small>
                                    <strong class="font-monospace text-dark fs-14">{{ number_format($cycleTimes['avg_setup_time'] ?? 0, 1) }}m</strong>
                                </div>
                                <div class="col-3 border-start py-2">
                                    <small class="text-muted fs-11 d-block mb-1">Processing</small>
                                    <strong class="font-monospace text-dark fs-14">{{ number_format($cycleTimes['avg_processing_time'] ?? 0, 1) }}m</strong>
                                </div>
                                <div class="col-3 border-start py-2">
                                    <small class="text-muted fs-11 d-block mb-1">Total Cycle</small>
                                    <strong class="font-monospace text-primary fs-14">{{ number_format($cycleTimes['avg_cycle_time'] ?? 0, 1) }}m</strong>
                                </div>
                                <div class="col-3 border-start py-2">
                                    <small class="text-muted fs-11 d-block mb-1">Waiting</small>
                                    <strong class="font-monospace text-dark fs-14">{{ number_format($cycleTimes['avg_waiting_time'] ?? 0, 1) }}m</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 rounded-3 dash-panel-subtle border h-100 d-flex flex-column justify-content-between" style="min-height: 135px;">
                            <span class="fs-11 fw-bold text-uppercase text-muted d-block mb-3">
                                <i class="feather-cpu me-1 text-primary"></i>Resource Utilizations
                            </span>
                            <div class="row g-2 text-center">
                                <div class="col-4 py-2">
                                    <small class="text-muted fs-11 d-block mb-1">Machines</small>
                                    <strong class="font-monospace text-dark fs-14">{{ number_format($assetUtilizations['machine_utilization'] ?? 0, 1) }}%</strong>
                                </div>
                                <div class="col-4 border-start py-2">
                                    <small class="text-muted fs-11 d-block mb-1">Operators</small>
                                    <strong class="font-monospace text-dark fs-14">{{ number_format($assetUtilizations['operator_utilization'] ?? 0, 1) }}%</strong>
                                </div>
                                <div class="col-4 border-start py-2">
                                    <small class="text-muted fs-11 d-block mb-1">Work Centers</small>
                                    <strong class="font-monospace text-dark fs-14">{{ number_format($assetUtilizations['work_center_utilization'] ?? 0, 1) }}%</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── 8. Plant Maintenance & Subcontracting SLA Integration (Phase 3C) ── --}}
        <div class="card dash-card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="dash-card-icon-avatar bg-soft-primary text-primary">
                        <i class="feather-tool"></i>
                    </div>
                    <div>
                        <h6 class="card-title fw-bold text-dark mb-0 fs-14">Plant Maintenance &amp; Subcontracting SLA</h6>
                        <span class="fs-11 text-muted">PM schedules, breakdown work orders, vendor delivery compliance &amp; external WIP.</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <x-ui.button variant="light" icon="feather-file-text" class="border" href="{{ route('production.maintenance.work-orders.index') }}">
                        Work Orders
                    </x-ui.button>
                    <x-ui.button variant="light" icon="feather-tool" class="border" href="{{ route('production.maintenance.dashboard') }}">
                        Maintenance Hub
                    </x-ui.button>
                    <x-ui.button variant="primary" icon="feather-truck" href="{{ route('production.subcontract.analytics') }}">
                        Vendor Analytics
                    </x-ui.button>
                </div>
            </div>
            <div class="card-body p-3">
                {{-- Plant Maintenance Top Strip --}}
                <div class="row g-3 mb-3">
                    <div class="col-md-3 col-sm-6">
                        <a href="{{ route('production.maintenance.schedules.index') }}" class="text-decoration-none">
                            <div class="dash-tile dash-tile-default maintenance-tile-box border {{ $overduePmCount > 0 ? 'border-primary' : '' }} hover-shadow">
                                <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">Overdue PM</span>
                                <h3 class="fw-bold text-dark my-1 font-monospace fs-22">{{ number_format($overduePmCount) }}</h3>
                                <small class="text-muted fs-11">Preventive Due</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="{{ route('production.maintenance.schedules.index') }}" class="text-decoration-none">
                            <div class="dash-tile dash-tile-default maintenance-tile-box border hover-shadow">
                                <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">PM Due (7 Days)</span>
                                <h3 class="fw-bold text-dark my-1 font-monospace fs-22">{{ number_format($duePmCount) }}</h3>
                                <small class="text-muted fs-11">Upcoming Service</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="{{ route('production.maintenance.work-orders.index', ['type' => 'breakdown']) }}" class="text-decoration-none">
                            <div class="dash-tile dash-tile-default maintenance-tile-box border {{ $openBreakdownWosCount > 0 ? 'border-primary' : '' }} hover-shadow">
                                <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">Breakdown WOs</span>
                                <h3 class="fw-bold text-dark my-1 font-monospace fs-22">{{ number_format($openBreakdownWosCount) }}</h3>
                                <small class="text-muted fs-11">Stoppage Orders</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="{{ route('production.mes.machines.index', ['status' => 'under_maintenance']) }}" class="text-decoration-none">
                            <div class="dash-tile dash-tile-default maintenance-tile-box border hover-shadow">
                                <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">Under Maintenance</span>
                                <h3 class="fw-bold text-dark my-1 font-monospace fs-22">{{ number_format($machinesUnderMaintenanceCount) }}</h3>
                                <small class="text-muted fs-11">Out of Service</small>
                            </div>
                        </a>
                    </div>
                </div>

                {{-- Subcontracting SLA & External Operations Strip --}}
                @if(!empty($subcontractMetrics))
                    <div class="pt-3 border-top">
                        <div class="d-flex align-items-center justify-content-between mb-2.5 flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="fs-11 fw-bold text-uppercase text-muted d-inline-flex align-items-center gap-1">
                                    <i class="feather-truck text-primary"></i> Vendor SLA &amp; External Operations:
                                </span>
                                <span class="badge bg-soft-primary text-primary fs-11 font-monospace rounded-pill px-2.5 py-1">
                                    OTD: {{ number_format($subcontractDelivery['on_time_delivery_pct'] ?? 100, 1) }}%
                                </span>
                                @if(($subcontractDelivery['avg_late_delay_days'] ?? 0) > 0)
                                    <span class="badge bg-soft-primary text-primary fs-11 font-monospace rounded-pill px-2.5 py-1">
                                        Avg Delay: +{{ number_format($subcontractDelivery['avg_late_delay_days'], 1) }}d
                                    </span>
                                @endif
                            </div>
                            <span class="fs-11 text-muted">Hybrid &amp; Subcontracting Job Work Flow</span>
                        </div>
                        <div class="row g-2 text-center">
                            <div class="col">
                                <a href="{{ route('production.orders.index', ['filter' => 'subcontract_awaiting_pr']) }}" class="text-decoration-none">
                                    <div class="dash-tile dash-tile-default subcontract-pipeline-box border hover-shadow">
                                        <span class="fs-10 text-muted text-uppercase fw-bold d-block mb-0.5">Awaiting PR</span>
                                        <h5 class="text-dark fw-bold mb-0 my-1 font-monospace fs-16">{{ number_format($subcontractMetrics['awaiting_subcontract_pr'] ?? 0) }}</h5>
                                    </div>
                                </a>
                            </div>
                            <div class="col">
                                <a href="{{ route('purchase.orders.index', ['type' => 'subcontract', 'status' => 'draft']) }}" class="text-decoration-none">
                                    <div class="dash-tile dash-tile-default subcontract-pipeline-box border hover-shadow">
                                        <span class="fs-10 text-muted text-uppercase fw-bold d-block mb-0.5">PO Awaiting</span>
                                        <h5 class="text-dark fw-bold mb-0 my-1 font-monospace fs-16">{{ number_format($subcontractMetrics['po_awaiting_approval'] ?? 0) }}</h5>
                                    </div>
                                </a>
                            </div>
                            <div class="col">
                                <a href="{{ route('production.orders.index', ['filter' => 'subcontract_ready_dispatch']) }}" class="text-decoration-none">
                                    <div class="dash-tile dash-tile-default subcontract-pipeline-box border hover-shadow">
                                        <span class="fs-10 text-muted text-uppercase fw-bold d-block mb-0.5">Ready Dispatch</span>
                                        <h5 class="text-dark fw-bold mb-0 my-1 font-monospace fs-16">{{ number_format($subcontractMetrics['ready_for_dispatch'] ?? 0) }}</h5>
                                    </div>
                                </a>
                            </div>
                            <div class="col">
                                <a href="{{ route('production.orders.index', ['filter' => 'subcontract_at_vendor']) }}" class="text-decoration-none">
                                    <div class="dash-tile dash-tile-default subcontract-pipeline-box border hover-shadow">
                                        <span class="fs-10 text-muted text-uppercase fw-bold d-block mb-0.5">At Vendor</span>
                                        <h5 class="text-dark fw-bold mb-0 my-1 font-monospace fs-16">{{ number_format($subcontractMetrics['at_vendor'] ?? 0) }}</h5>
                                    </div>
                                </a>
                            </div>
                            <div class="col">
                                <a href="{{ route('production.orders.index', ['filter' => 'subcontract_delayed']) }}" class="text-decoration-none">
                                    <div class="dash-tile dash-tile-default subcontract-pipeline-box border hover-shadow">
                                        <span class="fs-10 text-muted text-uppercase fw-bold d-block mb-0.5">Delayed</span>
                                        <h5 class="text-dark fw-bold mb-0 my-1 font-monospace fs-16">{{ number_format($subcontractMetrics['vendor_delayed'] ?? 0) }}</h5>
                                    </div>
                                </a>
                            </div>
                            <div class="col">
                                <a href="{{ route('production.inspections.index', ['type' => 'subcontract']) }}" class="text-decoration-none">
                                    <div class="dash-tile dash-tile-default subcontract-pipeline-box border hover-shadow">
                                        <span class="fs-10 text-muted text-uppercase fw-bold d-block mb-0.5">QC Pending</span>
                                        <h5 class="text-dark fw-bold mb-0 my-1 font-monospace fs-16">{{ number_format($subcontractMetrics['subcontract_qc_pending'] ?? 0) }}</h5>
                                    </div>
                                </a>
                            </div>
                            <div class="col">
                                <a href="{{ route('production.rework.index') }}" class="text-decoration-none">
                                    <div class="dash-tile dash-tile-default subcontract-pipeline-box border hover-shadow">
                                        <span class="fs-10 text-muted text-uppercase fw-bold d-block mb-0.5">Vendor Rework</span>
                                        <h5 class="text-dark fw-bold mb-0 my-1 font-monospace fs-16">{{ number_format($subcontractMetrics['vendor_rework'] ?? 0) }}</h5>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ── 9. Execution Variance, Order Risk & Planning Pulse (Phase 4) ──────── --}}
        <div class="card dash-card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="dash-card-icon-avatar bg-soft-primary text-primary">
                        <i class="feather-trending-up"></i>
                    </div>
                    <div>
                        <h6 class="card-title fw-bold text-dark mb-0 fs-14">Execution Variance, Order Risk &amp; Planning Pulse</h6>
                        <span class="fs-11 text-muted">Operational variance, near-term order completion risks, master planning and engineering change pipeline.</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <x-ui.button variant="light" icon="feather-activity" class="border" href="{{ route('production.variances.index') }}">
                        Routing Variances
                    </x-ui.button>
                    <x-ui.button variant="light" icon="feather-calendar" class="border" href="{{ route('production.plans.index') }}">
                        Master Plans
                    </x-ui.button>
                    <x-ui.button variant="primary" icon="feather-git-pull-request" href="{{ route('production.ecos.index') }}">
                        ECO Hub
                    </x-ui.button>
                </div>
            </div>
            <div class="card-body p-3">
                <div class="row g-3">
                    {{-- Left Card: Operational Execution Variance --}}
                    <div class="col-xl-4 col-md-6 col-12">
                        <div class="p-2 rounded-3 border dash-panel-subtle h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <span class="fs-11 fw-bold text-uppercase text-muted d-inline-flex align-items-center gap-1">
                                        <i class="feather-percent text-primary"></i> Execution Variance ({{ ucfirst($timeframe) }})
                                    </span>
                                    <span class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill px-2.5 py-1">
                                        {{ number_format($productionSummary['schedule_adherence'] ?? 100, 1) }}% Adherence
                                    </span>
                                </div>

                                {{-- Output Metrics --}}
                                <div class="p-2 rounded-3 dash-card-inner border mb-3">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="fs-11 text-muted">Output Units (Planned vs Actual)</span>
                                        <span class="fs-11 fw-bold font-monospace text-dark">
                                            {{ $outputVariance >= 0 ? '+'.number_format($outputVariance) : number_format($outputVariance) }} net
                                        </span>
                                    </div>
                                    <div class="d-flex align-items-baseline justify-content-between">
                                        <span class="fs-13 fw-bold font-monospace text-dark">
                                            {{ number_format($productionSummary['actual_quantity'] ?? 0) }} <small class="text-muted fw-normal">/ {{ number_format($productionSummary['planned_quantity'] ?? 0) }}</small>
                                        </span>
                                        <small class="text-muted fs-11">Yield: <strong class="text-dark font-monospace">{{ number_format($scrapStats['yield'] ?? 100, 1) }}%</strong></small>
                                    </div>
                                    <div class="progress mt-2" style="height: 6px;">
                                        @php
                                            $plannedQty = (float)($productionSummary['planned_quantity'] ?? 0);
                                            $actualQty = (float)($productionSummary['actual_quantity'] ?? 0);
                                            $outPct = $plannedQty > 0 ? min(100, round(($actualQty / $plannedQty) * 100, 1)) : 100;
                                        @endphp
                                        <div class="progress-bar bg-primary" style="width: {{ $outPct }}%"></div>
                                    </div>
                                </div>

                                {{-- Duration Metrics --}}
                                <div class="p-2 rounded-3 dash-card-inner border mb-3">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="fs-11 text-muted">Operation Hours (Actual vs Plan)</span>
                                        <span class="fs-11 fw-bold font-monospace text-dark">
                                            {{ $durationVarianceHours > 0 ? '+'.$durationVarianceHours : $durationVarianceHours }}h delta
                                        </span>
                                    </div>
                                    <div class="d-flex align-items-baseline justify-content-between">
                                        <span class="fs-13 fw-bold font-monospace text-dark">
                                            {{ number_format($actualDurationHours, 1) }}h <small class="text-muted fw-normal">/ {{ number_format($plannedDurationHours, 1) }}h</small>
                                        </span>
                                        <small class="text-muted fs-11">Efficiency: <strong class="text-dark font-monospace">{{ number_format($durationEfficiency, 1) }}%</strong></small>
                                    </div>
                                    <div class="progress mt-2" style="height: 6px;">
                                        @php
                                            $durPct = $plannedDurationHours > 0 ? min(100, round(($actualDurationHours / $plannedDurationHours) * 100, 1)) : ($actualDurationHours > 0 ? 100 : 0);
                                        @endphp
                                        <div class="progress-bar bg-primary" style="width: {{ $durPct }}%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="pt-1 mt-3 border-top text-end">
                                <a href="{{ route('production.variances.index') }}" class="fs-11 text-primary text-decoration-none fw-semibold">
                                    Full Variance Analysis <i class="feather-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Middle Card: Near-Term Order Completion Risk --}}
                    <div class="col-xl-5 col-md-6 col-12">
                        <div class="p-2 rounded-3 border dash-panel-subtle h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fs-11 fw-bold text-uppercase text-muted d-inline-flex align-items-center gap-1">
                                            <i class="feather-clock text-primary"></i> Near-Term Completion Risk (&lt;72h)
                                        </span>
                                        <span class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill px-2 py-0.5">
                                            {{ $atRiskOrders->count() }} At Risk
                                        </span>
                                    </div>
                                    @if($qualityConstrainedOrdersCount > 0)
                                        <span class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill px-2 py-0.5" title="Active orders with open NCR">
                                            <i class="feather-shield me-1"></i>{{ $qualityConstrainedOrdersCount }} Quality Hold
                                        </span>
                                    @endif
                                </div>

                                @if($atRiskOrders->count() > 0)
                                    <div class="table-responsive dash-card-inner rounded-3 border p-2 mb-3">
                                        <table class="table table-sm table-borderless table-hover align-middle mb-0 fs-12">
                                            <thead class="dash-table-head text-muted border-bottom fs-10 text-uppercase">
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
                                                        $targetQ = (float)$riskOrder->quantity_ordered;
                                                        $prodQ = (float)$riskOrder->quantity_produced;
                                                        $progPct = $targetQ > 0 ? round(($prodQ / $targetQ) * 100, 1) : 0;
                                                        $dueCarbon = \Illuminate\Support\Carbon::parse($riskOrder->end_date);
                                                        $hoursLeft = (int) now()->diffInHours($dueCarbon->copy()->endOfDay(), false);
                                                        $countdownLabel = $hoursLeft <= 24 ? 'Today' : ($hoursLeft <= 48 ? '1 Day' : '2 Days');
                                                        $activeOp = $riskOrder->operations->first();
                                                    @endphp
                                                    <tr class="border-bottom">
                                                        <td class="py-1.5">
                                                            <a href="{{ route('production.orders.show', $riskOrder->id) }}" class="fw-bold text-dark text-decoration-none d-block font-monospace">
                                                                {{ $riskOrder->order_number }}
                                                            </a>
                                                            <span class="text-muted fs-11 text-truncate d-inline-block" style="max-width: 140px;" title="{{ $riskOrder->product?->name }}">
                                                                {{ $riskOrder->product?->name ?? 'N/A' }}
                                                            </span>
                                                        </td>
                                                        <td class="text-center py-1.5">
                                                            <span class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill d-inline-block px-2">
                                                                {{ $countdownLabel }}
                                                            </span>
                                                            <div class="fs-10 text-muted font-monospace">{{ $dueCarbon->format('M d') }}</div>
                                                        </td>
                                                        <td class="py-1.5" style="min-width: 110px;">
                                                            <div class="d-flex align-items-center justify-content-between fs-11 mb-1">
                                                                <span class="font-monospace fw-bold text-dark">{{ $progPct }}%</span>
                                                                <span class="text-muted fs-10 font-monospace">{{ (int)$prodQ }}/{{ (int)$targetQ }}</span>
                                                            </div>
                                                            <div class="progress" style="height: 4px;">
                                                                <div class="progress-bar bg-primary" style="width: {{ $progPct }}%"></div>
                                                            </div>
                                                        </td>
                                                        <td class="text-end py-1.5">
                                                            @if($activeOp)
                                                                <span class="badge bg-soft-primary text-primary fs-10 text-truncate font-monospace rounded-pill px-2" style="max-width: 100px;" title="{{ $activeOp->name }} @if($activeOp->workCenter)({{ $activeOp->workCenter->name }})@endif">
                                                                    {{ $activeOp->name }}
                                                                </span>
                                                            @else
                                                                <span class="badge bg-soft-primary text-primary fs-10 font-monospace rounded-pill px-2">In Queue</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="text-center py-5 px-3 text-muted dash-card-inner rounded-3 border d-flex flex-column align-items-center justify-content-center" style="min-height: 190px;">
                                        <div class="rounded-circle bg-soft-primary text-primary d-inline-flex align-items-center justify-content-center mb-3" style="width: 44px; height: 44px;">
                                            <i class="feather-check-circle fs-22 text-primary"></i>
                                        </div>
                                        <span class="fs-13 fw-semibold text-dark mb-1">No Near-Term Completion Risk</span>
                                        <div class="fs-11 text-muted" style="max-width: 320px;">All active orders due within 72 hours have achieved &ge; 50% target progress.</div>
                                    </div>
                                @endif
                            </div>
                            <div class="pt-1 mt-3 border-top text-end">
                                <a href="{{ route('production.planning-exceptions.index') }}" class="fs-11 text-primary text-decoration-none fw-semibold">
                                    Full Exception Engine <i class="feather-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Right Card: Planning & ECO Pipeline Pulse --}}
                    <div class="col-xl-3 col-12">
                        <div class="p-2 rounded-3 border dash-panel-subtle h-100 d-flex flex-column justify-content-between">
                            <div>
                                <span class="fs-11 fw-bold text-uppercase text-muted d-block mb-3">
                                    <i class="feather-layers me-1 text-primary"></i>Planning &amp; ECO Pulse
                                </span>

                                {{-- Master Plans Strip --}}
                                <div class="p-2 rounded-3 dash-card-inner border mb-3">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="fs-11 fw-bold text-dark"><i class="feather-calendar me-1 text-primary"></i>Master Plans</span>
                                        <a href="{{ route('production.plans.index') }}" class="fs-10 text-primary text-decoration-none font-monospace">View All</a>
                                    </div>
                                    <div class="d-flex flex-wrap gap-1 text-center">
                                        <div class="flex-fill p-1.5 rounded dash-mini-tile border">
                                            <small class="text-muted fs-10 d-block">Draft</small>
                                            <strong class="font-monospace text-dark fs-12">{{ $planStatusCounts['draft'] ?? 0 }}</strong>
                                        </div>
                                        <div class="flex-fill p-1.5 rounded dash-mini-tile border">
                                            <small class="text-muted fs-10 d-block">Pending</small>
                                            <strong class="font-monospace text-dark fs-12">{{ $planStatusCounts['pending_approval'] ?? 0 }}</strong>
                                        </div>
                                        <div class="flex-fill p-1.5 rounded dash-mini-tile border">
                                            <small class="text-muted fs-10 d-block">Approved</small>
                                            <strong class="font-monospace text-dark fs-12">{{ $planStatusCounts['approved'] ?? 0 }}</strong>
                                        </div>
                                        <div class="flex-fill p-1.5 rounded dash-mini-tile border">
                                            <small class="text-muted fs-10 d-block">Released</small>
                                            <strong class="font-monospace text-primary fs-12">{{ $planStatusCounts['released'] ?? 0 }}</strong>
                                        </div>
                                    </div>
                                </div>

                                {{-- ECO Pipeline Strip --}}
                                <div class="p-2 rounded-3 dash-card-inner border mb-3">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="fs-11 fw-bold text-dark"><i class="feather-git-pull-request me-1 text-primary"></i>ECO Pipeline</span>
                                        <a href="{{ route('production.ecos.index') }}" class="fs-10 text-primary text-decoration-none font-monospace">View All</a>
                                    </div>
                                    <div class="d-flex flex-wrap gap-1 text-center">
                                        <div class="flex-fill p-1.5 rounded dash-mini-tile border">
                                            <small class="text-muted fs-10 d-block">Draft</small>
                                            <strong class="font-monospace text-dark fs-12">{{ $ecoStatusCounts['draft'] ?? 0 }}</strong>
                                        </div>
                                        <div class="flex-fill p-1.5 rounded dash-mini-tile border">
                                            <small class="text-muted fs-10 d-block">Review</small>
                                            <strong class="font-monospace text-dark fs-12">{{ $ecoStatusCounts['under_review'] ?? 0 }}</strong>
                                        </div>
                                        <div class="flex-fill p-1.5 rounded dash-mini-tile border">
                                            <small class="text-muted fs-10 d-block">Approved</small>
                                            <strong class="font-monospace text-dark fs-12">{{ $ecoStatusCounts['approved'] ?? 0 }}</strong>
                                        </div>
                                        <div class="flex-fill p-1.5 rounded dash-mini-tile border">
                                            <small class="text-muted fs-10 d-block">Released</small>
                                            <strong class="font-monospace text-primary fs-12">{{ $ecoStatusCounts['released'] ?? 0 }}</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="pt-3 mt-3 border-top d-flex align-items-center justify-content-between fs-11">
                                <span class="text-muted">Engineering Changes</span>
                                <a href="{{ route('production.ecos.index') }}" class="text-primary text-decoration-none fw-semibold">
                                    ECO Register <i class="feather-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── 10. Material Readiness Banner (If any fully issued orders exist) ── --}}
        @if($fullyIssuedCount > 0)
            @php $firstFullyOrder = $fullyIssuedOrders->first(); @endphp
            <div class="ready-start-banner p-3 mb-3 shadow-sm d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-md bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center fs-20 flex-shrink-0" style="width: 48px; height: 48px;">
                        <i class="feather-check-circle"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1 fs-14">
                            <i class="feather-box me-1 text-primary"></i>
                            @if($fullyIssuedCount === 1)
                                Store Material Fully Issued for Order #{{ $firstFullyOrder->order_number }}!
                            @else
                                {{ $fullyIssuedCount }} Production Order(s) - Store Material Fully Issued!
                            @endif
                        </h6>
                        <span class="fs-12 text-muted">
                            Raw materials have been fully issued by warehouse store. Ready for scheduling and shop floor execution.
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <x-ui.button variant="primary" icon="feather-arrow-right-circle" onclick="activateTab('content-ready-tab')">
                        View Ready Orders ({{ $fullyIssuedCount }})
                    </x-ui.button>
                </div>
            </div>
        @endif

        {{-- ── 11. Operational Worklists using Common Horizontal Tabs & Table ──── --}}
        <div class="card dash-card border-0 shadow-sm rounded-3 mb-3 overflow-hidden">
            <div class="card-header pt-3 pb-0 px-4 border-bottom-0">
                <x-ui.horizontal-tabs id="operationalTabs" :tabs="$worklistTabs" />
            </div>

            <div class="card-body p-0">
                <div class="tab-content" id="operationalTabsContent">

                    {{-- ── TAB 1: Pending Demand ────────────────────────────────── --}}
                    <div class="tab-pane fade show active" id="content-demand" role="tabpanel" aria-labelledby="content-demand-tab">
                        <div class="px-4 py-2 dash-panel-subtle border-bottom d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="fw-bold text-dark mb-1 fs-13">Pending Sales Orders to Manufacture</h6>
                                <small class="text-muted d-block">Sales Orders / Material Requisitions awaiting Production Order conversion.</small>
                            </div>
                            <span class="badge bg-soft-primary text-primary fs-11 px-2.5 py-1 rounded-pill">
                                {{ $pendingSalesOrderCount }} Pending Demand(s)
                            </span>
                        </div>
                        <div class="table-responsive p-0">
                            <table class="table table-hover align-middle mb-0 fs-12">
                                <thead class="dash-table-head text-muted fs-11 text-uppercase">
                                    <tr>
                                        <th class="ps-4 py-2.5">Sales Order / Request #</th>
                                        <th class="py-2.5">Customer</th>
                                        <th class="py-2.5">Target Product</th>
                                        <th class="text-end py-2.5">Requested Qty</th>
                                        <th class="py-2.5">Status</th>
                                        <th class="text-center pe-4 py-2.5" style="width: 180px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($pendingRequests as $req)
                                        @php
                                            $deliveryItem = $req->materialRequirementItem;
                                            $delivery = $deliveryItem?->materialRequirement;
                                            $sales = $delivery?->salesOrder ?? $deliveryItem?->salesOrderItem?->salesOrder;
                                            $customer = $sales?->customer;
                                            $product = $req->product;
                                        @endphp
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-primary font-monospace">
                                                    {{ $sales?->sales_order_number ?? ('REQ-' . sprintf('%06d', $req->id)) }}
                                                </div>
                                                @if($delivery)
                                                    <small class="text-muted fs-11"><i class="feather-file-text me-1"></i>{{ $delivery->requirement_number }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark">{{ $customer?->company_name ?? $customer?->name ?? 'Direct Requirement' }}</div>
                                            </td>
                                            <td>
                                                @if($product)
                                                    <div class="fw-semibold text-dark">{{ $product->name }}</div>
                                                    <small class="text-muted font-monospace fs-11">SKU: {{ $product->sku }}</small>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-end fw-bold text-dark font-monospace">
                                                {{ number_format((float) $req->quantity_requested, 2) }}
                                            </td>
                                            <td>
                                                <span class="badge bg-soft-primary text-primary fs-11 font-monospace rounded-pill px-2.5 py-1">
                                                    Pending
                                                </span>
                                            </td>
                                            <td class="text-center pe-4">
                                                <x-ui.button variant="primary" icon="feather-plus-circle" href="{{ route('production.orders.create', ['production_order_request_id' => $req->id, 'sales_order_id' => $sales?->id]) }}">
                                                    Create Order
                                                </x-ui.button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-5">
                                                <div class="py-4 my-2 d-flex flex-column align-items-center justify-content-center">
                                                    <div class="rounded-circle bg-soft-primary text-primary d-inline-flex align-items-center justify-content-center mb-3" style="width: 52px; height: 52px;">
                                                        <i class="feather-check-circle fs-26 text-primary"></i>
                                                    </div>
                                                    <h6 class="fw-semibold text-dark mb-1 fs-14">All sales order demands have active Production Orders created.</h6>
                                                    <p class="text-muted fs-12 mb-0">There are no pending sales order conversions required right now.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- ── TAB 2: Ready to Start ────────────────────────────────── --}}
                    <div class="tab-pane fade" id="content-ready" role="tabpanel" aria-labelledby="content-ready-tab">
                        <div class="table-responsive p-0">
                            <table class="table table-hover align-middle mb-0 fs-12">
                                <thead class="dash-table-head text-muted fs-11 text-uppercase">
                                    <tr>
                                        <th class="ps-4 py-2.5">Order #</th>
                                        <th class="py-2.5">Finished Product</th>
                                        <th class="text-end py-2.5">Qty Ordered</th>
                                        <th class="text-center py-2.5">Store Material Status</th>
                                        <th class="text-center py-2.5">Schedule Status</th>
                                        <th class="text-center pe-4 py-2.5" style="width: 170px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($readyToStartOrders as $order)
                                        @php
                                            $latestSlip = $order->requisitionSlips->last();
                                            $slipStatusLower = strtolower($latestSlip?->status ?? '');
                                            $isFully = in_array($slipStatusLower, ['fully issued', 'completed', 'issued']);
                                            $activeSchedule = $order->schedules->whereNotIn('status', ['cancelled'])->first();
                                        @endphp
                                        <tr>
                                            <td class="ps-4">
                                                <a href="{{ route('production.orders.show', $order->id) }}" class="fw-bold text-primary font-monospace">
                                                    {{ $order->order_number }}
                                                </a>
                                                <div class="fs-11 text-muted text-uppercase font-monospace">{{ $order->production_mode }}</div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark">{{ $order->product?->name ?? '—' }}</div>
                                                <small class="text-muted font-monospace fs-11">SKU: {{ $order->product?->sku }}</small>
                                            </td>
                                            <td class="text-end fw-bold font-monospace text-dark">
                                                {{ number_format($order->quantity_ordered, 2) }}
                                            </td>
                                            <td class="text-center">
                                                @if($isFully)
                                                    <span class="badge bg-soft-primary text-primary border border-primary border-opacity-20 fs-11 px-2.5 py-1 rounded-pill font-monospace">
                                                        <i class="feather-check-circle me-1"></i>Fully Issued
                                                    </span>
                                                @else
                                                    <span class="badge bg-soft-primary text-primary border border-primary border-opacity-20 fs-11 px-2.5 py-1 rounded-pill font-monospace">
                                                        <i class="feather-alert-circle me-1"></i>Partially Issued
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if($activeSchedule)
                                                    <a href="{{ route('production.schedules.show', $activeSchedule->id) }}" class="badge bg-soft-primary text-primary border border-primary border-opacity-20 font-monospace fs-11 px-2.5 py-1 rounded-pill">
                                                        <i class="feather-calendar me-1"></i>{{ $activeSchedule->schedule_number }}
                                                    </a>
                                                @else
                                                    <span class="badge bg-soft-primary text-primary fs-11 px-2.5 py-1 rounded-pill">Unscheduled</span>
                                                @endif
                                            </td>
                                            <td class="text-center pe-4">
                                                <div class="d-flex align-items-center justify-content-center gap-2">
                                                    <x-ui.button variant="light" icon="feather-eye" class="border" href="{{ route('production.orders.show', $order->id) }}">
                                                        View
                                                    </x-ui.button>
                                                    @if(!$activeSchedule)
                                                        <x-ui.button variant="primary" icon="feather-calendar" href="{{ route('production.schedules.index') }}">
                                                            Schedule
                                                        </x-ui.button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-5">
                                                <div class="py-4 my-2 d-flex flex-column align-items-center justify-content-center">
                                                    <div class="rounded-circle bg-soft-primary text-primary d-inline-flex align-items-center justify-content-center mb-3" style="width: 52px; height: 52px;">
                                                        <i class="feather-inbox fs-26"></i>
                                                    </div>
                                                    <h6 class="fw-semibold text-dark mb-1 fs-14">No orders currently waiting in Ready-to-Start status.</h6>
                                                    <p class="text-muted fs-12 mb-0">All ready orders have either progressed to shop floor or are pending material release.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- ── TAB 3: Active In-Progress Execution ───────────────────── --}}
                    <div class="tab-pane fade" id="content-inprogress" role="tabpanel" aria-labelledby="content-inprogress-tab">
                        <div class="table-responsive p-0">
                            <table class="table table-hover align-middle mb-0 fs-12">
                                <thead class="dash-table-head text-muted fs-11 text-uppercase">
                                    <tr>
                                        <th class="ps-4 py-2.5">Order #</th>
                                        <th class="py-2.5">Product</th>
                                        <th class="text-end py-2.5">Target Qty</th>
                                        <th class="text-center py-2.5">Status</th>
                                        <th class="text-center py-2.5" style="width: 180px;">Operation Progress</th>
                                        <th class="text-center py-2.5">Target Finish</th>
                                        <th class="text-center pe-4 py-2.5" style="width: 150px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($inProgressOrders as $order)
                                        @php
                                            $totalOps = $order->operations->count();
                                            $completedOps = $order->operations->where('status', 'completed')->count();
                                            $progressPercent = $totalOps > 0 ? round(($completedOps / $totalOps) * 100) : 0;
                                            $currentOp = $order->operations->whereIn('status', ['running', 'in_progress', 'ready'])->first();
                                        @endphp
                                        <tr>
                                            <td class="ps-4">
                                                <a href="{{ route('production.orders.show', $order->id) }}" class="fw-bold text-primary font-monospace">
                                                    {{ $order->order_number }}
                                                </a>
                                                <div class="fs-11 text-muted text-uppercase font-monospace">{{ $order->production_mode }}</div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark">{{ $order->product?->name ?? '—' }}</div>
                                                <small class="text-muted font-monospace fs-11">SKU: {{ $order->product?->sku }}</small>
                                            </td>
                                            <td class="text-end fw-bold font-monospace text-dark">
                                                {{ number_format($order->quantity_ordered, 2) }}
                                            </td>
                                            <td class="text-center">
                                                <x-ui.status-badge :status="$order->status" />
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress flex-grow-1" style="height: 6px;">
                                                        <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $progressPercent }}%"></div>
                                                    </div>
                                                    <span class="fs-11 font-monospace fw-bold text-dark">{{ $progressPercent }}%</span>
                                                </div>
                                                @if($currentOp)
                                                    <div class="fs-11 text-muted mt-1 text-truncate" style="max-width: 180px;">
                                                        <i class="feather-sliders me-1 text-primary"></i>{{ $currentOp->name }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-center font-monospace fs-12 text-muted">
                                                {{ $order->end_date ? \Carbon\Carbon::parse($order->end_date)->format('M d, Y') : '—' }}
                                            </td>
                                            <td class="text-center pe-4">
                                                <div class="d-flex align-items-center justify-content-center gap-2">
                                                    <x-ui.button variant="light" icon="feather-eye" class="border" href="{{ route('production.orders.show', $order->id) }}">
                                                        View
                                                    </x-ui.button>
                                                    <x-ui.button variant="primary" icon="feather-sliders" href="{{ route('production.orders.show', ['order' => $order->id, 'tab' => 'vtab-operations']) }}">
                                                        Ops
                                                    </x-ui.button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-5">
                                                <div class="py-4 my-2 d-flex flex-column align-items-center justify-content-center">
                                                    <div class="rounded-circle bg-soft-primary text-primary d-inline-flex align-items-center justify-content-center mb-3" style="width: 52px; height: 52px;">
                                                        <i class="feather-activity fs-26"></i>
                                                    </div>
                                                    <h6 class="fw-semibold text-dark mb-1 fs-14">No production orders currently in execution.</h6>
                                                    <p class="text-muted fs-12 mb-0">Shop floor operations are clear or pending job dispatch.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- ── TAB 4: At-Risk / Overdue Orders ───────────────────────── --}}
                    <div class="tab-pane fade" id="content-overdue" role="tabpanel" aria-labelledby="content-overdue-tab">
                        <div class="table-responsive p-0">
                            <table class="table table-hover align-middle mb-0 fs-12">
                                <thead class="dash-table-head text-muted fs-11 text-uppercase">
                                    <tr>
                                        <th class="ps-4 py-2.5">Order #</th>
                                        <th class="py-2.5">Product</th>
                                        <th class="text-end py-2.5">Ordered Qty</th>
                                        <th class="text-center py-2.5">Status</th>
                                        <th class="text-center py-2.5">Planned Due Date</th>
                                        <th class="text-center py-2.5">Overdue Severity</th>
                                        <th class="text-center pe-4 py-2.5" style="width: 140px;">Actions</th>
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
                                                <a href="{{ route('production.orders.show', $order->id) }}" class="fw-bold text-dark font-monospace text-decoration-none">
                                                    {{ $order->order_number }}
                                                </a>
                                                <div class="fs-11 text-muted text-uppercase font-monospace">{{ $order->production_mode }}</div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark">{{ $order->product?->name ?? '—' }}</div>
                                                <small class="text-muted font-monospace fs-11">SKU: {{ $order->product?->sku }}</small>
                                            </td>
                                            <td class="text-end fw-bold font-monospace text-dark">
                                                {{ number_format($order->quantity_ordered, 2) }}
                                            </td>
                                            <td class="text-center">
                                                <x-ui.status-badge :status="$order->status" />
                                            </td>
                                            <td class="text-center font-monospace fs-12 text-muted">
                                                <i class="feather-calendar me-1 text-primary"></i>{{ $endDate->format('M d, Y') }}
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-soft-primary text-primary fs-11 px-2.5 py-1 rounded-pill font-monospace">
                                                    {{ $daysOverdue }} Day(s) Overdue
                                                </span>
                                            </td>
                                            <td class="text-center pe-4">
                                                <x-ui.button variant="primary" icon="feather-arrow-right" href="{{ route('production.orders.show', $order->id) }}">
                                                    Expedite
                                                </x-ui.button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-5">
                                                <div class="py-4 my-2 d-flex flex-column align-items-center justify-content-center">
                                                    <div class="rounded-circle bg-soft-primary text-primary d-inline-flex align-items-center justify-content-center mb-3" style="width: 52px; height: 52px;">
                                                        <i class="feather-check-circle fs-26"></i>
                                                    </div>
                                                    <h6 class="fw-semibold text-dark mb-1 fs-14">Zero Overdue Orders! All production schedules are within target delivery dates.</h6>
                                                    <p class="text-muted fs-12 mb-0">Manufacturing throughput is adhering to customer commitments.</p>
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
    </div>

    @push('scripts')
        <script>
            function activateTab(tabId) {
                const tabEl = document.getElementById(tabId);
                if (tabEl) {
                    const tab = new bootstrap.Tab(tabEl);
                    tab.show();
                    tabEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
        </script>
    @endpush
@endsection
