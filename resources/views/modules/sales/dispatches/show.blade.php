@extends('layouts.duralux')

@section('title', 'Dispatch Order ' . $dispatch->dispatch_number . ' | SaaS ERP')
@section('page-title', 'Dispatch Order ' . $dispatch->dispatch_number)
@section('breadcrumb', 'Sales / Dispatches / ' . $dispatch->dispatch_number)

@section('content')

    {{-- Header Card --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-4">
                <div>
                    <span class="fs-12 text-muted text-uppercase fw-bold d-block mb-1">Dispatch Shipment</span>
                    <h3 class="fw-bold text-dark mb-1">{{ $dispatch->dispatch_number }}</h3>
                    <span class="fs-13 text-muted">
                        Material Requirement: 
                        @if($dispatch->material_requirement_id && $dispatch->materialRequirement)
                            <a href="{{ route('sales.material-requirements.show', $dispatch->material_requirement_id) }}" class="fw-bold text-primary">{{ $dispatch->materialRequirement->requirement_number }}</a>
                        @else
                            <span class="badge bg-soft-success text-success font-monospace">Direct Outward Dispatch</span>
                        @endif
                        | Sales Order: 
                        @if($dispatch->sales_order_id && $dispatch->salesOrder)
                            <a href="{{ route('sales.orders.show', $dispatch->sales_order_id) }}" class="fw-bold text-info">{{ $dispatch->salesOrder->sales_order_number }}</a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                        | Customer: <strong class="text-dark">{{ $dispatch->customer?->name ?? 'Direct / Walk-in Customer' }}</strong>
                    </span>
                </div>

                <div class="d-flex gap-3 flex-wrap align-items-center">
                    @php
                        $statusClass = 'bg-soft-secondary text-secondary';
                        if ($dispatch->status === 'Dispatched') $statusClass = 'bg-soft-info text-info';
                        elseif ($dispatch->status === 'Delivered') $statusClass = 'bg-soft-success text-success';
                        elseif ($dispatch->status === 'Invoiced') $statusClass = 'bg-soft-dark text-dark';
                    @endphp
                    <span class="badge {{ $statusClass }} px-3 py-2 fs-12 fw-semibold">{{ $dispatch->status }}</span>
                    <small class="text-muted">{{ $dispatch->dispatch_date ? $dispatch->dispatch_date->format('d M Y') : '—' }}</small>
                </div>

                <div class="d-flex gap-2">
                    @if ($dispatch->status === 'Pending')
                        <form action="{{ route('sales.dispatches.confirm', $dispatch->id) }}" method="POST" id="confirmDispatchForm">
                            @csrf
                            <button type="button" class="btn btn-success fw-bold px-3" onclick="confirmAction({ title: 'Confirm & Dispatch Shipment', message: 'Confirm dispatch for {{ $dispatch->dispatch_number }}? This will deduct finished stock from warehouse.', variant: 'success', confirmText: 'Confirm Dispatch' }, function() { document.getElementById('confirmDispatchForm').submit(); })">
                                <i class="feather-check-circle me-1.5"></i> Confirm & Dispatch
                            </button>
                        </form>
                    @elseif (in_array($dispatch->status, ['Dispatched', 'Delivered']) && $dispatch->material_requirement_id)
                        <a href="{{ route('sales.invoices.create', ['dispatch_order_id' => $dispatch->id, 'material_requirement_id' => $dispatch->material_requirement_id, 'mode' => 'dispatch_order']) }}" class="btn btn-primary fw-bold px-3">
                            <i class="feather-file-text me-1.5"></i> Create Invoice
                        </a>
                    @endif
                    @if($dispatch->material_requirement_id)
                        <a href="{{ route('sales.material-requirements.show', $dispatch->material_requirement_id) }}" class="btn btn-light border">
                            <i class="feather-arrow-left me-2"></i>Back to MR
                        </a>
                    @endif
                    <a href="{{ route('sales.dispatches.index') }}" class="btn btn-light border">
                        <i class="feather-list me-2"></i>All Dispatches
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 align-items-stretch">
        {{-- Items Table --}}
        <div class="col-lg-8 d-flex flex-column">
            <div class="card border-0 shadow-sm flex-grow-1 d-flex flex-column h-100">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h6 class="card-title mb-0 fw-bold text-dark">
                        <i class="feather-list me-2 text-primary"></i>Dispatched Items
                    </h6>
                </div>
                <div class="card-body p-0 flex-grow-1 position-relative" style="min-height: 0;">
                    <div class="table-responsive h-100" style="overflow-y: auto;">
                        <table class="table align-middle fs-13 mb-0">
                            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                                <tr>
                                    <th class="ps-4">Product Details</th>
                                    <th>Warehouse</th>
                                    <th class="text-end">Order Qty</th>
                                    <th class="text-end pe-4">Dispatched Qty</th>
                                </tr>
                            </thead>
                            <tbody class="text-dark">
                                @forelse ($dispatch->items as $item)
                                    <tr>
                                        <td class="ps-4">
                                            <strong>{{ $item->product?->name }}</strong>
                                            @if ($item->product?->sku)
                                                <small class="text-muted d-block font-monospace fs-10">SKU: {{ $item->product->sku }}</small>
                                            @endif
                                        </td>
                                        <td>{{ $item->warehouse?->name ?? '—' }}</td>
                                        <td class="text-end fw-semibold">{{ (int)$item->quantity_ordered }}</td>
                                        <td class="text-end fw-bold text-success pe-4">{{ (int)$item->quantity_dispatched }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">No items found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
            {{-- Carrier Info --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h6 class="card-title mb-0 fw-bold text-dark">
                        <i class="feather-truck me-2 text-primary"></i>Transport Details
                    </h6>
                </div>
                <div class="card-body p-4 fs-13 text-dark">
                    <div class="mb-3">
                        <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">Carrier</span>
                        <strong>{{ $dispatch->carrier ?: '—' }}</strong>
                    </div>
                    <div class="mb-3">
                        <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">Tracking Number</span>
                        <strong>{{ $dispatch->tracking_number ?: '—' }}</strong>
                    </div>
                    <div class="mb-3">
                        <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">Vehicle Number</span>
                        <strong>{{ $dispatch->vehicle_number ?: '—' }}</strong>
                    </div>
                    <div class="mb-3">
                        <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">Driver</span>
                        <strong>{{ $dispatch->driver_name ?: '—' }}</strong>
                        @if ($dispatch->driver_phone)
                            <small class="text-muted d-block">{{ $dispatch->driver_phone }}</small>
                        @endif
                    </div>
                    @if ($dispatch->notes)
                        <div class="mb-0">
                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">Notes</span>
                            <p class="text-muted mb-0 fs-12">{{ $dispatch->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- DO Summary --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h6 class="card-title mb-0 fw-bold text-dark">
                        <i class="feather-info me-2 text-primary"></i>Delivery Summary
                    </h6>
                </div>
                <div class="card-body p-4 fs-13 text-dark">
                    <div class="mb-2 d-flex justify-content-between">
                        <span class="text-muted">Dispatch #</span>
                        <strong>{{ $dispatch->dispatch_number }}</strong>
                    </div>
                    <div class="mb-2 d-flex justify-content-between">
                        <span class="text-muted">Dispatch Date</span>
                        <strong>{{ $dispatch->dispatch_date ? $dispatch->dispatch_date->format('d M Y') : '—' }}</strong>
                    </div>
                    <div class="mb-2 d-flex justify-content-between">
                        <span class="text-muted">Material Requirement</span>
                        @if ($dispatch->material_requirement_id && $dispatch->materialRequirement)
                            <a href="{{ route('sales.material-requirements.show', $dispatch->material_requirement_id) }}" class="fw-bold text-primary">
                                {{ $dispatch->materialRequirement->requirement_number }}
                            </a>
                        @else
                            <span class="badge bg-soft-success text-success font-monospace">Direct Outward Dispatch</span>
                        @endif
                    </div>
                    <div class="mb-2 d-flex justify-content-between">
                        <span class="text-muted">Total Items</span>
                        <strong>{{ $dispatch->items->count() }}</strong>
                    </div>
                    <div class="mb-0 d-flex justify-content-between">
                        <span class="text-muted">Status</span>
                        <span class="badge {{ $statusClass }} fs-11">{{ $dispatch->status }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
