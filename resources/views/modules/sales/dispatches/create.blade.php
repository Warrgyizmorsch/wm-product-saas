@extends('layouts.duralux')

@section('title', 'Create Dispatch Order | SaaS ERP')
@section('page-title', 'Create Dispatch Order')
@section('breadcrumb', 'Sales / Dispatch Orders / Create')

@section('content')
    @if ($errors->any())
        <x-ui.alert variant="danger" :dismissible="true" class="mb-4">
            <strong>Unable to create dispatch order.</strong>
            <ul class="mb-0 ps-3 mt-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <form action="{{ route('sales.dispatches.store') }}" method="POST" id="dispatchForm">
        @csrf
        <input type="hidden" name="material_requirement_id" id="deliveryOrderId" value="{{ old('material_requirement_id') }}">

        <x-ui.odoo-form-ui type="sheet" class="erp-single-panel bg-white p-4">
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                <div>
                    <h5 class="fw-bold text-dark mb-0">New Dispatch Order</h5>
                    <span class="fs-12 text-muted">Select a delivery order, then confirm the quantities to dispatch.</span>
                </div>
                <x-ui.button href="{{ route('sales.dispatches.index') }}" variant="light" size="sm" class="border">Cancel</x-ui.button>
            </div>

            <div class="d-flex align-items-center justify-content-between gap-3 mb-4 p-3 bg-light rounded border">
                <div>
                    <span class="d-block fs-12 text-muted">Material Requirement</span>
                    <strong id="selectedDeliveryOrder" class="text-dark">No material requirement selected</strong>
                    <span id="selectedCustomer" class="d-block fs-12 text-muted"></span>
                </div>
                <x-ui.button type="button" variant="outline-primary" size="sm" icon="feather-truck" data-bs-toggle="modal" data-bs-target="#deliveryOrderPicker">
                    Select Material Requirement
                </x-ui.button>
            </div>

            <div class="row g-4 mb-4 fs-13 text-dark">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="Dispatch Date" name="dispatch_date" :value="old('dispatch_date', now()->toDateString())" :required="true" />
                    <x-ui.odoo-form-ui type="input" label="Carrier / Courier" name="carrier" :value="old('carrier')" placeholder="e.g. Blue Dart, DHL" />
                    <x-ui.odoo-form-ui type="input" label="Tracking Number" name="tracking_number" :value="old('tracking_number')" placeholder="AWB or tracking reference" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" label="Vehicle Number" name="vehicle_number" :value="old('vehicle_number')" placeholder="e.g. MH-12-AB-1234" />
                    <x-ui.odoo-form-ui type="input" label="Driver Name" name="driver_name" :value="old('driver_name')" placeholder="Driver's full name" />
                    <x-ui.odoo-form-ui type="input" label="Driver Phone" name="driver_phone" :value="old('driver_phone')" placeholder="e.g. +91 98765 43210" />
                </div>
            </div>

            <div class="border-top pt-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-dark mb-0 fs-14">Items to Dispatch</h6>
                    <span id="itemsHint" class="fs-12 text-muted">Select a material requirement to load its items.</span>
                </div>
                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table" id="dispatchItemsTable">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th style="width: 20%">Warehouse</th>
                                <th class="text-end" style="width: 10%">Ordered</th>
                                <th class="text-end" style="width: 11%">Reserved</th>
                                <th class="text-end" style="width: 12%">Dispatched</th>
                                <th class="text-end" style="width: 11%">Remaining</th>
                                <th class="text-end" style="width: 13%">Dispatch Qty</th>
                            </tr>
                        </thead>
                        <tbody id="dispatchItemsBody">
                            <tr id="emptyItemsRow">
                                <td colspan="5" class="text-center py-4 text-muted fs-12">No items selected.</td>
                            </tr>
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <div class="border-top pt-4 mt-4">
                <x-ui.odoo-form-ui type="textarea" label="Internal Notes" name="notes" rows="3" placeholder="Dispatch instructions or internal remarks...">{{ old('notes') }}</x-ui.odoo-form-ui>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <x-ui.button href="{{ route('sales.dispatches.index') }}" variant="light" class="border px-4">Discard</x-ui.button>
                <x-ui.button type="submit" id="saveDispatchBtn" variant="primary" class="px-5" disabled="disabled">Save Dispatch Order</x-ui.button>
            </div>
        </x-ui.odoo-form-ui>
    </form>

    <x-ui.modal id="deliveryOrderPicker" title="Select Material Requirement" size="lg" :centered="true" :scrollable="true">
        <div class="mb-3">
            <x-ui.odoo-form-ui type="input" id="deliveryOrderSearch" placeholder="Search by material requirement, sales order, or customer..." />
        </div>
        <div id="deliveryOrderList" class="d-grid gap-2">
            <div class="text-center py-4 text-muted">
                <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading material requirements...
            </div>
        </div>
        <x-slot:footer>
            <x-ui.button type="button" variant="light" class="border" data-bs-dismiss="modal">Cancel</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
@endsection

@push('scripts')
    <script>
        const warehouses = @json($warehouses->map(fn ($warehouse) => ['id' => $warehouse->id, 'name' => $warehouse->name]));
        const deliveryOrderId = document.getElementById('deliveryOrderId');
        const deliveryOrderList = document.getElementById('deliveryOrderList');
        const itemsBody = document.getElementById('dispatchItemsBody');
        const saveButton = document.getElementById('saveDispatchBtn');
        const itemsHint = document.getElementById('itemsHint');
        const pickerElement = document.getElementById('deliveryOrderPicker');
        const searchInput = document.getElementById('deliveryOrderSearch');
        let deliveryOrders = [];

        // ── Auto-select if material_requirement_id is passed in URL ──────────────────
        const urlParams = new URLSearchParams(window.location.search);
        const preselectedId = parseInt(urlParams.get('material_requirement_id')) || null;

        pickerElement.addEventListener('show.bs.modal', () => {
            if (deliveryOrders.length) {
                renderDeliveryOrders();
                return;
            }

            fetch('{{ route('sales.dispatches.pending-mr') }}', { headers: { Accept: 'application/json' } })
                .then(response => response.ok ? response.json() : Promise.reject())
                .then(data => {
                    deliveryOrders = data;
                    renderDeliveryOrders();
                })
                .catch(() => {
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

        pickerElement.addEventListener('hidden.bs.modal', () => {
            document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
            document.body.style.removeProperty('overflow');
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
                const options = warehouses.map(warehouse =>
                    `<option value="${warehouse.id}" ${Number(item.warehouse_id) === Number(warehouse.id) ? 'selected' : ''}>${escapeHtml(warehouse.name)}</option>`
                ).join('');

                if (item.fully_dispatched) {
                    // Show as a locked, informational row
                    rowsHtml += `
                        <tr class="text-muted">
                            <td>
                                <strong class="text-muted">${escapeHtml(item.product_name || 'Unknown product')}</strong>
                                ${item.product_sku ? `<small class="d-block text-muted font-monospace fs-11">SKU: ${escapeHtml(item.product_sku)}</small>` : ''}
                            </td>
                            <td><span class="text-muted fs-12">—</span></td>
                            <td class="text-end">${item.quantity_ordered}</td>
                            <td class="text-end">${item.quantity_reserved}</td>
                            <td class="text-end fw-bold text-success">${item.already_dispatched}</td>
                            <td class="text-end fw-bold text-muted">0</td>
                            <td class="text-end">
                                <span class="badge bg-soft-success text-success fs-11 px-2">Fully Dispatched</span>
                            </td>
                        </tr>`;
                } else {
                    dispatchableCount++;
                    rowsHtml += `
                        <tr>
                            <td>
                                <input type="hidden" name="items[${index}][material_requirement_item_id]" value="${item.id}">
                                <input type="hidden" name="items[${index}][product_id]" value="${item.product_id}">
                                <strong>${escapeHtml(item.product_name || 'Unknown product')}</strong>
                                ${item.product_sku ? `<small class="d-block text-muted font-monospace fs-11">SKU: ${escapeHtml(item.product_sku)}</small>` : ''}
                            </td>
                            <td><select name="items[${index}][warehouse_id]" class="odoo-table-select"><option value="">Select warehouse...</option>${options}</select></td>
                            <td class="text-end fw-semibold">${item.quantity_ordered}</td>
                            <td class="text-end fw-semibold text-info">${item.quantity_reserved}</td>
                            <td class="text-end fw-bold ${item.already_dispatched > 0 ? 'text-warning' : 'text-muted'}">${item.already_dispatched}</td>
                            <td class="text-end fw-bold text-primary">${item.remaining_qty}</td>
                            <td class="text-end">
                                <input type="hidden" name="items[${index}][quantity_ordered]" value="${item.quantity_ordered}">
                                <input
                                    type="number"
                                    name="items[${index}][quantity_dispatched]"
                                    class="odoo-table-input text-end"
                                    value="${item.dispatch_qty}"
                                    min="1"
                                    max="${item.remaining_qty}"
                                    title="Max dispatchable: ${item.remaining_qty}"
                                    required
                                >
                                <small class="text-muted d-block text-end" style="font-size:10px;">max: ${item.remaining_qty}</small>
                            </td>
                        </tr>`;
                }
            });

            itemsBody.innerHTML = rowsHtml;
            itemsHint.textContent = `${dispatchableCount} item(s) available to dispatch.`;
            saveButton.disabled = dispatchableCount === 0;
        }

        function escapeHtml(value) {
            const element = document.createElement('div');
            element.textContent = value;
            return element.innerHTML;
        }

        document.getElementById('dispatchForm').addEventListener('submit', event => {
            if (!deliveryOrderId.value || !itemsBody.querySelector('input[name$="[quantity_dispatched]"]')) {
                event.preventDefault();
                alert('Select a material requirement with at least one dispatch item before saving.');
            }
        });

        // ── Auto-load on page ready if preselectedId present ──────────────────
        if (preselectedId) {
            fetch('{{ route('sales.dispatches.pending-mr') }}', { headers: { Accept: 'application/json' } })
                .then(response => response.ok ? response.json() : Promise.reject())
                .then(data => {
                    deliveryOrders = data;
                    const order = deliveryOrders.find(o => o.id === preselectedId);
                    if (order) {
                        selectDeliveryOrder(order);
                    }
                })
                .catch(() => {/* silently fail on autoload */});
        }
    </script>
@endpush
