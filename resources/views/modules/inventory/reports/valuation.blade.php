@extends('layouts.duralux')

@section('title', 'Stock Valuation Report | SaaS ERP')
@section('page-title', 'Inventory Asset Valuation Report')
@section('breadcrumb', 'Inventory / Reports / Valuation')

@section('content')
<div class="erp-single-panel text-dark">
    <x-ui.odoo-form-ui type="sheet">

        <!-- Top Valuation KPI Stat Widgets -->
        <div class="row g-3 mb-4">
            <div class="col-md-4 col-12">
                <x-ui.stat-widget 
                    title="Total Physical Valuation" 
                    value="₹{{ number_format($totalValuation, 2) }}" 
                    subtitle="Total asset value of stock on hand"
                    icon="feather-dollar-sign" 
                    color="primary" 
                    variant="compact" 
                />
            </div>
            <div class="col-md-4 col-6">
                <x-ui.stat-widget 
                    title="Active Stocked SKUs" 
                    value="{{ number_format($stocks->total()) }} SKUs" 
                    subtitle="Products with available stock"
                    icon="feather-package" 
                    color="info" 
                    variant="compact" 
                />
            </div>
            <div class="col-md-4 col-6">
                <x-ui.stat-widget 
                    title="Total On-Hand Quantity" 
                    value="{{ number_format($stocks->sum('quantity'), 2) }} Units" 
                    subtitle="Units across all warehouses"
                    icon="feather-layers" 
                    color="success" 
                    variant="compact" 
                />
            </div>
        </div>

        <!-- Toolbar: Header, Search, Filter & Action Buttons -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <h5 class="fw-bold text-dark mb-0 me-2">
                    <i class="feather-pie-chart text-primary me-2"></i>Stock Asset Valuation Matrix
                </h5>
            </div>

            <div class="d-flex align-items-center flex-wrap gap-2">
                
                <!-- Quick Search (HRMS Common Component Style) -->
                <form method="GET" action="{{ route('inventory.reports.valuation') }}" class="d-flex align-items-center bg-light border rounded px-3 py-1">
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

                <x-ui.button href="javascript:window.print()" variant="light" class="border btn-sm" icon="feather-printer">
                    Print Valuation Summary
                </x-ui.button>
            </div>
        </div>

        <!-- Valuation Matrix Table -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="stockValuationTable">
                <thead class="table-light bg-light">
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input">
                        </th>
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
                            $value = (float)$stock->quantity * (float)$stock->unit_cost;
                        @endphp
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input">
                            </td>
                            <td>
                                @if($stock->product)
                                    <a href="{{ route('inventory.products.show', $stock->product_id) }}" class="fw-bold text-dark text-decoration-none">
                                        {{ $stock->product->name }}
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
                                ₹{{ number_format($stock->unit_cost, 2) }}
                            </td>
                            <td class="text-end pe-3 font-monospace fw-bold fs-13 text-primary">
                                ₹{{ number_format($value, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
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
