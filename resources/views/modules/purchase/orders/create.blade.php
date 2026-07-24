@extends('layouts.duralux')

@section('title', 'Create Purchase Order | SaaS ERP')
@section('page-title', 'New Purchase Order')
@section('breadcrumb')
    <a href="{{ route('purchase.orders.index') }}">Purchase Orders</a> &gt; Create
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .odoo-sheet {
            background: #ffffff;
            border-radius: 4px;
        }

        /* Prevent Select2 container from causing overflow in table cells */
        .select2-container {
            width: 100% !important;
            max-width: 100% !important;
        }

        /* Fix scrollbar by enforcing fixed layout that fits container width */
        #poItemsTable {
            table-layout: fixed;
            width: 100% !important;
        }

        /* Hide horizontal scrollbar on desktops when space is sufficient */
        @media (min-width: 992px) {
            .table-responsive {
                overflow-x: hidden !important;
            }
        }
        
        .tax-column, .discount-column {
            display: none;
        }
        .summary-input-group {
            width: 180px;
        }
    </style>
@endpush

@section('content')
    @php
        $currency = tenant()?->settings['currency'] ?? 'INR';
    @endphp
    <div class="row text-dark">
        <div class="col-12">
            <div class="card border-0 shadow-sm p-4 p-md-5 bg-white">
                <form action="{{ route('purchase.orders.store') }}" method="POST" id="createPoForm" class="odoo-sheet">
                    @csrf
                    @foreach($requisitionItemIds ?? [] as $itemId)
                        <input type="hidden" name="requisition_item_ids[]" value="{{ $itemId }}">
                    @endforeach

                    <!-- Actions Top bar -->
                    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom flex-wrap gap-2">
                        <div>
                            <h4 class="fw-bold text-dark mb-0">Create Purchase Order</h4>
                            <small class="text-muted fs-12">Create a new purchase order directly or source it from an approved purchase request.</small>
                        </div>
                        <div class="d-flex gap-2">
                            <x-ui.button href="{{ route('purchase.orders.index') }}" variant="light" size="sm">
                                Cancel
                            </x-ui.button>
                            <x-ui.button type="submit" variant="primary" size="sm" icon="feather-save" style="background-color: #714B67; border-color: #714B67;">
                                Save Draft PO
                            </x-ui.button>
                        </div>
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="row g-4 fs-13 text-dark">
                        <!-- Left Panel: Supplier & Location details -->
                        <div class="col-md-6 border-end">
                            <h6 class="fw-bold text-primary mb-3">Supplier & Location Details</h6>

                            <x-ui.odoo-form-ui type="select" label="Location / Warehouse" name="location" id="locationSelect" required="true">
                                <option value="">Select Warehouse...</option>
                                @foreach($warehouses as $w)
                                    <option value="{{ $w->name }}" @selected(old('location', request('location')) == $w->name || request('warehouse_id') == $w->id)>{{ $w->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="select" label="Supplier Name" name="vendor_id" id="vendorSelect" required="true">
                                <option value="">Select Supplier...</option>
                                @foreach($vendors as $v)
                                    <option value="{{ $v->id }}" 
                                        data-code="{{ $v->code }}" 
                                        data-phone="{{ $v->phone }}" 
                                        data-email="{{ $v->email }}"
                                        data-address="{{ $v->address }}"
                                        @selected(old('vendor_id', request('vendor_id')) == $v->id)>
                                        {{ $v->name }} {{ $v->code ? '('.$v->code.')' : '' }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>


                            <x-ui.odoo-form-ui type="input" label="Purchase Order No." name="po_number_dummy" value="[Auto-Generated]" readonly="true" />



                            <x-ui.odoo-form-ui type="select" label="Load from PR (Indent)" name="purchase_requisition_id" id="requisitionSelect">
                                <option value="">-- Direct PO (No PR link) --</option>
                                @foreach($requisitions as $pr)
                                    <option value="{{ $pr->id }}" @selected($selectedRequisitionId == $pr->id)>
                                        {{ $pr->requisition_number }} ({{ $pr->requester?->name ?? 'System' }} - {{ $pr->requisition_date->format('d-M-Y') }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <!-- Right Panel: Dates, Discount & Tax Types -->
                        <div class="col-md-6">
                            <h6 class="fw-bold text-primary mb-3">Dates & Calculations Options</h6>

                            <x-ui.odoo-form-ui type="input" label="Order Date" name="date" inputType="date" :value="old('date', date('Y-m-d'))" required="true" />
                            <x-ui.odoo-form-ui type="input" label="Delivery Date" name="delivery_date" inputType="date" :value="old('delivery_date')" />

                            <x-ui.odoo-form-ui type="select" label="Discount Option" name="discount_type" id="discountTypeSelect" required="true">
                                <option value="without_discount" @selected(old('discount_type') === 'without_discount')>Without Discount</option>
                                <option value="item_wise" @selected(old('discount_type') === 'item_wise')>With Discount At Item Level</option>
                                <option value="order_wise" @selected(old('discount_type') === 'order_wise')>With Discount At Order Level</option>
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="select" label="Tax Option" name="tax_type" id="taxTypeSelect" required="true">
                                <option value="without_tax" @selected(old('tax_type') === 'without_tax')>Without Tax</option>
                                <option value="item_wise_tax" @selected(old('tax_type') === 'item_wise_tax')>Item Wise Tax</option>
                                <option value="order_wise_tax" @selected(old('tax_type', 'order_wise_tax') === 'order_wise_tax')>Order Wise Tax</option>
                            </x-ui.odoo-form-ui>

                            <div id="gstTypeContainer">
                                <x-ui.odoo-form-ui type="select" label="GST Type" name="gst_type" id="gstTypeSelect" required="true">
                                    <option value="cgst_sgst" @selected(old('gst_type', 'cgst_sgst') === 'cgst_sgst')>CGST + SGST (Intra-State)</option>
                                    <option value="igst" @selected(old('gst_type') === 'igst')>IGST (Inter-State)</option>
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="mt-5">
                        <h5 class="fw-bold text-dark mb-3"><i class="feather-layers text-primary me-2"></i>Purchase Order Line Items</h5>
                        <div class="table-responsive">
                            <x-ui.odoo-form-ui type="table" id="poItemsTable">
                                <thead>
                                    <tr>
                                        <th style="width: 32%">Product <span class="text-danger">*</span></th>
                                        <th class="text-end" style="width: 8%">Qty <span class="text-danger">*</span></th>
                                        <th class="text-end" style="width: 8%">Rate <span class="text-danger">*</span></th>
                                        <th class="text-end" style="width: 8%">Amount</th>
                                        
                                        <!-- Discount Columns -->
                                        <th class="text-end discount-column" style="width: 6%">Disc %</th>
                                        <th class="text-end discount-column" style="width: 8%">Disc Amt</th>
                                        
                                        <!-- Tax Columns (Item Wise) -->
                                        <th class="text-end tax-column" style="width: 8%">Tax %</th>
                                        <th class="text-end tax-column" style="width: 10%">Tax Amt</th>

                                        <th class="text-end" style="width: 11%">Total Amt</th>
                                        <th style="width: 3%"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Prefilled items (if loaded from request query or old input) -->
                                    @if(count($prefilledItems) > 0)
                                        @foreach($prefilledItems as $idx => $item)
                                            <tr class="item-row" data-index="{{ $idx }}">
                                                <td>
                                                    <x-ui.odoo-form-ui type="select" name="items[{{ $idx }}][product_id]" class="product-select" required="true">
                                                        <option value="">Select Product...</option>
                                                        @foreach($products as $p)
                                                            <option value="{{ $p->id }}" data-cost="{{ $p->unit_cost ?? 0.00 }}" @selected($p->id == $item['product_id'])>
                                                                {{ $p->name }} ({{ $p->sku ?: 'No SKU' }})
                                                            </option>
                                                        @endforeach
                                                    </x-ui.odoo-form-ui>
                                                </td>
                                                <td>
                                                    <x-ui.odoo-form-ui type="input" inputType="number" name="items[{{ $idx }}][quantity]" class="text-end qty-input" step="0.0001" min="0.0001" required="true" :value="$item['quantity']" />
                                                </td>
                                                <td>
                                                    <x-ui.odoo-form-ui type="input" inputType="number" name="items[{{ $idx }}][rate]" class="text-end rate-input" step="0.01" min="0" required="true" :value="$item['rate']" />
                                                </td>
                                                <td>
                                                    <x-ui.odoo-form-ui type="input" inputType="number" name="items[{{ $idx }}][amount]" class="text-end amount-input" step="0.01" min="0" readonly="true" :value="$item['quantity'] * $item['rate']" />
                                                </td>
                                                <!-- Discount -->
                                                <td class="discount-column">
                                                    <x-ui.odoo-form-ui type="input" inputType="number" name="items[{{ $idx }}][discount_percent]" class="text-end disc-percent-input" step="0.01" min="0" max="100" value="0.00" />
                                                </td>
                                                <td class="discount-column">
                                                    <x-ui.odoo-form-ui type="input" inputType="number" name="items[{{ $idx }}][discount_amount]" class="text-end disc-amount-input" step="0.01" readonly="true" value="0.00" />
                                                </td>
                                                <!-- Tax rates (Percent and Amount columns) -->
                                                <td class="tax-column">
                                                    <x-ui.odoo-form-ui type="input" inputType="number" name="items[{{ $idx }}][tax_percent]" class="text-end tax-percent-input" step="0.01" min="0" value="0.00" />
                                                    <!-- Hidden splits calculated by JS -->
                                                    <input type="hidden" name="items[{{ $idx }}][cgst_percent]" class="cgst-percent-input" value="0.00">
                                                    <input type="hidden" name="items[{{ $idx }}][sgst_percent]" class="sgst-percent-input" value="0.00">
                                                    <input type="hidden" name="items[{{ $idx }}][igst_percent]" class="igst-percent-input" value="0.00">
                                                    <input type="hidden" name="items[{{ $idx }}][cgst_amount]" class="cgst-amount-input" value="0.00">
                                                    <input type="hidden" name="items[{{ $idx }}][sgst_amount]" class="sgst-amount-input" value="0.00">
                                                    <input type="hidden" name="items[{{ $idx }}][igst_amount]" class="igst-amount-input" value="0.00">
                                                </td>
                                                <td class="tax-column">
                                                    <x-ui.odoo-form-ui type="input" inputType="number" name="items[{{ $idx }}][tax_amount]" class="text-end tax-amount-input" step="0.01" readonly="true" value="0.00" />
                                                </td>
                                                <td>
                                                    <x-ui.odoo-form-ui type="input" inputType="number" name="items[{{ $idx }}][total_amount]" class="text-end total-amount-input" step="0.01" readonly="true" value="{{ $item['quantity'] * $item['rate'] }}" />
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-link text-danger remove-row-btn p-1 border-0"><i class="feather-trash-2 fs-14"></i></button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <!-- One blank row template -->
                                        <tr class="item-row" data-index="0">
                                            <td>
                                                <x-ui.odoo-form-ui type="select" name="items[0][product_id]" class="product-select" required="true">
                                                    <option value="">Select Product...</option>
                                                    @foreach($products as $p)
                                                        <option value="{{ $p->id }}" data-cost="{{ $p->unit_cost ?? 0.00 }}">{{ $p->name }} ({{ $p->sku ?: 'No SKU' }})</option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>
                                            </td>
                                            <td>
                                                <x-ui.odoo-form-ui type="input" inputType="number" name="items[0][quantity]" class="text-end qty-input" step="0.0001" min="0.0001" required="true" placeholder="0" />
                                            </td>
                                            <td>
                                                <x-ui.odoo-form-ui type="input" inputType="number" name="items[0][rate]" class="text-end rate-input" step="0.01" min="0" required="true" placeholder="0.00" />
                                            </td>
                                            <td>
                                                <x-ui.odoo-form-ui type="input" inputType="number" name="items[0][amount]" class="text-end amount-input" step="0.01" min="0" readonly="true" placeholder="0.00" />
                                            </td>
                                            <!-- Discount -->
                                            <td class="discount-column">
                                                <x-ui.odoo-form-ui type="input" inputType="number" name="items[0][discount_percent]" class="text-end disc-percent-input" step="0.01" min="0" max="100" value="0.00" />
                                            </td>
                                            <td class="discount-column">
                                                <x-ui.odoo-form-ui type="input" inputType="number" name="items[0][discount_amount]" class="text-end disc-amount-input" step="0.01" readonly="true" value="0.00" />
                                            </td>
                                            <!-- Tax rates (Percent and Amount columns) -->
                                            <td class="tax-column">
                                                <x-ui.odoo-form-ui type="input" inputType="number" name="items[0][tax_percent]" class="text-end tax-percent-input" step="0.01" min="0" placeholder="0" />
                                                <!-- Hidden splits calculated by JS -->
                                                <input type="hidden" name="items[0][cgst_percent]" class="cgst-percent-input" value="0.00">
                                                <input type="hidden" name="items[0][sgst_percent]" class="sgst-percent-input" value="0.00">
                                                <input type="hidden" name="items[0][igst_percent]" class="igst-percent-input" value="0.00">
                                                <input type="hidden" name="items[0][cgst_amount]" class="cgst-amount-input" value="0.00">
                                                <input type="hidden" name="items[0][sgst_amount]" class="sgst-amount-input" value="0.00">
                                                <input type="hidden" name="items[0][igst_amount]" class="igst-amount-input" value="0.00">
                                            </td>
                                            <td class="tax-column">
                                                <x-ui.odoo-form-ui type="input" inputType="number" name="items[0][tax_amount]" class="text-end tax-amount-input" step="0.01" readonly="true" placeholder="0.00" />
                                            </td>
                                            <td>
                                                <x-ui.odoo-form-ui type="input" inputType="number" name="items[0][total_amount]" class="text-end total-amount-input" step="0.01" readonly="true" placeholder="0.00" />
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-link text-danger remove-row-btn p-1 border-0" disabled><i class="feather-trash-2 fs-14"></i></button>
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="mt-3">
                            <button type="button" class="btn btn-sm btn-soft-primary px-3 fw-bold" id="addRowBtn">
                                <i class="feather-plus me-1"></i> Add Line
                            </button>
                        </div>
                    </div>

                    <!-- Bottom Details & Totals Summary -->
                    <div class="row mt-5 pt-3 border-top g-4">
                        <!-- Left side: Rich text editor notes -->
                        <div class="col-md-7">
                            <x-ui.odoo-form-ui type="editor" label="Terms & Notes" name="notes" placeholder="Specify any delivery terms, quality checks, payment instructions, etc.">
                                {!! old('notes') !!}
                            </x-ui.odoo-form-ui>
                        </div>
                        
                        <!-- Right side: Calculation Breakdown -->
                        <div class="col-md-5 d-flex flex-column align-items-end fs-13">
                            <div class="card border-0 shadow-sm w-100" style="max-width: 380px; background: #ffffff; border-radius: 8px; border: 1px solid #cbd5e1 !important; overflow: hidden;">
                                <div class="fw-bold py-3 px-3 text-white" style="background-color: #2563eb; font-size: 12px; letter-spacing: 0.5px; text-transform: uppercase;">
                                    ORDER SUMMARY
                                </div>
                                <div class="p-3 bg-white text-dark">
                                    <!-- Taxable Subtotal -->
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="text-muted fs-13 fw-semibold">Taxable Subtotal</span>
                                        <input type="text" id="summarySubtotalText" class="form-control form-control-sm text-end fw-bold" style="width: 140px; height: 32px; border: 1px solid #cbd5e1; border-radius: 4px; color: #334155; background-color: #f8fafc;" readonly value="0.00">
                                        <input type="hidden" name="subtotal" id="summarySubtotal" value="0.00">
                                    </div>

                                    <!-- Total Discount -->
                                    <div class="d-flex justify-content-between align-items-center mb-3" id="summaryDiscountRow">
                                        <span class="text-muted fs-13 fw-semibold">Discount Amount</span>
                                        <input type="number" name="discount_amount" id="summaryDiscount" class="form-control form-control-sm text-end fw-bold" style="width: 140px; height: 32px; border: 1px solid #cbd5e1; border-radius: 4px; color: #334155;" step="0.01" value="0.00">
                                    </div>

                                    <!-- Gross Total -->
                                    <div class="d-flex justify-content-between align-items-center mb-3" id="summaryGrossRow">
                                        <span class="text-muted fs-13 fw-semibold">Gross Total (Before Tax)</span>
                                        <input type="text" id="summaryGrossText" class="form-control form-control-sm text-end fw-bold" style="width: 140px; height: 32px; border: 1px solid #cbd5e1; border-radius: 4px; color: #334155; background-color: #f8fafc;" readonly value="0.00">
                                    </div>

                                    <!-- Tax Rate (Percent) -->
                                    <div class="d-flex justify-content-between align-items-center mb-3" id="orderTaxPercentRow">
                                        <span class="text-muted fs-13 fw-semibold">Tax Rate (%)</span>
                                        <input type="number" id="orderTaxPercent" class="form-control form-control-sm text-end fw-bold" style="width: 140px; height: 32px; border: 1px solid #cbd5e1; border-radius: 4px; color: #334155;" min="0" step="0.01" value="0.00">
                                    </div>

                                    <!-- Hidden splits submitted to backend -->
                                    <input type="hidden" name="cgst_amount" id="summaryCgst" value="0.00">
                                    <input type="hidden" name="sgst_amount" id="summarySgst" value="0.00">
                                    <input type="hidden" name="igst_amount" id="summaryIgst" value="0.00">

                                    <!-- Tax Amount -->
                                    <div class="d-flex justify-content-between align-items-center mb-3" id="summaryTaxRow">
                                        <span class="text-muted fs-13 fw-semibold">Tax Amount</span>
                                        <input type="text" id="summaryTaxText" class="form-control form-control-sm text-end fw-bold" style="width: 140px; height: 32px; border: 1px solid #cbd5e1; border-radius: 4px; color: #334155; background-color: #f8fafc;" readonly value="0.00">
                                        <input type="hidden" name="tax_amount" id="summaryTax" value="0.00">
                                    </div>

                                    <!-- Grand Total (Mewar Balance Amount style) -->
                                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                        <span class="fw-bold fs-13" style="color: #2563eb;">Grand Total</span>
                                        <input type="text" id="summaryGrandtotalText" class="form-control form-control-sm text-end fw-extrabold" style="width: 140px; height: 32px; border: 1px solid #2563eb; border-radius: 4px; background-color: #eff6ff; color: #2563eb;" readonly value="0.00">
                                        <input type="hidden" name="grand_total" id="summaryGrandtotal" value="0.00">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2 dropdowns
            function initSelect2(context) {
                $(context).find('.odoo-select2').select2({
                    theme: 'bootstrap-5',
                    width: '100%'
                });
            }
            initSelect2(document);


            // Toggle Columns based on Tax Type and Discount Type
            function adjustLayout() {
                const discType = $('select[name="discount_type"]').val();
                const taxType = $('select[name="tax_type"]').val();

                // 1. Discount option changes
                if (discType === 'item_wise') {
                    $('.discount-column').show();
                    $('#summaryDiscountRow').show();
                    $('#summaryGrossRow').show();
                    $('#summaryDiscount').prop('readonly', true).css('background-color', '#f8fafc');
                } else if (discType === 'order_wise') {
                    $('.discount-column').hide();
                    $('.disc-percent-input').val('0.00');
                    $('.disc-amount-input').val('0.00');
                    $('#summaryDiscountRow').show();
                    $('#summaryGrossRow').show();
                    $('#summaryDiscount').prop('readonly', false).css('background-color', '#ffffff');
                } else {
                    // without_discount
                    $('.discount-column').hide();
                    $('.disc-percent-input').val('0.00');
                    $('.disc-amount-input').val('0.00');
                    $('#summaryDiscountRow').hide();
                    $('#summaryGrossRow').hide();
                    $('#summaryDiscount').val('0.00');
                }

                // 2. Tax option changes
                if (taxType === 'item_wise_tax') {
                    $('.tax-column').show();
                    $('#orderTaxPercentRow').hide().find('#orderTaxPercent').val('0.00');
                    $('#summaryTaxRow').show();
                    $('#gstTypeContainer').show();
                } else if (taxType === 'order_wise_tax') {
                    $('.tax-column').hide();
                    $('.tax-percent-input, .tax-amount-input').val('0.00');
                    $('.cgst-percent-input, .sgst-percent-input, .igst-percent-input').val('0.00');
                    $('.cgst-amount-input, .sgst-amount-input, .igst-amount-input').val('0.00');
                    $('#orderTaxPercentRow').show();
                    $('#orderTaxPercent').prop('readonly', false).css('background-color', '#ffffff');
                    $('#summaryTaxRow').show();
                    $('#gstTypeContainer').show();
                } else {
                    // without_tax
                    $('.tax-column').hide();
                    $('.tax-percent-input, .tax-amount-input').val('0.00');
                    $('.cgst-percent-input, .sgst-percent-input, .igst-percent-input').val('0.00');
                    $('.cgst-amount-input, .sgst-amount-input, .igst-amount-input').val('0.00');
                    $('#orderTaxPercentRow').hide().find('#orderTaxPercent').val('0.00');
                    $('#summaryTaxRow').hide();
                    $('#gstTypeContainer').hide();
                    $('#summaryCgst, #summarySgst, #summaryIgst, #summaryTax').val('0.00');
                }

                calculateAll();
            }

            $(document).on('change', 'select[name="discount_type"], select[name="tax_type"], select[name="gst_type"]', adjustLayout);
            adjustLayout(); // run initial layout adjustments

            // Recalculations Engine
            function calculateAll() {
                const discType = $('select[name="discount_type"]').val();
                const taxType = $('select[name="tax_type"]').val();
                const gstType = $('select[name="gst_type"]').val();
                
                let subtotal = 0.00;
                let totalItemDiscount = 0.00;
                let totalItemTax = 0.00;

                let totalCgst = 0.00;
                let totalSgst = 0.00;
                let totalIgst = 0.00;

                $('#poItemsTable tbody tr.item-row').each(function() {
                    const $row = $(this);
                    const qty = parseFloat($row.find('.qty-input').val()) || 0;
                    const rate = parseFloat($row.find('.rate-input').val()) || 0;
                    
                    const amount = qty * rate;
                    $row.find('.amount-input').val(amount.toFixed(2));
                    subtotal += amount;

                    // Row Discount calculations
                    let rowDiscount = 0.00;
                    if (discType === 'item_wise') {
                        const discPercent = parseFloat($row.find('.disc-percent-input').val()) || 0;
                        rowDiscount = amount * (discPercent / 100);
                        $row.find('.disc-amount-input').val(rowDiscount.toFixed(2));
                        totalItemDiscount += rowDiscount;
                    } else {
                        $row.find('.disc-amount-input').val('0.00');
                    }

                    const taxableAmount = amount - rowDiscount;

                    // Row Tax calculations
                    let rowTax = 0.00;
                    if (taxType === 'item_wise_tax') {
                        const taxPercent = parseFloat($row.find('.tax-percent-input').val()) || 0;
                        
                        let cgstPct = 0.00;
                        let sgstPct = 0.00;
                        let igstPct = 0.00;

                        if (gstType === 'cgst_sgst') {
                            cgstPct = taxPercent / 2;
                            sgstPct = taxPercent / 2;
                            igstPct = 0;
                        } else {
                            cgstPct = 0;
                            sgstPct = 0;
                            igstPct = taxPercent;
                        }

                        $row.find('.cgst-percent-input').val(cgstPct.toFixed(2));
                        $row.find('.sgst-percent-input').val(sgstPct.toFixed(2));
                        $row.find('.igst-percent-input').val(igstPct.toFixed(2));

                        const cgstAmt = taxableAmount * (cgstPct / 100);
                        const sgstAmt = taxableAmount * (sgstPct / 100);
                        const igstAmt = taxableAmount * (igstPct / 100);

                        $row.find('.cgst-amount-input').val(cgstAmt.toFixed(2));
                        $row.find('.sgst-amount-input').val(sgstAmt.toFixed(2));
                        $row.find('.igst-amount-input').val(igstAmt.toFixed(2));

                        totalCgst += cgstAmt;
                        totalSgst += sgstAmt;
                        totalIgst += igstAmt;

                        rowTax = cgstAmt + sgstAmt + igstAmt;
                        $row.find('.tax-amount-input').val(rowTax.toFixed(2));
                        totalItemTax += rowTax;
                    } else {
                        $row.find('.tax-amount-input').val('0.00');
                        $row.find('.cgst-percent-input, .sgst-percent-input, .igst-percent-input').val('0.00');
                        $row.find('.cgst-amount-input, .sgst-amount-input, .igst-amount-input').val('0.00');
                    }

                    const rowTotal = taxableAmount + rowTax;
                    $row.find('.total-amount-input').val(rowTotal.toFixed(2));
                });

                // Update subtotal
                $('#summarySubtotal').val(subtotal.toFixed(2));
                $('#summarySubtotalText').val(subtotal.toFixed(2));

                // Resolve discount
                let finalDiscount = 0.00;
                if (discType === 'item_wise') {
                    finalDiscount = totalItemDiscount;
                    $('#summaryDiscount').val(finalDiscount.toFixed(2));
                } else if (discType === 'order_wise') {
                    finalDiscount = parseFloat($('#summaryDiscount').val()) || 0.00;
                } else {
                    finalDiscount = 0.00;
                    $('#summaryDiscount').val('0.00');
                }

                const grossTotal = subtotal - finalDiscount;
                $('#summaryGrossText').val(grossTotal.toFixed(2));

                // Resolve tax totals
                let finalTax = 0.00;
                if (taxType === 'item_wise_tax') {
                    finalTax = totalItemTax;
                    $('#summaryCgst').val(totalCgst.toFixed(2));
                    $('#summarySgst').val(totalSgst.toFixed(2));
                    $('#summaryIgst').val(totalIgst.toFixed(2));
                    
                    $('#summaryTaxText').val(finalTax.toFixed(2));
                } else if (taxType === 'order_wise_tax') {
                    const orderTaxPercent = parseFloat($('#orderTaxPercent').val()) || 0;

                    let cgstPct = 0;
                    let sgstPct = 0;
                    let igstPct = 0;

                    if (gstType === 'cgst_sgst') {
                        cgstPct = orderTaxPercent / 2;
                        sgstPct = orderTaxPercent / 2;
                        igstPct = 0;
                    } else {
                        cgstPct = 0;
                        sgstPct = 0;
                        igstPct = orderTaxPercent;
                    }

                    const cgstAmt = grossTotal * (cgstPct / 100);
                    const sgstAmt = grossTotal * (sgstPct / 100);
                    const igstAmt = grossTotal * (igstPct / 100);

                    finalTax = cgstAmt + sgstAmt + igstAmt;

                    $('#summaryCgst').val(cgstAmt.toFixed(2));
                    $('#summarySgst').val(sgstAmt.toFixed(2));
                    $('#summaryIgst').val(igstAmt.toFixed(2));
                    
                    $('#summaryTaxText').val(finalTax.toFixed(2));
                } else {
                    $('#summaryCgst').val('0.00');
                    $('#summarySgst').val('0.00');
                    $('#summaryIgst').val('0.00');
                    $('#summaryTaxText').val('0.00');
                }

                $('#summaryTax').val(finalTax.toFixed(2));

                const grandTotal = grossTotal + finalTax;
                $('#summaryGrandtotal').val(grandTotal.toFixed(2));
                $('#summaryGrandtotalText').val(grandTotal.toFixed(2));
            }

            // Calculations triggers
            $(document).on('input', '.qty-input, .rate-input, .disc-percent-input, .tax-percent-input', calculateAll);
            $('#summaryDiscount, #orderTaxPercent').on('input', calculateAll);

            // Handle Product Selection - prefill rate
            $(document).on('change', '.product-select', function() {
                const opt = this.options[this.selectedIndex];
                const cost = parseFloat($(opt).attr('data-cost')) || 0.00;
                $(this).closest('tr').find('.rate-input').val(cost.toFixed(2)).trigger('input');
            });

            // Dynamic Rows Addition
            let rowIdx = $('#poItemsTable tbody tr').length - 1;

            $('#addRowBtn').on('click', function() {
                rowIdx++;
                const newRow = `
                    <tr class="item-row" data-index="${rowIdx}">
                        <td>
                            <select name="items[${rowIdx}][product_id]" class="odoo-table-select odoo-select2 product-select" required style="border-radius:0;">
                                <option value="">Select Product...</option>
                                @foreach($products as $p)
                                    <option value="{{ $p->id }}" data-cost="{{ $p->unit_cost ?? 0.00 }}">{{ $p->name }} ({{ $p->sku ?: 'No SKU' }})</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="number" name="items[${rowIdx}][quantity]" class="odoo-table-input text-end qty-input" step="0.0001" min="0.0001" required placeholder="0">
                        </td>
                        <td>
                            <input type="number" name="items[${rowIdx}][rate]" class="odoo-table-input text-end rate-input" step="0.01" min="0" required placeholder="0.00">
                        </td>
                        <td>
                            <input type="number" name="items[${rowIdx}][amount]" class="odoo-table-input text-end amount-input" step="0.01" readonly placeholder="0.00">
                        </td>
                        <!-- Discount -->
                        <td class="discount-column">
                            <input type="number" name="items[${rowIdx}][discount_percent]" class="odoo-table-input text-end disc-percent-input" step="0.01" min="0" max="100" value="0.00">
                        </td>
                        <td class="discount-column">
                            <input type="number" name="items[${rowIdx}][discount_amount]" class="odoo-table-input text-end disc-amount-input" step="0.01" readonly value="0.00">
                        </td>
                        <!-- Tax rates (Percent and Amount columns) -->
                        <td class="tax-column">
                            <input type="number" name="items[${rowIdx}][tax_percent]" class="odoo-table-input text-end tax-percent-input" step="0.01" min="0" value="0.00">
                            <!-- Hidden splits calculated by JS -->
                            <input type="hidden" name="items[${rowIdx}][cgst_percent]" class="cgst-percent-input" value="0.00">
                            <input type="hidden" name="items[${rowIdx}][sgst_percent]" class="sgst-percent-input" value="0.00">
                            <input type="hidden" name="items[${rowIdx}][igst_percent]" class="igst-percent-input" value="0.00">
                            <input type="hidden" name="items[${rowIdx}][cgst_amount]" class="cgst-amount-input" value="0.00">
                            <input type="hidden" name="items[${rowIdx}][sgst_amount]" class="sgst-amount-input" value="0.00">
                            <input type="hidden" name="items[${rowIdx}][igst_amount]" class="igst-amount-input" value="0.00">
                        </td>
                        <td class="tax-column">
                            <input type="number" name="items[${rowIdx}][tax_amount]" class="odoo-table-input text-end tax-amount-input" step="0.01" readonly value="0.00">
                        </td>
                        <td>
                            <input type="number" name="items[${rowIdx}][total_amount]" class="odoo-table-input text-end total-amount-input" step="0.01" readonly placeholder="0.00">
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-link text-danger remove-row-btn p-1 border-0"><i class="feather-trash-2 fs-14"></i></button>
                        </td>
                    </tr>
                `;
                const $tr = $(newRow);
                $('#poItemsTable tbody').append($tr);
                initSelect2($tr);
                updateRemoveRowButtons();
                adjustLayout();
            });

            $(document).on('click', '.remove-row-btn', function() {
                $(this).closest('tr').remove();
                updateRemoveRowButtons();
                calculateAll();
            });

            function updateRemoveRowButtons() {
                const count = $('#poItemsTable tbody tr.item-row').length;
                if (count <= 1) {
                    $('#poItemsTable tbody tr.item-row').find('.remove-row-btn').prop('disabled', true);
                } else {
                    $('#poItemsTable tbody tr.item-row').find('.remove-row-btn').prop('disabled', false);
                }
            }
            updateRemoveRowButtons();

            // Load Items Dynamically from Purchase Requisition selection
            $('#requisitionSelect').on('change', function() {
                const prId = $(this).val();
                if (!prId) return;

                if (!confirm('Loading requisition items will clear any lines you have currently added. Do you want to proceed?')) {
                    $(this).val('').trigger('change.select2');
                    return;
                }

                $.ajax({
                    url: "{{ route('purchase.orders.get-requisition-items') }}",
                    type: 'GET',
                    data: { requisition_id: prId },
                    success: function(res) {
                        if (res.success && res.items.length > 0) {
                            if (res.items[0] && res.items[0].warehouse_name && res.items[0].warehouse_name !== '—') {
                                $('#locationSelect').val(res.items[0].warehouse_name).trigger('change');
                            }
                            $('#poItemsTable tbody').empty();
                            rowIdx = -1;
                            res.items.forEach(function(item) {
                                rowIdx++;
                                const trMarkup = `
                                    <tr class="item-row" data-index="${rowIdx}">
                                        <td>
                                            <select name="items[${rowIdx}][product_id]" class="odoo-table-select odoo-select2 product-select" required style="border-radius:0;">
                                                <option value="">Select Product...</option>
                                                @foreach($products as $p)
                                                    <option value="{{ $p->id }}" data-cost="{{ $p->unit_cost ?? 0.00 }}">${item.product_name}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" name="items[${rowIdx}][quantity]" class="odoo-table-input text-end qty-input" step="0.0001" min="0.0001" required value="${item.quantity}">
                                        </td>
                                        <td>
                                            <input type="number" name="items[${rowIdx}][rate]" class="odoo-table-input text-end rate-input" step="0.01" min="0" required value="${item.rate}">
                                        </td>
                                        <td>
                                            <input type="number" name="items[${rowIdx}][amount]" class="odoo-table-input text-end amount-input" step="0.01" readonly value="${(item.quantity * item.rate).toFixed(2)}">
                                        </td>
                                        <!-- Discount -->
                                        <td class="discount-column">
                                            <input type="number" name="items[${rowIdx}][discount_percent]" class="odoo-table-input text-end disc-percent-input" step="0.01" min="0" max="100" value="0.00">
                                        </td>
                                        <td class="discount-column">
                                            <input type="number" name="items[${rowIdx}][discount_amount]" class="odoo-table-input text-end disc-amount-input" step="0.01" readonly value="0.00">
                                        </td>
                                        <!-- Tax rates (Percent and Amount columns) -->
                                        <td class="tax-column">
                                            <input type="number" name="items[${rowIdx}][tax_percent]" class="odoo-table-input text-end tax-percent-input" step="0.01" min="0" value="0.00">
                                            <!-- Hidden splits calculated by JS -->
                                            <input type="hidden" name="items[${rowIdx}][cgst_percent]" class="cgst-percent-input" value="0.00">
                                            <input type="hidden" name="items[${rowIdx}][sgst_percent]" class="sgst-percent-input" value="0.00">
                                            <input type="hidden" name="items[${rowIdx}][igst_percent]" class="igst-percent-input" value="0.00">
                                            <input type="hidden" name="items[${rowIdx}][cgst_amount]" class="cgst-amount-input" value="0.00">
                                            <input type="hidden" name="items[${rowIdx}][sgst_amount]" class="sgst-amount-input" value="0.00">
                                            <input type="hidden" name="items[${rowIdx}][igst_amount]" class="igst-amount-input" value="0.00">
                                        </td>
                                        <td class="tax-column">
                                            <input type="number" name="items[${rowIdx}][tax_amount]" class="odoo-table-input text-end tax-amount-input" step="0.01" readonly value="0.00">
                                        </td>
                                        <td>
                                            <input type="number" name="items[${rowIdx}][total_amount]" class="odoo-table-input text-end total-amount-input" step="0.01" readonly value="${(item.quantity * item.rate).toFixed(2)}">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-link text-danger remove-row-btn p-1 border-0"><i class="feather-trash-2 fs-14"></i></button>
                                        </td>
                                    </tr>
                                `;
                                const $tr = $(trMarkup);
                                $('#poItemsTable tbody').append($tr);
                                $tr.find('.product-select').val(item.product_id);
                            });
                            
                            initSelect2($('#poItemsTable tbody'));
                            updateRemoveRowButtons();
                            adjustLayout();
                        } else {
                            alert('No items found or failed to fetch requisition items.');
                        }
                    },
                    error: function() {
                        alert('Error communicating with the server.');
                    }
                });
            });

            // Initial Calculations trigger
            calculateAll();
        });
    </script>
@endpush
