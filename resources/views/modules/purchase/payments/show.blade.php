@extends('layouts.duralux')

@section('title', __('purchase.payment') . " {$payment->payment_number} | SaaS ERP")
@section('page-title', __('purchase.vendor_payment_details'))
@section('breadcrumb')
    <a href="{{ route('purchase.payments.index') }}">{{ __('purchase.vendor_payments') }}</a> &gt; {{ $payment->payment_number }}
@endsection

@section('page-actions')
    <div class="d-flex gap-2 flex-wrap text-dark">
        <a href="{{ route('purchase.payments.index') }}" class="btn btn-light border fs-12">
            <i class="feather-arrow-left me-2"></i>{{ __('purchase.back_to_payments') }}
        </a>
    </div>
@endsection

@section('content')

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pb-3 mb-4 border-bottom">
            <div>
                <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1 letter-spacing-1">{{ __('purchase.vendor_payment') }}</span>
                <h4 class="fw-bold text-dark mb-1">{{ $payment->payment_number }}</h4>
                <span class="fs-13 text-muted">
                    {{ __('purchase.supplier_vendor') }}:&nbsp;<strong class="text-dark">{{ $payment->vendor?->name }}</strong>
                </span>
            </div>

            <div>
                <span class="badge bg-soft-success text-success px-3 py-1.5 fs-13 fw-bold">{{ __('purchase.posted_to_accounting') }}</span>
            </div>
        </div>

        <div class="row g-3 mb-4 fs-13 text-dark">
            <div class="col-md-3">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">{{ __('purchase.payment_date') }}</span>
                <strong>{{ $payment->payment_date ? $payment->payment_date->format('d-M-Y') : '—' }}</strong>
            </div>
            <div class="col-md-3">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">{{ __('purchase.payment_method') }}</span>
                @php $methodKey = 'purchase.pay_method_' . strtolower(str_replace(' ', '_', $payment->payment_method)); @endphp
                <span class="badge bg-soft-info text-info fs-12 fw-semibold">{{ \Illuminate\Support\Facades\Lang::has($methodKey) ? __($methodKey) : $payment->payment_method }}</span>
            </div>
            <div class="col-md-3">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">{{ __('purchase.reference_utr_no') }}</span>
                <strong class="font-monospace fs-13">{{ $payment->reference_number ?: 'N/A' }}</strong>
            </div>
            <div class="col-md-3 text-md-end">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">{{ __('purchase.paid_amount') }}</span>
                <strong class="fs-18 font-monospace text-success">₹{{ number_format($payment->amount, 2) }}</strong>
            </div>
        </div>

        <h6 class="fw-bold text-dark mb-2">{{ __('purchase.allocated_vendor_bills') }}</h6>
        @if($payment->allocations->count() > 0)
            <div class="table-responsive rounded border mb-4">
                <table class="table table-bordered table-sm align-middle fs-13 text-dark mb-0">
                    <thead class="table-light fs-11 text-uppercase text-muted fw-semibold">
                        <tr>
                            <th class="ps-3" style="width: 25%;">{{ __('purchase.bill_number') }}</th>
                            <th style="width: 25%;">{{ __('purchase.bill_date') }}</th>
                            <th class="text-end" style="width: 25%;">{{ __('purchase.bill_grand_total') }}</th>
                            <th class="text-end pe-3" style="width: 25%;">{{ __('purchase.allocated_paid_amount') }}</th>
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
                <i class="feather-info me-1"></i>{{ __('purchase.advance_payment_no_allocation_help') }}
            </div>
        @endif

    </div>

@endsection
