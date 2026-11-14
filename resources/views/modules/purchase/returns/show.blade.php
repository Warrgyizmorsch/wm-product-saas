@extends('layouts.duralux')

@section('title', 'Purchase Return | ' . $return->return_number . ' | SaaS ERP')
@section('page-title', 'Purchase Return ' . $return->return_number)
@section('breadcrumb')
    <a href="{{ route('purchase.returns.index') }}">{{ __('purchase.purchase_returns') }}</a> &gt; {{ $return->return_number }}
@endsection

@section('page-actions')
    <div class="d-flex gap-2 flex-wrap text-dark d-print-none">
        <a href="{{ route('purchase.returns.index') }}" class="btn btn-sm btn-light border fw-semibold px-3">
            <i class="feather-arrow-left me-1.5"></i>{{ __('purchase.back_to_returns') }}
        </a>

        @if (in_array($return->status, ['Pending', 'Draft']))
            <form action="{{ route('purchase.returns.approve', $return->id) }}" method="POST" id="approveReturnForm" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-success text-white fw-bold px-3 shadow-sm" onclick="return confirm('Approve this return and remove stock from inventory?')">
                    <i class="feather-check-circle me-1.5"></i>{{ __('purchase.approve_and_remove_inventory') }}
                </button>
            </form>
        @endif

        <a href="{{ route('purchase.returns.download', $return->id) }}" class="btn btn-sm btn-primary fw-bold px-3 shadow-sm">
            <i class="feather-download me-1.5"></i>Download PDF
        </a>

        <button onclick="window.print()" class="btn btn-sm btn-light border fw-semibold px-3 shadow-sm">
            <i class="feather-printer me-1.5"></i>Print Return Note
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
    .rib-pending   { background:#d97706; }
    .rib-draft     { background:#64748b; }
    .rib-cancelled { background:#dc2626; }

    /* ── Items Table ─────────────────────── */
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
                $rc = match($return->status){
                    'Completed', 'Approved' => 'rib-confirmed',
                    'Pending' => 'rib-pending',
                    'Cancelled', 'Rejected' => 'rib-cancelled',
                    default => 'rib-draft'
                }; 
            @endphp
            <div class="ribbon {{ $rc }}">{{ $return->status ?: 'PENDING' }}</div>
        </div>

        {{-- ══════ 1. DOCUMENT HEADER ══════ --}}
        <div class="row align-items-start pb-4 border-bottom mb-4">
            @php
                $buyerOrg = billing_organization_details($return);
                $displayRefundTotal = $return->total_refund_amount > 0
                    ? $return->total_refund_amount
                    : ($return->total_amount > 0 ? $return->total_amount : $return->items->sum(fn($i) => (float)$i->quantity * (float)$i->unit_price));
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
                        <span class="fs-11 text-muted">Official Purchase Debit Note / Return Voucher</span>
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
                    DEBIT NOTE / RETURN
                </h2>
                <div class="fs-14 fw-bold text-dark mb-3"># {{ $return->return_number }}</div>

                <div class="fs-12 text-secondary" style="line-height:2;">
                    <div class="d-flex justify-content-end gap-2">
                        <span class="text-muted">Return Date:</span>
                        <strong class="text-dark">{{ $return->return_date ? date('d-M-Y', strtotime($return->return_date)) : '—' }}</strong>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <span class="text-muted">Return Status:</span>
                        <strong class="{{ in_array($return->status, ['Completed', 'Approved']) ? 'text-success' : 'text-warning' }}">
                            {{ $return->status ?: 'Pending' }}
                        </strong>
                    </div>
                    @if($return->purchaseOrder)
                        <div class="d-flex justify-content-end gap-2">
                            <span class="text-muted">Linked PO:</span>
                            <a href="{{ route('purchase.orders.show', $return->purchaseOrder->id) }}" class="fw-bold text-primary font-monospace">
                                {{ $return->purchaseOrder->purchase_order_number ?? $return->purchaseOrder->order_number }}
                            </a>
                        </div>
                    @endif
                    @if($return->goodsReceiptNote)
                        <div class="d-flex justify-content-end gap-2">
                            <span class="text-muted">Linked GRN:</span>
                            <span class="fw-bold text-dark font-monospace">{{ $return->goodsReceiptNote->grn_number }}</span>
                        </div>
                    @endif
                    @if($return->vendorBill)
                        <div class="d-flex justify-content-end gap-2">
                            <span class="text-muted">Linked Bill:</span>
                            <a href="{{ route('purchase.bills.show', $return->vendorBill->id) }}" class="fw-bold text-primary font-monospace">
                                {{ $return->vendorBill->bill_number }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ══════ 2. SUPPLIER & RETURN SUMMARY (2 EQUAL BOX COLUMNS) ══════ --}}
        <div class="row g-4 mb-4 fs-12 text-dark">
            <!-- Left: Supplier / Vendor Details -->
            <div class="col-6">
                <div class="p-3 bg-light bg-opacity-40 rounded border h-100 d-flex flex-column justify-content-between">
                    <div>
                        <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-3" style="letter-spacing: 0.5px;">Return To (Supplier / Vendor):</span>
                        
                        <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                            <div class="avatar-text pay-vendor-avatar text-white fw-bold me-3 d-flex align-items-center justify-content-center rounded-3 shadow-sm" style="width: 42px; height: 42px; font-size: 16px; flex-shrink: 0;">
                                {{ strtoupper(substr($return->vendor?->name ?: 'V', 0, 1)) }}
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0 fs-14">{{ $return->vendor?->name ?: '—' }}</h6>
                                <span class="fs-11 text-muted">{{ $return->vendor?->company_name ?: 'Valued Supplier' }}</span>
                            </div>
                        </div>

                        <div class="fs-12 text-secondary mb-2">
                            <div class="d-flex align-items-center mb-1.5">
                                <i class="feather-mail me-2 text-muted fs-12" style="width: 16px;"></i>
                                <span class="text-muted me-1">Email:</span>
                                <strong class="text-dark">{{ $return->vendor?->email ?: '—' }}</strong>
                            </div>
                            <div class="d-flex align-items-center mb-1.5">
                                <i class="feather-phone me-2 text-muted fs-12" style="width: 16px;"></i>
                                <span class="text-muted me-1">Phone:</span>
                                <strong class="text-dark">{{ $return->vendor?->phone ?: '—' }}</strong>
                            </div>
                            @if($return->vendor?->gstin)
                                <div class="d-flex align-items-center mb-1.5">
                                    <i class="feather-hash me-2 text-muted fs-12" style="width: 16px;"></i>
                                    <span class="text-muted me-1">GSTIN:</span>
                                    <strong class="text-dark font-monospace">{{ $return->vendor->gstin }}</strong>
                                </div>
                            @endif
                        </div>

                        @php
                            $vendorAddr = $return->vendor?->address;
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

            <!-- Right: Return & Refund Status Summary -->
            <div class="col-6">
                <div class="p-3 bg-light bg-opacity-40 rounded border h-100">
                    <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-2" style="letter-spacing: 0.5px;">Return &amp; Refund Summary:</span>

                    <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                        <span class="text-muted">Total Refund Amount:</span>
                        <strong class="fw-black text-danger fs-15 font-monospace">{{ format_currency($displayRefundTotal) }}</strong>
                    </div>

                    <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                        <span class="text-muted">Total Returned Quantity:</span>
                        <span class="fw-bold text-dark font-monospace">{{ (float)$return->items->sum('quantity') }} Units</span>
                    </div>

                    <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                        <span class="text-muted">Line Items:</span>
                        <span class="fw-semibold text-dark">{{ $return->items->count() }} {{ Str::plural('Item', $return->items->count()) }}</span>
                    </div>

                    <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                        <span class="text-muted">Inventory Impact:</span>
                        <span class="fw-semibold {{ in_array($return->status, ['Completed', 'Approved']) ? 'text-success' : 'text-warning' }}">
                            @if(in_array($return->status, ['Completed', 'Approved']))
                                <i class="feather-check-circle me-1"></i>Stock Deducted
                            @else
                                <i class="feather-clock me-1"></i>Awaiting Approval
                            @endif
                        </span>
                    </div>

                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Settlement Type:</span>
                        <span class="fw-bold text-primary">
                            {{ $return->vendor_bill_id ? 'Vendor Bill Adjustment' : 'Supplier Debit Balance' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════ 3. LINKED REFERENCE BAR (IF PO / GRN / BILL LINKED) ══════ --}}
        @if($return->purchaseOrder || $return->goodsReceiptNote || $return->vendorBill)
            <div class="card border rounded mb-4 shadow-none bg-light bg-opacity-30">
                <div class="card-header bg-light py-2.5 px-3 border-bottom d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <div class="d-flex align-items-center justify-content-center link-icon-box"
                             style="width:26px;height:26px;border-radius:5px;">
                            <i class="feather-link-2 fs-12"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-0 fs-13">
                            Linked Purchase References
                        </h6>
                    </div>
                </div>
                <div class="card-body py-2.5 px-3">
                    <div class="row g-3 fs-12 text-dark align-items-center">
                        @if($return->purchaseOrder)
                            <div class="col-md-4">
                                <span class="text-muted d-block fs-11 text-uppercase fw-bold mb-0.5">Purchase Order</span>
                                <a href="{{ route('purchase.orders.show', $return->purchaseOrder->id) }}" class="fw-bold text-primary font-monospace">
                                    <i class="feather-shopping-cart me-1 fs-11"></i>{{ $return->purchaseOrder->purchase_order_number ?? $return->purchaseOrder->order_number }}
                                </a>
                            </div>
                        @endif
                        @if($return->goodsReceiptNote)
                            <div class="col-md-4">
                                <span class="text-muted d-block fs-11 text-uppercase fw-bold mb-0.5">Goods Receipt (GRN)</span>
                                <span class="fw-bold text-dark font-monospace">
                                    <i class="feather-package me-1 fs-11 text-muted"></i>{{ $return->goodsReceiptNote->grn_number }}
                                </span>
                            </div>
                        @endif
                        @if($return->vendorBill)
                            <div class="col-md-4">
                                <span class="text-muted d-block fs-11 text-uppercase fw-bold mb-0.5">Vendor Bill</span>
                                <a href="{{ route('purchase.bills.show', $return->vendorBill->id) }}" class="fw-bold text-primary font-monospace">
                                    <i class="feather-file-text me-1 fs-11"></i>{{ $return->vendorBill->bill_number }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- ══════ 4. RETURNED ITEMS TABLE ══════ --}}
        <div class="border-top pt-4 mb-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="d-flex align-items-center justify-content-center link-icon-box"
                         style="width:28px;height:28px;border-radius:6px;">
                        <i class="feather-box fs-12"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-0 fs-14">
                        Returned Items &amp; Stock Deduction Details
                    </h6>
                </div>
                <span class="badge bg-soft-info text-info fs-11 px-2">
                    {{ $return->items->count() }} {{ Str::plural('Item', $return->items->count()) }}
                </span>
            </div>

            <table class="alloc-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 38%;">Product / Item Description</th>
                        <th style="width: 22%;">Return Warehouse</th>
                        <th class="text-end" style="width: 15%;">Quantity</th>
                        <th class="text-end" style="width: 20%;">Refund Amount</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($return->items as $idx => $item)
                    @php
                        $lineTotal = (float)$item->quantity * (float)$item->unit_price;
                    @endphp
                    <tr>
                        <td class="text-muted fs-11">{{ $idx + 1 }}</td>
                        <td>
                            <strong class="text-dark d-block fs-13">{{ $item->product?->name ?: 'Item' }}</strong>
                            @if($item->product?->sku)
                                <small class="text-muted fs-11 font-monospace">SKU: {{ $item->product->sku }}</small>
                            @endif
                            @if(!empty($item->serial_numbers))
                                <div class="mt-1">
                                    <span class="badge bg-soft-secondary text-secondary font-monospace fs-10">
                                        S/N: {{ $item->serial_numbers }}
                                    </span>
                                </div>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex align-items-center text-dark">
                                <i class="feather-map-pin me-1 text-muted fs-11"></i>
                                <span>{{ $item->warehouse?->name ?: 'Main Warehouse' }}</span>
                            </div>
                        </td>
                        <td class="text-end font-monospace fw-bold text-dark">
                            {{ (float) $item->quantity }}
                        </td>
                        <td class="text-end font-monospace fw-bold text-danger">
                            {{ format_currency($lineTotal) }}
                        </td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-end text-muted fw-bold">Total Refund Value:</td>
                        <td class="text-end font-monospace text-dark fw-bold">
                            {{ (float)$return->items->sum('quantity') }}
                        </td>
                        <td class="text-end font-monospace text-danger fw-bold fs-14">
                            {{ format_currency($displayRefundTotal) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- ══════ 5. REASON & REMARKS ══════ --}}
        @if($return->reason)
            <div class="p-3 pay-notes-box rounded border mb-4 fs-12">
                <strong class="d-block mb-1 text-dark">
                    <i class="feather-alert-circle me-1 text-warning"></i>Reason for Return / Remarks:
                </strong>
                <span class="text-secondary leading-relaxed">{{ $return->reason }}</span>
            </div>
        @endif

        {{-- ══════ 6. FOOTER & AUDIT ══════ --}}
        <div class="d-flex justify-content-between align-items-center text-muted fs-11 pt-3 border-top flex-wrap gap-2">
            <div>
                <span>Return Number: <strong class="font-monospace text-dark">{{ $return->return_number }}</strong></span>
                <span class="mx-1">•</span>
                <span>Date: {{ $return->created_at ? $return->created_at->format('d-M-Y h:i A') : '—' }}</span>
            </div>
            <div class="d-flex align-items-center gap-1 font-monospace">
                <i class="feather-shield text-success fs-12"></i>
                <span>Automated Inventory Stock Adjustment System</span>
            </div>
        </div>

    </div>

@endsection
