@extends('layouts.duralux')

@section('title', 'Attendance Management | SaaS ERP')
@section('page-title', 'Attendance Management')
@section('breadcrumb', 'HRMS / Attendance')

@section('page-actions')
    <div class="d-flex gap-2 align-items-center">
        
        <x-ui.import-export-dropdown 
            type="attendance" 
            export-route="{{ route('hrms.attendance.export', request()->query()) }}" 
            download-template-route="{{ route('hrms.attendance.import.template') }}" 
            import-modal-target="#attendanceImportModal" 
        />

        @if(($view ?? 'date') === 'date')
            <x-ui.button type="button" onclick="switchView('employee')" variant="light" class="border fw-semibold text-uppercase px-3 py-2" icon="feather-users" style="height: 38px; display: inline-flex; align-items: center; font-size: 11px;">
                Employee View
            </x-ui.button>
        @else
            <x-ui.button type="button" onclick="switchView('date')" variant="light" class="border fw-semibold text-uppercase px-3 py-2" icon="feather-calendar" style="height: 38px; display: inline-flex; align-items: center; font-size: 11px;">
                Date View
            </x-ui.button>
        @endif

        @if(($view ?? 'date') === 'corrections')
            <x-ui.button type="button" onclick="switchView('date')" variant="warning" class="text-white fw-semibold text-uppercase px-3 py-2" icon="feather-edit-3" style="height: 38px; display: inline-flex; align-items: center; font-size: 11px;">
                Correction Requests
            </x-ui.button>
        @else
            <x-ui.button type="button" onclick="switchView('corrections')" variant="light" class="border fw-semibold text-uppercase px-3 py-2" icon="feather-edit-3" style="height: 38px; display: inline-flex; align-items: center; font-size: 11px;">
                Correction Requests
            </x-ui.button>
        @endif

        <x-ui.button href="{{ route('hrms.attendance.create') }}" variant="primary" icon="feather-plus" style="height: 38px; display: inline-flex; align-items: center;">
            Add Attendance
        </x-ui.button>
    </div>
@endsection

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-radius: 8px;">
            <i class="feather-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-radius: 8px;">
            <i class="feather-alert-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm mb-4" role="alert" style="border-radius: 8px;">
            <div class="fw-bold mb-1"><i class="feather-alert-circle me-2"></i>Some rows were skipped during import:</div>
            <ul class="mb-0 ps-3 fs-12">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Attendance List Card -->
    <div>
        <div class="mb-4 pb-3 border-bottom">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 w-100">
                <div>
                    <h5 class="fw-bold text-dark mb-0 fs-16">Daily Attendance Overview</h5>
                    @if(($view ?? 'date') === 'date')
                        <p class="text-muted fs-12 mb-0">Monitor and manage employee logs grouped by date</p>
                    @else
                        <p class="text-muted fs-12 mb-0">Monitor and manage employee logs for the selected date: <strong>{{ \Carbon\Carbon::parse($filters['date'] ?? today()->format('Y-m-d'))->format('M d, Y') }}</strong></p>
                    @endif
                </div>
                
                <!-- Search & Filters (Standard Theme Toolbar) -->
                <form method="GET" action="{{ route('hrms.attendance.index') }}" id="attendanceFilterForm" class="d-flex align-items-center gap-2 flex-wrap">
                    <input type="hidden" name="sort" id="attendanceSortInput" value="{{ $sort ?? 'name_asc' }}">
                    <input type="hidden" name="view" value="{{ $view ?? 'date' }}">

                    <!-- Search Field -->
                    <div class="d-flex align-items-center bg-light border rounded px-3 py-1" style="height: 38px;">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input 
                            type="text" 
                            name="search" 
                            class="form-control border-0 bg-transparent p-0 fs-13" 
                            placeholder="Search employee..." 
                            value="{{ $filters['search'] ?? '' }}"
                            style="box-shadow: none; width: 180px;"
                        >
                    </div>

                    <!-- Sort Dropdown -->
                    <x-ui.sort-dropdown label="Sort">
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ ($sort ?? 'name_asc') === 'name_asc' ? 'active' : '' }}" href="javascript:void(0)" onclick="applySort('name_asc')">
                            <span>Name (A-Z)</span>
                            @if(($sort ?? 'name_asc') === 'name_asc') <i class="feather-check ms-3 text-primary"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ ($sort ?? 'name_asc') === 'name_desc' ? 'active' : '' }}" href="javascript:void(0)" onclick="applySort('name_desc')">
                            <span>Name (Z-A)</span>
                            @if(($sort ?? 'name_asc') === 'name_desc') <i class="feather-check ms-3 text-primary"></i> @endif
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ ($sort ?? 'name_asc') === 'checkin_asc' ? 'active' : '' }}" href="javascript:void(0)" onclick="applySort('checkin_asc')">
                            <span>Check-in (Earliest)</span>
                            @if(($sort ?? 'name_asc') === 'checkin_asc') <i class="feather-check ms-3 text-primary"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ ($sort ?? 'name_asc') === 'checkin_desc' ? 'active' : '' }}" href="javascript:void(0)" onclick="applySort('checkin_desc')">
                            <span>Check-in (Latest)</span>
                            @if(($sort ?? 'name_asc') === 'checkin_desc') <i class="feather-check ms-3 text-primary"></i> @endif
                        </a>
                    </x-ui.sort-dropdown>

                    <!-- Filter Dropdown -->
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Date</label>
                            <x-ui.odoo-form-ui type="input" inputType="date" name="date" value="{{ request('date') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Department</label>
                            <x-ui.odoo-form-ui type="select" name="department_id" select2-selector="default">
                                <option value="">All Departments</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" @selected(($filters['department_id'] ?? '') == $dept->id)>{{ $dept->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.odoo-form-ui type="select" name="status" select2-selector="default">
                                <option value="">All Statuses</option>
                                <option value="present" @selected(($filters['status'] ?? '') === 'present')>Present</option>
                                <option value="wfh" @selected(($filters['status'] ?? '') === 'wfh')>Work from Home</option>
                                <option value="late" @selected(($filters['status'] ?? '') === 'late')>Late</option>
                                <option value="half_day" @selected(($filters['status'] ?? '') === 'half_day')>Half Day</option>
                                <option value="absent" @selected(($filters['status'] ?? '') === 'absent')>Absent</option>
                                <option value="on_leave" @selected(($filters['status'] ?? '') === 'on_leave')>Leave</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('hrms.attendance.index') }}?view={{ $view ?? 'date' }}" class="btn btn-sm btn-light border">{{ __('hrms.common.reset') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary text-white">{{ __('hrms.common.apply') }}</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    @if(($view ?? 'date') === 'corrections')
                        <thead class="bg-light text-uppercase fs-10 tracking-wider">
                            <tr>
                                <th class="ps-4">Employee Details</th>
                                <th>Date</th>
                                <th>Requested Check In</th>
                                <th>Requested Check Out</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th class="pe-4 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($corrections as $correction)
                                <tr>
                                    <!-- Employee Details -->
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-sm rounded-circle bg-light d-flex align-items-center justify-content-center text-dark fw-bold border" style="width: 32px; height: 32px; font-size: 11px;">
                                                @if($correction->employee->photo)
                                                    <img src="{{ asset('storage/' . $correction->employee->photo) }}" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">
                                                @else
                                                    {{ strtoupper(substr($correction->employee->first_name, 0, 1) . substr($correction->employee->last_name, 0, 1)) ?: 'EM' }}
                                                @endif
                                            </div>
                                            <div>
                                                <a href="{{ route('hrms.employees.show', $correction->employee->id) }}" class="fw-bold text-dark fs-13 d-block text-decoration-none hover-primary">
                                                    {{ $correction->employee->display_name }}
                                                </a>
                                                <span class="text-muted fs-11 d-block">{{ $correction->employee->designation ? $correction->employee->designation->name : 'No Designation' }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <!-- Date -->
                                    <td class="fw-bold text-dark fs-12">
                                        {{ $correction->date->format('M d, Y') }}
                                    </td>
                                    <!-- Requested Check-in -->
                                    <td class="fs-12 text-dark fw-semibold">
                                        {{ $correction->requested_check_in ? $correction->requested_check_in->format('h:i A') : '-' }}
                                    </td>
                                    <!-- Requested Check-out -->
                                    <td class="fs-12 text-dark fw-semibold">
                                        {{ $correction->requested_check_out ? $correction->requested_check_out->format('h:i A') : '-' }}
                                    </td>
                                    <!-- Reason -->
                                    <td class="fs-12 text-muted" style="max-width: 250px; white-space: normal; word-break: break-word;">
                                        {{ $correction->reason }}
                                    </td>
                                    <!-- Status -->
                                    <td>
                                        @if($correction->status === 'pending')
                                            <span class="badge bg-soft-warning text-warning px-3 py-1.5 fs-11 rounded-pill fw-bold">Pending</span>
                                        @elseif($correction->status === 'approved')
                                            <span class="badge bg-soft-success text-success px-3 py-1.5 fs-11 rounded-pill fw-bold">Approved</span>
                                        @else
                                            <span class="badge bg-soft-danger text-danger px-3 py-1.5 fs-11 rounded-pill fw-bold" title="Reason: {{ $correction->rejected_reason }}">Rejected</span>
                                        @endif
                                    </td>
                                    <!-- Actions -->
                                    <td class="pe-4 text-end">
                                        @if($correction->status === 'pending')
                                            <div class="d-flex align-items-center justify-content-end gap-1">
                                                <button type="button" 
                                                        class="btn btn-xs btn-soft-success text-uppercase fw-bold" 
                                                        style="font-size: 9px; padding: 4px 8px;"
                                                        data-id="{{ $correction->id }}"
                                                        data-employee="{{ $correction->employee->display_name }}"
                                                        data-date="{{ $correction->date->format('M d, Y') }}"
                                                        data-check-in="{{ $correction->requested_check_in ? $correction->requested_check_in->format('H:i') : '' }}"
                                                        data-check-out="{{ $correction->requested_check_out ? $correction->requested_check_out->format('H:i') : '' }}"
                                                        onclick="openApproveModal(this)">
                                                    Approve
                                                </button>
                                                <button type="button" class="btn btn-xs btn-soft-danger text-uppercase fw-bold" style="font-size: 9px; padding: 4px 8px;" onclick="openRejectModal({{ $correction->id }})">
                                                    Reject
                                                </button>
                                            </div>
                                        @else
                                            <span class="text-muted fs-11">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted fs-13">No correction requests found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    @elseif(($view ?? 'date') === 'date')
                        <thead class="bg-light text-uppercase fs-10 tracking-wider">
                            <tr>
                                <th class="ps-4">Date</th>
                                <th>Attendance Summary</th>
                                <th class="pe-4 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($dates as $dateItem)
                                @php
                                    $dStr = $dateItem->date->format('Y-m-d');
                                    $dayStats = $statsByDate->get($dStr, collect());
                                    $presentCount = $dayStats->where('status', 'present')->sum('count');
                                    $lateCount = $dayStats->where('status', 'late')->sum('count');
                                    $wfhCount = $dayStats->filter(function($item) {
                                        return $item->status === 'wfh' || strtolower($item->location_type) === 'wfh';
                                    })->sum('count');
                                    $halfDayCount = $dayStats->where('status', 'half_day')->sum('count');
                                    $absentCount = $dayStats->where('status', 'absent')->sum('count');
                                    $leaveCount = $dayStats->where('status', 'on_leave')->sum('count');
                                    $overtimeCount = \App\Domains\HRMS\Models\Attendance::where('date', $dStr)
                                        ->whereIn('employee_id', function($q) use ($dStr) {
                                            $q->select('employee_id')
                                              ->from('overtime_requests')
                                              ->where('date', $dStr)
                                              ->where('status', 'approved');
                                        })->count();
                                @endphp
                                <tr>
                                    <td class="ps-4 fw-bold text-dark fs-13">
                                        {{ $dateItem->date->format('M d, Y') }}
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <span class="badge bg-soft-success text-success px-3 py-1.5 fs-11 rounded-pill fw-bold" style="cursor: pointer;" onclick="openDailyLogs('{{ $dStr }}', '{{ $dateItem->date->format('M d, Y') }}', 'present')">
                                                Present: {{ $presentCount }}
                                            </span>
                                            <span class="badge bg-soft-warning text-warning px-3 py-1.5 fs-11 rounded-pill fw-bold" style="cursor: pointer;" onclick="openDailyLogs('{{ $dStr }}', '{{ $dateItem->date->format('M d, Y') }}', 'late')">
                                                Late: {{ $lateCount }}
                                            </span>
                                            <span class="badge px-3 py-1.5 fs-11 rounded-pill fw-bold" style="cursor: pointer; background-color: rgba(111, 66, 193, 0.1); color: #6f42c1 !important;" onclick="openDailyLogs('{{ $dStr }}', '{{ $dateItem->date->format('M d, Y') }}', 'half_day')">
                                                Half Day: {{ $halfDayCount }}
                                            </span>
                                            <span class="badge bg-soft-danger text-danger px-3 py-1.5 fs-11 rounded-pill fw-bold" style="cursor: pointer;" onclick="openDailyLogs('{{ $dStr }}', '{{ $dateItem->date->format('M d, Y') }}', 'absent')">
                                                Absent: {{ $absentCount }}
                                            </span>
                                            <span class="badge bg-soft-secondary text-secondary px-3 py-1.5 fs-11 rounded-pill fw-bold" style="cursor: pointer; background-color: rgba(108, 117, 125, 0.1); color: #6c757d !important;" onclick="openDailyLogs('{{ $dStr }}', '{{ $dateItem->date->format('M d, Y') }}', 'on_leave')">
                                                Leave: {{ $leaveCount }}
                                            </span>
                                            <span class="badge bg-soft-primary text-primary px-3 py-1.5 fs-11 rounded-pill fw-bold" style="cursor: pointer;" onclick="openDailyLogs('{{ $dStr }}', '{{ $dateItem->date->format('M d, Y') }}', 'overtime')">
                                                Overtime: {{ $overtimeCount }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <div class="d-flex align-items-center justify-content-end gap-2">
                                            <!-- View Drawer -->
                                            <x-ui.icon-btn 
                                                variant="soft-primary" 
                                                icon="feather-eye" 
                                                title="View Daily Attendance Logs" 
                                                onclick="openDailyLogs('{{ $dStr }}', '{{ $dateItem->date->format('M d, Y') }}', 'all')" 
                                            />

                                            <!-- Edit Manual Entry -->
                                            <x-ui.icon-btn 
                                                variant="soft-warning" 
                                                icon="feather-edit" 
                                                title="Edit Attendance" 
                                                href="{{ route('hrms.attendance.create', array_merge(['date' => $dStr], request()->query())) }}" 
                                            />

                                            <!-- Delete All logs for the date -->
                                            <form action="{{ route('hrms.attendance.destroy-date', $dStr) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete all attendance records for {{ $dateItem->date->format('M d, Y') }}? This action cannot be undone.');">
                                                @csrf
                                                @method('DELETE')
                                                <x-ui.icon-btn 
                                                    type="submit" 
                                                    variant="soft-danger" 
                                                    icon="feather-trash-2" 
                                                    title="Delete Attendance Logs" 
                                                />
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted fs-13">No dates found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    @else
                        <thead class="bg-light text-uppercase fs-10 tracking-wider">
                            <tr>
                                <th class="ps-4">Employee Details</th>
                                <th>Attendance Summary</th>
                                <th class="pe-4 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($employees as $employee)
                                @php
                                    $log = $employee->attendances->first();
                                    $dStr = $filters['date'] ?? today()->format('Y-m-d');
                                    
                                    // Query counts for this employee across all dates
                                    $allAttendances = \App\Domains\HRMS\Models\Attendance::where('employee_id', $employee->id)->get();
                                    $presentCount = $allAttendances->where('status', 'present')->count();
                                    $lateCount = $allAttendances->where('status', 'late')->count();
                                    $wfhCount = $allAttendances->where(function($item) {
                                        return $item->status === 'wfh' || strtolower($item->location_type) === 'wfh';
                                    })->count();
                                    $halfDayCount = $allAttendances->where('status', 'half_day')->count();
                                    $absentCount = $allAttendances->where('status', 'absent')->count();
                                    $leaveCount = $allAttendances->where('status', 'on_leave')->count();
                                    $overtimeCount = \App\Domains\HRMS\Models\Attendance::where('employee_id', $employee->id)
                                        ->whereIn('date', function($q) use ($employee) {
                                            $q->select('date')
                                              ->from('overtime_requests')
                                              ->where('employee_id', $employee->id)
                                              ->where('status', 'approved');
                                        })->count();
                                @endphp
                                <tr>
                                    <!-- Employee Details -->
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-sm rounded-circle bg-light d-flex align-items-center justify-content-center text-dark fw-bold border" style="width: 32px; height: 32px; font-size: 11px;">
                                                @if($employee->photo)
                                                    <img src="{{ asset('storage/' . $employee->photo) }}" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">
                                                @else
                                                    {{ strtoupper(substr($employee->first_name, 0, 1) . substr($employee->last_name, 0, 1)) ?: 'EM' }}
                                                @endif
                                            </div>
                                            <div>
                                                <a href="{{ route('hrms.employees.show', $employee->id) }}?tab=attendance" class="fw-bold text-dark fs-13 d-block text-decoration-none hover-primary">
                                                    {{ $employee->display_name }}
                                                </a>
                                                <span class="text-muted fs-11 d-block"><code class="fw-bold">{{ $employee->employee_id }}</code> &bull; {{ $employee->department?->name ?? 'No Department' }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Attendance Summary -->
                                    <td>
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <span class="badge bg-soft-success text-success px-3 py-1.5 fs-11 rounded-pill fw-bold" style="cursor: pointer;" onclick="openAttendanceLogs('{{ $employee->id }}', '{{ addslashes($employee->display_name) }}', '{{ $employee->employee_id }}', 'present')">
                                                Present: {{ $presentCount }}
                                            </span>
                                            <span class="badge bg-soft-warning text-warning px-3 py-1.5 fs-11 rounded-pill fw-bold" style="cursor: pointer;" onclick="openAttendanceLogs('{{ $employee->id }}', '{{ addslashes($employee->display_name) }}', '{{ $employee->employee_id }}', 'late')">
                                                Late: {{ $lateCount }}
                                            </span>
                                            <span class="badge px-3 py-1.5 fs-11 rounded-pill fw-bold" style="cursor: pointer; background-color: rgba(111, 66, 193, 0.1); color: #6f42c1 !important;" onclick="openAttendanceLogs('{{ $employee->id }}', '{{ addslashes($employee->display_name) }}', '{{ $employee->employee_id }}', 'half_day')">
                                                Half Day: {{ $halfDayCount }}
                                            </span>
                                            <span class="badge bg-soft-danger text-danger px-3 py-1.5 fs-11 rounded-pill fw-bold" style="cursor: pointer;" onclick="openAttendanceLogs('{{ $employee->id }}', '{{ addslashes($employee->display_name) }}', '{{ $employee->employee_id }}', 'absent')">
                                                Absent: {{ $absentCount }}
                                            </span>
                                            <span class="badge bg-soft-secondary text-secondary px-3 py-1.5 fs-11 rounded-pill fw-bold" style="cursor: pointer; background-color: rgba(108, 117, 125, 0.1); color: #6c757d !important;" onclick="openAttendanceLogs('{{ $employee->id }}', '{{ addslashes($employee->display_name) }}', '{{ $employee->employee_id }}', 'on_leave')">
                                                Leave: {{ $leaveCount }}
                                            </span>
                                            <span class="badge bg-soft-primary text-primary px-3 py-1.5 fs-11 rounded-pill fw-bold" style="cursor: pointer;" onclick="openAttendanceLogs('{{ $employee->id }}', '{{ addslashes($employee->display_name) }}', '{{ $employee->employee_id }}', 'overtime')">
                                                Overtime: {{ $overtimeCount }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Actions -->
                                    <td class="pe-4 text-end">
                                        <div class="d-flex align-items-center justify-content-end gap-1">
                                            <!-- Eye Icon Details (Drawer trigger) -->
                                            <x-ui.icon-btn 
                                                variant="soft-primary" 
                                                icon="feather-eye" 
                                                title="View Attendance History" 
                                                onclick="openAttendanceLogs('{{ $employee->id }}', '{{ addslashes($employee->display_name) }}', '{{ $employee->employee_id }}', 'all')" 
                                            />
                                            <!-- Edit Manual Entry -->
                                            <x-ui.icon-btn 
                                                variant="soft-warning" 
                                                icon="feather-edit-2" 
                                                title="Edit Employee Attendance Logs" 
                                                href="{{ route('hrms.attendance.create', array_merge(['employee_id' => $employee->id], request()->query())) }}" 
                                            />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted fs-13">No employees found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    @endif
                </table>
            </div>

            @if(($view ?? 'date') === 'date' && method_exists($dates, 'currentPage'))
                <div class="p-3">
                    <x-ui.pagination 
                        :current-page="$dates->currentPage()"
                        :total-pages="$dates->lastPage()"
                        :total-results="$dates->total()"
                        :per-page="$dates->perPage()"
                    />
                </div>
            @elseif(($view ?? 'date') === 'employee' && method_exists($employees, 'currentPage'))
                <div class="p-3">
                    <x-ui.pagination 
                        :current-page="$employees->currentPage()"
                        :total-pages="$employees->lastPage()"
                        :total-results="$employees->total()"
                        :per-page="$employees->perPage()"
                    />
                </div>
            @elseif(($view ?? 'date') === 'corrections' && method_exists($corrections, 'currentPage'))
                <div class="p-3">
                    <x-ui.pagination 
                        :current-page="$corrections->currentPage()"
                        :total-pages="$corrections->lastPage()"
                        :total-results="$corrections->total()"
                        :per-page="$corrections->perPage()"
                    />
                </div>
            @endif
        </div>
</div>

<!-- Dynamic Detailed logs Drawer -->
<x-ui.drawer id="attendanceDetailDrawer" title="Attendance History" position="end" style="width: 800px; max-width: 95vw;">
    <!-- Loading Spinner -->
    <div id="drawerLoader" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="text-muted mt-2 fs-12">Fetching logs...</div>
    </div>

    <!-- Drawer Content -->
    <div id="drawerContent" class="d-none">
        <div class="bg-light border rounded-3 p-3 mb-3" id="drawerEmployeeMetaCard">
            <h6 class="fw-bold mb-1 text-dark" id="drawerEmployeeName">Employee Name</h6>
            <span class="text-muted fs-11" id="drawerEmployeeCodeContainer">Employee ID: <code class="fw-bold fs-12" id="drawerEmployeeCode">Code</code></span>
        </div>
        
        <div class="table-responsive" style="overflow-x: hidden;">
            <table class="table table-sm table-hover align-middle mb-0" style="font-size: 11px;">
                <thead class="bg-light text-uppercase fs-9 tracking-wider">
                    <tr id="drawerTableHeaderRow">
                        <th>Date & Location</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Breaks</th>
                        <th>Hours</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="drawerTableBody">
                    <!-- Loaded dynamically via JavaScript -->
                </tbody>
            </table>
        </div>
    </div>

    <x-slot name="footer">
        <button type="button" class="btn btn-light border fw-semibold text-uppercase fs-11" data-bs-dismiss="offcanvas">Close Panel</button>
    </x-slot>
</x-ui.drawer>

<script>
    // Global references for drawer logs and map
    window.currentDrawerLogs = [];
    window.activeRowMap = null;

    // 1. Open Employee Historical Logs (triggered in Employee view)
    function openAttendanceLogs(employeeId, employeeName, employeeCode, filterStatus = 'all') {
        // Trigger Bootstrap Offcanvas
        const drawerEl = document.getElementById('attendanceDetailDrawer');
        const bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(drawerEl);
        
        // Populate header details
        let filterTitleSuffix = "";
        if (filterStatus !== 'all') {
            const formatMap = {
                'present': 'Present',
                'late': 'Late',
                'wfh': 'WFH',
                'absent': 'Absent',
                'on_leave': 'Leave',
                'overtime': 'Overtime',
                'half_day': 'Half Day'
            };
            const statusLabel = formatMap[filterStatus] || filterStatus;
            filterTitleSuffix = ` (${statusLabel})`;
        }
        document.getElementById('attendanceDetailDrawerLabel').textContent = "Employee Attendance History" + filterTitleSuffix;
        document.getElementById('drawerEmployeeMetaCard').classList.remove('d-none');
        document.getElementById('drawerEmployeeName').textContent = employeeName;
        document.getElementById('drawerEmployeeCode').textContent = employeeCode;
        
        // Reset table columns for employee logs view
        document.getElementById('drawerTableHeaderRow').innerHTML = `
            <th>Date & Location</th>
            <th>Check In</th>
            <th>Check Out</th>
            <th>Breaks</th>
            <th>Hours</th>
            <th>Status</th>
            <th class="text-end pe-3">Details</th>
        `;

        // Reset loader/view
        document.getElementById('drawerLoader').classList.remove('d-none');
        document.getElementById('drawerContent').classList.add('d-none');
        document.getElementById('drawerTableBody').innerHTML = '';
        
        // Destroy existing map if any
        if (window.activeRowMap) {
            window.activeRowMap.remove();
            window.activeRowMap = null;
        }
        
        bsOffcanvas.show();
        
        // Fetch logs from controller
        fetch(`/hrms/attendance/employee/${employeeId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                const tbody = document.getElementById('drawerTableBody');
                tbody.innerHTML = '';
                
                let logs = data.logs || [];
                if (filterStatus !== 'all') {
                    logs = logs.filter(log => {
                        if (filterStatus === 'present') {
                            return log.status_raw === 'present';
                        }
                        if (filterStatus === 'late') {
                            return log.status_raw === 'late';
                        }
                        if (filterStatus === 'wfh') {
                            return log.status_raw === 'wfh' || (log.location_type && log.location_type.toLowerCase() === 'wfh');
                        }
                        if (filterStatus === 'absent') {
                            return log.status_raw === 'absent';
                        }
                        if (filterStatus === 'on_leave') {
                            return log.status_raw === 'on_leave';
                        }
                        if (filterStatus === 'half_day') {
                            return log.status_raw === 'half_day';
                        }
                        if (filterStatus === 'overtime') {
                            return log.has_overtime === true;
                        }
                        return true;
                    });
                }
                
                window.currentDrawerLogs = logs;
                
                if (logs.length > 0) {
                    logs.forEach(log => {
                        const tr = document.createElement('tr');
                        
                        let badgeClass = 'bg-soft-dark text-slate';
                        const locLower = log.location_type ? log.location_type.toLowerCase() : '';
                        if (locLower === 'office') {
                            badgeClass = 'bg-soft-primary text-primary';
                        } else if (locLower === 'wfh') {
                            badgeClass = 'bg-soft-success text-success';
                        } else if (locLower === 'on-site' || locLower === 'onsite') {
                            badgeClass = 'bg-soft-warning text-warning';
                        }

                        tr.innerHTML = `
                            <td class="text-nowrap">
                                <span class="fw-semibold text-dark d-block mb-1">${log.date}</span>
                                <span class="badge ${badgeClass} border-0 fs-10 rounded-pill">${log.location_type}</span>
                            </td>
                            <td class="text-muted text-nowrap">${log.check_in}</td>
                            <td class="text-muted text-nowrap">${log.check_out}</td>
                            <td>${log.breaks}</td>
                            <td class="fw-semibold text-dark text-nowrap">${log.work_hours}</td>
                            <td>${log.status}</td>
                            <td class="text-end pe-3">
                                <button type="button" class="btn erp-icon-btn erp-icon-btn--primary btn-sm toggle-details-btn" onclick="toggleAttendanceRowDetails(this, '${log.id}')">
                                    <i class="feather-chevron-down fs-14"></i>
                                </button>
                            </td>
                        `;
                        
                        // Collapsible details row
                        const detailsTr = document.createElement('tr');
                        detailsTr.id = `details-row-${log.id}`;
                        detailsTr.className = 'attendance-details-row d-none bg-light';
                        detailsTr.innerHTML = `
                            <td colspan="7" class="p-0">
                                <div style="padding: 12px; max-width: 100%; overflow-x: hidden;">
                                     <div class="row g-2">
                                         <!-- Check In -->
                                         <div class="col-sm-6 col-12">
                                              <div class="card shadow-sm border rounded-3 p-3 bg-white h-100">
                                                   <div class="d-flex align-items-center gap-2 mb-2">
                                                        <div class="avatar-sm bg-soft-success text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">
                                                            <i class="feather-log-in fs-11"></i>
                                                        </div>
                                                        <span class="fw-bold text-dark fs-12">Check In Details</span>
                                                   </div>
                                                   <div class="mb-2">
                                                        <span class="text-muted fs-10 d-block">TIME & COORDINATES</span>
                                                        <span class="fw-semibold text-dark fs-12">${log.check_in}</span>
                                                        <span class="text-muted fs-10 d-block mt-0.5">${log.check_in_latitude ? 'Lat: ' + parseFloat(log.check_in_latitude).toFixed(6) + ', Lng: ' + parseFloat(log.check_in_longitude).toFixed(6) : 'No coordinates'}</span>
                                                   </div>
                                                   <div>
                                                        <span class="text-muted fs-10 d-block mb-1">SELFIE</span>
                                                        <div class="bg-light border border-dashed rounded p-1.5 d-flex align-items-center justify-content-center" style="height: 80px;">
                                                            ${log.check_in_selfie_url ? `<img src="${log.check_in_selfie_url}" class="rounded border shadow-sm" style="max-height: 70px; max-width: 100%; object-fit: contain;">` : `<span class="text-muted fs-10 text-center"><i class="feather-image d-block mb-0.5 fs-12"></i> None</span>`}
                                                        </div>
                                                   </div>
                                              </div>
                                         </div>
                                         <!-- Check Out -->
                                         <div class="col-sm-6 col-12">
                                              <div class="card shadow-sm border rounded-3 p-3 bg-white h-100">
                                                   <div class="d-flex align-items-center gap-2 mb-2">
                                                        <div class="avatar-sm bg-soft-danger text-danger rounded-circle d-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">
                                                            <i class="feather-log-out fs-11"></i>
                                                        </div>
                                                        <span class="fw-bold text-dark fs-12">Check Out Details</span>
                                                   </div>
                                                   <div class="mb-2">
                                                        <span class="text-muted fs-10 d-block">TIME & COORDINATES</span>
                                                        <span class="fw-semibold text-dark fs-12">${log.check_out}</span>
                                                        <span class="text-muted fs-10 d-block mt-0.5">${log.check_out_latitude ? 'Lat: ' + parseFloat(log.check_out_latitude).toFixed(6) + ', Lng: ' + parseFloat(log.check_out_longitude).toFixed(6) : 'No coordinates'}</span>
                                                   </div>
                                                   <div>
                                                        <span class="text-muted fs-10 d-block mb-1">SELFIE</span>
                                                        <div class="bg-light border border-dashed rounded p-1.5 d-flex align-items-center justify-content-center" style="height: 80px;">
                                                            ${log.check_out_selfie_url ? `<img src="${log.check_out_selfie_url}" class="rounded border shadow-sm" style="max-height: 70px; max-width: 100%; object-fit: contain;">` : `<span class="text-muted fs-10 text-center"><i class="feather-image d-block mb-0.5 fs-12"></i> None</span>`}
                                                        </div>
                                                   </div>
                                              </div>
                                         </div>
                                     </div>
                                     
                                     <!-- Map -->
                                     <div class="mt-2">
                                          <span class="text-muted fs-10 text-uppercase fw-semibold d-block mb-1">Location Map</span>
                                          <div class="position-relative w-100" id="map-wrap-${log.id}" style="display: none; overflow: hidden; border-radius: 8px;">
                                               <div id="map-${log.id}" class="attendance-row-map" style="height: 180px; width: 100%; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; z-index: 1;"></div>
                                          </div>
                                          <div id="map-none-${log.id}" class="alert alert-light border text-center fs-11 py-2.5 mb-0">
                                               <i class="feather-map-pin text-muted fs-14 d-block mb-0.5"></i> No location coordinates captured for check-in or check-out.
                                          </div>
                                     </div>
                                </div>
                            </td>
                        `;
                        
                        tbody.appendChild(tr);
                        tbody.appendChild(detailsTr);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No attendance logs found matching this filter.</td></tr>';
                }
                
                document.getElementById('drawerLoader').classList.add('d-none');
                document.getElementById('drawerContent').classList.remove('d-none');
            })
            .catch(error => {
                console.error('Error loading attendance logs:', error);
                document.getElementById('drawerTableBody').innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Failed to load logs. Please try again.</td></tr>';
                document.getElementById('drawerLoader').classList.add('d-none');
                document.getElementById('drawerContent').classList.remove('d-none');
            });
    }

    // 2. Open Daily Logs (triggered in Date view)
    function openDailyLogs(dateStr, formattedDate, filterStatus = 'all') {
        // Trigger Bootstrap Offcanvas
        const drawerEl = document.getElementById('attendanceDetailDrawer');
        const bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(drawerEl);
        
        // Populate header details
        let filterTitleSuffix = "";
        if (filterStatus !== 'all') {
            const formatMap = {
                'present': 'Present',
                'late': 'Late',
                'wfh': 'WFH',
                'absent': 'Absent',
                'on_leave': 'Leave',
                'overtime': 'Overtime',
                'half_day': 'Half Day'
            };
            const statusLabel = formatMap[filterStatus] || filterStatus;
            filterTitleSuffix = ` (${statusLabel})`;
        }
        document.getElementById('attendanceDetailDrawerLabel').textContent = "Daily Attendance Overview" + filterTitleSuffix;
        document.getElementById('drawerEmployeeMetaCard').classList.add('d-none'); // Hide employee card info since it shows date logs

        // Reset table columns for daily logs view
        document.getElementById('drawerTableHeaderRow').innerHTML = `
            <th>Employee Details</th>
            <th>Check In</th>
            <th>Check Out</th>
            <th>Breaks</th>
            <th>Hours</th>
            <th>Status</th>
            <th class="text-end pe-3">Details</th>
        `;

        // Reset loader/view
        document.getElementById('drawerLoader').classList.remove('d-none');
        document.getElementById('drawerContent').classList.add('d-none');
        document.getElementById('drawerTableBody').innerHTML = '';
        
        // Destroy existing map if any
        if (window.activeRowMap) {
            window.activeRowMap.remove();
            window.activeRowMap = null;
        }
        
        bsOffcanvas.show();
        
        // Fetch logs for this date
        fetch(`/hrms/attendance/date/${dateStr}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                const tbody = document.getElementById('drawerTableBody');
                tbody.innerHTML = '';
                
                let logs = data.logs || [];
                if (filterStatus !== 'all') {
                    logs = logs.filter(log => {
                        if (filterStatus === 'present') {
                            return log.status_raw === 'present';
                        }
                        if (filterStatus === 'late') {
                            return log.status_raw === 'late';
                        }
                        if (filterStatus === 'wfh') {
                            return log.status_raw === 'wfh' || (log.location_type && log.location_type.toLowerCase() === 'wfh');
                        }
                        if (filterStatus === 'absent') {
                            return log.status_raw === 'absent';
                        }
                        if (filterStatus === 'on_leave') {
                            return log.status_raw === 'on_leave';
                        }
                        if (filterStatus === 'half_day') {
                            return log.status_raw === 'half_day';
                        }
                        if (filterStatus === 'overtime') {
                            return log.has_overtime === true;
                        }
                        return true;
                    });
                }
                
                window.currentDrawerLogs = logs;
                
                if (logs.length > 0) {
                    logs.forEach(log => {
                        const tr = document.createElement('tr');
                        
                        let locationBadge = '';
                        const locLower = log.location_type ? log.location_type.toLowerCase() : '';
                        if (locLower === 'wfh') {
                            locationBadge = '<span class="badge bg-soft-success text-success border-0" style="font-size: 9px; padding: 2px 6px; border-radius: 4px; margin-top: 4px; display: inline-block;">WFH</span>';
                        } else if (locLower === 'on-site' || locLower === 'onsite') {
                            locationBadge = '<span class="badge bg-soft-warning text-warning border-0" style="font-size: 9px; padding: 2px 6px; border-radius: 4px; margin-top: 4px; display: inline-block;">On-Site</span>';
                        } else if (locLower && locLower !== 'office' && locLower !== '-') {
                            locationBadge = `<span class="badge bg-soft-dark text-slate border-0" style="font-size: 9px; padding: 2px 6px; border-radius: 4px; margin-top: 4px; display: inline-block;">${log.location_type}</span>`;
                        }

                        tr.innerHTML = `
                            <td>
                                <span class="fw-bold text-dark d-block fs-13 mb-0">${log.employee_name}</span>
                                ${locationBadge}
                            </td>
                            <td class="text-muted text-nowrap">${log.check_in}</td>
                            <td class="text-muted text-nowrap">${log.check_out}</td>
                            <td>${log.breaks}</td>
                            <td class="fw-semibold text-dark text-nowrap">${log.work_hours}</td>
                            <td>
                                <div>${log.status}</div>
                            </td>
                            <td class="text-end pe-3">
                                <button type="button" class="btn erp-icon-btn erp-icon-btn--primary btn-sm toggle-details-btn" onclick="toggleAttendanceRowDetails(this, '${log.id}')">
                                    <i class="feather-chevron-down fs-14"></i>
                                </button>
                            </td>
                        `;
                        
                        // Collapsible details row
                        const detailsTr = document.createElement('tr');
                        detailsTr.id = `details-row-${log.id}`;
                        detailsTr.className = 'attendance-details-row d-none bg-light';
                        detailsTr.innerHTML = `
                            <td colspan="7" class="p-0">
                                <div style="padding: 12px; max-width: 100%; overflow-x: hidden;">
                                     <div class="row g-2">
                                         <!-- Check In -->
                                         <div class="col-sm-6 col-12">
                                              <div class="card shadow-sm border rounded-3 p-3 bg-white h-100">
                                                   <div class="d-flex align-items-center gap-2 mb-2">
                                                        <div class="avatar-sm bg-soft-success text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">
                                                            <i class="feather-log-in fs-11"></i>
                                                        </div>
                                                        <span class="fw-bold text-dark fs-12">Check In Details</span>
                                                   </div>
                                                   <div class="mb-2">
                                                        <span class="text-muted fs-10 d-block">TIME & COORDINATES</span>
                                                        <span class="fw-semibold text-dark fs-12">${log.check_in}</span>
                                                        <span class="text-muted fs-10 d-block mt-0.5">${log.check_in_latitude ? 'Lat: ' + parseFloat(log.check_in_latitude).toFixed(6) + ', Lng: ' + parseFloat(log.check_in_longitude).toFixed(6) : 'No coordinates'}</span>
                                                   </div>
                                                   <div>
                                                        <span class="text-muted fs-10 d-block mb-1">SELFIE</span>
                                                        <div class="bg-light border border-dashed rounded p-1.5 d-flex align-items-center justify-content-center" style="height: 80px;">
                                                            ${log.check_in_selfie_url ? `<img src="${log.check_in_selfie_url}" class="rounded border shadow-sm" style="max-height: 70px; max-width: 100%; object-fit: contain;">` : `<span class="text-muted fs-10 text-center"><i class="feather-image d-block mb-0.5 fs-12"></i> None</span>`}
                                                        </div>
                                                   </div>
                                              </div>
                                         </div>
                                         <!-- Check Out -->
                                         <div class="col-sm-6 col-12">
                                              <div class="card shadow-sm border rounded-3 p-3 bg-white h-100">
                                                   <div class="d-flex align-items-center gap-2 mb-2">
                                                        <div class="avatar-sm bg-soft-danger text-danger rounded-circle d-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">
                                                            <i class="feather-log-out fs-11"></i>
                                                        </div>
                                                        <span class="fw-bold text-dark fs-12">Check Out Details</span>
                                                   </div>
                                                   <div class="mb-2">
                                                        <span class="text-muted fs-10 d-block">TIME & COORDINATES</span>
                                                        <span class="fw-semibold text-dark fs-12">${log.check_out}</span>
                                                        <span class="text-muted fs-10 d-block mt-0.5">${log.check_out_latitude ? 'Lat: ' + parseFloat(log.check_out_latitude).toFixed(6) + ', Lng: ' + parseFloat(log.check_out_longitude).toFixed(6) : 'No coordinates'}</span>
                                                   </div>
                                                   <div>
                                                        <span class="text-muted fs-10 d-block mb-1">SELFIE</span>
                                                        <div class="bg-light border border-dashed rounded p-1.5 d-flex align-items-center justify-content-center" style="height: 80px;">
                                                            ${log.check_out_selfie_url ? `<img src="${log.check_out_selfie_url}" class="rounded border shadow-sm" style="max-height: 70px; max-width: 100%; object-fit: contain;">` : `<span class="text-muted fs-10 text-center"><i class="feather-image d-block mb-0.5 fs-12"></i> None</span>`}
                                                        </div>
                                                   </div>
                                              </div>
                                         </div>
                                     </div>
                                     
                                     <!-- Map -->
                                     <div class="mt-2">
                                          <span class="text-muted fs-10 text-uppercase fw-semibold d-block mb-1">Location Map</span>
                                          <div class="position-relative w-100" id="map-wrap-${log.id}" style="display: none; overflow: hidden; border-radius: 8px;">
                                               <div id="map-${log.id}" class="attendance-row-map" style="height: 180px; width: 100%; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; z-index: 1;"></div>
                                          </div>
                                          <div id="map-none-${log.id}" class="alert alert-light border text-center fs-11 py-2.5 mb-0">
                                               <i class="feather-map-pin text-muted fs-14 d-block mb-0.5"></i> No location coordinates captured for check-in or check-out.
                                          </div>
                                     </div>
                                </div>
                            </td>
                        `;
                        
                        tbody.appendChild(tr);
                        tbody.appendChild(detailsTr);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No attendance logs found matching this filter.</td></tr>';
                }
                
                document.getElementById('drawerLoader').classList.add('d-none');
                document.getElementById('drawerContent').classList.remove('d-none');
            })
            .catch(error => {
                console.error('Error loading attendance logs:', error);
                document.getElementById('drawerTableBody').innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Failed to load logs. Please try again.</td></tr>';
                document.getElementById('drawerLoader').classList.add('d-none');
                document.getElementById('drawerContent').classList.remove('d-none');
            });
    }

    // Toggle details row collapse/expand
    function toggleAttendanceRowDetails(btn, logId) {
        const detailsRow = document.getElementById(`details-row-${logId}`);
        if (!detailsRow) return;
        
        const isHidden = detailsRow.classList.contains('d-none');
        
        // Close all other detail rows
        document.querySelectorAll('.attendance-details-row').forEach(row => {
            row.classList.add('d-none');
        });
        
        // Reset all chevron icons
        document.querySelectorAll('.toggle-details-btn i').forEach(icon => {
            icon.className = 'feather-chevron-down fs-14';
        });
        
        // Destroy existing map
        if (window.activeRowMap) {
            window.activeRowMap.remove();
            window.activeRowMap = null;
        }
        
        if (isHidden) {
            // Show this details row
            detailsRow.classList.remove('d-none');
            btn.querySelector('i').className = 'feather-chevron-up fs-14';
            
            // Find current data in window.currentDrawerLogs
            if (window.currentDrawerLogs) {
                const log = window.currentDrawerLogs.find(l => String(l.id) === String(logId));
                if (log) {
                    initializeRowMap(logId, log);
                }
            }
        }
    }

    // Initialize Leaflet Map for a specific details row
    function initializeRowMap(logId, log) {
        const checkinLat = parseFloat(log.check_in_latitude);
        const checkinLng = parseFloat(log.check_in_longitude);
        const checkoutLat = parseFloat(log.check_out_latitude);
        const checkoutLng = parseFloat(log.check_out_longitude);
        const locationLogs = log.location_logs || [];
        
        const hasCheckin = checkinLat && checkinLng && checkinLat !== 0 && checkinLng !== 0;
        const hasCheckout = checkoutLat && checkoutLng && checkoutLat !== 0 && checkoutLng !== 0;
        const hasLogs = locationLogs.length > 0;
        
        const mapWrap = document.getElementById(`map-wrap-${logId}`);
        const mapNone = document.getElementById(`map-none-${logId}`);
        
        if (hasCheckin || hasCheckout || hasLogs) {
            if (mapWrap) mapWrap.style.display = 'block';
            if (mapNone) mapNone.style.display = 'none';
            
            setTimeout(() => {
                const mapEl = document.getElementById(`map-${logId}`);
                if (!mapEl) return;
                
                // Base map set to center on check-in or India by default
                const defaultCenter = hasCheckin ? [checkinLat, checkinLng] : [20.5937, 78.9629];
                const map = L.map(`map-${logId}`).setView(defaultCenter, 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap'
                }).addTo(map);
                
                const markersGroup = L.featureGroup().addTo(map);
                const pathLatLngs = [];
                
                // 1. Add Check-In Marker (Green)
                if (hasCheckin) {
                    const checkinLatLng = [checkinLat, checkinLng];
                    pathLatLngs.push(checkinLatLng);
                    const checkinIcon = L.icon({
                        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    });
                    L.marker(checkinLatLng, { icon: checkinIcon })
                        .addTo(markersGroup)
                        .bindPopup(`<b>Check In Point</b><br>Lat: ${checkinLat.toFixed(6)}<br>Lng: ${checkinLng.toFixed(6)}`);
                }
                
                // 2. Add intermediate tracking location logs (Blue circles)
                if (hasLogs) {
                    locationLogs.forEach(locLog => {
                        if (locLog.lat && locLog.lng) {
                            const logLatLng = [parseFloat(locLog.lat), parseFloat(locLog.lng)];
                            pathLatLngs.push(logLatLng);
                            L.circleMarker(logLatLng, {
                                radius: 5,
                                fillColor: '#3b82f6',
                                color: '#ffffff',
                                weight: 1.5,
                                opacity: 1,
                                fillOpacity: 0.8
                            }).addTo(markersGroup)
                              .bindPopup(`<b>Tracking Log</b><br>Time: ${locLog.time}<br>Lat: ${logLatLng[0].toFixed(6)}<br>Lng: ${logLatLng[1].toFixed(6)}`);
                        }
                    });
                }
                
                // 3. Add Check-Out Marker (Red)
                if (hasCheckout) {
                    const checkoutLatLng = [checkoutLat, checkoutLng];
                    pathLatLngs.push(checkoutLatLng);
                    const checkoutIcon = L.icon({
                        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    });
                    L.marker(checkoutLatLng, { icon: checkoutIcon })
                        .addTo(markersGroup)
                        .bindPopup(`<b>Check Out Point</b><br>Lat: ${checkoutLat.toFixed(6)}<br>Lng: ${checkoutLng.toFixed(6)}`);
                }
                
                // 4. Indigo Path Connecting Line
                if (pathLatLngs.length >= 2) {
                    L.polyline(pathLatLngs, {
                        color: '#4f46e5',
                        weight: 3,
                        opacity: 0.8,
                        dashArray: '6, 6',
                        lineJoin: 'round'
                    }).addTo(markersGroup);
                }
                
                window.activeRowMap = map;
                
                map.invalidateSize();
                if (pathLatLngs.length > 0) {
                    if (pathLatLngs.length >= 2) {
                        map.fitBounds(markersGroup.getBounds(), { padding: [20, 20] });
                    } else {
                        map.setView(pathLatLngs[0], 14);
                    }
                }
            }, 150);
        } else {
            if (mapWrap) mapWrap.style.display = 'none';
            if (mapNone) mapNone.style.display = 'block';
        }
    }

    function submitCleanForm(form) {
        if (!form) return;
        const inputs = form.querySelectorAll('input, select');
        inputs.forEach(input => {
            if (input.name && (input.value === null || input.value === undefined || input.value.trim() === '')) {
                input.disabled = true;
            }
            if (input.name === 'sort' && input.value === 'name_asc') {
                input.disabled = true;
            }
        });
        form.submit();
    }

    function switchView(viewName) {
        const form = document.getElementById('attendanceFilterForm');
        if (form) {
            let viewInput = form.querySelector('input[name="view"]');
            if (!viewInput) {
                viewInput = document.createElement('input');
                viewInput.type = 'hidden';
                viewInput.name = 'view';
                form.appendChild(viewInput);
            }
            viewInput.value = viewName;
            
            // Clear any active pagination page since pages differ between views
            let pageInput = form.querySelector('input[name="page"]');
            if (pageInput) {
                pageInput.value = '1';
            }
            
            submitCleanForm(form);
        } else {
            window.location.href = "{{ route('hrms.attendance.index') }}?view=" + viewName;
        }
    }

    function applySort(val) {
        const form = document.getElementById('attendanceFilterForm');
        document.getElementById('attendanceSortInput').value = val;
        submitCleanForm(form);
    }

    function openRejectModal(correctionId) {
        const form = document.getElementById('rejectCorrectionForm');
        form.action = `/hrms/attendance/corrections/${correctionId}/reject`;
        document.getElementById('reject_reason_input').value = '';
        const modal = new bootstrap.Modal(document.getElementById('rejectCorrectionModal'));
        modal.show();
    }

    function openApproveModal(btn) {
        const id = btn.getAttribute('data-id');
        const employee = btn.getAttribute('data-employee');
        const date = btn.getAttribute('data-date');
        const checkIn = btn.getAttribute('data-check-in');
        const checkOut = btn.getAttribute('data-check-out');

        const form = document.getElementById('approveCorrectionForm');
        form.action = `/hrms/attendance/corrections/${id}/approve`;

        document.getElementById('approve_employee_name').textContent = employee;
        document.getElementById('approve_date').textContent = date;
        document.getElementById('approve_check_in_input').value = checkIn;
        document.getElementById('approve_check_out_input').value = checkOut;

        const modal = new bootstrap.Modal(document.getElementById('approveCorrectionModal'));
        modal.show();
    }

    // Live Search with Debounce and Focus cursor position restore
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('attendanceFilterForm');
        const searchInput = document.querySelector('input[name="search"]');
        
        // Clean empty parameters on direct form submit (e.g. clicking Apply button)
        if (form) {
            form.addEventListener('submit', function() {
                const inputs = form.querySelectorAll('input, select');
                inputs.forEach(input => {
                    if (input.name && (input.value === null || input.value === undefined || input.value.trim() === '')) {
                        input.disabled = true;
                    }
                    if (input.name === 'sort' && input.value === 'name_asc') {
                        input.disabled = true;
                    }
                });
            });
        }

        if (searchInput && form) {
            // Autofocus and place cursor at the end if there is a value
            if (searchInput.value.length > 0) {
                searchInput.focus();
                const val = searchInput.value;
                searchInput.value = '';
                searchInput.value = val;
            }

            let timeout = null;
            searchInput.addEventListener('input', function() {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    submitCleanForm(form);
                }, 500); // 500ms debounce
            });
        }

        const drawerEl = document.getElementById('attendanceDetailDrawer');
        if (drawerEl) {
            drawerEl.addEventListener('hide.bs.offcanvas', function () {
                if (window.activeRowMap) {
                    window.activeRowMap.remove();
                    window.activeRowMap = null;
                }
            });
        }

        const rejectModal = document.getElementById('rejectCorrectionModal');
        if (rejectModal) {
            document.body.appendChild(rejectModal);
        }

        const approveModal = document.getElementById('approveCorrectionModal');
        if (approveModal) {
            document.body.appendChild(approveModal);
        }
    });
</script>

<!-- Import Attendance Modal -->
<div class="modal fade" id="attendanceImportModal" tabindex="-1" aria-labelledby="attendanceImportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pt-4 px-4 pb-2">
                <h5 class="modal-title fw-bold text-dark fs-15" id="attendanceImportModalLabel">
                    <i class="feather-upload text-primary me-2"></i>Import Attendance Logs
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.attendance.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body px-4 py-3">
                    <p class="text-muted fs-12 mb-3">Upload your attendance CSV file matching our standard template. All check-in/out times will be processed and statuses auto-detected if set to "auto".</p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-11 text-uppercase mb-1">Select CSV File</label>
                        <input type="file" name="file" class="form-control fs-12" accept=".csv,.txt" required>
                    </div>

                    <div class="bg-light border rounded-3 p-3">
                        <h6 class="fw-bold text-dark fs-12 mb-1"><i class="feather-info text-primary me-1"></i>Import Notes:</h6>
                        <ul class="text-muted fs-11 ps-3 mb-0" style="line-height: 1.5;">
                            <li>Columns required: <code>employee_code</code>, <code>date</code></li>
                            <li>Columns optional: <code>check_in</code>, <code>check_out</code>, <code>status</code></li>
                            <li>Status values: <code>auto</code>, <code>present</code>, <code>absent</code>, <code>half_day</code>, <code>on_leave</code>, <code>wfh</code></li>
                            <li>Set status to <code>auto</code> to auto-calculate penalties, grace shifts, leaves, and WFH logs automatically.</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4 px-4 pt-2 d-flex justify-content-between">
                    <a href="{{ route('hrms.attendance.import.template') }}" class="btn btn-sm btn-link text-decoration-none fw-semibold fs-11 p-0">
                        <i class="feather-download-cloud me-1"></i>Download Sample Template
                    </a>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-primary text-white px-3">Import CSV</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Rejection Reason Modal -->
<div class="modal fade" id="rejectCorrectionModal" tabindex="-1" aria-labelledby="rejectCorrectionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 text-dark">
            <div class="modal-header border-0 pt-4 px-4 pb-2">
                <h5 class="modal-title fw-bold text-dark fs-15" id="rejectCorrectionModalLabel">
                    <i class="feather-alert-triangle text-danger me-2"></i>Reject Correction Request
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="rejectCorrectionForm" method="POST" action="">
                @csrf
                <div class="modal-body px-4 py-3">
                    <p class="text-muted fs-12 mb-3">Please provide a reason for rejecting this attendance correction request. This will be visible to the employee.</p>
                    
                    <x-ui.odoo-form-ui type="textarea" label="Rejection Reason" name="rejected_reason" id="reject_reason_input" placeholder="Type reason here..." :required="true" />
                </div>
                <div class="modal-footer border-0 pb-4 px-4 pt-2">
                    <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger text-white px-3">Reject Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Approval Time Edit Modal -->
<div class="modal fade" id="approveCorrectionModal" tabindex="-1" aria-labelledby="approveCorrectionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 text-dark">
            <div class="modal-header border-0 pt-4 px-4 pb-2">
                <h5 class="modal-title fw-bold text-dark fs-15" id="approveCorrectionModalLabel">
                    <i class="feather-check-circle text-success me-2"></i>Approve Correction Request
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="approveCorrectionForm" method="POST" action="">
                @csrf
                <div class="modal-body px-4 py-3 d-flex flex-column gap-3">
                    <p class="text-muted fs-12 mb-0">Review or adjust the check-in and check-out times before approving this request.</p>
                    
                    <div class="bg-light border rounded-3 p-3 mb-1">
                        <div class="row g-2">
                            <div class="col-6">
                                <span class="text-muted fs-10 d-block text-uppercase">Employee</span>
                                <span class="fw-bold text-dark fs-12" id="approve_employee_name">-</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted fs-10 d-block text-uppercase">Date</span>
                                <span class="fw-bold text-dark fs-12" id="approve_date">-</span>
                            </div>
                        </div>
                    </div>

                    <x-ui.odoo-form-ui type="input" inputType="time" label="Approved Check-In Time" name="approved_check_in" id="approve_check_in_input" :required="true" />
                    <x-ui.odoo-form-ui type="input" inputType="time" label="Approved Check-Out Time" name="approved_check_out" id="approve_check_out_input" :required="true" />
                </div>
                <div class="modal-footer border-0 pb-4 px-4 pt-2">
                    <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success text-white px-3">Approve & Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
