@extends('layouts.duralux')

@section('title', __('inventory.items_list') . ' | SaaS ERP')
@section('page-title', __('inventory.items_management'))
@section('breadcrumb', __('inventory.inventory_items'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('page-actions')
    <x-ui.button href="{{ route('inventory.products.create') }}" variant="primary" icon="feather-plus">
        {{ __('inventory.new_item') }}
    </x-ui.button>
@endsection

@section('content')
    <div class="erp-single-panel">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if ($errors->any())
            <div class="alert alert-danger mb-3 alert-dismissible fade show fs-12 py-2" role="alert">
                <ul class="mb-0 ps-3 text-start">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.75rem 1rem;"></button>
            </div>
        @endif

        @php
            $sortBy = request('sort_by', 'created_at');
            $sortOrder = request('sort_order', 'desc');
        @endphp

        <!-- Toolbar: Sort, Filters -->
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">{{ __('inventory.items_listing') }}</h5>
            <div class="d-flex gap-2 ms-auto">
                <!-- Custom Sort Component -->
                <x-ui.sort-dropdown :label="__('inventory.sort')">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('inventory.sort_item_name_az') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('inventory.sort_item_name_za') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'sku', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'sku' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('inventory.sort_sku_az') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'sku', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'sku' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('inventory.sort_sku_za') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'selling_price', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'selling_price' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('inventory.sort_selling_price_desc') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'selling_price', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'selling_price' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('inventory.sort_selling_price_asc') }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'cost_price', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'cost_price' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>{{ __('inventory.sort_cost_price_desc') }}</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'cost_price', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'cost_price' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>{{ __('inventory.sort_cost_price_asc') }}</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Custom Filter Component -->
                <form method="GET" action="{{ route('inventory.products.index') }}" class="d-inline">
                    <x-ui.filter :label="__('inventory.filter')" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('inventory.filter_options') }}</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('inventory.search_keywords') }}</label>
                            <x-ui.odoo-form-ui type="input" name="search" :placeholder="__('inventory.search_placeholder_products')" value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('inventory.item_type') }}</label>
                            <x-ui.odoo-form-ui type="select" name="item_type">
                                <option value="">{{ __('inventory.all_item_types') }}</option>
                                <option value="Goods" {{ request('item_type') === 'Goods' ? 'selected' : '' }}>{{ __('inventory.goods_physical') }}</option>
                                <option value="Service" {{ request('item_type') === 'Service' ? 'selected' : '' }}>{{ __('inventory.service') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('inventory.status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">{{ __('inventory.all_statuses') }}</option>
                                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('inventory.active') }}</option>
                                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>{{ __('inventory.inactive') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('inventory.products.index') }}" class="btn btn-sm btn-light border">{{ __('inventory.reset') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('inventory.apply_filters') }}</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- Items Table -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="productsTable">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input">
                        </th>
                        <th>{{ __('inventory.item_name_sku') }}</th>
                        <th>{{ __('inventory.type') }}</th>
                        <th>{{ __('inventory.variation') }}</th>
                        <th class="text-end">{{ __('inventory.selling_price') }}</th>
                        <th class="text-end">{{ __('inventory.cost_price') }}</th>
                        <th class="text-end">{{ __('inventory.stock_on_hand') }}</th>
                        <th>{{ __('inventory.status') }}</th>
                        <th class="text-end pe-4">{{ __('inventory.action') }}</th>
                    </tr>
                </thead>
                <tbody class="fs-13 text-dark">
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
                                    <span class="badge bg-soft-info text-info px-2 py-0.5 fs-11 fw-semibold">{{ __('inventory.goods') }}</span>
                                @else
                                    <span class="badge bg-soft-warning text-warning px-2 py-0.5 fs-11 fw-semibold">{{ __('inventory.service') }}</span>
                                @endif
                            </td>
                            <td>
                                @if($product->variation_type === 'Variant')
                                    <span class="badge bg-soft-primary text-primary px-2 py-0.5 fs-11 fw-semibold">
                                        {{ $product->variants->count() }} {{ __('inventory.variants') }}
                                    </span>
                                @else
                                    <span class="badge bg-soft-secondary text-secondary px-2 py-0.5 fs-11 fw-semibold">{{ __('inventory.single') }}</span>
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
                                    <span class="badge bg-soft-success text-success px-2 py-0.5 fs-11 fw-semibold">{{ __('inventory.active') }}</span>
                                @else
                                    <span class="badge bg-soft-secondary text-secondary px-2 py-0.5 fs-11 fw-semibold">{{ __('inventory.inactive') }}</span>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                <x-ui.action-dropdown :viewUrl="route('inventory.products.show', $product)">
                                    <li>
                                        <a href="{{ route('inventory.products.edit', $product) }}" class="dropdown-item">
                                            <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('inventory.edit_item') }}
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form action="{{ route('inventory.products.destroy', $product) }}" method="POST" onsubmit="return confirm('{{ __('inventory.confirm_delete_product') }}');" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('inventory.delete_item') }}
                                            </button>
                                        </form>
                                    </li>
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="feather-box fs-1 d-block mb-3 text-light"></i>
                                {{ __('inventory.no_items_found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <div class="pt-3">
            <x-ui.pagination 
                :currentPage="$products->currentPage()" 
                :totalPages="$products->lastPage()" 
                :totalResults="$products->total()" 
                :perPage="$products->perPage()" />
        </div>
    </div>
@endsection
