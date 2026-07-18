@extends('layouts.duralux')

@section('title', 'Request for Quotations | SaaS ERP')
@section('page-title', 'Request for Quotations (RFQ)')
@section('breadcrumb')
    Request for Quotations
@endsection

@section('page-actions')
    <x-ui.button href="{{ route('purchase.rfqs.create') }}" variant="primary" icon="feather-plus">
        New RFQ
    </x-ui.button>
@endsection

@section('content')

    @php
        $sortBy = request('sort_by', 'rfq_date');
        $sortOrder = request('sort_order', 'desc');
    @endphp

    <div class="erp-single-panel">
        <!-- Toast Notifications -->
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <!-- Toolbar: Sort, Filters -->
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">RFQs List</h5>
            <div class="d-flex gap-2 ms-auto">
                <!-- Custom Sort Component -->
                <x-ui.sort-dropdown :label="__('crm.sort')">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'rfq_date', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'rfq_date' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>RFQ Date (Latest)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'rfq_date', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'rfq_date' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>RFQ Date (Oldest)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'rfq_number', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'rfq_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>RFQ Number (A-Z)</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Custom Filter Component -->
                <form method="GET" action="{{ route('purchase.rfqs.index') }}" class="d-inline">
                    <x-ui.filter :label="__('ui.filter')" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">All Statuses</option>
                                <option value="Draft" {{ request('status') === 'Draft' ? 'selected' : '' }}>Draft</option>
                                <option value="Sent" {{ request('status') === 'Sent' ? 'selected' : '' }}>Sent</option>
                                <option value="Received" {{ request('status') === 'Received' ? 'selected' : '' }}>Received</option>
                                <option value="Confirmed" {{ request('status') === 'Confirmed' ? 'selected' : '' }}>Confirmed</option>
                                <option value="Cancelled" {{ request('status') === 'Cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('purchase.rfqs.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- RFQs List Table -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="rfqsTable">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input">
                        </th>
                        <th>RFQ Number</th>
                        <th>Vendors / Suppliers</th>
                        <th>RFQ Date</th>
                        <th>Linked Requisition</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rfqs as $rfq)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input">
                            </td>
                            <td>
                                <a href="{{ route('purchase.rfqs.show', $rfq->id) }}" class="fw-bold text-primary">
                                    {{ $rfq->rfq_number }}
                                </a>
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-1">
                                    @foreach($rfq->rfqVendors as $rv)
                                        <div class="fw-semibold text-dark">
                                            <i class="feather-user text-muted me-1 fs-12"></i>{{ $rv->vendor?->name ?? '—' }}
                                            @if($rv->status === 'Received')
                                                <span class="badge bg-soft-success text-success fs-10 ms-1 font-monospace">Quoted</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                            <td>
                                {{ $rfq->rfq_date ? $rfq->rfq_date->format('d-m-Y') : '—' }}
                            </td>
                            <td>
                                @if($rfq->requisition)
                                    <a href="{{ route('purchase.requisitions.show', $rfq->purchase_requisition_id) }}" class="text-decoration-underline text-secondary">
                                        {{ $rfq->requisition->requisition_number }}
                                    </a>
                                @else
                                    <span class="text-muted fs-12">Direct / Manual</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $badgeClass = match($rfq->status) {
                                        'Draft' => 'bg-soft-secondary text-secondary',
                                        'Sent' => 'bg-soft-info text-info',
                                        'Received' => 'bg-soft-warning text-warning',
                                        'Confirmed' => 'bg-soft-success text-success',
                                        'Cancelled' => 'bg-soft-danger text-danger',
                                        default => 'bg-soft-dark text-dark',
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }} px-2.5 py-1 fw-bold fs-11">
                                    {{ $rfq->status }}
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <x-ui.action-dropdown :viewUrl="route('purchase.rfqs.show', $rfq->id)">
                                    @if($rfq->status === 'Draft')
                                        <li>
                                            <a href="{{ route('purchase.rfqs.edit', $rfq->id) }}" class="dropdown-item">
                                                <i class="feather-edit me-2 text-muted fs-12"></i>Edit RFQ
                                            </a>
                                        </li>
                                        <li>
                                            <form action="{{ route('purchase.rfqs.send', $rfq->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="dropdown-item">
                                                    <i class="feather-mail me-2 text-muted fs-12"></i>Send RFQ to Vendors
                                                </button>
                                            </form>
                                        </li>
                                    @endif

                                    @if($rfq->status === 'Received')
                                        <li>
                                            <form action="{{ route('purchase.rfqs.confirm', $rfq->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="dropdown-item">
                                                    <i class="feather-check-circle me-2 text-muted fs-12"></i>Confirm & Finalize
                                                </button>
                                            </form>
                                        </li>
                                    @endif

                                    @if(in_array($rfq->status, ['Draft', 'Sent']))
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form action="{{ route('purchase.rfqs.destroy', $rfq->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this RFQ?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="feather-trash-2 me-2 text-danger fs-12"></i>Delete
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="feather-list fs-1 d-block mb-3 text-light"></i>
                                No Request for Quotations found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <div class="c pt-3">
            <x-ui.pagination 
                :currentPage="$rfqs->currentPage()" 
                :totalPages="$rfqs->lastPage()" 
                :totalResults="$rfqs->total()" 
                :perPage="$rfqs->perPage()" />
        </div>
    </div>
@endsection
