@extends('layouts.duralux')

@section('title', 'Stock Reservations | SaaS ERP')
@section('page-title', 'Active Stock Reservations')
@section('breadcrumb', 'Inventory / Stock Reservations')

@section('content')

    @php
        $sortBy = request('sort_by', 'created_at');
        $sortOrder = request('sort_order', 'desc');
    @endphp

    <div class="erp-single-panel">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <!-- Toolbar: Tabs, Sort & Filters (Matching Lead Module Standards) -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <h5 class="fw-bold text-dark mb-0 me-1">Stock Reservations</h5>
                <a href="{{ request()->fullUrlWithQuery(['status' => null]) }}" class="btn btn-xs {{ !request('status') ? 'btn-primary' : 'btn-light border' }}">
                    All Reservations
                </a>
                <a href="{{ request()->fullUrlWithQuery(['status' => 'Active']) }}" class="btn btn-xs {{ request('status', 'Active') === 'Active' ? 'btn-warning text-white' : 'btn-soft-warning text-warning' }}">
                    Active
                </a>
                <a href="{{ request()->fullUrlWithQuery(['status' => 'Completed']) }}" class="btn btn-xs {{ request('status') === 'Completed' ? 'btn-success text-white' : 'btn-soft-success text-success' }}">
                    Released
                </a>
                <a href="{{ request()->fullUrlWithQuery(['status' => 'Expired']) }}" class="btn btn-xs {{ request('status') === 'Expired' ? 'btn-danger text-white' : 'btn-soft-danger text-danger' }}">
                    Expired
                </a>
            </div>

            <div class="d-flex align-items-center flex-wrap gap-2">
                <!-- Custom Sort Component -->
                <x-ui.sort-dropdown label="Sort">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Latest Created</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Oldest Created</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'reserved_qty', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'reserved_qty' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Highest Reserved Qty</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- System Filter Component -->
                <form method="GET" action="{{ route('inventory.reservations.index') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Reservations</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Product</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="Product name or SKU..." value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">All Statuses</option>
                                <option value="Active" {{ request('status') === 'Active' ? 'selected' : '' }}>Active Reservations</option>
                                <option value="Completed" {{ request('status') === 'Completed' ? 'selected' : '' }}>Completed / Released</option>
                                <option value="Expired" {{ request('status') === 'Expired' ? 'selected' : '' }}>Expired</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('inventory.reservations.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- Reservations Table -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="stockReservationsTable">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input">
                        </th>
                        <th>Product Name</th>
                        <th>Warehouse</th>
                        <th class="text-end">Reserved Qty</th>
                        <th>Reference Doc</th>
                        <th>Expires At</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reservations as $res)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input">
                            </td>
                            <td>
                                <strong class="text-dark d-block">{{ $res->product->name ?? 'N/A' }}</strong>
                                <small class="text-muted">SKU: {{ $res->product->sku ?? '-' }}</small>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark">{{ $res->warehouse->name ?? 'N/A' }}</span>
                            </td>
                            <td class="text-end fw-bold text-warning">
                                {{ number_format($res->reserved_qty, 2) }}
                            </td>
                            <td>
                                <x-ui.badge variant="light" class="border">
                                    {{ $res->reference_type }} #{{ $res->reference_id }}
                                </x-ui.badge>
                            </td>
                            <td>
                                <span class="text-muted fs-12">
                                    {{ $res->expires_at ? \Carbon\Carbon::parse($res->expires_at)->format('d M Y, h:i A') : 'No Expiry' }}
                                </span>
                            </td>
                            <td>
                                <x-ui.badge :variant="$res->status === 'Active' ? 'warning' : ($res->status === 'Completed' ? 'success' : 'danger')">
                                    {{ $res->status }}
                                </x-ui.badge>
                            </td>
                            <td class="text-end pe-4">
                                <x-ui.action-dropdown>
                                    @if($res->status === 'Active')
                                        <li>
                                            <form action="{{ route('inventory.reservations.release', $res->id) }}" method="POST" id="releaseForm_{{ $res->id }}">
                                                @csrf
                                                <button type="button" class="dropdown-item text-danger fw-semibold" onclick="if(confirm('Are you sure you want to release this stock reservation?')) document.getElementById('releaseForm_{{ $res->id }}').submit();">
                                                    <i class="feather-unlock me-2 text-danger fs-12"></i>Release Stock
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="feather-lock fs-1 d-block mb-3 text-light"></i>
                                No stock reservations found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <!-- Pagination Section (Matching Lead Module Component) -->
        <div class="pt-3">
            <x-ui.pagination 
                :currentPage="$reservations->currentPage()" 
                :totalPages="$reservations->lastPage()" 
                :totalResults="$reservations->total()" 
                :perPage="$reservations->perPage()" />
        </div>
    </div>
@endsection
