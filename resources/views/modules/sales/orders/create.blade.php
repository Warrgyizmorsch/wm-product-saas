@extends('layouts.duralux')

@section('title', __('crm.create_sales_order') . ' | SaaS ERP')
@section('page-title', __('crm.create_sales_order'))
@section('breadcrumb', __('crm.sales') . ' / ' . __('crm.sales_orders') . ' / ' . __('crm.create'))

@section('page-actions')
    <a href="{{ route('sales.orders.index') }}" class="btn btn-light">
        <i class="feather-arrow-left me-2"></i>{{ __('crm.back_to_listing') }}
    </a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white rounded-3">

        <form action="{{ route('sales.orders.store') }}" method="POST" id="salesOrderForm">
            @csrf
        
        <x-ui.odoo-form-ui type="sheet">
            <div class="d-flex align-items-center mb-4 border-bottom pb-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-3 fs-18">
                        <i class="feather-shopping-bag"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold text-dark mb-0">{{ __('crm.sales_order_details') }}</h4>
                        <span class="text-muted fs-12">{{ __('crm.sales_order_subtitle') }}</span>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4 fs-13 text-dark">
                <!-- Column 1: Customer, Sales Rep & Dates -->
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" :label="__('crm.customer')" name="customer_id" id="customerSelect" :required="true">
                        <option value="">{{ __('crm.select_customer') }}</option>
                        <option value="__ADD_NEW__" class="fw-bold text-primary" data-master="customer">{{ __('crm.add_new_customer') }}</option>
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}"
                                data-billing="{{ $c->billing_address ?? '' }}"
                                data-shipping="{{ $c->shipping_address ?? '' }}"
                                @selected(old('customer_id', $prefillQuotation?->customer_id ?? request('customer_id')) == $c->id)>
                                {{ $c->name }} ({{ $c->email ?: $c->phone ?: 'No Contact' }})
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>

                    <x-ui.odoo-form-ui type="select" :label="__('crm.quotation_ref')" name="quotation_id" id="quotationSelect">
                        <option value="">{{ __('crm.select_quotation_ref') }}</option>
                        @foreach ($quotations as $q)
                            <option value="{{ $q->id }}" @selected(old('quotation_id', $prefillQuotation?->id) == $q->id)>
                                {{ $q->quotation_number }} - {{ $q->customer?->name }} ({{ format_currency($q->total_amount) }})
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>

                    <x-ui.odoo-form-ui type="select" :label="__('crm.sales_rep')" name="sales_person_id">
                        <option value="">{{ __('crm.select_sales_rep') }}</option>
                        @foreach ($salesReps as $u)
                            <option value="{{ $u->id }}" @selected(old('sales_person_id', $prefillQuotation?->sales_person_id) == $u->id)>
                                {{ $u->name }}
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>

                    <x-ui.odoo-form-ui type="input" :label="__('crm.order_number')" name="sales_order_number" :value="old('sales_order_number', $nextOrderNumber)" :readonly="true" :required="true" style="font-weight: bold; color: #495057;" />

                    <x-ui.odoo-form-ui type="input" inputType="date" :label="__('crm.order_date')" name="order_date" :value="old('order_date', date('Y-m-d'))" :required="true" />

                    <x-ui.odoo-form-ui type="input" inputType="date" :label="__('crm.shipment_date')" name="shipment_date" :value="old('shipment_date')" />

                    <x-ui.odoo-form-ui type="select" :label="__('crm.payment_terms')" name="payment_terms" id="paymentTermsSelect">
                        <option value="">{{ __('crm.choose_payment_terms') }}</option>
                        @foreach ($paymentTerms as $pt)
                            <option value="{{ $pt->name }}" data-due-days="{{ $pt->due_days }}" @selected(old('payment_terms', $prefillQuotation?->crmDeal?->payment_terms ?? $prefillQuotation?->payment_terms) == $pt->name)>
                                {{ $pt->display_name }}
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>

                <!-- Column 2: Commercial, Tax & Delivery Options -->
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" :label="__('crm.discount_option')" name="discount_type" id="discountTypeSelect" :required="true">
                        <option value="without_discount" @selected(old('discount_type') === 'without_discount')>{{ __('crm.without_discount') }}</option>
                        <option value="item_wise" @selected(old('discount_type', 'item_wise') === 'item_wise')>{{ __('crm.item_level_discount') }}</option>
                        <option value="order_wise" @selected(old('discount_type') === 'order_wise')>{{ __('crm.order_level_discount') }}</option>
                    </x-ui.odoo-form-ui>

                    <x-ui.odoo-form-ui type="select" :label="__('crm.tax_option')" name="tax_type" id="taxTypeSelect" :required="true">
                        <option value="without_tax" @selected(old('tax_type') === 'without_tax')>{{ __('crm.without_tax') }}</option>
                        <option value="item_wise_tax" @selected(old('tax_type', 'item_wise_tax') === 'item_wise_tax')>{{ __('crm.item_wise_tax') }}</option>
                        <option value="order_wise_tax" @selected(old('tax_type') === 'order_wise_tax')>{{ __('crm.order_wise_tax') }}</option>
                    </x-ui.odoo-form-ui>

                    <div id="gstTypeContainer">
                        <x-ui.odoo-form-ui type="select" :label="__('crm.gst_type')" name="gst_type" id="gstTypeSelect" :required="true">
                            <option value="cgst_sgst" @selected(old('gst_type', 'cgst_sgst') === 'cgst_sgst')>{{ __('crm.intra_state_gst') }}</option>
                            <option value="igst" @selected(old('gst_type') === 'igst')>{{ __('crm.inter_state_gst') }}</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <x-ui.odoo-form-ui type="select" :label="__('crm.freight_terms')" name="freight_terms" id="freightTermsSelect">
                        <option value="To Pay" @selected(old('freight_terms') == 'To Pay')>{{ __('crm.freight_to_pay') }}</option>
                        <option value="To Be Billed" @selected(old('freight_terms') == 'To Be Billed')>{{ __('crm.freight_to_be_billed') }}</option>
                        <option value="Prepaid" @selected(old('freight_terms') == 'Prepaid')>{{ __('crm.freight_prepaid') }}</option>
                        <option value="Customer Pickup" @selected(old('freight_terms') == 'Customer Pickup')>{{ __('crm.freight_customer_pickup') }}</option>
                    </x-ui.odoo-form-ui>

                    <div id="soFreightAmountContainer" class="{{ old('freight_terms') === 'To Be Billed' ? '' : 'd-none' }}">
                        <x-ui.odoo-form-ui type="input" inputType="number" :label="__('crm.freight_amount')" name="freight_amount" id="freightAmountInput" :value="old('freight_amount', 0)" min="0" step="0.01" />
                    </div>
            </div>

            <!-- Address fields -->
            <div class="row g-4 mt-1 border-top pt-3 fs-13 text-dark">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="textarea" :label="__('crm.billing_address')" name="billing_address" rows="2" :placeholder="__('crm.enter_billing_details')">{{ old('billing_address') }}</x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="textarea" :label="__('crm.shipping_address')" name="shipping_address" rows="2" :placeholder="__('crm.enter_shipping_details')">{{ old('shipping_address') }}</x-ui.odoo-form-ui>
                </div>
            </div>

            <!-- Order Lines Table -->
            <div class="border-top pt-4">
                <h5 class="fw-bold text-dark mb-3 fs-14">{{ __('crm.order_lines') }}</h5>
                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table" id="itemsTable">
                        <thead>
                            <tr style="background-color: #f1f5f9;">
                                <th style="width: 38%; min-width: 200px;">{{ __('crm.product_description') }}</th>
                                <th class="text-end" style="width: 9%;">{{ __('crm.quantity') }}</th>
                                <th class="text-end" style="width: 13%;">{{ __('crm.unit_price') }}</th>
                                <th class="text-end col-discount" style="width: 11%;">{{ __('crm.discount') }}</th>
                                <th class="text-end col-tax" style="width: 11%;">{{ __('crm.taxes_percent') }}</th>
                                <th class="text-end pe-3" style="width: 13%;">{{ __('crm.amount') }}</th>
                                <th class="text-center" style="width: 5%;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dynamic Rows -->
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="mt-2.5">
                    <button type="button" class="btn btn-xs btn-outline-primary fw-bold" id="addItemRow" style="font-size: 10px; padding: 2px 8px; text-transform: none !important;">
                        <i class="feather-plus me-1"></i>{{ __('crm.add_a_product') }}
                    </button>
                </div>
            </div>

            <!-- Totals & Sub-elements -->
            <div class="row mt-4 pt-3 border-top text-dark fs-13">
                <div class="col-md-7">
                    <div class="pe-md-4 mb-3">
                        <x-ui.odoo-form-ui type="editor" :label="__('crm.terms_conditions')" name="terms_conditions" editorHeight="ht-150" :errorText="$errors->first('terms_conditions')">{!! old('terms_conditions') !!}</x-ui.odoo-form-ui>
                    </div>
                </div>
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
                                <input type="number" name="discount" id="discountInput" class="form-control form-control-sm text-end fw-bold text-danger d-none" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px;" value="{{ old('discount', $prefillQuotation?->discount ?: 0) }}" step="0.01">
                            </div>

                            <!-- 3. Items Taxable Value -->
                            <div class="d-flex justify-content-between align-items-center mb-3" id="calcTaxableRow">
                                <span class="text-muted fs-13 fw-semibold">{{ __('crm.items_taxable_value') }}</span>
                                <input type="text" id="calcTaxableAmount" class="form-control form-control-sm text-end fw-bold" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; color: #334155; background-color: #f8fafc;" readonly value="0.00">
                            </div>

                            <!-- 4. Order Tax Rate (%) -->
                            <div class="d-flex justify-content-between align-items-center mb-3 d-none" id="summaryOrderTaxRow">
                                <span class="text-muted fs-13 fw-semibold">{{ __('crm.order_tax_rate') }}</span>
                                <input type="number" name="order_tax_rate" id="orderTaxInput" class="form-control form-control-sm text-end fw-bold" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px;" value="{{ old('order_tax_rate', 18) }}" min="0" max="100" step="0.01">
                            </div>

                            <!-- 5. CGST (Central Tax) & SGST (State Tax) -->
                            <div id="cgstSgstRows" style="display: none;">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted fs-13 fw-semibold">{{ __('crm.add_cgst') }}</span>
                                    <input type="text" id="calcCgst" class="form-control form-control-sm text-end font-monospace text-muted" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" readonly value="+0.00">
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted fs-13 fw-semibold">{{ __('crm.add_sgst') }}</span>
                                    <input type="text" id="calcSgst" class="form-control form-control-sm text-end font-monospace text-muted" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" readonly value="+0.00">
                                </div>
                            </div>

                            <!-- 6. IGST (Integrated Tax) -->
                            <div id="igstRow" style="display: none;">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted fs-13 fw-semibold">{{ __('crm.add_igst') }}</span>
                                    <input type="text" id="calcIgst" class="form-control form-control-sm text-end font-monospace text-muted" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" readonly value="+0.00">
                                </div>
                            </div>

                            <!-- 7. Billed Items Total (Incl. GST) -->
                            <div class="d-flex justify-content-between align-items-center mb-3 fw-bold text-dark" id="calcItemsTotalRow">
                                <span class="fs-13">{{ __('crm.billed_items_total') }}</span>
                                <input type="text" id="calcItemsTotalInclGst" class="form-control form-control-sm text-end fw-bold text-dark" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f1f5f9;" readonly value="0.00">
                            </div>

                            <!-- 8. Freight Charges -->
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted fs-13 fw-semibold">{{ __('crm.freight_charges') }}</span>
                                <input type="number" name="freight_amount_display" id="freightAmountDisplay" class="form-control form-control-sm text-end fw-bold text-primary" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px; background-color: #f8fafc;" readonly value="0.00">
                            </div>

                            <!-- 9. Adjustment -->
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted fs-13 fw-semibold">{{ __('crm.adjustment') }}</span>
                                <input type="number" name="adjustment" id="adjustmentInput" class="form-control form-control-sm text-end fw-bold" style="width: 150px; height: 34px; border: 1px solid #cbd5e1; border-radius: 4px;" value="{{ old('adjustment', 0) }}" step="0.01">
                            </div>

                            <!-- 10. Grand Total -->
                            <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-3">
                                <span class="fw-bold text-primary fs-13">{{ __('crm.grand_total') }}</span>
                                <input type="text" id="calcTotal" class="form-control form-control-sm text-end fw-bold" style="width: 150px; height: 36px; border: 1.5px solid #2563eb; border-radius: 4px; background-color: #eff6ff; color: #2563eb; font-size: 14px; font-weight: 800;" readonly value="0.00">
                            </div>                  </div>

                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Form Action Footer -->
            <div class="d-flex justify-content-end align-items-center gap-2 mt-4 pt-3 border-top">
                <a href="{{ route('sales.orders.index') }}" class="btn btn-light border px-4 py-2 fs-13 fw-semibold">
                    {{ __('crm.cancel') }}
                </a>
                <button type="submit" form="salesOrderForm" class="btn btn-primary px-4 py-2 fs-13 fw-bold shadow-sm">
                    <i class="feather-check-circle me-1.5"></i>{{ __('crm.save_sales_order') }}
                </button>
            </div>
        </x-ui.odoo-form-ui>
    </form>

    {{-- Product & Customer quick-create modals --}}
    <x-ui.master-modals :masters="['product', 'customer']" />
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            // Select2 Initialization is automated by odoo-select2 class

            // Auto-fill billing & shipping address on customer change
            $(document).on('change', '#customerSelect', function() {
                var selected = $(this).find('option:selected');
                var billing  = selected.data('billing')  || '';
                var shipping = selected.data('shipping') || '';

                if (billing) {
                    $('textarea[name="billing_address"]').val(billing);
                }
                if (shipping) {
                    $('textarea[name="shipping_address"]').val(shipping);
                }
            });

            // Redirect on quotation reference select
            $('#quotationSelect').on('change', function() {
                const quotationId = $(this).val();
                if (quotationId) {
                    window.location.href = "{{ route('sales.orders.create') }}?quotation_id=" + quotationId;
                } else {
                    window.location.href = "{{ route('sales.orders.create') }}";
                }
            });

            let rowIndex = 0;

            // Load products securely
            @php
                $mappedProducts = $products->map(function($p) {
                    return [
                        'id' => $p->id,
                        'name' => $p->name,
                        'sku' => $p->sku,
                        'selling_price' => $p->selling_price  // Sales price from item master (like Odoo list_price / Zoho item rate)
                    ];
                });
            @endphp
            const productsList = @json($mappedProducts);

            function escapeHtml(string) {
                return String(string).replace(/[&<>"']/g, function (s) {
                    return {
                        "&": "&amp;",
                        "<": "&lt;",
                        ">": "&gt;",
                        '"': '&quot;',
                        "'": '&#39;'
                    }[s];
                });
            }

            const warehousesList = @json($warehouses);

            function buildWarehouseOptions(selectedId = '') {
                let opts = '<option value="">Select Warehouse...</option>';
                warehousesList.forEach(function(w) {
                    const sel = (w.id == selectedId) ? ' selected' : '';
                    opts += `<option value="${w.id}"${sel}>${escapeHtml(w.name)}</option>`;
                });
                return opts;
            }

            function buildProductOptions(selectedId = '') {
                let opts = '<option value="">Select Product...</option>';
                opts += '<option value="__ADD_NEW__" class="fw-bold text-primary" data-master="product">+ Add New Product</option>';
                productsList.forEach(function(p) {
                    const sel = (p.id == selectedId) ? ' selected' : '';
                    opts += `<option value="${p.id}" data-selling-price="${p.selling_price ?? 0}"${sel}>${escapeHtml(p.name)} (${escapeHtml(p.sku)})</option>`;
                });
                return opts;
            }

            function getRowHtml(index, selectedId = '') {
                return `
                    <tr class="item-row" data-row-id="${index}">
                        <td style="width: 38%; min-width: 200px; vertical-align: top; padding: 8px 8px 8px 12px;">
                            <select name="items[${index}][product_id]" class="form-select odoo-table-select item-name-input" data-master="product" required style="width: 100%;">
                                ${buildProductOptions(selectedId)}
                            </select>
                            <div class="description-container mt-2" id="desc-container-${index}" style="display: none;">
                                <textarea name="items[${index}][description]" class="form-control odoo-table-input" placeholder="Scope/details..." style="width: 100%;"></textarea>
                            </div>
                            <a href="javascript:void(0)" class="toggle-desc-btn text-primary fs-11 mt-1 d-inline-block" data-row-id="${index}">
                                <i class="feather-plus me-1"></i>Add Description
                            </a>
                        </td>
                        <td style="width: 9%; text-align: right; vertical-align: middle; padding: 8px 6px;">
                            <input type="number" name="items[${index}][quantity]" class="odoo-table-input text-end qty-input" value="1" min="1" required style="width: 100%; max-width: 90px; margin-left: auto; display: block;">
                        </td>
                        <td style="width: 13%; text-align: right; vertical-align: middle; padding: 8px 6px;">
                            <input type="number" name="items[${index}][unit_price]" class="odoo-table-input text-end price-input" value="0.00" min="0" step="0.01" required style="width: 100%; max-width: 120px; margin-left: auto; display: block;">
                        </td>
                        <td class="col-discount" style="width: 11%; text-align: right; vertical-align: middle; padding: 8px 6px;">
                            <input type="number" name="items[${index}][discount]" class="odoo-table-input text-end line-discount-input" value="0.00" min="0" step="0.01" style="width: 100%; max-width: 100px; margin-left: auto; display: block;">
                        </td>
                        <td class="col-tax" style="width: 11%; text-align: right; vertical-align: middle; padding: 8px 6px;">
                            <input type="number" name="items[${index}][tax_rate]" class="odoo-table-input text-end tax-input" value="18.00" min="0" max="100" step="0.01" style="width: 100%; max-width: 90px; margin-left: auto; display: block;">
                        </td>
                        <td style="width: 13%; text-align: right; vertical-align: middle; padding: 8px 12px 8px 6px;" class="fw-bold text-dark amount-display">
                            {{ active_currency_symbol() }}0.00
                        </td>
                        <td style="width: 5%; text-align: center; vertical-align: middle; padding: 8px 6px;">
                            <button type="button" class="btn btn-icon btn-sm btn-soft-danger remove-row-btn">
                                <i class="feather-trash-2"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }

            // Add row action
            $('#addItemRow').on('click', function() {
                addRow();
            });

            // Toggle Description input visibility
            $(document).on('click', '.toggle-desc-btn', function(e) {
                e.preventDefault();
                const idx = $(this).data('row-id');
                const container = $('#desc-container-' + idx);
                if (container.is(':visible')) {
                    container.slideUp(120);
                    container.find('textarea').val('');
                    $(this).html('<i class="feather-plus me-1"></i>Add Description');
                } else {
                    container.slideDown(120);
                    $(this).html('<i class="feather-minus me-1"></i>Remove Description');
                }
            });

            // Remove row action
            $(document).on('click', '.remove-row-btn', function() {
                const rowsCount = $('.item-row').length;
                if (rowsCount > 1) {
                    $(this).closest('tr').remove();
                    calculateTotals();
                } else {
                    alert('You must include at least one item line in a sales order.');
                }
            });

            const currencySymbol = "{{ active_currency_symbol() }}";

            // Toggle Tax and Discount columns based on dropdown selections
            function toggleTaxAndDiscountOptions() {
                const discountType = $('#discountTypeSelect').val() || 'item_wise';
                const taxType = $('#taxTypeSelect').val() || 'item_wise_tax';

                if (discountType === 'item_wise') {
                    $('.col-discount').removeClass('d-none').show();
                    $('#summaryDiscountRow').removeClass('d-none').show();
                    $('#calcDiscountDisplay').removeClass('d-none').show();
                    $('#discountInput').addClass('d-none').hide();
                } else if (discountType === 'order_wise') {
                    $('.col-discount').addClass('d-none').hide();
                    $('#summaryDiscountRow').removeClass('d-none').show();
                    $('#calcDiscountDisplay').addClass('d-none').hide();
                    $('#discountInput').removeClass('d-none').show();
                } else { // without_discount
                    $('.col-discount').addClass('d-none').hide();
                    $('#summaryDiscountRow').addClass('d-none').hide();
                    $('#discountInput').val('0.00');
                    $('#calcDiscountDisplay').val('-' + currencySymbol + '0.00');
                }

                if (taxType === 'item_wise_tax') {
                    $('.col-tax').removeClass('d-none').show();
                    $('#summaryOrderTaxRow').addClass('d-none').hide();
                    $('#calcTaxableRow').removeClass('d-none').show();
                    $('#gstTypeContainer').removeClass('d-none').show();
                } else if (taxType === 'order_wise_tax') {
                    $('.col-tax').addClass('d-none').hide();
                    $('#summaryOrderTaxRow').removeClass('d-none').show();
                    $('#calcTaxableRow').removeClass('d-none').show();
                    $('#gstTypeContainer').removeClass('d-none').show();
                } else { // without_tax
                    $('.col-tax').addClass('d-none').hide();
                    $('#summaryOrderTaxRow').addClass('d-none').hide();
                    $('#calcTaxableRow').addClass('d-none').hide();
                    $('#gstTypeContainer').addClass('d-none').hide();
                }

                toggleFreightDisplay();
                calculateTotals();
            }

            function toggleFreightDisplay() {
                const freightTerms = $('#freightTermsSelect').val() || 'To Pay';
                if (freightTerms === 'To Be Billed') {
                    $('#soFreightAmountContainer').removeClass('d-none').show();
                    $('#summaryFreightRow').removeClass('d-none').show();
                } else {
                    $('#soFreightAmountContainer').addClass('d-none').hide();
                    $('#summaryFreightRow').addClass('d-none').hide();
                    $('#freightAmountInput').val('0');
                }
            }

            $('#discountTypeSelect, #taxTypeSelect, #gstTypeSelect, #freightTermsSelect').on('change', toggleTaxAndDiscountOptions);

            // Input listeners for calculations
            $(document).on('input change', '.qty-input, .price-input, .tax-input, .line-discount-input, #discountInput, #orderTaxInput, #freightTermsSelect, #freightAmountInput, #adjustmentInput', function() {
                calculateTotals();
            });

            function addRow(item = null) {
                const selectedId = item ? (item.product_id || '') : '';
                const newRow = $(getRowHtml(rowIndex, selectedId));
                $('#itemsTable tbody').append(newRow);

                // Initialize select2 if plugin is loaded
                if (typeof $.fn.select2 === 'function') {
                    newRow.find('.item-name-input').select2({ theme: "bootstrap-5", width: "100%" });
                }

                let isPrefilling = false;
                if (item) {
                    isPrefilling = true;
                    newRow.find('.item-name-input').val(item.product_id).trigger('change');
                    newRow.find('textarea').val(item.description || '');
                    if (item.description) {
                        $('#desc-container-' + rowIndex).show();
                        newRow.find('.toggle-desc-btn').html('<i class="feather-minus me-1"></i>Remove Description');
                    }
                    newRow.find('.qty-input').val(item.quantity);
                    newRow.find('.price-input').val(item.unit_price);
                    newRow.find('.tax-input').val(item.tax_rate);
                    newRow.find('.line-discount-input').val(item.discount || 0);
                    isPrefilling = false;
                }

                // Auto-fill selling price from item master when product is changed (like Odoo/Zoho)
                newRow.find('.item-name-input').on('change', function() {
                    if (isPrefilling) return;
                    const selectedOption = $(this).find('option:selected');
                    const sellingPrice = parseFloat(selectedOption.attr('data-selling-price')) || 0;
                    if (sellingPrice > 0) {
                        $(this).closest('tr').find('.price-input').val(sellingPrice.toFixed(2));
                        calculateTotals();
                    }
                });

                rowIndex++;
                toggleTaxAndDiscountOptions();
            }

            // Live calculations calculation logic
            function calculateTotals() {
                const discountType = $('#discountTypeSelect').val() || 'item_wise';
                const taxType = $('#taxTypeSelect').val() || 'item_wise_tax';

                let subtotal = 0;
                let totalItemDiscount = 0;
                let taxTotal = 0;

                $('.item-row').each(function() {
                    const qty = parseFloat($(this).find('.qty-input').val()) || 0;
                    const price = parseFloat($(this).find('.price-input').val()) || 0;
                    const lineDiscount = (discountType === 'item_wise') ? (parseFloat($(this).find('.line-discount-input').val()) || 0) : 0;
                    const taxRate = (taxType === 'item_wise_tax') ? (parseFloat($(this).find('.tax-input').val()) || 0) : 0;

                    const untaxedAmount = qty * price;
                    const lineTaxable = Math.max(0, untaxedAmount - lineDiscount);
                    const lineTax = lineTaxable * (taxRate / 100);
                    const lineTotalInclTax = lineTaxable + lineTax;

                    subtotal += untaxedAmount;
                    totalItemDiscount += lineDiscount;

                    if (taxType === 'item_wise_tax') {
                        taxTotal += lineTax;
                    }

                    $(this).find('.amount-display').text(currencySymbol + lineTotalInclTax.toFixed(2));
                });

                let discountVal = 0;
                if (discountType === 'item_wise') {
                    discountVal = totalItemDiscount;
                    $('#discountInput').val(discountVal.toFixed(2));
                    $('#calcDiscountDisplay').val('-' + currencySymbol + discountVal.toFixed(2));
                } else if (discountType === 'order_wise') {
                    discountVal = parseFloat($('#discountInput').val()) || 0;
                    $('#calcDiscountDisplay').val('-' + currencySymbol + discountVal.toFixed(2));
                }

                if (taxType === 'order_wise_tax') {
                    const orderTaxRate = parseFloat($('#orderTaxInput').val()) || 0;
                    const taxableAmount = Math.max(0, subtotal - discountVal);
                    taxTotal = taxableAmount * (orderTaxRate / 100);
                } else if (taxType === 'without_tax') {
                    taxTotal = 0;
                }

                const freightTerms = $('#freightTermsSelect').val() || 'To Pay';
                const freightAmount = parseFloat($('#freightAmountInput').val()) || 0;
                const adjustment = parseFloat($('#adjustmentInput').val()) || 0;

                const effectiveFreight = (freightTerms === 'To Be Billed') ? freightAmount : 0;
                $('#freightAmountDisplay').val(effectiveFreight.toFixed(2));

                const itemsTaxTotal = taxTotal;
                const taxableAmount = Math.max(0, subtotal - discountVal);
                const itemsTotalInclGst = taxableAmount + itemsTaxTotal;
                const grandTotal = itemsTotalInclGst + effectiveFreight + adjustment;

                $('#calcSubtotal').val(currencySymbol + subtotal.toFixed(2));
                $('#calcTaxableAmount').val(currencySymbol + taxableAmount.toFixed(2));
                $('#calcTaxAmount').val('+' + currencySymbol + itemsTaxTotal.toFixed(2));
                $('#calcItemsTotalInclGst').val(currencySymbol + itemsTotalInclGst.toFixed(2));

                const gstType = $('#gstTypeSelect').val() || 'cgst_sgst';
                if (taxType !== 'without_tax' && itemsTaxTotal > 0) {
                    if (gstType === 'cgst_sgst') {
                        $('#cgstSgstRows').removeClass('d-none').show();
                        $('#igstRow').addClass('d-none').hide();
                        const halfTax = itemsTaxTotal / 2;
                        $('#calcCgst').val(currencySymbol + halfTax.toFixed(2));
                        $('#calcSgst').val(currencySymbol + halfTax.toFixed(2));
                    } else { // igst
                        $('#cgstSgstRows').addClass('d-none').hide();
                        $('#igstRow').removeClass('d-none').show();
                        $('#calcIgst').val(currencySymbol + itemsTaxTotal.toFixed(2));
                    }
                } else {
                    $('#cgstSgstRows').addClass('d-none').hide();
                    $('#igstRow').addClass('d-none').hide();
                }

                $('#calcTotal').val(currencySymbol + Math.max(0, grandTotal).toFixed(2));
            }

            $('#freightTermsSelect, #freightAmountInput, #discountInput, #adjustmentInput').on('input change', calculateTotals);

            // Customer select address prefill
            $('#customerSelect').on('change', function() {
                const selected = $(this).find('option:selected');
                const billing = selected.attr('data-billing') || '';
                const shipping = selected.attr('data-shipping') || '';
                if (billing && !$('textarea[name="billing_address"]').val()) {
                    $('textarea[name="billing_address"]').val(billing);
                }
                if (shipping && !$('textarea[name="shipping_address"]').val()) {
                    $('textarea[name="shipping_address"]').val(shipping);
                }
            }).trigger('change');

            // Prefill order items
            const prefillItems = @json($prefillQuotation ? $prefillQuotation->items : []);
            if (prefillItems.length > 0) {
                prefillItems.forEach(function(item) {
                    addRow(item);
                });
            } else {
                addRow();
            }

            // Fast Barcode Scan Lookup for Sales Orders
            function handleOrderBarcodeScan() {
                const input = $('#fastBarcodeScanInput');
                const code = input.val().trim();
                if (!code) return;

                $.ajax({
                    url: "{{ route('inventory.products.barcodeLookup') }}",
                    data: { code: code },
                    success: function(res) {
                        if (res.success && res.product) {
                            const prod = res.product;
                            
                            // Ensure product is in JS productsList
                            const exists = productsList.some(p => p.id == prod.id);
                            if (!exists) {
                                productsList.push({
                                    id: prod.id,
                                    name: prod.name,
                                    sku: prod.sku,
                                    selling_price: prod.selling_price || prod.cost_price || prod.unit_cost
                                });
                            }

                            // Look for existing row with same product_id
                            let targetRow = null;
                            $('.item-row').each(function() {
                                const sel = $(this).find('.item-name-input');
                                if (sel.length && sel.val() == prod.id) {
                                    targetRow = $(this);
                                    return false;
                                }
                            });

                            if (targetRow) {
                                const qtyInput = targetRow.find('.qty-input');
                                if (qtyInput.length) {
                                    const currentQty = parseFloat(qtyInput.val()) || 0;
                                    qtyInput.val(currentQty + 1).trigger('input').trigger('change');
                                }
                            } else {
                                // Remove empty placeholder row if exists
                                $('.item-row').each(function() {
                                    const sel = $(this).find('.item-name-input');
                                    if (sel.length && (!sel.val() || sel.val() === '')) {
                                        $(this).remove();
                                    }
                                });

                                // Add populated row
                                addRow({
                                    product_id: prod.id,
                                    quantity: 1,
                                    unit_price: prod.selling_price || prod.cost_price || prod.unit_cost,
                                    tax_rate: prod.gst_rate || 18,
                                    discount: 0
                                });
                            }

                            calculateTotals();
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
                    handleOrderBarcodeScan();
                }
            });
            $('#fastBarcodeScanBtn').on('click', function(e) {
                e.preventDefault();
                handleOrderBarcodeScan();
            });
        });
    </script>
@endpush
