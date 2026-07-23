@extends('layouts.duralux')

@section('title', 'Create RFQ | SaaS ERP')
@section('page-title', 'New Request for Quotation')
@section('breadcrumb')
    <a href="{{ route('purchase.rfqs.index') }}">RFQs</a> &gt; Create
@endsection

@push('styles')
    <!-- Select2 Theme Styles -->
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
            background-color: #714B67 !important;
            color: #fff !important;
            border: none !important;
            font-size: 11px !important;
            font-weight: 600 !important;
        }
        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove {
            color: #fff !important;
            border-right: 1px solid rgba(255, 255, 255, 0.2) !important;
            margin-right: 5px !important;
        }
        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove:hover {
            background-color: rgba(255, 255, 255, 0.2) !important;
        }
        .selected-vendors-badges .badge {
            background-color: #f1eef1 !important;
            color: #714B67 !important;
            border: 1px solid #e1d6df !important;
            font-weight: 600;
        }
    </style>
@endpush

@section('content')
    <div class="row text-dark">
        <div class="col-12">
            <!-- Professional Flat Form Sheet -->
            <div class="card border-0 shadow-sm p-4 p-md-5 bg-white">
                <form action="{{ route('purchase.rfqs.store') }}" method="POST" id="createRfqForm" class="odoo-sheet">
                    @csrf

                    <!-- Top buttons bar -->
                    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom flex-wrap gap-2">
                        <div>
                            <h4 class="fw-bold text-dark mb-0">Create Request for Quotation</h4>
                            <small class="text-muted fs-12">Send price and terms inquiries to multiple suppliers. You can pull items from an approved PR.</small>
                        </div>
                        <div class="d-flex gap-2">
                            <x-ui.button href="{{ route('purchase.rfqs.index') }}" variant="light" size="sm">
                                Cancel
                            </x-ui.button>
                            <x-ui.button type="submit" variant="primary" size="sm" icon="feather-save" style="background-color: #714B67; border-color: #714B67;">
                                Save RFQ
                            </x-ui.button>
                        </div>
                    </div>

                    <div class="row g-4 fs-13 text-dark">
                        <!-- Left Column -->
                        <div class="col-md-6 border-end">
                            <h6 class="fw-bold text-primary mb-3">General Information</h6>

                            <x-ui.odoo-form-ui type="input" label="RFQ Date" name="rfq_date" inputType="date" :value="old('rfq_date', date('Y-m-d'))" required="true" />
                            
                            <x-ui.odoo-form-ui type="select" label="Source Requisition" name="purchase_requisition_id" id="requisitionSelect" class="select2-simple">
                                <option value="">Select Approved PR (Optional)...</option>
                                @foreach($requisitions as $pr)
                                    <option value="{{ $pr->id }}" @selected($selectedRequisitionId == $pr->id)>
                                        {{ $pr->requisition_number }} (Requested by: {{ $pr->requester?->name ?? '—' }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <!-- Right Column -->
                        <div class="col-md-6">
                            <h6 class="fw-bold text-primary mb-3">Additional Details</h6>
                            <x-ui.odoo-form-ui type="textarea" label="Notes" name="notes" rows="6" placeholder="Terms of delivery, special requests, remarks, etc.">{{ old('notes') }}</x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <!-- Line Items Section -->
                    <div class="mt-5">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <h5 class="fw-bold text-dark mb-0"><i class="feather-layers text-primary me-2"></i>Inquiry Line Items</h5>
                            
                            <!-- Bulk Supplier Assignment controls -->
                            <div class="d-flex align-items-center gap-2 bg-light p-2 rounded border shadow-sm">
                                <span class="fs-12 fw-bold text-muted text-uppercase me-1"><i class="feather-truck text-primary me-1"></i>Bulk Supplier:</span>
                                <div style="width: 220px;">
                                    <select id="bulkSupplierSelect" class="form-select form-select-sm fw-semibold select2-simple">
                                        <option value="">Select Supplier...</option>
                                        @foreach($vendors as $v)
                                            <option value="{{ $v->id }}">{{ $v->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="button" class="btn btn-sm btn-primary px-3 fw-bold d-flex align-items-center gap-1" id="bulkAddSupplierBtn" style="background-color: #714B67; border-color: #714B67;">
                                    <i class="feather-user-plus fs-12"></i> Add
                                </button>
                                <button type="button" class="btn btn-sm btn-soft-danger px-3 fw-bold d-flex align-items-center gap-1" id="bulkRemoveSupplierBtn">
                                    <i class="feather-user-minus fs-12"></i> Remove
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive border rounded shadow-sm">
                            <table class="odoo-table mb-0" id="rfqItemsTable" style="table-layout: fixed; width: 100%;">
                                <thead class="bg-light">
                                    <tr>
                                        <th style="width: 4%;" class="text-center">
                                            <input type="checkbox" id="selectAllItems" class="form-check-input">
                                        </th>
                                        <th style="width: 32%">Product <span class="text-danger">*</span></th>
                                        <th style="width: 30%">Assigned Suppliers <span class="text-danger">*</span></th>
                                        <th class="text-end" style="width: 14%">Quantity <span class="text-danger">*</span></th>
                                        <th class="text-end" style="width: 15%">Est. Cost (₹)</th>
                                        <th class="text-center" style="width: 5%"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Prefilled items if linked to PR -->
                                    @forelse($prefilledItems as $index => $item)
                                        <tr class="item-row" data-index="{{ $index }}">
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input row-item-checkbox">
                                            </td>
                                            <td>
                                                <select name="items[{{ $index }}][product_id]" required class="odoo-table-select product-select select2-simple">
                                                    <option value="">Select Product...</option>
                                                    @foreach($products as $p)
                                                        <option value="{{ $p->id }}" data-cost="{{ $p->unit_cost ?? 0.00 }}" data-vendor="{{ $p->preferred_vendor_id }}" @selected($item['product_id'] == $p->id)>{!! htmlspecialchars_decode($p->name) !!} ({{ $p->sku ?: 'No SKU' }})</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center w-100 px-1">
                                                    <div class="selected-vendors-badges d-flex flex-wrap gap-1 align-items-center" style="flex-grow: 1;">
                                                        <!-- Badges get rendered here dynamically via JS -->
                                                    </div>
                                                    <select name="items[{{ $index }}][vendor_ids][]" class="vendor-hidden-select d-none" multiple required>
                                                        @foreach($vendors as $v)
                                                            <option value="{{ $v->id }}" @selected(isset($item['vendor_id']) && $item['vendor_id'] == $v->id)>{{ $v->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="number" name="items[{{ $index }}][quantity]" class="odoo-table-input text-end qty-input" step="0.0001" min="0.0001" required value="{{ (float)$item['quantity'] }}" placeholder="0.00">
                                            </td>
                                            <td>
                                                <input type="number" name="items[{{ $index }}][estimated_cost]" class="odoo-table-input text-end cost-input" step="0.01" min="0" value="{{ $item['estimated_cost'] }}" placeholder="0.00">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-link text-danger remove-row-btn p-1 border-0"><i class="feather-trash-2 fs-14"></i></button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr class="item-row" data-index="0">
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input row-item-checkbox">
                                            </td>
                                            <td>
                                                <select name="items[0][product_id]" required class="odoo-table-select product-select select2-simple">
                                                    <option value="">Select Product...</option>
                                                    @foreach($products as $p)
                                                        <option value="{{ $p->id }}" data-cost="{{ $p->unit_cost ?? 0.00 }}" data-vendor="{{ $p->preferred_vendor_id }}">{!! htmlspecialchars_decode($p->name) !!} ({{ $p->sku ?: 'No SKU' }})</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center w-100 px-1">
                                                    <div class="selected-vendors-badges d-flex flex-wrap gap-1 align-items-center" style="flex-grow: 1;">
                                                        <!-- Badges get rendered here dynamically via JS -->
                                                    </div>
                                                    <select name="items[0][vendor_ids][]" class="vendor-hidden-select d-none" multiple required>
                                                        @foreach($vendors as $v)
                                                            <option value="{{ $v->id }}">{{ $v->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="number" name="items[0][quantity]" class="odoo-table-input text-end qty-input" step="0.0001" min="0.0001" required placeholder="0.00">
                                            </td>
                                            <td>
                                                <input type="number" name="items[0][estimated_cost]" class="odoo-table-input text-end cost-input" step="0.01" min="0" placeholder="0.00">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-link text-danger remove-row-btn p-1 border-0" disabled><i class="feather-trash-2 fs-14"></i></button>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            <button type="button" class="btn btn-sm btn-soft-primary px-3 fw-bold" id="addRowBtn">
                                <i class="feather-plus me-1"></i> Add Line
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Row Template for Dynamic Lines -->
    <template id="rowTemplate">
        <tr class="item-row" data-index="__INDEX__">
            <td class="text-center">
                <input type="checkbox" class="form-check-input row-item-checkbox">
            </td>
            <td>
                <select name="items[__INDEX__][product_id]" class="odoo-table-select product-select select2-simple" required>
                    <option value="">Select Product...</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}" data-cost="{{ $p->unit_cost ?? 0.00 }}" data-vendor="{{ $p->preferred_vendor_id }}">{!! htmlspecialchars_decode($p->name) !!} ({{ $p->sku ?: 'No SKU' }})</option>
                    @endforeach
                </select>
            </td>
            <td>
                <div class="d-flex align-items-center w-100 px-1">
                    <div class="selected-vendors-badges d-flex flex-wrap gap-1 align-items-center" style="flex-grow: 1;">
                        <!-- Badges get rendered here dynamically via JS -->
                    </div>
                    <select name="items[__INDEX__][vendor_ids][]" class="vendor-hidden-select d-none" multiple required>
                        @foreach($vendors as $v)
                            <option value="{{ $v->id }}">{{ $v->name }}</option>
                        @endforeach
                    </select>
                </div>
            </td>
            <td>
                <input type="number" name="items[__INDEX__][quantity]" class="odoo-table-input text-end qty-input" step="0.0001" min="0.0001" required placeholder="0.00">
            </td>
            <td>
                <input type="number" name="items[__INDEX__][estimated_cost]" class="odoo-table-input text-end cost-input" step="0.01" min="0" placeholder="0.00">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-link text-danger remove-row-btn p-1 border-0"><i class="feather-trash-2 fs-14"></i></button>
            </td>
        </tr>
    </template>
@endsection

@push('scripts')
    <!-- Select2 Vendor Scripts -->
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            // Initialize select2
            function initSelect2(context) {
                $(context).find('.select2-simple').select2({
                    theme: 'bootstrap-5',
                    width: '100%'
                });
            }
            initSelect2(document);

            // Function to update the displayed badges from hidden select values
            function updateBadgeDisplay(row) {
                const $row = $(row);
                const $select = $row.find('.vendor-hidden-select');
                const $badgeContainer = $row.find('.selected-vendors-badges');
                $badgeContainer.empty();
                
                const selectedOptions = $select.find('option:selected');
                if (selectedOptions.length === 0) {
                    $badgeContainer.html('<span class="text-muted fs-11 italic me-2">No Supplier</span>');
                } else {
                    selectedOptions.each(function() {
                        const id = $(this).val();
                        const name = $(this).text();
                        $badgeContainer.append(`
                            <span class="badge d-inline-flex align-items-center gap-1 border me-1 mb-1" style="background-color: #f1eef1; color: #714B67; border-color: #e1d6df; padding: 4px 8px; line-height: 1.2;" title="${name}">
                                ${name}
                                <span class="remove-vendor-btn cursor-pointer fw-bold text-danger ms-1" data-vendor-id="${id}" style="font-size: 13px; line-height: 1;">&times;</span>
                            </span>
                        `);
                    });
                }
                
                // Add the plus badge at the end of the badge list
                $badgeContainer.append(`
                    <span class="badge bg-light text-secondary border cursor-pointer edit-item-vendors-btn py-1 mb-1" style="line-height: 1.2;" title="Add Supplier">
                        <i class="feather-plus me-0.5"></i> Add
                    </span>
                `);
            }

            // Run on initial static rows
            $('#rfqItemsTable tbody tr.item-row').each(function() {
                updateBadgeDisplay(this);
            });

            // Handle product cost pre-filling and auto vendor pre-filling
            $(document).on('change', '.product-select', function() {
                const option = this.options[this.selectedIndex];
                const cost = parseFloat($(option).attr('data-cost')) || 0.00;
                const vendorId = $(option).attr('data-vendor');
                
                const $row = $(this).closest('tr');
                $row.find('.cost-input').val(cost.toFixed(2));
                
                const $select = $row.find('.vendor-hidden-select');
                if (vendorId) {
                    $select.val([vendorId]);
                } else {
                    $select.val([]);
                }
                updateBadgeDisplay($row);
            });

            // Dynamic items table rows
            let rowIdx = {{ count($prefilledItems) > 0 ? count($prefilledItems) : 1 }};

            function addRow(data = null) {
                let html = $('#rowTemplate').html();
                html = html.replace(/__INDEX__/g, rowIdx);
                
                const $newTr = $(html);
                $('#rfqItemsTable tbody').append($newTr);
                initSelect2($newTr);

                if (data) {
                    $newTr.find('.product-select').val(data.product_id).trigger('change.select2');
                    $newTr.find('.qty-input').val(data.quantity.toFixed(4));
                    $newTr.find('.cost-input').val(data.estimated_cost.toFixed(2));
                    
                    const $select = $newTr.find('.vendor-hidden-select');
                    if (data.vendor_id) {
                        $select.val([data.vendor_id]);
                    } else {
                        $select.val([]);
                    }
                }
                
                updateBadgeDisplay($newTr);
                rowIdx++;
                updateRemoveRowButtons();
            }

            $('#addRowBtn').on('click', function() {
                addRow();
            });

            // Requisition Change listener -> Pull items via AJAX
            $('#requisitionSelect').on('change', function() {
                const reqId = $(this).val();
                if (!reqId) {
                    return;
                }

                $.ajax({
                    url: '{{ route("purchase.rfqs.get-requisition-items") }}',
                    method: 'GET',
                    data: { requisition_id: reqId },
                    success: function(response) {
                        if (response.success) {
                            $('#rfqItemsTable tbody').empty();
                            rowIdx = 0;

                            if (response.items.length === 0) {
                                addRow();
                                return;
                            }

                            response.items.forEach(function(item) {
                                addRow(item);
                            });
                        }
                    },
                    error: function(xhr) {
                        console.error('Error loading PR items:', xhr);
                    }
                });
            });

            // Remove row button click
            $(document).on('click', '.remove-row-btn', function() {
                $(this).closest('tr').remove();
                updateRemoveRowButtons();
            });

            function updateRemoveRowButtons() {
                const rowCount = $('#rfqItemsTable tbody tr').length;
                if (rowCount <= 1) {
                    $('.remove-row-btn').prop('disabled', true);
                } else {
                    $('.remove-row-btn').prop('disabled', false);
                }
            }

            updateRemoveRowButtons(); // run on load

            // --- Inline Vendor Selection via Dropdown (replaces Modal) ---
            $(document).on('click', '.edit-item-vendors-btn', function(e) {
                e.stopPropagation();
                const $row = $(this).closest('tr');
                const $select = $row.find('.vendor-hidden-select');
                const currentVals = $select.val() || [];
                
                // Build dropdown options containing non-selected vendors
                let optionsHtml = '<option value="">-- Choose --</option>';
                $select.find('option').each(function() {
                    const id = $(this).val();
                    const name = $(this).text();
                    if (!currentVals.includes(id)) {
                        optionsHtml += `<option value="${id}">${name}</option>`;
                    }
                });
                
                const $dropdown = $(`
                    <select class="inline-vendor-select border" style="font-size: 11px; padding: 2px 4px; border-radius: 4px; width: 130px; height: 24px; vertical-align: middle; background-color: #fff; color: #333;">
                        ${optionsHtml}
                    </select>
                `);
                
                $(this).replaceWith($dropdown);
                $dropdown.focus();
            });

            // Handle dropdown change event to add supplier
            $(document).on('change', '.inline-vendor-select', function() {
                const $row = $(this).closest('tr');
                const $select = $row.find('.vendor-hidden-select');
                const selectedVal = $(this).val();
                
                if (selectedVal) {
                    let currentVals = $select.val() || [];
                    if (!currentVals.includes(selectedVal)) {
                        currentVals.push(selectedVal);
                        $select.val(currentVals);
                    }
                }
                updateBadgeDisplay($row);
            });

            // Revert back to normal display when losing focus
            $(document).on('blur', '.inline-vendor-select', function() {
                const $row = $(this).closest('tr');
                setTimeout(() => {
                    if ($row.find('.inline-vendor-select').length) {
                        updateBadgeDisplay($row);
                    }
                }, 150);
            });

            // Handle clicking 'x' on a vendor badge to remove it
            $(document).on('click', '.remove-vendor-btn', function(e) {
                e.stopPropagation();
                const $row = $(this).closest('tr');
                const $select = $row.find('.vendor-hidden-select');
                const vendorIdToRemove = $(this).attr('data-vendor-id');
                
                let currentVals = $select.val() || [];
                currentVals = currentVals.filter(id => id != vendorIdToRemove);
                
                $select.val(currentVals);
                updateBadgeDisplay($row);
            });

            // --- Bulk Action Events ---
            // Select All Checkbox
            $('#selectAllItems').on('change', function() {
                const isChecked = $(this).prop('checked');
                $('.row-item-checkbox').prop('checked', isChecked);
            });

            $(document).on('change', '.row-item-checkbox', function() {
                const total = $('.row-item-checkbox').length;
                const checked = $('.row-item-checkbox:checked').length;
                $('#selectAllItems').prop('checked', total === checked);
            });

            // Bulk Add Supplier
            $('#bulkAddSupplierBtn').on('click', function() {
                const selectedVendorId = $('#bulkSupplierSelect').val();
                if (!selectedVendorId) {
                    alert('Please select a Supplier first.');
                    return;
                }

                const checkedRows = $('.row-item-checkbox:checked');
                if (checkedRows.length === 0) {
                    alert('Please select at least one item row using checkboxes.');
                    return;
                }

                checkedRows.each(function() {
                    const $row = $(this).closest('tr');
                    const $select = $row.find('.vendor-hidden-select');
                    let currentVals = $select.val() || [];
                    
                    if (!currentVals.includes(selectedVendorId)) {
                        currentVals.push(selectedVendorId);
                        $select.val(currentVals);
                        updateBadgeDisplay($row);
                    }
                });
            });

            // Bulk Remove Supplier
            $('#bulkRemoveSupplierBtn').on('click', function() {
                const selectedVendorId = $('#bulkSupplierSelect').val();
                if (!selectedVendorId) {
                    alert('Please select a Supplier first.');
                    return;
                }

                const checkedRows = $('.row-item-checkbox:checked');
                if (checkedRows.length === 0) {
                    alert('Please select at least one item row using checkboxes.');
                    return;
                }

                checkedRows.each(function() {
                    const $row = $(this).closest('tr');
                    const $select = $row.find('.vendor-hidden-select');
                    let currentVals = $select.val() || [];
                    
                    const index = currentVals.indexOf(selectedVendorId);
                    if (index > -1) {
                        currentVals.splice(index, 1);
                        $select.val(currentVals);
                        updateBadgeDisplay($row);
                    }
                });
            });
        });
    </script>
@endpush
