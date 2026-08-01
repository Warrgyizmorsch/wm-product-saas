@extends('layouts.duralux')

@section('title', 'Low Stock Report | SaaS ERP')
@section('page-title', 'Low Stock & Reorder Alert Report')
@section('breadcrumb', 'Inventory / Reports / Low Stock')

@section('content')
<div class="erp-single-panel">
    <!-- Toolbar: Header, Sort, Filter & Action Buttons -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="d-flex align-items-center flex-wrap gap-2">
            <h5 class="fw-bold text-dark mb-0 me-1"><i class="feather-alert-triangle text-danger me-2"></i>Low Stock Alert Items</h5>
            <x-ui.badge variant="danger" class="fs-12">
                {{ $products instanceof \Illuminate\Pagination\LengthAwarePaginator ? $products->total() : $products->count() }} Alert Items
            </x-ui.badge>
        </div>

        <div class="d-flex align-items-center flex-wrap gap-2">
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

    <!-- Table Component (Matching Lead Module Standard) -->
    <div class="table-responsive">
        <x-ui.odoo-form-ui type="table" id="lowStockReportTable">
            <thead>
                <tr>
                    <th style="width: 3%" class="text-center">
                        <input type="checkbox" class="form-check-input">
                    </th>
                    <th>Product Name</th>
                    <th>SKU Code</th>
                    <th class="text-end">Current Total Stock</th>
                    <th class="text-end">Reorder Point</th>
                    <th class="text-end">Shortage Qty</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    @php
                        $shortage = max(0, $product->reorder_point - $product->total_stock);
                    @endphp
                    <tr>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input">
                        </td>
                        <td>
                            <strong class="text-dark d-block">{{ $product->name }}</strong>
                            <small class="text-muted">Type: {{ ucfirst($product->type ?: 'Goods') }}</small>
                        </td>
                        <td>
                            <span class="font-monospace text-dark fw-semibold">{{ $product->sku }}</span>
                        </td>
                        <td class="text-end fw-bold text-danger">
                            {{ number_format($product->total_stock, 2) }}
                        </td>
                        <td class="text-end fw-bold text-dark">
                            {{ number_format($product->reorder_point, 2) }}
                        </td>
                        <td class="text-end fw-bold text-danger">
                            +{{ number_format($shortage, 2) }}
                        </td>
                        <td>
                            @if($product->total_stock <= 0)
                                <x-ui.badge variant="danger">Out of Stock</x-ui.badge>
                            @else
                                <x-ui.badge variant="warning">Low Stock Alert</x-ui.badge>
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
</div>
@endsection
