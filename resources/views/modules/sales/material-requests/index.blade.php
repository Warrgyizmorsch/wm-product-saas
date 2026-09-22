@extends('layouts.duralux')

@section('title', __('crm.material_requests_page_title'))
@section('page-title', __('crm.material_request_slips'))
@section('breadcrumb', __('crm.material_requests'))

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
    </style>
@endpush

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.import-export-dropdown 
            type="material_requests" 
            :can-import="false" 
            :can-download-template="false" 
            export-route="{{ route('inventory.material-requests.export') }}" />
    </div>
@endsection

@section('content')
    <!-- Toast Notifications -->

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <!-- Header Controls & System Filter -->
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2 pb-2 border-bottom">
            <div>
                <h5 class="fw-bold text-dark mb-0">{{ __('crm.material_request_slips') }}</h5>
                <p class="text-muted fs-12 mb-0">{{ __('crm.manage_material_requisition_slips_desc') }}</p>
            </div>

            <!-- Common Filter Component -->
            <form method="GET" action="{{ route('sales.material-requests.index') }}" class="d-inline">
                <x-ui.filter :label="__('crm.filter')" offset="0, 5">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('crm.filter_options') }}</h6>
                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.search_keyword') }}</label>
                        <x-ui.odoo-form-ui type="input" name="search" placeholder="{{ __('crm.search_slip_or_po_placeholder') }}" value="{{ request('search') }}" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.status') }}</label>
                        <x-ui.odoo-form-ui type="select" name="status">
                            <option value="">{{ __('crm.all_statuses') }}</option>
                            <option value="pending" @selected(request('status') === 'pending')>{{ __('crm.pending_issue') }}</option>
                            <option value="partial" @selected(request('status') === 'partial')>{{ __('crm.partially_issued') }}</option>
                            <option value="completed" @selected(request('status') === 'completed')>{{ __('crm.completed') }}</option>
                        </x-ui.odoo-form-ui>
                    </div>
                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="{{ route('sales.material-requests.index') }}" class="btn btn-sm btn-light border">{{ __('crm.reset') }}</a>
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('crm.apply_filters') }}</button>
                    </div>
                </x-ui.filter>
            </form>
        </div>

        <!-- Table of requisition slips -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="mrTable">
                <thead>
                    <tr>
                        <th style="width: 20%">{{ __('crm.slip_number') }}</th>
                        <th style="width: 30%">{{ __('crm.production_order') }}</th>
                        <th style="width: 20%">{{ __('crm.requisition_date') }}</th>
                        <th style="width: 15%" class="text-center">{{ __('crm.status') }}</th>
                        <th style="width: 15%" class="text-end">{{ __('crm.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($slips as $slip)
                        <tr>
                            <td class="fw-bold">
                                <a href="{{ route('sales.material-requests.show', $slip->id) }}" class="text-primary text-decoration-none font-monospace">
                                    {{ $slip->requisition_number }}
                                </a>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">
                                    {{ $slip->order->order_number ?? 'MO #' . $slip->production_order_id }}
                                </div>
                                <div class="text-muted fs-11">
                                    {{ __('crm.product_label') }} {{ $slip->order->product->name ?? '—' }}
                                </div>
                            </td>
                            <td>{{ date('d-M-Y', strtotime($slip->requisition_date)) }}</td>
                            <td class="text-center">
                                @php
                                    $statusLower = strtolower($slip->status ?? 'pending');
                                @endphp
                                @if(in_array($statusLower, ['fully issued', 'completed', 'issued']))
                                    <span class="badge bg-soft-success text-success px-2.5 py-1 fw-bold fs-11">{{ __('crm.fully_issued') }}</span>
                                @elseif(in_array($statusLower, ['partially issued', 'partial', 'reserved']))
                                    <span class="badge bg-soft-warning text-warning px-2.5 py-1 fw-bold fs-11">{{ $statusLower === 'reserved' ? __('crm.reserved') : __('crm.partially_issued') }}</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger px-2.5 py-1 fw-bold fs-11">{{ __('crm.pending_issue') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <a href="{{ route('sales.material-requests.show', $slip->id) }}" class="action-icon-btn view-btn" title="{{ __('crm.view_details') }}" data-bs-toggle="tooltip">
                                        <i class="feather feather-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="feather-info fs-36 text-secondary d-block mb-2"></i>
                                <h6 class="fw-bold text-dark mb-1">{{ __('crm.no_material_request_slips_found') }}</h6>
                                <p class="fs-12 mb-0">{{ __('crm.no_requisition_slips_matching_filters') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <!-- Pagination -->
        <x-ui.pagination 
            :currentPage="$slips->currentPage()" 
            :totalPages="$slips->lastPage()" 
            :totalResults="$slips->total()" 
            :perPage="$slips->perPage()" 
        />
    </div>
@endsection
