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

    <div class="erp-single-panel text-dark">
        <x-ui.odoo-form-ui type="sheet">

            <!-- Toolbar: Tabs, Sort & Filters -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4 pb-3 border-bottom">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <h5 class="fw-bold text-dark mb-0 me-2">Stock Adjustments Listing</h5>
                    <a href="{{ request()->fullUrlWithQuery(['status' => null]) }}" class="btn btn-xs {{ !request('status') ? 'btn-dark text-white fw-bold' : 'btn-light text-muted border' }}">
                        All Adjustments
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['status' => 'Draft']) }}" class="btn btn-xs {{ request('status') === 'Draft' ? 'btn-soft-secondary text-secondary border border-secondary-subtle fw-bold' : 'btn-light text-muted border' }}">
                        Draft
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['status' => 'Approved']) }}" class="btn btn-xs {{ request('status') === 'Approved' ? 'btn-soft-success text-success border border-success-subtle fw-bold' : 'btn-light text-muted border' }}">
                        Approved
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['status' => 'Cancelled']) }}" class="btn btn-xs {{ request('status') === 'Cancelled' ? 'btn-soft-danger text-danger border border-danger-subtle fw-bold' : 'btn-light text-muted border' }}">
                        Cancelled
                    </a>
                </div>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <!-- Quick Search (HRMS Common Component Style) -->
                    <form method="GET" action="{{ route('inventory.adjustments.index') }}" class="d-flex align-items-center bg-light border rounded px-3 py-1">
                        @if(request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
                        
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input 
                            type="text" 
                            name="search" 
                            class="form-control border-0 bg-transparent p-0 fs-13" 
                            placeholder="Adjustment # or reason..." 
                            value="{{ request('search') }}"
                            style="box-shadow: none; height: 32px; width: 220px;"
                        >
                    </form>

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
                    <thead class="table-light bg-light">
                        <tr>
                            <th style="width: 3%" class="text-center">
                                <input type="checkbox" class="form-check-input">
                            </th>
                            <th>Adjustment #</th>
                            <th>Warehouse</th>
                            <th>Adjustment Date</th>
                            <th>Reason</th>
                            <th class="text-center">Status</th>
                            <th>Created By</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-dark">
                        @forelse ($adjustments as $adjustment)
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input">
                                </td>
                                <td>
                                    <a href="{{ route('inventory.adjustments.show', $adjustment->id) }}" class="fw-bold text-primary text-decoration-none">
                                        <i class="feather-sliders text-primary me-1 fs-12"></i>{{ $adjustment->adjustment_number }}
                                    </a>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark fs-12">
                                        <i class="feather-map-pin text-muted me-1 fs-11"></i>{{ $adjustment->warehouse?->name ?? 'N/A' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted fs-12">
                                        <i class="feather-calendar me-1 text-muted fs-11"></i>{{ $adjustment->adjustment_date ? \Carbon\Carbon::parse($adjustment->adjustment_date)->format('d M Y') : 'N/A' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace fs-11">{{ $adjustment->reason }}</span>
                                </td>
                                <td class="text-center">
                                    <x-ui.status-badge :status="$adjustment->status" size="sm" />
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
    </x-ui.odoo-form-ui>
</div>
@endsection
