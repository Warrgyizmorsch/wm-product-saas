@extends('layouts.duralux')

@section('title', 'Payment Receipt Details | SaaS ERP')
@section('page-title', 'Payment ' . $payment->payment_number)
@section('breadcrumb', 'Sales / Payments / Details')

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

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <a href="{{ route('sales.payments.index') }}" class="btn btn-sm btn-light border py-1.5 px-3">
            <i class="feather-arrow-left me-1"></i>Back to Payments
        </a>
    </div>

    <div class="erp-single-panel bg-white p-4">
        <x-ui.odoo-form-ui type="sheet">
            <!-- Header Block -->
            <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
                <div>
                    <h4 class="fw-bold text-dark mb-1">{{ $payment->payment_number }}</h4>
                    <span class="text-muted">Payment Voucher Receipt</span>
                </div>
                <div class="text-end">
                    @if ($payment->status == 'Confirmed')
                        <span class="badge bg-soft-success text-success px-3 py-1.5 fs-12 fw-bold">CONFIRMED</span>
                    @elseif ($payment->status == 'Cancelled')
                        <span class="badge bg-soft-danger text-danger px-3 py-1.5 fs-12 fw-bold">CANCELLED</span>
                    @else
                        <span class="badge bg-soft-secondary text-secondary px-3 py-1.5 fs-12 fw-bold">DRAFT</span>
                    @endif
                </div>
            </div>

            <!-- Details Block -->
            <div class="row g-4 mb-4 fs-13 text-dark">
                <div class="col-md-6">
                    <div class="mb-3">
                        <span class="text-muted d-block mb-1">Received From (Customer):</span>
                        <strong class="text-dark fs-14">{{ $payment->customer?->name }}</strong>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <span class="text-muted d-block mb-1">Payment Method:</span>
                            <span class="fw-bold text-dark">{{ $payment->payment_method }}</span>
                        </div>
                        <div class="col-6 mb-3">
                            <span class="text-muted d-block mb-1">Bank Reference No:</span>
                            <span class="fw-bold text-muted">{{ $payment->reference_no ?: '—' }}</span>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 text-md-end">
                    <div class="p-3 bg-light rounded d-inline-block text-start border" style="min-width: 250px;">
                        <span class="text-muted d-block mb-1 fs-12">Total Amount Received:</span>
                        <h2 class="fw-extrabold text-primary mb-0">₹{{ number_format($payment->amount, 2) }}</h2>
                        <span class="text-muted d-block mt-2 fs-11 border-top pt-1">Date: <strong>{{ date('d M Y', strtotime($payment->payment_date)) }}</strong></span>
                    </div>
                </div>
            </div>

            <!-- Allocations Block -->
            <div class="border-top pt-4 mt-4">
                <h5 class="fw-bold text-dark mb-3 fs-14"><i class="feather-link me-1"></i>Adjustments / Allocations</h5>
                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table" id="paymentAllocationsTable">
                        <thead>
                            <tr>
                                <th style="width: 40%;">Linked Document</th>
                                <th>Document Type</th>
                                <th class="text-end pe-3" style="width: 30%;">Allocated Amount</th>
                            </tr>
                        </thead>
                        <tbody class="fs-13 text-dark">
                            @forelse ($payment->allocations as $alloc)
                                <tr>
                                    <td>
                                        @if ($alloc->invoice_id)
                                            <a href="{{ route('sales.invoices.show', $alloc->invoice_id) }}" class="text-primary fw-bold">
                                                {{ $alloc->invoice?->invoice_number }}
                                            </a>
                                        @elseif ($alloc->sales_order_id)
                                            <a href="{{ route('sales.orders.show', $alloc->sales_order_id) }}" class="text-primary fw-bold">
                                                {{ $alloc->salesOrder?->sales_order_number }}
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($alloc->invoice_id)
                                            <span class="badge bg-soft-info text-info px-2 py-0.5 fs-11">Customer Invoice</span>
                                        @elseif ($alloc->sales_order_id)
                                            <span class="badge bg-soft-warning text-warning px-2 py-0.5 fs-11">Sales Order (Advance)</span>
                                        @else
                                            <span class="text-muted">Unallocated Receipt</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-3 fw-bold text-dark">
                                        ₹{{ number_format($alloc->allocated_amount, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-3 fs-12">
                                        No allocations linked yet. This payment receipt is unallocated (available as advance credits).
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            @if ($payment->notes)
                <div class="row g-4 mt-2 border-top pt-3 fs-12 text-muted">
                    <div class="col-md-12">
                        <strong class="text-dark d-block mb-1">Receipt Notes:</strong>
                        <p class="mb-0 bg-light p-2 rounded">{{ $payment->notes }}</p>
                    </div>
                </div>
            @endif
        </x-ui.odoo-form-ui>
    </div>
@endsection
