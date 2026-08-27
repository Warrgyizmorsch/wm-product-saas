@extends('layouts.duralux')

@section('title', 'Maintenance Work Orders | SaaS ERP')
@section('page-title', 'Maintenance Work Orders')
@section('breadcrumb', 'Work Orders')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reportBreakdownModal">
            <i class="feather-alert-octagon me-2"></i>Report Breakdown
        </button>
        <a href="{{ route('production.maintenance.work-orders.create') }}" class="btn btn-primary">
            <i class="feather-plus me-2"></i>New Work Order
        </a>
    </div>
@endsection

@section('content')
    @php
        $sortBy = request('sort_by', 'created_at');
        $sortOrder = request('sort_order', 'desc');
    @endphp

    <div class="erp-single-panel">
        <!-- Toast Notifications -->
        @if(session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if(session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <!-- Toolbar: Sort, Filters -->
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">Maintenance Work Orders</h5>
            <div class="d-flex gap-2 ms-auto">
                <div id="normal-toolbar" class="d-flex gap-2">
                    <!-- Sort Component -->
                    <x-ui.sort-dropdown label="Sort">
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'desc' ? 'active' : '' }}">
                            <span>Created Date (Newest)</span>
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'created_at' && $sortOrder === 'asc' ? 'active' : '' }}">
                            <span>Created Date (Oldest)</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'priority', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'priority' && $sortOrder === 'desc' ? 'active' : '' }}">
                            <span>Priority (High to Low)</span>
                        </a>
                    </x-ui.sort-dropdown>

                    <!-- Filter Component -->
                    <form method="GET" action="{{ route('production.maintenance.work-orders.index') }}" class="d-inline">
                        <x-ui.filter label="Filter" offset="0, 5">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Work Orders</h6>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                                <x-ui.odoo-form-ui type="input" name="search" placeholder="Search WO number or description..." value="{{ request('search') }}" />
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Machine</label>
                                <x-ui.odoo-form-ui type="select" name="machine_id">
                                    <option value="">All Machines</option>
                                    @foreach($machines as $m)
                                        <option value="{{ $m->id }}" @selected(request('machine_id') == $m->id)>{{ $m->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">WO Type</label>
                                <x-ui.odoo-form-ui type="select" name="type">
                                    <option value="">All Types</option>
                                    <option value="preventive" @selected(request('type') == 'preventive')>Preventive</option>
                                    <option value="breakdown" @selected(request('type') == 'breakdown')>Breakdown</option>
                                    <option value="calibration" @selected(request('type') == 'calibration')>Calibration</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                                <x-ui.odoo-form-ui type="select" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                                    <option value="scheduled" @selected(request('status') === 'scheduled')>Scheduled</option>
                                    <option value="in_progress" @selected(request('status') === 'in_progress')>In Progress</option>
                                    <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                                    <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <a href="{{ route('production.maintenance.work-orders.index') }}" class="btn btn-sm btn-light border">Reset</a>
                                <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                            </div>
                        </x-ui.filter>
                    </form>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-13">
                <thead class="bg-light text-muted">
                    <tr>
                        <th>WO Number</th>
                        <th>Machine</th>
                        <th>Type</th>
                        <th>Priority</th>
                        <th>Technician</th>
                        <th>Status</th>
                        <th>Planned Start</th>
                        <th class="text-end">Total Cost</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($workOrders as $wo)
                        <tr>
                            <td>
                                <a href="{{ route('production.maintenance.work-orders.show', $wo->id) }}" class="fw-bold text-primary">
                                    {{ $wo->work_order_number }}
                                </a>
                            </td>
                            <td>{{ $wo->machine?->name ?? '-' }}</td>
                            <td>
                                <span class="badge bg-soft-{{ $wo->type === 'breakdown' ? 'danger' : 'info' }} text-{{ $wo->type === 'breakdown' ? 'danger' : 'info' }}">
                                    {{ ucfirst($wo->type) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-soft-{{ $wo->priority === 'critical' ? 'danger' : ($wo->priority === 'high' ? 'warning' : 'secondary') }} text-dark">
                                    {{ ucfirst($wo->priority) }}
                                </span>
                            </td>
                            <td>{{ $wo->technician?->name ?? 'Unassigned' }}</td>
                            <td>
                                <span class="badge bg-soft-{{ $wo->status === 'completed' ? 'success' : ($wo->status === 'in_progress' ? 'warning' : ($wo->status === 'cancelled' ? 'danger' : 'secondary')) }} text-dark">
                                    {{ ucfirst(str_replace('_', ' ', $wo->status)) }}
                                </span>
                            </td>
                            <td>{{ $wo->planned_start ? $wo->planned_start->format('Y-m-d H:i') : '-' }}</td>
                            <td class="text-end fw-bold">${{ number_format($wo->total_cost, 2) }}</td>
                            <td class="text-end">
                                <a href="{{ route('production.maintenance.work-orders.show', $wo->id) }}" class="btn btn-outline-primary btn-xs">
                                    View / Execute
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No maintenance work orders found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $workOrders->links() }}
        </div>
    </div>

    <!-- Report Breakdown Modal Component -->
    <x-ui.modal id="reportBreakdownModal" title="<span class='text-danger fw-bold'><i class='feather-alert-octagon me-2'></i>Report Emergency Breakdown</span>" formAction="{{ route('production.maintenance.work-orders.breakdown') }}" submitText="Report & Start Breakdown WO">
        <x-ui.odoo-form-ui type="select" label="Machine" name="machine_id" :required="true">
            <option value="">Select Machine</option>
            @foreach($machines as $m)
                <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->code }})</option>
            @endforeach
        </x-ui.odoo-form-ui>

        <x-ui.odoo-form-ui type="select" label="Priority" name="priority" :required="true">
            <option value="high" selected>High</option>
            <option value="critical">Critical</option>
            <option value="medium">Medium</option>
        </x-ui.odoo-form-ui>

        <x-ui.odoo-form-ui type="textarea" label="Breakdown Description / Reason" name="reason" rows="3" :required="true" placeholder="Describe the failure, error codes, or abnormal sounds..." />
    </x-ui.modal>
@endsection
