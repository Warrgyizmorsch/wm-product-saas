@extends('layouts.duralux')

@section('title', 'Pending PR Items | SaaS ERP')
@section('page-title', 'Pending Requisition Items')
@section('breadcrumb', 'Purchase / Pending Requisitions')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .select2-container {
            z-index: 1060 !important;
        }
        .action-icon-btn {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 32px !important;
            height: 32px !important;
            border-radius: 8px !important;
            border: 1.5px solid #cbd5e1 !important;
            background-color: #ffffff !important;
            color: #475569 !important;
            transition: all 0.28s ease !important;
            text-decoration: none !important;
            cursor: pointer !important;
        }
        .action-icon-btn.po-btn:hover {
            background-color: color-mix(in srgb, var(--bs-primary) 10%, transparent) !important;
            border-color: var(--bs-primary) !important;
            color: var(--bs-primary) !important;
        }
        .action-icon-btn.rfq-btn:hover {
            background-color: color-mix(in srgb, var(--bs-info) 10%, transparent) !important;
            border-color: var(--bs-info) !important;
            color: var(--bs-info) !important;
        }
    </style>
@endpush

@section('content')
    <div class="row text-dark">
        <!-- Toast Notifications -->
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <div class="col-12">
            <div class="card border-0 shadow-sm p-4 p-md-5 bg-white">
                
                <!-- Page Top Control Bar -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom flex-wrap gap-3">
                    <div>
                        <h4 class="fw-bold text-dark mb-0">Pending Requisition Line Items</h4>
                        <small class="text-muted fs-12">Review approved requisition items waiting to be ordered. Select items to generate draft Purchase Orders.</small>
                    </div>
                    
                    <!-- Group By Filter Selector -->
                    <div class="d-flex align-items-center gap-2">
                        <label class="form-label fs-12 fw-bold text-muted mb-0 text-uppercase">Group By:</label>
                        <select id="groupBySelect" class="form-select form-select-sm fw-semibold text-primary" style="width: 180px;" onchange="changeGroupBy(this.value)">
                            <option value="supplier" @selected($groupBy === 'supplier')>Supplier / Vendor</option>
                            <option value="pr" @selected($groupBy === 'pr')>PR Number</option>
                            <option value="date" @selected($groupBy === 'date')>Date</option>
                        </select>
                    </div>
                </div>

                <form id="bulkPoForm" action="#" method="POST" onsubmit="return false;">
                    @csrf

                    <!-- Execute Actions Banner -->
                    <div class="d-flex justify-content-between align-items-center p-3 mb-4 rounded border" style="background-color: #f8f9fa;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="feather-info text-primary fs-18"></i>
                            <span class="fs-13 fw-semibold text-dark">
                                Select items from the list below. Choose whether to bulk generate Draft Purchase Orders or Draft RFQs grouped by Supplier.
                            </span>
                        </div>
                        <div class="d-flex gap-2">
                            <x-ui.button type="button" variant="primary" size="sm" class="btn-submit-bulk" data-action="po" icon="feather-plus-circle">
                                Create Bulk POs
                            </x-ui.button>
                            <x-ui.button type="button" variant="success" size="sm" class="btn-submit-bulk text-white" data-action="rfq" icon="feather-mail">
                                Create Bulk RFQs
                            </x-ui.button>
                        </div>
                    </div>

                    @if($groupBy === 'supplier')
                        <!-- Tab navigation for Supplier-wise grouping -->
                        <ul class="nav nav-tabs nav-tabs-custom mb-4" id="pendingPrTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active fw-bold position-relative" id="assigned-tab" data-bs-toggle="tab" data-bs-target="#assigned-pane" type="button" role="tab">
                                    <i class="feather-truck me-2"></i>Assigned Suppliers
                                    <span class="badge rounded-pill bg-primary ms-2 fs-10">{{ count($assignedItems) }}</span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-bold position-relative" id="unassigned-tab" data-bs-toggle="tab" data-bs-target="#unassigned-pane" type="button" role="tab">
                                    <i class="feather-help-circle text-danger me-2"></i>No Supplier
                                    <span class="badge rounded-pill bg-danger ms-2 fs-10">{{ count($unassignedItems) }}</span>
                                </button>
                            </li>
                        </ul>

                        <!-- Tab Content panes -->
                        <div class="tab-content" id="pendingPrTabsContent">
                            
                            <!-- Pane 1: Assigned Items Table -->
                            <div class="tab-pane fade show active" id="assigned-pane" role="tabpanel">
                                @if(empty($assignedItems))
                                    <div class="text-center py-5 border rounded bg-light">
                                        <i class="feather-check-circle text-success fs-32 mb-2"></i>
                                        <h6 class="fw-bold">No assigned items</h6>
                                        <p class="text-muted fs-12 mb-0">All items are either unassigned or fully ordered.</p>
                                    </div>
                                @else
                                    <div class="table-responsive">
                                        <x-ui.odoo-form-ui type="table" id="assignedTable">
                                            <thead>
                                                <tr>
                                                    <th style="width: 4%;" class="text-center">
                                                        <input type="checkbox" class="form-check-input select-all-pane" data-pane="assigned-pane">
                                                    </th>
                                                    <th style="width: 22%;">Product Details</th>
                                                    <th style="width: 18%;">Supplier / Vendor</th>
                                                    <th style="width: 14%;">PR / Date</th>
                                                    <th style="width: 12%;">Warehouse</th>
                                                    <th class="text-end" style="width: 8%;">Req Qty</th>
                                                    <th class="text-end" style="width: 8%;">Ordered</th>
                                                    <th class="text-end" style="width: 8%;">Pending</th>
                                                    <th style="width: 12%;" class="text-center">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($assignedItems as $pi)
                                                    <tr class="item-row">
                                                        <td class="text-center">
                                                            <input type="checkbox" name="item_ids[]" value="{{ $pi['item_id'] }}" class="form-check-input row-checkbox pane-checkbox-assigned-pane" data-vendor-id="{{ $pi['vendor_id'] }}" data-vendor-name="{{ $pi['vendor_name'] }}" data-product-name="{{ $pi['product_name'] }}" data-quantity="{{ $pi['quantity_pending'] }}" data-uom="{{ $pi['uom'] }}" data-warehouse-id="{{ $pi['warehouse_id'] }}" data-warehouse-name="{{ $pi['warehouse_name'] }}">
                                                        </td>
                                                        <td>
                                                            <div class="fw-bold text-truncate text-dark" title="{{ $pi['product_name'] }}">{{ $pi['product_name'] }}</div>
                                                            <div class="text-muted fs-11">SKU: {{ $pi['sku'] }}</div>
                                                        </td>
                                                        <td class="fw-semibold text-primary text-truncate" title="{{ $pi['vendor_name'] }}">
                                                            {{ $pi['vendor_name'] }}
                                                        </td>
                                                        <td>
                                                            <span class="fw-semibold text-dark">{{ $pi['requisition_number'] }}</span>
                                                            <div class="text-muted fs-11">{{ $pi['requisition_date'] ? \Carbon\Carbon::parse($pi['requisition_date'])->format('d-M-Y') : '—' }}</div>
                                                        </td>
                                                        <td class="text-truncate" title="{{ $pi['warehouse_name'] }}">
                                                            {{ $pi['warehouse_name'] }}
                                                        </td>
                                                        <td class="text-end fw-semibold text-muted">
                                                            {{ (float) $pi['quantity_requested'] }} {{ $pi['uom'] }}
                                                        </td>
                                                        <td class="text-end text-success fw-semibold">
                                                            {{ (float) $pi['quantity_ordered'] }} {{ $pi['uom'] }}
                                                        </td>
                                                        <td class="text-end text-danger fw-semibold">
                                                            {{ (float) $pi['quantity_pending'] }} {{ $pi['uom'] }}
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="d-flex justify-content-center gap-1">
                                                                <button type="button" class="action-icon-btn po-btn btn-individual-po" data-item-id="{{ $pi['item_id'] }}" data-vendor-id="{{ $pi['vendor_id'] }}" data-vendor-name="{{ $pi['vendor_name'] }}" data-product-name="{{ $pi['product_name'] }}" data-quantity="{{ $pi['quantity_pending'] }}" data-uom="{{ $pi['uom'] }}" data-warehouse-id="{{ $pi['warehouse_id'] ?? '' }}" data-warehouse-name="{{ $pi['warehouse_name'] ?? '' }}" title="Convert to PO" data-bs-toggle="tooltip">
                                                                    <i class="feather feather-plus-circle"></i>
                                                                </button>
                                                                <a href="{{ route('purchase.rfqs.create', ['requisition_item_ids' => [$pi['item_id']]]) }}" class="action-icon-btn rfq-btn" title="Send RFQ" data-bs-toggle="tooltip">
                                                                    <i class="feather feather-mail"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </x-ui.odoo-form-ui>
                                    </div>
                                @endif
                            </div>

                            <!-- Pane 2: Unassigned Items (No Supplier) Table -->
                            <div class="tab-pane fade" id="unassigned-pane" role="tabpanel">
                                @if(empty($unassignedItems))
                                    <div class="text-center py-5 border rounded bg-light">
                                        <i class="feather-check-circle text-success fs-32 mb-2"></i>
                                        <h6 class="fw-bold">No unassigned items</h6>
                                        <p class="text-muted fs-12 mb-0">All pending items have a preferred supplier resolved!</p>
                                    </div>
                                @else
                                    <div class="table-responsive">
                                        <x-ui.odoo-form-ui type="table" id="unassignedTable">
                                            <thead>
                                                <tr>
                                                    <th style="width: 4%;" class="text-center">
                                                        <input type="checkbox" class="form-check-input select-all-pane" data-pane="unassigned-pane">
                                                    </th>
                                                    <th style="width: 22%;">Product Details</th>
                                                    <th style="width: 18%;">Supplier / Vendor</th>
                                                    <th style="width: 14%;">PR / Date</th>
                                                    <th style="width: 12%;">Warehouse</th>
                                                    <th class="text-end" style="width: 8%;">Req Qty</th>
                                                    <th class="text-end" style="width: 8%;">Ordered</th>
                                                    <th class="text-end" style="width: 8%;">Pending</th>
                                                    <th style="width: 12%;" class="text-center">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($unassignedItems as $pi)
                                                    <tr class="item-row">
                                                        <td class="text-center">
                                                            <input type="checkbox" name="item_ids[]" value="{{ $pi['item_id'] }}" class="form-check-input row-checkbox pane-checkbox-unassigned-pane" data-vendor-id="{{ $pi['vendor_id'] }}" data-vendor-name="{{ $pi['vendor_name'] }}" data-product-name="{{ $pi['product_name'] }}" data-quantity="{{ $pi['quantity_pending'] }}" data-uom="{{ $pi['uom'] }}" data-warehouse-id="{{ $pi['warehouse_id'] }}" data-warehouse-name="{{ $pi['warehouse_name'] }}">
                                                        </td>
                                                        <td>
                                                            <div class="fw-bold text-truncate text-dark" title="{{ $pi['product_name'] }}">{{ $pi['product_name'] }}</div>
                                                            <div class="text-muted fs-11">SKU: {{ $pi['sku'] }}</div>
                                                        </td>
                                                        <td class="text-muted italic">
                                                            — No Supplier —
                                                        </td>
                                                        <td>
                                                            <span class="fw-semibold text-dark">{{ $pi['requisition_number'] }}</span>
                                                            <div class="text-muted fs-11">{{ $pi['requisition_date'] ? \Carbon\Carbon::parse($pi['requisition_date'])->format('d-M-Y') : '—' }}</div>
                                                        </td>
                                                        <td class="text-truncate" title="{{ $pi['warehouse_name'] }}">
                                                            {{ $pi['warehouse_name'] }}
                                                        </td>
                                                        <td class="text-end fw-semibold text-muted">
                                                            {{ (float) $pi['quantity_requested'] }} {{ $pi['uom'] }}
                                                        </td>
                                                        <td class="text-end text-success fw-semibold">
                                                            {{ (float) $pi['quantity_ordered'] }} {{ $pi['uom'] }}
                                                        </td>
                                                        <td class="text-end text-danger fw-semibold">
                                                            {{ (float) $pi['quantity_pending'] }} {{ $pi['uom'] }}
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="d-flex justify-content-center gap-1">
                                                                <button type="button" class="action-icon-btn po-btn btn-individual-po" data-item-id="{{ $pi['item_id'] }}" data-vendor-id="{{ $pi['vendor_id'] }}" data-vendor-name="{{ $pi['vendor_name'] }}" data-product-name="{{ $pi['product_name'] }}" data-quantity="{{ $pi['quantity_pending'] }}" data-uom="{{ $pi['uom'] }}" data-warehouse-id="{{ $pi['warehouse_id'] ?? '' }}" data-warehouse-name="{{ $pi['warehouse_name'] ?? '' }}" title="Convert to PO" data-bs-toggle="tooltip">
                                                                    <i class="feather feather-plus-circle"></i>
                                                                </button>
                                                                <a href="{{ route('purchase.rfqs.create', ['requisition_item_ids' => [$pi['item_id']]]) }}" class="action-icon-btn rfq-btn" title="Send RFQ" data-bs-toggle="tooltip">
                                                                    <i class="feather feather-mail"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </x-ui.odoo-form-ui>
                                    </div>
                                @endif
                            </div>

                        </div>
                    @else
                        <!-- Unified single table for PR or Date grouping -->
                        @if(empty($pendingItems))
                            <div class="text-center py-5 border rounded bg-light">
                                <i class="feather-check-circle text-success fs-32 mb-2"></i>
                                <h6 class="fw-bold">No pending items</h6>
                                <p class="text-muted fs-12 mb-0">All items are fully ordered.</p>
                            </div>
                        @else
                            <div class="table-responsive">
                                <x-ui.odoo-form-ui type="table" id="generalTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 4%;" class="text-center">
                                                <input type="checkbox" id="selectAllGeneral" class="form-check-input">
                                            </th>
                                            <th style="width: 20%;">Product Details</th>
                                            <th style="width: 15%;">Supplier / Vendor</th>
                                            <th style="width: 13%;">PR / Date</th>
                                            <th style="width: 12%;">Warehouse</th>
                                            <th class="text-end" style="width: 8%;">Req Qty</th>
                                            <th class="text-end" style="width: 8%;">Ordered</th>
                                            <th class="text-end" style="width: 8%;">Pending</th>
                                            <th style="width: 12%;" class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($pendingItems as $pi)
                                            <tr class="item-row">
                                                <td class="text-center">
                                                    <input type="checkbox" name="item_ids[]" value="{{ $pi['item_id'] }}" class="form-check-input row-checkbox" data-vendor-id="{{ $pi['vendor_id'] }}" data-vendor-name="{{ $pi['vendor_name'] }}" data-product-name="{{ $pi['product_name'] }}" data-quantity="{{ $pi['quantity_pending'] }}" data-uom="{{ $pi['uom'] }}" data-warehouse-id="{{ $pi['warehouse_id'] }}" data-warehouse-name="{{ $pi['warehouse_name'] }}">
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-truncate text-dark" title="{{ $pi['product_name'] }}">{{ $pi['product_name'] }}</div>
                                                    <div class="text-muted fs-11">SKU: {{ $pi['sku'] }}</div>
                                                </td>
                                                <td class="fw-semibold text-primary text-truncate" title="{{ $pi['vendor_name'] }}">
                                                    {{ $pi['vendor_name'] ?: '— No Supplier —' }}
                                                </td>
                                                <td>
                                                    <span class="fw-semibold text-dark">{{ $pi['requisition_number'] }}</span>
                                                    <div class="text-muted fs-11">{{ $pi['requisition_date'] ? \Carbon\Carbon::parse($pi['requisition_date'])->format('d-M-Y') : '—' }}</div>
                                                </td>
                                                <td class="text-truncate" title="{{ $pi['warehouse_name'] }}">
                                                    {{ $pi['warehouse_name'] }}
                                                </td>
                                                <td class="text-end fw-semibold text-muted">
                                                    {{ (float) $pi['quantity_requested'] }} {{ $pi['uom'] }}
                                                </td>
                                                <td class="text-end text-success fw-semibold">
                                                    {{ (float) $pi['quantity_ordered'] }} {{ $pi['uom'] }}
                                                </td>
                                                <td class="text-end text-danger fw-semibold">
                                                    {{ (float) $pi['quantity_pending'] }} {{ $pi['uom'] }}
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center gap-1">
                                                        <button type="button" class="action-icon-btn po-btn btn-individual-po" data-item-id="{{ $pi['item_id'] }}" data-vendor-id="{{ $pi['vendor_id'] }}" data-vendor-name="{{ $pi['vendor_name'] }}" data-product-name="{{ $pi['product_name'] }}" data-quantity="{{ $pi['quantity_pending'] }}" data-uom="{{ $pi['uom'] }}" data-warehouse-id="{{ $pi['warehouse_id'] ?? '' }}" data-warehouse-name="{{ $pi['warehouse_name'] ?? '' }}" title="Convert to PO" data-bs-toggle="tooltip">
                                                            <i class="feather feather-plus-circle"></i>
                                                        </button>
                                                        <a href="{{ route('purchase.rfqs.create', ['requisition_item_ids' => [$pi['item_id']]]) }}" class="action-icon-btn rfq-btn" title="Send RFQ" data-bs-toggle="tooltip">
                                                            <i class="feather feather-mail"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </x-ui.odoo-form-ui>
                            </div>
                        @endif
                    @endif
                </form>

                <form id="postToCreatePoForm" action="{{ route('purchase.orders.create') }}" method="POST" style="display: none;">
                    @csrf
                    <input type="hidden" name="vendor_id" id="postPoVendorId">
                    <input type="hidden" name="warehouse_id" id="postPoWarehouseId">
                    <div id="postPoItemIdsContainer"></div>
                </form>

            </div>
        </div>
    </div>

    <!-- Choose Supplier Modal -->
    <x-ui.modal id="supplierSelectModal" title="Select Supplier for Purchase Order" size="lg" :centered="true" :static="true" :showFooter="true">
        <div class="text-dark">
            <div class="mb-4">
                <label class="form-label fw-semibold text-muted text-uppercase fs-12">Select Supplier / Vendor *</label>
                <select id="modalSupplierSelect" class="form-select form-select-sm fw-semibold text-dark p-2" style="font-size: 14px;">
                    <option value="">-- Choose Supplier --</option>
                    @foreach($vendors as $v)
                        <option value="{{ $v->id }}">{{ $v->name }} {{ $v->code ? '('.$v->code.')' : '' }}</option>
                    @endforeach
                </select>
                <small class="text-muted fs-11 mt-1 d-block">This Purchase Order will be generated against the selected supplier. Any selected items will be assigned to this supplier.</small>
            </div>

            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Selected PR Line Items to Include:</h6>
            <div class="table-responsive border rounded" style="max-height: 250px; overflow-y: auto;">
                <table class="table table-sm table-striped align-middle mb-0 fs-13">
                    <thead class="bg-light text-muted">
                        <tr>
                            <th>Product Details</th>
                            <th>Current Preferred Supplier</th>
                            <th class="text-end">Pending Qty</th>
                        </tr>
                    </thead>
                    <tbody id="modalSelectedItemsTable">
                        <!-- Populated dynamically by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>

        <x-slot name="footer">
            <x-ui.button type="button" variant="light" data-bs-dismiss="modal">Cancel</x-ui.button>
            <x-ui.button type="button" variant="primary" id="btnConfirmModalPo" icon="feather-plus-circle">
                Proceed to Create PO
            </x-ui.button>
        </x-slot>
    </x-ui.modal>
@endsection

@push('scripts')
    <script>
        function changeGroupBy(val) {
            const url = new URL(window.location.href);
            url.searchParams.set('group_by', val);
            window.location.href = url.toString();
        }

        $(document).ready(function() {
            // Initialize Select2 on the supplier select inside the modal
            $('#modalSupplierSelect').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#supplierSelectModal'),
                width: '100%'
            });

            // General select all (for PR / Date grouping)
            $('#selectAllGeneral').on('change', function() {
                const isChecked = $(this).prop('checked');
                $('.row-checkbox').prop('checked', isChecked);
            });

            $('.row-checkbox').on('change', function() {
                const total = $('.row-checkbox').length;
                const checked = $('.row-checkbox:checked').length;
                $('#selectAllGeneral').prop('checked', total === checked);
            });

            // Pane-specific select all (for Supplier tabs grouping)
            $('.select-all-pane').on('change', function() {
                const paneId = $(this).data('pane');
                const isChecked = $(this).prop('checked');
                $(`#${paneId} .row-checkbox`).prop('checked', isChecked);
            });

            $(document).on('change', '.row-checkbox', function() {
                const $pane = $(this).closest('.tab-pane');
                if ($pane.length) {
                    const paneId = $pane.attr('id');
                    const total = $(`#${paneId} .row-checkbox`).length;
                    const checked = $(`#${paneId} .row-checkbox:checked`).length;
                    $(`.select-all-pane[data-pane="${paneId}"]`).prop('checked', total === checked);
                }
            });

            // Handle bulk redirect execution
            $('.btn-submit-bulk').on('click', function() {
                const action = $(this).data('action'); // 'po' or 'rfq'
                const checkedCount = $('.row-checkbox:checked').length;
                
                if (checkedCount === 0) {
                    alert('Please select at least one item.');
                    return;
                }

                // Gather checked item IDs
                const selectedItems = $('.row-checkbox:checked').map(function() {
                    const $cb = $(this);
                    return {
                        id: $cb.val(),
                        vendorId: $cb.data('vendor-id'),
                        vendorName: $cb.data('vendor-name') || 'No Supplier Assigned',
                        productName: $cb.data('product-name'),
                        quantity: $cb.data('quantity'),
                        uom: $cb.data('uom'),
                        warehouseId: $cb.data('warehouse-id')
                    };
                }).get();

                const selectedIds = selectedItems.map(item => item.id);

                if (action === 'po') {
                    // For PO, open the supplier selection modal
                    // Find first item with an assigned vendor to pre-select
                    const firstVendorItem = selectedItems.find(item => item.vendorId);
                    const defaultVendorId = firstVendorItem ? firstVendorItem.vendorId : '';

                    // Find first item with a warehouse ID
                    const firstWarehouseItem = selectedItems.find(item => item.warehouseId);
                    const defaultWarehouseId = firstWarehouseItem ? firstWarehouseItem.warehouseId : '';
                    
                    $('#modalSupplierSelect').val(defaultVendorId).trigger('change');

                    let tableHtml = '';
                    selectedItems.forEach(item => {
                        tableHtml += `
                            <tr>
                                <td>
                                    <div class="fw-bold">${item.productName}</div>
                                </td>
                                <td class="text-primary fw-semibold">${item.vendorName}</td>
                                <td class="text-end fw-semibold">${item.quantity} ${item.uom}</td>
                            </tr>
                        `;
                    });
                    $('#modalSelectedItemsTable').html(tableHtml);

                    // Set selected IDs and default warehouse on the proceed button
                    $('#btnConfirmModalPo')
                        .data('selected-ids', selectedIds)
                        .data('warehouse-id', defaultWarehouseId);

                    // Open modal
                    $('#supplierSelectModal').modal('show');

                } else if (action === 'rfq') {
                    // For RFQ, proceed directly
                    let redirectUrl = '{{ route("purchase.rfqs.create") }}';
                    const url = new URL(redirectUrl);
                    selectedIds.forEach(id => url.searchParams.append('requisition_item_ids[]', id));
                    
                    if (confirm('Redirect to RFQ Create Form with ' + checkedCount + ' selected item(s)?')) {
                        window.location.href = url.toString();
                    }
                }
            });

            // Handle individual PO button click
            $(document).on('click', '.btn-individual-po', function(e) {
                e.preventDefault();
                const itemId = $(this).data('item-id');
                const vendorId = $(this).data('vendor-id');
                const warehouseId = $(this).data('warehouse-id');

                // Construct redirect URL
                let redirectUrl = '{{ route("purchase.orders.create") }}';
                const url = new URL(redirectUrl);
                url.searchParams.append('requisition_item_ids[]', itemId);
                if (vendorId) {
                    url.searchParams.append('vendor_id', vendorId);
                }
                if (warehouseId) {
                    url.searchParams.append('warehouse_id', warehouseId);
                }

                window.location.href = url.toString();
            });

            // Handle Modal confirmation
            $('#btnConfirmModalPo').on('click', function() {
                const vendorId = $('#modalSupplierSelect').val();
                if (!vendorId) {
                    alert('Please select a Supplier/Vendor.');
                    return;
                }

                const selectedIds = $(this).data('selected-ids');
                if (!selectedIds || selectedIds.length === 0) {
                    alert('No items selected.');
                    return;
                }

                const warehouseId = $(this).data('warehouse-id');

                // Populate and submit the hidden POST form
                $('#postPoVendorId').val(vendorId);
                $('#postPoWarehouseId').val(warehouseId || '');
                const $container = $('#postPoItemIdsContainer');
                $container.empty();
                
                selectedIds.forEach(id => {
                    $container.append(`<input type="hidden" name="requisition_item_ids[]" value="${id}">`);
                });

                // Hide modal and submit form
                $('#supplierSelectModal').modal('hide');
                $('#postToCreatePoForm').submit();
            });
        });
    </script>
@endpush
