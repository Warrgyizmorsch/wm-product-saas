@extends('layouts.duralux')

@section('title', __('purchase.new_goods_receipt') . ' | SaaS ERP')
@section('page-title', __('purchase.create_goods_receipt_note'))
@section('breadcrumb', __('ui.purchase') . ' / ' . __('purchase.goods_receipt_notes') . ' / ' . __('purchase.create'))

@section('page-actions')
    <x-ui.button href="{{ route('grns.index') }}" variant="light" icon="feather-arrow-left" class="border">
        {{ __('purchase.back_to_grns') }}
    </x-ui.button>
@endsection

@section('content')
    @php
        $currency = tenant()?->settings['currency'] ?? 'INR';
    @endphp

    <div class="row text-dark">
        <div class="col-12">
            <!-- Toast Notifications -->

            <form action="{{ route('grns.store') }}" method="POST" id="grnCreateForm">
                @csrf

                <!-- Single Card Odoo Sheet Layout -->
                <x-ui.odoo-form-ui type="sheet" class="shadow-sm rounded border-0">
                    <div class="border-bottom py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center">
                            <h5 class="fw-bold text-dark mb-0 me-3"><i class="feather-plus-circle text-primary me-2"></i>{{ __('purchase.new_goods_receipt') }}</h5>
                            <span class="badge bg-soft-primary text-primary px-2.5 py-1 fw-bold fs-11 font-monospace">{{ $grnNumber }}</span>
                        </div>
                        <div>
                            <x-ui.button type="submit" variant="primary" icon="feather-save" class="fw-bold px-4 py-2">
                                {{ __('purchase.save_grn') }}
                            </x-ui.button>
                        </div>
                    </div>

                    <div class="p-4 p-md-5">
                        @if ($errors->any())
                            <div class="alert alert-danger mb-4">
                                <h6 class="fw-bold mb-2"><i class="feather-alert-triangle me-1"></i>Validation Errors:</h6>
                                <ul class="mb-0 ps-3">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <!-- Header Form Controls -->
                        <div class="row g-3 fs-13 pb-4 border-bottom">
                            <div class="col-md-6 border-end">
                                <h6 class="fw-bold text-primary mb-3">{{ __('purchase.po_supplier_details') }}</h6>

                                <x-ui.odoo-form-ui type="select" label="{{ __('purchase.select_po') }}" name="purchase_order_id" id="po_selector" :required="true" :error-text="$errors->first('purchase_order_id')">
                                    <option value="">-- {{ __('purchase.choose_approved_po') }} --</option>
                                    @foreach($approvedOrders as $po)
                                        @php
                                            $ordQty = (float)$po->items->sum('quantity');
                                            $recQty = (float)$po->items->sum('received_qty');
                                            $remQty = max(0.0, $ordQty - $recQty);
                                        @endphp
                                        <option value="{{ $po->id }}" @selected($selectedPo && $selectedPo->id === $po->id)>
                                            {{ $po->purchase_order_number }} - {{ $po->vendor?->name }} (Rem Qty: {{ number_format($remQty, 2) }})
                                        </option>
                                    @endforeach
                                </x-ui.odoo-form-ui>

                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" label="{{ __('purchase.supplier_vendor') }}" name="vendor_display" id="vendor_display" value="{{ $selectedPo?->vendor?->name ?? '' }}" readonly="true" placeholder="{{ __('purchase.autoloaded_from_po') }}" :error-text="$errors->first('vendor_id')" />
                                        <input type="hidden" name="vendor_id" id="vendor_id" value="{{ $selectedPo?->vendor_id ?? '' }}">
                                    </div>
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="select" label="{{ __('purchase.warehouse') }}" name="warehouse_id" id="warehouse_id" :required="true" :error-text="$errors->first('warehouse_id')">
                                            <option value="">{{ __('purchase.select_warehouse_placeholder') }}</option>
                                            @foreach($warehouses as $wh)
                                                <option value="{{ $wh->id }}" @selected(($selectedPo && $selectedPo->warehouse?->id === $wh->id) || $loop->first)>
                                                    {{ $wh->name }}
                                                </option>
                                            @endforeach
                                        </x-ui.odoo-form-ui>
                                    </div>
                                </div>

                                <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('purchase.receipt_date') }}" name="received_date" id="received_date" value="{{ old('received_date', date('Y-m-d')) }}" :required="true" :error-text="$errors->first('received_date')" />
                            </div>

                            <div class="col-md-6">
                                <h6 class="fw-bold text-primary mb-3">{{ __('purchase.challan_logistics_details') }}</h6>

                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" label="{{ __('purchase.challan_invoice_no') }}" name="challan_number" id="challan_number" value="{{ old('challan_number') }}" placeholder="{{ __('purchase.supplier_challan_no') }}" :error-text="$errors->first('challan_number')" />
                                    </div>
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('purchase.challan_date') }}" name="challan_date" id="challan_date" value="{{ old('challan_date', date('Y-m-d')) }}" :error-text="$errors->first('challan_date')" />
                                    </div>
                                </div>

                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" label="{{ __('purchase.transporter_name') }}" name="transporter_name" id="transporter_name" value="{{ old('transporter_name') }}" placeholder="{{ __('purchase.courier_transporter_placeholder') }}" :error-text="$errors->first('transporter_name')" />
                                    </div>
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" label="{{ __('purchase.vehicle_number') }}" name="vehicle_number" id="vehicle_number" value="{{ old('vehicle_number') }}" placeholder="e.g. MH-12-AB-1234" :error-text="$errors->first('vehicle_number')" />
                                    </div>
                                </div>

                                <x-ui.odoo-form-ui type="input" label="{{ __('purchase.lr_number') }}" name="lr_number" id="lr_number" value="{{ old('lr_number') }}" placeholder="Lorry Receipt / Docket No" :error-text="$errors->first('lr_number')" />
                            </div>
                        </div>

                        <!-- Notes Section -->
                        <div class="mt-3 mb-4">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('purchase.store_receipt_remarks') }}" name="notes" placeholder="{{ __('purchase.store_receipt_remarks_placeholder') }}" rows="2" :error-text="$errors->first('notes')">{{ old('notes') }}</x-ui.odoo-form-ui>
                        </div>

                        <!-- Item Matrix Section using Common Odoo Table Component -->
                        <div class="mt-4">
                            <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                                <h6 class="fw-bold text-primary mb-0"><i class="feather-layers text-primary me-2"></i>{{ __('purchase.received_products_matrix') }}</h6>
                                <div class="d-flex align-items-center gap-2" style="width: 420px;">
                                    <div class="input-group input-group-sm shadow-2xs rounded overflow-hidden" style="border: 1px solid #cbd5e1 !important;">
                                        <span class="input-group-text bg-primary text-white border-0 px-3 fw-semibold"><i class="feather-camera me-1"></i> Barcode</span>
                                        <input type="text" id="fastBarcodeScanInput" class="form-control border-0 bg-white" placeholder="Scan Barcode / SKU (Press Enter)..." autocomplete="off" style="font-size: 13px;">
                                        <button type="button" class="btn btn-primary border-0 px-3" id="fastBarcodeScanBtn"><i class="feather-search"></i></button>
                                    </div>
                                </div>
                            </div>

                             <div class="table-responsive border rounded bg-white">
                                <x-ui.odoo-form-ui type="table" id="grnItemsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 4%;">#</th>
                                            <th style="width: 25%;">{{ __('purchase.product_description') }}</th>
                                            <th style="width: 10%;" class="text-center">{{ __('purchase.ordered') }}</th>
                                            <th style="width: 10%;" class="text-center">{{ __('purchase.prev_received') }}</th>
                                            <th style="width: 10%;" class="text-center">{{ __('purchase.remaining_qty') }}</th>
                                            <th style="width: 11%;" class="text-center">{{ __('purchase.receive_qty') }} <span class="text-danger">*</span></th>
                                            <th style="width: 10%;" class="text-center">{{ __('purchase.reject_qty') }}</th>
                                            <th style="width: 10%;" class="text-center">{{ __('purchase.accepted') }}</th>
                                            <th style="width: 10%;" class="text-end">{{ __('purchase.unit_rate') }} ({{ $currency }})</th>
                                            <th style="width: 12%;" class="text-end">{{ __('purchase.total_amount') }} ({{ $currency }})</th>
                                        </tr>
                                    </thead>
                                    <tbody id="grnItemsTbody">
                                        <tr>
                                            <td colspan="10" class="text-center py-4 text-muted">
                                                <i class="feather-info me-1"></i>{{ __('purchase.select_po_to_load_details') }}
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="table-light fw-bold" id="grnItemsTfoot" style="display: none;">
                                        <tr>
                                            <td colspan="5" class="text-end">{{ __('purchase.totals') }}:</td>
                                            <td class="text-center font-monospace text-primary fs-13" id="footTotalReceive">0.00</td>
                                            <td class="text-center font-monospace text-danger fs-13" id="footTotalReject">0.00</td>
                                            <td class="text-center font-monospace text-success fs-13" id="footTotalAccepted">0.00</td>
                                            <td></td>
                                            <td class="text-end font-monospace text-dark fs-13" id="footTotalAmount">0.00</td>
                                        </tr>
                                    </tfoot>
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>
                    </div>
                </x-ui.odoo-form-ui>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function showWarningModal(title, message, callback) {
        if (typeof confirmAction === 'function') {
            confirmAction({
                title: title || 'Validation Warning',
                message: message || 'Please review the form warnings before saving.',
                variant: 'warning',
                confirmText: 'Got It / Fix Errors',
                cancelButtonText: 'Dismiss',
                onConfirm: function() {
                    if (typeof callback === 'function') {
                        callback();
                    } else {
                        var firstError = $('.bg-soft-danger:visible, .border-danger:visible, .is-invalid:visible, [id^="batch_error_"]:visible').first();
                        if (firstError.length) {
                            var remarkRow = firstError.closest('.remark-row');
                            if (remarkRow.length && !remarkRow.is(':visible')) {
                                remarkRow.slideDown(150);
                            }
                            $('html, body').animate({
                                scrollTop: firstError.offset().top - 160
                            }, 450);
                        }
                    }
                }
            });
        }
    }

    $(document).ready(function() {
        // Initialize PO Selector
        $('#po_selector').on('change', function() {
            var poId = $(this).val();
            if (!poId) {
                resetGrnItems();
                return;
            }

            var url = "{{ route('grns.get-po-items', ':poId') }}".replace(':poId', poId);
            
            $('#grnItemsTbody').html('<tr><td colspan="10" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div>{{ __('purchase.js_loading_po_items') }}</td></tr>');

            $.ajax({
                url: url,
                type: 'GET',
                success: function(res) {
                    if (res.success) {
                        $('#vendor_display').val(res.vendor_name);
                        $('#vendor_id').val(res.vendor_id);
                        if (res.warehouse_id) {
                            $('#warehouse_id').val(res.warehouse_id);
                        }

                        renderPoItems(res.items);
                    } else {
                        showWarningModal('Failed to Load PO', '{{ __('purchase.js_failed_to_load_po_details') }}');
                    }
                },
                error: function() {
                    showWarningModal('Error Fetching PO', '{{ __('purchase.js_error_fetching_po_items') }}');
                    resetGrnItems();
                }
            });
        });

        // Trigger change if PO pre-selected
        if ($('#po_selector').val()) {
            $('#po_selector').trigger('change');
        }

        function resetGrnItems() {
            $('#vendor_display').val('');
            $('#vendor_id').val('');
            $('#itemsCountBadge').text('0 {{ __('purchase.items') }}');
            $('#grnItemsTbody').html('<tr><td colspan="10" class="text-center py-4 text-muted"><i class="feather-info me-1"></i>{{ __('purchase.select_po_to_load_details') }}</td></tr>');
            $('#grnItemsTfoot').hide();
        }

        function renderPoItems(items) {
            if (!items || items.length === 0) {
                $('#grnItemsTbody').html('<tr><td colspan="10" class="text-center py-4 text-muted">{{ __('purchase.js_no_pending_items_found') }}</td></tr>');
                $('#grnItemsTfoot').hide();
                return;
            }

            var html = '';
            items.forEach(function(item, idx) {
                var remaining = parseFloat(item.remaining_qty);
                var defaultRec = remaining > 0 ? remaining : 0;
                var defaultRej = 0;
                var defaultAcc = defaultRec - defaultRej;
                var unitRate = parseFloat(item.unit_rate);
                var totalAmt = defaultAcc * unitRate;

                var isSerialTracked = item.track_serial_number;
                var isBatchTracked = item.track_batch;

                var trackingBadgeHtml = '';
                if (isSerialTracked) {
                    trackingBadgeHtml = `<span class="badge bg-soft-primary text-primary fs-10 px-2 py-0.5 ms-2"><i class="feather-hash me-1"></i>Serial Tracked</span>`;
                } else if (isBatchTracked) {
                    trackingBadgeHtml = `<span class="badge bg-soft-warning text-warning fs-10 px-2 py-0.5 ms-2"><i class="feather-layers me-1"></i>Batch Tracked</span>`;
                }

                var isAutoExpanded = isSerialTracked || isBatchTracked;
                var defaultPrefix = (item.product_code || 'SN-').replace(/[^a-zA-Z0-9]/g, '').toUpperCase() + '-';

                var trackingSectionHtml = '';
                if (isSerialTracked) {
                    trackingSectionHtml = `
                        <div class="col-md-7">
                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                <label class="form-label fs-11 fw-bold text-uppercase text-primary mb-0">
                                    <i class="feather-hash me-1"></i>Serial Numbers (Scan / Manual)
                                </label>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-soft-warning text-warning fs-11 serial-count-badge" id="serial_count_badge_${idx}">
                                        0 / ${Math.round(defaultAcc)} Serials
                                    </span>
                                    <button type="button" class="btn btn-xs btn-soft-primary fw-semibold btn-auto-serial" 
                                            data-idx="${idx}" 
                                            data-product="${item.product_name}"
                                            data-prefix="${defaultPrefix}"
                                            data-count="${Math.round(defaultAcc)}">
                                        <i class="feather-zap me-1"></i>Auto-Generate
                                    </button>
                                </div>
                            </div>
                            
                            <textarea class="form-control form-control-sm fs-12 text-dark font-monospace serial-textarea" 
                                      name="items[${idx}][serial_numbers]" 
                                      id="serial_input_${idx}" 
                                      data-idx="${idx}"
                                      rows="2"
                                      placeholder="Scan barcodes or enter serial numbers separated by comma or new line..."></textarea>
                            <span class="fs-10 text-muted">Each scanned barcode or comma/newline registers 1 unique Serial Number.</span>
                        </div>
                    `;
                } else if (isBatchTracked) {
                    var defaultBatchNo1 = 'BAT-' + (item.product_code || 'LOT').replace(/[^a-zA-Z0-9]/g, '') + '-01';
                    var defaultMfg = new Date().toISOString().split('T')[0];
                    var nextYear = new Date();
                    nextYear.setFullYear(nextYear.getFullYear() + 1);
                    var defaultExp = nextYear.toISOString().split('T')[0];

                    trackingSectionHtml = `
                        <div class="col-md-7">
                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                <label class="form-label fs-11 fw-bold text-uppercase text-warning mb-0">
                                    <i class="feather-layers me-1"></i>Batch / Lot Inward Allocation
                                </label>
                                <button type="button" class="btn btn-xs btn-outline-warning fw-semibold btn-add-batch-row" data-idx="${idx}" data-code="${item.product_code || 'LOT'}">
                                    <i class="feather-plus me-1"></i>+ Add Another Batch / Lot
                                </button>
                            </div>
                            
                            <div id="batch_rows_container_${idx}" class="d-grid gap-2">
                                <div class="batch-split-row border p-2 rounded-3 bg-white shadow-xs" data-batch-idx="0">
                                    <div class="row g-2 align-items-center">
                                        <div class="col-md-3">
                                            <label class="fs-10 text-muted mb-0 fw-semibold">Batch No <span class="text-danger">*</span></label>
                                            <input type="text" 
                                                   class="form-control form-control-sm font-monospace fw-bold text-dark" 
                                                   name="items[${idx}][batches][0][batch_number]" 
                                                   placeholder="e.g. BAT-01" 
                                                   value="${defaultBatchNo1}"
                                                   required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="fs-10 text-muted mb-0 fw-semibold">Qty Rec. <span class="text-danger">*</span></label>
                                            <input type="number" step="0.0001" min="0.0001" 
                                                   class="form-control form-control-sm text-center font-monospace fw-bold text-primary batch-qty-input" 
                                                   data-idx="${idx}"
                                                   name="items[${idx}][batches][0][received_qty]" 
                                                   value="${defaultAcc.toFixed(2)}" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="fs-10 text-muted mb-0 fw-semibold">Mfg Date</label>
                                            <input type="date" 
                                                   class="form-control form-control-sm text-dark fs-11" 
                                                   name="items[${idx}][batches][0][manufacturing_date]" 
                                                   value="${defaultMfg}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="fs-10 text-muted mb-0 fw-semibold">Expiry Date</label>
                                            <input type="date" 
                                                   class="form-control form-control-sm text-dark fs-11" 
                                                   name="items[${idx}][batches][0][expiry_date]" 
                                                   value="${defaultExp}">
                                        </div>
                                        <div class="col-md-1 text-center pt-3">
                                            <button type="button" class="btn btn-xs btn-link text-muted p-0 border-0 disabled" title="Primary Batch Line" disabled style="opacity: 0.3;">
                                                <i class="feather-trash-2 fs-14"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div id="batch_error_${idx}" class="mt-1.5 fs-11 fw-bold p-2 rounded shadow-xs" style="display: none;"></div>
                            <span class="fs-10 text-muted mt-1 d-block"><i class="feather-info me-1"></i>Split received items into multiple batches if received in different lots/boxes.</span>
                        </div>
                    `;
                }

                html += `
                    <tr class="grn-item-row" data-idx="${idx}">
                        <td class="text-center fw-semibold text-muted">${idx + 1}</td>
                        <td>
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="fw-bold text-dark d-flex align-items-center">
                                        ${item.product_name} ${trackingBadgeHtml}
                                    </div>
                                    <div class="fs-11 text-muted">Code: ${item.product_code || 'N/A'} | UOM: <strong>${item.uom_name}</strong></div>
                                </div>
                                <button type="button" class="btn btn-xs ${isAutoExpanded ? 'bg-soft-danger text-danger' : 'bg-soft-primary text-primary'} border-0 btn-toggle-remark ms-2 px-2 py-1 rounded-pill fw-semibold" data-target="#remark_row_${idx}">
                                    <i class="${isAutoExpanded ? 'feather-minus' : 'feather-plus'} me-1 fs-11"></i><span class="btn-lbl">${isAutoExpanded ? '{{ __('purchase.hide_lbl') }}' : '{{ __('purchase.note_lbl') }}'}</span>
                                </button>
                            </div>
                            <input type="hidden" name="items[${idx}][purchase_order_item_id]" value="${item.purchase_order_item_id}">
                            <input type="hidden" name="items[${idx}][product_id]" value="${item.product_id}">
                        </td>
                        <td class="text-center font-monospace font-semibold">${item.ordered_qty.toFixed(2)}</td>
                        <td class="text-center font-monospace text-muted">${item.previous_received_qty.toFixed(2)}</td>
                        <td class="text-center font-monospace fw-bold text-danger item-remaining" data-remaining="${remaining}">
                            ${remaining.toFixed(2)}
                        </td>
                        <td>
                            <input type="number" step="0.0001" min="0" max="${remaining}" 
                                   class="odoo-table-input text-center font-monospace fw-bold input-receive" 
                                   name="items[${idx}][received_qty]" 
                                   value="${defaultRec.toFixed(2)}" required>
                        </td>
                        <td>
                            <input type="number" step="0.0001" min="0" 
                                   class="odoo-table-input text-center font-monospace input-reject text-danger" 
                                   name="items[${idx}][rejected_qty]" 
                                   value="${defaultRej.toFixed(2)}">
                        </td>
                        <td class="text-center font-monospace fw-bold text-success cell-accepted">
                            ${defaultAcc.toFixed(2)}
                        </td>
                        <td class="text-end font-monospace">${unitRate.toFixed(2)}</td>
                        <td class="text-end font-monospace fw-bold text-dark cell-total">
                            ${totalAmt.toFixed(2)}
                        </td>
                    </tr>
                    <tr id="remark_row_${idx}" class="remark-row bg-white" style="${isAutoExpanded ? '' : 'display: none;'}">
                        <td class="border-0"></td>
                        <td colspan="9" class="py-2 px-3 border-top-0">
                            <div class="p-3 rounded-3 border bg-white shadow-xs">
                                <div class="row g-3">
                                    ${trackingSectionHtml}
                                    
                                    <div class="${trackingSectionHtml ? 'col-md-5' : 'col-md-12'}">
                                        <label class="form-label fs-11 fw-bold text-uppercase text-muted mb-1.5">
                                            <i class="feather-message-square me-1"></i>Rejection Reason / Remarks
                                        </label>
                                        <textarea class="form-control form-control-sm fs-12 text-dark" 
                                                  name="items[${idx}][remarks]" 
                                                  rows="2" 
                                                  placeholder="{{ __('purchase.js_enter_rejection_remarks') }} ${item.product_name}..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                `;
            });

            $('#grnItemsTbody').html(html);
            $('#itemsCountBadge').text(items.length + ' ' + (items.length === 1 ? '{{ __('purchase.item') }}' : '{{ __('purchase.items') }}'));
            $('#grnItemsTfoot').show();

            recalculateTotals();
        }

        // Toggle Remarks Row via Soft Primary Button
        $(document).on('click', '.btn-toggle-remark', function() {
            var target = $($(this).data('target'));
            var icon = $(this).find('i');
            var lbl = $(this).find('.btn-lbl');

            target.slideToggle(150);
            if (icon.hasClass('feather-plus')) {
                icon.removeClass('feather-plus').addClass('feather-minus');
                $(this).removeClass('bg-soft-primary text-primary').addClass('bg-soft-danger text-danger');
                lbl.text('{{ __('purchase.hide_lbl') }}');
            } else {
                icon.removeClass('feather-minus').addClass('feather-plus');
                $(this).removeClass('bg-soft-danger text-danger').addClass('bg-soft-primary text-primary');
                lbl.text('{{ __('purchase.note_lbl') }}');
            }
        });

        // Open Auto-Generate Modal
        $(document).on('click', '.btn-auto-serial', function() {
            var idx = $(this).data('idx');
            var product = $(this).data('product');
            var prefix = $(this).data('prefix') || 'SN-';
            var count = parseInt($(this).data('count')) || 1;

            var row = $('tr.grn-item-row[data-idx="' + idx + '"]');
            var acc = Math.round(parseFloat(row.find('.cell-accepted').text()) || count);

            $('#auto_serial_target_idx').val(idx);
            $('#autoSerialModalTitle').html('<i class="feather-zap me-1"></i>Auto-Generate Serials (' + product + ')');
            $('#auto_serial_prefix').val(prefix);
            $('#auto_serial_start').val('1001');
            $('#auto_serial_count').val(acc > 0 ? acc : 1);

            var modal = new bootstrap.Modal(document.getElementById('autoSerialModal'));
            modal.show();
        });

        // Confirm Auto-Generate Serials
        $('#btn_confirm_auto_serial').on('click', function() {
            var idx = $('#auto_serial_target_idx').val();
            var prefix = $('#auto_serial_prefix').val().trim();
            var startNo = parseInt($('#auto_serial_start').val()) || 1001;
            var count = parseInt($('#auto_serial_count').val()) || 1;

            var serials = [];
            for (var i = 0; i < count; i++) {
                serials.push(prefix + (startNo + i));
            }

            var textarea = $('#serial_input_' + idx);
            textarea.val(serials.join(', ')).trigger('input');

            var modalEl = document.getElementById('autoSerialModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        });

        // Dynamic + Add Another Batch Row Handler
        $(document).on('click', '.btn-add-batch-row', function() {
            var idx = $(this).data('idx');
            var prodCode = $(this).data('code') || 'LOT';
            var container = $('#batch_rows_container_' + idx);
            var batchCount = container.children('.batch-split-row').length;
            var nextNum = (batchCount + 1).toString().padStart(2, '0');
            var batchNo = 'BAT-' + prodCode.replace(/[^a-zA-Z0-9]/g, '') + '-' + nextNum;

            var defaultMfg = new Date().toISOString().split('T')[0];
            var nextYear = new Date();
            nextYear.setFullYear(nextYear.getFullYear() + 1);
            var defaultExp = nextYear.toISOString().split('T')[0];

            var newRowHtml = `
                <div class="batch-split-row border p-2 rounded-3 bg-white shadow-xs mt-2" data-batch-idx="${batchCount}">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3">
                            <label class="fs-10 text-muted mb-0 fw-semibold">Batch No <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control form-control-sm font-monospace fw-bold text-dark" 
                                   name="items[${idx}][batches][${batchCount}][batch_number]" 
                                   placeholder="e.g. BAT-${nextNum}" 
                                   value="${batchNo}"
                                   required>
                        </div>
                        <div class="col-md-2">
                            <label class="fs-10 text-muted mb-0 fw-semibold">Qty Rec. <span class="text-danger">*</span></label>
                            <input type="number" step="0.0001" min="0.0001" 
                                   class="form-control form-control-sm text-center font-monospace fw-bold text-primary batch-qty-input" 
                                   name="items[${idx}][batches][${batchCount}][received_qty]" 
                                   value="1" required>
                        </div>
                        <div class="col-md-3">
                            <label class="fs-10 text-muted mb-0 fw-semibold">Mfg Date</label>
                            <input type="date" 
                                   class="form-control form-control-sm text-dark fs-11" 
                                   name="items[${idx}][batches][${batchCount}][manufacturing_date]" 
                                   value="${defaultMfg}">
                        </div>
                        <div class="col-md-3">
                            <label class="fs-10 text-muted mb-0 fw-semibold">Expiry Date</label>
                            <input type="date" 
                                   class="form-control form-control-sm text-dark fs-11" 
                                   name="items[${idx}][batches][${batchCount}][expiry_date]" 
                                   value="${defaultExp}">
                        </div>
                        <div class="col-md-1 text-center pt-3">
                            <button type="button" class="btn btn-xs btn-link text-danger remove-batch-subrow-btn p-0 border-0" title="Remove Batch Line">
                                <i class="feather-trash-2 fs-14"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;

            container.append(newRowHtml);
        });

        $(document).on('click', '.remove-batch-subrow-btn', function() {
            var container = $(this).closest('.d-grid');
            var idxId = container.attr('id');
            $(this).closest('.batch-split-row').remove();
            if (idxId) {
                var idx = idxId.replace('batch_rows_container_', '');
                validateBatchAllocations(idx);
            }
        });

        function validateBatchAllocations(idx) {
            var row = $('tr.grn-item-row[data-idx="' + idx + '"]');
            var accVal = parseFloat(row.find('.cell-accepted').text()) || 0;
            var container = $('#batch_rows_container_' + idx);
            var errorDiv = $('#batch_error_' + idx);

            if (!container.length || !container.find('.batch-qty-input').length) return true;

            var totalAllocated = 0;
            container.find('.batch-qty-input').each(function() {
                totalAllocated += parseFloat($(this).val()) || 0;
            });

            totalAllocated = Math.round(totalAllocated * 10000) / 10000;
            var roundedAccepted = Math.round(accVal * 10000) / 10000;

            if (totalAllocated > roundedAccepted) {
                container.find('.batch-qty-input').addClass('border-danger text-danger');
                errorDiv.removeClass('bg-soft-success text-success bg-soft-info text-info bg-soft-warning text-warning')
                        .addClass('bg-soft-danger text-danger border border-danger-subtle d-block')
                        .html('<i class="feather-alert-triangle me-1 fs-12"></i>Batch Allocation Error: Total Batch Qty (' + totalAllocated + ') exceeds Accepted Item Qty (' + roundedAccepted + '). Please reduce batch quantities before saving.');
                return false;
            } else if (totalAllocated < roundedAccepted) {
                container.find('.batch-qty-input').removeClass('border-danger text-danger');
                errorDiv.removeClass('bg-soft-danger text-danger bg-soft-success text-success')
                        .addClass('bg-soft-warning text-warning border border-warning-subtle d-block')
                        .html('<i class="feather-info me-1 fs-12"></i>Batch Allocation Notice: Allocated ' + totalAllocated + ' of ' + roundedAccepted + ' Accepted Qty. Remaining ' + (Math.round((roundedAccepted - totalAllocated)*10000)/10000) + ' unassigned.');
                return true;
            } else {
                container.find('.batch-qty-input').removeClass('border-danger text-danger');
                errorDiv.removeClass('bg-soft-danger text-danger bg-soft-warning text-warning')
                        .addClass('bg-soft-success text-success border border-success-subtle d-block')
                        .html('<i class="feather-check-circle me-1 fs-12"></i>100% Batch Quantity Allocated (' + totalAllocated + ' / ' + roundedAccepted + ').');
                return true;
            }
        }

        $(document).on('input change', '.batch-qty-input', function() {
            var container = $(this).closest('.d-grid');
            var idxId = container.attr('id');
            if (idxId) {
                var idx = idxId.replace('batch_rows_container_', '');
                validateBatchAllocations(idx);
            }
        });

        $('form').on('submit', function(e) {
            var firstErrorIdx = null;
            var hasBatchError = false;

            $('.grn-item-row').each(function() {
                var idx = $(this).data('idx');
                if (!validateBatchAllocations(idx)) {
                    hasBatchError = true;
                    if (firstErrorIdx === null) {
                        firstErrorIdx = idx;
                    }
                }
            });

            if (hasBatchError) {
                e.preventDefault();
                showWarningModal(
                    'Batch Quantity Error',
                    'Total Batch Quantity allocated exceeds the Accepted Quantity for one or more items. Click below to jump directly to the error.',
                    function() {
                        if (firstErrorIdx !== null) {
                            var remarkRow = $('#remark_row_' + firstErrorIdx);
                            var toggleBtn = $('tr.grn-item-row[data-idx="' + firstErrorIdx + '"]').find('.btn-toggle-remark');
                            
                            if (remarkRow.length && !remarkRow.is(':visible')) {
                                remarkRow.slideDown(150);
                                toggleBtn.find('i').removeClass('feather-plus').addClass('feather-minus');
                                toggleBtn.removeClass('bg-soft-primary text-primary').addClass('bg-soft-danger text-danger');
                                toggleBtn.find('.btn-lbl').text('{{ __('purchase.hide_lbl') }}');
                            }

                            var targetError = $('#batch_error_' + firstErrorIdx);
                            if (targetError.length) {
                                $('html, body').animate({
                                    scrollTop: targetError.offset().top - 180
                                }, 450, function() {
                                    $('#batch_rows_container_' + firstErrorIdx).find('.batch-qty-input.border-danger').first().focus();
                                });
                            }
                        }
                    }
                );
                return false;
            }
        });

        // Live validation and count update for Serial Textarea
        $(document).on('input change', '.serial-textarea', function() {
            var idx = $(this).data('idx');
            var text = $(this).val();
            var row = $('tr.grn-item-row[data-idx="' + idx + '"]');
            var accVal = Math.round(parseFloat(row.find('.cell-accepted').text()) || 0);

            var items = text.split(/[\r\n,]+/).map(s => s.trim()).filter(s => s.length > 0);
            var uniqueSerials = [...new Set(items)];
            var count = uniqueSerials.length;

            var badge = $('#serial_count_badge_' + idx);
            if (accVal > 0 && count === accVal) {
                badge.removeClass('bg-soft-warning text-warning bg-soft-danger text-danger')
                     .addClass('bg-soft-success text-success')
                     .html('<i class="feather-check-circle me-1"></i>' + count + ' / ' + accVal + ' Serials Verified');
            } else if (count > 0) {
                badge.removeClass('bg-soft-success text-success bg-soft-danger text-danger')
                     .addClass('bg-soft-warning text-warning')
                     .text(count + ' / ' + accVal + ' Serials Entered');
            } else {
                badge.removeClass('bg-soft-success text-success bg-soft-danger text-danger')
                     .addClass('bg-soft-warning text-warning')
                     .text('0 / ' + accVal + ' Serials');
            }
        });

        // Live calculation on input change
        $(document).on('input change', '.input-receive, .input-reject', function() {
            var row = $(this).closest('tr.grn-item-row');
            var idx = row.data('idx');
            var remaining = parseFloat(row.find('.item-remaining').data('remaining')) || 0;
            var receiveInput = row.find('.input-receive');
            var rejectInput = row.find('.input-reject');

            var recVal = parseFloat(receiveInput.val()) || 0;
            var rejVal = parseFloat(rejectInput.val()) || 0;

            if (recVal > remaining) {
                showWarningModal('Quantity Exceeded', '{{ __('purchase.js_rec_qty_exceed_rem') }} (' + remaining.toFixed(2) + ')');
                recVal = remaining;
                receiveInput.val(recVal.toFixed(2));
            }

            if (rejVal > recVal) {
                showWarningModal('Quantity Exceeded', '{{ __('purchase.js_rej_qty_exceed_rec') }} (' + recVal.toFixed(2) + ')');
                rejVal = recVal;
                rejectInput.val(rejVal.toFixed(2));
            }

            // Auto-expand remarks row if rejection quantity > 0
            var remarkRow = $('#remark_row_' + idx);
            var toggleBtn = row.find('.btn-toggle-remark');
            if (rejVal > 0 && !remarkRow.is(':visible')) {
                remarkRow.slideDown(150);
                toggleBtn.find('i').removeClass('feather-plus').addClass('feather-minus');
                toggleBtn.removeClass('bg-soft-primary text-primary').addClass('bg-soft-danger text-danger');
                toggleBtn.find('.btn-lbl').text('{{ __('purchase.hide_lbl') }}');
            }

            var accVal = Math.max(0, recVal - rejVal);
            var rateVal = parseFloat(row.find('td:nth-child(9)').text()) || 0;
            var totalAmt = accVal * rateVal;

            row.find('.cell-accepted').text(accVal.toFixed(2));
            row.find('.cell-total').text(totalAmt.toFixed(2));

            // Trigger serial count re-validation
            $('#serial_input_' + idx).trigger('input');

            recalculateTotals();
        });

        function recalculateTotals() {
            var totalRec = 0;
            var totalRej = 0;
            var totalAcc = 0;
            var totalAmt = 0;

            $('tr.grn-item-row').each(function() {
                var rec = parseFloat($(this).find('.input-receive').val()) || 0;
                var rej = parseFloat($(this).find('.input-reject').val()) || 0;
                var acc = parseFloat($(this).find('.cell-accepted').text()) || 0;
                var amt = parseFloat($(this).find('.cell-total').text()) || 0;

                totalRec += rec;
                totalRej += rej;
                totalAcc += acc;
                totalAmt += amt;
            });

            $('#footTotalReceive').text(totalRec.toFixed(2));
            $('#footTotalReject').text(totalRej.toFixed(2));
            $('#footTotalAccepted').text(totalAcc.toFixed(2));
            $('#footTotalAmount').text(totalAmt.toFixed(2));
        }
    });
</script>

<!-- Auto-Generate Serials Modal -->
<div class="modal fade" id="autoSerialModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-2.5 px-3">
                <h6 class="modal-title fw-bold text-white fs-13 mb-0" id="autoSerialModalTitle">
                    <i class="feather-zap me-1"></i>Auto-Generate Serial Numbers
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="auto_serial_target_idx" value="">
                <div class="mb-2">
                    <label class="form-label fs-11 fw-bold text-muted text-uppercase mb-1">Prefix</label>
                    <input type="text" id="auto_serial_prefix" class="form-control form-control-sm font-monospace" placeholder="e.g. SN-LAP-">
                </div>
                <div class="mb-2">
                    <label class="form-label fs-11 fw-bold text-muted text-uppercase mb-1">Start Number</label>
                    <input type="number" id="auto_serial_start" class="form-control form-control-sm font-monospace" value="1001" min="1">
                </div>
                <div class="mb-3">
                    <label class="form-label fs-11 fw-bold text-muted text-uppercase mb-1">Quantity</label>
                    <input type="number" id="auto_serial_count" class="form-control form-control-sm font-monospace" value="1" min="1">
                </div>
                <div class="d-grid">
                    <button type="button" class="btn btn-sm btn-primary fw-semibold" id="btn_confirm_auto_serial">
                        <i class="feather-check-circle me-1"></i>Generate & Fill
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Fast Barcode Scan Lookup for GRN Received Items
    function handleGrnBarcodeScan() {
        const input = document.getElementById('fastBarcodeScanInput');
        const code = input ? input.value.trim() : '';
        if (!code) return;

        fetch("{{ route('inventory.products.barcodeLookup') }}?code=" + encodeURIComponent(code), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.product) {
                    const prod = data.product;
                    let foundMatch = false;
                    document.querySelectorAll('#grnItemsTbody tr').forEach(row => {
                        const prodNameEl = row.querySelector('.product-name, td:nth-child(2)');
                        if (prodNameEl && (prodNameEl.textContent.includes(prod.name) || prodNameEl.textContent.includes(prod.sku))) {
                            const rxInput = row.querySelector('input[name$="[quantity_received]"]');
                            if (rxInput) {
                                rxInput.value = (parseFloat(rxInput.value) || 0) + 1;
                                rxInput.dispatchEvent(new Event('input', { bubbles: true }));
                                foundMatch = true;
                            }
                        }
                    });
                    if (!foundMatch) {
                        alert('Scanned item (' + prod.name + ') is not present in the selected Purchase Order items.');
                    }
                    input.value = '';
                } else {
                    alert('Product Not Found for scanned code: ' + code);
                    input.value = '';
                }
            })
            .catch(err => {
                alert('Barcode lookup failed: ' + code);
                input.value = '';
            });
    }

    document.getElementById('fastBarcodeScanInput')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleGrnBarcodeScan();
        }
    });
    document.getElementById('fastBarcodeScanBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        handleGrnBarcodeScan();
    });
</script>
@endpush
