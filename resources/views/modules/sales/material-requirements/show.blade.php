@extends('layouts.duralux')

@section('title', __('crm.material_requirements') . ' ' . $delivery->requirement_number . ' | SaaS ERP')
@section('page-title', __('crm.material_requirements') . ' ' . $delivery->requirement_number)
@section('breadcrumb', __('ui.inventory') . ' / ' . __('crm.material_requirements') . ' / ' . $delivery->requirement_number)

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button href="{{ route('inventory.material-requirements.index') }}" variant="light" class="border" icon="feather-arrow-left">
            {{ __('crm.back') }}
        </x-ui.button>
        <x-ui.button href="{{ route('sales.orders.show', $delivery->sales_order_id) }}" variant="light" class="border" icon="feather-external-link">
            {{ __('crm.so_details') }}
        </x-ui.button>
        @php
            $physicalItems = $delivery->items->filter(fn($i) => !($i->product && $i->product->isService()));
            $hasUnreleasedItems = $physicalItems->contains(function ($i) {
                $ord = (float) ($i->quantity_ordered > 0 ? $i->quantity_ordered : $i->quantity);
                return (float) ($i->quantity_released ?? 0) < $ord;
            });
            $totalReleasedQty = (float) $physicalItems->sum('quantity_released');
            $totalDispatchedQty = (float) $physicalItems->sum('dispatched_qty');
            $totalOrderedQty = (float) $physicalItems->sum(fn($i) => (float)($i->quantity_ordered > 0 ? $i->quantity_ordered : $i->quantity));

            // Remaining released units that are ready for dispatch and have not yet been dispatched
            $pendingReleasedToDispatch = (float) $physicalItems->sum(function ($i) {
                $rel = (float) ($i->quantity_released ?? 0);
                $disp = (float) ($i->dispatched_qty ?? 0);
                return max(0.0, $rel - $disp);
            });

            $isFullyFulfilled = $totalOrderedQty > 0 && $totalDispatchedQty >= $totalOrderedQty;
            $soNumber = $delivery->salesOrder?->sales_order_number ?? '';
            $dispatchesCount = \App\Domains\Sales\Models\DispatchOrder::where('tenant_id', $delivery->tenant_id)
                ->where(function ($q) use ($delivery) {
                    $q->where('material_requirement_id', $delivery->id);
                    if ($delivery->sales_order_id) {
                        $q->orWhere('sales_order_id', $delivery->sales_order_id);
                    }
                })
                ->where('status', '!=', 'Cancelled')
                ->count();
        @endphp

        {{-- 1. Store Incharge Release Action / Status --}}
        @if ($hasUnreleasedItems && !in_array($delivery->status, ['Delivered', 'Cancelled']))
            <button type="button" id="openReleaseModalBtn" class="btn btn-sm btn-success fw-bold px-3 shadow-2xs" data-bs-toggle="modal" data-bs-target="#releaseForDispatchModal">
                <i class="feather-send me-1.5"></i>{{ $totalReleasedQty > 0 ? 'Release Remaining Items' : 'Release for Dispatch' }}
            </button>
        @else
            <span class="badge bg-soft-success text-success px-2.5 py-1.5 fs-11 fw-bold border border-success-subtle d-inline-flex align-items-center">
                <i class="feather-check-circle me-1"></i>All Items Released
            </span>
        @endif

        {{-- 2. Dispatch Order Action / View Dispatches --}}
        @if (!in_array($delivery->status, ['Delivered', 'Cancelled']))
            @if ($pendingReleasedToDispatch > 0)
                <x-ui.button href="{{ route('sales.dispatches.create', ['material_requirement_id' => $delivery->id, 'sales_order_id' => $delivery->sales_order_id]) }}" variant="primary" class="fw-bold px-3 shadow-2xs" icon="feather-truck">
                    {{ __('crm.dispatch_order') }} ({{ (int)$pendingReleasedToDispatch }})
                </x-ui.button>
            @elseif ($dispatchesCount > 0)
                @if ($delivery->status === 'Delivered' || ((float)$delivery->total_delivered_qty >= $totalOrderedQty && $totalOrderedQty > 0))
                    <span class="badge bg-soft-success text-success px-2.5 py-1.5 fs-11 fw-bold border border-success-subtle d-inline-flex align-items-center me-1">
                        <i class="feather-check-circle me-1"></i>Delivered
                    </span>
                @elseif ($isFullyFulfilled)
                    <span class="badge bg-soft-primary text-primary px-2.5 py-1.5 fs-11 fw-bold border border-primary-subtle d-inline-flex align-items-center me-1">
                        <i class="feather-truck me-1"></i>Fully Dispatched
                    </span>
                @endif
                <x-ui.button href="{{ route('inventory.dispatches.index', ['search' => $soNumber ?: $delivery->requirement_number, 'tab' => 'all']) }}" variant="light" class="border fw-bold px-3" icon="feather-truck">
                    View Dispatches ({{ $dispatchesCount }})
                </x-ui.button>
            @endif
        @endif

        {{-- Fallback manual deliver ONLY if no formal Dispatch Orders exist --}}
        @if ($delivery->status === 'Dispatched' && $dispatchesCount === 0)
            <form action="{{ route('inventory.material-requirements.deliver', $delivery->id) }}" method="POST" class="d-inline">
                @csrf
                <x-ui.button type="submit" variant="success" class="fw-bold px-3" icon="feather-check-circle">
                    {{ __('crm.mark_delivered') }}
                </x-ui.button>
            </form>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        .mr-status-pipeline {
            display: inline-flex;
            align-items: center;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
            height: 32px;
        }
        .mr-status-pipeline .pipeline-step {
            position: relative;
            height: 32px;
            line-height: 32px;
            padding: 0 16px 0 24px;
            background-color: #f8fafc;
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        .mr-status-pipeline .pipeline-step:first-child {
            padding-left: 14px;
        }
        .mr-status-pipeline .pipeline-step::after {
            content: "";
            position: absolute;
            top: 0;
            right: -10px;
            width: 0;
            height: 0;
            border-top: 16px solid transparent;
            border-bottom: 16px solid transparent;
            border-left: 10px solid #f8fafc;
            z-index: 10;
            transition: all 0.2s ease;
        }
        .mr-status-pipeline .pipeline-step::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 0;
            height: 0;
            border-top: 16px solid transparent;
            border-bottom: 16px solid transparent;
            border-left: 10px solid #ffffff;
            z-index: 5;
        }
        .mr-status-pipeline .pipeline-step:first-child::before {
            display: none;
        }
        .mr-status-pipeline .pipeline-step.active {
            background-color: var(--bs-primary, #2563eb);
            color: #ffffff;
            z-index: 11;
        }
        .mr-status-pipeline .pipeline-step.active::after {
            border-left-color: var(--bs-primary, #2563eb);
        }
        .mr-status-pipeline .pipeline-step.completed {
            background-color: #e2e8f0;
            color: #334155;
            z-index: 6;
        }
        .mr-status-pipeline .pipeline-step.completed::after {
            border-left-color: #e2e8f0;
        }

        /* Metric KPI Box (Executive ERP Cards) */
        .mr-metric-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px 16px;
            transition: all 0.2s ease;
        }
        .mr-metric-box:hover {
            border-color: #cbd5e1;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        }

        /* Dark mode support */
        html.app-skin-dark .mr-metric-box {
            background: #0f172a;
            border-color: #1e293b;
        }
        html.app-skin-dark .mr-status-pipeline {
            background-color: #0f172a !important;
            border-color: #1e293b !important;
        }
        html.app-skin-dark .mr-status-pipeline .pipeline-step {
            background-color: #0f172a;
            color: #94a3b8;
        }
        html.app-skin-dark .mr-status-pipeline .pipeline-step::after {
            border-left-color: #0f172a;
        }
        html.app-skin-dark .mr-status-pipeline .pipeline-step::before {
            border-left-color: #1e293b;
        }
        html.app-skin-dark .mr-status-pipeline .pipeline-step.active {
            background-color: var(--bs-primary, #2563eb) !important;
            color: #ffffff !important;
        }
        html.app-skin-dark .mr-status-pipeline .pipeline-step.active::after {
            border-left-color: var(--bs-primary, #2563eb) !important;
        }
        html.app-skin-dark .mr-status-pipeline .pipeline-step.completed {
            background-color: #1e293b;
            color: #cbd5e1;
        }
        html.app-skin-dark .mr-status-pipeline .pipeline-step.completed::after {
            border-left-color: #1e293b;
        }

        .mr-line-table th {
            background-color: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: #475569;
        }
        .mr-line-table td {
            vertical-align: middle;
        }
    </style>
@endpush

@section('content')



    @php
        $doStatusClass = 'warning';
        $doStatusClass = 'warning';
        if (in_array($delivery->status, ['Released', 'Ready', 'Delivered']))   $doStatusClass = 'success';
        elseif (in_array($delivery->status, ['Partially Ready', 'Processing'])) $doStatusClass = 'info';
        elseif ($delivery->status === 'Pending' || $delivery->status === 'Draft') $doStatusClass = 'warning';
        elseif ($delivery->status === 'Dispatched')                             $doStatusClass = 'dark';
        elseif ($delivery->status === 'Cancelled')                              $doStatusClass = 'danger';
        elseif (in_array($delivery->status, ['Picked', 'Packed']))              $doStatusClass = 'primary';

        $statusBadgeClass = match($delivery->status) {
            'Delivered' => 'bg-soft-success text-success',
            'Partially Delivered' => 'bg-soft-info text-info',
            'Dispatched' => 'bg-soft-primary text-primary',
            'Partially Dispatched' => 'bg-soft-info text-info',
            'Released', 'Ready' => 'bg-soft-success text-success',
            'Partially Ready', 'Processing' => 'bg-soft-info text-info',
            'Pending', 'Draft' => 'bg-soft-warning text-warning',
            'Cancelled' => 'bg-soft-danger text-danger',
            'Picked', 'Packed' => 'bg-soft-primary text-primary',
            default => 'bg-soft-warning text-warning',
        };

        $totalOrdered = (float)$delivery->items->sum(fn($i) => (float)($i->quantity_ordered > 0 ? $i->quantity_ordered : $i->quantity));
        $totalReserved = (float)$delivery->items->sum('quantity_reserved');
        $totalReleased = (float)$delivery->items->sum(fn($i) => (float)($i->quantity_released ?? 0));
        $totalDispatched = (float)$delivery->items->sum(fn($i) => (float)$i->dispatched_qty);
        $totalDelivered = (float)$delivery->items->sum(fn($i) => (float)$i->delivered_qty);
        $totalPending = max(0, $totalOrdered - max($totalDispatched, $totalReleased) - $totalReserved);
        $fulfillmentRate = $totalOrdered > 0 ? round((($totalReserved + $totalDispatched) / $totalOrdered) * 100) : 0;

        $statusStep = match($delivery->status) {
            'Draft', 'Pending' => 1,
            'Processing', 'Partially Ready' => 2,
            'Released', 'Ready', 'Picked', 'Packed' => 3,
            'Dispatched', 'Partially Dispatched' => 4,
            'Delivered', 'Partially Delivered' => 5,
            default => 1,
        };
    @endphp

    {{-- ERP Single Panel --}}
    <div class="erp-single-panel">
        <x-ui.odoo-form-ui type="sheet" class="p-0">

            {{-- Unified Executive Header: Document Identity + Pipeline --}}
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pb-3 mb-3 border-bottom">
                {{-- Left: Icon + Doc Title + Status Badge --}}
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-text avatar-lg bg-soft-primary text-primary rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                        <i class="feather-clipboard fs-22"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                            <span class="fs-10 text-uppercase fw-bold text-muted letter-spacing-1">MATERIAL REQUIREMENT</span>
                            <span class="badge {{ $statusBadgeClass }} px-2.5 py-1 fs-11 fw-bold rounded-pill shadow-2xs d-inline-flex align-items-center" style="gap: 6px;">
                                <span class="badge-dot" style="display:inline-block; width:6px; height:6px; border-radius:50%; background:currentColor; flex-shrink: 0;"></span>
                                <span>{{ $delivery->status }}</span>
                            </span>
                        </div>
                        <h2 class="fw-bold text-dark mb-0 fs-22 tracking-tight">{{ $delivery->requirement_number }}</h2>
                    </div>
                </div>

                {{-- Right: Chevron Status Pipeline (Clean, No Double Numbering) --}}
                <div class="mr-status-pipeline d-none d-md-inline-flex shadow-2xs">
                    @php
                        $stages = [
                            1 => 'Pending Review',
                            2 => 'Processing',
                            3 => 'Released',
                            4 => 'Dispatched',
                            5 => 'Delivered',
                        ];
                    @endphp
                    @foreach($stages as $stepNum => $stepLabel)
                        <div class="pipeline-step {{ $statusStep > $stepNum ? 'completed' : ($statusStep === $stepNum ? 'active' : '') }}">
                            {{ $stepLabel }}
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Document Metadata Row --}}
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pb-3 mb-4 border-bottom fs-13 text-muted">
                <div class="d-flex align-items-center flex-wrap gap-x-4 gap-y-2">
                    <div>
                        <i class="feather-shopping-bag text-primary me-1.5"></i>
                        <span class="text-muted">{{ __('crm.sales_order') }}:</span>
                        <a href="{{ route('sales.orders.show', $delivery->sales_order_id) }}" class="fw-bold text-primary hover-underline font-monospace">
                            {{ $delivery->salesOrder->sales_order_number }}
                        </a>
                    </div>
                    <span>&bull;</span>
                    <div>
                        <i class="feather-user text-muted me-1.5"></i>
                        <span class="text-muted">{{ __('crm.customer') }}:</span>
                        <strong class="text-dark">{{ $delivery->salesOrder->customer?->name ?? 'Walk-in Customer' }}</strong>
                    </div>
                    <span>&bull;</span>
                    <div>
                        <i class="feather-calendar text-muted me-1.5"></i>
                        <span class="text-muted">{{ __('crm.date') }}:</span>
                        <strong class="text-dark">{{ $delivery->requirement_date ? $delivery->requirement_date->format('d M Y') : $delivery->created_at->format('d M Y') }}</strong>
                    </div>
                    @if($delivery->salesOrder->branch)
                        <span>&bull;</span>
                        <div>
                            <i class="feather-map-pin text-muted me-1.5"></i>
                            <span class="text-muted">Branch:</span>
                            <strong class="text-dark">{{ $delivery->salesOrder->branch->name }}</strong>
                        </div>
                    @endif
                </div>

                {{-- Quick Demand Badges --}}
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-dark border px-2.5 py-1.5 fw-semibold fs-11">
                        <i class="feather-layers me-1 text-primary"></i>{{ count($delivery->items) }} Line Item(s)
                    </span>
                    <span class="badge bg-soft-primary text-primary px-3 py-1.5 fw-bold fs-11">
                        <i class="feather-package me-1"></i>Total Demand: {{ (int)$totalOrdered }} Units
                    </span>
                </div>
            </div>

            {{-- Key KPI Metrics Ribbon (Executive Overview) --}}
            <div class="row g-3 mb-4 d-print-none">
                <!-- KPI 1: Order Qty -->
                <div class="col-sm-6 col-lg">
                    <div class="mr-metric-box h-100 shadow-2xs">
                        <div class="d-flex align-items-center justify-content-between mb-1.5">
                            <span class="text-muted fs-11 text-uppercase fw-bold">{{ __('crm.order_qty_th') }}</span>
                            <div class="avatar-text avatar-xs bg-soft-primary text-primary rounded-circle">
                                <i class="feather-package fs-13"></i>
                            </div>
                        </div>
                        <div class="fs-18 fw-bold text-dark mb-0.5">{{ (int)$totalOrdered }} <span class="fs-12 fw-normal text-muted">Units</span></div>
                        <div class="text-muted fs-11">Total required for Sales Order</div>
                    </div>
                </div>

                <!-- KPI 2: Released for Dispatch -->
                <div class="col-sm-6 col-lg">
                    <div class="mr-metric-box h-100 shadow-2xs">
                        <div class="d-flex align-items-center justify-content-between mb-1.5">
                            <span class="text-muted fs-11 text-uppercase fw-bold">Released for Dispatch</span>
                            <div class="avatar-text avatar-xs bg-soft-info text-info rounded-circle">
                                <i class="feather-send fs-13"></i>
                            </div>
                        </div>
                        <div class="fs-18 fw-bold text-info mb-0.5">{{ (int)$totalReleased }} <span class="fs-12 fw-normal text-muted">Units</span></div>
                        <div class="text-muted fs-11">Allocated & sent to dispatch floor</div>
                    </div>
                </div>

                <!-- KPI 3: Reserved Stock -->
                <div class="col-sm-6 col-lg">
                    <div class="mr-metric-box h-100 shadow-2xs">
                        <div class="d-flex align-items-center justify-content-between mb-1.5">
                            <span class="text-muted fs-11 text-uppercase fw-bold">{{ __('crm.reserved_th') }}</span>
                            <div class="avatar-text avatar-xs bg-soft-success text-success rounded-circle">
                                <i class="feather-check-circle fs-13"></i>
                            </div>
                        </div>
                        <div class="fs-18 fw-bold text-success mb-0.5">{{ (int)$totalReserved }} <span class="fs-12 fw-normal text-muted">Units</span></div>
                        <div class="text-muted fs-11">Allocated in warehouse stock</div>
                    </div>
                </div>

                <!-- KPI 4: Pending Shortage -->
                <div class="col-sm-6 col-lg">
                    <div class="mr-metric-box h-100 shadow-2xs">
                        <div class="d-flex align-items-center justify-content-between mb-1.5">
                            <span class="text-muted fs-11 text-uppercase fw-bold">{{ __('crm.pending_th') }}</span>
                            <div class="avatar-text avatar-xs {{ $totalPending > 0 ? 'bg-soft-warning text-warning' : 'bg-soft-secondary text-muted' }} rounded-circle">
                                <i class="feather-clock fs-13"></i>
                            </div>
                        </div>
                        <div class="fs-18 fw-bold {{ $totalPending > 0 ? 'text-warning' : 'text-muted' }} mb-0.5">{{ (int)$totalPending }} <span class="fs-12 fw-normal text-muted">Units</span></div>
                        <div class="text-muted fs-11">{{ $totalPending > 0 ? 'Shortage — Requires Indent / MO' : 'No Shortage' }}</div>
                    </div>
                </div>

                <!-- KPI 5: Fulfillment Progress -->
                <div class="col-sm-6 col-lg">
                    <div class="mr-metric-box h-100 shadow-2xs">
                        <div class="d-flex align-items-center justify-content-between mb-1.5">
                            <span class="text-muted fs-11 text-uppercase fw-bold">{{ __('crm.fulfilled') }}</span>
                            <div class="avatar-text avatar-xs bg-soft-primary text-primary rounded-circle">
                                <i class="feather-pie-chart fs-13"></i>
                            </div>
                        </div>
                        <div class="d-flex align-items-baseline gap-2 mb-1.5">
                            <span class="fs-18 fw-bold text-primary">{{ $delivery->fulfillment_rate }}%</span>
                            @if($delivery->total_dispatched_qty > 0)
                                <span class="fs-11 text-muted">({{ (int)$delivery->total_dispatched_qty }} shipped)</span>
                            @endif
                        </div>
                        <div class="progress" style="height: 6px; border-radius: 3px; background-color: #e2e8f0;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: {{ min(100, $delivery->fulfillment_rate) }}%; border-radius: 3px;" aria-valuenow="{{ $delivery->fulfillment_rate }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Material Requirement Lines Section --}}
            <div class="mb-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="fw-bold text-dark mb-0 fs-14">
                        <i class="feather-list me-2 text-primary"></i>{{ __('crm.mr_lines_header') }}
                    </h6>
                    <span class="badge bg-soft-primary text-primary fw-bold px-2.5 py-1 fs-12">
                        {{ count($delivery->items) }} Line Item(s)
                    </span>
                </div>

                <div class="table-responsive" style="overflow: visible !important;">
                    <x-ui.odoo-form-ui type="table" class="align-middle fs-13 mb-0 mr-line-table" style="margin-top:0;">
                            <thead class="fs-11 text-uppercase fw-bold text-muted">
                                <tr>
                                    <th style="width:23%" class="ps-4">{{ __('crm.product_details') }}</th>
                                    <th style="width:7%" class="text-center">{{ __('crm.method') }}</th>
                                    <th style="width:7%" class="text-end">{{ __('crm.order_qty_th') }}</th>
                                    <th style="width:8%" class="text-end text-primary fw-bold"><i class="feather-send me-1 fs-10"></i>Released</th>
                                    <th style="width:7%" class="text-end">{{ __('crm.reserved_th') }}</th>
                                    <th style="width:7%" class="text-end">{{ __('crm.pending_th') }}</th>
                                    <th style="width:17%">{{ __('crm.warehouse_th') }}</th>
                                    <th style="width:7%" class="text-end">{{ __('crm.avail_th') }}</th>
                                    <th style="width:9%" class="text-center">{{ __('crm.status_th') }}</th>
                                    <th style="width:8%" class="text-center pe-4">{{ __('crm.action_th') }}</th>
                                </tr>
                            </thead>
                            <tbody class="text-dark">
                                @foreach ($delivery->items as $item)
                                    @php
                                        $method      = strtolower($item->product?->supplier_method ?? 'trade');
                                        $isService   = $item->product?->item_type === 'Service';
                                        $orderedQty    = (float)($item->quantity_ordered > 0 ? $item->quantity_ordered : $item->quantity);
                                        $reservedQty   = (float)$item->quantity_reserved;
                                        $releasedQty   = (float)($item->quantity_released ?? 0);
                                        $dispatchedQty = $item->dispatched_qty;
                                        $pendingQty    = $item->pending_qty;
                                        $availableQty  = (float)($item->available_qty ?? 0);
                                        $isLocked      = in_array($delivery->status, ['Dispatched', 'Delivered', 'Cancelled']);
                                        $displayStatus = $item->calculated_status;

                                        $lineBadge = 'secondary';
                                        if ($displayStatus === 'Delivered')                    $lineBadge = 'success';
                                        elseif (in_array($displayStatus, ['Reserved', 'Ready']))  $lineBadge = 'success';
                                        elseif ($displayStatus === 'Waiting Purchase')         $lineBadge = 'warning';
                                        elseif ($displayStatus === 'Partially PR Raised')      $lineBadge = 'info';
                                        elseif ($displayStatus === 'Waiting Production')       $lineBadge = 'danger';
                                        elseif (in_array($displayStatus, ['Partially Reserved', 'Partially Dispatched', 'Partially Delivered'])) $lineBadge = 'info';
                                        elseif ($displayStatus === 'Picked')                   $lineBadge = 'primary';
                                        elseif ($displayStatus === 'Packed')                   $lineBadge = 'info';
                                        elseif ($displayStatus === 'Dispatched')               $lineBadge = 'primary';
                                    @endphp
                                    <tr class="mr-item-row"
                                        data-item-id="{{ $item->id }}"
                                        data-product-name="{{ $item->product?->name }}"
                                        data-sku="{{ $item->product?->sku }}"
                                        data-ordered="{{ (int)$orderedQty }}"
                                        data-pending="{{ (int)$pendingQty }}"
                                        data-reserved="{{ (int)$reservedQty }}"
                                        data-released="{{ (float)($item->quantity_released ?? 0) }}"
                                        data-status="{{ $item->status }}"
                                        data-is-service="{{ $isService ? '1' : '0' }}"
                                        data-warehouse-breakdown='@json($item->warehouse_breakdown ?? [])'
                                    >
                                        {{-- Product --}}
                                        <td class="ps-4 py-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="avatar-text avatar-sm bg-soft-secondary text-dark rounded-circle">
                                                    <i class="feather-box fs-12"></i>
                                                </div>
                                                <div>
                                                    <strong class="text-dark d-block fs-13">{{ $item->product?->name ?? 'Unknown Product' }}</strong>
                                                    @if ($item->product?->sku)
                                                         <span class="badge bg-light text-muted font-monospace fs-10 px-1.5 py-0.5 border">SKU: {{ $item->product->sku }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>

                                        {{-- Method --}}
                                        <td class="text-center">
                                            @if ($method === 'manufacture')
                                                <x-ui.badge :soft="true" variant="warning" class="fs-11 px-2 py-1 fw-bold">
                                                    <i class="feather-cpu me-1"></i>Mfg
                                                </x-ui.badge>
                                            @else
                                                <x-ui.badge :soft="true" variant="success" class="fs-11 px-2 py-1 fw-bold">
                                                    <i class="feather-shopping-cart me-1"></i>Trade
                                                </x-ui.badge>
                                            @endif
                                        </td>

                                        <td class="text-end fw-bold fs-13">{{ (int)$orderedQty }}</td>
                                        <td class="text-end fw-bold fs-13">
                                            @if($releasedQty > 0)
                                                <span class="badge bg-soft-primary text-primary fs-11 px-2 py-0.5">
                                                    <i class="feather-send me-1 fs-9"></i>{{ (int)$releasedQty }}
                                                </span>
                                            @else
                                                <span class="text-muted fs-12">—</span>
                                            @endif
                                        </td>
                                        <td class="text-end fw-bold text-success fs-13">{{ (int)$reservedQty }}</td>
                                        <td class="text-end fw-semibold {{ $pendingQty > 0 ? 'text-warning' : 'text-muted' }} fs-13">{{ (int)$pendingQty }}</td>
                                        
                                        {{-- Warehouse Dropdown & Stock Info --}}
                                        <td>
                                            @if ($isService)
                                                <span class="text-muted">—</span>
                                            @else
                                                <div class="d-flex align-items-center gap-1.5">
                                                    <div class="flex-grow-1">
                                                        <select
                                                            id="warehouse-select-{{ $item->id }}"
                                                            class="form-select form-select-sm border-gray-300 fs-12 item-warehouse-select"
                                                            data-item-id="{{ $item->id }}"
                                                            onchange="changeWarehouse({{ $item->id }}, this)"
                                                            {{ $isLocked ? 'disabled' : '' }}
                                                            data-select2-selector="default"
                                                            data-master="warehouse"
                                                            style="width: 100%;"
                                                        >
                                                            <option value="">{{ __('crm.select_warehouse') }}</option>
                                                            <option value="__ADD_NEW__" class="fw-bold text-primary" data-master="warehouse">+ {{ __('crm.add_new_warehouse') }}</option>
                                                            @foreach ($warehouses as $w)
                                                                @php
                                                                    $wStock = (float)($item->warehouse_breakdown[$w->id]['available_qty'] ?? 0);
                                                                @endphp
                                                                <option value="{{ $w->id }}" data-avail="{{ (int)$wStock }}" {{ ($item->warehouse_id ?: $defaultWarehouseId) == $w->id ? 'selected' : '' }}>
                                                                    {{ $w->name }} ({{ (int)$wStock }} avail)
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="btn btn-xs btn-outline-secondary rounded-circle p-0 flex-shrink-0 shadow-2xs wh-stock-info-btn"
                                                        style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center;"
                                                        data-bs-toggle="popover"
                                                        data-bs-trigger="hover focus"
                                                        data-bs-placement="top"
                                                        data-bs-html="true"
                                                        data-bs-custom-class="wh-stock-popover shadow-sm"
                                                        title="<div class='fw-bold fs-12 text-dark'><i class='feather-map-pin text-primary me-1'></i>Stock by Warehouse</div>"
                                                        data-bs-content="<div class='fs-12 py-1'><div class='text-muted mb-2 font-monospace fs-11 border-bottom pb-1'>{{ addslashes($item->product?->name) }}</div><table class='table table-sm table-borderless mb-1 fs-11'>@foreach ($warehouses as $w)@php $wStock = (float)($item->warehouse_breakdown[$w->id]['available_qty'] ?? 0); $isCurr = ($item->warehouse_id ?: $defaultWarehouseId) == $w->id; @endphp<tr class='{{ $isCurr ? 'bg-soft-primary' : '' }}' style='border-radius:4px;'><td class='py-1 ps-1.5 {{ $isCurr ? 'fw-bold text-primary' : 'text-dark' }}'>{{ addslashes($w->name) }} {{ $isCurr ? '(Selected)' : '' }}</td><td class='text-end py-1 pe-1.5 {{ $wStock > 0 ? 'text-success fw-bold' : 'text-muted' }}'>{{ (int)$wStock }} Units</td></tr>@endforeach</table><div class='text-muted fs-10 mt-1 fst-italic border-top pt-1 text-center'>Select warehouse from dropdown to switch sourcing</div></div>"
                                                    >
                                                        <i class="feather-info fs-12"></i>
                                                    </button>
                                                </div>
                                            @endif
                                        </td>

                                        {{-- Available Qty --}}
                                        <td class="text-end fw-bold fs-13">
                                            @if ($isService)
                                                <span class="text-muted">—</span>
                                            @else
                                                <span
                                                    id="available-qty-{{ $item->id }}"
                                                    data-ordered="{{ (int)$orderedQty }}"
                                                    class="{{ $availableQty >= $orderedQty ? 'text-success' : 'text-danger' }}"
                                                 >{{ (int)$availableQty }}</span>
                                            @endif
                                        </td>

                                        {{-- Line Status --}}
                                        <td class="text-center">
                                            <x-ui.badge :soft="true" :variant="$lineBadge" class="fs-11 px-2.5 py-1 fw-semibold">
                                                {{ $displayStatus }}
                                            </x-ui.badge>
                                        </td>

                                        {{-- Action --}}
                                        <td class="text-center pe-4">
                                            @if (!$isLocked)
                                                <div class="d-flex flex-column align-items-center gap-1">
                                                    @if ($pendingQty > 0)
                                                        {{-- Reserve Button --}}
                                                        <div id="reserve-btn-wrap-{{ $item->id }}" class="w-100 {{ $availableQty > 0 ? '' : 'd-none' }}" style="{{ $availableQty > 0 ? '' : 'display: none;' }}">
                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-soft-primary px-2 py-1 fs-11 fw-bold w-100"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#reserveModal-{{ $item->id }}"
                                                            ><i class="feather-archive me-1"></i>{{ __('crm.reserve') }}</button>
                                                        </div>

                                                        @if ($method === 'buy' || $method === 'trade')
                                                            @php
                                                                $prRaised = (float)($item->quantity_pr_raised ?? 0);
                                                                $remainingPrQty = $item->remaining_pr_qty;
                                                                $hasShortage = $availableQty < $pendingQty;
                                                            @endphp

                                                            @if ($item->status === 'Waiting Purchase' || ($prRaised > 0 && $remainingPrQty <= 0))
                                                                <span class="badge bg-soft-warning text-warning px-2 py-1 fs-11 w-100 text-center fw-semibold">
                                                                    <i class="feather-check-circle me-1"></i>{{ __('crm.pr_raised') }} ({{ (int)$prRaised }}/{{ (int)$pendingQty }})
                                                                </span>
                                                            @elseif ($prRaised > 0 && $remainingPrQty > 0)
                                                                <span class="badge bg-soft-info text-info px-2 py-1 fs-11 w-100 text-center mb-1 fw-semibold">
                                                                    <i class="feather-clock me-1"></i>{{ __('crm.pr_raised') }} ({{ (int)$prRaised }}/{{ (int)$pendingQty }})
                                                                </span>
                                                                <div id="indent-btn-wrap-{{ $item->id }}" data-pending="{{ (int)$pendingQty }}" class="w-100 {{ $hasShortage ? '' : 'd-none' }}" style="{{ $hasShortage ? '' : 'display: none;' }}">
                                                                    <button
                                                                        type="button"
                                                                        class="btn btn-sm btn-soft-warning px-2 py-1 fs-11 fw-bold w-100"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#indentModal-{{ $item->id }}"
                                                                    ><i class="feather-plus-circle me-1"></i>{{ __('crm.create_indent') }} (+{{ (int)$remainingPrQty }})</button>
                                                                </div>
                                                            @else
                                                                <div id="indent-btn-wrap-{{ $item->id }}" data-pending="{{ (int)$pendingQty }}" class="w-100 {{ $hasShortage ? '' : 'd-none' }}" style="{{ $hasShortage ? '' : 'display: none;' }}">
                                                                    <button
                                                                        type="button"
                                                                        class="btn btn-sm btn-soft-warning px-2 py-1 fs-11 fw-bold w-100"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#indentModal-{{ $item->id }}"
                                                                    ><i class="feather-file-text me-1"></i>{{ __('crm.indent') }}</button>
                                                                </div>
                                                            @endif
                                                        @elseif ($method === 'manufacture')
                                                            @php
                                                                $hasShortage = $availableQty < $pendingQty;
                                                            @endphp
                                                            @if ($item->status === 'Waiting Production')
                                                                <x-ui.badge :soft="true" variant="warning" class="fs-11 px-2 py-1 w-100 text-center fw-semibold">
                                                                    <i class="feather-clock me-1"></i>{{ __('crm.mo_raised') }}
                                                                </x-ui.badge>
                                                            @else
                                                                <div id="indent-btn-wrap-{{ $item->id }}" data-pending="{{ (int)$pendingQty }}" class="w-100 {{ $hasShortage ? '' : 'd-none' }}" style="{{ $hasShortage ? '' : 'display: none;' }}">
                                                                    <button
                                                                        type="button"
                                                                        class="btn btn-sm btn-soft-danger px-2 py-1 fs-11 fw-bold w-100"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#generateMoModal-{{ $item->id }}"
                                                                    ><i class="feather-cpu me-1"></i>{{ __('crm.gen_mo') }}</button>
                                                                </div>
                                                            @endif
                                                        @endif
                                                    @else
                                                        <x-ui.badge :soft="true" variant="success" class="fs-11 px-2.5 py-1 fw-bold">
                                                            <i class="feather-check-circle me-1"></i>{{ __('crm.fulfilled') }}
                                                        </x-ui.badge>
                                                    @endif
                                                </div>
                                            @else
                                                @if ($delivery->status === 'Delivered' || $item->calculated_status === 'Delivered')
                                                    <x-ui.badge :soft="true" variant="success" class="fs-11 px-2.5 py-1 fw-bold">
                                                        <i class="feather-check-circle me-1"></i>Delivered
                                                    </x-ui.badge>
                                                @elseif ($delivery->status === 'Dispatched' || $item->calculated_status === 'Dispatched')
                                                    <x-ui.badge :soft="true" variant="primary" class="fs-11 px-2.5 py-1 fw-bold">
                                                        <i class="feather-truck me-1"></i>Dispatched
                                                    </x-ui.badge>
                                                @else
                                                    <span class="text-muted fs-12 fw-semibold">
                                                        <i class="feather-lock me-1"></i>{{ __('crm.locked') }}
                                                    </span>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>
                </div>
        </x-ui.odoo-form-ui>
    </div>{{-- /erp-single-panel --}}


    {{-- ============================================================ --}}
    {{-- Per-item Modals                                              --}}
    {{-- ============================================================ --}}
    @foreach ($delivery->items as $item)
        @php
            $method       = strtolower($item->product?->supplier_method ?? 'trade');
            $isService    = $item->product?->item_type === 'Service';
            $orderedQty   = (float)($item->quantity_ordered > 0 ? $item->quantity_ordered : $item->quantity);
            $reservedQty  = (float)$item->quantity_reserved;
            $dispatchedQty= $item->dispatched_qty;
            $pendingQty   = $item->pending_qty;
            $availableQty = (float)($item->available_qty ?? 0);
            $shortageQty  = max(0, $pendingQty - $availableQty);
        @endphp

        @if (($method === 'buy' || $method === 'trade' || $method === 'manufacture') && $pendingQty > 0)

            {{-- Reserve Stock Modal --}}
            <x-ui.modal
                id="reserveModal-{{ $item->id }}"
                :title="__('crm.reserve_stock') . ' — ' . $item->product?->name"
                :submitText="__('crm.confirm_reservation')"
                formAction="{{ route('inventory.material-requirements.reserve-qty', $item->id) }}"
                :centered="true"
            >
                <div class="fs-13 text-dark">
                    {{-- Product info banner --}}
                    <div class="d-flex align-items-center gap-3 p-3 bg-light rounded border mb-4">
                        <div class="avatar-text avatar-md bg-soft-primary text-primary">
                            <i class="feather-package"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-0">{{ $item->product?->name }}</h6>
                            <small class="text-muted font-monospace">SKU: {{ $item->product?->sku ?? '—' }}</small>
                        </div>
                    </div>

                    {{-- Warehouse Selector --}}
                    <div class="odoo-form-group mb-3">
                        <label class="odoo-form-label" for="reserve-warehouse-{{ $item->id }}">{{ __('crm.warehouse_th') }}</label>
                        <div class="flex-grow-1">
                            <select
                                id="reserve-warehouse-{{ $item->id }}"
                                name="warehouse_id"
                                class="odoo-form-control form-select-sm"
                                onchange="updateReserveAvailable({{ $item->id }}, this)"
                                style="border-radius:0;"
                            >
                                @foreach ($warehouses as $w)
                                    <option
                                        value="{{ $w->id }}"
                                        data-avail="{{ \App\Domains\Inventory\Services\StockService::getAvailableStock($item->product_id, $w->id) }}"
                                        {{ ($item->warehouse_id ?: $defaultWarehouseId) == $w->id ? 'selected' : '' }}
                                    >{{ $w->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Stock Summary --}}
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <div class="bg-light rounded p-2 text-center border">
                                <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">{{ __('crm.avail_th') }}</span>
                                <span id="reserve-modal-avail-{{ $item->id }}" class="fs-16 fw-bold text-success">{{ (int)$availableQty }}</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light rounded p-2 text-center border">
                                <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">{{ __('crm.order_qty_th') }}</span>
                                <span class="fs-16 fw-bold text-dark">{{ (int)$orderedQty }}</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light rounded p-2 text-center border">
                                <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">{{ __('crm.pending_th') }}</span>
                                <span class="fs-16 fw-bold text-danger">{{ (int)$pendingQty }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Qty to Reserve --}}
                    <div class="odoo-form-group mb-0">
                        <label class="odoo-form-label" for="reserve-qty-input-{{ $item->id }}">
                            {{ __('crm.qty_to_reserve') }} <span class="text-danger">*</span>
                        </label>
                        <div class="flex-grow-1">
                            <input
                                type="number"
                                name="quantity_reserve"
                                id="reserve-qty-input-{{ $item->id }}"
                                class="odoo-form-control reserve-qty-input"
                                data-item-id="{{ $item->id }}"
                                data-pending="{{ (int)$pendingQty }}"
                                min="1"
                                max="{{ min((int)$pendingQty, (int)$availableQty) }}"
                                value="{{ min((int)$pendingQty, (int)$availableQty) }}"
                                required
                            >
                            <div class="text-muted fs-11 mt-1">
                                Max: <span id="reserve-max-label-{{ $item->id }}" class="fw-bold">{{ min((int)$pendingQty, (int)$availableQty) }}</span> units
                            </div>
                            <div class="text-danger fs-11 mt-1 fw-bold" id="reserve-qty-error-{{ $item->id }}" style="display: none; color: #dc2626 !important;"></div>
                        </div>
                    </div>
                </div>
            </x-ui.modal>

            @php
                $prRaised = (float)($item->quantity_pr_raised ?? 0);
                $remainingPrQty = $item->remaining_pr_qty;
            @endphp

            @if (($method === 'buy' || $method === 'trade') && $item->status !== 'Waiting Purchase' && $remainingPrQty > 0)
                {{-- Create Indent Modal --}}
                <x-ui.modal
                    id="indentModal-{{ $item->id }}"
                    :title="__('crm.create_purchase_indent') . ' — ' . $item->product?->name"
                    :submitText="__('crm.submit_indent_request')"
                    formAction="{{ route('inventory.material-requirements.mock-indent', $item->id) }}"
                    :centered="true"
                    :showFooter="true"
                >
                    <div class="fs-13 text-dark">
                        <div class="d-flex align-items-center gap-3 p-3 bg-light rounded border mb-4">
                            <div class="avatar-text avatar-md bg-soft-warning text-warning">
                                <i class="feather-file-text"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0">{{ $item->product?->name }}</h6>
                                <small class="text-muted font-monospace">SKU: {{ $item->product?->sku ?? '—' }}</small>
                            </div>
                        </div>

                        <x-ui.alert variant="warning" icon="feather-info" class="border-0 fs-12 py-2 mb-3">
                            {{ __('crm.purchase_indent_alert') }}
                        </x-ui.alert>

                        <div class="row g-2 mb-3">
                            <div class="col-3">
                                <div class="bg-light rounded p-2 text-center border">
                                    <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">{{ __('crm.order_qty_th') }}</span>
                                    <span class="fs-15 fw-bold text-dark">{{ (int)$orderedQty }}</span>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="bg-light rounded p-2 text-center border">
                                    <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">{{ __('crm.reserved_th') }}</span>
                                    <span class="fs-15 fw-bold text-success">{{ (int)$reservedQty }}</span>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="bg-light rounded p-2 text-center border">
                                    <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">{{ __('crm.pr_raised') }}</span>
                                    <span class="fs-15 fw-bold text-info">{{ (int)$prRaised }}</span>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="bg-light rounded p-2 text-center border">
                                    <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">{{ __('crm.pending_th') }}</span>
                                    <span class="fs-15 fw-bold text-danger">{{ (int)$remainingPrQty }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="odoo-form-group">
                            <label class="odoo-form-label">{{ __('crm.qty_to_indent') }} <span class="text-danger">*</span></label>
                            <div class="flex-grow-1">
                                <input type="number" class="odoo-form-control" name="quantity_request" min="1" max="{{ (int)$remainingPrQty }}" value="{{ (int)$remainingPrQty }}" required>
                                <div class="text-muted fs-11 mt-1">Pre-filled with remaining un-indented quantity ({{ (int)$remainingPrQty }} unit(s)). You can adjust.</div>
                            </div>
                        </div>

                        <div class="odoo-form-group">
                            <label class="odoo-form-label">{{ __('crm.destination_warehouse') }} <span class="text-danger">*</span></label>
                            <div class="flex-grow-1">
                                <select class="odoo-form-control" name="warehouse_id" required>
                                    @foreach($warehouses as $w)
                                        <option value="{{ $w->id }}" @selected($w->id == $item->warehouse_id)>{{ $w->name }}</option>
                                    @endforeach
                                </select>
                                <div class="text-muted fs-11 mt-1">Select the target warehouse for procurement.</div>
                            </div>
                        </div>

                        <div class="odoo-form-group">
                            <label class="odoo-form-label">{{ __('crm.expected_date') }}</label>
                            <div class="flex-grow-1">
                                <input type="date" class="odoo-form-control" name="expected_date">
                                <div class="text-muted fs-11 mt-1">Target date by which required items should arrive.</div>
                            </div>
                        </div>

                        <div class="odoo-form-group mb-0">
                            <label class="odoo-form-label">{{ __('crm.notes') }}</label>
                            <div class="flex-grow-1">
                                <textarea class="odoo-form-control" name="notes" rows="2" placeholder="Reason, urgency, etc…"></textarea>
                            </div>
                        </div>
                    </div>
                </x-ui.modal>
            @endif

        @if ($method === 'manufacture' && $item->status !== 'Waiting Production')

            {{-- Generate MO Modal --}}
            <x-ui.modal
                id="generateMoModal-{{ $item->id }}"
                :title="__('crm.generate_mo') . ' — ' . $item->product?->name"
                :submitText="__('crm.raise_mo_request')"
                formAction="{{ route('inventory.material-requirements.mock-mo', $item->id) }}"
                :centered="true"
            >
                <div class="fs-13 text-dark">
                    <div class="d-flex align-items-center gap-3 p-3 bg-light rounded border mb-4">
                        <div class="avatar-text avatar-md bg-soft-danger text-danger">
                            <i class="feather-cpu"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-0">{{ $item->product?->name }}</h6>
                            <small class="text-muted font-monospace">SKU: {{ $item->product?->sku ?? '—' }}</small>
                        </div>
                    </div>

                    <x-ui.alert variant="danger" icon="feather-cpu" class="border-0 fs-12 py-2 mb-3">
                        {{ __('crm.mo_alert') }}
                    </x-ui.alert>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="bg-light rounded p-2 text-center border">
                                <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">{{ __('crm.order_qty_th') }}</span>
                                <span class="fs-16 fw-bold text-dark">{{ (int)$orderedQty }}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-light rounded p-2 text-center border">
                                <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">{{ __('crm.qty_to_mfg') }}</span>
                                <span class="fs-16 fw-bold text-danger">{{ (int)$pendingQty }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="odoo-form-group">
                        <label class="odoo-form-label">{{ __('crm.qty_to_mfg') }} <span class="text-danger">*</span></label>
                        <div class="flex-grow-1">
                            <input type="number" name="quantity_mfg" class="odoo-form-control" value="{{ (int)$pendingQty }}" min="1" required>
                        </div>
                    </div>

                    <div class="odoo-form-group mb-0">
                        <label class="odoo-form-label">{{ __('crm.notes_priority') }}</label>
                        <div class="flex-grow-1">
                            <textarea name="notes" class="odoo-form-control" rows="2" placeholder="Priority, due date, special instructions…"></textarea>
                        </div>
                    </div>
                </div>
            </x-ui.modal>

        @endif

    @endif

    @endforeach

    {{-- Dispatch Modal --}}
    @if ($delivery->status === 'Packed')
        <x-ui.modal
            id="dispatchModal"
            title="Transporter & Shipment Details"
            formAction="{{ route('inventory.material-requirements.dispatch', $delivery->id) }}"
            :submitText="__('crm.dispatch_order')"
            :centered="true"
        >
            <div class="fs-13 text-dark">
                <div class="odoo-form-group">
                    <label class="odoo-form-label">{{ __('crm.carrier_courier') }} <span class="text-danger">*</span></label>
                    <div class="flex-grow-1">
                        <input type="text" name="carrier" class="odoo-form-control" placeholder="e.g. DHL, BlueDart, SafeExpress" required>
                    </div>
                </div>
                <div class="odoo-form-group">
                    <label class="odoo-form-label">{{ __('crm.tracking_number') }} <span class="text-danger">*</span></label>
                    <div class="flex-grow-1">
                        <input type="text" name="tracking_number" class="odoo-form-control" placeholder="e.g. TRK983742 or MH-12-XX-XXXX" required>
                    </div>
                </div>
                <div class="odoo-form-group mb-0">
                    <label class="odoo-form-label">{{ __('crm.notes') }}</label>
                    <div class="flex-grow-1">
                        <textarea name="notes" class="odoo-form-control" rows="3" placeholder="Remarks regarding dispatch…"></textarea>
                    </div>
                </div>
            </div>
        </x-ui.modal>
    @endif

    {{-- ============================================================ --}}
    {{-- Release for Dispatch Modal (Stock Review & Allocation)      --}}
    {{-- ============================================================ --}}
    <div class="modal fade" id="releaseForDispatchModal" tabindex="-1" aria-labelledby="releaseForDispatchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <form action="{{ route('inventory.material-requirements.release', $delivery->id) }}" method="POST" id="releaseForDispatchForm">
                    @csrf
                    <div class="modal-header bg-light border-bottom py-3">
                        <div class="d-flex align-items-center gap-2.5">
                            <div class="avatar-text avatar-sm bg-soft-success text-success rounded-circle">
                                <i class="feather-send fs-14"></i>
                            </div>
                            <div>
                                <h6 class="modal-title fw-bold text-dark mb-0 fs-15" id="releaseForDispatchModalLabel">Release for Dispatch — Stock Allocation Review</h6>
                                <span class="text-muted fs-11 font-monospace">{{ $delivery->requirement_number }} &bull; {{ $delivery->salesOrder->sales_order_number }}</span>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body p-4">
                        {{-- Dynamic Alert Banner --}}
                        <div id="releaseStockAlertBanner" class="alert alert-soft-info border d-flex align-items-start gap-2 mb-3 fs-12 py-2.5">
                            <i class="feather-info fs-16 mt-0.5 flex-shrink-0 text-primary" id="releaseAlertIcon"></i>
                            <div id="releaseStockAlertText">
                                Review warehouse stock availability below. You can change the sourcing warehouse or select specific in-stock items for partial release.
                            </div>
                        </div>

                        {{-- Line Items Table --}}
                        <div class="table-responsive border rounded-3 mb-3">
                            <table class="table table-sm align-middle mb-0 fs-12">
                                <thead class="bg-light text-uppercase fs-10 text-muted fw-bold">
                                    <tr>
                                        <th style="width: 4%;" class="text-center ps-3">
                                            <input type="checkbox" id="selectAllReleaseItems" class="form-check-input">
                                        </th>
                                        <th style="width: 27%;">Product</th>
                                        <th style="width: 24%;">Sourcing Warehouse</th>
                                        <th style="width: 9%;" class="text-end">Required</th>
                                        <th style="width: 10%;" class="text-end">Avail In Wh</th>
                                        <th style="width: 13%;" class="text-end">Release Qty</th>
                                        <th style="width: 12%;" class="text-center pe-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="releaseModalItemsBody">
                                    {{-- Dynamically populated on modal show --}}
                                </tbody>
                            </table>
                        </div>

                        {{-- Real ERP Guidance Box --}}
                        <div class="p-3 bg-light rounded-3 border fs-12">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="feather-shield text-primary fs-14"></i>
                                <strong class="text-dark">Warehouse Fulfillment Rule (Real ERP Standard)</strong>
                            </div>
                            <p class="text-muted mb-0 fs-11 leading-relaxed">
                                Items released here will be dispatched from their assigned warehouse. If an item is out of stock in your primary warehouse but available elsewhere, switch the warehouse above before releasing. Shortage items that are not released will remain in this requirement for indenting or inter-warehouse transfer.
                            </p>
                        </div>
                    </div>

                    <div class="modal-footer bg-light border-top py-2.5 px-4 d-flex align-items-center justify-content-between">
                        <span class="fs-12 text-muted fw-semibold" id="releaseSelectionSummary">0 item(s) selected</span>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" id="confirmReleaseBtn" class="btn btn-sm btn-success fw-bold px-4 shadow-2xs">
                                <i class="feather-check-circle me-1.5"></i>Confirm Release
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <x-ui.master-modals :masters="['warehouse']" />
@endsection


@push('scripts')
    <script>
        /**
         * Called when the Warehouse dropdown changes on the main table row.
         */
        function changeWarehouse(itemId, select) {
            const warehouseId = select ? (select.value || $(select).val()) : $(`#warehouse-select-${itemId}`).val();
            if (!warehouseId || warehouseId === '__ADD_NEW__') return;
            const url = `{{ url('inventory/material-requirements/items') }}/${itemId}/warehouse`;

            $.ajax({
                url:    url,
                method: 'POST',
                data:   { _token: '{{ csrf_token() }}', warehouse_id: warehouseId },
                success: function (response) {
                    if (!response.success) return;
                    const avail = parseInt(response.available_qty) || 0;
                    $(`#available-qty-${itemId}`).text(avail);

                    const $reserveSelect = $(`#reserve-warehouse-${itemId}`);
                    if ($reserveSelect.length) {
                        $reserveSelect.val(warehouseId);
                        if ($reserveSelect.hasClass('select2-hidden-accessible')) {
                            $reserveSelect.trigger('change.select2');
                        }
                    }

                    updateReserveAvailableFromQty(itemId, avail);
                },
                error: function (err) { console.error('Error updating warehouse stock: ', err); }
            });
        }

        /**
         * Called when warehouse changes inside the Reserve Stock modal.
         */
        function updateReserveAvailable(itemId, select) {
            const selectedOption = select.options[select.selectedIndex];
            const avail = parseInt(selectedOption.getAttribute('data-avail')) || 0;

            const $mainSelect = $(`#warehouse-select-${itemId}`);
            if ($mainSelect.length) {
                $mainSelect.val(select.value);
                if ($mainSelect.hasClass('select2-hidden-accessible')) {
                    $mainSelect.trigger('change.select2');
                }
            }

            updateReserveAvailableFromQty(itemId, avail);

            $.ajax({
                url:    `{{ url('inventory/material-requirements/items') }}/${itemId}/warehouse`,
                method: 'POST',
                data:   { _token: '{{ csrf_token() }}', warehouse_id: select.value },
                success: function (response) {
                    if (response.success) {
                        const serverAvail = parseInt(response.available_qty) || 0;
                        $(`#available-qty-${itemId}`).text(serverAvail);
                        updateReserveAvailableFromQty(itemId, serverAvail);
                    }
                }
            });
        }

        /**
         * Shared helper — updates available label + max of qty input in reserve modal + toggles reserve button.
         */
        function updateReserveAvailableFromQty(itemId, avail) {
            $(`#reserve-modal-avail-${itemId}`).text(avail);

            const $availCell = $(`#available-qty-${itemId}`);
            $availCell.text(avail);
            const ordered = parseInt($availCell.data('ordered')) || 0;
            $availCell.removeClass('text-success text-danger');
            $availCell.addClass(avail >= ordered ? 'text-success' : 'text-danger');

            // Dynamically show or hide the Reserve button based on stock in selected warehouse
            const $reserveWrap = $(`#reserve-btn-wrap-${itemId}`);
            if ($reserveWrap.length) {
                if (avail > 0) {
                    $reserveWrap.removeClass('d-none').show();
                } else {
                    $reserveWrap.addClass('d-none').hide();
                }
            }

            // Dynamically show or hide the Indent / Gen MO button based on shortage
            const $indentWrap = $(`#indent-btn-wrap-${itemId}`);
            const $input = $(`#reserve-qty-input-${itemId}`);
            const pending = $indentWrap.length 
                ? (parseInt($indentWrap.data('pending')) || 0) 
                : ($input.length ? (parseInt($input.data('pending')) || 0) : 0);

            if ($indentWrap.length) {
                if (avail < pending) {
                    $indentWrap.removeClass('d-none').show();
                } else {
                    $indentWrap.addClass('d-none').hide();
                }
            }

            if ($input.length) {
                const pendingAttr = parseInt($input.data('pending')) || 0;
                const maxVal = Math.min(pendingAttr, avail);
                $input.attr('max', maxVal);
                $input.val(maxVal > 0 ? maxVal : '');
                $(`#reserve-max-label-${itemId}`).text(maxVal);

                $input.removeClass('is-invalid');
                $(`#reserve-qty-error-${itemId}`).text('').css('display', 'none');
            }
        }

        // Prevent default HTML5 validation tooltip so custom red text error message is displayed
        $(document).on('show.bs.modal', '[id^="reserveModal-"]', function () {
            $(this).find('form').attr('novalidate', 'novalidate');
        });

        // Live validation on Qty to Reserve inputs
        $(document).on('input change keyup', '.reserve-qty-input', function () {
            validateReserveQty($(this));
        });

        // Intercept form submit and validate on submit button click
        $(document).on('submit', '[id^="reserveModal-"] form', function (e) {
            const $input = $(this).find('.reserve-qty-input');
            if ($input.length) {
                const isValid = validateReserveQty($input);
                if (!isValid) {
                    e.preventDefault();
                    e.stopPropagation();
                    $input.focus();
                    return false;
                }
            }
        });

        function validateReserveQty($input) {
            const itemId = $input.data('item-id') || $input.attr('id').replace('reserve-qty-input-', '');
            const $errorEl = $(`#reserve-qty-error-${itemId}`);
            const rawVal = $input.val();
            const val = parseFloat(rawVal);
            const maxVal = parseFloat($(`#reserve-max-label-${itemId}`).text());
            const effectiveMax = !isNaN(maxVal) ? maxVal : (parseFloat($input.attr('max')) || 0);

            if (isNaN(val) || rawVal === '' || rawVal === null) {
                $input.addClass('is-invalid');
                $errorEl.text('Quantity to reserve is required.').css('display', 'block');
                return false;
            } else if (val < 1) {
                $input.addClass('is-invalid');
                $errorEl.text('Minimum 1 unit required to reserve.').css('display', 'block');
                return false;
            } else if (val > effectiveMax) {
                $input.addClass('is-invalid');
                $errorEl.text(`Quantity cannot exceed max available limit of ${effectiveMax} unit(s).`).css('display', 'block');
                return false;
            } else {
                $input.removeClass('is-invalid');
                $errorEl.text('').css('display', 'none');
                return true;
            }
        }

        $(document).ready(function () {
            // Initialize Bootstrap Popovers for Warehouse Breakdown
            const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl, { sanitize: false });
            });

            @foreach ($delivery->items as $item)
                @php
                    $orderedQty2 = (float)($item->quantity_ordered > 0 ? $item->quantity_ordered : $item->quantity);
                    $pendingQty2 = (int)$item->pending_qty;
                @endphp
                $('#reserve-qty-input-{{ $item->id }}').data('pending', {{ (int)$pendingQty2 }});
            @endforeach
        });

        // ============================================================
        // Release for Dispatch Modal Logic (Stock Allocation & Gating)
        // ============================================================
        $(document).on('click', '#openReleaseModalBtn', function (e) {
            e.preventDefault();
            const modalEl = document.getElementById('releaseForDispatchModal');
            if (modalEl) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
        });

        const warehousesList = @json($warehouses->map(fn($w) => ['id' => $w->id, 'name' => $w->name]));

        $('#releaseForDispatchModal').on('show.bs.modal', function () {
            renderReleaseModalItems();
        });

        function renderReleaseModalItems() {
            const $tbody = $('#releaseModalItemsBody');
            $tbody.empty();

            let totalPhysicalItems = 0;
            let unreleasedItemsCount = 0;

            $('.mr-item-row').each(function () {
                const $row = $(this);
                const isService = $row.data('is-service') == '1';
                if (isService) return;

                totalPhysicalItems++;
                const itemId = $row.data('item-id');
                const productName = $row.data('product-name') || 'Product';
                const productSku = $row.data('sku') || '';
                const ordered = parseFloat($row.data('ordered')) || 0;
                const released = parseFloat($row.data('released')) || 0;
                const reserved = parseFloat($row.data('reserved')) || 0;
                const remainingToRelease = Math.max(0, ordered - released);
                const isAlreadyReleased = remainingToRelease <= 0;

                const breakdown = $row.data('warehouse-breakdown') || {};
                const currentWhId = $(`#warehouse-select-${itemId}`).val() || '1';

                const currentWhStock = breakdown[currentWhId] ? parseFloat(breakdown[currentWhId].available_qty || 0) : 0;
                const totalEffectiveStock = currentWhStock + reserved;

                const alternativeWarehouses = Object.values(breakdown).filter(w => w.warehouse_id != currentWhId && parseFloat(w.available_qty) > 0);

                let whOptionsHtml = '';
                warehousesList.forEach(w => {
                    const availInW = breakdown[w.id] ? Math.round(breakdown[w.id].available_qty || 0) : 0;
                    whOptionsHtml += `<option value="${w.id}" ${w.id == currentWhId ? 'selected' : ''}>${w.name} (${availInW} avail)</option>`;
                });

                if (isAlreadyReleased) {
                    // Item already fully released -> Disabled, cannot re-release
                    const rowHtml = `
                        <tr class="modal-item-row bg-soft-light text-muted" style="opacity: 0.8;" data-item-id="${itemId}" data-already-released="1">
                            <td class="text-center ps-3">
                                <input type="checkbox" class="form-check-input" disabled title="Already fully released">
                            </td>
                            <td>
                                <strong class="text-dark d-block fs-12">${productName}</strong>
                                ${productSku ? `<span class="text-muted font-monospace fs-10">SKU: ${productSku}</span>` : ''}
                            </td>
                            <td>
                                <select class="form-select form-select-sm fs-11" disabled>
                                    ${whOptionsHtml}
                                </select>
                            </td>
                            <td class="text-end fw-bold fs-12 text-muted">${ordered}</td>
                            <td class="text-end fw-bold fs-12 text-muted">${Math.round(currentWhStock)}</td>
                            <td class="text-end pe-2">
                                <span class="badge bg-soft-success text-success fw-bold fs-11">${released}/${ordered} Released</span>
                            </td>
                            <td class="text-center pe-3">
                                <span class="badge bg-soft-success text-success fs-11 px-2 py-1">
                                    <i class="feather-check-circle me-1"></i>Released
                                </span>
                            </td>
                        </tr>
                    `;
                    $tbody.append(rowHtml);
                } else {
                    unreleasedItemsCount++;
                    const maxReleasable = Math.min(remainingToRelease, totalEffectiveStock);
                    const isShortage = totalEffectiveStock <= 0;
                    const isReady = totalEffectiveStock >= remainingToRelease;

                    let suggestionHtml = '';
                    if (isShortage && alternativeWarehouses.length > 0) {
                        const bestAlt = alternativeWarehouses[0];
                        suggestionHtml = `
                            <div class="mt-1">
                                <span class="badge bg-soft-info text-info cursor-pointer quick-switch-wh-btn border border-info-subtle"
                                      data-item-id="${itemId}"
                                      data-target-wh="${bestAlt.warehouse_id}"
                                      style="cursor: pointer;"
                                      title="Click to switch sourcing warehouse to ${bestAlt.warehouse_name}">
                                    <i class="feather-shuffle me-1"></i>Switch to ${bestAlt.warehouse_name} (${Math.round(bestAlt.available_qty)} avail)
                                </span>
                            </div>`;
                    }

                    let statusBadgeHtml = '';
                    if (isShortage) {
                        statusBadgeHtml = `<span class="badge bg-soft-danger text-danger fs-11 px-2 py-1"><i class="feather-slash me-1"></i>0 Stock</span>`;
                    } else if (isReady) {
                        statusBadgeHtml = `<span class="badge bg-soft-success text-success fs-11 px-2 py-1"><i class="feather-check-circle me-1"></i>In Stock</span>`;
                    } else {
                        statusBadgeHtml = `<span class="badge bg-soft-warning text-warning fs-11 px-2 py-1"><i class="feather-alert-circle me-1"></i>Partial (${Math.round(totalEffectiveStock)}/${remainingToRelease})</span>`;
                    }

                    let releaseQtyInputHtml = '';
                    if (isShortage) {
                        releaseQtyInputHtml = `<input type="number" class="form-control form-control-sm text-end" value="0" disabled style="width: 80px; display: inline-block;">`;
                    } else {
                        releaseQtyInputHtml = `
                            <div class="d-inline-flex flex-column align-items-end">
                                <input type="number"
                                       name="release_quantities[${itemId}]"
                                       id="release-qty-${itemId}"
                                       class="form-control form-control-sm text-end fw-bold release-qty-input"
                                       value="${maxReleasable}"
                                       min="0.0001"
                                       max="${maxReleasable}"
                                       data-max="${maxReleasable}"
                                       data-remaining="${remainingToRelease}"
                                       data-item-id="${itemId}"
                                       step="any"
                                       style="width: 80px; display: inline-block;">
                                <span class="text-muted fs-10 mt-0.5">Rem: ${remainingToRelease}</span>
                            </div>
                        `;
                    }

                    const isChecked = !isShortage;

                    const rowHtml = `
                        <tr class="modal-item-row ${isShortage ? 'bg-soft-light' : ''}" data-item-id="${itemId}" data-remaining="${remainingToRelease}">
                            <td class="text-center ps-3">
                                <input type="checkbox"
                                       name="selected_items[]"
                                       value="${itemId}"
                                       class="form-check-input release-item-checkbox"
                                       ${isShortage ? 'disabled' : (isChecked ? 'checked' : '')}>
                            </td>
                            <td>
                                <strong class="text-dark d-block fs-12">${productName}</strong>
                                ${productSku ? `<span class="text-muted font-monospace fs-10">SKU: ${productSku}</span>` : ''}
                                ${released > 0 ? `<span class="badge bg-soft-info text-info fs-10 py-0.5 px-1.5 mt-0.5">Partially Released (${released}/${ordered})</span>` : ''}
                            </td>
                            <td>
                                <select name="item_warehouses[${itemId}]" class="form-select form-select-sm fs-11 modal-warehouse-select" data-item-id="${itemId}">
                                    ${whOptionsHtml}
                                </select>
                                ${suggestionHtml}
                            </td>
                            <td class="text-end fw-bold fs-12">${remainingToRelease}</td>
                            <td class="text-end fw-bold fs-12 ${currentWhStock > 0 ? 'text-success' : 'text-danger'}">
                                ${Math.round(currentWhStock)}
                                ${reserved > 0 ? `<small class="d-block text-muted fs-10">(+${Math.round(reserved)} res)</small>` : ''}
                            </td>
                            <td class="text-end pe-2">
                                ${releaseQtyInputHtml}
                            </td>
                            <td class="text-center pe-3">${statusBadgeHtml}</td>
                        </tr>
                    `;
                    $tbody.append(rowHtml);
                }
            });

            updateModalReleaseSummary(unreleasedItemsCount, totalPhysicalItems);
        }

        function updateModalReleaseSummary(unreleasedCount, totalPhysical) {
            unreleasedCount = typeof unreleasedCount !== 'undefined' ? unreleasedCount : $('.modal-item-row:not([data-already-released])').length;
            totalPhysical = typeof totalPhysical !== 'undefined' ? totalPhysical : $('.modal-item-row').length;

            const $selectedCheckboxes = $('.release-item-checkbox:checked:not(:disabled)');
            let validItemsCount = 0;
            let totalReleaseUnits = 0;
            let isAllRemainingFull = true;

            $selectedCheckboxes.each(function () {
                const itemId = $(this).val();
                const $row = $(this).closest('tr');
                const remaining = parseFloat($row.data('remaining')) || 0;
                const $qtyInput = $(`#release-qty-${itemId}`);
                const qtyVal = parseFloat($qtyInput.val()) || 0;

                if (qtyVal > 0) {
                    validItemsCount++;
                    totalReleaseUnits += qtyVal;
                    if (qtyVal < remaining) {
                        isAllRemainingFull = false;
                    }
                }
            });

            const $alertBanner = $('#releaseStockAlertBanner');
            const $alertIcon = $('#releaseAlertIcon');
            const $alertText = $('#releaseStockAlertText');
            const $submitBtn = $('#confirmReleaseBtn');
            const $summaryLabel = $('#releaseSelectionSummary');

            $summaryLabel.text(`${validItemsCount} item(s) selected (${totalReleaseUnits} units to release)`);

            if (validItemsCount === 0) {
                $alertBanner.removeClass('alert-soft-info alert-soft-success alert-soft-warning').addClass('alert-soft-danger');
                $alertIcon.removeClass('text-primary text-success text-warning').addClass('text-danger');
                $alertText.html('<strong>Stock Shortage / None Selected:</strong> No valid items with available stock selected. Please switch warehouse or raise an Indent.');
                $submitBtn.prop('disabled', true).removeClass('btn-success btn-primary').addClass('btn-secondary').html('<i class="feather-slash me-1.5"></i>Cannot Release (Shortage)');
            } else if (validItemsCount === unreleasedCount && isAllRemainingFull && unreleasedCount === totalPhysical) {
                $alertBanner.removeClass('alert-soft-info alert-soft-danger alert-soft-warning').addClass('alert-soft-success');
                $alertIcon.removeClass('text-primary text-danger text-warning').addClass('text-success');
                $alertText.html(`<strong>All Items Ready:</strong> Full stock is available for all items. Releasing ${totalReleaseUnits} units directly to dispatch floor.`);
                $submitBtn.prop('disabled', false).removeClass('btn-secondary btn-primary').addClass('btn-success').html(`<i class="feather-check-circle me-1.5"></i>Confirm Full Release (${validItemsCount} Items — ${totalReleaseUnits} Units)`);
            } else {
                $alertBanner.removeClass('alert-soft-info alert-soft-danger alert-soft-success').addClass('alert-soft-warning');
                $alertIcon.removeClass('text-primary text-danger text-success').addClass('text-warning');
                $alertText.html(`<strong>Partial Release:</strong> ${validItemsCount} item(s) (${totalReleaseUnits} units) will be released for dispatch. Remaining shortage quantities will stay in requirement queue.`);
                $submitBtn.prop('disabled', false).removeClass('btn-secondary btn-success').addClass('btn-primary').html(`<i class="feather-send me-1.5"></i>Confirm Partial Release (${validItemsCount} Items — ${totalReleaseUnits} Units)`);
            }
        }

        $(document).on('change', '#selectAllReleaseItems', function () {
            const isChecked = $(this).is(':checked');
            $('.release-item-checkbox:not(:disabled)').prop('checked', isChecked);
            updateModalReleaseSummary();
        });

        $(document).on('change', '.release-item-checkbox', function () {
            updateModalReleaseSummary();
        });

        $(document).on('input change keyup', '.release-qty-input', function () {
            const maxVal = parseFloat($(this).data('max')) || 0;
            let val = parseFloat($(this).val()) || 0;
            if (val > maxVal) {
                $(this).val(maxVal);
            } else if (val < 0) {
                $(this).val(0);
            }
            updateModalReleaseSummary();
        });

        $(document).on('click', '.quick-switch-wh-btn', function () {
            const itemId = $(this).data('item-id');
            const targetWhId = $(this).data('target-wh');

            const $mainSelect = $(`#warehouse-select-${itemId}`);
            if ($mainSelect.length) {
                $mainSelect.val(targetWhId);
            }

            changeWarehouse(itemId, { value: targetWhId });
            setTimeout(renderReleaseModalItems, 300);
        });

        $(document).on('change', '.modal-warehouse-select', function () {
            const itemId = $(this).data('item-id');
            const targetWhId = $(this).val();

            const $mainSelect = $(`#warehouse-select-${itemId}`);
            if ($mainSelect.length) {
                $mainSelect.val(targetWhId);
            }

            changeWarehouse(itemId, { value: targetWhId });
            setTimeout(renderReleaseModalItems, 300);
        });
    </script>
@endpush
