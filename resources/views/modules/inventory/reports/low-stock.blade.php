@extends('layouts.duralux')

@section('title', 'Low Stock Report | SaaS ERP')
@section('page-title', 'Low Stock & Reorder Alert Report')
@section('breadcrumb', 'Inventory / Reports / Low Stock')

@section('content')
<div class="erp-single-panel text-dark">
    <x-ui.odoo-form-ui type="sheet">

        <!-- Toolbar: Header, Search, Filter & Action Buttons -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <h5 class="fw-bold text-dark mb-0 me-2">
                    <i class="feather-alert-triangle text-danger me-2"></i>Low Stock Alert Items
                </h5>
                <span class="badge bg-soft-danger text-danger border border-danger-subtle font-monospace fw-bold fs-11">
                    {{ $products instanceof \Illuminate\Pagination\LengthAwarePaginator ? $products->total() : $products->count() }} Alert Items
                </span>
            </div>

            <div class="d-flex align-items-center flex-wrap gap-2">
                
                <!-- Quick Search (HRMS Common Component Style) -->
                <form method="GET" action="{{ route('inventory.reports.low-stock') }}" class="d-flex align-items-center bg-light border rounded px-3 py-1">
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
                <form method="GET" action="{{ route('inventory.reports.low-stock') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Low Stock Report</h6>
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Product Name or SKU</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="Type product name or SKU..." value="{{ request('search') }}" />
                        </div>
                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('inventory.reports.low-stock') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filter</button>
                        </div>
                    </x-ui.filter>
                </form>

                <x-ui.button href="javascript:window.print()" variant="light" class="border btn-sm" icon="feather-printer">
                    Print Report
                </x-ui.button>
            </div>
        </div>

        <!-- Table Component -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="lowStockReportTable">
                <thead class="table-light bg-light">
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input">
                        </th>
                        <th>Product Name</th>
                        <th>SKU Code</th>
                        <th class="text-end">Current Total Stock</th>
                        <th class="text-end">Reorder Point</th>
                        <th class="text-end">Shortage Qty</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody class="text-dark">
                    @forelse($products as $product)
                        @php
                            $effectiveReorderPoint = (float)($product->reorder_point > 0 ? $product->reorder_point : 10);
                            $shortage = max(0, $effectiveReorderPoint - $product->total_stock);
                        @endphp
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input">
                            </td>
                            <td>
                                <strong class="text-dark d-block fs-13">{{ $product->name }}</strong>
                                <small class="text-muted">Type: {{ ucfirst($product->type ?: 'Goods') }}</small>
                            </td>
                            <td>
                                <span class="font-monospace text-dark fw-semibold fs-12">{{ $product->sku ?: '—' }}</span>
                            </td>
                            <td class="text-end font-monospace">
                                <span class="badge bg-soft-danger text-danger border border-danger-subtle px-2 py-1 fw-bold fs-11">
                                    {{ number_format($product->total_stock, 2) }}
                                </span>
                            </td>
                            <td class="text-end font-monospace fw-bold text-dark fs-12">
                                {{ number_format($effectiveReorderPoint, 2) }}
                            </td>
                            <td class="text-end font-monospace fw-bold text-danger fs-12">
                                +{{ number_format($shortage, 2) }}
                            </td>
                            <td class="text-center">
                                @if($product->total_stock <= 0)
                                    <x-ui.status-badge status="out_of_stock" size="sm" />
                                @else
                                    <x-ui.status-badge status="low_stock" size="sm" />
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                <x-ui.action-dropdown>
                                    <li>
                                        <a href="{{ route('inventory.products.edit', $product->id) }}" class="dropdown-item">
                                            <i class="feather-edit me-2 text-muted fs-12"></i>Edit Product & Reorder
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('inventory.adjustments.create') }}?product_id={{ $product->id }}" class="dropdown-item text-primary fw-semibold">
                                            <i class="feather-plus-circle me-2 text-primary fs-12"></i>Create Stock Adjustment
                                        </a>
                                    </li>
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-success fw-bold">
                                <i class="feather-check-circle fs-1 d-block mb-3 text-success opacity-75"></i>
                                Excellent! All items are above their reorder stock levels.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <!-- Pagination Section -->
        @if($products instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div class="pt-3">
                <x-ui.pagination 
                    :currentPage="$products->currentPage()" 
                    :totalPages="$products->lastPage()" 
                    :totalResults="$products->total()" 
                    :perPage="$products->perPage()" />
            </div>
        @endif

    </x-ui.odoo-form-ui>
</div>
@endsection
