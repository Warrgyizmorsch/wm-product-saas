@extends('layouts.duralux')

@section('title', __('inventory.new_stock_adjustment') . ' | SaaS ERP')
@section('page-title', __('inventory.new_stock_adjustment'))
@section('breadcrumb', __('inventory.inventory_stock_adjustments_create'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .select2-container {
            width: 100% !important;
            max-width: 100% !important;
        }

        /* Table wrapper avoiding overflow clipping */
        .items-table-wrapper {
            position: relative;
            overflow: visible !important;
            border-radius: 8px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
        }
        .items-table-wrapper table {
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }
        .items-table-wrapper table th {
            background-color: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
            padding: 10px 12px;
        }
        .items-table-wrapper table td {
            vertical-align: top;
            padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9;
        }

        /* Serial multi-select component */
        .serial-input-box {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #ffffff;
            min-height: 34px;
            padding: 4px 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .serial-input-box:hover,
        .serial-input-box.active {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
        }
        .serial-chip {
            background-color: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            border-radius: 4px;
            font-family: monospace;
            font-size: 11px;
            padding: 2px 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .serial-chip .remove-chip {
            cursor: pointer;
            font-weight: bold;
            color: #2563eb;
            line-height: 1;
        }
        .serial-chip .remove-chip:hover {
            color: #dc2626;
        }

        .serial-dropdown-pane {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            width: 100%;
            min-width: 290px;
            max-width: 360px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15);
            z-index: 1050;
            padding: 10px;
        }
        .serial-scroll-list {
            max-height: 190px;
            overflow-y: auto;
            border: 1px solid #f1f5f9;
            border-radius: 6px;
            background: #fafafa;
        }
        .serial-scroll-list::-webkit-scrollbar {
            width: 4px;
        }
        .serial-scroll-list::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .serial-option-item {
            padding: 6px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.15s ease;
        }
        .serial-option-item:hover {
            background: #f1f5f9;
        }

        /* Auto-generate preview box */
        .auto-serial-preview-box {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
            padding: 8px 12px;
            font-family: monospace;
            font-size: 12px;
            color: #1e293b;
        }

        /* ================= DARK MODE STYLING ================= */
        html.app-skin-dark .erp-single-panel {
            background-color: #121a2d !important;
            color: #ffffff !important;
        }
        html.app-skin-dark .erp-single-panel .bg-white {
            background-color: #162038 !important;
        }
        html.app-skin-dark .erp-single-panel .text-dark {
            color: #f1f5f9 !important;
        }
        html.app-skin-dark .items-table-wrapper {
            background-color: #162038 !important;
            border-color: #283c50 !important;
        }
        html.app-skin-dark .items-table-wrapper table th {
            background-color: #1a263d !important;
            border-color: #283c50 !important;
            color: #94a3b8 !important;
        }
        html.app-skin-dark .items-table-wrapper table td {
            border-color: #283c50 !important;
            color: #cbd5e1 !important;
        }
        html.app-skin-dark .serial-input-box {
            border-color: #283c50 !important;
            background-color: #121a2d !important;
            color: #f1f5f9 !important;
        }
        html.app-skin-dark .serial-input-box:hover,
        html.app-skin-dark .serial-input-box.active {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25) !important;
        }
        html.app-skin-dark .serial-chip {
            background-color: rgba(59, 130, 246, 0.2) !important;
            color: #93c5fd !important;
            border-color: rgba(59, 130, 246, 0.4) !important;
        }
        html.app-skin-dark .serial-dropdown-pane {
            background: #162038 !important;
            border-color: #283c50 !important;
            color: #e2e8f0 !important;
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.6) !important;
        }
        html.app-skin-dark .serial-search-input {
            background-color: #121a2d !important;
            border-color: #283c50 !important;
            color: #ffffff !important;
        }
        html.app-skin-dark .serial-scroll-list {
            background-color: #121a2d !important;
            border-color: #283c50 !important;
        }
        html.app-skin-dark .serial-option-item {
            color: #cbd5e1 !important;
        }
        html.app-skin-dark .serial-option-item:hover {
            background-color: #1a2844 !important;
            color: #ffffff !important;
        }
        html.app-skin-dark .auto-serial-preview-box {
            background: #121a2d !important;
            border-color: #283c50 !important;
            color: #93c5fd !important;
        }
    </style>
@endpush

@section('content')
<div class="erp-single-panel bg-white p-4">

    <form action="{{ route('inventory.adjustments.store') }}" method="POST" id="adjustmentForm">
        @csrf
        <x-ui.odoo-form-ui type="sheet">
            <!-- Header Bar -->
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 flex-wrap gap-2">
                <div>
                    <h4 class="fw-bold text-dark mb-0">{{ __('inventory.new_stock_adjustment') }}</h4>
                    <span class="fs-12 text-muted">{{ __('inventory.new_stock_adjustment_help') }}</span>
                </div>
            </div>

            <!-- Form Grid -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="select" :label="__('inventory.warehouse')" name="warehouse_id" id="warehouse_id" class="select2-select" :required="true" :error-text="$errors->first('warehouse_id')">
                        <option value="">{{ __('inventory.select_warehouse') }}</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ old('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" inputType="date" :label="__('inventory.adjustment_date')" name="adjustment_date" value="{{ old('adjustment_date', date('Y-m-d')) }}" :required="true" :error-text="$errors->first('adjustment_date')" />
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="select" :label="__('inventory.reason')" name="reason" class="select2-select" :required="true" :error-text="$errors->first('reason')">
                        <option value="Stock Count Variance" {{ old('reason') === 'Stock Count Variance' ? 'selected' : '' }}>{{ __('inventory.stock_count_variance') }}</option>
                        <option value="Damaged" {{ old('reason') === 'Damaged' ? 'selected' : '' }}>{{ __('inventory.damaged') }}</option>
                        <option value="Expired" {{ old('reason') === 'Expired' ? 'selected' : '' }}>{{ __('inventory.expired') }}</option>
                        <option value="Theft/Loss" {{ old('reason') === 'Theft/Loss' ? 'selected' : '' }}>{{ __('inventory.theft_loss') }}</option>
                        <option value="Scrap" {{ old('reason') === 'Scrap' ? 'selected' : '' }}>{{ __('inventory.scrap') }}</option>
                        <option value="Sample" {{ old('reason') === 'Sample' ? 'selected' : '' }}>{{ __('inventory.sample') }}</option>
                        <option value="Other" {{ old('reason') === 'Other' ? 'selected' : '' }}>{{ __('inventory.other') }}</option>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-12">
                    <x-ui.odoo-form-ui type="textarea" :label="__('inventory.notes_description')" name="notes" rows="2" :placeholder="__('inventory.describe_adjustment_reason')" :error-text="$errors->first('notes')">{{ old('notes') }}</x-ui.odoo-form-ui>
                </div>
            </div>

            <!-- Line Items Table Section -->
            <div class="border-top pt-4">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom flex-wrap gap-2">
                    <h5 class="fw-bold text-dark mb-0 fs-14"><i class="feather-layers text-primary me-2"></i>{{ __('inventory.adjustment_line_items') }}</h5>
                    <div class="d-flex align-items-center gap-2" style="width: 380px; max-width: 100%;">
                        <div class="input-group input-group-sm shadow-2xs rounded overflow-hidden" style="border: 1px solid #cbd5e1 !important;">
                            <span class="input-group-text bg-primary text-white border-0 px-3 fw-semibold"><i class="feather-camera me-1"></i> {{ __('inventory.barcode') }}</span>
                            <input type="text" id="fastBarcodeScanInput" class="form-control border-0 bg-white" placeholder="{{ __('inventory.scan_barcode_sku_press_enter') }}" autocomplete="off" style="font-size: 13px;">
                            <button type="button" class="btn btn-primary border-0 px-3" id="fastBarcodeScanBtn"><i class="feather-search"></i></button>
                        </div>
                    </div>
                </div>

                <div class="items-table-wrapper">
                    <table class="table" id="items-table">
                        <thead>
                            <tr>
                                <th style="width: 32%;" class="ps-3">{{ __('inventory.product') }} <span class="text-danger">*</span></th>
                                <th style="width: 13%;">{{ __('inventory.type') }} <span class="text-danger">*</span></th>
                                <th style="width: 11%;" class="text-center">{{ __('inventory.quantity') }} <span class="text-danger">*</span></th>
                                <th style="width: 10%;" class="text-center">{{ __('inventory.unit_cost') }} ({{ active_currency_symbol() }})</th>
                                <th style="width: 30%;">Tracking (Batch / Serial Numbers)</th>
                                <th style="width: 4%;" class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody aria-live="polite">
                            <tr class="adjustment-row" data-row-index="0">
                                <td class="ps-3">
                                    <select name="items[0][product_id]" class="form-select odoo-table-select product-select" required>
                                        <option value="">{{ __('inventory.select_product') }}</option>
                                        @foreach($products as $prod)
                                            <option value="{{ $prod->id }}" 
                                                    data-sku="{{ $prod->sku }}" 
                                                    data-track-serial="{{ $prod->track_serial_number ? 1 : 0 }}" 
                                                    data-track-batch="{{ $prod->track_batch ? 1 : 0 }}" 
                                                    data-cost="{{ $prod->cost_price ?? 0 }}">
                                                {{ $prod->name }} (SKU: {{ $prod->sku }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="product-stock-badge mt-1" id="stock_badge_0">
                                        <span class="badge bg-soft-secondary text-muted border border-secondary-subtle px-2 py-0.5 fs-11">
                                            <i class="feather-info me-1"></i>Select warehouse & product to view stock
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <select name="items[0][type]" class="form-select odoo-table-select type-select" required>
                                        <option value="Deduction">{{ __('inventory.deduction_sign') }} (-)</option>
                                        <option value="Addition">{{ __('inventory.addition_sign') }} (+)</option>
                                    </select>
                                </td>
                                <td class="text-center">
                                    <input type="number" name="items[0][quantity]" class="odoo-table-input text-center qty-input mx-auto" step="1" min="0.01" placeholder="Qty" value="1" required style="width: 85px;">
                                    <div class="qty-warning-msg text-danger fs-11 fw-semibold mt-0.5" style="display:none;"></div>
                                </td>
                                <td class="text-center">
                                    <input type="number" name="items[0][unit_cost]" class="odoo-table-input text-center cost-input mx-auto" step="0.01" min="0" placeholder="0.00" style="width: 85px;">
                                </td>
                                <td>
                                    <div class="tracking-cell" id="tracking_cell_0">
                                        <div class="text-muted fs-11 py-1">
                                            <i class="feather-info me-1"></i>Select Product to view tracking options
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-icon btn-sm btn-soft-danger remove-row mt-1" title="Remove line"><i class="feather-trash-2"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <button type="button" class="btn btn-xs btn-outline-primary fw-bold" id="add-item" style="font-size: 11px; padding: 4px 10px; text-transform: none !important;">
                        <i class="feather-plus me-1"></i>{{ __('inventory.add_line_item') }}
                    </button>
                </div>
            </div>

            <!-- Footer Action Bar -->
            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <x-ui.button href="{{ route('inventory.adjustments.index') }}" variant="light" class="border px-4">{{ __('inventory.discard') }}</x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="feather-check" class="px-4">{{ __('inventory.save_adjustment') }}</x-ui.button>
            </div>
        </x-ui.odoo-form-ui>
    </form>
</div>

<!-- Auto-Generate Serials Modal -->
<div class="modal fade" id="autoSerialModal" tabindex="-1" aria-labelledby="autoSerialModalTitle" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom py-3 px-4 bg-light-subtle">
                <h5 class="modal-title fs-14 fw-bold text-dark d-flex align-items-center" id="autoSerialModalTitle">
                    <i class="feather-zap text-warning me-2 fs-16"></i>Auto-Generate Serial Numbers
                </h5>
                <button type="button" class="btn-close fs-12" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="auto_serial_target_idx" value="">
                <input type="hidden" id="auto_serial_prod_id" value="">

                <div class="mb-3">
                    <label class="form-label fs-11 fw-bold text-muted text-uppercase mb-1">Product</label>
                    <div class="fw-semibold text-dark fs-13" id="auto_serial_product_name">—</div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-7">
                        <label class="form-label fs-11 fw-bold text-muted text-uppercase mb-1">Prefix</label>
                        <input type="text" id="auto_serial_prefix" class="form-control form-control-sm font-monospace fw-semibold" placeholder="e.g. SN-">
                    </div>
                    <div class="col-5">
                        <label class="form-label fs-11 fw-bold text-muted text-uppercase mb-1">Start Sequence</label>
                        <input type="number" id="auto_serial_start" class="form-control form-control-sm font-monospace fw-bold text-primary" value="1001" min="1">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fs-11 fw-bold text-muted text-uppercase mb-1">Quantity (Serials Count)</label>
                    <input type="number" id="auto_serial_count" class="form-control form-control-sm font-monospace fw-bold" value="1" min="1" max="1000">
                    <small class="text-muted fs-11">Matches line item adjustment quantity.</small>
                </div>

                <div class="mb-2">
                    <label class="form-label fs-11 fw-bold text-muted text-uppercase mb-1">Preview</label>
                    <div class="auto-serial-preview-box" id="auto_serial_preview">
                        —
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 py-2 px-4 bg-light-subtle d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary px-3 fw-semibold" id="btn_confirm_auto_serial">
                    <i class="feather-check me-1"></i>Generate & Insert
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
<script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
<script>
    // State repository per row index
    const rowTrackingState = {};
    let rowIndexCounter = 1;

    function initRowState(idx) {
        rowTrackingState[idx] = {
            trackingType: 'standard', // standard | batch | serial | batch_and_serial
            type: 'Deduction',        // Addition | Deduction
            batches: [],
            serials: [],
            selectedSerials: [],
            totalSerials: 0,
            hasMore: false,
            page: 1,
            isLoading: false,
            search: '',
            availableQty: 0
        };
    }

    // Initialize first row
    initRowState(0);

    $(document).ready(function() {
        if (typeof $.fn.select2 === 'function') {
            $('.select2-select').select2({ theme: "bootstrap-5", width: "100%" });
            initProductSelect2($('.product-select'));
        }

        // Warehouse change triggers refresh of all rows
        $('#warehouse_id').on('change', function() {
            $('.adjustment-row').each(function() {
                checkRowStock($(this));
            });
        });

        // Add line item
        $('#add-item').on('click', function() {
            addNewRow();
        });

        // Fast Barcode lookup
        $('#fastBarcodeScanInput').on('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleAdjBarcodeScan();
            }
        });
        $('#fastBarcodeScanBtn').on('click', function(e) {
            e.preventDefault();
            handleAdjBarcodeScan();
        });

        // Form submit validation
        $('#adjustmentForm').on('submit', function(e) {
            const whId = $('#warehouse_id').val();
            if (!whId) {
                e.preventDefault();
                alert('Please select a Warehouse first.');
                $('#warehouse_id').focus();
                return false;
            }

            let hasError = false;
            let errorMsg = '';

            $('.adjustment-row').each(function() {
                const idx = $(this).data('row-index');
                const state = rowTrackingState[idx];
                const type = $(this).find('.type-select').val();
                const qty = parseFloat($(this).find('.qty-input').val()) || 0;
                const prodId = $(this).find('.product-select').val();
                const prodName = $(this).find('.product-select option:selected').text();

                if (prodId && type === 'Deduction') {
                    if (qty > (state.availableQty + 0.0001)) {
                        hasError = true;
                        errorMsg = `Deduction quantity (${qty}) exceeds available stock (${state.availableQty}) for ${prodName}!`;
                        $(this).find('.qty-input').addClass('is-invalid');
                        return false;
                    }
                }

                if (prodId && state.trackingType === 'serial' && type === 'Deduction') {
                    if (state.selectedSerials.length !== Math.round(qty)) {
                        hasError = true;
                        errorMsg = `Please select exactly ${Math.round(qty)} serial numbers for ${prodName} (${state.selectedSerials.length} currently selected).`;
                        return false;
                    }
                }
            });

            if (hasError) {
                e.preventDefault();
                alert(errorMsg);
                return false;
            }
        });
    });

    function initProductSelect2($el) {
        $el.select2({ 
            theme: "bootstrap-5", 
            width: "100%",
            placeholder: "{{ __('inventory.select_product') }}"
        });
    }

    function addNewRow() {
        const idx = rowIndexCounter++;
        initRowState(idx);

        const rowHtml = `
            <tr class="adjustment-row" data-row-index="${idx}">
                <td class="ps-3">
                    <select name="items[${idx}][product_id]" class="form-select odoo-table-select product-select" required>
                        <option value="">{{ __('inventory.select_product') }}</option>
                        @foreach($products as $prod)
                            <option value="{{ $prod->id }}" 
                                    data-sku="{{ $prod->sku }}" 
                                    data-track-serial="{{ $prod->track_serial_number ? 1 : 0 }}" 
                                    data-track-batch="{{ $prod->track_batch ? 1 : 0 }}" 
                                    data-cost="{{ $prod->cost_price ?? 0 }}">
                                {{ $prod->name }} (SKU: {{ $prod->sku }})
                            </option>
                        @endforeach
                    </select>
                    <div class="product-stock-badge mt-1" id="stock_badge_${idx}">
                        <span class="badge bg-soft-secondary text-muted border border-secondary-subtle px-2 py-0.5 fs-11">
                            <i class="feather-info me-1"></i>Select warehouse & product to view stock
                        </span>
                    </div>
                </td>
                <td>
                    <select name="items[${idx}][type]" class="form-select odoo-table-select type-select" required>
                        <option value="Deduction">{{ __('inventory.deduction_sign') }} (-)</option>
                        <option value="Addition">{{ __('inventory.addition_sign') }} (+)</option>
                    </select>
                </td>
                <td class="text-center">
                    <input type="number" name="items[${idx}][quantity]" class="odoo-table-input text-center qty-input mx-auto" step="1" min="0.01" placeholder="Qty" value="1" required style="width: 85px;">
                    <div class="qty-warning-msg text-danger fs-11 fw-semibold mt-0.5" style="display:none;"></div>
                </td>
                <td class="text-center">
                    <input type="number" name="items[${idx}][unit_cost]" class="odoo-table-input text-center cost-input mx-auto" step="0.01" min="0" placeholder="0.00" style="width: 85px;">
                </td>
                <td>
                    <div class="tracking-cell" id="tracking_cell_${idx}">
                        <div class="text-muted fs-11 py-1">
                            <i class="feather-info me-1"></i>Select Product to view tracking options
                        </div>
                    </div>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-icon btn-sm btn-soft-danger remove-row mt-1" title="Remove line"><i class="feather-trash-2"></i></button>
                </td>
            </tr>
        `;

        $('#items-table tbody').append(rowHtml);
        const $newRow = $('#items-table tbody tr.adjustment-row:last');
        initProductSelect2($newRow.find('.product-select'));
    }

    // Row deletion
    $(document).on('click', '.remove-row', function() {
        if ($('#items-table tbody tr.adjustment-row').length > 1) {
            $(this).closest('tr.adjustment-row').remove();
        } else {
            alert('At least one item line is required.');
        }
    });

    // Product change listener
    $(document).on('change', '.product-select', function() {
        const $row = $(this).closest('tr.adjustment-row');
        const selectedOpt = $(this).find('option:selected');
        const cost = selectedOpt.data('cost');
        if (cost !== undefined && cost !== null && cost !== '') {
            $row.find('.cost-input').val(cost);
        }
        checkRowStock($row);
    });

    // Type change listener (Addition vs Deduction)
    $(document).on('change', '.type-select', function() {
        const $row = $(this).closest('tr.adjustment-row');
        const idx = $row.data('row-index');
        rowTrackingState[idx].type = $(this).val();
        validateQty($row);
        renderTrackingUI($row, idx);
    });

    // Quantity change listener
    $(document).on('input change', '.qty-input', function() {
        const $row = $(this).closest('tr.adjustment-row');
        validateQty($row);
    });

    // Check Live Stock for a row
    function checkRowStock($row) {
        const idx = $row.data('row-index');
        const whId = $('#warehouse_id').val();
        const prodId = $row.find('.product-select').val();
        const $badge = $(`#stock_badge_${idx}`);
        const $trackingCell = $(`#tracking_cell_${idx}`);

        if (!whId) {
            $badge.html('<span class="badge bg-soft-warning text-warning border border-warning-subtle px-2 py-0.5 fs-11"><i class="feather-alert-triangle me-1"></i>Select Warehouse above</span>');
            return;
        }

        if (!prodId) {
            $badge.html('<span class="badge bg-soft-secondary text-muted border border-secondary-subtle px-2 py-0.5 fs-11"><i class="feather-info me-1"></i>Select Product to view stock</span>');
            $trackingCell.html('<div class="text-muted fs-11 py-1"><i class="feather-info me-1"></i>Select Product to view tracking options</div>');
            return;
        }

        $badge.html('<span class="badge bg-soft-info text-info border border-info-subtle px-2 py-0.5 fs-11"><i class="feather-loader spin me-1"></i>Checking Live Stock...</span>');

        $.ajax({
            url: "{{ route('inventory.products.stockCheck') }}",
            data: { warehouse_id: whId, product_id: prodId },
            success: function(res) {
                if (res && res.success) {
                    const avail = parseFloat(res.available_qty) || 0;
                    const resv = parseFloat(res.reserved_qty) || 0;
                    const phys = parseFloat(res.physical_qty) || 0;

                    let html = '';
                    if (avail <= 0) {
                        html += `<span class="badge bg-soft-danger text-danger border border-danger-subtle px-2 py-0.5 fw-bold fs-11"><i class="feather-x-circle me-1"></i>Net Avail: 0</span> `;
                    } else {
                        html += `<span class="badge bg-soft-success text-success border border-success-subtle px-2 py-0.5 fw-bold fs-11"><i class="feather-check-circle me-1"></i>Net Avail: ${avail}</span> `;
                    }
                    html += `<span class="badge bg-soft-warning text-warning border border-warning-subtle px-2 py-0.5 fs-11">Reserved: ${resv}</span> `;
                    html += `<span class="badge bg-soft-secondary text-muted border border-secondary-subtle px-2 py-0.5 fs-11">Physical: ${phys}</span>`;

                    $badge.html(html);

                    // Update state
                    const state = rowTrackingState[idx];
                    state.availableQty = avail;
                    state.batches = res.available_batches || [];
                    state.serials = res.available_serials || [];
                    state.totalSerials = res.serials_total || (res.available_serials ? res.available_serials.length : 0);
                    state.hasMore = !!res.serials_has_more;
                    state.page = 1;

                    const trackSerial = !!res.track_serial_number;
                    const trackBatch = !!res.track_batch;

                    if (trackSerial && trackBatch) {
                        state.trackingType = 'batch_and_serial';
                    } else if (trackSerial) {
                        state.trackingType = 'serial';
                    } else if (trackBatch) {
                        state.trackingType = 'batch';
                    } else {
                        state.trackingType = 'standard';
                    }

                    validateQty($row);
                    renderTrackingUI($row, idx);
                } else {
                    $badge.html('<span class="badge bg-soft-secondary text-muted border px-2 py-0.5 fs-11">Stock info unavailable</span>');
                }
            },
            error: function() {
                $badge.html('<span class="badge bg-soft-secondary text-muted border px-2 py-0.5 fs-11">Stock check error</span>');
            }
        });
    }

    // Validate Quantity against Available Stock (for Deduction items)
    function validateQty($row) {
        const idx = $row.data('row-index');
        const state = rowTrackingState[idx];
        const type = $row.find('.type-select').val();
        const $qtyInput = $row.find('.qty-input');
        const $warn = $row.find('.qty-warning-msg');
        const qty = parseFloat($qtyInput.val()) || 0;

        if (type === 'Deduction') {
            if (qty > (state.availableQty + 0.0001)) {
                $qtyInput.addClass('is-invalid');
                $warn.html(`<i class="feather-alert-triangle me-1"></i>Max available: ${state.availableQty}`).show();
            } else {
                $qtyInput.removeClass('is-invalid');
                $warn.hide();
            }
        } else {
            $qtyInput.removeClass('is-invalid');
            $warn.hide();
        }

        // Auto sync Auto-generate button count
        $row.find('.btn-open-auto-serial').attr('data-count', Math.max(1, Math.round(qty)));
    }

    // Render dynamic tracking cell
    function renderTrackingUI($row, idx) {
        const state = rowTrackingState[idx];
        const type = $row.find('.type-select').val(); // Addition | Deduction
        const $cell = $(`#tracking_cell_${idx}`);
        const prodId = $row.find('.product-select').val();
        const prodOpt = $row.find('.product-select option:selected');
        const prodName = prodOpt.text();
        const prodSku = prodOpt.data('sku') || 'SN';

        if (!prodId) {
            $cell.html('<div class="text-muted fs-11 py-1"><i class="feather-info me-1"></i>Select Product to view tracking options</div>');
            return;
        }

        if (state.trackingType === 'standard') {
            $cell.html(`
                <span class="badge bg-soft-secondary text-muted border border-secondary-subtle px-2.5 py-1.5 fs-11">
                    <i class="feather-check text-success me-1"></i>Standard (No Tracking)
                </span>
                <input type="hidden" name="items[${idx}][batch_id]" value="">
                <input type="hidden" name="items[${idx}][serial_numbers]" value="">
            `);
            return;
        }

        // ================= SERIAL ONLY =================
        if (state.trackingType === 'serial') {
            if (type === 'Deduction') {
                // Multiselect dropdown of available serials
                $cell.html(`
                    <input type="hidden" name="items[${idx}][batch_id]" value="">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <label class="form-label fs-11 text-muted fw-bold mb-0">
                                <i class="feather-hash text-primary me-1"></i>Select Available Serials
                            </label>
                            <span class="badge bg-soft-primary text-primary fs-10 serial-count-badge" id="serial_badge_${idx}">
                                ${state.selectedSerials.length} selected
                            </span>
                        </div>
                        <div class="serial-multiselect-wrapper position-relative">
                            <div class="serial-input-box d-flex flex-wrap align-items-center gap-1" data-idx="${idx}">
                                <span class="serial-placeholder text-muted fs-11">Click to select available serials...</span>
                                <div class="serial-chips-list d-flex flex-wrap gap-1"></div>
                            </div>
                            <input type="hidden" name="items[${idx}][serial_numbers]" class="serial-numbers-hidden" value="">

                            <!-- Dropdown Pane -->
                            <div class="serial-dropdown-pane d-none" id="serial_pane_${idx}">
                                <div class="input-group input-group-sm mb-2">
                                    <span class="input-group-text bg-light border-end-0 py-0"><i class="feather-search text-muted fs-11"></i></span>
                                    <input type="text" class="form-control serial-search-input border-start-0 fs-11" placeholder="Search serial #..." data-idx="${idx}">
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-1 px-1 fs-11 text-muted">
                                    <span class="serials-header-meta" id="serial_meta_${idx}">Showing ${state.serials.length} of ${state.totalSerials}</span>
                                    <button type="button" class="btn btn-link btn-xs text-danger p-0 serial-clear-btn" data-idx="${idx}" style="text-decoration: none; font-size: 11px;">Clear</button>
                                </div>
                                <div class="serial-scroll-list p-1" data-idx="${idx}">
                                    <div class="serial-items-container"></div>
                                    <div class="serial-loading-spinner text-center py-2 d-none">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status" style="width: 14px; height: 14px; border-width: 2px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `);
                populateSerialOptions($row, idx);
                updateSelectedSerialsUI($row, idx);
            } else {
                // Addition: Textarea / scanner + Auto-Generate button
                const currentQty = Math.max(1, Math.round(parseFloat($row.find('.qty-input').val()) || 1));
                $cell.html(`
                    <input type="hidden" name="items[${idx}][batch_id]" value="">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <label class="form-label fs-11 text-muted fw-bold mb-0">
                                <i class="feather-hash text-success me-1"></i>Serial Numbers (Enter / Scan)
                            </label>
                            <button type="button" class="btn btn-xs btn-soft-primary fw-semibold btn-open-auto-serial" 
                                    data-idx="${idx}" 
                                    data-product-id="${prodId}" 
                                    data-product-name="${prodName}" 
                                    data-prefix="${prodSku}-" 
                                    data-count="${currentQty}">
                                <i class="feather-zap me-1"></i>Auto-Generate
                            </button>
                        </div>
                        <textarea name="items[${idx}][serial_numbers]" 
                                  class="form-control form-control-sm font-monospace fs-12 serial-text-area" 
                                  id="serial_text_${idx}" 
                                  rows="1" 
                                  placeholder="SN-1001, SN-1002..."></textarea>
                    </div>
                `);
            }
            return;
        }

        // ================= BATCH ONLY =================
        if (state.trackingType === 'batch') {
            if (type === 'Deduction') {
                let batchOpts = '<option value="">-- Select Available Batch --</option>';
                if (state.batches && state.batches.length > 0) {
                    state.batches.forEach(b => {
                        const exp = b.expiry_date ? `, Exp: ${b.expiry_date}` : '';
                        batchOpts += `<option value="${b.id}" data-avail="${b.available_qty}">Batch #${b.batch_number} (Avail: ${b.available_qty}${exp})</option>`;
                    });
                } else {
                    batchOpts = '<option value="">No active batches available in warehouse</option>';
                }
                $cell.html(`
                    <input type="hidden" name="items[${idx}][serial_numbers]" value="">
                    <div>
                        <label class="form-label fs-11 text-muted fw-bold mb-1">
                            <i class="feather-layers text-primary me-1"></i>Batch to Deduct <span class="text-danger">*</span>
                        </label>
                        <select name="items[${idx}][batch_id]" class="form-select form-select-sm fs-12 batch-select" required>
                            ${batchOpts}
                        </select>
                    </div>
                `);
            } else {
                // Addition: enter batch number & expiry date
                const defaultBatch = 'BAT-' + prodSku.replace(/[^a-zA-Z0-9]/g, '') + '-01';
                $cell.html(`
                    <input type="hidden" name="items[${idx}][serial_numbers]" value="">
                    <div>
                        <label class="form-label fs-11 text-muted fw-bold mb-1">
                            <i class="feather-layers text-success me-1"></i>New / Existing Batch Details
                        </label>
                        <div class="row g-1">
                            <div class="col-7">
                                <input type="text" name="items[${idx}][batch_number]" class="form-control form-control-sm font-monospace fs-12" placeholder="Batch No (e.g. ${defaultBatch})" required>
                            </div>
                            <div class="col-5">
                                <input type="date" name="items[${idx}][expiry_date]" class="form-control form-control-sm fs-12" title="Expiry Date">
                            </div>
                        </div>
                    </div>
                `);
            }
            return;
        }

        // ================= BATCH AND SERIAL =================
        if (state.trackingType === 'batch_and_serial') {
            if (type === 'Deduction') {
                let batchOpts = '<option value="">-- All / Filter by Batch --</option>';
                if (state.batches && state.batches.length > 0) {
                    state.batches.forEach(b => {
                        const exp = b.expiry_date ? `, Exp: ${b.expiry_date}` : '';
                        batchOpts += `<option value="${b.id}">Batch #${b.batch_number} (Avail: ${b.available_qty}${exp})</option>`;
                    });
                }
                $cell.html(`
                    <div class="mb-1.5">
                        <select name="items[${idx}][batch_id]" class="form-select form-select-sm fs-11 batch-filter-select" data-idx="${idx}">
                            ${batchOpts}
                        </select>
                    </div>
                    <div class="serial-multiselect-wrapper position-relative">
                        <div class="serial-input-box d-flex flex-wrap align-items-center gap-1" data-idx="${idx}">
                            <span class="serial-placeholder text-muted fs-11">Select available serials...</span>
                            <div class="serial-chips-list d-flex flex-wrap gap-1"></div>
                        </div>
                        <input type="hidden" name="items[${idx}][serial_numbers]" class="serial-numbers-hidden" value="">
                        <div class="serial-dropdown-pane d-none" id="serial_pane_${idx}">
                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-light border-end-0 py-0"><i class="feather-search text-muted fs-11"></i></span>
                                <input type="text" class="form-control serial-search-input border-start-0 fs-11" placeholder="Search serial..." data-idx="${idx}">
                            </div>
                            <div class="serial-scroll-list p-1" data-idx="${idx}">
                                <div class="serial-items-container"></div>
                                <div class="serial-loading-spinner text-center py-2 d-none">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status" style="width: 14px; height: 14px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                `);
                populateSerialOptions($row, idx);
                updateSelectedSerialsUI($row, idx);
            } else {
                const currentQty = Math.max(1, Math.round(parseFloat($row.find('.qty-input').val()) || 1));
                const defaultBatch = 'BAT-' + prodSku.replace(/[^a-zA-Z0-9]/g, '') + '-01';
                $cell.html(`
                    <div class="row g-1 mb-1.5">
                        <div class="col-7">
                            <input type="text" name="items[${idx}][batch_number]" class="form-control form-control-sm font-monospace fs-12" placeholder="Batch No (e.g. ${defaultBatch})">
                        </div>
                        <div class="col-5">
                            <input type="date" name="items[${idx}][expiry_date]" class="form-control form-control-sm fs-12" title="Expiry Date">
                        </div>
                    </div>
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <label class="form-label fs-11 text-muted fw-bold mb-0">Serials</label>
                            <button type="button" class="btn btn-xs btn-soft-primary fw-semibold btn-open-auto-serial" 
                                    data-idx="${idx}" 
                                    data-product-id="${prodId}" 
                                    data-product-name="${prodName}" 
                                    data-prefix="${prodSku}-" 
                                    data-count="${currentQty}">
                                <i class="feather-zap me-1"></i>Auto-Generate
                            </button>
                        </div>
                        <textarea name="items[${idx}][serial_numbers]" class="form-control form-control-sm font-monospace fs-12 serial-text-area" id="serial_text_${idx}" rows="1" placeholder="SN1, SN2..."></textarea>
                    </div>
                `);
            }
        }
    }

    // Populate checkboxes in serial multiselect dropdown
    function populateSerialOptions($row, idx, append = false) {
        const state = rowTrackingState[idx];
        const $pane = $(`#serial_pane_${idx}`);
        const $container = $pane.find('.serial-items-container');
        const $meta = $(`#serial_meta_${idx}`);
        const $spinner = $pane.find('.serial-loading-spinner');

        $spinner.addClass('d-none');

        if (!append) {
            $container.empty();
        }

        if (!state.serials || state.serials.length === 0) {
            if (!append) {
                $container.html('<div class="text-muted fs-11 p-2 text-center">No available serials found.</div>');
            }
            if ($meta.length) $meta.text('0 available');
            return;
        }

        if ($meta.length) {
            $meta.text(`Showing ${state.serials.length} of ${state.totalSerials}`);
        }

        const listToRender = append 
            ? state.serials.slice((state.page - 1) * 20) 
            : state.serials;

        let itemsHtml = '';
        listToRender.forEach(sn => {
            const isChecked = state.selectedSerials.includes(sn);
            itemsHtml += `
                <label class="serial-option-item">
                    <input type="checkbox" class="form-check-input serial-checkbox m-0" value="${sn}" ${isChecked ? 'checked' : ''} data-idx="${idx}">
                    <span class="font-monospace fs-11">${sn}</span>
                </label>
            `;
        });

        $container.append(itemsHtml);
    }

    // Update chips display and hidden input
    function updateSelectedSerialsUI($row, idx) {
        const state = rowTrackingState[idx];
        const $box = $row.find(`.serial-input-box[data-idx="${idx}"]`);
        const $chipsList = $box.find('.serial-chips-list');
        const $placeholder = $box.find('.serial-placeholder');
        const $hiddenInput = $row.find('.serial-numbers-hidden');
        const $badge = $(`#serial_badge_${idx}`);

        $chipsList.empty();

        if (state.selectedSerials.length > 0) {
            $placeholder.hide();
            state.selectedSerials.forEach(sn => {
                $chipsList.append(`
                    <span class="serial-chip">
                        <span>${sn}</span>
                        <span class="remove-chip" data-sn="${sn}" data-idx="${idx}">&times;</span>
                    </span>
                `);
            });
            $hiddenInput.val(state.selectedSerials.join(','));
        } else {
            $placeholder.show();
            $hiddenInput.val('');
        }

        if ($badge.length) {
            $badge.text(`${state.selectedSerials.length} selected`);
        }

        // Sync with quantity input
        if (state.selectedSerials.length > 0) {
            $row.find('.qty-input').val(state.selectedSerials.length);
            validateQty($row);
        }
    }

    // Toggle serial dropdown
    $(document).on('click', '.serial-input-box', function(e) {
        if ($(e.target).hasClass('remove-chip')) return;
        const idx = $(this).data('idx');
        const $pane = $(`#serial_pane_${idx}`);
        const isClosed = $pane.hasClass('d-none');

        $('.serial-dropdown-pane').addClass('d-none');
        $('.serial-input-box').removeClass('active');

        if (isClosed) {
            // Auto flip if near bottom
            const rect = this.getBoundingClientRect();
            const spaceBelow = window.innerHeight - rect.bottom;
            if (spaceBelow < 240 && rect.top > 240) {
                $pane.css({ top: 'auto', bottom: 'calc(100% + 4px)' });
            } else {
                $pane.css({ top: 'calc(100% + 4px)', bottom: 'auto' });
            }

            $pane.removeClass('d-none');
            $(this).addClass('active');
            $pane.find('.serial-search-input').focus();
        }
    });

    // Close serial dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.serial-multiselect-wrapper').length) {
            $('.serial-dropdown-pane').addClass('d-none');
            $('.serial-input-box').removeClass('active');
        }
    });

    // Remove single chip
    $(document).on('click', '.remove-chip', function(e) {
        e.stopPropagation();
        const sn = $(this).data('sn');
        const idx = $(this).data('idx');
        const state = rowTrackingState[idx];
        const $row = $(this).closest('tr.adjustment-row');

        state.selectedSerials = state.selectedSerials.filter(item => item !== sn);
        $(`#serial_pane_${idx} .serial-checkbox[value="${sn}"]`).prop('checked', false);
        updateSelectedSerialsUI($row, idx);
    });

    // Clear all serials
    $(document).on('click', '.serial-clear-btn', function() {
        const idx = $(this).data('idx');
        const state = rowTrackingState[idx];
        const $row = $(this).closest('tr.adjustment-row');

        state.selectedSerials = [];
        $(`#serial_pane_${idx} .serial-checkbox`).prop('checked', false);
        updateSelectedSerialsUI($row, idx);
    });

    // Checkbox toggle
    $(document).on('change', '.serial-checkbox', function() {
        const idx = $(this).data('idx');
        const state = rowTrackingState[idx];
        const sn = $(this).val();
        const $row = $(this).closest('tr.adjustment-row');

        if ($(this).is(':checked')) {
            if (!state.selectedSerials.includes(sn)) {
                state.selectedSerials.push(sn);
            }
        } else {
            state.selectedSerials = state.selectedSerials.filter(item => item !== sn);
        }

        updateSelectedSerialsUI($row, idx);
    });

    // Search serials with debouncing
    let searchDebounceTimer = null;
    $(document).on('input', '.serial-search-input', function() {
        const idx = $(this).data('idx');
        const state = rowTrackingState[idx];
        const query = $(this).val().trim();
        const $row = $(this).closest('tr.adjustment-row');

        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(function() {
            state.search = query;
            state.page = 1;
            fetchSerialsPage($row, idx, false);
        }, 300);
    });

    // Capturing scroll event listener for 20-by-20 infinite scroll
    document.addEventListener('scroll', function(e) {
        const target = e.target;
        if (target && target.classList && target.classList.contains('serial-scroll-list')) {
            const idx = $(target).data('idx');
            if (idx === undefined) return;
            const state = rowTrackingState[idx];

            if (state && !state.isLoading && state.hasMore) {
                if (target.scrollTop + target.clientHeight >= target.scrollHeight - 25) {
                    const $row = $(target).closest('tr.adjustment-row');
                    state.page += 1;
                    fetchSerialsPage($row, idx, true);
                }
            }
        }
    }, true);

    function fetchSerialsPage($row, idx, append = false) {
        const state = rowTrackingState[idx];
        const whId = $('#warehouse_id').val();
        const prodId = $row.find('.product-select').val();
        const batchId = $row.find('.batch-filter-select').val() || '';
        const $pane = $(`#serial_pane_${idx}`);
        const $spinner = $pane.find('.serial-loading-spinner');

        state.isLoading = true;
        $spinner.removeClass('d-none');

        $.ajax({
            url: "{{ route('inventory.products.availableSerials') }}",
            data: {
                warehouse_id: whId,
                product_id: prodId,
                batch_id: batchId,
                search: state.search,
                page: state.page,
                per_page: 20
            },
            success: function(res) {
                state.isLoading = false;
                $spinner.addClass('d-none');

                if (res && res.success) {
                    if (append) {
                        state.serials = state.serials.concat(res.serials || []);
                    } else {
                        state.serials = res.serials || [];
                    }
                    state.totalSerials = res.total;
                    state.hasMore = !!res.has_more;
                    populateSerialOptions($row, idx, append);
                }
            },
            error: function() {
                state.isLoading = false;
                $spinner.addClass('d-none');
            }
        });
    }

    // ================= AUTO-GENERATE SERIALS MODAL =================
    $(document).on('click', '.btn-open-auto-serial', function() {
        const idx = $(this).data('idx');
        const prodId = $(this).data('product-id');
        const prodName = $(this).data('product-name');
        const prefix = $(this).data('prefix') || 'SN-';
        const count = parseInt($(this).attr('data-count')) || parseInt($(this).data('count')) || 1;

        $('#auto_serial_target_idx').val(idx);
        $('#auto_serial_prod_id').val(prodId);
        $('#auto_serial_product_name').text(prodName);
        $('#auto_serial_prefix').val(prefix);
        $('#auto_serial_count').val(count);
        $('#auto_serial_start').val(1001);

        updateAutoSerialPreview();

        // Fetch sequence from API
        if (prodId) {
            $.ajax({
                url: "{{ route('inventory.products.nextSerialSequence') }}",
                data: { product_id: prodId, prefix: prefix },
                success: function(res) {
                    if (res && res.success && res.next_number) {
                        $('#auto_serial_start').val(res.next_number);
                        if (res.prefix) $('#auto_serial_prefix').val(res.prefix);
                        updateAutoSerialPreview();
                    }
                }
            });
        }

        const modal = new bootstrap.Modal(document.getElementById('autoSerialModal'));
        modal.show();
    });

    $('#auto_serial_prefix, #auto_serial_start, #auto_serial_count').on('input change', function() {
        updateAutoSerialPreview();
    });

    function updateAutoSerialPreview() {
        const prefix = $('#auto_serial_prefix').val();
        const start = parseInt($('#auto_serial_start').val()) || 1;
        const count = parseInt($('#auto_serial_count').val()) || 1;

        if (count <= 1) {
            $('#auto_serial_preview').text(`${prefix}${start} (1 serial)`);
        } else {
            const end = start + count - 1;
            $('#auto_serial_preview').text(`${prefix}${start} ... to ... ${prefix}${end} (${count} serials)`);
        }
    }

    $('#btn_confirm_auto_serial').on('click', function() {
        const idx = $('#auto_serial_target_idx').val();
        const prefix = $('#auto_serial_prefix').val();
        const start = parseInt($('#auto_serial_start').val()) || 1;
        const count = parseInt($('#auto_serial_count').val()) || 1;

        const serials = [];
        for (let i = 0; i < count; i++) {
            serials.push(prefix + (start + i));
        }

        const $textarea = $(`#serial_text_${idx}`);
        if ($textarea.length) {
            $textarea.val(serials.join(', ')).trigger('input');
        }

        const modalEl = document.getElementById('autoSerialModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
    });

    // Fast Barcode Lookup
    function handleAdjBarcodeScan() {
        const input = document.getElementById('fastBarcodeScanInput');
        const code = input.value.trim();
        if (!code) return;

        fetch("{{ route('inventory.products.barcodeLookup') }}?code=" + encodeURIComponent(code), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.product) {
                    const prod = data.product;
                    let targetRow = null;

                    $('.adjustment-row').each(function() {
                        const sel = $(this).find('.product-select').val();
                        if (sel == prod.id) targetRow = $(this);
                    });

                    if (!targetRow) {
                        $('.adjustment-row').each(function() {
                            const sel = $(this).find('.product-select').val();
                            if (!sel) targetRow = $(this);
                        });
                    }

                    if (!targetRow) {
                        addNewRow();
                        targetRow = $('.adjustment-row:last');
                    }

                    if (targetRow) {
                        const $sel = targetRow.find('.product-select');
                        if ($sel.val() != prod.id) {
                            $sel.val(prod.id).trigger('change');
                        }
                    }
                    input.value = '';
                } else {
                    alert('Product not found for: ' + code);
                    input.value = '';
                }
            })
            .catch(() => {
                alert('Barcode lookup failed: ' + code);
                input.value = '';
            });
    }
</script>
@endpush
@endsection
