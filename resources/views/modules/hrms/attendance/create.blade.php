@extends('layouts.duralux')

@section('title', 'Manual Attendance Entry | SaaS ERP')
@section('page-title', 'Manual Attendance Entry')
@section('breadcrumb', isset($employee) ? 'HRMS / Attendance / Employee Entry' : 'HRMS / Attendance / Manual Entry')

@section('page-actions')
    <div class="d-flex gap-2">
        <x-ui.button href="{{ route('hrms.attendance.index', isset($employee) ? array_merge(['view' => 'employee'], request()->except(['employee_id', 'date', 'view'])) : request()->except(['employee_id', 'date', 'view'])) }}" variant="light" icon="feather-arrow-left">
            Back to Attendance
        </x-ui.button>
    </div>
@endsection

@section('content')
<div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <div class="mb-4 pb-3 border-bottom">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 w-100">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h5 class="fw-bold text-dark mb-0 fs-16"><i class="feather-list text-primary me-2"></i>Attendance Logs</h5>
                        @if(isset($employee))
                            <span class="badge bg-soft-primary text-primary px-2.5 py-1 rounded-pill fs-11 fw-bold" id="visible_count_badge">
                                {{ count($dates) }} Days Listed
                            </span>
                        @else
                            <span class="badge bg-soft-primary text-primary px-2.5 py-1 rounded-pill fs-11 fw-bold" id="visible_count_badge">
                                {{ $employees->count() }} Employees Listed
                            </span>
                        @endif
                    </div>
                    @if(isset($employee))
                        <p class="text-muted fs-12 mb-0">Record logs for employee: <strong>{{ $employee->display_name }} ({{ $employee->employee_id }})</strong></p>
                    @else
                        <p class="text-muted fs-12 mb-0">Record logs for the date: <strong>{{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</strong></p>
                    @endif
                </div>

                @if(!isset($employee))
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <!-- Date Picker -->
                        <div class="d-flex align-items-center bg-light border rounded px-3 py-1" style="height: 38px;">
                            <i class="feather-calendar text-muted me-2" style="font-size: 14px;"></i>
                            <input 
                                type="date" 
                                id="attendance_date" 
                                class="form-control border-0 bg-transparent p-0 fs-13 fw-semibold text-dark" 
                                value="{{ $date }}"
                                style="box-shadow: none; width: 125px; height: 32px;"
                            >
                        </div>

                        <!-- Search Field -->
                        <div class="d-flex align-items-center bg-light border rounded px-3 py-1" style="height: 38px;">
                            <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                            <input 
                                type="text" 
                                id="employee_search_input" 
                                class="form-control border-0 bg-transparent p-0 fs-13" 
                                placeholder="Search employee..." 
                                style="box-shadow: none; width: 180px;"
                            >
                        </div>

                        <!-- Sort Dropdown -->
                        <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                            <a class="dropdown-item d-flex justify-content-between align-items-center py-2 active" href="javascript:void(0)" onclick="applyClientSort('name_asc', this)">
                                <span>{{ __('hrms.common.sort_name_asc') }}</span>
                                <i class="feather-check ms-3 text-primary sort-check"></i>
                            </a>
                            <a class="dropdown-item d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" onclick="applyClientSort('name_desc', this)">
                                <span>{{ __('hrms.common.sort_name_desc') }}</span>
                                <i class="feather-check ms-3 text-primary sort-check d-none"></i>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" onclick="applyClientSort('dept_asc', this)">
                                <span>Department (A-Z)</span>
                                <i class="feather-check ms-3 text-primary sort-check d-none"></i>
                            </a>
                            <a class="dropdown-item d-flex justify-content-between align-items-center py-2" href="javascript:void(0)" onclick="applyClientSort('dept_desc', this)">
                                <span>Department (Z-A)</span>
                                <i class="feather-check ms-3 text-primary sort-check d-none"></i>
                            </a>
                        </x-ui.sort-dropdown>

                        <!-- Filter Dropdown -->
                        <x-ui.filter label="{{ __('hrms.common.filter') }}" offset="0, 5">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> {{ __('hrms.common.filter_options') }}</h6>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Department</label>
                                <x-ui.odoo-form-ui type="select" id="filter_department_id" select2-selector="default">
                                    <option value="">All Departments</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                                <x-ui.odoo-form-ui type="select" id="filter_attendance_status" select2-selector="default">
                                    <option value="">All Statuses</option>
                                    <option value="present">Present</option>
                                    <option value="late">Late</option>
                                    <option value="half_day">Half Day</option>
                                    <option value="absent">Absent</option>
                                    <option value="on_leave">Leave</option>
                                    <option value="wfh">Work from home</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="d-flex gap-2 justify-content-end mt-4">
                                <button type="button" class="btn btn-sm btn-light text-uppercase fw-bold py-2 px-3" style="border-radius: 6px; font-size: 11px; letter-spacing: 0.05em; background-color: #f1f5f9; border: 1px solid #e2e8f0; color: #475569;" onclick="resetClientFilters()">{{ __('hrms.common.reset') }}</button>
                                <button type="button" class="btn btn-sm btn-primary text-uppercase fw-bold py-2 px-3 text-white" style="border-radius: 6px; font-size: 11px; letter-spacing: 0.05em;" onclick="applyClientFilters()">{{ __('hrms.common.apply') }}</button>
                            </div>
                        </x-ui.filter>
                    </div>
                @endif
            </div>
        </div>
        
        <div>
            @if(isset($employee))
                {{-- EMPLOYEE MODE --}}
                <form method="POST" action="{{ route('hrms.attendance.store-manual') }}">
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                    <input type="hidden" name="redirect_url" value="{{ route('hrms.attendance.index', array_merge(['view' => 'employee'], request()->except(['employee_id', 'date', 'view']))) }}">
                    
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-uppercase fs-10 tracking-wider">
                                <tr>
                                    <th class="ps-4">Date</th>
                                    <th>Check In</th>
                                    <th>Check Out</th>
                                    <th>Total Hours</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($dates as $dateStr)
                                    @php
                                        $log = $attendances->get($dateStr);
                                    @endphp
                                    <tr class="employee-row">
                                        <!-- Date Details -->
                                        <td class="ps-4">
                                            <input type="hidden" name="attendance[{{ $dateStr }}][date]" value="{{ $dateStr }}">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center text-dark fw-bold border" style="width: 32px; height: 32px; font-size: 11px;">
                                                    <i class="feather-calendar"></i>
                                                </div>
                                                <div>
                                                    <span class="fw-bold text-dark fs-13 d-block">{{ \Carbon\Carbon::parse($dateStr)->format('M d, Y') }}</span>
                                                    <span class="text-muted fs-11 d-block">{{ \Carbon\Carbon::parse($dateStr)->format('l') }}</span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Check In -->
                                        <td>
                                            <div style="max-width: 140px;">
                                                <input 
                                                    type="time" 
                                                    class="form-control fs-12 px-2.5" 
                                                    name="attendance[{{ $dateStr }}][check_in]" 
                                                    value="{{ $log && $log->check_in && !in_array($log->status, ['absent', 'on_leave']) ? $log->check_in->format('H:i') : '' }}"
                                                    style="height: 34px;"
                                                >
                                            </div>
                                        </td>

                                        <!-- Check Out -->
                                        <td>
                                            <div style="max-width: 140px;">
                                                <input 
                                                    type="time" 
                                                    class="form-control fs-12 px-2.5 check-out-input" 
                                                    name="attendance[{{ $dateStr }}][check_out]" 
                                                    value="{{ $log && $log->check_out && !in_array($log->status, ['absent', 'on_leave']) ? $log->check_out->format('H:i') : '' }}"
                                                    style="height: 34px;"
                                                >
                                            </div>
                                        </td>

                                        <!-- Total Hours -->
                                        <td>
                                            <span class="total-hours-display fw-bold text-dark fs-12">-</span>
                                        </td>

                                        <!-- Status -->
                                        <td>
                                            <div style="max-width: 160px;">
                                                <x-ui.odoo-form-ui type="select" name="attendance[{{ $dateStr }}][status]" class="status-select">
                                                    <option value="auto" @selected($log ? $log->status === 'auto' : true)>Auto (Detect)</option>
                                                    <option value="present" @selected($log ? $log->status === 'present' : false)>Present</option>
                                                    <option value="absent" @selected($log ? $log->status === 'absent' : false)>Absent</option>
                                                    <option value="half_day" @selected($log ? $log->status === 'half_day' : false)>Half Day</option>
                                                    <option value="on_leave" @selected($log ? $log->status === 'on_leave' : false)>Leave</option>
                                                    <option value="wfh" @selected($log ? $log->status === 'wfh' : false)>Work from home</option>
                                                </x-ui.odoo-form-ui>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Submit Toolbar -->
                    <div class="card-footer bg-transparent border-top p-4 d-flex justify-content-end gap-3">
                        <x-ui.button href="{{ route('hrms.attendance.index', array_merge(['view' => 'employee'], request()->except(['employee_id', 'date', 'view']))) }}" variant="light" class="border fw-semibold text-uppercase px-4 py-2" style="font-size: 11px;">Cancel</x-ui.button>
                        <x-ui.button type="submit" variant="primary" class="fw-semibold text-uppercase px-4 py-2" style="font-size: 11px;">Save Attendance Logs</x-ui.button>
                    </div>
                </form>
            @else
                {{-- DATE MODE (default) --}}
                @if($employees->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <i class="feather-alert-circle d-block fs-32 text-warning mb-2"></i>
                        <span class="fs-13 fw-semibold">No active employees found.</span>
                    </div>
                @else
                    <form method="POST" action="{{ route('hrms.attendance.store-manual') }}">
                        @csrf
                        <input type="hidden" name="date" value="{{ $date }}">
                        <input type="hidden" name="redirect_url" value="{{ route('hrms.attendance.index', request()->except(['employee_id', 'date', 'view'])) }}">
                        
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light text-uppercase fs-10 tracking-wider">
                                    <tr>
                                        <th class="ps-4">Employee Details</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Total Hours</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($employees as $employee)
                                        @php
                                            $log = $employee->attendances->first();
                                        @endphp
                                        <tr class="employee-row" 
                                            data-name="{{ strtolower($employee->display_name) }}"
                                            data-department-id="{{ $employee->department_id ?? '' }}"
                                            data-department-name="{{ strtolower($employee->department?->name ?? '') }}"
                                            data-company-id="{{ $employee->company_id }}" 
                                            data-business-unit-id="{{ $employee->business_unit_id ?? '' }}" 
                                            data-branch-id="{{ $employee->branch_id ?? '' }}">
                                            
                                            <!-- Employee Details -->
                                            <td class="ps-4">
                                                <input type="hidden" name="attendance[{{ $employee->id }}][employee_id]" class="employee-id-input" value="{{ $employee->id }}">
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center text-dark fw-bold border" style="width: 32px; height: 32px; font-size: 11px;">
                                                        @if($employee->photo)
                                                            <img src="{{ asset('storage/' . $employee->photo) }}" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">
                                                        @else
                                                            {{ strtoupper(substr($employee->first_name, 0, 1) . substr($employee->last_name, 0, 1)) ?: 'EM' }}
                                                        @endif
                                                    </div>
                                                    <div>
                                                        <span class="fw-bold text-dark fs-13 d-block">{{ $employee->display_name }}</span>
                                                        <span class="text-muted fs-11 d-block"><code class="fw-bold fs-11">{{ $employee->employee_id }}</code> &bull; {{ $employee->department?->name ?? 'No Department' }}</span>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Check In -->
                                            <td>
                                                <div style="max-width: 140px;">
                                                    <input 
                                                        type="time" 
                                                        class="form-control fs-12 px-2.5" 
                                                        name="attendance[{{ $employee->id }}][check_in]" 
                                                        value="{{ $log && $log->check_in && !in_array($log->status, ['absent', 'on_leave']) ? $log->check_in->format('H:i') : '' }}"
                                                        style="height: 34px;"
                                                    >
                                                </div>
                                            </td>

                                            <!-- Check Out -->
                                            <td>
                                                <div style="max-width: 140px;">
                                                    <input 
                                                        type="time" 
                                                        class="form-control fs-12 px-2.5 check-out-input" 
                                                        name="attendance[{{ $employee->id }}][check_out]" 
                                                        value="{{ $log && $log->check_out && !in_array($log->status, ['absent', 'on_leave']) ? $log->check_out->format('H:i') : '' }}"
                                                        style="height: 34px;"
                                                    >
                                                </div>
                                            </td>

                                            <!-- Total Hours -->
                                            <td>
                                                <span class="total-hours-display fw-bold text-dark fs-12">-</span>
                                            </td>

                                            <!-- Status -->
                                            <td>
                                                <div style="max-width: 160px;">
                                                    <x-ui.odoo-form-ui type="select" name="attendance[{{ $employee->id }}][status]" class="status-select">
                                                        <option value="auto" @selected($log ? $log->status === 'auto' : true)>Auto (Detect)</option>
                                                        <option value="present" @selected($log ? $log->status === 'present' : false)>Present</option>
                                                        <option value="absent" @selected($log ? $log->status === 'absent' : false)>Absent</option>
                                                        <option value="half_day" @selected($log ? $log->status === 'half_day' : false)>Half Day</option>
                                                        <option value="on_leave" @selected($log ? $log->status === 'on_leave' : false)>Leave</option>
                                                        <option value="wfh" @selected($log ? $log->status === 'wfh' : false)>Work from home</option>
                                                    </x-ui.odoo-form-ui>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Submit Toolbar -->
                        <div class="border-top pt-4 mt-3 d-flex justify-content-end gap-3">
                            <x-ui.button href="{{ route('hrms.attendance.index', request()->except(['employee_id', 'date', 'view'])) }}" variant="light" class="border fw-semibold text-uppercase px-4 py-2" style="font-size: 11px;">Cancel</x-ui.button>
                            <x-ui.button type="submit" variant="primary" class="fw-semibold text-uppercase px-4 py-2" style="font-size: 11px;">Save Attendance Logs</x-ui.button>
                        </div>
                    </form>
                @endif
            @endif
    </div>

<script>
    let currentFilters = {
        search: '',
        departmentId: '',
        status: ''
    };
    let currentSort = 'name_asc';

    // Real-time Search Input Handler
    const searchInput = document.getElementById('employee_search_input');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            currentFilters.search = this.value.trim().toLowerCase();
            applyAllClientFilters();
        });
    }

    // Apply Client Sort
    function applyClientSort(sortOption, element) {
        currentSort = sortOption;
        
        // Update checkmarks in dropdown
        document.querySelectorAll('.sort-check').forEach(el => el.classList.add('d-none'));
        element.querySelector('.sort-check').classList.remove('d-none');
        
        // Update active class
        element.closest('.dropdown-menu').querySelectorAll('.dropdown-item').forEach(item => item.classList.remove('active'));
        element.classList.add('active');

        sortRows();
    }

    // Apply Filters button handler
    function applyClientFilters() {
        currentFilters.departmentId = document.getElementById('filter_department_id').value;
        currentFilters.status = document.getElementById('filter_attendance_status').value;
        
        // Close dropdown
        $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
        $('.erp-filter-dropdown.show').removeClass('show');

        applyAllClientFilters();
    }

    // Reset Filters button handler
    function resetClientFilters() {
        currentFilters.departmentId = '';
        currentFilters.status = '';
        
        // Update selects
        const deptSelect = $('#filter_department_id');
        const statusSelect = $('#filter_attendance_status');
        deptSelect.val('');
        statusSelect.val('');

        if (deptSelect.data('select2')) deptSelect.trigger('change.select2');
        if (statusSelect.data('select2')) statusSelect.trigger('change.select2');

        applyAllClientFilters();
    }

    // Apply Search + Department + Status Filters
    function applyAllClientFilters() {
        const rows = document.querySelectorAll('.employee-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const name = row.getAttribute('data-name');
            const deptId = row.getAttribute('data-department-id');
            const deptName = row.getAttribute('data-department-name');
            
            // Read selected status inside this row
            const statusSelect = row.querySelector('select[name*="[status]"]');
            const statusVal = statusSelect ? statusSelect.value : '';

            let matches = true;

            // Search query
            if (currentFilters.search) {
                if (!name.includes(currentFilters.search) && !deptName.includes(currentFilters.search)) {
                    matches = false;
                }
            }

            // Department
            if (currentFilters.departmentId && deptId != currentFilters.departmentId) {
                matches = false;
            }

            // Status
            if (currentFilters.status) {
                if (statusVal !== currentFilters.status) {
                    matches = false;
                }
            }

            if (matches) {
                row.classList.remove('d-none');
                row.querySelectorAll('input, select').forEach(el => el.disabled = false);
                visibleCount++;
            } else {
                row.classList.add('d-none');
                row.querySelectorAll('input, select').forEach(el => el.disabled = true);
            }
        });

        // Update count badge
        document.getElementById('visible_count_badge').textContent = `${visibleCount} Employees Listed`;
    }

    // Sort rows dynamically
    function sortRows() {
        const tbody = document.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('.employee-row'));

        rows.sort((a, b) => {
            let valA = '';
            let valB = '';

            if (currentSort === 'name_asc' || currentSort === 'name_desc') {
                valA = a.getAttribute('data-name');
                valB = b.getAttribute('data-name');
            } else if (currentSort === 'dept_asc' || currentSort === 'dept_desc') {
                valA = a.getAttribute('data-department-name');
                valB = b.getAttribute('data-department-name');
            }

            if (currentSort.endsWith('_asc')) {
                return valA.localeCompare(valB);
            } else {
                return valB.localeCompare(valA);
            }
        });

        // Append sorted rows to tbody
        rows.forEach(row => tbody.appendChild(row));
    }

    // Function to calculate and display total work hours in real-time (formatted as Xhr Ym)
    function calculateRowHours(row) {
        const checkInInput = row.querySelector('input[name*="[check_in]"]');
        const checkOutInput = row.querySelector('input[name*="[check_out]"]');
        const display = row.querySelector('.total-hours-display');
        
        if (!checkInInput || !checkOutInput || !display) return;

        const checkIn = checkInInput.value;
        const checkOut = checkOutInput.value;

        if (checkIn && checkOut) {
            const [inH, inM] = checkIn.split(':').map(Number);
            const [outH, outM] = checkOut.split(':').map(Number);

            const inMin = inH * 60 + inM;
            const outMin = outH * 60 + outM;

            if (outMin > inMin) {
                const diffMin = outMin - inMin;
                const hrs = Math.floor(diffMin / 60);
                const mins = diffMin % 60;

                let formatted = '';
                if (hrs > 0) {
                    formatted += `${hrs}hr`;
                }
                if (mins > 0) {
                    if (hrs > 0) formatted += ' ';
                    formatted += `${mins}m`;
                }
                if (hrs === 0 && mins === 0) {
                    formatted = '0m';
                }

                display.textContent = formatted;
                display.classList.remove('text-danger');
                display.classList.add('text-dark');
            } else {
                display.textContent = '0m';
                display.classList.remove('text-dark');
                display.classList.add('text-danger');
            }
        } else {
            display.textContent = '-';
            display.classList.remove('text-danger');
            display.classList.add('text-dark');
        }
    }

    // Function to enable/disable Present, Late, Half Day based on whether Check-In exists
    // (Note: Late has been removed from manual selections, but present, half_day, wfh still checked)
    function updateStatusOptions(row) {
        const checkInInput = row.querySelector('input[name*="[check_in]"]');
        const statusSelect = row.querySelector('select[name*="[status]"]');
        
        if (!checkInInput || !statusSelect) return;

        const hasPunch = checkInInput.value.trim() !== '';
        
        const optPresent = statusSelect.querySelector('option[value="present"]');
        const optHalfDay = statusSelect.querySelector('option[value="half_day"]');
        const optWfh = statusSelect.querySelector('option[value="wfh"]');

        if (!hasPunch) {
            if (optPresent) optPresent.disabled = true;
            if (optHalfDay) optHalfDay.disabled = true;
            if (optWfh) optWfh.disabled = true;

            if (['present', 'half_day', 'wfh'].includes(statusSelect.value)) {
                statusSelect.value = 'auto';
            }
        } else {
            if (optPresent) optPresent.disabled = false;
            if (optHalfDay) optHalfDay.disabled = false;
            if (optWfh) optWfh.disabled = false;
        }

        if ($(statusSelect).data('select2')) {
            $(statusSelect).trigger('change.select2');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize status options state and total hours display for all rows on startup
        document.querySelectorAll('.employee-row').forEach(row => {
            updateStatusOptions(row);
            calculateRowHours(row);
        });

        // Listen to check-in/out input changes to recalculate hours and update status
        $(document).on('change input', 'input[name*="[check_in]"], input[name*="[check_out]"]', function() {
            const row = this.closest('tr');
            updateStatusOptions(row);
            calculateRowHours(row);
        });

        // Date Picker change reloads page to fetch appropriate logs
        const dateInput = document.getElementById('attendance_date');
        if (dateInput) {
            dateInput.addEventListener('change', function() {
                const url = new URL(window.location.href);
                url.searchParams.set('date', this.value);
                window.location.href = url.toString();
            });
        }
    });
</script>
@endsection
