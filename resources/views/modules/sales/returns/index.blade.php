@extends('layouts.duralux')

@section('title', 'Sales Returns | SaaS ERP')
@section('page-title', 'Sales Returns')
@section('breadcrumb', 'Sales / Returns')

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
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <h5 class="fw-bold text-dark mb-0">Sales Returns (Credit Notes)</h5>
                <a href="{{ route('sales.returns.create') }}" class="btn btn-sm btn-primary py-1.5 px-3">
                    <i class="feather-plus me-1"></i>Create Return
                </a>
            </div>

            <div class="table-responsive">
                <table class="table odoo-table align-middle bg-white rounded border">
                    <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                        <tr>
                            <th class="ps-3">Return Number</th>
                            <th>Date</th>
                            <th>Sales Order</th>
                            <th>Customer</th>
                            <th class="text-end">Refund Amount</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fs-13 text-dark">
                        @forelse ($returns as $ret)
                            <tr>
                                <td class="ps-3 fw-bold">
                                    <a href="{{ route('sales.returns.show', $ret->id) }}" class="text-primary">
                                        {{ $ret->return_number }}
                                    </a>
                                </td>
                                <td>{{ date('d/m/Y', strtotime($ret->return_date)) }}</td>
                                <td>
                                    @if ($ret->salesOrder)
                                        <a href="{{ route('sales.orders.show', $ret->salesOrder->id) }}" class="text-muted">
                                            {{ $ret->salesOrder->sales_order_number }}
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $ret->salesOrder?->customer?->name ?: '—' }}</td>
                                <td class="text-end fw-bold text-dark">₹{{ number_format($ret->total_refund_amount, 2) }}</td>
                                <td class="text-center">
                                    @if ($ret->status == 'Completed')
                                        <span class="badge bg-soft-success text-success px-2 py-0.5 fs-11">Completed</span>
                                    @elseif ($ret->status == 'Approved')
                                        <span class="badge bg-soft-info text-info px-2 py-0.5 fs-11">Approved</span>
                                    @elseif ($ret->status == 'Cancelled')
                                        <span class="badge bg-soft-danger text-danger px-2 py-0.5 fs-11">Cancelled</span>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary px-2 py-0.5 fs-11">Draft</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('sales.returns.show', $ret->id) }}" class="btn btn-sm btn-light border py-1">
                                        <i class="feather-eye me-1"></i>View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4 fs-12">
                                    No sales returns processed yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
