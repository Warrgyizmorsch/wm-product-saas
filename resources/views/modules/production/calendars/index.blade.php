@extends('layouts.duralux')

@section('title', 'Production Calendars | SaaS ERP')
@section('page-title', 'Production Calendars')
@section('breadcrumb', 'Calendars')

@section('page-actions')
    <a href="{{ route('production.calendars.create') }}" class="btn btn-primary">
        <i class="feather-plus me-2"></i>Create Calendar
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
            <h5 class="fw-bold text-dark mb-0">Operations Calendars</h5>
            <div class="d-flex gap-2 ms-auto">
                <form method="GET" action="{{ route('production.calendars.index') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                            <x-ui.input name="search" placeholder="Search by calendar name..." value="{{ request('search') }}" />
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('production.calendars.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- Calendars Table -->
        <x-ui.odoo-form-ui type="table">
            <thead>
                <tr>
                    <th style="width: 35%">Calendar Name</th>
                    <th style="width: 45%">Working Days</th>
                    <th style="width: 15%">Default Calendar</th>
                    <th class="text-end" style="width: 5%">Actions</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $dayNames = [
                        0 => 'Sun',
                        1 => 'Mon',
                        2 => 'Tue',
                        3 => 'Wed',
                        4 => 'Thu',
                        5 => 'Fri',
                        6 => 'Sat'
                    ];
                @endphp
                @forelse($calendars as $calendar)
                    <tr>
                        <td class="fw-bold text-dark">
                            <a href="{{ route('production.calendars.edit', $calendar->id) }}" class="text-dark hover-primary">
                                {{ $calendar->name }}
                            </a>
                        </td>
                        <td>
                            @php
                                $wDays = $calendar->working_days ?? [];
                                sort($wDays);
                            @endphp
                            <div class="d-flex flex-wrap gap-1">
                                @foreach($wDays as $d)
                                    <span class="badge bg-soft-secondary text-secondary font-monospace">{{ $dayNames[$d] ?? $d }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td>
                            @if($calendar->is_default)
                                <span class="erp-badge-active"><i class="feather-star me-1 text-warning"></i>Default System Calendar</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <x-ui.action-dropdown>
                                <li>
                                    <a href="{{ route('production.calendars.edit', $calendar->id) }}" class="dropdown-item">
                                        <i class="feather-edit me-2 text-muted fs-12"></i>Edit Calendar
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('production.calendars.destroy', $calendar->id) }}"
                                          onsubmit="return confirm('Are you sure you want to delete this calendar?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="feather-trash-2 me-2 text-danger fs-12"></i>Delete Permanent
                                        </button>
                                    </form>
                                </li>
                            </x-ui.action-dropdown>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center py-4 text-muted">
                            <i class="feather-info me-2 fs-16"></i>No production calendars configured.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.odoo-form-ui>

        <div class="mt-4">
            {{ $calendars->links() }}
        </div>
    </div>
@endsection
