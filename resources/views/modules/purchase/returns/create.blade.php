@extends('layouts.duralux')

@section('title', 'New Purchase Return | SaaS ERP')
@section('page-title', 'New Purchase Return')
@section('breadcrumb', 'Purchase / Returns / Create')

@section('content')
    <div class="erp-single-panel bg-white">
        @if ($errors->any())
            <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible class="mb-4">
                <h6 class="alert-heading fw-bold mb-1">Cannot create this return</h6>
                <ul class="fs-12 mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <form action="{{ route('purchase.returns.store') }}" method="POST" id="purchaseReturnForm">
            @csrf

            <x-ui.odoo-form-ui type="sheet">
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                    <h5 class="fw-bold text-dark mb-0">Purchase Return Details</h5>
                    <x-ui.button href="{{ route('purchase.returns.index') }}" variant="light" size="sm" class="border">Cancel</x-ui.button>
                </div>

                <!-- 2-Mode Selection Radio Bar -->
                <div class="mb-4 bg-light p-3 rounded border">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted d-block mb-2">Create Purchase Return Based On:</label>
                    <div class="d-flex gap-4 flex-wrap align-items-center">
                        <div class="form-check">
                            <input class="form-check-input mode-radio" type="radio" name="mode_option" id="modeGRN" value="grn" {{ $mode !== 'direct' ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold text-dark fs-13" for="modeGRN">
                                <i class="feather-truck me-1 text-info"></i>Against Goods Receipt (GRN)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input mode-radio" type="radio" name="mode_option" id="modeDirect" value="direct" {{ $mode === 'direct' ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold text-dark fs-13" for="modeDirect">
                                <i class="feather-user me-1 text-success"></i>Direct Return (Standalone)
                            </label>
                        </div>
                    </div>
                </div>

                <div class="row g-4 fs-13 text-dark">
                    <div class="col-md-3">
                        <x-ui.odoo-form-ui type="input" label="Return Number" name="return_number" :value="old('return_number', $nextReturnNumber)" :required="true" />
                    </div>
                    <div class="col-md-3">
                        <x-ui.odoo-form-ui type="input" inputType="date" label="Return Date" name="return_date" :value="old('return_date', date('Y-m-d'))" :required="true" />
                    </div>

                    @if ($mode !== 'direct')
                        <div class="col-md-3">
                            <x-ui.odoo-form-ui type="select" label="Goods Receipt (GRN) Reference" name="goods_receipt_note_id" id="grnSelect" class="odoo-select2" :required="true">
                                <option value="">Select GRN...</option>
                                @foreach ($goodsReceiptNotes as $g)
                                    <option value="{{ $g->id }}" {{ (string)$prefillGrnId === (string)$g->id ? 'selected' : '' }}>
                                        {{ $g->grn_number }} (Vendor: {{ $g->vendor?->name ?? '—' }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <input type="hidden" name="purchase_order_id" value="{{ $prefillPurchaseOrderId }}">
                        <div class="col-md-3">
                            <x-ui.odoo-form-ui type="select" label="Vendor" name="vendor_id" id="vendorSelect" class="odoo-select2" :required="true">
                                <option value="">Select Vendor...</option>
                                @foreach ($vendors as $v)
                                    <option value="{{ $v->id }}" {{ (string)$prefillVendorId === (string)$v->id ? 'selected' : '' }}>
                                        {{ $v->name }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                    @else
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="Vendor" name="vendor_id" id="vendorSelect" class="odoo-select2" :required="true">
                                <option value="">Select Vendor...</option>
                                @foreach ($vendors as $v)
                                    <option value="{{ $v->id }}" {{ (string)$prefillVendorId === (string)$v->id ? 'selected' : '' }}>
                                        {{ $v->name }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                    @endif
                </div>
                <div class="row g-4 fs-13 text-dark mt-1">
                    <div class="col-md-12">
                        <x-ui.odoo-form-ui type="input" label="Reason" name="reason" :value="old('reason')" placeholder="Why is this being returned to the vendor?" />
                    </div>
                </div>

                <div class="border-top pt-4 mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0 fs-14">Returned Items</h5>
                    </div>
                    <div class="table-responsive">
                        <x-ui.odoo-form-ui type="table" id="itemsTable">
                            <thead>
                                <tr>
                                    <th style="width: 32%;">Product</th>
                                    <th style="width: 28%;">Warehouse</th>
                                    <th class="text-end" style="width: 14%;">Qty</th>
                                    <th class="text-end" style="width: 16%;">Unit Price</th>
                                    <th class="text-center" style="width: 10%;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dynamic Rows -->
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-xs btn-outline-primary fw-bold" id="addItemRow" style="font-size: 10px; padding: 2px 8px; text-transform: none !important;">
                            <i class="feather-plus me-1"></i>Add a line
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <x-ui.button href="{{ route('purchase.returns.index') }}" variant="light" size="md" class="border">Discard</x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="md" class="fw-bold">Save Return</x-ui.button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            let rowIndex = 0;

            const productsList = @json($productsJson);
            const warehousesList = @json($warehousesJson);
            const prefillItems = @json($prefillItemsJson);

            function escapeHtml(string) {
                return String(string).replace(/[&<>"']/g, function (s) {
                    return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': '&quot;', "'": '&#39;' }[s];
                });
            }

            function buildOptions(list, labelFn, selectedVal) {
                let opts = '<option value="">Select...</option>';
                list.forEach(function(o) {
                    const sel = (selectedVal && selectedVal == o.id) ? 'selected' : '';
                    opts += `<option value="${o.id}" ${sel}>${escapeHtml(labelFn(o))}</option>`;
                });
                return opts;
            }

            function getRowHtml(index, data = {}) {
                const prodId = data.product_id || '';
                const whId = data.warehouse_id || (warehousesList[0] ? warehousesList[0].id : '');
                const qty = data.quantity !== undefined ? data.quantity : 1;
                const price = data.unit_price !== undefined ? data.unit_price : 0.00;

                return `
                    <tr class="item-row" data-row-id="${index}">
                        <td class="ps-3">
                            <select name="items[${index}][product_id]" class="form-select odoo-table-select odoo-select2 product-select" required>
                                ${buildOptions(productsList, p => p.sku ? `${p.sku} - ${p.name}` : p.name, prodId)}
                            </select>
                        </td>
                        <td>
                            <select name="items[${index}][warehouse_id]" class="form-select odoo-table-select odoo-select2 warehouse-select" required>
                                ${buildOptions(warehousesList, w => w.name, whId)}
                            </select>
                        </td>
                        <td>
                            <input type="number" name="items[${index}][quantity]" class="odoo-table-input text-end" value="${qty}" min="0.0001" step="0.0001" style="width: 90px; margin-left: auto;">
                        </td>
                        <td>
                            <input type="number" name="items[${index}][unit_price]" class="odoo-table-input text-end" value="${parseFloat(price).toFixed(2)}" min="0" step="0.01" style="width: 110px; margin-left: auto;">
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-icon btn-sm btn-soft-danger remove-row-btn mt-1">
                                <i class="feather-trash-2"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }

            $('.mode-radio').on('change', function() {
                window.location.href = "{{ route('purchase.returns.create') }}?mode=" + $(this).val();
            });

            $('#grnSelect').on('change', function() {
                const grnId = $(this).val();
                window.location.href = "{{ route('purchase.returns.create') }}?mode=grn&goods_receipt_note_id=" + grnId;
            });

            $('#addItemRow').on('click', function() {
                addRow();
            });

            $(document).on('click', '.remove-row-btn', function() {
                const rowsCount = $('.item-row').length;
                if (rowsCount > 1) {
                    $(this).closest('tr').remove();
                } else {
                    alert('A return requires at least one line.');
                }
            });

            function addRow(data = {}) {
                const newRow = $(getRowHtml(rowIndex, data));
                $('#itemsTable tbody').append(newRow);

                if (typeof $.fn.select2 === 'function') {
                    newRow.find('.product-select, .warehouse-select').select2({ theme: "bootstrap-5", width: "100%" });
                }

                rowIndex++;
            }

            if (prefillItems && prefillItems.length > 0) {
                prefillItems.forEach(item => addRow(item));
            } else {
                addRow();
            }
        });
    </script>
@endpush
