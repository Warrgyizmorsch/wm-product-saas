@extends('layouts.duralux')

@section('title', 'Edit & Revise Quotation | SaaS ERP')
@section('page-title', 'Create Quotation Revision')
@section('breadcrumb', 'Edit Quotation')

@section('page-actions')
    <a href="{{ $quotation->deal ? route('crm.deals.show', $quotation->deal->id) : route('crm.quotations.show', $quotation) }}" class="btn btn-light">
        <i class="feather-arrow-left me-1"></i>Back
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm p-4 p-md-5 bg-white">
                <form action="{{ route('crm.quotations.update', $quotation->id) }}" method="POST" id="quotationEditForm" class="odoo-sheet" novalidate>
                    @csrf
                    @method('PUT')

                    @if($quotation->crm_deal_id)
                        <input type="hidden" name="crm_deal_id" value="{{ $quotation->crm_deal_id }}">
                    @endif
                    @if($quotation->crm_account_id)
                        <input type="hidden" name="crm_account_id" value="{{ $quotation->crm_account_id }}">
                    @endif
                    @if($quotation->lead_id)
                        <input type="hidden" name="lead_id" value="{{ $quotation->lead_id }}">
                    @endif

                    <!-- Header & Revision Info Banner -->
                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3 flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-text avatar-md bg-soft-warning text-warning rounded-3 fs-18">
                                <i class="feather-edit-3"></i>
                            </div>
                            <div>
                                <h4 class="fw-bold text-dark mb-0">Create Revision {{ $nextRevisionNumber }} ({{ $newQuotationNumber }})</h4>
                                <span class="text-muted fs-12">
                                    Updating Quotation <strong class="text-primary">{{ $quotation->quotation_number }}</strong> will automatically create Revision #{{ $nextRevisionNumber }}.
                                </span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-soft-warning text-warning border font-monospace px-3 py-2 fs-13 fw-bold">{{ $newQuotationNumber }}</span>
                            <input type="hidden" name="quotation_number" value="{{ $newQuotationNumber }}">
                        </div>
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-danger py-2 px-3 mb-4 fs-12 shadow-sm border-0 bg-soft-danger text-danger rounded-3">
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Master Details Section -->
                    <div class="row g-4 mb-4 fs-13 text-dark">
                        <!-- Left Column: Customer & Salesperson -->
                        <div class="col-lg-6 border-end">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-user me-2"></i>Customer Information</h6>
                            
                            @php
                                $clientName = $quotation->account ? $quotation->account->name : ($quotation->lead ? ($quotation->lead->company_name ?: $quotation->lead->contact_person) : 'Client');
                            @endphp

                            <x-ui.odoo-form-ui type="input" :label="__('crm.customer')" name="_customer_display"
                                :value="$clientName" readonly="true"
                                style="font-weight: bold; color: var(--bs-primary);" />

                            <x-ui.odoo-form-ui type="select" :label="__('crm.sales_person')" name="sales_person_id">
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected(old('sales_person_id', $quotation->sales_person_id) == $user->id)>
                                        {{ $user->name }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <!-- Right Column: Quotation Dates & Status -->
                        <div class="col-lg-6">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-calendar me-2"></i>Quotation Specifications</h6>

                            <x-ui.odoo-form-ui type="input" inputType="date" :label="__('crm.date')" name="quotation_date"
                                :value="old('quotation_date', $quotation->quotation_date ? \Illuminate\Support\Carbon::parse($quotation->quotation_date)->format('Y-m-d') : date('Y-m-d'))" required="true" />

                            <x-ui.odoo-form-ui type="input" inputType="date" :label="__('crm.expiration')" name="expiry_date"
                                :value="old('expiry_date', $quotation->expiry_date ? \Illuminate\Support\Carbon::parse($quotation->expiry_date)->format('Y-m-d') : date('Y-m-d', strtotime('+30 days')))" />

                            <x-ui.odoo-form-ui type="select" :label="__('crm.status')" name="status" required="true">
                                <option value="Draft" @selected(old('status', $quotation->status) === 'Draft')>{{ __('crm.quotation_statuses.Draft') }}</option>
                                <option value="Pending Approval" @selected(old('status', $quotation->status) === 'Pending Approval')>Sent for Approval</option>
                                <option value="Approved" @selected(old('status', $quotation->status) === 'Approved')>Approved</option>
                                <option value="Sent" @selected(old('status', $quotation->status) === 'Sent')>Sent to Client</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <!-- Order Lines Table -->
                    <div class="border-top pt-4 mb-4">
                        <div class="mb-3">
                            <h5 class="fw-bold text-dark mb-0 fs-14"><i class="feather-box me-2 text-primary"></i>{{ __('crm.order_lines') }}</h5>
                        </div>

                        <div class="table-responsive">
                            <x-ui.odoo-form-ui type="table" id="itemsTable">
                                <thead>
                                    <tr>
                                        <th style="width: 45%;">{{ __('crm.product_description') }}</th>
                                        <th class="text-end" style="width: 12%;">{{ __('crm.quantity') }}</th>
                                        <th class="text-end" style="width: 15%;">{{ __('crm.unit_price') }} (₹)</th>
                                        <th class="text-end" style="width: 12%;">{{ __('crm.taxes') }} (%)</th>
                                        <th class="text-end pe-3" style="width: 16%;">{{ __('crm.amount') }}</th>
                                        <th class="text-center" style="width: 5%;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($quotation->items as $idx => $item)
                                        <tr class="item-row">
                                            <td>
                                                <select name="items[{{ $idx }}][product_id]" class="odoo-table-select product-select" required>
                                                    <option value="" disabled>-- Select Product --</option>
                                                    @foreach ($products as $p)
                                                        <option value="{{ $p->id }}" @selected($item->product_id == $p->id)>
                                                            {{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="text-end">
                                                <input type="number" name="items[{{ $idx }}][quantity]" class="odoo-table-input item-qty text-end" value="{{ $item->quantity }}" min="1" required>
                                            </td>
                                            <td class="text-end">
                                                <input type="number" name="items[{{ $idx }}][unit_price]" class="odoo-table-input item-price text-end" value="{{ $item->unit_price }}" step="0.01" min="0" required>
                                            </td>
                                            <td class="text-end">
                                                <input type="number" name="items[{{ $idx }}][tax_rate]" class="odoo-table-input item-tax text-end" value="{{ $item->tax_rate ?: 18 }}" step="0.01" min="0">
                                            </td>
                                            <td class="text-end fw-bold item-amount pe-3" style="padding-top: 8px;">
                                                ₹{{ number_format(($item->quantity * $item->unit_price) * (1 + (($item->tax_rate ?: 18) / 100)), 2) }}
                                            </td>
                                            <td class="text-center" style="padding-top: 8px;">
                                                <button type="button" class="btn btn-link text-danger p-0 remove-row" title="Remove"><i class="feather-trash-2 fs-14"></i></button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="mt-2.5">
                            <button type="button" class="btn btn-xs btn-outline-primary fw-bold" id="addItemRow" style="font-size: 11px; padding: 3px 10px; text-transform: none !important;">
                                <i class="feather-plus me-1"></i>Add a product
                            </button>
                        </div>
                    </div>

                    <!-- Subtotal / Discount / Totals -->
                    <div class="row mt-4 pt-3 border-top text-dark fs-13">
                        <div class="col-md-7">
                            <div class="pe-md-4">
                                <x-ui.odoo-form-ui type="editor" :label="__('crm.terms_conditions')" name="terms_conditions" editorHeight="ht-150" :errorText="$errors->first('terms_conditions')">{!! old('terms_conditions', $quotation->terms_conditions) !!}</x-ui.odoo-form-ui>
                                <x-ui.odoo-form-ui type="textarea" :label="__('crm.notes')" name="notes" rows="2" placeholder="Internal notes or customer special requirements...">{{ old('notes', $quotation->notes) }}</x-ui.odoo-form-ui>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="pe-md-2">
                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                    <span class="text-muted fw-semibold fs-13">{{ __('crm.untaxed_amount') }}:</span>
                                    <span class="fw-bold text-dark fs-14" id="calcSubtotal">₹{{ number_format($quotation->subtotal, 2) }}</span>
                                </div>

                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                    <span class="text-muted fw-semibold fs-13">{{ __('crm.taxes') }}:</span>
                                    <span class="fw-bold text-dark fs-14" id="calcTax">₹{{ number_format($quotation->tax, 2) }}</span>
                                </div>

                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                    <span class="text-muted fw-semibold fs-13">{{ __('crm.discount_colon') }}</span>
                                    <input type="number" name="discount" id="discountInput" class="odoo-table-input text-end fw-bold fs-13" style="width: 120px;" value="{{ old('discount', $quotation->discount ?: 0) }}" min="0" step="0.01">
                                </div>

                                <div class="d-flex justify-content-between align-items-center py-3 border-top border-2 mt-3" style="border-top: 2px solid var(--bs-primary) !important;">
                                    <span class="text-dark fw-bold fs-15 text-uppercase">{{ __('crm.total_colon') }}</span>
                                    <span class="fw-extrabold text-primary fs-20" id="calcTotal">₹{{ number_format($quotation->total_amount, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Action Buttons -->
                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="{{ $quotation->deal ? route('crm.deals.show', $quotation->deal->id) : route('crm.quotations.show', $quotation) }}" class="btn btn-md btn-light border py-2 px-4 shadow-sm fs-13">
                            {{ __('crm.discard') }}
                        </a>
                        <button type="submit" class="btn btn-md btn-warning text-dark py-2 px-5 fw-bold shadow-sm fs-13">
                            <i class="feather-save me-1.5"></i>Save as {{ $newQuotationNumber }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            var productsData = {
                @foreach ($products as $p)
                    "{{ $p->id }}": {
                        price: {{ (float) ($p->price ?: 0) }},
                        tax_rate: {{ (float) ($p->tax_rate ?: 18) }}
                    },
                @endforeach
            };

            var itemIndex = {{ count($quotation->items) }};

            function addRow(productId = '', qty = 1, price = 0, taxRate = 18) {
                var rowId = 'row_' + itemIndex;
                var html = `
                    <tr id="${rowId}" class="item-row">
                        <td>
                            <select name="items[${itemIndex}][product_id]" class="odoo-table-select product-select" required>
                                <option value="" disabled ${!productId ? 'selected' : ''}>-- Select Product --</option>
                                @foreach ($products as $p)
                                    <option value="{{ $p->id }}" ${productId == '{{ $p->id }}' ? 'selected' : ''}>
                                        {{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td class="text-end">
                            <input type="number" name="items[${itemIndex}][quantity]" class="odoo-table-input item-qty text-end" value="${qty}" min="1" required>
                        </td>
                        <td class="text-end">
                            <input type="number" name="items[${itemIndex}][unit_price]" class="odoo-table-input item-price text-end" value="${price}" step="0.01" min="0" required>
                        </td>
                        <td class="text-end">
                            <input type="number" name="items[${itemIndex}][tax_rate]" class="odoo-table-input item-tax text-end" value="${taxRate}" step="0.01" min="0">
                        </td>
                        <td class="text-end fw-bold item-amount pe-3" style="padding-top: 8px;">
                            ₹0.00
                        </td>
                        <td class="text-center" style="padding-top: 8px;">
                            <button type="button" class="btn btn-link text-danger p-0 remove-row" title="Remove"><i class="feather-trash-2 fs-14"></i></button>
                        </td>
                    </tr>
                `;
                $('#itemsTable tbody').append(html);
                itemIndex++;
                calculateTotals();
            }

            $('#addItemRow').on('click', function() {
                addRow();
            });

            $(document).on('click', '.remove-row', function() {
                if ($('#itemsTable tbody tr').length > 1) {
                    $(this).closest('tr').remove();
                    calculateTotals();
                }
            });

            $(document).on('change', '.product-select', function() {
                var pId = $(this).val();
                var row = $(this).closest('tr');
                if (pId && productsData[pId]) {
                    row.find('.item-price').val(productsData[pId].price);
                    row.find('.item-tax').val(productsData[pId].tax_rate);
                }
                calculateTotals();
            });

            $(document).on('input change', '.item-qty, .item-price, .item-tax, #discountInput', function() {
                calculateTotals();
            });

            function calculateTotals() {
                var subtotal = 0;
                var totalTax = 0;

                $('#itemsTable tbody tr').each(function() {
                    var qty = parseFloat($(this).find('.item-qty').val()) || 0;
                    var price = parseFloat($(this).find('.item-price').val()) || 0;
                    var taxRate = parseFloat($(this).find('.item-tax').val()) || 0;

                    var lineTotal = qty * price;
                    var lineTax = lineTotal * (taxRate / 100);

                    subtotal += lineTotal;
                    totalTax += lineTax;

                    $(this).find('.item-amount').text('₹' + (lineTotal + lineTax).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                });

                var discount = parseFloat($('#discountInput').val()) || 0;
                var grandTotal = Math.max(0, subtotal + totalTax - discount);

                $('#calcSubtotal').text('₹' + subtotal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                $('#calcTax').text('₹' + totalTax.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                $('#calcTotal').text('₹' + grandTotal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            }

            calculateTotals();
        });
    </script>
@endpush
