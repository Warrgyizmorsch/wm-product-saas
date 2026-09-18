@extends('layouts.duralux')

@section('title', __('inventory.batch_expiry_report') . ' | SaaS ERP')
@section('page-title', __('inventory.batch_expiry_shelf_life_report'))
@section('breadcrumb', __('inventory.inventory_reports_expiry'))

@section('content')
<div class="erp-single-panel">
    <!-- Toolbar: Header, Tabs/Filter & Action Buttons -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="d-flex align-items-center flex-wrap gap-2">
            <h5 class="fw-bold text-dark mb-0 me-1"><i class="feather-clock text-primary me-2"></i>{{ __('inventory.batch_expiry_tracker') }}</h5>
            <a href="{{ route('inventory.reports.expiry', ['days' => '30']) }}" class="btn btn-xs {{ request('days') == '30' ? 'btn-primary' : 'btn-light border' }}">
                {{ __('inventory.expiring_in_30_days') }}
            </a>
            <a href="{{ route('inventory.reports.expiry', ['days' => '60']) }}" class="btn btn-xs {{ request('days') == '60' || !request('days') ? 'btn-primary' : 'btn-light border' }}">
                {{ __('inventory.expiring_in_60_days') }}
            </a>
            <a href="{{ route('inventory.reports.expiry', ['days' => '90']) }}" class="btn btn-xs {{ request('days') == '90' ? 'btn-primary' : 'btn-light border' }}">
                {{ __('inventory.expiring_in_90_days') }}
            </a>
            <a href="{{ route('inventory.reports.expiry', ['days' => 'expired']) }}" class="btn btn-xs {{ request('days') == 'expired' ? 'btn-danger text-white' : 'btn-soft-danger text-danger' }}">
                {{ __('inventory.already_expired') }}
            </a>
        </div>

        <div class="d-flex align-items-center flex-wrap gap-2">
            <x-ui.button href="javascript:window.print()" variant="light" class="border btn-sm" icon="feather-printer">
                {{ __('inventory.print_report') }}
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
                    <th>{{ __('inventory.batch_number') }}</th>
                    <th>{{ __('inventory.product_name') }}</th>
                    <th>{{ __('inventory.warehouse') }}</th>
                    <th class="text-end">{{ __('inventory.available_stock_qty') }}</th>
                    <th>{{ __('inventory.manufacturing_date') }}</th>
                    <th>{{ __('inventory.expiry_date') }}</th>
                    <th>{{ __('inventory.status') }}</th>
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
                                <x-ui.badge variant="danger">{{ __('inventory.expired_days_ago', ['days' => abs($diffDays)]) }}</x-ui.badge>
                            @else
                                <x-ui.badge variant="warning">{{ $diffDays }} {{ __('inventory.days_left') }}</x-ui.badge>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-success fw-bold">
                            <i class="feather-check-circle fs-1 d-block mb-3 text-success opacity-75"></i>
                            {{ __('inventory.no_expiring_batches_found') }}
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
