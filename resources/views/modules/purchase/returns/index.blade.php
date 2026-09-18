@extends('layouts.duralux')

@section('title', __('purchase.purchase_returns') . ' | SaaS ERP')
@section('page-title', __('purchase.purchase_returns'))
@section('breadcrumb', __('ui.purchase') . ' / ' . __('purchase.purchase_returns'))

@push('styles')
    <style>
        #returnsTable th {
            white-space: nowrap !important;
            font-size: 11px !important;
            letter-spacing: 0.5px !important;
        }
        #returnsTable td {
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
    <a href="{{ route('purchase.returns.create') }}" class="btn btn-primary fs-12 px-3 fw-semibold">
        <i class="feather-plus me-1.5"></i>{{ __('purchase.create_return') }}
    </a>
@endsection

@section('content')

    <div class="erp-single-panel">
        <!-- 1. Header Title & Actions (Top) -->
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <i class="feather-rotate-ccw me-2 text-primary"></i>{{ __('purchase.purchase_returns_debit_notes') }}
                </h5>
                <p class="text-muted fs-12 mb-0">{{ __('purchase.manage_purchase_returns_help') }}</p>
            </div>

            <!-- Actions & Common Filter Panel -->
            <div class="d-flex align-items-center flex-wrap gap-2">
                <!-- Search Box -->
                <form method="GET" action="{{ route('purchase.returns.index') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1" style="height: 34px; min-width: 240px;">
                    @foreach(request()->except(['search', 'page']) as $k => $v)
                        @if(is_scalar($v) && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                    <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark" placeholder="{{ __('purchase.search_return_placeholder') }}" value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                    @if(request('search'))
                        <a href="{{ route('purchase.returns.index', request()->except(['search', 'page'])) }}" class="text-muted text-decoration-none ms-1" title="Clear Search">
                            <i class="feather-x fs-12"></i>
                        </a>
                    @endif
                </form>

                <a href="{{ route('purchase.returns.create') }}" class="btn btn-sm btn-soft-primary fw-bold text-primary px-3 shadow-sm border border-primary-subtle">
                    <i class="feather-plus me-1"></i>{{ __('purchase.create_return') }}
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="returnsTable" class="mb-0">
                <thead>
                    <tr style="background-color: #e8ecf1 !important;">
                        <th style="min-width: 150px; background-color: #e8ecf1 !important;">{{ __('purchase.return_number') }}</th>
                        <th style="min-width: 120px; background-color: #e8ecf1 !important;">{{ __('purchase.return_date') }}</th>
                        <th style="min-width: 180px; background-color: #e8ecf1 !important;">{{ __('purchase.supplier_vendor') }}</th>
                        <th style="min-width: 140px; background-color: #e8ecf1 !important;" class="text-end pe-3">{{ __('purchase.refund_amount') }}</th>
                        <th style="min-width: 110px; background-color: #e8ecf1 !important;" class="text-center">{{ __('purchase.status') }}</th>
                        <th style="min-width: 80px; background-color: #e8ecf1 !important;" class="text-end pe-3">{{ __('purchase.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($returns as $ret)
                        @php
                            $retTotal = $ret->total_refund_amount > 0
                                ? $ret->total_refund_amount
                                : ($ret->total_amount > 0 ? $ret->total_amount : $ret->items->sum(fn($i) => (float)$i->quantity * (float)$i->unit_price));
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('purchase.returns.show', $ret->id) }}" class="fw-bold text-primary">
                                    {{ $ret->return_number }}
                                </a>
                            </td>
                            <td class="text-secondary fs-12">
                                {{ $ret->return_date ? date('d-M-Y', strtotime($ret->return_date)) : '—' }}
                            </td>
                            <td>
                                @if($ret->vendor)
                                    <a href="{{ route('purchase.vendors.show', $ret->vendor->id) }}" class="fw-semibold text-dark text-decoration-none">
                                        {{ $ret->vendor->name }}
                                    </a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end font-monospace fw-bold text-dark pe-3">
                                {{ format_currency($retTotal) }}
                            </td>
                            <td class="text-center">
                                @php
                                    $badgeClass = 'bg-soft-secondary text-secondary';
                                    if ($ret->status == 'Completed' || $ret->status == 'Approved') $badgeClass = 'bg-soft-success text-success';
                                    elseif ($ret->status == 'Cancelled' || $ret->status == 'Rejected') $badgeClass = 'bg-soft-danger text-danger';
                                    elseif ($ret->status == 'Pending' || $ret->status == 'Draft') $badgeClass = 'bg-soft-warning text-warning';
                                @endphp
                                <span class="badge {{ $badgeClass }} px-2.5 py-1 fs-11 fw-semibold">{{ $ret->status }}</span>
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-flex justify-content-end gap-1">
                                    <a href="{{ route('purchase.returns.show', $ret->id) }}" class="action-icon-btn view-btn" title="{{ __('purchase.view_return_details') }}" data-bs-toggle="tooltip">
                                        <i class="feather-eye fs-12"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="feather-rotate-ccw fs-36 text-secondary d-block mb-2"></i>
                                <h6 class="fw-bold text-dark mb-1">{{ __('purchase.no_returns_recorded') }}</h6>
                                <p class="fs-12 mb-0">{{ __('purchase.no_returns_help') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        @if(method_exists($returns, 'links'))
            <x-ui.pagination 
                :currentPage="$returns->currentPage()" 
                :totalPages="$returns->lastPage()" 
                :totalResults="$returns->total()" 
                :perPage="$returns->perPage()" 
            />
        @endif
    </div>

@endsection
