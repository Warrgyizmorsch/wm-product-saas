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

        <x-ui.button href="{{ route('hrms.attendance.create') }}" variant="primary" icon="feather-plus" style="height: 38px; display: inline-flex; align-items: center;">
            Add Attendance
        </x-ui.button>
    </div>
@endsection

@section('content')
<div class="container-fluid px-4 py-4">

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
    <div class="card border-0 shadow-sm rounded-4">
        <!-- Card Header with Filters (Common UI Toolbar Style) -->
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-3">
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

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    @if(($view ?? 'date') === 'date')
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
                                                href="{{ route('hrms.attendance.create', ['date' => $dStr]) }}" 
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
                                                href="{{ route('hrms.attendance.create') }}?employee_id={{ $employee->id }}" 
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
            @endif
        </div>
    </div>
</div>

<!-- Dynamic Detailed logs Drawer -->
<x-ui.drawer id="attendanceDetailDrawer" title="Attendance History" position="end" style="width: 650px; max-width: 95vw;">
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
        
        <div class="table-responsive">
            <table class="table table-hover align-middle fs-12 mb-0">
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
        `;

        // Reset loader/view
        document.getElementById('drawerLoader').classList.remove('d-none');
        document.getElementById('drawerContent').classList.add('d-none');
        document.getElementById('drawerTableBody').innerHTML = '';
        
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
                        `;
                        tbody.appendChild(tr);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No attendance logs found matching this filter.</td></tr>';
                }
                
                document.getElementById('drawerLoader').classList.add('d-none');
                document.getElementById('drawerContent').classList.remove('d-none');
            })
            .catch(error => {
                console.error('Error loading attendance logs:', error);
                document.getElementById('drawerTableBody').innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">Failed to load logs. Please try again.</td></tr>';
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
        `;

        // Reset loader/view
        document.getElementById('drawerLoader').classList.remove('d-none');
        document.getElementById('drawerContent').classList.add('d-none');
        document.getElementById('drawerTableBody').innerHTML = '';
        
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
                        `;
                        tbody.appendChild(tr);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No attendance logs found matching this filter.</td></tr>';
                }
                
                document.getElementById('drawerLoader').classList.add('d-none');
                document.getElementById('drawerContent').classList.remove('d-none');
            })
            .catch(error => {
                console.error('Error loading attendance logs:', error);
                document.getElementById('drawerTableBody').innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">Failed to load logs. Please try again.</td></tr>';
                document.getElementById('drawerLoader').classList.add('d-none');
                document.getElementById('drawerContent').classList.remove('d-none');
            });
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
@endsection
