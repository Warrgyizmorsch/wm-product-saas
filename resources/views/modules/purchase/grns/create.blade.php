@extends('layouts.duralux')

@section('title', 'New Goods Receipt Note | SaaS ERP')
@section('page-title', 'Create Goods Receipt Note (GRN)')
@section('breadcrumb', 'Purchase / Goods Receipt Notes / Create')

@section('page-actions')
    <x-ui.button href="{{ route('purchase.grns.index') }}" variant="light" icon="feather-arrow-left" class="border">
        Back to GRNs
    </x-ui.button>
@endsection

@section('content')
    @php
        $currency = tenant()?->settings['currency'] ?? 'INR';
    @endphp

    <div class="row text-dark">
        <div class="col-12">
            <!-- Toast Notifications -->
            @if (session('error'))
                <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
            @endif

            <form action="{{ route('purchase.grns.store') }}" method="POST" id="grnCreateForm">
                @csrf

                <!-- Single Card Odoo Sheet Layout -->
                <x-ui.odoo-form-ui type="sheet" class="shadow-sm rounded border-0">
                    <div class="border-bottom py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center">
                            <h5 class="fw-bold text-dark mb-0 me-3"><i class="feather-plus-circle text-primary me-2"></i>New Goods Receipt Note</h5>
                            <span class="badge bg-soft-primary text-primary px-2.5 py-1 fw-bold fs-11 font-monospace">{{ $grnNumber }}</span>
                        </div>
                        <div>
                            <x-ui.button type="submit" variant="primary" icon="feather-save" class="fw-bold px-4 py-2">
                                Save GRN
                            </x-ui.button>
                        </div>
                    </div>

                    <div class="p-4 p-md-5">
                        <!-- Header Form Controls -->
                        <div class="row g-3 fs-13 pb-4 border-bottom">
                            <div class="col-md-6 border-end">
                                <h6 class="fw-bold text-primary mb-3">PO & Supplier Details</h6>

                                <x-ui.odoo-form-ui type="select" label="Select PO" name="purchase_order_id" id="po_selector" :required="true" :error-text="$errors->first('purchase_order_id')">
                                    <option value="">-- Choose Approved Purchase Order --</option>
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
                                        <x-ui.odoo-form-ui type="input" label="Vendor Name" name="vendor_display" id="vendor_display" value="{{ $selectedPo?->vendor?->name ?? '' }}" readonly="true" placeholder="Auto-loaded from PO" :error-text="$errors->first('vendor_id')" />
                                        <input type="hidden" name="vendor_id" id="vendor_id" value="{{ $selectedPo?->vendor_id ?? '' }}">
                                    </div>
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="select" label="Warehouse" name="warehouse_id" id="warehouse_id" :required="true" :error-text="$errors->first('warehouse_id')">
                                            <option value="">Select Warehouse...</option>
                                            @foreach($warehouses as $wh)
                                                <option value="{{ $wh->id }}" @selected(($selectedPo && $selectedPo->warehouse?->id === $wh->id) || $loop->first)>
                                                    {{ $wh->name }}
                                                </option>
                                            @endforeach
                                        </x-ui.odoo-form-ui>
                                    </div>
                                </div>

                                <x-ui.odoo-form-ui type="input" inputType="date" label="Receipt Date" name="received_date" id="received_date" value="{{ old('received_date', date('Y-m-d')) }}" :required="true" :error-text="$errors->first('received_date')" />
                            </div>

                            <div class="col-md-6">
                                <h6 class="fw-bold text-primary mb-3">Challan & Logistics Details</h6>

                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" label="Challan / Invoice No" name="challan_number" id="challan_number" value="{{ old('challan_number') }}" placeholder="Supplier Challan No" :error-text="$errors->first('challan_number')" />
                                    </div>
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" inputType="date" label="Challan Date" name="challan_date" id="challan_date" value="{{ old('challan_date', date('Y-m-d')) }}" :error-text="$errors->first('challan_date')" />
                                    </div>
                                </div>

                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" label="Transporter Name" name="transporter_name" id="transporter_name" value="{{ old('transporter_name') }}" placeholder="Courier / Transporter" :error-text="$errors->first('transporter_name')" />
                                    </div>
                                    <div class="col-md-6">
                                        <x-ui.odoo-form-ui type="input" label="Vehicle Number" name="vehicle_number" id="vehicle_number" value="{{ old('vehicle_number') }}" placeholder="e.g. MH-12-AB-1234" :error-text="$errors->first('vehicle_number')" />
                                    </div>
                                </div>

                                <x-ui.odoo-form-ui type="input" label="L.R. Number" name="lr_number" id="lr_number" value="{{ old('lr_number') }}" placeholder="Lorry Receipt / Docket No" :error-text="$errors->first('lr_number')" />
                            </div>
                        </div>

                        <!-- Notes Section -->
                        <div class="mt-3 mb-4">
                            <x-ui.odoo-form-ui type="textarea" label="Store Receipt Remarks / Notes" name="notes" placeholder="Enter any store verification remarks or package condition notes..." rows="2" :error-text="$errors->first('notes')">{{ old('notes') }}</x-ui.odoo-form-ui>
                        </div>

                        <!-- Item Matrix Section using Common Odoo Table Component -->
                        <div class="mt-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="fw-bold text-primary mb-0"><i class="feather-layers text-primary me-2"></i>Received Products Matrix</h6>
                                <span class="badge bg-soft-info text-info fs-11 fw-semibold" id="itemsCountBadge">0 Items</span>
                            </div>

                            <div class="table-responsive border rounded bg-white">
                                <x-ui.odoo-form-ui type="table" id="grnItemsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 4%;">#</th>
                                            <th style="width: 25%;">Product Details</th>
                                            <th style="width: 10%;" class="text-center">Ordered</th>
                                            <th style="width: 10%;" class="text-center">Prev. Rec.</th>
                                            <th style="width: 10%;" class="text-center">Remaining</th>
                                            <th style="width: 11%;" class="text-center">Receive Qty <span class="text-danger">*</span></th>
                                            <th style="width: 10%;" class="text-center">Reject Qty</th>
                                            <th style="width: 10%;" class="text-center">Accepted</th>
                                            <th style="width: 10%;" class="text-end">Rate ({{ $currency }})</th>
                                            <th style="width: 12%;" class="text-end">Total ({{ $currency }})</th>
                                        </tr>
                                    </thead>
                                    <tbody id="grnItemsTbody">
                                        <tr>
                                            <td colspan="10" class="text-center py-4 text-muted">
                                                <i class="feather-info me-1"></i>Please select a Purchase Order above to load item details.
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="table-light fw-bold" id="grnItemsTfoot" style="display: none;">
                                        <tr>
                                            <td colspan="5" class="text-end">Totals:</td>
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
    $(document).ready(function() {
        // Initialize PO Selector
        $('#po_selector').on('change', function() {
            var poId = $(this).val();
            if (!poId) {
                resetGrnItems();
                return;
            }

            var url = "{{ route('purchase.grns.get-po-items', ':poId') }}".replace(':poId', poId);
            
            $('#grnItemsTbody').html('<tr><td colspan="10" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading Purchase Order Items...</td></tr>');

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
                        alert('Failed to load PO details.');
                    }
                },
                error: function() {
                    alert('Error fetching Purchase Order items.');
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
            $('#itemsCountBadge').text('0 Items');
            $('#grnItemsTbody').html('<tr><td colspan="10" class="text-center py-4 text-muted"><i class="feather-info me-1"></i>Please select a Purchase Order above to load item details.</td></tr>');
            $('#grnItemsTfoot').hide();
        }

        function renderPoItems(items) {
            if (!items || items.length === 0) {
                $('#grnItemsTbody').html('<tr><td colspan="10" class="text-center py-4 text-muted">No pending items found for this Purchase Order.</td></tr>');
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

                html += `
                    <tr class="grn-item-row" data-idx="${idx}">
                        <td class="text-center fw-semibold text-muted">${idx + 1}</td>
                        <td>
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="fw-bold text-dark">${item.product_name}</div>
                                    <div class="fs-11 text-muted">Code: ${item.product_code || 'N/A'} | UOM: <strong>${item.uom_name}</strong></div>
                                </div>
                                <button type="button" class="btn btn-xs bg-soft-primary text-primary border-0 btn-toggle-remark ms-2 px-2 py-1 rounded-pill fw-semibold" data-target="#remark_row_${idx}">
                                    <i class="feather-plus me-1 fs-11"></i><span class="btn-lbl">Note</span>
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
                    <tr id="remark_row_${idx}" class="remark-row bg-white" style="display: none;">
                        <td class="border-0"></td>
                        <td colspan="9" class="py-2 px-3 border-top-0">
                            <div class="p-3 rounded-3 border bg-white shadow-xs">
                                <div class="d-flex align-items-center gap-2 mb-1.5">
                                    <span class="badge bg-soft-primary text-primary fs-10 fw-bold text-uppercase">
                                        <i class="feather-message-square me-1"></i>Item Remarks / Rejection Reason
                                    </span>
                                    <span class="fs-11 text-muted">For <strong>${item.product_name}</strong></span>
                                </div>
                                <input type="text" class="odoo-table-input fs-12 text-dark px-2 py-1" 
                                       name="items[${idx}][remarks]" 
                                       placeholder="Enter rejection cause, damage details, or item notes for ${item.product_name}...">
                            </div>
                        </td>
                    </tr>
                `;
            });

            $('#grnItemsTbody').html(html);
            $('#itemsCountBadge').text(items.length + ' Items');
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
                lbl.text('Hide');
            } else {
                icon.removeClass('feather-minus').addClass('feather-plus');
                $(this).removeClass('bg-soft-danger text-danger').addClass('bg-soft-primary text-primary');
                lbl.text('Note');
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
                alert('Received Qty cannot exceed Remaining Qty (' + remaining.toFixed(2) + ')');
                recVal = remaining;
                receiveInput.val(recVal.toFixed(2));
            }

            if (rejVal > recVal) {
                alert('Rejected Qty cannot exceed Received Qty (' + recVal.toFixed(2) + ')');
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
                toggleBtn.find('.btn-lbl').text('Hide');
            }

            var accVal = Math.max(0, recVal - rejVal);
            var rateVal = parseFloat(row.find('td:nth-child(9)').text()) || 0;
            var totalAmt = accVal * rateVal;

            row.find('.cell-accepted').text(accVal.toFixed(2));
            row.find('.cell-total').text(totalAmt.toFixed(2));

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
@endpush
