@extends('layouts.duralux')

@section('title', 'Customer Payments | SaaS ERP')
@section('page-title', 'Customer Payments')
@section('breadcrumb', 'Sales / Payments')

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
                <h5 class="fw-bold text-dark mb-0">Customer Payments (Receipts)</h5>
                <a href="{{ route('sales.payments.create') }}" class="btn btn-sm btn-primary py-1.5 px-3">
                    <i class="feather-plus me-1"></i>Record Payment
                </a>
            </div>

            <div class="table-responsive">
                <table class="table odoo-table align-middle bg-white rounded border">
                    <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                        <tr>
                            <th class="ps-3">Payment Number</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Method</th>
                            <th>Reference No</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fs-13 text-dark">
                        @forelse ($payments as $payment)
                            <tr>
                                <td class="ps-3 fw-bold">
                                    <a href="{{ route('sales.payments.show', $payment->id) }}" class="text-primary">
                                        {{ $payment->payment_number }}
                                    </a>
                                </td>
                                <td>{{ date('d/m/Y', strtotime($payment->payment_date)) }}</td>
                                <td>{{ $payment->customer?->name }}</td>
                                <td>{{ $payment->payment_method }}</td>
                                <td class="text-muted">{{ $payment->reference_no ?: '—' }}</td>
                                <td class="text-end fw-bold text-dark">₹{{ number_format($payment->amount, 2) }}</td>
                                <td class="text-center">
                                    @if ($payment->status == 'Confirmed')
                                        <span class="badge bg-soft-success text-success px-2 py-0.5 fs-11">Confirmed</span>
                                    @elseif ($payment->status == 'Cancelled')
                                        <span class="badge bg-soft-danger text-danger px-2 py-0.5 fs-11">Cancelled</span>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary px-2 py-0.5 fs-11">Draft</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('sales.payments.show', $payment->id) }}" class="btn btn-sm btn-light border py-1">
                                        <i class="feather-eye me-1"></i>View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4 fs-12">
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
