@extends('layouts.duralux')

@section('title', 'Create Dispatch Order | SaaS ERP')
@section('page-title', 'Create Dispatch Order')
@section('breadcrumb', 'Sales / Dispatch Orders / Create')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .select2-container {
            width: 100% !important;
            max-width: 100% !important;
        }
        #dispatchItemsTable {
            table-layout: fixed;
            width: 100% !important;
        }
        #dispatchItemsTable td {
            vertical-align: top !important;
        }
    </style>
@endpush

@section('content')
    <div class="erp-single-panel bg-white p-4">

        <form action="{{ route('sales.dispatches.store') }}" method="POST" id="dispatchForm">
            @csrf
            <input type="hidden" name="material_requirement_id" id="deliveryOrderId" value="{{ old('material_requirement_id') }}">

            <x-ui.odoo-form-ui type="sheet">
                <!-- Actions Top Bar -->
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 flex-wrap gap-2">
                    <div>
                        <h4 class="fw-bold text-dark mb-0">New Dispatch Order</h4>
                        <span class="fs-12 text-muted">Issue an Outward Dispatch Order against a Material Requirement or create a Direct Outward Dispatch.</span>
                    </div>
                    <div class="d-flex gap-2">
                        <x-ui.button href="{{ route('sales.dispatches.index') }}" variant="light" size="sm" class="border">
                            Cancel
                        </x-ui.button>
                        <x-ui.button type="submit" id="saveDispatchBtn" variant="primary" size="sm" icon="feather-save" style="background-color: #714B67; border-color: #714B67;">
                            Save Dispatch Order
                        </x-ui.button>
                    </div>
                </div>

                <!-- Dispatch Mode Switcher Bar -->
                <div class="mb-4 bg-light p-3 rounded border">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted d-block mb-2">Dispatch Creation Mode:</label>
                    <div class="d-flex gap-4 flex-wrap align-items-center">
                        <div class="form-check">
                            <input class="form-check-input mode-radio" type="radio" name="dispatch_mode" id="mode_mr" value="mr" checked autocomplete="off">
                            <label class="form-check-label fw-bold text-dark fs-13" for="mode_mr">
                                <i class="feather-truck me-1 text-primary"></i>Against Material Requirement
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input mode-radio" type="radio" name="dispatch_mode" id="mode_direct" value="direct" autocomplete="off">
                            <label class="form-check-label fw-bold text-dark fs-13" for="mode_direct">
                                <i class="feather-plus-circle me-1 text-success"></i>Direct Dispatch (Over-The-Counter Outward)
                            </label>
                        </div>
                    </div>
                </div>

                <!-- MR Selection Banner (MR Mode) -->
                <div id="mrBanner" class="d-flex align-items-center justify-content-between gap-3 mb-4 p-3 bg-light rounded border">
                    <div>
                        <span class="d-block fs-12 text-muted">Material Requirement Reference:</span>
                        <strong id="selectedDeliveryOrder" class="text-dark fs-14">No material requirement selected</strong>
                        <span id="selectedCustomer" class="d-block fs-12 text-muted"></span>
                    </div>
                    <x-ui.button type="button" variant="outline-primary" size="sm" icon="feather-truck" data-bs-toggle="modal" data-bs-target="#deliveryOrderPicker">
                        Select Material Requirement
                    </x-ui.button>
                </div>

                <!-- Direct Mode Customer Dropdown (Direct Mode) -->
                <div id="customerSelectCol" class="mb-4 p-3 bg-light rounded border" style="display: none;">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="Customer Name (Optional)" name="customer_id" id="customerSelect" class="odoo-select2">
                                <option value="">— Direct / Walk-in Customer —</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}" @selected(old('customer_id') == $c->id)>
                                        {{ $c->name }} {{ $c->code ? "({$c->code})" : '' }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <span class="badge bg-soft-success text-success fs-12 p-2">
                                <i class="feather-check-circle me-1"></i>Direct Outward Mode Active
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Header Details Section -->
                <div class="row g-4 mb-4 fs-13 text-dark">
                    <div class="col-md-6 border-end pe-md-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-calendar me-2"></i>Dispatch & Logistics Details</h6>
                        <x-ui.odoo-form-ui type="input" inputType="date" label="Dispatch Date" name="dispatch_date" :value="old('dispatch_date', now()->toDateString())" :required="true" />
                        <x-ui.odoo-form-ui type="input" label="Carrier / Courier Partner" name="carrier" :value="old('carrier')" placeholder="e.g. Blue Dart, DHL, Professional Courier" />
                        <x-ui.odoo-form-ui type="input" label="Tracking / Docket Number" name="tracking_number" :value="old('tracking_number')" placeholder="AWB or tracking reference" />
                    </div>

                    <div class="col-md-6 ps-md-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-truck me-2"></i>Vehicle & Driver Information</h6>
                        <x-ui.odoo-form-ui type="input" label="Vehicle Number" name="vehicle_number" :value="old('vehicle_number')" placeholder="e.g. MH-12-AB-1234" />
                        <x-ui.odoo-form-ui type="input" label="Driver Name" name="driver_name" :value="old('driver_name')" placeholder="Driver's full name" />
                        <x-ui.odoo-form-ui type="input" label="Driver Phone Number" name="driver_phone" :value="old('driver_phone')" placeholder="e.g. +91 98765 43210" />
                    </div>
                </div>

                <!-- Line Items Matrix Section -->
                <div class="border-top pt-4">
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                        <div>
                            <h6 class="fw-bold text-dark mb-0 fs-14"><i class="feather-layers me-2 text-primary"></i>Items to Dispatch</h6>
                            <span id="itemsHint" class="fs-12 text-muted">Select a material requirement or scan/add items directly below.</span>
                        </div>
                        <div class="d-flex align-items-center gap-2" style="width: 420px;">
                            <div class="input-group input-group-sm shadow-2xs rounded overflow-hidden" style="border: 1px solid #cbd5e1 !important;">
                                <span class="input-group-text bg-primary text-white border-0 px-3 fw-semibold"><i class="feather-camera me-1"></i> Barcode</span>
                                <input type="text" id="fastBarcodeScanInput" class="form-control border-0 bg-white" placeholder="Scan Barcode / SKU (Press Enter)..." autocomplete="off" style="font-size: 13px;">
                                <button type="button" class="btn btn-primary border-0 px-3" id="fastBarcodeScanBtn"><i class="feather-search"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive border rounded bg-white">
                        <x-ui.odoo-form-ui type="table" id="dispatchItemsTable">
                            <thead class="table-light fs-12">
                                <tr>
                                    <th style="width: 45%;" class="ps-3">Product Details</th>
                                    <th style="width: 30%;">Warehouse Location</th>
                                    <th class="text-end mr-col" style="width: 8%;">Ordered</th>
                                    <th class="text-end mr-col" style="width: 8%;">Available</th>
                                    <th class="text-end mr-col" style="width: 8%;">Reserved</th>
                                    <th class="text-end mr-col" style="width: 8%;">Dispatched</th>
                                    <th class="text-end mr-col" style="width: 8%;">Remaining</th>
                                    <th class="text-end" style="width: 18%;">Dispatch Qty</th>
                                    <th class="text-center direct-col" style="width: 7%; display: none;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="dispatchItemsBody" class="fs-13">
                                <tr id="emptyItemsRow">
                                    <td colspan="9" class="text-center py-4 text-muted fs-12">
                                        <i class="feather-info me-1"></i>No items selected.
                                    </td>
                                </tr>
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>

                    <!-- Add a line button outside table (Matching Sales Orders & Quotations UI) -->
                    <div class="mt-3" id="directAddContainer" style="display: none;">
                        <button type="button" class="btn btn-xs btn-outline-primary fw-bold" id="addDirectItemBtn" style="font-size: 10px; padding: 2px 8px; text-transform: none !important;">
                            <i class="feather-plus me-1"></i>Add a line
                        </button>
                    </div>
                </div>

                <!-- Internal Notes -->
                <div class="border-top pt-4 mt-4">
                    <x-ui.odoo-form-ui type="textarea" label="Internal Notes & Instructions" name="notes" rows="3" placeholder="Special delivery instructions or gate pass notes...">{{ old('notes') }}</x-ui.odoo-form-ui>
                </div>

                <!-- Bottom Action Bar -->
                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <x-ui.button href="{{ route('sales.dispatches.index') }}" variant="light" class="border px-4">
                        Discard
                    </x-ui.button>
                    <x-ui.button type="submit" id="saveDispatchBtnFooter" variant="primary" icon="feather-save" class="px-4" style="background-color: #714B67; border-color: #714B67;" disabled="disabled">
                        Save Dispatch Order
                    </x-ui.button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>

    <!-- MR Picker Modal Component -->
    <x-ui.modal id="deliveryOrderPicker" title="Select Material Requirement" size="lg" :centered="true" :scrollable="true">
        <div class="mb-3">
            <x-ui.odoo-form-ui type="input" id="deliveryOrderSearch" placeholder="Search by material requirement, sales order, or customer..." />
        </div>
        <div id="deliveryOrderList" class="d-grid gap-2">
            <div class="text-center py-4 text-muted">
                <div class="spinner-border spinner-border-sm me-2 text-primary" role="status"></div>Loading material requirements...
            </div>
        </div>
        <x-slot:footer>
            <x-ui.button type="button" variant="light" class="border" data-bs-dismiss="modal">Cancel</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
@endsection

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script>
        const warehouses = @json($formattedWarehouses);
        const productsList = @json($formattedProducts);

        const deliveryOrderId = document.getElementById('deliveryOrderId');
        const deliveryOrderList = document.getElementById('deliveryOrderList');
        const itemsBody = document.getElementById('dispatchItemsBody');
        const saveButtonTop = document.getElementById('saveDispatchBtn');
        const saveButtonFooter = document.getElementById('saveDispatchBtnFooter');
        const itemsHint = document.getElementById('itemsHint');
        const pickerElement = document.getElementById('deliveryOrderPicker');
        const searchInput = document.getElementById('deliveryOrderSearch');
        const addDirectItemBtn = document.getElementById('addDirectItemBtn');
        const directAddContainer = document.getElementById('directAddContainer');
        const customerSelectCol = document.getElementById('customerSelectCol');
        const mrBanner = document.getElementById('mrBanner');

        let deliveryOrders = [];
        let directItemIndex = 0;

        function initSelect2(context) {
            $(context).find('.odoo-select2').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });
        }

        $(document).ready(function() {
            initSelect2(document);
        });

        function setSaveButtonsDisabled(disabled) {
            if (saveButtonTop) saveButtonTop.disabled = disabled;
            if (saveButtonFooter) saveButtonFooter.disabled = disabled;
        }

        // Mode Switching (MR vs Direct)
        document.querySelectorAll('input[name="dispatch_mode"]').forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'direct') {
                    deliveryOrderId.value = '';
                    mrBanner.style.display = 'none';
                    customerSelectCol.style.display = 'block';
                    directAddContainer.style.display = 'block';
                    itemsHint.textContent = 'Add product line items manually to issue a Direct Outward Dispatch.';
                    document.querySelectorAll('.mr-col').forEach(el => el.style.display = 'none');
                    document.querySelectorAll('.direct-col').forEach(el => el.style.display = '');
                    itemsBody.innerHTML = '';
                    addDirectItemRow();
                } else {
                    mrBanner.style.display = 'flex';
                    customerSelectCol.style.display = 'none';
                    directAddContainer.style.display = 'none';
                    itemsHint.textContent = 'Select a material requirement to load its items.';
                    document.querySelectorAll('.mr-col').forEach(el => el.style.display = '');
                    document.querySelectorAll('.direct-col').forEach(el => el.style.display = 'none');
                    itemsBody.innerHTML = '<tr id="emptyItemsRow"><td colspan="9" class="text-center py-4 text-muted fs-12"><i class="feather-info me-1"></i>No items selected.</td></tr>';
                    setSaveButtonsDisabled(true);
                }
            });
        });

        // Add Direct Item Row
        addDirectItemBtn.addEventListener('click', addDirectItemRow);

        function addDirectItemRow() {
            const idx = directItemIndex++;
            const productOptions = productsList.map(p => 
                `<option value="${p.id}" data-serial="${p.track_serial_number ? '1' : '0'}" data-batch="${p.track_batch ? '1' : '0'}">${escapeHtml(p.name)} ${p.sku ? `(${escapeHtml(p.sku)})` : ''}</option>`
            ).join('');

            const warehouseOptions = warehouses.map(w => 
                `<option value="${w.id}">${escapeHtml(w.name)}</option>`
            ).join('');

            const newRow = `
                <tr class="item-row" id="direct_row_${idx}">
                    <td class="ps-3 pe-2 py-2 align-top">
                        <select name="items[${idx}][product_id]" class="form-select odoo-table-select product-select odoo-select2 fw-semibold" data-idx="${idx}" required>
                            <option value="">-- Select Product --</option>
                            ${productOptions}
                        </select>

                        <!-- Batch Selection Box (FEFO) -->
                        <div id="batch_block_${idx}" class="mt-2 p-2.5 bg-white border border-warning-subtle rounded shadow-sm" style="display: none;">
                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                <span class="fw-bold fs-11 text-dark d-flex align-items-center">
                                    <i class="feather-layers text-warning me-1 fs-12"></i>Batch / Lot (FEFO Expiry Order):
                                </span>
                                <span class="badge bg-soft-warning text-warning fs-10 fw-bold"><i class="feather-clock me-1"></i>FEFO Active</span>
                            </div>
                            <select name="items[${idx}][batch_number]" id="batch_select_${idx}" class="form-select form-select-sm font-monospace fs-11 text-dark odoo-select2">
                                <option value="">-- Auto-Deduct Nearest Expiry Batch --</option>
                            </select>
                            <span class="fs-10 text-muted mt-1 d-block"><i class="feather-info me-1"></i>Sorted by earliest expiry date. Select specific batch or leave auto.</span>
                        </div>

                        <!-- Serial Numbers Box -->
                        <div id="serial_block_${idx}" class="mt-2 p-3 bg-white border border-primary-subtle rounded shadow-sm" style="display: none;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="fw-bold fs-11 text-dark d-flex align-items-center">
                                    <i class="feather-hash text-primary me-1 fs-12"></i>Tracked Serial Numbers:
                                </span>
                                <button type="button" class="btn btn-xs btn-outline-primary fw-semibold fetch-serials-btn" data-idx="${idx}">
                                    <i class="feather-zap me-1"></i>Auto-Fill Available
                                </button>
                            </div>
                            <textarea name="items[${idx}][serial_numbers]" id="serial_input_${idx}" class="form-control form-control-sm font-monospace fs-11 text-dark" rows="3" placeholder="Scan barcode or enter serial numbers (1 per line or comma separated)..."></textarea>
                            <span class="fs-10 text-muted mt-1 d-block"><i class="feather-info me-1"></i>System will automatically assign these serial numbers upon saving.</span>
                        </div>
                    </td>
                    <td class="px-2 py-2 align-top">
                        <select name="items[${idx}][warehouse_id]" class="form-select odoo-table-select warehouse-select odoo-select2" data-idx="${idx}" required>
                            ${warehouseOptions}
                        </select>
                    </td>
                    <td class="text-end px-2 py-2 align-top">
                        <input type="number" name="items[${idx}][quantity]" class="form-control odoo-table-input text-end dispatch-qty-input fw-bold" value="1" min="0.0001" step="any" required>
                    </td>
                    <td class="text-center px-2 py-2 align-top">
                        <button type="button" class="btn btn-sm btn-link text-danger remove-row-btn p-1 border-0" data-idx="${idx}" title="Remove line item">
                            <i class="feather-trash-2 fs-14"></i>
                        </button>
                    </td>
                </tr>
            `;

            const $tr = $(newRow);
            $('#dispatchItemsBody').append($tr);
            initSelect2($tr);
            setSaveButtonsDisabled(false);

            function updateBlocks() {
                const selectedOpt = $tr.find('.product-select option:selected');
                const isSerial = selectedOpt.data('serial') == 1;
                const isBatch = selectedOpt.data('batch') == 1;
                const prodId = $tr.find('.product-select').val();
                const whId = $tr.find('.warehouse-select').val();

                const serialBlock = document.getElementById(`serial_block_${idx}`);
                if (serialBlock) serialBlock.style.display = isSerial ? 'block' : 'none';

                const batchBlock = document.getElementById(`batch_block_${idx}`);
                if (batchBlock) {
                    batchBlock.style.display = isBatch ? 'block' : 'none';
                    if (isBatch && prodId) {
                        fetchBatchesForLine(prodId, whId, idx);
                    }
                }
            }

            $tr.find('.product-select, .warehouse-select').on('change', updateBlocks);

            // Remove Row Listener
            $tr.find('.remove-row-btn').on('click', function() {
                $tr.remove();
                if (itemsBody.children.length === 0) {
                    itemsBody.innerHTML = '<tr id="emptyItemsRow"><td colspan="9" class="text-center py-4 text-muted fs-12"><i class="feather-info me-1"></i>No items selected.</td></tr>';
                    setSaveButtonsDisabled(true);
                }
            });

            // Fetch Available Serials Listener
            $tr.find('.fetch-serials-btn').on('click', function() {
                const prodId = $tr.find('.product-select').val();
                const whId = $tr.find('.warehouse-select').val();
                if (!prodId) {
                    alert('Please select a product first.');
                    return;
                }
                fetch(`{{ route('sales.dispatches.available-serials') }}?product_id=${prodId}&warehouse_id=${whId}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.serials && data.serials.length) {
                            const qty = parseFloat($tr.find('.dispatch-qty-input').val()) || 1;
                            const serialsToFill = data.serials.slice(0, qty).join("\n");
                            document.getElementById(`serial_input_${idx}`).value = serialsToFill;
                        } else {
                            alert('No available serial numbers found in stock for this product/warehouse.');
                        }
                    });
            });
        }

        function fetchBatchesForLine(productId, warehouseId, lineIdx) {
            const batchSelect = document.getElementById(`batch_select_${lineIdx}`);
            if (!batchSelect) return;

            const $select = $(batchSelect);
            if ($select.hasClass("select2-hidden-accessible")) {
                $select.select2('destroy');
            }

            batchSelect.innerHTML = '<option value="">Loading FEFO batches...</option>';

            fetch(`{{ route('sales.dispatches.available-batches') }}?product_id=${productId}&warehouse_id=${warehouseId}`)
                .then(res => res.json())
                .then(data => {
                    batchSelect.innerHTML = '<option value="">-- Auto-Deduct Nearest Expiry Batch (FEFO Rule) --</option>';
                    if (data.batches && data.batches.length) {
                        data.batches.forEach((b, index) => {
                            const badge = index === 0 ? '⚡ Earliest Expiry' : '';
                            const expTag = b.is_expired ? `🚨 EXPIRED (${b.expiry_date})` : (b.is_expiring_soon ? `⚠️ Exp: ${b.expiry_date}` : `Exp: ${b.expiry_date}`);
                            const whLabel = b.warehouse_name ? ` in ${b.warehouse_name}` : '';
                            const optText = `${b.batch_number} (${expTag}) [Available: ${b.available_qty}${whLabel}] ${badge}`;
                            const opt = document.createElement('option');
                            opt.value = b.batch_number;
                            opt.textContent = optText;
                            batchSelect.appendChild(opt);
                        });
                    } else {
                        batchSelect.innerHTML = '<option value="">No active batches found in warehouse</option>';
                    }

                    $select.select2({
                        theme: 'bootstrap-5',
                        width: '100%'
                    });
                })
                .catch(() => {
                    batchSelect.innerHTML = '<option value="">-- Auto-Deduct Nearest Expiry Batch --</option>';
                    $select.select2({
                        theme: 'bootstrap-5',
                        width: '100%'
                    });
                });
        }

        // Auto-select if material_requirement_id is passed in URL
        const urlParams = new URLSearchParams(window.location.search);
        const preselectedId = parseInt(urlParams.get('material_requirement_id')) || null;

        pickerElement.addEventListener('show.bs.modal', () => {
            if (deliveryOrders.length) {
                renderDeliveryOrders();
                return;
            }

            fetch('{{ route('sales.dispatches.pending-mr') }}', { headers: { Accept: 'application/json' } })
                .then(response => response.ok ? response.json() : Promise.reject())
                .then(res => {
                    deliveryOrders = res.data || res || [];
                    renderDeliveryOrders();
                })
                .catch((err) => {
                    console.error('Fetch error:', err);
                    deliveryOrderList.innerHTML = '<div class="alert alert-danger mb-0">Material requirements could not be loaded. Please try again.</div>';
                });
        });

        searchInput.addEventListener('input', renderDeliveryOrders);

        function renderDeliveryOrders() {
            const search = searchInput.value.trim().toLowerCase();
            const filtered = deliveryOrders.filter(order =>
                [order.requirement_number, order.sales_order, order.customer].filter(Boolean).some(value => value.toLowerCase().includes(search))
            );

            if (!filtered.length) {
                deliveryOrderList.innerHTML = '<div class="text-center py-4 text-muted">No pending material requirements found.</div>';
                return;
            }

            deliveryOrderList.innerHTML = filtered.map(order => `
                <button type="button" class="btn btn-light border text-start p-3 delivery-order-option" data-id="${order.id}" data-bs-dismiss="modal">
                    <strong class="d-block text-primary">${escapeHtml(order.requirement_number)}</strong>
                    <span class="fs-12 text-muted">${escapeHtml(order.sales_order || '')} &mdash; ${escapeHtml(order.customer || 'No customer')}</span>
                    <span class="badge bg-soft-secondary text-secondary float-end">${order.items.length} item(s)</span>
                </button>
            `).join('');
        }

        deliveryOrderList.addEventListener('click', event => {
            const option = event.target.closest('.delivery-order-option');
            if (!option) return;

            const order = deliveryOrders.find(item => item.id === Number(option.dataset.id));
            if (!order) return;

            selectDeliveryOrder(order);
            bootstrap.Modal.getOrCreateInstance(pickerElement).hide();
        });

        function selectDeliveryOrder(order) {
            deliveryOrderId.value = order.id;
            document.getElementById('selectedDeliveryOrder').textContent = order.requirement_number;
            document.getElementById('selectedCustomer').textContent = `${order.sales_order || ''}${order.customer ? ` — ${order.customer}` : ''}`;
            renderDispatchItems(order.items);
        }

        function renderDispatchItems(items) {
            let rowsHtml = '';
            let dispatchableCount = 0;

            items.forEach((item, index) => {
                const options = warehouses.map(w =>
                    `<option value="${w.id}" ${Number(item.warehouse_id) === Number(w.id) ? 'selected' : ''}>${escapeHtml(w.name)}</option>`
                ).join('');

                if (item.fully_dispatched) {
                    rowsHtml += `
                        <tr class="text-muted bg-light">
                            <td class="ps-3 align-top">
                                <strong class="text-muted">${escapeHtml(item.product_name || 'Unknown product')}</strong>
                                ${item.product_sku ? `<small class="d-block text-muted font-monospace fs-11">SKU: ${escapeHtml(item.product_sku)}</small>` : ''}
                            </td>
                            <td class="align-top"><span class="text-muted fs-12">—</span></td>
                            <td class="text-end align-top">${item.quantity_ordered}</td>
                            <td class="text-end fw-semibold text-success align-top">${item.available_qty ?? 0}</td>
                            <td class="text-end align-top">${item.quantity_reserved}</td>
                            <td class="text-end fw-bold text-success align-top">${item.already_dispatched}</td>
                            <td class="text-end fw-bold text-muted align-top">0</td>
                            <td class="text-end align-top">
                                <span class="badge bg-soft-success text-success fs-11 px-2">Fully Dispatched</span>
                            </td>
                        </tr>`;
                } else {
                    dispatchableCount++;
                    rowsHtml += `
                        <tr>
                            <td class="ps-3 align-top">
                                <input type="hidden" name="items[${index}][material_requirement_item_id]" value="${item.id}">
                                <input type="hidden" name="items[${index}][product_id]" value="${item.product_id}">
                                <strong class="text-dark fs-13">${escapeHtml(item.product_name || 'Unknown product')}</strong>
                                ${item.product_sku ? `<small class="d-block text-muted font-monospace fs-11">SKU: ${escapeHtml(item.product_sku)}</small>` : ''}
                            </td>
                            <td class="align-top"><select name="items[${index}][warehouse_id]" class="form-select odoo-table-select odoo-select2">${options}</select></td>
                            <td class="text-end fw-semibold mr-col align-top">${item.quantity_ordered}</td>
                            <td class="text-end fw-semibold text-success mr-col align-top">${item.available_qty ?? 0}</td>
                            <td class="text-end fw-semibold text-info mr-col align-top">${item.quantity_reserved}</td>
                            <td class="text-end fw-bold mr-col align-top ${item.already_dispatched > 0 ? 'text-warning' : 'text-muted'}">${item.already_dispatched}</td>
                            <td class="text-end fw-bold text-primary mr-col align-top">${item.remaining_qty}</td>
                            <td class="text-end pe-3 align-top">
                                <input type="hidden" name="items[${index}][quantity_ordered]" value="${item.quantity_ordered}">
                                <input
                                    type="number"
                                    name="items[${index}][quantity]"
                                    class="odoo-table-input text-end dispatch-qty-input fw-bold"
                                    value="${item.dispatch_qty}"
                                    min="0.0001"
                                    max="${item.remaining_qty}"
                                    data-max="${item.remaining_qty}"
                                    required
                                >
                                <div class="qty-error-msg text-danger fs-11 fw-semibold text-end mt-1 d-none">Dispatch Qty must be greater than 0.</div>
                            </td>
                        </tr>`;
                }
            });

            itemsBody.innerHTML = rowsHtml;
            initSelect2(itemsBody);
            itemsHint.textContent = `${dispatchableCount} item(s) available to dispatch.`;
            setSaveButtonsDisabled(dispatchableCount === 0);
        }

        function escapeHtml(value) {
            const element = document.createElement('div');
            element.textContent = value;
            return element.innerHTML;
        }

        $(document).on('input change', '.dispatch-qty-input', function() {
            const val = parseFloat($(this).val());
            const max = parseFloat($(this).data('max')) || Infinity;
            const $cell = $(this).closest('td');
            let $error = $cell.find('.qty-error-msg');

            if (!$error.length) {
                $error = $('<div class="qty-error-msg text-danger fs-11 fw-semibold text-end mt-1">Dispatch Qty must be greater than 0.</div>');
                $cell.append($error);
            }

            if (isNaN(val) || val <= 0) {
                $(this).css('border-bottom-color', '#ef4444');
                $error.text('Dispatch Qty must be greater than 0.').removeClass('d-none');
                setSaveButtonsDisabled(true);
            } else if (val > max) {
                $(this).css('border-bottom-color', '#ef4444');
                $error.text(`Cannot exceed remaining quantity (${max}).`).removeClass('d-none');
                setSaveButtonsDisabled(true);
            } else {
                $(this).css('border-bottom-color', '#cbd5e1');
                $error.addClass('d-none');
                setSaveButtonsDisabled(false);
            }
        });

        document.getElementById('dispatchForm').addEventListener('submit', event => {
            const mode = document.querySelector('input[name="dispatch_mode"]:checked').value;
            if (mode === 'mr' && (!deliveryOrderId.value || !itemsBody.querySelector('input[name$="[quantity]"]'))) {
                event.preventDefault();
                alert('Select a material requirement with at least one dispatch item before saving.');
                return;
            }
            if (mode === 'direct' && !itemsBody.querySelector('select[name$="[product_id]"]')) {
                event.preventDefault();
                alert('Please add at least one product line item to dispatch.');
                return;
            }
        });

        if (preselectedId) {
            fetch('{{ route('sales.dispatches.pending-mr') }}', { headers: { Accept: 'application/json' } })
                .then(response => response.ok ? response.json() : Promise.reject())
                .then(res => {
                    deliveryOrders = res.data || res || [];
                    const order = deliveryOrders.find(o => o.id === preselectedId);
                    if (order) {
                        selectDeliveryOrder(order);
                    }
                });
        }

        // Fast Barcode Scan Lookup for Dispatches
        function handleDispatchBarcodeScan() {
            const input = document.getElementById('fastBarcodeScanInput');
            const code = input ? input.value.trim() : '';
            if (!code) return;

            // Switch to direct mode if in MR mode
            const currentMode = document.querySelector('input[name="dispatch_mode"]:checked')?.value;
            if (currentMode !== 'direct') {
                const directRadio = document.getElementById('mode_direct');
                if (directRadio) {
                    directRadio.checked = true;
                    directRadio.dispatchEvent(new Event('change'));
                }
            }

            $.ajax({
                url: "{{ route('inventory.products.barcodeLookup') }}",
                data: { code: code },
                success: function(data) {
                    if (data.success && data.product) {
                        const prod = data.product;

                        // Ensure product is in productsList
                        const exists = productsList.some(p => p.id == prod.id);
                        if (!exists) {
                            productsList.push({
                                id: prod.id,
                                name: prod.name,
                                sku: prod.sku,
                                track_serial_number: prod.track_serial_number || 0,
                                track_batch: prod.track_batch || 0
                            });
                        }

                        // 1. Look for existing row with SAME product AND SAME warehouse
                        let targetTr = null;
                        const targetWhId = data.warehouse_id || null;

                        $('#dispatchItemsBody tr').each(function() {
                            const tr = $(this);
                            const rowProdId = tr.find('select[name$="[product_id]"]').val();
                            const rowWhId = tr.find('select[name$="[warehouse_id]"]').val();

                            if (rowProdId == prod.id) {
                                if (!targetWhId || !rowWhId || rowWhId == targetWhId) {
                                    targetTr = tr;
                                    return false; // Found matching product + warehouse row
                                }
                            }
                        });

                        let isNewRow = false;
                        // 2. If no matching row found, look for an empty row
                        if (!targetTr) {
                            $('#dispatchItemsBody tr').each(function() {
                                const tr = $(this);
                                const rowProdId = tr.find('select[name$="[product_id]"]').val();
                                if (!rowProdId) {
                                    targetTr = tr;
                                    isNewRow = true;
                                    return false;
                                }
                            });
                        }

                        // 3. If no empty row found, create a new row
                        if (!targetTr) {
                            addDirectItemRow();
                            targetTr = $('#dispatchItemsBody tr').last();
                            isNewRow = true;
                        }

                        if (targetTr && targetTr.length) {
                            const targetSelect = targetTr.find('select[name$="[product_id]"]');

                            if (!targetSelect.find(`option[value="${prod.id}"]`).length) {
                                targetSelect.append(`<option value="${prod.id}" data-serial="${prod.track_serial_number ? '1' : '0'}" data-batch="${prod.track_batch ? '1' : '0'}">${escapeHtml(prod.name)} ${prod.sku ? `(${escapeHtml(prod.sku)})` : ''}</option>`);
                            }
                            if (targetSelect.val() != prod.id) {
                                targetSelect.val(prod.id).trigger('change');
                            }

                            // Auto-select Warehouse for this row if provided
                            if (targetWhId) {
                                const whSelect = targetTr.find('select[name$="[warehouse_id]"]');
                                if (whSelect.length && whSelect.find(`option[value="${targetWhId}"]`).length) {
                                    if (whSelect.val() != targetWhId) {
                                        whSelect.val(targetWhId).trigger('change');
                                    }
                                }
                            }

                            // Qty Increment or Initial Set
                            const qtyInput = targetTr.find('input[name$="[qty]"], input[name$="[dispatch_qty]"], input[name$="[quantity]"]');
                            if (qtyInput.length) {
                                const currentQty = parseFloat(qtyInput.val()) || 0;
                                if (!isNewRow && currentQty > 0) {
                                    qtyInput.val(currentQty + 1).trigger('input').trigger('change');
                                } else {
                                    if (currentQty === 0) {
                                        qtyInput.val(1).trigger('input').trigger('change');
                                    }
                                }
                            }

                            // Auto-fill Serial Number if scanned code is a Serial Number
                            const scannedSn = data.serial_number || (data.is_serial ? code : null);
                            if (scannedSn) {
                                const serialTextarea = targetTr.find('textarea[name$="[serial_numbers]"], input[name$="[serial_numbers]"]');
                                if (serialTextarea.length) {
                                    let currentText = serialTextarea.val().trim();
                                    let serialsArr = currentText ? currentText.split(/[\r\n,;]+/).map(s => s.trim()).filter(Boolean) : [];
                                    if (!serialsArr.includes(scannedSn)) {
                                        serialsArr.push(scannedSn);
                                        serialTextarea.val(serialsArr.join('\n'));
                                    }
                                    if (qtyInput.length && serialsArr.length > (parseFloat(qtyInput.val()) || 0)) {
                                        qtyInput.val(serialsArr.length).trigger('input').trigger('change');
                                    }
                                }
                            }
                        }

                        if (input) input.value = '';
                    } else {
                        alert('Product or Serial Number Not Found for code: ' + code);
                        if (input) input.value = '';
                    }
                },
                error: function() {
                    alert('Product or Serial Number Not Found for scanned code: ' + code);
                    if (input) input.value = '';
                }
            });
        }

        document.getElementById('fastBarcodeScanInput')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleDispatchBarcodeScan();
            }
        });
        document.getElementById('fastBarcodeScanBtn')?.addEventListener('click', function(e) {
            e.preventDefault();
            handleDispatchBarcodeScan();
        });
    </script>
@endpush
