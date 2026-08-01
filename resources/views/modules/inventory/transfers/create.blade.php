@extends('layouts.duralux')

@section('title', 'New Stock Transfer | SaaS ERP')
@section('page-title', 'New Stock Transfer')
@section('breadcrumb', 'Inventory / Stock Transfers / Create')

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
    </style>
@endpush

@section('content')
<div class="erp-single-panel bg-white p-4">
    @if (session('error'))
        <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
    @endif
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-danger text-white me-3">
                    <i class="feather-alert-triangle"></i>
                </div>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Validation Errors:</h6>
                    <ul class="fs-12 mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form action="{{ route('inventory.transfers.store') }}" method="POST" id="transferForm">
        @csrf
        <x-ui.odoo-form-ui type="sheet">
            <!-- Header Bar -->
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 flex-wrap gap-2">
                <div>
                    <h4 class="fw-bold text-dark mb-0">New Stock Transfer</h4>
                    <span class="fs-12 text-muted">Create an inter-warehouse inventory movement order with real-time stock validation.</span>
                </div>
                <div class="d-flex gap-2">
                    <x-ui.button href="{{ route('inventory.transfers.index') }}" variant="light" class="border">Discard</x-ui.button>
                    <x-ui.button type="submit" variant="primary" icon="feather-check" id="submitBtn">Create Transfer</x-ui.button>
                </div>
            </div>

            <!-- Form Grid -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="select" label="From Warehouse" name="from_warehouse_id" id="fromWarehouseSelect" class="select2-select" :required="true" :error-text="$errors->first('from_warehouse_id')">
                        <option value="">Select Source Warehouse</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ old('from_warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="select" label="To Warehouse" name="to_warehouse_id" id="toWarehouseSelect" class="select2-select" :required="true" :error-text="$errors->first('to_warehouse_id')">
                        <option value="">Select Target Warehouse</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ old('to_warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="Transfer Date" name="transfer_date" value="{{ old('transfer_date', date('Y-m-d')) }}" :required="true" :error-text="$errors->first('transfer_date')" />
                </div>
                <div class="col-md-12">
                    <x-ui.odoo-form-ui type="textarea" label="Notes / Reason" name="notes" rows="2" placeholder="Optional transfer comments..." :error-text="$errors->first('notes')">{{ old('notes') }}</x-ui.odoo-form-ui>
                </div>
            </div>

            <!-- Line Items Table Section -->
            <div class="border-top pt-4">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                    <h5 class="fw-bold text-dark mb-0 fs-14"><i class="feather-layers text-primary me-2"></i>Items to Transfer</h5>
                    <div class="d-flex align-items-center gap-2" style="width: 420px;">
                        <div class="input-group input-group-sm shadow-2xs rounded border overflow-hidden">
                            <span class="input-group-text bg-primary text-white border-0 px-3 fw-semibold"><i class="feather-camera me-1"></i> Barcode</span>
                            <input type="text" id="fastBarcodeScanInput" class="form-control border-0 bg-white" placeholder="Scan Barcode / SKU (Press Enter)..." autocomplete="off" style="font-size: 13px;">
                            <button type="button" class="btn btn-primary border-0 px-3" id="fastBarcodeScanBtn"><i class="feather-search"></i></button>
                        </div>
                    </div>
                </div>

                <div class="table-responsive border rounded bg-white">
                    <x-ui.odoo-form-ui type="table" id="items-table">
                        <thead class="table-light fs-12">
                            <tr>
                                <th style="width: 45%;" class="ps-3">Product <span class="text-danger">*</span></th>
                                <th style="width: 15%;" class="text-center">Quantity <span class="text-danger">*</span></th>
                                <th style="width: 35%;">Serial Numbers (Comma separated)</th>
                                <th style="width: 5%;" class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="transfer-row">
                                <td class="ps-3 align-top py-3">
                                    <select name="items[0][product_id]" class="form-select odoo-table-select product-select" required>
                                        <option value="">Select Product</option>
                                        @foreach($products as $prod)
                                            <option value="{{ $prod->id }}">{{ $prod->name }} (SKU: {{ $prod->sku }})</option>
                                        @endforeach
                                    </select>

                                    <!-- Enterprise Live Stock Status Badge -->
                                    <div class="stock-badge-container d-flex align-items-center flex-wrap gap-1 mt-1" style="display: none !important;">
                                        <span class="badge bg-soft-success text-success border border-success-subtle px-2 py-0.5 rounded-pill fw-medium">
                                            <i class="feather-check-circle me-1"></i>Net Avail: <span class="avail-val">0</span>
                                        </span>
                                        <span class="badge bg-soft-warning text-warning border border-warning-subtle px-2 py-0.5 rounded-pill fw-medium">
                                            Reserved: <span class="res-val">0</span>
                                        </span>
                                        <span class="badge bg-soft-secondary text-muted border border-secondary-subtle px-2 py-0.5 rounded-pill fw-medium">
                                            Physical: <span class="phys-val">0</span>
                                        </span>
                                    </div>
                                </td>
                                <td class="text-center align-top py-3">
                                    <input type="number" name="items[0][quantity]" class="odoo-table-input text-center qty-input mx-auto" step="0.01" min="0.01" placeholder="Qty" value="1" required style="width: 100px;" data-max-avail="999999">
                                    <div class="stock-error-text">
                                        <i class="feather-alert-triangle me-1"></i>Exceeds Available Stock (<span class="max-avail-display">0</span>)!
                                    </div>
                                </td>
                                <td class="align-top py-3">
                                    <input type="text" name="items[0][serial_numbers]" class="odoo-table-input" placeholder="SN1, SN2...">
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
                        <i class="feather-plus me-1"></i>Add a product item
                    </button>
                </div>
            </div>

            <!-- Footer Action Bar -->
            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <x-ui.button href="{{ route('inventory.transfers.index') }}" variant="light" class="border px-4">Discard</x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="feather-check" class="px-4">Save Stock Transfer</x-ui.button>
            </div>
        </x-ui.odoo-form-ui>
    </form>
</div>

@push('scripts')
<script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
<script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
<script>
    $(document).ready(function() {
        if (typeof $.fn.select2 === 'function') {
            $('.select2-select').select2({ theme: "bootstrap-5", width: "100%" });
            $('.product-select').select2({ theme: "bootstrap-5", width: "100%" });
        }
    });

    // Stock Lookup and Validation Logic
    function checkRowStock($row) {
        const fromWhId = $('#fromWarehouseSelect').val();
        const prodId = $row.find('.product-select').val();
        const $badgeContainer = $row.find('.stock-badge-container');
        const $qtyInput = $row.find('.qty-input');
        const $errText = $row.find('.stock-error-text');

        if (!fromWhId || !prodId) {
            $badgeContainer.attr('style', 'display: none !important;');
            $qtyInput.removeAttr('data-max-avail');
            validateQty($row);
            return;
        }

        $.ajax({
            url: "{{ route('inventory.products.stockCheck') }}",
            data: { warehouse_id: fromWhId, product_id: prodId },
            success: function(res) {
                if (res.success) {
                    $row.find('.avail-val').text(res.available_qty);
                    $row.find('.res-val').text(res.reserved_qty);
                    $row.find('.phys-val').text(res.physical_qty);
                    $row.find('.max-avail-display').text(res.available_qty);

                    $badgeContainer.attr('style', 'display: flex !important;');
                    $qtyInput.attr('data-max-avail', res.available_qty);
                    validateQty($row);
                }
            }
        });
    }

    function validateQty($row) {
        const $qtyInput = $row.find('.qty-input');
        const $errText = $row.find('.stock-error-text');
        const maxAvailAttr = $qtyInput.attr('data-max-avail');

        if (maxAvailAttr !== undefined && maxAvailAttr !== null) {
            const maxAvail = parseFloat(maxAvailAttr);
            const inputQty = parseFloat($qtyInput.val()) || 0;

            if (inputQty > maxAvail) {
                $qtyInput.addClass('is-invalid border-danger');
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

    // Event Listeners
    $(document).on('change', '#fromWarehouseSelect', function() {
        $('#items-table tbody tr.transfer-row').each(function() {
            checkRowStock($(this));
        });
    });

    $(document).on('change', '.product-select', function() {
        const $row = $(this).closest('tr');
        checkRowStock($row);
    });

    $(document).on('input keyup change', '.qty-input', function() {
        const $row = $(this).closest('tr');
        validateQty($row);
    });

    $('#transferForm').on('submit', function(e) {
        let hasError = false;
        let errorMessage = '';

        $('#items-table tbody tr.transfer-row').each(function() {
            const $row = $(this);
            const $qtyInput = $row.find('.qty-input');
            const maxAvailAttr = $qtyInput.attr('data-max-avail');
            const prodName = $row.find('.product-select option:selected').text();

            if (maxAvailAttr !== undefined && maxAvailAttr !== null) {
                const maxAvail = parseFloat(maxAvailAttr);
                const inputQty = parseFloat($qtyInput.val()) || 0;

                if (inputQty > maxAvail) {
                    hasError = true;
                    errorMessage = `Cannot transfer ${inputQty} for '${prodName}'. Only ${maxAvail} Net Available in selected warehouse.`;
                    return false;
                }
            }
        });

        if (hasError) {
            e.preventDefault();
            alert('⚠️ Cannot Submit Transfer:\n' + errorMessage);
            return false;
        }
    });

    let rowIndex = 1;
    document.getElementById('add-item').addEventListener('click', function() {
        let table = document.getElementById('items-table').getElementsByTagName('tbody')[0];
        let newRow = table.insertRow();
        newRow.className = 'transfer-row';
        newRow.innerHTML = `
            <td class="ps-3 align-top py-3">
                <select name="items[${rowIndex}][product_id]" class="form-select odoo-table-select product-select" required>
                    <option value="">Select Product</option>
                    @foreach($products as $prod)
                        <option value="{{ $prod->id }}">{{ $prod->name }} (SKU: {{ $prod->sku }})</option>
                    @endforeach
                </select>
                <div class="stock-badge-container d-flex align-items-center flex-wrap gap-1 mt-1" style="display: none !important;">
                    <span class="badge bg-soft-success text-success border border-success-subtle px-2 py-0.5 rounded-pill fw-medium">
                        <i class="feather-check-circle me-1"></i>Net Avail: <span class="avail-val">0</span>
                    </span>
                    <span class="badge bg-soft-warning text-warning border border-warning-subtle px-2 py-0.5 rounded-pill fw-medium">
                        Reserved: <span class="res-val">0</span>
                    </span>
                    <span class="badge bg-soft-secondary text-muted border border-secondary-subtle px-2 py-0.5 rounded-pill fw-medium">
                        Physical: <span class="phys-val">0</span>
                    </span>
                </div>
            </td>
            <td class="text-center align-top py-3">
                <input type="number" name="items[${rowIndex}][quantity]" class="odoo-table-input text-center qty-input mx-auto" step="0.01" min="0.01" placeholder="Qty" value="1" required style="width: 100px;">
                <div class="stock-error-text">
                    <i class="feather-alert-triangle me-1"></i>Exceeds Available Stock (<span class="max-avail-display">0</span>)!
                </div>
            </td>
            <td class="align-top py-3">
                <input type="text" name="items[${rowIndex}][serial_numbers]" class="odoo-table-input" placeholder="SN1, SN2...">
            </td>
            <td class="text-center align-top py-3">
                <button type="button" class="btn btn-icon btn-sm btn-soft-danger remove-row mt-1"><i class="feather-trash-2"></i></button>
            </td>
        `;
        if (typeof $.fn.select2 === 'function') {
            $(newRow).find('.product-select').select2({ theme: "bootstrap-5", width: "100%" });
        }
        rowIndex++;
    });

    document.addEventListener('click', function(e) {
        if (e.target && e.target.closest('.remove-row')) {
            let row = e.target.closest('tr');
            if (document.querySelectorAll('#items-table tbody tr').length > 1) {
                row.remove();
            }
        }
    });

    // Fast Barcode Lookup with Realtime Stock Check
    function handleTransferBarcodeScan() {
        const input = document.getElementById('fastBarcodeScanInput');
        const code = input.value.trim();
        if (!code) return;

        fetch("{{ route('inventory.products.barcodeLookup') }}?code=" + encodeURIComponent(code))
            .then(res => res.json())
            .then(data => {
                if (data.success && data.product) {
                    const prod = data.product;
                    let rows = document.querySelectorAll('.transfer-row');
                    let targetRow = null;

                    // 1. First check if row with this product already exists
                    rows.forEach(r => {
                        let sel = r.querySelector('.product-select');
                        if (sel && sel.value == prod.id) {
                            targetRow = r;
                        }
                    });

                    // 2. If no existing row for this product, use an empty row
                    if (!targetRow) {
                        rows.forEach(r => {
                            let sel = r.querySelector('.product-select');
                            if (sel && (!sel.value || sel.value === '')) {
                                targetRow = r;
                            }
                        });
                    }

                    // 3. If no empty row exists, add a new line
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

                        // Auto-fill Serial Number if scanned code is a Serial Number!
                        if (data.is_serial && data.serial_number) {
                            let snInput = targetRow.querySelector('input[name*="[serial_numbers]"]');
                            if (snInput) {
                                let currentSns = snInput.value ? snInput.value.split(',').map(s => s.trim()).filter(Boolean) : [];
                                if (!currentSns.includes(data.serial_number)) {
                                    currentSns.push(data.serial_number);
                                    snInput.value = currentSns.join(', ');
                                    let qtyIn = targetRow.querySelector('.qty-input');
                                    if (qtyIn) {
                                        qtyIn.value = currentSns.length;
                                        $(qtyIn).trigger('change');
                                    }
                                }
                            }
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
