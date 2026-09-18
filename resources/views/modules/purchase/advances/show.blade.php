@extends('layouts.duralux')

@section('title', __('purchase.advance_payment') . " {$advance->payment_number} | SaaS ERP")
@section('page-title', __('purchase.advance_details'))
@section('breadcrumb')
    <a href="{{ route('purchase.advances.index') }}">{{ __('purchase.advance_payments') }}</a> &gt; {{ $advance->payment_number }}
@endsection

@section('page-actions')
    <div class="d-flex gap-2 flex-wrap text-dark">
        <a href="{{ route('purchase.advances.index') }}" class="btn btn-light border fs-12">
            <i class="feather-arrow-left me-2"></i>{{ __('purchase.back_to_advances') }}
        </a>
    </div>
@endsection

@section('content')

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pb-3 mb-4 border-bottom">
            <div>
                <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1 letter-spacing-1">{{ __('purchase.advance_payment') }}</span>
                <h4 class="fw-bold text-dark mb-1">{{ $advance->payment_number }}</h4>
                <span class="fs-13 text-muted">
                    {{ __('purchase.supplier_vendor') }}:&nbsp;
                    @if($advance->vendor)
                        <a href="{{ route('purchase.vendors.show', $advance->vendor->id) }}" class="fw-semibold text-dark text-decoration-none">
                            {{ $advance->vendor->name }}
                        </a>
                    @else
                        <strong class="text-dark">—</strong>
                    @endif
                </span>
            </div>

            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-soft-success text-success px-3 py-1.5 fs-13 fw-bold">
                    {{ $advance->status ? ucfirst($advance->status) : __('purchase.status_recorded') }}
                </span>
            </div>
        </div>

        <div class="row g-3 mb-4 fs-13 text-dark">
            <div class="col-md-3">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">{{ __('purchase.payment_date') }}</span>
                <strong>{{ $advance->payment_date ? $advance->payment_date->format('d-M-Y') : '—' }}</strong>
            </div>
            <div class="col-md-3">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">{{ __('purchase.payment_method') }}</span>
                @php $methodKey = 'purchase.pay_method_' . strtolower(str_replace(' ', '_', $advance->payment_method ?? '')); @endphp
                <span class="badge bg-soft-info text-info fs-12 fw-semibold">
                    {{ \Illuminate\Support\Facades\Lang::has($methodKey) ? __($methodKey) : ($advance->payment_method ?: 'Bank Transfer') }}
                </span>
            </div>
            <div class="col-md-3">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">{{ __('purchase.reference_utr_no') }}</span>
                <strong class="font-monospace fs-13">{{ $advance->reference_number ?: 'N/A' }}</strong>
            </div>
            <div class="col-md-3 text-md-end">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold">{{ __('purchase.paid_amount') }}</span>
                <strong class="fs-18 font-monospace text-success">{{ format_currency($advance->amount) }}</strong>
            </div>
        </div>

        @if($advance->purchaseOrder)
            <div class="card border rounded mb-4 shadow-none">
                <div class="card-header bg-light py-2 px-3">
                    <h6 class="fw-bold text-dark mb-0 fs-12">
                        <i class="feather-file-text text-primary me-1"></i>{{ __('purchase.linked_purchase_order') }}
                    </h6>
                </div>
                <div class="card-body py-3 px-3">
                    <div class="row g-2 fs-13">
                        <div class="col-md-4">
                            <span class="text-muted d-block fs-11 text-uppercase fw-bold">{{ __('purchase.po_number') }}</span>
                            <a href="{{ route('purchase.orders.show', $advance->purchaseOrder->id) }}" class="fw-bold text-primary">
                                {{ $advance->purchaseOrder->order_number }}
                            </a>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted d-block fs-11 text-uppercase fw-bold">{{ __('purchase.order_date') }}</span>
                            <span>{{ $advance->purchaseOrder->order_date ? \Carbon\Carbon::parse($advance->purchaseOrder->order_date)->format('d-M-Y') : '—' }}</span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted d-block fs-11 text-uppercase fw-bold">{{ __('purchase.po_total_value') }}</span>
                            <span class="font-monospace fw-bold text-dark">{{ format_currency($advance->purchaseOrder->grand_total ?? $advance->purchaseOrder->total_amount ?? 0) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($advance->notes)
            <div class="border rounded p-3 mb-4 bg-light">
                <span class="text-muted d-block fs-11 text-uppercase fw-bold mb-1">{{ __('purchase.notes') }}</span>
                <p class="fs-13 text-dark mb-0">{{ $advance->notes }}</p>
            </div>
        @endif

        <div class="text-muted fs-11 pt-3 border-top d-flex justify-content-between">
            <span>{{ __('purchase.created_by') }}: <strong>{{ $advance->creator?->name ?? 'System' }}</strong></span>
            <span>{{ __('purchase.created_at') }}: {{ $advance->created_at ? $advance->created_at->format('d-M-Y H:i') : '—' }}</span>
        </div>
    </div>

@endsection
