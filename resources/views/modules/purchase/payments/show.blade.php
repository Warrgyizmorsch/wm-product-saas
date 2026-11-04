@extends('layouts.duralux')

@section('title', 'Payment Voucher | ' . $payment->payment_number . ' | SaaS ERP')
@section('page-title', 'Payment ' . $payment->payment_number)
@section('breadcrumb')
    <a href="{{ route('purchase.payments.index') }}">{{ __('purchase.vendor_payments') }}</a> &gt; {{ $payment->payment_number }}
@endsection

@section('page-actions')
    <div class="d-flex gap-2 flex-wrap text-dark d-print-none">
        <a href="{{ route('purchase.payments.index') }}" class="btn btn-sm btn-light border fw-semibold px-3">
            <i class="feather-arrow-left me-1.5"></i>{{ __('purchase.back_to_payments') }}
        </a>

        <a href="{{ route('purchase.payments.download', $payment->id) }}" class="btn btn-sm btn-primary fw-bold px-3 shadow-sm">
            <i class="feather-download me-1.5"></i>Download PDF
        </a>

        <button onclick="window.print()" class="btn btn-sm btn-light border fw-semibold px-3 shadow-sm">
            <i class="feather-printer me-1.5"></i>Print Voucher
        </button>
    </div>
@endsection

@push('styles')
<style>
    /* ── Sheet ──────────────────────────── */
    .pay-sheet {
        max-width: 900px;
        margin: 0 auto;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,.05);
        padding: 40px;
        position: relative;
        overflow: hidden;
        font-family: 'Inter', system-ui, sans-serif;
        color: #1e293b;
    }

    .pay-main-title {
        color: #0284c7;
    }

    .pay-avatar-box {
        background-color: #0284c7 !important;
    }

    .pay-vendor-avatar {
        background-color: #0284c7 !important;
    }

    .pay-sheet .text-primary {
        color: #0284c7 !important;
    }

    .pay-sheet .bg-primary {
        background-color: #0284c7 !important;
        color: #ffffff !important;
    }

    .link-icon-box {
        background-color: #e0f2fe !important;
    }

    .link-icon-box i {
        color: #0284c7 !important;
    }

    .pay-notes-box {
        background-color: #f8fafc !important;
        border-color: #e2e8f0 !important;
    }

    .pay-advance-banner {
        background-color: #f0f9ff !important;
        border: 1px solid #bae6fd !important;
    }
    .pay-advance-banner .banner-title {
        color: #0f172a !important;
    }
    .pay-advance-banner .banner-sub {
        color: #475569 !important;
    }

    /* ── Light Mode Explicit Styles ───────── */
    html:not(.app-skin-dark) .pay-sheet {
        background: #ffffff !important;
        border-color: #e2e8f0 !important;
        color: #1e293b !important;
    }
    html:not(.app-skin-dark) .pay-sheet .text-dark {
        color: #0f172a !important;
    }
    html:not(.app-skin-dark) .pay-sheet .text-muted {
        color: #64748b !important;
    }
    html:not(.app-skin-dark) .pay-sheet .text-secondary {
        color: #475569 !important;
    }
    html:not(.app-skin-dark) .pay-sheet .bg-light,
    html:not(.app-skin-dark) .pay-sheet [class*="bg-light"] {
        background-color: #f8fafc !important;
        border-color: #e2e8f0 !important;
    }

    /* ── Corner Ribbon ───────────────────── */
    .pay-ribbon-wrap { position:absolute;top:0;right:0;width:80px;height:80px;overflow:hidden;pointer-events:none;z-index:10; }
    .pay-ribbon-wrap .ribbon {
        position:absolute;top:16px;right:-24px;width:105px;
        transform:rotate(45deg);text-align:center;font-size:9px;
        font-weight:800;letter-spacing:1px;text-transform:uppercase;
        padding:4px 0;color:#fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.15);
    }
    .rib-confirmed { background:#16a34a; }
    .rib-draft     { background:#64748b; }
    .rib-cancelled { background:#dc2626; }

    /* ── Allocations Table ──────────────── */
    .alloc-table { width: 100%; border-collapse: collapse; }
    .alloc-table thead th {
        background: #f8fafc;
        color: #475569;
        font-size: 10.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        padding: 10px 12px;
        border-top: 1px solid #e2e8f0;
        border-bottom: 2px solid #cbd5e1;
    }
    .alloc-table tbody td {
        padding: 11px 12px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
        color: #1e293b;
        vertical-align: middle;
    }
    .alloc-table tbody tr:hover { background: #fafcff; }
    .alloc-table tfoot td {
        padding: 10px 12px;
        background: #f8fafc;
        border-top: 1.5px solid #cbd5e1;
        font-size: 13px;
        font-weight: 700;
        color: #0f172a;
    }

    /* ── Unallocated notice ──────────────── */
    .no-alloc {
        padding: 32px;
        text-align: center;
        color: #94a3b8;
    }
    .no-alloc i { font-size: 36px; display: block; margin-bottom: 8px; opacity: .4; }

    /* ==================== DARK MODE SUPPORT ==================== */
    html.app-skin-dark .pay-sheet {
        background-color: #0f172a !important;
        border-color: #1e293b !important;
        color: #e2e8f0 !important;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.45) !important;
    }

    html.app-skin-dark .pay-sheet .pay-main-title {
        color: #38bdf8 !important;
    }

    html.app-skin-dark .pay-sheet .text-dark,
    html.app-skin-dark .pay-sheet h2,
    html.app-skin-dark .pay-sheet h4,
    html.app-skin-dark .pay-sheet h6,
    html.app-skin-dark .pay-sheet strong {
        color: #f8fafc !important;
    }

    html.app-skin-dark .pay-sheet .text-muted {
        color: #94a3b8 !important;
    }

    html.app-skin-dark .pay-sheet .text-secondary {
        color: #cbd5e1 !important;
    }

    html.app-skin-dark .pay-sheet .border,
    html.app-skin-dark .pay-sheet .border-bottom,
    html.app-skin-dark .pay-sheet .border-top {
        border-color: #1e293b !important;
    }

    html.app-skin-dark .pay-sheet .bg-light,
    html.app-skin-dark .pay-sheet [class*="bg-light"] {
        background-color: #1e293b !important;
        border-color: #334155 !important;
    }

    html.app-skin-dark .pay-sheet .alloc-table thead th {
        background: #0b1329 !important;
        color: #94a3b8 !important;
        border-top-color: #1e293b !important;
        border-bottom-color: #334155 !important;
    }

    html.app-skin-dark .pay-sheet .alloc-table tbody td {
        border-bottom-color: #1e293b !important;
        color: #e2e8f0 !important;
    }

    html.app-skin-dark .pay-sheet .alloc-table tbody tr:hover {
        background: #1e293b !important;
    }

    html.app-skin-dark .pay-sheet .alloc-table tfoot td {
        background: #0b1329 !important;
        border-top-color: #334155 !important;
        color: #f8fafc !important;
    }

    html.app-skin-dark .pay-sheet .pay-advance-banner {
        background-color: #0c2d48 !important;
        border-color: #0284c7 !important;
    }
    html.app-skin-dark .pay-sheet .pay-advance-banner .banner-title {
        color: #f8fafc !important;
    }
    html.app-skin-dark .pay-sheet .pay-advance-banner .banner-sub {
        color: #94a3b8 !important;
    }
    html.app-skin-dark .pay-sheet .pay-advance-banner strong {
        color: #ffffff !important;
    }

    html.app-skin-dark .pay-sheet .pay-notes-box {
        background-color: #1e293b !important;
        border-color: #334155 !important;
    }

    /* ── Print Styles ────────────────────── */
    @media print {
        @page {
            size: A4 portrait;
            margin: 0;
        }

        body > *,
        .nxl-container,
        .nxl-content,
        .page-header,
        .main-content,
        .nxl-navigation,
        .nxl-header,
        footer,
        .loader-bg {
            visibility: hidden;
        }

        .pay-sheet,
        .pay-sheet * {
            visibility: visible;
        }

        html, body {
            margin: 0 !important;
            padding: 0 !important;
            background: #fff !important;
        }

        .nxl-container,
        .nxl-content,
        .main-content,
        .page-header,
        .row, .col-12 {
            margin: 0 !important;
            padding: 0 !important;
        }

        .pay-sheet {
            position: fixed;
            top: 0;
            left: 0;
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 36px 44px !important;
            border: none !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            background: #ffffff !important;
            color: #000000 !important;
        }

        .pay-sheet .pay-main-title {
            color: #0284c7 !important;
        }

        .pay-sheet .pay-avatar-box,
        .pay-sheet .pay-vendor-avatar {
            background-color: #0284c7 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .pay-sheet .text-dark,
        .pay-sheet h2,
        .pay-sheet h4,
        .pay-sheet h6,
        .pay-sheet strong {
            color: #000000 !important;
        }

        .d-print-none,
        .pay-ribbon-wrap {
            display: none !important;
            visibility: hidden !important;
        }
    }
</style>
@endpush

@section('content')

    <div class="pay-sheet mb-5">

        {{-- Ribbon --}}
        <div class="pay-ribbon-wrap">
            @php 
                // Exactly matching the dark slate ribbon from sales payment show page
                $rc = match($payment->status){ 'Cancelled' => 'rib-cancelled', default => 'rib-draft' }; 
            @endphp
            <div class="ribbon {{ $rc }}">{{ $payment->status }}</div>
        </div>

        {{-- ══════ 1. DOCUMENT HEADER ══════ --}}
        <div class="row align-items-start pb-4 border-bottom mb-4">
            @php
                $buyerOrg = billing_organization_details($payment);
            @endphp

            {{-- Left : Company / Buyer Details --}}
            <div class="col-7">
                <div class="d-flex align-items-center mb-3">
                    <div class="d-flex align-items-center justify-content-center fw-bold text-white fs-3 me-3 pay-avatar-box shadow-sm"
                         style="width:50px;height:50px;border-radius:8px;flex-shrink:0;">
                        {{ strtoupper(substr($buyerOrg['name'] ?? 'W', 0, 1)) }}
                    </div>
                    <div>
                        <h4 class="fw-bold text-dark mb-0 fs-17">{{ $buyerOrg['name'] }}</h4>
                        <span class="fs-11 text-muted">Official Payment Receipt</span>
                    </div>
                </div>
                <div class="fs-12 text-secondary" style="line-height:1.75;">
                    @if(!empty($buyerOrg['address']))
                        <div>{{ $buyerOrg['address'] }}</div>
                    @endif
                    <div>
                        @if(!empty($buyerOrg['gstin']))
                            <strong>GSTIN:</strong> {{ $buyerOrg['gstin'] }}
                        @endif
                        @if(!empty($buyerOrg['state_code']) || !empty($buyerOrg['state_name']))
                            @if(!empty($buyerOrg['gstin'])) &nbsp;|&nbsp; @endif
                            <strong>State Code:</strong> {{ $buyerOrg['state_code'] ?? '—' }} ({{ $buyerOrg['state_name'] ?? '—' }})
                        @endif
                    </div>
                    <div>
                        @if(!empty($buyerOrg['email']))
                            <strong>Email:</strong> {{ $buyerOrg['email'] }}
                        @endif
                        @if(!empty($buyerOrg['phone']))
                            @if(!empty($buyerOrg['email'])) &nbsp;|&nbsp; @endif
                            <strong>Phone:</strong> {{ $buyerOrg['phone'] }}
                        @endif
                    </div>
                </div>
            </div>

            {{-- Right : Voucher Title + Meta --}}
            <div class="col-5 text-end">
                <h2 class="pay-main-title fw-black text-uppercase mb-1" style="font-size:22px;letter-spacing:1px;">
                    {{ $payment->payment_type === 'Advance' ? 'ADVANCE RECEIPT' : 'PAYMENT RECEIPT' }}
                </h2>
                <div class="fs-14 fw-bold text-dark mb-3"># {{ $payment->payment_number }}</div>

                <div class="fs-12 text-secondary" style="line-height:2;">
                    <div class="d-flex justify-content-end gap-2">
                        <span class="text-muted">Payment Date:</span>
                        <strong class="text-dark">{{ $payment->payment_date ? $payment->payment_date->format('d-M-Y') : '—' }}</strong>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <span class="text-muted">Payment Method:</span>
                        <strong class="text-dark">{{ $payment->payment_method ?: '—' }}</strong>
                    </div>
                    @if($payment->reference_number)
                        <div class="d-flex justify-content-end gap-2">
                            <span class="text-muted">Bank Ref / UTR No:</span>
                            <strong class="text-dark font-monospace">{{ $payment->reference_number }}</strong>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ══════ 2. SUPPLIER & PAYMENT SUMMARY (2 EQUAL BOX COLUMNS) ══════ --}}
        <div class="row g-4 mb-4 fs-12 text-dark">
            <!-- Left: Supplier / Vendor Details -->
            <div class="col-6">
                <div class="p-3 bg-light bg-opacity-40 rounded border h-100 d-flex flex-column justify-content-between">
                    <div>
                        <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-3" style="letter-spacing: 0.5px;">Paid To (Supplier / Vendor):</span>
                        
                        <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                            <div class="avatar-text pay-vendor-avatar text-white fw-bold me-3 d-flex align-items-center justify-content-center rounded-3 shadow-sm" style="width: 42px; height: 42px; font-size: 16px; flex-shrink: 0;">
                                {{ strtoupper(substr($payment->vendor?->name ?: 'V', 0, 1)) }}
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0 fs-14">{{ $payment->vendor?->name ?: '—' }}</h6>
                                <span class="fs-11 text-muted">{{ $payment->vendor?->company_name ?: 'Valued Supplier' }}</span>
                            </div>
                        </div>

                        <div class="fs-12 text-secondary mb-2">
                            <div class="d-flex align-items-center mb-1.5">
                                <i class="feather-mail me-2 text-muted fs-12" style="width: 16px;"></i>
                                <span class="text-muted me-1">Email:</span>
                                <strong class="text-dark">{{ $payment->vendor?->email ?: '—' }}</strong>
                            </div>
                            <div class="d-flex align-items-center mb-1.5">
                                <i class="feather-phone me-2 text-muted fs-12" style="width: 16px;"></i>
                                <span class="text-muted me-1">Phone:</span>
                                <strong class="text-dark">{{ $payment->vendor?->phone ?: '—' }}</strong>
                            </div>
                            @if($payment->vendor?->gstin)
                                <div class="d-flex align-items-center mb-1.5">
                                    <i class="feather-hash me-2 text-muted fs-12" style="width: 16px;"></i>
                                    <span class="text-muted me-1">GSTIN:</span>
                                    <strong class="text-dark font-monospace">{{ $payment->vendor->gstin }}</strong>
                                </div>
                            @endif
                        </div>

                        @php
                            $vendorAddr = $payment->vendor?->address;
                        @endphp

                        @if ($vendorAddr)
                            <div class="mt-2 pt-2 border-top text-secondary fs-11">
                                <strong class="text-dark d-block mb-1"><i class="feather-map-pin me-1 fs-11 text-muted"></i>Vendor Address:</strong>
                                <span style="white-space: pre-wrap;" class="text-muted leading-relaxed">{{ $vendorAddr }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right: Payment & Status Summary -->
            <div class="col-6">
                <div class="p-3 bg-light bg-opacity-40 rounded border h-100">
                    <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-2" style="letter-spacing: 0.5px;">Payment Summary &amp; Status:</span>

                    <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                        <span class="text-muted">Total Amount Paid:</span>
                        <strong class="fw-black text-primary fs-15">{{ format_currency($payment->amount) }}</strong>
                    </div>

                    @php
                        $allocTotal = $payment->allocations->sum('allocated_amount');
                        $unallocated = max(0.0, (float)$payment->amount - (float)$allocTotal);
                    @endphp

                    <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                        <span class="text-muted">Allocated Amount:</span>
                        <span class="fw-bold text-dark">{{ format_currency($allocTotal) }}</span>
                    </div>

                    @if($unallocated > 0.001)
                        <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                            <span class="text-muted">Unallocated (Advance):</span>
                            <span class="fw-bold text-warning font-monospace">{{ format_currency($unallocated) }}</span>
                        </div>
                    @endif

                    <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                        <span class="text-muted">Paid From Account:</span>
                        <span class="fw-semibold text-dark">
                            @if($payment->bankAccount)
                                {{ $payment->bankAccount->code }} — {{ $payment->bankAccount->name }}
                            @else
                                {{ $payment->payment_method === 'Cash' ? '1010 — Cash-in-Hand' : '1020 — Bank Account' }}
                            @endif
                        </span>
                    </div>

                    <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                        <span class="text-muted">Payment Method:</span>
                        <span class="fw-semibold text-dark">{{ $payment->payment_method ?: '—' }}</span>
                    </div>

                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Payment Status:</span>
                        <span class="fw-bold text-dark">
                            {{ $payment->status }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════ 3. ADVANCE ADJUSTMENTS & BILL ALLOCATIONS ══════ --}}
        @php
            // Calculate total advance adjusted on all linked bills across this settlement
            $allLinkedAdvanceSettled = 0.0;
            $advanceVouchers = [];

            foreach ($payment->allocations as $alloc) {
                if ($alloc->bill) {
                    $otherAllocs = $alloc->bill->allocations->filter(function($a) use ($payment) {
                        return $a->vendor_payment_id != $payment->id && ($a->vendorPayment?->payment_type === 'Advance' || $a->payment?->payment_type === 'Advance');
                    });
                    foreach ($otherAllocs as $oa) {
                        $allLinkedAdvanceSettled += (float)$oa->allocated_amount;
                        $vNum = $oa->vendorPayment?->payment_number ?? $oa->payment?->payment_number;
                        if ($vNum && !in_array($vNum, $advanceVouchers)) {
                            $advanceVouchers[] = $vNum;
                        }
                    }
                }
            }
        @endphp

        {{-- Highlight Banner if Advance Credit was adjusted to settle these bills --}}
        @if($allLinkedAdvanceSettled > 0.001)
            <div class="p-3 mb-4 rounded border d-flex align-items-center justify-content-between flex-wrap gap-2 pay-advance-banner">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-primary text-white fs-11 px-2.5 py-1 fw-bold">Advance Credit Adjusted</span>
                        <strong class="banner-title fs-13">Advance Applied on Linked Bills:</strong>
                        <span class="text-primary fw-bold font-monospace fs-14">{{ format_currency($allLinkedAdvanceSettled) }}</span>
                    </div>
                    <small class="banner-sub d-block fs-11">
                        Settled via Advance Payment Voucher(s): <strong>{{ implode(', ', $advanceVouchers) }}</strong>. Total Settled: <strong>{{ format_currency($payment->amount + $allLinkedAdvanceSettled) }}</strong> (Bank {{ format_currency($payment->amount) }} + Advance {{ format_currency($allLinkedAdvanceSettled) }}).
                    </small>
                </div>
                <span class="badge bg-soft-success text-success border border-success-subtle px-3 py-1.5 fs-11 font-monospace fw-bold">
                    <i class="feather-check-circle me-1"></i>Fully Adjusted
                </span>
            </div>
        @endif

        <div class="border-top pt-4 mb-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="d-flex align-items-center justify-content-center link-icon-box"
                         style="width:28px;height:28px;border-radius:6px;">
                        <i class="feather-link fs-12"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-0 fs-14">
                        {{ $payment->payment_type === 'Advance' ? 'Advance Allocations / Adjustments' : 'Bill Allocations / Adjustments' }}
                    </h6>
                </div>
                <span class="badge bg-soft-info text-info fs-11 px-2">
                    {{ $payment->allocations->count() }} {{ Str::plural('Record', $payment->allocations->count()) }}
                </span>
            </div>

            <table class="alloc-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">Linked Document</th>
                        <th style="width: 15%;">Type</th>
                        <th style="width: 14%;">Document Date</th>
                        <th class="text-end" style="width: 15%;">Bill Total</th>
                        <th class="text-end" style="width: 16%;">Paid from Bank</th>
                        <th class="text-end" style="width: 15%;">Advance Adjusted</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($payment->allocations as $alloc)
                    @php
                        $bill = $alloc->bill;
                        
                        // Find advance credit applied on this specific bill from advance vouchers
                        $advAppliedOnThisBill = 0.0;
                        $advVoucherNums = [];
                        if ($bill) {
                            $advAllocs = $bill->allocations->filter(function($a) use ($payment) {
                                return $a->vendor_payment_id != $payment->id && ($a->vendorPayment?->payment_type === 'Advance' || $a->payment?->payment_type === 'Advance');
                            });
                            $advAppliedOnThisBill = (float)$advAllocs->sum('allocated_amount');
                            foreach ($advAllocs as $aa) {
                                $num = $aa->vendorPayment?->payment_number ?? $aa->payment?->payment_number;
                                if ($num && !in_array($num, $advVoucherNums)) {
                                    $advVoucherNums[] = $num;
                                }
                            }
                        }

                        $totalBillSettled = (float)$alloc->allocated_amount + $advAppliedOnThisBill;
                    @endphp
                    <tr>
                        <td>
                            @if($bill)
                                <a href="{{ route('purchase.bills.show', $bill->id) }}"
                                   class="text-primary fw-bold text-decoration-none d-block">
                                    <i class="feather-file-text me-1 fs-12"></i>{{ $bill->bill_number }}
                                </a>
                                @if($bill->purchaseOrder)
                                    <span class="text-muted fs-11 d-block mt-0.5">
                                        PO: {{ $bill->purchaseOrder->purchase_order_number }}
                                    </span>
                                @endif
                            @else
                                <span class="text-muted fst-italic fs-12">Unallocated</span>
                            @endif
                        </td>
                        <td>
                            @if($payment->payment_type === 'Advance')
                                <span class="badge bg-soft-info text-info fs-11 px-2">Advance Credit</span>
                            @else
                                <span class="badge bg-soft-info text-info fs-11 px-2">Vendor Bill</span>
                            @endif
                        </td>
                        <td>
                            <span class="text-dark fs-12">
                                {{ $bill?->bill_date ? $bill->bill_date->format('d-M-Y') : '—' }}
                            </span>
                        </td>
                        <td class="text-end font-monospace fw-semibold text-dark">
                            {{ format_currency($bill?->grand_total ?: 0) }}
                        </td>
                        <td class="text-end font-monospace fw-bold text-dark">
                            {{ format_currency($alloc->allocated_amount) }}
                        </td>
                        <td class="text-end font-monospace">
                            @if($advAppliedOnThisBill > 0.001)
                                <span class="fw-bold text-primary">{{ format_currency($advAppliedOnThisBill) }}</span>
                                @if(count($advVoucherNums) > 0)
                                    <small class="text-muted d-block fs-10 font-monospace">({{ implode(', ', $advVoucherNums) }})</small>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="no-alloc">
                            <i class="feather-inbox"></i>
                            <span class="fs-13 d-block fw-semibold text-dark">No direct bill allocations recorded.</span>
                            <span class="fs-11 text-muted">This payment was recorded as an unallocated advance or historical opening balance settlement.</span>
                        </td>
                    </tr>
                @endforelse
                </tbody>
                @if($payment->allocations->count() > 0)
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end text-muted fw-bold">Totals:</td>
                            <td class="text-end font-monospace text-dark">
                                {{ format_currency($payment->allocations->sum(fn($a) => $a->bill?->grand_total ?: 0)) }}
                            </td>
                            <td class="text-end font-monospace text-dark fw-bold">
                                {{ format_currency($allocTotal) }}
                            </td>
                            <td class="text-end font-monospace text-primary fw-bold">
                                {{ format_currency($allLinkedAdvanceSettled) }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        {{-- ══════ 4. NOTES & REMARKS ══════ --}}
        @if($payment->notes)
            <div class="p-3 pay-notes-box rounded border mb-4 fs-12">
                <strong class="d-block mb-1 text-dark">
                    <i class="feather-file-text me-1 text-primary"></i>Payment Remarks / Notes:
                </strong>
                <span class="text-secondary leading-relaxed">{{ $payment->notes }}</span>
            </div>
        @endif

        {{-- ══════ 5. FOOTER & AUDIT ══════ --}}
        <div class="d-flex justify-content-between align-items-center text-muted fs-11 pt-3 border-top flex-wrap gap-2">
            <div>
                <span>Recorded by: <strong>{{ $payment->creator?->name ?? 'System Administrator' }}</strong></span>
                <span class="mx-1">•</span>
                <span>Created: {{ $payment->created_at ? $payment->created_at->format('d-M-Y h:i A') : '—' }}</span>
            </div>
            <div class="d-flex align-items-center gap-1 font-monospace">
                <i class="feather-shield text-success fs-12"></i>
                <span>General Ledger Auto-Posted Voucher</span>
            </div>
        </div>

    </div>

@endsection
