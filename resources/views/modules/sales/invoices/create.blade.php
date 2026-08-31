@extends('layouts.duralux')

@section('title', 'Generate Invoice | SaaS ERP')
@section('page-title', 'Generate Invoice')
@section('breadcrumb', 'Sales / Invoices / Generate')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .select2-container {
            width: 100% !important;
            max-width: 100% !important;
        }
        .tax-column, .discount-column {
            display: none;
        }
    </style>
@endpush

@section('content')
    <div class="erp-single-panel bg-white p-4">

        <form action="{{ route('sales.invoices.store') }}" method="POST" id="invoiceForm">
            @csrf
            <input type="hidden" name="mode" id="currentModeInput" value="{{ $mode }}">

            <x-ui.odoo-form-ui type="sheet">
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Generate Customer Invoice</h5>
                        <span class="fs-12 text-muted">Create billing invoice against Sales Order, Dispatch Order, or directly for Customer.</span>
                    </div>
                    <div class="d-flex gap-2">
                        <x-ui.button href="{{ route('sales.invoices.index') }}" variant="light" size="sm" class="border">Cancel</x-ui.button>
                        <x-ui.button type="submit" variant="primary" size="sm" icon="feather-save" style="background-color: #1e40af; border-color: #1e40af;">Save Invoice</x-ui.button>
                    </div>
                </div>

                <!-- 3-Mode Selection Radio Bar -->
                <div class="mb-4 bg-light p-3 rounded border">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted d-block mb-2">Create Invoice Based On:</label>
                    <div class="d-flex gap-4 flex-wrap align-items-center">
                        <div class="form-check">
                            <input class="form-check-input mode-radio" type="radio" name="mode_option" id="modeSalesOrder" value="sales_order" {{ $mode === 'sales_order' ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold text-dark fs-13" for="modeSalesOrder">
                                <i class="feather-file-text me-1 text-primary"></i>Against Sales Order
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input mode-radio" type="radio" name="mode_option" id="modeDispatchOrder" value="dispatch_order" {{ $mode === 'dispatch_order' ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold text-dark fs-13" for="modeDispatchOrder">
                                <i class="feather-truck me-1 text-info"></i>Against Dispatch Order / Delivery
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input mode-radio" type="radio" name="mode_option" id="modeDirect" value="direct" {{ $mode === 'direct' ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold text-dark fs-13" for="modeDirect">
                                <i class="feather-user me-1 text-success"></i>Direct Invoice (Standalone)
                            </label>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4 fs-13 text-dark">
                    <!-- Column 1: Source Selector & Customer Info -->
                    <div class="col-md-6 border-end pe-md-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-user me-1"></i>Customer & Billing Reference</h6>

                        @if ($mode === 'sales_order')
                            <!-- Mode 1: Sales Order Dropdown -->
                            <x-ui.odoo-form-ui type="select" label="Sales Order Reference" name="sales_order_id" id="salesOrderSelect" class="odoo-select2" :required="true">
                                <option value="">Select Sales Order...</option>
                                @foreach ($salesOrders as $so)
                                    <option value="{{ $so->id }}" @selected($salesOrder?->id == $so->id)>
                                        {{ $so->sales_order_number }} (Customer: {{ $so->customer?->name }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="input" label="Customer" name="_customer_display" :value="$salesOrder?->customer?->name ?: '—'" readonly="true" style="font-weight: bold; background-color: transparent;" />

                            <x-ui.odoo-form-ui type="input" label="Payment Terms" name="_terms_display" :value="$salesOrder?->payment_terms ?: 'Immediate Payment'" readonly="true" style="background-color: transparent;" />

                        @elseif ($mode === 'dispatch_order')
                            <!-- Mode 2: Dispatch Order Dropdown -->
                            <x-ui.odoo-form-ui type="select" label="Dispatch Order Reference" name="dispatch_order_id" id="dispatchOrderSelect" class="odoo-select2" :required="true">
                                <option value="">Select Dispatch Order...</option>
                                @foreach ($dispatchOrders as $do)
                                    <option value="{{ $do->id }}" @selected($dispatchOrder?->id == $do->id)>
                                        {{ $do->dispatch_number }} (SO: {{ $do->salesOrder?->sales_order_number }} - Customer: {{ $do->salesOrder?->customer?->name }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>

                            <input type="hidden" name="material_requirement_id" value="{{ $dispatchOrder?->material_requirement_id }}">
                            <input type="hidden" name="sales_order_id" value="{{ $salesOrder?->id }}">

                            <x-ui.odoo-form-ui type="input" label="Customer" name="_customer_display" :value="$salesOrder?->customer?->name ?: ($dispatchOrder?->salesOrder?->customer?->name ?: '—')" readonly="true" style="font-weight: bold; background-color: transparent;" />

                            <x-ui.odoo-form-ui type="input" label="Payment Terms" name="_terms_display" :value="$salesOrder?->payment_terms ?: 'Immediate Payment'" readonly="true" style="background-color: transparent;" />

                        @else
                            <!-- Mode 3: Direct Customer Dropdown -->
                            <x-ui.odoo-form-ui type="select" label="Customer / Client" name="customer_id" id="directCustomerSelect" class="odoo-select2" :required="true">
                                <option value="">Select Customer...</option>
                                @foreach ($customers as $c)
                                    <option value="{{ $c->id }}" @selected(old('customer_id', $customerId ?? request('customer_id')) == $c->id)>
                                        {{ $c->name }} ({{ $c->company_name ?: 'Individual' }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="select" label="Payment Terms" name="payment_terms">
                                <option value="Immediate Payment">Immediate Payment</option>
                                <option value="15 Days">15 Days</option>
                                <option value="30 Days">30 Days</option>
                                <option value="45 Days">45 Days</option>
                                <option value="60 Days">60 Days</option>
                            </x-ui.odoo-form-ui>
                        @endif

                        @if ($advanceAllocations > 0)
                            <div class="alert alert-info border-0 shadow-sm mt-3 py-2 px-3 fs-12 text-info">
                                <i class="feather-info me-2 fw-bold"></i>
                                Advance Paid on Sales Order: <strong>₹{{ number_format($advanceAllocations, 2) }}</strong>. Automatically adjusted!
                            </div>
                        @endif
                    </div>

                    <!-- Column 2: Invoice Dates, Tax & Discount Options (PO Style) -->
                    <div class="col-md-6 ps-md-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-sliders me-1"></i>Dates & Calculation Options</h6>

                        <x-ui.odoo-form-ui type="input" label="Invoice Number" name="invoice_number" :value="old('invoice_number', $nextInvoiceNumber)" :required="true" style="font-weight: bold; color: #495057;" />

                        <x-ui.odoo-form-ui type="input" inputType="date" label="Invoice Date" name="invoice_date" :value="old('invoice_date', date('Y-m-d'))" :required="true" />

                        <x-ui.odoo-form-ui type="input" inputType="date" label="Due Date" name="due_date" :value="old('due_date', date('Y-m-d', strtotime('+15 days')))" />

                        <!-- PO Style Discount Option -->
                        <x-ui.odoo-form-ui type="select" label="Discount Option" name="discount_type" id="discountTypeSelect" :required="true">
                            <option value="without_discount" @selected(old('discount_type', $salesOrder?->discount_type ?? 'item_wise') === 'without_discount')>Without Discount</option>
                            <option value="item_wise" @selected(old('discount_type', $salesOrder?->discount_type ?? 'item_wise') === 'item_wise')>Item Level Discount</option>
                            <option value="order_wise" @selected(old('discount_type', $salesOrder?->discount_type) === 'order_wise')>Order Level Discount</option>
                        </x-ui.odoo-form-ui>

                        <!-- PO Style Tax Option -->
                        <x-ui.odoo-form-ui type="select" label="Tax Option" name="tax_type" id="taxTypeSelect" :required="true">
                            <option value="without_tax" @selected(old('tax_type', $salesOrder?->tax_type) === 'without_tax')>Without Tax</option>
                            <option value="item_wise_tax" @selected(old('tax_type', $salesOrder?->tax_type ?? 'item_wise_tax') === 'item_wise_tax')>Item Wise Tax</option>
                            <option value="order_wise_tax" @selected(old('tax_type', $salesOrder?->tax_type) === 'order_wise_tax')>Order Wise Tax</option>
                        </x-ui.odoo-form-ui>

                        <!-- GST Option (CGST/SGST vs IGST) -->
                        <x-ui.odoo-form-ui type="select" label="GST Option" name="gst_type" id="gstTypeSelect" :required="true">
                            <option value="cgst_sgst" @selected(old('gst_type', $salesOrder?->gst_type ?? 'cgst_sgst') === 'cgst_sgst')>CGST + SGST (Intra-State)</option>
                            <option value="igst" @selected(old('gst_type', $salesOrder?->gst_type) === 'igst')>IGST (Inter-State)</option>
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="select" label="Freight Terms" name="freight_terms" id="invFreightTermsSelect">
                            <option value="To Pay" @selected(old('freight_terms', $dispatchOrder?->freight_terms ?? $salesOrder?->freight_terms ?? 'To Pay') == 'To Pay')>To Pay (Freight Collect by Driver)</option>
                            <option value="To Be Billed" @selected(old('freight_terms', $dispatchOrder?->freight_terms ?? $salesOrder?->freight_terms ?? '') == 'To Be Billed')>To Be Billed (Prepaid & Added to Invoice)</option>
                            <option value="Prepaid" @selected(old('freight_terms', $dispatchOrder?->freight_terms ?? $salesOrder?->freight_terms ?? '') == 'Prepaid')>Prepaid (Freight Included / Seller Paid)</option>
                            <option value="Customer Pickup" @selected(old('freight_terms', $dispatchOrder?->freight_terms ?? $salesOrder?->freight_terms ?? '') == 'Customer Pickup')>Customer Pickup (Self Vehicle)</option>
                        </x-ui.odoo-form-ui>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" inputType="number" label="Freight Amount (₹)" name="freight_amount" id="invFreightAmountInput" :value="old('freight_amount', $dispatchOrder?->freight_amount ?? $salesOrder?->freight_amount ?? 0)" min="0" step="0.01" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" label="Freight GST Tax Rate" name="freight_tax_rate" id="invFreightTaxRateSelect">
                                    <option value="highest" @selected(old('freight_tax_rate', $salesOrder?->freight_tax_rate ?? 'highest') === 'highest')>Highest Item Rate (Auto)</option>
                                    <option value="18" @selected(old('freight_tax_rate', $salesOrder?->freight_tax_rate) === '18')>18% GST</option>
                                    <option value="12" @selected(old('freight_tax_rate', $salesOrder?->freight_tax_rate) === '12')>12% GST</option>
                                    <option value="5" @selected(old('freight_tax_rate', $salesOrder?->freight_tax_rate) === '5')>5% GST</option>
                                    <option value="28" @selected(old('freight_tax_rate', $salesOrder?->freight_tax_rate) === '28')>28% GST</option>
                                    <option value="0" @selected(old('freight_tax_rate', $salesOrder?->freight_tax_rate) === '0')>0% (Exempt / Nil)</option>
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Items Table (Matching PO Component Design) -->
                <div class="mt-5">
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                        <h5 class="fw-bold text-dark mb-0 fs-14"><i class="feather-layers text-primary me-2"></i>Invoice Line Items</h5>
                        <div class="d-flex align-items-center gap-2" style="width: 420px;">
                            <div class="input-group input-group-sm shadow-2xs rounded overflow-hidden" style="border: 1px solid #cbd5e1 !important;">
                                <span class="input-group-text bg-primary text-white border-0 px-3 fw-semibold"><i class="feather-camera me-1"></i> Barcode</span>
                                <input type="text" id="fastBarcodeScanInput" class="form-control border-0 bg-white" placeholder="Scan Barcode / SKU (Press Enter)..." autocomplete="off" style="font-size: 13px;">
                                <button type="button" class="btn btn-primary border-0 px-3" id="fastBarcodeScanBtn"><i class="feather-search"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table" id="invoiceItemsTable">
                            <thead>
                                <tr>
                                    <th style="width: 32%;">Product <span class="text-danger">*</span></th>
                                    <th class="text-end" style="width: 10%;">Qty <span class="text-danger">*</span></th>
                                    <th class="text-end" style="width: 12%;">Rate <span class="text-danger">*</span></th>
                                    <th class="text-end" style="width: 12%;">Amount</th>
                                    <th class="text-end discount-column" style="width: 10%;">Disc (₹)</th>
                                    <th class="text-end tax-column" style="width: 10%;">Tax Rate (%)</th>
                                    <th class="text-end pe-3" style="width: 14%;">Total Amount</th>
                                    <th style="width: 3%;"></th>
                                </tr>
                            </thead>
                            <tbody class="fs-13 text-dark">
                                @forelse ($invoiceItems as $index => $item)
                                    <tr class="item-row" data-index="{{ $index }}">
                                        <td>
                                            @if ($mode === 'direct')
                                                <x-ui.odoo-form-ui type="select" name="items[{{ $index }}][product_id]" class="product-select odoo-select2" :required="true">
                                                    <option value="">Select Product...</option>
                                                    @foreach ($products as $p)
                                                        <option value="{{ $p->id }}" data-cost="{{ $p->selling_price ?? 0 }}" data-name="{{ $p->name }}" @selected($p->id == $item['product_id'])>
                                                            {{ $p->name }} {{ $p->sku ? '('.$p->sku.')' : '' }}
                                                        </option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>
                                                <input type="hidden" name="items[{{ $index }}][item_name]" class="item-name-input" value="{{ $item['product_name'] }}">
                                            @else
                                                <strong class="text-dark d-block mb-1">{{ $item['product_name'] }}</strong>
                                                @if($item['sku'])
                                                    <small class="text-muted d-block mt-0.5">SKU: {{ $item['sku'] }}</small>
                                                @endif
                                                <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $item['product_id'] }}">
                                                <input type="hidden" name="items[{{ $index }}][item_name]" value="{{ $item['product_name'] }}">
                                                <input type="hidden" name="items[{{ $index }}][sales_order_item_id]" value="{{ $item['sales_order_item_id'] }}">
                                                <input type="hidden" name="items[{{ $index }}][material_requirement_item_id]" value="{{ $item['material_requirement_item_id'] }}">
                                            @endif
                                        </td>
                                        <td>
                                            <x-ui.odoo-form-ui type="input" inputType="number" name="items[{{ $index }}][quantity]" class="text-end qty-input" step="0.0001" min="0.0001" :required="true" :value="(float)$item['quantity']" />
                                        </td>
                                        <td>
                                            <x-ui.odoo-form-ui type="input" inputType="number" name="items[{{ $index }}][unit_price]" class="text-end rate-input" step="0.01" min="0" :required="true" :value="(float)$item['unit_price']" />
                                        </td>
                                        <td>
                                            <x-ui.odoo-form-ui type="input" inputType="number" name="items[{{ $index }}][amount]" class="text-end amount-input" step="0.01" min="0" readonly="true" :value="$item['quantity'] * $item['unit_price']" />
                                        </td>
                                        <td class="discount-column">
                                            <x-ui.odoo-form-ui type="input" inputType="number" name="items[{{ $index }}][discount]" class="text-end disc-input" step="0.01" min="0" :value="(float)$item['discount']" />
                                        </td>
                                        <td class="tax-column">
                                            <x-ui.odoo-form-ui type="input" inputType="number" name="items[{{ $index }}][tax_rate]" class="text-end tax-input" step="0.01" min="0" max="100" :value="(float)$item['tax_rate']" />
                                        </td>
                                        <td>
                                            <x-ui.odoo-form-ui type="input" inputType="number" name="items[{{ $index }}][total_amount]" class="text-end total-amount-input" step="0.01" readonly="true" :value="$item['total_amount']" />
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-link text-danger remove-row-btn p-1 border-0"><i class="feather-trash-2 fs-14"></i></button>
                                        </td>
                                    </tr>
                                @empty
                                    @if ($mode !== 'direct')
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4 fs-12">
                                                <i class="feather-info me-1 text-primary"></i>Please select a {{ $mode === 'dispatch_order' ? 'Dispatch Order' : 'Sales Order' }} from the dropdown above to populate line items.
                                            </td>
                                        </tr>
                                    @endif
                                @endforelse
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>

                    @if ($mode === 'direct')
                        <div class="mt-3">
                            <button type="button" class="btn btn-sm btn-soft-primary px-3 fw-bold" id="addDirectRowBtn">
                                <i class="feather-plus me-1"></i> Add Line
                            </button>
                        </div>
                    @endif
                </div>

                <!-- Bottom Details & Totals Summary (PO Card Style) -->
                <div class="row mt-5 pt-3 border-top g-4">
                    <div class="col-md-7">
                        <!-- GST Rate Breakdown Card (Spacious Left Alignment) -->
                        <div class="card border-0 shadow-sm mb-3 w-100" id="gstBreakdownContainer" style="border-radius: 8px; border: 1px solid #cbd5e1 !important; overflow: hidden; display: none;">
                            <div class="fw-bold py-2 px-3 bg-light border-bottom text-dark fs-12 text-uppercase d-flex justify-content-between align-items-center">
                                <span><i class="feather-pie-chart text-primary me-1"></i>GST Tax Summary</span>
                                <span class="badge bg-soft-primary text-primary fs-11" id="gstBreakdownModeBadge">CGST + SGST</span>
                            </div>
                            <div class="p-0 table-responsive" style="overflow-x: visible;">
                                <table class="table table-sm table-bordered mb-0 align-middle fs-12 text-center w-100">
                                    <thead class="bg-light text-muted fw-bold">
                                        <tr id="gstBreakdownHeader">
                                            <!-- Dynamic Header -->
                                        </tr>
                                    </thead>
                                    <tbody id="gstBreakdownBody" class="fs-12 text-dark">
                                        <!-- Dynamic Grouped Rows -->
                                    </tbody>
                                    <tfoot class="bg-light fw-bold text-dark border-top" id="gstBreakdownFooter">
                                        <!-- Dynamic Footers -->
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <x-ui.odoo-form-ui type="editor" label="Invoice Notes / Terms & Conditions" name="notes" editorHeight="ht-150">{!! old('notes') !!}</x-ui.odoo-form-ui>
                    </div>

                    <!-- Right Side: Order Summary Card -->
                    <div class="col-md-5 d-flex flex-column align-items-end fs-13">
                        <div class="card shadow-sm border-0 rounded-3 overflow-hidden w-100" style="border: 1px solid #cbd5e1 !important;">
                            <div class="fw-bold py-2.5 px-3 text-white" style="background-color: #2563eb; font-size: 12px; letter-spacing: 0.5px; text-transform: uppercase;">
                                FINANCIAL SUMMARY
                            </div>
                            <div class="p-3 bg-white text-dark">
                                
                                <!-- 1. Subtotal (Excl. Tax) -->
                                <div class="d-flex justify-content-between align-items-center mb-3" id="summarySubtotalRow">
                                    <span class="text-muted fs-13 fw-semibold">Subtotal (Excl. Tax):</span>
                                    <input type="text" id="calcSubtotal" class="form-control form-control-sm text-end fw-bold" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; color: #334155; background-color: #f8fafc;" readonly value="₹0.00">
                                </div>

                                <!-- 2. Less: Item Discounts -->
                                <div class="d-flex justify-content-between align-items-center mb-3 d-none" id="summaryDiscountRow">
                                    <span class="text-muted fs-13 fw-semibold" id="summaryDiscountLabel">Less: Item Discounts:</span>
                                    <input type="text" id="calcDiscountDisplay" class="form-control form-control-sm text-end fw-bold text-danger" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" readonly value="-₹0.00">
                                    <input type="number" name="discount_amount" id="summaryDiscount" class="form-control form-control-sm text-end fw-bold text-danger d-none" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px;" value="{{ old('discount_amount', $salesOrder?->discount ?: 0) }}" step="0.01">
                                </div>

                                <!-- 3. Items Taxable Value -->
                                <div class="d-flex justify-content-between align-items-center mb-3" id="calcTaxableRow">
                                    <span class="text-muted fs-13 fw-semibold">Items Taxable Value:</span>
                                    <input type="text" id="calcTaxableAmount" class="form-control form-control-sm text-end fw-bold" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; color: #334155; background-color: #f8fafc;" readonly value="₹0.00">
                                </div>

                                <!-- 4. Order Tax Rate (%) -->
                                <div class="d-flex justify-content-between align-items-center mb-3 d-none" id="summaryOrderTaxRow">
                                    <span class="text-muted fs-13 fw-semibold">Order Tax Rate (%):</span>
                                    <input type="number" name="order_tax_rate" id="orderTaxPercent" class="form-control form-control-sm text-end fw-bold" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px;" value="{{ old('order_tax_rate', $salesOrder?->order_tax_rate ?: 18) }}" min="0" max="100" step="0.01">
                                </div>

                                <!-- 5. Add: Items GST Tax -->
                                <div class="d-flex justify-content-between align-items-center mb-3" id="summaryItemsTaxRow">
                                    <span class="text-muted fs-13 fw-semibold">Add: Items GST Tax:</span>
                                    <input type="text" id="summaryItemsTaxText" class="form-control form-control-sm text-end font-monospace text-muted" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" readonly value="+₹0.00">
                                </div>

                                <!-- 6. Billed Items Total (Incl. GST) -->
                                <div class="d-flex justify-content-between align-items-center mb-3 fw-bold text-dark" id="calcItemsTotalRow">
                                    <span class="fs-13">Billed Items Total (Incl. GST):</span>
                                    <input type="text" id="calcItemsTotalInclGst" class="form-control form-control-sm text-end fw-bold text-dark" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f1f5f9;" readonly value="₹0.00">
                                </div>

                                <!-- FREIGHT BREAKDOWN SECTION -->
                                <div id="summaryFreightSectionContainer">
                                    <hr class="my-2 border-slate">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="text-muted fs-13 fw-semibold">Freight Charges:</span>
                                        <input type="number" id="summaryFreightText" class="form-control form-control-sm text-end fw-bold text-primary" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" min="0" step="0.01" value="{{ old('freight_amount', $dispatchOrder?->freight_amount ?? $salesOrder?->freight_amount ?? 0) }}">
                                        <input type="hidden" name="freight_amount" id="invFreightAmountInput" value="{{ old('freight_amount', $dispatchOrder?->freight_amount ?? $salesOrder?->freight_amount ?? 0) }}">
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-3" id="summaryFreightTaxRow">
                                        <span class="text-muted fs-13 fw-semibold" id="summaryFreightTaxLabel">Add: Freight GST Tax:</span>
                                        <input type="text" id="summaryFreightTaxText" class="form-control form-control-sm text-end font-monospace text-muted" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" readonly value="+₹0.00">
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-3 fw-bold text-dark" id="summaryFreightTotalRow">
                                        <span class="fs-13">Total Freight (Incl. GST):</span>
                                        <input type="text" id="summaryFreightTotalText" class="form-control form-control-sm text-end fw-bold text-primary" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #eff6ff;" readonly value="₹0.00">
                                    </div>
                                </div>

                                <!-- OVERALL TAX BREAKDOWN -->
                                <hr class="my-2 border-slate">
                                <div id="cgstSgstRows">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="text-muted fs-13 fw-semibold">CGST (Central Tax):</span>
                                        <input type="text" id="summaryCgstText" class="form-control form-control-sm text-end font-monospace text-muted" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" readonly value="+₹0.00">
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="text-muted fs-13 fw-semibold">SGST (State Tax):</span>
                                        <input type="text" id="summarySgstText" class="form-control form-control-sm text-end font-monospace text-muted" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" readonly value="+₹0.00">
                                    </div>
                                </div>

                                <div id="igstRow" style="display: none;">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="text-muted fs-13 fw-semibold">IGST (Integrated Tax):</span>
                                        <input type="text" id="summaryIgstText" class="form-control form-control-sm text-end font-monospace text-muted" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" readonly value="+₹0.00">
                                    </div>
                                </div>

                                <!-- 9. Adjustment -->
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted fs-13 fw-semibold">Adjustment:</span>
                                    <input type="number" name="adjustment" id="adjustmentInput" class="form-control form-control-sm text-end fw-bold" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px;" value="{{ old('adjustment', $salesOrder?->adjustment ?: 0) }}" step="0.01">
                                </div>

                                <!-- 10. Grand Total -->
                                <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-3">
                                    <span class="fw-bold text-primary fs-13">Grand Total:</span>
                                    <input type="text" id="summaryGrandtotalText" class="form-control form-control-sm text-end fw-bold" style="width: 150px; height: 36px; border: 1.5px solid #2563eb; border-radius: 4px; background-color: #eff6ff; color: #2563eb; font-size: 14px; font-weight: 800;" readonly value="₹0.00">
                                </div>

                                @if ($advanceAllocations > 0)
                                    <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top text-info">
                                        <span class="fs-12 fw-semibold">Advance Adjusted</span>
                                        <span class="fw-bold">-₹{{ number_format($advanceAllocations, 2) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-1 pt-1 text-success">
                                        <span class="fw-bold fs-13">Balance Due</span>
                                        <span class="fw-extrabold fs-14" id="summaryBalanceDueText">₹0.00</span>
                                    </div>
                                @endif

                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <x-ui.button href="{{ route('sales.invoices.index') }}" variant="light" size="md" class="border py-2 px-4 fs-12 shadow-sm">Discard</x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="md" class="py-2 px-5 fw-bold fs-12 shadow-sm" style="background-color: #1e40af; border-color: #1e40af;">Generate and Save Invoice</x-ui.button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    @php
        $productsData = $products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'price' => $p->selling_price ?? 0])->values();
    @endphp
    <script>
        $(function () {
            const productsList = @json($productsData);
            let directRowIndex = {{ count($invoiceItems) }};
            const advanceAllocated = {{ (float)$advanceAllocations }};

            // Initialize Odoo Select2 (PO style)
            function initSelect2(context) {
                $(context).find('.odoo-select2').select2({
                    theme: 'bootstrap-5',
                    width: '100%'
                });
            }
            initSelect2(document);

            // Toggle Columns & Summary Visibility (PO Logic)
            function toggleTaxAndDiscountDisplay() {
                const discountType = $('#discountTypeSelect').val() || 'item_wise';
                const taxType      = $('#taxTypeSelect').val() || 'item_wise_tax';

                if (discountType === 'item_wise') {
                    $('.discount-column').removeClass('d-none').show();
                    $('#summaryDiscountRow').removeClass('d-none').show();
                    $('#calcDiscountDisplay').removeClass('d-none').show();
                    $('#summaryDiscount').addClass('d-none').hide();
                } else if (discountType === 'order_wise') {
                    $('.discount-column').addClass('d-none').hide();
                    $('#summaryDiscountRow').removeClass('d-none').show();
                    $('#calcDiscountDisplay').addClass('d-none').hide();
                    $('#summaryDiscount').removeClass('d-none').show();
                } else { // without_discount
                    $('.discount-column').addClass('d-none').hide();
                    $('#summaryDiscountRow').addClass('d-none').hide();
                    $('#summaryDiscount').val('0.00');
                    $('#calcDiscountDisplay').val('-₹0.00');
                }

                if (taxType === 'item_wise_tax') {
                    $('.tax-column').removeClass('d-none').show();
                    $('#summaryOrderTaxRow').addClass('d-none').hide();
                    $('#calcTaxableRow').removeClass('d-none').show();
                } else if (taxType === 'order_wise_tax') {
                    $('.tax-column').addClass('d-none').hide();
                    $('#summaryOrderTaxRow').removeClass('d-none').show();
                    $('#calcTaxableRow').removeClass('d-none').show();
                } else { // without_tax
                    $('.tax-column').addClass('d-none').hide();
                    $('#summaryOrderTaxRow').addClass('d-none').hide();
                    $('#calcTaxableRow').addClass('d-none').hide();
                }

                recalculateInvoiceTotals();
            }

            $('#discountTypeSelect, #taxTypeSelect, #gstTypeSelect, #orderTaxPercent').on('change input', function() {
                toggleTaxAndDiscountDisplay();
            });

            // Mode radio buttons
            $('.mode-radio').on('change', function() {
                window.location.href = "{{ route('sales.invoices.create') }}?mode=" + $(this).val();
            });

            // Sales Order Selection Switch
            $('#salesOrderSelect').on('change', function() {
                const soId = $(this).val();
                if (soId) {
                    window.location.href = "{{ route('sales.invoices.create') }}?mode=sales_order&sales_order_id=" + soId;
                }
            });

            // Dispatch Order Selection Switch
            $('#dispatchOrderSelect').on('change', function() {
                const doId = $(this).val();
                if (doId) {
                    window.location.href = "{{ route('sales.invoices.create') }}?mode=dispatch_order&dispatch_order_id=" + doId;
                }
            });

            // Add Direct Product Line (PO Component Style)
            $('#addDirectRowBtn').on('click', function() {
                let productOptions = '<option value="">Select Product...</option>';
                productsList.forEach(p => {
                    productOptions += `<option value="${p.id}" data-cost="${p.price}" data-name="${p.name}">${p.name} ${p.sku ? '('+p.sku+')' : ''}</option>`;
                });

                const rowHtml = `
                    <tr class="item-row" data-index="${directRowIndex}">
                        <td>
                            <x-ui.odoo-form-ui type="select" name="items[${directRowIndex}][product_id]" class="product-select odoo-select2" required="true">
                                ${productOptions}
                            </x-ui.odoo-form-ui>
                            <input type="hidden" name="items[${directRowIndex}][item_name]" class="item-name-input" value="">
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="input" inputType="number" name="items[${directRowIndex}][quantity]" class="text-end qty-input" step="0.0001" min="0.0001" required="true" value="1" />
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="input" inputType="number" name="items[${directRowIndex}][unit_price]" class="text-end rate-input" step="0.01" min="0" required="true" value="0.00" />
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="input" inputType="number" name="items[${directRowIndex}][amount]" class="text-end amount-input" step="0.01" min="0" readonly="true" value="0.00" />
                        </td>
                        <td class="discount-column">
                            <x-ui.odoo-form-ui type="input" inputType="number" name="items[${directRowIndex}][discount]" class="text-end disc-input" step="0.01" min="0" value="0.00" />
                        </td>
                        <td class="tax-column">
                            <x-ui.odoo-form-ui type="input" inputType="number" name="items[${directRowIndex}][tax_rate]" class="text-end tax-input" step="0.01" min="0" max="100" value="18.00" />
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="input" inputType="number" name="items[${directRowIndex}][total_amount]" class="text-end total-amount-input" step="0.01" readonly="true" value="0.00" />
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-link text-danger remove-row-btn p-1 border-0"><i class="feather-trash-2 fs-14"></i></button>
                        </td>
                    </tr>
                `;
                const $newRow = $(rowHtml);
                $('#invoiceItemsTable tbody').append($newRow);
                initSelect2($newRow);
                directRowIndex++;
                toggleTaxAndDiscountDisplay();
            });

            // Product select change for price auto-fill
            $(document).on('change', '.product-select', function() {
                const opt = $(this).find('option:selected');
                const row = $(this).closest('tr');
                row.find('.rate-input').val(opt.data('cost') || opt.data('price') || 0);
                row.find('.item-name-input').val(opt.data('name') || '');
                recalculateInvoiceTotals();
            });

            // Event listeners for recalculating
            $(document).on('input change', '.qty-input, .rate-input, .disc-input, .tax-input, #summaryDiscount, #orderTaxPercent, #invFreightTermsSelect, #invFreightTaxRateSelect, #summaryFreightText, #adjustmentInput', function() {
                recalculateInvoiceTotals();
            });

            $(document).on('click', '.remove-row-btn', function() {
                $(this).closest('tr').remove();
                recalculateInvoiceTotals();
            });

            function recalculateInvoiceTotals() {
                const discountType = $('#discountTypeSelect').val() || 'item_wise';
                const taxType      = $('#taxTypeSelect').val() || 'item_wise_tax';
                const gstType      = $('#gstTypeSelect').val() || 'cgst_sgst';

                let subtotal = 0;
                let itemTaxTotal = 0;
                let itemDiscTotal = 0;
                let maxItemTaxRate = 0;

                $('#invoiceItemsTable tbody tr.item-row').each(function() {
                    const q = parseFloat($(this).find('.qty-input').val()) || 0;
                    const p = parseFloat($(this).find('.rate-input').val()) || 0;
                    const d = (discountType === 'item_wise') ? (parseFloat($(this).find('.disc-input').val()) || 0) : 0;
                    const t = (taxType === 'item_wise_tax') ? (parseFloat($(this).find('.tax-input').val()) || 0) : 0;

                    if (t > maxItemTaxRate) {
                        maxItemTaxRate = t;
                    }

                    const lineAmount = q * p;
                    const lineTaxable = Math.max(0, lineAmount - d);
                    const lineTax = lineTaxable * (t / 100);
                    const lineTotal = lineTaxable + lineTax;

                    subtotal += lineAmount;
                    itemDiscTotal += d;
                    itemTaxTotal += lineTax;

                    $(this).find('.amount-input').val(lineAmount.toFixed(2));
                    $(this).find('.total-amount-input').val(lineTotal.toFixed(2));
                });

                let totalDiscount = 0;
                if (discountType === 'item_wise') {
                    totalDiscount = itemDiscTotal;
                    $('#summaryDiscount').val(totalDiscount.toFixed(2));
                    $('#calcDiscountDisplay').val('-₹' + totalDiscount.toFixed(2));
                } else if (discountType === 'order_wise') {
                    totalDiscount = parseFloat($('#summaryDiscount').val()) || 0;
                    $('#calcDiscountDisplay').val('-₹' + totalDiscount.toFixed(2));
                }

                const grossBeforeTax = Math.max(0, subtotal - totalDiscount);

                let totalTax = 0;
                if (taxType === 'item_wise_tax') {
                    totalTax = itemTaxTotal;
                } else if (taxType === 'order_wise_tax') {
                    const taxPercent = parseFloat($('#orderTaxPercent').val()) || 0;
                    totalTax = grossBeforeTax * (taxPercent / 100);
                }

                const itemsTaxTotal = totalTax;

                const freightTerms = $('#invFreightTermsSelect').val() || 'To Pay';
                const summaryFreight = parseFloat($('#summaryFreightText').val()) || 0;
                const effectiveFreight = (freightTerms === 'To Be Billed') ? summaryFreight : 0;
                $('#invFreightAmountInput').val(effectiveFreight);

                const freightTaxRateOption = $('#invFreightTaxRateSelect').val() || 'highest';
                let freightTaxRate = 18;
                if (freightTaxRateOption === 'highest') {
                    if (taxType === 'order_wise_tax') {
                        freightTaxRate = parseFloat($('#orderTaxPercent').val()) || 18;
                    } else {
                        freightTaxRate = (maxItemTaxRate > 0) ? maxItemTaxRate : 18;
                    }
                } else {
                    freightTaxRate = parseFloat(freightTaxRateOption) || 0;
                }

                const freightTax = (effectiveFreight > 0 && taxType !== 'without_tax') ? Math.round(effectiveFreight * (freightTaxRate / 100) * 100) / 100 : 0;
                const totalFreightInclGst = effectiveFreight + freightTax;
                const grandTotalTax = itemsTaxTotal + freightTax;

                $('#summaryItemsTaxText').val('+₹' + itemsTaxTotal.toFixed(2));
                $('#summaryFreightTaxText').val('+₹' + freightTax.toFixed(2));
                $('#summaryFreightTotalText').val('₹' + totalFreightInclGst.toFixed(2));

                if (taxType !== 'without_tax' && grandTotalTax > 0) {
                    if (gstType === 'igst') {
                        $('#cgstSgstRows').addClass('d-none').hide();
                        $('#igstRow').removeClass('d-none').show();
                        $('#summaryIgstText').val('+₹' + grandTotalTax.toFixed(2));
                    } else {
                        $('#cgstSgstRows').removeClass('d-none').show();
                        $('#igstRow').addClass('d-none').hide();
                        const halfTax = grandTotalTax / 2;
                        $('#summaryCgstText').val('+₹' + halfTax.toFixed(2));
                        $('#summarySgstText').val('+₹' + halfTax.toFixed(2));
                    }
                } else {
                    $('#cgstSgstRows').addClass('d-none').hide();
                    $('#igstRow').addClass('d-none').hide();
                }

                const itemsTotalInclGst = grossBeforeTax + itemsTaxTotal;
                const adjustment = parseFloat($('#adjustmentInput').val()) || 0;
                const grandTotal = itemsTotalInclGst + totalFreightInclGst + adjustment;
                const balanceDue = Math.max(0, grandTotal - advanceAllocated);

                $('#calcSubtotal').val('₹' + subtotal.toFixed(2));
                $('#calcTaxableAmount').val('₹' + grossBeforeTax.toFixed(2));
                $('#calcItemsTotalInclGst').val('₹' + itemsTotalInclGst.toFixed(2));
                $('#summaryGrandtotalText').val('₹' + grandTotal.toFixed(2));
                $('#summaryBalanceDueText').text('₹' + balanceDue.toFixed(2));

                // Build Tally-Style Tax Rate Breakdown Analysis Table
                const taxGroups = {};
                if (taxType === 'item_wise_tax') {
                    $('#invoiceItemsTable tbody tr.item-row').each(function() {
                        const q = parseFloat($(this).find('.qty-input').val()) || 0;
                        const p = parseFloat($(this).find('.rate-input').val()) || 0;
                        const d = (discountType === 'item_wise') ? (parseFloat($(this).find('.disc-input').val()) || 0) : 0;
                        const t = parseFloat($(this).find('.tax-input').val()) || 0;

                        const lineAmount = q * p;
                        const lineTaxable = Math.max(0, lineAmount - d);
                        const lineTax = lineTaxable * (t / 100);

                        const key = t.toFixed(2);
                        if (!taxGroups[key]) {
                            taxGroups[key] = { rate: t, taxable: 0, tax: 0 };
                        }
                        taxGroups[key].taxable += lineTaxable;
                        taxGroups[key].tax += lineTax;
                    });

                    if (effectiveFreight > 0 && freightTax > 0) {
                        const fKey = freightTaxRate.toFixed(2);
                        if (!taxGroups[fKey]) {
                            taxGroups[fKey] = { rate: freightTaxRate, taxable: 0, tax: 0 };
                        }
                        taxGroups[fKey].taxable += effectiveFreight;
                        taxGroups[fKey].tax += freightTax;
                    }
                }

                const taxRates = Object.keys(taxGroups).sort((a, b) => parseFloat(a) - parseFloat(b));
                if (taxType === 'item_wise_tax' && taxRates.length > 0) {
                    $('#gstBreakdownContainer').show();
                    $('#gstBreakdownModeBadge').text(gstType === 'igst' ? 'IGST (Inter-State)' : 'CGST + SGST (Intra-State)');

                    let headerHtml = '';
                    let bodyHtml = '';
                    let footerHtml = '';

                    let totalTaxableVal = 0;
                    let totalCgstVal = 0;
                    let totalSgstVal = 0;
                    let totalIgstVal = 0;
                    let totalTaxVal = 0;

                    if (gstType === 'igst') {
                        headerHtml = `
                            <th class="py-1">Tax Rate</th>
                            <th class="py-1 text-end">Total Tax</th>
                            <th class="py-1 text-end">IGST Amt</th>
                        `;

                        taxRates.forEach(key => {
                            const grp = taxGroups[key];
                            const igstAmt = grp.tax;
                            totalIgstVal += igstAmt;
                            totalTaxVal += grp.tax;

                            bodyHtml += `
                                <tr>
                                    <td class="py-1 fw-bold">GST ${parseFloat(grp.rate)}%</td>
                                    <td class="py-1 text-end fw-bold text-dark">₹${grp.tax.toFixed(2)}</td>
                                    <td class="py-1 text-end">₹${igstAmt.toFixed(2)}</td>
                                </tr>
                            `;
                        });

                        footerHtml = `
                            <tr>
                                <td class="py-1">Total</td>
                                <td class="py-1 text-end text-primary">₹${totalTaxVal.toFixed(2)}</td>
                                <td class="py-1 text-end">₹${totalIgstVal.toFixed(2)}</td>
                            </tr>
                        `;
                    } else {
                        headerHtml = `
                            <th class="py-1">Tax Rate</th>
                            <th class="py-1 text-end">Total Tax</th>
                            <th class="py-1 text-end">CGST Amt</th>
                            <th class="py-1 text-end">SGST Amt</th>
                        `;

                        taxRates.forEach(key => {
                            const grp = taxGroups[key];
                            const cgstAmt = Math.round((grp.tax / 2) * 100) / 100;
                            const sgstAmt = Math.round((grp.tax - cgstAmt) * 100) / 100;

                            totalCgstVal += cgstAmt;
                            totalSgstVal += sgstAmt;
                            totalTaxVal += grp.tax;

                            bodyHtml += `
                                <tr>
                                    <td class="py-1 fw-bold">GST ${parseFloat(grp.rate)}%</td>
                                    <td class="py-1 text-end fw-bold text-dark">₹${grp.tax.toFixed(2)}</td>
                                    <td class="py-1 text-end">₹${cgstAmt.toFixed(2)}</td>
                                    <td class="py-1 text-end">₹${sgstAmt.toFixed(2)}</td>
                                </tr>
                            `;
                        });

                        footerHtml = `
                            <tr>
                                <td class="py-1">Total</td>
                                <td class="py-1 text-end text-primary">₹${totalTaxVal.toFixed(2)}</td>
                                <td class="py-1 text-end">₹${totalCgstVal.toFixed(2)}</td>
                                <td class="py-1 text-end">₹${totalSgstVal.toFixed(2)}</td>
                            </tr>
                        `;
                    }

                    $('#gstBreakdownHeader').html(headerHtml);
                    $('#gstBreakdownBody').html(bodyHtml);
                    $('#gstBreakdownFooter').html(footerHtml);
                } else {
                    $('#gstBreakdownContainer').hide();
                }
            }

            // Initial calculation
            toggleTaxAndDiscountDisplay();

            // Auto add 1 row on Direct Mode if table empty
            if ("{{ $mode }}" === "direct" && $('#invoiceItemsTable tbody tr.item-row').length === 0) {
                $('#addDirectRowBtn').click();
            }

            // Barcode Scanner Fast Auto-Fill
            function handleBarcodeScan() {
                const input = $('#fastBarcodeScanInput');
                const code = input.val().trim();
                if (!code) return;

                $.ajax({
                    url: "{{ route('inventory.products.barcodeLookup') }}",
                    data: { code: code },
                    success: function(res) {
                        if (res.success && res.product) {
                            const prod = res.product;
                            let targetRow = null;
                            let isNewRow = false;

                            // 1. Look for existing row with SAME product
                            $('#invoiceItemsTable tbody tr.item-row').each(function() {
                                const sel = $(this).find('.product-select');
                                if (sel.length && sel.val() == prod.id) {
                                    targetRow = $(this);
                                    return false;
                                }
                            });

                            // 2. If no matching row found, look for empty row
                            if (!targetRow) {
                                $('#invoiceItemsTable tbody tr.item-row').each(function() {
                                    const sel = $(this).find('.product-select');
                                    if (sel.length && (!sel.val() || sel.val() === '')) {
                                        targetRow = $(this);
                                        isNewRow = true;
                                        return false;
                                    }
                                });
                            }

                            // 3. If no empty row found, create new row
                            if (!targetRow) {
                                $('#addDirectRowBtn').click();
                                targetRow = $('#invoiceItemsTable tbody tr.item-row').last();
                                isNewRow = true;
                            }

                            const selectEl = targetRow.find('.product-select');
                            if (selectEl.length) {
                                if (!selectEl.find(`option[value="${prod.id}"]`).length) {
                                    selectEl.append(`<option value="${prod.id}" data-cost="${prod.selling_price || prod.cost_price}" data-name="${prod.name}">${prod.name} (${prod.sku})</option>`);
                                }
                                if (selectEl.val() != prod.id) {
                                    selectEl.val(prod.id).trigger('change');
                                }
                                targetRow.find('.rate-input').val(prod.selling_price || prod.cost_price || prod.unit_cost);
                                targetRow.find('.tax-input').val(prod.gst_rate || 18);

                                // Qty increment or initial set
                                const qtyInput = targetRow.find('.qty-input');
                                if (qtyInput.length) {
                                    const currentQty = parseFloat(qtyInput.val()) || 0;
                                    if (!isNewRow && currentQty > 0) {
                                        qtyInput.val(currentQty + 1).trigger('input').trigger('change');
                                    } else {
                                        if (currentQty === 0) {
                                            qtyInput.val(1).trigger('input').trigger('change');
                                        }
                                    }
                                }

                                recalculateInvoiceTotals();
                            }
                            input.val('');
                        }
                    },
                    error: function(err) {
                        alert('Product Not Found for scanned code: ' + code);
                        input.val('');
                    }
                });
            }

            $('#fastBarcodeScanInput').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    handleBarcodeScan();
                }
            });

            $('#fastBarcodeScanBtn').on('click', function(e) {
                e.preventDefault();
                handleBarcodeScan();
            });
        });
    </script>
@endpush
