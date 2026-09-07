@extends('layouts.duralux')

@section('title', 'New Subcontract Delivery Challan | SaaS ERP')
@section('page-title', 'Create Subcontract Delivery Challan')
@section('breadcrumb', 'New Delivery Challan')

@section('content')
<div class="erp-single-panel bg-white">

    @if ($errors->any())
        <x-ui.toast :auto="true" type="error" title="Validation Error: {{ $errors->first() }}" />
    @endif

    <form method="POST" action="{{ route('production.subcontract.delivery-challans.store') }}" id="challan-form">
        @csrf

        {{-- Hidden References --}}
        <input type="hidden" name="production_order_id" value="{{ $order?->id }}">
        <input type="hidden" name="production_order_operation_id" value="{{ $operation?->id }}">

        <x-ui.odoo-form-ui type="sheet">
            <!-- Header with Close Button -->
            <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                <div>
                    <h4 class="fw-bold text-dark mb-1"><i class="feather-truck me-2 text-primary"></i>New Subcontract Delivery Challan (Gate Pass)</h4>
                    <span class="fs-12 text-muted">Outward company material dispatch with per-item warehouse stock verification.</span>
                </div>
                <a href="{{ route('production.subcontract.delivery-challans.index') }}" class="text-muted hover-danger fs-18">
                    <i class="feather-x"></i>
                </a>
            </div>

            @php
                $isWipJobWork = $operation?->isWipJobWork() ?? false;
            @endphp

            @if($order)
                @if($isWipJobWork)
                    <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center justify-content-between mb-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center fs-20">
                                <i class="feather-layers"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Subcontracted WIP Job Work Dispatch (Shopfloor Intermediate WIP)</h6>
                                <div class="fs-12 text-dark mt-1">
                                    Predecessor Operation: <strong>{{ $prevOp ? "Op #{$prevOp->operation_number} — {$prevOp->name}" : "Shopfloor Launch" }}</strong>
                                    | Available Shopfloor WIP for Dispatch: <strong class="{{ ($availableWip ?? 0) > 0 ? 'text-success' : 'text-danger' }}">{{ number_format($availableWip ?? 0, 2) }} PCS</strong>
                                </div>
                                @if(($availableWip ?? 0) <= 0)
                                    <div class="fs-12 text-danger fw-bold mt-1">
                                        <i class="feather-alert-triangle me-1"></i>Predecessor operation has not produced any available WIP yet (0.00 PCS). Dispatching to vendor is blocked until previous operation produces output.
                                    </div>
                                @endif
                            </div>
                        </div>
                        <span class="badge bg-soft-warning text-dark border font-monospace fs-11">WIP Job Work</span>
                    </div>
                @else
                    <div class="alert alert-info border-0 shadow-sm d-flex align-items-center justify-content-between mb-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md bg-info text-white rounded-circle d-flex align-items-center justify-content-center fs-20">
                                <i class="feather-layers"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Linked Production Order: <span class="text-primary font-monospace">{{ $order->order_number }}</span></h6>
                                <div class="fs-12 text-muted">
                                    Finished Product: <strong>{{ $order->product?->name }}</strong> (SKU: {{ $order->product?->sku }}) | Order Qty: <strong>{{ number_format($order->quantity_ordered, 2) }}</strong>
                                </div>
                                @if($operation)
                                    <div class="fs-12 text-dark mt-1">
                                        Outsourced Operation: <span class="badge bg-primary text-white font-monospace">Op #{{ $operation->operation_number }} — {{ $operation->name }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <span class="badge bg-soft-info text-info border font-monospace fs-11">Subcontract Dispatch</span>
                    </div>
                @endif
            @endif

            <!-- Master Header Fields (Using Odoo Form Component System) -->
            <div class="row g-4 mb-4 fs-13 text-dark">
                <!-- Left Column -->
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Subcontractor / Vendor" name="vendor_id" id="vendor_id" :required="true" :error-text="$errors->first('vendor_id')">
                        <option value="">Select Vendor</option>
                        @foreach($vendors as $v)
                            <option value="{{ $v->id }}" @selected(($vendor?->id == $v->id || old('vendor_id') == $v->id))>
                                {{ $v->name }} ({{ $v->vendor_code ?? 'VND' }})
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>

                    <x-ui.odoo-form-ui type="select" label="Default Warehouse" name="warehouse_id" id="default_warehouse_id">
                        <option value="">Select Default Warehouse (Optional)</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" @selected(($defaultWarehouseId == $wh->id || old('warehouse_id') == $wh->id))>
                                {{ $wh->name }}
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>

                    <x-ui.odoo-form-ui type="input" label="Challan Date" name="challan_date" id="challan_date" inputType="date" :value="old('challan_date', date('Y-m-d'))" :required="true" :error-text="$errors->first('challan_date')" />

                    <x-ui.odoo-form-ui type="input" label="Expected Return Date" name="expected_return_date" id="expected_return_date" inputType="date" :value="old('expected_return_date', $operation ? date('Y-m-d', strtotime('+' . ($operation->subcontract_lead_time_days ?? 1) . ' days')) : '')" :error-text="$errors->first('expected_return_date')" />
                </div>

                <!-- Right Column -->
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" label="Vehicle Number" name="vehicle_number" id="vehicle_number" placeholder="e.g. MH-12-AB-1234" :value="old('vehicle_number')" :error-text="$errors->first('vehicle_number')" />

                    <x-ui.odoo-form-ui type="input" label="Transporter Name" name="transporter_name" id="transporter_name" placeholder="e.g. Logistics Express" :value="old('transporter_name')" :error-text="$errors->first('transporter_name')" />

                    <x-ui.odoo-form-ui type="input" label="Lorry Receipt (LR) #" name="lr_number" id="lr_number" placeholder="e.g. LR-987654" :value="old('lr_number')" :error-text="$errors->first('lr_number')" />

                    <x-ui.odoo-form-ui type="input" label="Driver Name" name="driver_name" id="driver_name" placeholder="Driver name / Mobile #" :value="old('driver_name')" :error-text="$errors->first('driver_name')" />

                    <x-ui.odoo-form-ui type="textarea" label="Remarks / Notes" name="notes" id="notes" placeholder="Dispatch notes, gatekeeper instructions..." :rows="2" :value="old('notes')" :error-text="$errors->first('notes')" />
                </div>
            </div>

            <!-- Dispatched Company Material Lines Table -->
            <div class="mb-4">
                <h5 class="fw-bold text-dark mb-3"><i class="feather-box me-1 text-warning"></i>Dispatched Company Material Lines</h5>

                <div class="table-responsive mb-3">
                    <x-ui.odoo-form-ui type="table" id="items-table" data-is-wip-job-work="{{ $isWipJobWork ? '1' : '0' }}">
                        <thead class="table-light fs-11 text-uppercase text-muted">
                            <tr>
                                <th style="width: 5%" class="text-center">SEQ</th>
                                <th style="width: 32%">MATERIAL / PRODUCT *</th>
                                <th style="width: 25%">{{ $isWipJobWork ? 'DISPATCH LOCATION / WAREHOUSE *' : 'SOURCE WAREHOUSE *' }}</th>
                                <th style="width: 15%">{{ $isWipJobWork ? 'AVAILABLE WIP QTY' : 'AVAILABLE QTY' }}</th>
                                <th style="width: 12%">DISPATCH QTY *</th>
                                <th style="width: 8%">UOM</th>
                                <th style="width: 5%" class="text-center">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($prefilledItems as $idx => $item)
                                <tr class="item-row" data-row-idx="{{ $idx }}" data-available-wip="{{ number_format($item['available_wip'] ?? $availableWip ?? 0, 2, '.', '') }}">
                                    <td class="fw-bold text-center align-middle row-seq">
                                        {{ $idx + 1 }}
                                        <input type="hidden" name="items[{{ $idx }}][production_wip_id]" class="wip-id-input" value="{{ $item['production_wip_id'] ?? '' }}" />
                                        <input type="hidden" name="items[{{ $idx }}][production_batch_id]" class="batch-id-input" value="{{ $item['production_batch_id'] ?? '' }}" />
                                    </td>
                                    <td class="align-middle">
                                        <x-ui.odoo-form-ui type="select" name="items[{{ $idx }}][product_id]" class="product-select" :searchable="false">
                                            <option value="">Select Product</option>
                                            @foreach($products as $p)
                                                <option value="{{ $p->id }}" @selected($item['product_id'] == $p->id)>
                                                    {{ $p->name }} (SKU: {{ $p->sku }})
                                                </option>
                                            @endforeach
                                        </x-ui.odoo-form-ui>
                                    </td>
                                    <td class="align-middle">
                                        <x-ui.odoo-form-ui type="select" name="items[{{ $idx }}][warehouse_id]" class="warehouse-select" :searchable="false">
                                            <option value="">Select Warehouse</option>
                                            @foreach($warehouses as $wh)
                                                <option value="{{ $wh->id }}" @selected(($item['warehouse_id'] ?? $defaultWarehouseId) == $wh->id)>
                                                    {{ $wh->name }}
                                                </option>
                                            @endforeach
                                        </x-ui.odoo-form-ui>
                                    </td>
                                    <td class="align-middle">
                                        <div class="stock-status-badge font-monospace py-1"><span class="text-muted">—</span></div>
                                    </td>
                                    <td class="align-middle">
                                        <x-ui.odoo-form-ui type="input" name="items[{{ $idx }}][quantity]" inputType="number" class="quantity-input" :value="number_format($item['quantity'], 2, '.', '')" placeholder="0.00" />
                                    </td>
                                    <td class="align-middle">
                                        <x-ui.odoo-form-ui type="input" name="items[{{ $idx }}][unit_of_measure]" class="uom-input text-uppercase" :value="$item['uom']" placeholder="PCS" />
                                    </td>
                                    <td class="text-center align-middle">
                                        <button type="button" class="erp-btn-delete remove-item-btn" title="Remove line">
                                            <i class="feather-x"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr class="item-row" data-row-idx="0" data-available-wip="{{ number_format($availableWip ?? 0, 2, '.', '') }}">
                                    <td class="fw-bold text-center align-middle row-seq">
                                        1
                                        <input type="hidden" name="items[0][production_wip_id]" class="wip-id-input" value="" />
                                        <input type="hidden" name="items[0][production_batch_id]" class="batch-id-input" value="" />
                                    </td>
                                    <td class="align-middle">
                                        <x-ui.odoo-form-ui type="select" name="items[0][product_id]" class="product-select" :searchable="false">
                                            <option value="">Select Product</option>
                                            @foreach($products as $p)
                                                <option value="{{ $p->id }}">{{ $p->name }} (SKU: {{ $p->sku }})</option>
                                            @endforeach
                                        </x-ui.odoo-form-ui>
                                    </td>
                                    <td class="align-middle">
                                        <x-ui.odoo-form-ui type="select" name="items[0][warehouse_id]" class="warehouse-select" :searchable="false">
                                            <option value="">Select Warehouse</option>
                                            @foreach($warehouses as $wh)
                                                <option value="{{ $wh->id }}" @selected($defaultWarehouseId == $wh->id)>
                                                    {{ $wh->name }}
                                                </option>
                                            @endforeach
                                        </x-ui.odoo-form-ui>
                                    </td>
                                    <td class="align-middle">
                                        <div class="stock-status-badge font-monospace py-1"><span class="text-muted">—</span></div>
                                    </td>
                                    <td class="align-middle">
                                        <x-ui.odoo-form-ui type="input" name="items[0][quantity]" inputType="number" class="quantity-input" value="1.00" placeholder="0.00" />
                                    </td>
                                    <td class="align-middle">
                                        <x-ui.odoo-form-ui type="input" name="items[0][unit_of_measure]" class="uom-input text-uppercase" value="PCS" placeholder="PCS" />
                                    </td>
                                    <td class="text-center align-middle">
                                        <button type="button" class="erp-btn-delete remove-item-btn" title="Remove line">
                                            <i class="feather-x"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>

                <div class="mb-4">
                    <button type="button" class="btn btn-link p-0 text-primary fw-semibold text-decoration-none shadow-none border-0 bg-transparent" id="add-item-btn">
                        <i class="feather-plus me-1 fs-14"></i>Add Material Line
                    </button>
                </div>
            </div>

            <!-- Footer Action Buttons -->
            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                <a href="{{ route('production.subcontract.delivery-challans.index') }}" class="btn btn-sm btn-light border">Cancel</a>
                <button type="submit" name="status" value="draft" class="btn btn-sm btn-outline-secondary">
                    <i class="feather-save me-1"></i> Save Draft Gate Pass
                </button>
                <button type="submit" name="status" value="dispatched" id="dispatch-submit-btn" class="btn btn-sm btn-primary px-4 fw-bold shadow-sm">
                    <i class="feather-send me-1"></i> Create & Dispatch Delivery Challan {{ ($operation?->isWipJobWork() ?? false) ? '(Outward WIP Dispatch)' : '(Deduct Stock)' }}
                </button>
            </div>
        </x-ui.odoo-form-ui>
    </form>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        function fixRowInputNames() {
            $('#items-table tbody tr.item-row').each(function(idx) {
                $(this).find('.row-seq').contents().filter(function() {
                    return this.nodeType === 3; // text node
                }).first().replaceWith((idx + 1).toString());

                const $wipInp = $(this).find('.wip-id-input');
                const $batchInp = $(this).find('.batch-id-input');
                const $prodSel = $(this).find('.product-select select, select.product-select');
                const $whSel = $(this).find('.warehouse-select select, select.warehouse-select');
                const $qtyInp = $(this).find('.quantity-input input, input.quantity-input');
                const $uomInp = $(this).find('.uom-input input, input.uom-input');

                if ($wipInp.length) $wipInp.attr('name', `items[${idx}][production_wip_id]`);
                if ($batchInp.length) $batchInp.attr('name', `items[${idx}][production_batch_id]`);
                if ($prodSel.length) $prodSel.attr('name', `items[${idx}][product_id]`);
                if ($whSel.length) $whSel.attr('name', `items[${idx}][warehouse_id]`);
                if ($qtyInp.length) $qtyInp.attr('name', `items[${idx}][quantity]`);
                if ($uomInp.length) $uomInp.attr('name', `items[${idx}][unit_of_measure]`);
            });
        }

        function updateRowStockBadge(tr) {
            const $tr = $(tr);
            const $prodSel = $tr.find('.product-select select, select.product-select').first();
            const $whSel = $tr.find('.warehouse-select select, select.warehouse-select').first();
            const $qtyInp = $tr.find('.quantity-input input, input.quantity-input').first();
            const $badgeDiv = $tr.find('.stock-status-badge');

            const productId = $prodSel.length ? $prodSel.val() : null;
            const warehouseId = $whSel.length ? $whSel.val() : null;
            const reqQty = parseFloat($qtyInp.length ? $qtyInp.val() : 0) || 0;

            const isWipJobWork = $('#items-table').data('is-wip-job-work') == '1';

            if (isWipJobWork) {
                const availableWip = parseFloat($tr.data('available-wip')) || 0;
                if (availableWip > 0 && reqQty <= (availableWip + 0.0001)) {
                    $badgeDiv.html(`<span class="fw-bold text-success font-monospace fs-13">${availableWip.toFixed(2)} PCS (WIP)</span>`);
                } else {
                    $badgeDiv.html(`<span class="fw-bold text-danger font-monospace fs-13">${availableWip.toFixed(2)} PCS (WIP)</span>`);
                }
                return;
            }

            if (!warehouseId || !productId) {
                $badgeDiv.html('<span class="text-muted">—</span>');
                return;
            }

            $.ajax({
                url: `{{ route('production.subcontract.delivery-challans.check-stock') }}`,
                type: 'GET',
                data: { warehouse_id: warehouseId, product_id: productId },
                dataType: 'json',
                success: function(data) {
                    if (data.success && data.stocks) {
                        const stockObj = data.stocks[productId];
                        const availableQty = stockObj ? (parseFloat(stockObj.available_qty) || 0) : 0;

                        if (availableQty >= reqQty) {
                            $badgeDiv.html(`<span class="fw-bold text-success font-monospace fs-13">${availableQty.toFixed(2)}</span>`);
                        } else {
                            $badgeDiv.html(`<span class="fw-bold text-danger font-monospace fs-13">${availableQty.toFixed(2)}</span>`);
                        }
                    } else {
                        $badgeDiv.html('<span class="fw-bold text-danger font-monospace fs-13">0.00</span>');
                    }
                },
                error: function() {
                    $badgeDiv.html('<span class="text-muted">—</span>');
                }
            });
        }

        function updateAllRows() {
            fixRowInputNames();
            $('#items-table tbody tr.item-row').each(function() {
                updateRowStockBadge(this);
            });
        }

        // Handle Default Warehouse Change
        $(document).on('change change.select2', '#default_warehouse_id', function() {
            const defWhId = $(this).val();
            if (!defWhId) return;

            $('#items-table tbody tr.item-row').each(function() {
                const $whSelect = $(this).find('.warehouse-select select, select.warehouse-select').first();
                if ($whSelect.length) {
                    $whSelect.val(defWhId).trigger('change').trigger('change.select2');
                    updateRowStockBadge(this);
                }
            });
        });

        // Event listener for ANY change or input inside the items table
        $(document).on('change change.select2 input', '#items-table select, #items-table input', function() {
            const tr = $(this).closest('tr.item-row')[0];
            if (tr) {
                updateRowStockBadge(tr);
            }
        });

        // Add item row
        $('#add-item-btn').on('click', function() {
            const curIdx = $('#items-table tbody tr.item-row').length;
            const defaultWh = $('#default_warehouse_id select, #default_warehouse_id').val() || '';

            const trHtml = `
                <tr class="item-row" data-row-idx="${curIdx}">
                    <td class="fw-bold text-center align-middle row-seq">${curIdx + 1}</td>
                    <td class="align-middle">
                        <x-ui.odoo-form-ui type="select" name="items[${curIdx}][product_id]" class="product-select" :searchable="false">
                            <option value="">Select Product</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} (SKU: {{ $p->sku }})</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </td>
                    <td class="align-middle">
                        <x-ui.odoo-form-ui type="select" name="items[${curIdx}][warehouse_id]" class="warehouse-select" :searchable="false">
                            <option value="">Select Warehouse</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">${'{{ $wh->name }}'}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </td>
                    <td class="align-middle">
                        <div class="stock-status-badge font-monospace py-1"><span class="text-muted">—</span></div>
                    </td>
                    <td class="align-middle">
                        <x-ui.odoo-form-ui type="input" name="items[${curIdx}][quantity]" inputType="number" class="quantity-input" value="1.00" placeholder="0.00" />
                    </td>
                    <td class="align-middle">
                        <x-ui.odoo-form-ui type="input" name="items[${curIdx}][unit_of_measure]" class="uom-input text-uppercase" value="PCS" placeholder="PCS" />
                    </td>
                    <td class="text-center align-middle">
                        <button type="button" class="erp-btn-delete remove-item-btn" title="Remove line">
                            <i class="feather-x"></i>
                        </button>
                    </td>
                </tr>
            `;

            const $tr = $(trHtml);
            $('#items-table tbody').append($tr);

            if (defaultWh) {
                $tr.find('.warehouse-select select, select.warehouse-select').val(defaultWh);
            }

            fixRowInputNames();
            updateRowStockBadge($tr[0]);
        });

        // Remove item row
        $(document).on('click', '.remove-item-btn', function() {
            if ($('#items-table tbody tr.item-row').length > 1) {
                $(this).closest('tr.item-row').remove();
                fixRowInputNames();
            }
        });

        fixRowInputNames();
        updateAllRows();
    });
</script>
@endpush
