@extends('layouts.duralux')

@section('title', 'Material Requirements | SaaS ERP')
@section('page-title', 'Material Requirements')
@section('breadcrumb', 'Sales / Material Requirements')

@section('content')

    {{-- Session Alerts --}}
    @if (session('success'))
        <x-ui.alert variant="success" :dismissible="true" icon="feather-check-circle" class="shadow-sm mb-4">
            <strong>Success!</strong> {{ session('success') }}
        </x-ui.alert>
    @endif
    @if (session('error'))
        <x-ui.alert variant="danger" :dismissible="true" icon="feather-alert-triangle" class="shadow-sm mb-4">
            <strong>Error!</strong> {{ session('error') }}
        </x-ui.alert>
    @endif

    <div class="erp-single-panel">
        <!-- Toolbar: Sort, Filters -->
        <div class="d-flex align-items-center mb-3">
            <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold text-dark mb-0 me-2">Material Requirements</h5>
            </div>
            <div class="d-flex gap-2 ms-auto">
                <!-- Custom Sort Component -->
                <x-ui.sort-dropdown label="Sort">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requirement_date', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'requirement_date' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Newest Date First</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requirement_date', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'requirement_date' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Oldest Date First</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requirement_number', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'requirement_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Requirement # (A-Z)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requirement_number', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'requirement_number' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Requirement # (Z-A)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'customer', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'customer' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Customer Name (A-Z)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'customer', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'customer' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Customer Name (Z-A)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'status', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'status' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Status (A-Z)</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Custom Filter Component -->
                <form method="GET" action="{{ route('sales.material-requirements.index') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="Requirement #, SO #, Customer..." value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">All Statuses</option>
                                @foreach(['Pending', 'Processing', 'Picked', 'Packed', 'Ready', 'Dispatched', 'Delivered', 'Cancelled'] as $st)
                                    <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>{{ $st }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Date From</label>
                                <x-ui.odoo-form-ui type="input" inputType="date" name="date_from" value="{{ request('date_from') }}" />
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Date To</label>
                                <x-ui.odoo-form-ui type="input" inputType="date" name="date_to" value="{{ request('date_to') }}" />
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('sales.material-requirements.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
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
                                Requirement Number
                                @if($sortBy === 'requirement_number')
                                    <i class="feather-chevron-{{ $sortOrder === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </a>
                        </th>
                        <th>Sales Order</th>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'customer', 'sort_order' => ($sortBy === 'customer' && $sortOrder === 'asc') ? 'desc' : 'asc']) }}" class="text-muted text-decoration-none d-flex align-items-center gap-1">
                                Customer
                                @if($sortBy === 'customer')
                                    <i class="feather-chevron-{{ $sortOrder === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </a>
                        </th>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'requirement_date', 'sort_order' => ($sortBy === 'requirement_date' && $sortOrder === 'asc') ? 'desc' : 'asc']) }}" class="text-muted text-decoration-none d-flex align-items-center gap-1">
                                Requirement Date
                                @if($sortBy === 'requirement_date')
                                    <i class="feather-chevron-{{ $sortOrder === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </a>
                        </th>
                        <th>Carrier</th>
                        <th>Tracking</th>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'status', 'sort_order' => ($sortBy === 'status' && $sortOrder === 'asc') ? 'desc' : 'asc']) }}" class="text-muted text-decoration-none d-flex align-items-center gap-1">
                                Status
                                @if($sortBy === 'status')
                                    <i class="feather-chevron-{{ $sortOrder === 'asc' ? 'up' : 'down' }} text-primary"></i>
                                @endif
                            </a>
                        </th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-dark">
                    @forelse ($deliveries as $do)
                        @php
                            $badgeVariant = 'secondary';
                            if (in_array($do->status, ['Ready', 'Delivered']))              $badgeVariant = 'success';
                            elseif (in_array($do->status, ['Partially Ready', 'Processing'])) $badgeVariant = 'info';
                            elseif (in_array($do->status, ['Picked', 'Packed']))             $badgeVariant = 'primary';
                            elseif ($do->status === 'Dispatched')                            $badgeVariant = 'dark';
                            elseif ($do->status === 'Cancelled')                             $badgeVariant = 'danger';
                        @endphp
                        <tr>
                            <td class="ps-4">
                                <a href="{{ route('sales.material-requirements.show', $do->id) }}" class="fw-bold text-primary">
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
                                <x-ui.action-dropdown :viewUrl="route('sales.material-requirements.show', $do->id)">
                                    <x-ui.dropdown-item href="{{ route('sales.material-requirements.show', $do->id) }}" icon="feather-eye">
                                        View Details
                                    </x-ui.dropdown-item>
                                    @if ($canInvoice && !$invoiced)
                                        <x-ui.dropdown-item href="{{ route('sales.invoices.create', ['material_requirement_id' => $do->id]) }}" icon="feather-file-text">
                                            Create Invoice
                                        </x-ui.dropdown-item>
                                    @endif
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="feather-clipboard fs-1 d-block text-muted mb-2"></i>
                                <span class="text-muted fs-13">No material requirements found matching your criteria.</span>
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
