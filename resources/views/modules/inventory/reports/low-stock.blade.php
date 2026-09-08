@extends('layouts.duralux')

@section('title', 'Low Stock Report | SaaS ERP')
@section('page-title', 'Low Stock & Reorder Alert Report')
@section('breadcrumb', 'Inventory / Reports / Low Stock')

@section('content')
<div class="erp-single-panel text-dark">
    <x-ui.odoo-form-ui type="sheet">

        <!-- Toolbar: Header, Search, Filter & Action Buttons -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <h5 class="fw-bold text-dark mb-0 me-2">
                    <i class="feather-alert-triangle text-danger me-2"></i>Low Stock Alert Items
                </h5>
                <span class="badge bg-soft-danger text-danger border border-danger-subtle font-monospace fw-bold fs-11">
                    {{ $products instanceof \Illuminate\Pagination\LengthAwarePaginator ? $products->total() : $products->count() }} Alert Items
                </span>
            </div>

            <div class="d-flex align-items-center flex-wrap gap-2">

                <!-- Raise PR Button (Triggers Modal) -->
                <button type="button" id="raisePrBtn" class="btn btn-sm btn-primary d-flex align-items-center gap-1 opacity-50" disabled>
                    <i class="feather-file-plus fs-14"></i>
                    <span>Raise Purchase Requisition (PR)</span>
                    <span id="selectedBadge" class="badge bg-white text-primary ms-1 d-none">0</span>
                </button>

                <!-- Quick Search -->
                <div class="d-flex align-items-center bg-light border rounded px-3 py-1">
                    <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                    <input 
                        type="text" 
                        id="quickSearchInput"
                        class="form-control border-0 bg-transparent p-0 fs-13" 
                        placeholder="Search product or SKU..." 
                        value="{{ request('search') }}"
                        style="box-shadow: none; height: 32px; width: 200px;"
                    >
                </div>

                <!-- Filter Component -->
                <x-ui.filter label="Filter" offset="0, 5">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Low Stock Report</h6>
                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Product Name or SKU</label>
                        <x-ui.odoo-form-ui type="input" name="search" placeholder="Type product name or SKU..." value="{{ request('search') }}" />
                    </div>
                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="{{ route('inventory.reports.low-stock') }}" class="btn btn-sm btn-light border">Reset</a>
                        <button type="submit" form="filterForm" class="btn btn-sm btn-primary">Apply Filter</button>
                    </div>
                </x-ui.filter>

                <x-ui.button href="javascript:window.print()" variant="light" class="border btn-sm" icon="feather-printer">
                    Print Report
                </x-ui.button>
            </div>
        </div>

        <!-- Table Component -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="lowStockReportTable">
                <thead class="table-light bg-light">
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" id="selectAllLowStock" class="form-check-input" title="Select All Items">
                        </th>
                        <th>Product Name</th>
                        <th>SKU Code</th>
                        <th class="text-end">Current Total Stock</th>
                        <th class="text-end">Reorder Point</th>
                        <th class="text-end">Shortage Qty</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody class="text-dark">
                    @forelse($products as $product)
                        @php
                            $effectiveReorderPoint = (float)($product->reorder_point > 0 ? $product->reorder_point : 10);
                            $shortage = max(0, $effectiveReorderPoint - $product->total_stock);

                            // Existing PR Tracking Data
                            $prItems = $existingPrItems[$product->id] ?? collect();
                            $draftPrs = $prItems->filter(fn($i) => optional($i->requisition)->status === 'Draft');
                            $approvedPrs = $prItems->filter(fn($i) => optional($i->requisition)->status === 'Approved');
                            $draftQty = $draftPrs->sum('quantity');
                            $approvedQty = $approvedPrs->sum('quantity');
                        @endphp
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" 
                                       value="{{ $product->id }}" 
                                       class="form-check-input product-select-checkbox"
                                       data-name="{{ $product->name }}"
                                       data-sku="{{ $product->sku }}"
                                       data-stock="{{ number_format($product->total_stock, 2, '.', '') }}"
                                       data-reorder="{{ number_format($effectiveReorderPoint, 2, '.', '') }}"
                                       data-shortage="{{ number_format($shortage, 2, '.', '') }}">
                            </td>
                            <td>
                                <a href="{{ route('inventory.products.show', $product->id) }}" class="fw-bold text-primary text-decoration-none fs-13">
                                    {{ $product->name }}
                                </a>
                                <small class="text-muted d-block fs-11">Type: {{ ucfirst($product->type ?: 'Goods') }}</small>

                                <!-- PR Tracking Badges -->
                                @if($draftQty > 0 || $approvedQty > 0)
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        @if($draftQty > 0)
                                            @php
                                                $draftNumbers = $draftPrs->map(fn($i) => $i->requisition?->requisition_number)->filter()->unique()->implode(', ');
                                            @endphp
                                            <span class="badge bg-soft-warning text-warning border border-warning-subtle fs-10 py-1 px-2" title="Draft PR(s): {{ $draftNumbers }}">
                                                <i class="feather-clock me-1"></i>PR Raised: {{ number_format($draftQty, 2) }} (Draft)
                                            </span>
                                        @endif
                                        @if($approvedQty > 0)
                                            @php
                                                $approvedNumbers = $approvedPrs->map(fn($i) => $i->requisition?->requisition_number)->filter()->unique()->implode(', ');
                                            @endphp
                                            <span class="badge bg-soft-success text-success border border-success-subtle fs-10 py-1 px-2" title="Approved PR(s): {{ $approvedNumbers }}">
                                                <i class="feather-check-circle me-1"></i>PR Approved: {{ number_format($approvedQty, 2) }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td>
                                <span class="font-monospace text-dark fw-semibold fs-12">{{ $product->sku ?: '—' }}</span>
                            </td>
                            <td class="text-end font-monospace">
                                <span class="badge bg-soft-danger text-danger border border-danger-subtle px-2 py-1 fw-bold fs-11">
                                    {{ number_format($product->total_stock, 2) }}
                                </span>
                            </td>
                            <td class="text-end font-monospace fw-bold text-dark fs-12">
                                {{ number_format($effectiveReorderPoint, 2) }}
                            </td>
                            <td class="text-end font-monospace fw-bold text-danger fs-12">
                                +{{ number_format($shortage, 2) }}
                            </td>
                            <td class="text-center">
                                @if($product->total_stock <= 0)
                                    <x-ui.status-badge status="out_of_stock" size="sm" />
                                @else
                                    <x-ui.status-badge status="low_stock" size="sm" />
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                <x-ui.action-dropdown>
                                    <li>
                                        <button type="button" class="dropdown-item text-primary fw-semibold single-pr-btn" data-product-id="{{ $product->id }}">
                                            <i class="feather-file-plus me-2 text-primary fs-12"></i>Raise PR for Item
                                        </button>
                                    </li>
                                    <li>
                                        <a href="{{ route('inventory.products.edit', $product->id) }}" class="dropdown-item">
                                            <i class="feather-edit me-2 text-muted fs-12"></i>Edit Product & Reorder
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('inventory.adjustments.create') }}?product_id={{ $product->id }}" class="dropdown-item text-secondary fs-12">
                                            <i class="feather-plus-circle me-2 text-secondary fs-12"></i>Create Stock Adjustment
                                        </a>
                                    </li>
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-success fw-bold">
                                <i class="feather-check-circle fs-1 d-block mb-3 text-success opacity-75"></i>
                                Excellent! All items are above their reorder stock levels.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <!-- Pagination Section -->
        @if($products instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div class="pt-3">
                <x-ui.pagination 
                    :currentPage="$products->currentPage()" 
                    :totalPages="$products->lastPage()" 
                    :totalResults="$products->total()" 
                    :perPage="$products->perPage()" />
            </div>
        @endif

        <form id="filterForm" method="GET" action="{{ route('inventory.reports.low-stock') }}" class="d-none">
            <input type="hidden" name="search" id="filterFormSearch" value="{{ request('search') }}">
        </form>

    </x-ui.odoo-form-ui>
</div>

<!-- Modal: Prefill & Edit PR Quantities Before Submit -->
<x-ui.modal id="createPrModal" title="<i class='feather-file-plus text-primary me-2'></i>Raise Purchase Requisition (PR)" size="xl" centered="true" :showFooter="false">
    <form id="lowStockPrForm" method="POST" action="{{ route('inventory.reports.low-stock.create-pr') }}">
        @csrf
        <div class="alert alert-soft-primary border border-primary-subtle d-flex align-items-center gap-2 py-2 px-3 fs-12 rounded-3 mb-3 text-dark">
            <i class="feather-info text-primary fs-16 flex-shrink-0"></i>
            <div>
                <strong>Review & Modify Quantities:</strong> Calculated shortage quantities have been prefilled below. You can adjust the PR quantity for each item before generating the Draft PR.
            </div>
        </div>

        <div class="table-responsive border rounded-3 mb-3 bg-white">
            <table class="table table-hover align-middle mb-0 text-dark fs-13">
                <thead class="table-light bg-light border-bottom">
                    <tr>
                        <th style="width: 35%;">Product Name & SKU</th>
                        <th class="text-end" style="width: 15%;">Current Stock</th>
                        <th class="text-end" style="width: 15%;">Reorder Point</th>
                        <th class="text-end" style="width: 15%;">Shortage Qty</th>
                        <th class="text-end pe-3" style="width: 20%;">PR Request Qty *</th>
                    </tr>
                </thead>
                <tbody id="modalPrTableBody">
                    <!-- Populated dynamically via JS -->
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-end gap-2 pt-3 border-top">
            <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" id="confirmSubmitPrBtn" class="btn btn-sm btn-primary px-3">
                <i class="feather-check-circle me-1"></i>Confirm & Create Draft PR
            </button>
        </div>
    </form>
</x-ui.modal>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        const $selectAll = $('#selectAllLowStock');
        const $checkboxes = $('.product-select-checkbox');
        const $raiseBtn = $('#raisePrBtn');
        const $selectedBadge = $('#selectedBadge');

        function updateActionState() {
            const checkedBoxes = $('.product-select-checkbox:checked');
            const count = checkedBoxes.length;

            if (count > 0) {
                $raiseBtn.prop('disabled', false).removeClass('opacity-50');
                $selectedBadge.removeClass('d-none').text(count);
            } else {
                $raiseBtn.prop('disabled', true).addClass('opacity-50');
                $selectedBadge.addClass('d-none').text('0');
            }

            if ($checkboxes.length > 0) {
                $selectAll.prop('checked', count === $checkboxes.length);
            }
        }

        $selectAll.on('change', function() {
            $checkboxes.prop('checked', $(this).is(':checked'));
            updateActionState();
        });

        $checkboxes.on('change', function() {
            updateActionState();
        });

        function populateAndOpenPrModal(checkedBoxes) {
            let html = '';
            checkedBoxes.each(function(index) {
                const $chk = $(this);
                const id = $chk.val();
                const name = $chk.data('name');
                const sku = $chk.data('sku');
                const stock = parseFloat($chk.data('stock') || 0).toFixed(2);
                const reorder = parseFloat($chk.data('reorder') || 0).toFixed(2);
                const shortage = parseFloat($chk.data('shortage') || 0).toFixed(2);
                const initialQty = shortage > 0 ? shortage : 1;

                html += `
                    <tr>
                        <td class="py-2.5">
                            <strong class="text-dark d-block fs-13">${name}</strong>
                            <span class="text-muted font-monospace fs-11">${sku || '—'}</span>
                            <input type="hidden" name="items[${index}][product_id]" value="${id}">
                        </td>
                        <td class="text-end font-monospace py-2.5">
                            <span class="badge bg-light text-dark border font-monospace px-2 py-1 fs-11">${stock}</span>
                        </td>
                        <td class="text-end font-monospace fw-semibold text-muted py-2.5">${reorder}</td>
                        <td class="text-end font-monospace py-2.5">
                            <span class="badge bg-soft-danger text-danger border border-danger-subtle px-2 py-1 fw-bold fs-11">+${shortage}</span>
                        </td>
                        <td class="text-end py-2.5 pe-3">
                            <div class="input-group input-group-sm ms-auto" style="width: 140px;">
                                <input type="number" 
                                       step="0.0001" 
                                       min="0.0001" 
                                       name="items[${index}][quantity]" 
                                       value="${initialQty}" 
                                       class="form-control form-control-sm text-end font-monospace fw-bold text-primary border-primary shadow-sm" 
                                       style="border-radius: 6px; font-size: 13px;"
                                       required>
                            </div>
                        </td>
                    </tr>
                `;
            });

            $('#modalPrTableBody').html(html);

            const modalEl = document.getElementById('createPrModal');
            if (modalEl) {
                const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
                bsModal.show();
            }
        }

        // Raise PR button click (Bulk)
        $raiseBtn.on('click', function() {
            const checkedBoxes = $('.product-select-checkbox:checked');
            if (checkedBoxes.length > 0) {
                populateAndOpenPrModal(checkedBoxes);
            }
        });

        // Single PR button handler
        $('.single-pr-btn').on('click', function() {
            const productId = $(this).data('product-id');
            if (productId) {
                $checkboxes.prop('checked', false);
                const $target = $(`.product-select-checkbox[value="${productId}"]`);
                $target.prop('checked', true);
                updateActionState();
                populateAndOpenPrModal($target);
            }
        });

        // Quick search press Enter
        $('#quickSearchInput').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#filterFormSearch').val($(this).val());
                $('#filterForm').submit();
            }
        });
    });
</script>
@endpush
