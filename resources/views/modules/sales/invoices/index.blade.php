@extends('layouts.duralux')

@section('title', 'Invoices | SaaS ERP')
@section('page-title', 'Invoices')
@section('breadcrumb', 'Sales / Invoices')

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
                <i class="feather-file-text me-2 text-primary"></i>Customer Invoices
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                        <tr>
                            <th class="ps-4">Invoice Number</th>
                            <th>Date</th>
                            <th>Sales Order</th>
                            <th>Customer</th>
                            <th class="text-end">Grand Total</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fs-13 text-dark">
                        @forelse ($invoices as $inv)
                            <tr>
                                <td class="ps-4 fw-bold text-primary">
                                    <a href="{{ route('sales.invoices.show', $inv->id) }}">
                                        {{ $inv->invoice_number }}
                                    </a>
                                </td>
                                <td>{{ date('d/m/Y', strtotime($inv->invoice_date)) }}</td>
                                <td>
                                    <a href="{{ route('sales.orders.show', $inv->salesOrder->id) }}" class="text-muted fw-semibold">
                                        {{ $inv->salesOrder->sales_order_number }}
                                    </a>
                                </td>
                                <td>
                                    <span class="fw-bold">{{ $inv->salesOrder->customer?->name }}</span>
                                </td>
                                <td class="text-end fw-bold text-dark">₹{{ number_format($inv->grand_total, 2) }}</td>
                                <td>
                                    @php
                                        $badgeClass = 'bg-soft-secondary text-secondary';
                                        if ($inv->status == 'Paid') $badgeClass = 'bg-soft-success text-success';
                                        elseif ($inv->status == 'Partially Paid') $badgeClass = 'bg-soft-warning text-warning';
                                        elseif ($inv->status == 'Sent') $badgeClass = 'bg-soft-info text-info';
                                        elseif ($inv->status == 'Cancelled') $badgeClass = 'bg-soft-danger text-danger';
                                    @endphp
                                    <span class="badge {{ $badgeClass }} px-2 py-0.5 fs-11 fw-semibold">{{ $inv->status }}</span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-2 align-items-center">
                                        <a href="{{ route('sales.invoices.show', $inv->id) }}" class="avatar-text avatar-md bg-soft-primary text-primary" data-bs-toggle="tooltip" title="View Invoice Details">
                                            <i class="feather feather-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="feather-file-text fs-1 mb-2 d-block text-gray-300"></i>
                                    No invoices generated yet. Create invoices from confirmed Sales Orders.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
