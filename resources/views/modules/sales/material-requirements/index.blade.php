@extends('layouts.duralux')

@section('title', __('crm.material_requirements') . ' | SaaS ERP')
@section('page-title', __('crm.material_requirements'))
@section('breadcrumb', __('ui.inventory') . ' / ' . __('crm.material_requirements'))

@section('content')

    <div class="erp-single-panel">
        <!-- Toolbar: Sort, Filters -->
        <div class="d-flex align-items-center mb-3">
            <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold text-dark mb-0 me-2">{{ __('crm.material_requirements') }}</h5>
            </div>
            <div class="d-flex gap-2 ms-auto">
                <!-- Custom Sort Component -->
                <x-ui.sort-dropdown :label="__('crm.sort') ?: 'Sort'">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requirement_date', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'requirement_date' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('crm.newest_date_first') ?: 'Newest Date First' }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requirement_date', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'requirement_date' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('crm.oldest_date_first') ?: 'Oldest Date First' }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requirement_number', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'requirement_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('crm.requirement_num_az') ?: 'Requirement # (A-Z)' }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requirement_number', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'requirement_number' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('crm.requirement_num_za') ?: 'Requirement # (Z-A)' }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'customer', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'customer' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('crm.customer_name_az') ?: 'Customer Name (A-Z)' }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'customer', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'customer' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('crm.customer_name_za') ?: 'Customer Name (Z-A)' }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'status', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'status' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('crm.status_az') ?: 'Status (A-Z)' }}</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Custom Filter Component -->
                <form method="GET" action="{{ route('inventory.material-requirements.index') }}" class="d-inline">
                    <x-ui.filter :label="__('crm.filter') ?: 'Filter'" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('crm.filter_options') ?: 'Filter Options' }}</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.search_keywords') ?: 'Search Keywords' }}</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="Requirement #, SO #, Customer..." value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.status') ?: 'Status' }}</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">{{ __('crm.all_statuses') ?: 'All Statuses' }}</option>
                                @foreach(['Pending', 'Processing', 'Picked', 'Packed', 'Ready', 'Dispatched', 'Delivered', 'Cancelled'] as $st)
                                    <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>{{ $st }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.date_from') ?: 'Date From' }}</label>
                                <x-ui.odoo-form-ui type="input" inputType="date" name="date_from" value="{{ request('date_from') }}" />
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.date_to') ?: 'Date To' }}</label>
                                <x-ui.odoo-form-ui type="input" inputType="date" name="date_to" value="{{ request('date_to') }}" />
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('inventory.material-requirements.index') }}" class="btn btn-sm btn-light border">{{ __('crm.reset') ?: 'Reset' }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('crm.apply_filters') ?: 'Apply Filters' }}</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <x-ui.odoo-form-ui type="sheet" class="p-0">

        {{-- Table --}}
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" class="align-middle fs-13 mb-0" style="margin-top:0; border-radius:0;">
                <thead class="fs-11 text-uppercase fw-semibold text-muted bg-light">
                    <tr>
                        <th class="ps-4">
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requirement_number', 'sort_order' => ($sortBy === 'requirement_number' && $sortOrder === 'asc') ? 'desc' : 'asc']) }}" class="text-muted text-decoration-none d-flex align-items-center gap-1">
                                {{ __('crm.requirement_number') ?: 'Requirement Number' }}
                                @if($sortBy === 'requirement_number')
                                    <i class="feather-chevron-{{ $sortOrder === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </a>
                        </th>
                        <th>{{ __('crm.sales_order') ?: 'Sales Order' }}</th>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'customer', 'sort_order' => ($sortBy === 'customer' && $sortOrder === 'asc') ? 'desc' : 'asc']) }}" class="text-muted text-decoration-none d-flex align-items-center gap-1">
                                {{ __('crm.customer') ?: 'Customer' }}
                                @if($sortBy === 'customer')
                                    <i class="feather-chevron-{{ $sortOrder === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </a>
                        </th>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requirement_date', 'sort_order' => ($sortBy === 'requirement_date' && $sortOrder === 'asc') ? 'desc' : 'asc']) }}" class="text-muted text-decoration-none d-flex align-items-center gap-1">
                                {{ __('crm.requirement_date') ?: 'Requirement Date' }}
                                @if($sortBy === 'requirement_date')
                                    <i class="feather-chevron-{{ $sortOrder === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </a>
                        </th>
                        <th>{{ __('crm.carrier') ?: 'Carrier' }}</th>
                        <th>{{ __('crm.tracking') ?: 'Tracking' }}</th>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'status', 'sort_order' => ($sortBy === 'status' && $sortOrder === 'asc') ? 'desc' : 'asc']) }}" class="text-muted text-decoration-none d-flex align-items-center gap-1">
                                {{ __('crm.status') ?: 'Status' }}
                                @if($sortBy === 'status')
                                    <i class="feather-chevron-{{ $sortOrder === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </a>
                        </th>
                        <th class="text-end pe-4">{{ __('crm.actions') ?: 'Actions' }}</th>
                    </tr>
                </thead>
                <tbody class="text-dark">
                    @forelse ($deliveries as $do)
                        @php
                            $badgeVariant = 'warning';
                            if (in_array($do->status, ['Ready', 'Delivered']))              $badgeVariant = 'success';
                            elseif (in_array($do->status, ['Partially Ready', 'Processing'])) $badgeVariant = 'info';
                            elseif (in_array($do->status, ['Picked', 'Packed']))             $badgeVariant = 'primary';
                            elseif ($do->status === 'Pending' || $do->status === 'Draft')    $badgeVariant = 'warning';
                            elseif ($do->status === 'Dispatched')                            $badgeVariant = 'dark';
                            elseif ($do->status === 'Cancelled')                             $badgeVariant = 'danger';
                        @endphp
                        <tr>
                            <td class="ps-4">
                                <a href="{{ route('inventory.material-requirements.show', $do->id) }}" class="fw-bold text-primary">
                                    {{ $do->requirement_number }}
                                </a>
                            </td>
                            <td>
                                @if($do->salesOrder)
                                    <a href="{{ route('sales.orders.show', $do->sales_order_id) }}" class="fw-semibold text-dark">
                                        {{ $do->salesOrder->sales_order_number }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="fw-semibold">{{ $do->salesOrder?->customer?->name ?? $do->salesOrder?->customer?->company_name ?? '—' }}</td>
                            <td class="text-muted">{{ $do->requirement_date ? $do->requirement_date->format('d/m/Y') : '—' }}</td>
                            <td class="text-muted">{{ $do->carrier ?: '—' }}</td>
                            <td class="text-muted font-monospace fs-12">{{ $do->tracking_number ?: '—' }}</td>
                            <td>
                                <x-ui.badge :soft="true" :variant="$badgeVariant" class="fs-11 px-2">
                                    {{ $do->status }}
                                </x-ui.badge>
                            </td>
                            <td class="text-end pe-4">
                                @php
                                    $invoiced      = $do->salesOrder?->invoices->where('material_requirement_id', $do->id)->first();
                                    $invoicePolicy = config('sales.invoice_policy', 'On Dispatch');
                                    $canInvoice    = ($invoicePolicy === 'On Dispatch')
                                        ? in_array($do->status, ['Dispatched', 'Delivered', 'Shipped'])
                                        : ($do->status === 'Delivered');
                                @endphp
                                <x-ui.action-dropdown :viewUrl="route('inventory.material-requirements.show', $do->id)">
                                    <x-ui.dropdown-item href="{{ route('inventory.material-requirements.show', $do->id) }}" icon="feather-eye">
                                        {{ __('crm.view_details') ?: 'View Details' }}
                                    </x-ui.dropdown-item>
                                    @if ($canInvoice && !$invoiced)
                                        <x-ui.dropdown-item href="{{ route('sales.invoices.create', ['material_requirement_id' => $do->id]) }}" icon="feather-file-text">
                                            {{ __('crm.create_invoice') ?: 'Create Invoice' }}
                                        </x-ui.dropdown-item>
                                    @endif
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="feather-clipboard fs-1 d-block text-muted mb-2"></i>
                                <span class="text-muted fs-13">{{ __('crm.no_material_requirements_found') ?: 'No material requirements found matching your criteria.' }}</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        {{-- Pagination --}}
        <div class="pt-3 px-4 pb-3">
            <x-ui.pagination 
                :currentPage="$deliveries->currentPage()" 
                :totalPages="$deliveries->lastPage()" 
                :totalResults="$deliveries->total()" 
                :perPage="$deliveries->perPage()" />
        </div>

    </x-ui.odoo-form-ui>
    </div>

@endsection
