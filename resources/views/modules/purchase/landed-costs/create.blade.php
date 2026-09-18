@extends('layouts.duralux')

@section('title', __('purchase.new_landed_cost_voucher') . ' | SaaS ERP')
@section('page-title', __('purchase.create_landed_cost_voucher'))
@section('breadcrumb')
    <a href="{{ route('purchase.landed-costs.index') }}">{{ __('purchase.landed_cost_vouchers') }}</a> &gt; {{ __('purchase.create') }}
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .odoo-sheet {
            background: #ffffff;
            border-radius: 4px;
        }
        .select2-container {
            width: 100% !important;
        }
        .select2-container--bootstrap-5 .select2-selection {
            border: none !important;
            border-bottom: 1px solid #ced4da !important;
            border-radius: 0 !important;
            background-color: transparent !important;
            min-height: 32px !important;
            padding: 0 !important;
        }
        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
            background-color: var(--bs-primary) !important;
            color: #ffffff !important;
            border: none !important;
            border-radius: 3px !important;
            padding: 2px 8px !important;
            font-size: 12px !important;
        }
    </style>
@endpush

@section('content')
    @php
        $transportersGroup = $vendors->filter(fn($v) => $v->is_transporter);
        $suppliersGroup = $vendors->filter(fn($v) => !$v->is_transporter);
    @endphp
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <form method="POST" action="{{ route('purchase.landed-costs.store') }}" id="landedCostForm" class="odoo-sheet">
            @csrf

            <!-- Title & Header Bar -->
            <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom flex-wrap gap-2">
                <div>
                    <small class="text-muted text-uppercase font-monospace fw-bold fs-11">{{ __('purchase.procurement_cost_allocation_sheet') }}</small>
                    <h3 class="fw-bold text-dark mb-0">{{ __('purchase.new_landed_cost_voucher') }}</h3>
                </div>
            </div>

            <!-- Primary 2-Column Fields Grid -->
            <div class="row g-4 mb-4 fs-13">
                <div class="col-md-6 border-end pe-md-4">
                    <h6 class="fw-bold text-primary mb-3"><i class="feather-calendar me-2"></i>{{ __('purchase.voucher_header_details') }}</h6>

                    <x-ui.odoo-form-ui type="input" :label="__('purchase.voucher_date')" name="voucher_date" inputType="date" value="{{ old('voucher_date', date('Y-m-d')) }}" required="true" />

                    <x-ui.odoo-form-ui type="select" :label="__('purchase.select_grns')" name="grn_ids[]" id="grnSelect" multiple="true" required="true">
                        @foreach($grns as $grn)
                            <option value="{{ $grn->id }}">
                                {{ $grn->grn_number }} — {{ $grn->vendor->name ?? 'Vendor' }} ({{ date('d-M-Y', strtotime($grn->received_date)) }})
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>

                <div class="col-md-6 ps-md-4">
                    <h6 class="fw-bold text-primary mb-3"><i class="feather-info me-2"></i>{{ __('purchase.valuation_summary') }}</h6>

                    <x-ui.odoo-form-ui type="input" :label="__('purchase.posting_date')" name="posting_date_dummy" :value="__('purchase.auto_set_on_post')" readonly="true" />

                    <x-ui.odoo-form-ui type="input" :label="__('purchase.total_expenses')" name="total_expenses_dummy" id="totalExpensesDisplay" value="{{ active_currency_symbol() }}0.00" readonly="true" class="fw-bold text-primary font-monospace fs-14" />
                </div>
            </div>

            <!-- Section 1: Additional Procurement Expenses Table -->
            <div class="mt-4 pt-3 border-top">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="fw-bold text-primary mb-0"><i class="feather-layers me-2"></i>1. {{ __('purchase.additional_procurement_expenses') }}</h6>
                </div>

                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table" id="expensesTable">
                        <thead>
                            <tr>
                                <th style="width: 20%">{{ __('purchase.expense_head') }} <span class="text-danger">*</span></th>
                                <th style="width: 22%">{{ __('purchase.transporter_vendor') }}</th>
                                <th style="width: 15%" class="text-end">{{ __('purchase.base_amount') }} ({{ active_currency_symbol() }}) <span class="text-danger">*</span></th>
                                <th style="width: 13%">{{ __('purchase.gst_rate') }}</th>
                                <th style="width: 15%">{{ __('purchase.tax_mechanism') }}</th>
                                <th style="width: 10%">{{ __('purchase.allocation_basis') }}</th>
                                <th style="width: 5%" class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="expensesTbody">
                            <tr class="expense-row">
                                <td>
                                    <x-ui.odoo-form-ui type="select" name="expenses[0][cost_head]" required="true">
                                        <option value="Freight & Transport" selected>{{ __('purchase.freight_and_transport') }}</option>
                                        <option value="Customs Duty">{{ __('purchase.customs_duty') }}</option>
                                        <option value="Loading & Unloading">{{ __('purchase.loading_unloading') }}</option>
                                        <option value="Insurance">{{ __('purchase.insurance') }}</option>
                                        <option value="Handling Charges">{{ __('purchase.handling_charges') }}</option>
                                        <option value="Other Costs">{{ __('purchase.other_costs') }}</option>
                                    </x-ui.odoo-form-ui>
                                </td>
                                <td>
                                    <x-ui.odoo-form-ui type="select" name="expenses[0][vendor_id]">
                                        <option value="">{{ __('purchase.select_vendor_transporter') }}</option>
                                        @if($transportersGroup->isNotEmpty())
                                            <optgroup label="🚛 {{ __('purchase.transporters_logistics') }}">
                                                @foreach($transportersGroup as $v)
                                                    <option value="{{ $v->id }}">{{ $v->name }} ({{ __('purchase.transporter_vendor') }})</option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                        @if($suppliersGroup->isNotEmpty())
                                            <optgroup label="🏭 {{ __('purchase.material_suppliers_vendors') }}">
                                                @foreach($suppliersGroup as $v)
                                                    <option value="{{ $v->id }}">{{ $v->name }} ({{ __('purchase.vendor') }})</option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    </x-ui.odoo-form-ui>
                                </td>
                                <td>
                                    <x-ui.odoo-form-ui type="input" inputType="number" name="expenses[0][amount]" class="text-end expense-amount font-monospace fw-bold" step="0.0001" min="0.0001" placeholder="0.00" required="true" />
                                </td>
                                <td>
                                    <x-ui.odoo-form-ui type="select" name="expenses[0][tax_rate]" class="tax-rate-select">
                                        <option value="0" selected>0% ({{ __('purchase.no_tax') }})</option>
                                        <option value="5">5% GST</option>
                                        <option value="12">12% GST</option>
                                        <option value="18">18% GST</option>
                                        <option value="28">28% GST</option>
                                    </x-ui.odoo-form-ui>
                                </td>
                                <td>
                                    <x-ui.odoo-form-ui type="select" name="expenses[0][gst_type]" class="gst-type-select">
                                        <option value="cgst_sgst" selected>CGST + SGST (FCM)</option>
                                        <option value="igst">IGST (FCM Inter-state)</option>
                                        <option value="rcm_cgst_sgst">RCM Intra-State (CGST + SGST)</option>
                                        <option value="rcm_igst">RCM Inter-State (IGST)</option>
                                    </x-ui.odoo-form-ui>
                                </td>
                                <td>
                                    <x-ui.odoo-form-ui type="select" name="expenses[0][allocation_basis]" class="basis-select" required="true">
                                        <option value="by_qty" selected>{{ __('purchase.by_qty') }}</option>
                                        <option value="by_amount">{{ __('purchase.by_value') }}</option>
                                        <option value="equal">{{ __('purchase.equal') }}</option>
                                    </x-ui.odoo-form-ui>
                                </td>
                                <td class="text-center align-middle">
                                    <button type="button" class="btn btn-link text-danger p-0 border-0 remove-expense-btn disabled" title="{{ __('purchase.remove_line') }}">
                                        <i class="feather-trash-2"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>

                <div class="mt-2 mb-4">
                    <button type="button" class="btn btn-link text-primary p-0 text-decoration-none fw-semibold fs-12" id="addExpenseBtn">
                        <i class="feather-plus me-1"></i>{{ __('purchase.add_expense_line') }}
                    </button>
                </div>
            </div>

            <!-- Section 2: Item Cost Allocation Live Preview -->
            <div class="mt-4 pt-3 border-top">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h6 class="fw-bold text-primary mb-0"><i class="feather-box me-2"></i>2. {{ __('purchase.item_cost_allocation_live_preview') }}</h6>
                        <small class="text-muted fs-12">{{ __('purchase.item_cost_allocation_help') }}</small>
                    </div>
                </div>

                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table" id="allocationPreviewTable">
                        <thead>
                            <tr>
                                <th style="width: 15%">{{ __('purchase.grn_no') }}</th>
                                <th style="width: 25%">{{ __('purchase.product_name') }}</th>
                                <th style="width: 12%" class="text-center">{{ __('purchase.received_qty') }}</th>
                                <th style="width: 13%" class="text-end">{{ __('purchase.base_unit_rate') }} ({{ active_currency_symbol() }})</th>
                                <th style="width: 15%" class="text-end">{{ __('purchase.allocated_extra_cost') }} ({{ active_currency_symbol() }})</th>
                                <th style="width: 20%" class="text-end">{{ __('purchase.new_landed_unit_cost') }} ({{ active_currency_symbol() }})</th>
                            </tr>
                        </thead>
                        <tbody id="previewTbody">
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted fs-13">
                                    <i class="feather-info me-1"></i>{{ __('purchase.select_grns_to_allocate') }}
                                </td>
                            </tr>
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <!-- Notes Section -->
            <div class="mt-4 pt-3 border-top">
                <x-ui.odoo-form-ui type="textarea" :label="__('purchase.notes_remarks')" name="notes" :placeholder="__('purchase.notes_shipment_details_placeholder')" rows="2" />
            </div>

            <!-- Bottom Action Buttons (like Lead form) -->
            <div class="d-flex align-items-center justify-content-end gap-2 mt-4 pt-3 border-top">
                <x-ui.button href="{{ route('purchase.landed-costs.index') }}" variant="light" class="border px-4 py-2 fs-13">
                    {{ __('purchase.discard') }}
                </x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="feather-save" class="px-4 py-2 fs-13 fw-bold shadow-sm">
                    {{ __('purchase.save_draft_voucher') }}
                </x-ui.button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script>
        $(document).ready(function () {
            $('#grnSelect').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: @json(__('purchase.select_grns_placeholder'))
            });

            let expenseRowIndex = 1;
            @php
                $transportersGroup = $vendors->filter(fn($v) => $v->is_transporter);
                $suppliersGroup = $vendors->filter(fn($v) => !$v->is_transporter);
            @endphp
            const vendorsOptions = `
                @if($transportersGroup->isNotEmpty())
                    <optgroup label="🚛 {{ __('purchase.transporters_logistics') }}">
                        @foreach($transportersGroup as $v)
                            <option value="{{ $v->id }}">{{ addslashes($v->name) }} ({{ __('purchase.transporter_vendor') }})</option>
                        @endforeach
                    </optgroup>
                @endif
                @if($suppliersGroup->isNotEmpty())
                    <optgroup label="🏭 {{ __('purchase.material_suppliers_vendors') }}">
                        @foreach($suppliersGroup as $v)
                            <option value="{{ $v->id }}">{{ addslashes($v->name) }} ({{ __('purchase.vendor') }})</option>
                        @endforeach
                    </optgroup>
                @endif
            `;

            // Add Line
            $('#addExpenseBtn').on('click', function () {
                const trHtml = `
                    <tr class="expense-row">
                        <td>
                            <x-ui.odoo-form-ui type="select" name="expenses[${expenseRowIndex}][cost_head]" required="true">
                                <option value="Freight & Transport" selected>{{ __('purchase.freight_and_transport') }}</option>
                                <option value="Customs Duty">{{ __('purchase.customs_duty') }}</option>
                                <option value="Loading & Unloading">{{ __('purchase.loading_unloading') }}</option>
                                <option value="Insurance">{{ __('purchase.insurance') }}</option>
                                <option value="Handling Charges">{{ __('purchase.handling_charges') }}</option>
                                <option value="Other Costs">{{ __('purchase.other_costs') }}</option>
                            </x-ui.odoo-form-ui>
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="select" name="expenses[${expenseRowIndex}][vendor_id]">
                                <option value="">{{ __('purchase.select_vendor_transporter') }}</option>
                                ${vendorsOptions}
                            </x-ui.odoo-form-ui>
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="input" inputType="number" name="expenses[${expenseRowIndex}][amount]" class="text-end expense-amount font-monospace fw-bold" step="0.0001" min="0.0001" placeholder="0.00" required="true" />
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="select" name="expenses[${expenseRowIndex}][tax_rate]" class="tax-rate-select">
                                <option value="0" selected>0% ({{ __('purchase.no_tax') }})</option>
                                <option value="5">5% GST</option>
                                <option value="12">12% GST</option>
                                <option value="18">18% GST</option>
                                <option value="28">28% GST</option>
                            </x-ui.odoo-form-ui>
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="select" name="expenses[${expenseRowIndex}][gst_type]" class="gst-type-select">
                                <option value="cgst_sgst" selected>CGST + SGST (FCM)</option>
                                <option value="igst">IGST (FCM Inter-state)</option>
                                <option value="rcm_cgst_sgst">RCM Intra-State (CGST + SGST)</option>
                                <option value="rcm_igst">RCM Inter-State (IGST)</option>
                            </x-ui.odoo-form-ui>
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="select" name="expenses[${expenseRowIndex}][allocation_basis]" class="basis-select" required="true">
                                <option value="by_qty" selected>{{ __('purchase.by_qty') }}</option>
                                <option value="by_amount">{{ __('purchase.by_value') }}</option>
                                <option value="equal">{{ __('purchase.equal') }}</option>
                            </x-ui.odoo-form-ui>
                        </td>
                        <td class="text-center align-middle">
                            <button type="button" class="btn btn-link text-danger p-0 border-0 remove-expense-btn" title="{{ __('purchase.remove_line') }}">
                                <i class="feather-trash-2"></i>
                            </button>
                        </td>
                    </tr>
                `;
                $('#expensesTbody').append(trHtml);
                expenseRowIndex++;
                updateExpenseButtons();
                recalculateAllocations();
            });

            $(document).on('click', '.remove-expense-btn', function () {
                $(this).closest('tr').remove();
                updateExpenseButtons();
                recalculateAllocations();
            });

            function updateExpenseButtons() {
                const count = $('#expensesTbody tr.expense-row').length;
                if (count <= 1) {
                    $('#expensesTbody tr.expense-row').find('.remove-expense-btn').addClass('disabled');
                } else {
                    $('#expensesTbody tr.expense-row').find('.remove-expense-btn').removeClass('disabled');
                }
            }

            $(document).on('input change', '.expense-amount, .basis-select, .tax-rate-select, .gst-type-select', function () {
                recalculateAllocations();
            });

            let loadedGrnItems = [];

            $('#grnSelect').on('change', function () {
                const grnIds = $(this).val();
                if (!grnIds || grnIds.length === 0) {
                    loadedGrnItems = [];
                    renderPreviewTable();
                    return;
                }

                $.ajax({
                    url: "{{ route('purchase.landed-costs.get-grn-items') }}",
                    method: "GET",
                    data: { grn_ids: grnIds },
                    success: function (res) {
                        loadedGrnItems = res.items || [];
                        recalculateAllocations();
                    },
                    error: function () {
                        loadedGrnItems = [];
                        renderPreviewTable();
                    }
                });
            });

            function recalculateAllocations() {
                let totalExpenses = 0.0;
                let totalTax = 0.0;
                let totalPayable = 0.0;

                $('#expensesTbody tr.expense-row').each(function () {
                    const amt = parseFloat($(this).find('.expense-amount').val()) || 0.0;
                    const taxRate = parseFloat($(this).find('.tax-rate-select').val()) || 0.0;
                    const gstType = $(this).find('.gst-type-select').val() || 'cgst_sgst';
                    const isRcm = gstType === 'rcm_cgst_sgst' || gstType === 'rcm_igst';

                    let taxAmt = 0.0;
                    if (taxRate > 0) {
                        taxAmt = amt * (taxRate / 100);
                    }

                    const linePayable = isRcm ? amt : (amt + taxAmt);

                    totalExpenses += amt;
                    totalTax += taxAmt;
                    totalPayable += linePayable;
                });

                const currSym = @json(active_currency_symbol());
                $('#totalExpensesDisplay').val(currSym + ' ' + totalExpenses.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

                renderPreviewTable(totalExpenses);
            }

            function renderPreviewTable(totalExpenses = 0.0) {
                const $tbody = $('#previewTbody');
                $tbody.empty();

                if (loadedGrnItems.length === 0) {
                    $tbody.append(`
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted fs-13">
                                <i class="feather-info me-1"></i>Select GRN(s) above to view live item cost allocations.
                            </td>
                        </tr>
                    `);
                    return;
                }

                const currSym = @json(active_currency_symbol());
                const totalQty = loadedGrnItems.reduce((acc, item) => acc + item.received_qty, 0);
                const totalAmount = loadedGrnItems.reduce((acc, item) => acc + item.total_amount, 0);

                const expenseRules = [];
                $('#expensesTbody tr.expense-row').each(function () {
                    const amt = parseFloat($(this).find('.expense-amount').val()) || 0.0;
                    const basis = $(this).find('.basis-select').val() || 'by_qty';
                    if (amt > 0) {
                        expenseRules.push({ amount: amt, basis: basis });
                    }
                });

                loadedGrnItems.forEach(function (item) {
                    let allocated = 0.0;

                    expenseRules.forEach(function (rule) {
                        if (rule.basis === 'by_amount' && totalAmount > 0) {
                            allocated += (item.total_amount / totalAmount) * rule.amount;
                        } else if (rule.basis === 'equal' && loadedGrnItems.length > 0) {
                            allocated += rule.amount / loadedGrnItems.length;
                        } else {
                            if (totalQty > 0) {
                                allocated += (item.received_qty / totalQty) * rule.amount;
                            }
                        }
                    });

                    const newTotal = item.total_amount + allocated;
                    const newLandedUnitCost = item.received_qty > 0 ? (newTotal / item.received_qty) : item.unit_rate;

                    const rowHtml = `
                        <tr>
                            <td><span class="font-monospace fw-bold text-primary">${item.grn_number}</span></td>
                            <td>
                                <div class="fw-bold text-dark">${item.product_name}</div>
                                <small class="text-muted font-monospace">SKU: ${item.sku}</small>
                            </td>
                            <td class="text-center fw-semibold">${item.received_qty} ${item.uom}</td>
                            <td class="text-end font-monospace">${currSym} ${item.unit_rate.toFixed(2)}</td>
                            <td class="text-end font-monospace text-primary fw-bold">+ ${currSym} ${allocated.toFixed(2)}</td>
                            <td class="text-end font-monospace text-success fw-bold">${currSym} ${newLandedUnitCost.toFixed(2)} / ${item.uom}</td>
                        </tr>
                    `;
                    $tbody.append(rowHtml);
                });
            }
        });
    </script>
@endpush
