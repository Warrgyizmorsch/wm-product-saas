@extends('layouts.duralux')

@section('title', 'Rework Orders Register | SaaS ERP')
@section('page-title', 'Rework Orders')
@section('breadcrumb', 'Rework')

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
            <h5 class="fw-bold text-dark mb-0">Rework Orders Log</h5>
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
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'cost_estimate', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'cost_estimate' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Est Cost (Asc)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'cost_estimate', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'cost_estimate' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Est Cost (Desc)</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Custom Filter Component -->
                <form method="GET" action="{{ route('production.rework.index') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                            <x-ui.input name="search" placeholder="Search rework order number..." value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.select name="status" :options="[
                                '' => 'All Statuses',
                                'draft' => 'Draft',
                                'scheduled' => 'Scheduled',
                                'running' => 'Running / In Progress',
                                'completed' => 'Completed'
                            ]" selected="{{ request('status') }}" data-select2-selector="default" />
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('production.rework.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- Rework Table -->
        <x-ui.odoo-form-ui type="table">
            <thead>
                <tr>
                    <th style="width: 15%">Rework Number</th>
                    <th style="width: 15%">Linked NCR</th>
                    <th style="width: 15%">Status</th>
                    <th style="width: 15%" class="text-end">Estimated Cost</th>
                    <th style="width: 15%" class="text-end">Actual Cost</th>
                    <th style="width: 15%">Labor Hours</th>
                    <th class="text-end" style="width: 10%">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reworks as $rwk)
                    <tr>
                        <td class="font-monospace fw-bold text-dark">
                            <a href="{{ route('production.rework.show', $rwk->id) }}" class="text-dark hover-primary">
                                {{ $rwk->rework_number }}
                            </a>
                        </td>
                        <td>
                            @if($rwk->ncr)
                                <a href="{{ route('production.ncrs.show', $rwk->ncr->id) }}" class="fw-semibold text-danger">
                                    {{ $rwk->ncr->ncr_number }}
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($rwk->status === 'completed')
                                <span class="erp-badge-active">Completed</span>
                            @elseif($rwk->status === 'running')
                                <span class="erp-badge-pending">Running</span>
                            @else
                                <span class="erp-badge-draft text-uppercase">{{ $rwk->status }}</span>
                            @endif
                        </td>
                        <td class="text-end text-muted">${{ number_format($rwk->cost_estimate, 2) }}</td>
                        <td class="text-end fw-bold text-dark">${{ number_format($rwk->actual_cost, 2) }}</td>
                        <td class="text-dark">{{ number_format($rwk->labor_hours_actual, 2) }} hrs</td>
                        <td class="text-end">
                            <x-ui.action-dropdown :viewUrl="route('production.rework.show', $rwk->id)" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="feather-alert-circle me-2 fs-16"></i>No Rework Orders found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.odoo-form-ui>

        <div class="mt-4">
            {{ $reworks->links() }}
        </div>
    </div>
@endsection
