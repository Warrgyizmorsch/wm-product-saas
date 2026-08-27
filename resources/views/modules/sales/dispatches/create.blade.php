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
        #dispatchItemsTable th {
            white-space: nowrap !important;
            padding-left: 6px !important;
            padding-right: 6px !important;
            font-size: 11px !important;
            vertical-align: middle !important;
        }
        #dispatchItemsTable td {
            vertical-align: top !important;
            padding-bottom: 12px !important;
            overflow: visible !important;
        }
        .qty-error-msg {
            white-space: normal !important;
            word-wrap: break-word !important;
            clear: both !important;
            line-height: 1.3 !important;
            display: block !important;
        }
    </style>
@endpush

@section('content')
    <div class="erp-single-panel bg-white p-4">

        <form action="{{ route('sales.dispatches.store') }}" method="POST" id="dispatchForm">
            @csrf
            <input type="hidden" name="material_requirement_id" id="deliveryOrderId" value="{{ old('material_requirement_id', $mrId ?? request('material_requirement_id') ?? request('mr_id')) }}">

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

                <!-- MR Selection Banner (Against Material Requirement) -->
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

                <!-- Header Details Section -->
                <div class="row g-4 mb-4 fs-13 text-dark">
                    <div class="col-md-6 border-end pe-md-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-calendar me-2"></i>Dispatch & Logistics Details</h6>
                        <x-ui.odoo-form-ui type="input" inputType="date" label="Dispatch Date" name="dispatch_date" :value="old('dispatch_date', now()->toDateString())" :required="true" />
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label mb-0 fw-bold fs-12 text-muted">TRANSPORTER MASTER</label>
                                <button type="button" class="btn btn-xs btn-link text-primary p-0 text-decoration-none fw-semibold" data-bs-toggle="modal" data-bs-target="#quickTransporterModal">
                                    <i class="feather-plus-circle me-1"></i>Quick Add
                                </button>
                            </div>
                            <select name="transporter_id" id="transporterSelect" class="form-select odoo-select2">
                                <option value="">— Select Transporter —</option>
                                @foreach($transporters as $t)
                                    <option value="{{ $t->id }}" data-id="{{ $t->transporter_id }}" data-gstin="{{ $t->gstin }}" @selected(old('transporter_id') == $t->id)>
                                        {{ $t->name }} {{ $t->transporter_id ? "({$t->transporter_id})" : ($t->gstin ? "({$t->gstin})" : '') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <x-ui.odoo-form-ui type="input" label="Carrier / Courier Partner" name="carrier" id="carrierInput" :value="old('carrier')" placeholder="e.g. Blue Dart, DHL, Professional Courier" />

                        <x-ui.odoo-form-ui type="select" label="Freight Terms" name="freight_terms" id="freightTermsSelect">
                            <option value="To Pay" @selected(old('freight_terms', $prefillSalesOrder?->freight_terms ?? 'To Pay') == 'To Pay')>To Pay (Freight Collect by Driver from Customer)</option>
                            <option value="To Be Billed" @selected(old('freight_terms', $prefillSalesOrder?->freight_terms ?? '') == 'To Be Billed')>To Be Billed (Prepaid & Added to Invoice)</option>
                            <option value="Prepaid" @selected(old('freight_terms', $prefillSalesOrder?->freight_terms ?? '') == 'Prepaid')>Prepaid (Freight Included / Seller Paid)</option>
                            <option value="Customer Pickup" @selected(old('freight_terms', $prefillSalesOrder?->freight_terms ?? '') == 'Customer Pickup')>Customer Pickup (Self Vehicle)</option>
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="input" inputType="number" label="Freight Amount (₹)" name="freight_amount" id="freightAmountInput" :value="old('freight_amount', $prefillSalesOrder?->freight_amount ?? 0)" min="0" step="0.01" />
                    </div>

                    <div class="col-md-6 ps-md-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-truck me-2"></i>Vehicle & Driver Information</h6>
                        <x-ui.odoo-form-ui type="input" label="Vehicle Number" name="vehicle_number" :value="old('vehicle_number')" placeholder="e.g. MH-12-AB-1234" />
                        <x-ui.odoo-form-ui type="input" label="Driver Name" name="driver_name" :value="old('driver_name')" placeholder="Driver's full name" />
                        <x-ui.odoo-form-ui type="input" label="Driver Phone Number" name="driver_phone" :value="old('driver_phone')" placeholder="e.g. +91 98765 43210" />
                    </div>
                </div>

                <!-- Shipping Address Section -->
                <div class="mb-4">
                    <x-ui.odoo-form-ui type="textarea" label="Delivery / Shipping Address (Ship-To Destination)" name="shipping_address" rows="2" placeholder="Enter delivery location / destination address if different from default billing address...">{{ old('shipping_address') }}</x-ui.odoo-form-ui>
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
                            <thead class="table-light fs-11 text-uppercase fw-bold text-muted">
                                <tr>
                                    <th style="width: 26%;" class="ps-3">Product Details</th>
                                    <th style="width: 20%;">Warehouse Location</th>
                                    <th class="text-end mr-col" style="width: 7%;">Ordered</th>
                                    <th class="text-end mr-col" style="width: 7%;">Available</th>
                                    <th class="text-end mr-col" style="width: 7%;">Reserved</th>
                                    <th class="text-end mr-col" style="width: 7%;">Dispatched</th>
                                    <th class="text-end mr-col" style="width: 7%;">Remaining</th>
                                    <th class="text-end pe-2" style="width: 14%;">Dispatch Qty</th>
                                    <th class="text-center" style="width: 5%;">Action</th>
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
    <!-- Quick Transporter Add Modal Component -->
    <x-ui.modal id="quickTransporterModal" title="Add Transporter Master" size="md" :centered="true">
        <form id="quickTransporterForm">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold fs-12 text-dark mb-1">Transporter Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. Blue Dart Express, V-Trans" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold fs-12 text-dark mb-1">15-Digit Transporter ID (E-Way Bill)</label>
                <input type="text" name="transporter_id" class="form-control form-control-sm font-monospace" placeholder="Optional 15-digit E-Way ID">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold fs-12 text-dark mb-1">GSTIN Number</label>
                <input type="text" name="gstin" class="form-control form-control-sm font-monospace text-uppercase" placeholder="Optional 15-digit GSTIN">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold fs-12 text-dark mb-1">Phone Number</label>
                <input type="text" name="phone" class="form-control form-control-sm" placeholder="Contact number">
            </div>
            <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveQuickTransporterBtn">Save Transporter</button>
            </div>
        </form>
    </x-ui.modal>
@endsection

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script>
        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // Quick Transporter AJAX Store
        $('#quickTransporterForm').on('submit', function(e) {
            e.preventDefault();
            const $btn = $('#saveQuickTransporterBtn');
            $btn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: "{{ route('sales.transporters.quick-create') }}",
                type: "POST",
                data: $(this).serialize(),
                success: function(response) {
                    $btn.prop('disabled', false).text('Save Transporter');
                    if (response.success && response.transporter) {
                        const t = response.transporter;
                        const labelText = t.name + (t.transporter_id ? ` (${t.transporter_id})` : (t.gstin ? ` (${t.gstin})` : ''));
                        const newOpt = new Option(labelText, t.id, true, true);
                        $('#transporterSelect').append(newOpt).trigger('change');
                        
                        // Auto-fill Carrier input if empty
                        if (!$('#carrierInput').val()) {
                            $('#carrierInput').val(t.name);
                        }

                        const modalEl = document.getElementById('quickTransporterModal');
                        const modalInstance = bootstrap.Modal.getInstance(modalEl);
                        if (modalInstance) modalInstance.hide();
                        $('#quickTransporterForm')[0].reset();
                    }
                },
                error: function(err) {
                    $btn.prop('disabled', false).text('Save Transporter');
                    alert('Error saving transporter: ' + (err.responseJSON?.message || 'Invalid data'));
                }
            });
        });

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
        const preselectedId = parseInt('{{ $mrId ?? request('material_requirement_id') ?? request('mr_id') }}') || parseInt(urlParams.get('material_requirement_id')) || parseInt(urlParams.get('mr_id')) || null;

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

            if (order.freight_terms) {
                $('#freightTermsSelect').val(order.freight_terms).trigger('change');
            }
            if (order.freight_amount !== undefined) {
                $('#freightAmountInput').val(parseFloat(order.freight_amount).toFixed(2));
            }

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
                            <td class="ps-3 align-top product-detail-cell">
                                <input type="hidden" name="items[${index}][material_requirement_item_id]" value="${item.id}">
                                <input type="hidden" name="items[${index}][product_id]" value="${item.product_id}">
                                <strong class="text-dark fs-13 d-block">${escapeHtml(item.product_name || 'Unknown product')}</strong>
                                ${item.product_sku ? `<small class="d-block text-muted font-monospace fs-11">SKU: ${escapeHtml(item.product_sku)}</small>` : ''}
                            </td>
                            <td class="align-top"><select name="items[${index}][warehouse_id]" class="form-select odoo-table-select odoo-select2 item-warehouse-select">${options}</select></td>
                            <td class="text-end fw-semibold mr-col align-top">${item.quantity_ordered}</td>
                            <td class="text-end fw-semibold text-success mr-col align-top avail-qty-cell">${item.available_qty ?? 0}</td>
                            <td class="text-end fw-semibold text-info mr-col align-top reserved-qty-cell">${item.quantity_reserved}</td>
                            <td class="text-end fw-bold mr-col align-top ${item.already_dispatched > 0 ? 'text-warning' : 'text-muted'}">${item.already_dispatched}</td>
                            <td class="text-end fw-bold text-primary mr-col align-top">${item.remaining_qty}</td>
                            <td class="text-end pe-2 align-top qty-cell">
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
                                <div class="qty-error-msg text-danger fs-11 fw-bold text-end mt-1" style="display: none;"></div>
                            </td>
                            <td class="text-center align-top pe-2">
                                <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-dispatch-row-btn" title="Remove line item from this dispatch">
                                    <i class="feather-trash-2 fs-15"></i>
                                </button>
                            </td>
                        </tr>`;
                }
            });

            itemsBody.innerHTML = rowsHtml;
            initSelect2(itemsBody);
            itemsHint.textContent = `${dispatchableCount} item(s) available to dispatch.`;
            
            setTimeout(function() {
                checkAllDispatchRowsValidation();
            }, 50);
        }

        function escapeHtml(value) {
            const element = document.createElement('div');
            element.textContent = value;
            return element.innerHTML;
        }

        function validateDispatchRow($tr) {
            const $qtyInput = $tr.find('.dispatch-qty-input, input[name$="[quantity]"], input[name$="[qty]"]');
            if (!$qtyInput.length) return true;

            const val = parseFloat($qtyInput.val());
            const maxAttr = $qtyInput.attr('data-max') || $qtyInput.data('max');
            const remainingMax = (maxAttr !== undefined && maxAttr !== '' && !isNaN(parseFloat(maxAttr))) ? parseFloat(maxAttr) : Infinity;

            const $availCell = $tr.find('.avail-qty-cell');
            const $reservedCell = $tr.find('.reserved-qty-cell');

            let avail = 0;
            if ($availCell.length) {
                avail = parseFloat($availCell.text().trim()) || 0;
            } else if ($tr.attr('data-available-qty') !== undefined) {
                avail = parseFloat($tr.attr('data-available-qty')) || 0;
            }

            let reserved = 0;
            if ($reservedCell.length) {
                reserved = parseFloat($reservedCell.text().trim()) || 0;
            }

            const totalUsableStock = reserved + avail;

            const $qtyCell = $tr.find('.qty-cell');
            let $error = $qtyCell.length ? $qtyCell.find('.qty-error-msg') : $tr.find('.qty-error-msg');

            if (!$error.length) {
                $error = $('<div class="qty-error-msg text-danger fs-11 fw-bold text-end mt-1"></div>');
                if ($qtyCell.length) {
                    $qtyCell.append($error);
                } else {
                    $qtyInput.after($error);
                }
            }

            let errorMsg = '';

            if (isNaN(val) || val <= 0) {
                errorMsg = 'Dispatch Qty must be greater than 0.';
            } else if ($availCell.length && totalUsableStock <= 0) {
                errorMsg = 'No stock available in selected warehouse (Reserved: ' + reserved + ', Available: ' + avail + ').';
            } else if ($availCell.length && val > totalUsableStock) {
                errorMsg = 'Exceeded available stock (' + val + ' requested > ' + totalUsableStock + ' available).';
            } else if (remainingMax !== Infinity && val > remainingMax) {
                errorMsg = 'Exceeded remaining order quantity (' + remainingMax + ').';
            }

            if (errorMsg) {
                $qtyInput.addClass('is-invalid').css({'border-bottom': '2px solid #ef4444', 'color': '#ef4444'});
                $error.css({'display': 'block', 'color': '#ef4444', 'font-size': '11px', 'font-weight': '700', 'margin-top': '4px', 'clear': 'both'})
                      .removeClass('d-none')
                      .html('<span class="text-danger fw-bold fs-11 d-block text-end mt-1"><i class="feather-alert-circle me-1"></i>' + errorMsg + '</span>')
                      .show();

                return false;
            }

            $qtyInput.removeClass('is-invalid').css({'border-bottom': '1px solid #cbd5e1', 'color': 'inherit'});
            $error.addClass('d-none').css('display', 'none').empty().hide();
            return true;
        }

        function checkAllDispatchRowsValidation() {
            let hasError = false;
            let rowCount = 0;

            $('#dispatchItemsBody tr').each(function() {
                const $tr = $(this);
                if ($tr.find('.dispatch-qty-input, input[name$="[quantity]"], input[name$="[qty]"]').length) {
                    rowCount++;
                    const isValid = validateDispatchRow($tr);
                    if (!isValid) {
                        hasError = true;
                    }
                }
            });

            if (rowCount === 0) {
                setSaveButtonsDisabled(true);
            } else {
                setSaveButtonsDisabled(hasError);
            }

            return !hasError;
        }

        $(document).on('click', '.remove-dispatch-row-btn, .remove-row-btn', function () {
            const $tr = $(this).closest('tr');
            $tr.remove();

            const remainingRows = itemsBody.querySelectorAll('tr:not(#emptyItemsRow)');
            itemsHint.textContent = `${remainingRows.length} item(s) available to dispatch.`;

            if (remainingRows.length === 0) {
                itemsBody.innerHTML = '<tr id="emptyItemsRow"><td colspan="9" class="text-center py-4 text-muted fs-12"><i class="feather-info me-1"></i>No items selected in this dispatch order.</td></tr>';
                setSaveButtonsDisabled(true);
            } else {
                checkAllDispatchRowsValidation();
            }
        });

        $(document).on('input change keyup blur', '.dispatch-qty-input, input[name$="[quantity]"], input[name$="[qty]"]', function() {
            checkAllDispatchRowsValidation();
        });

        document.getElementById('dispatchForm').addEventListener('submit', event => {
            const modeRadio = document.querySelector('input[name="dispatch_mode"]:checked');
            const mode = modeRadio ? modeRadio.value : 'mr';
            if (mode === 'mr' && (!deliveryOrderId.value || !itemsBody.querySelector('input[name$="[quantity]"]'))) {
                event.preventDefault();
                alert('Please select a material requirement with dispatchable items.');
                return false;
            }
            if (!checkAllDispatchRowsValidation()) {
                event.preventDefault();
                alert('Please fix the quantity errors before saving the dispatch order.');
                return false;
            }
        });

        if (preselectedId) {
            fetch('{{ route('sales.dispatches.pending-mr') }}', { headers: { Accept: 'application/json' } })
                .then(response => response.ok ? response.json() : Promise.reject())
                .then(res => {
                    deliveryOrders = res.data || res || [];
                    const order = deliveryOrders.find(o => Number(o.id) === Number(preselectedId));
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



        // Auto-update Available & Reserved Stock quantity when Warehouse or Product is changed in Dispatch table
        $(document).on('change change.select2', 'select[name$="[warehouse_id]"], select[name$="[product_id]"]', function() {
            const $tr = $(this).closest('tr');
            const warehouseId = $tr.find('select[name$="[warehouse_id]"]').val();
            let productId = $tr.find('input[name$="[product_id]"]').val() || $tr.find('select[name$="[product_id]"]').val();
            let mrItemId = $tr.find('input[name$="[material_requirement_item_id]"]').val();
            const $availCell = $tr.find('.avail-qty-cell');
            const $reservedCell = $tr.find('.reserved-qty-cell');
            const $availBadgeSpan = $tr.find('.avail-qty-span');
            const $badgeBox = $tr.find('.stock-info-badge span');

            if (!productId || !warehouseId) return;

            $.ajax({
                url: "{{ route('inventory.products.stockCheck') }}",
                type: "GET",
                data: {
                    product_id: productId,
                    warehouse_id: warehouseId,
                    material_requirement_item_id: mrItemId || null
                },
                success: function(response) {
                    if (response && response.success) {
                        const avail = parseFloat(response.available_qty) || 0;
                        const itemReserved = parseFloat(response.item_reserved_qty) || 0;

                        $tr.attr('data-available-qty', avail);

                        if ($availCell.length) {
                            $availCell.text(avail);
                            $availCell.removeClass('text-success text-danger');
                            $availCell.addClass(avail > 0 ? 'text-success' : 'text-danger');
                        }

                        if ($reservedCell.length) {
                            $reservedCell.text(itemReserved);
                        }

                        if ($availBadgeSpan.length) {
                            $availBadgeSpan.text(avail);
                            if (avail <= 0) {
                                $badgeBox.removeClass('bg-soft-info text-info bg-soft-success text-success').addClass('bg-soft-danger text-danger border-danger');
                            } else {
                                $badgeBox.removeClass('bg-soft-info text-info bg-soft-danger text-danger').addClass('bg-soft-success text-success border-success');
                            }
                        }

                        checkAllDispatchRowsValidation();
                    }
                },
                error: function(err) {
                    console.error('Failed to update stock for warehouse:', err);
                }
            });
        });
    </script>
@endpush
