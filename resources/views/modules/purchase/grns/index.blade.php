@extends('layouts.duralux')

@section('title', __('purchase.goods_receipt_notes') . ' | SaaS ERP')
@section('page-title', __('purchase.goods_receipt_notes_grn'))
@section('breadcrumb', __('ui.purchase') . ' / ' . __('purchase.goods_receipt_notes'))

@push('styles')
    <style>
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
        .action-icon-btn.download-btn:hover {
            background-color: color-mix(in srgb, var(--bs-primary) 10%, transparent) !important;
            border-color: var(--bs-primary) !important;
            color: var(--bs-primary) !important;
        }
    </style>
@endpush

@section('page-actions')
    <div class="d-flex gap-2 flex-wrap">
        <x-ui.button href="{{ route('purchase.grns.pending') }}" variant="warning" icon="feather-clock" class="text-dark fw-semibold">
            {{ __('purchase.pending_goods_receipts') }}
        </x-ui.button>
        <x-ui.button href="{{ route('purchase.grns.create') }}" variant="primary" icon="feather-plus">
            {{ __('purchase.new_goods_receipt') }}
        </x-ui.button>
    </div>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <!-- Toast Notifications -->
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif



        <!-- Header Title & Common Filter -->
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0"><i class="feather-truck text-primary me-2"></i>{{ __('purchase.all_grns') }}</h5>
                <p class="text-muted fs-12 mb-0">{{ __('purchase.all_grns_help') }}</p>
            </div>

            <!-- Common Filter Panel -->
            <form method="GET" action="{{ route('purchase.grns.index') }}" class="d-inline">
                <x-ui.filter :label="__('ui.filter') ?? 'Filters'" offset="0, 5">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('purchase.filter_options') }}</h6>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.search_keyword') }}</label>
                        <x-ui.odoo-form-ui type="input" name="search" placeholder="{{ __('purchase.search_grn_placeholder') }}" value="{{ request('search') }}" />
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.status') }}</label>
                        <x-ui.odoo-form-ui type="select" name="status">
                            <option value="">{{ __('purchase.all_statuses') }}</option>
                            <option value="Draft" @selected(request('status') === 'Draft')>{{ __('purchase.status_draft') }}</option>
                            <option value="Approved" @selected(request('status') === 'Approved')>{{ __('purchase.status_approved') }}</option>
                            <option value="Cancelled" @selected(request('status') === 'Cancelled')>{{ __('purchase.status_cancelled') }}</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.date_from') }}</label>
                        <x-ui.odoo-form-ui type="input" inputType="date" name="date_from" value="{{ request('date_from') }}" />
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.date_to') }}</label>
                        <x-ui.odoo-form-ui type="input" inputType="date" name="date_to" value="{{ request('date_to') }}" />
                    </div>

                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="{{ route('purchase.grns.index') }}" class="btn btn-sm btn-light border">{{ __('purchase.reset') }}</a>
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('purchase.apply_filters') }}</button>
                    </div>
                </x-ui.filter>
            </form>
        </div>

        <!-- Table View using Common Odoo Table Component -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="allGrnTable">
                <thead>
                    <tr>
                        <th style="width: 12%">{{ __('purchase.grn_number') }}</th>
                        <th style="width: 12%">{{ __('purchase.po_number') }}</th>
                        <th style="width: 16%">{{ __('purchase.supplier_vendor') }}</th>
                        <th style="width: 14%">{{ __('purchase.warehouse') }}</th>
                        <th style="width: 12%">{{ __('purchase.receipt_date') }}</th>
                        <th style="width: 10%" class="text-center">{{ __('purchase.received_qty') }}</th>
                        <th style="width: 10%" class="text-center">{{ __('purchase.status') }}</th>
                        <th style="width: 12%">{{ __('purchase.created_by') }}</th>
                        <th style="width: 12%" class="text-end">{{ __('purchase.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($grns as $grn)
                        @php
                            $recQty = (float)$grn->items->sum('received_qty');
                            $badgeClass = match($grn->status) {
                                'Draft' => 'bg-soft-warning text-warning',
                                'Approved' => 'bg-soft-success text-success',
                                'Cancelled' => 'bg-soft-danger text-danger',
                                default => 'bg-soft-secondary text-secondary',
                            };
                        @endphp
                        <tr>
                            <td class="ps-4 fw-bold font-monospace">
                                <a href="{{ route('purchase.grns.show', $grn->id) }}" class="text-primary">
                                    {{ $grn->grn_number }}
                                </a>
                            </td>
                            <td class="font-monospace fw-semibold">
                                @if($grn->purchaseOrder)
                                    <a href="{{ route('purchase.orders.show', $grn->purchase_order_id) }}" class="text-dark">
                                        {{ $grn->purchaseOrder->purchase_order_number }}
                                    </a>
                                @else
                                    <span class="text-muted">{{ __('purchase.direct_receipt') }}</span>
                                @endif
                            </td>
                            <td class="fw-semibold text-dark">{{ $grn->vendor?->name ?? 'N/A' }}</td>
                            <td>
                                <i class="feather-archive me-1 text-muted"></i>{{ $grn->warehouse?->name ?? __('purchase.main_warehouse') }}
                            </td>
                            <td>{{ $grn->received_date ? $grn->received_date->format('d-M-Y') : '—' }}</td>
                            <td class="text-center font-monospace fw-bold text-primary">{{ number_format($recQty, 2) }}</td>
                            <td class="text-center">
                                <span class="badge {{ $badgeClass }} px-2.5 py-1 fw-bold fs-11">{{ __('purchase.status_' . strtolower($grn->status)) }}</span>
                            </td>
                            <td>
                                <div class="fs-12 fw-semibold text-dark">{{ $grn->creator?->name ?? __('purchase.system') }}</div>
                                <div class="fs-11 text-muted">{{ $grn->created_at->format('d-M H:i') }}</div>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <a href="{{ route('purchase.grns.show', $grn->id) }}" class="action-icon-btn view-btn" title="{{ __('purchase.view_details') }}" data-bs-toggle="tooltip">
                                        <i class="feather feather-eye"></i>
                                    </a>
                                    <a href="{{ route('purchase.grns.download', $grn->id) }}" class="action-icon-btn download-btn" title="{{ __('purchase.download_pdf') }}" data-bs-toggle="tooltip">
                                        <i class="feather feather-download"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="feather-inbox fs-36 text-secondary d-block mb-2"></i>
                                <h6 class="fw-bold text-dark mb-1">{{ __('purchase.no_grns_found') }}</h6>
                                <p class="fs-12 mb-0">{{ __('purchase.no_grns_help') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <!-- Common Pagination Component -->
        <x-ui.pagination 
            :currentPage="$grns->currentPage()" 
            :totalPages="$grns->lastPage()" 
            :totalResults="$grns->total()" 
            :perPage="$grns->perPage()" 
        />
    </div>
@endsection
