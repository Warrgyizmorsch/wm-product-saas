@extends('layouts.duralux')

@section('title', "PO {$order->purchase_order_number} | SaaS ERP")
@section('page-title', "Purchase Order Details")
@section('breadcrumb')
    <a href="{{ route('purchase.orders.index') }}">Purchase Orders</a> &gt; {{ $order->purchase_order_number }}
@endsection

@section('page-actions')
    <div class="d-flex gap-2 flex-wrap text-dark">
        <a href="{{ route('purchase.orders.index') }}" class="btn btn-light border fs-12">
            <i class="feather-arrow-left me-2"></i>Back to Orders
        </a>
        <a href="{{ route('purchase.orders.download', $order->id) }}" class="btn btn-light border fs-12">
            <i class="feather-printer me-2"></i>Download PDF
        </a>

        @if($order->status === 'Draft')
            <a href="{{ route('purchase.orders.edit', $order->id) }}" class="btn btn-warning fs-12">
                <i class="feather-edit me-2"></i>Edit Draft
            </a>
            <form action="{{ route('purchase.orders.approve', $order->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to approve this purchase order?')">
                @csrf
                <button type="submit" class="btn btn-success text-white fs-12">
                    <i class="feather-check-circle me-2"></i>Approve Order
                </button>
            </form>
        @endif
    </div>
@endsection

@once
    @push('styles')
        <style>
            .so-status-pipeline {
                display: inline-flex;
                align-items: center;
                border-radius: 4px;
                overflow: hidden;
                border: 1px solid #cbd5e1;
                background-color: #f1f5f9;
            }
            .so-status-pipeline .pipeline-step {
                position: relative;
                padding: 6px 14px 6px 24px;
                background-color: #f1f5f9;
                color: #64748b;
                font-size: 10px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                border: none;
                outline: none;
                transition: all 0.2s ease;
                display: inline-flex;
                align-items: center;
            }
            .so-status-pipeline .pipeline-step:first-child {
                padding-left: 14px;
                border-top-left-radius: 3px;
                border-bottom-left-radius: 3px;
            }
            .so-status-pipeline .pipeline-step:last-child {
                padding-right: 14px;
                border-top-right-radius: 3px;
                border-bottom-right-radius: 3px;
            }
            /* Right pointing arrowhead */
            .so-status-pipeline .pipeline-step::after {
                content: "";
                position: absolute;
                top: 0;
                right: -10px;
                width: 0;
                height: 0;
                border-top: 14px solid transparent;
                border-bottom: 14px solid transparent;
                border-left: 10px solid #f1f5f9;
                z-index: 10;
                transition: all 0.2s ease;
            }
            /* Left cutout */
            .so-status-pipeline .pipeline-step::before {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                width: 0;
                height: 0;
                border-top: 14px solid transparent;
                border-bottom: 14px solid transparent;
                border-left: 10px solid #ffffff;
                z-index: 5;
            }
            .so-status-pipeline .pipeline-step:first-child::before {
                display: none;
            }
            /* Active stage */
            .so-status-pipeline .pipeline-step.active {
                background-color: #3454d1;
                color: #ffffff;
            }
            .so-status-pipeline .pipeline-step.active::after {
                border-left-color: #3454d1;
            }
            /* Completed/previous stage */
            .so-status-pipeline .pipeline-step.completed {
                background-color: #cbd5e1;
                color: #475569;
            }
            .so-status-pipeline .pipeline-step.completed::after {
                border-left-color: #cbd5e1;
            }

            .terms-conditions-content p {
                margin-bottom: 4px !important;
                line-height: 1.4 !important;
            }
            .terms-conditions-content p:last-child {
                margin-bottom: 0 !important;
            }
        </style>
    @endpush
@endonce

@section('content')
    @php
        $currency = tenant()?->settings['currency'] ?? 'INR';
        $currencySymbol = ($currency === 'INR') ? '₹' : $currency . ' ';
    @endphp
    
    <div class="row text-dark">
        <div class="col-12">
            <!-- Toast Notifications -->
            @if (session('success'))
                <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
            @endif
            @if (session('error'))
                <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
            @endif

            <!-- Main PO Card (Single Page Wrapper) -->
            <div class="card border-0 shadow-sm bg-white mb-4 print-area">
                <div class="card-header bg-white border-bottom py-0 px-4 d-print-none d-flex justify-content-between align-items-center flex-wrap gap-2" style="min-height: 48px;">
                    <h5 class="fw-bold text-dark mb-0 py-3 fs-14">Purchase Order Details</h5>
                    
                    <!-- Custom Chevron Status Pipeline -->
                    <div class="so-status-pipeline my-2 d-print-none">
                        @php
                            $statuses = ['Draft', 'Approved'];
                            if ($order->status === 'Cancelled') {
                                $statuses[] = 'Cancelled';
                            }
                            $currentIndex = array_search($order->status, $statuses);
                        @endphp
                        @foreach($statuses as $index => $state)
                            @php
                                $stepClass = '';
                                if ($order->status === $state) {
                                    $stepClass = 'active';
                                } elseif ($currentIndex !== false && $index < $currentIndex) {
                                    $stepClass = 'completed';
                                }
                            @endphp
                            <span class="pipeline-step {{ $stepClass }}">
                                {{ $state }}
                            </span>
                        @endforeach
                    </div>
                </div>

                <div class="card-body p-4 p-md-5">
                    <!-- Header Section -->
                    <div class="row align-items-center mb-4">
                        <div class="col-sm-6 text-start">
                            <div class="d-flex align-items-center">
                                <div class="avatar-text bg-primary text-white fs-2 fw-bold me-3 shadow d-flex align-items-center justify-content-center" style="border-radius: 6px; width: 55px; height: 55px; flex-shrink: 0;">
                                    {{ strtoupper(substr(tenant() ? tenant()->name : 'ERP', 0, 1)) }}
                                </div>
                                <div>
                                    <h4 class="fw-bold text-dark mb-0 fs-15">{{ tenant() ? tenant()->name : 'SaaS ERP Workspace' }}</h4>
                                    <div class="text-muted fs-11 mt-1">
                                        <div>H-1, Industrial Area, Sukher, Udaipur 313001, Rajasthan, India</div>
                                        <div>Tel: +91 294 2440230 | GSTIN: 08AAFCS1234E1Z0</div>
                                        <div>Email: {{ tenant() ? tenant()->billing_email : 'info@sasserp.com' }} | Web: www.sasserp.com</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 text-sm-end mt-3 mt-sm-0 text-start text-sm-end">
                            <h5 class="fw-bold text-primary mb-1" style="letter-spacing: 0.5px; font-size: 14px;">PURCHASE ORDER</h5>
                            <span class="fs-13 fw-bold text-dark d-block">No: {{ $order->purchase_order_number }}</span>
                            @php
                                $badgeClass = match($order->status) {
                                    'Draft' => 'bg-soft-secondary text-secondary',
                                    'Approved' => 'bg-soft-success text-success',
                                    'Cancelled' => 'bg-soft-danger text-danger',
                                    default => 'bg-soft-dark text-dark',
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }} px-2 py-0.5 fw-bold fs-10 mt-1">{{ $order->status }}</span>
                        </div>
                    </div>

                    <!-- Metadata Grid -->
                    <div class="row g-4 fs-12 pb-3 mb-4 text-dark">
                        <!-- Column 1: Supplier Info -->
                        <div class="col-md-4">
                            <div class="p-3 border rounded h-100 bg-light bg-opacity-10">
                                <h6 class="fw-bold text-primary fs-10 text-uppercase border-bottom pb-1.5 mb-2.5" style="letter-spacing: 0.5px;">Supplier Info</h6>
                                <div class="mb-2 d-flex align-items-baseline">
                                    <span class="text-muted fw-semibold" style="width: 100px; flex-shrink: 0;">Supplier:</span>
                                    <span class="fw-bold text-dark fs-13">{{ $order->vendor->name ?? '—' }}</span>
                                </div>
                                @if($order->vendor?->code)
                                    <div class="mb-2 d-flex align-items-baseline">
                                        <span class="text-muted fw-semibold" style="width: 100px; flex-shrink: 0;">Supplier Code:</span>
                                        <span class="fw-semibold text-secondary">{{ $order->vendor->code }}</span>
                                    </div>
                                @endif
                                @if($order->supplier_quotation_number)
                                    <div class="mb-2 d-flex align-items-baseline">
                                        <span class="text-muted fw-semibold" style="width: 100px; flex-shrink: 0;">Quotation No:</span>
                                        <span class="fw-bold text-primary">{{ $order->supplier_quotation_number }}</span>
                                    </div>
                                @endif
                                <div class="mb-2 d-flex align-items-baseline">
                                    <span class="text-muted fw-semibold" style="width: 100px; flex-shrink: 0;">Supplier Address:</span>
                                    <span class="text-dark" style="line-height: 1.4; white-space: pre-line;">{{ $order->vendor->address ?? '—' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Column 2: Dates & Calculations Options -->
                        <div class="col-md-4">
                            <div class="p-3 border rounded h-100 bg-light bg-opacity-10">
                                <h6 class="fw-bold text-primary fs-10 text-uppercase border-bottom pb-1.5 mb-2.5" style="letter-spacing: 0.5px;">Dates & Options</h6>
                                <div class="mb-2 d-flex align-items-baseline">
                                    <span class="text-muted fw-semibold" style="width: 110px; flex-shrink: 0;">Order Date:</span>
                                    <span class="fw-semibold text-dark">{{ $order->date ? $order->date->format('d M Y') : '—' }}</span>
                                </div>
                                <div class="mb-2 d-flex align-items-baseline">
                                    <span class="text-muted fw-semibold" style="width: 110px; flex-shrink: 0;">Delivery Date:</span>
                                    <span class="fw-semibold text-danger">{{ $order->delivery_date ? $order->delivery_date->format('d M Y') : '—' }}</span>
                                </div>
                                <div class="mb-2 d-flex align-items-baseline">
                                    <span class="text-muted fw-semibold" style="width: 110px; flex-shrink: 0;">Location:</span>
                                    <div class="fw-semibold text-dark">
                                        {{ $order->location ?: '—' }}
                                        @if($order->warehouse?->address)
                                            <div class="text-muted fs-11 fw-normal mt-1" style="line-height: 1.3;">{{ $order->warehouse->address }}</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="mb-2 d-flex align-items-baseline">
                                    <span class="text-muted fw-semibold" style="width: 110px; flex-shrink: 0;">Discount Option:</span>
                                    <span class="fw-semibold text-dark">{{ ucwords(str_replace('_', ' ', $order->discount_type)) }}</span>
                                </div>
                                <div class="mb-2 d-flex align-items-baseline">
                                    <span class="text-muted fw-semibold" style="width: 110px; flex-shrink: 0;">Tax Option:</span>
                                    <span class="fw-semibold text-dark">{{ ucwords(str_replace('_', ' ', $order->tax_type)) }}</span>
                                </div>
                                @if($order->tax_type !== 'without_tax')
                                    <div class="mb-2 d-flex align-items-baseline">
                                        <span class="text-muted fw-semibold" style="width: 110px; flex-shrink: 0;">GST Type:</span>
                                        <span class="fw-semibold text-dark">{{ $order->gst_type === 'igst' ? 'IGST (Inter-State)' : 'CGST + SGST (Intra-State)' }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Column 3: Traceability & Audit -->
                        <div class="col-md-4">
                            <div class="p-3 border rounded h-100 bg-light bg-opacity-10">
                                <h6 class="fw-bold text-primary fs-10 text-uppercase border-bottom pb-1.5 mb-2.5" style="letter-spacing: 0.5px;">Traceability & Audit</h6>
                                <div class="mb-2 d-flex align-items-baseline">
                                    <span class="text-muted fw-semibold" style="width: 115px; flex-shrink: 0;">Reference:</span>
                                    <span class="fw-semibold text-dark">{{ $order->reference ?: '—' }}</span>
                                </div>
                                <div class="mb-2 d-flex align-items-baseline">
                                    <span class="text-muted fw-semibold" style="width: 115px; flex-shrink: 0;">Source Request:</span>
                                    <span class="fw-semibold text-dark">
                                        @if($order->requisition)
                                            <span class="text-primary fw-bold">{{ $order->requisition->requisition_number }}</span>
                                        @else
                                            <span class="text-muted">Direct Purchase</span>
                                        @endif
                                    </span>
                                </div>
                                <div class="mb-2 d-flex align-items-baseline">
                                    <span class="text-muted fw-semibold" style="width: 115px; flex-shrink: 0;">Created By:</span>
                                    <span class="fw-semibold text-dark">{{ $order->creator->name ?? 'System' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Items Details Table (Quotation style table) -->
                    <div class="mt-4">
                        <h5 class="fw-bold text-dark mb-3 fs-14">Order Lines</h5>
                        <div class="table-responsive rounded border">
                            <table class="table table-bordered table-sm align-middle fs-13 text-dark mb-0" style="width: 100%; min-width: 900px;">
                                <thead class="table-light fs-11 text-uppercase text-muted fw-semibold">
                                    <tr>
                                        <th class="ps-3" style="width: 5%;" class="text-center">S.No.</th>
                                        <th style="width: 35%;">Product Description</th>
                                        <th class="text-center" style="width: 10%;">Qty</th>
                                        <th class="text-end" style="width: 12%;">Rate</th>
                                        <th class="text-end" style="width: 12%;">Amount</th>
                                        
                                        @if($order->discount_type === 'item_wise')
                                            <th class="text-end text-danger" style="width: 8%;">Disc %</th>
                                            <th class="text-end text-danger" style="width: 10%;">Disc Amt</th>
                                        @endif

                                        @if($order->tax_type === 'item_wise_tax')
                                            <th class="text-end text-muted" style="width: 8%;">Tax %</th>
                                            <th class="text-end text-muted" style="width: 10%;">Tax Amt</th>
                                        @endif

                                        <th class="text-end pe-3" style="width: 15%;">Total Amt</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($order->items as $index => $item)
                                        <tr>
                                            <td class="text-center fw-semibold text-muted ps-3">{{ $index + 1 }}</td>
                                            <td>
                                                <strong class="text-dark">{{ $item->product->name ?? '—' }}</strong>
                                                @if($item->product?->sku)
                                                    <small class="text-muted d-block mt-0.5">SKU: {{ $item->product->sku }}</small>
                                                @endif
                                            </td>
                                            <td class="text-center font-monospace">{{ (float)$item->quantity }}</td>
                                            <td class="text-end font-monospace">{{ $currencySymbol }}{{ number_format($item->rate, 2) }}</td>
                                            <td class="text-end font-monospace">{{ $currencySymbol }}{{ number_format($item->amount, 2) }}</td>
                                            
                                            @if($order->discount_type === 'item_wise')
                                                <td class="text-end font-monospace text-danger">{{ (float)$item->discount_percent }}%</td>
                                                <td class="text-end font-monospace text-danger">-{{ $currencySymbol }}{{ number_format($item->discount_amount, 2) }}</td>
                                            @endif

                                            @if($order->tax_type === 'item_wise_tax')
                                                <td class="text-end font-monospace text-muted">
                                                    {{ (float)($item->tax_percent > 0 ? $item->tax_percent : ($item->cgst_percent + $item->sgst_percent + $item->igst_percent)) }}%
                                                </td>
                                                <td class="text-end font-monospace text-muted">+{{ $currencySymbol }}{{ number_format($item->tax_amount, 2) }}</td>
                                            @endif

                                            <td class="text-end pe-3 font-monospace fw-bold text-success">{{ $currencySymbol }}{{ number_format($item->total_amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Bottom Details & Totals Summary (Sales Order Style) -->
                    <div class="row mt-4 pt-3 border-top text-dark">
                        <!-- Left side: Terms & Notes -->
                        <div class="col-md-7 text-start">
                            <h6 class="fw-bold text-dark mb-1.5 fs-12 text-uppercase" style="letter-spacing: 0.5px;">Terms & Conditions</h6>
                            @if($order->notes)
                                <div class="text-muted fs-11 mb-0 terms-conditions-content" style="white-space: pre-line; line-height: 1.4;">{!! $order->notes !!}</div>
                            @else
                                <p class="text-muted fs-11 mb-0" style="font-style: italic;">No terms or notes specified for this purchase order.</p>
                            @endif
                        </div>
                        
                        <!-- Right side: Calculation Breakdown (Sales Order style box) -->
                        <div class="col-md-5">
                            <div class="border p-3 rounded bg-light">
                                <div class="d-flex justify-content-between mb-1.5 fs-12">
                                    <span class="text-muted">Subtotal:</span>
                                    <span class="fw-semibold text-dark">{{ $currencySymbol }}{{ number_format($order->subtotal, 2) }}</span>
                                </div>

                                @if($order->discount_type !== 'without_discount' && $order->discount_amount > 0)
                                    <div class="d-flex justify-content-between mb-1.5 fs-12 text-danger">
                                        <span>Discount:</span>
                                        <span>-{{ $currencySymbol }}{{ number_format($order->discount_amount, 2) }}</span>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mb-1.5 fs-12">
                                        <span class="text-muted">Gross Total (Before Tax):</span>
                                        <span class="fw-semibold text-dark">{{ $currencySymbol }}{{ number_format($order->subtotal - $order->discount_amount, 2) }}</span>
                                    </div>
                                @endif

                                @if($order->tax_type !== 'without_tax' && $order->tax_amount > 0)
                                    @php
                                        $grossTotal = $order->subtotal - $order->discount_amount;
                                        $taxPercent = $grossTotal > 0 ? ($order->tax_amount / $grossTotal) * 100 : 0;
                                    @endphp
                                    <div class="d-flex justify-content-between mb-1.5 fs-12">
                                        <span class="text-muted">Taxes ({{ round($taxPercent, 2) }}%):</span>
                                        <span class="fw-semibold text-dark">+{{ $currencySymbol }}{{ number_format($order->tax_amount, 2) }}</span>
                                    </div>
                                @endif

                                <hr class="my-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fs-13 fw-bold text-dark">Total Amount:</span>
                                    <span class="fs-13 fw-bold text-primary">{{ $currencySymbol }}{{ number_format($order->grand_total, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Signature block -->
                    <div class="row mt-4 pt-3 border-top text-dark">
                        <div class="col-6 text-start">
                            <p class="fs-10 text-muted mb-0">For queries regarding fulfillment, please refer to the purchase department.</p>
                        </div>
                        <div class="col-6 text-end">
                            <div class="d-inline-block text-center" style="width: 180px;">
                                <hr class="mb-1 mt-3">
                                <span class="fs-10 text-muted text-uppercase fw-semibold" style="letter-spacing: 0.5px;">Authorized Signature</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Advance Payments & Accounting Section -->
            @if($order->status === 'Approved')
                <div class="card border-0 shadow-sm bg-white mb-4 d-print-none">
                    <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold text-dark mb-0 fs-13"><i class="feather-dollar-sign text-success me-2"></i>Vendor Advance Payments & Accounting</h6>
                            <small class="text-muted fs-11">Record advance payments made to supplier against this Purchase Order</small>
                        </div>
                        @if($order->balance_due > 0)
                            <button type="button" class="btn btn-sm btn-primary py-1.5 px-3 fs-12 fw-semibold" data-bs-toggle="modal" data-bs-target="#advancePaymentModal" style="background-color: var(--bs-primary); border-color: var(--bs-primary);">
                                <i class="feather-plus-circle me-1.5"></i>Register Advance Payment
                            </button>
                        @endif
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3 mb-3 text-dark">
                            <div class="col-md-4">
                                <div class="p-3 border rounded bg-light-50">
                                    <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">Total PO Amount</span>
                                    <h4 class="fw-bold text-dark mb-0">{{ $currencySymbol }}{{ number_format($order->grand_total, 2) }}</h4>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 border rounded bg-light-50">
                                    <span class="fs-11 text-uppercase text-success fw-bold d-block mb-1">Advance Paid (Posted to Accounting)</span>
                                    <h4 class="fw-bold text-success mb-0">{{ $currencySymbol }}{{ number_format($order->total_advance_paid, 2) }}</h4>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 border rounded bg-light-50">
                                    <span class="fs-11 text-uppercase text-primary fw-bold d-block mb-1">Balance Due</span>
                                    <h4 class="fw-bold text-primary mb-0">{{ $currencySymbol }}{{ number_format($order->balance_due, 2) }}</h4>
                                </div>
                            </div>
                        </div>

                        @if($order->advancePayments->count() > 0)
                            <div class="table-responsive rounded border mt-3">
                                <table class="table table-bordered table-sm align-middle fs-13 text-dark mb-0">
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
                                                <td class="ps-3 fw-bold text-primary">{{ $adv->payment_number }}</td>
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
                            <div class="text-center py-3 text-muted fs-12">
                                <i class="feather-info me-1"></i>No advance payments registered for this order yet.
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Register Advance Payment Modal -->
                <x-ui.modal id="advancePaymentModal" title="Register Vendor Advance Payment" size="lg">
                    <form action="{{ route('purchase.orders.advance-payments.store') }}" method="POST" class="odoo-sheet">
                        @csrf
                        <input type="hidden" name="purchase_order_id" value="{{ $order->id }}">
                        <input type="hidden" name="vendor_id" value="{{ $order->vendor_id }}">

                        <div class="p-3">
                            <div class="alert alert-info py-2 px-3 fs-12 mb-3">
                                <i class="feather-info me-1"></i>
                                Registering this payment will post a double-entry Journal Entry to <strong>Vendor Advance (1200)</strong> and <strong>Bank (1010)</strong> via Black-Box Accounting Engine.
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
                                        <option value="Bank Transfer" selected>Bank Transfer (NEFT/RTGS)</option>
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
                                    <x-ui.odoo-form-ui type="input" label="Reference / Transaction No" name="reference_number" id="reference_number" placeholder="e.g. UTR123456789" />
                                </div>
                            </div>

                            <x-ui.odoo-form-ui type="textarea" label="Payment Notes / Remarks" name="notes" placeholder="Enter payment notes..." rows="2" />
                        </div>

                        <div class="modal-footer border-top px-3 py-2 bg-light d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-primary fw-semibold" style="background-color: var(--bs-primary); border-color: var(--bs-primary);">
                                <i class="feather-check me-1"></i>Post Advance Payment
                            </button>
                        </div>
                    </form>
                </x-ui.modal>
            @endif
        </div>
    </div>
@endsection
