@extends('layouts.duralux')

@section('title', 'Pending Freight Bills | SaaS ERP')
@section('page-title', 'Pending Freight Obligations')
@section('breadcrumb', __('ui.purchase') . ' / ' . __('purchase.vendor_bills') . ' / Pending Freight Bills')

@push('styles')
    <style>
        #pendingFreightTable {
            table-layout: auto !important;
            width: 100% !important;
        }
        #pendingFreightTable th {
            white-space: nowrap !important;
            font-size: 11px !important;
            letter-spacing: 0.5px !important;
            padding: 10px 12px !important;
            background-color: #f8fafc !important;
        }
        #pendingFreightTable td {
            vertical-align: middle !important;
            white-space: nowrap !important;
            padding: 10px 12px !important;
        }
        .customer-col {
            max-width: 140px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .transporter-col {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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

@section('content')
    <div class="erp-single-panel">
        <!-- 1. Header Title & Common Filter (Top) -->
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <i class="feather-truck me-2 text-primary"></i>{{ __('purchase.pending_freight_obligations') }}
                </h5>
                <p class="text-muted fs-12 mb-0">{{ __('purchase.pending_freight_obligations_help') }}</p>
            </div>

            <!-- Actions & Common Filter Panel -->
            <div class="d-flex align-items-center flex-wrap gap-2">
                <!-- Search Box (CRM Leads / HRMS Style) -->
                <form method="GET" action="{{ route('purchase.bills.pending-freight') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1" style="height: 34px; min-width: 240px;">
                    @foreach(request()->except(['search', 'page']) as $k => $v)
                        @if(is_scalar($v) && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                    <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark" placeholder="{{ __('purchase.search_dispatch_invoice_transporter') }}" value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                    @if(request('search'))
                        <a href="{{ route('purchase.bills.pending-freight', request()->except(['search', 'page'])) }}" class="text-muted text-decoration-none ms-1" title="Clear Search">
                            <i class="feather-x fs-12"></i>
                        </a>
                    @endif
                </form>

                <a href="{{ route('purchase.bills.create-service') }}" class="btn btn-sm btn-soft-primary fw-bold text-primary px-3 shadow-sm border border-primary-subtle">
                    <i class="feather-plus me-1"></i>{{ __('purchase.create_service_bill') }}
                </a>

                <form method="GET" action="{{ route('purchase.bills.pending-freight') }}" class="d-inline">
                    <x-ui.filter :label="__('ui.filter') ?? 'Filters'" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('purchase.filter_options') }}</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.search_keyword') }}</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="{{ __('purchase.search_dispatch_invoice_transporter') }}" value="{{ request('search') }}" />
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('purchase.bills.pending-freight') }}" class="btn btn-sm btn-light border">{{ __('purchase.reset') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('purchase.apply_filters') }}</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- 2. Tab navigation (Below filter line) -->
        <x-ui.horizontal-tabs id="vendorBillsPendingFreightTabNav" class="mb-3" :tabs="[
            [
                'id' => 'tab-all-bills',
                'label' => __('purchase.tab_all_bills'),
                'active' => false,
                'icon' => 'feather-file-text',
            ],
            [
                'id' => 'tab-pending-bills',
                'label' => __('purchase.tab_pending_inbound_bills') . (($pendingGrnsCount ?? 0) > 0 ? ' (' . $pendingGrnsCount . ')' : ''),
                'active' => false,
                'icon' => 'feather-clock',
            ],
            [
                'id' => 'tab-pending-freight',
                'label' => __('purchase.tab_pending_outbound_freight') . (($pendingFreightCount ?? 0) > 0 ? ' (' . $pendingFreightCount . ')' : ''),
                'active' => true,
                'icon' => 'feather-truck',
            ]
        ]" />

        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="pendingFreightTable" class="mb-0">
                <thead>
                    <tr style="background-color: #e8ecf1 !important;">
                        <th style="min-width: 140px; background-color: #e8ecf1 !important;" class="ps-3">{{ __('purchase.dispatch_number') }}</th>
                        <th style="min-width: 140px; background-color: #e8ecf1 !important;">{{ __('purchase.ref_invoice_number') }}</th>
                        <th style="min-width: 170px; background-color: #e8ecf1 !important;">{{ __('purchase.customer') }}</th>
                        <th style="min-width: 160px; background-color: #e8ecf1 !important;">{{ __('purchase.transporter') }}</th>
                        <th style="min-width: 130px; background-color: #e8ecf1 !important;">{{ __('purchase.lr_bilty') }}</th>
                        <th style="min-width: 100px; background-color: #e8ecf1 !important;">{{ __('purchase.terms') }}</th>
                        <th style="min-width: 130px; background-color: #e8ecf1 !important;" class="text-end">{{ __('purchase.expected_freight') }}</th>
                        <th style="min-width: 100px; background-color: #e8ecf1 !important;" class="text-center">{{ __('purchase.status') }}</th>
                        <th style="min-width: 140px; background-color: #e8ecf1 !important;" class="text-end pe-3">{{ __('purchase.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pendingFreightDispatches as $dispatch)
                        <tr>
                            <td class="ps-3 fw-bold font-monospace fs-12">
                                <a href="{{ route('sales.dispatches.show', $dispatch->id) }}" class="text-primary">
                                    {{ $dispatch->dispatch_number }}
                                </a>
                            </td>
                            <td class="font-monospace fw-semibold fs-12">
                                @if($dispatch->invoice)
                                    <a href="{{ route('sales.invoices.show', $dispatch->invoice_id) }}" class="text-dark">
                                        {{ $dispatch->invoice->invoice_number }}
                                    </a>
                                @elseif($dispatch->salesOrder)
                                    <a href="{{ route('sales.orders.show', $dispatch->sales_order_id) }}" class="text-muted">
                                        {{ $dispatch->salesOrder->sales_order_number }}
                                    </a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="customer-col fw-semibold text-dark fs-12" title="{{ $dispatch->customer?->name ?? 'N/A' }}">
                                {{ $dispatch->customer?->name ?? 'N/A' }}
                            </td>
                            <td class="transporter-col fs-12" title="{{ $dispatch->transporter?->name ?: ($dispatch->carrier ?: 'Transporter N/A') }}">
                                <i class="feather-truck me-1 text-muted"></i>{{ $dispatch->transporter?->name ?: ($dispatch->carrier ?: 'Transporter N/A') }}
                            </td>
                            <td class="font-monospace fs-12">
                                {{ $dispatch->lr_number ?: '—' }}
                            </td>
                            <td>
                                @php
                                    $termClass = match($dispatch->freight_terms) {
                                        'To Be Billed' => 'bg-soft-success text-success border-success-subtle',
                                        'FOR Site', 'Prepaid' => 'bg-soft-info text-info border-info-subtle',
                                        default => 'bg-soft-primary text-primary border-primary-subtle',
                                    };
                                @endphp
                                <span class="badge {{ $termClass }} border px-2 py-0.5 fw-bold fs-11">
                                    {{ $dispatch->freight_terms ?: 'To Be Billed' }}
                                </span>
                            </td>
                            <td class="text-end font-monospace fw-bold text-dark fs-12">
                                {{ format_currency($dispatch->freight_amount) }}
                            </td>
                            <td class="text-center">
                                <span class="badge bg-soft-warning text-warning border border-warning-subtle px-2 py-0.5 fw-bold fs-11">
                                    <i class="feather-clock me-1"></i>{{ __('purchase.pending_bill') }}
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-flex justify-content-end gap-2 align-items-center">
                                    <a href="{{ route('purchase.bills.create-service', ['mode' => 'outbound', 'dispatch_order_id' => $dispatch->id]) }}" class="btn btn-sm btn-success text-white fw-bold shadow-sm px-2.5 py-1 fs-11 d-inline-flex align-items-center gap-1.5 text-nowrap">
                                        <i class="feather-plus-circle fs-12"></i> {{ __('purchase.create_service_bill') }}
                                    </a>
                                    <a href="{{ route('sales.dispatches.show', $dispatch->id) }}" class="action-icon-btn view-btn" title="{{ __('ui.view') }}" data-bs-toggle="tooltip">
                                        <i class="feather-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="feather-check-circle fs-32 text-success d-block mb-1"></i>
                                <h6 class="fw-bold text-dark mb-0 fs-13">{{ __('purchase.no_pending_freight_found') }}</h6>
                                <p class="fs-12 text-muted mb-0">{{ __('purchase.no_pending_freight_help') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        @if($pendingFreightDispatches->hasPages())
            <div class="mt-3">
                {{ $pendingFreightDispatches->links() }}
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $('#tab-all-bills-tab').on('click', function() {
                window.location.href = "{{ route('purchase.bills.index') }}";
            });
            $('#tab-pending-bills-tab').on('click', function() {
                window.location.href = "{{ route('purchase.bills.pending') }}";
            });
        });
    </script>
@endpush
