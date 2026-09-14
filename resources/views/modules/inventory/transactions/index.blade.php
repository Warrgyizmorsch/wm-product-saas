@extends('layouts.duralux')

@section('title', 'Stock Ledger & Transactions | SaaS ERP')
@section('page-title', 'Stock Ledger & Movement History')
@section('breadcrumb', 'Inventory / Stock Ledger')

@push('styles')
<style>
    .ledger-tab-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 22px;
        font-size: 13px;
        font-weight: 600;
        color: #475569;
        background-color: #ffffff;
        border: 1.5px solid #cbd5e1;
        border-radius: 8px;
        transition: all 0.2s ease-in-out;
        text-decoration: none !important;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    }
    .ledger-tab-link:hover {
        background-color: #f8fafc;
        border-color: var(--bs-primary);
        color: var(--bs-primary);
        transform: translateY(-1px);
    }
    .ledger-tab-link.active {
        background-color: var(--bs-primary) !important;
        border-color: var(--bs-primary) !important;
        color: #ffffff !important;
        box-shadow: 0 4px 12px color-mix(in srgb, var(--bs-primary) 25%, transparent);
    }
    .ledger-tab-link.active i {
        color: #ffffff !important;
    }
</style>
@endpush

@section('content')

    @php
        $sortBy = request('sort_by', 'created_at');
        $sortOrder = request('sort_order', 'desc');
    @endphp

    <div class="erp-single-panel">
        <!-- Item Category Ledger Top Tabs -->
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 pb-3 border-bottom">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <a href="{{ request()->fullUrlWithQuery(['item_category' => 'all', 'page' => 1]) }}" 
                   class="ledger-tab-link {{ request('item_category', 'all') === 'all' ? 'active' : '' }}">
                    <i class="feather-box fs-15 text-primary"></i>
                    <span>All Items Ledger</span>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['item_category' => 'fg', 'page' => 1]) }}" 
                   class="ledger-tab-link {{ request('item_category') === 'fg' ? 'active' : '' }}">
                    <i class="feather-package fs-15 text-warning"></i>
                    <span>Finished Goods (FG) Ledger</span>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['item_category' => 'rm', 'page' => 1]) }}" 
                   class="ledger-tab-link {{ request('item_category') === 'rm' ? 'active' : '' }}">
                    <i class="feather-layers fs-15 text-info"></i>
                    <span>Raw Materials & Other Items</span>
                </a>
            </div>
        </div>

        <!-- Top Stock Movement KPI Stat Widgets -->
        <div class="row g-3 mb-4">
            <div class="col-lg-3 col-md-6 col-12 d-flex">
                <div class="card h-100 w-100 border-0 shadow-sm rounded-3 p-3 bg-white d-flex align-items-center flex-row">
                    <div class="avatar-text bg-soft-success text-success rounded-3 flex-shrink-0 me-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="feather-arrow-down-left fs-5"></i>
                    </div>
                    <div class="overflow-hidden flex-grow-1">
                        <span class="fs-11 fw-bold text-uppercase text-muted d-block text-truncate mb-1" title="TOTAL INWARD (IN)">TOTAL INWARD (IN)</span>
                        <div class="fs-16 fw-bold text-success text-nowrap text-truncate" title="+{{ number_format($totalInQty, 2) }} Units">+{{ number_format($totalInQty, 2) }} Units</div>
                        <span class="fs-11 text-muted d-block text-truncate mt-0.5">Inward Value: ₹{{ number_format($totalInValue, 2) }}</span>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-12 d-flex">
                <div class="card h-100 w-100 border-0 shadow-sm rounded-3 p-3 bg-white d-flex align-items-center flex-row">
                    <div class="avatar-text bg-soft-danger text-danger rounded-3 flex-shrink-0 me-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="feather-arrow-up-right fs-5"></i>
                    </div>
                    <div class="overflow-hidden flex-grow-1">
                        <span class="fs-11 fw-bold text-uppercase text-muted d-block text-truncate mb-1" title="TOTAL OUTWARD (OUT)">TOTAL OUTWARD (OUT)</span>
                        <div class="fs-16 fw-bold text-danger text-nowrap text-truncate" title="-{{ number_format($totalOutQty, 2) }} Units">-{{ number_format($totalOutQty, 2) }} Units</div>
                        <span class="fs-11 text-muted d-block text-truncate mt-0.5">Outward Value: ₹{{ number_format($totalOutValue, 2) }}</span>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-12 d-flex">
                <div class="card h-100 w-100 border-0 shadow-sm rounded-3 p-3 bg-white d-flex align-items-center flex-row">
                    <div class="avatar-text bg-soft-primary text-primary rounded-3 flex-shrink-0 me-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="feather-activity fs-5"></i>
                    </div>
                    <div class="overflow-hidden flex-grow-1">
                        <span class="fs-11 fw-bold text-uppercase text-muted d-block text-truncate mb-1" title="NET MOVEMENT DELTA">NET MOVEMENT DELTA</span>
                        <div class="fs-16 fw-bold text-dark text-nowrap text-truncate" title="{{ $netQty >= 0 ? '+' : '' }}{{ number_format($netQty, 2) }} Units">{{ $netQty >= 0 ? '+' : '' }}{{ number_format($netQty, 2) }} Units</div>
                        <span class="fs-11 text-muted d-block text-truncate mt-0.5">Net stock change balance</span>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-12 d-flex">
                <div class="card h-100 w-100 border-0 shadow-sm rounded-3 p-3 bg-white d-flex align-items-center flex-row">
                    <div class="avatar-text bg-soft-info text-info rounded-3 flex-shrink-0 me-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="feather-list fs-5"></i>
                    </div>
                    <div class="overflow-hidden flex-grow-1">
                        <span class="fs-11 fw-bold text-uppercase text-muted d-block text-truncate mb-1" title="TOTAL MOVEMENT LOGS">TOTAL MOVEMENT LOGS</span>
                        <div class="fs-16 fw-bold text-dark text-nowrap text-truncate" title="{{ number_format($totalTransactionsCount) }} Entries">{{ number_format($totalTransactionsCount) }} Entries</div>
                        <span class="fs-11 text-muted d-block text-truncate mt-0.5">Stock ledger transaction logs</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toolbar: Tabs, Sort, Filter & Download (Matching Lead Module Standards) -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <h5 class="fw-bold text-dark mb-0 me-1">Stock Movement History</h5>
                <a href="{{ request()->fullUrlWithQuery(['type' => null]) }}" class="btn btn-xs {{ !request('type') ? 'btn-primary' : 'btn-light border' }}">
                    All Movements
                </a>
                <a href="{{ request()->fullUrlWithQuery(['type' => 'IN']) }}" class="btn btn-xs {{ request('type') === 'IN' ? 'btn-success text-white' : 'btn-soft-success text-success' }}">
                    IN
                </a>
                <a href="{{ request()->fullUrlWithQuery(['type' => 'OUT']) }}" class="btn btn-xs {{ request('type') === 'OUT' ? 'btn-danger text-white' : 'btn-soft-danger text-danger' }}">
                    OUT
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
                    <input type="hidden" name="item_category" value="{{ request('item_category', 'all') }}">
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
                                <option value="IN" {{ request('type') === 'IN' ? 'selected' : '' }}>IN</option>
                                <option value="OUT" {{ request('type') === 'OUT' ? 'selected' : '' }}>OUT</option>
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

                <!-- Export Download Button -->
                <a href="{{ route('inventory.transactions.export', request()->all()) }}" class="btn btn-sm btn-success text-white shadow-xs d-inline-flex align-items-center gap-1.5" title="Export stock ledger transactions with active filters to Excel/CSV">
                    <i class="feather-download"></i>
                    <span>Download Ledger (Excel / CSV)</span>
                </a>
            </div>
        </div>

        <!-- Table Component -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="stockLedgerTable">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Product</th>
                        <th>Warehouse</th>
                        <th>Type</th>
                        <th class="text-end">Quantity</th>
                        <th class="text-end">Balance Qty</th>
                        <th class="text-end">Unit Cost</th>
                        <th class="text-end">Total Value</th>
                        <th>Reference Document</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $trx)
                        <tr>
                            <td>
                                <span class="fw-semibold text-dark">{{ \Carbon\Carbon::parse($trx->created_at)->format('d M Y, h:i A') }}</span>
                            </td>
                            <td>
                                @if($trx->product_id)
                                    <a href="{{ route('inventory.products.show', $trx->product_id) }}" 
                                       class="fw-semibold text-primary text-decoration-underline-hover d-inline-flex align-items-center gap-1"
                                       title="Click to view product details for {{ $trx->product->name ?? '' }}">
                                        <span>{{ $trx->product->name ?? 'N/A' }}</span>
                                        <i class="feather-external-link fs-11 opacity-75"></i>
                                    </a>
                                @else
                                    <strong class="text-dark d-block">N/A</strong>
                                @endif
                                <small class="text-muted d-block">SKU: {{ $trx->product->sku ?? '-' }}</small>

                                {{-- Batch Number & Expiry Badge --}}
                                @if($trx->batch)
                                    <div class="mt-1">
                                        <span class="badge bg-soft-warning text-dark border border-warning-subtle fs-11 px-2 py-0.5" title="Batch Expiry: {{ $trx->batch->expiry_date ? $trx->batch->expiry_date->format('d M Y') : 'N/A' }}">
                                            <i class="feather-box text-warning me-1"></i>Batch: {{ $trx->batch->batch_number }}
                                            @if($trx->batch->expiry_date)
                                                <span class="text-muted ms-1">(Exp: {{ $trx->batch->expiry_date->format('d M Y') }})</span>
                                            @endif
                                        </span>
                                    </div>
                                @endif

                                {{-- Serial Numbers Badge --}}
                                @php
                                    $serials = $trx->type === 'IN' ? $trx->incomingSerials : $trx->outgoingSerials;
                                @endphp
                                @if($serials && $serials->count() > 0)
                                    <div class="mt-1">
                                        <span class="badge bg-soft-info text-info border border-info-subtle fs-11 px-2 py-0.5" title="Serial Numbers: {{ $serials->pluck('serial_number')->join(', ') }}">
                                            <i class="feather-hash me-1"></i>S/N: {{ $serials->pluck('serial_number')->take(2)->join(', ') }}{{ $serials->count() > 2 ? ' +'.($serials->count() - 2).' more' : '' }}
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <span class="fw-semibold text-dark">{{ $trx->warehouse->name ?? 'N/A' }}</span>
                            </td>
                            <td>
                                @if($trx->type === 'IN')
                                    <span class="badge bg-soft-success text-success border border-success-subtle px-2.5 py-1 fs-11 fw-bold">
                                        IN
                                    </span>
                                @else
                                    <span class="badge bg-soft-danger text-danger border border-danger-subtle px-2.5 py-1 fs-11 fw-bold">
                                        OUT
                                    </span>
                                @endif
                            </td>
                            <td class="text-end fw-bold {{ $trx->type === 'IN' ? 'text-success' : 'text-danger' }}">
                                {{ number_format($trx->quantity, 2) }}
                            </td>
                            <td class="text-end font-monospace fw-bold text-dark fs-13">
                                {{ number_format($trx->balance_qty ?? 0, 2) }}
                            </td>
                            <td class="text-end font-monospace text-muted fs-12">₹{{ number_format($trx->unit_cost, 2) }}</td>
                            <td class="text-end font-monospace fw-bold text-dark fs-13">₹{{ number_format($trx->total_value, 2) }}</td>
                            <td>
                                <div class="d-flex flex-column align-items-start gap-1">
                                    <span class="badge bg-light text-secondary border fs-11 fw-medium">
                                        {{ $trx->reference_type ?: 'Direct Movement' }}
                                    </span>
                                    @if($trx->document_url)
                                        <a href="{{ $trx->document_url }}" class="fw-bold text-primary text-decoration-underline-hover fs-12 d-inline-flex align-items-center gap-1" title="Click to view {{ $trx->document_number }}">
                                            <i class="feather-external-link fs-11"></i>
                                            <span>{{ $trx->document_number }}</span>
                                        </a>
                                    @else
                                        <span class="fw-semibold text-dark fs-12">{{ $trx->document_number }}</span>
                                    @endif
                                </div>
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
