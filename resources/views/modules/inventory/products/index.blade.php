@extends('layouts.duralux')

@section('title', __('inventory.items_list') . ' | SaaS ERP')
@section('page-title', __('inventory.items_management'))
@section('breadcrumb', __('inventory.inventory_items'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .product-thumb-wrapper {
            width: 44px;
            height: 44px;
        }
        .product-thumb-link {
            width: 44px;
            height: 44px;
            border-color: #e2e8f0 !important;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .product-thumb-link:hover {
            transform: scale(1.08);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12) !important;
            border-color: #6366f1 !important;
        }
        .product-img-display {
            transition: transform 0.25s ease;
        }
        .product-thumb-link:hover .product-img-display {
            transform: scale(1.06);
        }
        .product-initials-badge {
            letter-spacing: 0.5px;
            font-family: inherit;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.import-export-dropdown 
            type="products" 
            :can-import="true" 
            :can-download-template="true" 
            template-route="{{ route('inventory.products.downloadSample') }}" 
            import-route="{{ route('inventory.products.import') }}" 
            export-route="{{ route('inventory.products.export') }}" />
        <x-ui.button href="{{ route('inventory.products.create') }}" variant="primary" icon="feather-plus">
            {{ __('inventory.new_item') }}
        </x-ui.button>
    </div>
@endsection

@section('content')
    <div class="erp-single-panel">

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
                        <th>{{ __('inventory.item_name_sku') }}</th>
                        <th>{{ __('inventory.type') }}</th>
                        <th>{{ __('inventory.material_type') }}</th>
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
                            <td>
                                @php
                                    $mainImg = $product->main_image_url;
                                    $words = preg_split('/\s+/', trim($product->name));
                                    $initials = '';
                                    if (count($words) >= 2) {
                                        $initials = mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
                                    } else {
                                        $initials = mb_strtoupper(mb_substr($product->name, 0, 2));
                                    }
                                    $colors = ['primary', 'info', 'success', 'warning', 'danger', 'secondary'];
                                    $colorClass = $colors[abs(crc32($product->name)) % count($colors)];
                                    $photoCount = ($product->relationLoaded('images') ? $product->images->count() : 0) + 
                                                  ($product->relationLoaded('variants') ? $product->variants->sum(fn($v) => $v->images->count()) : 0);
                                @endphp
                                <div class="d-flex align-items-center gap-3">
                                    <div class="product-thumb-wrapper position-relative flex-shrink-0">
                                        <a href="{{ route('inventory.products.show', $product) }}" class="product-thumb-link d-block rounded-3 border bg-white overflow-hidden shadow-2xs position-relative">
                                            @if($mainImg)
                                                <img src="{{ $mainImg }}" alt="{{ $product->name }}" class="product-img-display w-100 h-100 object-fit-cover" loading="lazy" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="product-initials-badge bg-soft-{{ $colorClass }} text-{{ $colorClass }} fw-bold fs-12 d-none align-items-center justify-content-center w-100 h-100">
                                                    {{ $initials }}
                                                </div>
                                            @else
                                                <div class="product-initials-badge bg-soft-{{ $colorClass }} text-{{ $colorClass }} fw-bold fs-12 d-flex align-items-center justify-content-center w-100 h-100">
                                                    {{ $initials }}
                                                </div>
                                            @endif
                                        </a>
                                        @if($product->variation_type === 'Variant')
                                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary border border-white text-white p-1" style="font-size: 9px; line-height: 1; transform: translate(-30%, -30%) !important;" title="{{ $product->variants->count() }} {{ __('inventory.variants') }}">
                                                <i class="feather-layers" style="font-size: 8px;"></i>
                                            </span>
                                        @elseif($photoCount > 1)
                                            <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-dark bg-opacity-75 text-white p-1" style="font-size: 9px; line-height: 1; margin: 2px;" title="{{ $photoCount }} {{ __('inventory.photos') }}">
                                                <i class="feather-image" style="font-size: 8px;"></i> {{ $photoCount }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="d-flex flex-column min-w-0">
                                        <a href="{{ route('inventory.products.show', $product) }}" class="fw-bold text-dark hover-primary fs-13 text-truncate" title="{{ $product->name }}">
                                            {{ $product->name }}
                                        </a>
                                        <div class="d-flex align-items-center gap-2 mt-0.5">
                                            <span class="text-muted font-monospace fs-11">{{ $product->sku ?: '—' }}</span>
                                            @if($product->barcode)
                                                <span class="text-muted fs-11 font-monospace d-none d-md-inline" title="Barcode: {{ $product->barcode }}"><i class="feather-maximize-2 fs-10 me-1"></i>{{ $product->barcode }}</span>
                                            @endif
                                        </div>
                                    </div>
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
                                @php
                                    $mtMap = [
                                        'raw_material'  => ['label' => __('inventory.raw_material'),  'color' => 'warning'],
                                        'semi_finished' => ['label' => __('inventory.semi_finished'), 'color' => 'info'],
                                        'finished_good' => ['label' => __('inventory.finished_good'), 'color' => 'success'],
                                        'consumable'    => ['label' => __('inventory.consumable'),    'color' => 'secondary'],
                                        'component'     => ['label' => __('inventory.component'),     'color' => 'danger'],
                                        'service'       => ['label' => __('inventory.service'),       'color' => 'primary'],
                                    ];
                                    $mt = $mtMap[$product->type] ?? ['label' => ucfirst(str_replace('_', ' ', $product->type ?? '—')), 'color' => 'secondary'];
                                @endphp
                                <span class="badge bg-soft-{{ $mt['color'] }} text-{{ $mt['color'] }} border border-{{ $mt['color'] }}-subtle px-2 py-1 fs-11 fw-semibold">
                                    {{ $mt['label'] }}
                                </span>
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
                                {{ format_currency($product->selling_price) }}
                            </td>
                            <td class="text-end text-muted">
                                {{ format_currency($product->cost_price) }}
                            </td>
                            <td class="text-end">
                                @if($product->item_type === 'Service')
                                    <span class="text-muted">N/A</span>
                                @else
                                    <span class="fw-bold {{ $product->total_stock <= $product->reorder_point ? 'text-danger' : 'text-success' }}">
                                        {{ number_format($product->total_stock, 0) }}
                                    </span>
                                    <small class="text-muted">/ {{ $product->uom?->code ?? 'pcs' }}</small>
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
                                        <form action="{{ route('inventory.products.destroy', $product) }}" method="POST" id="deleteProductForm_{{ $product->id }}" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="dropdown-item text-danger" onclick="confirmAction({ title: 'Delete Product', message: 'Are you sure you want to delete product &quot;{{ addslashes($product->name) }}&quot; (SKU: {{ $product->sku ?: '—' }})?', variant: 'danger', confirmText: 'Delete Product', onConfirm: function() { document.getElementById('deleteProductForm_{{ $product->id }}').submit(); } })">
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

    {{-- Import Products Modal --}}
    <x-ui.modal id="importProductsModal" :title="__('inventory.import_items_products')" :submitText="__('inventory.import_file')" :centered="true">
        <form method="POST" action="{{ route('inventory.products.import') }}" enctype="multipart/form-data" id="importProductsForm">
            @csrf
            <p class="fs-13 text-muted mb-3">{{ __('inventory.upload_file_help') }}</p>
            <x-ui.odoo-form-ui type="file" name="file" :label="__('inventory.excel_csv_file')" required :placeholder="__('inventory.choose_file')" />
        </form>
        <x-slot name="footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('inventory.cancel') }}</button>
            <button type="submit" form="importProductsForm" class="btn btn-primary">{{ __('inventory.import_file') }}</button>
        </x-slot>
    </x-ui.modal>
@endsection
