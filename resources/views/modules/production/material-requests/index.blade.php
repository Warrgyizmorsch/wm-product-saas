@extends('layouts.duralux')

@section('title', 'Material Requests | SaaS ERP')
@section('page-title', 'Material Request Slips')
@section('breadcrumb', 'Material Requests')

@section('content')
    <div class="erp-single-panel">
        <!-- Toast Notifications -->
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <x-ui.odoo-form-ui type="sheet">
            <!-- Header section with actions -->
            <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                <div>
                    <h4 class="fw-bold text-dark mb-0">Material Request Slips</h4>
                    <small class="text-muted fs-12">Manage material requisition slips generated from Production Orders (MOs).</small>
                </div>
            </div>

            <!-- Filters & Search Bar -->
            <form method="GET" action="{{ route('production.material-requests.index') }}" class="mb-4">
                <div class="row g-2">
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="feather-search fs-14"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 fs-13" placeholder="Search by Slip No or Production Order..." value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select fs-13" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                            <option value="partial" @selected(request('status') === 'partial')>Partially Issued</option>
                            <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100 fs-13">Apply Filters</button>
                    </div>
                    <div class="col-md-2">
                        <a href="{{ route('production.material-requests.index') }}" class="btn btn-light border w-100 fs-13">Reset</a>
                    </div>
                </div>
            </form>

            <!-- Table of requisition slips -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 fs-13 text-dark">
                    <thead class="bg-soft-light text-uppercase fs-11 fw-semibold text-muted">
                        <tr>
                            <th style="width: 20%">Slip Number</th>
                            <th style="width: 25%">Production Order</th>
                            <th style="width: 20%">Requisition Date</th>
                            <th style="width: 15%">Status</th>
                            <th style="width: 20%" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($slips as $slip)
                            <tr>
                                <td class="fw-bold">
                                    <a href="{{ route('production.material-requests.show', $slip->id) }}" class="text-primary text-decoration-none">
                                        {{ $slip->requisition_number }}
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-semibold">
                                        {{ $slip->order->order_number ?? 'MO #' . $slip->production_order_id }}
                                    </div>
                                    <div class="text-muted fs-11">
                                        Product: {{ $slip->order->product->name ?? '—' }}
                                    </div>
                                </td>
                                <td>{{ date('d-m-Y', strtotime($slip->requisition_date)) }}</td>
                                <td>
                                    @if($slip->status === 'completed')
                                        <span class="badge bg-success-soft text-success px-2 py-1 fs-11">Fully Issued</span>
                                    @elseif($slip->status === 'partial')
                                        <span class="badge bg-warning-soft text-warning px-2 py-1 fs-11">Partially Issued</span>
                                    @else
                                        <span class="badge bg-danger-soft text-danger px-2 py-1 fs-11">Pending Issue</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('production.material-requests.show', $slip->id) }}" class="btn btn-sm btn-outline-primary border">
                                        <i class="feather-eye me-1"></i> View Details
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted fs-14">
                                    No requisition slips found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination Links -->
            @if($slips->hasPages())
                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                    <span class="fs-12 text-muted">Showing {{ $slips->firstItem() }} to {{ $slips->lastItem() }} of {{ $slips->total() }} records</span>
                    <div>{{ $slips->links() }}</div>
                </div>
            @endif
        </x-ui.odoo-form-ui>
    </div>
@endsection
