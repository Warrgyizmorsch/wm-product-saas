@extends('layouts.duralux')

@section('title', 'Dispatch Orders | SaaS ERP')
@section('page-title', 'Dispatch Orders')
@section('breadcrumb', 'Sales / Dispatches')

@section('content')
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0 fw-bold text-dark">
                <i class="feather-send me-2 text-primary"></i>All Dispatch Orders
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle fs-13 mb-0">
                    <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                        <tr>
                            <th class="ps-4">Dispatch #</th>
                            <th>Delivery Order</th>
                            <th>Sales Order</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Carrier</th>
                            <th class="text-center">Status</th>
                            <th class="text-center pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-dark">
                        @forelse ($dispatches as $dispatch)
                            @php
                                $badge = 'bg-soft-secondary text-secondary';
                                if ($dispatch->status === 'Dispatched') $badge = 'bg-soft-info text-info';
                                elseif ($dispatch->status === 'Delivered') $badge = 'bg-soft-success text-success';
                                elseif ($dispatch->status === 'Invoiced') $badge = 'bg-soft-dark text-dark';
                            @endphp
                            <tr>
                                <td class="ps-4 fw-bold">
                                    <a href="{{ route('sales.dispatches.show', $dispatch->id) }}" class="text-primary">{{ $dispatch->dispatch_number }}</a>
                                </td>
                                <td>
                                    <a href="{{ route('sales.deliveries.show', $dispatch->delivery_order_id) }}" class="text-dark fw-semibold">
                                        {{ $dispatch->deliveryOrder->delivery_number }}
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ route('sales.orders.show', $dispatch->sales_order_id) }}" class="text-dark">
                                        {{ $dispatch->salesOrder->sales_order_number }}
                                    </a>
                                </td>
                                <td>{{ $dispatch->salesOrder->customer?->name }}</td>
                                <td>{{ $dispatch->dispatch_date->format('d M Y') }}</td>
                                <td>{{ $dispatch->carrier ?: '—' }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $badge }} px-2 py-1 fs-11 fw-semibold">{{ $dispatch->status }}</span>
                                </td>
                                <td class="text-center pe-4">
                                    <a href="{{ route('sales.dispatches.show', $dispatch->id) }}" class="btn btn-sm btn-light border">
                                        <i class="feather-eye me-1"></i>View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="avatar-text avatar-lg bg-soft-secondary text-muted mx-auto mb-3">
                                        <i class="feather-send fs-20"></i>
                                    </div>
                                    <p class="text-muted mb-0">No dispatch orders found.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
