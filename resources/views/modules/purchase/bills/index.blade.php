@extends('layouts.duralux')

@section('title', 'Vendor Bills | SaaS ERP')
@section('page-title', 'Vendor Bills & Invoices')
@section('breadcrumb', 'Purchase / Vendor Bills')

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

    {{-- Session Alerts --}}
    @if (session('success'))
        <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
    @endif
    @if (session('error'))
        <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
    @endif

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <!-- Header Title & Common Filter -->
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <i class="feather-file-text me-2 text-primary"></i>Vendor Bills
                </h5>
                <p class="text-muted fs-12 mb-0">Manage vendor invoices generated from Goods Receipt Notes</p>
            </div>

            <!-- Common Filter Panel -->
            <form method="GET" action="{{ route('purchase.bills.index') }}" class="d-inline">
                <x-ui.filter :label="__('ui.filter') ?? 'Filters'" offset="0, 5">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keyword</label>
                        <x-ui.odoo-form-ui type="input" name="search" placeholder="Search bill no, vendor..." value="{{ request('search') }}" />
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                        <x-ui.odoo-form-ui type="select" name="status">
                            <option value="">All Statuses</option>
                            <option value="Posted" @selected(request('status') === 'Posted')>Posted</option>
                            <option value="Paid" @selected(request('status') === 'Paid')>Paid</option>
                            <option value="Partially Paid" @selected(request('status') === 'Partially Paid')>Partially Paid</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="{{ route('purchase.bills.index') }}" class="btn btn-sm btn-light border">Reset</a>
                        <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                    </div>
                </x-ui.filter>
            </form>
        </div>

        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="billsTable">
                <thead>
                    <tr>
                        <th style="width: 11%">Bill Number</th>
                        <th style="width: 11%">Vendor Invoice No</th>
                        <th style="width: 15%">Vendor</th>
                        <th style="width: 8%">Bill Date</th>
                        <th style="width: 8%">Due Date</th>
                        <th style="width: 9%" class="text-center">Status</th>
                        <th style="width: 9%" class="text-end">Grand Total</th>
                        <th style="width: 9%" class="text-end">Paid Amount</th>
                        <th style="width: 9%" class="text-end">Due Amount</th>
                        <th style="width: 11%" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bills as $bill)
                        @php
                            $badgeClass = 'warning';
                            if ($bill->status === 'Paid') $badgeClass = 'success';
                            elseif ($bill->status === 'Partially Paid') $badgeClass = 'info';
                            elseif ($bill->status === 'Posted') $badgeClass = 'primary';
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
                                    {{ $bill->status }}
                                </span>
                            </td>
                            <td class="text-end font-monospace fw-bold text-dark">₹{{ number_format($bill->grand_total, 2) }}</td>
                            <td class="text-end font-monospace text-success fw-semibold">₹{{ number_format($bill->paid_amount, 2) }}</td>
                            <td class="text-end font-monospace fw-bold text-danger">₹{{ number_format($bill->due_amount, 2) }}</td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <a href="{{ route('purchase.bills.show', $bill->id) }}" class="action-icon-btn view-btn" title="View Details" data-bs-toggle="tooltip">
                                        <i class="feather feather-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="feather-info fs-36 text-secondary d-block mb-2"></i>
                                <h6 class="fw-bold text-dark mb-1">No Vendor Bills Found</h6>
                                <p class="fs-12 mb-0">Vendor Bills are generated from Approved Goods Receipt Notes (GRN).</p>
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
