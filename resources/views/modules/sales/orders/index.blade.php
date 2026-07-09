@extends('layouts.duralux')

@section('title', 'Sales Orders | SaaS ERP')
@section('page-title', 'Sales Orders')
@section('breadcrumb', 'Sales / Sales Orders')

@section('page-actions')
    <div class="d-flex gap-2">
        <a href="{{ route('sales.orders.create') }}" class="btn btn-primary">
            <i class="feather-plus me-2"></i>Create Sales Order
        </a>
    </div>
@endsection

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

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-bottom py-3">
            <h5 class="card-title mb-0 fw-bold text-dark">
                <i class="feather-shopping-cart me-2 text-primary"></i>All Sales Orders
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                        <tr>
                            <th class="ps-4">Sales Order #</th>
                            <th>Customer</th>
                            <th>Quotation Ref</th>
                            <th>Order Date</th>
                            <th>Shipment Date</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fs-13 text-dark">
                        @forelse ($orders as $order)
                            <tr>
                                <td class="ps-4 fw-bold text-primary">
                                    <a href="{{ route('sales.orders.show', $order->id) }}">{{ $order->sales_order_number }}</a>
                                </td>
                                <td>
                                    <span class="fw-bold">{{ $order->customer?->name ?? '—' }}</span>
                                </td>
                                <td>
                                    @if ($order->quotation)
                                        <a href="{{ route('crm.quotations.show', $order->quotation_id) }}" class="text-muted fw-semibold">
                                            {{ $order->quotation->quotation_number }}
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $order->order_date ? $order->order_date->format('d/m/Y') : '—' }}</td>
                                <td>{{ $order->shipment_date ? $order->shipment_date->format('d/m/Y') : 'Estimated Shipment Not Scheduled' }}</td>
                                <td class="fw-bold text-dark">₹{{ number_format($order->total_amount, 2) }}</td>
                                <td>
                                    @php
                                        $badgeClass = 'bg-soft-secondary text-secondary';
                                        if ($order->status === 'Confirmed') $badgeClass = 'bg-soft-info text-info';
                                        elseif ($order->status === 'Shipped') $badgeClass = 'bg-soft-success text-success';
                                        elseif ($order->status === 'Cancelled') $badgeClass = 'bg-soft-danger text-danger';
                                    @endphp
                                    <span class="badge {{ $badgeClass }} px-2 py-0.5 fs-11 fw-semibold">{{ $order->status }}</span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-2 align-items-center">
                                        @if ($order->status === 'Draft')
                                            <form action="{{ route('sales.orders.confirm', $order->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-soft-success py-1 px-2 fs-11 fw-bold border-0" data-bs-toggle="tooltip" title="Confirm Order">
                                                    <i class="feather-check me-1"></i>Confirm
                                                </button>
                                            </form>
                                        @elseif ($order->status === 'Confirmed' || $order->status === 'Partially Shipped')
                                            <a href="{{ route('sales.deliveries.create', ['sales_order_id' => $order->id]) }}" class="btn btn-sm btn-soft-primary py-1 px-2 fs-11 fw-bold border-0" data-bs-toggle="tooltip" title="Create Delivery Order">
                                                <i class="feather-truck me-1"></i>Ship
                                            </a>
                                        @endif

                                        <a href="{{ route('sales.orders.show', $order->id) }}" class="avatar-text avatar-md bg-soft-primary text-primary" data-bs-toggle="tooltip" title="View Sales Order">
                                            <i class="feather feather-eye"></i>
                                        </a>

                                        @if ($order->status !== 'Shipped' && $order->status !== 'Cancelled')
                                            <a href="{{ route('sales.orders.edit', $order->id) }}" class="avatar-text avatar-md bg-soft-warning text-warning" data-bs-toggle="tooltip" title="Edit Sales Order">
                                                <i class="feather feather-edit-2"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="feather-shopping-cart fs-1 mb-2 d-block"></i>
                                    No sales orders found in this tenant workspace.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
