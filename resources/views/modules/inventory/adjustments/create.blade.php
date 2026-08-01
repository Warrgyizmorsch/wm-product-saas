@extends('layouts.duralux')

@section('title', 'New Stock Adjustment | SaaS ERP')
@section('page-title', 'New Stock Adjustment')
@section('breadcrumb', 'Inventory / Stock Adjustments / Create')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .select2-container {
            width: 100% !important;
            max-width: 100% !important;
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

    <form action="{{ route('inventory.adjustments.store') }}" method="POST" id="adjustmentForm">
        @csrf
        <x-ui.odoo-form-ui type="sheet">
            <!-- Header Bar -->
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 flex-wrap gap-2">
                <div>
                    <h4 class="fw-bold text-dark mb-0">New Stock Adjustment</h4>
                    <span class="fs-12 text-muted">Create a physical stock count variance or inventory adjustment order.</span>
                </div>
                <div class="d-flex gap-2">
                    <x-ui.button href="{{ route('inventory.adjustments.index') }}" variant="light" class="border">Discard</x-ui.button>
                    <x-ui.button type="submit" variant="primary" icon="feather-check">Save Adjustment</x-ui.button>
                </div>
            </div>

            <!-- Form Grid -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="select" label="Warehouse" name="warehouse_id" class="select2-select" :required="true" :error-text="$errors->first('warehouse_id')">
                        <option value="">Select Warehouse</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ old('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="Adjustment Date" name="adjustment_date" value="{{ old('adjustment_date', date('Y-m-d')) }}" :required="true" :error-text="$errors->first('adjustment_date')" />
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="select" label="Reason" name="reason" class="select2-select" :required="true" :error-text="$errors->first('reason')">
                        <option value="Stock Count Variance" {{ old('reason') === 'Stock Count Variance' ? 'selected' : '' }}>Stock Count Variance</option>
                        <option value="Damaged" {{ old('reason') === 'Damaged' ? 'selected' : '' }}>Damaged</option>
                        <option value="Expired" {{ old('reason') === 'Expired' ? 'selected' : '' }}>Expired</option>
                        <option value="Theft/Loss" {{ old('reason') === 'Theft/Loss' ? 'selected' : '' }}>Theft/Loss</option>
                        <option value="Scrap" {{ old('reason') === 'Scrap' ? 'selected' : '' }}>Scrap</option>
                        <option value="Sample" {{ old('reason') === 'Sample' ? 'selected' : '' }}>Sample</option>
                        <option value="Other" {{ old('reason') === 'Other' ? 'selected' : '' }}>Other</option>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-12">
                    <x-ui.odoo-form-ui type="textarea" label="Notes / Description" name="notes" rows="2" placeholder="Describe reason for adjustment..." :error-text="$errors->first('notes')">{{ old('notes') }}</x-ui.odoo-form-ui>
                </div>
            </div>

            <!-- Line Items Table Section -->
            <div class="border-top pt-4">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                    <h5 class="fw-bold text-dark mb-0 fs-14"><i class="feather-layers text-primary me-2"></i>Adjustment Line Items</h5>
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
                                <th style="width: 40%;" class="ps-3">Product <span class="text-danger">*</span></th>
                                <th style="width: 15%;">Type <span class="text-danger">*</span></th>
                                <th style="width: 12%;" class="text-center">Quantity <span class="text-danger">*</span></th>
                                <th style="width: 13%;" class="text-center">Unit Cost</th>
                                <th style="width: 15%;">Serial Numbers</th>
                                <th style="width: 5%;" class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="adjustment-row">
                                <td class="ps-3">
                                    <select name="items[0][product_id]" class="form-select odoo-table-select product-select" required>
                                        <option value="">Select Product</option>
                                        @foreach($products as $prod)
                                            <option value="{{ $prod->id }}">{{ $prod->name }} (SKU: {{ $prod->sku }})</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select name="items[0][type]" class="form-select odoo-table-select" required>
                                        <option value="Deduction">Deduction (-)</option>
                                        <option value="Addition">Addition (+)</option>
                                    </select>
                                </td>
                                <td class="text-center">
                                    <input type="number" name="items[0][quantity]" class="odoo-table-input text-center qty-input mx-auto" step="0.01" min="0.01" placeholder="Qty" value="1" required style="width: 90px;">
                                </td>
                                <td class="text-center">
                                    <input type="number" name="items[0][unit_cost]" class="odoo-table-input text-center cost-input mx-auto" step="0.01" min="0" placeholder="Cost" style="width: 90px;">
                                </td>
                                <td>
                                    <input type="text" name="items[0][serial_numbers]" class="odoo-table-input" placeholder="SN1, SN2...">
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-icon btn-sm btn-soft-danger remove-row mt-1"><i class="feather-trash-2"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>

                <div class="mt-3">
                    <button type="button" class="btn btn-xs btn-outline-primary fw-bold" id="add-item" style="font-size: 10px; padding: 2px 8px; text-transform: none !important;">
                        <i class="feather-plus me-1"></i>Add line item
                    </button>
                </div>
            </div>

            <!-- Footer Action Bar -->
            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <x-ui.button href="{{ route('inventory.adjustments.index') }}" variant="light" class="border px-4">Discard</x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="feather-check" class="px-4">Save Adjustment</x-ui.button>
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

    let rowIndex = 1;
    document.getElementById('add-item').addEventListener('click', function() {
        let table = document.getElementById('items-table').getElementsByTagName('tbody')[0];
        let newRow = table.insertRow();
        newRow.className = 'adjustment-row';
        newRow.innerHTML = `
            <td class="ps-3">
                <select name="items[${rowIndex}][product_id]" class="form-select odoo-table-select product-select" required>
                    <option value="">Select Product</option>
                    @foreach($products as $prod)
                        <option value="{{ $prod->id }}">{{ $prod->name }} (SKU: {{ $prod->sku }})</option>
                    @endforeach
                </select>
            </td>
            <td>
                <select name="items[${rowIndex}][type]" class="form-select odoo-table-select" required>
                    <option value="Deduction">Deduction (-)</option>
                    <option value="Addition">Addition (+)</option>
                </select>
            </td>
            <td class="text-center">
                <input type="number" name="items[${rowIndex}][quantity]" class="odoo-table-input text-center qty-input mx-auto" step="0.01" min="0.01" placeholder="Qty" value="1" required style="width: 90px;">
            </td>
            <td class="text-center">
                <input type="number" name="items[${rowIndex}][unit_cost]" class="odoo-table-input text-center cost-input mx-auto" step="0.01" min="0" placeholder="Cost" style="width: 90px;">
            </td>
            <td>
                <input type="text" name="items[${rowIndex}][serial_numbers]" class="odoo-table-input" placeholder="SN1, SN2...">
            </td>
            <td class="text-center">
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

    // Fast Barcode Lookup
    function handleAdjBarcodeScan() {
        const input = document.getElementById('fastBarcodeScanInput');
        const code = input.value.trim();
        if (!code) return;

        fetch("{{ route('inventory.products.barcodeLookup') }}?code=" + encodeURIComponent(code))
            .then(res => res.json())
            .then(data => {
                if (data.success && data.product) {
                    const prod = data.product;
                    let rows = document.querySelectorAll('.adjustment-row');
                    let targetRow = null;

                    rows.forEach(r => {
                        let sel = r.querySelector('.product-select');
                        if (sel && (!sel.value || sel.value === '')) {
                            targetRow = r;
                        }
                    });

                    if (!targetRow) {
                        document.getElementById('add-item').click();
                        let newRows = document.querySelectorAll('.adjustment-row');
                        targetRow = newRows[newRows.length - 1];
                    }

                    if (targetRow) {
                        let sel = targetRow.querySelector('.product-select');
                        let costIn = targetRow.querySelector('.cost-input');
                        if (sel) $(sel).val(prod.id).trigger('change');
                        if (costIn) costIn.value = prod.unit_cost || prod.cost_price;
                    }
                    input.value = '';
                } else {
                    alert('Product not found for code: ' + code);
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
            handleAdjBarcodeScan();
        }
    });
    document.getElementById('fastBarcodeScanBtn').addEventListener('click', function(e) {
        e.preventDefault();
        handleAdjBarcodeScan();
    });
</script>
@endpush
@endsection
