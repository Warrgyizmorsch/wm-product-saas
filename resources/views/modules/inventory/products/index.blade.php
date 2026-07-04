@extends('layouts.duralux')

@section('title', 'Items List | SaaS ERP')
@section('page-title', 'Items Management')
@section('breadcrumb', 'Inventory / Items')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('page-actions')
    <a href="{{ route('inventory.products.create') }}" class="btn btn-primary">
        <i class="feather-plus me-2"></i>New Item
    </a>
@endsection

@section('content')
    <div class="erp-single-panel">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <div class="d-flex align-items-center">
                    <div class="avatar-text avatar-md bg-success text-white me-3">
                        <i class="feather-check-circle"></i>
                    </div>
                    <div>
                        <h6 class="alert-heading fw-bold mb-1">Success!</h6>
                        <p class="fs-12 mb-0">{{ session('success') }}</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- Filters Section - Inlined on Single panel -->
        <form method="GET" action="{{ route('inventory.products.index') }}" class="mb-4">
            <div class="row g-3">
                <div class="col-md-4 erp-form-input-col">
                    <input type="text" name="search" class="form-control" placeholder="Search by name, SKU..." value="{{ request('search') }}" />
                </div>
                <div class="col-md-3 erp-form-input-col">
                    <select name="item_type" class="form-select" data-select2-selector="default">
                        <option value="">All Item Types</option>
                        <option value="Goods" {{ request('item_type') === 'Goods' ? 'selected' : '' }}>Goods (Physical)</option>
                        <option value="Service" {{ request('item_type') === 'Service' ? 'selected' : '' }}>Service</option>
                    </select>
                </div>
                <div class="col-md-3 erp-form-input-col">
                    <select name="status" class="form-select" data-select2-selector="default">
                        <option value="">All Statuses</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="d-grid w-100">
                        <button type="submit" class="btn btn-secondary h-40">
                            <i class="feather-filter me-2"></i>Filter
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <!-- Items Table -->
        <div class="table-responsive">
            <table class="erp-thin-table">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input">
                        </th>
                        <th style="width: 25%">Item Name / SKU</th>
                        <th style="width: 12%">Type</th>
                        <th style="width: 12%">Variation</th>
                        <th style="width: 12%" class="text-end">Selling Price</th>
                        <th style="width: 12%" class="text-end">Cost Price</th>
                        <th style="width: 12%" class="text-end">Stock on Hand</th>
                        <th style="width: 10%">Status</th>
                        <th class="text-end" style="width: 15%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input">
                            </td>
                            <td>
                                <div class="d-flex flex-column">
                                    <a href="{{ route('inventory.products.show', $product) }}" class="fw-bold text-primary hover-primary">
                                        {{ $product->name }}
                                    </a>
                                    <small class="text-muted font-monospace fs-10">{{ $product->sku ?: '—' }}</small>
                                </div>
                            </td>
                            <td>
                                @if($product->item_type === 'Goods')
                                    <span class="badge bg-soft-info text-info px-2 py-0.5 fs-11 fw-semibold">Goods</span>
                                @else
                                    <span class="badge bg-soft-warning text-warning px-2 py-0.5 fs-11 fw-semibold">Service</span>
                                @endif
                            </td>
                            <td>
                                @if($product->variation_type === 'Variant')
                                    <span class="badge bg-soft-primary text-primary px-2 py-0.5 fs-11 fw-semibold">
                                        {{ $product->variants->count() }} Variants
                                    </span>
                                @else
                                    <span class="badge bg-soft-secondary text-secondary px-2 py-0.5 fs-11 fw-semibold">Single</span>
                                @endif
                            </td>
                            <td class="text-end fw-bold">
                                ₹{{ number_format($product->selling_price, 2) }}
                            </td>
                            <td class="text-end text-muted">
                                ₹{{ number_format($product->cost_price, 2) }}
                            </td>
                            <td class="text-end">
                                @if($product->item_type === 'Service')
                                    <span class="text-muted">N/A</span>
                                @else
                                    <span class="fw-bold {{ $product->total_stock <= $product->reorder_point ? 'text-danger' : 'text-success' }}">
                                        {{ number_format($product->total_stock, 0) }}
                                    </span>
                                    <small class="text-muted">/ {{ $product->uom ? $product->uom->code : 'pcs' }}</small>
                                    @if($product->total_stock <= $product->reorder_point)
                                        <i class="feather-alert-triangle text-danger ms-1" title="Below Reorder Point ({{ number_format($product->reorder_point, 0) }})"></i>
                                    @endif
                                @endif
                            </td>
                            <td>
                                @if ($product->status === 'active')
                                    <span class="erp-badge-active">Active</span>
                                @else
                                    <span class="erp-badge-draft">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1 justify-content-end align-items-center">
                                    <x-ui.icon-btn href="{{ route('inventory.products.show', $product) }}" variant="soft-info" title="View details" icon="feather-eye" />
                                    <x-ui.icon-btn href="{{ route('inventory.products.edit', $product) }}" variant="soft-primary" title="Edit" icon="feather-edit" />
                                    <form action="{{ route('inventory.products.destroy', $product) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this product?');" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.icon-btn type="submit" variant="soft-danger" title="Delete" icon="feather-trash-2" />
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="feather-box me-2 fs-16"></i>No items or products found in this workspace.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
            <div class="mt-3">
                {{ $products->links() }}
            </div>
        @endif
    </div>
@endsection
