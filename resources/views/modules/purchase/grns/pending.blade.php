@extends('layouts.duralux')

@section('title', 'Pending Goods Receipts | SaaS ERP')
@section('page-title', 'Pending Goods Receipts')
@section('breadcrumb', 'Purchase / Goods Receipt Notes / Pending Receipts')

@push('styles')
    <style>
        .action-icon-btn {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 32px !important;
            height: 32px !important;
            border-radius: 8px !important;
            border: 1.5px solid #cbd5e1 !important;
            background-color: #ffffff !important;
            color: #475569 !important;
            transition: all 0.28s ease !important;
            text-decoration: none !important;
            cursor: pointer !important;
        }
        .action-icon-btn.grn-btn:hover {
            background-color: color-mix(in srgb, var(--bs-primary) 10%, transparent) !important;
            border-color: var(--bs-primary) !important;
            color: var(--bs-primary) !important;
        }
    </style>
@endpush

@section('page-actions')
    <div class="d-flex gap-2 flex-wrap">
        <x-ui.button href="{{ route('purchase.grns.index') }}" variant="light" icon="feather-list" class="border">
            All Goods Receipts
        </x-ui.button>
        <x-ui.button href="{{ route('purchase.grns.create') }}" variant="primary" icon="feather-plus" style="background-color: var(--bs-primary); border-color: var(--bs-primary);">
            New Goods Receipt
        </x-ui.button>
    </div>
@endsection

@section('content')
    @php
        $currency = tenant()?->settings['currency'] ?? 'INR';
    @endphp

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <!-- Toast Notifications -->
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif



        <!-- Header Controls & System Filter -->
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0"><i class="feather-clock text-primary me-2"></i>Pending Goods Receipts</h5>
                <p class="text-muted fs-12 mb-0">Approved Purchase Orders waiting for store material receipt</p>
            </div>

            <!-- Common Filter Component -->
            <form method="GET" action="{{ route('purchase.grns.pending') }}" class="d-inline">
                <x-ui.filter :label="__('ui.filter') ?? 'Filters'" offset="0, 5">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keyword</label>
                        <x-ui.odoo-form-ui type="input" name="search" placeholder="Search PO # or Vendor..." value="{{ request('search') }}" />
                    </div>
                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="{{ route('purchase.grns.pending') }}" class="btn btn-sm btn-light border">Reset</a>
                        <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                    </div>
                </x-ui.filter>
            </form>
        </div>

        <!-- Listing Table using Common Odoo Table Component -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="pendingGrnTable">
                <thead>
                    <tr>
                        <th style="width: 12%">PO Number</th>
                        <th style="width: 16%">Vendor</th>
                        <th style="width: 14%">Warehouse</th>
                        <th style="width: 10%">PO Date</th>
                        <th style="width: 12%" class="text-end">Total Amount</th>
                        <th style="width: 8%" class="text-center">Ordered Qty</th>
                        <th style="width: 8%" class="text-center">Received Qty</th>
                        <th style="width: 8%" class="text-center">Remaining Qty</th>
                        <th style="width: 10%" class="text-center">Status</th>
                        <th style="width: 12%" class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pendingOrders as $order)
                        @php
                            $ordQty = (float)$order->items->sum('quantity');
                            $recQty = (float)$order->items->sum('received_qty');
                            $remQty = max(0.0, $ordQty - $recQty);
                            $badgeClass = match($order->status) {
                                'Approved' => 'bg-soft-info text-info',
                                'Partially Received' => 'bg-soft-warning text-warning',
                                default => 'bg-soft-secondary text-secondary',
                            };
                        @endphp
                        <tr>
                            <td class="fw-bold">
                                <a href="{{ route('purchase.orders.show', $order->id) }}" class="text-primary font-monospace">
                                    {{ $order->purchase_order_number }}
                                </a>
                            </td>
                            <td class="fw-semibold text-dark">{{ $order->vendor?->name ?? 'N/A' }}</td>
                            <td>
                                <i class="feather-archive me-1 text-muted"></i>{{ $order->location ?? $order->warehouse?->name ?? 'Main Warehouse' }}
                            </td>
                            <td>{{ $order->date ? $order->date->format('d-M-Y') : '—' }}</td>
                            <td class="text-end fw-bold text-dark font-monospace">{{ $currency }} {{ number_format($order->grand_total, 2) }}</td>
                            <td class="text-center font-monospace fw-semibold">{{ number_format($ordQty, 2) }}</td>
                            <td class="text-center font-monospace text-success fw-semibold">{{ number_format($recQty, 2) }}</td>
                            <td class="text-center font-monospace text-danger fw-bold">{{ number_format($remQty, 2) }}</td>
                            <td class="text-center">
                                <span class="badge {{ $badgeClass }} px-2.5 py-1 fw-bold fs-11">{{ $order->status }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('purchase.grns.create', ['po_id' => $order->id]) }}" class="action-icon-btn grn-btn" title="Create GRN" data-bs-toggle="tooltip">
                                    <i class="feather feather-plus-circle"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="feather-check-circle fs-36 text-success d-block mb-2"></i>
                                <h6 class="fw-bold text-dark mb-1">All Approved POs Received!</h6>
                                <p class="fs-12 mb-0">There are currently no pending purchase orders waiting for goods receipt.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>
    </div>
@endsection
