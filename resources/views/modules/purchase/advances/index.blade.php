@extends('layouts.duralux')

@section('title', __('purchase.advance_payments') . ' | SaaS ERP')
@section('page-title', __('purchase.advance_payments'))
@section('breadcrumb', __('ui.purchase') . ' / ' . __('purchase.advance_payments'))

@push('styles')
    <style>
        #advancesTable th {
            white-space: nowrap !important;
            font-size: 11px !important;
            letter-spacing: 0.5px !important;
        }
        #advancesTable td {
            vertical-align: middle !important;
            white-space: nowrap !important;
        }
        .action-icon-btn {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 32px !important;
            height: 32px !important;
            border-radius: 8px !important;
            border: 1.5px solid #cbd5e1 !important;
            background-color: #ffffff !important;
            color: #475569 !important;
            transition: all 0.28s ease !important;
            text-decoration: none !important;
            cursor: pointer !important;
        }
        .action-icon-btn.view-btn:hover {
            background-color: color-mix(in srgb, var(--bs-primary) 10%, transparent) !important;
            border-color: var(--bs-primary) !important;
            color: var(--bs-primary) !important;
        }
    </style>
@endpush

@section('page-actions')
    <a href="{{ route('purchase.advances.create') }}" class="btn btn-primary fs-12 px-3 fw-semibold">
        <i class="feather-plus me-1.5"></i>{{ __('purchase.record_advance_payment') }}
    </a>
@endsection

@section('content')

    <div class="erp-single-panel">
        <!-- 1. Header Title & Common Filter (Top) -->
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <i class="feather-dollar-sign me-2 text-primary"></i>{{ __('purchase.advance_payments_ledger') }}
                </h5>
                <p class="text-muted fs-12 mb-0">{{ __('purchase.manage_advance_payments_help') }}</p>
            </div>

            <!-- Actions & Common Filter Panel -->
            <div class="d-flex align-items-center flex-wrap gap-2">
                <!-- Search Box -->
                <form method="GET" action="{{ route('purchase.advances.index') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1" style="height: 34px; min-width: 240px;">
                    @foreach(request()->except(['search', 'page']) as $k => $v)
                        @if(is_scalar($v) && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                    <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark" placeholder="{{ __('purchase.search_payment_placeholder') }}" value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                    @if(request('search'))
                        <a href="{{ route('purchase.advances.index', request()->except(['search', 'page'])) }}" class="text-muted text-decoration-none ms-1" title="Clear Search">
                            <i class="feather-x fs-12"></i>
                        </a>
                    @endif
                </form>

                <a href="{{ route('purchase.advances.create') }}" class="btn btn-sm btn-soft-primary fw-bold text-primary px-3 shadow-sm border border-primary-subtle">
                    <i class="feather-plus me-1"></i>{{ __('purchase.record_advance_payment') }}
                </a>

                <!-- Filter popup -->
                <form method="GET" action="{{ route('purchase.advances.index') }}" class="d-inline">
                    <x-ui.filter :label="__('ui.filter') ?? 'Filters'" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('purchase.filter_options') }}</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.search_keyword') }}</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="{{ __('purchase.search_payment_placeholder') }}" value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.sort_by') }}</label>
                            <x-ui.odoo-form-ui type="select" name="sort_by">
                                <option value="id" @selected(request('sort_by') === 'id')>{{ __('purchase.id') }}</option>
                                <option value="payment_number" @selected(request('sort_by') === 'payment_number')>{{ __('purchase.payment_number') }}</option>
                                <option value="payment_date" @selected(request('sort_by') === 'payment_date')>{{ __('purchase.payment_date') }}</option>
                                <option value="amount" @selected(request('sort_by') === 'amount')>{{ __('purchase.paid_amount') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.order_direction') }}</label>
                            <x-ui.odoo-form-ui type="select" name="sort_order">
                                <option value="desc" @selected(request('sort_order', 'desc') === 'desc')>{{ __('purchase.descending') }}</option>
                                <option value="asc" @selected(request('sort_order') === 'asc')>{{ __('purchase.ascending') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('purchase.advances.index') }}" class="btn btn-sm btn-light border">{{ __('purchase.reset') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('purchase.apply_filters') }}</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="advancesTable" class="mb-0">
                <thead>
                    <tr style="background-color: #e8ecf1 !important;">
                        <th style="min-width: 140px; background-color: #e8ecf1 !important;">{{ __('purchase.advance_number') }}</th>
                        <th style="min-width: 180px; background-color: #e8ecf1 !important;">{{ __('purchase.supplier_vendor') }}</th>
                        <th style="min-width: 140px; background-color: #e8ecf1 !important;">{{ __('purchase.purchase_order') }}</th>
                        <th style="min-width: 110px; background-color: #e8ecf1 !important;">{{ __('purchase.date') }}</th>
                        <th style="min-width: 120px; background-color: #e8ecf1 !important;" class="text-center">{{ __('purchase.method') }}</th>
                        <th style="min-width: 140px; background-color: #e8ecf1 !important;">{{ __('purchase.reference_utr') }}</th>
                        <th style="min-width: 120px; background-color: #e8ecf1 !important;" class="text-end pe-3">{{ __('purchase.paid_amount') }}</th>
                        <th style="min-width: 100px; background-color: #e8ecf1 !important;" class="text-center">{{ __('purchase.status') }}</th>
                        <th style="min-width: 80px; background-color: #e8ecf1 !important;" class="text-end pe-3">{{ __('purchase.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($advances as $adv)
                        <tr>
                            <td>
                                <a href="{{ route('purchase.advances.show', $adv->id) }}" class="fw-bold text-primary">
                                    {{ $adv->advance_number ?: $adv->payment_number }}
                                </a>
                            </td>
                            <td>
                                @if($adv->vendor)
                                    <a href="{{ route('purchase.vendors.show', $adv->vendor->id) }}" class="fw-semibold text-dark text-decoration-none">
                                        {{ $adv->vendor->name }}
                                    </a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($adv->purchaseOrder)
                                    <a href="{{ route('purchase.orders.show', $adv->purchaseOrder->id) }}" class="badge bg-soft-primary text-primary fs-11 fw-semibold text-decoration-none">
                                        {{ $adv->purchaseOrder->order_number }}
                                    </a>
                                @else
                                    <span class="text-muted fs-12">—</span>
                                @endif
                            </td>
                            <td class="text-secondary fs-12">
                                {{ $adv->payment_date ? $adv->payment_date->format('d-M-Y') : '—' }}
                            </td>
                            <td class="text-center">
                                @php $methodKey = 'purchase.pay_method_' . strtolower(str_replace(' ', '_', $adv->payment_method ?? '')); @endphp
                                <span class="badge bg-soft-info text-info px-2.5 py-1 fs-11 fw-semibold">
                                    {{ \Illuminate\Support\Facades\Lang::has($methodKey) ? __($methodKey) : ($adv->payment_method ?: 'Bank Transfer') }}
                                </span>
                            </td>
                            <td class="font-monospace fs-12 text-secondary">
                                {{ $adv->reference_number ?: 'N/A' }}
                            </td>
                            <td class="text-end font-monospace fw-bold text-success pe-3">
                                {{ format_currency($adv->amount) }}
                            </td>
                            <td class="text-center">
                                <span class="badge bg-soft-success text-success px-2.5 py-1 fs-11 fw-semibold">
                                    {{ $adv->status ? ucfirst($adv->status) : __('purchase.status_recorded') }}
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-flex justify-content-end gap-1">
                                    <a href="{{ route('purchase.advances.show', $adv->id) }}" class="action-icon-btn view-btn" title="{{ __('purchase.view_details') }}" data-bs-toggle="tooltip">
                                        <i class="feather-eye fs-12"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="feather-dollar-sign fs-36 text-secondary d-block mb-2"></i>
                                <h6 class="fw-bold text-dark mb-1">{{ __('purchase.no_advances_recorded') }}</h6>
                                <p class="fs-12 mb-0">{{ __('purchase.no_advances_recorded_help') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <x-ui.pagination 
            :currentPage="$advances->currentPage()" 
            :totalPages="$advances->lastPage()" 
            :totalResults="$advances->total()" 
            :perPage="$advances->perPage()" 
        />
    </div>

@endsection
