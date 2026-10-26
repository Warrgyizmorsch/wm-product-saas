@extends('layouts.duralux')

@section('title', __('hrms.my_attendance.title') . ' | SaaS ERP')
@section('page-title', __('hrms.my_attendance.title'))
@section('breadcrumb', 'HRMS / ' . __('hrms.my_attendance.title'))

@section('page-actions')
    <x-ui.button type="button" data-bs-toggle="modal" data-bs-target="#requestCorrectionModal" onclick="openCorrectionModal()" variant="primary" class="text-white fw-bold text-uppercase px-3" icon="feather-edit-3" style="height: 38px; display: inline-flex; align-items: center; font-size: 11px; border-radius: 6px;">
        {{ __('hrms.my_attendance.request_correction') }}
    </x-ui.button>
@endsection

@push('styles')
@once
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
@endonce
<style>
    #detail-drawer-map {
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.06);
    }
</style>
@endpush

@section('content')
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
        $officeRad = $rule?->office_radius ? (int)$rule->office_radius : null;
        $wfhLat    = $employee->wfh_latitude  ?? null;
        $wfhLng    = $employee->wfh_longitude ?? null;
        $wfhRad    = $rule?->wfh_tracking_meters ? (int)$rule->wfh_tracking_meters : null;
    @endphp

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
                <ul class="mb-0 ps-3 fs-12">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="mb-4 pb-3 border-bottom">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 w-100">
                <div>
                    <h5 class="fw-bold text-dark mb-0 fs-16">{{ __('hrms.my_attendance.history_title') }}</h5>
                    <p class="text-muted fs-12 mb-0">{{ __('hrms.my_attendance.history_subtitle') }}</p>
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
                            placeholder="{{ __('hrms.my_attendance.search_placeholder') }}" 
                            value="{{ $search ?? '' }}"
                            style="box-shadow: none; width: 180px;"
                        >
                    </div>

                    <!-- Sort Dropdown -->
                    <x-ui.sort-dropdown :label="__('hrms.my_attendance.sort')">
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ ($sort ?? 'date_desc') === 'date_desc' ? 'active' : '' }}" href="javascript:void(0)" onclick="applySort('date_desc')">
                            <span>{{ __('hrms.my_attendance.sort_date_desc') }}</span>
                            @if(($sort ?? 'date_desc') === 'date_desc') <i class="feather-check ms-3 text-primary"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ ($sort ?? 'date_desc') === 'date_asc' ? 'active' : '' }}" href="javascript:void(0)" onclick="applySort('date_asc')">
                            <span>{{ __('hrms.my_attendance.sort_date_asc') }}</span>
                            @if(($sort ?? 'date_desc') === 'date_asc') <i class="feather-check ms-3 text-primary"></i> @endif
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ ($sort ?? 'date_desc') === 'checkin_asc' ? 'active' : '' }}" href="javascript:void(0)" onclick="applySort('checkin_asc')">
                            <span>{{ __('hrms.my_attendance.sort_checkin_asc') }}</span>
                            @if(($sort ?? 'date_desc') === 'checkin_asc') <i class="feather-check ms-3 text-primary"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ ($sort ?? 'date_desc') === 'checkin_desc' ? 'active' : '' }}" href="javascript:void(0)" onclick="applySort('checkin_desc')">
                            <span>{{ __('hrms.my_attendance.sort_checkin_desc') }}</span>
                            @if(($sort ?? 'date_desc') === 'checkin_desc') <i class="feather-check ms-3 text-primary"></i> @endif
                        </a>
                    </x-ui.sort-dropdown>

                    <!-- Filter Dropdown -->
                    <x-ui.filter :label="__('hrms.my_attendance.filter')" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.my_attendance.filter_options') }}</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.my_attendance.date') }}</label>
                            <x-ui.odoo-form-ui type="input" inputType="date" name="date" value="{{ $date }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.my_attendance.month') }}</label>
                            <x-ui.odoo-form-ui type="select" name="month" select2-selector="default">
                                <option value="">{{ __('hrms.my_attendance.all_months') }}</option>
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
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.my_attendance.status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">{{ __('hrms.my_attendance.all_statuses') }}</option>
                                <option value="present" @selected($status === 'present')>{{ __('hrms.my_attendance.present') }}</option>
                                <option value="wfh" @selected($status === 'wfh')>{{ __('hrms.my_attendance.wfh') }}</option>
                                <option value="late" @selected($status === 'late')>{{ __('hrms.my_attendance.late') }}</option>
                                <option value="half_day" @selected($status === 'half_day')>{{ __('hrms.my_attendance.half_day') }}</option>
                                <option value="absent" @selected($status === 'absent')>{{ __('hrms.my_attendance.absent') }}</option>
                                <option value="on_leave" @selected($status === 'on_leave')>{{ __('hrms.my_attendance.leave') }}</option>
                                <option value="week_off" @selected($status === 'week_off')>{{ __('hrms.my_attendance.weekly_off') }}</option>
                                <option value="holiday" @selected($status === 'holiday')>{{ __('hrms.my_attendance.holiday') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <x-ui.button href="{{ route('hrms.attendance.myAttendance') }}" variant="light" size="sm" class="border">{{ __('hrms.my_attendance.reset') }}</x-ui.button>
                            <x-ui.button type="submit" variant="primary" size="sm">{{ __('hrms.my_attendance.apply') }}</x-ui.button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-uppercase fs-10 tracking-wider">
                        <tr>
                            <th class="ps-4">{{ __('hrms.my_attendance.th_date_location') }}</th>
                            <th>{{ __('hrms.my_attendance.th_check_in') }}</th>
                            <th>{{ __('hrms.my_attendance.th_check_out') }}</th>
                            <th>{{ __('hrms.my_attendance.th_breaks') }}</th>
                            <th>{{ __('hrms.my_attendance.th_working_hours') }}</th>
                            <th>{{ __('hrms.my_attendance.th_status') }}</th>
                            <th class="text-end pe-4">{{ __('hrms.my_attendance.th_details') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($attendances as $attendance)
                            @php
                                $statusVal = $attendance->status ?: 'present';
                                $isAbsentOrLeave = in_array($statusVal, ['absent', 'on_leave']);

                                // Location Badge Class & Translated Label
                                $locLower = strtolower((string)$attendance->location_type);
                                $locBadgeClass = 'bg-soft-primary text-primary';
                                $locText = __('hrms.my_attendance.office');
                                if ($locLower === 'wfh') {
                                    $locBadgeClass = 'bg-soft-success text-success';
                                    $locText = __('hrms.my_attendance.wfh');
                                } elseif (in_array($locLower, ['onsite', 'on-site', 'site'])) {
                                    $locBadgeClass = 'bg-soft-warning text-warning';
                                    $locText = __('hrms.my_attendance.onsite');
                                } elseif ($locLower === 'office') {
                                    $locBadgeClass = 'bg-soft-primary text-primary';
                                    $locText = __('hrms.my_attendance.office');
                                } elseif (!empty($attendance->formatted_location_type) && $attendance->formatted_location_type !== '—') {
                                    $locText = $attendance->formatted_location_type;
                                }

                                // Status Badge & Translated Label
                                $statusBadgeClass = 'bg-soft-secondary text-secondary';
                                $statusText = ucfirst(str_replace('_', ' ', $statusVal));
                                $statusStyle = '';
                                if ($statusVal === 'present') {
                                    $statusBadgeClass = 'bg-soft-success text-success';
                                    $statusText = __('hrms.my_attendance.present');
                                } elseif ($statusVal === 'wfh') {
                                    $statusBadgeClass = 'bg-soft-info text-info';
                                    $statusText = __('hrms.my_attendance.wfh');
                                } elseif ($statusVal === 'late') {
                                    $statusBadgeClass = 'bg-soft-warning text-warning';
                                    $statusText = __('hrms.my_attendance.late');
                                } elseif ($statusVal === 'half_day') {
                                    $statusStyle = 'background-color: rgba(111, 66, 193, 0.1); color: #6f42c1 !important;';
                                    $statusText = __('hrms.my_attendance.half_day');
                                } elseif ($statusVal === 'on_leave') {
                                    $statusBadgeClass = 'bg-soft-primary text-primary';
                                    $statusText = __('hrms.my_attendance.on_leave');
                                    if (!empty($attendance->leave_type_name) && strtolower(trim($attendance->leave_type_name)) !== 'leave') {
                                        $statusText = __('hrms.my_attendance.on_leave') . ': ' . $attendance->leave_type_name;
                                    }
                                } elseif ($statusVal === 'absent') {
                                    $statusBadgeClass = 'bg-soft-danger text-danger';
                                    $statusText = __('hrms.my_attendance.absent');
                                } elseif ($statusVal === 'week_off' || $statusVal === 'weekly_off') {
                                    $statusBadgeClass = 'bg-soft-secondary text-secondary';
                                    $statusText = __('hrms.my_attendance.weekly_off');
                                } elseif ($statusVal === 'holiday') {
                                    $statusStyle = 'background-color: rgba(79, 70, 229, 0.1); color: #4f46e5 !important;';
                                    $statusText = __('hrms.my_attendance.holiday');
                                    if (!empty($attendance->holiday_name)) {
                                        $statusText = __('hrms.my_attendance.holiday') . ': ' . $attendance->holiday_name;
                                    }
                                }
                            @endphp
                            <tr>
                                <!-- Date & Location -->
                                <td class="ps-4">
                                    <span class="fw-bold text-dark d-block fs-13 mb-1">{{ $attendance->date->format('M d, Y') }}</span>
                                    <span class="badge {{ $locBadgeClass }} border-0 fs-10 rounded-pill px-2 py-0.5">{{ $locText }}</span>
                                </td>

                                <!-- Check-In -->
                                <td>
                                    @if ($attendance->check_in && !$isAbsentOrLeave)
                                        <span class="d-block fw-semibold text-dark fs-12">{{ $attendance->check_in->format('h:i A') }}</span>
                                        @if ($attendance->check_in_latitude)
                                            <span class="text-muted fs-10 d-block"><i class="feather-map-pin fs-9"></i> {{ __('hrms.my_attendance.gps_tracked') }}</span>
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
                                            <span class="text-muted fs-10 d-block"><i class="feather-map-pin fs-9"></i> {{ __('hrms.my_attendance.gps_tracked') }}</span>
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
                                                    $brkOut = $brk->break_out ? \Carbon\Carbon::parse($brk->break_out)->format('h:i A') : __('hrms.my_attendance.active');
                                                    $brkDur = $brk->duration_minutes !== null ? $brk->duration_minutes . 'm' : __('hrms.my_attendance.active');
                                                @endphp
                                                <span class="fs-10 text-muted" style="line-height: 1.2;">{{ $brkIn }} - {{ $brkOut }} ({{ $brkDur }})</span>
                                            @endforeach
                                            @if($attendance->total_break_hours > 0)
                                                <span class="fw-bold mt-1 text-dark" style="font-size: 10px;">{{ __('hrms.my_attendance.total_break') }}: {{ $attendance->formatted_break_hours }}</span>
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
                                    <span class="badge {{ $statusBadgeClass }} px-3 py-1.5 fs-11 rounded-pill fw-bold" @if($statusStyle) style="{{ $statusStyle }}" @endif>{{ $statusText }}</span>
                                </td>

                                <!-- Actions (Eye Button for Drawer Details) -->
                                <td class="text-end pe-4">
                                    @php
                                        $dateKey = $attendance->date->format('Y-m-d');
                                        $hasPending = isset($correctionRequests[$dateKey]) && $correctionRequests[$dateKey]->where('status', 'pending')->isNotEmpty();
                                        $hasApproved = isset($correctionRequests[$dateKey]) && $correctionRequests[$dateKey]->where('status', 'approved')->isNotEmpty();
                                    @endphp

                                    @if($hasPending)
                                        <span class="badge bg-soft-warning text-warning fs-10 border-0 me-1">{{ __('hrms.my_attendance.correction_pending') }}</span>
                                    @elseif($hasApproved)
                                        <span class="badge bg-soft-success text-success fs-10 border-0 me-1">{{ __('hrms.my_attendance.regularized') }}</span>
                                    @endif

                                    @if ($attendance->id)
                                    <button type="button" 
                                             class="btn btn-sm btn-soft-primary rounded-circle p-0 d-inline-flex align-items-center justify-content-center" 
                                            style="width: 28px; height: 28px;" 
                                            data-date="{{ $attendance->date->format('M d, Y') }}"
                                            data-date-raw="{{ $attendance->date->format('Y-m-d') }}"
                                            data-status="{{ $statusVal }}"
                                            data-status-label="{{ $statusText }}"
                                            data-location-type="{{ $attendance->location_type ?: '—' }}"
                                            data-location-label="{{ $locText }}"
                                            data-check-in-time="{{ $attendance->check_in ? $attendance->check_in->format('h:i:s A') : '-' }}"
                                            data-check-out-time="{{ $attendance->check_out ? $attendance->check_out->format('h:i:s A') : '-' }}"
                                            data-check-in-raw="{{ $attendance->check_in ? $attendance->check_in->format('H:i') : '' }}"
                                            data-check-out-raw="{{ $attendance->check_out ? $attendance->check_out->format('H:i') : '' }}"
                                            data-has-pending="{{ $hasPending ? '1' : '0' }}"
                                            data-can-correct="{{ (!$hasPending && $attendance->date->lte(now())) ? '1' : '0' }}"
                                            data-check-in-selfie="{{ $attendance->check_in_selfie_path ? asset('storage/' . $attendance->check_in_selfie_path) : '' }}"
                                            data-check-out-selfie="{{ $attendance->check_out_selfie_path ? asset('storage/' . $attendance->check_out_selfie_path) : '' }}"
                                            data-check-in-lat="{{ $attendance->check_in_latitude }}"
                                            data-check-in-lng="{{ $attendance->check_in_longitude }}"
                                            data-check-out-lat="{{ $attendance->check_out_latitude }}"
                                            data-check-out-lng="{{ $attendance->check_out_longitude }}"
                                            data-location-logs="{{ json_encode($attendance->formatted_location_logs ?? []) }}"
                                            data-route-snapshot="{{ $attendance->route_snapshot_url ?? '' }}"
                                            data-total-distance="{{ (float)($attendance->total_distance_km ?? 0) }}"
                                            data-route-summary="{{ json_encode($attendance->route_summary ?? []) }}"
                                            onclick="viewAttendanceDetailDrawer(this)">
                                        <i class="feather-eye fs-13"></i>
                                    </button>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <div class="d-flex flex-column align-items-center justify-content-center py-4">
                                        <i class="feather-calendar fs-36 text-muted mb-3 opacity-30"></i>
                                        <h6 class="fw-semibold text-dark">{{ __('hrms.my_attendance.empty_title') }}</h6>
                                        <p class="fs-12 text-muted mb-0">{{ __('hrms.my_attendance.empty_desc') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<!-- Attendance Record Details Drawer -->
<x-ui.drawer id="attendanceRecordDetailDrawer" :title="__('hrms.my_attendance.drawer_title')" position="end" style="width: 480px; max-width: 95vw;">
    <div class="px-1">
        <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom">
            <div>
                <span class="text-muted fs-11 d-block text-uppercase">{{ __('hrms.my_attendance.work_mode') }}</span>
                <span class="badge bg-soft-primary text-primary px-3 py-1 fs-11 rounded-pill fw-bold" id="detail-drawer-location">{{ __('hrms.my_attendance.office') }}</span>
            </div>
            <div class="text-end">
                <span class="text-muted fs-11 d-block text-uppercase">{{ __('hrms.my_attendance.status') }}</span>
                <span class="badge bg-soft-success text-success px-3 py-1 fs-11 rounded-pill fw-bold" id="detail-drawer-status">{{ __('hrms.my_attendance.present') }}</span>
            </div>
        </div>

        <!-- Date Info Card -->
        <div class="bg-light border rounded-3 p-3 mb-4 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted fs-11 d-block text-uppercase">{{ __('hrms.my_attendance.date') }}</span>
                <h6 class="fw-bold text-dark mb-0 fs-13" id="detail-drawer-date">-</h6>
            </div>
            <div class="avatar-sm bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                <i class="feather-calendar fs-16"></i>
            </div>
        </div>

        <!-- Single Location Map -->
        <div class="mb-4">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="text-muted fs-11 text-uppercase fw-semibold">{{ __('hrms.my_attendance.session_map') }}</span>
                <div class="d-flex align-items-center gap-1">
                    <span class="badge bg-soft-primary text-primary border px-2 py-0.5 rounded-pill fs-11 fw-semibold" id="detail-drawer-distance-badge" style="display: none;">
                        <i class="feather-navigation fs-11 me-1"></i> <span id="detail-drawer-distance-val">0.00</span> KM
                    </span>
                    <a href="#" target="_blank" id="detail-drawer-snapshot-link" class="badge bg-light text-muted border px-2 py-0.5 rounded-pill fs-11 fw-semibold text-decoration-none" style="display: none;">
                        <i class="feather-image fs-11 me-1"></i> Snapshot
                    </a>
                </div>
            </div>
            <div class="position-relative w-100" id="detail-drawer-map-wrap" style="display: none;">
                <input type="text" id="detail_drawer_map_search" class="form-control position-absolute" style="top: 10px; right: 10px; width: 240px; z-index: 1000; box-shadow: 0 2px 8px rgba(0,0,0,0.15) !important; font-size: 11px; border: none !important; border-radius: 6px !important; padding: 6px 12px !important; height: 34px !important; background-color: #fff !important; outline: none !important;" placeholder="{{ __('hrms.my_attendance.search_address_placeholder') }}">
                <div id="detail-drawer-map" style="height: 250px; width: 100%; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; z-index: 1;"></div>
            </div>
            <div id="detail-drawer-map-none" class="alert alert-light border text-center fs-12 py-4 mb-0">
                <i class="feather-map-pin text-muted fs-20 d-block mb-1"></i> {{ __('hrms.my_attendance.no_coords') }}
            </div>
        </div>

        <!-- Telemetry Compliance & Tracking Gap Status (Pillar 1 & Pillar 3) -->
        <div id="detail-drawer-telemetry-card" class="mb-4 rounded-3 p-3 border" style="display: none;">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <div class="d-flex align-items-center gap-2">
                    <span id="detail-drawer-telemetry-icon" class="avatar-xs rounded-circle d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;">
                        <i class="feather-activity fs-12"></i>
                    </span>
                    <strong class="fs-12 fw-bold" id="detail-drawer-telemetry-title">GPS Telemetry Status</strong>
                </div>
                <span class="badge rounded-pill fs-10 fw-bold px-2.5 py-1" id="detail-drawer-telemetry-badge">100% Active</span>
            </div>
            <p class="fs-11 text-muted mb-0 ps-4" id="detail-drawer-telemetry-desc">Shift telemetry continuously recorded without GPS interruption.</p>
            <div id="detail-drawer-telemetry-gap-list" class="mt-2 ps-4" style="display: none;"></div>
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
                        <span class="fw-bold text-dark fs-12">{{ __('hrms.my_attendance.check_in') }}</span>
                    </div>
                    <div class="mb-2">
                        <span class="text-muted fs-10 d-block">{{ __('hrms.my_attendance.lbl_time') }}</span>
                        <span class="fw-bold text-dark fs-12" id="detail-drawer-checkin-time">-</span>
                    </div>
                    <div>
                        <span class="text-muted fs-10 d-block mb-1">{{ __('hrms.my_attendance.lbl_selfie') }}</span>
                        <div class="bg-light border border-dashed rounded p-2 d-flex align-items-center justify-content-center" style="height: 100px;">
                            <img id="detail-drawer-checkin-selfie" src="" class="rounded border shadow-sm" style="max-height: 85px; max-width: 100%; object-fit: cover; display: none; transform: scaleX(-1);">
                            <span id="detail-drawer-checkin-selfie-none" class="text-muted fs-10 text-center"><i class="feather-image d-block mb-0.5 fs-12"></i> {{ __('hrms.my_attendance.none') }}</span>
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
                        <span class="fw-bold text-dark fs-12">{{ __('hrms.my_attendance.check_out') }}</span>
                    </div>
                    <div class="mb-2">
                        <span class="text-muted fs-10 d-block">{{ __('hrms.my_attendance.lbl_time') }}</span>
                        <span class="fw-bold text-dark fs-12" id="detail-drawer-checkout-time">-</span>
                    </div>
                    <div>
                        <span class="text-muted fs-10 d-block mb-1">{{ __('hrms.my_attendance.lbl_selfie') }}</span>
                        <div class="bg-light border border-dashed rounded p-2 d-flex align-items-center justify-content-center" style="height: 100px;">
                            <img id="detail-drawer-checkout-selfie" src="" class="rounded border shadow-sm" style="max-height: 85px; max-width: 100%; object-fit: cover; display: none; transform: scaleX(-1);">
                            <span id="detail-drawer-checkout-selfie-none" class="text-muted fs-10 text-center"><i class="feather-image d-block mb-0.5 fs-12"></i> {{ __('hrms.my_attendance.none') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-ui.drawer>

<!-- Attendance Correction Request Modal -->
<div class="modal fade" id="requestCorrectionModal" tabindex="-1" aria-labelledby="requestCorrectionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content text-dark">
            <form method="POST" action="{{ route('hrms.attendance.corrections.store') }}" id="correctionRequestForm">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="requestCorrectionModalLabel">{{ __('hrms.my_attendance.modal_title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body d-flex flex-column gap-3">
                    <div class="alert alert-info py-2 px-3 mb-0 fs-12">
                        <i class="feather-info me-1"></i> {{ __('hrms.my_attendance.modal_info') }}
                    </div>
                    
                    <x-ui.odoo-form-ui type="input" inputType="date" :label="__('hrms.my_attendance.lbl_date_of_correction')" name="date" id="correction_date_val" :required="true" />
                    <x-ui.odoo-form-ui type="input" inputType="time" :label="__('hrms.my_attendance.lbl_requested_check_in')" name="requested_check_in" id="correction_check_in" />
                    <x-ui.odoo-form-ui type="input" inputType="time" :label="__('hrms.my_attendance.lbl_requested_check_out')" name="requested_check_out" id="correction_check_out" />
                    
                    <x-ui.odoo-form-ui type="textarea" :label="__('hrms.my_attendance.lbl_reason')" name="reason" id="correction_reason" :placeholder="__('hrms.my_attendance.placeholder_reason')" :required="true" />
                </div>
                <div class="modal-footer">
                    <x-ui.button type="button" variant="light" class="border text-uppercase fw-bold fs-12" data-bs-dismiss="modal">{{ __('hrms.my_attendance.cancel') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary" class="text-uppercase fw-bold fs-12">{{ __('hrms.my_attendance.btn_submit_request') }}</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')

<script>
    let detailDrawerMapObj = null;
    let detailDrawerOverlays = [];


    function clearDetailDrawerOverlays() {
        detailDrawerOverlays.forEach(overlay => {
            if (overlay && typeof overlay.remove === 'function') {
                overlay.remove();
            }
        });
        detailDrawerOverlays = [];
    }

    window.viewAttendanceDetailDrawer = function(btn) {
        const searchInput = document.getElementById('detail_drawer_map_search');
        if (searchInput) {
            searchInput.value = '';
            searchInput.disabled = false;
            searchInput.placeholder = {!! json_encode(__('hrms.my_attendance.search_address_placeholder')) !!};
        }

        const date = btn.getAttribute('data-date');
        const status = btn.getAttribute('data-status');
        const statusLabel = btn.getAttribute('data-status-label') || status;
        const locationType = btn.getAttribute('data-location-type') || 'office';
        const locationLabel = btn.getAttribute('data-location-label') || locationType;
        
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
        document.getElementById('detail-drawer-status').textContent = statusLabel;
        document.getElementById('detail-drawer-location').textContent = locationLabel.toUpperCase();
        
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

        // Distance & Route Snapshot
        const totalDistance = parseFloat(btn.getAttribute('data-total-distance') || '0');
        const routeSnapshot = btn.getAttribute('data-route-snapshot') || '';
        const distBadge = document.getElementById('detail-drawer-distance-badge');
        const distVal = document.getElementById('detail-drawer-distance-val');
        const snapshotLink = document.getElementById('detail-drawer-snapshot-link');

        // Route Summary & Telemetry Compliance (Pillar 1 & Pillar 3)
        let routeSummary = {};
        try {
            const rawSummary = btn.getAttribute('data-route-summary');
            if (rawSummary) {
                routeSummary = (typeof rawSummary === 'string') ? JSON.parse(rawSummary) : rawSummary;
            }
        } catch(e) {
            console.error("Failed to parse route summary:", e);
        }

        const teleCard = document.getElementById('detail-drawer-telemetry-card');
        const teleTitle = document.getElementById('detail-drawer-telemetry-title');
        const teleDesc = document.getElementById('detail-drawer-telemetry-desc');
        const teleBadge = document.getElementById('detail-drawer-telemetry-badge');
        const teleIcon = document.getElementById('detail-drawer-telemetry-icon');
        const teleGapList = document.getElementById('detail-drawer-telemetry-gap-list');

        if (distBadge && distVal) {
            if (totalDistance > 0) {
                distVal.textContent = totalDistance.toFixed(2);
                distBadge.style.display = 'inline-flex';
            } else {
                distBadge.style.display = 'none';
            }
        }

        if (snapshotLink) {
            if (routeSnapshot && routeSnapshot !== '') {
                snapshotLink.href = routeSnapshot;
                snapshotLink.style.display = 'inline-flex';
            } else {
                snapshotLink.style.display = 'none';
            }
        }

        // Show/hide map div based on coordinates presence
        const mapWrap = document.getElementById('detail-drawer-map-wrap');
        const mapNone = document.getElementById('detail-drawer-map-none');
        
        const hasCheckinCoords = checkinLat && checkinLng && parseFloat(checkinLat) !== 0 && parseFloat(checkinLng) !== 0;
        const hasCheckoutCoords = checkoutLat && checkoutLng && parseFloat(checkoutLat) !== 0 && parseFloat(checkoutLng) !== 0;
        const hasLocationLogs = locationLogs && locationLogs.length > 0;
        const hasPunchCoords = hasCheckinCoords || hasCheckoutCoords || hasLocationLogs;

        if (hasPunchCoords) {
            mapWrap.style.display = 'block';
            mapNone.style.display = 'none';
        } else {
            mapWrap.style.display = 'none';
            mapNone.style.display = 'block';
        }

        // Telemetry Compliance Status (Pillar 1 & Pillar 3)
        if (teleCard) {
            if (hasPunchCoords) {
                teleCard.style.display = 'block';
                const hasGaps = !!(routeSummary && routeSummary.has_tracking_gaps && routeSummary.tracking_gaps && routeSummary.tracking_gaps.length > 0);
                const totalUntracked = routeSummary ? (routeSummary.total_untracked_minutes || 0) : 0;

                if (hasGaps) {
                    teleCard.style.background = '#fff5f5';
                    teleCard.style.borderColor = '#fecaca';
                    if (teleIcon) {
                        teleIcon.className = 'avatar-xs rounded-circle d-flex align-items-center justify-content-center bg-danger text-white';
                        teleIcon.innerHTML = '<i class="feather-alert-triangle fs-12"></i>';
                    }
                    if (teleTitle) {
                        teleTitle.className = 'fs-12 fw-bold text-danger';
                        teleTitle.textContent = 'Location Tracking Interrupted';
                    }
                    if (teleBadge) {
                        teleBadge.className = 'badge bg-danger text-white rounded-pill fs-10 fw-bold px-2 py-0.5';
                        teleBadge.textContent = totalUntracked + 'm Untracked';
                    }
                    if (teleDesc) {
                        teleDesc.className = 'fs-11 text-dark mb-0 ps-4';
                        teleDesc.textContent = 'GPS was disabled or signal was lost mid-shift. Compliance gap audit:';
                    }
                    if (teleGapList) {
                        teleGapList.style.display = 'block';
                        teleGapList.innerHTML = routeSummary.tracking_gaps.map(function(g) {
                            if (g.is_approved_break) {
                                return '<div class="d-flex align-items-center gap-1.5 fs-11 text-success py-0.5"><i class="feather-check-circle fs-11"></i> <strong>' + (g.duration_min || 0) + ' mins (Approved Break)</strong>: ' + (g.from_time || '') + ' &rarr; ' + (g.to_time || '') + '</div>';
                            }
                            return '<div class="d-flex align-items-center gap-1.5 fs-11 text-danger py-0.5"><i class="feather-clock fs-11"></i> <strong>' + (g.duration_min || 0) + ' mins offline (GPS Interrupted)</strong>: ' + (g.from_time || '') + ' &rarr; ' + (g.to_time || '') + '</div>';
                        }).join('');
                    }
                } else {
                    teleCard.style.background = '#f0fdf4';
                    teleCard.style.borderColor = '#bbf7d0';
                    if (teleIcon) {
                        teleIcon.className = 'avatar-xs rounded-circle d-flex align-items-center justify-content-center bg-success text-white';
                        teleIcon.innerHTML = '<i class="feather-check fs-12"></i>';
                    }
                    if (teleTitle) {
                        teleTitle.className = 'fs-12 fw-bold text-success';
                        teleTitle.textContent = 'Continuous Telemetry Verified';
                    }
                    if (teleBadge) {
                        teleBadge.className = 'badge bg-success text-white rounded-pill fs-10 fw-bold px-2 py-0.5';
                        teleBadge.textContent = '100% Compliant';
                    }
                    if (teleDesc) {
                        teleDesc.className = 'fs-11 text-muted mb-0 ps-4';
                        teleDesc.textContent = 'Location telemetry verified continuously with zero missing intervals.';
                    }
                    if (teleGapList) {
                        teleGapList.style.display = 'none';
                    }
                }
            } else {
                teleCard.style.display = 'none';
            }
        }

        // Show Drawer
        const drawerEl = document.getElementById('attendanceRecordDetailDrawer');
        if (drawerEl) {
            try {
                if (typeof bootstrap !== 'undefined' && bootstrap.Offcanvas) {
                    const bootstrapDrawer = bootstrap.Offcanvas.getOrCreateInstance(drawerEl);
                    bootstrapDrawer.show();
                } else if (typeof $ !== 'undefined' && typeof $(drawerEl).offcanvas === 'function') {
                    $(drawerEl).offcanvas('show');
                } else {
                    drawerEl.classList.add('show');
                }
            } catch (err) {
                console.error("Error opening drawer:", err);
            }
        }

        if (!window.initNominatimAutocomplete) {
            window.initNominatimAutocomplete = function(inputEl, onSelectCallback) {
                if (!inputEl) return;
                inputEl.setAttribute('autocomplete', 'off');

                let parent = inputEl.parentElement;
                if (!parent) return;

                if (window.getComputedStyle(parent).position === 'static') {
                    parent.style.position = 'relative';
                }

                let dropdown = parent.querySelector('.nominatim-autocomplete-dropdown');
                if (!dropdown) {
                    dropdown = document.createElement('div');
                    dropdown.className = 'nominatim-autocomplete-dropdown shadow border bg-white rounded-2 position-absolute';
                    parent.appendChild(dropdown);
                }

                const updateDropdownPosition = () => {
                    const topPos = (inputEl.offsetTop + inputEl.offsetHeight + 4);
                    const leftPos = inputEl.offsetLeft;
                    const widthPos = inputEl.offsetWidth;

                    dropdown.style.cssText = `position: absolute; top: ${topPos}px; left: ${leftPos}px; width: ${widthPos}px; max-height: 160px; overflow-y: auto; z-index: 99999; display: none; box-shadow: 0 4px 16px rgba(0,0,0,0.18) !important; background: #ffffff; border-radius: 6px;`;
                };

                let debounceTimer = null;
                let selectedIndex = -1;
                let currentResults = [];

                const closeDropdown = () => {
                    dropdown.style.display = 'none';
                    dropdown.innerHTML = '';
                    selectedIndex = -1;
                    currentResults = [];
                };

                const escapeHtml = (str) => {
                    if (!str) return '';
                    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
                };

                const renderResults = (results) => {
                    currentResults = Array.isArray(results) ? results : [];
                    dropdown.innerHTML = '';
                    selectedIndex = -1;
                    updateDropdownPosition();

                    if (!currentResults || currentResults.length === 0) {
                        dropdown.innerHTML = '<div class="p-2 text-muted fs-11 text-center bg-white">' + {!! json_encode(__('hrms.my_attendance.no_locations_found')) !!} + '</div>';
                        dropdown.style.display = 'block';
                        return;
                    }

                    currentResults.forEach((item, index) => {
                        const name = item.display_name || item.description || '';
                        const mainName = name.split(',')[0] || name;
                        const div = document.createElement('div');
                        div.className = 'p-2 border-bottom cursor-pointer nominatim-item text-truncate d-flex align-items-center gap-2';
                        div.style.cssText = 'cursor: pointer; transition: background 0.15s ease; border-color: #f1f5f9 !important; background: #ffffff; text-align: left;';
                        div.innerHTML = `<i class="feather-map-pin text-primary flex-shrink-0" style="font-size: 12px;"></i>
                                         <div class="text-truncate">
                                             <div class="fw-semibold text-dark text-truncate" style="font-size: 11px;">${escapeHtml(mainName)}</div>
                                             <div class="text-muted fs-10 text-truncate">${escapeHtml(name)}</div>
                                         </div>`;

                        div.addEventListener('mouseenter', () => { highlightItem(index); });
                        div.addEventListener('mousedown', (e) => { 
                            e.preventDefault();
                            selectItem(index); 
                        });
                        dropdown.appendChild(div);
                    });

                    dropdown.style.display = 'block';
                };

                const highlightItem = (index) => {
                    const items = dropdown.querySelectorAll('.nominatim-item');
                    items.forEach((el, idx) => {
                        el.style.backgroundColor = (idx === index) ? '#f8f9fa' : '#ffffff';
                    });
                    selectedIndex = index;
                };

                const selectItem = (index) => {
                    if (index >= 0 && index < currentResults.length) {
                        const item = currentResults[index];
                        const displayName = item.display_name || item.description || '';
                        inputEl.value = displayName;
                        closeDropdown();
                        if (typeof onSelectCallback === 'function') {
                            onSelectCallback({
                                lat: parseFloat(item.lat),
                                lng: parseFloat(item.lon || item.lng),
                                formatted_address: displayName
                            });
                        }
                    }
                };

                inputEl.addEventListener('input', function() {
                    const query = this.value.trim();
                    clearTimeout(debounceTimer);
                    if (query.length < 2) {
                        closeDropdown();
                        return;
                    }

                    debounceTimer = setTimeout(() => {
                        fetch('/api/maps/autocomplete?query=' + encodeURIComponent(query))
                            .then(res => res.json())
                            .then(data => {
                                if (data && data.success && Array.isArray(data.predictions) && data.predictions.length > 0) {
                                    renderResults(data.predictions);
                                } else {
                                    fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(query) + '&addressdetails=1&limit=8')
                                        .then(res => res.json())
                                        .then(osmData => renderResults(osmData))
                                        .catch(() => closeDropdown());
                                }
                            })
                            .catch(() => {
                                fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(query) + '&addressdetails=1&limit=8')
                                    .then(res => res.json())
                                    .then(osmData => renderResults(osmData))
                                    .catch(() => closeDropdown());
                            });
                    }, 300);
                });

                inputEl.addEventListener('keydown', function(e) {
                    if (dropdown.style.display === 'none' || currentResults.length === 0) return;
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        highlightItem((selectedIndex + 1) % currentResults.length);
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        highlightItem((selectedIndex - 1 + currentResults.length) % currentResults.length);
                    } else if (e.key === 'Enter') {
                        if (selectedIndex >= 0) {
                            e.preventDefault();
                            selectItem(selectedIndex);
                        }
                    } else if (e.key === 'Escape') {
                        closeDropdown();
                    }
                });

                document.addEventListener('click', function(e) {
                    if (!inputEl.contains(e.target) && !dropdown.contains(e.target)) {
                        closeDropdown();
                    }
                });
            };
        }

        // Render Leaflet map
        setTimeout(() => {
            if (hasCheckinCoords || hasCheckoutCoords || hasLocationLogs) {
                if (typeof L === 'undefined') {
                    return;
                }

                @if(!empty($officeLat) && !empty($officeLng))
                const initCenter = [{{ (float)$officeLat }}, {{ (float)$officeLng }}];
                @elseif(!empty($wfhLat) && !empty($wfhLng))
                const initCenter = [{{ (float)$wfhLat }}, {{ (float)$wfhLng }}];
                @else
                const initCenter = [20.5937, 78.9629];
                @endif

                const mapEl = document.getElementById('detail-drawer-map');
                if (!mapEl) return;

                // Initialize map if not yet initialized
                if (!detailDrawerMapObj || !mapEl._leaflet_id) {
                    if (detailDrawerMapObj) {
                        detailDrawerMapObj.remove();
                        detailDrawerMapObj = null;
                    }
                    mapEl.innerHTML = '';

                    detailDrawerMapObj = L.map('detail-drawer-map').setView(initCenter, 13);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap contributors'
                    }).addTo(detailDrawerMapObj);

                    // Nominatim Autocomplete search logic for details drawer map (Hybrid OpenStreetMap Search)
                    const searchInput = document.getElementById('detail_drawer_map_search');
                    if (searchInput && window.initNominatimAutocomplete) {
                        window.initNominatimAutocomplete(searchInput, function(place) {
                            const lat = parseFloat(place.lat);
                            const lng = parseFloat(place.lng);
                            detailDrawerMapObj.setView([lat, lng], 15);
                        });

                        searchInput.addEventListener('keypress', function(e) {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                            }
                        });
                    }
                } else {
                    clearDetailDrawerOverlays();
                }

                const pathLatLngs = [];

                const checkinLatVal = hasCheckinCoords ? parseFloat(checkinLat) : null;
                const checkinLngVal = hasCheckinCoords ? parseFloat(checkinLng) : null;
                const checkoutLatVal = hasCheckoutCoords ? parseFloat(checkoutLat) : null;
                const checkoutLngVal = hasCheckoutCoords ? parseFloat(checkoutLng) : null;

                // Detect if Check-In and Check-Out are in virtually the exact same location (< 35m)
                const isSameSpot = hasCheckinCoords && hasCheckoutCoords &&
                    (Math.abs(checkinLatVal - checkoutLatVal) < 0.00035) &&
                    (Math.abs(checkinLngVal - checkoutLngVal) < 0.00035);

                const greenIcon = L.divIcon({
                    className: 'custom-attendance-pin',
                    html: `<div style="background-color: #10b981; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; border: 2.5px solid #ffffff; box-shadow: 0 2px 8px rgba(0,0,0,0.3); font-weight: bold;"><i class="feather-log-in" style="font-size: 13px;"></i></div>`,
                    iconSize: [28, 28],
                    iconAnchor: isSameSpot ? [30, 14] : [14, 14],
                    popupAnchor: isSameSpot ? [-16, -14] : [0, -14]
                });

                const redIcon = L.divIcon({
                    className: 'custom-attendance-pin',
                    html: `<div style="background-color: #ef4444; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; border: 2.5px solid #ffffff; box-shadow: 0 2px 8px rgba(0,0,0,0.3); font-weight: bold;"><i class="feather-log-out" style="font-size: 13px;"></i></div>`,
                    iconSize: [28, 28],
                    iconAnchor: isSameSpot ? [-2, 14] : [14, 14],
                    popupAnchor: isSameSpot ? [16, -14] : [0, -14]
                });

                // Add Check-In Marker (Green)
                if (hasCheckinCoords) {
                    const checkinLatLng = [checkinLatVal, checkinLngVal];
                    pathLatLngs.push(checkinLatLng);
                    
                    const marker = L.marker(checkinLatLng, {
                        icon: greenIcon,
                        title: {!! json_encode(__('hrms.my_attendance.check_in_point')) !!},
                        zIndexOffset: 600
                    }).addTo(detailDrawerMapObj).bindPopup('<b>' + {!! json_encode(__('hrms.my_attendance.check_in_point')) !!} + '</b><br>' + {!! json_encode(__('hrms.my_attendance.lbl_time') . ': ') !!} + checkinTime + '<br>Lat: ' + checkinLatVal.toFixed(6) + '<br>Lng: ' + checkinLngVal.toFixed(6));
                    
                    detailDrawerOverlays.push(marker);
                }

                // Add Location tracking Logs
                if (hasLocationLogs) {
                    locationLogs.forEach(log => {
                        if (log.lat && log.lng) {
                            const latVal = parseFloat(log.lat);
                            const lngVal = parseFloat(log.lng);
                            const logLatLng = [latVal, lngVal];
                            pathLatLngs.push(logLatLng);

                            const circleMarker = L.circleMarker(logLatLng, {
                                radius: 5,
                                fillColor: '#3b82f6',
                                color: '#ffffff',
                                weight: 1.5,
                                fillOpacity: 0.85
                            }).addTo(detailDrawerMapObj).bindPopup('<b>' + {!! json_encode(__('hrms.my_attendance.location_tracking')) !!} + '</b><br>' + {!! json_encode(__('hrms.my_attendance.lbl_time') . ': ') !!} + (log.time || '') + '<br>Lat: ' + latVal.toFixed(6) + '<br>Lng: ' + lngVal.toFixed(6));
                            
                            detailDrawerOverlays.push(circleMarker);
                        }
                    });
                }

                // Add Check-Out Marker (Red)
                if (hasCheckoutCoords) {
                    const checkoutLatLng = [checkoutLatVal, checkoutLngVal];
                    pathLatLngs.push(checkoutLatLng);

                    const marker = L.marker(checkoutLatLng, {
                        icon: redIcon,
                        title: {!! json_encode(__('hrms.my_attendance.check_out_point')) !!},
                        zIndexOffset: 500
                    }).addTo(detailDrawerMapObj).bindPopup('<b>' + {!! json_encode(__('hrms.my_attendance.check_out_point')) !!} + '</b><br>' + {!! json_encode(__('hrms.my_attendance.lbl_time') . ': ') !!} + checkoutTime + '<br>Lat: ' + checkoutLatVal.toFixed(6) + '<br>Lng: ' + checkoutLngVal.toFixed(6));
                    
                    detailDrawerOverlays.push(marker);
                }

                // Add Polyline connecting all tracking path points (Vibrant Route Path with Pillar 1 Gap Highlighting)
                if (pathLatLngs.length >= 2) {
                    const trackingGaps = (routeSummary && routeSummary.tracking_gaps) ? routeSummary.tracking_gaps : [];
                    const gapMap = {};
                    trackingGaps.forEach(function(g) {
                        if (typeof g.from_index !== 'undefined') {
                            gapMap[parseInt(g.from_index)] = g;
                        }
                    });

                    for (let i = 0; i < pathLatLngs.length - 1; i++) {
                        const p1 = pathLatLngs[i];
                        const p2 = pathLatLngs[i + 1];

                        let gapInfo = gapMap[i];
                        if (!gapInfo && trackingGaps.length > 0) {
                            gapInfo = trackingGaps.find(function(g) {
                                return Math.abs(parseFloat(g.from_lat) - p1[0]) < 0.0001 &&
                                       Math.abs(parseFloat(g.from_lng) - p1[1]) < 0.0001;
                            });
                        }

                        if (gapInfo) {
                            const isBreak = !!gapInfo.is_approved_break;
                            const lineColor = isBreak ? '#f59e0b' : '#ef4444';
                            const badgeBg = isBreak ? '#f59e0b' : '#ef4444';
                            const badgeIcon = isBreak ? 'feather-pause' : 'feather-alert-triangle';
                            const badgeText = isBreak ? `${gapInfo.duration_min || 0}m Break` : `${gapInfo.duration_min || 0}m GPS Off`;
                            const popupTitle = isBreak ? '<b>✓ Authorized Break Window</b>' : '<b>⚠️ Location Tracking Gap</b>';

                            // Render Polyline (Amber dashed for break, Red dashed for offline)
                            const gapPolyline = L.polyline([p1, p2], {
                                color: lineColor,
                                weight: 4.5,
                                opacity: 0.95,
                                dashArray: isBreak ? '6, 6' : '8, 8',
                                lineCap: 'round',
                                lineJoin: 'round'
                            }).addTo(detailDrawerMapObj);
                            detailDrawerOverlays.push(gapPolyline);

                            // Midpoint Marker
                            const midLat = (p1[0] + p2[0]) / 2;
                            const midLng = (p1[1] + p2[1]) / 2;
                            const gapIcon = L.divIcon({
                                className: 'custom-gap-marker',
                                html: `<div style="background: ${badgeBg}; color: #ffffff; border: 2px solid #ffffff; border-radius: 9999px; padding: 2px 7px; font-size: 10px; font-weight: 700; white-space: nowrap; box-shadow: 0 2px 6px rgba(0,0,0,0.25); display: flex; align-items: center; gap: 4px; cursor: pointer;"><i class="${badgeIcon}" style="font-size: 10px;"></i> ${badgeText}</div>`,
                                iconAnchor: [38, 12]
                            });
                            const gapMarker = L.marker([midLat, midLng], { icon: gapIcon, zIndexOffset: 700 })
                                .addTo(detailDrawerMapObj)
                                .bindPopup(`${popupTitle}<br>Duration: ${gapInfo.duration_min || 0} mins<br>From: ${gapInfo.from_time || ''} &rarr; To: ${gapInfo.to_time || ''}`);
                            detailDrawerOverlays.push(gapMarker);
                        } else {
                            // Normal verified continuous path segment
                            const segmentGlow = L.polyline([p1, p2], {
                                color: '#6366f1',
                                weight: 8,
                                opacity: 0.35,
                                lineCap: 'round',
                                lineJoin: 'round'
                            }).addTo(detailDrawerMapObj);
                            detailDrawerOverlays.push(segmentGlow);

                            const segmentLine = L.polyline([p1, p2], {
                                color: '#3b82f6',
                                weight: 4.5,
                                opacity: 0.95,
                                lineCap: 'round',
                                lineJoin: 'round'
                            }).addTo(detailDrawerMapObj);
                            detailDrawerOverlays.push(segmentLine);
                        }
                    }
                }

                // Draw geofence threshold radius circles (informative background overlays)
                @if(!empty($officeLat) && !empty($officeLng) && !empty($officeRad))
                const officePos = [{{ (float)$officeLat }}, {{ (float)$officeLng }}];
                const officeCircle = L.circle(officePos, {
                    color: '#4f46e5',
                    weight: 2,
                    fillColor: '#4f46e5',
                    fillOpacity: 0.08,
                    radius: {{ (int)$officeRad }}
                }).addTo(detailDrawerMapObj);
                detailDrawerOverlays.push(officeCircle);
                @endif

                @if(!empty($wfhLat) && !empty($wfhLng) && !empty($wfhRad))
                const wfhPos = [{{ (float)$wfhLat }}, {{ (float)$wfhLng }}];
                const wfhCircle = L.circle(wfhPos, {
                    color: '#10b981',
                    weight: 2,
                    fillColor: '#10b981',
                    fillOpacity: 0.08,
                    radius: {{ (int)$wfhRad }}
                }).addTo(detailDrawerMapObj);
                detailDrawerOverlays.push(wfhCircle);
                @endif

                // Focus and Zoom map strictly on ACTUAL SESSION POINTS
                window.lastDrawerPathPoints = pathLatLngs;
                detailDrawerMapObj.invalidateSize();
                if (pathLatLngs.length > 0) {
                    const bounds = L.latLngBounds(pathLatLngs);
                    const sw = bounds.getSouthWest();
                    const ne = bounds.getNorthEast();
                    const latSpan = Math.abs(ne.lat - sw.lat);
                    const lngSpan = Math.abs(ne.lng - sw.lng);

                    // If single spot or walking distance (< 300m), zoom in to neighborhood street level (zoom 16)
                    if (latSpan < 0.003 && lngSpan < 0.003) {
                        detailDrawerMapObj.setView(bounds.getCenter(), 16);
                    } else {
                        detailDrawerMapObj.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 });
                    }
                } else {
                    detailDrawerMapObj.setView(initCenter, 15);
                }
            }
        }, 300);
    }

    // Invalidate Leaflet size automatically once offcanvas finish slide animation
    const detailDrawerEl = document.getElementById('attendanceRecordDetailDrawer');
    if (detailDrawerEl) {
        detailDrawerEl.addEventListener('shown.bs.offcanvas', function() {
            if (detailDrawerMapObj) {
                detailDrawerMapObj.invalidateSize();
                if (typeof window.lastDrawerPathPoints !== 'undefined' && window.lastDrawerPathPoints.length > 0) {
                    const bounds = L.latLngBounds(window.lastDrawerPathPoints);
                    const sw = bounds.getSouthWest();
                    const ne = bounds.getNorthEast();
                    if (Math.abs(ne.lat - sw.lat) < 0.003 && Math.abs(ne.lng - sw.lng) < 0.003) {
                        detailDrawerMapObj.setView(bounds.getCenter(), 16);
                    } else {
                        detailDrawerMapObj.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 });
                    }
                }
            }
        });
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

        const modalEl = document.getElementById('requestCorrectionModal');
        if (modalEl && modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
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

    window.openCorrectionModal = function() {
        const dateInput = document.getElementById('correction_date_val');
        if (dateInput) dateInput.value = '';
        const checkinInput = document.getElementById('correction_check_in');
        if (checkinInput) checkinInput.value = '';
        const checkoutInput = document.getElementById('correction_check_out');
        if (checkoutInput) checkoutInput.value = '';
        const reasonInput = document.getElementById('correction_reason');
        if (reasonInput) reasonInput.value = '';

        const modalEl = document.getElementById('requestCorrectionModal');
        if (modalEl) {
            if (modalEl.parentElement !== document.body) {
                document.body.appendChild(modalEl);
            }
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            } else if (typeof $ !== 'undefined') {
                $('#requestCorrectionModal').modal('show');
            }
        }
    };
</script>
@endpush
@endsection
