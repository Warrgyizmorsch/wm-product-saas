@extends('layouts.duralux')

@section('title', 'Stock Valuation Report | SaaS ERP')
@section('page-title', 'Inventory Asset Valuation Report')
@section('breadcrumb', 'Inventory / Reports / Valuation')

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
<div class="erp-single-panel text-dark">
    <!-- Top Tabs for Category Valuation Breakdown -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 pb-3 border-bottom">
        <div class="d-flex align-items-center flex-wrap gap-2">
            <a href="{{ request()->fullUrlWithQuery(['item_category' => 'all', 'page' => 1]) }}" 
               class="ledger-tab-link {{ request('item_category', 'all') === 'all' ? 'active' : '' }}">
                <i class="feather-pie-chart fs-15 text-primary"></i>
                <span>All Items Valuation (₹{{ number_format($totalValuation, 0) }})</span>
            </a>
            <a href="{{ request()->fullUrlWithQuery(['item_category' => 'fg', 'page' => 1]) }}" 
               class="ledger-tab-link {{ request('item_category') === 'fg' ? 'active' : '' }}">
                <i class="feather-package fs-15 text-success"></i>
                <span>Finished Goods (FG) Valuation (₹{{ number_format($fgValuation, 0) }})</span>
            </a>
            <a href="{{ request()->fullUrlWithQuery(['item_category' => 'rm', 'page' => 1]) }}" 
               class="ledger-tab-link {{ request('item_category') === 'rm' ? 'active' : '' }}">
                <i class="feather-layers fs-15 text-info"></i>
                <span>Raw Materials & Components (₹{{ number_format($rmValuation, 0) }})</span>
            </a>
        </div>
    </div>

    <!-- Top Real ERP Valuation KPI Stat Widgets -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6 col-12 d-flex">
            <div class="card h-100 w-100 border-0 shadow-sm rounded-3 p-3 bg-white d-flex align-items-center flex-row">
                <div class="avatar-text bg-soft-primary text-primary rounded-3 flex-shrink-0 me-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                    <i class="feather-dollar-sign fs-5"></i>
                </div>
                <div class="overflow-hidden flex-grow-1">
                    <span class="fs-11 fw-bold text-uppercase text-muted d-block text-truncate mb-1" title="TOTAL STOCK VALUATION">TOTAL STOCK VALUATION</span>
                    <div class="fs-16 fw-bold text-dark text-nowrap text-truncate" title="₹{{ number_format($totalValuation, 2) }}">₹{{ number_format($totalValuation, 2) }}</div>
                    <span class="fs-11 text-muted d-block text-truncate mt-0.5">Total physical asset value</span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 col-12 d-flex">
            <div class="card h-100 w-100 border-0 shadow-sm rounded-3 p-3 bg-white d-flex align-items-center flex-row">
                <div class="avatar-text bg-soft-success text-success rounded-3 flex-shrink-0 me-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                    <i class="feather-package fs-5"></i>
                </div>
                <div class="overflow-hidden flex-grow-1">
                    <span class="fs-11 fw-bold text-uppercase text-muted d-block text-truncate mb-1" title="FINISHED GOODS VALUATION">FINISHED GOODS VALUATION</span>
                    <div class="fs-16 fw-bold text-dark text-nowrap text-truncate" title="₹{{ number_format($fgValuation, 2) }}">₹{{ number_format($fgValuation, 2) }}</div>
                    <span class="fs-11 text-muted d-block text-truncate mt-0.5">Finished stock ({{ number_format($fgQty, 2) }} Units)</span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 col-12 d-flex">
            <div class="card h-100 w-100 border-0 shadow-sm rounded-3 p-3 bg-white d-flex align-items-center flex-row">
                <div class="avatar-text bg-soft-info text-info rounded-3 flex-shrink-0 me-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                    <i class="feather-layers fs-5"></i>
                </div>
                <div class="overflow-hidden flex-grow-1">
                    <span class="fs-11 fw-bold text-uppercase text-muted d-block text-truncate mb-1" title="RAW MATERIALS VALUATION">RAW MATERIALS VALUATION</span>
                    <div class="fs-16 fw-bold text-dark text-nowrap text-truncate" title="₹{{ number_format($rmValuation, 2) }}">₹{{ number_format($rmValuation, 2) }}</div>
                    <span class="fs-11 text-muted d-block text-truncate mt-0.5">Raw materials & WIP ({{ number_format($rmQty, 2) }} Units)</span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 col-12 d-flex">
            <div class="card h-100 w-100 border-0 shadow-sm rounded-3 p-3 bg-white d-flex align-items-center flex-row">
                <div class="avatar-text bg-soft-warning text-warning rounded-3 flex-shrink-0 me-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                    <i class="feather-box fs-5"></i>
                </div>
                <div class="overflow-hidden flex-grow-1">
                    <span class="fs-11 fw-bold text-uppercase text-muted d-block text-truncate mb-1" title="TOTAL ON-HAND QUANTITY">TOTAL ON-HAND QUANTITY</span>
                    <div class="fs-16 fw-bold text-dark text-nowrap text-truncate" title="{{ number_format($totalQty, 2) }} Units">{{ number_format($totalQty, 2) }} Units</div>
                    <span class="fs-11 text-muted d-block text-truncate mt-0.5">Physical stock across warehouses</span>
                </div>
            </div>
        </div>
    </div>

    <x-ui.odoo-form-ui type="sheet">
        <!-- Toolbar: Header, Search, Filter & Action Buttons -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <h5 class="fw-bold text-dark mb-0 me-2">
                    <i class="feather-pie-chart text-primary me-2"></i>Stock Asset Valuation Matrix
                </h5>
            </div>

            <div class="d-flex align-items-center flex-wrap gap-2">
                <!-- Quick Search -->
                <form method="GET" action="{{ route('inventory.reports.valuation') }}" class="d-flex align-items-center bg-light border rounded px-3 py-1">
                    @if(request('item_category')) <input type="hidden" name="item_category" value="{{ request('item_category') }}"> @endif
                    @if(request('warehouse_id')) <input type="hidden" name="warehouse_id" value="{{ request('warehouse_id') }}"> @endif
                    
                    <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                    <input 
                        type="text" 
                        name="search" 
                        class="form-control border-0 bg-transparent p-0 fs-13" 
                        placeholder="Search product or SKU..." 
                        value="{{ request('search') }}"
                        style="box-shadow: none; height: 32px; width: 220px;"
                    >
                </form>

                <!-- Filter Component -->
                <form method="GET" action="{{ route('inventory.reports.valuation') }}" class="d-inline">
                    @if(request('item_category')) <input type="hidden" name="item_category" value="{{ request('item_category') }}"> @endif
                    @if(request('search')) <input type="hidden" name="search" value="{{ request('search') }}"> @endif

                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Valuation</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Warehouse Location</label>
                            <x-ui.odoo-form-ui type="select" name="warehouse_id">
                                <option value="">— All Warehouses —</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" @selected(request('warehouse_id') == $wh->id)>{{ $wh->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('inventory.reports.valuation') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filter</button>
                        </div>
                    </x-ui.filter>
                </form>

                <a href="{{ route('inventory.reports.valuation.export', request()->all()) }}" class="btn btn-sm btn-success text-white shadow-xs d-inline-flex align-items-center gap-1.5" title="Export unpaginated valuation data with active filters to Excel/CSV">
                    <i class="feather-download"></i>
                    <span>Download Report (Excel / CSV)</span>
                </a>
            </div>
        </div>

        <!-- Valuation Matrix Table -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="stockValuationTable">
                <thead class="table-light bg-light">
                    <tr>
                        <th>Product Name</th>
                        <th>SKU Code</th>
                        <th>Warehouse Location</th>
                        <th class="text-end">On Hand Qty</th>
                        <th class="text-end">Unit Cost Rate</th>
                        <th class="text-end pe-3">Total Asset Value</th>
                    </tr>
                </thead>
                <tbody class="text-dark">
                    @forelse($stocks as $stock)
                        @php
                            $unitCost = (float)($stock->unit_cost > 0 ? $stock->unit_cost : ($stock->product->unit_cost ?? 0));
                            $value = (float)$stock->quantity * $unitCost;
                        @endphp
                        <tr>
                            <td>
                                @if($stock->product)
                                    <a href="{{ route('inventory.products.show', $stock->product_id) }}" 
                                       class="fw-semibold text-primary text-decoration-underline-hover d-inline-flex align-items-center gap-1.5"
                                       title="Click to view product details for {{ $stock->product->name }}">
                                        <span>{{ $stock->product->name }}</span>
                                        <i class="feather-external-link fs-11 opacity-75"></i>
                                    </a>
                                @else
                                    <strong class="text-dark">N/A</strong>
                                @endif
                            </td>
                            <td>
                                <span class="font-monospace text-dark fw-semibold fs-12">{{ $stock->product->sku ?? '—' }}</span>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark fs-12">
                                    <i class="feather-map-pin text-muted me-1 fs-11"></i>{{ $stock->warehouse->name ?? 'N/A' }}
                                </span>
                            </td>
                            <td class="text-end font-monospace fw-bold fs-13 text-dark">
                                {{ number_format($stock->quantity, 2) }}
                            </td>
                            <td class="text-end font-monospace text-muted fs-12">
                                ₹{{ number_format($unitCost, 2) }}
                            </td>
                            <td class="text-end pe-3 font-monospace fw-bold fs-13 text-primary">
                                ₹{{ number_format($value, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="feather-archive fs-1 d-block mb-3 text-light"></i>
                                No physical stock valuation data available.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <!-- Pagination Section -->
        @if($stocks instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div class="pt-3">
                <x-ui.pagination 
                    :currentPage="$stocks->currentPage()" 
                    :totalPages="$stocks->lastPage()" 
                    :totalResults="$stocks->total()" 
                    :perPage="$stocks->perPage()" />
            </div>
        @endif

    </x-ui.odoo-form-ui>
</div>
@endsection
