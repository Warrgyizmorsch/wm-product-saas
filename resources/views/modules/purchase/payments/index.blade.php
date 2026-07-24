@extends('layouts.duralux')

@section('title', 'Vendor Payments | SaaS ERP')
@section('page-title', 'Vendor Payments & Allocations')
@section('breadcrumb', 'Purchase / Vendor Payments')

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
    <x-ui.button href="{{ route('purchase.payments.create') }}" variant="primary" icon="feather-plus">
        Register Vendor Payment
    </x-ui.button>
@endsection

@section('content')

    {{-- Session Alerts --}}
    @if (session('success'))
        <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
    @endif

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <!-- Header Controls & System Filter -->
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <i class="feather-credit-card me-2 text-primary"></i>Vendor Payments Ledger
                </h5>
                <p class="text-muted fs-12 mb-0">View vendor payments and multi-bill allocation records</p>
            </div>

            <!-- Common Filter Component -->
            <form method="GET" action="{{ route('purchase.payments.index') }}" class="d-inline">
                <x-ui.filter :label="__('ui.filter') ?? 'Filters'" offset="0, 5">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keyword</label>
                        <x-ui.odoo-form-ui type="input" name="search" placeholder="Search payment no, vendor..." value="{{ request('search') }}" />
                    </div>
                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="{{ route('purchase.payments.index') }}" class="btn btn-sm btn-light border">Reset</a>
                        <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                    </div>
                </x-ui.filter>
            </form>
        </div>

        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="paymentsTable">
                <thead>
                    <tr>
                        <th style="width: 14%">Payment Number</th>
                        <th style="width: 18%">Vendor</th>
                        <th style="width: 10%">Date</th>
                        <th style="width: 10%" class="text-center">Type</th>
                        <th style="width: 10%" class="text-center">Method</th>
                        <th style="width: 16%">Reference UTR</th>
                        <th style="width: 12%" class="text-end">Paid Amount</th>
                        <th style="width: 10%" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $pay)
                        <tr>
                            <td>
                                <a href="{{ route('purchase.payments.show', $pay->id) }}" class="fw-bold text-primary">
                                    {{ $pay->payment_number }}
                                </a>
                            </td>
                            <td class="fw-semibold text-dark">{{ $pay->vendor?->name ?: '—' }}</td>
                            <td>{{ $pay->payment_date ? $pay->payment_date->format('d-M-Y') : '—' }}</td>
                            <td class="text-center">
                                <span class="badge bg-soft-primary text-primary px-2.5 py-1 fs-11 fw-semibold">{{ $pay->payment_type }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-soft-info text-info px-2.5 py-1 fs-11 fw-semibold">{{ $pay->payment_method }}</span>
                            </td>
                            <td class="font-monospace fs-12 text-secondary">{{ $pay->reference_number ?: 'N/A' }}</td>
                            <td class="text-end font-monospace fw-bold text-success">₹{{ number_format($pay->amount, 2) }}</td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <a href="{{ route('purchase.payments.show', $pay->id) }}" class="action-icon-btn view-btn" title="View Details" data-bs-toggle="tooltip">
                                        <i class="feather feather-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="feather-info fs-36 text-secondary d-block mb-2"></i>
                                <h6 class="fw-bold text-dark mb-1">No Payments Registered</h6>
                                <p class="fs-12 mb-0">Register your first payment using the register button.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <x-ui.pagination 
            :currentPage="$payments->currentPage()" 
            :totalPages="$payments->lastPage()" 
            :totalResults="$payments->total()" 
            :perPage="$payments->perPage()" 
        />
    </div>

@endsection
