@extends('layouts.duralux')

@section('title', 'PM Schedules | SaaS ERP')
@section('page-title', 'Preventive Maintenance Schedules')
@section('breadcrumb', 'PM Schedules')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <form action="{{ route('production.maintenance.schedules.generate-work-orders') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-primary">
                <i class="feather-refresh-cw me-2"></i>Generate Due WOs
            </button>
        </form>
        <a href="{{ route('production.maintenance.schedules.create') }}" class="btn btn-primary">
            <i class="feather-plus me-2"></i>New PM Schedule
        </a>
    </div>
@endsection

@section('content')
    @php
        $sortBy = request('sort_by', 'next_due_date');
        $sortOrder = request('sort_order', 'asc');
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
            <h5 class="fw-bold text-dark mb-0">Preventive Maintenance Schedules</h5>
            <div class="d-flex gap-2 ms-auto">
                <div id="normal-toolbar" class="d-flex gap-2">
                    <!-- Sort Dropdown Component -->
                    <x-ui.sort-dropdown label="Sort">
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'next_due_date', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'next_due_date' && $sortOrder === 'asc' ? 'active' : '' }}">
                            <span>Next Due Date (Asc)</span>
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'next_due_date', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'next_due_date' && $sortOrder === 'desc' ? 'active' : '' }}">
                            <span>Next Due Date (Desc)</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'asc' ? 'active' : '' }}">
                            <span>Schedule Name (A-Z)</span>
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'name' && $sortOrder === 'desc' ? 'active' : '' }}">
                            <span>Schedule Name (Z-A)</span>
                        </a>
                    </x-ui.sort-dropdown>

                    <!-- Filter Component -->
                    <form method="GET" action="{{ route('production.maintenance.schedules.index') }}" class="d-inline">
                        <x-ui.filter label="Filter" offset="0, 5">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter PM Schedules</h6>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                                <x-ui.odoo-form-ui type="input" name="search" placeholder="Search by code or name..." value="{{ request('search') }}" />
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

                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <a href="{{ route('production.maintenance.schedules.index') }}" class="btn btn-sm btn-light border">Reset</a>
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
                        <th>Code</th>
                        <th>Schedule Name</th>
                        <th>Machine</th>
                        <th>Type</th>
                        <th>Frequency</th>
                        <th>Last Completed</th>
                        <th>Next Due</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($schedules as $s)
                        <tr>
                            <td class="fw-bold text-primary">{{ $s->code }}</td>
                            <td>
                                <div class="fw-bold text-dark">{{ $s->name }}</div>
                                <span class="fs-11 text-muted">Est. {{ $s->estimated_duration_hours }} hrs</span>
                            </td>
                            <td>{{ $s->machine?->name ?? '-' }}</td>
                            <td><span class="badge bg-soft-info text-info">{{ ucfirst($s->maintenance_type) }}</span></td>
                            <td>Every {{ $s->frequency_value }} {{ $s->frequency_type }}</td>
                            <td>{{ $s->last_completed_date ? $s->last_completed_date->format('Y-m-d') : '-' }}</td>
                            <td>
                                <span class="{{ $s->isOverdue() ? 'text-danger fw-bold' : ($s->isDue() ? 'text-warning fw-bold' : 'text-dark') }}">
                                    {{ $s->next_due_date ? $s->next_due_date->format('Y-m-d') : '-' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-soft-{{ $s->is_active ? 'success' : 'secondary' }} text-dark">
                                    {{ $s->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('production.maintenance.schedules.edit', $s->id) }}" class="btn btn-outline-secondary btn-xs me-1">
                                    <i class="feather-edit-2"></i>
                                </a>
                                <form action="{{ route('production.maintenance.schedules.destroy', $s->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this schedule?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-xs"><i class="feather-trash-2"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No PM schedules found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div class="mt-3">
            {{ $schedules->links() }}
        </div>
    </div>
@endsection
