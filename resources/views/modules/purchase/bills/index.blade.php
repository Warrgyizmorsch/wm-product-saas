@extends('layouts.duralux')

@section('title', __('purchase.vendor_bills') . ' | SaaS ERP')
@section('page-title', __('purchase.vendor_bills_invoices'))
@section('breadcrumb', __('ui.purchase') . ' / ' . __('purchase.vendor_bills'))

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

@section('content')

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <!-- Header Title & Common Filter -->
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <i class="feather-file-text me-2 text-primary"></i>{{ __('purchase.vendor_bills') }}
                </h5>
                <p class="text-muted fs-12 mb-0">{{ __('purchase.manage_vendor_invoices_help') }}</p>
            </div>

            <!-- Common Filter Panel -->
            <form method="GET" action="{{ route('purchase.bills.index') }}" class="d-inline">
                <x-ui.filter :label="__('ui.filter') ?? 'Filters'" offset="0, 5">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('purchase.filter_options') }}</h6>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.search_keyword') }}</label>
                        <x-ui.odoo-form-ui type="input" name="search" placeholder="{{ __('purchase.search_po_placeholder') }}" value="{{ request('search') }}" />
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('purchase.status') }}</label>
                        <x-ui.odoo-form-ui type="select" name="status">
                            <option value="">{{ __('purchase.all_statuses') }}</option>
                            <option value="Posted" @selected(request('status') === 'Posted')>{{ __('purchase.status_posted') }}</option>
                            <option value="Paid" @selected(request('status') === 'Paid')>{{ __('purchase.status_paid') }}</option>
                            <option value="Partially Paid" @selected(request('status') === 'Partially Paid')>{{ __('purchase.status_partially_paid') }}</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="{{ route('purchase.bills.index') }}" class="btn btn-sm btn-light border">{{ __('purchase.reset') }}</a>
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('purchase.apply_filters') }}</button>
                    </div>
                </x-ui.filter>
            </form>
        </div>

        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="billsTable">
                <thead>
                    <tr>
                        <th style="width: 11%">{{ __('purchase.bill_number') }}</th>
                        <th style="width: 11%">{{ __('purchase.vendor_invoice_no') }}</th>
                        <th style="width: 15%">{{ __('purchase.supplier_vendor') }}</th>
                        <th style="width: 8%">{{ __('purchase.bill_date') }}</th>
                        <th style="width: 8%">{{ __('purchase.due_date') }}</th>
                        <th style="width: 9%" class="text-center">{{ __('purchase.status') }}</th>
                        <th style="width: 9%" class="text-end">{{ __('purchase.grand_total') }}</th>
                        <th style="width: 9%" class="text-end">{{ __('purchase.paid_amount') }}</th>
                        <th style="width: 9%" class="text-end">{{ __('purchase.due_amount') }}</th>
                        <th style="width: 11%" class="text-end">{{ __('purchase.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bills as $bill)
                        @php
                            $statusText = match($bill->status) {
                                'Paid' => 'Paid',
                                'Partially Paid' => 'Partially Paid',
                                'Unpaid' => 'Unpaid',
                                'Posted' => 'Posted',
                                'Cancelled' => 'Cancelled',
                                default => $bill->status,
                            };
                            $badgeClass = match($bill->status) {
                                'Paid' => 'success',
                                'Partially Paid' => 'info',
                                'Unpaid' => 'danger',
                                'Posted', 'Draft' => 'warning',
                                'Cancelled' => 'secondary',
                                default => 'secondary',
                            };
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('purchase.bills.show', $bill->id) }}" class="fw-bold text-primary">
                                    {{ $bill->bill_number }}
                                </a>
                                @if($bill->goodsReceiptNote)
                                    <small class="text-muted d-block fs-11">GRN: {{ $bill->goodsReceiptNote->grn_number }}</small>
                                @endif
                            </td>
                            <td class="font-monospace fw-semibold">{{ $bill->vendor_invoice_number ?: '—' }}</td>
                            <td class="fw-semibold text-dark">{{ $bill->vendor?->name ?: '—' }}</td>
                            <td>{{ $bill->bill_date ? $bill->bill_date->format('d-M-Y') : '—' }}</td>
                            <td>{{ $bill->due_date ? $bill->due_date->format('d-M-Y') : '—' }}</td>
                            <td class="text-center">
                                <span class="badge bg-soft-{{ $badgeClass }} text-{{ $badgeClass }} px-2.5 py-1 fs-11 fw-bold">
                                    {{ $statusText }}
                                </span>
                            </td>
                            <td class="text-end font-monospace fw-bold text-dark">₹{{ number_format($bill->grand_total, 2) }}</td>
                            <td class="text-end font-monospace text-success fw-semibold">₹{{ number_format($bill->paid_amount, 2) }}</td>
                            <td class="text-end font-monospace fw-bold text-danger">₹{{ number_format($bill->due_amount, 2) }}</td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <a href="{{ route('purchase.bills.show', $bill->id) }}" class="action-icon-btn view-btn" title="{{ __('purchase.view_details') }}" data-bs-toggle="tooltip">
                                        <i class="feather feather-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="feather-info fs-36 text-secondary d-block mb-2"></i>
                                <h6 class="fw-bold text-dark mb-1">{{ __('purchase.no_vendor_bills_found') }}</h6>
                                <p class="fs-12 mb-0">{{ __('purchase.no_vendor_bills_help') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <x-ui.pagination 
            :currentPage="$bills->currentPage()" 
            :totalPages="$bills->lastPage()" 
            :totalResults="$bills->total()" 
            :perPage="$bills->perPage()" 
        />
    </div>

@endsection
