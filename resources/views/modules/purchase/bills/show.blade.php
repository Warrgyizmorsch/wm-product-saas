@extends('layouts.duralux')

@section('title', __('purchase.bill') . " {$bill->bill_number} | SaaS ERP")
@section('page-title', __('purchase.vendor_bill_details'))
@section('breadcrumb')
    <a href="{{ route('purchase.bills.index') }}">{{ __('purchase.vendor_bills') }}</a> &gt; {{ $bill->bill_number }}
@endsection

@section('page-actions')
    <div class="d-flex gap-2 flex-wrap text-dark">
        <a href="{{ route('purchase.bills.index') }}" class="btn btn-light border fs-12">
            <i class="feather-arrow-left me-2"></i>{{ __('purchase.back_to_bills') }}
        </a>

        @if($bill->due_amount > 0)
            @php
                $availAdvAction = max($availableAdvance ?? 0, $bill->goodsReceiptNote?->purchaseOrder?->total_advance_paid ?? 0);
            @endphp
            @if($availAdvAction > 0)
                <form action="{{ route('purchase.bills.apply-advance', $bill->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary text-white fs-12 fw-bold shadow-sm me-1" style="background-color: #714B67; border-color: #714B67;">
                        <i class="feather-check-circle me-1.5"></i>Apply Vendor Advance Credit
                    </button>
                </form>
            @endif
            <a href="{{ route('purchase.payments.create', ['bill_id' => $bill->id]) }}" class="btn btn-success text-white fs-12 fw-bold shadow-sm">
                <i class="feather-credit-card me-2"></i>{{ __('purchase.register_vendor_payment') }}
            </a>
        @endif
    </div>
@endsection

@section('content')

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <!-- Status Bar -->
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pb-3 mb-4 border-bottom">
            <div>
                <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1 letter-spacing-1">{{ __('purchase.vendor_bill') }}</span>
                <h4 class="fw-bold text-dark mb-1">{{ $bill->bill_number }}</h4>
                <span class="fs-13 text-muted">
                    {{ __('purchase.supplier_vendor') }}:&nbsp;<strong class="text-dark">{{ $bill->vendor?->name }}</strong>
                    @if($bill->goodsReceiptNote)
                        &nbsp;·&nbsp;GRN:&nbsp;<a href="{{ route('purchase.grns.show', $bill->goodsReceiptNote->id) }}" class="fw-semibold text-primary">{{ $bill->goodsReceiptNote->grn_number }}</a>
                    @endif
                </span>
            </div>

            <div class="d-flex align-items-center gap-2">
                @php
                    $statusText = match($bill->status) {
                        'Paid' => 'PAID',
                        'Partially Paid' => 'PARTIALLY PAID',
                        'Unpaid' => 'UNPAID',
                        'Posted' => 'POSTED',
                        'Cancelled' => 'CANCELLED',
                        default => strtoupper($bill->status),
                    };
                    $badgeClass = match($bill->status) {
                        'Paid' => 'success',
                        'Partially Paid' => 'info',
                        'Unpaid' => 'danger',
                        'Posted', 'Draft' => 'warning',
                        'Cancelled' => 'secondary',
                        default => 'secondary',
                    };
                @endphp
                <span class="badge bg-soft-{{ $badgeClass }} text-{{ $badgeClass }} px-3 py-1.5 fs-13 fw-bold">
                    {{ $statusText }}
                </span>
            </div>
        </div>

        @php
            $availAdv = max($availableAdvance ?? 0, $bill->goodsReceiptNote?->purchaseOrder?->total_advance_paid ?? 0);
        @endphp

        @if($availAdv > 0)
            <div class="alert alert-info border-info p-3 mb-4 rounded shadow-sm">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <strong class="text-dark fs-13"><i class="feather-info text-info me-1.5"></i>Vendor Advance Credit Available:</strong>
                        <span class="text-success fw-bold font-monospace fs-14 ms-1">₹{{ number_format($availAdv, 2) }}</span>
                        <small class="text-muted d-block fs-11 mt-0.5">This supplier has available advance credit that can be applied to settle this bill.</small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        @if($bill->due_amount > 0)
                            <form action="{{ route('purchase.bills.apply-advance', $bill->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm fw-bold shadow-sm" style="background-color: #714B67; border-color: #714B67;">
                                    <i class="feather-check-circle me-1"></i>Apply Vendor Advance Credit
                                </button>
                            </form>
                        @endif
                        <span class="badge bg-primary text-white p-2 fs-12">{{ __('purchase.net_payable_from_bank') }}: ₹{{ number_format(max(0, $bill->due_amount - $availAdv), 2) }}</span>
                    </div>
                </div>
            </div>
        @endif

        <!-- Metadata Row -->
        <div class="row g-3 mb-4 fs-13 text-dark">
            <div class="col-md-3">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">{{ __('purchase.vendor_invoice_no') }}</span>
                <strong class="font-monospace fs-14">{{ $bill->vendor_invoice_number ?: '—' }}</strong>
            </div>
            <div class="col-md-3">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">{{ __('purchase.bill_date') }}</span>
                <strong>{{ $bill->bill_date ? $bill->bill_date->format('d-M-Y') : '—' }}</strong>
            </div>
            <div class="col-md-3">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">{{ __('purchase.due_date') }}</span>
                <strong>{{ $bill->due_date ? $bill->due_date->format('d-M-Y') : '—' }}</strong>
            </div>
            <div class="col-md-3 text-md-end">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">{{ __('purchase.grand_total') }}</span>
                <strong class="fs-16 font-monospace text-primary">₹{{ number_format($bill->grand_total, 2) }}</strong>
            </div>
        </div>

        <!-- Billed Items Table -->
        <h6 class="fw-bold text-dark mb-2">{{ __('purchase.billed_items') }}</h6>
        <div class="table-responsive rounded border mb-4">
            <table class="table table-bordered table-sm align-middle fs-13 text-dark mb-0">
                <thead class="table-light fs-11 text-uppercase text-muted fw-semibold">
                    <tr>
                        <th class="ps-3" style="width: 5%;">#</th>
                        <th style="width: 45%;">{{ __('purchase.product') }}</th>
                        <th class="text-center" style="width: 15%;">{{ __('purchase.billed_qty') }}</th>
                        <th class="text-end" style="width: 15%;">{{ __('purchase.unit_rate') }}</th>
                        <th class="text-end pe-3" style="width: 20%;">{{ __('purchase.total_amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bill->items as $idx => $item)
                        <tr>
                            <td class="ps-3 text-muted fw-semibold">{{ $idx + 1 }}</td>
                            <td>
                                <strong class="text-dark">{{ $item->product?->name }}</strong>
                                @if($item->product?->sku)
                                    <small class="text-muted d-block">{{ __('purchase.sku') }}: {{ $item->product->sku }}</small>
                                @endif
                            </td>
                            <td class="text-center font-monospace">{{ (float)$item->quantity }}</td>
                            <td class="text-end font-monospace">₹{{ number_format($item->unit_rate, 2) }}</td>
                            <td class="text-end pe-3 font-monospace fw-bold text-success">₹{{ number_format($item->total_amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Payment Breakdown Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="p-3 border rounded bg-light-50">
                    <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">{{ __('purchase.grand_total') }}</span>
                    <h4 class="fw-bold text-dark mb-0">₹{{ number_format($bill->grand_total, 2) }}</h4>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 border rounded bg-light-50">
                    <span class="fs-11 text-uppercase text-success fw-bold d-block mb-1">{{ __('purchase.paid_settled_amount') }}</span>
                    <h4 class="fw-bold text-success mb-0">₹{{ number_format($bill->paid_amount, 2) }}</h4>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 border rounded bg-light-50">
                    <span class="fs-11 text-uppercase text-danger fw-bold d-block mb-1">{{ __('purchase.net_balance_due') }}</span>
                    <h4 class="fw-bold text-danger mb-0">₹{{ number_format($bill->due_amount, 2) }}</h4>
                </div>
            </div>
        </div>

        <!-- Allocated Payments History -->
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold text-dark mb-0">{{ __('purchase.allocated_payments_ledger') }}</h6>
            @if($bill->due_amount > 0)
                <a href="{{ route('purchase.payments.create', ['bill_id' => $bill->id]) }}" class="btn btn-sm btn-success text-white fw-bold py-1 px-3 fs-12">
                    <i class="feather-plus me-1"></i>{{ __('purchase.register_payment') }}
                </a>
            @endif
        </div>

        @if($bill->allocations->count() > 0)
            <div class="table-responsive rounded border mb-4">
                <table class="table table-bordered table-sm align-middle fs-13 text-dark mb-0">
                    <thead class="table-light fs-11 text-uppercase text-muted fw-semibold">
                        <tr>
                            <th class="ps-3">{{ __('purchase.payment_number') }}</th>
                            <th>{{ __('purchase.date') }}</th>
                            <th>{{ __('purchase.payment_method') }}</th>
                            <th>{{ __('purchase.reference_utr_no') }}</th>
                            <th class="text-end pe-3">{{ __('purchase.allocated_amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bill->allocations as $alloc)
                            <tr>
                                <td class="ps-3 fw-bold text-primary">{{ $alloc->payment?->payment_number }}</td>
                                <td>{{ $alloc->payment?->payment_date ? $alloc->payment->payment_date->format('d-M-Y') : '—' }}</td>
                                <td><span class="badge bg-soft-info text-info fs-11 fw-semibold">{{ $alloc->payment?->payment_method }}</span></td>
                                <td class="font-monospace">{{ $alloc->payment?->reference_number ?: 'N/A' }}</td>
                                <td class="text-end pe-3 font-monospace fw-bold text-success">₹{{ number_format($alloc->allocated_amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-3 text-muted fs-12 border rounded mb-4">
                <i class="feather-info me-1"></i>{{ __('purchase.no_payments_registered_yet') }}
            </div>
        @endif

    </div>

@endsection
