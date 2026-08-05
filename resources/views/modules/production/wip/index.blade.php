@extends('layouts.duralux')

@section('title', __('production.wip_management') . ' | SaaS ERP')
@section('page-title', __('production.wip_management'))
@section('breadcrumb', __('production.wip_management'))

@push('styles')
    <style>
        .erp-single-panel {
            display: flex !important;
            flex-direction: column !important;
            min-height: calc(100vh - 180px) !important;
        }
        .table-responsive {
            position: relative;
        }
    </style>
@endpush

@section('content')

    {{-- ── WIP Tracking Workflow Guidance Component ── --}}
    @php
        $firstOrder = isset($ordersPaginator) ? $ordersPaginator->first() : null;
        $orderTargetId = $firstOrder ? $firstOrder->id : null;
    @endphp

    <x-ui.workflow-guide title="What's Next?">
        Track live shop floor production progress across work centers. The <span class="badge bg-soft-success text-success border border-success-subtle fw-semibold me-1"><i class="feather-box me-1"></i>Receive Completed FG</span> button will automatically appear once completed finished goods units are available. Clicking it transfers the finished goods into your warehouse. All receipt logs can be tracked under the 
        @if($orderTargetId)
            <a href="{{ route('production.orders.show', ['order' => $orderTargetId, 'tab' => 'vtab-progress']) }}" class="fw-bold text-primary text-decoration-underline">Order Progress & Receipts Tab</a>.
        @else
            <span class="fw-bold text-primary text-decoration-underline">Order Progress & Receipts Tab</span> on the Production Order page.
        @endif
    </x-ui.workflow-guide>

    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">
        <!-- Success & Error Messages -->

        {{-- WIP Summary Cards --}}
        @if(isset($wipSummary))
            <div class="row g-3 mb-4">
                <div class="col">
                    <div class="bg-light border rounded p-3 text-center">
                        <span class="text-muted fs-11 text-uppercase fw-bold">Total WIP Cards</span>
                        <h4 class="text-dark fw-bold mt-1 mb-0">{{ number_format($wipSummary['total_count']) }}</h4>
                    </div>
                </div>
                <div class="col">
                    <div class="bg-soft-primary border rounded p-3 text-center">
                        <span class="text-primary fs-11 text-uppercase fw-bold">Active / In-Process</span>
                        <h4 class="text-primary fw-bold mt-1 mb-0">{{ number_format($wipSummary['active_count']) }}</h4>
                        <small class="fs-10 text-muted">({{ number_format($wipSummary['total_available'], 2) }} units)</small>
                    </div>
                </div>
                <div class="col">
                    <div class="bg-soft-warning border rounded p-3 text-center">
                        <span class="text-warning fs-11 text-uppercase fw-bold">Quality Hold</span>
                        <h4 class="text-warning fw-bold mt-1 mb-0">{{ number_format($wipSummary['hold_count']) }}</h4>
                    </div>
                </div>
                <div class="col">
                    <div class="bg-soft-danger border rounded p-3 text-center">
                        <span class="text-danger fs-11 text-uppercase fw-bold">Rework</span>
                        <h4 class="text-danger fw-bold mt-1 mb-0">{{ number_format($wipSummary['rework_count']) }}</h4>
                    </div>
                </div>
                <div class="col">
                    <div class="bg-soft-success border rounded p-3 text-center">
                        <span class="text-success fs-11 text-uppercase fw-bold">Completed</span>
                        <h4 class="text-success fw-bold mt-1 mb-0">{{ number_format($wipSummary['completed_count']) }}</h4>
                    </div>
                </div>
            </div>
        @endif

        <!-- Toolbar: Sort, Filters, View Switcher -->
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">{{ __('production.wip_list') }}</h5>
            
            <!-- View Switcher Toggle -->
            <div class="btn-group btn-group-sm ms-3 gap-0" role="group">
                <a href="{{ route('production.wip.index', array_merge(request()->query(), ['view' => 'order'])) }}" 
                   class="btn {{ $viewMode === 'order' ? 'btn-primary' : 'btn-outline-secondary' }}" title="Consolidate WIP cards by Production Order">
                    <i class="feather-layers me-1"></i> By Production Order
                </a>
                <a href="{{ route('production.wip.index', array_merge(request()->query(), ['view' => 'detailed'])) }}" 
                   class="btn {{ $viewMode === 'detailed' ? 'btn-primary' : 'btn-outline-secondary' }}" title="View individual stage/batch WIP cards">
                    <i class="feather-list me-1"></i> All WIP Sub-Cards
                </a>
            </div>

            <div class="d-flex gap-2 ms-auto">
                <form method="GET" action="{{ route('production.wip.index') }}" class="d-inline">
                    <input type="hidden" name="view" value="{{ $viewMode }}" />
                    <x-ui.filter :label="__('ui.filter')" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('production.filter_options') }}</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.search_keywords') }}</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="Search product or order..." value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">All Statuses</option>
                                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="quality_hold" {{ request('status') === 'quality_hold' ? 'selected' : '' }}>Quality Hold</option>
                                <option value="rework" {{ request('status') === 'rework' ? 'selected' : '' }}>Rework</option>
                                <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <x-ui.button href="{{ route('production.wip.index', ['view' => $viewMode]) }}" variant="light" size="sm" class="border">
                                {{ __('production.reset') }}
                            </x-ui.button>
                            <x-ui.button type="submit" variant="primary" size="sm">
                                {{ __('production.apply_filters') }}
                            </x-ui.button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        {{-- Work Center Filter & Bulk Accordion Controls --}}
        <div class="d-flex align-items-center justify-content-between gap-3 mb-3 bg-light p-2 rounded border">
            <form method="GET" action="{{ route('production.wip.index') }}" class="d-flex align-items-center gap-2 flex-grow-1">
                <input type="hidden" name="view" value="{{ $viewMode }}" />
                @foreach(request()->except(['work_center_id', 'page']) as $k => $v)
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}" />
                @endforeach
                <label class="fs-11 text-uppercase text-muted fw-bold mb-0 text-nowrap">
                    <i class="feather-cpu text-primary me-1"></i> Work Center:
                </label>
                <div style="max-width:280px; min-width:180px;">
                    <x-ui.odoo-form-ui type="select" name="work_center_id" onchange="this.form.submit()">
                        <option value="">All Work Centers</option>
                        @foreach($workCenters as $wc)
                            <option value="{{ $wc->id }}" {{ $workCenterIdFilter == $wc->id ? 'selected' : '' }}>
                                {{ $wc->name }}
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                @if($workCenterIdFilter)
                    <x-ui.button href="{{ route('production.wip.index', array_merge(request()->except(['work_center_id', 'page']), ['view' => $viewMode])) }}" variant="light" size="sm" class="border text-nowrap">
                        <i class="feather-x me-1"></i> Clear
                    </x-ui.button>
                @endif
            </form>
            <div class="d-flex gap-2 flex-shrink-0">
                <x-ui.button type="button" variant="light" size="sm" class="border" onclick="expandAllWorkCenters()">
                    <i class="feather-maximize-2 me-1"></i> Expand All
                </x-ui.button>
                <x-ui.button type="button" variant="light" size="sm" class="border" onclick="collapseAllWorkCenters()">
                    <i class="feather-minimize-2 me-1"></i> Collapse All
                </x-ui.button>
            </div>
        </div>

        @if($viewMode === 'order')
            {{-- ORDER CONSOLIDATED VIEW --}}
            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 5%"></th>
                            <th style="width: 15%">{{ __('production.production_order') }}</th>
                            <th style="width: 20%">{{ __('production.product') }}</th>
                            <th style="width: 20%">Active Work Centers</th>
                            <th class="text-end" style="width: 10%" title="Total physical units currently on the factory floor across all operation stages">
                                Shop Floor Qty
                            </th>
                            <th class="text-end" style="width: 10%" title="Finished units that completed final production step ready for warehouse receipt">
                                Completed FG
                            </th>
                            <th class="text-end" style="width: 10%">Total WIP Value</th>
                            <th style="width: 5%">Status</th>
                            <th class="text-end" style="width: 5%">{{ __('production.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ordersPaginator as $order)
                            @php
                                $orderId = $order->id;
                                $product = $order->product;
                                $summaries = $orderSummariesMap[$orderId] ?? collect();
                                $totalAvailable = $summaries->sum('total_available');
                                $totalCompleted = $summaries->sum('total_completed');
                                $totalValue = $summaries->sum('accrued_value');
                                $totalBatches = $summaries->sum('batch_count');
                                $hasHold = $summaries->sum('total_hold') > 0;
                                $hasRework = $summaries->sum('total_rework') > 0;
                                $orderStatus = $hasHold ? 'quality_hold' : ($hasRework ? 'rework' : ($order->status === 'completed' ? 'completed' : 'active'));
                            @endphp
                            <tr class="align-middle">
                                <td>
                                    <button class="btn btn-sm btn-light border p-1 rounded-circle" type="button" 
                                            data-bs-toggle="collapse" data-bs-target="#wip-group-{{ $orderId }}" 
                                            aria-expanded="false" aria-controls="wip-group-{{ $orderId }}"
                                            title="Expand Work Centers">
                                        <i class="feather-chevron-down fs-12"></i>
                                    </button>
                                </td>
                                <td>
                                    <a href="{{ route('production.orders.show', $orderId) }}" class="fw-bold text-primary hover-primary">
                                        {{ $order->order_number ?? 'Order #' . $orderId }}
                                    </a>
                                    <div class="fs-10 text-muted">
                                        <span class="badge bg-soft-primary text-primary fs-10">{{ $totalBatches }} WIP {{ Str::plural('Card', $totalBatches) }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="fw-semibold text-dark">{{ $product->name ?? '—' }}</span>
                                        <small class="text-muted font-monospace fs-10">{{ $product->sku ?? '' }}</small>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($summaries as $s)
                                            <span class="badge bg-light text-dark border fs-10">
                                                {{ $s['work_center_name'] }}: {{ number_format($s['total_available'], 2) }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="text-end fw-bold text-dark fs-13">
                                    {{ number_format($totalAvailable, 2) }}
                                </td>
                                <td class="text-end text-success fw-semibold fs-13">
                                    {{ number_format($totalCompleted, 2) }}
                                </td>
                                <td class="text-end text-primary fw-bold fs-13">
                                    {{ format_currency($totalValue) }}
                                </td>
                                <td>
                                    @if($orderStatus === 'active')
                                        <span class="badge bg-soft-success text-success text-uppercase">Active</span>
                                    @elseif($orderStatus === 'quality_hold')
                                        <span class="badge bg-soft-warning text-warning text-uppercase">Quality Hold</span>
                                    @elseif($orderStatus === 'rework')
                                        <span class="badge bg-soft-danger text-danger text-uppercase">Rework</span>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary text-uppercase">Completed</span>
                                    @endif
                                </td>
                                @php
                                    $readyQtyToReceive = $totalCompleted;
                                @endphp
                                <td class="text-end">
                                    @if($readyQtyToReceive > 0)
                                        <button type="button" class="btn btn-xs btn-success me-1 text-nowrap" data-bs-toggle="modal" data-bs-target="#bulkFgModal{{ $orderId }}" title="Receive all completed units into warehouse">
                                            <i class="feather-arrow-down-right me-1"></i> Receive Completed FG ({{ number_format($readyQtyToReceive, 0) }})
                                        </button>
                                    @endif
                                    <x-ui.action-dropdown :viewUrl="route('production.orders.show', $orderId)" />

                                    <!-- Bulk FG Modal for Order -->
                                    <x-ui.modal id="bulkFgModal{{ $orderId }}" title="Receive Finished Goods - Order #{{ $order->order_number ?? $orderId }}"
                                        formAction="{{ route('production.wip.convert-order', $orderId) }}" submitText="Receive {{ number_format($readyQtyToReceive, 2) }} Units into Warehouse" closeText="Cancel" class="text-start">
                                        <div class="alert alert-info py-2 fs-12 mb-3 text-start">
                                            <i class="feather-info me-1"></i> Transfer all <strong>{{ number_format($readyQtyToReceive, 2) }} completed finished units</strong> across all sub-cards for this order directly into Finished Goods warehouse inventory in one click.
                                        </div>

                                        <x-ui.odoo-form-ui type="select" label="Destination Warehouse" name="warehouse_id" :searchable="false" required>
                                            @foreach($warehouses as $wh)
                                                <option value="{{ $wh->id }}">{{ $wh->name }} {{ $wh->is_default ? '(Default)' : '' }}</option>
                                            @endforeach
                                        </x-ui.odoo-form-ui>

                                        <x-ui.odoo-form-ui type="input" label="Receipt Remarks" name="remarks" placeholder="Bulk received from Order WIP cards" />
                                    </x-ui.modal>
                                </td>
                            </tr>
                            {{-- Expandable Sub-Cards Accordion Row --}}
                            <tr class="p-0 border-0">
                                <td colspan="9" class="p-0 border-0">
                                    <div class="collapse wip-order-collapse bg-light p-3 border-bottom shadow-inner" id="wip-group-{{ $orderId }}">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="fs-12 text-uppercase fw-bold text-dark mb-0">
                                                <i class="feather-grid me-1 text-primary"></i> Work Center Breakdown for {{ $order->order_number ?? 'Order #' . $orderId }}
                                            </h6>
                                            <span class="fs-11 text-muted">{{ $totalBatches }} Sub-Cards across {{ $summaries->count() }} Work Centers</span>
                                        </div>

                                        @foreach($summaries as $s)
                                            @php
                                                $wcId = $s['work_center_id'];
                                                $tenantId = require_tenant_id();
                                                $initialBatchesPaginator = app(\App\Domains\Production\Services\ProductionWipService::class)
                                                    ->getPaginatedWorkCenterWips($tenantId, $orderId, $wcId, null, null, 5);
                                            @endphp
                                            <div class="bg-white rounded border mb-3">
                                                <div class="bg-light px-3 py-1.5 border-bottom d-flex justify-content-between align-items-center">
                                                    <span class="fw-bold text-dark fs-12"><i class="feather-cpu text-primary me-1"></i> {{ $s['work_center_name'] }}</span>
                                                    <span class="fs-11 text-muted">
                                                        Floor Qty: <strong class="text-dark me-2">{{ number_format($s['total_available'], 2) }}</strong> | 
                                                        Completed: <strong class="text-success me-2">{{ number_format($s['total_completed'], 2) }}</strong> | 
                                                        Total Cost: <strong class="text-primary">{{ format_currency($s['accrued_value']) }}</strong>
                                                    </span>
                                                </div>
                                                <div class="table-responsive">
                                                    <table class="table table-sm align-middle mb-0 fs-12">
                                                        <thead class="bg-light fs-11 text-muted text-uppercase">
                                                            <tr>
                                                                <th>WIP Card #</th>
                                                                <th>Batch #</th>
                                                                <th>Current Stage</th>
                                                                <th class="text-end">Available Qty</th>
                                                                <th class="text-end">Completed Qty</th>
                                                                <th class="text-end">WIP Value</th>
                                                                <th>Status</th>
                                                                <th class="text-end">Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="wc-batches-tbody-{{ $orderId }}-{{ $wcId }}">
                                                            @include('modules.production.wip.partials.work-center-batch-rows', ['wips' => $initialBatchesPaginator->items()])
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div id="wc-batches-pagination-{{ $orderId }}-{{ $wcId }}">
                                                    @if($initialBatchesPaginator->lastPage() > 1)
                                                        @include('modules.production.wip.partials.wc-pagination', [
                                                            'paginator'    => $initialBatchesPaginator,
                                                            'orderId'      => $orderId,
                                                            'workCenterId' => $wcId,
                                                        ])
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="feather-info fs-24 d-block mb-2"></i>
                                    {{ __('production.no_wips_found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>

                <x-ui.pagination
                    :currentPage="$ordersPaginator->currentPage()"
                    :totalPages="$ordersPaginator->lastPage()"
                    :totalResults="$ordersPaginator->total()"
                    :perPage="$ordersPaginator->perPage()"
                />
            </div>
        @else
            {{-- DETAILED CARDS VIEW --}}
            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 10%">{{ __('production.wip_number') }}</th>
                            <th style="width: 15%">{{ __('production.production_order') }}</th>
                            <th style="width: 20%">{{ __('production.product') }}</th>
                            <th style="width: 15%">{{ __('production.current_operation') }}</th>
                            <th class="text-end" style="width: 10%">{{ __('production.available_qty') }}</th>
                            <th class="text-end" style="width: 10%">{{ __('production.completed_qty') }}</th>
                            <th class="text-end" style="width: 10%">{{ __('production.wip_value') }}</th>
                            <th style="width: 5%">{{ __('production.status') }}</th>
                            <th class="text-end" style="width: 5%">{{ __('production.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($wips as $wip)
                            <tr>
                                <td>
                                    <a href="{{ route('production.wip.show', $wip->id) }}" class="fw-bold text-primary hover-primary">
                                        WIP-#{{ str_pad($wip->id, 5, '0', STR_PAD_LEFT) }}
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ route('production.orders.show', $wip->production_order_id) }}" class="fw-semibold text-dark">
                                        {{ $wip->order->order_number ?? 'Order #' . $wip->production_order_id }}
                                    </a>
                                    @if($wip->batch)
                                        <div class="fs-10 text-muted"><i class="feather-box"></i> {{ $wip->batch->batch_number }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="fw-semibold text-dark">{{ $wip->product->name }}</span>
                                        <small class="text-muted font-monospace fs-10">{{ $wip->product->sku }}</small>
                                    </div>
                                </td>
                                <td>
                                    @if($wip->status === 'completed')
                                        <div class="fw-semibold text-success">Finished Goods (Ready)</div>
                                        <small class="text-muted">Warehouse Stock</small>
                                    @elseif($wip->currentRoutingOperation)
                                        <div class="fw-semibold text-dark">{{ $wip->currentRoutingOperation->name }}</div>
                                        <small class="text-muted">{{ $wip->currentWorkCenter->name ?? '' }}</small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold text-dark">
                                    {{ number_format($wip->available_quantity, 2) }}
                                </td>
                                <td class="text-end text-success fw-semibold">
                                    {{ number_format($wip->completed_quantity, 2) }}
                                </td>
                                <td class="text-end text-primary fw-bold">
                                    {{ format_currency($wip->total_value) }}
                                </td>

                                <td>
                                    @if($wip->status === 'active')
                                        <span class="badge bg-soft-success text-success text-uppercase">Active</span>
                                    @elseif($wip->status === 'quality_hold')
                                        <span class="badge bg-soft-warning text-warning text-uppercase">Quality Hold</span>
                                    @elseif($wip->status === 'rework')
                                        <span class="badge bg-soft-danger text-danger text-uppercase">Rework</span>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary text-uppercase">Completed</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <x-ui.action-dropdown :viewUrl="route('production.wip.show', $wip->id)" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="feather-info fs-24 d-block mb-2"></i>
                                    {{ __('production.no_wips_found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
            <x-ui.pagination
                :currentPage="$wips->currentPage()"
                :totalPages="$wips->lastPage()"
                :totalResults="$wips->total()"
                :perPage="$wips->perPage()"
            />
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        function expandAllWorkCenters() {
            document.querySelectorAll('.wip-order-collapse').forEach(function(el) {
                var c = new bootstrap.Collapse(el, { toggle: false });
                c.show();
            });
        }

        function collapseAllWorkCenters() {
            document.querySelectorAll('.wip-order-collapse').forEach(function(el) {
                var c = new bootstrap.Collapse(el, { toggle: false });
                c.hide();
            });
        }

        function loadWorkCenterBatchPage(orderId, workCenterId, page) {
            var tbody = document.getElementById('wc-batches-tbody-' + orderId + '-' + workCenterId);
            var paginationContainer = document.getElementById('wc-batches-pagination-' + orderId + '-' + workCenterId);
            if (!tbody) return;

            tbody.style.opacity = '0.5';
            var url = "{{ url('production/wip/orders') }}/" + orderId + "/work-centers/" + workCenterId + "/batches?page=" + page;

            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                tbody.style.opacity = '1';
                if (data.success) {
                    tbody.innerHTML = data.html;
                    if (paginationContainer) {
                        renderBatchPaginationControls(paginationContainer, orderId, workCenterId, data.current_page, data.last_page, data.total, data.per_page);
                    }
                }
            })
            .catch(function(err) {
                tbody.style.opacity = '1';
            });
        }

        function renderBatchPaginationControls(container, orderId, workCenterId, currentPage, lastPage, total, perPage) {
            if (lastPage <= 1) { container.innerHTML = ''; return; }
            total   = total   || (lastPage * (perPage || 5));
            perPage = perPage || 5;
            var from = Math.min((currentPage - 1) * perPage + 1, total);
            var to   = Math.min(currentPage * perPage, total);

            var html = '<div class="erp-pagination-container border-top"><ul class="erp-pagination">';

            // Prev
            html += '<li class="page-item' + (currentPage <= 1 ? ' disabled' : '') + '">';
            html += '<button type="button" class="page-link"' + (currentPage <= 1 ? ' disabled' : '') + ' onclick="loadWorkCenterBatchPage(' + orderId + ',' + workCenterId + ',' + (currentPage - 1) + ')" aria-label="Previous"><i class="feather-chevron-left"></i></button></li>';

            // Page window
            var start = Math.max(1, currentPage - 2);
            var end   = Math.min(lastPage, currentPage + 2);
            if (start > 1) {
                html += '<li class="page-item"><button type="button" class="page-link" onclick="loadWorkCenterBatchPage(' + orderId + ',' + workCenterId + ',1)">1</button></li>';
                if (start > 2) html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
            for (var i = start; i <= end; i++) {
                html += '<li class="page-item' + (currentPage === i ? ' active' : '') + '">';
                html += '<button type="button" class="page-link" onclick="loadWorkCenterBatchPage(' + orderId + ',' + workCenterId + ',' + i + ')">' + i + '</button></li>';
            }
            if (end < lastPage) {
                if (end < lastPage - 1) html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
                html += '<li class="page-item"><button type="button" class="page-link" onclick="loadWorkCenterBatchPage(' + orderId + ',' + workCenterId + ',' + lastPage + ')">' + lastPage + '</button></li>';
            }

            // Next
            html += '<li class="page-item' + (currentPage >= lastPage ? ' disabled' : '') + '">';
            html += '<button type="button" class="page-link"' + (currentPage >= lastPage ? ' disabled' : '') + ' onclick="loadWorkCenterBatchPage(' + orderId + ',' + workCenterId + ',' + (currentPage + 1) + ')" aria-label="Next"><i class="feather-chevron-right"></i></button></li>';

            html += '</ul><div class="erp-pagination-info">Showing ' + from + ' to ' + to + ' of ' + total + ' batches</div></div>';
            container.innerHTML = html;
        }
    </script>
@endpush
