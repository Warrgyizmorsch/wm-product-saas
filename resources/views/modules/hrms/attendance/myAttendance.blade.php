@extends('layouts.duralux')

@section('title', 'My Attendance | SaaS ERP')
@section('page-title', 'My Attendance')
@section('breadcrumb', 'HRMS / My Attendance')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<style>
    #detail-drawer-map {
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.06);
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-4 py-4">

    @php


        // Load geofencing configs for Leaflet Map
        $rule = \App\Domains\HRMS\Models\AttendanceRule::where(function ($q) use ($employee) {
                $q->where('company_id', $employee->company_id)
                  ->orWhereNull('company_id');
            })
            ->where('status', true)
            ->orderByRaw('company_id IS NULL ASC')
            ->first();

        $officeLat = $rule ? $rule->office_latitude  : null;
        $officeLng = $rule ? $rule->office_longitude : null;
        $officeRad = ($rule && $rule->office_radius) ? (int)$rule->office_radius : 200;
        $wfhLat    = $employee->wfh_latitude  ?? null;
        $wfhLng    = $employee->wfh_longitude ?? null;
        $wfhRad    = ($rule && $rule->wfh_tracking_meters) ? (int)$rule->wfh_tracking_meters : 200;
    @endphp

    <!-- Attendance Card -->
    <div class="card border-0 shadow-sm rounded-4">
        <!-- Filters Toolbar -->
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-3">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 w-100">
                <div>
                    <h5 class="fw-bold text-dark mb-0 fs-16">Attendance History</h5>
                    <p class="text-muted fs-12 mb-0">Track and review your clock-in, clock-out, breaks and status history</p>
                </div>
                
                <!-- Search & Filters Toolbar (Standard Theme) -->
                <form method="GET" action="{{ route('hrms.attendance.myAttendance') }}" id="myAttendanceFilterForm" class="d-flex align-items-center gap-2 flex-wrap">
                    <input type="hidden" name="sort" id="myAttendanceSortInput" value="{{ $sort ?? 'date_desc' }}">

                    <!-- Search Field -->
                    <div class="d-flex align-items-center bg-light border rounded px-3 py-1" style="height: 38px;">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input 
                            type="text" 
                            name="search" 
                            id="myAttendanceSearchInput"
                            class="form-control border-0 bg-transparent p-0 fs-13" 
                            placeholder="Search location, status..." 
                            value="{{ $search ?? '' }}"
                            style="box-shadow: none; width: 180px;"
                        >
                    </div>

                    <!-- Sort Dropdown -->
                    <x-ui.sort-dropdown label="Sort">
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ ($sort ?? 'date_desc') === 'date_desc' ? 'active' : '' }}" href="javascript:void(0)" onclick="applySort('date_desc')">
                            <span>Date (Newest)</span>
                            @if(($sort ?? 'date_desc') === 'date_desc') <i class="feather-check ms-3 text-primary"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ ($sort ?? 'date_desc') === 'date_asc' ? 'active' : '' }}" href="javascript:void(0)" onclick="applySort('date_asc')">
                            <span>Date (Oldest)</span>
                            @if(($sort ?? 'date_desc') === 'date_asc') <i class="feather-check ms-3 text-primary"></i> @endif
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ ($sort ?? 'date_desc') === 'checkin_asc' ? 'active' : '' }}" href="javascript:void(0)" onclick="applySort('checkin_asc')">
                            <span>Check-in (Earliest)</span>
                            @if(($sort ?? 'date_desc') === 'checkin_asc') <i class="feather-check ms-3 text-primary"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ ($sort ?? 'date_desc') === 'checkin_desc' ? 'active' : '' }}" href="javascript:void(0)" onclick="applySort('checkin_desc')">
                            <span>Check-in (Latest)</span>
                            @if(($sort ?? 'date_desc') === 'checkin_desc') <i class="feather-check ms-3 text-primary"></i> @endif
                        </a>
                    </x-ui.sort-dropdown>

                    <!-- Filter Dropdown -->
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Date</label>
                            <x-ui.odoo-form-ui type="input" inputType="date" name="date" value="{{ $date }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Month</label>
                            <x-ui.odoo-form-ui type="select" name="month">
                                <option value="">All Months</option>
                                @for($i = 0; $i > -12; $i--)
                                    @php
                                        $m = now()->addMonths($i);
                                        $val = $m->format('Y-m');
                                        $label = $m->format('F Y');
                                    @endphp
                                    <option value="{{ $val }}" @selected(($monthFilter ?? '') === $val)>{{ $label }}</option>
                                @endfor
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">All Statuses</option>
                                <option value="present" @selected($status === 'present')>Present</option>
                                <option value="wfh" @selected($status === 'wfh')>Work From Home</option>
                                <option value="late" @selected($status === 'late')>Late</option>
                                <option value="half_day" @selected($status === 'half_day')>Half Day</option>
                                <option value="absent" @selected($status === 'absent')>Absent</option>
                                <option value="on_leave" @selected($status === 'on_leave')>On Leave</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('hrms.attendance.myAttendance') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary text-white">Apply</button>
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
                            <th class="ps-4">DATE & LOCATION</th>
                            <th>CHECK IN</th>
                            <th>CHECK OUT</th>
                            <th>BREAKS</th>
                            <th>WORKING HOURS</th>
                            <th>STATUS</th>
                            <th class="text-end pe-4">DETAILS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($attendances as $attendance)
                            @php
                                $statusVal = $attendance->status ?: 'present';
                                $isAbsentOrLeave = in_array($statusVal, ['absent', 'on_leave']);

                                // Location Badge Class
                                $locLower = strtolower($attendance->location_type);
                                $locBadgeClass = 'bg-soft-secondary text-secondary';
                                if ($locLower === 'office') {
                                    $locBadgeClass = 'bg-soft-primary text-primary';
                                } elseif ($locLower === 'wfh') {
                                    $locBadgeClass = 'bg-soft-success text-success';
                                } elseif ($locLower === 'onsite') {
                                    $locBadgeClass = 'bg-soft-warning text-warning';
                                }
                            @endphp
                            <tr>
                                <!-- Date & Location -->
                                <td class="ps-4">
                                    <span class="fw-bold text-dark d-block fs-13 mb-1">{{ $attendance->date->format('M d, Y') }}</span>
                                    <span class="badge {{ $locBadgeClass }} border-0 fs-10 rounded-pill px-2 py-0.5">{{ $attendance->formatted_location_type }}</span>
                                </td>

                                <!-- Check-In -->
                                <td>
                                    @if ($attendance->check_in && !$isAbsentOrLeave)
                                        <span class="d-block fw-semibold text-dark fs-12">{{ $attendance->check_in->format('h:i A') }}</span>
                                        @if ($attendance->check_in_latitude)
                                            <span class="text-muted fs-10 d-block"><i class="feather-map-pin fs-9"></i> GPS Tracked</span>
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>

                                <!-- Check-Out -->
                                <td>
                                    @if ($attendance->check_out && !$isAbsentOrLeave)
                                        <span class="d-block fw-semibold text-dark fs-12">{{ $attendance->check_out->format('h:i A') }}</span>
                                        @if ($attendance->check_out_latitude)
                                            <span class="text-muted fs-10 d-block"><i class="feather-map-pin fs-9"></i> GPS Tracked</span>
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>

                                <!-- Breaks -->
                                <td>
                                    @if ($attendance->breaks->isNotEmpty() && !$isAbsentOrLeave)
                                        <div class="d-flex flex-column gap-0.5">
                                            @foreach($attendance->breaks as $brk)
                                                @php
                                                    $brkIn = \Carbon\Carbon::parse($brk->break_in)->format('h:i A');
                                                    $brkOut = $brk->break_out ? \Carbon\Carbon::parse($brk->break_out)->format('h:i A') : 'Active';
                                                    $brkDur = $brk->duration_minutes !== null ? $brk->duration_minutes . 'm' : 'Active';
                                                @endphp
                                                <span class="fs-10 text-muted" style="line-height: 1.2;">{{ $brkIn }} - {{ $brkOut }} ({{ $brkDur }})</span>
                                            @endforeach
                                            @if($attendance->total_break_hours > 0)
                                                <span class="fw-bold mt-1 text-dark" style="font-size: 10px;">Total: {{ $attendance->formatted_break_hours }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>

                                <!-- Working Hours -->
                                <td>
                                    @if ($attendance->check_out && !$isAbsentOrLeave)
                                        <span class="fw-bold text-dark fs-13">{{ $attendance->formatted_work_hours }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>

                                <!-- Status -->
                                <td>
                                    @if($statusVal === 'present')
                                        <span class="badge bg-soft-success text-success px-3 py-1.5 fs-11 rounded-pill fw-bold">Present</span>
                                    @elseif($statusVal === 'wfh')
                                        <span class="badge bg-soft-info text-info px-3 py-1.5 fs-11 rounded-pill fw-bold">WFH</span>
                                    @elseif($statusVal === 'late')
                                        <span class="badge bg-soft-warning text-warning px-3 py-1.5 fs-11 rounded-pill fw-bold">Late</span>
                                    @elseif($statusVal === 'half_day')
                                        <span class="badge px-3 py-1.5 fs-11 rounded-pill fw-bold" style="background-color: rgba(111, 66, 193, 0.1); color: #6f42c1 !important;">Half Day</span>
                                    @elseif($statusVal === 'on_leave')
                                        <span class="badge bg-soft-primary text-primary px-3 py-1.5 fs-11 rounded-pill fw-bold">On Leave</span>
                                    @elseif($statusVal === 'absent')
                                        <span class="badge bg-soft-danger text-danger px-3 py-1.5 fs-11 rounded-pill fw-bold">Absent</span>
                                    @else
                                        <span class="badge bg-soft-secondary text-secondary px-3 py-1.5 fs-11 rounded-pill fw-bold">{{ ucfirst(str_replace('_', ' ', $statusVal)) }}</span>
                                    @endif
                                </td>

                                <!-- Actions (Eye Button for Drawer Details) -->
                                <td class="text-end pe-4">
                                    <button type="button" 
                                            class="btn btn-sm btn-soft-primary rounded-circle p-0 d-inline-flex align-items-center justify-content-center" 
                                            style="width: 28px; height: 28px;" 
                                            data-date="{{ $attendance->date->format('M d, Y') }}"
                                            data-status="{{ ucfirst($statusVal) }}"
                                            data-location-type="{{ $attendance->location_type }}"
                                            data-check-in-time="{{ $attendance->check_in ? $attendance->check_in->format('h:i:s A') : '-' }}"
                                            data-check-out-time="{{ $attendance->check_out ? $attendance->check_out->format('h:i:s A') : '-' }}"
                                            data-check-in-selfie="{{ $attendance->check_in_selfie_path ? asset('storage/' . $attendance->check_in_selfie_path) : '' }}"
                                            data-check-out-selfie="{{ $attendance->check_out_selfie_path ? asset('storage/' . $attendance->check_out_selfie_path) : '' }}"
                                            data-check-in-lat="{{ $attendance->check_in_latitude }}"
                                            data-check-in-lng="{{ $attendance->check_in_longitude }}"
                                            data-check-out-lat="{{ $attendance->check_out_latitude }}"
                                            data-check-out-lng="{{ $attendance->check_out_longitude }}"
                                            data-location-logs="{{ json_encode($attendance->locationLogs->map(fn($l) => ['lat' => (float)$l->latitude, 'lng' => (float)$l->longitude, 'time' => $l->created_at ? $l->created_at->format('h:i A') : ''])) }}"
                                            onclick="viewAttendanceDetailDrawer(this)">
                                        <i class="feather-eye fs-13"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <div class="d-flex flex-column align-items-center justify-content-center py-4">
                                        <i class="feather-calendar fs-36 text-muted mb-3 opacity-30"></i>
                                        <h6 class="fw-semibold text-dark">No Attendance Logs Found</h6>
                                        <p class="fs-12 text-muted mb-0">Try adjusting your filters or search date.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Attendance Record Details Drawer -->
<x-ui.drawer id="attendanceRecordDetailDrawer" title="Attendance Session Details" position="end" style="width: 480px; max-width: 95vw;">
    <div class="px-1">
        <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom">
            <div>
                <span class="text-muted fs-11 d-block text-uppercase">Work Mode</span>
                <span class="badge bg-soft-primary text-primary px-3 py-1 fs-11 rounded-pill fw-bold" id="detail-drawer-location">OFFICE</span>
            </div>
            <div class="text-end">
                <span class="text-muted fs-11 d-block text-uppercase">Status</span>
                <span class="badge bg-soft-success text-success px-3 py-1 fs-11 rounded-pill fw-bold" id="detail-drawer-status">Present</span>
            </div>
        </div>

        <!-- Date Info Card -->
        <div class="bg-light border rounded-3 p-3 mb-4 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted fs-11 d-block text-uppercase">Date</span>
                <h6 class="fw-bold text-dark mb-0 fs-13" id="detail-drawer-date">Aug 07, 2026</h6>
            </div>
            <div class="avatar-sm bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                <i class="feather-calendar fs-16"></i>
            </div>
        </div>

        <!-- Single Location Map -->
        <div class="mb-4">
            <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">Session Location Map</span>
            <div class="position-relative w-100" id="detail-drawer-map-wrap" style="display: none;">
                <input type="text" id="detail_drawer_map_search" class="form-control position-absolute" style="top: 10px; right: 10px; width: 240px; z-index: 1000; box-shadow: 0 2px 8px rgba(0,0,0,0.15) !important; font-size: 11px; border: none !important; border-radius: 6px !important; padding: 6px 12px !important; height: 34px !important; background-color: #fff !important; outline: none !important;" placeholder="Search address or subarea (Press Enter)...">
                <div id="detail-drawer-map" style="height: 250px; width: 100%; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; z-index: 1;"></div>
            </div>
            <div id="detail-drawer-map-none" class="alert alert-light border text-center fs-12 py-4 mb-0">
                <i class="feather-map-pin text-muted fs-20 d-block mb-1"></i> No location coordinates captured for check-in or check-out.
            </div>
        </div>

        <!-- Check-In & Check-Out Info Grid (Stacked/Comparison) -->
        <div class="row g-3">
            <!-- Check In Info -->
            <div class="col-6">
                <div class="card border rounded-3 p-3 h-100 bg-white shadow-sm">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="avatar-sm bg-soft-success text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">
                            <i class="feather-log-in fs-11"></i>
                        </div>
                        <span class="fw-bold text-dark fs-12">Check In</span>
                    </div>
                    <div class="mb-2">
                        <span class="text-muted fs-10 d-block">TIME</span>
                        <span class="fw-bold text-dark fs-12" id="detail-drawer-checkin-time">-</span>
                    </div>
                    <div>
                        <span class="text-muted fs-10 d-block mb-1">SELFIE</span>
                        <div class="bg-light border border-dashed rounded p-2 d-flex align-items-center justify-content-center" style="height: 100px;">
                            <img id="detail-drawer-checkin-selfie" src="" class="rounded border shadow-sm" style="max-height: 85px; max-width: 100%; object-fit: cover; display: none; transform: scaleX(-1);">
                            <span id="detail-drawer-checkin-selfie-none" class="text-muted fs-10 text-center"><i class="feather-image d-block mb-0.5 fs-12"></i> None</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Check Out Info -->
            <div class="col-6">
                <div class="card border rounded-3 p-3 h-100 bg-white shadow-sm">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="avatar-sm bg-soft-danger text-danger rounded-circle d-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">
                            <i class="feather-log-out fs-11"></i>
                        </div>
                        <span class="fw-bold text-dark fs-12">Check Out</span>
                    </div>
                    <div class="mb-2">
                        <span class="text-muted fs-10 d-block">TIME</span>
                        <span class="fw-bold text-dark fs-12" id="detail-drawer-checkout-time">-</span>
                    </div>
                    <div>
                        <span class="text-muted fs-10 d-block mb-1">SELFIE</span>
                        <div class="bg-light border border-dashed rounded p-2 d-flex align-items-center justify-content-center" style="height: 100px;">
                            <img id="detail-drawer-checkout-selfie" src="" class="rounded border shadow-sm" style="max-height: 85px; max-width: 100%; object-fit: cover; display: none; transform: scaleX(-1);">
                            <span id="detail-drawer-checkout-selfie-none" class="text-muted fs-10 text-center"><i class="feather-image d-block mb-0.5 fs-12"></i> None</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-ui.drawer>

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
    let detailDrawerMapObj = null;
    let detailDrawerMarkersGroup = null;

    function viewAttendanceDetailDrawer(btn) {
        const searchInput = document.getElementById('detail_drawer_map_search');
        if (searchInput) {
            searchInput.value = '';
            searchInput.disabled = false;
            searchInput.placeholder = 'Search address or subarea (Press Enter)...';
        }

        const date = btn.getAttribute('data-date');
        const status = btn.getAttribute('data-status');
        const locationType = btn.getAttribute('data-location-type') || 'office';
        
        const checkinTime = btn.getAttribute('data-check-in-time');
        const checkoutTime = btn.getAttribute('data-check-out-time');
        
        const checkinSelfie = btn.getAttribute('data-check-in-selfie');
        const checkoutSelfie = btn.getAttribute('data-check-out-selfie');
        
        const checkinLat = btn.getAttribute('data-check-in-lat');
        const checkinLng = btn.getAttribute('data-check-in-lng');
        const checkoutLat = btn.getAttribute('data-check-out-lat');
        const checkoutLng = btn.getAttribute('data-check-out-lng');

        // Populate header & status details
        document.getElementById('detail-drawer-date').textContent = date;
        document.getElementById('detail-drawer-status').textContent = status;
        document.getElementById('detail-drawer-location').textContent = locationType.toUpperCase();
        
        // Update badge color styles dynamically based on status
        const statusBadge = document.getElementById('detail-drawer-status');
        statusBadge.className = 'badge px-3 py-1 fs-11 rounded-pill fw-bold';
        const statusLower = status.toLowerCase();
        if (statusLower === 'present') {
            statusBadge.classList.add('bg-soft-success', 'text-success');
        } else if (statusLower === 'late') {
            statusBadge.classList.add('bg-soft-warning', 'text-warning');
        } else if (statusLower === 'half day' || statusLower === 'half_day') {
            statusBadge.classList.add('bg-soft-danger', 'text-danger');
        } else if (statusLower === 'absent') {
            statusBadge.classList.add('bg-soft-danger', 'text-danger');
        } else {
            statusBadge.classList.add('bg-soft-primary', 'text-primary');
        }

        // Check In details
        document.getElementById('detail-drawer-checkin-time').textContent = checkinTime;
        const imgCheckin = document.getElementById('detail-drawer-checkin-selfie');
        const noneCheckin = document.getElementById('detail-drawer-checkin-selfie-none');
        if (checkinSelfie) {
            imgCheckin.src = checkinSelfie;
            imgCheckin.style.display = 'block';
            noneCheckin.style.display = 'none';
        } else {
            imgCheckin.src = '';
            imgCheckin.style.display = 'none';
            noneCheckin.style.display = 'block';
        }

        // Check Out details
        document.getElementById('detail-drawer-checkout-time').textContent = checkoutTime;
        const imgCheckout = document.getElementById('detail-drawer-checkout-selfie');
        const noneCheckout = document.getElementById('detail-drawer-checkout-selfie-none');
        if (checkoutSelfie) {
            imgCheckout.src = checkoutSelfie;
            imgCheckout.style.display = 'block';
            noneCheckout.style.display = 'none';
        } else {
            imgCheckout.src = '';
            imgCheckout.style.display = 'none';
            noneCheckout.style.display = 'block';
        }

        // Parse intermediate 15-minute tracking location logs
        const locationLogsStr = btn.getAttribute('data-location-logs') || '[]';
        let locationLogs = [];
        try {
            locationLogs = JSON.parse(locationLogsStr);
        } catch(e) {
            console.error("Failed to parse location logs:", e);
        }

        // Show/hide map div based on coordinates presence
        const mapWrap = document.getElementById('detail-drawer-map-wrap');
        const mapNone = document.getElementById('detail-drawer-map-none');
        
        const hasCheckinCoords = checkinLat && checkinLng && parseFloat(checkinLat) !== 0 && parseFloat(checkinLng) !== 0;
        const hasCheckoutCoords = checkoutLat && checkoutLng && parseFloat(checkoutLat) !== 0 && parseFloat(checkoutLng) !== 0;
        const hasLocationLogs = locationLogs && locationLogs.length > 0;
        const hasGeofenceCoords = {{ ($officeLat && $officeLng) || ($wfhLat && $wfhLng) ? 'true' : 'false' }};

        if (hasCheckinCoords || hasCheckoutCoords || hasLocationLogs || hasGeofenceCoords) {
            mapWrap.style.display = 'block';
            mapNone.style.display = 'none';
        } else {
            mapWrap.style.display = 'none';
            mapNone.style.display = 'block';
        }

        // Show Drawer
        const drawerEl = document.getElementById('attendanceRecordDetailDrawer');
        const bootstrapDrawer = bootstrap.Offcanvas.getOrCreateInstance(drawerEl);
        bootstrapDrawer.show();

        // Render Leaflet map
        setTimeout(() => {
            if (hasCheckinCoords || hasCheckoutCoords || hasLocationLogs || hasGeofenceCoords) {
                // Initialize map if not yet initialized
                if (!detailDrawerMapObj) {
                    @if($officeLat && $officeLng)
                    detailDrawerMapObj = L.map('detail-drawer-map').setView([{{ (float)$officeLat }}, {{ (float)$officeLng }}], 13);
                    @elseif($wfhLat && $wfhLng)
                    detailDrawerMapObj = L.map('detail-drawer-map').setView([{{ (float)$wfhLat }}, {{ (float)$wfhLng }}], 13);
                    @else
                    detailDrawerMapObj = L.map('detail-drawer-map').setView([20.5937, 78.9629], 5);
                    @endif
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '© OpenStreetMap'
                    }).addTo(detailDrawerMapObj);
                    detailDrawerMarkersGroup = L.featureGroup().addTo(detailDrawerMapObj);

                    // Geocoding search logic for details drawer map
                    const searchInput = document.getElementById('detail_drawer_map_search');
                    if (searchInput) {
                        const performDetailSearch = () => {
                            const query = searchInput.value;
                            if (!query) return;

                            searchInput.disabled = true;
                            searchInput.placeholder = 'Searching...';

                            fetch(`https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/findAddressCandidates?f=json&singleLine=${encodeURIComponent(query)}&maxLocations=1`)
                                .then(res => res.json())
                                .then(data => {
                                    if (data && data.candidates && data.candidates.length > 0) {
                                        const lat = parseFloat(data.candidates[0].location.y);
                                        const lng = parseFloat(data.candidates[0].location.x);
                                        if (detailDrawerMapObj) {
                                            detailDrawerMapObj.setView([lat, lng], 15);
                                        }
                                        searchInput.disabled = false;
                                        searchInput.placeholder = 'Search address or subarea (Press Enter)...';
                                    } else {
                                        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`)
                                            .then(res2 => res2.json())
                                            .then(data2 => {
                                                if (data2 && data2.length > 0) {
                                                    const lat = parseFloat(data2[0].lat);
                                                    const lng = parseFloat(data2[0].lon);
                                                    if (detailDrawerMapObj) {
                                                        detailDrawerMapObj.setView([lat, lng], 15);
                                                    }
                                                } else {
                                                    alert("Location not found. Please try a different query.");
                                                }
                                                searchInput.disabled = false;
                                                searchInput.placeholder = 'Search address or subarea (Press Enter)...';
                                            })
                                            .catch(() => {
                                                alert("Location not found. Please try a different query.");
                                                searchInput.disabled = false;
                                                searchInput.placeholder = 'Search address or subarea (Press Enter)...';
                                            });
                                    }
                                })
                                .catch(err => {
                                    console.error("Geocoding error:", err);
                                    searchInput.disabled = false;
                                    searchInput.placeholder = 'Search address or subarea (Press Enter)...';
                                });
                        };

                        searchInput.addEventListener('keypress', function(e) {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                performDetailSearch();
                            }
                        });
                    }
                } else {
                    detailDrawerMarkersGroup.clearLayers();
                }

                const pathLatLngs = [];

                // Add Check-In Marker
                if (hasCheckinCoords) {
                    const checkinLatVal = parseFloat(checkinLat);
                    const checkinLngVal = parseFloat(checkinLng);
                    const checkinLatLng = [checkinLatVal, checkinLngVal];
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
                        .addTo(detailDrawerMarkersGroup)
                        .bindPopup(`<b>Check In Point</b><br>Time: ${checkinTime}<br>Lat: ${checkinLatVal.toFixed(6)}<br>Lng: ${checkinLngVal.toFixed(6)}`);
                }

                // Add Location tracking Logs
                if (hasLocationLogs) {
                    locationLogs.forEach(log => {
                        if (log.lat && log.lng) {
                            const latVal = parseFloat(log.lat);
                            const lngVal = parseFloat(log.lng);
                            const logLatLng = [latVal, lngVal];
                            pathLatLngs.push(logLatLng);

                            L.circleMarker(logLatLng, {
                                radius: 6,
                                fillColor: '#3b82f6',
                                color: '#ffffff',
                                weight: 2,
                                opacity: 1,
                                fillOpacity: 0.8
                            }).addTo(detailDrawerMarkersGroup)
                              .bindPopup(`<b>Location Log (Tracking)</b><br>Time: ${log.time}<br>Lat: ${latVal.toFixed(6)}<br>Lng: ${lngVal.toFixed(6)}`);
                        }
                    });
                }

                // Add Check-Out Marker
                if (hasCheckoutCoords) {
                    const checkoutLatVal = parseFloat(checkoutLat);
                    const checkoutLngVal = parseFloat(checkoutLng);
                    const checkoutLatLng = [checkoutLatVal, checkoutLngVal];
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
                        .addTo(detailDrawerMarkersGroup)
                        .bindPopup(`<b>Check Out Point</b><br>Time: ${checkoutTime}<br>Lat: ${checkoutLatVal.toFixed(6)}<br>Lng: ${checkoutLngVal.toFixed(6)}`);
                }

                // Add Polyline connecting all tracking path points
                if (pathLatLngs.length >= 2) {
                    L.polyline(pathLatLngs, {
                        color: '#4f46e5',
                        weight: 4,
                        opacity: 0.8,
                        dashArray: '8, 8',
                        lineJoin: 'round'
                    }).addTo(detailDrawerMarkersGroup);
                }

                // Draw geofence threshold radius circles
                @if($officeLat && $officeLng)
                const officeGeofenceLat = {{ (float)$officeLat }};
                const officeGeofenceLng = {{ (float)$officeLng }};
                const officeGeofenceRadius = {{ $officeRad }};
                L.circle([officeGeofenceLat, officeGeofenceLng], {
                    radius: officeGeofenceRadius,
                    color: '#4f46e5',
                    weight: 2,
                    fillColor: '#4f46e5',
                    fillOpacity: 0.08,
                    dashArray: '6, 4'
                }).addTo(detailDrawerMarkersGroup)
                  .bindPopup(`<b>Office Geofence</b><br>Lat: ${officeGeofenceLat.toFixed(6)}<br>Lng: ${officeGeofenceLng.toFixed(6)}<br>Radius: ${officeGeofenceRadius}m`);
                @endif

                @if($wfhLat && $wfhLng)
                const wfhGeofenceLat = {{ (float)$wfhLat }};
                const wfhGeofenceLng = {{ (float)$wfhLng }};
                const wfhGeofenceRadius = {{ $wfhRad }};
                L.circle([wfhGeofenceLat, wfhGeofenceLng], {
                    radius: wfhGeofenceRadius,
                    color: '#10b981',
                    weight: 2,
                    fillColor: '#10b981',
                    fillOpacity: 0.08,
                    dashArray: '6, 4'
                }).addTo(detailDrawerMarkersGroup)
                  .bindPopup(`<b>WFH Geofence</b><br>Lat: ${wfhGeofenceLat.toFixed(6)}<br>Lng: ${wfhGeofenceLng.toFixed(6)}<br>Radius: ${wfhGeofenceRadius}m`);
                @endif

                // Set View/Bounds
                if (pathLatLngs.length > 0) {
                    const bounds = detailDrawerMarkersGroup.getBounds();
                    if (pathLatLngs.length >= 2) {
                        detailDrawerMapObj.fitBounds(bounds, { padding: [30, 30] });
                    } else {
                        detailDrawerMapObj.setView(pathLatLngs[0], 15);
                    }
                } else if (detailDrawerMarkersGroup.getLayers().length > 0) {
                    try {
                        detailDrawerMapObj.fitBounds(detailDrawerMarkersGroup.getBounds(), { padding: [20, 20] });
                    } catch(e) {
                        @if($officeLat && $officeLng)
                        detailDrawerMapObj.setView([{{ (float)$officeLat }}, {{ (float)$officeLng }}], 15);
                        @elseif($wfhLat && $wfhLng)
                        detailDrawerMapObj.setView([{{ (float)$wfhLat }}, {{ (float)$wfhLng }}], 15);
                        @endif
                    }
                }
                
                detailDrawerMapObj.invalidateSize();
            }
        }, 300);
    }

    // Attach invalidation listener on drawer open & handle live AJAX filters/search
    let activeAttendanceRequest = null;

    function loadAttendanceList(url, closeFilter = false) {
        const listContainer = $('.table-responsive');
        listContainer.css('opacity', '0.5');

        if (activeAttendanceRequest) {
            activeAttendanceRequest.abort();
        }

        activeAttendanceRequest = $.ajax({
            url: url,
            type: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(response, 'text/html');
                // Update table contents
                var newTable = doc.querySelector('.table-responsive');
                var oldTable = document.querySelector('.table-responsive');
                if (oldTable && newTable) {
                    oldTable.innerHTML = newTable.innerHTML;
                }
                // Sync sorting checkmarks in sort dropdown
                var newUrl = new URL(url, window.location.href);
                var sortVal = newUrl.searchParams.get('sort') || 'date_desc';
                document.getElementById('myAttendanceSortInput').value = sortVal;
                
                const dropdownLinks = document.querySelectorAll('.dropdown-item[onclick^="applySort"]');
                dropdownLinks.forEach(link => {
                    const onClickAttr = link.getAttribute('onclick');
                    const match = onClickAttr.match(/applySort\('([^']+)'\)/);
                    if (match && match[1]) {
                        const itemSort = match[1];
                        link.classList.remove('active');
                        const checkIcon = link.querySelector('.feather-check');
                        if (checkIcon) {
                            checkIcon.remove();
                        }
                        if (itemSort === sortVal) {
                            link.classList.add('active');
                            const span = link.querySelector('span');
                            if (span) {
                                const check = document.createElement('i');
                                check.className = 'feather-check ms-3 text-primary';
                                span.parentNode.appendChild(check);
                            }
                        }
                    }
                });

                if (closeFilter) {
                    $('.dropdown-menu.show').removeClass('show');
                    $('.dropdown.show').removeClass('show');
                }

                // Push state to browser URL history
                if (window.history.pushState) {
                    window.history.pushState({path: url}, '', url);
                }
            },
            complete: function() {
                listContainer.css('opacity', '1');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const drawerEl = document.getElementById('attendanceRecordDetailDrawer');
        if (drawerEl) {
            document.body.appendChild(drawerEl);
            drawerEl.addEventListener('shown.bs.offcanvas', function() {
                if (detailDrawerMapObj) {
                    detailDrawerMapObj.invalidateSize();
                }
            });
        }

        // Live input search without page reloads
        const searchInput = document.getElementById('myAttendanceSearchInput');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    var form = $('#myAttendanceFilterForm');
                    var formData = form.serialize();
                    var url = form.attr('action') + '?' + formData;
                    loadAttendanceList(url);
                }, 300); // 300ms debounce
            });
        }

        // Handle Filter Form submit via AJAX
        $(document).on('submit', '#myAttendanceFilterForm', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var form = $(this);
            var formData = form.serialize();
            var url = form.attr('action') + '?' + formData;
            loadAttendanceList(url, true);
        });
    });

    function applySort(val) {
        document.getElementById('myAttendanceSortInput').value = val;
        var form = $('#myAttendanceFilterForm');
        var formData = form.serialize();
        var url = form.attr('action') + '?' + formData;
        loadAttendanceList(url);
    }
</script>
@endpush
@endsection
