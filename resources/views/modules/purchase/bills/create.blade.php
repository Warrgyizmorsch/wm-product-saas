@extends('layouts.duralux')

@section('title', __('purchase.create_vendor_bill') . ' | SaaS ERP')
@section('page-title', __('purchase.create_vendor_bill'))
@section('breadcrumb')
    <a href="{{ route('purchase.bills.index') }}">{{ __('purchase.vendor_bills') }}</a> &gt; {{ __('purchase.create') }}
@endsection

@push('styles')
    <style>
        .odoo-sheet {
            background: #ffffff;
            border-radius: 6px;
        }
        .so-status-pipeline {
            display: inline-flex;
            align-items: center;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            background-color: #f8fafc;
        }
        .so-status-pipeline .pipeline-step {
            padding: 4px 16px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            border-right: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .so-status-pipeline .pipeline-step:last-child {
            border-right: none;
        }
        .so-status-pipeline .pipeline-step.active {
            background: #3b82f6;
            color: #ffffff;
        }
        .bill-summary-card {
            max-width: 420px;
            width: 100%;
            border-radius: 8px;
            border: 1px solid #cbd5e1 !important;
            overflow: hidden;
            background: #ffffff;
        }
        .discount-column, .tax-column, .freight-column {
            display: none;
        }
    </style>
@endpush

@section('content')

    @php
        $po = $selectedGrn->purchaseOrder;
        
        // Initial order tax percent from PO or default 18%
        $initialOrderTaxPercent = (float)($po?->order_tax_percent ?? 18.00);
        if ($initialOrderTaxPercent <= 0) {
            $initialOrderTaxPercent = 18.00;
        }
    @endphp

    <div class="row text-dark">
        <div class="col-12">
            <div class="card border-0 shadow-sm p-4 p-md-5 bg-white">
                <form action="{{ route('purchase.bills.store') }}" method="POST" id="createVendorBillForm" class="odoo-sheet">
                    @csrf

                    <input type="hidden" name="goods_receipt_note_id" value="{{ $selectedGrn->id }}">
                    <input type="hidden" name="purchase_order_id" value="{{ $selectedGrn->purchase_order_id }}">

                    <!-- Top Header Bar & Status Pipeline -->
                    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-soft-warning text-warning fw-bold px-3 py-1.5 fs-12 border border-warning-subtle">
                                <i class="feather-clock me-1"></i>Draft Bill
                            </span>
                            @if($selectedGrn)
                                <span class="badge bg-soft-success text-success fs-12 fw-semibold px-3 py-1.5 rounded-pill">
                                    <i class="feather-check-circle me-1"></i>Source GRN: {{ $selectedGrn->grn_number }} @if($po) (PO: {{ $po->purchase_order_number }}) @endif
                                </span>
                            @endif
                        </div>

                        <!-- Status Pipeline -->
                        <div class="so-status-pipeline d-none d-sm-inline-flex">
                            <span class="pipeline-step active">Draft</span>
                            <span class="pipeline-step">Posted</span>
                        </div>
                    </div>

                    <!-- Available Advance Banner -->
                    @if(($availableAdvance ?? 0) > 0 || ($po?->total_advance_paid ?? 0) > 0)
                        @php
                            $advanceAmount = max($availableAdvance ?? 0, $po?->total_advance_paid ?? 0);
                        @endphp
                        <div class="alert alert-info border-info p-3 mb-4 rounded shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <strong class="text-dark fs-13"><i class="feather-info text-info me-1.5"></i>Vendor Advance Credit Available:</strong>
                                <span class="text-success fw-bold font-monospace fs-14 ms-1">₹{{ number_format($advanceAmount, 2) }}</span>
                                <small class="text-muted d-block fs-11 mt-0.5">This advance credit will be available to apply against the bill immediately after posting.</small>
                            </div>
                            <span class="badge bg-primary text-white px-3 py-2 fs-12 font-monospace">Credit Available</span>
                        </div>
                    @endif

                    <!-- Title Banner -->
                    <div class="mb-4">
                        <h3 class="fw-bold text-dark mb-1">Draft Vendor Bill</h3>
                        <p class="text-muted fs-12 mb-0">Generate vendor invoice record and post stock liability directly to accounting ledger. Inherits Discount, GST &amp; Tax settings from Purchase Order.</p>
                    </div>

                    <!-- General Information Grid -->
                    <div class="row g-4 fs-13 mb-4 pb-3 border-bottom">
                        <!-- Left Column: Vendor & Dates -->
                        <div class="col-md-6 border-end pe-md-4">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-user me-1"></i>Vendor &amp; Invoice Info</h6>

                            <input type="hidden" name="vendor_id" value="{{ $selectedGrn->vendor_id }}">
                            <div class="mb-3">
                                <x-ui.odoo-form-ui type="input" label="{{ __('purchase.supplier_vendor') }}" name="vendor_display_name" :value="$selectedGrn->vendor?->name ?? 'N/A'" readonly="true" />
                                <small class="text-muted fs-11 mt-1 d-block"><i class="feather-lock me-1 text-primary"></i>Vendor is locked from Source Goods Receipt Note ({{ $selectedGrn->grn_number }})</small>
                            </div>

                            <x-ui.odoo-form-ui type="input" label="{{ __('purchase.vendor_invoice_bill_no') }}" name="vendor_invoice_number" :value="old('vendor_invoice_number', $selectedGrn->challan_number)" placeholder="e.g. INV-98765" :errorText="$errors->first('vendor_invoice_number')" />
                            
                            <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('purchase.bill_date') }}" name="bill_date" :value="old('bill_date', date('Y-m-d'))" required="true" :errorText="$errors->first('bill_date')" />

                            <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('purchase.due_date') }}" name="due_date" :value="old('due_date', date('Y-m-d', strtotime('+30 days')))" required="true" :errorText="$errors->first('due_date')" />
                        </div>

                        <!-- Right Column: Tax & Calculation Options (Matching PO) -->
                        <div class="col-md-6 ps-md-4">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-sliders me-1"></i>Dates &amp; Calculation Options (Inherited from PO)</h6>

                            <x-ui.odoo-form-ui type="select" label="Discount Option" name="discount_type" id="discountTypeSelect" required="true">
                                <option value="without_discount" @selected(old('discount_type', $po?->discount_type ?? 'without_discount') === 'without_discount')>Without Discount</option>
                                <option value="item_wise" @selected(old('discount_type', $po?->discount_type ?? '') === 'item_wise')>Item Level Discount</option>
                                <option value="order_wise" @selected(old('discount_type', $po?->discount_type ?? '') === 'order_wise')>Order Level Discount</option>
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="select" label="Tax Option" name="tax_type" id="taxTypeSelect" required="true">
                                <option value="without_tax" @selected(old('tax_type', $po?->tax_type ?? '') === 'without_tax')>Without Tax</option>
                                <option value="item_wise_tax" @selected(old('tax_type', $po?->tax_type ?? '') === 'item_wise_tax')>Item Level Tax</option>
                                <option value="order_wise_tax" @selected(old('tax_type', $po?->tax_type ?? 'order_wise_tax') === 'order_wise_tax')>Order Level Tax</option>
                            </x-ui.odoo-form-ui>

                            <div id="gstTypeContainer">
                                <x-ui.odoo-form-ui type="select" label="GST Type" name="gst_type" id="gstTypeSelect" required="true">
                                    <option value="cgst_sgst" @selected(old('gst_type', $po?->gst_type ?? 'cgst_sgst') === 'cgst_sgst')>Intra-State (CGST + SGST)</option>
                                    <option value="igst" @selected(old('gst_type', $po?->gst_type ?? '') === 'igst')>Inter-State (IGST)</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <x-ui.odoo-form-ui type="select" label="Freight Terms" name="freight_terms" id="freightTermsSelect">
                                <option value="to_pay" @selected(old('freight_terms', $po?->freight_terms ?? 'to_pay') === 'to_pay')>To Pay (Freight Collect on Delivery)</option>
                                <option value="to_be_billed" @selected(old('freight_terms', $po?->freight_terms ?? '') === 'to_be_billed')>To Be Billed (Vendor Prepaid &amp; Added)</option>
                                <option value="prepaid" @selected(old('freight_terms', $po?->freight_terms ?? '') === 'prepaid')>FOR Site (Freight Included in Price)</option>
                                <option value="customer_pickup" @selected(old('freight_terms', $po?->freight_terms ?? '') === 'customer_pickup')>Self Pickup (Ex-Works)</option>
                            </x-ui.odoo-form-ui>

                            <!-- Dynamic Freight Fields Container -->
                            <div id="freightFieldsContainer">
                                <div class="row g-2">
                                    <div class="col-md-6" id="freightAmountCol">
                                        <x-ui.odoo-form-ui type="input" label="Freight Amount (₹)" name="freight_amount" id="freightAmountInput" inputType="number" step="0.01" min="0" :value="old('freight_amount', number_format($po?->freight_amount ?? 0, 2, '.', ''))" />
                                    </div>
                                    <div class="col-md-6" id="freightTaxMethodContainer">
                                        <x-ui.odoo-form-ui type="select" label="Freight Tax Method" name="freight_tax_method" id="freightTaxMethodSelect">
                                            <option value="highest_rate" @selected(old('freight_tax_method') === 'highest_rate')>Highest Item GST Rate (Composite Rule)</option>
                                            <option value="pro_rata" @selected(old('freight_tax_method') === 'pro_rata')>Pro-Rata Apportionment (Item Value Ratio)</option>
                                            <option value="manual" @selected(old('freight_tax_method') === 'manual')>Custom Specific Tax Rate (%)</option>
                                        </x-ui.odoo-form-ui>
                                    </div>
                                </div>
                                <div id="customFreightTaxPercentContainer" class="mt-2 d-none">
                                    <x-ui.odoo-form-ui type="input" label="Custom Freight Tax (%)" name="freight_tax_percent" id="freightTaxPercentInput" inputType="number" step="0.01" min="0" max="100" value="18" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Line Items Table -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold text-dark mb-0"><i class="feather-box text-primary me-2"></i>{{ __('purchase.grn_received_items_rates') }}</h6>
                            <span class="fs-12 text-muted"><i class="feather-info me-1"></i>Quantities &amp; Rates inherited from GRN &amp; PO</span>
                        </div>

                        <div class="table-responsive">
                            <x-ui.odoo-form-ui type="table" id="billItemsTable">
                                <thead>
                                    <tr>
                                        <th class="ps-3 py-2" style="width: 4%;">#</th>
                                        <th class="py-2" style="width: 26%;">{{ __('purchase.product') }}</th>
                                        <th class="text-center py-2" style="width: 10%;">{{ __('purchase.accepted_qty') }}</th>
                                        <th class="text-end py-2" style="width: 12%;">{{ __('purchase.unit_rate') }} (₹)</th>
                                        <th class="text-end py-2" style="width: 12%;">Amount (₹)</th>
                                        
                                        <!-- Item Level Discount Columns -->
                                        <th class="text-center py-2 discount-column" style="width: 9%;">Disc (%)</th>
                                        <th class="text-end py-2 discount-column" style="width: 11%;">Disc Amt (₹)</th>

                                        <!-- Freight Share Column (Shown on Pro-Rata Method) -->
                                        <th class="text-end py-2 freight-column text-primary" style="width: 12%;">Freight Share (₹)</th>

                                        <!-- Item Level Tax Columns -->
                                        <th class="text-center py-2 tax-column" style="width: 9%;">Tax Rate (%)</th>
                                        <th class="text-end py-2 tax-column" style="width: 11%;">Tax Amt (₹)</th>

                                        <th class="text-end pe-3 py-2" style="width: 14%;">{{ __('purchase.line_total') }} (₹)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($selectedGrn->items as $idx => $item)
                                        @php
                                            $poItem = $item->purchaseOrderItem;
                                            
                                            // Inherit item discount percent
                                            $discPct = (float)($poItem?->discount_percent ?? 0);
                                            
                                            // Inherit Tax Percent from PO Item or Product
                                            $inheritedTaxRate = $poItem?->tax_percent ?? (($poItem?->cgst_percent ?? 0) + ($poItem?->sgst_percent ?? 0) + ($poItem?->igst_percent ?? 0));
                                            if (!$inheritedTaxRate || $inheritedTaxRate <= 0) {
                                                $inheritedTaxRate = (float)($initialOrderTaxPercent ?? $item->product?->tax_rate ?? 18);
                                            }

                                            $qty = (float) $item->accepted_qty;
                                            $rate = (float) $item->unit_rate;
                                            $lineSub = $qty * $rate;
                                        @endphp
                                        <tr class="bill-line-row">
                                            <td class="ps-3 py-2 text-muted fw-semibold">{{ $idx + 1 }}</td>
                                            <td class="py-2">
                                                <input type="hidden" name="items[{{ $idx }}][product_id]" value="{{ $item->product_id }}">
                                                <input type="hidden" name="items[{{ $idx }}][goods_receipt_note_item_id]" value="{{ $item->id }}">
                                                <input type="hidden" name="items[{{ $idx }}][purchase_order_item_id]" value="{{ $item->purchase_order_item_id }}">
                                                <strong class="text-dark d-block fs-13">{{ $item->product?->name }}</strong>
                                                @if($item->product?->sku)
                                                    <span class="text-muted fs-11 font-monospace">SKU: {{ $item->product->sku }}</span>
                                                @endif
                                            </td>
                                            <td class="text-center py-2">
                                                <input type="number" name="items[{{ $idx }}][quantity]" class="odoo-table-input text-center font-monospace item-qty" value="{{ $qty }}" step="0.001" min="0.001" required readonly>
                                            </td>
                                            <td class="text-end py-2">
                                                <input type="number" name="items[{{ $idx }}][unit_price]" class="odoo-table-input text-end font-monospace item-rate" value="{{ $rate }}" step="0.01" min="0" required>
                                            </td>
                                            <td class="text-end py-2 font-monospace text-dark item-amount-display">
                                                ₹{{ number_format($lineSub, 2) }}
                                            </td>

                                            <!-- Discount Columns -->
                                            <td class="text-center py-2 discount-column">
                                                <input type="number" name="items[{{ $idx }}][discount_percent]" class="odoo-table-input text-center font-monospace item-disc-percent" value="{{ $discPct }}" step="0.01" min="0" max="100">
                                            </td>
                                            <td class="text-end py-2 font-monospace text-danger discount-column item-disc-amount-display">
                                                ₹0.00
                                            </td>

                                            <!-- Freight Share Column -->
                                            <td class="text-end py-2 font-monospace text-primary freight-column item-freight-share-display">
                                                +₹0.00
                                            </td>

                                            <!-- Tax Columns -->
                                            <td class="text-center py-2 tax-column">
                                                <input type="number" name="items[{{ $idx }}][tax_rate]" class="odoo-table-input text-center font-monospace item-tax-rate" value="{{ $inheritedTaxRate }}" step="0.01" min="0" max="100">
                                            </td>
                                            <td class="text-end py-2 font-monospace text-muted tax-column item-tax-display">
                                                ₹0.00
                                            </td>

                                            <td class="text-end pe-3 py-2 font-monospace fw-bold text-dark item-total-display">
                                                ₹0.00
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <!-- Bottom Section: Notes & Absolute ERP Financial Summary -->
                    <div class="row mt-4 pt-3 border-top g-4">
                        <div class="col-md-7">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('purchase.notes_terms') }}" name="notes" placeholder="{{ __('purchase.notes_placeholder') }}" rows="4" />
                        </div>

                        <!-- Enterprise Financial Summary Box (Absolute ERP Flow) -->
                        <div class="col-md-5 d-flex flex-column align-items-end fs-13">
                            <div class="bill-summary-card shadow-sm">
                                <div class="fw-bold py-3 px-3 text-white" style="background-color: #2563eb; font-size: 12px; letter-spacing: 0.5px; text-transform: uppercase;">
                                    Financial Summary
                                </div>
                                <div class="p-3 bg-white text-dark">
                                    
                                    <!-- 1. Gross Subtotal -->
                                    <div class="d-flex justify-content-between align-items-center mb-2" id="summarySubtotalRow">
                                        <span class="text-muted fs-13 fw-semibold">Subtotal (Excl. Tax):</span>
                                        <input type="text" id="summarySubtotalText" class="form-control form-control-sm text-end fw-bold" style="width: 150px; height: 30px; border: 1px solid #cbd5e1; border-radius: 4px; color: #334155; background-color: #f8fafc;" readonly value="0.00">
                                    </div>

                                    <!-- 2. Discount Row -->
                                    <div class="d-flex justify-content-between align-items-center mb-2" id="summaryDiscountRow">
                                        <span class="text-muted fs-13 fw-semibold" id="summaryDiscountLabel">Less: Discount:</span>
                                        <input type="number" name="discount_amount" id="discountAmountInput" class="form-control form-control-sm text-end fw-bold" style="width: 150px; height: 30px; border: 1px solid #cbd5e1; border-radius: 4px; color: #dc2626;" step="0.01" min="0" value="{{ number_format($po?->discount_amount ?? 0, 2, '.', '') }}">
                                        <input type="text" id="summaryItemDiscountText" class="form-control form-control-sm text-end fw-bold text-danger d-none" style="width: 150px; height: 30px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" readonly value="0.00">
                                    </div>

                                    <!-- 3. Net Items Taxable Value -->
                                    <div class="d-flex justify-content-between align-items-center mb-2" id="summaryGrossRow">
                                        <span class="text-muted fs-13 fw-semibold" id="summaryGrossLabel">Items Taxable Value:</span>
                                        <input type="text" id="summaryGrossText" class="form-control form-control-sm text-end fw-bold" style="width: 150px; height: 30px; border: 1px solid #cbd5e1; border-radius: 4px; color: #334155; background-color: #f8fafc;" readonly value="0.00">
                                    </div>

                                    <!-- 4. Items GST Tax (Item Level Tax Mode) -->
                                    <div class="d-flex justify-content-between align-items-center mb-2" id="summaryItemsTaxRow">
                                        <span class="text-muted fs-13 fw-semibold" id="summaryItemsTaxLabel">Add: Items GST Tax:</span>
                                        <input type="text" id="summaryItemsTaxText" class="form-control form-control-sm text-end font-monospace text-muted" style="width: 150px; height: 30px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" readonly value="0.00">
                                    </div>

                                    <!-- 5. Billed Items Total (Item Level Tax Mode) -->
                                    <div class="d-flex justify-content-between align-items-center mb-3 fw-bold text-dark" id="summaryItemsTotalRow">
                                        <span class="fs-13" id="summaryItemsTotalLabel">Billed Items Total (Incl. GST):</span>
                                        <input type="text" id="summaryItemsTotalText" class="form-control form-control-sm text-end fw-bold text-dark" style="width: 150px; height: 30px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f1f5f9;" readonly value="0.00">
                                    </div>

                                    <!-- FREIGHT BREAKDOWN SECTION (Item Level Tax Non-Pro-Rata Mode) -->
                                    <div id="summaryFreightSectionContainer">
                                        <hr class="my-2 border-slate">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted fs-13 fw-semibold">Freight Charges:</span>
                                            <input type="text" id="summaryFreightText" class="form-control form-control-sm text-end fw-bold text-primary" style="width: 150px; height: 30px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" readonly value="0.00">
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mb-2" id="summaryFreightTaxRow">
                                            <span class="text-muted fs-13 fw-semibold" id="summaryFreightTaxLabel">Add: Freight GST Tax:</span>
                                            <input type="text" id="summaryFreightTaxText" class="form-control form-control-sm text-end font-monospace text-muted" style="width: 150px; height: 30px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" readonly value="0.00">
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mb-3 fw-bold text-dark" id="summaryFreightTotalRow">
                                            <span class="fs-13">Total Freight (Incl. GST):</span>
                                            <input type="text" id="summaryFreightTotalText" class="form-control form-control-sm text-end fw-bold text-primary" style="width: 150px; height: 30px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #eff6ff;" readonly value="0.00">
                                        </div>
                                    </div>

                                    <!-- FREIGHT CHARGES (Order Level Tax Mode) -->
                                    <div class="d-flex justify-content-between align-items-center mb-2" id="summaryOrderFreightRow">
                                        <span class="text-muted fs-13 fw-semibold">Add: Freight Charges:</span>
                                        <input type="text" id="summaryOrderFreightText" class="form-control form-control-sm text-end fw-bold text-primary" style="width: 150px; height: 30px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" readonly value="0.00">
                                    </div>

                                    <!-- TOTAL TAXABLE BASE (Order Level Tax Mode) -->
                                    <div class="d-flex justify-content-between align-items-center mb-2" id="summaryTotalTaxableBaseRow">
                                        <span class="text-dark fs-13 fw-bold">Total Taxable Base (Items + Freight):</span>
                                        <input type="text" id="summaryTotalTaxableBaseText" class="form-control form-control-sm text-end fw-bold text-dark" style="width: 150px; height: 30px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f1f5f9;" readonly value="0.00">
                                    </div>

                                    <!-- ORDER TAX RATE INPUT & GST AMOUNT -->
                                    <div id="summaryOrderTaxSection">
                                        <hr class="my-2 border-slate">
                                        <div class="d-flex justify-content-between align-items-center mb-2" id="orderTaxPercentRow">
                                            <span class="text-muted fs-13 fw-semibold">Order Tax Rate (%):</span>
                                            <input type="number" name="order_tax_percent" id="orderTaxPercentInput" class="form-control form-control-sm text-end fw-bold" style="width: 150px; height: 30px; border: 1px solid #cbd5e1; border-radius: 4px; color: #334155;" min="0" step="0.01" value="{{ $initialOrderTaxPercent }}">
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mb-2" id="summaryOrderTaxAmtRow">
                                            <span class="text-muted fs-13 fw-semibold" id="summaryOrderTaxAmtLabel">Add: Order GST Tax:</span>
                                            <input type="text" id="summaryOrderTaxAmtText" class="form-control form-control-sm text-end font-monospace text-muted" style="width: 150px; height: 30px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" readonly value="0.00">
                                        </div>
                                    </div>

                                    <!-- TAX SPLIT & GRAND TOTAL -->
                                    <hr class="my-2 border-slate">

                                    <div class="d-flex justify-content-between align-items-center mb-2" id="cgstRow">
                                        <span class="text-muted fs-13 fw-semibold" id="cgstLabel">CGST (Central Tax):</span>
                                        <input type="text" id="cgstText" class="form-control form-control-sm text-end font-monospace text-muted" style="width: 150px; height: 30px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" readonly value="0.00">
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-2" id="sgstRow">
                                        <span class="text-muted fs-13 fw-semibold" id="sgstLabel">SGST (State Tax):</span>
                                        <input type="text" id="sgstText" class="form-control form-control-sm text-end font-monospace text-muted" style="width: 150px; height: 30px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" readonly value="0.00">
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-2 d-none" id="igstRow">
                                        <span class="text-muted fs-13 fw-semibold" id="igstLabel">IGST (Integrated Tax):</span>
                                        <input type="text" id="igstText" class="form-control form-control-sm text-end font-monospace text-muted" style="width: 150px; height: 30px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" readonly value="0.00">
                                    </div>

                                    <hr class="my-2 border-slate">

                                    <!-- Grand Total Amount -->
                                    <div class="d-flex justify-content-between align-items-center pt-1">
                                        <span class="fw-bold fs-14 text-primary">Grand Total:</span>
                                        <input type="text" id="summaryGrandtotalText" class="form-control form-control-sm text-end fw-extrabold" style="width: 150px; height: 34px; border: 1px solid #2563eb; border-radius: 4px; background-color: #eff6ff; color: #2563eb; font-size: 15px;" readonly value="0.00">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Action Control Bar -->
                    <div class="d-flex justify-content-between align-items-center mt-5 pt-3 border-top flex-wrap gap-2">
                        <x-ui.button href="{{ route('purchase.bills.pending') }}" variant="light" icon="feather-arrow-left" class="border fw-semibold">
                            {{ __('purchase.cancel') }}
                        </x-ui.button>

                        <x-ui.button type="submit" variant="primary" icon="feather-check-circle" class="fw-bold px-4 py-2 text-white shadow-sm" style="background-color: #714B67; border-color: #714B67;">
                            {{ __('purchase.post_bill_to_gl') }}
                        </x-ui.button>
                    </div>

                </form>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            function adjustLayoutAndCalculate() {
                var discType = $('#discountTypeSelect').val() || $('select[name="discount_type"]').val() || 'without_discount';
                var taxType = $('#taxTypeSelect').val() || $('select[name="tax_type"]').val() || 'order_wise_tax';
                var gstType = $('#gstTypeSelect').val() || $('select[name="gst_type"]').val() || 'cgst_sgst';
                var freightTerms = $('#freightTermsSelect').val() || $('select[name="freight_terms"]').val() || 'to_pay';
                var freightTaxMethod = $('#freightTaxMethodSelect').val() || $('select[name="freight_tax_method"]').val() || 'highest_rate';

                var isWithoutTax = (taxType === 'without_tax');
                var isItemWiseTax = (taxType === 'item_wise_tax');
                var isOrderWiseTax = (taxType === 'order_wise_tax');

                var isIgst = (gstType === 'igst');
                var isFreightEligible = (freightTerms === 'to_pay' || freightTerms === 'to_be_billed');

                // 1. Discount Option Layout Toggles
                if (discType === 'item_wise') {
                    $('.discount-column').removeClass('d-none').show();
                    $('#summaryDiscountRow').removeClass('d-none').show();
                    $('#discountAmountInput').addClass('d-none').hide();
                    $('#summaryItemDiscountText').removeClass('d-none').show();
                    $('#summaryDiscountLabel').text('Less: Item Discounts:');
                    $('#summaryGrossRow').removeClass('d-none').show();
                } else if (discType === 'order_wise') {
                    $('.discount-column').addClass('d-none').hide();
                    $('#summaryDiscountRow').removeClass('d-none').show();
                    $('#discountAmountInput').removeClass('d-none').show();
                    $('#summaryItemDiscountText').addClass('d-none').hide();
                    $('#summaryDiscountLabel').text('Less: Order Discount:');
                    $('#summaryGrossRow').removeClass('d-none').show();
                } else {
                    // without_discount
                    $('.discount-column').addClass('d-none').hide();
                    $('#summaryDiscountRow').addClass('d-none').hide();
                    $('#discountAmountInput').val('0.00');
                    $('#summaryGrossRow').addClass('d-none').hide();
                }

                // 2. Tax Option Layout Toggles
                if (isItemWiseTax) {
                    $('.tax-column').removeClass('d-none').show();
                    $('#gstTypeContainer').removeClass('d-none').show();
                    $('#freightTaxMethodContainer').removeClass('d-none').show();
                    $('#freightAmountCol').removeClass('col-md-12').addClass('col-md-6');

                    $('#summaryItemsTaxRow').removeClass('d-none').show();
                    $('#summaryItemsTotalRow').removeClass('d-none').show();

                    $('#summaryOrderFreightRow').addClass('d-none').hide();
                    $('#summaryTotalTaxableBaseRow').addClass('d-none').hide();
                    $('#summaryOrderTaxSection').addClass('d-none').hide();
                } else if (isOrderWiseTax) {
                    $('.tax-column').addClass('d-none').hide();
                    $('#gstTypeContainer').removeClass('d-none').show();

                    // Hide Freight Tax Method container in Order Wise Tax
                    $('#freightTaxMethodContainer').addClass('d-none').hide();
                    $('#freightAmountCol').removeClass('col-md-6').addClass('col-md-12');

                    $('#summaryItemsTaxRow').addClass('d-none').hide();
                    $('#summaryItemsTotalRow').addClass('d-none').hide();
                    $('#summaryFreightSectionContainer').addClass('d-none').hide();

                    if (isFreightEligible) {
                        $('#summaryOrderFreightRow').removeClass('d-none').show();
                    } else {
                        $('#summaryOrderFreightRow').addClass('d-none').hide();
                    }

                    $('#summaryTotalTaxableBaseRow').removeClass('d-none').show();
                    $('#summaryOrderTaxSection').removeClass('d-none').show();
                } else {
                    // without_tax
                    $('.tax-column').addClass('d-none').hide();
                    $('#gstTypeContainer').addClass('d-none').hide();
                    $('#freightTaxMethodContainer').addClass('d-none').hide();

                    $('#summaryItemsTaxRow').addClass('d-none').hide();
                    $('#summaryItemsTotalRow').addClass('d-none').hide();
                    $('#summaryFreightSectionContainer').addClass('d-none').hide();
                    $('#summaryOrderFreightRow').addClass('d-none').hide();
                    $('#summaryTotalTaxableBaseRow').addClass('d-none').hide();
                    $('#summaryOrderTaxSection').addClass('d-none').hide();
                }

                // 3. Freight Terms & Pro-Rata Freight Column Toggles
                var freightAmount = 0;
                if (isFreightEligible) {
                    $('#freightFieldsContainer').removeClass('d-none').show();
                    freightAmount = parseFloat($('#freightAmountInput').val()) || 0;
                } else {
                    $('#freightFieldsContainer').addClass('d-none').hide();
                    freightAmount = 0;
                }

                var isProRataFreight = (isItemWiseTax && isFreightEligible && freightTaxMethod === 'pro_rata' && freightAmount > 0);

                if (isItemWiseTax && isFreightEligible && !isProRataFreight) {
                    $('#summaryFreightSectionContainer').removeClass('d-none').show();
                } else {
                    $('#summaryFreightSectionContainer').addClass('d-none').hide();
                }

                if (isProRataFreight) {
                    $('.freight-column').removeClass('d-none').show();
                } else {
                    $('.freight-column').addClass('d-none').hide();
                }

                // Toggle custom tax percent input box (only when item-wise tax & manual freight tax method)
                if (isItemWiseTax && freightTaxMethod === 'manual') {
                    $('#customFreightTaxPercentContainer').removeClass('d-none').show();
                } else {
                    $('#customFreightTaxPercentContainer').addClass('d-none').hide();
                }

                // First Pass: Calculate total gross subtotal & total item discounts for taxable base ratio
                var grossSubtotal = 0;
                var itemDiscountsTotal = 0;

                $('.bill-line-row').each(function() {
                    var row = $(this);
                    var qty = parseFloat(row.find('.item-qty').val()) || 0;
                    var rate = parseFloat(row.find('.item-rate').val()) || 0;
                    var lineSub = qty * rate;
                    grossSubtotal += lineSub;

                    if (discType === 'item_wise') {
                        var discPct = parseFloat(row.find('.item-disc-percent').val()) || 0;
                        var lineDisc = lineSub * (discPct / 100);
                        itemDiscountsTotal += lineDisc;
                    }
                });

                var discountAmount = 0;
                if (discType === 'order_wise') {
                    discountAmount = parseFloat($('#discountAmountInput').val()) || 0;
                } else if (discType === 'item_wise') {
                    discountAmount = itemDiscountsTotal;
                }

                var itemsTaxableValue = Math.max(0, grossSubtotal - discountAmount);

                // Second Pass: Row-by-Row Calculation with Freight Share & Tax
                var itemTaxesTotal = 0;
                var freightTaxAmount = 0;

                $('.bill-line-row').each(function() {
                    var row = $(this);
                    var qty = parseFloat(row.find('.item-qty').val()) || 0;
                    var rate = parseFloat(row.find('.item-rate').val()) || 0;
                    var lineSub = qty * rate;

                    var lineDisc = 0;
                    if (discType === 'item_wise') {
                        var discPct = parseFloat(row.find('.item-disc-percent').val()) || 0;
                        lineDisc = lineSub * (discPct / 100);
                        row.find('.item-disc-amount-display').text('₹' + lineDisc.toFixed(2));
                    }

                    var lineNetSub = Math.max(0, lineSub - lineDisc);

                    // Apportion Freight Share to Item Line if pro_rata is selected
                    var itemFreightShare = 0;
                    if (isProRataFreight && itemsTaxableValue > 0) {
                        var ratio = lineNetSub / itemsTaxableValue;
                        itemFreightShare = freightAmount * ratio;
                        row.find('.item-freight-share-display').text('+₹' + itemFreightShare.toFixed(2));
                    } else {
                        row.find('.item-freight-share-display').text('+₹0.00');
                    }

                    var lineTax = 0;
                    var itemTaxRate = 0;

                    if (isItemWiseTax) {
                        itemTaxRate = parseFloat(row.find('.item-tax-rate').val()) || 0;
                        if (isProRataFreight) {
                            var pureItemTax = lineNetSub * (itemTaxRate / 100);
                            var pureFreightTax = itemFreightShare * (itemTaxRate / 100);
                            itemTaxesTotal += pureItemTax;
                            freightTaxAmount += pureFreightTax;
                            lineTax = pureItemTax + pureFreightTax;
                        } else {
                            lineTax = lineNetSub * (itemTaxRate / 100);
                            itemTaxesTotal += lineTax;
                        }
                    }

                    row.find('.item-tax-display').text('₹' + lineTax.toFixed(2));

                    var lineTotal = lineNetSub + itemFreightShare + lineTax;

                    row.find('.item-amount-display').text('₹' + lineSub.toFixed(2));
                    row.find('.item-total-display').text('₹' + lineTotal.toFixed(2));
                });

                // Summary Calculation Engine based on Tax Mode
                var totalGST = 0;
                var grandTotal = 0;

                if (isWithoutTax) {
                    totalGST = 0;
                    grandTotal = itemsTaxableValue + freightAmount;
                } else if (isOrderWiseTax) {
                    var orderTaxPct = parseFloat($('#orderTaxPercentInput').val()) || 0;
                    var totalTaxableBase = itemsTaxableValue + freightAmount;
                    totalGST = totalTaxableBase * (orderTaxPct / 100);
                    grandTotal = totalTaxableBase + totalGST;

                    $('#summaryOrderFreightText').val('₹' + freightAmount.toFixed(2));
                    $('#summaryTotalTaxableBaseText').val('₹' + totalTaxableBase.toFixed(2));
                    $('#summaryOrderTaxAmtLabel').text('Add: Order GST Tax (' + orderTaxPct + '%):');
                    $('#summaryOrderTaxAmtText').val('+₹' + totalGST.toFixed(2));
                } else if (isItemWiseTax) {
                    var itemsTaxTotal = itemTaxesTotal;

                    if (isFreightEligible && freightAmount > 0 && !isProRataFreight) {
                        if (freightTaxMethod === 'manual') {
                            var manualTaxPct = parseFloat($('#freightTaxPercentInput').val()) || 0;
                            freightTaxAmount = freightAmount * (manualTaxPct / 100);
                            $('#summaryFreightTaxLabel').text('Add: Freight GST Tax (' + manualTaxPct + '%):');
                        } else {
                            // highest_rate
                            var maxTaxRate = 0;
                            $('.bill-line-row').each(function() {
                                var rRate = parseFloat($(this).find('.item-tax-rate').val()) || 0;
                                if (rRate > maxTaxRate) maxTaxRate = rRate;
                            });
                            if (maxTaxRate <= 0) maxTaxRate = 18;
                            freightTaxAmount = freightAmount * (maxTaxRate / 100);
                            $('#summaryFreightTaxLabel').text('Add: Freight Tax (Highest Rate ' + maxTaxRate + '%):');
                        }
                    }

                    totalGST = itemsTaxTotal + freightTaxAmount;

                    if (isProRataFreight) {
                        $('#summaryItemsTaxLabel').text('Add: Total GST Tax (Items + Freight):');
                        $('#summaryItemsTotalLabel').text('Total Payable Amount (Incl. GST):');
                        var itemsTotalInclTax = itemsTaxableValue + freightAmount + totalGST;
                        grandTotal = itemsTotalInclTax;
                    } else {
                        $('#summaryItemsTaxLabel').text('Add: Items GST Tax:');
                        $('#summaryItemsTotalLabel').text('Billed Items Total (Incl. GST):');
                        var itemsTotalInclTax = itemsTaxableValue + itemsTaxTotal;
                        var freightTotalInclTax = freightAmount + freightTaxAmount;
                        grandTotal = Math.max(0, itemsTotalInclTax + freightTotalInclTax);
                    }

                    $('#summaryItemsTaxText').val('+₹' + (isProRataFreight ? totalGST : itemsTaxTotal).toFixed(2));
                    $('#summaryItemsTotalText').val('₹' + itemsTotalInclTax.toFixed(2));
                    $('#summaryFreightText').val('₹' + freightAmount.toFixed(2));
                    $('#summaryFreightTaxText').val('+₹' + freightTaxAmount.toFixed(2));
                    $('#summaryFreightTotalText').val('₹' + (freightAmount + freightTaxAmount).toFixed(2));
                }

                // Financial Summary Card Common DOM Updates
                $('#summarySubtotalText').val('₹' + grossSubtotal.toFixed(2));
                $('#summaryItemDiscountText').val('-₹' + itemDiscountsTotal.toFixed(2));
                $('#summaryGrossText').val('₹' + itemsTaxableValue.toFixed(2));
                $('#summaryGrandtotalText').val('₹' + grandTotal.toFixed(2));

                if (isWithoutTax) {
                    $('#cgstRow, #sgstRow, #igstRow').addClass('d-none').hide();
                } else if (isIgst) {
                    $('#cgstRow, #sgstRow').addClass('d-none').hide();
                    $('#igstRow').removeClass('d-none').show();
                    $('#igstText').val('₹' + totalGST.toFixed(2));
                } else {
                    $('#igstRow').addClass('d-none').hide();
                    $('#cgstRow, #sgstRow').removeClass('d-none').show();

                    var halfTax = totalGST / 2;
                    $('#cgstText').val('₹' + halfTax.toFixed(2));
                    $('#sgstText').val('₹' + halfTax.toFixed(2));
                }
            }

            // Auto-fill empty tax rate input only when switching tax type option
            $(document).on('change change.select2 select2:select', '#taxTypeSelect', function() {
                if ($(this).val() === 'item_wise_tax') {
                    $('.bill-line-row').each(function() {
                        var taxInput = $(this).find('.item-tax-rate');
                        var rawVal = $.trim(taxInput.val());
                        if (rawVal === '') {
                            var defaultPct = parseFloat($('#orderTaxPercentInput').val()) || 18;
                            taxInput.val(defaultPct);
                        }
                    });
                }
            });

            // Bind jQuery event handlers to handle native selects and Select2 events
            $(document).on('change change.select2 select2:select input keyup', '#discountTypeSelect, #taxTypeSelect, #gstTypeSelect, #freightTermsSelect, #freightTaxMethodSelect, #discountAmountInput, #freightAmountInput, #freightTaxPercentInput, #orderTaxPercentInput, .item-qty, .item-rate, .item-disc-percent, .item-tax-rate', function() {
                adjustLayoutAndCalculate();
            });

            // Initial calculation run immediately
            adjustLayoutAndCalculate();
            setTimeout(adjustLayoutAndCalculate, 200);
        });
    </script>
@endpush
