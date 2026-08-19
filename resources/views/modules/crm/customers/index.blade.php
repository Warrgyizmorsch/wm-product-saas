@extends('layouts.duralux')

@section('title', 'Customers | SaaS ERP')
@section('page-title', 'Customers Directory')
@section('breadcrumb', 'CRM / Customers')

@section('page-actions')
    <x-ui.button href="{{ route('crm.customers.create') }}" variant="primary" icon="feather-plus">
        New Customer
    </x-ui.button>
@endsection

@section('content')

    @php
        $sortBy = request('sort_by', 'created_at');
        $sortOrder = request('sort_order', 'desc');
        $activeStatus = request('status');
    @endphp

    <div class="erp-single-panel">
        {{-- 1. Header & Actions Toolbar (Matches Leads Listing Header 100%) --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h5 class="fw-bold text-dark mb-0">Customer Directory</h5>

            <div class="d-flex align-items-center flex-wrap gap-2">
                <!-- Outside Search Box (HRMS & Leads Style) -->
                <form method="GET" action="{{ route('crm.customers.index') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1" style="height: 34px; min-width: 240px;">
                    @foreach(request()->except(['search', 'page']) as $k => $v)
                        @if(is_scalar($v) && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                    <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark" placeholder="Search customer, email, phone..." value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                    @if(request('search'))
                        <a href="{{ route('crm.customers.index', request()->except(['search', 'page'])) }}" class="text-muted text-decoration-none ms-1" title="Clear Search">
                            <i class="feather-x fs-12"></i>
                        </a>
                    @endif
                </form>


                <!-- Sort Dropdown -->
                <x-ui.sort-dropdown label="Sort">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Latest Created</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Oldest Created</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Customer Name (A - Z)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Customer Name (Z - A)</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Filter Dropdown -->
                <form method="GET" action="{{ route('crm.customers.index') }}" class="d-inline">
                    @foreach(request()->except(['status', 'search', 'page']) as $k => $v)
                        @if(is_scalar($v) && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <x-ui.filter label="Filter" offset="0, 5">
                        <div class="p-3 style-filter-menu" style="min-width: 250px;">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                                <x-ui.odoo-form-ui type="input" name="search" placeholder="Search customer, email, phone..." value="{{ request('search') }}" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Customer Status</label>
                                <x-ui.odoo-form-ui type="select" name="status">
                                    <option value="all" @selected(request('status', 'all') === 'all')>All Customers (Default)</option>
                                    <option value="active" @selected(request('status') === 'active')>Active Only</option>
                                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive Only</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="d-flex justify-content-between pt-2 border-top">
                                <a href="{{ route('crm.customers.index') }}" class="btn btn-xs btn-light">Reset</a>
                                <x-ui.button type="submit" variant="primary" size="xs">Apply Filter</x-ui.button>
                            </div>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        {{-- 2. Odoo Table --}}
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="customersTable" class="mb-0">
                <thead>
                    <tr style="background-color: #e8ecf1 !important;">
                        <th style="width: 35px; background-color: #e8ecf1 !important;" class="text-center">
                            <input type="checkbox" class="form-check-input" id="selectAllCustomers">
                        </th>
                        <th style="background-color: #e8ecf1 !important;">Customer Name</th>
                        <th style="background-color: #e8ecf1 !important;">Email Address</th>
                        <th style="background-color: #e8ecf1 !important;">Phone / Mobile</th>
                        <th style="background-color: #e8ecf1 !important;">Status</th>
                        <th style="width: 5%; background-color: #e8ecf1 !important;" class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input customer-checkbox">
                            </td>
                            <td>
                                <div>
                                    <a href="{{ route('crm.customers.show', $customer) }}" class="fw-bold text-dark hover-primary d-block">{{ $customer->name }}</a>
                                    @if($customer->gstin)
                                        <span class="fs-10 font-monospace text-muted">GST: {{ $customer->gstin }}</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if($customer->email)
                                    <span class="text-dark"><i class="feather-mail me-1 text-muted fs-11"></i>{{ $customer->email }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($customer->phone)
                                    <span class="text-dark"><i class="feather-phone me-1 text-muted fs-11"></i>{{ $customer->phone }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if (strtolower($customer->status) === 'active')
                                    <x-ui.status-badge status="active" label="Active" size="sm" />
                                @else
                                    <x-ui.status-badge status="inactive" label="Inactive" size="sm" />
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <x-ui.action-dropdown :viewUrl="route('crm.customers.show', $customer)">
                                    @if (strtolower($customer->status) === 'active')
                                        <li>
                                            <a href="{{ route('crm.customers.toggleStatus', [$customer, 'status' => 'inactive']) }}" class="dropdown-item fs-12 py-1.5 text-danger">
                                                <i class="feather-user-x me-2 text-danger"></i>Mark as Inactive
                                            </a>
                                        </li>
                                    @else
                                        <li>
                                            <a href="{{ route('crm.customers.toggleStatus', [$customer, 'status' => 'active']) }}" class="dropdown-item fs-12 py-1.5 text-success">
                                                <i class="feather-user-check me-2 text-success"></i>Mark as Active
                                            </a>
                                        </li>
                                    @endif
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="feather-users display-6 mb-2 text-muted opacity-50 d-block"></i>
                                No customers found matching your criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        {{-- 4. Pagination --}}
        @if($customers->hasPages())
            <div class="mt-3">
                <x-ui.pagination 
                    :currentPage="$customers->currentPage()" 
                    :totalPages="$customers->lastPage()" 
                    :totalResults="$customers->total()" 
                    :perPage="$customers->perPage()" 
                />
            </div>
        @endif
    </div>

    @push('scripts')
    <script>
        document.getElementById('selectAllCustomers')?.addEventListener('change', function() {
            document.querySelectorAll('.customer-checkbox').forEach(cb => cb.checked = this.checked);
        });
    </script>
    @endpush
@endsection
