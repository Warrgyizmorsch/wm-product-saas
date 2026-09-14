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
        }

        .action-tile-card {
            border-radius: 10px;
            transition: all 0.2s ease-in-out;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            min-height: 116px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 16px 14px;
        }

        .action-tile-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
        }

        .loss-tile-box {
            border-radius: 10px;
            min-height: 106px;
            padding: 14px 10px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
            transition: all 0.2s ease-in-out;
        }

        .machine-state-box {
            border-radius: 10px;
            min-height: 96px;
            padding: 13px 8px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
            transition: all 0.2s ease-in-out;
        }

        .quality-tile-box {
            border-radius: 10px;
            min-height: 114px;
            padding: 16px 14px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.2s ease-in-out;
        }

        .maintenance-tile-box {
            border-radius: 10px;
            min-height: 110px;
            padding: 16px 12px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
            transition: all 0.2s ease-in-out;
        }

        .subcontract-pipeline-box {
            border-radius: 8px;
            min-height: 80px;
            padding: 12px 6px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
            transition: all 0.2s ease-in-out;
        }

        .metric-tile-box {
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 14px 12px;
            transition: all 0.15s ease-in-out;
        }

        .hover-shadow {
            transition: all 0.2s ease-in-out;
        }

        .hover-shadow:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        }

        .ready-start-banner {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-left: 5px solid #16a34a;
            border-radius: 10px;
        }

        .production-dashboard-wrapper {
            padding: 14px 24px 36px 24px;
        }

        @media (max-width: 768px) {
            .production-dashboard-wrapper {
                padding: 8px 12px 24px 12px;
            }
        }
    </style>
@endpush

@section('content')
    <div class="production-dashboard-wrapper">
        {{-- ── 1. Top Executive Welcome Card (HRMS Style) ────────────────────────── --}}
        <div class="card border-0 shadow-sm mb-4 bg-white rounded-3">
            <div class="card-body p-4 px-4">
                <div class="row align-items-center">
                    <div class="col-lg-7">
                        <div class="d-flex align-items-center gap-4">
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

        {{-- ── 2. Row of 4 Hero KPI Cards (HRMS Standard Tokens) ────────────────── --}}
        <div class="row g-3 mb-4">
            {{-- KPI 1: Active Production Orders Pipeline --}}
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
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
                                <strong class="text-warning fs-13 font-monospace">{{ $orderStatusCounts['draft'] }}</strong>
                            </div>
                            <div class="col-4 border-end px-1">
                                <span class="text-muted d-block fs-10 text-uppercase fw-semibold mb-0.5">Released</span>
                                <strong class="text-primary fs-13 font-monospace">{{ $orderStatusCounts['released'] }}</strong>
                            </div>
                            <div class="col-4 ps-1">
                                <span class="text-muted d-block fs-10 text-uppercase fw-semibold mb-0.5">In Progress</span>
                                <strong class="text-info fs-13 font-monospace">{{ $orderStatusCounts['in_progress'] }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- KPI 2: Production Volume & Adherence --}}
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                    <div class="card-body p-3.5">
                        <div class="d-flex align-items-center justify-content-between mb-2.5">
                            <div class="avatar-text avatar-md bg-soft-success text-success rounded-3">
                                <i class="feather-check-circle fs-5"></i>
                            </div>
                            <span class="badge bg-soft-success text-success fw-bold px-2 py-0.5 fs-11">
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
                                <strong class="text-success fs-13 font-monospace">{{ number_format($productionSummary['actual_quantity'] ?? 0) }}</strong>
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
                <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                    <div class="card-body p-3.5">
                        <div class="d-flex align-items-center justify-content-between mb-2.5">
                            <div class="avatar-text avatar-md bg-soft-info text-info rounded-3">
                                <i class="feather-activity fs-5"></i>
                            </div>
                            <span class="badge bg-soft-info text-info fw-bold px-2 py-0.5 fs-11">
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
                                <strong class="{{ ($oeeKpi['variance'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }} fs-13 font-monospace">
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
                <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                    <div class="card-body p-3.5">
                        <div class="d-flex align-items-center justify-content-between mb-2.5">
                            <div class="avatar-text avatar-md bg-soft-warning text-warning rounded-3">
                                <i class="feather-layers fs-5"></i>
                            </div>
                            <span class="badge bg-soft-warning text-warning fw-bold px-2 py-0.5 fs-11">
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
                                <strong class="text-success fs-13 font-monospace">{{ number_format($wipSummary['total_available_qty'] ?? 0) }}</strong>
                            </div>
                            <div class="col-4 border-end px-1">
                                <span class="text-muted d-block fs-10 text-uppercase fw-semibold mb-0.5">Total Qty</span>
                                <strong class="text-dark fs-13 font-monospace">{{ number_format($wipSummary['total_wip_qty'] ?? 0) }}</strong>
                            </div>
                            <div class="col-4 ps-1">
                                <span class="text-muted d-block fs-10 text-uppercase fw-semibold mb-0.5">Scrapped</span>
                                <strong class="text-danger fs-13 font-monospace">{{ number_format($wipSummary['total_scrap_qty'] ?? 0) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── 3. Critical Action Center (Real-Time Red Flags & Exceptions) ─────── --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="dash-card-icon-avatar {{ $totalCriticalExceptions > 0 ? 'bg-soft-danger text-danger' : 'bg-soft-success text-success' }}">
                        <i class="{{ $totalCriticalExceptions > 0 ? 'feather-alert-triangle' : 'feather-check-circle' }}"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h6 class="card-title fw-bold text-dark mb-0 fs-14">Critical Action Center</h6>
                            @if($totalCriticalExceptions > 0)
                                <span class="badge bg-soft-danger text-danger rounded-pill px-2.5 py-0.5 fs-11 fw-bold border border-danger border-opacity-10">
                                    {{ $totalCriticalExceptions }} Critical Alert(s) Active
                                </span>
                            @else
                                <span class="badge bg-soft-success text-success rounded-pill px-2.5 py-0.5 fs-11 fw-bold border border-success border-opacity-10">
                                    All Operations On Track
                                </span>
                            @endif
                        </div>
                        <span class="fs-11 text-muted">Immediate manufacturing and shop-floor exception resolution worklist.</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('production.planning-exceptions.index') }}" class="btn btn-sm btn-soft-danger fw-bold fs-12 d-inline-flex align-items-center gap-1.5 px-3 py-1.5 rounded-pill">
                        <i class="feather-alert-octagon fs-13"></i>
                        <span>Exception Engine</span>
                        <i class="feather-arrow-right fs-12"></i>
                    </a>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    {{-- Alert 1: Overdue Production Orders --}}
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <a href="#operationalTabs" onclick="activateTab('content-overdue-tab')" class="text-decoration-none">
                            <div class="action-tile-card {{ $overdueOrdersCount > 0 ? 'border-danger bg-soft-danger' : 'bg-light' }} h-100">
                                <div class="d-flex align-items-center justify-content-between mb-1.5">
                                    <span class="fs-11 fw-bold text-uppercase {{ $overdueOrdersCount > 0 ? 'text-danger' : 'text-muted' }}">Overdue Orders</span>
                                    <i class="feather-clock {{ $overdueOrdersCount > 0 ? 'text-danger' : 'text-muted' }} fs-14"></i>
                                </div>
                                <h3 class="fw-bold my-1 font-monospace {{ $overdueOrdersCount > 0 ? 'text-danger' : 'text-dark' }} fs-22">
                                    {{ number_format($overdueOrdersCount) }}
                                </h3>
                                <small class="text-muted fs-11">Past planned completion</small>
                            </div>
                        </a>
                    </div>

                    {{-- Alert 2: Machine Breakdowns --}}
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <a href="{{ route('production.mes.dashboard') }}" class="text-decoration-none">
                            <div class="action-tile-card {{ $breakdownCount > 0 ? 'border-danger bg-soft-danger' : 'bg-light' }} h-100">
                                <div class="d-flex align-items-center justify-content-between mb-1.5">
                                    <span class="fs-11 fw-bold text-uppercase {{ $breakdownCount > 0 ? 'text-danger' : 'text-muted' }}">Machine Breakdown</span>
                                    <i class="feather-alert-octagon {{ $breakdownCount > 0 ? 'text-danger' : 'text-muted' }} fs-14"></i>
                                </div>
                                <h3 class="fw-bold my-1 font-monospace {{ $breakdownCount > 0 ? 'text-danger' : 'text-dark' }} fs-22">
                                    {{ number_format($breakdownCount) }}
                                </h3>
                                <small class="text-muted fs-11">Halted equipment</small>
                            </div>
                        </a>
                    </div>

                    {{-- Alert 3: Material Blockers --}}
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <a href="{{ route('sales.material-requests.index') }}" class="text-decoration-none">
                            <div class="action-tile-card {{ $materialBlockersCount > 0 ? 'border-warning bg-soft-warning' : 'bg-light' }} h-100">
                                <div class="d-flex align-items-center justify-content-between mb-1.5">
                                    <span class="fs-11 fw-bold text-uppercase {{ $materialBlockersCount > 0 ? 'text-warning-emphasis' : 'text-muted' }}">Material Blockers</span>
                                    <i class="feather-package {{ $materialBlockersCount > 0 ? 'text-warning' : 'text-muted' }} fs-14"></i>
                                </div>
                                <h3 class="fw-bold my-1 font-monospace {{ $materialBlockersCount > 0 ? 'text-warning-emphasis' : 'text-dark' }} fs-22">
                                    {{ number_format($materialBlockersCount) }}
                                </h3>
                                <small class="text-muted fs-11">Pending store release</small>
                            </div>
                        </a>
                    </div>

                    {{-- Alert 4: Quality Holds & Pending QC --}}
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <a href="{{ route('production.inspections.index') }}" class="text-decoration-none">
                            <div class="action-tile-card {{ ($pendingQcCount + $openNcrCount) > 0 ? 'border-info bg-soft-info' : 'bg-light' }} h-100">
                                <div class="d-flex align-items-center justify-content-between mb-1.5">
                                    <span class="fs-11 fw-bold text-uppercase {{ ($pendingQcCount + $openNcrCount) > 0 ? 'text-info-emphasis' : 'text-muted' }}">Pending QC &amp; NCR</span>
                                    <i class="feather-shield {{ ($pendingQcCount + $openNcrCount) > 0 ? 'text-info' : 'text-muted' }} fs-14"></i>
                                </div>
                                <h3 class="fw-bold my-1 font-monospace {{ ($pendingQcCount + $openNcrCount) > 0 ? 'text-info-emphasis' : 'text-dark' }} fs-22">
                                    {{ number_format($pendingQcCount) }} <span class="fs-12 fw-normal text-muted">/ {{ $openNcrCount }} NCR</span>
                                </h3>
                                <small class="text-muted fs-11">Awaiting inspection audit</small>
                            </div>
                        </a>
                    </div>

                    {{-- Alert 5: Subcontracting Delays --}}
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <a href="{{ route('production.subcontract.analytics') }}" class="text-decoration-none">
                            <div class="action-tile-card {{ $vendorDelayedCount > 0 ? 'border-danger bg-soft-danger' : 'bg-light' }} h-100">
                                <div class="d-flex align-items-center justify-content-between mb-1.5">
                                    <span class="fs-11 fw-bold text-uppercase {{ $vendorDelayedCount > 0 ? 'text-danger' : 'text-muted' }}">Vendor Delayed</span>
                                    <i class="feather-truck {{ $vendorDelayedCount > 0 ? 'text-danger' : 'text-muted' }} fs-14"></i>
                                </div>
                                <h3 class="fw-bold my-1 font-monospace {{ $vendorDelayedCount > 0 ? 'text-danger' : 'text-dark' }} fs-22">
                                    {{ number_format($vendorDelayedCount) }}
                                </h3>
                                <small class="text-muted fs-11">Outside ops overdue</small>
                            </div>
                        </a>
                    </div>

                    {{-- Alert 6: Pending Sales Demands --}}
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <a href="#operationalTabs" onclick="activateTab('content-demand-tab')" class="text-decoration-none">
                            <div class="action-tile-card {{ $pendingSalesOrderCount > 0 ? 'border-primary bg-soft-primary' : 'bg-light' }} h-100">
                                <div class="d-flex align-items-center justify-content-between mb-1.5">
                                    <span class="fs-11 fw-bold text-uppercase {{ $pendingSalesOrderCount > 0 ? 'text-primary' : 'text-muted' }}">Pending Demand</span>
                                    <i class="feather-shopping-cart {{ $pendingSalesOrderCount > 0 ? 'text-primary' : 'text-muted' }} fs-14"></i>
                                </div>
                                <h3 class="fw-bold my-1 font-monospace {{ $pendingSalesOrderCount > 0 ? 'text-primary' : 'text-dark' }} fs-22">
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
        <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
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
                    <x-ui.button variant="danger" icon="feather-alert-triangle" href="{{ route('production.intelligence.andon') }}">
                        Live Andon Board
                    </x-ui.button>
                </div>
            </div>
            <div class="card-body p-4">
                {{-- Machine States Grid --}}
                <div class="row g-2 mb-3">
                    <div class="col-6 col-sm-4 col-md-2">
                        <a href="{{ route('production.mes.machines.index', ['state' => 'Running']) }}" class="text-decoration-none">
                            <div class="machine-state-box border border-success-subtle bg-soft-success hover-shadow">
                                <div class="d-flex align-items-center justify-content-center gap-1.5 mb-1">
                                    <span class="spinner-grow spinner-grow-sm text-success" style="width: 8px; height: 8px;"></span>
                                    <span class="fs-11 fw-bold text-success text-uppercase">Running</span>
                                </div>
                                <h3 class="fw-bold text-success-emphasis mb-0 font-monospace fs-18">{{ number_format($machineStateCounts['running'] ?? 0) }}</h3>
                                <small class="text-muted fs-11">Active Line</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-sm-4 col-md-2">
                        <a href="{{ route('production.mes.machines.index', ['state' => 'Idle']) }}" class="text-decoration-none">
                            <div class="machine-state-box border bg-light hover-shadow">
                                <span class="fs-11 fw-bold text-secondary text-uppercase d-block mb-1">Idle</span>
                                <h3 class="fw-bold text-dark mb-0 font-monospace fs-18">{{ number_format($machineStateCounts['idle'] ?? 0) }}</h3>
                                <small class="text-muted fs-11">Awaiting Jobs</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-sm-4 col-md-2">
                        <a href="{{ route('production.mes.machines.index', ['state' => 'Setup']) }}" class="text-decoration-none">
                            <div class="machine-state-box border border-info-subtle bg-soft-info hover-shadow">
                                <span class="fs-11 fw-bold text-info text-uppercase d-block mb-1">Setup</span>
                                <h3 class="fw-bold text-info-emphasis mb-0 font-monospace fs-18">{{ number_format($machineStateCounts['setup'] ?? 0) }}</h3>
                                <small class="text-muted fs-11">Tooling / Change</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-sm-4 col-md-2">
                        <a href="{{ route('production.mes.machines.index', ['state' => 'Breakdown']) }}" class="text-decoration-none">
                            <div class="machine-state-box border {{ ($machineStateCounts['breakdown'] ?? 0) > 0 ? 'border-danger-subtle bg-soft-danger' : 'bg-light' }} hover-shadow">
                                <div class="d-flex align-items-center justify-content-center gap-1 mb-1">
                                    @if(($machineStateCounts['breakdown'] ?? 0) > 0)
                                        <i class="feather-alert-octagon text-danger fs-12"></i>
                                    @endif
                                    <span class="fs-11 fw-bold {{ ($machineStateCounts['breakdown'] ?? 0) > 0 ? 'text-danger' : 'text-muted' }} text-uppercase">Breakdown</span>
                                </div>
                                <h3 class="fw-bold {{ ($machineStateCounts['breakdown'] ?? 0) > 0 ? 'text-danger' : 'text-dark' }} mb-0 font-monospace fs-18">{{ number_format($machineStateCounts['breakdown'] ?? 0) }}</h3>
                                <small class="text-muted fs-11">Halted</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-sm-4 col-md-2">
                        <a href="{{ route('production.mes.machines.index', ['state' => 'Maintenance']) }}" class="text-decoration-none">
                            <div class="machine-state-box border {{ ($machineStateCounts['maintenance'] ?? 0) > 0 ? 'border-warning-subtle bg-soft-warning' : 'bg-light' }} hover-shadow">
                                <span class="fs-11 fw-bold {{ ($machineStateCounts['maintenance'] ?? 0) > 0 ? 'text-warning-emphasis' : 'text-muted' }} text-uppercase d-block mb-1">Maintenance</span>
                                <h3 class="fw-bold {{ ($machineStateCounts['maintenance'] ?? 0) > 0 ? 'text-warning-emphasis' : 'text-dark' }} mb-0 font-monospace fs-18">{{ number_format($machineStateCounts['maintenance'] ?? 0) }}</h3>
                                <small class="text-muted fs-11">PM Servicing</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-6 col-sm-4 col-md-2">
                        <a href="{{ route('production.mes.machines.index', ['state' => 'Offline']) }}" class="text-decoration-none">
                            <div class="machine-state-box border bg-light hover-shadow">
                                <span class="fs-11 fw-bold text-muted text-uppercase d-block mb-1">Offline</span>
                                <h3 class="fw-bold text-muted mb-0 font-monospace fs-18">{{ number_format($machineStateCounts['offline'] ?? 0) }}</h3>
                                <small class="text-muted fs-11">Powered Down</small>
                            </div>
                        </a>
                    </div>
                </div>

                {{-- Andon Exceptions & Live Alerts --}}
                @if(!empty($attentionMachines) && $attentionMachines->isNotEmpty())
                    <div class="alert alert-danger border-0 bg-soft-danger p-3 rounded-3 mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fw-bold text-danger mb-0 d-flex align-items-center gap-1.5 fs-13">
                                <i class="feather-alert-triangle"></i>
                                Equipment Requiring Immediate Attention ({{ $attentionMachines->count() }})
                            </h6>
                            <a href="{{ route('production.intelligence.andon') }}" class="btn btn-sm btn-danger py-0.5 px-2 fs-11">
                                Open Full Andon Board
                            </a>
                        </div>
                        <div class="row g-2">
                            @foreach($attentionMachines as $m)
                                <div class="col-md-4 col-sm-6">
                                    <div class="p-2.5 bg-white rounded-3 border border-danger-subtle h-100 shadow-2xs">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <a href="{{ route('production.mes.machines.show', $m['machine_id']) }}" class="fw-bold text-dark font-monospace fs-12">
                                                {{ $m['code'] }} - {{ $m['name'] }}
                                            </a>
                                            <span class="badge {{ strtolower($m['current_state']) === 'breakdown' ? 'bg-danger' : 'bg-warning text-dark' }} fs-10">
                                                {{ $m['current_state'] }}
                                            </span>
                                        </div>
                                        <div class="fs-11 text-muted mt-1">
                                            <i class="feather-map-pin me-1"></i>{{ $m['work_center_name'] }}
                                            @if(!empty($m['operator_name']) && $m['operator_name'] !== '—')
                                                | <i class="feather-user me-1"></i>{{ $m['operator_name'] }}
                                            @endif
                                        </div>
                                        @if(!empty($m['current_state_reason']) && $m['current_state_reason'] !== '—')
                                            <div class="fs-11 text-danger mt-1 fw-semibold">
                                                <i class="feather-info me-1"></i>{{ $m['current_state_reason'] }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Live MES / Shop-Floor Pulse Strip --}}
                <div class="p-3 bg-light rounded-3 border d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="dash-card-icon-avatar bg-soft-info text-info rounded-3">
                            <i class="feather-activity"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-0 fs-13">Shop Floor MES Execution Pulse</h6>
                            <div class="d-flex flex-wrap gap-2 mt-1 fs-12">
                                <span><strong class="text-success font-monospace">{{ number_format($mesRunningCount ?? 0) }}</strong> Running Ops</span>
                                <span class="text-muted">&bull;</span>
                                <span><strong class="text-primary font-monospace">{{ number_format($mesReadyCount ?? 0) }}</strong> Ready in Queue</span>
                                <span class="text-muted">&bull;</span>
                                <span><strong class="text-warning font-monospace">{{ number_format($mesPausedCount ?? 0) }}</strong> Paused</span>
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
        <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="dash-card-icon-avatar bg-soft-warning text-warning">
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
            <div class="card-body p-4">
                {{-- Bottleneck Spotlight (If any center is >= 85% or >100%) --}}
                @if(!empty($bottleneckWorkCenters) && $bottleneckWorkCenters->isNotEmpty())
                    <div class="alert alert-warning border border-warning bg-soft-warning p-3 rounded-3 mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fw-bold text-warning-emphasis mb-0 d-flex align-items-center gap-1.5 fs-13">
                                <i class="feather-alert-octagon"></i>
                                Bottleneck Spotlight: {{ $bottleneckWorkCenters->count() }} Work Center(s) Near or Above Target Capacity
                            </h6>
                            <small class="text-muted fs-11">Thresholds: Warning &ge;85%, Critical &gt;100%</small>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($bottleneckWorkCenters as $bwc)
                                <a href="{{ route('production.mes.work-centers.show', $bwc['id']) }}" class="badge {{ $bwc['status'] === 'Critical' ? 'bg-danger' : 'bg-warning text-dark' }} p-2 text-decoration-none fs-11 d-inline-flex align-items-center gap-1.5 rounded-pill">
                                    <i class="feather-alert-triangle"></i>
                                    {{ $bwc['name'] }} ({{ $bwc['code'] }}): {{ $bwc['utilization'] }}% Load ({{ $bwc['status'] }})
                                </a>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="alert alert-success border-0 bg-soft-success py-2.5 px-3 rounded-3 mb-3 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2 fs-12 text-success-emphasis">
                            <i class="feather-check-circle fs-15 text-success"></i>
                            <span><strong>Zero Bottlenecks Active!</strong> All work centers are operating within standard capacity limits (&lt;85% utilization).</span>
                        </div>
                        <span class="badge bg-success fs-10 font-monospace">Capacity Balanced</span>
                    </div>
                @endif

                {{-- Work Center Load Cards Grid --}}
                @if(!empty($workCenterLoads))
                    <div class="row g-3">
                        @foreach(array_slice($workCenterLoads, 0, 6) as $wcl)
                            <div class="col-xl-4 col-md-6">
                                <div class="p-3 border rounded-3 h-100 hover-shadow bg-white">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div>
                                            <a href="{{ route('production.mes.work-centers.show', $wcl['id']) }}" class="fw-bold text-dark text-decoration-none fs-13">
                                                {{ $wcl['name'] }}
                                            </a>
                                            <div class="fs-11 text-muted font-monospace">{{ $wcl['code'] }} &bull; {{ $wcl['machine_count'] }} Machine(s)</div>
                                        </div>
                                        <span class="badge {{ $wcl['status'] === 'Critical' ? 'bg-danger' : ($wcl['status'] === 'Warning' ? 'bg-warning text-dark' : 'bg-soft-success text-success') }} fs-11 font-monospace">
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
                                        <div class="progress-bar {{ $wcl['status'] === 'Critical' ? 'bg-danger' : ($wcl['status'] === 'Warning' ? 'bg-warning' : 'bg-success') }}"
                                             role="progressbar"
                                             style="width: {{ min(100, $wcl['utilization']) }}%"></div>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between fs-11 text-muted">
                                        <span>Utilization: <strong class="text-dark font-monospace">{{ $wcl['utilization'] }}%</strong></span>
                                        <span><i class="feather-play text-success me-1"></i>{{ $wcl['running_ops'] }} Running | {{ $wcl['waiting_ops'] }} Ready</span>
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
        <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="dash-card-icon-avatar bg-soft-success text-success">
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
            <div class="card-body p-4">
                <div class="row g-3 mb-3">
                    {{-- First Pass Yield (FPY) --}}
                    <div class="col-xl-3 col-sm-6">
                        <div class="quality-tile-box border border-success-subtle bg-soft-success h-100">
                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                <span class="fs-11 fw-bold text-success text-uppercase">First Pass Yield</span>
                                <span class="badge bg-success fs-10 rounded-pill">FPY</span>
                            </div>
                            <h2 class="fw-bold text-success-emphasis my-1 font-monospace fs-24">{{ number_format($qualityKpis['fpy'] ?? 100, 1) }}%</h2>
                            <small class="text-muted fs-11">Final Stage Acceptance Rate</small>
                        </div>
                    </div>

                    {{-- Inspections Gate Track --}}
                    <div class="col-xl-3 col-sm-6">
                        <div class="quality-tile-box border bg-light h-100">
                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                <span class="fs-11 fw-bold text-muted text-uppercase">Inspections Gate</span>
                                <span class="badge bg-secondary fs-10 font-monospace rounded-pill">{{ number_format($qualityKpis['totalInspections'] ?? 0) }} Total</span>
                            </div>
                            <div class="d-flex align-items-baseline gap-3 my-1">
                                <div>
                                    <span class="fs-18 fw-bold text-success font-monospace">{{ number_format($qualityKpis['passedInspections'] ?? 0) }}</span>
                                    <small class="text-muted d-block fs-10">Passed</small>
                                </div>
                                <div class="border-start ps-3">
                                    <span class="fs-18 fw-bold text-danger font-monospace">{{ number_format($qualityKpis['failedInspections'] ?? 0) }}</span>
                                    <small class="text-muted d-block fs-10">Failed</small>
                                </div>
                                <div class="border-start ps-3">
                                    <span class="fs-18 fw-bold text-warning font-monospace">{{ number_format($qualityKpis['pendingInspections'] ?? 0) }}</span>
                                    <small class="text-muted d-block fs-10">Pending</small>
                                </div>
                            </div>
                            <small class="text-muted fs-11">Receiving, in-line &amp; final audits</small>
                        </div>
                    </div>

                    {{-- Active NCRs & CAPAs --}}
                    <div class="col-xl-3 col-sm-6">
                        <div class="quality-tile-box border {{ ($qualityKpis['ncrOpen'] ?? 0) > 0 ? 'border-danger-subtle bg-soft-danger' : 'bg-light' }} h-100">
                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                <span class="fs-11 fw-bold {{ ($qualityKpis['ncrOpen'] ?? 0) > 0 ? 'text-danger' : 'text-muted' }} text-uppercase">Non-Conformances</span>
                                @if(($qualityKpis['ncrOpen'] ?? 0) > 0)
                                    <span class="badge bg-danger fs-10 rounded-pill">Action Required</span>
                                @else
                                    <span class="badge bg-soft-success text-success fs-10 rounded-pill">Zero Open</span>
                                @endif
                            </div>
                            <div class="d-flex align-items-baseline gap-3 my-1">
                                <div>
                                    <span class="fs-18 fw-bold {{ ($qualityKpis['ncrOpen'] ?? 0) > 0 ? 'text-danger' : 'text-dark' }} font-monospace">{{ number_format($qualityKpis['ncrOpen'] ?? 0) }}</span>
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
                        <div class="quality-tile-box border bg-light h-100">
                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                <span class="fs-11 fw-bold text-muted text-uppercase">Dispositions</span>
                                <span class="badge bg-light text-dark border fs-10 rounded-pill">Rework &amp; Scrap</span>
                            </div>
                            <div class="d-flex align-items-baseline gap-3 my-1">
                                <div>
                                    <span class="fs-18 fw-bold text-warning-emphasis font-monospace">{{ number_format($qualityKpis['reworkCount'] ?? 0) }}</span>
                                    <small class="text-muted d-block fs-10">Rework Orders</small>
                                </div>
                                <div class="border-start ps-3">
                                    <span class="fs-18 fw-bold text-secondary font-monospace">{{ number_format($qualityKpis['scrapCount'] ?? 0) }}</span>
                                    <small class="text-muted d-block fs-10">Scrap Disposals</small>
                                </div>
                            </div>
                            <small class="text-muted fs-11">Material resolution dispositions</small>
                        </div>
                    </div>
                </div>

                {{-- Top Defect Reasons Bar --}}
                <div class="p-3 bg-light rounded-3 border d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="fs-11 fw-bold text-muted text-uppercase d-inline-flex align-items-center gap-1">
                            <i class="feather-alert-octagon text-danger"></i> Top Defect Categories:
                        </span>
                        @if(!empty($topDefectCategories) && $topDefectCategories->isNotEmpty())
                            <div class="d-flex flex-wrap gap-1.5">
                                @foreach($topDefectCategories as $cat)
                                    <span class="badge bg-white text-dark border shadow-xs fs-11 rounded-pill px-2.5 py-1">
                                        {{ $cat->category }}: <strong class="text-danger font-monospace">{{ $cat->count }}</strong>
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <span class="badge bg-soft-success text-success fs-11 rounded-pill px-2.5 py-1">Zero Quality Non-Conformances active</span>
                        @endif
                    </div>
                    <div class="fs-11 text-muted">
                        Quality clearance guard active on all warehouse receipts
                    </div>
                </div>
            </div>
        </div>

        {{-- ── 7. Six Big Losses & Cycle Time Intelligence (Phase 3B) ─────────── --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
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
            <div class="card-body p-4">
                {{-- Six Big Losses Grid --}}
                <div class="mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-2.5">
                        <span class="fs-11 fw-bold text-uppercase text-muted">TPM Six Big Losses Classification</span>
                        <small class="text-muted fs-11">Overall Downtime Rate: <strong class="text-dark font-monospace">{{ number_format($downtimeRate, 1) }}%</strong></small>
                    </div>
                    <div class="row g-2">
                        {{-- 1. Equipment Failure (Availability) --}}
                        <div class="col-md-2 col-sm-4 col-6">
                            <div class="loss-tile-box border border-danger-subtle bg-soft-danger">
                                <span class="fs-11 text-danger text-uppercase fw-bold d-block mb-1">Equipment Failure</span>
                                <h4 class="fw-bold text-danger my-1 font-monospace fs-18">{{ number_format($sixBigLosses['equipment_failure_minutes'] ?? 0, 1) }}<small class="fs-11 fw-normal">m</small></h4>
                                <small class="text-muted fs-11">Breakdowns</small>
                            </div>
                        </div>
                        {{-- 2. Setup & Adjustment (Availability) --}}
                        <div class="col-md-2 col-sm-4 col-6">
                            <div class="loss-tile-box border border-warning-subtle bg-soft-warning">
                                <span class="fs-11 text-warning-emphasis text-uppercase fw-bold d-block mb-1">Setup &amp; Adjust</span>
                                <h4 class="fw-bold text-warning-emphasis my-1 font-monospace fs-18">{{ number_format($sixBigLosses['setup_adjustment_minutes'] ?? 0, 1) }}<small class="fs-11 fw-normal">m</small></h4>
                                <small class="text-muted fs-11">Tooling/Change</small>
                            </div>
                        </div>
                        {{-- 3. Minor Stops (Performance) --}}
                        <div class="col-md-2 col-sm-4 col-6">
                            <div class="loss-tile-box border bg-light">
                                <span class="fs-11 text-secondary text-uppercase fw-bold d-block mb-1">Minor Stops</span>
                                <h4 class="fw-bold text-dark my-1 font-monospace fs-18">{{ number_format($sixBigLosses['minor_stops_minutes'] ?? 0, 1) }}<small class="fs-11 fw-normal">m</small></h4>
                                <small class="text-muted fs-11">Idling &lt; 5m</small>
                            </div>
                        </div>
                        {{-- 4. Reduced Speed (Performance) --}}
                        <div class="col-md-2 col-sm-4 col-6">
                            <div class="loss-tile-box border bg-light">
                                <span class="fs-11 text-secondary text-uppercase fw-bold d-block mb-1">Reduced Speed</span>
                                <h4 class="fw-bold text-dark my-1 font-monospace fs-18">{{ number_format($sixBigLosses['reduced_speed_minutes'] ?? 0, 1) }}<small class="fs-11 fw-normal">m</small></h4>
                                <small class="text-muted fs-11">Slow Pace</small>
                            </div>
                        </div>
                        {{-- 5. Startup Rejects (Quality) --}}
                        <div class="col-md-2 col-sm-4 col-6">
                            <div class="loss-tile-box border bg-light">
                                <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">Startup Rejects</span>
                                <h4 class="fw-bold text-dark my-1 font-monospace fs-18">{{ number_format($sixBigLosses['startup_rejects_count'] ?? 0) }}</h4>
                                <small class="text-muted fs-11">First-Run</small>
                            </div>
                        </div>
                        {{-- 6. Production Rejects (Quality) --}}
                        <div class="col-md-2 col-sm-4 col-6">
                            <div class="loss-tile-box border bg-light">
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
                        <div class="p-4 rounded-3 bg-light border h-100 d-flex flex-column justify-content-between" style="min-height: 135px;">
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
                                    <strong class="font-monospace text-warning-emphasis fs-14">{{ number_format($cycleTimes['avg_waiting_time'] ?? 0, 1) }}m</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-4 rounded-3 bg-light border h-100 d-flex flex-column justify-content-between" style="min-height: 135px;">
                            <span class="fs-11 fw-bold text-uppercase text-muted d-block mb-3">
                                <i class="feather-cpu me-1 text-success"></i>Resource Utilizations
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
        <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="dash-card-icon-avatar bg-soft-warning text-warning">
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
            <div class="card-body p-4">
                {{-- Plant Maintenance Top Strip --}}
                <div class="row g-3 mb-3">
                    <div class="col-md-3 col-sm-6">
                        <a href="{{ route('production.maintenance.schedules.index') }}" class="text-decoration-none">
                            <div class="maintenance-tile-box border {{ $overduePmCount > 0 ? 'border-danger-subtle bg-soft-danger' : 'bg-light' }} hover-shadow">
                                <span class="fs-11 {{ $overduePmCount > 0 ? 'text-danger' : 'text-muted' }} text-uppercase fw-bold d-block mb-1">Overdue PM</span>
                                <h3 class="fw-bold {{ $overduePmCount > 0 ? 'text-danger' : 'text-dark' }} my-1 font-monospace fs-22">{{ number_format($overduePmCount) }}</h3>
                                <small class="text-muted fs-11">Preventive Due</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="{{ route('production.maintenance.schedules.index') }}" class="text-decoration-none">
                            <div class="maintenance-tile-box border bg-light hover-shadow">
                                <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1">PM Due (7 Days)</span>
                                <h3 class="fw-bold text-dark my-1 font-monospace fs-22">{{ number_format($duePmCount) }}</h3>
                                <small class="text-muted fs-11">Upcoming Service</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="{{ route('production.maintenance.work-orders.index', ['type' => 'breakdown']) }}" class="text-decoration-none">
                            <div class="maintenance-tile-box border {{ $openBreakdownWosCount > 0 ? 'border-danger-subtle bg-soft-danger' : 'bg-light' }} hover-shadow">
                                <span class="fs-11 {{ $openBreakdownWosCount > 0 ? 'text-danger' : 'text-muted' }} text-uppercase fw-bold d-block mb-1">Breakdown WOs</span>
                                <h3 class="fw-bold {{ $openBreakdownWosCount > 0 ? 'text-danger' : 'text-dark' }} my-1 font-monospace fs-22">{{ number_format($openBreakdownWosCount) }}</h3>
                                <small class="text-muted fs-11">Stoppage Orders</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="{{ route('production.mes.machines.index', ['status' => 'under_maintenance']) }}" class="text-decoration-none">
                            <div class="maintenance-tile-box border bg-light hover-shadow">
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
                                <span class="badge {{ ($subcontractDelivery['on_time_delivery_pct'] ?? 100) >= 90 ? 'bg-soft-success text-success' : 'bg-soft-warning text-warning-emphasis' }} fs-11 font-monospace rounded-pill px-2.5 py-1">
                                    OTD: {{ number_format($subcontractDelivery['on_time_delivery_pct'] ?? 100, 1) }}%
                                </span>
                                @if(($subcontractDelivery['avg_late_delay_days'] ?? 0) > 0)
                                    <span class="badge bg-soft-danger text-danger fs-11 font-monospace rounded-pill px-2.5 py-1">
                                        Avg Delay: +{{ number_format($subcontractDelivery['avg_late_delay_days'], 1) }}d
                                    </span>
                                @endif
                            </div>
                            <span class="fs-11 text-muted">Hybrid &amp; Subcontracting Job Work Flow</span>
                        </div>
                        <div class="row g-2 text-center">
                            <div class="col">
                                <a href="{{ route('production.orders.index', ['filter' => 'subcontract_awaiting_pr']) }}" class="text-decoration-none">
                                    <div class="subcontract-pipeline-box bg-light border hover-shadow">
                                        <span class="fs-10 text-muted text-uppercase fw-bold d-block mb-0.5">Awaiting PR</span>
                                        <h5 class="text-dark fw-bold mb-0 my-1 font-monospace fs-16">{{ number_format($subcontractMetrics['awaiting_subcontract_pr'] ?? 0) }}</h5>
                                    </div>
                                </a>
                            </div>
                            <div class="col">
                                <a href="{{ route('purchase.orders.index', ['type' => 'subcontract', 'status' => 'draft']) }}" class="text-decoration-none">
                                    <div class="subcontract-pipeline-box bg-soft-warning border border-warning-subtle hover-shadow">
                                        <span class="fs-10 text-warning text-uppercase fw-bold d-block mb-0.5">PO Awaiting</span>
                                        <h5 class="text-warning-emphasis fw-bold mb-0 my-1 font-monospace fs-16">{{ number_format($subcontractMetrics['po_awaiting_approval'] ?? 0) }}</h5>
                                    </div>
                                </a>
                            </div>
                            <div class="col">
                                <a href="{{ route('production.orders.index', ['filter' => 'subcontract_ready_dispatch']) }}" class="text-decoration-none">
                                    <div class="subcontract-pipeline-box bg-soft-info border border-info-subtle hover-shadow">
                                        <span class="fs-10 text-info text-uppercase fw-bold d-block mb-0.5">Ready Dispatch</span>
                                        <h5 class="text-info-emphasis fw-bold mb-0 my-1 font-monospace fs-16">{{ number_format($subcontractMetrics['ready_for_dispatch'] ?? 0) }}</h5>
                                    </div>
                                </a>
                            </div>
                            <div class="col">
                                <a href="{{ route('production.orders.index', ['filter' => 'subcontract_at_vendor']) }}" class="text-decoration-none">
                                    <div class="subcontract-pipeline-box bg-soft-primary border border-primary-subtle hover-shadow">
                                        <span class="fs-10 text-primary text-uppercase fw-bold d-block mb-0.5">At Vendor</span>
                                        <h5 class="text-primary fw-bold mb-0 my-1 font-monospace fs-16">{{ number_format($subcontractMetrics['at_vendor'] ?? 0) }}</h5>
                                    </div>
                                </a>
                            </div>
                            <div class="col">
                                <a href="{{ route('production.orders.index', ['filter' => 'subcontract_delayed']) }}" class="text-decoration-none">
                                    <div class="subcontract-pipeline-box bg-soft-danger border border-danger-subtle hover-shadow">
                                        <span class="fs-10 text-danger text-uppercase fw-bold d-block mb-0.5">Delayed</span>
                                        <h5 class="text-danger fw-bold mb-0 my-1 font-monospace fs-16">{{ number_format($subcontractMetrics['vendor_delayed'] ?? 0) }}</h5>
                                    </div>
                                </a>
                            </div>
                            <div class="col">
                                <a href="{{ route('production.inspections.index', ['type' => 'subcontract']) }}" class="text-decoration-none">
                                    <div class="subcontract-pipeline-box bg-soft-info border border-info-subtle hover-shadow">
                                        <span class="fs-10 text-info text-uppercase fw-bold d-block mb-0.5">QC Pending</span>
                                        <h5 class="text-info fw-bold mb-0 my-1 font-monospace fs-16">{{ number_format($subcontractMetrics['subcontract_qc_pending'] ?? 0) }}</h5>
                                    </div>
                                </a>
                            </div>
                            <div class="col">
                                <a href="{{ route('production.rework.index') }}" class="text-decoration-none">
                                    <div class="subcontract-pipeline-box bg-soft-secondary border border-secondary-subtle hover-shadow">
                                        <span class="fs-10 text-secondary text-uppercase fw-bold d-block mb-0.5">Vendor Rework</span>
                                        <h5 class="text-secondary fw-bold mb-0 my-1 font-monospace fs-16">{{ number_format($subcontractMetrics['vendor_rework'] ?? 0) }}</h5>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ── 9. Execution Variance, Order Risk & Planning Pulse (Phase 4) ──────── --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
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
            <div class="card-body p-4">
                <div class="row g-3">
                    {{-- Left Card: Operational Execution Variance --}}
                    <div class="col-xl-4 col-md-6 col-12">
                        <div class="p-4 rounded-3 border bg-light h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <span class="fs-11 fw-bold text-uppercase text-muted d-inline-flex align-items-center gap-1">
                                        <i class="feather-percent text-primary"></i> Execution Variance ({{ ucfirst($timeframe) }})
                                    </span>
                                    <span class="badge {{ ($productionSummary['schedule_adherence'] ?? 100) >= 90 ? 'bg-soft-success text-success' : 'bg-soft-warning text-warning' }} fs-10 font-monospace rounded-pill px-2.5 py-1">
                                        {{ number_format($productionSummary['schedule_adherence'] ?? 100, 1) }}% Adherence
                                    </span>
                                </div>

                                {{-- Output Metrics --}}
                                <div class="p-3 rounded-3 bg-white border mb-3">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="fs-11 text-muted">Output Units (Planned vs Actual)</span>
                                        <span class="fs-11 fw-bold font-monospace {{ $outputVariance >= 0 ? 'text-success' : 'text-danger' }}">
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
                                        <div class="progress-bar {{ $outputVariance >= 0 ? 'bg-success' : 'bg-warning' }}" style="width: {{ $outPct }}%"></div>
                                    </div>
                                </div>

                                {{-- Duration Metrics --}}
                                <div class="p-3 rounded-3 bg-white border mb-3">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="fs-11 text-muted">Operation Hours (Actual vs Plan)</span>
                                        <span class="fs-11 fw-bold font-monospace {{ $durationVarianceHours <= 0 ? 'text-success' : 'text-danger' }}">
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
                                        <div class="progress-bar {{ $durationVarianceHours <= 0 ? 'bg-success' : 'bg-danger' }}" style="width: {{ $durPct }}%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="pt-3 mt-3 border-top text-end">
                                <a href="{{ route('production.variances.index') }}" class="fs-11 text-primary text-decoration-none fw-semibold">
                                    Full Variance Analysis <i class="feather-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Middle Card: Near-Term Order Completion Risk --}}
                    <div class="col-xl-5 col-md-6 col-12">
                        <div class="p-4 rounded-3 border bg-light h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fs-11 fw-bold text-uppercase text-muted d-inline-flex align-items-center gap-1">
                                            <i class="feather-clock text-danger"></i> Near-Term Completion Risk (&lt;72h)
                                        </span>
                                        <span class="badge {{ $atRiskOrders->count() > 0 ? 'bg-danger' : 'bg-soft-success text-success' }} fs-10 font-monospace rounded-pill px-2 py-0.5">
                                            {{ $atRiskOrders->count() }} At Risk
                                        </span>
                                    </div>
                                    @if($qualityConstrainedOrdersCount > 0)
                                        <span class="badge bg-soft-warning text-warning fs-10 font-monospace rounded-pill px-2 py-0.5" title="Active orders with open NCR">
                                            <i class="feather-shield me-1"></i>{{ $qualityConstrainedOrdersCount }} Quality Hold
                                        </span>
                                    @endif
                                </div>

                                @if($atRiskOrders->count() > 0)
                                    <div class="table-responsive bg-white rounded-3 border p-2 mb-3">
                                        <table class="table table-sm table-borderless table-hover align-middle mb-0 fs-12">
                                            <thead class="text-muted border-bottom fs-10 text-uppercase">
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
                                                        $countdownBadge = $hoursLeft <= 24 ? 'bg-danger' : ($hoursLeft <= 48 ? 'bg-warning text-dark' : 'bg-info');
                                                        $countdownLabel = $hoursLeft <= 24 ? 'Today' : ($hoursLeft <= 48 ? '1 Day' : '2 Days');
                                                        $activeOp = $riskOrder->operations->first();
                                                    @endphp
                                                    <tr class="border-bottom border-light">
                                                        <td class="py-1.5">
                                                            <a href="{{ route('production.orders.show', $riskOrder->id) }}" class="fw-bold text-dark text-decoration-none d-block font-monospace">
                                                                {{ $riskOrder->order_number }}
                                                            </a>
                                                            <span class="text-muted fs-11 text-truncate d-inline-block" style="max-width: 140px;" title="{{ $riskOrder->product?->name }}">
                                                                {{ $riskOrder->product?->name ?? 'N/A' }}
                                                            </span>
                                                        </td>
                                                        <td class="text-center py-1.5">
                                                            <span class="badge {{ $countdownBadge }} fs-10 font-monospace rounded-pill d-inline-block px-2">
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
                                                                <div class="progress-bar bg-danger" style="width: {{ $progPct }}%"></div>
                                                            </div>
                                                        </td>
                                                        <td class="text-end py-1.5">
                                                            @if($activeOp)
                                                                <span class="badge bg-soft-primary text-primary fs-10 text-truncate font-monospace rounded-pill px-2" style="max-width: 100px;" title="{{ $activeOp->name }} @if($activeOp->workCenter)({{ $activeOp->workCenter->name }})@endif">
                                                                    {{ $activeOp->name }}
                                                                </span>
                                                            @else
                                                                <span class="badge bg-soft-secondary text-secondary fs-10 font-monospace rounded-pill px-2">In Queue</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="text-center py-5 px-3 text-muted bg-white rounded-3 border d-flex flex-column align-items-center justify-content-center" style="min-height: 190px;">
                                        <div class="rounded-circle bg-soft-success text-success d-inline-flex align-items-center justify-content-center mb-3" style="width: 44px; height: 44px;">
                                            <i class="feather-check-circle fs-22"></i>
                                        </div>
                                        <span class="fs-13 fw-semibold text-dark mb-1">No Near-Term Completion Risk</span>
                                        <div class="fs-11 text-muted" style="max-width: 320px;">All active orders due within 72 hours have achieved &ge; 50% target progress.</div>
                                    </div>
                                @endif
                            </div>
                            <div class="pt-3 mt-3 border-top text-end">
                                <a href="{{ route('production.planning-exceptions.index') }}" class="fs-11 text-danger text-decoration-none fw-semibold">
                                    Full Exception Engine <i class="feather-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Right Card: Planning & ECO Pipeline Pulse --}}
                    <div class="col-xl-3 col-12">
                        <div class="p-4 rounded-3 border bg-light h-100 d-flex flex-column justify-content-between">
                            <div>
                                <span class="fs-11 fw-bold text-uppercase text-muted d-block mb-3">
                                    <i class="feather-layers me-1 text-info"></i>Planning &amp; ECO Pulse
                                </span>

                                {{-- Master Plans Strip --}}
                                <div class="p-3 rounded-3 bg-white border mb-3">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="fs-11 fw-bold text-dark"><i class="feather-calendar me-1 text-primary"></i>Master Plans</span>
                                        <a href="{{ route('production.plans.index') }}" class="fs-10 text-primary text-decoration-none font-monospace">View All</a>
                                    </div>
                                    <div class="d-flex flex-wrap gap-1 text-center">
                                        <div class="flex-fill p-1.5 rounded bg-light border">
                                            <small class="text-muted fs-10 d-block">Draft</small>
                                            <strong class="font-monospace text-dark fs-12">{{ $planStatusCounts['draft'] ?? 0 }}</strong>
                                        </div>
                                        <div class="flex-fill p-1.5 rounded bg-light border">
                                            <small class="text-muted fs-10 d-block">Pending</small>
                                            <strong class="font-monospace text-warning fs-12">{{ $planStatusCounts['pending_approval'] ?? 0 }}</strong>
                                        </div>
                                        <div class="flex-fill p-1.5 rounded bg-light border">
                                            <small class="text-muted fs-10 d-block">Approved</small>
                                            <strong class="font-monospace text-info fs-12">{{ $planStatusCounts['approved'] ?? 0 }}</strong>
                                        </div>
                                        <div class="flex-fill p-1.5 rounded bg-light border">
                                            <small class="text-muted fs-10 d-block">Released</small>
                                            <strong class="font-monospace text-success fs-12">{{ $planStatusCounts['released'] ?? 0 }}</strong>
                                        </div>
                                    </div>
                                </div>

                                {{-- ECO Pipeline Strip --}}
                                <div class="p-3 rounded-3 bg-white border mb-3">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="fs-11 fw-bold text-dark"><i class="feather-git-pull-request me-1 text-warning"></i>ECO Pipeline</span>
                                        <a href="{{ route('production.ecos.index') }}" class="fs-10 text-primary text-decoration-none font-monospace">View All</a>
                                    </div>
                                    <div class="d-flex flex-wrap gap-1 text-center">
                                        <div class="flex-fill p-1.5 rounded bg-light border">
                                            <small class="text-muted fs-10 d-block">Draft</small>
                                            <strong class="font-monospace text-dark fs-12">{{ $ecoStatusCounts['draft'] ?? 0 }}</strong>
                                        </div>
                                        <div class="flex-fill p-1.5 rounded bg-light border">
                                            <small class="text-muted fs-10 d-block">Review</small>
                                            <strong class="font-monospace text-warning fs-12">{{ $ecoStatusCounts['under_review'] ?? 0 }}</strong>
                                        </div>
                                        <div class="flex-fill p-1.5 rounded bg-light border">
                                            <small class="text-muted fs-10 d-block">Approved</small>
                                            <strong class="font-monospace text-info fs-12">{{ $ecoStatusCounts['approved'] ?? 0 }}</strong>
                                        </div>
                                        <div class="flex-fill p-1.5 rounded bg-light border">
                                            <small class="text-muted fs-10 d-block">Released</small>
                                            <strong class="font-monospace text-success fs-12">{{ $ecoStatusCounts['released'] ?? 0 }}</strong>
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
            <div class="ready-start-banner p-4 mb-4 shadow-sm d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-md bg-success text-white rounded-circle d-flex align-items-center justify-content-center fs-20 flex-shrink-0" style="width: 48px; height: 48px;">
                        <i class="feather-check-circle"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-success-emphasis mb-1 fs-14">
                            <i class="feather-box me-1"></i>
                            @if($fullyIssuedCount === 1)
                                Store Material Fully Issued for Order #{{ $firstFullyOrder->order_number }}!
                            @else
                                {{ $fullyIssuedCount }} Production Order(s) - Store Material Fully Issued!
                            @endif
                        </h6>
                        <span class="fs-12 text-dark">
                            Raw materials have been fully issued by warehouse store. Ready for scheduling and shop floor execution.
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <x-ui.button variant="success" icon="feather-arrow-right-circle" onclick="activateTab('content-ready-tab')">
                        View Ready Orders ({{ $fullyIssuedCount }})
                    </x-ui.button>
                </div>
            </div>
        @endif

        {{-- ── 11. Operational Worklists using Common Horizontal Tabs & Table ──── --}}
        <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white overflow-hidden">
            <div class="card-header bg-white pt-3 pb-0 px-4 border-bottom-0">
                <x-ui.horizontal-tabs id="operationalTabs" :tabs="$worklistTabs" />
            </div>

            <div class="card-body p-0">
                <div class="tab-content" id="operationalTabsContent">

                    {{-- ── TAB 1: Pending Demand ────────────────────────────────── --}}
                    <div class="tab-pane fade show active" id="content-demand" role="tabpanel" aria-labelledby="content-demand-tab">
                        <div class="px-4 py-3.5 bg-light border-bottom d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="fw-bold text-dark mb-1 fs-13">Pending Sales Orders to Manufacture</h6>
                                <small class="text-muted d-block">Sales Orders / Material Requisitions awaiting Production Order conversion.</small>
                            </div>
                            <span class="badge bg-primary fs-11 px-2.5 py-1 rounded-pill">
                                {{ $pendingSalesOrderCount }} Pending Demand(s)
                            </span>
                        </div>
                        <div class="table-responsive p-0">
                            <table class="table table-hover align-middle mb-0 fs-12">
                                <thead class="bg-light text-muted fs-11 text-uppercase">
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
                                                <x-ui.status-badge status="pending" text="Pending Production Order" />
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
                                                    <div class="rounded-circle bg-soft-success text-success d-inline-flex align-items-center justify-content-center mb-3" style="width: 52px; height: 52px;">
                                                        <i class="feather-check-circle fs-26"></i>
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
                                <thead class="bg-light text-muted fs-11 text-uppercase">
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
                                                    <span class="badge bg-soft-success text-success border border-success border-opacity-20 fs-11 px-2.5 py-1 rounded-pill">
                                                        <i class="feather-check-circle me-1"></i>Fully Issued — Ready
                                                    </span>
                                                @else
                                                    <span class="badge bg-soft-warning text-warning border border-warning border-opacity-20 fs-11 px-2.5 py-1 rounded-pill">
                                                        <i class="feather-alert-circle me-1"></i>Partially Issued
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if($activeSchedule)
                                                    <a href="{{ route('production.schedules.show', $activeSchedule->id) }}" class="badge bg-soft-info text-info border border-info border-opacity-20 font-monospace fs-11 px-2.5 py-1 rounded-pill">
                                                        <i class="feather-calendar me-1"></i>{{ $activeSchedule->schedule_number }}
                                                    </a>
                                                @else
                                                    <span class="badge bg-soft-secondary text-secondary fs-11 px-2.5 py-1 rounded-pill">Unscheduled</span>
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
                                                    <div class="rounded-circle bg-soft-secondary text-muted d-inline-flex align-items-center justify-content-center mb-3 opacity-75" style="width: 52px; height: 52px;">
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
                                <thead class="bg-light text-muted fs-11 text-uppercase">
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
                                                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $progressPercent }}%"></div>
                                                    </div>
                                                    <span class="fs-11 font-monospace fw-bold text-dark">{{ $progressPercent }}%</span>
                                                </div>
                                                @if($currentOp)
                                                    <div class="fs-11 text-muted mt-1 text-truncate" style="max-width: 180px;">
                                                        <i class="feather-sliders me-1"></i>{{ $currentOp->name }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-center font-monospace fs-12">
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
                                <thead class="bg-light text-muted fs-11 text-uppercase">
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
                                        <tr class="table-danger table-opacity-10">
                                            <td class="ps-4">
                                                <a href="{{ route('production.orders.show', $order->id) }}" class="fw-bold text-danger font-monospace">
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
                                            <td class="text-center font-monospace fs-12 text-danger fw-bold">
                                                <i class="feather-calendar me-1"></i>{{ $endDate->format('M d, Y') }}
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-danger fs-11 px-2.5 py-1 rounded-pill">
                                                    <i class="feather-alert-triangle me-1"></i>{{ $daysOverdue }} Day(s) Overdue
                                                </span>
                                            </td>
                                            <td class="text-center pe-4">
                                                <x-ui.button variant="danger" icon="feather-arrow-right" href="{{ route('production.orders.show', $order->id) }}">
                                                    Expedite
                                                </x-ui.button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-5">
                                                <div class="py-4 my-2 d-flex flex-column align-items-center justify-content-center">
                                                    <div class="rounded-circle bg-soft-success text-success d-inline-flex align-items-center justify-content-center mb-3" style="width: 52px; height: 52px;">
                                                        <i class="feather-check-circle fs-26"></i>
                                                    </div>
                                                    <h6 class="fw-semibold text-success mb-1 fs-14">Zero Overdue Orders! All production schedules are within target delivery dates.</h6>
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
