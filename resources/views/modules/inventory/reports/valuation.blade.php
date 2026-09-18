@extends('layouts.duralux')

@section('title', __('inventory.stock_valuation_report') . ' | SaaS ERP')
@section('page-title', __('inventory.inventory_asset_valuation_report'))
@section('breadcrumb', __('inventory.inventory_reports_valuation'))

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
                <span>{{ __('inventory.all_items_valuation') }} ({!! format_currency($totalValuation) !!})</span>
            </a>
            <a href="{{ request()->fullUrlWithQuery(['item_category' => 'fg', 'page' => 1]) }}" 
               class="ledger-tab-link {{ request('item_category') === 'fg' ? 'active' : '' }}">
                <i class="feather-package fs-15 text-success"></i>
                <span>{{ __('inventory.finished_goods_valuation') }} ({!! format_currency($fgValuation) !!})</span>
            </a>
            <a href="{{ request()->fullUrlWithQuery(['item_category' => 'rm', 'page' => 1]) }}" 
               class="ledger-tab-link {{ request('item_category') === 'rm' ? 'active' : '' }}">
                <i class="feather-layers fs-15 text-info"></i>
                <span>{{ __('inventory.raw_materials_valuation') }} ({!! format_currency($rmValuation) !!})</span>
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
                    <span class="fs-11 fw-bold text-uppercase text-muted d-block text-truncate mb-1" title="{{ __('inventory.total_stock_valuation') }}">{{ __('inventory.total_stock_valuation') }}</span>
                    <div class="fs-16 fw-bold text-dark text-nowrap text-truncate">{!! format_currency($totalValuation) !!}</div>
                    <span class="fs-11 text-muted d-block text-truncate mt-0.5">{{ __('inventory.total_physical_asset_value') }}</span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 col-12 d-flex">
            <div class="card h-100 w-100 border-0 shadow-sm rounded-3 p-3 bg-white d-flex align-items-center flex-row">
                <div class="avatar-text bg-soft-success text-success rounded-3 flex-shrink-0 me-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                    <i class="feather-package fs-5"></i>
                </div>
                <div class="overflow-hidden flex-grow-1">
                    <span class="fs-11 fw-bold text-uppercase text-muted d-block text-truncate mb-1" title="{{ __('inventory.finished_goods_fg_valuation') }}">{{ __('inventory.finished_goods_fg_valuation') }}</span>
                    <div class="fs-16 fw-bold text-dark text-nowrap text-truncate">{!! format_currency($fgValuation) !!}</div>
                    <span class="fs-11 text-muted d-block text-truncate mt-0.5">{{ __('inventory.finished_stock') }} ({{ number_format($fgQty, 2) }} {{ __('inventory.units') }})</span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 col-12 d-flex">
            <div class="card h-100 w-100 border-0 shadow-sm rounded-3 p-3 bg-white d-flex align-items-center flex-row">
                <div class="avatar-text bg-soft-info text-info rounded-3 flex-shrink-0 me-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                    <i class="feather-layers fs-5"></i>
                </div>
                <div class="overflow-hidden flex-grow-1">
                    <span class="fs-11 fw-bold text-uppercase text-muted d-block text-truncate mb-1" title="{{ __('inventory.raw_materials_wip_valuation') }}">{{ __('inventory.raw_materials_wip_valuation') }}</span>
                    <div class="fs-16 fw-bold text-dark text-nowrap text-truncate">{!! format_currency($rmValuation) !!}</div>
                    <span class="fs-11 text-muted d-block text-truncate mt-0.5">{{ __('inventory.raw_materials_and_wip') }} ({{ number_format($rmQty, 2) }} {{ __('inventory.units') }})</span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 col-12 d-flex">
            <div class="card h-100 w-100 border-0 shadow-sm rounded-3 p-3 bg-white d-flex align-items-center flex-row">
                <div class="avatar-text bg-soft-warning text-warning rounded-3 flex-shrink-0 me-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                    <i class="feather-box fs-5"></i>
                </div>
                <div class="overflow-hidden flex-grow-1">
                    <span class="fs-11 fw-bold text-uppercase text-muted d-block text-truncate mb-1" title="{{ __('inventory.total_on_hand_quantity') }}">{{ __('inventory.total_on_hand_quantity') }}</span>
                    <div class="fs-16 fw-bold text-dark text-nowrap text-truncate">{{ number_format($totalQty, 2) }} {{ __('inventory.units') }}</div>
                    <span class="fs-11 text-muted d-block text-truncate mt-0.5">{{ __('inventory.physical_stock_across_warehouses') }}</span>
                </div>
            </div>
        </div>
    </div>

    <x-ui.odoo-form-ui type="sheet">
        <!-- Toolbar: Header, Search, Filter & Action Buttons -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <h5 class="fw-bold text-dark mb-0 me-2">
                    <i class="feather-pie-chart text-primary me-2"></i>{{ __('inventory.stock_asset_valuation_matrix') }}
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
                        placeholder="{{ __('inventory.search_product_or_sku') }}" 
                        value="{{ request('search') }}"
                        style="box-shadow: none; height: 32px; width: 220px;"
                    >
                </form>

                <!-- Filter Component -->
                <form method="GET" action="{{ route('inventory.reports.valuation') }}" class="d-inline">
                    @if(request('item_category')) <input type="hidden" name="item_category" value="{{ request('item_category') }}"> @endif
                    @if(request('search')) <input type="hidden" name="search" value="{{ request('search') }}"> @endif

                    <x-ui.filter :label="__('inventory.filter')" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('inventory.filter_valuation') }}</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('inventory.warehouse_location') }}</label>
                            <x-ui.odoo-form-ui type="select" name="warehouse_id">
                                <option value="">— {{ __('inventory.all_warehouses') }} —</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" @selected(request('warehouse_id') == $wh->id)>{{ $wh->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('inventory.reports.valuation') }}" class="btn btn-sm btn-light border">{{ __('inventory.reset') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('inventory.apply_filter') }}</button>
                        </div>
                    </x-ui.filter>
                </form>

                <a href="{{ route('inventory.reports.valuation.export', request()->all()) }}" class="btn btn-sm btn-success text-white shadow-xs d-inline-flex align-items-center gap-1.5" title="Export unpaginated valuation data with active filters to Excel/CSV">
                    <i class="feather-download"></i>
                    <span>{{ __('inventory.download_report_excel_csv') }}</span>
                </a>
            </div>
        </div>

        <!-- Valuation Matrix Table -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="stockValuationTable">
                <thead class="table-light bg-light">
                    <tr>
                        <th>{{ __('inventory.product_name') }}</th>
                        <th>{{ __('inventory.sku_code') }}</th>
                        <th>{{ __('inventory.warehouse_location') }}</th>
                        <th class="text-end">{{ __('inventory.on_hand_qty') }}</th>
                        <th class="text-end">{{ __('inventory.unit_cost_rate') }}</th>
                        <th class="text-end pe-3">{{ __('inventory.total_asset_value') }}</th>
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
                                {!! format_currency($unitCost) !!}
                            </td>
                            <td class="text-end pe-3 font-monospace fw-bold fs-13 text-primary">
                                {!! format_currency($value) !!}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="feather-archive fs-1 d-block mb-3 text-light"></i>
                                {{ __('inventory.no_physical_stock_valuation_data') }}
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
