@extends('layouts.duralux')

@section('title', __('inventory.new_stock_transfer') . ' | SaaS ERP')
@section('page-title', __('inventory.new_stock_transfer'))
@section('breadcrumb', __('inventory.stock_transfers_create'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .select2-container {
            width: 100% !important;
            max-width: 100% !important;
        }
        .stock-badge-container {
            font-size: 11px;
            margin-top: 4px;
        }
        .stock-error-text {
            font-size: 11px;
            color: #dc3545;
            font-weight: 600;
            margin-top: 3px;
            display: none;
        }
        /* Table overflow visible to prevent dropdown scroller/clipping */
        .items-table-wrapper {
            overflow: visible !important;
            position: relative;
        }
        .items-table-wrapper table {
            overflow: visible !important;
        }
        .table-responsive {
            overflow: visible !important;
        }
        /* Custom Multiselect & Scrollable Dropdown for Serials */
        .serial-input-box {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 4px 8px;
            min-height: 36px;
            background-color: #fff;
            cursor: pointer;
            transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
        }
        .serial-input-box:hover, .serial-input-box.active {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
        }
        .serial-chip {
            background-color: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            border-radius: 4px;
            padding: 1px 6px;
            font-size: 11px;
            font-family: monospace;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .serial-chip .remove-chip {
            cursor: pointer;
            color: #93c5fd;
            font-weight: bold;
            font-size: 12px;
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
            min-width: 320px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.18), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            z-index: 1060;
            padding: 8px;
        }
        .serial-scroll-list {
            max-height: 160px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #fafafa;
        }
        .serial-scroll-list::-webkit-scrollbar {
            width: 5px;
        }
        .serial-scroll-list::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        .serial-scroll-list::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        .serial-scroll-list::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        .serial-option-item {
            display: flex;
            align-items: center;
            padding: 5px 8px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            user-select: none;
            color: #1e293b;
            transition: background 0.1s ease;
        }
        .serial-option-item:hover {
            background-color: #f1f5f9;
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
        html.app-skin-dark .serial-chip .remove-chip {
            color: #93c5fd !important;
        }
        html.app-skin-dark .serial-chip .remove-chip:hover {
            color: #f87171 !important;
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
        html.app-skin-dark .serial-search-input::placeholder {
            color: #64748b !important;
        }
        html.app-skin-dark .serial-dropdown-pane .input-group-text {
            background-color: #121a2d !important;
            border-color: #283c50 !important;
            color: #94a3b8 !important;
        }
        html.app-skin-dark .serials-header-meta {
            color: #94a3b8 !important;
        }
        html.app-skin-dark .serial-clear-btn {
            color: #f87171 !important;
        }
        html.app-skin-dark .serial-scroll-list {
            background-color: #121a2d !important;
            border-color: #283c50 !important;
        }
        html.app-skin-dark .serial-scroll-list::-webkit-scrollbar-track {
            background-color: #121a2d !important;
        }
        html.app-skin-dark .serial-scroll-list::-webkit-scrollbar-thumb {
            background-color: #283c50 !important;
        }
        html.app-skin-dark .serial-scroll-list::-webkit-scrollbar-thumb:hover {
            background-color: #3b506b !important;
        }
        html.app-skin-dark .serial-option-item {
            color: #cbd5e1 !important;
        }
        html.app-skin-dark .serial-option-item:hover {
            background-color: #1a2844 !important;
            color: #ffffff !important;
        }
        html.app-skin-dark .serial-option-item .font-monospace {
            color: #cbd5e1 !important;
        }
        html.app-skin-dark .serial-option-item:hover .font-monospace {
            color: #ffffff !important;
        }
        html.app-skin-dark .serial-checkbox {
            background-color: #121a2d;
            border-color: #3b506b;
        }
        html.app-skin-dark .batch-select {
            background-color: #121a2d !important;
            border-color: #283c50 !important;
            color: #ffffff !important;
        }
        html.app-skin-dark #fastBarcodeScanInput {
            background-color: #121a2d !important;
            color: #ffffff !important;
        }
    </style>
@endpush

@section('content')
<div class="erp-single-panel bg-white p-4">

    @if(session('error'))
        <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
            <i class="feather-alert-triangle me-2 fs-18"></i>
            <div><strong>Transfer Error:</strong> {{ session('error') }}</div>
        </div>
    @endif

    <form action="{{ route('inventory.transfers.store') }}" method="POST" id="transferForm">
        @csrf
        <x-ui.odoo-form-ui type="sheet">
            <!-- Header Bar -->
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 flex-wrap gap-2">
                <div>
                    <h4 class="fw-bold text-dark mb-0">{{ __('inventory.new_stock_transfer') }}</h4>
                    <span class="fs-12 text-muted">{{ __('inventory.new_stock_transfer_help') }}</span>
                </div>
                <div>
                    <x-ui.button href="{{ route('inventory.transfers.index') }}" variant="light" class="border" icon="feather-arrow-left">{{ __('inventory.back_to_transfers') ?? 'Back to Transfers' }}</x-ui.button>
                </div>
            </div>

            <!-- Form Grid -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="select" :label="__('inventory.from_warehouse')" name="from_warehouse_id" id="fromWarehouseSelect" class="select2-select" :required="true" :error-text="$errors->first('from_warehouse_id')">
                        <option value="">{{ __('inventory.select_source_warehouse') }}</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ old('from_warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="select" :label="__('inventory.to_warehouse')" name="to_warehouse_id" id="toWarehouseSelect" class="select2-select" :required="true" :error-text="$errors->first('to_warehouse_id')">
                        <option value="">{{ __('inventory.select_target_warehouse') }}</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ old('to_warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" inputType="date" :label="__('inventory.transfer_date')" name="transfer_date" value="{{ old('transfer_date', date('Y-m-d')) }}" :required="true" :error-text="$errors->first('transfer_date')" />
                </div>
                <div class="col-md-12">
                    <x-ui.odoo-form-ui type="textarea" :label="__('inventory.notes_reason')" name="notes" rows="2" :placeholder="__('inventory.optional_transfer_comments')" :error-text="$errors->first('notes')">{{ old('notes') }}</x-ui.odoo-form-ui>
                </div>
            </div>

            <!-- Line Items Table Section -->
            <div class="border-top pt-4">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                    <h5 class="fw-bold text-dark mb-0 fs-14"><i class="feather-layers text-primary me-2"></i>{{ __('inventory.items_to_transfer') }}</h5>
                    <div class="d-flex align-items-center gap-2" style="width: 420px;">
                        <div class="input-group input-group-sm shadow-2xs rounded overflow-hidden" style="border: 1px solid #cbd5e1 !important;">
                            <span class="input-group-text bg-primary text-white border-0 px-3 fw-semibold"><i class="feather-camera me-1"></i> Barcode</span>
                            <input type="text" id="fastBarcodeScanInput" class="form-control border-0 bg-white" placeholder="{{ __('inventory.scan_barcode_sku_placeholder') }}" autocomplete="off" style="font-size: 13px;">
                            <button type="button" class="btn btn-primary border-0 px-3" id="fastBarcodeScanBtn"><i class="feather-search"></i></button>
                        </div>
                    </div>
                </div>

                <div class="items-table-wrapper border rounded bg-white mb-2" style="overflow: visible !important;">
                    <x-ui.odoo-form-ui type="table" id="items-table">
                        <thead class="table-light fs-12">
                            <tr>
                                <th style="width: 38%;" class="ps-3">{{ __('inventory.product') }} <span class="text-danger">*</span></th>
                                <th style="width: 14%;" class="text-center">{{ __('inventory.quantity') }} <span class="text-danger">*</span></th>
                                <th style="width: 43%;">Tracking (Batch / Serial Numbers)</th>
                                <th style="width: 5%;" class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="transfer-row" data-row-index="0">
                                <td class="ps-3 align-top py-3">
                                    <select name="items[0][product_id]" class="form-select odoo-table-select product-select" required>
                                        <option value="">{{ __('inventory.select_product') }}</option>
                                        @foreach($products as $prod)
                                            <option value="{{ $prod->id }}">{{ $prod->name }} (SKU: {{ $prod->sku }})</option>
                                        @endforeach
                                    </select>

                                    <!-- Enterprise Live Stock Status Badge -->
                                    <div class="stock-badge-container d-flex align-items-center flex-wrap gap-1 mt-1">
                                        <span class="badge bg-soft-secondary text-muted border border-secondary-subtle px-2 py-0.5 rounded-pill fw-medium select-prompt-badge">
                                            <i class="feather-info me-1"></i>Select Source Warehouse & Product
                                        </span>
                                    </div>
                                </td>
                                <td class="text-center align-top py-3">
                                    <input type="number" name="items[0][quantity]" class="odoo-table-input text-center qty-input mx-auto" step="0.01" min="0.01" placeholder="{{ __('inventory.qty') }}" value="1" required style="width: 100px;">
                                    <div class="stock-error-text text-danger fs-11 fw-bold mt-1" style="display: none;">
                                        <i class="feather-alert-triangle me-1"></i><span class="error-msg-text">Exceeds Available Stock</span> (<span class="max-avail-display">0</span> available)!
                                    </div>
                                    <div class="serial-qty-hint text-muted fs-11 mt-1" style="display: none;"></div>
                                </td>
                                <td class="align-top py-3 tracking-cell">
                                    <div class="tracking-placeholder text-muted fs-11 py-1">
                                        <i class="feather-info me-1"></i>Select a product to configure tracking
                                    </div>
                                </td>
                                <td class="text-center align-top py-3">
                                    <button type="button" class="btn btn-icon btn-sm btn-soft-danger remove-row mt-1">
                                        <i class="feather-trash-2"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>

                <div class="mt-3">
                    <button type="button" class="btn btn-xs btn-outline-primary fw-bold" id="add-item" style="font-size: 10px; padding: 2px 8px; text-transform: none !important;">
                        <i class="feather-plus me-1"></i>{{ __('inventory.add_product_item') }}
                    </button>
                </div>
            </div>

            <!-- Footer Action Bar -->
            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <x-ui.button href="{{ route('inventory.transfers.index') }}" variant="light" class="border px-4">{{ __('inventory.discard') }}</x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="feather-check" class="px-4">{{ __('inventory.save_stock_transfer') }}</x-ui.button>
            </div>
        </x-ui.odoo-form-ui>
    </form>
</div>

@push('scripts')
<script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
<script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
<script>
    // State per row for tracking: batches, serials, pagination
    const rowTrackingState = {};

    $(document).ready(function() {
        if (typeof $.fn.select2 === 'function') {
            $('.select2-select').select2({ theme: "bootstrap-5", width: "100%" });
            $('.product-select').select2({ theme: "bootstrap-5", width: "100%" });
        }

        // Close serial dropdowns when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.serial-multiselect-wrapper').length) {
                $('.serial-dropdown-pane').addClass('d-none');
                $('.serial-input-box').removeClass('active');
            }
        });
    });

    // Stock Lookup and Tracking Renderer
    function checkRowStock($row) {
        const fromWhId = $('#fromWarehouseSelect').val();
        const prodId = $row.find('.product-select').val();
        const rowIndex = $row.data('row-index') !== undefined ? $row.data('row-index') : $row.index();
        const $badgeContainer = $row.find('.stock-badge-container');
        const $qtyInput = $row.find('.qty-input');
        const $trackingCell = $row.find('.tracking-cell');

        if (!rowTrackingState[rowIndex]) {
            rowTrackingState[rowIndex] = {
                trackingType: 'standard',
                batches: [],
                serials: [],
                selectedSerials: [],
                selectedBatchId: null,
                page: 1,
                hasMore: false,
                isLoading: false,
                search: ''
            };
        }

        if (!fromWhId) {
            $badgeContainer.html('<span class="badge bg-soft-warning text-warning border border-warning-subtle px-2 py-0.5 rounded-pill fw-medium"><i class="feather-alert-triangle me-1"></i>Select Source Warehouse above</span>');
            $trackingCell.html('<div class="text-muted fs-11 py-1"><i class="feather-info me-1"></i>Select Source Warehouse above</div>');
            $qtyInput.removeAttr('data-max-avail').removeAttr('max');
            validateQty($row);
            return;
        }

        if (!prodId) {
            $badgeContainer.html('<span class="badge bg-soft-secondary text-muted border border-secondary-subtle px-2 py-0.5 rounded-pill fw-medium"><i class="feather-info me-1"></i>Select Product to view stock</span>');
            $trackingCell.html('<div class="text-muted fs-11 py-1"><i class="feather-info me-1"></i>Select Product to view tracking</div>');
            $qtyInput.removeAttr('data-max-avail').removeAttr('max');
            validateQty($row);
            return;
        }

        $badgeContainer.html('<span class="badge bg-soft-info text-info border border-info-subtle px-2 py-0.5 rounded-pill fw-medium"><i class="feather-loader spin me-1"></i>Checking Live Stock...</span>');

        $.ajax({
            url: "{{ route('inventory.products.stockCheck') }}",
            data: { warehouse_id: fromWhId, product_id: prodId },
            success: function(res) {
                if (res && res.success) {
                    const avail = parseFloat(res.available_qty) || 0;
                    const resv = parseFloat(res.reserved_qty) || 0;
                    const phys = parseFloat(res.physical_qty) || 0;

                    let html = '';
                    if (avail <= 0) {
                        html += `<span class="badge bg-soft-danger text-danger border border-danger-subtle px-2 py-0.5 rounded-pill fw-bold"><i class="feather-x-circle me-1"></i>Out of Stock (0 Net Avail)</span>`;
                    } else {
                        html += `<span class="badge bg-soft-success text-success border border-success-subtle px-2 py-0.5 rounded-pill fw-bold"><i class="feather-check-circle me-1"></i>Net Avail: ${avail}</span>`;
                    }
                    html += `<span class="badge bg-soft-warning text-warning border border-warning-subtle px-2 py-0.5 rounded-pill fw-medium">Reserved: ${resv}</span>`;
                    html += `<span class="badge bg-soft-secondary text-muted border border-secondary-subtle px-2 py-0.5 rounded-pill fw-medium">Physical: ${phys}</span>`;

                    $badgeContainer.html(html);
                    $qtyInput.attr('data-max-avail', avail).attr('max', avail);
                    validateQty($row);

                    // Determine tracking type
                    const trackSerial = !!res.track_serial_number;
                    const trackBatch = !!res.track_batch;

                    let trackingType = 'standard';
                    if (trackSerial && trackBatch) {
                        trackingType = 'batch_and_serial';
                    } else if (trackSerial) {
                        trackingType = 'serial';
                    } else if (trackBatch) {
                        trackingType = 'batch';
                    }

                    rowTrackingState[rowIndex].trackingType = trackingType;
                    rowTrackingState[rowIndex].batches = res.available_batches || [];
                    rowTrackingState[rowIndex].serials = res.available_serials || [];
                    rowTrackingState[rowIndex].page = 1;
                    rowTrackingState[rowIndex].hasMore = !!res.serials_has_more;
                    rowTrackingState[rowIndex].totalSerials = res.serials_total || (res.available_serials ? res.available_serials.length : 0);
                    rowTrackingState[rowIndex].search = '';

                    renderTrackingUI($row, rowIndex);
                } else {
                    $badgeContainer.html('<span class="badge bg-soft-secondary text-muted border border-secondary-subtle px-2 py-0.5 rounded-pill fw-medium">Stock info unavailable</span>');
                    $trackingCell.html('<div class="text-muted fs-11 py-1">Stock info unavailable</div>');
                }
            },
            error: function() {
                $badgeContainer.html('<span class="badge bg-soft-secondary text-muted border border-secondary-subtle px-2 py-0.5 rounded-pill fw-medium">Stock info unavailable</span>');
                $trackingCell.html('<div class="text-muted fs-11 py-1">Error fetching stock</div>');
            }
        });
    }

    // Render Tracking UI inside a row depending on standard vs batch vs serial
    function renderTrackingUI($row, rowIndex) {
        const state = rowTrackingState[rowIndex];
        const $trackingCell = $row.find('.tracking-cell');
        let html = '';

        if (state.trackingType === 'standard') {
            html = `
                <div class="d-flex align-items-center gap-2 mt-1">
                    <span class="badge bg-soft-secondary text-muted border border-secondary-subtle px-2.5 py-1.5 fs-11">
                        <i class="feather-check me-1 text-success"></i>Standard Inventory (No Serial/Batch Tracking)
                    </span>
                    <input type="hidden" name="items[${rowIndex}][batch_id]" value="">
                    <input type="hidden" name="items[${rowIndex}][serial_numbers]" value="">
                </div>
            `;
            $trackingCell.html(html);
            $row.find('.serial-qty-hint').hide();
        } else if (state.trackingType === 'batch') {
            let batchOpts = '<option value="">-- Select Available Batch --</option>';
            if (state.batches && state.batches.length > 0) {
                state.batches.forEach(b => {
                    const expText = b.expiry_date ? `, Exp: ${b.expiry_date}` : '';
                    batchOpts += `<option value="${b.id}" data-avail="${b.available_qty}">Batch #${b.batch_number} (Avail: ${b.available_qty}${expText})</option>`;
                });
            } else {
                batchOpts = '<option value="">No Active Batches Available</option>';
            }

            html = `
                <div>
                    <label class="form-label fs-11 text-muted fw-bold mb-1 d-flex align-items-center justify-content-between">
                        <span><i class="feather-layers text-primary me-1"></i>Batch Number <span class="text-danger">*</span></span>
                        <span class="text-muted fs-10 batch-info-text"></span>
                    </label>
                    <select name="items[${rowIndex}][batch_id]" class="form-select odoo-table-select batch-select fs-12" required>
                        ${batchOpts}
                    </select>
                    <input type="hidden" name="items[${rowIndex}][serial_numbers]" value="">
                </div>
            `;
            $trackingCell.html(html);
            $row.find('.serial-qty-hint').hide();
        } else if (state.trackingType === 'serial' || state.trackingType === 'batch_and_serial') {
            let batchSection = '';
            if (state.trackingType === 'batch_and_serial') {
                let batchOpts = '<option value="">-- Select Batch (Optional/Filter) --</option>';
                if (state.batches && state.batches.length > 0) {
                    state.batches.forEach(b => {
                        const expText = b.expiry_date ? `, Exp: ${b.expiry_date}` : '';
                        batchOpts += `<option value="${b.id}" data-avail="${b.available_qty}">Batch #${b.batch_number} (Avail: ${b.available_qty}${expText})</option>`;
                    });
                }
                batchSection = `
                    <div class="mb-2">
                        <label class="form-label fs-11 text-muted fw-bold mb-1"><i class="feather-layers text-primary me-1"></i>Batch Number</label>
                        <select name="items[${rowIndex}][batch_id]" class="form-select odoo-table-select batch-select fs-12">
                            ${batchOpts}
                        </select>
                    </div>
                `;
            } else {
                batchSection = `<input type="hidden" name="items[${rowIndex}][batch_id]" value="">`;
            }

            html = `
                ${batchSection}
                <div>
                    <label class="form-label fs-11 text-muted fw-bold mb-1 d-flex align-items-center justify-content-between">
                        <span><i class="feather-hash text-info me-1"></i>Select Serial Numbers</span>
                        <span class="serial-count-badge badge bg-soft-primary text-primary fs-10">0 selected</span>
                    </label>
                    <div class="serial-multiselect-wrapper position-relative">
                        <div class="serial-input-box d-flex flex-wrap align-items-center gap-1">
                            <span class="serial-placeholder text-muted fs-11">Click to select serial numbers...</span>
                            <div class="serial-chips-list d-flex flex-wrap gap-1"></div>
                        </div>
                        <input type="hidden" name="items[${rowIndex}][serial_numbers]" class="serial-numbers-hidden" value="">

                        <!-- Dropdown Menu Box -->
                        <div class="serial-dropdown-pane d-none">
                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-light border-end-0 py-0"><i class="feather-search text-muted fs-11"></i></span>
                                <input type="text" class="form-control serial-search-input border-start-0 fs-11" placeholder="Search serial # (Press type)...">
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-1 px-1 fs-11 text-muted">
                                <span class="serials-header-meta">Showing ${state.serials.length} of ${state.totalSerials || state.serials.length}</span>
                                <button type="button" class="btn btn-link btn-xs text-danger p-0 serial-clear-btn" style="text-decoration: none; font-size: 11px;">Clear All</button>
                            </div>
                            <div class="serial-scroll-list p-1">
                                <div class="serial-items-container">
                                    <!-- Populated dynamically -->
                                </div>
                                <div class="serial-loading-spinner text-center py-2 d-none">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status" style="width: 14px; height: 14px; border-width: 2px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $trackingCell.html(html);
            populateSerialOptions($row, rowIndex);
        }
    }

    // Populate checkboxes inside the scrollable serials list
    function populateSerialOptions($row, rowIndex, append = false) {
        const state = rowTrackingState[rowIndex];
        const $scrollList = $row.find('.serial-scroll-list');
        const $itemsContainer = $scrollList.find('.serial-items-container');
        const $meta = $row.find('.serials-header-meta');
        const $spinner = $scrollList.find('.serial-loading-spinner');

        $spinner.addClass('d-none');

        if (!append) {
            $itemsContainer.empty();
        }

        if (!state.serials || state.serials.length === 0) {
            if (!append) {
                $itemsContainer.html('<div class="text-muted fs-11 p-2 text-center">No available serial numbers found.</div>');
            }
            $meta.text('0 available');
            return;
        }

        $meta.text(`Showing ${state.serials.length} of ${state.totalSerials || state.serials.length}`);

        const listToRender = append ? state.lastLoadedChunk || [] : state.serials;
        let htmlChunk = '';

        listToRender.forEach(sn => {
            const isChecked = state.selectedSerials.includes(sn) ? 'checked' : '';
            htmlChunk += `
                <label class="serial-option-item">
                    <input type="checkbox" class="form-check-input me-2 serial-checkbox" value="${sn}" ${isChecked}>
                    <span class="font-monospace fs-12">${sn}</span>
                </label>
            `;
        });

        if (append) {
            $itemsContainer.append(htmlChunk);
        } else {
            $itemsContainer.html(htmlChunk);
        }
    }

    // Toggle Dropdown open / close with smart viewport auto-flip
    $(document).on('click', '.serial-input-box', function(e) {
        e.stopPropagation();
        const $wrapper = $(this).closest('.serial-multiselect-wrapper');
        const $pane = $wrapper.find('.serial-dropdown-pane');
        const isCurrentlyOpen = !$pane.hasClass('d-none');

        // Close all other dropdowns
        $('.serial-dropdown-pane').addClass('d-none');
        $('.serial-input-box').removeClass('active');

        if (!isCurrentlyOpen) {
            $pane.removeClass('d-none');
            $(this).addClass('active');

            // Smart auto-flip: check if dropdown would overflow bottom of viewport
            const rect = $wrapper[0].getBoundingClientRect();
            const dropdownHeight = 230;
            const spaceBelow = window.innerHeight - rect.bottom;
            if (spaceBelow < dropdownHeight && rect.top > dropdownHeight) {
                $pane.css({ top: 'auto', bottom: 'calc(100% + 4px)' });
            } else {
                $pane.css({ top: 'calc(100% + 4px)', bottom: 'auto' });
            }

            $pane.find('.serial-search-input').focus();
        }
    });

    $(document).on('click', '.serial-dropdown-pane', function(e) {
        e.stopPropagation();
    });

    // Checkbox toggle
    $(document).on('change', '.serial-checkbox', function() {
        const $row = $(this).closest('tr');
        const rowIndex = $row.data('row-index') !== undefined ? $row.data('row-index') : $row.index();
        const state = rowTrackingState[rowIndex];
        const sn = $(this).val();

        if ($(this).is(':checked')) {
            if (!state.selectedSerials.includes(sn)) {
                state.selectedSerials.push(sn);
            }
        } else {
            state.selectedSerials = state.selectedSerials.filter(s => s !== sn);
        }

        updateSelectedSerialsUI($row, rowIndex);
    });

    // Remove chip click
    $(document).on('click', '.remove-chip', function(e) {
        e.stopPropagation();
        const $row = $(this).closest('tr');
        const rowIndex = $row.data('row-index') !== undefined ? $row.data('row-index') : $row.index();
        const state = rowTrackingState[rowIndex];
        const sn = $(this).data('sn');

        state.selectedSerials = state.selectedSerials.filter(s => s !== sn);
        // Uncheck matching checkbox if present in scroll list
        $row.find(`.serial-checkbox[value="${sn}"]`).prop('checked', false);

        updateSelectedSerialsUI($row, rowIndex);
    });

    // Clear All Serials button
    $(document).on('click', '.serial-clear-btn', function(e) {
        e.preventDefault();
        const $row = $(this).closest('tr');
        const rowIndex = $row.data('row-index') !== undefined ? $row.data('row-index') : $row.index();
        const state = rowTrackingState[rowIndex];

        state.selectedSerials = [];
        $row.find('.serial-checkbox').prop('checked', false);
        updateSelectedSerialsUI($row, rowIndex);
    });

    // Update UI (chips, hidden input, quantity auto-sync)
    function updateSelectedSerialsUI($row, rowIndex) {
        const state = rowTrackingState[rowIndex];
        const $chipsList = $row.find('.serial-chips-list');
        const $placeholder = $row.find('.serial-placeholder');
        const $hiddenInput = $row.find('.serial-numbers-hidden');
        const $counterBadge = $row.find('.serial-count-badge');
        const $qtyInput = $row.find('.qty-input');
        const $qtyHint = $row.find('.serial-qty-hint');

        $chipsList.empty();

        if (state.selectedSerials.length > 0) {
            $placeholder.hide();
            state.selectedSerials.forEach(sn => {
                $chipsList.append(`
                    <span class="serial-chip">
                        ${sn}
                        <span class="remove-chip" data-sn="${sn}">&times;</span>
                    </span>
                `);
            });
            $hiddenInput.val(state.selectedSerials.join(', '));
            $counterBadge.text(`${state.selectedSerials.length} selected`);

            // Auto-sync quantity to match selected serials count
            $qtyInput.val(state.selectedSerials.length);
            validateQty($row);
            $qtyHint.html(`<i class="feather-info me-1"></i>Qty synchronized with ${state.selectedSerials.length} serials`).show();
        } else {
            $placeholder.show();
            $hiddenInput.val('');
            $counterBadge.text('0 selected');
            $qtyHint.hide();
        }
    }

    // Infinite Scroll handler on serial list (load next 20)
    function handleSerialListScroll(el) {
        const $scrollList = $(el);
        const $row = $scrollList.closest('tr');
        const rowIndex = $row.data('row-index') !== undefined ? $row.data('row-index') : $row.index();
        const state = rowTrackingState[rowIndex];

        if (!state || !state.hasMore || state.isLoading) {
            return;
        }

        const scrollTop = el.scrollTop;
        const clientHeight = el.clientHeight;
        const scrollHeight = el.scrollHeight;

        // Trigger loading when user scrolls to within 35px of bottom
        if (scrollTop + clientHeight >= scrollHeight - 35) {
            loadNextSerialsPage($row, rowIndex);
        }
    }

    // Capture phase event listener (true) because scroll events DO NOT bubble to document!
    document.addEventListener('scroll', function(e) {
        if (e.target && e.target.classList && e.target.classList.contains('serial-scroll-list')) {
            handleSerialListScroll(e.target);
        }
    }, true);

    // AJAX load next page of 20 serials
    function loadNextSerialsPage($row, rowIndex) {
        const state = rowTrackingState[rowIndex];
        const fromWhId = $('#fromWarehouseSelect').val();
        const prodId = $row.find('.product-select').val();
        const $spinner = $row.find('.serial-loading-spinner');

        if (!fromWhId || !prodId || !state.hasMore || state.isLoading) return;

        state.isLoading = true;
        $spinner.removeClass('d-none');
        state.page = (state.page || 1) + 1;

        $.ajax({
            url: "{{ route('inventory.products.availableSerials') }}",
            data: {
                warehouse_id: fromWhId,
                product_id: prodId,
                batch_id: state.selectedBatchId,
                search: state.search,
                page: state.page,
                per_page: 20
            },
            success: function(res) {
                state.isLoading = false;
                $spinner.addClass('d-none');

                if (res && res.success && res.serials && res.serials.length > 0) {
                    state.lastLoadedChunk = res.serials;
                    state.serials = state.serials.concat(res.serials);
                    state.hasMore = !!res.has_more;
                    state.totalSerials = res.total;

                    populateSerialOptions($row, rowIndex, true);
                } else {
                    state.hasMore = false;
                }
            },
            error: function() {
                state.isLoading = false;
                $spinner.addClass('d-none');
                state.hasMore = false;
            }
        });
    }

    // Search input inside serials dropdown (debounced 250ms)
    let searchDebounceTimer = null;
    $(document).on('input', '.serial-search-input', function() {
        const $input = $(this);
        const $row = $input.closest('tr');
        const rowIndex = $row.data('row-index') !== undefined ? $row.data('row-index') : $row.index();
        const state = rowTrackingState[rowIndex];
        const searchVal = $input.val().trim();

        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(function() {
            state.search = searchVal;
            state.page = 1;
            state.isLoading = false;

            const fromWhId = $('#fromWarehouseSelect').val();
            const prodId = $row.find('.product-select').val();
            const $loading = $row.find('.serial-loading-indicator');

            $loading.removeClass('d-none');

            $.ajax({
                url: "{{ route('inventory.products.availableSerials') }}",
                data: {
                    warehouse_id: fromWhId,
                    product_id: prodId,
                    batch_id: state.selectedBatchId,
                    search: state.search,
                    page: 1,
                    per_page: 20
                },
                success: function(res) {
                    $loading.addClass('d-none');
                    if (res && res.success) {
                        state.serials = res.serials || [];
                        state.hasMore = !!res.has_more;
                        state.totalSerials = res.total;
                        populateSerialOptions($row, rowIndex, false);
                    }
                },
                error: function() {
                    $loading.addClass('d-none');
                }
            });
        }, 250);
    });

    // Batch Select change: filter serials if batch_and_serial
    $(document).on('change', '.batch-select', function() {
        const $row = $(this).closest('tr');
        const rowIndex = $row.data('row-index') !== undefined ? $row.data('row-index') : $row.index();
        const state = rowTrackingState[rowIndex];
        const batchId = $(this).val();

        state.selectedBatchId = batchId || null;

        if (state.trackingType === 'batch_and_serial') {
            state.page = 1;
            state.search = '';
            const fromWhId = $('#fromWarehouseSelect').val();
            const prodId = $row.find('.product-select').val();

            $.ajax({
                url: "{{ route('inventory.products.availableSerials') }}",
                data: {
                    warehouse_id: fromWhId,
                    product_id: prodId,
                    batch_id: state.selectedBatchId,
                    page: 1,
                    per_page: 20
                },
                success: function(res) {
                    if (res && res.success) {
                        state.serials = res.serials || [];
                        state.hasMore = !!res.has_more;
                        state.totalSerials = res.total;
                        populateSerialOptions($row, rowIndex, false);
                    }
                }
            });
        }
    });

    function validateQty($row) {
        const $qtyInput = $row.find('.qty-input');
        const $errText = $row.find('.stock-error-text');
        const maxAvailAttr = $qtyInput.attr('data-max-avail');

        if (maxAvailAttr !== undefined && maxAvailAttr !== null) {
            const maxAvail = parseFloat(maxAvailAttr);
            const inputQty = parseFloat($qtyInput.val()) || 0;

            if (maxAvail <= 0) {
                $qtyInput.addClass('is-invalid border-danger');
                $errText.find('.error-msg-text').text('Out of Stock (0 available)');
                $errText.find('.max-avail-display').text('0');
                $errText.show();
            } else if (inputQty > maxAvail) {
                $qtyInput.addClass('is-invalid border-danger');
                $errText.find('.error-msg-text').text('Exceeds Available Stock');
                $errText.find('.max-avail-display').text(maxAvail);
                $errText.show();
            } else if (inputQty <= 0) {
                $qtyInput.addClass('is-invalid border-danger');
                $errText.find('.error-msg-text').text('Quantity must be greater than 0');
                $errText.show();
            } else {
                $qtyInput.removeClass('is-invalid border-danger');
                $errText.hide();
            }
        } else {
            $qtyInput.removeClass('is-invalid border-danger');
            $errText.hide();
        }
    }

    // Event Listeners for Source Warehouse and Product
    $(document).on('change select2:select', '#fromWarehouseSelect', function() {
        const fromVal = $(this).val();
        $('#toWarehouseSelect option').prop('disabled', false);
        if (fromVal) {
            $('#toWarehouseSelect option[value="' + fromVal + '"]').prop('disabled', true);
            if ($('#toWarehouseSelect').val() === fromVal) {
                $('#toWarehouseSelect').val('').trigger('change');
            }
        }
        $('#items-table tbody tr.transfer-row').each(function() {
            checkRowStock($(this));
        });
    });

    $(document).on('change select2:select', '#toWarehouseSelect', function() {
        const toVal = $(this).val();
        $('#fromWarehouseSelect option').prop('disabled', false);
        if (toVal) {
            $('#fromWarehouseSelect option[value="' + toVal + '"]').prop('disabled', true);
            if ($('#fromWarehouseSelect').val() === toVal) {
                $('#fromWarehouseSelect').val('').trigger('change');
            }
        }
    });

    $(document).on('change select2:select', '.product-select', function() {
        const $row = $(this).closest('tr');
        checkRowStock($row);
    });

    $(document).on('input keyup change', '.qty-input', function() {
        const $row = $(this).closest('tr');
        validateQty($row);
    });

    // Check all rows on initial document ready if warehouse is preselected
    if ($('#fromWarehouseSelect').val()) {
        $('#items-table tbody tr.transfer-row').each(function() {
            checkRowStock($(this));
        });
    }

    // Form Submit Validation
    $('#transferForm').on('submit', function(e) {
        let hasError = false;
        let errorMessage = '';

        const fromWh = $('#fromWarehouseSelect').val();
        if (!fromWh) {
            e.preventDefault();
            alert('⚠️ Please select a Source Warehouse first.');
            $('#fromWarehouseSelect').focus();
            return false;
        }

        const toWh = $('#toWarehouseSelect').val();
        if (!toWh) {
            e.preventDefault();
            alert('⚠️ Please select a Destination Warehouse.');
            $('#toWarehouseSelect').focus();
            return false;
        }

        if (fromWh === toWh) {
            e.preventDefault();
            alert('⚠️ Source Warehouse and Destination Warehouse cannot be the same.');
            return false;
        }

        $('#items-table tbody tr.transfer-row').each(function() {
            const $row = $(this);
            const rowIndex = $row.data('row-index') !== undefined ? $row.data('row-index') : $row.index();
            const state = rowTrackingState[rowIndex];
            const $qtyInput = $row.find('.qty-input');
            const maxAvailAttr = $qtyInput.attr('data-max-avail');
            const prodName = $row.find('.product-select option:selected').text().trim() || 'Selected Item';
            const prodVal = $row.find('.product-select').val();
            const inputQty = parseFloat($qtyInput.val()) || 0;

            if (!prodVal) {
                hasError = true;
                errorMessage = 'Please select a product for all rows.';
                $row.find('.product-select').focus();
                return false;
            }

            if (inputQty <= 0) {
                hasError = true;
                errorMessage = `Quantity for '${prodName}' must be greater than 0.`;
                $qtyInput.focus();
                return false;
            }

            if (maxAvailAttr !== undefined && maxAvailAttr !== null) {
                const maxAvail = parseFloat(maxAvailAttr);

                if (maxAvail <= 0) {
                    hasError = true;
                    errorMessage = `Cannot transfer '${prodName}'. This item is Out of Stock (0 Net Available) in the selected source warehouse.`;
                    $qtyInput.focus();
                    return false;
                }

                if (inputQty > maxAvail) {
                    hasError = true;
                    errorMessage = `Cannot transfer ${inputQty} for '${prodName}'. Only ${maxAvail} Net Available in the selected source warehouse.`;
                    $qtyInput.focus();
                    return false;
                }
            }

            // Tracking validation: Serial Numbers
            if (state && (state.trackingType === 'serial' || state.trackingType === 'batch_and_serial')) {
                if (!state.selectedSerials || state.selectedSerials.length === 0) {
                    hasError = true;
                    errorMessage = `'${prodName}' is serial-tracked. Please select at least ${inputQty} serial number(s).`;
                    return false;
                }
                if (state.selectedSerials.length !== Math.round(inputQty)) {
                    hasError = true;
                    errorMessage = `For '${prodName}', quantity is ${inputQty} but you selected ${state.selectedSerials.length} serial numbers. The number of serials must equal the transfer quantity.`;
                    return false;
                }
            }

            // Tracking validation: Batch Number
            if (state && state.trackingType === 'batch') {
                const batchVal = $row.find('.batch-select').val();
                if (!batchVal) {
                    hasError = true;
                    errorMessage = `'${prodName}' is batch-tracked. Please select a batch number.`;
                    $row.find('.batch-select').focus();
                    return false;
                }
            }
        });

        if (hasError) {
            e.preventDefault();
            alert('⚠️ Cannot Submit Stock Transfer:\n\n' + errorMessage);
            return false;
        }
    });

    // Add Row
    let rowIndexCounter = 1;
    document.getElementById('add-item').addEventListener('click', function() {
        let table = document.getElementById('items-table').getElementsByTagName('tbody')[0];
        let newRow = table.insertRow();
        const currentIdx = rowIndexCounter++;
        newRow.className = 'transfer-row';
        newRow.setAttribute('data-row-index', currentIdx);
        newRow.innerHTML = `
            <td class="ps-3 align-top py-3">
                <select name="items[${currentIdx}][product_id]" class="form-select odoo-table-select product-select" required>
                    <option value="">{{ __('inventory.select_product') }}</option>
                    @foreach($products as $prod)
                        <option value="{{ $prod->id }}">{{ $prod->name }} (SKU: {{ $prod->sku }})</option>
                    @endforeach
                </select>
                <div class="stock-badge-container d-flex align-items-center flex-wrap gap-1 mt-1">
                    <span class="badge bg-soft-secondary text-muted border border-secondary-subtle px-2 py-0.5 rounded-pill fw-medium select-prompt-badge">
                        <i class="feather-info me-1"></i>Select Source Warehouse & Product
                    </span>
                </div>
            </td>
            <td class="text-center align-top py-3">
                <input type="number" name="items[${currentIdx}][quantity]" class="odoo-table-input text-center qty-input mx-auto" step="0.01" min="0.01" placeholder="{{ __('inventory.qty') }}" value="1" required style="width: 100px;">
                <div class="stock-error-text text-danger fs-11 fw-bold mt-1" style="display: none;">
                    <i class="feather-alert-triangle me-1"></i><span class="error-msg-text">Exceeds Available Stock</span> (<span class="max-avail-display">0</span> available)!
                </div>
                <div class="serial-qty-hint text-muted fs-11 mt-1" style="display: none;"></div>
            </td>
            <td class="align-top py-3 tracking-cell">
                <div class="tracking-placeholder text-muted fs-11 py-1">
                    <i class="feather-info me-1"></i>Select a product to configure tracking
                </div>
            </td>
            <td class="text-center align-top py-3">
                <button type="button" class="btn btn-icon btn-sm btn-soft-danger remove-row mt-1"><i class="feather-trash-2"></i></button>
            </td>
        `;

        if (typeof $.fn.select2 === 'function') {
            $(newRow).find('.product-select').select2({ theme: "bootstrap-5", width: "100%" });
        }
        checkRowStock($(newRow));
    });

    // Remove row
    document.addEventListener('click', function(e) {
        if (e.target && e.target.closest('.remove-row')) {
            let row = e.target.closest('tr');
            if (document.querySelectorAll('#items-table tbody tr').length > 1) {
                row.remove();
            }
        }
    });

    // Fast Barcode Lookup
    function handleTransferBarcodeScan() {
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
                    let rows = document.querySelectorAll('.transfer-row');
                    let targetRow = null;

                    rows.forEach(r => {
                        let sel = r.querySelector('.product-select');
                        if (sel && sel.value == prod.id) {
                            targetRow = r;
                        }
                    });

                    if (!targetRow) {
                        rows.forEach(r => {
                            let sel = r.querySelector('.product-select');
                            if (sel && (!sel.value || sel.value === '')) {
                                targetRow = r;
                            }
                        });
                    }

                    if (!targetRow) {
                        document.getElementById('add-item').click();
                        let newRows = document.querySelectorAll('.transfer-row');
                        targetRow = newRows[newRows.length - 1];
                    }

                    if (targetRow) {
                        let targetSelect = targetRow.querySelector('.product-select');
                        if (targetSelect && targetSelect.value != prod.id) {
                            $(targetSelect).val(prod.id).trigger('change');
                        }

                        const scannedSn = data.serial_number || (data.is_serial ? code : null);
                        if (scannedSn) {
                            setTimeout(() => {
                                const $r = $(targetRow);
                                const rIdx = $r.data('row-index') !== undefined ? $r.data('row-index') : $r.index();
                                const state = rowTrackingState[rIdx];
                                if (state && (state.trackingType === 'serial' || state.trackingType === 'batch_and_serial')) {
                                    if (!state.selectedSerials.includes(scannedSn)) {
                                        state.selectedSerials.push(scannedSn);
                                        updateSelectedSerialsUI($r, rIdx);
                                    }
                                }
                            }, 500);
                        }
                    }
                    input.value = '';
                } else {
                    alert('Product or Serial Number not found for code: ' + code);
                    input.value = '';
                }
            })
            .catch(err => {
                alert('Barcode lookup failed: ' + code);
                input.value = '';
            });
    }

    document.getElementById('fastBarcodeScanInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleTransferBarcodeScan();
        }
    });
    document.getElementById('fastBarcodeScanBtn').addEventListener('click', function(e) {
        e.preventDefault();
        handleTransferBarcodeScan();
    });
</script>
@endpush
@endsection
