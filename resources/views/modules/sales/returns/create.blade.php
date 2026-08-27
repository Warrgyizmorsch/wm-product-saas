@extends('layouts.duralux')

@section('title', 'Process Sales Return | SaaS ERP')
@section('page-title', 'Create Sales Return')
@section('breadcrumb', 'Sales / Returns / Create')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .select2-container {
            width: 100% !important;
            max-width: 100% !important;
        }
        #returnItemsTable td {
            vertical-align: top !important;
        }
    </style>
@endpush

@section('content')
    <div class="erp-single-panel bg-white p-4">

        <form action="{{ route('sales.returns.store') }}" method="POST" id="returnForm">
            @csrf

            <x-ui.odoo-form-ui type="sheet">
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 flex-wrap gap-2">
                    <div>
                        <h4 class="fw-bold text-dark mb-0">Record Customer Return</h4>
                        <span class="fs-12 text-muted">Create a sales return against an Order or directly for Over-The-Counter / Walk-in returns.</span>
                    </div>
                    <div class="d-flex gap-2">
                        <x-ui.button href="{{ route('sales.returns.index') }}" variant="light" size="sm" class="border">Cancel</x-ui.button>
                        <x-ui.button type="submit" id="saveReturnBtn" variant="primary" size="sm" icon="feather-save" style="background-color: #714B67; border-color: #714B67;">Save Return Draft</x-ui.button>
                    </div>
                </div>

                <!-- Mode Switcher Bar -->
                <div class="mb-4 bg-light p-3 rounded border">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted d-block mb-2">Return Creation Mode:</label>
                    <div class="d-flex gap-4 flex-wrap align-items-center">
                        <div class="form-check">
                            <input class="form-check-input mode-radio" type="radio" name="return_mode" id="mode_so" value="so" checked autocomplete="off">
                            <label class="form-check-label fw-bold text-dark fs-13" for="mode_so">
                                <i class="feather-file-text me-1 text-primary"></i>Against Sales Order
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input mode-radio" type="radio" name="return_mode" id="mode_direct" value="direct" autocomplete="off">
                            <label class="form-check-label fw-bold text-dark fs-13" for="mode_direct">
                                <i class="feather-plus-circle me-1 text-success"></i>Direct Return (Over-The-Counter / Walk-in)
                            </label>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4 fs-13 text-dark">
                    <!-- Column 1: Source Document & Customer -->
                    <div class="col-md-6 border-end pe-md-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-user me-2"></i>Customer & Sales Reference</h6>
                        
                        <!-- SO Selector (SO Mode) -->
                        <div id="soSelectBlock">
                            <x-ui.odoo-form-ui type="select" label="Sales Order Reference" name="sales_order_id" id="salesOrderSelect" class="odoo-select2">
                                <option value="">Select Sales Order...</option>
                                @foreach ($salesOrders as $so)
                                    <option value="{{ $so->id }}" @selected(old('sales_order_id', $prefillSalesOrderId) == $so->id)>
                                        {{ $so->sales_order_number }} (Customer: {{ $so->customer?->name }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>

                            <div id="invoiceSelectWrapper" class="mt-3">
                                <x-ui.odoo-form-ui type="select" label="Sales Invoice Reference (Exact Tax & Line Mapping)" name="invoice_id" id="invoiceSelect" class="odoo-select2">
                                    <option value="">-- Select Sales Invoice --</option>
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>

                        <!-- Customer Selector (Direct Mode) -->
                        <div id="customerSelectBlock" style="display: none;">
                            <x-ui.odoo-form-ui type="select" label="Customer Name" name="customer_id" id="customerSelect" class="odoo-select2">
                                <option value="">Select Customer...</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}" @selected(old('customer_id', $prefillCustomerId) == $c->id)>
                                        {{ $c->name }} {{ $c->code ? "({$c->code})" : '' }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <x-ui.odoo-form-ui type="input" label="Reason for Return" name="reason" :value="old('reason')" placeholder="e.g. Defective items, wrong size delivered..." />
                    </div>

                    <!-- Column 2: Date & Return Code -->
                    <div class="col-md-6 ps-md-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-calendar me-2"></i>Return Details</h6>
                        <x-ui.odoo-form-ui type="input" label="Return Number" name="return_number" :value="old('return_number', $nextReturnNumber)" :readonly="true" :required="true" style="font-weight: bold;" />

                        <x-ui.odoo-form-ui type="input" inputType="date" label="Return Date" name="return_date" :value="old('return_date', date('Y-m-d'))" :required="true" />
                    </div>
                </div>

                <!-- Return Lines Table -->
                <div class="border-top pt-4 mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="fw-bold text-dark mb-0 fs-14"><i class="feather-rotate-ccw me-2 text-danger"></i>Items to Return</h6>
                            <span id="itemsHint" class="fs-12 text-muted">Select a Sales Order / Invoice to populate return items.</span>
                        </div>
                    </div>

                    <div class="table-responsive border rounded bg-white">
                        <x-ui.odoo-form-ui type="table" id="returnItemsTable">
                            <thead class="table-light fs-12">
                                <tr>
                                    <th style="width: 40%;" class="ps-3">Product Details & Serials</th>
                                    <th style="width: 25%;">Restock Warehouse</th>
                                    <th class="text-end" style="width: 13%;">Return Qty</th>
                                    <th class="text-end pe-3" style="width: 17%;">Refund Unit Price</th>
                                    <th class="text-center pe-3" style="width: 5%;"></th>
                                </tr>
                            </thead>
                            <tbody id="returnItemsBody" class="fs-13 text-dark">
                                <tr id="emptyItemsRow">
                                    <td colspan="5" class="text-center text-muted py-4 fs-12">
                                        <i class="feather-info me-1"></i>Please select a Sales Order / Invoice to populate items.
                                    </td>
                                </tr>
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>

                    <!-- Add a line button outside table (Direct Mode) -->
                    <div class="mt-3" id="directAddContainer" style="display: none;">
                        <button type="button" class="btn btn-xs btn-outline-primary fw-bold" id="addDirectItemBtn" style="font-size: 10px; padding: 2px 8px; text-transform: none !important;">
                            <i class="feather-plus me-1"></i>Add a line
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <x-ui.button href="{{ route('sales.returns.index') }}" variant="light" class="border px-4">Discard</x-ui.button>
                    <x-ui.button type="submit" id="saveReturnBtnFooter" variant="primary" icon="feather-save" class="px-4" style="background-color: #714B67; border-color: #714B67;">Save Return Draft</x-ui.button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    @php
        $salesOrdersData = $salesOrders->map(function($so) {
            return [
                'id' => $so->id,
                'sales_order_number' => $so->sales_order_number,
                'customer_name' => $so->customer?->name,
                'items' => $so->items->map(function($item) {
                    return [
                        'product_id' => $item->product_id,
                        'item_name' => $item->item_name ?? $item->product?->name ?? 'Product Item',
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'warehouse_id' => $item->warehouse_id,
                        'warehouse_name' => $item->warehouse?->name ?? 'Main Warehouse',
                        'track_serial_number' => (bool)($item->product?->track_serial_number),
                    ];
                })->values()->toArray()
            ];
        })->values()->toArray();

        $invoicesData = $invoices->map(function($inv) {
            return [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'sales_order_id' => $inv->sales_order_id,
                'invoice_date' => $inv->invoice_date ? $inv->invoice_date->format('d/m/Y') : '',
                'total_amount' => (float)$inv->total_amount,
                'gst_type' => $inv->gst_type ?? ((float)$inv->igst_amount > 0 ? 'IGST' : 'CGST + SGST'),
                'items' => $inv->items->map(function($item) {
                    return [
                        'product_id' => $item->product_id,
                        'item_name' => $item->item_name ?? $item->product?->name ?? 'Product Item',
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'warehouse_id' => $item->warehouse_id,
                        'warehouse_name' => $item->warehouse?->name ?? 'Main Warehouse',
                        'track_serial_number' => (bool)($item->product?->track_serial_number),
                    ];
                })->values()->toArray()
            ];
        })->values()->toArray();
    @endphp
    <script>
        const warehouses = @json($formattedWarehouses);
        const productsList = @json($formattedProducts);
        const salesOrdersList = @json($salesOrdersData);
        const invoicesList = @json($invoicesData);
        const itemsBody = document.getElementById('returnItemsBody');
        const itemsHint = document.getElementById('itemsHint');
        const directAddContainer = document.getElementById('directAddContainer');
        const soSelectBlock = document.getElementById('soSelectBlock');
        const customerSelectBlock = document.getElementById('customerSelectBlock');

        let directItemIndex = 0;

        function initSelect2(context) {
            $(context).find('.odoo-select2').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });
        }

        $(document).ready(function() {
            initSelect2(document);

            function renderItemsList(items) {
                itemsBody.innerHTML = '';
                if (!items || items.length === 0) {
                    itemsBody.innerHTML = '<tr id="emptyItemsRow"><td colspan="5" class="text-center text-muted py-4 fs-12"><i class="feather-info me-1"></i>No products found.</td></tr>';
                    return;
                }

                items.forEach((item, index) => {
                    if (!item.product_id) return;
                    
                    const isSerial = item.track_serial_number;
                    const serialBlock = isSerial ? `
                        <div id="serial_block_${index}" class="mt-2 p-3 bg-white border border-primary-subtle rounded shadow-sm">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="fw-bold fs-11 text-dark d-flex align-items-center">
                                    <i class="feather-hash text-primary me-1 fs-12"></i>Tracked Serial Numbers (Returning):
                                </span>
                                <button type="button" class="btn btn-xs btn-outline-primary fw-semibold fetch-sold-serials-btn" data-product-id="${item.product_id}" data-warehouse-id="${item.warehouse_id}" data-idx="${index}">
                                    <i class="feather-zap me-1"></i>Auto-Fill Sold
                                </button>
                            </div>
                            <textarea name="items[${index}][serial_numbers]" id="serial_input_${index}" class="form-control form-control-sm font-monospace fs-11 text-dark" rows="2" placeholder="Scan barcode or enter returning serial numbers (1 per line or comma)..."></textarea>
                            <span class="fs-10 text-muted mt-1 d-block"><i class="feather-info me-1"></i>Restores serial numbers back to Available status in inventory upon approval.</span>
                        </div>
                    ` : '';

                    const row = `
                        <tr class="item-row align-top">
                            <td class="ps-3 pe-2 py-2">
                                <strong class="text-dark fs-13 d-block">${escapeHtml(item.item_name)}</strong>
                                <input type="hidden" name="items[${index}][product_id]" value="${item.product_id}">
                                ${serialBlock}
                            </td>
                            <td class="px-2 py-2">
                                <span class="text-dark fw-semibold fs-12">${escapeHtml(item.warehouse_name)}</span>
                                <input type="hidden" name="items[${index}][warehouse_id]" value="${item.warehouse_id}">
                            </td>
                            <td class="text-end px-2 py-2">
                                <input type="number" 
                                       name="items[${index}][quantity]" 
                                       class="form-control odoo-table-input text-end fw-bold text-primary return-qty-input" 
                                       value="${item.quantity}" 
                                       min="0.0001" 
                                       max="${item.quantity}" 
                                       required 
                                       style="width: 100px; margin-left: auto;">
                            </td>
                            <td class="text-end pe-3 py-2">
                                <input type="number" 
                                       name="items[${index}][unit_price]" 
                                       class="form-control odoo-table-input text-end text-muted fw-bold" 
                                       value="${item.unit_price}" 
                                       min="0" 
                                       step="0.01" 
                                       required 
                                       style="width: 120px; margin-left: auto;">
                            </td>
                            <td class="text-center pe-3 py-2 align-middle">
                                <button type="button" class="btn btn-sm btn-link text-danger remove-row-btn p-1 border-0" title="Remove item from return">
                                    <i class="feather-trash-2 fs-15"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    const $tr = $(row);
                    $(itemsBody).append($tr);

                    $tr.find('.remove-row-btn').on('click', function() {
                        $tr.remove();
                        if (itemsBody.children.length === 0) {
                            itemsBody.innerHTML = '<tr id="emptyItemsRow"><td colspan="5" class="text-center text-muted py-4 fs-12"><i class="feather-info me-1"></i>No products found.</td></tr>';
                        }
                    });

                    attachSoldSerialsHandler($tr);
                });
            }

            function updateModeValidation(mode) {
                if (mode === 'direct') {
                    soSelectBlock.style.display = 'none';
                    customerSelectBlock.style.display = 'block';
                    directAddContainer.style.display = 'block';
                    itemsHint.textContent = 'Add product line items manually to issue a Direct Sales Return.';

                    $('#salesOrderSelect').prop('required', false).val('').trigger('change.select2');
                    $('#customerSelect').prop('required', true);

                    if ($('#returnItemsBody tr').length === 0 || $('#emptyItemsRow').length > 0) {
                        itemsBody.innerHTML = '';
                        addDirectItemRow();
                    }
                } else {
                    soSelectBlock.style.display = 'block';
                    customerSelectBlock.style.display = 'none';
                    directAddContainer.style.display = 'none';
                    itemsHint.textContent = 'Select a Sales Order / Invoice to populate return items.';

                    $('#salesOrderSelect').prop('required', true);
                    $('#customerSelect').prop('required', false).val('').trigger('change.select2');

                    itemsBody.innerHTML = '<tr id="emptyItemsRow"><td colspan="5" class="text-center text-muted py-4 fs-12"><i class="feather-info me-1"></i>Please select a Sales Order to populate items.</td></tr>';
                    if ($('#salesOrderSelect').val()) {
                        $('#salesOrderSelect').trigger('change');
                    }
                }
            }

            // Mode Switching Handler
            document.querySelectorAll('input[name="return_mode"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    updateModeValidation(this.value);
                });
            });

            // Initialize Mode Validation state
            const initialMode = document.querySelector('input[name="return_mode"]:checked').value;
            updateModeValidation(initialMode);

            // Sales Order Selection Handler
            $('#salesOrderSelect').on('change', function() {
                if (document.querySelector('input[name="return_mode"]:checked').value === 'direct') return;

                const soId = $(this).val();
                const $invoiceSel = $('#invoiceSelect');
                $invoiceSel.empty().append('<option value="">-- All Invoices / Select Specific Invoice --</option>');

                if (!soId) {
                    $invoiceSel.trigger('change.select2');
                    itemsBody.innerHTML = '<tr id="emptyItemsRow"><td colspan="5" class="text-center text-muted py-4 fs-12"><i class="feather-info me-1"></i>Please select a Sales Order to populate items.</td></tr>';
                    return;
                }

                // Populate Invoices belonging to this Sales Order
                const matchingInvoices = invoicesList.filter(inv => inv.sales_order_id == soId);
                matchingInvoices.forEach(inv => {
                    $invoiceSel.append(`<option value="${inv.id}">${inv.invoice_number} (Date: ${inv.invoice_date} - Total: ₹${inv.total_amount.toFixed(2)} - Tax: ${inv.gst_type})</option>`);
                });

                if (matchingInvoices.length > 0) {
                    $invoiceSel.val(matchingInvoices[0].id).trigger('change.select2');
                    renderItemsList(matchingInvoices[0].items);
                } else {
                    $invoiceSel.trigger('change.select2');
                    const selectedSo = salesOrdersList.find(so => so.id == soId);
                    if (selectedSo) {
                        renderItemsList(selectedSo.items);
                    }
                }
            });

            // Invoice Selection Handler
            $('#invoiceSelect').on('change', function() {
                const invId = $(this).val();
                if (invId) {
                    const selectedInv = invoicesList.find(inv => inv.id == invId);
                    if (selectedInv) {
                        renderItemsList(selectedInv.items);
                    }
                } else {
                    const soId = $('#salesOrderSelect').val();
                    if (soId) {
                        const selectedSo = salesOrdersList.find(so => so.id == soId);
                        if (selectedSo) {
                            renderItemsList(selectedSo.items);
                        }
                    }
                }
            });

            // Add Direct Item Row Handler
            $('#addDirectItemBtn').on('click', addDirectItemRow);

            function addDirectItemRow() {
                const idx = directItemIndex++;
                const productOptions = productsList.map(p => 
                    `<option value="${p.id}" data-price="${p.selling_price}" data-serial="${p.track_serial_number ? '1' : '0'}">${escapeHtml(p.name)} ${p.sku ? `(${escapeHtml(p.sku)})` : ''}</option>`
                ).join('');

                const warehouseOptions = warehouses.map(w => 
                    `<option value="${w.id}">${escapeHtml(w.name)}</option>`
                ).join('');

                const newRow = `
                    <tr class="item-row align-top" id="direct_row_${idx}">
                        <td class="ps-3 pe-2 py-2">
                            <select name="items[${idx}][product_id]" class="form-select odoo-table-select product-select odoo-select2 fw-semibold" data-idx="${idx}" required>
                                <option value="">-- Select Product --</option>
                                ${productOptions}
                            </select>
                            <div id="serial_block_${idx}" class="mt-2 p-3 bg-white border border-primary-subtle rounded shadow-sm" style="display: none;">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="fw-bold fs-11 text-dark d-flex align-items-center">
                                        <i class="feather-hash text-primary me-1 fs-12"></i>Tracked Serial Numbers (Returning):
                                    </span>
                                    <button type="button" class="btn btn-xs btn-outline-primary fw-semibold fetch-sold-serials-btn" data-idx="${idx}">
                                        <i class="feather-zap me-1"></i>Auto-Fill Sold
                                    </button>
                                </div>
                                <textarea name="items[${idx}][serial_numbers]" id="serial_input_${idx}" class="form-control form-control-sm font-monospace fs-11 text-dark" rows="2" placeholder="Scan barcode or enter returning serial numbers (1 per line or comma)..."></textarea>
                                <span class="fs-10 text-muted mt-1 d-block"><i class="feather-info me-1"></i>Restores serial numbers back to Available status in inventory upon approval.</span>
                            </div>
                        </td>
                        <td class="px-2 py-2">
                            <select name="items[${idx}][warehouse_id]" class="form-select odoo-table-select warehouse-select odoo-select2" data-idx="${idx}" required>
                                ${warehouseOptions}
                            </select>
                        </td>
                        <td class="text-end px-2 py-2">
                            <input type="number" name="items[${idx}][quantity]" class="form-control odoo-table-input text-end dispatch-qty-input fw-bold return-qty-input" value="1" min="0.0001" step="any" required style="width: 100px; margin-left: auto;">
                        </td>
                        <td class="text-end pe-3 py-2">
                            <input type="number" name="items[${idx}][unit_price]" class="form-control odoo-table-input text-end unit-price-input fw-bold" value="0.00" min="0" step="0.01" required style="width: 120px; margin-left: auto;">
                        </td>
                        <td class="text-center px-2 py-2">
                            <button type="button" class="btn btn-sm btn-link text-danger remove-row-btn p-1 border-0" data-idx="${idx}" title="Remove line item">
                                <i class="feather-trash-2 fs-14"></i>
                            </button>
                        </td>
                    </tr>
                `;

                const $tr = $(newRow);
                $(itemsBody).append($tr);
                initSelect2($tr);

                // Auto update price and serial block on product change
                $tr.find('.product-select').on('change', function() {
                    const selectedOpt = $(this).find('option:selected');
                    const price = selectedOpt.data('price') || 0;
                    const isSerial = selectedOpt.data('serial') == 1;

                    $tr.find('.unit-price-input').val(price);
                    const block = document.getElementById(`serial_block_${idx}`);
                    if (block) {
                        block.style.display = isSerial ? 'block' : 'none';
                    }
                });

                // Remove Row Handler
                $tr.find('.remove-row-btn').on('click', function() {
                    $tr.remove();
                    if (itemsBody.children.length === 0) {
                        itemsBody.innerHTML = '<tr id="emptyItemsRow"><td colspan="5" class="text-center text-muted py-4 fs-12"><i class="feather-info me-1"></i>No items selected.</td></tr>';
                    }
                });

                attachSoldSerialsHandler($tr);
            }

            function attachSoldSerialsHandler($tr) {
                $tr.find('.fetch-sold-serials-btn').on('click', function() {
                    const prodId = $(this).data('product-id') || $tr.find('.product-select').val();
                    const whId = $(this).data('warehouse-id') || $tr.find('.warehouse-select').val();
                    const idx = $(this).data('idx');

                    if (!prodId) {
                        alert('Please select a product first.');
                        return;
                    }

                    fetch(`{{ route('sales.dispatches.available-serials') }}?product_id=${prodId}&warehouse_id=${whId}&status=Sold`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.serials && data.serials.length) {
                                const qty = parseFloat($tr.find('.return-qty-input').val()) || 1;
                                const serialsToFill = data.serials.slice(0, qty).join("\n");
                                document.getElementById(`serial_input_${idx}`).value = serialsToFill;
                            } else {
                                alert('No sold serial numbers found for this product/warehouse.');
                            }
                        })
                        .catch(() => {
                            alert('Unable to fetch sold serial numbers.');
                        });
                });
            }

            function escapeHtml(value) {
                const element = document.createElement('div');
                element.textContent = value;
                return element.innerHTML;
            }

            // Form Submit Listener for Validation Check
            document.getElementById('returnForm').addEventListener('submit', function(e) {
                const mode = document.querySelector('input[name="return_mode"]:checked').value;
                if (mode === 'so' && !$('#salesOrderSelect').val()) {
                    e.preventDefault();
                    alert('Please select a Sales Order before saving.');
                    return;
                }
                if (mode === 'direct' && !$('#customerSelect').val()) {
                    e.preventDefault();
                    alert('Please select a Customer before saving.');
                    return;
                }
                if (!itemsBody.querySelector('input[name$="[product_id]"]') && !itemsBody.querySelector('select[name$="[product_id]"]')) {
                    e.preventDefault();
                    alert('Please add at least one line item to return.');
                    return;
                }
            });
        });
    </script>
@endpush
