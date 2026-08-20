@extends('layouts.duralux')

@section('title', 'Material Requirement ' . $delivery->requirement_number . ' | SaaS ERP')
@section('page-title', 'Material Requirement ' . $delivery->requirement_number)
@section('breadcrumb', 'Sales / Material Requirements / ' . $delivery->requirement_number)

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button href="{{ route('sales.material-requirements.index') }}" variant="light" size="sm" class="border" icon="feather-arrow-left">
            Back
        </x-ui.button>
        <x-ui.button href="{{ route('sales.orders.show', $delivery->sales_order_id) }}" variant="light" size="sm" class="border" icon="feather-external-link">
            SO Details
        </x-ui.button>
        <x-ui.button href="{{ route('inventory.mrp-shortage.index', ['mr_ids' => [$delivery->id]]) }}" variant="warning" size="sm" class="fw-bold px-3 text-dark border me-1" icon="feather-cpu">
            MRP Shortage Analysis
        </x-ui.button>

        @if (in_array($delivery->status, ['Ready', 'Partially Ready', 'Processing', 'Picked', 'Packed']))
            <x-ui.button href="{{ route('sales.dispatches.create', ['material_requirement_id' => $delivery->id, 'sales_order_id' => $delivery->sales_order_id]) }}" variant="primary" size="sm" class="fw-bold px-3" icon="feather-truck">
                Create Dispatch
            </x-ui.button>
        @elseif ($delivery->status === 'Dispatched')
            <form action="{{ route('sales.material-requirements.deliver', $delivery->id) }}" method="POST" class="d-inline">
                @csrf
                <x-ui.button type="submit" variant="success" size="sm" class="fw-bold px-3" icon="feather-check-circle">
                    Mark Delivered
                </x-ui.button>
            </form>
        @endif

        <x-ui.action-dropdown id="mrActionsDropdown">
            <li>
                <a href="javascript:void(0)" onclick="window.print()" class="dropdown-item py-2">
                    <i class="feather-printer me-2 text-muted fs-12"></i>Print Requirement Sheet
                </a>
            </li>
            <li>
                <a href="{{ route('sales.orders.show', $delivery->sales_order_id) }}" class="dropdown-item py-2">
                    <i class="feather-shopping-cart me-2 text-muted fs-12"></i>View Sales Order #{{ $delivery->salesOrder->sales_order_number }}
                </a>
            </li>
        </x-ui.action-dropdown>
    </div>
@endsection

@push('styles')
    <style>
        .erp-single-panel {
            padding: 0 !important;
        }
        .mr-status-pipeline {
            display: inline-flex;
            align-items: center;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
        }
        .mr-status-pipeline .pipeline-step {
            position: relative;
            padding: 6px 14px 6px 22px;
            background-color: #f8fafc;
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s ease;
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
            border-top: 14px solid transparent;
            border-bottom: 14px solid transparent;
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
            border-top: 14px solid transparent;
            border-bottom: 14px solid transparent;
            border-left: 10px solid #ffffff;
            z-index: 5;
        }
        .mr-status-pipeline .pipeline-step:first-child::before {
            display: none;
        }
        .mr-status-pipeline .pipeline-step.active {
            background-color: #2563eb;
            color: #ffffff;
        }
        .mr-status-pipeline .pipeline-step.active::after {
            border-left-color: #2563eb;
        }
        .mr-status-pipeline .pipeline-step.completed {
            background-color: #e2e8f0;
            color: #334155;
        }
        .mr-status-pipeline .pipeline-step.completed::after {
            border-left-color: #e2e8f0;
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
        .mr-info-card {
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
    </style>
@endpush

@section('content')

    {{-- Session Alerts --}}
    @if (session('success'))
        <x-ui.alert variant="success" :dismissible="true" icon="feather-check-circle" class="shadow-sm mb-4">
            <strong>Success!</strong> {{ session('success') }}
        </x-ui.alert>
    @endif

    @if (session('error'))
        <x-ui.alert variant="danger" :dismissible="true" icon="feather-alert-triangle" class="shadow-sm mb-4">
            <strong>Error!</strong> {{ session('error') }}
        </x-ui.alert>
    @endif

    @if ($errors->any())
        <x-ui.alert variant="danger" :dismissible="true" icon="feather-alert-triangle" class="shadow-sm mb-4">
            <strong>Please fix the following errors:</strong>
            <ul class="mb-0 mt-1 ps-3">
                @foreach ($errors->all() as $error)
                    <li class="fs-12">{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    @php
        $doStatusClass = 'warning';
        if (in_array($delivery->status, ['Ready', 'Delivered']))               $doStatusClass = 'success';
        elseif (in_array($delivery->status, ['Partially Ready', 'Processing'])) $doStatusClass = 'info';
        elseif ($delivery->status === 'Pending' || $delivery->status === 'Draft') $doStatusClass = 'warning';
        elseif ($delivery->status === 'Dispatched')                             $doStatusClass = 'dark';
        elseif ($delivery->status === 'Cancelled')                              $doStatusClass = 'danger';
        elseif (in_array($delivery->status, ['Picked', 'Packed']))              $doStatusClass = 'primary';

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

    {{-- ERP Single Panel Odoo Sheet (Matching purchase/orders/show & sales/orders/show) --}}
    <div class="erp-single-panel">
        <x-ui.odoo-form-ui type="sheet" class="p-0">

            {{-- Top Status Bar (Pipeline + Quick Status Badge) --}}
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 px-4 py-2.5 bg-light border-bottom">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="fs-11 text-uppercase fw-bold text-muted letter-spacing-1 me-1">STATUS:</span>
                    <x-ui.badge :soft="true" :variant="$doStatusClass" class="fs-11 px-2.5 py-1 fw-bold me-2">
                        {{ $delivery->status }}
                    </x-ui.badge>

                    {{-- Quick KPI summary pills --}}
                    <div class="d-flex align-items-center gap-3 fs-12 text-muted ms-1">
                        <span><i class="feather-package text-primary me-1"></i>Order: <strong class="text-dark">{{ (int)$delivery->total_ordered_qty }} Units</strong></span>
                        <span><i class="feather-check-circle text-success me-1"></i>Reserved: <strong class="text-success">{{ (int)$delivery->total_reserved_qty }} Units</strong></span>
                        @if($delivery->total_dispatched_qty > 0)
                            <span><i class="feather-truck text-info me-1"></i>Dispatched: <strong class="text-info">{{ (int)$delivery->total_dispatched_qty }} Units</strong></span>
                        @endif
                        <span><i class="feather-clock text-warning me-1"></i>Pending: <strong class="{{ $delivery->total_pending_qty > 0 ? 'text-warning' : 'text-muted' }}">{{ (int)$delivery->total_pending_qty }} Units</strong></span>
                        <span class="badge bg-soft-primary text-primary px-2 py-0.5 font-monospace fs-11">{{ $delivery->fulfillment_rate }}% Fulfilled</span>
                    </div>
                </div>

                {{-- Chevron Status Pipeline --}}
                <div class="mr-status-pipeline d-none d-md-inline-flex">
                    <div class="pipeline-step {{ $statusStep > 1 ? 'completed' : ($statusStep === 1 ? 'active' : '') }}">
                        1. DRAFT
                    </div>
                    <div class="pipeline-step {{ $statusStep > 2 ? 'completed' : ($statusStep === 2 ? 'active' : '') }}">
                        2. PROCESSING
                    </div>
                    <div class="pipeline-step {{ $statusStep > 3 ? 'completed' : ($statusStep === 3 ? 'active' : '') }}">
                        3. READY
                    </div>
                    <div class="pipeline-step {{ $statusStep > 4 ? 'completed' : ($statusStep === 4 ? 'active' : '') }}">
                        4. DISPATCHED
                    </div>
                    <div class="pipeline-step {{ $statusStep === 5 ? 'active' : '' }}">
                        5. DELIVERED
                    </div>
                </div>
            </div>

        {{-- Document Header Metadata + 3 Compact Right Stat Boxes --}}
        <div class="p-4 border-bottom">
            <div class="row align-items-center justify-content-between g-4">
                    <div class="col-md-7">
                        <div class="d-flex align-items-start gap-3">
                            <div class="avatar-text avatar-xl bg-soft-primary text-primary rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                                <i class="feather-clipboard fs-3"></i>
                            </div>
                            <div>
                                <span class="fs-10 text-uppercase fw-bold text-muted letter-spacing-1 d-block mb-1">MATERIAL REQUIREMENT DOCUMENT</span>
                                <h3 class="fw-bold text-dark mb-2">{{ $delivery->requirement_number }}</h3>
                                
                                <div class="d-flex align-items-center flex-wrap gap-x-4 gap-y-2 fs-13 text-muted">
                                    <div>
                                        <i class="feather-shopping-bag text-primary me-1"></i>
                                        Sales Order: 
                                        <a href="{{ route('sales.orders.show', $delivery->sales_order_id) }}" class="fw-bold text-primary">
                                            {{ $delivery->salesOrder->sales_order_number }}
                                        </a>
                                    </div>
                                    <div>
                                        <i class="feather-user me-1 text-muted"></i>
                                        Customer: <strong class="text-dark">{{ $delivery->salesOrder->customer?->name ?? 'Walk-in Customer' }}</strong>
                                    </div>
                                    <div>
                                        <i class="feather-calendar me-1 text-muted"></i>
                                        Date: <strong class="text-dark">{{ $delivery->requirement_date ? $delivery->requirement_date->format('d M Y') : $delivery->created_at->format('d M Y') }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>                   
                </div>
        </div>

        {{-- Material Requirement Lines Section --}}
        <div class="p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="fw-bold text-dark mb-0 fs-14">
                            <i class="feather-list me-2 text-primary"></i>Material Requirement Lines
                        </h6>
                        <span class="badge bg-soft-primary text-primary fw-bold px-2.5 py-1 fs-12">
                            {{ count($delivery->items) }} Item(s)
                        </span>
                    </div>

                    <div class="table-responsive" style="overflow: visible !important;">
                        <x-ui.odoo-form-ui type="table" class="align-middle fs-13 mb-0 mr-line-table" style="margin-top:0;">
                            <thead class="fs-11 text-uppercase fw-bold text-muted">
                                <tr>
                                    <th style="width:25%" class="ps-4">Product Details</th>
                                    <th style="width:9%" class="text-center">Method</th>
                                    <th style="width:8%" class="text-end">Order Qty</th>
                                    <th style="width:8%" class="text-end">Reserved</th>
                                    <th style="width:8%" class="text-end">Pending</th>
                                    <th style="width:18%">Warehouse</th>
                                    <th style="width:7%" class="text-end">Avail.</th>
                                    <th style="width:10%" class="text-center">Status</th>
                                    <th style="width:11%" class="text-center pe-4">Action</th>
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
                                                    <option value="">Select Warehouse...</option>
                                                    <option value="__ADD_NEW__" class="fw-bold text-primary" data-master="warehouse">+ Add New Warehouse</option>
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
                                                        {{-- Reserve Button (always rendered in wrapper so JS can toggle display dynamically on warehouse change) --}}
                                                        <div id="reserve-btn-wrap-{{ $item->id }}" class="w-100 {{ $availableQty > 0 ? '' : 'd-none' }}" style="{{ $availableQty > 0 ? '' : 'display: none;' }}">
                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-soft-primary px-2 py-1 fs-11 fw-bold w-100"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#reserveModal-{{ $item->id }}"
                                                            ><i class="feather-archive me-1"></i>Reserve</button>
                                                        </div>

                                                        @if ($method === 'buy' || $method === 'trade')
                                                            @php
                                                                $prRaised = (float)($item->quantity_pr_raised ?? 0);
                                                                $remainingPrQty = $item->remaining_pr_qty;
                                                            @endphp

                                                            @if ($item->status === 'Waiting Purchase' || ($prRaised > 0 && $remainingPrQty <= 0))
                                                                <span class="badge bg-soft-warning text-warning px-2 py-1 fs-11 w-100 text-center fw-semibold">
                                                                    <i class="feather-check-circle me-1"></i>PR Raised ({{ (int)$prRaised }}/{{ (int)$pendingQty }})
                                                                </span>
                                                            @elseif ($prRaised > 0 && $remainingPrQty > 0)
                                                                <span class="badge bg-soft-info text-info px-2 py-1 fs-11 w-100 text-center mb-1 fw-semibold">
                                                                    <i class="feather-clock me-1"></i>PR Raised ({{ (int)$prRaised }}/{{ (int)$pendingQty }})
                                                                </span>
                                                                <button
                                                                    type="button"
                                                                    class="btn btn-sm btn-soft-warning px-2 py-1 fs-11 fw-bold w-100"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#indentModal-{{ $item->id }}"
                                                                ><i class="feather-plus-circle me-1"></i>Create Indent (+{{ (int)$remainingPrQty }})</button>
                                                            @else
                                                                <button
                                                                    type="button"
                                                                    class="btn btn-sm btn-soft-warning px-2 py-1 fs-11 fw-bold w-100"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#indentModal-{{ $item->id }}"
                                                                ><i class="feather-file-text me-1"></i>Indent</button>
                                                            @endif
                                                        @elseif ($method === 'manufacture')
                                                            @if ($item->status === 'Waiting Production')
                                                                <x-ui.badge :soft="true" variant="warning" class="fs-11 px-2 py-1 w-100 text-center fw-semibold">
                                                                    <i class="feather-clock me-1"></i>MO Raised
                                                                </x-ui.badge>
                                                            @else
                                                                <button
                                                                    type="button"
                                                                    class="btn btn-sm btn-soft-danger px-2 py-1 fs-11 fw-bold w-100"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#generateMoModal-{{ $item->id }}"
                                                                ><i class="feather-cpu me-1"></i>Gen MO</button>
                                                            @endif
                                                        @endif
                                                    @else
                                                        <x-ui.badge :soft="true" variant="success" class="fs-11 px-2.5 py-1 fw-bold">
                                                            <i class="feather-check-circle me-1"></i>Fulfilled
                                                        </x-ui.badge>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-muted fs-12 fw-semibold">
                                                    <i class="feather-lock me-1"></i>Locked
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>
                </div>

        </div>{{-- /p-4 lines section --}}
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
                title="Reserve Stock — {{ $item->product?->name }}"
                submitText="Confirm Reservation"
                formAction="{{ route('sales.material-requirements.reserve-qty', $item->id) }}"
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
                        <label class="odoo-form-label" for="reserve-warehouse-{{ $item->id }}">Warehouse</label>
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
                                <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">Available</span>
                                <span id="reserve-modal-avail-{{ $item->id }}" class="fs-16 fw-bold text-success">{{ (int)$availableQty }}</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light rounded p-2 text-center border">
                                <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">Order Qty</span>
                                <span class="fs-16 fw-bold text-dark">{{ (int)$orderedQty }}</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light rounded p-2 text-center border">
                                <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">Pending</span>
                                <span class="fs-16 fw-bold text-danger">{{ (int)$pendingQty }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Qty to Reserve --}}
                    <div class="odoo-form-group mb-0">
                        <label class="odoo-form-label" for="reserve-qty-input-{{ $item->id }}">
                            Qty to Reserve <span class="text-danger">*</span>
                        </label>
                        <div class="flex-grow-1">
                            <input
                                type="number"
                                name="quantity_reserve"
                                id="reserve-qty-input-{{ $item->id }}"
                                class="odoo-form-control reserve-qty-input"
                                data-item-id="{{ $item->id }}"
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
                    title="Create Purchase Indent — {{ $item->product?->name }}"
                    submitText="Submit Indent Request"
                    formAction="{{ route('sales.material-requirements.mock-indent', $item->id) }}"
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
                            This will raise a <strong>Purchase Indent</strong> for the required procurement quantity.
                        </x-ui.alert>

                        <div class="row g-2 mb-3">
                            <div class="col-3">
                                <div class="bg-light rounded p-2 text-center border">
                                    <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">Order Qty</span>
                                    <span class="fs-15 fw-bold text-dark">{{ (int)$orderedQty }}</span>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="bg-light rounded p-2 text-center border">
                                    <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">Reserved</span>
                                    <span class="fs-15 fw-bold text-success">{{ (int)$reservedQty }}</span>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="bg-light rounded p-2 text-center border">
                                    <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">PR Raised</span>
                                    <span class="fs-15 fw-bold text-info">{{ (int)$prRaised }}</span>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="bg-light rounded p-2 text-center border">
                                    <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">Remaining</span>
                                    <span class="fs-15 fw-bold text-danger">{{ (int)$remainingPrQty }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="odoo-form-group">
                            <label class="odoo-form-label">Qty to Indent <span class="text-danger">*</span></label>
                            <div class="flex-grow-1">
                                <input type="number" class="odoo-form-control" name="quantity_request" min="1" max="{{ (int)$remainingPrQty }}" value="{{ (int)$remainingPrQty }}" required>
                                <div class="text-muted fs-11 mt-1">Pre-filled with remaining un-indented quantity ({{ (int)$remainingPrQty }} unit(s)). You can adjust.</div>
                            </div>
                        </div>

                        <div class="odoo-form-group">
                            <label class="odoo-form-label">Destination Warehouse <span class="text-danger">*</span></label>
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
                            <label class="odoo-form-label">Expected Date</label>
                            <div class="flex-grow-1">
                                <input type="date" class="odoo-form-control" name="expected_date">
                                <div class="text-muted fs-11 mt-1">Target date by which required items should arrive.</div>
                            </div>
                        </div>

                        <div class="odoo-form-group mb-0">
                            <label class="odoo-form-label">Notes</label>
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
                title="Generate Manufacturing Order — {{ $item->product?->name }}"
                submitText="Raise MO Request"
                formAction="{{ route('sales.material-requirements.mock-mo', $item->id) }}"
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
                        This will create a <strong>Manufacturing Order</strong> for the required quantity.
                    </x-ui.alert>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="bg-light rounded p-2 text-center border">
                                <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">Order Qty</span>
                                <span class="fs-16 fw-bold text-dark">{{ (int)$orderedQty }}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-light rounded p-2 text-center border">
                                <span class="fs-10 text-muted d-block fw-semibold text-uppercase mb-1">To Manufacture</span>
                                <span class="fs-16 fw-bold text-danger">{{ (int)$pendingQty }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="odoo-form-group">
                        <label class="odoo-form-label">Qty to Mfg <span class="text-danger">*</span></label>
                        <div class="flex-grow-1">
                            <input type="number" name="quantity_mfg" class="odoo-form-control" value="{{ (int)$pendingQty }}" min="1" required>
                        </div>
                    </div>

                    <div class="odoo-form-group mb-0">
                        <label class="odoo-form-label">Notes / Priority</label>
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
            formAction="{{ route('sales.material-requirements.dispatch', $delivery->id) }}"
            submitText="Confirm Dispatch"
            :centered="true"
        >
            <div class="fs-13 text-dark">
                <div class="odoo-form-group">
                    <label class="odoo-form-label">Carrier <span class="text-danger">*</span></label>
                    <div class="flex-grow-1">
                        <input type="text" name="carrier" class="odoo-form-control" placeholder="e.g. DHL, BlueDart, SafeExpress" required>
                    </div>
                </div>
                <div class="odoo-form-group">
                    <label class="odoo-form-label">Tracking No. <span class="text-danger">*</span></label>
                    <div class="flex-grow-1">
                        <input type="text" name="tracking_number" class="odoo-form-control" placeholder="e.g. TRK983742 or MH-12-XX-XXXX" required>
                    </div>
                </div>
                <div class="odoo-form-group mb-0">
                    <label class="odoo-form-label">Notes</label>
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
            const warehouseId = select.value;
            const url = `{{ url('sales/material-requirements/items') }}/${itemId}/warehouse`;

            $.ajax({
                url:    url,
                method: 'POST',
                data:   { _token: '{{ csrf_token() }}', warehouse_id: warehouseId },
                success: function (response) {
                    if (!response.success) return;
                    const avail = parseInt(response.available_qty) || 0;
                    $(`#available-qty-${itemId}`).text(avail);

                    const $reserveSelect = $(`#reserve-warehouse-${itemId}`);
                    if ($reserveSelect.length) $reserveSelect.val(warehouseId);

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
            if ($mainSelect.length) $mainSelect.val(select.value);

            updateReserveAvailableFromQty(itemId, avail);

            $.ajax({
                url:    `{{ url('sales/material-requirements/items') }}/${itemId}/warehouse`,
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
            $(`#available-qty-${itemId}`).text(avail);

            // Dynamically show or hide the Reserve button based on stock in selected warehouse
            const $reserveWrap = $(`#reserve-btn-wrap-${itemId}`);
            if ($reserveWrap.length) {
                if (avail > 0) {
                    $reserveWrap.removeClass('d-none').show();
                } else {
                    $reserveWrap.addClass('d-none').hide();
                }
            }

            const ordered = parseInt($(`#reserve-modal-avail-${itemId}`).closest('.modal').find('.fs-16.text-dark').first().text()) || 0;
            const $availCell = $(`#available-qty-${itemId}`);
            $availCell.removeClass('text-success text-danger');
            $availCell.addClass(avail >= ordered ? 'text-success' : 'text-danger');

            const $input = $(`#reserve-qty-input-${itemId}`);
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
