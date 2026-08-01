@extends('layouts.duralux')

@section('title', 'Stock Ledger & Transactions | SaaS ERP')
@section('page-title', 'Stock Ledger & Movement History')
@section('breadcrumb', 'Inventory / Stock Ledger')

@section('content')

    @php
        $sortBy = request('sort_by', 'created_at');
        $sortOrder = request('sort_order', 'desc');
    @endphp

    <div class="erp-single-panel">
        <!-- Summary KPI Cards (Clean White ERP Executive Style) -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border rounded-3 p-3 bg-white shadow-2xs">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="fs-11 fw-bold text-muted text-uppercase tracking-wide">Total Stock Inflow Qty</span>
                            <h3 class="fw-bold text-dark mb-0 mt-1">{{ number_format($totalInQty, 2) }}</h3>
                        </div>
                        <div class="avatar-text avatar-md bg-soft-success text-success rounded-3 border border-success-subtle">
                            <i class="feather-arrow-down-left fs-16"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border rounded-3 p-3 bg-white shadow-2xs">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="fs-11 fw-bold text-muted text-uppercase tracking-wide">Total Stock Outflow Qty</span>
                            <h3 class="fw-bold text-dark mb-0 mt-1">{{ number_format($totalOutQty, 2) }}</h3>
                        </div>
                        <div class="avatar-text avatar-md bg-soft-danger text-danger rounded-3 border border-danger-subtle">
                            <i class="feather-arrow-up-right fs-16"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border rounded-3 p-3 bg-white shadow-2xs">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="fs-11 fw-bold text-muted text-uppercase tracking-wide">Total Movement Valuation</span>
                            <h3 class="fw-bold text-dark mb-0 mt-1">₹{{ number_format($totalValue, 2) }}</h3>
                        </div>
                        <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-3 border border-primary-subtle">
                            <i class="feather-dollar-sign fs-16"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toolbar: Tabs, Sort & Filter (Matching Lead Module Standards) -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <h5 class="fw-bold text-dark mb-0 me-1">Stock Movement History</h5>
                <a href="{{ request()->fullUrlWithQuery(['type' => null]) }}" class="btn btn-xs {{ !request('type') ? 'btn-primary' : 'btn-light border' }}">
                    All Movements
                </a>
                <a href="{{ request()->fullUrlWithQuery(['type' => 'IN']) }}" class="btn btn-xs {{ request('type') === 'IN' ? 'btn-success text-white' : 'btn-soft-success text-success' }}">
                    IN (+)
                </a>
                <a href="{{ request()->fullUrlWithQuery(['type' => 'OUT']) }}" class="btn btn-xs {{ request('type') === 'OUT' ? 'btn-danger text-white' : 'btn-soft-danger text-danger' }}">
                    OUT (-)
                </a>
            </div>

            <div class="d-flex align-items-center flex-wrap gap-2">
                <!-- Custom Sort Component -->
                <x-ui.sort-dropdown label="Sort">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Latest Date & Time</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Oldest Date & Time</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'quantity', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'quantity' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Highest Quantity</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'total_value', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'total_value' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Highest Valuation</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- System Filter Component -->
                <form method="GET" action="{{ route('inventory.transactions.index') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Stock Ledger</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Product</label>
                            <x-ui.odoo-form-ui type="select" name="product_id">
                                <option value="">All Products</option>
                                @foreach($products as $prod)
                                    <option value="{{ $prod->id }}" {{ request('product_id') == $prod->id ? 'selected' : '' }}>
                                        {{ $prod->name }} ({{ $prod->sku }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Warehouse</label>
                            <x-ui.odoo-form-ui type="select" name="warehouse_id">
                                <option value="">All Warehouses</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>
                                        {{ $wh->name }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Movement Type</label>
                            <x-ui.odoo-form-ui type="select" name="type">
                                <option value="">IN & OUT</option>
                                <option value="IN" {{ request('type') === 'IN' ? 'selected' : '' }}>IN (+)</option>
                                <option value="OUT" {{ request('type') === 'OUT' ? 'selected' : '' }}>OUT (-)</option>
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
                            <a href="{{ route('inventory.transactions.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- Table Component -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="stockLedgerTable">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input">
                        </th>
                        <th>Date & Time</th>
                        <th>Product</th>
                        <th>Warehouse</th>
                        <th>Type</th>
                        <th class="text-end">Quantity</th>
                        <th class="text-end">Unit Cost</th>
                        <th class="text-end">Total Value</th>
                        <th>Reference Document</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $trx)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input">
                            </td>
                            <td>
                                <span class="fw-semibold text-dark">{{ \Carbon\Carbon::parse($trx->created_at)->format('d M Y, h:i A') }}</span>
                            </td>
                            <td>
                                <strong class="text-dark d-block">{{ $trx->product->name ?? 'N/A' }}</strong>
                                <small class="text-muted">SKU: {{ $trx->product->sku ?? '-' }}</small>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark">{{ $trx->warehouse->name ?? 'N/A' }}</span>
                            </td>
                            <td>
                                <x-ui.badge :variant="$trx->type === 'IN' ? 'success' : 'danger'">
                                    {{ $trx->type }}
                                </x-ui.badge>
                            </td>
                            <td class="text-end fw-bold {{ $trx->type === 'IN' ? 'text-success' : 'text-danger' }}">
                                {{ $trx->type === 'IN' ? '+' : '-' }}{{ number_format($trx->quantity, 2) }}
                            </td>
                            <td class="text-end">₹{{ number_format($trx->unit_cost, 2) }}</td>
                            <td class="text-end fw-bold text-dark">₹{{ number_format($trx->total_value, 2) }}</td>
                            <td>
                                <x-ui.badge variant="light" class="border">
                                    {{ $trx->reference_type }}
                                </x-ui.badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="feather-archive fs-1 d-block mb-3 text-light"></i>
                                No stock movement transactions found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <!-- Pagination Section (Matching Lead Module Component) -->
        <div class="pt-3">
            <x-ui.pagination 
                :currentPage="$transactions->currentPage()" 
                :totalPages="$transactions->lastPage()" 
                :totalResults="$transactions->total()" 
                :perPage="$transactions->perPage()" />
        </div>
    </div>
@endsection
