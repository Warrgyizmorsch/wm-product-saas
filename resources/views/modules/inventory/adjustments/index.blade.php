@extends('layouts.duralux')

@section('title', 'Stock Adjustments | SaaS ERP')
@section('page-title', 'Stock Adjustments')
@section('breadcrumb', 'Inventory / Stock Adjustments')

@section('page-actions')
    <x-ui.button href="{{ route('inventory.adjustments.create') }}" variant="primary" icon="feather-plus">
        New Adjustment
    </x-ui.button>
@endsection

@section('content')

    @php
        $sortBy = request('sort_by', 'created_at');
        $sortOrder = request('sort_order', 'desc');
    @endphp

    <div class="erp-single-panel">

        <!-- Toolbar: Tabs, Sort & Filters (Matching Lead Module Standards) -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <h5 class="fw-bold text-dark mb-0 me-1">Stock Adjustments Listing</h5>
                <a href="{{ request()->fullUrlWithQuery(['status' => null]) }}" class="btn btn-xs {{ !request('status') ? 'btn-primary' : 'btn-light border' }}">
                    All Adjustments
                </a>
                <a href="{{ request()->fullUrlWithQuery(['status' => 'Draft']) }}" class="btn btn-xs {{ request('status') === 'Draft' ? 'btn-warning text-white' : 'btn-soft-warning text-warning' }}">
                    Draft
                </a>
                <a href="{{ request()->fullUrlWithQuery(['status' => 'Approved']) }}" class="btn btn-xs {{ request('status') === 'Approved' ? 'btn-success text-white' : 'btn-soft-success text-success' }}">
                    Approved
                </a>
                <a href="{{ request()->fullUrlWithQuery(['status' => 'Cancelled']) }}" class="btn btn-xs {{ request('status') === 'Cancelled' ? 'btn-danger text-white' : 'btn-soft-danger text-danger' }}">
                    Cancelled
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
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'adjustment_number', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'adjustment_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Adjustment # (A-Z)</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Custom Filter Component -->
                <form method="GET" action="{{ route('inventory.adjustments.index') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="Adjustment # or reason..." value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">All Statuses</option>
                                <option value="Draft" {{ request('status') === 'Draft' ? 'selected' : '' }}>Draft</option>
                                <option value="Approved" {{ request('status') === 'Approved' ? 'selected' : '' }}>Approved</option>
                                <option value="Cancelled" {{ request('status') === 'Cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('inventory.adjustments.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- Adjustments Table -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="adjustmentsTable">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input">
                        </th>
                        <th>Adjustment #</th>
                        <th>Warehouse</th>
                        <th>Adjustment Date</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($adjustments as $adjustment)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input">
                            </td>
                            <td>
                                <a href="{{ route('inventory.adjustments.show', $adjustment->id) }}" class="fw-bold text-primary">
                                    {{ $adjustment->adjustment_number }}
                                </a>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark">{{ $adjustment->warehouse?->name ?? 'N/A' }}</span>
                            </td>
                            <td>
                                <span class="text-muted fs-12">{{ $adjustment->adjustment_date ? \Carbon\Carbon::parse($adjustment->adjustment_date)->format('d M Y') : 'N/A' }}</span>
                            </td>
                            <td>
                                <span class="badge bg-soft-secondary text-dark border">{{ $adjustment->reason }}</span>
                            </td>
                            <td>
                                <x-ui.badge :variant="$adjustment->status === 'Approved' ? 'success' : ($adjustment->status === 'Cancelled' ? 'danger' : 'warning')">
                                    {{ $adjustment->status }}
                                </x-ui.badge>
                            </td>
                            <td>
                                <span class="text-muted fs-12">{{ $adjustment->creator?->name ?? 'Admin' }}</span>
                            </td>
                            <td class="text-end pe-4">
                                <x-ui.action-dropdown :viewUrl="route('inventory.adjustments.show', $adjustment->id)">
                                    @if($adjustment->status === 'Draft')
                                        <li>
                                            <form action="{{ route('inventory.adjustments.approve', $adjustment->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-success fw-semibold">
                                                    <i class="feather-check-circle me-2 text-success fs-12"></i>Approve & Apply
                                                </button>
                                            </form>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form action="{{ route('inventory.adjustments.cancel', $adjustment->id) }}" method="POST" id="cancelAdjForm_{{ $adjustment->id }}">
                                                @csrf
                                                <button type="button" class="dropdown-item text-danger fw-semibold" onclick="if(confirm('Cancel this adjustment?')) document.getElementById('cancelAdjForm_{{ $adjustment->id }}').submit();">
                                                    <i class="feather-x-circle me-2 text-danger fs-12"></i>Cancel Adjustment
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
                                <i class="feather-box fs-1 d-block mb-3 text-light"></i>
                                No Stock Adjustments found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <!-- Pagination Section (Matching Lead Module Component) -->
        <div class="pt-3">
            <x-ui.pagination 
                :currentPage="$adjustments->currentPage()" 
                :totalPages="$adjustments->lastPage()" 
                :totalResults="$adjustments->total()" 
                :perPage="$adjustments->perPage()" />
        </div>
    </div>
@endsection
