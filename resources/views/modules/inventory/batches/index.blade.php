@extends('layouts.duralux')

@section('title', 'Batch & Expiry Master | Inventory | SaaS ERP')
@section('page-title', 'Batch & Expiry Master Index (FEFO)')
@section('breadcrumb', 'Inventory > Batches')

@section('content')
@php
    $sortBy = request('sort_by', 'expiry_date');
    $sortOrder = request('sort_order', 'asc');
@endphp

<div class="erp-single-panel text-dark">
    <x-ui.odoo-form-ui type="sheet">

        <!-- Header & Controls Toolbar -->
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4 pb-3 border-bottom">
            <div>
                <h4 class="fw-bold text-dark mb-1">
                    <i class="feather-layers text-primary me-2"></i>Batch & Lot Expiry Tracking (FEFO)
                </h4>
                <small class="text-muted fs-12">Track, filter, and audit lot batch expiration dates across warehouses.</small>
            </div>

            <div class="d-flex gap-2 ms-auto align-items-center flex-wrap">
                
                <!-- Quick Search (HRMS Common Component Style) -->
                <form method="GET" action="{{ route('inventory.batches.index') }}" class="d-flex align-items-center bg-light border rounded px-3 py-1">
                    @if(request('product_id')) <input type="hidden" name="product_id" value="{{ request('product_id') }}"> @endif
                    @if(request('expiry_filter')) <input type="hidden" name="expiry_filter" value="{{ request('expiry_filter') }}"> @endif
                    @if(request('warehouse_id')) <input type="hidden" name="warehouse_id" value="{{ request('warehouse_id') }}"> @endif
                    @if(request('sort_by')) <input type="hidden" name="sort_by" value="{{ request('sort_by') }}"> @endif
                    @if(request('sort_order')) <input type="hidden" name="sort_order" value="{{ request('sort_order') }}"> @endif
                    
                    <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                    <input 
                        type="text" 
                        name="search" 
                        class="form-control border-0 bg-transparent p-0 fs-13" 
                        placeholder="Search Batch No, Product, SKU..." 
                        value="{{ request('search') }}"
                        style="box-shadow: none; height: 32px; width: 220px;"
                    >
                </form>

                <!-- Custom Sort Dropdown Component -->
                <x-ui.sort-dropdown :label="__('crm.sort')">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'expiry_date', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'expiry_date' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Nearest Expiry Date (FEFO First)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'expiry_date', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'expiry_date' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Furthest Expiry Date</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'batch_number', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'batch_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Batch Number (A-Z)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'batch_number', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'batch_number' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Batch Number (Z-A)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'available_qty', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'available_qty' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Available Qty (High to Low)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'available_qty', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'available_qty' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Available Qty (Low to High)</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Custom Filter Component -->
                <form method="GET" action="{{ route('inventory.batches.index') }}" class="d-inline">
                    @if(request('search')) <input type="hidden" name="search" value="{{ request('search') }}"> @endif
                    @if(request('sort_by')) <input type="hidden" name="sort_by" value="{{ request('sort_by') }}"> @endif
                    @if(request('sort_order')) <input type="hidden" name="sort_order" value="{{ request('sort_order') }}"> @endif

                    <x-ui.filter :label="__('ui.filter')" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Batches</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Product</label>
                            <x-ui.odoo-form-ui type="select" name="product_id">
                                <option value="">— All Products —</option>
                                @foreach($products as $p)
                                    <option value="{{ $p->id }}" @selected(request('product_id') == $p->id)>{{ $p->name }} ({{ $p->sku }})</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Expiry Status</label>
                            <x-ui.odoo-form-ui type="select" name="expiry_filter">
                                <option value="">— All Statuses —</option>
                                <option value="expiring_soon" @selected(request('expiry_filter') === 'expiring_soon')>Expiring Soon (< 30 Days)</option>
                                <option value="expired" @selected(request('expiry_filter') === 'expired')>Expired</option>
                                <option value="fresh" @selected(request('expiry_filter') === 'fresh')>Fresh (> 30 Days)</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Warehouse</label>
                            <x-ui.odoo-form-ui type="select" name="warehouse_id">
                                <option value="">— All Warehouses —</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" @selected(request('warehouse_id') == $wh->id)>{{ $wh->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('inventory.batches.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>

            </div>
        </div>

        <!-- Batches List Table -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="batchesTable">
                <thead class="table-light bg-light">
                    <tr>
                        <th class="ps-3" style="width: 18%;">Batch / Lot Number</th>
                        <th style="width: 24%;">Product Name & SKU</th>
                        <th style="width: 18%;">Warehouse Location</th>
                        <th style="width: 10%;">Mfg Date</th>
                        <th style="width: 10%;">Expiry Date</th>
                        <th style="width: 12%;">Expiry Status</th>
                        <th class="text-end" style="width: 8%;">Total Inward Qty</th>
                        <th class="text-end pe-3" style="width: 10%;">Available Stock Qty</th>
                    </tr>
                </thead>
                <tbody class="text-dark">
                    @forelse($batches as $b)
                        @php
                            $daysLeft = $b->expiry_date ? (int)now()->diffInDays($b->expiry_date, false) : 999;
                            $isExpired = $daysLeft < 0;
                            $isExpiringSoon = $daysLeft >= 0 && $daysLeft <= 30;
                        @endphp
                        <tr>
                            <td class="ps-3">
                                <span class="fw-bold text-primary font-monospace fs-13">
                                    <i class="feather-package text-primary me-1 fs-12"></i>{{ $b->batch_number }}
                                </span>
                            </td>
                            <td>
                                @if($b->product)
                                    <a href="{{ route('inventory.products.show', $b->product_id) }}" class="fw-bold text-dark text-decoration-none">
                                        {{ $b->product->name }}
                                    </a>
                                    <div class="fs-11 text-muted font-monospace">SKU: {{ $b->product->sku ?: '—' }}</div>
                                @else
                                    <span class="text-muted">Deleted Product</span>
                                @endif
                            </td>
                            <td>
                                <span class="fw-semibold text-dark fs-12">
                                    <i class="feather-map-pin text-muted me-1 fs-11"></i>{{ $b->warehouse->name ?? 'Main Warehouse' }}
                                </span>
                            </td>
                            <td class="text-muted fs-12">
                                <i class="feather-calendar me-1 text-muted fs-11"></i>{{ $b->manufacturing_date ? $b->manufacturing_date->format('d M Y') : '—' }}
                            </td>
                            <td class="fw-bold font-monospace fs-12 {{ $isExpired ? 'text-danger' : ($isExpiringSoon ? 'text-warning' : 'text-dark') }}">
                                <i class="feather-calendar me-1 fs-11 opacity-75"></i>{{ $b->expiry_date ? $b->expiry_date->format('d M Y') : '—' }}
                            </td>
                            <td>
                                @if($isExpired)
                                    <span class="badge bg-soft-danger text-danger px-2 py-1 fw-bold fs-11 border border-danger-subtle">
                                        <i class="feather-alert-octagon me-1"></i>Expired ({{ abs($daysLeft) }}d ago)
                                    </span>
                                @elseif($isExpiringSoon)
                                    <span class="badge bg-soft-warning text-warning px-2 py-1 fw-bold fs-11 border border-warning-subtle">
                                        <i class="feather-clock me-1"></i>Expiring Soon ({{ $daysLeft }}d left)
                                    </span>
                                @else
                                    <span class="badge bg-soft-success text-success px-2 py-1 fw-bold fs-11 border border-success-subtle">
                                        <i class="feather-check-circle me-1"></i>Fresh ({{ $daysLeft }}d left)
                                    </span>
                                @endif
                            </td>
                            <td class="text-end font-monospace text-muted fw-semibold fs-12">
                                {{ number_format($b->quantity, 0) }}
                            </td>
                            <td class="text-end pe-3 font-monospace fw-bold fs-13 {{ $b->available_qty > 0 ? 'text-primary' : 'text-muted' }}">
                                {{ number_format($b->available_qty, 0) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="feather-layers fs-1 d-block mb-2 opacity-50 text-warning"></i>
                                <span class="fw-bold fs-14 d-block text-dark mb-1">No Inventory Batches Found</span>
                                <span class="fs-12 text-muted">Batches are registered automatically during Goods Receipt Notes (GRN) or Opening Stock Entry.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <!-- Pagination Component -->
        <div class="pt-3">
            <x-ui.pagination 
                :currentPage="$batches->currentPage()" 
                :totalPages="$batches->lastPage()" 
                :totalResults="$batches->total()" 
                :perPage="$batches->perPage()" />
        </div>

    </x-ui.odoo-form-ui>
</div>
@endsection
