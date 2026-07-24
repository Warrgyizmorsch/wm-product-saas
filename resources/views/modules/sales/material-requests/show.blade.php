@extends('layouts.duralux')

@section('title', 'Slip Details | SaaS ERP')
@section('page-title', 'Material Requisition Slip Details')
@section('breadcrumb')
    <a href="{{ route('sales.material-requests.index') }}">Material Requests</a> &gt; Details
@endsection

@push('styles')
    <style>
        .action-dropdown-btn {
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
        .action-dropdown-btn:hover {
            background-color: color-mix(in srgb, var(--bs-primary) 10%, transparent) !important;
            border-color: var(--bs-primary) !important;
            color: var(--bs-primary) !important;
        }
        
        /* Force Duralux theme white container style with border on select2 selection box */
        #bulkWarehouseSelect + .select2-container .select2-selection,
        #bulkActionType + .select2-container .select2-selection {
            background-color: #ffffff !important;
            border: 1.5px solid #cbd5e1 !important;
            border-radius: 8px !important;
            height: 38px !important;
            display: inline-flex !important;
            align-items: center !important;
        }
        #bulkWarehouseSelect + .select2-container .select2-selection__rendered,
        #bulkActionType + .select2-container .select2-selection__rendered {
            color: var(--bs-primary) !important;
            font-weight: 600 !important;
            padding-left: 12px !important;
        }
    </style>
@endpush

@section('page-actions')
    <div class="d-flex align-items-center gap-0">
        <a href="{{ route('sales.material-requests.index') }}" class="action-dropdown-btn me-2" title="Back to Slips" data-bs-toggle="tooltip">
            <i class="feather feather-arrow-left"></i>
        </a>
    </div>
@endsection

@section('content')
    <div class="erp-single-panel">
        <!-- Toast Notifications -->
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <x-ui.odoo-form-ui type="sheet">
            <!-- Header bar with title and status badge next to it -->
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pb-3 mb-4 border-bottom">
                <div>
                    <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1 letter-spacing-1">Material Requisition Slip</span>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h4 class="fw-bold text-dark mb-0">{{ $slip->requisition_number }}</h4>
                        @php
                            $statusClass = 'danger';
                            if ($slip->status === 'completed') $statusClass = 'success';
                            elseif ($slip->status === 'partial') $statusClass = 'warning';
                        @endphp
                        <x-ui.badge :soft="true" :variant="$statusClass" class="px-2.5 py-1 fs-11 fw-bold">
                            {{ $slip->status === 'completed' ? 'Fully Issued' : ($slip->status === 'partial' ? 'Partially Issued' : 'Pending Issue') }}
                        </x-ui.badge>
                    </div>
                    <span class="fs-13 text-muted">
                        Generated on:&nbsp;<strong class="text-dark">{{ date('d-M-Y', strtotime($slip->requisition_date)) }}</strong>
                    </span>
                </div>
            </div>

            <!-- Metadata Row -->
            <div class="row g-3 mb-4 fs-13 text-dark pb-3 border-bottom">
                <div class="col-md-3">
                    <span class="text-muted d-block fs-11 text-uppercase fw-bold mb-1">Production Order</span>
                    <strong class="fs-14 font-monospace text-primary">{{ $slip->order->order_number ?? 'MO #' . $slip->production_order_id }}</strong>
                </div>
                <div class="col-md-6">
                    <span class="text-muted d-block fs-11 text-uppercase fw-bold mb-1">Target Product (to Manufacture)</span>
                    <strong>{{ $slip->order->product->name ?? '—' }} ({{ $slip->order->product->sku ?? '—' }})</strong>
                </div>
                <div class="col-md-3">
                    <span class="text-muted d-block fs-11 text-uppercase fw-bold mb-1">Qty Ordered</span>
                    <strong class="fs-14 font-monospace">{{ (float) ($slip->order->quantity_ordered ?? 0.0) }}</strong>
                </div>
            </div>

            <!-- Items Table -->
            <h5 class="fw-bold text-dark mb-3"><i class="feather-layers text-primary me-2"></i>Requested Components &amp; Raw Materials</h5>
            
            <!-- Bulk Actions Control Bar -->
            <div class="card border p-3 mb-4 bg-light">
                <div class="row align-items-center g-3">
                    <div class="col-md-3">
                        <label class="form-label fs-11 fw-bold text-muted mb-1 text-uppercase">1. Choose Bulk Action Type</label>
                        <select id="bulkActionType" name="action_type" class="form-select form-select-sm" data-select2-selector="default" required style="width: 100%;">
                            <option value="" selected>-- Select Bulk Action --</option>
                            <option value="reserve">Reserve Stock</option>
                            <option value="issue">Issue Stock</option>
                            <option value="indent">Create Indent (Procurement)</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fs-11 fw-bold text-muted mb-1 text-uppercase" id="warehouseLabel">2. Select Warehouse</label>
                        <select id="bulkWarehouseSelect" name="warehouse_id" class="form-select form-select-sm" data-select2-selector="default" data-master="warehouse" style="width: 100%;">
                            <option value="">Select Warehouse...</option>
                            <option value="__ADD_NEW__" class="fw-bold text-primary" data-master="warehouse">+ Add New Warehouse</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" @selected($wh->is_default)>
                                    {{ $wh->name }} {{ $wh->is_default ? '(Default)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 id-notes-field d-none">
                        <label class="form-label fs-11 fw-bold text-muted mb-1 text-uppercase" id="notesLabel">Remarks / Notes</label>
                        <input type="text" id="bulkRemarksNotes" name="remarks" class="form-control form-control-sm" placeholder="e.g. Bulk action remarks...">
                    </div>

                    <div class="col-md-2 d-flex align-items-end justify-content-end ms-auto">
                        <button type="button" id="executeBulkActionBtn" class="btn btn-sm btn-primary w-100 py-1.5 fw-semibold d-none">
                            <i class="feather-check-square me-1"></i>Execute Action
                        </button>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered align-middle text-dark mb-0 fs-13">
                    <thead class="bg-soft-light text-uppercase fs-11 fw-semibold text-muted">
                        <tr>
                            <th class="text-center" style="width: 5%">
                                <input type="checkbox" id="selectAllCheckbox" class="form-check-input" disabled>
                            </th>
                            <th style="width: 30%">Component Product</th>
                            <th class="text-center" style="width: 12%">Planned Qty</th>
                            <th class="text-center" style="width: 12%">Reserved Qty</th>
                            <th class="text-center" style="width: 12%">Issued Qty</th>
                            <th class="text-center" style="width: 16%">Available Stock</th>
                            <th style="width: 13%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            @php
                                $remainingToIssue = max(0.0, $item->quantity_planned - $item->quantity_issued);
                                $remainingToReserve = max(0.0, $item->quantity_planned - ($item->quantity_issued + $item->quantity_reserved));
                                $totalAvailableStock = \App\Domains\Inventory\Models\ProductWarehouseStock::where('tenant_id', $slip->tenant_id)
                                    ->where('product_id', $item->product_id)
                                    ->sum('available_qty');
                                $shortageQty = max(0.0, $remainingToIssue - ($item->quantity_reserved + $totalAvailableStock));
                                
                                // Warehouse stocks mapping
                                $stocksMap = [];
                                foreach($warehouses as $wh) {
                                    $stocksMap[$wh->id] = (float) \App\Domains\Inventory\Services\StockService::getAvailableStock($item->product_id, $wh->id);
                                }
                                $prWarehouseIds = $existingPrItems->where('product_id', $item->product_id)->pluck('warehouse_id')->toArray();
                            @endphp
                            <tr data-item-id="{{ $item->id }}" data-stocks="{{ json_encode($stocksMap) }}" data-planned="{{ $item->quantity_planned }}" data-reserved="{{ $item->quantity_reserved }}" data-issued="{{ $item->quantity_issued }}" data-pr-raised-warehouses="{{ json_encode($prWarehouseIds) }}">
                                <td class="text-center">
                                    <input type="checkbox" name="item_ids[]" value="{{ $item->id }}" class="form-check-input item-checkbox" disabled>
                                </td>
                                <td>
                                    <div class="fw-bold">{{ $item->product->name }}</div>
                                    <div class="text-muted fs-11">SKU: {{ $item->product->sku }} | Type: {{ ucfirst(str_replace('_', ' ', $item->product->type)) }}</div>
                                </td>
                                <td class="text-center fw-semibold">{{ (float) $item->quantity_planned }} {{ $item->uom->code }}</td>
                                <td class="text-center text-primary fw-semibold">{{ (float) $item->quantity_reserved }} {{ $item->uom->code }}</td>
                                <td class="text-center text-success fw-bold">{{ (float) $item->quantity_issued }} {{ $item->uom->code }}</td>
                                <td class="text-center text-muted fw-semibold">
                                    <span id="avail-stock-label-{{ $item->id }}">{{ (float) $totalAvailableStock }}</span> {{ $item->uom->code }}
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        @if($remainingToReserve > 0)
                                            <button type="button" class="btn btn-sm btn-soft-primary px-2 py-1 fs-11 fw-semibold w-100 text-start btn-row-reserve" data-bs-toggle="modal" data-bs-target="#reserveModal-{{ $item->id }}">
                                                <i class="feather-archive me-1"></i>Reserve
                                            </button>
                                        @endif

                                        @if($item->quantity_reserved > 0 || $remainingToIssue > 0)
                                            <button type="button" class="btn btn-sm btn-soft-success px-2 py-1 fs-11 fw-semibold w-100 text-start btn-row-issue" data-bs-toggle="modal" data-bs-target="#issueModal-{{ $item->id }}">
                                                <i class="feather-check-circle me-1"></i>Issue
                                            </button>
                                        @endif

                                        @if($remainingToIssue > 0)
                                            <span class="badge bg-warning-soft text-warning px-2 py-1 fs-12 w-100 text-center badge-row-pr-raised">
                                                <i class="feather-clock me-1"></i>PR Raised
                                            </span>
                                            <button type="button" class="btn btn-sm btn-soft-danger px-2 py-1 fs-11 fw-semibold w-100 text-start btn-row-indent" data-bs-toggle="modal" data-bs-target="#shortageModal-{{ $item->id }}">
                                                <i class="feather-shopping-cart me-1"></i>Create Indent
                                            </button>
                                        @endif

                                        @if($remainingToIssue <= 0)
                                            <span class="text-success fs-12 fw-bold"><i class="feather-check-circle me-1"></i> Fully Issued</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.odoo-form-ui>
    </div>

    <!-- Modals outside the card block using common x-ui.modal element -->
    @foreach($items as $item)
        @php
            $remainingToIssue = max(0.0, $item->quantity_planned - $item->quantity_issued);
            $remainingToReserve = max(0.0, $item->quantity_planned - ($item->quantity_issued + $item->quantity_reserved));
            $totalAvailableStock = \App\Domains\Inventory\Models\ProductWarehouseStock::where('tenant_id', $slip->tenant_id)
                ->where('product_id', $item->product_id)
                ->sum('available_qty');
            $shortageQty = max(0.0, $remainingToIssue - ($item->quantity_reserved + $totalAvailableStock));
        @endphp

        <!-- Reserve Modal -->
        @if($remainingToReserve > 0 && $totalAvailableStock > 0)
            <x-ui.modal
                id="reserveModal-{{ $item->id }}"
                title="Reserve Stock — {{ $item->product->name }}"
                submitText="Confirm Reservation"
                formAction="{{ route('sales.material-requests.reserve', $item->id) }}"
                :centered="true"
            >
                <div class="fs-13 text-dark">
                    <div class="d-flex align-items-center gap-3 p-3 bg-light rounded border mb-4">
                        <div class="avatar-text avatar-md bg-soft-primary text-primary">
                            <i class="feather-package"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-0">{{ $item->product->name }}</h6>
                            <small class="text-muted font-monospace">SKU: {{ $item->product->sku }} | Planned: {{ (float) $item->quantity_planned }}</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-11 fw-bold mb-1 text-muted">Select Warehouse</label>
                        <select class="form-select form-select-sm reserve-warehouse-select" data-item-id="{{ $item->id }}" data-remaining="{{ $remainingToReserve }}" name="warehouse_id" onchange="updateReserveQtyLimit({{ $item->id }}, this, {{ $remainingToReserve }})">
                            @foreach($warehouses as $wh)
                                @php
                                    $whAvail = \App\Domains\Inventory\Services\StockService::getAvailableStock($item->product_id, $wh->id);
                                @endphp
                                <option value="{{ $wh->id }}" data-avail="{{ $whAvail }}">
                                    {{ $wh->name }} (Available: {{ (float)$whAvail }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-11 fw-bold mb-1 text-muted">Qty to Reserve (Max: <span id="reserve-max-label-{{ $item->id }}" class="fw-bold text-dark">0</span>)</label>
                        <input type="number" id="reserve-qty-input-{{ $item->id }}" name="quantity" class="form-control" step="0.0001" min="0.0001" required>
                    </div>
                </div>
            </x-ui.modal>
        @endif

        <!-- Issue Modal -->
        @if($item->quantity_reserved > 0 || ($remainingToIssue > 0 && $totalAvailableStock > 0))
            <x-ui.modal
                id="issueModal-{{ $item->id }}"
                title="Issue Stock — {{ $item->product->name }}"
                submitText="Confirm Issue"
                formAction="{{ route('sales.material-requests.issue', $item->id) }}"
                :centered="true"
            >
                <div class="fs-13 text-dark">
                    <div class="d-flex align-items-center gap-3 p-3 bg-light rounded border mb-4">
                        <div class="avatar-text avatar-md bg-soft-success text-success">
                            <i class="feather-check-circle"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-0">{{ $item->product->name }}</h6>
                            <small class="text-muted font-monospace">SKU: {{ $item->product->sku }} | Reserved: {{ (float) $item->quantity_reserved }} | Planned: {{ (float) $item->quantity_planned }}</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-11 fw-bold mb-1 text-muted">Select Warehouse</label>
                        <select class="form-select form-select-sm issue-warehouse-select" data-item-id="{{ $item->id }}" data-remaining-issue="{{ $remainingToIssue }}" data-reserved="{{ $item->quantity_reserved }}" name="warehouse_id" onchange="updateIssueQtyLimit({{ $item->id }}, this, {{ $remainingToIssue }}, {{ $item->quantity_reserved }})">
                            @foreach($warehouses as $wh)
                                @php
                                    $whAvail = \App\Domains\Inventory\Services\StockService::getAvailableStock($item->product_id, $wh->id);
                                @endphp
                                <option value="{{ $wh->id }}" data-avail="{{ $whAvail }}" {{ $item->warehouse_id == $wh->id ? 'selected' : '' }}>
                                    {{ $wh->name }} (Available: {{ (float)$whAvail }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-11 fw-bold mb-1 text-muted">Qty to Issue (Max: <span id="issue-max-label-{{ $item->id }}" class="fw-bold text-dark">0</span>)</label>
                        <input type="number" id="issue-qty-input-{{ $item->id }}" name="quantity" class="form-control" step="0.0001" min="0.0001" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-11 fw-bold mb-1 text-muted">Remarks</label>
                        <input type="text" name="remarks" class="form-control" placeholder="e.g. Issued to shop floor">
                    </div>
                </div>
            </x-ui.modal>
        @endif

        <!-- Shortage Modal (Indent) -->
        @if($remainingToIssue > 0)
            <x-ui.modal
                id="shortageModal-{{ $item->id }}"
                title="Create Indent — {{ $item->product->name }}"
                submitText="Raise Purchase Requisition"
                formAction="{{ route('sales.material-requests.create-pr', $item->id) }}"
                :centered="true"
            >
                <div class="fs-13 text-dark">
                    <div class="d-flex align-items-center gap-3 p-3 bg-light rounded border mb-4">
                        <div class="avatar-text avatar-md bg-soft-danger text-danger">
                            <i class="feather-shopping-cart"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-0">{{ $item->product->name }}</h6>
                            <small class="text-muted font-monospace">SKU: {{ $item->product->sku }} | Shortage: <span class="shortage-badge-val-{{ $item->id }}">{{ $shortageQty }}</span></small>
                        </div>
                    </div>

                    <p class="mb-3">This will generate a Draft Purchase Requisition for the shortage quantity of <strong><span class="shortage-text-val-{{ $item->id }}">{{ $shortageQty }}</span> {{ $item->uom->code }}</strong>.</p>

                    <div class="mb-3">
                        <label class="form-label fs-11 fw-bold mb-1 text-muted">Destination Warehouse <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm shortage-warehouse-select" name="warehouse_id" required>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" @selected($wh->id == $item->warehouse_id)>
                                    {{ $wh->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="text-muted fs-11 mt-1">Select the target warehouse for procurement.</div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fs-11 fw-bold mb-1 text-muted">Notes</label>
                        <textarea class="form-control form-control-sm" name="notes" rows="2" placeholder="e.g. Urgent shortage for MO"></textarea>
                    </div>
                </div>
            </x-ui.modal>
        @endif
    @endforeach

    <!-- Confirm Bulk Action Modal -->
    <x-ui.modal
        id="confirmBulkActionModal"
        title="Confirm Bulk Action"
        submitText="Confirm & Execute"
        formAction="{{ route('sales.material-requests.bulk-action', $slip->id) }}"
        :centered="true"
        size="lg"
    >
        <div class="fs-13 text-dark">
            <!-- Hidden inputs dynamically injected by Javascript -->
            <div id="bulk-hidden-fields"></div>

            <div class="alert alert-info border py-2 mb-3">
                <i class="feather-info me-1"></i> Review and adjust quantities for the selected items below before executing the action.
            </div>

            <div class="table-responsive shadow-sm border rounded" style="max-height: 350px; overflow-y: auto; overflow-x: hidden !important;">
                <table class="table table-bordered table-sm align-middle fs-13 mb-0">
                    <thead class="bg-light fw-bold text-muted">
                        <tr>
                            <th>Product</th>
                            <th class="text-center" style="width: 25%">Stock</th>
                            <th class="text-center" style="width: 30%">Qty to Action</th>
                        </tr>
                    </thead>
                    <tbody id="confirm-items-tbody">
                        <!-- Dynamically populated by JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </x-ui.modal>

    <x-ui.master-modals :masters="['warehouse']" />
@endsection

@push('scripts')
    <script>
        function updateReserveQtyLimit(itemId, select, remainingToReserve) {
            if (!select || select.selectedIndex === -1) return;
            const selectedOption = select.options[select.selectedIndex];
            if (!selectedOption) return;
            const avail = parseFloat(selectedOption.getAttribute('data-avail')) || 0.0;
            
            // Limit reserve quantity: min of remaining to reserve and warehouse available stock
            const maxVal = Math.min(remainingToReserve, avail);
            
            const $input = $(`#reserve-qty-input-${itemId}`);
            $input.attr('max', maxVal);
            $input.val(maxVal > 0 ? maxVal : '');
            $(`#reserve-max-label-${itemId}`).text(maxVal);
        }

        function updateIssueQtyLimit(itemId, select, remainingToIssue, reservedQty) {
            if (!select || select.selectedIndex === -1) return;
            const selectedOption = select.options[select.selectedIndex];
            if (!selectedOption) return;
            const avail = parseFloat(selectedOption.getAttribute('data-avail')) || 0.0;
            
            // Limit issue quantity: min of remaining to issue and (reserved stock + warehouse available stock)
            const maxVal = Math.min(remainingToIssue, reservedQty + avail);
            
            const $input = $(`#issue-qty-input-${itemId}`);
            $input.attr('max', maxVal);
            $input.val(maxVal > 0 ? maxVal : '');
            $(`#issue-max-label-${itemId}`).text(maxVal);
        }

        $(document).ready(function () {
            $('.reserve-warehouse-select').each(function () {
                const itemId = $(this).data('item-id');
                const remaining = parseFloat($(this).data('remaining')) || 0.0;
                updateReserveQtyLimit(itemId, this, remaining);
            });

            $('.issue-warehouse-select').each(function () {
                const itemId = $(this).data('item-id');
                const remaining = parseFloat($(this).data('remaining-issue')) || 0.0;
                const reserved = parseFloat($(this).data('reserved')) || 0.0;
                updateIssueQtyLimit(itemId, this, remaining, reserved);
            });

            // Advanced Grid-based Bulk Action Logic
            const $selectAll = $('#selectAllCheckbox');
            const $bulkActionType = $('#bulkActionType');
            const $bulkWarehouseSelect = $('#bulkWarehouseSelect');
            const $executeBtn = $('#executeBulkActionBtn');

            function updateModalShortageQty(itemId) {
                const $row = $(`tr[data-item-id="${itemId}"]`);
                const planned = parseFloat($row.data('planned')) || 0.0;
                const reserved = parseFloat($row.data('reserved')) || 0.0;
                const issued = parseFloat($row.data('issued')) || 0.0;
                const stocks = $row.data('stocks') || {};
                
                const modalWarehouseId = $(`#shortageModal-${itemId} select[name="warehouse_id"]`).val();
                const warehouseStock = parseFloat(stocks[modalWarehouseId]) || 0.0;
                
                const remainingToIssue = Math.max(0.0, planned - issued);
                const shortageQty = Math.max(0.0, remainingToIssue - (reserved + warehouseStock));
                
                $(`.shortage-badge-val-${itemId}`).text(shortageQty);
                $(`.shortage-text-val-${itemId}`).text(shortageQty);
            }

            $(document).on('change', 'div[id^="shortageModal-"] select[name="warehouse_id"]', function() {
                const itemId = $(this).closest('.modal').attr('id').split('-')[1];
                updateModalShortageQty(itemId);
            });

            function updateRowStockAndActions($row, warehouseId) {
                const itemId = $row.data('item-id');
                const planned = parseFloat($row.data('planned')) || 0.0;
                const reserved = parseFloat($row.data('reserved')) || 0.0;
                const issued = parseFloat($row.data('issued')) || 0.0;
                const stocks = $row.data('stocks') || {};
                
                const warehouseStock = parseFloat(stocks[warehouseId]) || 0.0;

                const remainingToIssue = Math.max(0.0, planned - issued);
                const remainingToReserve = Math.max(0.0, planned - (issued + reserved));
                const shortageQty = Math.max(0.0, remainingToIssue - (reserved + warehouseStock));

                // Dynamically update Available Stock label in the row
                if (warehouseId) {
                    $(`#avail-stock-label-${itemId}`).text(warehouseStock);
                } else {
                    $(`#avail-stock-label-${itemId}`).text(0.0);
                }

                // Toggle Reserve Button
                if (remainingToReserve > 0 && warehouseStock > 0) {
                    $row.find('.btn-row-reserve').show();
                } else {
                    $row.find('.btn-row-reserve').hide();
                }

                // Toggle Issue Button
                if (reserved > 0 || (remainingToIssue > 0 && warehouseStock > 0)) {
                    $row.find('.btn-row-issue').show();
                } else {
                    $row.find('.btn-row-issue').hide();
                }

                // Toggle Indent Button / PR Raised badge
                const prRaisedWarehouses = $row.data('pr-raised-warehouses') || [];
                const isPrRaisedForSelectedWarehouse = prRaisedWarehouses.includes(parseInt(warehouseId)) || prRaisedWarehouses.includes(String(warehouseId));

                if (remainingToIssue > 0) {
                    if (isPrRaisedForSelectedWarehouse) {
                        $row.find('.btn-row-indent').hide();
                        $row.find('.badge-row-pr-raised').show();
                    } else if (shortageQty > 0) {
                        $row.find('.btn-row-indent').show();
                        $row.find('.badge-row-pr-raised').hide();
                    } else {
                        $row.find('.btn-row-indent').hide();
                        $row.find('.badge-row-pr-raised').hide();
                    }
                } else {
                    $row.find('.btn-row-indent').hide();
                    $row.find('.badge-row-pr-raised').hide();
                }

                // Sync the individual modals' warehouse select to match the top selected warehouse
                if (warehouseId) {
                    const $resSel = $(`#reserveModal-${itemId} select.reserve-warehouse-select`);
                    if ($resSel.length) {
                        $resSel.val(warehouseId).prop('disabled', true);
                        let $hidden = $(`#reserveModal-${itemId} input[type="hidden"][name="warehouse_id"]`);
                        if (!$hidden.length) {
                            $resSel.after($('<input>', { type: 'hidden', name: 'warehouse_id', value: warehouseId }));
                        } else {
                            $hidden.val(warehouseId);
                        }
                        updateReserveQtyLimit(itemId, $resSel[0], remainingToReserve);
                    }
                    const $issSel = $(`#issueModal-${itemId} select.issue-warehouse-select`);
                    if ($issSel.length) {
                        $issSel.val(warehouseId).prop('disabled', true);
                        let $hidden = $(`#issueModal-${itemId} input[type="hidden"][name="warehouse_id"]`);
                        if (!$hidden.length) {
                            $issSel.after($('<input>', { type: 'hidden', name: 'warehouse_id', value: warehouseId }));
                        } else {
                            $hidden.val(warehouseId);
                        }
                        updateIssueQtyLimit(itemId, $issSel[0], remainingToIssue, reserved);
                    }
                    const $shSel = $(`#shortageModal-${itemId} select.shortage-warehouse-select`);
                    if ($shSel.length) {
                        $shSel.val(warehouseId).prop('disabled', true);
                        let $hidden = $(`#shortageModal-${itemId} input[type="hidden"][name="warehouse_id"]`);
                        if (!$hidden.length) {
                            $shSel.after($('<input>', { type: 'hidden', name: 'warehouse_id', value: warehouseId }));
                        } else {
                            $hidden.val(warehouseId);
                        }
                        updateModalShortageQty(itemId);
                    }
                }

                return {
                    planned,
                    reserved,
                    issued,
                    warehouseStock,
                    remainingToIssue,
                    remainingToReserve,
                    shortageQty
                };
            }

            function updateGridStates() {
                const action = $bulkActionType.val();
                const warehouseId = $bulkWarehouseSelect.val();

                if (warehouseId === '__ADD_NEW__') {
                    return;
                }

                if (!action) {
                    $('.id-notes-field').addClass('d-none');
                    $executeBtn.addClass('d-none');
                    disableAllGridRows();
                    return;
                }

                $executeBtn.removeClass('d-none');

                if (action === 'reserve') {
                    $('#warehouseLabel').text('2. Select Warehouse');
                    $('.id-notes-field').addClass('d-none');
                    $bulkWarehouseSelect.prop('required', true);
                } else if (action === 'issue') {
                    $('#warehouseLabel').text('2. Select Warehouse');
                    $('.id-notes-field').removeClass('d-none');
                    $('#notesLabel').text('Remarks');
                    $('#bulkRemarksNotes').attr('placeholder', 'e.g. Bulk issued to production line');
                    $bulkWarehouseSelect.prop('required', true);
                } else if (action === 'indent') {
                    $('#warehouseLabel').text('2. Destination Warehouse');
                    $('.id-notes-field').removeClass('d-none');
                    $('#notesLabel').text('Notes');
                    $('#bulkRemarksNotes').attr('placeholder', 'e.g. Consolidated shortages for MO');
                    $bulkWarehouseSelect.prop('required', true);
                }

                if (action !== 'indent' && !warehouseId) {
                    disableAllGridRows();
                    return;
                }

                let eligibleRowsCount = 0;

                $('table tbody tr[data-item-id]').each(function () {
                    const $row = $(this);
                    const metrics = updateRowStockAndActions($row, warehouseId);

                    let isEligible = false;
                    let maxVal = 0.0;

                    if (action === 'reserve') {
                        maxVal = Math.min(metrics.remainingToReserve, metrics.warehouseStock);
                        if (maxVal > 0.0001) {
                            isEligible = true;
                        }
                    } else if (action === 'issue') {
                        maxVal = Math.min(metrics.remainingToIssue, metrics.reserved + metrics.warehouseStock);
                        if (maxVal > 0.0001) {
                            isEligible = true;
                        }
                    } else if (action === 'indent') {
                        const prRaisedWarehouses = $row.data('pr-raised-warehouses') || [];
                        const isPrRaisedForSelectedWarehouse = prRaisedWarehouses.includes(parseInt(warehouseId)) || prRaisedWarehouses.includes(String(warehouseId));
                        if (isPrRaisedForSelectedWarehouse) {
                            isEligible = false;
                        } else {
                            maxVal = metrics.shortageQty;
                            if (maxVal > 0.0001) {
                                isEligible = true;
                            }
                        }
                    }

                    const $checkbox = $row.find('.item-checkbox');

                    if (isEligible) {
                        $checkbox.prop('disabled', false);
                        eligibleRowsCount++;
                    } else {
                        $checkbox.prop('disabled', true).prop('checked', false);
                    }
                });

                if (eligibleRowsCount > 0) {
                    $selectAll.prop('disabled', false);
                } else {
                    $selectAll.prop('disabled', true).prop('checked', false);
                }

                updateSelectAllState();
            }

            function disableAllGridRows() {
                $('.item-checkbox').prop('disabled', true).prop('checked', false);
                $selectAll.prop('disabled', true).prop('checked', false);
                
                const warehouseId = $bulkWarehouseSelect.val();
                
                $('table tbody tr[data-item-id]').each(function () {
                    updateRowStockAndActions($(this), warehouseId);
                });
            }

            function updateSelectAllState() {
                const $enabledCheckboxes = $('.item-checkbox:not(:disabled)');
                if ($enabledCheckboxes.length > 0) {
                    const allChecked = $enabledCheckboxes.length === $enabledCheckboxes.filter(':checked').length;
                    $selectAll.prop('checked', allChecked);
                } else {
                    $selectAll.prop('checked', false);
                }
            }

            $bulkActionType.on('change', function () {
                updateGridStates();
            });

            $bulkWarehouseSelect.on('change', updateGridStates);

            $selectAll.on('change', function () {
                $('.item-checkbox:not(:disabled)').prop('checked', this.checked);
            });

            $(document).on('change', '.item-checkbox', updateSelectAllState);

            // Execute button with Toast notifications and confirmation modal population
            $executeBtn.on('click', function () {
                const action = $bulkActionType.val();
                const warehouseId = $bulkWarehouseSelect.val();
                const remarks = $('#bulkRemarksNotes').val();

                if (typeof Swal === 'undefined') {
                    alert('Swal library is missing!');
                    return;
                }

                const toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });

                if (!action) {
                    toast.fire({
                        icon: 'error',
                        title: 'Choose bulk action type first'
                    });
                    return;
                }

                if (action !== 'indent' && !warehouseId) {
                    toast.fire({
                        icon: 'error',
                        title: 'Choose warehouse first'
                    });
                    return;
                }

                const $checked = $('.item-checkbox:checked');
                if ($checked.length === 0) {
                    toast.fire({
                        icon: 'error',
                        title: 'Please select at least one item'
                    });
                    return;
                }

                // If valid, populate and launch the Confirm Modal
                const $hiddenFields = $('#bulk-hidden-fields');
                $hiddenFields.empty();

                // Append main parameters
                $hiddenFields.append($('<input>', { type: 'hidden', name: 'action_type', value: action }));
                if (warehouseId) {
                    $hiddenFields.append($('<input>', { type: 'hidden', name: 'warehouse_id', value: warehouseId }));
                }
                if (action === 'issue') {
                    $hiddenFields.append($('<input>', { type: 'hidden', name: 'remarks', value: remarks }));
                } else if (action === 'indent') {
                    $hiddenFields.append($('<input>', { type: 'hidden', name: 'notes', value: remarks }));
                }

                const $tbody = $('#confirm-items-tbody');
                $tbody.empty();

                let actionLabel = 'Process';
                if (action === 'reserve') actionLabel = 'Reserve';
                if (action === 'issue') actionLabel = 'Issue';
                if (action === 'indent') actionLabel = 'Indent';

                $('#confirmBulkActionModalLabel').text(`Confirm Bulk ${actionLabel}`);

                $checked.each(function () {
                    const $row = $(this).closest('tr');
                    const itemId = $row.data('item-id');
                    const productName = $row.find('td:nth-child(2) div.fw-bold').text();
                    const sku = $row.find('td:nth-child(2) div.text-muted').text();
                    const stocks = $row.data('stocks') || {};
                    const warehouseStock = parseFloat(stocks[warehouseId]) || 0.0;
                    
                    const planned = parseFloat($row.data('planned')) || 0.0;
                    const reserved = parseFloat($row.data('reserved')) || 0.0;
                    const issued = parseFloat($row.data('issued')) || 0.0;
                    
                    const remainingToIssue = Math.max(0.0, planned - issued);
                    const remainingToReserve = Math.max(0.0, planned - (issued + reserved));
                    const shortageQty = Math.max(0.0, remainingToIssue - (reserved + warehouseStock));

                    let maxVal = 0.0;
                    let defaultQty = 0.0;

                    if (action === 'reserve') {
                        maxVal = Math.min(remainingToReserve, warehouseStock);
                        defaultQty = maxVal;
                    } else if (action === 'issue') {
                        maxVal = Math.min(remainingToIssue, reserved + warehouseStock);
                        defaultQty = maxVal;
                    } else if (action === 'indent') {
                        maxVal = shortageQty;
                        defaultQty = maxVal;
                    }

                    // Add hidden item ID input
                    $hiddenFields.append($('<input>', { type: 'hidden', name: 'item_ids[]', value: itemId }));

                    // Build table row
                    const trHtml = `
                        <tr>
                            <td>
                                <div class="fw-bold">${productName}</div>
                                <small class="text-muted font-monospace">${sku}</small>
                            </td>
                            <td class="text-center font-monospace">${action === 'indent' ? '—' : warehouseStock}</td>
                            <td class="text-center">
                                <input type="number" name="action_qtys[${itemId}]" class="form-control form-control-sm text-center fw-semibold mx-auto" style="max-width: 100px;" step="0.0001" min="0.0001" max="${maxVal}" value="${defaultQty}" required>
                            </td>
                        </tr>
                    `;
                    $tbody.append(trHtml);
                });

                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('confirmBulkActionModal'));
                modal.show();
            });

            // Trigger grid update on ready
            updateGridStates();
        });
    </script>
@endpush
