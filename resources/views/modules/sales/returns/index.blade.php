@extends('layouts.duralux')

@section('title', 'Sales Returns | SaaS ERP')
@section('page-title', 'Sales Returns')
@section('breadcrumb', 'Sales / Returns')

@section('page-actions')
    <div class="d-flex gap-2">
        <a href="{{ route('sales.returns.create') }}" class="btn btn-primary">
            <i class="feather-plus me-2"></i>Create Return
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
                <i class="feather-rotate-ccw me-2 text-primary"></i>Sales Returns (Credit Notes)
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                        <tr>
                            <th class="ps-4">Return Number</th>
                            <th>Date</th>
                            <th>Sales Order</th>
                            <th>Customer</th>
                            <th class="text-end">Refund Amount</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fs-13 text-dark">
                        @forelse ($returns as $ret)
                            <tr>
                                <td class="ps-4 fw-bold text-primary">
                                    <a href="{{ route('sales.returns.show', $ret->id) }}">
                                        {{ $ret->return_number }}
                                    </a>
                                </td>
                                <td>{{ date('d/m/Y', strtotime($ret->return_date)) }}</td>
                                <td>
                                    @if ($ret->salesOrder)
                                        <a href="{{ route('sales.orders.show', $ret->salesOrder->id) }}" class="text-muted fw-semibold">
                                            {{ $ret->salesOrder->sales_order_number }}
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="fw-bold">{{ $ret->salesOrder?->customer?->name ?: '—' }}</span>
                                </td>
                                <td class="text-end fw-bold text-dark">₹{{ number_format($ret->total_refund_amount, 2) }}</td>
                                <td>
                                    @php
                                        $badgeClass = 'bg-soft-secondary text-secondary';
                                        if ($ret->status == 'Completed') $badgeClass = 'bg-soft-success text-success';
                                        elseif ($ret->status == 'Approved') $badgeClass = 'bg-soft-info text-info';
                                        elseif ($ret->status == 'Cancelled') $badgeClass = 'bg-soft-danger text-danger';
                                    @endphp
                                    <span class="badge {{ $badgeClass }} px-2 py-0.5 fs-11 fw-semibold">{{ $ret->status }}</span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-2 align-items-center">
                                        <a href="{{ route('sales.returns.show', $ret->id) }}" class="avatar-text avatar-md bg-soft-primary text-primary" data-bs-toggle="tooltip" title="View Return Details">
                                            <i class="feather feather-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="feather-rotate-ccw fs-1 mb-2 d-block text-gray-300"></i>
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
