@extends('layouts.duralux')

@section('title', 'Stock Valuation Report | SaaS ERP')
@section('page-title', 'Inventory Asset Valuation Report')
@section('breadcrumb', 'Inventory / Reports / Valuation')

@section('content')
<div class="erp-single-panel">
    <!-- Top Summary Card (Clean White ERP Style) -->
    <div class="card border rounded-3 p-3 bg-white shadow-2xs mb-4">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <span class="fs-11 fw-bold text-muted text-uppercase tracking-wide">Total Physical Inventory Valuation</span>
                <h2 class="fw-bold text-primary mb-0 mt-1">₹{{ number_format($totalValuation, 2) }}</h2>
            </div>
            <div class="avatar-text avatar-lg bg-soft-primary text-primary rounded-3 border border-primary-subtle fs-20">
                <i class="feather-dollar-sign"></i>
            </div>
        </div>
    </div>

    <!-- Toolbar: Header & Action Buttons -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="d-flex align-items-center flex-wrap gap-2">
            <h5 class="fw-bold text-dark mb-0 me-1"><i class="feather-pie-chart text-primary me-2"></i>Stock Asset Valuation Matrix</h5>
        </div>

        <div class="d-flex align-items-center flex-wrap gap-2">
            <x-ui.button href="javascript:window.print()" variant="light" class="border btn-sm" icon="feather-printer">
                Print Valuation Summary
            </x-ui.button>
        </div>
    </div>

    <!-- Table Component -->
    <div class="table-responsive">
        <x-ui.odoo-form-ui type="table" id="stockValuationTable">
            <thead>
                <tr>
                    <th style="width: 3%" class="text-center">
                        <input type="checkbox" class="form-check-input">
                    </th>
                    <th>Product Name</th>
                    <th>SKU Code</th>
                    <th>Warehouse Location</th>
                    <th class="text-end">On Hand Qty</th>
                    <th class="text-end">Unit Cost Rate</th>
                    <th class="text-end">Total Asset Value</th>
                </tr>
            </thead>
            <tbody>
                @forelse($stocks as $stock)
                    @php
                        $value = (float)$stock->quantity * (float)$stock->unit_cost;
                    @endphp
                    <tr>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input">
                        </td>
                        <td>
                            <strong class="text-dark d-block">{{ $stock->product->name ?? 'N/A' }}</strong>
                        </td>
                        <td>
                            <span class="font-monospace text-dark fw-semibold">{{ $stock->product->sku ?? '-' }}</span>
                        </td>
                        <td>
                            <span class="fw-semibold text-dark">{{ $stock->warehouse->name ?? 'N/A' }}</span>
                        </td>
                        <td class="text-end fw-bold text-dark">{{ number_format($stock->quantity, 2) }}</td>
                        <td class="text-end">₹{{ number_format($stock->unit_cost, 2) }}</td>
                        <td class="text-end fw-bold text-primary">₹{{ number_format($value, 2) }}</td>
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
</div>
@endsection
