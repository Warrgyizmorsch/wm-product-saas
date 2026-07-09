@extends('layouts.duralux')

@section('title', 'Quality Inspections Log | SaaS ERP')
@section('page-title', 'Quality Inspections Log')
@section('breadcrumb', 'Inspections')

@section('page-actions')
    <a href="{{ route('production.inspections.create') }}" class="btn btn-primary">
        <i class="feather-plus me-2"></i>New Inspection Checklist
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
            <h5 class="fw-bold text-dark mb-0">Quality Inspections Log</h5>
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
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'stage', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'stage' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Stage (A-Z)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'stage', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'stage' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Stage (Z-A)</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Custom Filter Component -->
                <form method="GET" action="{{ route('production.inspections.index') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                            <x-ui.input name="search" placeholder="Search by plan name..." value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Stage</label>
                            <x-ui.select name="stage" :options="[
                                '' => 'All Stages',
                                'incoming' => 'Incoming Material Check',
                                'in_process' => 'In-Process Check',
                                'final' => 'Final Product Quality Check'
                            ]" selected="{{ request('stage') }}" data-select2-selector="default" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.select name="status" :options="[
                                '' => 'All Statuses',
                                'draft' => 'Draft',
                                'submitted' => 'Submitted / Review Required',
                                'approved' => 'Approved / Audited'
                            ]" selected="{{ request('status') }}" data-select2-selector="default" />
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('production.inspections.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- Inspections Table -->
        <x-ui.odoo-form-ui type="table">
            <thead>
                <tr>
                    <th style="width: 10%">ID</th>
                    <th style="width: 25%">Quality Plan</th>
                    <th style="width: 15%">Stage</th>
                    <th style="width: 15%">Status</th>
                    <th style="width: 15%">Result</th>
                    <th style="width: 15%">Order ID</th>
                    <th class="text-end" style="width: 5%">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($inspections as $insp)
                    <tr>
                        <td class="font-monospace fw-bold text-dark">
                            <a href="{{ route('production.inspections.show', $insp->id) }}" class="text-dark hover-primary">
                                #{{ $insp->id }}
                            </a>
                        </td>
                        <td class="fw-semibold text-dark">{{ $insp->plan->name ?? '—' }}</td>
                        <td class="text-uppercase font-monospace fs-10 fw-bold text-muted">{{ $insp->stage }}</td>
                        <td>
                            @if($insp->status === 'approved')
                                <span class="erp-badge-active">Approved</span>
                            @elseif($insp->status === 'submitted')
                                <span class="erp-badge-pending">Submitted</span>
                            @else
                                <span class="erp-badge-draft">Draft</span>
                            @endif
                        </td>
                        <td>
                            @if($insp->result === 'passed')
                                <span class="text-success fw-bold"><i class="feather-check-circle me-1"></i>PASSED</span>
                            @elseif($insp->result === 'failed')
                                <span class="text-danger fw-bold"><i class="feather-alert-triangle me-1"></i>FAILED</span>
                            @else
                                <span class="text-warning fw-bold"><i class="feather-help-circle me-1"></i>PENDING</span>
                            @endif
                        </td>
                        <td>
                            @if($insp->production_order_id)
                                <span class="text-dark">Order #{{ $insp->production_order_id }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <x-ui.action-dropdown :viewUrl="route('production.inspections.show', $insp->id)" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="feather-alert-circle me-2 fs-16"></i>No quality inspections found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.odoo-form-ui>

        <div class="mt-4">
            {{ $inspections->links() }}
        </div>
    </div>
@endsection
