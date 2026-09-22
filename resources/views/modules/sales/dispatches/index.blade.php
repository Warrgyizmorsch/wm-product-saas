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
    @endphp

    <div class="erp-single-panel">

        <!-- Toolbar: Title, Sort, Filter Drawer -->
        <div class="d-flex align-items-center mb-3">
            <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold text-dark mb-0 me-2">{{ __('crm.all_dispatch_orders') }}</h5>
            </div>
            <div class="d-flex gap-2 ms-auto">
                <!-- Custom Sort Component (Lead style) -->
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

                <!-- Custom Filter Component (Lead style) -->
                <form method="GET" action="{{ route('sales.dispatches.index') }}" class="d-inline">
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
                                <option value="Dispatched" {{ request('status') === 'Dispatched' ? 'selected' : '' }}>Dispatched</option>
                                <option value="Delivered" {{ request('status') === 'Delivered' ? 'selected' : '' }}>Delivered</option>
                                <option value="Cancelled" {{ request('status') === 'Cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('sales.dispatches.index') }}" class="btn btn-sm btn-light border">{{ __('crm.reset') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('crm.apply_filters') }}</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- Dispatches List Table (Lead style components) -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="dispatchTable">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input" id="selectAllCheckbox">
                        </th>
                        <th>{{ __('crm.dispatch_date_and_num') }}</th>
                        <th>{{ __('crm.material_requirement') }}</th>
                        <th>{{ __('crm.sales_order') }}</th>
                        <th>{{ __('crm.customer') }}</th>
                        <th>{{ __('crm.carrier_vehicle') }}</th>
                        <th>{{ __('crm.status') }}</th>
                        <th class="text-end pe-4">{{ __('crm.actions') }}</th>
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
                                            <i class="feather-calendar me-1 fs-10"></i>{{ $dispatch->dispatch_date ? date('d/m/Y', strtotime($dispatch->dispatch_date)) : 'N/A' }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if ($dispatch->materialRequirement)
                                    <a href="{{ route('sales.material-requirements.show', $dispatch->material_requirement_id) }}" class="fw-semibold text-dark">
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
@endsection

@push('scripts')
    <script>
        $(function () {
            // Select all checkbox functionality
            $('#selectAllCheckbox').on('change', function() {
                $('.row-checkbox').prop('checked', $(this).is(':checked'));
            });

            // Live Search filter for the Dispatch table
            $('#tableSearch').on('input', function() {
                var value = $(this).val().toLowerCase().trim();
                var visibleRows = 0;
                var totalRows = 0;

                $('#dispatchTable tbody tr').each(function() {
                    if ($(this).hasClass('no-search-results')) return;
                    totalRows++;
                    var rowText = $(this).text().toLowerCase();
                    if (rowText.indexOf(value) > -1) {
                        $(this).show();
                        visibleRows++;
                    } else {
                        $(this).hide();
                    }
                });

                $('#dispatchTable tbody tr.no-search-results').remove();

                if (visibleRows === 0 && totalRows > 0) {
                    $('#dispatchTable tbody').append(
                        '<tr class="no-search-results"><td colspan="8" class="text-center py-4 text-muted"><i class="feather-search fs-3 d-block mb-2 text-light"></i>{{ __('crm.no_dispatch_orders_matching', ['query' => '']) }}"' + value + '"</td></tr>'
                    );
                }
            });
        });
    </script>
@endpush
