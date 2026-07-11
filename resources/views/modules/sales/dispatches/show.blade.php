@extends('layouts.duralux')

@section('title', 'Dispatch Order ' . $dispatch->dispatch_number . ' | SaaS ERP')
@section('page-title', 'Dispatch Order ' . $dispatch->dispatch_number)
@section('breadcrumb', 'Sales / Dispatches / ' . $dispatch->dispatch_number)

@section('content')
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-success text-white me-3">
                    <i class="feather-check-circle"></i>
                </div>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Success!</h6>
                    <p class="fs-12 mb-0">{{ session('success') }}</p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Header Card --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-4">
                <div>
                    <span class="fs-12 text-muted text-uppercase fw-bold d-block mb-1">Dispatch Shipment</span>
                    <h3 class="fw-bold text-dark mb-1">{{ $dispatch->dispatch_number }}</h3>
                    <span class="fs-13 text-muted">
                        Delivery Order: <a href="{{ route('sales.deliveries.show', $dispatch->delivery_order_id) }}" class="fw-bold text-primary">{{ $dispatch->deliveryOrder->delivery_number }}</a>
                        | Sales Order: <a href="{{ route('sales.orders.show', $dispatch->sales_order_id) }}" class="fw-bold text-info">{{ $dispatch->salesOrder->sales_order_number }}</a>
                        | Customer: <strong class="text-dark">{{ $dispatch->salesOrder->customer?->name }}</strong>
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
                    <small class="text-muted">{{ $dispatch->dispatch_date->format('d M Y') }}</small>
                </div>

                <div class="d-flex gap-2">
                    <a href="{{ route('sales.deliveries.show', $dispatch->delivery_order_id) }}" class="btn btn-light border">
                        <i class="feather-arrow-left me-2"></i>Back to DO
                    </a>
                    <a href="{{ route('sales.dispatches.index') }}" class="btn btn-light border">
                        <i class="feather-list me-2"></i>All Dispatches
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Items Table --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h6 class="card-title mb-0 fw-bold text-dark">
                        <i class="feather-list me-2 text-primary"></i>Dispatched Items
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
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
                        <strong>{{ $dispatch->dispatch_date->format('d M Y') }}</strong>
                    </div>
                    <div class="mb-2 d-flex justify-content-between">
                        <span class="text-muted">Delivery Order</span>
                        <a href="{{ route('sales.deliveries.show', $dispatch->delivery_order_id) }}" class="fw-bold text-primary">
                            {{ $dispatch->deliveryOrder->delivery_number }}
                        </a>
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
