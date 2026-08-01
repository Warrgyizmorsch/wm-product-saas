@extends('layouts.duralux')

@section('title', 'Batch Expiry Report | SaaS ERP')
@section('page-title', 'Batch Expiry & Shelf-Life Report')
@section('breadcrumb', 'Inventory / Reports / Expiry')

@section('content')
<div class="erp-single-panel">
    <!-- Toolbar: Header, Tabs/Filter & Action Buttons -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="d-flex align-items-center flex-wrap gap-2">
            <h5 class="fw-bold text-dark mb-0 me-1"><i class="feather-clock text-primary me-2"></i>Batch Expiry Tracker</h5>
            <a href="{{ route('inventory.reports.expiry', ['days' => '30']) }}" class="btn btn-xs {{ request('days') == '30' ? 'btn-primary' : 'btn-light border' }}">
                Expiring in 30 Days
            </a>
            <a href="{{ route('inventory.reports.expiry', ['days' => '60']) }}" class="btn btn-xs {{ request('days') == '60' || !request('days') ? 'btn-primary' : 'btn-light border' }}">
                Expiring in 60 Days
            </a>
            <a href="{{ route('inventory.reports.expiry', ['days' => '90']) }}" class="btn btn-xs {{ request('days') == '90' ? 'btn-primary' : 'btn-light border' }}">
                Expiring in 90 Days
            </a>
            <a href="{{ route('inventory.reports.expiry', ['days' => 'expired']) }}" class="btn btn-xs {{ request('days') == 'expired' ? 'btn-danger text-white' : 'btn-soft-danger text-danger' }}">
                Already Expired
            </a>
        </div>

        <div class="d-flex align-items-center flex-wrap gap-2">
            <x-ui.button href="javascript:window.print()" variant="light" class="border btn-sm" icon="feather-printer">
                Print Report
            </x-ui.button>
        </div>
    </div>

    <!-- Table Component -->
    <div class="table-responsive">
        <x-ui.odoo-form-ui type="table" id="expiryReportTable">
            <thead>
                <tr>
                    <th style="width: 3%" class="text-center">
                        <input type="checkbox" class="form-check-input">
                    </th>
                    <th>Batch #</th>
                    <th>Product Name</th>
                    <th>Warehouse</th>
                    <th class="text-end">Available Qty</th>
                    <th>Manufacturing Date</th>
                    <th>Expiry Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($batches as $batch)
                    @php
                        $expiryDate = \Carbon\Carbon::parse($batch->expiry_date);
                        $diffDays = (int)now()->diffInDays($expiryDate, false);
                    @endphp
                    <tr>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input">
                        </td>
                        <td><strong class="text-primary font-monospace">{{ $batch->batch_number }}</strong></td>
                        <td>
                            <strong class="text-dark d-block">{{ $batch->product->name ?? 'N/A' }}</strong>
                            <small class="text-muted">SKU: {{ $batch->product->sku ?? '-' }}</small>
                        </td>
                        <td><span class="fw-semibold text-dark">{{ $batch->warehouse->name ?? 'N/A' }}</span></td>
                        <td class="text-end fw-bold text-dark">{{ number_format($batch->available_qty, 2) }}</td>
                        <td>{{ $batch->manufacturing_date ? \Carbon\Carbon::parse($batch->manufacturing_date)->format('d M Y') : '-' }}</td>
                        <td class="fw-bold {{ $diffDays < 0 ? 'text-danger' : 'text-warning' }}">{{ $expiryDate->format('d M Y') }}</td>
                        <td>
                            @if($diffDays < 0)
                                <x-ui.badge variant="danger">Expired {{ abs($diffDays) }} days ago</x-ui.badge>
                            @else
                                <x-ui.badge variant="warning">{{ $diffDays }} days left</x-ui.badge>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-success fw-bold">
                            <i class="feather-check-circle fs-1 d-block mb-3 text-success opacity-75"></i>
                            No expiring batches found for the selected time range.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.odoo-form-ui>
    </div>

    <!-- Pagination Section -->
    @if($batches instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <div class="pt-3">
            <x-ui.pagination 
                :currentPage="$batches->currentPage()" 
                :totalPages="$batches->lastPage()" 
                :totalResults="$batches->total()" 
                :perPage="$batches->perPage()" />
        </div>
    @endif
</div>
@endsection
