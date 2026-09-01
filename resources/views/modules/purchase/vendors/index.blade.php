@extends('layouts.duralux')

@section('title', 'Suppliers & Vendors | SaaS ERP')
@section('page-title', 'Suppliers Directory')
@section('breadcrumb', 'Supply Chain / Purchase / Vendors')

@section('page-actions')
    <x-ui.button href="{{ route('purchase.vendors.create') }}" variant="primary" icon="feather-plus">
        NEW SUPPLIER
    </x-ui.button>
@endsection

@section('content')

    @php
        $sortBy = request('sort_by', 'created_at');
        $sortOrder = request('sort_order', 'desc');
        $activeStatus = request('status');
    @endphp

    <div class="erp-single-panel bg-white p-4 rounded-3 border shadow-sm">
        {{-- 1. Header & Actions Toolbar (Matches Customer Listing Header 100%) --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h5 class="fw-bold text-dark mb-0">Supplier Directory</h5>

            <div class="d-flex align-items-center flex-wrap gap-2">
                <!-- Outside Search Box -->
                <form method="GET" action="{{ route('purchase.vendors.index') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1" style="height: 34px; min-width: 260px;">
                    @foreach(request()->except(['search', 'page']) as $k => $v)
                        @if(is_scalar($v) && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                    <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark" placeholder="Search supplier, email, phone..." value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                    @if(request('search'))
                        <a href="{{ route('purchase.vendors.index', request()->except(['search', 'page'])) }}" class="text-muted text-decoration-none ms-1" title="Clear Search">
                            <i class="feather-x fs-12"></i>
                        </a>
                    @endif
                </form>

                <!-- Sort Dropdown -->
                <x-ui.sort-dropdown label="SORT">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Latest Created</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Oldest Created</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Supplier Name (A - Z)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Supplier Name (Z - A)</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Filter Dropdown -->
                <form method="GET" action="{{ route('purchase.vendors.index') }}" class="d-inline">
                    @foreach(request()->except(['status', 'search', 'page']) as $k => $v)
                        @if(is_scalar($v) && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <x-ui.filter label="FILTER" offset="0, 5">
                        <div class="p-3 style-filter-menu" style="min-width: 250px;">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                                <x-ui.odoo-form-ui type="input" name="search" placeholder="Search supplier, email, phone..." value="{{ request('search') }}" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Supplier Status</label>
                                <x-ui.odoo-form-ui type="select" name="status">
                                    <option value="all" @selected(request('status', 'all') === 'all')>All Suppliers (Default)</option>
                                    <option value="active" @selected(request('status') === 'active')>Active Only</option>
                                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive Only</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="d-flex justify-content-between pt-2 border-top">
                                <a href="{{ route('purchase.vendors.index') }}" class="btn btn-xs btn-light">Reset</a>
                                <x-ui.button type="submit" variant="primary" size="xs">Apply Filter</x-ui.button>
                            </div>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        {{-- 2. Odoo Table --}}
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="vendorsTable" class="mb-0">
                <thead>
                    <tr style="background-color: #e8ecf1 !important;">
                        <th style="width: 35px; background-color: #e8ecf1 !important;" class="text-center">
                            <input type="checkbox" class="form-check-input" id="selectAllVendors">
                        </th>
                        <th style="background-color: #e8ecf1 !important;">Supplier Name</th>
                        <th style="background-color: #e8ecf1 !important;">Email Address</th>
                        <th style="background-color: #e8ecf1 !important;">Phone / Mobile</th>
                        <th style="background-color: #e8ecf1 !important;">Status</th>
                        <th style="width: 5%; background-color: #e8ecf1 !important;" class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vendors as $vendor)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input vendor-checkbox">
                            </td>
                            <td>
                                <div>
                                    <a href="{{ route('purchase.vendors.show', $vendor) }}" class="fw-bold text-dark hover-primary d-block">{{ $vendor->name }}</a>
                                    <div class="fs-11 text-muted">
                                        @if($vendor->code)
                                            <span class="font-monospace text-primary fw-semibold">Code: {{ $vendor->code }}</span>
                                        @endif
                                        @if($vendor->gstin)
                                            <span class="ms-1 font-monospace">| GST: {{ $vendor->gstin }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($vendor->email)
                                    <span class="text-dark"><i class="feather-mail me-1 text-muted fs-11"></i>{{ $vendor->email }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($vendor->phone)
                                    <span class="text-dark"><i class="feather-phone me-1 text-muted fs-11"></i>{{ $vendor->phone }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if (strtolower($vendor->status) === 'active')
                                    <x-ui.status-badge status="active" label="Active" size="sm" />
                                @else
                                    <x-ui.status-badge status="inactive" label="Inactive" size="sm" />
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <x-ui.action-dropdown :viewUrl="route('purchase.vendors.show', $vendor)">
                                    <li>
                                        <a href="{{ route('purchase.vendors.edit', $vendor) }}" class="dropdown-item fs-12 py-1.5">
                                            <i class="feather-edit-2 me-2 text-primary"></i>Edit Supplier
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider my-1"></li>
                                    <li>
                                        <form action="{{ route('purchase.vendors.toggle-status', $vendor) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="dropdown-item fs-12 py-1.5 text-{{ strtolower($vendor->status) === 'active' ? 'danger' : 'success' }}">
                                                <i class="feather-{{ strtolower($vendor->status) === 'active' ? 'user-x' : 'check-circle' }} me-2"></i>
                                                Mark as {{ strtolower($vendor->status) === 'active' ? 'Inactive' : 'Active' }}
                                            </button>
                                        </form>
                                    </li>
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="feather-truck fs-30 d-block mb-2 text-muted"></i>
                                No suppliers found.
                                <div class="mt-2">
                                    <a href="{{ route('purchase.vendors.create') }}" class="btn btn-sm btn-primary"><i class="feather-plus me-1"></i>New Supplier</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        @if($vendors->hasPages())
            <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                <span class="fs-12 text-muted">Showing {{ $vendors->firstItem() }} to {{ $vendors->lastItem() }} of {{ $vendors->total() }} suppliers</span>
                {{ $vendors->links() }}
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const selectAll = document.getElementById('selectAllVendors');
        const checkboxes = document.querySelectorAll('.vendor-checkbox');

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                checkboxes.forEach(cb => cb.checked = selectAll.checked);
            });
        }
    });
</script>
@endpush
