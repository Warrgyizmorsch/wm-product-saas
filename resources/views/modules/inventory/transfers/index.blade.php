@extends('layouts.duralux')

@section('title', 'Stock Transfers | SaaS ERP')
@section('page-title', 'Stock Transfers')
@section('breadcrumb', 'Inventory / Stock Transfers')

@section('page-actions')
    <x-ui.button href="{{ route('inventory.transfers.create') }}" variant="primary" icon="feather-plus">
        New Transfer
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
                    <h5 class="fw-bold text-dark mb-0 me-2">Stock Transfers Listing</h5>
                    <a href="{{ request()->fullUrlWithQuery(['status' => null]) }}" class="btn btn-xs {{ !request('status') ? 'btn-dark text-white fw-bold' : 'btn-light text-muted border' }}">
                        All Transfers
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['status' => 'Draft']) }}" class="btn btn-xs {{ request('status') === 'Draft' ? 'btn-soft-secondary text-secondary border border-secondary-subtle fw-bold' : 'btn-light text-muted border' }}">
                        Draft
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['status' => 'In Transit']) }}" class="btn btn-xs {{ request('status') === 'In Transit' ? 'btn-soft-info text-info border border-info-subtle fw-bold' : 'btn-light text-muted border' }}">
                        In-Transit
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['status' => 'Completed']) }}" class="btn btn-xs {{ request('status') === 'Completed' ? 'btn-soft-success text-success border border-success-subtle fw-bold' : 'btn-light text-muted border' }}">
                        Completed
                    </a>
                </div>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <!-- Quick Search (HRMS Common Component Style) -->
                    <form method="GET" action="{{ route('inventory.transfers.index') }}" class="d-flex align-items-center bg-light border rounded px-3 py-1">
                        @if(request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
                        @if(request('warehouse_id')) <input type="hidden" name="warehouse_id" value="{{ request('warehouse_id') }}"> @endif
                        
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input 
                            type="text" 
                            name="search" 
                            class="form-control border-0 bg-transparent p-0 fs-13" 
                            placeholder="Transfer # or keyword..." 
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
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'transfer_number', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'transfer_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                            <span>Transfer # (A-Z)</span>
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'transfer_date', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'transfer_date' && $sortOrder === 'desc' ? 'active' : '' }}">
                            <span>Transfer Date</span>
                        </a>
                    </x-ui.sort-dropdown>

                    <!-- Custom Filter Component -->
                    <form method="GET" action="{{ route('inventory.transfers.index') }}" class="d-inline">
                        <x-ui.filter label="Filter" offset="0, 5">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                                <x-ui.odoo-form-ui type="select" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="Draft" {{ request('status') === 'Draft' ? 'selected' : '' }}>Draft</option>
                                    <option value="In Transit" {{ request('status') === 'In Transit' ? 'selected' : '' }}>In Transit</option>
                                    <option value="Completed" {{ request('status') === 'Completed' ? 'selected' : '' }}>Completed</option>
                                    <option value="Cancelled" {{ request('status') === 'Cancelled' ? 'selected' : '' }}>Cancelled</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Warehouse</label>
                                <x-ui.odoo-form-ui type="select" name="warehouse_id">
                                    <option value="">All Warehouses</option>
                                    @foreach($warehouses as $wh)
                                        <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <a href="{{ route('inventory.transfers.index') }}" class="btn btn-sm btn-light border">Reset</a>
                                <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                            </div>
                        </x-ui.filter>
                    </form>
                </div>
            </div>

            <!-- Stock Transfers Table -->
            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table" id="transfersTable">
                    <thead class="table-light bg-light">
                        <tr>
                            <th style="width: 3%" class="text-center">
                                <input type="checkbox" class="form-check-input">
                            </th>
                            <th>Transfer #</th>
                            <th>From Warehouse</th>
                            <th>To Warehouse</th>
                            <th>Transfer Date</th>
                            <th class="text-center">Status</th>
                            <th>Created By</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-dark">
                        @forelse ($transfers as $transfer)
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input">
                                </td>
                                <td>
                                    <a href="{{ route('inventory.transfers.show', $transfer->id) }}" class="fw-bold text-primary text-decoration-none">
                                        <i class="feather-box text-primary me-1 fs-12"></i>{{ $transfer->transfer_number }}
                                    </a>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark fs-12">
                                        <i class="feather-map-pin text-muted me-1 fs-11"></i>{{ $transfer->fromWarehouse?->name ?? 'N/A' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark fs-12">
                                        <i class="feather-map-pin text-muted me-1 fs-11"></i>{{ $transfer->toWarehouse?->name ?? 'N/A' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted fs-12">
                                        <i class="feather-calendar me-1 text-muted fs-11"></i>{{ $transfer->transfer_date ? \Carbon\Carbon::parse($transfer->transfer_date)->format('d M Y') : 'N/A' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <x-ui.status-badge :status="$transfer->status" size="sm" />
                                </td>
                            <td>
                                <span class="text-muted fs-12">{{ $transfer->creator?->name ?? 'Admin' }}</span>
                            </td>
                            <td class="text-end pe-4">
                                <x-ui.action-dropdown :viewUrl="route('inventory.transfers.show', $transfer->id)">
                                    @if($transfer->status === 'Draft')
                                        <li>
                                            <form action="{{ route('inventory.transfers.dispatch', $transfer->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-info fw-semibold">
                                                    <i class="feather-truck me-2 text-info fs-12"></i>Dispatch Transfer
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                    @if($transfer->status === 'In Transit')
                                        <li>
                                            <form action="{{ route('inventory.transfers.receive', $transfer->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-success fw-semibold">
                                                    <i class="feather-check-circle me-2 text-success fs-12"></i>Receive Transfer
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                    @if(in_array($transfer->status, ['Draft', 'In Transit']))
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form action="{{ route('inventory.transfers.cancel', $transfer->id) }}" method="POST" id="cancelTransferForm_{{ $transfer->id }}">
                                                @csrf
                                                <button type="button" class="dropdown-item text-danger fw-semibold" onclick="if(confirm('Are you sure you want to cancel this stock transfer?')) document.getElementById('cancelTransferForm_{{ $transfer->id }}').submit();">
                                                    <i class="feather-x-circle me-2 text-danger fs-12"></i>Cancel Transfer
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
                                No Stock Transfers found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <!-- Pagination Section -->
        <div class="pt-3">
            <x-ui.pagination 
                :currentPage="$transfers->currentPage()" 
                :totalPages="$transfers->lastPage()" 
                :totalResults="$transfers->total()" 
                :perPage="$transfers->perPage()" />
        </div>
    </x-ui.odoo-form-ui>
</div>
@endsection
