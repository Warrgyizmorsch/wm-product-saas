@extends('layouts.duralux')

@section('title', __('purchase.landed_cost_vouchers') . ' | SaaS ERP')
@section('page-title', __('purchase.landed_cost_vouchers'))
@section('breadcrumb', __('ui.purchase') . ' / ' . __('purchase.landed_cost_vouchers'))

@push('styles')
    <style>
        .action-icon-btn {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 32px !important;
            height: 32px !important;
            border-radius: 6px !important;
            border: 1px solid #cbd5e1 !important;
            background-color: #ffffff !important;
            color: #475569 !important;
            transition: all 0.2s ease !important;
            text-decoration: none !important;
            cursor: pointer !important;
        }
        .action-icon-btn:hover {
            background-color: var(--bs-primary) !important;
            border-color: var(--bs-primary) !important;
            color: #ffffff !important;
        }
    </style>
@endpush

@section('page-actions')
    <div class="d-flex gap-2 flex-wrap">
        <x-ui.button href="{{ route('purchase.landed-costs.create') }}" variant="primary" icon="feather-plus">
            {{ __('purchase.new_landed_cost_voucher') }}
        </x-ui.button>
    </div>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <!-- Header Title & Common Filter -->
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0"><i class="feather-layers text-primary me-2"></i>{{ __('purchase.landed_cost_vouchers') }}</h5>
                <p class="text-muted fs-12 mb-0">{{ __('purchase.landed_cost_subtitle') }}</p>
            </div>

            <!-- Common Filter Panel -->
            <form method="GET" action="{{ route('purchase.landed-costs.index') }}" class="d-inline">
                <x-ui.filter :label="__('ui.filter') ?? 'Filters'" offset="0, 5">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('purchase.filter_options') }}</h6>

                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="input" :label="__('purchase.search')" name="search" :placeholder="__('purchase.search_voucher_grn_placeholder')" value="{{ request('search') }}" />
                    </div>

                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="select" :label="__('purchase.status')" name="status">
                            <option value="">{{ __('purchase.all_statuses') }}</option>
                            <option value="Draft" @selected(request('status') === 'Draft')>{{ __('purchase.status_draft') }}</option>
                            <option value="Posted" @selected(request('status') === 'Posted')>{{ __('purchase.status_posted') }}</option>
                            <option value="Cancelled" @selected(request('status') === 'Cancelled')>{{ __('purchase.status_cancelled') }}</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="input" :label="__('purchase.date_from')" inputType="date" name="date_from" value="{{ request('date_from') }}" />
                    </div>

                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="input" :label="__('purchase.date_to')" inputType="date" name="date_to" value="{{ request('date_to') }}" />
                    </div>

                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="{{ route('purchase.landed-costs.index') }}" class="btn btn-sm btn-light border">{{ __('purchase.reset') }}</a>
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('purchase.apply_filters') }}</button>
                    </div>
                </x-ui.filter>
            </form>
        </div>

        <!-- Table View using Common Odoo Table Component -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="allLandedCostsTable">
                <thead>
                    <tr>
                        <th style="width: 15%">{{ __('purchase.voucher_no') }}</th>
                        <th style="width: 12%">{{ __('purchase.voucher_date') }}</th>
                        <th style="width: 25%">{{ __('purchase.linked_grns') }}</th>
                        <th style="width: 15%" class="text-end">{{ __('purchase.total_expenses') }} ({{ active_currency_symbol() }})</th>
                        <th style="width: 12%" class="text-center">{{ __('purchase.status') }}</th>
                        <th style="width: 11%" class="text-end">{{ __('purchase.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($landedCosts as $voucher)
                        <tr>
                            <td>
                                <a href="{{ route('purchase.landed-costs.show', $voucher->id) }}" class="fw-bold font-monospace text-primary">
                                    {{ $voucher->voucher_number }}
                                </a>
                            </td>
                            <td>{{ date('d-M-Y', strtotime($voucher->voucher_date)) }}</td>
                            <td>
                                @php
                                    $grnNumbers = $voucher->receipts->pluck('goodsReceiptNote.grn_number')->filter()->implode(', ');
                                @endphp
                                <span class="font-monospace fs-12 text-dark">{{ $grnNumbers ?: '—' }}</span>
                            </td>
                            <td class="text-end fw-bold font-monospace text-dark">
                                {{ active_currency_symbol() }} {{ number_format($voucher->total_expenses, 2) }}
                            </td>
                            <td class="text-center">
                                @php
                                    $statusLower = strtolower($voucher->status);
                                    $badgeVariant = $statusLower === 'posted' ? 'success' : ($statusLower === 'draft' ? 'warning' : 'danger');
                                    $statusLabel = $statusLower === 'posted' ? __('purchase.status_posted') : ($statusLower === 'draft' ? __('purchase.status_draft') : __('purchase.status_cancelled'));
                                @endphp
                                <x-ui.badge :soft="true" :variant="$badgeVariant" class="px-2.5 py-1 fs-11 fw-bold">
                                    {{ $statusLabel }}
                                </x-ui.badge>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('purchase.landed-costs.show', $voucher->id) }}" class="action-icon-btn me-1" title="{{ __('purchase.view_voucher') }}" data-bs-toggle="tooltip">
                                    <i class="feather-eye fs-14"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted fs-13">
                                <i class="feather-info me-1"></i>{{ __('purchase.no_landed_cost_vouchers_found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <!-- Pagination -->
        @if($landedCosts->hasPages())
            <div class="mt-3">
                <x-ui.pagination :paginator="$landedCosts" />
            </div>
        @endif
    </div>
@endsection
