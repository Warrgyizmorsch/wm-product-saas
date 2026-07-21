@extends('layouts.duralux')

@section('title', "Payment {$payment->payment_number} | SaaS ERP")
@section('page-title', "Vendor Payment Details")
@section('breadcrumb')
    <a href="{{ route('purchase.payments.index') }}">Vendor Payments</a> &gt; {{ $payment->payment_number }}
@endsection

@section('page-actions')
    <div class="d-flex gap-2 flex-wrap text-dark">
        <a href="{{ route('purchase.payments.index') }}" class="btn btn-light border fs-12">
            <i class="feather-arrow-left me-2"></i>Back to Payments
        </a>
    </div>
@endsection

@section('content')

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pb-3 mb-4 border-bottom">
            <div>
                <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1 letter-spacing-1">Vendor Payment</span>
                <h4 class="fw-bold text-dark mb-1">{{ $payment->payment_number }}</h4>
                <span class="fs-13 text-muted">
                    Vendor:&nbsp;<strong class="text-dark">{{ $payment->vendor?->name }}</strong>
                </span>
            </div>

            <div>
                <span class="badge bg-soft-success text-success px-3 py-1.5 fs-13 fw-bold">POSTED TO ACCOUNTING</span>
            </div>
        </div>

        <div class="row g-3 mb-4 fs-13 text-dark">
            <div class="col-md-3">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">Payment Date</span>
                <strong>{{ $payment->payment_date ? $payment->payment_date->format('d-M-Y') : '—' }}</strong>
            </div>
            <div class="col-md-3">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">Payment Method</span>
                <span class="badge bg-soft-info text-info fs-12 fw-semibold">{{ $payment->payment_method }}</span>
            </div>
            <div class="col-md-3">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">Reference / UTR No</span>
                <strong class="font-monospace fs-13">{{ $payment->reference_number ?: 'N/A' }}</strong>
            </div>
            <div class="col-md-3 text-md-end">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">Paid Amount</span>
                <strong class="fs-18 font-monospace text-success">₹{{ number_format($payment->amount, 2) }}</strong>
            </div>
        </div>

        <h6 class="fw-bold text-dark mb-2">Allocated Vendor Bills</h6>
        @if($payment->allocations->count() > 0)
            <div class="table-responsive rounded border mb-4">
                <table class="table table-bordered table-sm align-middle fs-13 text-dark mb-0">
                    <thead class="table-light fs-11 text-uppercase text-muted fw-semibold">
                        <tr>
                            <th class="ps-3">Bill Number</th>
                            <th>Bill Date</th>
                            <th class="text-end">Bill Grand Total</th>
                            <th class="text-end pe-3">Allocated Paid Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payment->allocations as $alloc)
                            <tr>
                                <td class="ps-3 fw-bold text-primary">
                                    @if($alloc->bill)
                                        <a href="{{ route('purchase.bills.show', $alloc->bill->id) }}">
                                            {{ $alloc->bill->bill_number }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $alloc->bill?->bill_date ? $alloc->bill->bill_date->format('d-M-Y') : '—' }}</td>
                                <td class="text-end font-monospace">₹{{ number_format($alloc->bill?->grand_total ?: 0, 2) }}</td>
                                <td class="text-end pe-3 font-monospace fw-bold text-success">₹{{ number_format($alloc->allocated_amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-3 text-muted fs-12 border rounded mb-4">
                <i class="feather-info me-1"></i>Advance payment recorded without specific bill allocation.
            </div>
        @endif

    </div>

@endsection
