@extends('layouts.duralux')

@section('title', __('crm.generate_invoice') . ' | SaaS ERP')
@section('page-title', __('crm.generate_invoice'))
@section('breadcrumb', __('crm.sales') . ' / ' . __('crm.invoices') . ' / ' . __('crm.generate_invoice'))

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
                <div class="d-flex align-items-center mb-4 border-bottom pb-2">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">{{ __('crm.generate_customer_invoice') }}</h5>
                        <span class="fs-12 text-muted">{{ __('crm.generate_invoice_subtitle') }}</span>
                    </div>
                </div>

                @php
                    $activeInvoicingPolicy = $invoicingPolicy ?? 'both';
                @endphp

                <!-- 3-Mode Selection Radio Bar -->
                <div class="mb-4 bg-light p-3 rounded border {{ $activeInvoicingPolicy !== 'both' ? 'd-none' : '' }}">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted d-block mb-2">{{ __('crm.create_invoice_based_on') }}</label>
                    <div class="d-flex gap-4 flex-wrap align-items-center">
                        <div class="form-check">
                            <input class="form-check-input mode-radio" type="radio" name="mode_option" id="modeSalesOrder" value="sales_order" {{ $mode === 'sales_order' ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold text-dark fs-13" for="modeSalesOrder">
                                <i class="feather-file-text me-1 text-primary"></i>{{ __('crm.against_sales_order') }}
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input mode-radio" type="radio" name="mode_option" id="modeDispatchOrder" value="dispatch_order" {{ $mode === 'dispatch_order' ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold text-dark fs-13" for="modeDispatchOrder">
                                <i class="feather-truck me-1 text-info"></i>{{ __('crm.against_dispatch_order') }}
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input mode-radio" type="radio" name="mode_option" id="modeDirect" value="direct" {{ $mode === 'direct' ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold text-dark fs-13" for="modeDirect">
                                <i class="feather-user me-1 text-success"></i>{{ __('crm.direct_invoice_standalone') }}
                            </label>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4 fs-13 text-dark">
                    <!-- Column 1: Source Selector & Customer Info -->
                    <div class="col-md-6 border-end pe-md-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-user me-1"></i>{{ __('crm.customer_billing_reference') }}</h6>

                        @if ($mode === 'sales_order')
                            <!-- Mode 1: Sales Order Dropdown -->
                            <x-ui.odoo-form-ui type="select" :label="__('crm.sales_order_reference')" name="sales_order_id" id="salesOrderSelect" class="odoo-select2" :required="true">
                                <option value="">{{ __('crm.select_sales_order_ph') }}</option>
                                @foreach ($salesOrders as $so)
                                    <option value="{{ $so->id }}" @selected($salesOrder?->id == $so->id)>
                                        {{ $so->sales_order_number }} (Customer: {{ $so->customer?->name }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="input" :label="__('crm.customer')" name="_customer_display" :value="$salesOrder?->customer?->name ?: '—'" readonly="true" style="font-weight: bold; background-color: transparent;" />

                            <x-ui.odoo-form-ui type="input" :label="__('crm.payment_terms')" name="_terms_display" :value="$salesOrder?->payment_terms ?: __('crm.immediate_payment')" readonly="true" style="background-color: transparent;" />

                        @elseif ($mode === 'dispatch_order')
                            <!-- Mode 2: Dispatch Order Dropdown -->
                            <x-ui.odoo-form-ui type="select" :label="__('crm.dispatch_order_reference')" name="dispatch_order_id" id="dispatchOrderSelect" class="odoo-select2" :required="true">
                                <option value="">{{ __('crm.select_dispatch_order_ph') }}</option>
                                @foreach ($dispatchOrders as $do)
                                    <option value="{{ $do->id }}" @selected($dispatchOrder?->id == $do->id)>
                                        {{ $do->dispatch_number }} (SO: {{ $do->salesOrder?->sales_order_number }} - Customer: {{ $do->salesOrder?->customer?->name }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>

                            <input type="hidden" name="material_requirement_id" value="{{ $dispatchOrder?->material_requirement_id }}">
                            <input type="hidden" name="sales_order_id" value="{{ $salesOrder?->id }}">

                            <x-ui.odoo-form-ui type="input" :label="__('crm.customer')" name="_customer_display" :value="$salesOrder?->customer?->name ?: ($dispatchOrder?->salesOrder?->customer?->name ?: '—')" readonly="true" style="font-weight: bold; background-color: transparent;" />

                            <x-ui.odoo-form-ui type="input" :label="__('crm.payment_terms')" name="_terms_display" :value="$salesOrder?->payment_terms ?: __('crm.immediate_payment')" readonly="true" style="background-color: transparent;" />

                        @else
                            <!-- Mode 3: Direct Customer Dropdown -->
                            <x-ui.odoo-form-ui type="select" :label="__('crm.customer')" name="customer_id" id="directCustomerSelect" class="odoo-select2" :required="true">
                                <option value="">{{ __('crm.select_customer_ph') }}</option>
                                @foreach ($customers as $c)
                                    <option value="{{ $c->id }}" @selected(old('customer_id', $customerId ?? request('customer_id')) == $c->id)>
                                        {{ $c->name }} ({{ $c->company_name ?: 'Individual' }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="select" :label="__('crm.payment_terms')" name="payment_terms">
                                <option value="Immediate Payment">{{ __('crm.immediate_payment') }}</option>
                                <option value="15 Days">15 Days</option>
                                <option value="30 Days">30 Days</option>
                                <option value="45 Days">45 Days</option>
                                <option value="60 Days">60 Days</option>
                            </x-ui.odoo-form-ui>
                        @endif

                        @if ($advanceAllocations > 0)
                            <div class="alert alert-info border-0 shadow-sm mt-3 py-2 px-3 fs-12 text-info">
                                <i class="feather-info me-2 fw-bold"></i>
                                {{ __('crm.advance_paid_on_so') }} <strong>{{ format_currency($advanceAllocations) }}</strong>. {{ __('crm.automatically_adjusted') }}
                            </div>
                        @endif
                    </div>

                    <!-- Column 2: Invoice Dates, Tax & Discount Options -->
                    <div class="col-md-6 ps-md-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-sliders me-1"></i>{{ __('crm.dates_calculation_options') }}</h6>

                        <x-ui.odoo-form-ui type="input" :label="__('crm.invoice_number')" name="invoice_number" :value="old('invoice_number', $nextInvoiceNumber)" :required="true" style="font-weight: bold; color: #495057;" />

                        <x-ui.odoo-form-ui type="input" inputType="date" :label="__('crm.invoice_date')" name="invoice_date" :value="old('invoice_date', date('Y-m-d'))" :required="true" />

                        <x-ui.odoo-form-ui type="input" inputType="date" :label="__('crm.due_date')" name="due_date" :value="old('due_date', date('Y-m-d', strtotime('+15 days')))" />

                        <!-- Discount Option -->
                        <x-ui.odoo-form-ui type="select" :label="__('crm.discount_option')" name="discount_type" id="discountTypeSelect" :required="true">
                            <option value="without_discount" @selected(old('discount_type', $salesOrder?->discount_type ?? 'item_wise') === 'without_discount')>{{ __('crm.without_discount') }}</option>
                            <option value="item_wise" @selected(old('discount_type', $salesOrder?->discount_type ?? 'item_wise') === 'item_wise')>{{ __('crm.item_level_discount') }}</option>
                            <option value="order_wise" @selected(old('discount_type', $salesOrder?->discount_type) === 'order_wise')>{{ __('crm.order_level_discount') }}</option>
                        </x-ui.odoo-form-ui>

                        <!-- Tax Option -->
                        <x-ui.odoo-form-ui type="select" :label="__('crm.tax_option')" name="tax_type" id="taxTypeSelect" :required="true">
                            <option value="without_tax" @selected(old('tax_type', $salesOrder?->tax_type) === 'without_tax')>{{ __('crm.without_tax') }}</option>
                            <option value="item_wise_tax" @selected(old('tax_type', $salesOrder?->tax_type ?? 'item_wise_tax') === 'item_wise_tax')>{{ __('crm.item_wise_tax') }}</option>
                            <option value="order_wise_tax" @selected(old('tax_type', $salesOrder?->tax_type) === 'order_wise_tax')>{{ __('crm.order_wise_tax') }}</option>
                        </x-ui.odoo-form-ui>

                        <!-- GST Option (CGST/SGST vs IGST) -->
                        <x-ui.odoo-form-ui type="select" :label="__('crm.gst_option')" name="gst_type" id="gstTypeSelect" :required="true">
                            <option value="cgst_sgst" @selected(old('gst_type', $salesOrder?->gst_type ?? 'cgst_sgst') === 'cgst_sgst')>{{ __('crm.intra_state_gst') }}</option>
                            <option value="igst" @selected(old('gst_type', $salesOrder?->gst_type) === 'igst')>{{ __('crm.inter_state_gst') }}</option>
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="select" :label="__('crm.freight_terms')" name="freight_terms" id="invFreightTermsSelect">
                            <option value="To Pay" @selected(old('freight_terms', $dispatchOrder?->freight_terms ?? $salesOrder?->freight_terms ?? 'To Pay') == 'To Pay')>{{ __('crm.freight_to_pay') }}</option>
                            <option value="To Be Billed" @selected(old('freight_terms', $dispatchOrder?->freight_terms ?? $salesOrder?->freight_terms ?? '') == 'To Be Billed')>{{ __('crm.freight_to_be_billed') }}</option>
                            <option value="Prepaid" @selected(old('freight_terms', $dispatchOrder?->freight_terms ?? $salesOrder?->freight_terms ?? '') == 'Prepaid')>{{ __('crm.freight_prepaid') }}</option>
                            <option value="Customer Pickup" @selected(old('freight_terms', $dispatchOrder?->freight_terms ?? $salesOrder?->freight_terms ?? '') == 'Customer Pickup')>{{ __('crm.freight_customer_pickup') }}</option>
                        </x-ui.odoo-form-ui>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" inputType="number" :label="__('crm.freight_amount') . ' (' . active_currency_symbol() . ')'" name="freight_amount" id="invFreightAmountInput" :value="old('freight_amount', $dispatchOrder?->freight_amount ?? $salesOrder?->freight_amount ?? 0)" min="0" step="0.01" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" :label="__('crm.freight_gst_tax_rate')" name="freight_tax_rate" id="invFreightTaxRateSelect">
                                    <option value="highest" @selected(old('freight_tax_rate', $salesOrder?->freight_tax_rate ?? 'highest') === 'highest')>{{ __('crm.highest_item_rate') }}</option>
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

                <!-- Items Table -->
                <div class="mt-5">
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                        <h5 class="fw-bold text-dark mb-0 fs-14"><i class="feather-layers text-primary me-2"></i>{{ __('crm.invoice_line_items') }}</h5>
                        <div class="d-flex align-items-center gap-2" style="width: 420px;">
                            <div class="input-group input-group-sm shadow-2xs rounded overflow-hidden" style="border: 1px solid #cbd5e1 !important;">
                                <span class="input-group-text bg-primary text-white border-0 px-3 fw-semibold"><i class="feather-camera me-1"></i> Barcode</span>
                                <input type="text" id="fastBarcodeScanInput" class="form-control border-0 bg-white" placeholder="{{ __('crm.scan_barcode_sku_ph') }}" autocomplete="off" style="font-size: 13px;">
                                <button type="button" class="btn btn-primary border-0 px-3" id="fastBarcodeScanBtn"><i class="feather-search"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table" id="invoiceItemsTable">
                            <thead>
                                <tr>
                                    <th style="width: 32%;">{{ __('crm.product') }} <span class="text-danger">*</span></th>
                                    <th class="text-end" style="width: 10%;">{{ __('crm.qty') }} <span class="text-danger">*</span></th>
                                    <th class="text-end" style="width: 12%;">{{ __('crm.rate') }} ({{ active_currency_symbol() }}) <span class="text-danger">*</span></th>
                                    <th class="text-end" style="width: 12%;">{{ __('crm.amount') }} ({{ active_currency_symbol() }})</th>
                                    <th class="text-end discount-column" style="width: 10%;">{{ __('crm.disc') }} ({{ active_currency_symbol() }})</th>
                                    <th class="text-end tax-column" style="width: 10%;">{{ __('crm.tax_rate') }}</th>
                                    <th class="text-end pe-3" style="width: 14%;">{{ __('crm.total_amount') }} ({{ active_currency_symbol() }})</th>
                                    <th style="width: 3%;"></th>
                                </tr>
                            </thead>
                            <tbody class="fs-13 text-dark">
                                @forelse ($invoiceItems as $index => $item)
                                    <tr class="item-row" data-index="{{ $index }}">
                                        <td>
                                            @if ($mode === 'direct')
                                                <x-ui.odoo-form-ui type="select" name="items[{{ $index }}][product_id]" class="product-select odoo-select2" :required="true">
                                                    <option value="">{{ __('crm.select_product_ph') }}</option>
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
                                            <x-ui.odoo-form-ui type="input" inputType="number" name="items[{{ $index }}][quantity]" class="text-end qty-input" step="0.0001" min="0.0001" :required="true" :value="(float)$item['quantity']" data-max="{{ isset($item['max_quantity']) ? (float)$item['max_quantity'] : '' }}" />
                                            <div class="qty-error-msg text-danger fs-11 fw-bold text-end mt-1 d-none" style="white-space: normal; line-height: 1.2;"></div>
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
                                <i class="feather-plus me-1"></i> {{ __('crm.add_line') }}
                            </button>
                        </div>
                    @endif
                </div>

                <!-- Bottom Details & Totals Summary -->
                <div class="row mt-5 pt-3 border-top g-4">
                    <div class="col-md-7">
                        <!-- GST Rate Breakdown Card -->
                        <div class="card border-0 shadow-sm mb-3 w-100" id="gstBreakdownContainer" style="border-radius: 8px; border: 1px solid #cbd5e1 !important; overflow: hidden; display: none;">
                            <div class="fw-bold py-2 px-3 bg-light border-bottom text-dark fs-12 text-uppercase d-flex justify-content-between align-items-center">
                                <span><i class="feather-pie-chart text-primary me-1"></i>{{ __('crm.gst_tax_summary') }}</span>
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

                        <x-ui.odoo-form-ui type="editor" :label="__('crm.invoice_notes_terms')" name="notes" editorHeight="ht-150">{!! old('notes', $salesOrder?->terms_conditions ?: ($salesOrder?->notes ?: '')) !!}</x-ui.odoo-form-ui>
                    </div>

                    <!-- Right Side: Order Summary Card -->
                    <div class="col-md-5 d-flex flex-column align-items-end fs-13">
                        <div class="card shadow-sm border-0 rounded-3 overflow-hidden w-100" style="border: 1px solid #cbd5e1 !important;">
                            <div class="fw-bold py-2.5 px-3 text-white" style="background-color: #2563eb; font-size: 12px; letter-spacing: 0.5px; text-transform: uppercase;">
                                {{ __('crm.financial_summary') }}
                            </div>
                            <div class="p-3 bg-white text-dark">
                                
                                <!-- 1. Subtotal (Excl. Tax) -->
                                <div class="d-flex justify-content-between align-items-center mb-3" id="summarySubtotalRow">
                                    <span class="text-muted fs-13 fw-semibold">{{ __('crm.subtotal_excl_tax') }}</span>
                                    <input type="text" id="calcSubtotal" class="form-control form-control-sm text-end fw-bold" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; color: #334155; background-color: #f8fafc;" readonly value="0.00">
                                </div>

                                <!-- 2. Less: Item Discounts -->
                                <div class="d-flex justify-content-between align-items-center mb-3 d-none" id="summaryDiscountRow">
                                    <span class="text-muted fs-13 fw-semibold" id="summaryDiscountLabel">{{ __('crm.less_item_discounts') }}</span>
                                    <input type="text" id="calcDiscountDisplay" class="form-control form-control-sm text-end fw-bold text-danger" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" readonly value="-0.00">
                                    <input type="number" name="discount_amount" id="summaryDiscount" class="form-control form-control-sm text-end fw-bold text-danger d-none" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px;" value="{{ old('discount_amount', $salesOrder?->discount ?: 0) }}" step="0.01">
                                </div>

                                <!-- 3. Items Taxable Value -->
                                <div class="d-flex justify-content-between align-items-center mb-3" id="calcTaxableRow">
                                    <span class="text-muted fs-13 fw-semibold">{{ __('crm.items_taxable_value') }}</span>
                                    <input type="text" id="calcTaxableAmount" class="form-control form-control-sm text-end fw-bold" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; color: #334155; background-color: #f8fafc;" readonly value="0.00">
                                </div>

                                <!-- 4. Order Tax Rate (%) -->
                                <div class="d-flex justify-content-between align-items-center mb-3 d-none" id="summaryOrderTaxRow">
                                    <span class="text-muted fs-13 fw-semibold">{{ __('crm.order_tax_rate') }}</span>
                                    <input type="number" name="order_tax_rate" id="orderTaxPercent" class="form-control form-control-sm text-end fw-bold" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px;" value="{{ old('order_tax_rate', $salesOrder?->order_tax_rate ?: 18) }}" min="0" max="100" step="0.01">
                                </div>

                                <!-- 5. Add: Items GST Tax -->
                                <div class="d-flex justify-content-between align-items-center mb-3" id="summaryItemsTaxRow">
                                    <span class="text-muted fs-13 fw-semibold">{{ __('crm.add_items_gst_tax') }}</span>
                                    <input type="text" id="summaryItemsTaxText" class="form-control form-control-sm text-end font-monospace text-muted" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" readonly value="+0.00">
                                </div>

                                <!-- 6. Billed Items Total (Incl. GST) -->
                                <div class="d-flex justify-content-between align-items-center mb-3 fw-bold text-dark" id="calcItemsTotalRow">
                                    <span class="fs-13">{{ __('crm.billed_items_total') }}</span>
                                    <input type="text" id="calcItemsTotalInclGst" class="form-control form-control-sm text-end fw-bold text-dark" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f1f5f9;" readonly value="0.00">
                                </div>

                                <!-- FREIGHT BREAKDOWN SECTION -->
                                <div id="summaryFreightSectionContainer">
                                    <hr class="my-2 border-slate">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="text-muted fs-13 fw-semibold">{{ __('crm.freight_charges') }}</span>
                                        <input type="number" id="summaryFreightText" class="form-control form-control-sm text-end fw-bold text-primary" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" min="0" step="0.01" value="{{ old('freight_amount', $dispatchOrder?->freight_amount ?? $salesOrder?->freight_amount ?? 0) }}">
                                        <input type="hidden" name="freight_amount" id="invFreightAmountInput" value="{{ old('freight_amount', $dispatchOrder?->freight_amount ?? $salesOrder?->freight_amount ?? 0) }}">
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-3" id="summaryFreightTaxRow">
                                        <span class="text-muted fs-13 fw-semibold" id="summaryFreightTaxLabel">{{ __('crm.add_freight_gst_tax') }}</span>
                                        <input type="text" id="summaryFreightTaxText" class="form-control form-control-sm text-end font-monospace text-muted" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" readonly value="+0.00">
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-3 fw-bold text-dark" id="summaryFreightTotalRow">
                                        <span class="fs-13">{{ __('crm.total_freight_incl_gst') }}</span>
                                        <input type="text" id="summaryFreightTotalText" class="form-control form-control-sm text-end fw-bold text-primary" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #eff6ff;" readonly value="0.00">
                                    </div>
                                </div>

                                <!-- OVERALL TAX BREAKDOWN -->
                                <hr class="my-2 border-slate">
                                <div id="cgstSgstRows">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="text-muted fs-13 fw-semibold">{{ __('crm.cgst_central_tax') }}</span>
                                        <input type="text" id="summaryCgstText" class="form-control form-control-sm text-end font-monospace text-muted" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" readonly value="+0.00">
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="text-muted fs-13 fw-semibold">{{ __('crm.sgst_state_tax') }}</span>
                                        <input type="text" id="summarySgstText" class="form-control form-control-sm text-end font-monospace text-muted" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" readonly value="+0.00">
                                    </div>
                                </div>

                                <div id="igstRow" style="display: none;">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="text-muted fs-13 fw-semibold">{{ __('crm.igst_integrated_tax') }}</span>
                                        <input type="text" id="summaryIgstText" class="form-control form-control-sm text-end font-monospace text-muted" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" readonly value="+0.00">
                                    </div>
                                </div>

                                <!-- 9. Adjustment -->
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted fs-13 fw-semibold">{{ __('crm.adjustment') }}</span>
                                    <input type="number" name="adjustment" id="adjustmentInput" class="form-control form-control-sm text-end fw-bold" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px;" value="{{ old('adjustment', $salesOrder?->adjustment ?: 0) }}" step="0.01">
                                </div>

                                <!-- 10. Grand Total -->
                                <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-3">
                                    <span class="fw-bold text-primary fs-13">{{ __('crm.grand_total') }}</span>
                                    <input type="text" id="summaryGrandtotalText" class="form-control form-control-sm text-end fw-bold" style="width: 150px; height: 36px; border: 1.5px solid #2563eb; border-radius: 4px; background-color: #eff6ff; color: #2563eb; font-size: 14px; font-weight: 800;" readonly value="0.00">
                                </div>

                                @if ($advanceAllocations > 0)
                                    <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top text-info">
                                        <span class="fs-12 fw-semibold">{{ __('crm.advance_adjusted') }}</span>
                                        <span class="fw-bold">-{{ format_currency($advanceAllocations) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-1 pt-1 text-success">
                                        <span class="fw-bold fs-13">{{ __('crm.balance_due') }}</span>
                                        <span class="fw-extrabold fs-14" id="summaryBalanceDueText">0.00</span>
                                    </div>
                                @endif

                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <x-ui.button href="{{ route('sales.invoices.index') }}" variant="light" size="md" class="border py-2 px-4 fs-12 shadow-sm">{{ __('crm.discard') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="md" class="py-2 px-5 fw-bold fs-12 shadow-sm" style="background-color: #1e40af; border-color: #1e40af;">{{ __('crm.generate_and_save_invoice') }}</x-ui.button>
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
            const currencySymbol = @json(active_currency_symbol());
            const productsList = @json($productsData);
            let directRowIndex = {{ count($invoiceItems) }};
            const advanceAllocated = {{ (float)$advanceAllocations }};

            // Initialize Select2
            function initSelect2(context) {
                $(context).find('.odoo-select2').select2({
                    theme: 'bootstrap-5',
                    width: '100%'
                });
            }
            initSelect2(document);

            // Toggle Columns & Summary Visibility
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
                    $('#calcDiscountDisplay').val('-' + currencySymbol + '0.00');
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
                const urlParams = new URLSearchParams(window.location.search);
                const soId = $('#salesOrderSelect').val() || urlParams.get('sales_order_id') || '{{ $salesOrder?->id ?? "" }}';
                let url = "{{ route('sales.invoices.create') }}?mode=" + $(this).val();
                if (soId) {
                    url += "&sales_order_id=" + soId;
                }
                window.location.href = url;
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

            // Add Direct Product Line
            $('#addDirectRowBtn').on('click', function() {
                let productOptions = '<option value="">{{ __("crm.select_product_ph") }}</option>';
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

            function validateInvoiceQtyRow($tr) {
                const $qtyInput = $tr.find('.qty-input');
                if (!$qtyInput.length) return true;

                const val = parseFloat($qtyInput.val());
                const maxAttr = $qtyInput.attr('data-max') || $qtyInput.data('max');
                const maxQty = (maxAttr !== undefined && maxAttr !== '' && !isNaN(parseFloat(maxAttr))) ? parseFloat(maxAttr) : Infinity;

                let $error = $tr.find('.qty-error-msg');
                if (!$error.length) {
                    $error = $('<div class="qty-error-msg text-danger fs-11 fw-bold text-end mt-1 d-none" style="white-space: normal; line-height: 1.2;"></div>');
                    $qtyInput.after($error);
                }

                let errorMsg = '';
                if (isNaN(val) || val <= 0) {
                    errorMsg = 'Invoice Qty must be greater than 0.';
                } else if (maxQty !== Infinity && val > (maxQty + 0.0001)) {
                    errorMsg = `Qty cannot exceed remaining order/dispatch quantity (Max: ${maxQty}).`;
                }

                if (errorMsg) {
                    $qtyInput.addClass('is-invalid').css({'border-color': '#ef4444', 'color': '#ef4444'});
                    $error.text(errorMsg).removeClass('d-none').show();
                    return false;
                } else {
                    $qtyInput.removeClass('is-invalid').css({'border-color': '', 'color': ''});
                    $error.text('').addClass('d-none').hide();
                    return true;
                }
            }

            function validateAllInvoiceRows() {
                let isValid = true;
                $('#invoiceItemsTable tbody tr.item-row').each(function() {
                    const rowValid = validateInvoiceQtyRow($(this));
                    if (!rowValid) {
                        isValid = false;
                    }
                });

                const $submitBtns = $('#invoiceForm button[type="submit"], button[type="submit"]');
                if (!isValid) {
                    $submitBtns.prop('disabled', true).addClass('disabled opacity-50');
                } else {
                    $submitBtns.prop('disabled', false).removeClass('disabled opacity-50');
                }

                return isValid;
            }

            // Event listeners for recalculating & validation
            $(document).on('input change', '.qty-input', function() {
                validateInvoiceQtyRow($(this).closest('tr'));
                validateAllInvoiceRows();
            });

            $(document).on('input change', '.qty-input, .rate-input, .disc-input, .tax-input, #summaryDiscount, #orderTaxPercent, #invFreightTermsSelect, #invFreightTaxRateSelect, #summaryFreightText, #adjustmentInput', function() {
                recalculateInvoiceTotals();
            });

            $(document).on('click', '.remove-row-btn', function() {
                $(this).closest('tr').remove();
                validateAllInvoiceRows();
                recalculateInvoiceTotals();
            });

            validateAllInvoiceRows();

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
                    $('#calcDiscountDisplay').val('-' + currencySymbol + totalDiscount.toFixed(2));
                } else if (discountType === 'order_wise') {
                    totalDiscount = parseFloat($('#summaryDiscount').val()) || 0;
                    $('#calcDiscountDisplay').val('-' + currencySymbol + totalDiscount.toFixed(2));
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

                $('#summaryItemsTaxText').val('+' + currencySymbol + itemsTaxTotal.toFixed(2));
                $('#summaryFreightTaxText').val('+' + currencySymbol + freightTax.toFixed(2));
                $('#summaryFreightTotalText').val(currencySymbol + totalFreightInclGst.toFixed(2));

                if (taxType !== 'without_tax' && grandTotalTax > 0) {
                    if (gstType === 'igst') {
                        $('#cgstSgstRows').addClass('d-none').hide();
                        $('#igstRow').removeClass('d-none').show();
                        $('#summaryIgstText').val('+' + currencySymbol + grandTotalTax.toFixed(2));
                    } else {
                        $('#cgstSgstRows').removeClass('d-none').show();
                        $('#igstRow').addClass('d-none').hide();
                        const halfTax = grandTotalTax / 2;
                        $('#summaryCgstText').val('+' + currencySymbol + halfTax.toFixed(2));
                        $('#summarySgstText').val('+' + currencySymbol + halfTax.toFixed(2));
                    }
                } else {
                    $('#cgstSgstRows').addClass('d-none').hide();
                    $('#igstRow').addClass('d-none').hide();
                }

                const itemsTotalInclGst = grossBeforeTax + itemsTaxTotal;
                const adjustment = parseFloat($('#adjustmentInput').val()) || 0;
                const grandTotal = itemsTotalInclGst + totalFreightInclGst + adjustment;
                const balanceDue = Math.max(0, grandTotal - advanceAllocated);

                $('#calcSubtotal').val(currencySymbol + subtotal.toFixed(2));
                $('#calcTaxableAmount').val(currencySymbol + grossBeforeTax.toFixed(2));
                $('#calcItemsTotalInclGst').val(currencySymbol + itemsTotalInclGst.toFixed(2));
                $('#summaryGrandtotalText').val(currencySymbol + grandTotal.toFixed(2));
                $('#summaryBalanceDueText').text(currencySymbol + balanceDue.toFixed(2));

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
                                    <td class="py-1 text-end fw-bold text-dark">${currencySymbol}${grp.tax.toFixed(2)}</td>
                                    <td class="py-1 text-end">${currencySymbol}${igstAmt.toFixed(2)}</td>
                                </tr>
                            `;
                        });

                        footerHtml = `
                            <tr>
                                <td class="py-1">Total</td>
                                <td class="py-1 text-end text-primary">${currencySymbol}${totalTaxVal.toFixed(2)}</td>
                                <td class="py-1 text-end">${currencySymbol}${totalIgstVal.toFixed(2)}</td>
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
                                    <td class="py-1 text-end fw-bold text-dark">${currencySymbol}${grp.tax.toFixed(2)}</td>
                                    <td class="py-1 text-end">${currencySymbol}${cgstAmt.toFixed(2)}</td>
                                    <td class="py-1 text-end">${currencySymbol}${sgstAmt.toFixed(2)}</td>
                                </tr>
                            `;
                        });

                        footerHtml = `
                            <tr>
                                <td class="py-1">Total</td>
                                <td class="py-1 text-end text-primary">${currencySymbol}${totalTaxVal.toFixed(2)}</td>
                                <td class="py-1 text-end">${currencySymbol}${totalCgstVal.toFixed(2)}</td>
                                <td class="py-1 text-end">${currencySymbol}${totalSgstVal.toFixed(2)}</td>
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

