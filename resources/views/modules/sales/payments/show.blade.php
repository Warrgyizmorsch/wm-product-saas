@extends('layouts.duralux')

@section('title', 'Payment Receipt | ' . $payment->payment_number)
@section('page-title', 'Payment ' . $payment->payment_number)
@section('breadcrumb', 'Sales / Payments / Details')

@section('page-actions')
    <a href="{{ route('sales.payments.index') }}" class="action-dropdown-btn" title="Back to Payments" data-bs-toggle="tooltip">
        <i class="feather feather-arrow-left"></i>
    </a>

    <a href="javascript:void(0)" onclick="window.print()" class="btn btn-sm btn-outline-secondary fw-bold px-3 d-print-none">
        <i class="feather-printer me-1.5"></i>Print
    </a>

    @if ($payment->status === 'Draft')
        <form action="{{ route('sales.payments.confirm', $payment->id) }}" method="POST" class="d-inline d-print-none">
            @csrf
            <button type="submit" class="btn btn-sm btn-success fw-bold px-3">
                <i class="feather-check-circle me-1"></i>Confirm Payment
            </button>
        </form>
    @endif
@endsection

@push('styles')
<style>
    /* ── Sheet ──────────────────────────── */
    .pay-sheet {
        max-width: 900px;
        margin: 0 auto;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,.05);
        padding: 40px;
        position: relative;
        overflow: hidden;
        font-family: 'Inter', system-ui, sans-serif;
    }

    /* ── Corner Ribbon ───────────────────── */
    .pay-ribbon-wrap { position:absolute;top:0;right:0;width:80px;height:80px;overflow:hidden;pointer-events:none;z-index:10; }
    .pay-ribbon-wrap .ribbon {
        position:absolute;top:16px;right:-24px;width:105px;
        transform:rotate(45deg);text-align:center;font-size:9px;
        font-weight:800;letter-spacing:1px;text-transform:uppercase;
        padding:4px 0;color:#fff;
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
        }

        .pay-sheet .bg-light,
        .pay-sheet [class*="bg-light"],
        .pay-sheet [class*="bg-opacity"] {
            background-color: #ffffff !important;
            background: #ffffff !important;
        }

        .pay-sheet .rounded.border,
        .pay-sheet .border {
            border-color: #e2e8f0 !important;
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
            @php $rc = match($payment->status){ 'Confirmed'=>'rib-confirmed','Cancelled'=>'rib-cancelled',default=>'rib-draft' }; @endphp
            <div class="ribbon {{ $rc }}">{{ $payment->status }}</div>
        </div>

        {{-- ══════ 1. DOCUMENT HEADER ══════ --}}
        <div class="row align-items-start pb-4 border-bottom mb-4">

            {{-- Left : Company --}}
            <div class="col-7">
                <div class="d-flex align-items-center mb-3">
                    <div class="d-flex align-items-center justify-content-center fw-bold text-white fs-3 me-3"
                         style="width:50px;height:50px;border-radius:8px;background:#0284c7;flex-shrink:0;">
                        {{ strtoupper(substr(tenant()?->name ?? 'E', 0, 1)) }}
                    </div>
                    <div>
                        <h4 class="fw-bold text-dark mb-0 fs-17">{{ tenant()?->name ?? 'SaaS ERP Workspace' }}</h4>
                        <span class="fs-11 text-muted">Official Payment Receipt</span>
                    </div>
                </div>
                <div class="fs-12 text-secondary" style="line-height:1.75;">
                    <div>H-1, Industrial Area, Sukher, Udaipur 313001, Rajasthan, India</div>
                    <div><strong>GSTIN:</strong> 08AAFCS1234E1Z0 &nbsp;|&nbsp; <strong>State Code:</strong> 08 (Rajasthan)</div>
                    <div><strong>Email:</strong> {{ tenant()?->billing_email ?? 'billing@sasserp.com' }} &nbsp;|&nbsp; <strong>Phone:</strong> +91 294 2440230</div>
                </div>
            </div>

            {{-- Right : Receipt title + meta --}}
            <div class="col-5 text-end">
                <h2 class="fw-black text-uppercase mb-1" style="color:#0284c7;font-size:22px;letter-spacing:1px;">PAYMENT RECEIPT</h2>
                <div class="fs-14 fw-bold text-dark mb-3"># {{ $payment->payment_number }}</div>

                <div class="fs-12 text-secondary" style="line-height:2;">
                    <div class="d-flex justify-content-end gap-2">
                        <span class="text-muted">Payment Date:</span>
                        <strong class="text-dark">{{ date('d-M-Y', strtotime($payment->payment_date)) }}</strong>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <span class="text-muted">Payment Method:</span>
                        <strong class="text-dark">{{ $payment->payment_method ?: '—' }}</strong>
                    </div>
                    @if($payment->reference_no)
                    <div class="d-flex justify-content-end gap-2">
                        <span class="text-muted">Bank / Ref No:</span>
                        <strong class="text-dark">{{ $payment->reference_no }}</strong>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ══════ 2. CUSTOMER & PAYMENT SUMMARY (2 EQUAL BOX COLUMNS) ══════ --}}
        <div class="row g-4 mb-4 fs-12 text-dark">
            <!-- Left: Customer Details -->
            <div class="col-6">
                <div class="p-3 bg-light bg-opacity-40 rounded border h-100 d-flex flex-column justify-content-between">
                    <div>
                        <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-3" style="letter-spacing: 0.5px;">Received From (Customer):</span>
                        
                        <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                            <div class="avatar-text bg-primary text-white fw-bold me-3 d-flex align-items-center justify-content-center rounded-3 shadow-sm" style="width: 42px; height: 42px; font-size: 16px; flex-shrink: 0; background-color: #0284c7 !important;">
                                {{ strtoupper(substr($payment->customer?->name ?: 'C', 0, 1)) }}
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0 fs-14">{{ $payment->customer?->name ?: '—' }}</h6>
                                <span class="fs-11 text-muted">Valued Customer</span>
                            </div>
                        </div>

                        <div class="fs-12 text-secondary mb-2">
                            <div class="d-flex align-items-center mb-1.5">
                                <i class="feather-mail me-2 text-muted fs-12" style="width: 16px;"></i>
                                <span class="text-muted me-1">Email:</span>
                                <strong class="text-dark">{{ $payment->customer?->email ?: '—' }}</strong>
                            </div>
                            <div class="d-flex align-items-center mb-1.5">
                                <i class="feather-phone me-2 text-muted fs-12" style="width: 16px;"></i>
                                <span class="text-muted me-1">Phone:</span>
                                <strong class="text-dark">{{ $payment->customer?->phone ?: '—' }}</strong>
                            </div>
                        </div>

                        @php
                            $firstAlloc = $payment->allocations->first();
                            $billingAddr = $payment->customer?->address ?? $firstAlloc?->invoice?->salesOrder?->billing_address ?? $firstAlloc?->salesOrder?->billing_address;
                        @endphp

                        @if ($billingAddr)
                            <div class="mt-2 pt-2 border-top text-secondary fs-11">
                                <strong class="text-dark d-block mb-1"><i class="feather-map-pin me-1 fs-11 text-muted"></i>Billing Address:</strong>
                                <span style="white-space: pre-wrap;" class="text-muted leading-relaxed">{{ $billingAddr }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right: Payment & Status Summary -->
            <div class="col-6">
                <div class="p-3 bg-light bg-opacity-40 rounded border h-100">
                    <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-2" style="letter-spacing: 0.5px;">Payment Summary & Status:</span>

                    <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                        <span class="text-muted">Total Amount Received:</span>
                        <strong class="fw-black text-primary fs-15">₹{{ number_format($payment->amount, 2) }}</strong>
                    </div>

                    @php
                        $allocTotal = $payment->allocations->sum('allocated_amount');
                        $unallocated = $payment->amount - $allocTotal;
                    @endphp

                    <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                        <span class="text-muted">Allocated Amount:</span>
                        <span class="fw-bold text-dark">₹{{ number_format($allocTotal, 2) }}</span>
                    </div>

                    @if($unallocated > 0)
                        <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                            <span class="text-muted">Unallocated (Advance):</span>
                            <span class="fw-bold text-warning">₹{{ number_format($unallocated, 2) }}</span>
                        </div>
                    @endif

                    <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                        <span class="text-muted">Payment Method:</span>
                        <span class="fw-semibold text-dark">{{ $payment->payment_method ?: '—' }}</span>
                    </div>

                    @if($payment->reference_no)
                        <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                            <span class="text-muted">Bank Ref / Cheque No:</span>
                            <span class="fw-semibold text-dark">{{ $payment->reference_no }}</span>
                        </div>
                    @endif

                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Payment Status:</span>
                        <span class="fw-bold {{ $payment->status === 'Confirmed' ? 'text-success' : ($payment->status === 'Cancelled' ? 'text-danger' : 'text-secondary') }}">
                            {{ $payment->status }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════ 3. ALLOCATIONS ══════ --}}
        <div class="border-top pt-4 mb-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="d-flex align-items-center justify-content-center"
                         style="width:28px;height:28px;border-radius:6px;background:#e0f2fe;">
                        <i class="feather-link fs-12" style="color:#0284c7;"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-0 fs-14">Invoice Allocations / Adjustments</h6>
                </div>
                <span class="badge bg-soft-info text-info fs-11 px-2">
                    {{ $payment->allocations->count() }} {{ Str::plural('Record', $payment->allocations->count()) }}
                </span>
            </div>

            <table class="alloc-table">
                <thead>
                    <tr>
                        <th style="width:35%;">Linked Document</th>
                        <th style="width:20%;">Type</th>
                        <th style="width:20%;">Document Date</th>
                        <th class="text-end" style="width:25%;">Allocated Amount</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($payment->allocations as $alloc)
                    <tr>
                        <td>
                            @if($alloc->invoice_id)
                                <a href="{{ route('sales.invoices.show', $alloc->invoice_id) }}"
                                   class="text-primary fw-bold text-decoration-none">
                                    <i class="feather-file-text me-1 fs-12"></i>{{ $alloc->invoice?->invoice_number }}
                                </a>
                            @elseif($alloc->sales_order_id)
                                <a href="{{ route('sales.orders.show', $alloc->sales_order_id) }}"
                                   class="text-primary fw-bold text-decoration-none">
                                    <i class="feather-shopping-cart me-1 fs-12"></i>{{ $alloc->salesOrder?->sales_order_number }}
                                </a>
                            @else
                                <span class="text-muted fst-italic fs-12">Unallocated</span>
                            @endif
                        </td>
                        <td>
                            @if($alloc->invoice_id)
                                <span class="badge bg-soft-info text-info px-2 py-1 fs-11">Customer Invoice</span>
                            @elseif($alloc->sales_order_id)
                                <span class="badge bg-soft-warning text-warning px-2 py-1 fs-11">Sales Order Advance</span>
                            @else
                                <span class="text-muted fs-12">—</span>
                            @endif
                        </td>
                        <td class="text-muted fs-12">
                            {{ $alloc->invoice?->invoice_date ? date('d M Y', strtotime($alloc->invoice->invoice_date)) : '—' }}
                        </td>
                        <td class="text-end fw-bold text-dark">
                            ₹{{ number_format($alloc->allocated_amount, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <div class="no-alloc">
                                <i class="feather-inbox"></i>
                                <div class="fw-semibold fs-13 mb-1">No Allocations Linked</div>
                                <div class="fs-12">This payment is available as advance credit against future invoices.</div>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
                @if($payment->allocations->count() > 0)
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-end text-muted fw-semibold">Total Allocated Amount:</td>
                        <td class="text-end" style="color:#0284c7;">₹{{ number_format($payment->allocations->sum('allocated_amount'), 2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

        {{-- ══════ 4. NOTES ══════ --}}
        @if($payment->notes)
        <div class="border-top pt-3 mb-4">
            <div class="pay-section-title">Receipt Notes / Remarks</div>
            <p class="fs-12 text-muted mb-0 p-3 rounded border" style="background:#f8fafc;white-space:pre-wrap;line-height:1.7;">{{ $payment->notes }}</p>
        </div>
        @endif

        {{-- ══════ 5. FOOTER SIGNATURE ══════ --}}
        <div class="row pt-4 border-top align-items-end mt-2 fs-11 text-muted">
            <div class="col-7">
                <div>This is a computer generated payment receipt and serves as official acknowledgement of payment received.</div>
                <div class="mt-1 fs-10 text-muted">{{ tenant()?->name ?? 'SaaS ERP' }} &nbsp;|&nbsp; Generated: {{ date('d M Y, h:i A') }}</div>
            </div>
            <div class="col-5 text-end">
                <div class="fw-bold text-dark mb-5">For {{ tenant()?->name ?? 'SaaS ERP' }}</div>
                <div class="border-top d-inline-block pt-1 px-4 text-muted fw-semibold">Authorized Signatory</div>
            </div>
        </div>

    </div>
@endsection
