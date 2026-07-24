@extends('layouts.duralux')

@section('title', 'Material Requests | SaaS ERP')
@section('page-title', 'Material Request Slips')
@section('breadcrumb', 'Material Requests')

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
    <!-- Toast Notifications -->
    @if (session('success'))
        <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
    @endif
    @if (session('error'))
        <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
    @endif

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <!-- Header Controls & System Filter -->
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2 pb-2 border-bottom">
            <div>
                <h5 class="fw-bold text-dark mb-0">Material Request Slips</h5>
                <p class="text-muted fs-12 mb-0">Manage material requisition slips generated from Production Orders (MOs).</p>
            </div>

            <!-- Common Filter Component -->
            <form method="GET" action="{{ route('sales.material-requests.index') }}" class="d-inline">
                <x-ui.filter :label="__('ui.filter') ?? 'Filters'" offset="0, 5">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keyword</label>
                        <x-ui.odoo-form-ui type="input" name="search" placeholder="Search Slip No or PO..." value="{{ request('search') }}" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                        <x-ui.odoo-form-ui type="select" name="status">
                            <option value="">All Statuses</option>
                            <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                            <option value="partial" @selected(request('status') === 'partial')>Partially Issued</option>
                            <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                        </x-ui.odoo-form-ui>
                    </div>
                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="{{ route('sales.material-requests.index') }}" class="btn btn-sm btn-light border">Reset</a>
                        <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                    </div>
                </x-ui.filter>
            </form>
        </div>

        <!-- Table of requisition slips -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="mrTable">
                <thead>
                    <tr>
                        <th style="width: 20%">Slip Number</th>
                        <th style="width: 30%">Production Order</th>
                        <th style="width: 20%">Requisition Date</th>
                        <th style="width: 15%" class="text-center">Status</th>
                        <th style="width: 15%" class="text-end">Actions</th>
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
                                    Product: {{ $slip->order->product->name ?? '—' }}
                                </div>
                            </td>
                            <td>{{ date('d-M-Y', strtotime($slip->requisition_date)) }}</td>
                            <td class="text-center">
                                @if($slip->status === 'completed')
                                    <span class="badge bg-soft-success text-success px-2.5 py-1 fw-bold fs-11">Fully Issued</span>
                                @elseif($slip->status === 'partial')
                                    <span class="badge bg-soft-warning text-warning px-2.5 py-1 fw-bold fs-11">Partially Issued</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger px-2.5 py-1 fw-bold fs-11">Pending Issue</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <a href="{{ route('sales.material-requests.show', $slip->id) }}" class="action-icon-btn view-btn" title="View Details" data-bs-toggle="tooltip">
                                        <i class="feather feather-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="feather-info fs-36 text-secondary d-block mb-2"></i>
                                <h6 class="fw-bold text-dark mb-1">No Material Request Slips Found</h6>
                                <p class="fs-12 mb-0">There are currently no requisition slips matching the filters.</p>
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
