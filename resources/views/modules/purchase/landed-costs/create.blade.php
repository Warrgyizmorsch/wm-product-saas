@extends('layouts.duralux')

@section('title', 'New Landed Cost Voucher | SaaS ERP')
@section('page-title', 'Create Landed Cost Voucher')
@section('breadcrumb')
    <a href="{{ route('purchase.landed-costs.index') }}">Landed Cost Vouchers</a> &gt; Create
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
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <form method="POST" action="{{ route('purchase.landed-costs.store') }}" id="landedCostForm" class="odoo-sheet">
            @csrf

            <!-- Title & Header Bar -->
            <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom flex-wrap gap-2">
                <div>
                    <small class="text-muted text-uppercase font-monospace fw-bold fs-11">Procurement Cost Allocation Sheet</small>
                    <h3 class="fw-bold text-dark mb-0">New Landed Cost Voucher</h3>
                </div>

                <div class="d-flex gap-2">
                    <x-ui.button href="{{ route('purchase.landed-costs.index') }}" variant="light" size="sm">
                        Discard
                    </x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="sm" icon="feather-save">
                        Save Draft Voucher
                    </x-ui.button>
                </div>
            </div>

            <!-- Primary 2-Column Fields Grid -->
            <div class="row g-4 mb-4 fs-13">
                <div class="col-md-6 border-end pe-md-4">
                    <h6 class="fw-bold text-primary mb-3"><i class="feather-calendar me-2"></i>Voucher Header Details</h6>

                    <x-ui.odoo-form-ui type="input" label="Voucher Date" name="voucher_date" inputType="date" value="{{ old('voucher_date', date('Y-m-d')) }}" required="true" />

                    <x-ui.odoo-form-ui type="select" label="Select GRN(s)" name="grn_ids[]" id="grnSelect" multiple="true" required="true">
                        @foreach($grns as $grn)
                            <option value="{{ $grn->id }}">
                                {{ $grn->grn_number }} — {{ $grn->vendor->name ?? 'Vendor' }} ({{ date('d-M-Y', strtotime($grn->received_date)) }})
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>

                <div class="col-md-6 ps-md-4">
                    <h6 class="fw-bold text-primary mb-3"><i class="feather-info me-2"></i>Valuation Summary</h6>

                    <x-ui.odoo-form-ui type="input" label="Posting Date" name="posting_date_dummy" value="[Auto-set on Post]" readonly="true" />

                    <x-ui.odoo-form-ui type="input" label="Total Expenses" name="total_expenses_dummy" id="totalExpensesDisplay" value="₹0.00" readonly="true" class="fw-bold text-primary font-monospace fs-14" />
                </div>
            </div>

            <!-- Section 1: Additional Procurement Expenses Table -->
            <div class="mt-4 pt-3 border-top">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="fw-bold text-primary mb-0"><i class="feather-layers me-2"></i>1. Additional Procurement Expenses</h6>
                </div>

                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table" id="expensesTable">
                        <thead>
                            <tr>
                                <th style="width: 20%">Expense Head <span class="text-danger">*</span></th>
                                <th style="width: 22%">Vendor / Transporter</th>
                                <th style="width: 15%" class="text-end">Base Amount (₹) <span class="text-danger">*</span></th>
                                <th style="width: 13%">GST Rate (%)</th>
                                <th style="width: 15%">Tax Mechanism</th>
                                <th style="width: 10%">Allocation Basis</th>
                                <th style="width: 5%" class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="expensesTbody">
                            <tr class="expense-row">
                                <td>
                                    <x-ui.odoo-form-ui type="select" name="expenses[0][cost_head]" required="true">
                                        <option value="Freight & Transport" selected>Freight &amp; Transport</option>
                                        <option value="Customs Duty">Customs Duty</option>
                                        <option value="Loading & Unloading">Loading &amp; Unloading</option>
                                        <option value="Insurance">Insurance</option>
                                        <option value="Handling Charges">Handling Charges</option>
                                        <option value="Other Costs">Other Costs</option>
                                    </x-ui.odoo-form-ui>
                                </td>
                                <td>
                                    <x-ui.odoo-form-ui type="select" name="expenses[0][vendor_id]">
                                        <option value="">Select Vendor / Transporter...</option>
                                        @foreach($vendors as $v)
                                            <option value="{{ $v->id }}">{{ $v->name }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </td>
                                <td>
                                    <x-ui.odoo-form-ui type="input" inputType="number" name="expenses[0][amount]" class="text-end expense-amount font-monospace fw-bold" step="0.0001" min="0.0001" placeholder="0.00" required="true" />
                                </td>
                                <td>
                                    <x-ui.odoo-form-ui type="select" name="expenses[0][tax_rate]" class="tax-rate-select">
                                        <option value="0" selected>0% (No Tax)</option>
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
                                        <option value="rcm">RCM (Reverse Charge 5%)</option>
                                    </x-ui.odoo-form-ui>
                                </td>
                                <td>
                                    <x-ui.odoo-form-ui type="select" name="expenses[0][allocation_basis]" class="basis-select" required="true">
                                        <option value="by_qty" selected>By Qty</option>
                                        <option value="by_amount">By Value</option>
                                        <option value="equal">Equal</option>
                                    </x-ui.odoo-form-ui>
                                </td>
                                <td class="text-center align-middle">
                                    <button type="button" class="btn btn-link text-danger p-0 border-0 remove-expense-btn disabled" title="Remove Line">
                                        <i class="feather-trash-2"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>

                <div class="mt-2 mb-4">
                    <button type="button" class="btn btn-link text-primary p-0 text-decoration-none fw-semibold fs-12" id="addExpenseBtn">
                        <i class="feather-plus me-1"></i>Add an expense line
                    </button>
                </div>
            </div>

            <!-- Section 2: Item Cost Allocation Live Preview -->
            <div class="mt-4 pt-3 border-top">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h6 class="fw-bold text-primary mb-0"><i class="feather-box me-2"></i>2. Item Cost Allocation Live Preview</h6>
                        <small class="text-muted fs-12">Real-time breakdown of how extra expenses will be added to each GRN item's cost price.</small>
                    </div>
                </div>

                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table" id="allocationPreviewTable">
                        <thead>
                            <tr>
                                <th style="width: 15%">GRN #</th>
                                <th style="width: 25%">Product Name</th>
                                <th style="width: 12%" class="text-center">Received Qty</th>
                                <th style="width: 13%" class="text-end">Base Unit Rate</th>
                                <th style="width: 15%" class="text-end">Allocated Extra Cost</th>
                                <th style="width: 20%" class="text-end">New Landed Unit Cost</th>
                            </tr>
                        </thead>
                        <tbody id="previewTbody">
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted fs-13">
                                    <i class="feather-info me-1"></i>Select GRN(s) above to view live item cost allocations.
                                </td>
                            </tr>
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <!-- Notes Section -->
            <div class="mt-4 pt-3 border-top">
                <x-ui.odoo-form-ui type="textarea" label="Notes / Remarks" name="notes" placeholder="Add optional remarks or shipment details..." rows="2" />
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
                placeholder: 'Select GRN(s)...'
            });

            let expenseRowIndex = 1;
            const vendorsOptions = `@foreach($vendors as $v)<option value="{{ $v->id }}">{{ addslashes($v->name) }}</option>@endforeach`;

            // Add Line
            $('#addExpenseBtn').on('click', function () {
                const trHtml = `
                    <tr class="expense-row">
                        <td>
                            <x-ui.odoo-form-ui type="select" name="expenses[${expenseRowIndex}][cost_head]" required="true">
                                <option value="Freight & Transport" selected>Freight &amp; Transport</option>
                                <option value="Customs Duty">Customs Duty</option>
                                <option value="Loading & Unloading">Loading &amp; Unloading</option>
                                <option value="Insurance">Insurance</option>
                                <option value="Handling Charges">Handling Charges</option>
                                <option value="Other Costs">Other Costs</option>
                            </x-ui.odoo-form-ui>
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="select" name="expenses[${expenseRowIndex}][vendor_id]">
                                <option value="">Select Vendor / Transporter...</option>
                                ${vendorsOptions}
                            </x-ui.odoo-form-ui>
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="input" inputType="number" name="expenses[${expenseRowIndex}][amount]" class="text-end expense-amount font-monospace fw-bold" step="0.0001" min="0.0001" placeholder="0.00" required="true" />
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="select" name="expenses[${expenseRowIndex}][tax_rate]" class="tax-rate-select">
                                <option value="0" selected>0% (No Tax)</option>
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
                                <option value="rcm">RCM (Reverse Charge 5%)</option>
                            </x-ui.odoo-form-ui>
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="select" name="expenses[${expenseRowIndex}][allocation_basis]" class="basis-select" required="true">
                                <option value="by_qty" selected>By Qty</option>
                                <option value="by_amount">By Value</option>
                                <option value="equal">Equal</option>
                            </x-ui.odoo-form-ui>
                        </td>
                        <td class="text-center align-middle">
                            <button type="button" class="btn btn-link text-danger p-0 border-0 remove-expense-btn" title="Remove Line">
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
                    const isRcm = gstType === 'rcm';

                    let taxAmt = 0.0;
                    if (taxRate > 0) {
                        taxAmt = amt * (taxRate / 100);
                    }

                    const linePayable = isRcm ? amt : (amt + taxAmt);

                    totalExpenses += amt;
                    totalTax += taxAmt;
                    totalPayable += linePayable;
                });

                $('#totalExpensesDisplay').val('₹' + totalExpenses.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

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
                            <td class="text-end font-monospace">₹${item.unit_rate.toFixed(2)}</td>
                            <td class="text-end font-monospace text-primary fw-bold">+ ₹${allocated.toFixed(2)}</td>
                            <td class="text-end font-monospace text-success fw-bold">₹${newLandedUnitCost.toFixed(2)} / ${item.uom}</td>
                        </tr>
                    `;
                    $tbody.append(rowHtml);
                });
            }
        });
    </script>
@endpush
