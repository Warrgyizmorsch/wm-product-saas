@extends('layouts.duralux')

@section('title', 'Non-Conformance Reports Log (NCR) | SaaS ERP')
@section('page-title', 'Non-Conformance Reports (NCR)')
@section('breadcrumb', 'NCR')

@section('page-actions')
    <a href="{{ route('production.ncrs.create') }}" class="btn btn-danger">
        <i class="feather-plus me-2"></i>Log Quality Defect (NCR)
    </a>
@endsection

@section('content')
    {{-- Toast alerts --}}
    @if (session('success'))
        <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
    @endif

    @if (session('error'))
        <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
    @endif

    <div class="erp-single-panel">
        <!-- Toolbar: Sort, Filters -->
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">Non-Conformance Reports Log</h5>
            <div class="d-flex gap-2 ms-auto">
                <!-- Sort dropdown -->
                @php
                    $sortBy = request('sort_by', 'id');
                    $sortOrder = request('sort_order', 'desc');
                @endphp
                <x-ui.sort-dropdown label="Sort">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'id', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'id' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Newest First</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'id', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'id' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Oldest First</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'ncr_number', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'ncr_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>NCR Number (Asc)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'ncr_number', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'ncr_number' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>NCR Number (Desc)</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Custom Filter Component -->
                <form method="GET" action="{{ route('production.ncrs.index') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                            <x-ui.input name="search" placeholder="Search NCR number or description..." value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Category</label>
                            <x-ui.select name="category" :options="[
                                '' => 'All Categories',
                                'material' => 'Material Defect',
                                'process' => 'Process Defect',
                                'machine' => 'Machine Defect',
                                'human_error' => 'Human Error'
                            ]" selected="{{ request('category') }}" data-select2-selector="default" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.select name="status" :options="[
                                '' => 'All Statuses',
                                'open' => 'Open',
                                'under_review' => 'Under Review',
                                'disposition' => 'Disposition Pending',
                                'closed' => 'Closed'
                            ]" selected="{{ request('status') }}" data-select2-selector="default" />
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('production.ncrs.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- NCR Table -->
        <x-ui.odoo-form-ui type="table">
            <thead>
                <tr>
                    <th style="width: 15%">NCR Number</th>
                    <th style="width: 15%">Category</th>
                    <th style="width: 15%">Status</th>
                    <th style="width: 20%">Linked Inspection</th>
                    <th style="width: 15%">Order ID</th>
                    <th style="width: 15%">Created At</th>
                    <th class="text-end" style="width: 5%">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ncrs as $ncr)
                    <tr>
                        <td class="font-monospace fw-bold text-danger">
                            <a href="{{ route('production.ncrs.show', $ncr->id) }}" class="text-danger hover-danger">
                                {{ $ncr->ncr_number }}
                            </a>
                        </td>
                        <td class="text-capitalize text-dark fw-medium">{{ str_replace('_', ' ', $ncr->category) }}</td>
                        <td>
                            @if($ncr->status === 'closed')
                                <span class="erp-badge-active">Closed</span>
                            @elseif($ncr->status === 'open')
                                <span class="erp-badge-draft text-danger">Open</span>
                            @elseif($ncr->status === 'under_review')
                                <span class="erp-badge-pending">Under Review</span>
                            @else
                                <span class="erp-badge-draft text-uppercase">{{ $ncr->status }}</span>
                            @endif
                        </td>
                        <td>
                            @if($ncr->quality_inspection_id)
                                <a href="{{ route('production.inspections.show', $ncr->quality_inspection_id) }}" class="fw-semibold text-primary">
                                    Inspection #{{ $ncr->quality_inspection_id }}
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($ncr->production_order_id)
                                <span class="text-dark">Order #{{ $ncr->production_order_id }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-muted">{{ $ncr->created_at->format('d/m/Y H:i') }}</td>
                        <td class="text-end">
                            <x-ui.action-dropdown :viewUrl="route('production.ncrs.show', $ncr->id)" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="feather-alert-circle me-2 fs-16"></i>No Non-Conformance Reports (NCR) found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.odoo-form-ui>

        <div class="mt-4">
            {{ $ncrs->links() }}
        </div>
    </div>
@endsection
