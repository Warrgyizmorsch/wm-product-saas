@extends('layouts.duralux')

@section('title', __('crm.dispatch_orders') . ' | SaaS ERP')
@section('page-title', __('crm.dispatch_orders'))
@section('breadcrumb', __('crm.sales_breadcrumb'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.import-export-dropdown 
            type="dispatches" 
            :can-import="false" 
            :can-download-template="false" 
            export-route="{{ route('sales.dispatches.export') }}" />
        <x-ui.button href="{{ route('sales.dispatches.create') }}" variant="primary" icon="feather-plus">
            {{ __('crm.create_dispatch_order') }}
        </x-ui.button>
    </div>
@endsection

@section('content')

    @php
        $sortBy = request('sort_by', 'created_at');
        $sortOrder = request('sort_order', 'desc');
        $pendingSalesOrders = $pendingSalesOrders ?? collect();
        $dispatches = $dispatches ?? collect();
        $pendingSalesOrdersCount = $pendingSalesOrdersCount ?? (is_countable($pendingSalesOrders) ? count($pendingSalesOrders) : 0);
        $allDispatchesCount = $allDispatchesCount ?? (is_countable($dispatches) ? count($dispatches) : 0);
        $currentTab = $activeTab ?? request('tab', 'pending');
        $isPendingActive = ($currentTab === 'pending' || (!request()->has('tab') && $pendingSalesOrdersCount > 0));
    @endphp

    <div class="erp-single-panel">

        <!-- Top Toolbar: Title, Sort, Filter Drawer -->
        <div class="d-flex align-items-center mb-3">
            <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold text-dark mb-0 me-2">{{ __('crm.dispatch_orders') }}</h5>
            </div>
            <div class="d-flex gap-2 ms-auto">
                <!-- Custom Sort Component -->
                <x-ui.sort-dropdown :label="__('crm.sort') ?: 'Sort'">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('crm.latest_dispatches_first') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('crm.oldest_dispatches_first') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'dispatch_number', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'dispatch_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('crm.dispatch_number_az') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'dispatch_number', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'dispatch_number' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('crm.dispatch_number_za') }}</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Custom Filter Component -->
                <form method="GET" action="{{ route('sales.dispatches.index') }}" class="d-inline">
                    <input type="hidden" name="tab" value="{{ $isPendingActive ? 'pending' : 'all' }}">
                    <x-ui.filter :label="__('crm.filter') ?: 'Filter'" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('crm.filter_options') }}</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.search_keywords') }}</label>
                            <x-ui.odoo-form-ui type="input" name="search" :placeholder="__('crm.search_placeholder_dispatches')" value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.dispatch_status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">{{ __('crm.all_statuses') }}</option>
                                <option value="Pending" {{ request('status') === 'Pending' ? 'selected' : '' }}>Pending</option>
                                <option value="Confirmed" {{ request('status') === 'Confirmed' ? 'selected' : '' }}>Confirmed</option>
                                <option value="Dispatched" {{ request('status') === 'Dispatched' ? 'selected' : '' }}>Dispatched</option>
                                <option value="Delivered" {{ request('status') === 'Delivered' ? 'selected' : '' }}>Delivered</option>
                                <option value="Cancelled" {{ request('status') === 'Cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('sales.dispatches.index', ['tab' => $isPendingActive ? 'pending' : 'all']) }}" class="btn btn-sm btn-light border">{{ __('crm.reset') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('crm.apply_filters') }}</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- Horizontal 2 Tabs Navigation -->
        <x-ui.horizontal-tabs id="dispatchesTabNav" class="mb-3" :tabs="[
            [
                'id' => 'tab-pending-dispatches',
                'label' => __('crm.tab_pending_dispatches') . (($pendingSalesOrdersCount ?? 0) > 0 ? ' (' . $pendingSalesOrdersCount . ')' : ''),
                'active' => $isPendingActive,
                'icon' => 'feather-clock',
            ],
            [
                'id' => 'tab-all-dispatches',
                'label' => __('crm.tab_all_dispatches') . (($allDispatchesCount ?? 0) > 0 ? ' (' . $allDispatchesCount . ')' : ''),
                'active' => !$isPendingActive,
                'icon' => 'feather-truck',
            ]
        ]" />

        <!-- Tab Content Panes -->
        <div class="tab-content" id="dispatchesTabContent">

            <!-- TAB 1: PENDING DISPATCH ORDERS (Sales Orders awaiting fulfillment) -->
            <div class="tab-pane fade {{ $isPendingActive ? 'show active' : '' }}" id="tab-pending-dispatches" role="tabpanel" aria-labelledby="tab-pending-dispatches-tab">
                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table" id="pendingDispatchesTable" class="mb-0">
                        <thead>
                            <tr style="background-color: #f1f5f9 !important;">
                                <th style="min-width: 150px;">{{ __('crm.so_number_and_date') }}</th>
                                <th style="min-width: 170px;">{{ __('crm.customer') }}</th>
                                <th style="min-width: 260px;">{{ __('crm.ordered_items_breakdown') }}</th>
                                <th style="min-width: 100px;" class="text-center">{{ __('crm.total_ordered') }}</th>
                                <th style="min-width: 100px;" class="text-center">{{ __('crm.total_dispatched') }}</th>
                                <th style="min-width: 110px;" class="text-center">{{ __('crm.pending_qty') }}</th>
                                <th style="min-width: 120px;" class="text-center">{{ __('crm.dispatch_progress') }}</th>
                                <th style="min-width: 130px;" class="text-end pe-3">{{ __('crm.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pendingSalesOrders as $so)
                                @php
                                    $prog = $so->dispatch_progress ?? 0;
                                    $progColor = $prog > 0 ? 'bg-info' : 'bg-warning';
                                    $statusBadgeClass = ($so->dispatch_status_label === 'Partially Dispatched') 
                                        ? 'bg-soft-info text-info' 
                                        : 'bg-soft-warning text-warning';
                                @endphp
                                <tr>
                                    {{-- Sales Order # & Date --}}
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-text avatar-sm bg-soft-primary text-primary me-2">
                                                <i class="feather-file-text fs-13"></i>
                                            </div>
                                            <div>
                                                <a href="{{ route('sales.orders.show', $so->id) }}" class="fw-bold text-primary d-block">
                                                    {{ $so->sales_order_number }}
                                                </a>
                                                <span class="text-muted fs-11">
                                                    <i class="feather-calendar me-1 fs-10"></i>{{ $so->order_date ? $so->order_date->format('d M Y') : $so->created_at?->format('d M Y') }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Customer --}}
                                    <td>
                                        <span class="fw-bold text-dark d-block">{{ $so->customer?->name ?? '—' }}</span>
                                        @if ($so->customer?->phone)
                                            <span class="text-muted fs-11"><i class="feather-phone me-1 fs-10"></i>{{ $so->customer->phone }}</span>
                                        @elseif ($so->customer?->email)
                                            <span class="text-muted fs-11"><i class="feather-mail me-1 fs-10"></i>{{ $so->customer->email }}</span>
                                        @endif
                                    </td>

                                    {{-- Items Breakdown & Stock Status --}}
                                    <td>
                                        <div class="d-flex flex-column gap-1">
                                            @foreach ($so->items_detail as $item)
                                                <div class="d-flex align-items-center justify-content-between p-1.5 rounded bg-light border border-gray-200 fs-11">
                                                    <div>
                                                        <strong class="text-dark">{{ $item['product_name'] }}</strong>
                                                        @if(!empty($item['sku']))
                                                            <span class="text-muted">({{ $item['sku'] }})</span>
                                                        @endif
                                                        <div class="fs-10 text-muted">
                                                            {{ __('crm.stock_available') }}: <span class="fw-bold {{ $item['available_stock'] >= $item['pending_qty'] ? 'text-success' : 'text-danger' }}">{{ (int)$item['available_stock'] }}</span>
                                                        </div>
                                                    </div>
                                                    <div class="text-end ps-2">
                                                        <span class="badge {{ $item['pending_qty'] > 0 ? 'bg-soft-danger text-danger' : 'bg-soft-success text-success' }} fs-10 fw-semibold">
                                                            {{ (int)$item['pending_qty'] }} / {{ (int)$item['ordered_qty'] }} {{ $item['uom'] }}
                                                        </span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>

                                    {{-- Total Ordered --}}
                                    <td class="text-center font-monospace fw-semibold text-dark fs-12">
                                        {{ (int)$so->total_ordered_qty }}
                                    </td>

                                    {{-- Dispatched Qty --}}
                                    <td class="text-center font-monospace fw-semibold text-info fs-12">
                                        {{ (int)$so->total_dispatched_qty }}
                                    </td>

                                    {{-- Pending Qty --}}
                                    <td class="text-center">
                                        <span class="badge bg-soft-danger text-danger fs-12 fw-bolder font-monospace px-2.5 py-1">
                                            {{ (int)$so->total_pending_qty }}
                                        </span>
                                    </td>

                                    {{-- Progress Bar --}}
                                    <td class="text-center">
                                        <div class="d-flex align-items-center justify-content-center gap-2">
                                            <div class="progress flex-grow-1" style="height: 6px; min-width: 60px;">
                                                <div class="progress-bar {{ $progColor }}" role="progressbar" style="width: {{ $prog }}%;" aria-valuenow="{{ $prog }}" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <span class="fs-10 fw-bold text-muted">{{ $prog }}%</span>
                                        </div>
                                        <span class="badge {{ $statusBadgeClass }} fs-10 mt-1">{{ $so->dispatch_status_label }}</span>
                                    </td>

                                    {{-- Actions --}}
                                    <td class="text-end pe-3">
                                        <div class="hstack gap-1 justify-content-end align-items-center">
                                            <a href="{{ route('sales.dispatches.create', ['sales_order_id' => $so->id]) }}" 
                                               class="btn btn-sm btn-primary text-nowrap fs-11 fw-bold px-2.5 py-1"
                                               title="{{ __('crm.dispatch_now') }}">
                                                <i class="feather-truck me-1 fs-11"></i>{{ __('crm.dispatch_now') }}
                                            </a>
                                            <a href="{{ route('sales.orders.show', $so->id) }}" 
                                               class="btn btn-sm btn-icon btn-light" 
                                               title="{{ __('crm.view_sales_order') }}">
                                                <i class="feather-eye fs-12"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="feather-check-circle fs-1 d-block mb-3 text-success opacity-50"></i>
                                        <h6 class="fw-bold text-dark">{{ __('crm.no_pending_sales_orders') }}</h6>
                                        <p class="fs-12 text-muted mb-0">All confirmed sales orders have been fully dispatched.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>

                @if ($pendingSalesOrders instanceof \Illuminate\Pagination\LengthAwarePaginator && $pendingSalesOrders->hasPages())
                    <div class="pt-3">
                        <x-ui.pagination 
                            :currentPage="$pendingSalesOrders->currentPage()" 
                            :totalPages="$pendingSalesOrders->lastPage()" 
                            :totalResults="$pendingSalesOrders->total()" 
                            :perPage="$pendingSalesOrders->perPage()" />
                    </div>
                @endif
            </div>

            <!-- TAB 2: ALL DISPATCH ORDERS (History & Completed Dispatches) -->
            <div class="tab-pane fade {{ !$isPendingActive ? 'show active' : '' }}" id="tab-all-dispatches" role="tabpanel" aria-labelledby="tab-all-dispatches-tab">
                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table" id="dispatchTable" class="mb-0">
                        <thead>
                            <tr style="background-color: #f1f5f9 !important;">
                                <th style="width: 3%" class="text-center">
                                    <input type="checkbox" class="form-check-input" id="selectAllCheckbox">
                                </th>
                                <th style="min-width: 140px;">{{ __('crm.dispatch_date_and_num') }}</th>
                                <th style="min-width: 130px;">{{ __('crm.material_requirement') }}</th>
                                <th style="min-width: 120px;">{{ __('crm.sales_order') }}</th>
                                <th style="min-width: 160px;">{{ __('crm.customer') }}</th>
                                <th style="min-width: 150px;">{{ __('crm.carrier_vehicle') }}</th>
                                <th style="min-width: 100px;">{{ __('crm.status') }}</th>
                                <th style="min-width: 110px;" class="text-end pe-4">{{ __('crm.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($dispatches as $dispatch)
                                @php
                                    $badgeClass = 'bg-soft-secondary text-secondary';
                                    if ($dispatch->status === 'Pending') $badgeClass = 'bg-soft-warning text-warning';
                                    elseif ($dispatch->status === 'Confirmed') $badgeClass = 'bg-soft-primary text-primary';
                                    elseif ($dispatch->status === 'Dispatched' || $dispatch->status === 'Shipped') $badgeClass = 'bg-soft-info text-info';
                                    elseif ($dispatch->status === 'Delivered') $badgeClass = 'bg-soft-success text-success';
                                    elseif ($dispatch->status === 'Cancelled') $badgeClass = 'bg-soft-danger text-danger';

                                    $custName = $dispatch->customer?->name 
                                        ?? $dispatch->salesOrder?->customer?->name 
                                        ?? $dispatch->materialRequirement?->salesOrder?->customer?->name 
                                        ?? '—';
                                @endphp
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input row-checkbox" value="{{ $dispatch->id }}">
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-text avatar-sm bg-soft-primary text-primary me-2">
                                                <i class="feather-send"></i>
                                            </div>
                                            <div>
                                                <a href="{{ route('sales.dispatches.show', $dispatch->id) }}" class="fw-bold text-primary d-block">
                                                    {{ $dispatch->dispatch_number }}
                                                </a>
                                                <span class="text-muted fs-11">
                                                    <i class="feather-calendar me-1 fs-10"></i>{{ $dispatch->dispatch_date ? date('d M Y', strtotime($dispatch->dispatch_date)) : 'N/A' }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if ($dispatch->materialRequirement)
                                            <a href="{{ route('inventory.material-requirements.show', $dispatch->material_requirement_id) }}" class="fw-semibold text-dark">
                                                {{ $dispatch->materialRequirement->requirement_number }}
                                            </a>
                                        @else
                                            <span class="badge bg-soft-success text-success font-monospace">{{ __('crm.direct_outward') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($dispatch->salesOrder)
                                            <a href="{{ route('sales.orders.show', $dispatch->sales_order_id) }}" class="text-muted fw-semibold">
                                                {{ $dispatch->salesOrder->sales_order_number }}
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark d-block mb-0.5">{{ $custName }}</span>
                                        @if ($dispatch->customer?->phone || $dispatch->salesOrder?->customer?->phone)
                                            <span class="text-muted fs-11"><i class="feather-phone me-1 fs-10"></i>{{ $dispatch->customer?->phone ?? $dispatch->salesOrder?->customer?->phone }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($dispatch->shipping_agent || $dispatch->vehicle_number || $dispatch->carrier)
                                            <span class="d-block fw-semibold text-dark fs-12">{{ $dispatch->shipping_agent ?: ($dispatch->carrier ?: 'Standard') }}</span>
                                            @if ($dispatch->vehicle_number)
                                                <span class="text-muted fs-11"><i class="feather-truck me-1 fs-10 text-primary"></i>{{ $dispatch->vehicle_number }}</span>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $badgeClass }} px-2.5 py-1 fs-11 fw-bold">{{ $dispatch->status }}</span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="hstack gap-1 justify-content-end align-items-center">
                                            @if ($dispatch->status === 'Pending')
                                                <form action="{{ route('sales.dispatches.confirm', $dispatch->id) }}" method="POST" id="confirmDispatchForm_{{ $dispatch->id }}" class="d-inline">
                                                    @csrf
                                                    <x-ui.button type="button" variant="soft-success" size="xs" icon="feather-check-circle" class="fw-bold fs-11 text-nowrap" style="padding: 8px 8px !important;" onclick="confirmAction({ title: '{{ __('crm.confirm_dispatch_order') }}', message: '{{ __('crm.confirm_dispatch') }} {{ $dispatch->dispatch_number }}?', variant: 'success', confirmText: '{{ __('crm.confirm_dispatch') }}' }, function() { document.getElementById('confirmDispatchForm_{{ $dispatch->id }}').submit(); })">
                                                        {{ __('crm.confirm') }}
                                                    </x-ui.button>
                                                </form>
                                            @endif

                                            <x-ui.action-dropdown :viewUrl="route('sales.dispatches.show', $dispatch->id)">
                                                <li>
                                                    <a href="{{ route('sales.dispatches.show', $dispatch->id) }}" class="dropdown-item">
                                                        <i class="feather-eye me-2 text-muted fs-12"></i>{{ __('crm.view_dispatch') }}
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('sales.dispatches.download-challan', $dispatch->id) }}" class="dropdown-item" target="_blank">
                                                        <i class="feather-printer me-2 text-muted fs-12"></i>{{ __('crm.delivery_challan_pdf') ?? 'Delivery Challan' }}
                                                    </a>
                                                </li>
                                                @if ($dispatch->status === 'Pending')
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form action="{{ route('sales.dispatches.confirm', $dispatch->id) }}" method="POST" id="confirmDispatchFormDropdown_{{ $dispatch->id }}">
                                                            @csrf
                                                            <button type="button" class="dropdown-item text-success fw-semibold" onclick="confirmAction({ title: '{{ __('crm.confirm_dispatch_order') }}', message: '{{ __('crm.confirm_dispatch') }} {{ $dispatch->dispatch_number }}?', variant: 'success', confirmText: '{{ __('crm.confirm_dispatch') }}' }, function() { document.getElementById('confirmDispatchFormDropdown_{{ $dispatch->id }}').submit(); })">
                                                                <i class="feather-check-circle me-2 text-success fs-12"></i>{{ __('crm.confirm_dispatch') }}
                                                            </button>
                                                        </form>
                                                    </li>
                                                @endif
                                            </x-ui.action-dropdown>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="feather-send fs-1 d-block mb-3 text-light"></i>
                                        {{ __('crm.no_dispatch_orders_found') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>

                <div class="pt-3">
                    <x-ui.pagination 
                        :currentPage="$dispatches->currentPage()" 
                        :totalPages="$dispatches->lastPage()" 
                        :totalResults="$dispatches->total()" 
                        :perPage="$dispatches->perPage()" />
                </div>
            </div>

        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            // Select all checkbox functionality
            $('#selectAllCheckbox').on('change', function() {
                $('.row-checkbox').prop('checked', $(this).is(':checked'));
            });
        });
    </script>
@endpush
