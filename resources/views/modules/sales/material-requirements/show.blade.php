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
        @if (!in_array($delivery->status, ['Delivered', 'Cancelled']))
            <x-ui.button href="{{ route('sales.dispatches.create', ['material_requirement_id' => $delivery->id, 'sales_order_id' => $delivery->sales_order_id]) }}" variant="primary" class="fw-bold px-3" icon="feather-truck">
                {{ __('crm.dispatch_order') }}
            </x-ui.button>
        @endif

        @if ($delivery->status === 'Dispatched')
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
        if (in_array($delivery->status, ['Ready', 'Delivered']))               $doStatusClass = 'success';
        elseif (in_array($delivery->status, ['Partially Ready', 'Processing'])) $doStatusClass = 'info';
        elseif ($delivery->status === 'Pending' || $delivery->status === 'Draft') $doStatusClass = 'warning';
        elseif ($delivery->status === 'Dispatched')                             $doStatusClass = 'dark';
        elseif ($delivery->status === 'Cancelled')                              $doStatusClass = 'danger';
        elseif (in_array($delivery->status, ['Picked', 'Packed']))              $doStatusClass = 'primary';

        $statusBadgeClass = match($delivery->status) {
            'Ready', 'Delivered' => 'bg-soft-success text-success',
            'Partially Ready', 'Processing' => 'bg-soft-info text-info',
            'Pending', 'Draft' => 'bg-soft-warning text-warning',
            'Dispatched' => 'bg-soft-dark text-dark',
            'Cancelled' => 'bg-soft-danger text-danger',
            'Picked', 'Packed' => 'bg-soft-primary text-primary',
            default => 'bg-soft-warning text-warning',
        };

        $totalOrdered = (float)$delivery->items->sum(fn($i) => (float)($i->quantity_ordered > 0 ? $i->quantity_ordered : $i->quantity));
        $totalReserved = (float)$delivery->items->sum('quantity_reserved');
        $totalDispatched = (float)$delivery->items->sum(fn($i) => (float)$i->dispatched_qty);
        $totalPending = max(0, $totalOrdered - $totalDispatched - $totalReserved);
        $fulfillmentRate = $totalOrdered > 0 ? round((($totalReserved + $totalDispatched) / $totalOrdered) * 100) : 0;

        $statusStep = match($delivery->status) {
            'Draft', 'Pending' => 1,
            'Processing', 'Partially Ready' => 2,
            'Ready', 'Picked', 'Packed' => 3,
            'Dispatched' => 4,
            'Delivered' => 5,
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
                            1 => 'Draft',
                            2 => 'Processing',
                            3 => 'Ready',
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
                <div class="col-sm-6 col-lg-3">
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

                <!-- KPI 2: Reserved Stock -->
                <div class="col-sm-6 col-lg-3">
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

                <!-- KPI 3: Pending Shortage -->
                <div class="col-sm-6 col-lg-3">
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

                <!-- KPI 4: Fulfillment Progress -->
                <div class="col-sm-6 col-lg-3">
                    <div class="mr-metric-box h-100 shadow-2xs">
                        <div class="d-flex align-items-center justify-content-between mb-1.5">
                            <span class="text-muted fs-11 text-uppercase fw-bold">{{ __('crm.fulfilled') }}</span>
                            <div class="avatar-text avatar-xs bg-soft-info text-info rounded-circle">
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
                                    <th style="width:25%" class="ps-4">{{ __('crm.product_details') }}</th>
                                    <th style="width:9%" class="text-center">{{ __('crm.method') }}</th>
                                    <th style="width:8%" class="text-end">{{ __('crm.order_qty_th') }}</th>
                                    <th style="width:8%" class="text-end">{{ __('crm.reserved_th') }}</th>
                                    <th style="width:8%" class="text-end">{{ __('crm.pending_th') }}</th>
                                    <th style="width:18%">{{ __('crm.warehouse_th') }}</th>
                                    <th style="width:7%" class="text-end">{{ __('crm.avail_th') }}</th>
                                    <th style="width:10%" class="text-center">{{ __('crm.status_th') }}</th>
                                    <th style="width:11%" class="text-center pe-4">{{ __('crm.action_th') }}</th>
                                </tr>
                            </thead>
                            <tbody class="text-dark">
                                @foreach ($delivery->items as $item)
                                    @php
                                        $method      = strtolower($item->product?->supplier_method ?? 'trade');
                                        $isService   = $item->product?->item_type === 'Service';
                                        $orderedQty    = (float)($item->quantity_ordered > 0 ? $item->quantity_ordered : $item->quantity);
                                        $reservedQty   = (float)$item->quantity_reserved;
                                        $dispatchedQty = $item->dispatched_qty;
                                        $pendingQty    = $item->pending_qty;
                                        $availableQty  = (float)($item->available_qty ?? 0);
                                        $isLocked      = in_array($delivery->status, ['Dispatched', 'Delivered', 'Cancelled']);
                                        $displayStatus = $item->calculated_status;

                                        $lineBadge = 'secondary';
                                        if (in_array($displayStatus, ['Reserved', 'Ready']))  $lineBadge = 'success';
                                        elseif ($displayStatus === 'Waiting Purchase')         $lineBadge = 'warning';
                                        elseif ($displayStatus === 'Partially PR Raised')      $lineBadge = 'info';
                                        elseif ($displayStatus === 'Waiting Production')       $lineBadge = 'danger';
                                        elseif (in_array($displayStatus, ['Partially Reserved', 'Partially Dispatched'])) $lineBadge = 'info';
                                        elseif ($displayStatus === 'Picked')                   $lineBadge = 'primary';
                                        elseif ($displayStatus === 'Packed')                   $lineBadge = 'info';
                                        elseif (in_array($displayStatus, ['Dispatched','Delivered'])) $lineBadge = 'dark';
                                    @endphp
                                    <tr>
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
                                        <td class="text-end fw-bold text-success fs-13">{{ (int)$reservedQty }}</td>
                                        <td class="text-end fw-semibold {{ $pendingQty > 0 ? 'text-warning' : 'text-muted' }} fs-13">{{ (int)$pendingQty }}</td>
                                        
                                        {{-- Warehouse Dropdown --}}
                                        <td>
                                            @if ($isService)
                                                <span class="text-muted">—</span>
                                            @else
                                                <select
                                                    id="warehouse-select-{{ $item->id }}"
                                                    class="form-select form-select-sm border-gray-300 fs-12"
                                                    onchange="changeWarehouse({{ $item->id }}, this)"
                                                    {{ $isLocked ? 'disabled' : '' }}
                                                    data-select2-selector="default"
                                                    data-master="warehouse"
                                                    style="width: 100%;"
                                                >
                                                    <option value="">{{ __('crm.select_warehouse') }}</option>
                                                    <option value="__ADD_NEW__" class="fw-bold text-primary" data-master="warehouse">+ {{ __('crm.add_new_warehouse') }}</option>
                                                    @foreach ($warehouses as $w)
                                                        <option value="{{ $w->id }}" {{ ($item->warehouse_id ?: $defaultWarehouseId) == $w->id ? 'selected' : '' }}>
                                                            {{ $w->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
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
                                                <span class="text-muted fs-12 fw-semibold">
                                                    <i class="feather-lock me-1"></i>{{ __('crm.locked') }}
                                                </span>
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
            @foreach ($delivery->items as $item)
                @php
                    $orderedQty2 = (float)($item->quantity_ordered > 0 ? $item->quantity_ordered : $item->quantity);
                    $pendingQty2 = (int)$item->pending_qty;
                @endphp
                $('#reserve-qty-input-{{ $item->id }}').data('pending', {{ (int)$pendingQty2 }});
            @endforeach
        });
    </script>
@endpush
