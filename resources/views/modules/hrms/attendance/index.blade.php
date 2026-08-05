@extends('layouts.duralux')

@section('title', 'Attendance Management | SaaS ERP')
@section('page-title', 'Attendance Management')
@section('breadcrumb', 'HRMS / Attendance')

@section('content')
<div class="container-fluid px-4 py-4">

    <!-- Attendance List Card -->
    <div class="card border-0 shadow-sm rounded-4">
        <!-- Card Header with Filters (Common UI Toolbar Style) -->
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-3">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 w-100">
                <div>
                    <h5 class="fw-bold text-dark mb-0 fs-16">Daily Attendance Overview</h5>
                    <p class="text-muted fs-12 mb-0">Monitor and manage employee logs for the selected date</p>
                </div>
                
                <!-- Search & Filters (Standard Theme Toolbar) -->
                <form method="GET" action="{{ route('hrms.attendance.index') }}" id="attendanceFilterForm" class="d-flex align-items-center gap-2 flex-wrap">
                    <input type="hidden" name="sort" id="attendanceSortInput" value="{{ $sort ?? 'name_asc' }}">

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
                            <x-ui.odoo-form-ui type="input" inputType="date" name="date" value="{{ $filters['date'] ?? \Carbon\Carbon::today()->format('Y-m-d') }}" />
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

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('hrms.attendance.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-uppercase fs-10 tracking-wider">
                        <tr>
                            <th class="ps-4">Employee Details</th>
                            <th>Department</th>
                            <th class="pe-4 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employees as $employee)
                            @php
                                $log = $employee->attendances->first();
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
                                            <span class="text-muted fs-11 d-block"><code class="fw-bold">{{ $employee->employee_id }}</code></span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Department -->
                                <td>
                                    <span class="fs-12 text-dark">{{ $employee->department?->name ?? '-' }}</span>
                                </td>

                                <!-- Actions -->
                                <td class="pe-4 text-end">
                                    <div class="d-flex align-items-center justify-content-end gap-1">
                                        <!-- Eye Icon Details (Drawer trigger) -->
                                        <button 
                                            type="button" 
                                            class="btn btn-icon-tile btn-xs py-1 px-2 border" 
                                            style="height: 28px; width: 28px; border-radius: 6px; background-color: #fff;" 
                                            title="View Attendance History"
                                            onclick="openAttendanceLogs('{{ $employee->id }}', '{{ addslashes($employee->display_name) }}', '{{ $employee->employee_id }}')"
                                        >
                                            <i class="feather-eye text-primary fs-14"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted fs-13">No employees found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($employees->hasPages())
                <div class="d-flex justify-content-end p-3 border-top">
                    {{ $employees->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Dynamic Detailed logs Drawer -->
<x-ui.drawer id="attendanceDetailDrawer" title="Employee Attendance History" position="end" style="width: 650px; max-width: 95vw;">
    <!-- Loading Spinner -->
    <div id="drawerLoader" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="text-muted mt-2 fs-12">Fetching attendance history...</div>
    </div>

    <!-- Drawer Content -->
    <div id="drawerContent" class="d-none">
        <div class="bg-light border rounded-3 p-3 mb-3">
            <h6 class="fw-bold mb-1 text-dark" id="drawerEmployeeName">Employee Name</h6>
            <span class="text-muted fs-11">Employee ID: <code class="fw-bold fs-12" id="drawerEmployeeCode">Code</code></span>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover align-middle fs-12 mb-0">
                <thead class="bg-light text-uppercase fs-9 tracking-wider">
                    <tr>
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
    function openAttendanceLogs(employeeId, employeeName, employeeCode) {
        // Trigger Bootstrap Offcanvas
        const drawerEl = document.getElementById('attendanceDetailDrawer');
        const bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(drawerEl);
        
        // Populate header details
        document.getElementById('drawerEmployeeName').textContent = employeeName;
        document.getElementById('drawerEmployeeCode').textContent = employeeCode;
        
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
                
                if (data.logs && data.logs.length > 0) {
                    data.logs.forEach(log => {
                        const tr = document.createElement('tr');
                        
                        let badgeClass = 'bg-soft-dark text-slate';
                        const locLower = log.location_type.toLowerCase();
                        if (locLower === 'office') {
                            badgeClass = 'bg-soft-primary text-primary';
                        } else if (locLower === 'wfh') {
                            badgeClass = 'bg-soft-success text-success';
                        } else if (locLower === 'on-site') {
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
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No attendance logs found.</td></tr>';
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

    function applySort(val) {
        document.getElementById('attendanceSortInput').value = val;
        document.getElementById('attendanceFilterForm').submit();
    }

    // Live Search with Debounce and Focus cursor position restore
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
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
                    document.getElementById('attendanceFilterForm').submit();
                }, 500); // 500ms debounce
            });
        }
    });
</script>
@endsection
