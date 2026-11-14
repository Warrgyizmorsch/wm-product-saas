@extends('layouts.duralux')

@section('title', __('purchase.new_purchase_return') . ' | SaaS ERP')
@section('page-title', __('purchase.new_purchase_return'))
@section('breadcrumb', __('ui.purchase') . ' / ' . __('purchase.purchase_returns') . ' / ' . __('purchase.create'))

@section('content')
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        @if ($errors->any())
            <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible class="mb-4">
                <h6 class="alert-heading fw-bold mb-1">{{ __('purchase.cannot_create_return') }}</h6>
                <ul class="fs-12 mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <form action="{{ route('purchase.returns.store') }}" method="POST" id="purchaseReturnForm" class="odoo-sheet">
            @csrf

            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                <div>
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="feather-rotate-ccw text-primary me-2"></i>{{ __('purchase.purchase_return_details') }}
                    </h5>
                    <small class="text-muted fs-12">{{ __('purchase.manage_purchase_returns_help') }}</small>
                </div>
            </div>

            <!-- 2-Mode Selection Radio Bar -->
            <div class="mb-4 bg-light p-3 rounded border">
                <label class="form-label fw-bold fs-11 text-uppercase text-muted d-block mb-2">{{ __('purchase.create_return_based_on') }}</label>
                <div class="d-flex gap-4 flex-wrap align-items-center">
                    <div class="form-check">
                        <input class="form-check-input mode-radio" type="radio" name="mode_option" id="modeGRN" value="grn" {{ $mode !== 'direct' ? 'checked' : '' }}>
                        <label class="form-check-label fw-bold text-dark fs-13" for="modeGRN">
                            <i class="feather-truck me-1 text-info"></i>{{ __('purchase.against_grn') }}
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input mode-radio" type="radio" name="mode_option" id="modeDirect" value="direct" {{ $mode === 'direct' ? 'checked' : '' }}>
                        <label class="form-check-label fw-bold text-dark fs-13" for="modeDirect">
                            <i class="feather-user me-1 text-success"></i>{{ __('purchase.direct_return') }}
                        </label>
                    </div>
                </div>
            </div>

            <div class="row g-3 fs-13 text-dark">
                <div class="col-md-3">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">
                        {{ __('purchase.return_number') }} <span class="text-danger">*</span>
                    </label>
                    <x-ui.odoo-form-ui type="input" name="return_number" :value="old('return_number', $nextReturnNumber)" :required="true" />
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">
                        {{ __('purchase.return_date') }} <span class="text-danger">*</span>
                    </label>
                    <x-ui.odoo-form-ui type="input" inputType="date" name="return_date" :value="old('return_date', date('Y-m-d'))" :required="true" />
                </div>

                @if ($mode !== 'direct')
                    <div class="col-md-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">
                            {{ __('purchase.grn_reference') }} <span class="text-danger">*</span>
                        </label>
                        <x-ui.odoo-form-ui type="select" name="goods_receipt_note_id" id="grnSelect" class="odoo-select2" :required="true">
                            <option value="">{{ __('purchase.select_grn') }}</option>
                            @foreach ($goodsReceiptNotes as $g)
                                <option value="{{ $g->id }}" {{ (string)$prefillGrnId === (string)$g->id ? 'selected' : '' }}>
                                    {{ $g->grn_number }} ({{ $g->vendor?->name ?? '—' }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>
                    <input type="hidden" name="purchase_order_id" value="{{ $prefillPurchaseOrderId }}">
                    <div class="col-md-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">
                            {{ __('purchase.supplier_vendor') }} <span class="text-danger">*</span>
                        </label>
                        <x-ui.odoo-form-ui type="select" name="vendor_id" id="vendorSelect" class="odoo-select2" :required="true">
                            <option value="">{{ __('purchase.select_vendor_placeholder') }}</option>
                            @foreach ($vendors as $v)
                                <option value="{{ $v->id }}" {{ (string)$prefillVendorId === (string)$v->id ? 'selected' : '' }}>
                                    {{ $v->name }}
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>
                @else
                    <div class="col-md-6">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">
                            {{ __('purchase.supplier_vendor') }} <span class="text-danger">*</span>
                        </label>
                        <x-ui.odoo-form-ui type="select" name="vendor_id" id="vendorSelect" class="odoo-select2" :required="true">
                            <option value="">{{ __('purchase.select_vendor_placeholder') }}</option>
                            @foreach ($vendors as $v)
                                <option value="{{ $v->id }}" {{ (string)$prefillVendorId === (string)$v->id ? 'selected' : '' }}>
                                    {{ $v->name }}
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>
                @endif

                <div class="col-12 mt-2">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">
                        {{ __('purchase.reason') }}
                    </label>
                    <x-ui.odoo-form-ui type="input" name="reason" :value="old('reason')" placeholder="{{ __('purchase.reason_placeholder') }}" />
                </div>
            </div>

            <div class="border-top pt-4 mt-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0 fs-14">
                        <i class="feather-layers text-primary me-1"></i>{{ __('purchase.returned_items') }}
                    </h5>
                </div>
                <div class="table-responsive border rounded bg-white">
                    <x-ui.odoo-form-ui type="table" id="itemsTable" class="mb-0">
                        <thead class="table-light fs-11 text-uppercase text-muted fw-semibold">
                            <tr>
                                <th style="width: 38%;" class="ps-3 text-start">{{ __('purchase.product') }}</th>
                                <th style="width: 25%;" class="text-start">{{ __('purchase.warehouse') ?? 'Warehouse' }}</th>
                                <th style="width: 16%;" class="text-end">{{ __('purchase.quantity') }}</th>
                                <th style="width: 16%;" class="text-end pe-3">{{ __('purchase.unit_price') }} ({{ active_currency_symbol() }})</th>
                                <th style="width: 5%;" class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsTableBody">
                            <!-- Dynamic Rows -->
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="mt-3">
                    <button type="button" class="btn btn-sm btn-soft-primary px-3 fw-bold" id="addItemRow">
                        <i class="feather-plus me-1"></i>{{ __('purchase.add_line') }}
                    </button>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <a href="{{ route('purchase.returns.index') }}" class="btn btn-light border fs-12 px-4">{{ __('purchase.discard') }}</a>
                <button type="submit" class="btn btn-primary text-white fs-12 px-4 fw-semibold shadow-sm">
                    <i class="feather-check me-1.5"></i>{{ __('purchase.save_return') }}
                </button>
            </div>
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
                let opts = '<option value="">{{ __("purchase.choose") }}</option>';
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
                    <tr class="item-row align-middle" data-row-id="${index}">
                        <td class="ps-3 py-2 text-start align-middle">
                            <select name="items[${index}][product_id]" class="form-select form-select-sm odoo-select2 product-select w-100" required>
                                ${buildOptions(productsList, p => p.sku ? `${p.sku} - ${p.name}` : p.name, prodId)}
                            </select>
                        </td>
                        <td class="py-2 text-start align-middle">
                            <select name="items[${index}][warehouse_id]" class="form-select form-select-sm odoo-select2 warehouse-select w-100" required>
                                ${buildOptions(warehousesList, w => w.name, whId)}
                            </select>
                        </td>
                        <td class="text-end py-2 align-middle">
                            <input type="number" name="items[${index}][quantity]" class="form-control form-control-sm text-end font-monospace fw-semibold w-100" value="${qty}" min="0.0001" step="any" placeholder="0.00" required>
                        </td>
                        <td class="text-end pe-3 py-2 align-middle">
                            <input type="number" name="items[${index}][unit_price]" class="form-control form-control-sm text-end font-monospace fw-semibold w-100" value="${parseFloat(price).toFixed(2)}" min="0" step="0.01" placeholder="0.00" required>
                        </td>
                        <td class="text-center py-2 align-middle">
                            <button type="button" class="btn btn-sm btn-link text-danger remove-row-btn p-1 border-0" title="{{ __('purchase.delete') }}">
                                <i class="feather-trash-2 fs-15"></i>
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
                    showAppToast('warning', 'At least one item row is required.');
                }
            });

            $(document).on('change', '.product-select', function() {
                const prodId = $(this).val();
                const row = $(this).closest('tr');
                const prod = productsList.find(p => p.id == prodId);
                if (prod && prod.price !== undefined) {
                    row.find('input[name*="[unit_price]"]').val(parseFloat(prod.price).toFixed(2));
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
