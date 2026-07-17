@extends('layouts.duralux')

@section('title', __('production.wip_management') . ' | SaaS ERP')
@section('page-title', __('production.wip_management'))
@section('breadcrumb', __('production.wip_management'))

@push('styles')
    <style>
        .erp-single-panel {
            display: flex !important;
            flex-direction: column !important;
            min-height: calc(100vh - 180px) !important;
        }
        .table-responsive {
            position: relative;
        }
    </style>
@endpush

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm">
        <!-- Success & Error Messages -->
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif

        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <!-- Toolbar: Sort, Filters -->
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">{{ __('production.wip_list') }}</h5>
            <div class="d-flex gap-2 ms-auto">
                <form method="GET" action="{{ route('production.wip.index') }}" class="d-inline">
                    <x-ui.filter :label="__('ui.filter')" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('production.filter_options') }}</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.search_keywords') }}</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="Search product or order..." value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('production.status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">All Statuses</option>
                                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="quality_hold" {{ request('status') === 'quality_hold' ? 'selected' : '' }}>Quality Hold</option>
                                <option value="rework" {{ request('status') === 'rework' ? 'selected' : '' }}>Rework</option>
                                <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <x-ui.button href="{{ route('production.wip.index') }}" variant="light" size="sm" class="border">
                                {{ __('production.reset') }}
                            </x-ui.button>
                            <x-ui.button type="submit" variant="primary" size="sm">
                                {{ __('production.apply_filters') }}
                            </x-ui.button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- WIP List Table -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table">
                <thead>
                    <tr>
                        <th style="width: 10%">{{ __('production.wip_number') }}</th>
                        <th style="width: 15%">{{ __('production.production_order') }}</th>
                        <th style="width: 20%">{{ __('production.product') }}</th>
                        <th style="width: 15%">{{ __('production.current_operation') }}</th>
                        <th class="text-end" style="width: 10%">{{ __('production.available_qty') }}</th>
                        <th class="text-end" style="width: 10%">{{ __('production.completed_qty') }}</th>
                        <th class="text-end" style="width: 10%">{{ __('production.wip_value') }}</th>
                        <th style="width: 5%">{{ __('production.status') }}</th>
                        <th class="text-end" style="width: 5%">{{ __('production.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($wips as $wip)
                        <tr>
                            <td>
                                <a href="{{ route('production.wip.show', $wip->id) }}" class="fw-bold text-primary hover-primary">
                                    WIP-#{{ str_pad($wip->id, 5, '0', STR_PAD_LEFT) }}
                                </a>
                            </td>
                            <td>
                                <a href="{{ route('production.orders.show', $wip->production_order_id) }}" class="fw-semibold text-dark">
                                    {{ $wip->order->order_number }}
                                </a>
                                @if($wip->batch)
                                    <div class="fs-10 text-muted"><i class="feather-box"></i> {{ $wip->batch->batch_number }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex flex-column">
                                    <span class="fw-semibold text-dark">{{ $wip->product->name }}</span>
                                    <small class="text-muted font-monospace fs-10">{{ $wip->product->sku }}</small>
                                </div>
                            </td>
                            <td>
                                @if($wip->currentRoutingOperation)
                                    <div class="fw-semibold text-dark">{{ $wip->currentRoutingOperation->name }}</div>
                                    <small class="text-muted">{{ $wip->currentWorkCenter->name ?? '' }}</small>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end fw-semibold text-dark">
                                {{ number_format($wip->available_quantity, 2) }}
                            </td>
                            <td class="text-end text-success fw-semibold">
                                {{ number_format($wip->completed_quantity, 2) }}
                            </td>
                            <td class="text-end text-primary fw-bold">
                                ${{ number_format($wip->total_value, 2) }}
                            </td>
                            <td>
                                @if($wip->status === 'active')
                                    <span class="badge bg-soft-success text-success text-uppercase">Active</span>
                                @elseif($wip->status === 'quality_hold')
                                    <span class="badge bg-soft-warning text-warning text-uppercase">Quality Hold</span>
                                @elseif($wip->status === 'rework')
                                    <span class="badge bg-soft-danger text-danger text-uppercase">Rework</span>
                                @else
                                    <span class="badge bg-soft-secondary text-secondary text-uppercase">Completed</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <x-ui.action-dropdown :viewUrl="route('production.wip.show', $wip->id)" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="feather-info fs-24 d-block mb-2"></i>
                                {{ __('production.no_wips_found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <div class="mt-3">
            {{ $wips->links() }}
        </div>
    </div>
@endsection
