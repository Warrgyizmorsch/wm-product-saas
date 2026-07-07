@extends('layouts.duralux')

@section('title', 'Delivery Orders | SaaS ERP')
@section('page-title', 'Delivery Orders')
@section('breadcrumb', 'Sales / Deliveries')

@section('content')
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <h5 class="fw-bold text-dark mb-0">Delivery Orders (Shipments)</h5>
            </div>

            <div class="table-responsive">
                <table class="table odoo-table align-middle bg-white rounded border">
                    <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                        <tr>
                            <th class="ps-3">Delivery Number</th>
                            <th>Sales Order</th>
                            <th>Customer</th>
                            <th>Delivery Date</th>
                            <th>Carrier</th>
                            <th>Tracking Number</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-3">Actions</th>
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
                                <td class="ps-3 fw-bold"><a href="{{ route('sales.deliveries.show', $do->id) }}" class="text-primary">{{ $do->delivery_number }}</a></td>
                                <td><a href="{{ route('sales.orders.show', $do->sales_order_id) }}" class="text-dark fw-semibold">{{ $do->salesOrder->sales_order_number }}</a></td>
                                <td>{{ $do->salesOrder->customer?->name ?? '—' }}</td>
                                <td>{{ $do->delivery_date->format('d/m/Y') }}</td>
                                <td>{{ $do->carrier ?: '—' }}</td>
                                <td>{{ $do->tracking_number ?: '—' }}</td>
                                <td class="text-center"><span class="badge {{ $badgeClass }} px-2 py-0.5">{{ $do->status }}</span></td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('sales.deliveries.show', $do->id) }}" class="btn btn-xs btn-outline-primary fw-bold">View Details</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4.5 text-muted">
                                    <i class="feather-truck fs-2 mb-2 d-block text-gray-300"></i>
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
