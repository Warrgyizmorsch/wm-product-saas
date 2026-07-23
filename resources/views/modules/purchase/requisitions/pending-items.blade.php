@extends('layouts.duralux')

@section('title', 'Pending PR Items | SaaS ERP')
@section('page-title', 'Pending Requisition Items')
@section('breadcrumb', 'Purchase / Pending Requisitions')

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
                            <button type="button" class="btn btn-sm btn-primary d-flex align-items-center gap-2 btn-submit-bulk" data-action="po" style="background-color: #714B67; border-color: #714B67;">
                                <i class="feather-plus-circle"></i>
                                <span>Create Bulk POs</span>
                            </button>
                            <button type="button" class="btn btn-sm btn-info text-white d-flex align-items-center gap-2 btn-submit-bulk" data-action="rfq" style="background-color: #10b981; border-color: #10b981;">
                                <i class="feather-mail"></i>
                                <span>Create Bulk RFQs</span>
                            </button>
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
                                    <div class="table-responsive border rounded shadow-sm">
                                        <table class="table table-bordered table-sm align-middle fs-13 mb-0" style="table-layout: fixed; width: 100%;">
                                            <thead class="bg-light fw-semibold text-muted text-uppercase">
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
                                                            <input type="checkbox" name="item_ids[]" value="{{ $pi['item_id'] }}" class="form-check-input row-checkbox pane-checkbox-assigned-pane">
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
                                                                <a href="{{ route('purchase.orders.create', ['requisition_item_ids' => [$pi['item_id']]]) }}" class="btn btn-xs btn-primary py-1 px-2 d-flex align-items-center gap-1" style="background-color: #714B67; border-color: #714B67;" title="Convert to PO">
                                                                    <i class="feather-plus-circle"></i> PO
                                                                </a>
                                                                <a href="{{ route('purchase.rfqs.create', ['requisition_item_ids' => [$pi['item_id']]]) }}" class="btn btn-xs btn-outline-secondary py-1 px-2 d-flex align-items-center gap-1" title="Send RFQ">
                                                                    <i class="feather-mail"></i> RFQ
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
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
                                    <div class="table-responsive border rounded shadow-sm">
                                        <table class="table table-bordered table-sm align-middle fs-13 mb-0" style="table-layout: fixed; width: 100%;">
                                            <thead class="bg-light fw-semibold text-muted text-uppercase">
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
                                                            <input type="checkbox" name="item_ids[]" value="{{ $pi['item_id'] }}" class="form-check-input row-checkbox pane-checkbox-unassigned-pane">
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
                                                                <a href="{{ route('purchase.orders.create', ['requisition_item_ids' => [$pi['item_id']]]) }}" class="btn btn-xs btn-primary py-1 px-2 d-flex align-items-center gap-1" style="background-color: #714B67; border-color: #714B67;" title="Convert to PO">
                                                                    <i class="feather-plus-circle"></i> PO
                                                                </a>
                                                                <a href="{{ route('purchase.rfqs.create', ['requisition_item_ids' => [$pi['item_id']]]) }}" class="btn btn-xs btn-outline-secondary py-1 px-2 d-flex align-items-center gap-1" title="Send RFQ">
                                                                    <i class="feather-mail"></i> RFQ
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
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
                            <div class="table-responsive border rounded shadow-sm">
                                <table class="table table-bordered table-sm align-middle fs-13 mb-0" style="table-layout: fixed; width: 100%;">
                                    <thead class="bg-light fw-semibold text-muted text-uppercase">
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
                                                    <input type="checkbox" name="item_ids[]" value="{{ $pi['item_id'] }}" class="form-check-input row-checkbox">
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
                                                        <a href="{{ route('purchase.orders.create', ['requisition_item_ids' => [$pi['item_id']]]) }}" class="btn btn-xs btn-primary py-1 px-2 d-flex align-items-center gap-1" style="background-color: #714B67; border-color: #714B67;" title="Convert to PO">
                                                            <i class="feather-plus-circle"></i> PO
                                                        </a>
                                                        <a href="{{ route('purchase.rfqs.create', ['requisition_item_ids' => [$pi['item_id']]]) }}" class="btn btn-xs btn-outline-secondary py-1 px-2 d-flex align-items-center gap-1" title="Send RFQ">
                                                            <i class="feather-mail"></i> RFQ
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @endif
                </form>

            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function changeGroupBy(val) {
            const url = new URL(window.location.href);
            url.searchParams.set('group_by', val);
            window.location.href = url.toString();
        }

        $(document).ready(function() {
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
                const selectedIds = $('.row-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();

                // Build redirect URL
                let redirectUrl = '';
                if (action === 'po') {
                    redirectUrl = '{{ route("purchase.orders.create") }}';
                } else if (action === 'rfq') {
                    redirectUrl = '{{ route("purchase.rfqs.create") }}';
                }

                const url = new URL(redirectUrl);
                selectedIds.forEach(id => url.searchParams.append('requisition_item_ids[]', id));
                
                const actionLabel = action === 'po' ? 'Purchase Order Create Form' : 'RFQ Create Form';
                if (confirm('Redirect to ' + actionLabel + ' with ' + checkedCount + ' selected item(s)?')) {
                    window.location.href = url.toString();
                }
            });
        });
    </script>
@endpush
