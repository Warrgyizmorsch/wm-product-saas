@extends('layouts.duralux')

@section('title', __('purchase.purchase_orders') . " {$order->purchase_order_number} | SaaS ERP")
@section('page-title', __('purchase.purchase_order_details'))
@section('breadcrumb')
    <a href="{{ route('purchase.orders.index') }}">{{ __('purchase.purchase_orders') }}</a> &gt; {{ $order->purchase_order_number }}
@endsection

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <!-- Back Button -->
        <a href="{{ route('purchase.orders.index') }}" class="action-dropdown-btn" title="{{ __('purchase.back') }}" data-bs-toggle="tooltip">
            <i class="feather feather-arrow-left"></i>
        </a>

        <!-- Download PDF Icon Button -->
        <a href="{{ route('purchase.orders.download', $order->id) }}" class="action-dropdown-btn" title="{{ __('purchase.download_pdf') }}" data-bs-toggle="tooltip">
            <i class="feather feather-download"></i>
        </a>

        @if($order->status === 'Draft')
            <!-- Action Dropdown -->
            <x-ui.action-dropdown id="poDetailsActions-{{ $order->id }}">
                <li>
                    <a class="dropdown-item py-2" href="{{ route('purchase.orders.edit', $order->id) }}">
                        <i class="feather-edit me-1.5 text-muted"></i> {{ __('purchase.edit') }}
                    </a>
                </li>
                <li>
                    <form action="{{ route('purchase.orders.destroy', $order->id) }}" method="POST" class="d-inline" id="deletePoShowForm">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="dropdown-item py-2 text-danger" onclick="confirmAction({ title: 'Delete Purchase Order', message: '{{ __('purchase.confirm_delete_po') }}', variant: 'danger', confirmText: 'Delete' }, function() { document.getElementById('deletePoShowForm').submit(); })">
                            <i class="feather-trash-2 me-1.5"></i> {{ __('purchase.delete') }}
                        </button>
                    </form>
                </li>
            </x-ui.action-dropdown>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        .po-lines-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .po-lines-table th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 14px;
            border-top: 1px solid #e2e8f0;
            border-bottom: 2px solid #cbd5e1;
        }
        .po-lines-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            font-size: 13px;
        }
        .po-lines-table tr:hover td {
            background-color: #f8fafc;
        }
        .summary-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 16px 20px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            font-size: 13px;
        }
        @media print {
            body * { visibility: hidden !important; }
            #printablePoContent, #printablePoContent * { visibility: visible !important; }
            #printablePoContent {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                padding: 0 !important;
            }
            .d-print-none { display: none !important; }
        }
    </style>
@endpush

@section('content')
    @php
        $currency = tenant()?->settings['currency'] ?? 'INR';
        $currencySymbol = ($currency === 'INR') ? '₹' : $currency . ' ';
    @endphp

    <div class="erp-single-panel text-dark">
        <!-- Toast Notifications -->

        <x-ui.odoo-form-ui type="sheet" class="p-0" id="printablePoContent">
            <!-- Header bar -->
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 px-4 pt-4 pb-3 border-bottom bg-white">
                <div>
                    <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1 letter-spacing-1">
                        <i class="feather-shopping-bag text-primary me-1"></i>Purchase Order
                    </span>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h3 class="fw-bold text-dark mb-0 font-monospace">{{ $order->purchase_order_number }}</h3>
                        @php
                            $statusVariant = 'warning';
                            if ($order->status === 'Approved') $statusVariant = 'success';
                            elseif ($order->status === 'Cancelled') $statusVariant = 'danger';
                        @endphp
                        <x-ui.badge :soft="true" :variant="$statusVariant" class="px-2.5 py-1 fs-11 fw-bold">
                            {{ $order->status }}
                        </x-ui.badge>
                    </div>
                    <span class="fs-13 text-muted">
                        Supplier:&nbsp;<strong class="text-dark">{{ $order->vendor->name ?? '—' }}</strong>
                        &nbsp;·&nbsp;Order Date:&nbsp;<strong class="text-dark">{{ $order->date ? $order->date->format('d-m-Y') : '—' }}</strong>
                        @if($order->delivery_date)
                            &nbsp;·&nbsp;Delivery Date:&nbsp;<strong class="text-danger">{{ $order->delivery_date->format('d-m-Y') }}</strong>
                        @endif
                    </span>
                </div>

                <!-- Grand Total Banner -->
                <div class="text-end bg-light p-3 rounded-3 border min-w-180">
                    <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1 letter-spacing-1">Grand Total</span>
                    <h3 class="fw-bold text-primary mb-0 font-monospace">{{ $currencySymbol }}{{ number_format($order->grand_total, 2) }}</h3>
                </div>
            </div>

            @if (in_array($order->status, ['Cancelled', 'Rejected']) && !empty($order->rejection_reason))
                <div class="alert alert-danger border-0 border-start border-4 border-danger m-4 mb-0 rounded-3 shadow-sm bg-soft-danger">
                    <div class="d-flex align-items-top">
                        <i class="feather-x-circle fs-18 text-danger me-3 mt-0.5"></i>
                        <div>
                            <h6 class="fw-bold text-danger mb-1">Purchase Order Cancelled / Rejected</h6>
                            <p class="fs-13 text-dark mb-0"><strong>Rejection Reason:</strong> {{ $order->rejection_reason }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- 3-Column Info Cards Section -->
            <div class="px-4 py-4 border-bottom bg-light-50">
                <div class="row g-4 fs-13 text-dark">
                    <!-- Column 1: Vendor Details -->
                    <div class="col-md-4 border-end-md">
                        <h6 class="fw-bold text-dark text-uppercase fs-11 letter-spacing-1 mb-3">
                            <i class="feather-truck text-primary me-1.5"></i>Supplier Information
                        </h6>
                        <table class="table table-borderless table-sm mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-muted ps-0" style="width: 110px;">Vendor:</td>
                                    <td class="fw-bold text-dark fs-14">{{ $order->vendor->name ?? '—' }}</td>
                                </tr>
                                @if($order->vendor?->code)
                                    <tr>
                                        <td class="text-muted ps-0">Vendor Code:</td>
                                        <td class="fw-semibold text-secondary font-monospace">{{ $order->vendor->code }}</td>
                                    </tr>
                                @endif
                                @if($order->supplier_quotation_number)
                                    <tr>
                                        <td class="text-muted ps-0">Quote Ref:</td>
                                        <td class="fw-bold text-primary font-monospace">{{ $order->supplier_quotation_number }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td class="text-muted ps-0">Address:</td>
                                    <td class="text-dark" style="line-height: 1.4;">{{ $order->vendor->address ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Column 2: Dates & Warehouse -->
                    <div class="col-md-4 border-end-md">
                        <h6 class="fw-bold text-dark text-uppercase fs-11 letter-spacing-1 mb-3">
                            <i class="feather-calendar text-primary me-1.5"></i>Dates & Calculation Options
                        </h6>
                        <table class="table table-borderless table-sm mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-muted ps-0" style="width: 120px;">Order Date:</td>
                                    <td class="fw-semibold text-dark">{{ $order->date ? $order->date->format('d-m-Y') : '—' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Delivery Date:</td>
                                    <td class="fw-semibold text-danger">{{ $order->delivery_date ? $order->delivery_date->format('d-m-Y') : '—' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Warehouse:</td>
                                    <td class="fw-semibold text-dark">{{ $order->location ?: 'Main Warehouse' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Discount Option:</td>
                                    <td class="fw-semibold text-dark text-capitalize">{{ str_replace('_', ' ', $order->discount_type) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Tax Option:</td>
                                    <td class="fw-semibold text-dark text-capitalize">{{ str_replace('_', ' ', $order->tax_type) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Column 3: Traceability -->
                    <div class="col-md-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="fw-bold text-dark text-uppercase fs-11 letter-spacing-1 mb-0">
                                <i class="feather-link text-primary me-1.5"></i>Traceability & Audit
                            </h6>
                        </div>
                        <table class="table table-borderless table-sm mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-muted ps-0" style="width: 130px;">Source Requisition:</td>
                                    <td class="fw-bold">
                                        @if($order->requisition)
                                            <a href="{{ route('purchase.requisitions.show', $order->purchase_requisition_id) }}" class="text-primary hover-underline font-monospace">
                                                <i class="feather-file-text me-1"></i>{{ $order->requisition->requisition_number }}
                                            </a>
                                        @else
                                            <span class="text-muted">Direct Creation</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Reference / Note:</td>
                                    <td class="fw-semibold text-dark">{{ $order->reference ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Created By:</td>
                                    <td class="fw-semibold text-dark">{{ $order->creator->name ?? 'System Admin' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">GST Treatment:</td>
                                    <td class="fw-semibold text-dark text-uppercase">{{ $order->gst_type === 'igst' ? 'IGST (Inter-State)' : 'CGST + SGST (Intra-State)' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Line Items Table Section -->
            <div class="px-4 py-4 border-bottom">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="feather-list text-primary me-2"></i>Purchase Order Line Items
                    </h5>
                    <span class="badge bg-light text-dark border px-2.5 py-1 font-monospace fs-12">
                        {{ $order->items->count() }} Line(s)
                    </span>
                </div>

                <div class="table-responsive rounded-3 border">
                    <table class="po-lines-table">
                        <thead>
                            <tr>
                                <th style="width: 4%;">#</th>
                                <th style="width: 32%;">Product & SKU</th>
                                <th class="text-end" style="width: 10%;">Qty</th>
                                <th class="text-end" style="width: 14%;">Rate</th>
                                <th class="text-end" style="width: 14%;">Amount</th>

                                @if($order->discount_type === 'item_wise')
                                    <th class="text-end text-danger" style="width: 12%;">Disc. Amt</th>
                                @endif

                                @if($order->tax_type === 'item_wise_tax')
                                    <th class="text-end text-muted" style="width: 12%;">Tax Amt</th>
                                @endif

                                <th class="text-end" style="width: 14%;">Total Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $groupedItems = $order->items->groupBy('product_id')->map(function($items) {
                                    $first = $items->first();
                                    return (object) [
                                        'id' => $first->id,
                                        'product' => $first->product,
                                        'product_id' => $first->product_id,
                                        'quantity' => $items->sum('quantity'),
                                        'rate' => $first->rate,
                                        'amount' => $items->sum('amount'),
                                        'discount_percent' => $first->discount_percent,
                                        'discount_amount' => $items->sum('discount_amount'),
                                        'tax_percent' => $first->tax_percent,
                                        'cgst_percent' => $first->cgst_percent,
                                        'sgst_percent' => $first->sgst_percent,
                                        'igst_percent' => $first->igst_percent,
                                        'tax_amount' => $items->sum('tax_amount'),
                                        'total_amount' => $items->sum('total_amount'),
                                    ];
                                })->values();
                            @endphp

                            @foreach($groupedItems as $index => $item)
                                <tr>
                                    <td class="text-muted fs-12 font-monospace">{{ $index + 1 }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-sm bg-soft-primary text-primary rounded-2 d-flex align-items-center justify-content-center fw-bold fs-13" style="width:30px; height:30px; flex-shrink:0;">
                                                <i class="feather-box"></i>
                                            </div>
                                            <div>
                                                <a href="{{ route('inventory.products.show', $item->product_id) }}" class="fw-bold text-dark text-decoration-none hover-underline">
                                                    {{ $item->product->name ?? '—' }}
                                                </a>
                                                <div class="text-muted fs-11 font-monospace">SKU: {{ $item->product->sku ?: '—' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end fw-bold text-dark font-monospace fs-14">
                                        {{ (float)$item->quantity }}
                                    </td>
                                    <td class="text-end font-monospace text-muted">
                                        {{ $currencySymbol }}{{ number_format($item->rate, 2) }}
                                    </td>
                                    <td class="text-end font-monospace fw-semibold text-dark">
                                        {{ $currencySymbol }}{{ number_format($item->amount, 2) }}
                                    </td>

                                    @if($order->discount_type === 'item_wise')
                                        <td class="text-end font-monospace text-danger">
                                            -{{ $currencySymbol }}{{ number_format($item->discount_amount, 2) }}
                                        </td>
                                    @endif

                                    @if($order->tax_type === 'item_wise_tax')
                                        <td class="text-end font-monospace text-muted">
                                            +{{ $currencySymbol }}{{ number_format($item->tax_amount, 2) }}
                                        </td>
                                    @endif

                                    <td class="text-end font-monospace fw-bold text-dark fs-14">
                                        {{ $currencySymbol }}{{ number_format($item->total_amount, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Financial Summary & Terms Section -->
            <div class="px-4 py-4 border-bottom bg-white">
                <div class="row g-4 text-dark fs-13">
                    <!-- Terms & Notes -->
                    <div class="col-md-7">
                        <h6 class="fw-bold text-dark text-uppercase fs-11 letter-spacing-1 mb-3">
                            <i class="feather-file-text me-1.5 text-primary"></i>Terms & Notes
                        </h6>
                        <div class="bg-light p-3 border rounded-3 text-muted" style="min-height: 90px; white-space: pre-line; line-height: 1.5;">
                            {!! $order->notes ? e($order->notes) : '<span class="italic"><i class="feather-info me-1"></i>No additional terms or notes specified for this purchase order.</span>' !!}
                        </div>
                    </div>

                    <!-- Financial Calculations Box -->
                    <div class="col-md-5">
                        <div class="summary-box">
                            <h6 class="fw-bold text-dark text-uppercase fs-11 letter-spacing-1 mb-3 border-bottom pb-2">
                                Financial Summary
                            </h6>

                            <!-- Subtotal -->
                            <div class="summary-row">
                                <span class="text-muted fw-semibold">{{ __('purchase.subtotal') }}:</span>
                                <span class="fw-bold text-dark font-monospace">{{ $currencySymbol }}{{ number_format($order->subtotal, 2) }}</span>
                            </div>

                            <!-- Discount Row (ONLY if discount > 0) -->
                            @if(($order->discount_type !== 'without_discount' || $order->discount_amount > 0) && $order->discount_amount > 0)
                                <div class="summary-row text-danger">
                                    <span class="fw-semibold">Discount ({{ str_replace('_', ' ', $order->discount_type) }}):</span>
                                    <span class="fw-bold font-monospace">-{{ $currencySymbol }}{{ number_format($order->discount_amount, 2) }}</span>
                                </div>

                                <div class="summary-row">
                                    <span class="text-muted fw-semibold">{{ __('purchase.gross_total_before_tax') }}:</span>
                                    <span class="fw-bold text-dark font-monospace">{{ $currencySymbol }}{{ number_format(max(0, $order->subtotal - $order->discount_amount), 2) }}</span>
                                </div>
                            @endif

                            <!-- Tax Breakdown (ONLY if tax > 0) -->
                            @if(($order->tax_type !== 'without_tax' || $order->tax_amount > 0) && $order->tax_amount > 0)
                                @php
                                    $grossTotal = max(0, $order->subtotal - $order->discount_amount);
                                    $effectiveTaxRate = $grossTotal > 0 ? ($order->tax_amount / $grossTotal) * 100 : 0;
                                @endphp
                                @if($order->gst_type === 'cgst_sgst' || ($order->cgst_amount > 0 || $order->sgst_amount > 0))
                                    @php
                                        $cgst = $order->cgst_amount > 0 ? $order->cgst_amount : round($order->tax_amount / 2, 2);
                                        $sgst = $order->sgst_amount > 0 ? $order->sgst_amount : round($order->tax_amount - $cgst, 2);
                                        $halfRate = round($effectiveTaxRate / 2, 2);
                                    @endphp
                                    <div class="summary-row">
                                        <span class="text-muted fw-semibold">CGST ({{ $halfRate }}%):</span>
                                        <span class="fw-semibold text-dark font-monospace">+{{ $currencySymbol }}{{ number_format($cgst, 2) }}</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="text-muted fw-semibold">SGST ({{ $halfRate }}%):</span>
                                        <span class="fw-semibold text-dark font-monospace">+{{ $currencySymbol }}{{ number_format($sgst, 2) }}</span>
                                    </div>
                                @elseif($order->gst_type === 'igst' || $order->igst_amount > 0)
                                    <div class="summary-row">
                                        <span class="text-muted fw-semibold">IGST ({{ round($effectiveTaxRate, 2) }}%):</span>
                                        <span class="fw-semibold text-dark font-monospace">+{{ $currencySymbol }}{{ number_format($order->tax_amount, 2) }}</span>
                                    </div>
                                @else
                                    <div class="summary-row">
                                        <span class="text-muted fw-semibold">Taxes ({{ round($effectiveTaxRate, 2) }}%):</span>
                                        <span class="fw-semibold text-dark font-monospace">+{{ $currencySymbol }}{{ number_format($order->tax_amount, 2) }}</span>
                                    </div>
                                @endif
                            @endif

                            <hr class="my-2">
                            <div class="d-flex justify-content-between align-items-center pt-1">
                                <span class="fs-14 fw-bold text-dark">Grand Total:</span>
                                <span class="fs-16 fw-bold text-primary font-monospace">{{ $currencySymbol }}{{ number_format($order->grand_total, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Signature Footer -->
                <div class="row mt-4 pt-3 text-dark">
                    <div class="col-6 text-start">
                        <p class="fs-11 text-muted mb-0">
                            For queries regarding fulfillment, please refer to the purchase department.
                        </p>
                    </div>
                    <div class="col-6 text-end">
                        <div class="d-inline-block text-center" style="width: 180px;">
                            <hr class="mb-1 mt-2">
                            <span class="fs-11 text-muted text-uppercase fw-bold letter-spacing-1">Authorized Signature</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advance Payments & Accounting Section (if Approved) -->
            @if($order->status === 'Approved')
                <div class="px-4 py-4 bg-light-50 d-print-none">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold text-dark mb-0">
                                <i class="feather-dollar-sign text-success me-1.5"></i>Vendor Advance Payments & Accounting
                            </h6>
                            <small class="text-muted fs-12">Record advance payments to vendor before bill receiving.</small>
                        </div>
                        @if($order->balance_due > 0)
                            <button type="button" class="btn btn-sm btn-success fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#advancePaymentModal">
                                <i class="feather-plus-circle me-1.5"></i>Register Advance Payment
                            </button>
                        @endif
                    </div>

                    <div class="row g-3 mb-3 text-dark">
                        <div class="col-md-4">
                            <div class="bg-white p-3 border rounded-3">
                                <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">Total PO Amount</span>
                                <h4 class="fw-bold text-dark mb-0 font-monospace">{{ $currencySymbol }}{{ number_format($order->grand_total, 2) }}</h4>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-white p-3 border rounded-3">
                                <span class="fs-11 text-uppercase text-success fw-bold d-block mb-1">Advance Paid & Posted</span>
                                <h4 class="fw-bold text-success mb-0 font-monospace">{{ $currencySymbol }}{{ number_format($order->total_advance_paid, 2) }}</h4>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-white p-3 border rounded-3">
                                <span class="fs-11 text-uppercase text-primary fw-bold d-block mb-1">Balance Due</span>
                                <h4 class="fw-bold text-primary mb-0 font-monospace">{{ $currencySymbol }}{{ number_format($order->balance_due, 2) }}</h4>
                            </div>
                        </div>
                    </div>

                    @if($order->advancePayments->count() > 0)
                        <div class="table-responsive rounded-3 border bg-white">
                            <table class="table table-sm align-middle fs-13 text-dark mb-0">
                                <thead class="table-light fs-11 text-uppercase text-muted fw-semibold">
                                    <tr>
                                        <th class="ps-3">Payment No</th>
                                        <th>Date</th>
                                        <th>Method</th>
                                        <th>Reference No</th>
                                        <th class="text-end pe-3">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($order->advancePayments as $adv)
                                        <tr>
                                            <td class="ps-3 fw-bold text-primary font-monospace">{{ $adv->payment_number }}</td>
                                            <td>{{ $adv->payment_date ? $adv->payment_date->format('d-M-Y') : '—' }}</td>
                                            <td><span class="badge bg-soft-info text-info fs-11 fw-semibold">{{ $adv->payment_method }}</span></td>
                                            <td class="font-monospace">{{ $adv->reference_number ?: 'N/A' }}</td>
                                            <td class="text-end pe-3 font-monospace fw-bold text-success">{{ $currencySymbol }}{{ number_format($adv->amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-3 text-muted fs-12 bg-white border rounded-3">
                            <i class="feather-info me-1"></i>No advance payments registered yet.
                        </div>
                    @endif
                </div>
            @endif
        </x-ui.odoo-form-ui>
    </div>

    <!-- Reject PO Modal -->
    @if($order->status === 'Draft')
        <div class="modal fade" id="rejectPoModal" tabindex="-1" aria-labelledby="rejectPoModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <form action="{{ route('purchase.orders.reject', $order->id) }}" method="POST">
                        @csrf
                        <div class="modal-header border-bottom bg-light">
                            <h5 class="modal-title fw-bold text-danger" id="rejectPoModalLabel">
                                <i class="feather-x-circle me-1.5"></i>Reject Purchase Order
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            <p class="fs-13 text-muted mb-3">
                                Please specify the reason for rejecting Purchase Order <strong class="text-dark">{{ $order->purchase_order_number }}</strong>.
                            </p>
                            <div class="mb-3">
                                <label for="rejection_reason" class="form-label fs-12 fw-bold text-dark">Rejection Reason <span class="text-danger">*</span></label>
                                <textarea name="rejection_reason" id="rejection_reason" rows="3" class="form-control fs-13" placeholder="Enter reason for rejection..." required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer border-top bg-light">
                            <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-danger fw-bold px-3">
                                <i class="feather-x-circle me-1"></i>Confirm Rejection
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Register Advance Payment Modal -->
    @if($order->status === 'Approved')
        <x-ui.modal id="advancePaymentModal" title="Register Vendor Advance Payment" size="lg">
            <form action="{{ route('purchase.orders.advance-payments.store') }}" method="POST" class="odoo-sheet">
                @csrf
                <input type="hidden" name="purchase_order_id" value="{{ $order->id }}">
                <input type="hidden" name="vendor_id" value="{{ $order->vendor_id }}">

                <div class="p-3">
                    <div class="alert alert-info py-2 px-3 fs-12 mb-3">
                        <i class="feather-info me-1"></i>
                        Register advance payment for this Purchase Order. An accounting journal entry will be automatically posted to Vendor Advance account.
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="Vendor" name="vendor_display" value="{{ $order->vendor?->name }}" readonly="true" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="PO Number" name="po_display" value="{{ $order->purchase_order_number }}" readonly="true" />
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" inputType="number" label="Advance Amount" name="amount" id="advance_amount" value="{{ min($order->balance_due, $order->grand_total) }}" step="0.01" min="0.01" max="{{ $order->balance_due }}" required="true" placeholder="Enter amount..." />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="Payment Method" name="payment_method" id="payment_method" required="true">
                                <option value="Bank Transfer" selected>Bank Transfer</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Cash">Cash</option>
                                <option value="UPI">UPI</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" inputType="date" label="Payment Date" name="payment_date" id="payment_date" value="{{ date('Y-m-d') }}" required="true" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="Transaction / UTR Ref" name="reference_number" id="reference_number" placeholder="e.g. UTR123456789" />
                        </div>
                    </div>

                    <x-ui.odoo-form-ui type="textarea" label="Notes / Remarks" name="notes" placeholder="Payment notes..." rows="2" />
                </div>

                <div class="modal-footer border-top px-3 py-2 bg-light d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-semibold">
                        <i class="feather-check me-1"></i>Post Advance Payment
                    </button>
                </div>
            </form>
        </x-ui.modal>
    @endif
@endsection
