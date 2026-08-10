@extends('layouts.duralux')

@section('title', 'Production Dashboard')

@push('styles')
    <style>
        .kpi-card {
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            background: #ffffff;
        }

        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
        }

        .kpi-icon-wrapper {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .ready-start-banner {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            border-left: 5px solid #2e7d32;
            border-radius: 10px;
        }

        .table-custom-hover tbody tr:hover {
            background-color: rgba(var(--bs-primary-rgb), 0.02);
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid py-3">

        {{-- ── Header & Quick Actions Bar ───────────────────────────────────────── --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h4 class="fw-bold text-dark mb-1">
                    <i class="feather-grid text-primary me-2"></i>Production Dashboard
                </h4>
                <p class="text-muted fs-13 mb-0">
                    Real-time operational readiness, sales order demand backlog, store material tracking & shop floor progress.
                </p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="{{ route('production.orders.create') }}" class="btn btn-primary d-inline-flex align-items-center gap-1.5 shadow-sm">
                    <i class="feather-plus-circle"></i> Create Production Order
                </a>
                <a href="{{ route('production.schedules.index') }}" class="btn btn-soft-primary d-inline-flex align-items-center gap-1.5">
                    <i class="feather-calendar"></i> Plan & Schedule
                </a>
                <a href="{{ route('production.mes.dashboard') }}" class="btn btn-soft-info d-inline-flex align-items-center gap-1.5">
                    <i class="feather-monitor"></i> Shop Floor (MES)
                </a>
                <a href="{{ route('sales.material-requests.index') }}" class="btn btn-soft-warning d-inline-flex align-items-center gap-1.5">
                    <i class="feather-package"></i> Store Requisitions
                </a>
                <a href="{{ route('production.wip.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1.5">
                    <i class="feather-layers"></i> WIP Tracking
                </a>
            </div>
        </div>

        {{-- ── 1. Top KPI Summary Cards Row ────────────────────────────────────── --}}
        <div class="row g-3 mb-4">
            {{-- KPI 1: Production Orders Overview --}}
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card p-3 h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fs-12 text-muted fw-semibold text-uppercase">Production Orders</span>
                        <div class="kpi-icon-wrapper bg-soft-primary text-primary">
                            <i class="feather-play-circle"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2 mb-2">
                        <h2 class="fw-bold text-dark mb-0 font-monospace">{{ number_format($orderStatusCounts['total']) }}</h2>
                        <span class="fs-12 text-muted">Total Active Orders</span>
                    </div>
                    <div class="d-flex flex-wrap gap-1 fs-11">
                        <span class="badge bg-soft-warning text-warning">Draft: {{ $orderStatusCounts['draft'] }}</span>
                        <span class="badge bg-soft-primary text-primary">Released: {{ $orderStatusCounts['released'] }}</span>
                        <span class="badge bg-soft-info text-info">In-Progress: {{ $orderStatusCounts['in_progress'] }}</span>
                        <span class="badge bg-soft-success text-success">Completed: {{ $orderStatusCounts['completed'] }}</span>
                    </div>
                </div>
            </div>

            {{-- KPI 2: Ready to Start (Material Issued) --}}
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card p-3 h-100 border-success border-opacity-25">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fs-12 text-success fw-bold text-uppercase"><i class="feather-check-circle me-1"></i>Ready To Start</span>
                        <div class="kpi-icon-wrapper bg-soft-success text-success">
                            <i class="feather-box"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2 mb-2">
                        <h2 class="fw-bold text-success mb-0 font-monospace">{{ number_format($readyToStartCount) }}</h2>
                        <span class="fs-12 text-muted">Material Issued Orders</span>
                    </div>
                    <p class="fs-11 text-muted mb-0">
                        Orders with store material fully or partially issued. Planners & operators can start execution immediately.
                    </p>
                </div>
            </div>

            {{-- KPI 3: Store Material Requisitions --}}
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card p-3 h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fs-12 text-muted fw-semibold text-uppercase">Store Material Requisitions</span>
                        <div class="kpi-icon-wrapper bg-soft-warning text-warning">
                            <i class="feather-shopping-bag"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2 mb-2">
                        <h2 class="fw-bold text-dark mb-0 font-monospace">{{ number_format($requisitionSummary['total']) }}</h2>
                        <span class="fs-12 text-muted">Total Slips Raised</span>
                    </div>
                    <div class="d-flex flex-wrap gap-1 fs-11">
                        <span class="badge bg-soft-success text-success">Fully Issued: {{ $requisitionSummary['fully_issued'] }}</span>
                        <span class="badge bg-soft-warning text-warning">Partially: {{ $requisitionSummary['partially_issued'] }}</span>
                        <span class="badge bg-soft-danger text-danger">Pending: {{ $requisitionSummary['pending'] }}</span>
                    </div>
                </div>
            </div>

            {{-- KPI 4: Shop Floor & Quality Exceptions --}}
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card p-3 h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fs-12 text-muted fw-semibold text-uppercase">Shop Floor & Quality Alerts</span>
                        <div class="kpi-icon-wrapper bg-soft-info text-info">
                            <i class="feather-users"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2 mb-2">
                        <h2 class="fw-bold text-dark mb-0 font-monospace">{{ number_format($operatorAssignedCount) }}</h2>
                        <span class="fs-12 text-muted">Assigned Operator Tasks</span>
                    </div>
                    <div class="d-flex flex-wrap gap-1 fs-11">
                        <span class="badge bg-soft-warning text-warning">Pending Rework: {{ $pendingReworkCount }}</span>
                        <span class="badge bg-soft-danger text-danger">Scrap Logged: {{ $scrapLoggedCount }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── 2. Ready To Start Production Alert Banner ───────────────────────── --}}
        @if($readyToStartCount > 0)
            <div class="ready-start-banner p-3 mb-4 shadow-sm d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-md bg-success text-white rounded-circle d-flex align-items-center justify-content-center fs-20">
                        <i class="feather-check-circle"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-success-emphasis mb-1">
                            <i class="feather-zap me-1"></i>{{ $readyToStartCount }} Production Order(s) Ready to Execute!
                        </h6>
                        <span class="fs-13 text-dark">
                            Store material has been issued for these orders. Planners can schedule work centers or operators can begin execution on MES.
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('production.mes.dashboard') }}" class="btn btn-sm btn-success px-3 fw-bold">
                        <i class="feather-play me-1"></i>Go to Shop Floor (MES)
                    </a>
                </div>
            </div>
        @endif

        <div class="row g-4">

            {{-- ── 3. Left Column: Pending Sales Orders to Manufacture ──────────── --}}
            <div class="col-lg-12">
                <div class="card border shadow-sm">
                    <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-xs bg-soft-primary text-primary rounded d-flex align-items-center justify-content-center">
                                <i class="feather-shopping-cart fs-14"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Pending Sales Orders to Manufacture</h6>
                                <small class="text-muted">Sales Orders / Requisitions requiring Production Order creation</small>
                            </div>
                        </div>
                        <span class="badge bg-primary fs-12 px-2.5 py-1">
                            {{ number_format($pendingSalesOrderCount) }} Pending Demand(s)
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-custom-hover align-middle mb-0">
                                <thead class="table-light fs-12 text-uppercase">
                                    <tr>
                                        <th>Sales Order / Request #</th>
                                        <th>Customer</th>
                                        <th>Target Product</th>
                                        <th class="text-end">Requested Qty</th>
                                        <th>Status</th>
                                        <th class="text-center" style="width: 180px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-13">
                                    @forelse($pendingRequests as $req)
                                        @php
                                            $deliveryItem = $req->materialRequirementItem;
                                            $delivery = $deliveryItem?->materialRequirement;
                                            $sales = $delivery?->salesOrder ?? $deliveryItem?->salesOrderItem?->salesOrder;
                                            $customer = $sales?->customer;
                                            $product = $req->product;
                                        @endphp
                                        <tr>
                                            <td>
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
                                                <span class="badge bg-soft-warning text-warning border border-warning fs-11">
                                                    Pending Production Order
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('production.orders.create', ['production_order_request_id' => $req->id, 'sales_order_id' => $sales?->id]) }}"
                                                   class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1 shadow-sm px-2.5 py-1 fs-12">
                                                    <i class="feather-plus-circle"></i> Create Order
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                <i class="feather-check-circle me-1 text-success fs-16"></i>
                                                All sales order demands have active Production Orders created.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── 4. Live Production Orders & Material Readiness ───────────────── --}}
            <div class="col-lg-12">
                <div class="card border shadow-sm">
                    <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-xs bg-soft-success text-success rounded d-flex align-items-center justify-content-center">
                                <i class="feather-activity fs-14"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Production Orders Overview & Store Material Readiness</h6>
                                <small class="text-muted">Track order status, store issue readiness, schedule links & output progress</small>
                            </div>
                        </div>
                        <a href="{{ route('production.orders.index') }}" class="btn btn-sm btn-outline-primary">
                            View All Production Orders <i class="feather-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-custom-hover align-middle mb-0">
                                <thead class="table-light fs-12 text-uppercase">
                                    <tr>
                                        <th>Order #</th>
                                        <th>Finished Product</th>
                                        <th class="text-end">Qty Ordered</th>
                                        <th class="text-center">Order Status</th>
                                        <th class="text-center">Store Material Status</th>
                                        <th class="text-center">Scheduling Status</th>
                                        <th class="text-center" style="width: 140px;">Progress</th>
                                        <th class="text-center" style="width: 150px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-13">
                                    @forelse($recentOrders as $order)
                                        @php
                                            $latestSlip = $order->requisitionSlips->last();
                                            $slipStatusLower = strtolower($latestSlip?->status ?? '');

                                            $materialBadge = match (true) {
                                                in_array($slipStatusLower, ['fully issued', 'completed', 'issued']) => ['variant' => 'success', 'label' => 'Material Issued — Ready', 'icon' => 'feather-check-circle'],
                                                in_array($slipStatusLower, ['partially issued', 'partial']) => ['variant' => 'warning', 'label' => 'Partially Issued — Partial Ready', 'icon' => 'feather-alert-circle'],
                                                $slipStatusLower === 'approved' || $slipStatusLower === 'reserved' => ['variant' => 'primary', 'label' => 'Store Preparing Material', 'icon' => 'feather-clock'],
                                                $slipStatusLower === 'pending' || $slipStatusLower === 'pending store release' => ['variant' => 'danger', 'label' => 'Pending Store Release', 'icon' => 'feather-loader'],
                                                default => ['variant' => 'secondary', 'label' => 'No Requisition Raised', 'icon' => 'feather-file-text'],
                                            };

                                            $activeSchedule = $order->schedules->whereNotIn('status', ['cancelled'])->first();

                                            $totalOps = $order->operations->count();
                                            $completedOps = $order->operations->where('status', 'completed')->count();
                                            $progressPercent = $totalOps > 0 ? round(($completedOps / $totalOps) * 100) : 0;
                                        @endphp
                                        <tr>
                                            <td>
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
                                                @php
                                                    $orderBadgeVariant = match($order->status) {
                                                        'draft' => 'warning',
                                                        'released' => 'primary',
                                                        'in_progress' => 'info',
                                                        'completed' => 'success',
                                                        'closed' => 'secondary',
                                                        default => 'dark'
                                                    };
                                                @endphp
                                                <span class="badge bg-soft-{{ $orderBadgeVariant }} text-{{ $orderBadgeVariant }} fs-11 px-2.5 py-1 text-uppercase">
                                                    {{ str_replace('_', ' ', $order->status) }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-soft-{{ $materialBadge['variant'] }} text-{{ $materialBadge['variant'] }} border border-{{ $materialBadge['variant'] }} fs-11 px-2.5 py-1">
                                                    <i class="{{ $materialBadge['icon'] }} me-1"></i>{{ $materialBadge['label'] }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                @if($activeSchedule)
                                                    <a href="{{ route('production.schedules.show', $activeSchedule->id) }}" class="badge bg-soft-info text-info border border-info font-monospace fs-11 px-2.5 py-1">
                                                        <i class="feather-calendar me-1"></i>{{ $activeSchedule->schedule_number }}
                                                    </a>
                                                @else
                                                    <span class="badge bg-soft-secondary text-secondary fs-11 px-2 py-1">Unscheduled</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress flex-grow-1" style="height: 6px;">
                                                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $progressPercent }}%" aria-valuenow="{{ $progressPercent }}" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                    <span class="fs-11 font-monospace fw-bold text-dark">{{ $progressPercent }}%</span>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex align-items-center justify-content-center gap-1">
                                                    <a href="{{ route('production.orders.show', $order->id) }}" class="btn btn-sm btn-light border py-1 px-2" title="View Order Details">
                                                        <i class="feather-eye me-1"></i>View
                                                    </a>
                                                    @if($order->isReleased() || $order->isInProgress())
                                                        <a href="{{ route('production.orders.show', ['order' => $order->id, 'tab' => 'vtab-operations']) }}" class="btn btn-sm btn-soft-primary py-1 px-2" title="Routing Operations">
                                                            <i class="feather-sliders me-1"></i>Ops
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">
                                                No active Production Orders found.
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
@endsection
