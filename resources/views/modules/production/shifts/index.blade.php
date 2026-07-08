@extends('layouts.duralux')

@section('title', 'Production Shifts | SaaS ERP')
@section('page-title', 'Production Shifts')
@section('breadcrumb', 'Shifts')

@section('page-actions')
    <a href="{{ route('production.shifts.create') }}" class="btn btn-primary">
        <i class="feather-plus me-2"></i>Configure New Shift
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
        <!-- Toolbar: Search, Filters -->
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">Registered Operations Shifts</h5>
            <div class="d-flex gap-2 ms-auto">
                <form method="GET" action="{{ route('production.shifts.index') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                            <x-ui.input name="search" placeholder="Search by name or code..." value="{{ request('search') }}" />
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('production.shifts.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- Shifts Table -->
        <x-ui.odoo-form-ui type="table">
            <thead>
                <tr>
                    <th style="width: 15%">Shift Code</th>
                    <th style="width: 25%">Shift Name</th>
                    <th style="width: 15%">Start Time</th>
                    <th style="width: 15%">End Time</th>
                    <th style="width: 15%">Break (Minutes)</th>
                    <th style="width: 10%">Status</th>
                    <th class="text-end" style="width: 5%">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($shifts as $shift)
                    <tr>
                        <td class="font-monospace fw-bold text-dark">
                            <a href="{{ route('production.shifts.edit', $shift->id) }}" class="text-dark hover-primary">
                                {{ $shift->code }}
                            </a>
                        </td>
                        <td class="fw-semibold text-dark">{{ $shift->name }}</td>
                        <td class="font-monospace">{{ substr($shift->start_time, 0, 5) }}</td>
                        <td class="font-monospace">{{ substr($shift->end_time, 0, 5) }}</td>
                        <td class="text-muted">{{ $shift->break_minutes }} min</td>
                        <td>
                            @if($shift->active)
                                <span class="erp-badge-active">Active</span>
                            @else
                                <span class="erp-badge-draft">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-1">
                                <a href="{{ route('production.shifts.edit', $shift->id) }}" class="btn btn-xs btn-outline-primary py-1"><i class="feather-edit-3 me-1"></i>Edit</a>
                                <form method="POST" action="{{ route('production.shifts.destroy', $shift->id) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this shift?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-outline-danger py-1"><i class="feather-trash-2"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="feather-info me-2 fs-16"></i>No production shifts configured.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.odoo-form-ui>

        <div class="mt-4">
            {{ $shifts->links() }}
        </div>
    </div>
@endsection
