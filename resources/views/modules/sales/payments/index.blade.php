@extends('layouts.duralux')

@section('title', 'Customer Payments | SaaS ERP')
@section('page-title', 'Customer Payments')
@section('breadcrumb', 'Sales / Payments')

@section('page-actions')
    <div class="d-flex gap-2">
        <a href="{{ route('sales.payments.create') }}" class="btn btn-primary">
            <i class="feather-plus me-2"></i>Record Payment
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
                <i class="feather-dollar-sign me-2 text-primary"></i>Customer Payments (Receipts)
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                        <tr>
                            <th class="ps-4">Payment Number</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Method</th>
                            <th>Reference No</th>
                            <th class="text-end">Amount</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fs-13 text-dark">
                        @forelse ($payments as $payment)
                            <tr>
                                <td class="ps-4 fw-bold text-primary">
                                    <a href="{{ route('sales.payments.show', $payment->id) }}">
                                        {{ $payment->payment_number }}
                                    </a>
                                </td>
                                <td>{{ date('d/m/Y', strtotime($payment->payment_date)) }}</td>
                                <td>
                                    <span class="fw-bold">{{ $payment->customer?->name }}</span>
                                </td>
                                <td>{{ $payment->payment_method }}</td>
                                <td class="text-muted">{{ $payment->reference_no ?: '—' }}</td>
                                <td class="text-end fw-bold text-dark">₹{{ number_format($payment->amount, 2) }}</td>
                                <td>
                                    @php
                                        $badgeClass = 'bg-soft-secondary text-secondary';
                                        if ($payment->status == 'Confirmed') $badgeClass = 'bg-soft-success text-success';
                                        elseif ($payment->status == 'Cancelled') $badgeClass = 'bg-soft-danger text-danger';
                                    @endphp
                                    <span class="badge {{ $badgeClass }} px-2 py-0.5 fs-11 fw-semibold">{{ $payment->status }}</span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-2 align-items-center">
                                        <a href="{{ route('sales.payments.show', $payment->id) }}" class="avatar-text avatar-md bg-soft-primary text-primary" data-bs-toggle="tooltip" title="View Payment Details">
                                            <i class="feather feather-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="feather-dollar-sign fs-1 mb-2 d-block text-gray-300"></i>
                                    No customer payments recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
