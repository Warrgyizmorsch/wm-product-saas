@extends('layouts.duralux')

@section('title', 'Delivery Orders | SaaS ERP')
@section('page-title', 'Delivery Orders')
@section('breadcrumb', 'Sales / Deliveries')

@section('content')
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-bottom py-3">
            <h5 class="card-title mb-0 fw-bold text-dark">
                <i class="feather-truck me-2 text-primary"></i>Delivery Orders (Shipments)
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                        <tr>
                            <th class="ps-4">Delivery Number</th>
                            <th>Sales Order</th>
                            <th>Customer</th>
                            <th>Delivery Date</th>
                            <th>Carrier</th>
                            <th>Tracking Number</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fs-13 text-dark">
                        @forelse ($deliveries as $do)
                            @php
                                $badgeClass = 'bg-soft-secondary text-secondary';
                                if ($do->status === 'Shipped') $badgeClass = 'bg-soft-success text-success';
                                elseif ($do->status === 'Cancelled') $badgeClass = 'bg-soft-danger text-danger';
                            @endphp
                            <tr>
                                <td class="ps-4 fw-bold text-primary">
                                    <a href="{{ route('sales.deliveries.show', $do->id) }}">{{ $do->delivery_number }}</a>
                                </td>
                                <td>
                                    <a href="{{ route('sales.orders.show', $do->sales_order_id) }}" class="text-dark fw-semibold">{{ $do->salesOrder->sales_order_number }}</a>
                                </td>
                                <td>
                                    <span class="fw-bold">{{ $do->salesOrder->customer?->name ?? '—' }}</span>
                                </td>
                                <td>{{ $do->delivery_date->format('d/m/Y') }}</td>
                                <td>{{ $do->carrier ?: '—' }}</td>
                                <td>{{ $do->tracking_number ?: '—' }}</td>
                                <td>
                                    <span class="badge {{ $badgeClass }} px-2 py-0.5 fs-11 fw-semibold">{{ $do->status }}</span>
                                </td>
                                <td class="text-end pe-4">
                                    @php
                                        // Check if this specific shipment has already been invoiced
                                        $invoiced = $do->salesOrder?->invoices->where('delivery_order_id', $do->id)->first();
                                    @endphp
                                    <x-ui.action-dropdown :viewUrl="route('sales.deliveries.show', $do->id)">
                                        <x-ui.dropdown-item href="{{ route('sales.deliveries.show', $do->id) }}" icon="feather-eye">
                                            View Details
                                        </x-ui.dropdown-item>
                                        @if ($do->status === 'Shipped')
                                            @if (!$invoiced)
                                                <x-ui.dropdown-item href="{{ route('sales.invoices.create', ['delivery_order_id' => $do->id]) }}" icon="feather-file-text">
                                                    Create Invoice
                                                </x-ui.dropdown-item>
                                            @endif
                                        @endif
                                    </x-ui.action-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="feather-truck fs-1 mb-2 d-block text-gray-300"></i>
                                    No delivery orders found. Create shipments directly from Confirmed Sales Orders.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
