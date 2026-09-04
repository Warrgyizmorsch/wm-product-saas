@extends('layouts.duralux')

@section('title', 'HRMS Executive & Role Dashboard | HRMS')
@section('page-title', 'HRMS Executive Dashboard')
@section('breadcrumb', 'HRMS / Dashboard')

@php
    $rawRole = auth()->user()?->role;
    $roleDisplayName = is_object($rawRole) ? ($rawRole->name ?? 'Company Admin') : ($rawRole ?? 'Company Admin');
    $roleDisplayName = ucwords(str_replace(['_', '-'], ' ', (string) $roleDisplayName));
@endphp

@push('styles')
<style>
    .avatar-initials-dash {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 15px;
    }
    .action-inbox-pills {
        background: #f1f5f9;
        padding: 3px;
        border-radius: 8px;
    }
    .action-inbox-pills .nav-link {
        border-radius: 6px;
        color: #64748b;
        font-weight: 600;
        font-size: 12px;
        padding: 5px 12px;
        background: transparent;
        border: none;
        transition: all 0.15s ease-in-out;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .action-inbox-pills .nav-link:hover {
        color: #1e293b;
        background: rgba(255, 255, 255, 0.6);
    }
    .action-inbox-pills .nav-link.active {
        background: #ffffff !important;
        color: #1e293b !important;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    }
    .action-inbox-pills .nav-link .tab-count-badge {
        font-size: 11px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 10px;
        line-height: 1.2;
    }
    .day-strip-box {
        flex: 1;
        border-radius: 8px;
        padding: 8px 4px;
        text-align: center;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        transition: all 0.15s ease-in-out;
    }
    .day-strip-box:hover {
        background: #f1f5f9;
    }
    .day-strip-box.is-today {
        background: #eff6ff;
        border-color: #93c5fd;
    }
    .dash-card-icon-avatar {
        width: 28px;
        height: 28px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        flex-shrink: 0;
    }
</style>
@endpush

@section('page-actions')
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="{{ route('hrms.employees.index') }}" class="btn btn-sm btn-primary fw-semibold shadow-sm d-inline-flex align-items-center gap-1.5" style="background-color: #1c3faa; border-color: #1c3faa;">
            <i class="feather-user-plus fs-14"></i> Add Employee
        </a>
    </div>
@endsection

@section('content')

    <!-- Flash Messages -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4 text-dark" role="alert">
            <i class="feather-check-circle me-2 text-success"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4 text-dark" role="alert">
            <i class="feather-alert-circle me-2 text-danger"></i>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- 1. Top Executive Welcome Card -->
    <div class="card border-0 shadow-sm mb-3.5">
        <div class="card-body p-4 px-4">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <div class="d-flex align-items-center gap-4">
                        <div class="avatar-initials-dash bg-soft-primary text-primary shadow-sm border border-primary border-opacity-10 fw-bolder fs-16 flex-shrink-0" style="width: 56px; height: 56px; border-radius: 50%;">
                            {{ strtoupper(substr(auth()->user()?->name ?? ($currentEmployee?->full_name ?? 'AD'), 0, 2)) }}
                        </div>
                        <div>
                            @php
                                $hour = date('H');
                                $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
                            @endphp
                            <div class="d-flex align-items-center gap-3 flex-wrap mb-2">
                                <h5 class="fw-bold mb-0 text-dark fs-18" style="line-height: 1.3;">{{ $greeting }}, {{ auth()->user()?->name ?? ($currentEmployee?->full_name ?? 'Rahul Sharma') }}! 👋</h5>
                                <span class="badge bg-soft-primary text-primary px-2.5 py-1 fs-11 fw-bold rounded-pill border border-primary border-opacity-20 d-inline-flex align-items-center gap-1.5">
                                    <i class="feather-shield text-primary"></i> Role: {{ $roleDisplayName }}
                                </span>
                            </div>
                            <div class="d-flex align-items-center gap-3 flex-wrap fs-12 text-muted">
                                <span class="fw-semibold text-secondary d-inline-flex align-items-center gap-1.5">
                                    <i class="feather-briefcase text-muted"></i>{{ $currentEmployee ? ($currentEmployee->designation->name ?? 'Staff') : 'Engineering' }}
                                </span>
                                <span class="text-muted opacity-50">&bull;</span>
                                <span class="d-inline-flex align-items-center gap-1.5">
                                    <i class="feather-layers text-muted"></i>{{ $currentEmployee ? ($currentEmployee->department->name ?? 'Engineering Department') : 'Operations' }}
                                </span>
                                <span class="text-muted opacity-50">&bull;</span>
                                <span class="text-dark d-inline-flex align-items-center gap-1.5">
                                    <i class="feather-calendar text-primary"></i>{{ date('l, d F Y') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
                    @php
                        $myProfileUrl = $currentEmployee ? route('hrms.employees.show', $currentEmployee->id) : route('hrms.employees.index');
                    @endphp
                    <div class="d-inline-flex align-items-center justify-content-end gap-3.5 text-start">
                        <div>
                            <span class="fs-10 text-uppercase fw-bold text-muted d-block tracking-wide mb-0.5">Profile & KYC</span>
                            <span class="fw-bolder text-dark fs-15 d-block mb-1" style="line-height: 1.2;">{{ $profileCompletion }}% Completed</span>
                            <a href="{{ $myProfileUrl }}" class="fs-11 text-primary fw-bold text-decoration-none d-inline-flex align-items-center gap-1">
                                Update Details <i class="feather-arrow-right fs-11"></i>
                            </a>
                        </div>
                        <a href="{{ $myProfileUrl }}" class="position-relative d-flex align-items-center justify-content-center flex-shrink-0" style="width: 58px; height: 58px;" title="Click to view & update Profile / KYC">
                            <svg width="58" height="58" viewBox="0 0 58 58" style="transform: rotate(-90deg);">
                                <circle cx="29" cy="29" r="23" fill="none" stroke="#e2e8f0" stroke-width="4.5"></circle>
                                <circle cx="29" cy="29" r="23" fill="none" stroke="url(#dashProfileGrad)" stroke-width="4.5" stroke-dasharray="144.5" stroke-dashoffset="{{ round(144.5 - (144.5 * $profileCompletion / 100)) }}" stroke-linecap="round"></circle>
                                <defs>
                                    <linearGradient id="dashProfileGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#1c3faa" />
                                        <stop offset="100%" stop-color="#3b82f6" />
                                    </linearGradient>
                                </defs>
                            </svg>
                            <div class="position-absolute text-center">
                                <i class="feather-user-check text-primary fs-5"></i>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Row of 4 Hero KPI Cards using Standard Design Tokens -->
    <div class="row g-3 mb-3">
        <!-- KPI 1: Workforce -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-3">
                            <i class="feather-users fs-5"></i>
                        </div>
                        <span class="badge bg-soft-primary text-primary fw-bold px-2 py-0.5 fs-11">Workforce</span>
                    </div>
                    <span class="fs-11 text-uppercase text-muted fw-bold d-block">Total Employees</span>
                    <h3 class="fw-bolder text-dark mb-0 fs-22">{{ $totalEmployees }}</h3>
                    <div class="row g-0 pt-2 mt-2 border-top text-center">
                        <div class="col-4 border-end pe-1">
                            <span class="text-muted d-block fs-9 text-uppercase fw-semibold">Confirmed</span>
                            <strong class="text-success fs-12">{{ $confirmedCount }}</strong>
                        </div>
                        <div class="col-4 border-end px-1">
                            <span class="text-muted d-block fs-9 text-uppercase fw-semibold">Probation</span>
                            <strong class="text-warning fs-12">{{ $probationCount }}</strong>
                        </div>
                        <div class="col-4 ps-1">
                            <span class="text-muted d-block fs-9 text-uppercase fw-semibold">Notice</span>
                            <strong class="text-danger fs-12">{{ $noticeCount }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 2: Today's Attendance -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="avatar-text avatar-md bg-soft-success text-success rounded-3">
                            <i class="feather-check-circle fs-5"></i>
                        </div>
                        <span class="badge bg-soft-success text-success fw-bold px-2 py-0.5 fs-11">{{ $attendancePercent }}% Rate</span>
                    </div>
                    <span class="fs-11 text-uppercase text-muted fw-bold d-block">Today's Attendance</span>
                    <div class="d-flex align-items-baseline gap-1.5 mb-0">
                        <h3 class="fw-bolder text-dark mb-0 fs-22">{{ $presentCount }}</h3>
                        <span class="text-muted fs-12 fw-normal">/ {{ $totalEmployees }} Present</span>
                    </div>
                    <div class="row g-0 pt-2 mt-2 border-top text-center">
                        <div class="col-4 border-end pe-1">
                            <span class="text-muted d-block fs-9 text-uppercase fw-semibold">WFH</span>
                            <strong class="text-info fs-12">{{ $wfhCount }}</strong>
                        </div>
                        <div class="col-4 border-end px-1">
                            <span class="text-muted d-block fs-9 text-uppercase fw-semibold">Late</span>
                            <strong class="text-warning fs-12">{{ $lateCount }}</strong>
                        </div>
                        <div class="col-4 ps-1">
                            <span class="text-muted d-block fs-9 text-uppercase fw-semibold">On Leave</span>
                            <strong class="text-danger fs-12">{{ $onLeaveCount }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 3: Action Center (Pending Inboxes) -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="avatar-text avatar-md bg-soft-warning text-warning rounded-3">
                            <i class="feather-inbox fs-5"></i>
                        </div>
                        <span class="badge bg-soft-{{ $totalPendingApprovals > 0 ? 'warning' : 'success' }} text-{{ $totalPendingApprovals > 0 ? 'warning' : 'success' }} fw-bold px-2 py-0.5 fs-11">
                            {{ $totalPendingApprovals > 0 ? 'Action Required' : 'All Cleared' }}
                        </span>
                    </div>
                    <span class="fs-11 text-uppercase text-muted fw-bold d-block">Pending Approvals</span>
                    <h3 class="fw-bolder text-dark mb-0 fs-22">{{ $totalPendingApprovals }}</h3>
                    <div class="row g-0 pt-2 mt-2 border-top text-center">
                        <div class="col-4 border-end pe-1">
                            <span class="text-muted d-block fs-9 text-uppercase fw-semibold">Leaves</span>
                            <strong class="text-primary fs-12">{{ $pendingLeaves->count() }}</strong>
                        </div>
                        <div class="col-4 border-end px-1">
                            <span class="text-muted d-block fs-9 text-uppercase fw-semibold">WFH</span>
                            <strong class="text-info fs-12">{{ $pendingWfh->count() }}</strong>
                        </div>
                        <div class="col-4 ps-1">
                            <span class="text-muted d-block fs-9 text-uppercase fw-semibold">Punches</span>
                            <strong class="text-warning fs-12">{{ $pendingCorrections->count() }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI 4: LifeCycle & Offboarding -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="avatar-text avatar-md bg-soft-info text-info rounded-3">
                            <i class="feather-activity fs-5"></i>
                        </div>
                        <span class="badge bg-soft-info text-info fw-bold px-2 py-0.5 fs-11">Lifecycle</span>
                    </div>
                    <span class="fs-11 text-uppercase text-muted fw-bold d-block">Probation & Exits</span>
                    <h3 class="fw-bolder text-dark mb-0 fs-22">{{ $upcomingProbationEmployees->count() + $activeExits->count() }}</h3>
                    <div class="row g-0 pt-2 mt-2 border-top text-center">
                        <div class="col-6 border-end pe-1">
                            <span class="text-muted d-block fs-9 text-uppercase fw-semibold">Reviews Due</span>
                            <strong class="text-warning fs-12">{{ $upcomingProbationEmployees->count() }}</strong>
                        </div>
                        <div class="col-6 ps-1">
                            <span class="text-muted d-block fs-9 text-uppercase fw-semibold">Active Exits</span>
                            <strong class="text-danger fs-12">{{ $activeExits->count() }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Main 12-Column Dashboard Grid -->
    <div class="row g-3">
        
        <!-- ── MAIN COLUMN (8 COLS) ── -->
        <div class="col-lg-8">
            
            <!-- Widget 1: Real-Time Web Attendance Punch Hub (Light Theme Aligned) -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-soft-primary text-primary fs-11 fw-bold px-3 py-1.5 rounded-pill d-inline-flex align-items-center gap-2" style="line-height: 1.2;">
                                <i class="feather-clock fs-12" style="margin-right: 2px;"></i>
                                <span>Web Punch Station</span>
                            </span>
                            <span class="text-muted fs-12">&bull; General Shift (09:30 AM - 06:30 PM)</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <a href="{{ route('hrms.wfh.index') }}" class="btn btn-sm btn-light border text-dark fw-semibold shadow-sm d-inline-flex align-items-center py-1 px-2.5 fs-11">
                                <i class="feather-home text-info fs-12 me-2"></i>
                                <span>Request WFH</span>
                            </a>
                            <a href="{{ route('hrms.attendance.myAttendance') }}" class="btn btn-sm btn-light border text-dark fw-semibold shadow-sm d-inline-flex align-items-center py-1 px-2.5 fs-11">
                                <i class="feather-clock text-warning fs-12 me-2"></i>
                                <span>Regularize Punch</span>
                            </a>
                        </div>
                    </div>

                    <div class="row align-items-center">
                        <div class="col-lg-7">
                            <div class="d-flex align-items-center gap-3 mb-2 flex-wrap">
                                <div class="fw-bolder text-dark" id="liveDigitalClock" style="font-size: 32px; letter-spacing: -0.5px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1;">
                                    {{ date('h:i:s A') }}
                                </div>
                                <div class="badge bg-soft-secondary text-dark fs-12 fw-normal px-2.5 py-1 d-inline-flex align-items-center rounded-2">
                                    <i class="feather-calendar text-primary fs-12 me-1.5"></i>
                                    <span>{{ date('l, d F Y') }}</span>
                                </div>
                            </div>

                            @if($myTodayAttendance && $myTodayAttendance->check_in)
                                <div class="d-flex align-items-center gap-2 fs-13 flex-wrap">
                                    <span class="badge bg-soft-success text-success px-3 py-1.5 rounded-pill fw-bold d-inline-flex align-items-center">
                                        <i class="feather-check-circle fs-13 me-1.5"></i>
                                        <span>Clocked In</span>
                                    </span>
                                    <span class="text-muted">In at</span>
                                    <strong class="text-dark">{{ \Carbon\Carbon::parse($myTodayAttendance->check_in)->format('h:i A') }}</strong>
                                    @if($myTodayAttendance->check_out)
                                        <span class="text-muted">&bull; Out at</span>
                                        <strong class="text-dark">{{ \Carbon\Carbon::parse($myTodayAttendance->check_out)->format('h:i A') }}</strong>
                                        <span class="badge bg-soft-info text-info fw-bold">({{ $myTodayAttendance->total_work_hours }} hrs)</span>
                                    @endif
                                </div>
                            @else
                                <div class="text-warning fs-13 fw-semibold d-inline-flex align-items-center">
                                    <i class="feather-alert-triangle fs-13 me-1.5"></i>
                                    <span>You have not clocked in yet today.</span>
                                </div>
                            @endif
                        </div>

                        <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
                            <form method="POST" action="{{ route('hrms.dashboard.punch') }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="employee_id" value="{{ $currentEmployee->id ?? '' }}">

                                @if(!$myTodayAttendance || !$myTodayAttendance->check_in)
                                    <div class="d-flex flex-column align-items-lg-end gap-2">
                                        <div class="d-flex gap-2 align-items-center justify-content-lg-end">
                                            <input type="hidden" name="location_type" value="office">
                                            <button type="submit" name="action" value="in" class="btn btn-success fw-bold px-4 d-inline-flex align-items-center shadow-sm" style="height: 38px;">
                                                <i class="feather-log-in me-2"></i> Web Clock-In
                                            </button>
                                        </div>
                                        <span class="text-muted fs-11">Click to record your check-in timestamp</span>
                                    </div>
                                @elseif($myTodayAttendance && !$myTodayAttendance->check_out)
                                    <div class="d-flex flex-wrap gap-2 justify-content-lg-end align-items-center">
                                        @if($myTodayAttendance->isOnBreak())
                                            <button type="submit" name="action" value="break_in" class="btn btn-warning text-dark fw-bold px-3 d-inline-flex align-items-center shadow-sm" style="height: 38px;">
                                                <i class="feather-play me-2"></i> Resume Work
                                            </button>
                                        @else
                                            <button type="submit" name="action" value="break_out" class="btn btn-light border text-dark fw-semibold px-3 d-inline-flex align-items-center shadow-sm" style="height: 38px;">
                                                <i class="feather-coffee text-warning me-2"></i> Take Break
                                            </button>
                                        @endif
                                        
                                        <button type="button" class="btn btn-danger fw-bold px-4 d-inline-flex align-items-center shadow-sm" style="height: 38px;" data-bs-toggle="modal" data-bs-target="#confirmClockOutModal">
                                            <i class="feather-log-out me-2"></i> Clock-Out
                                        </button>
                                    </div>
                                @else
                                    <div class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded-2 bg-soft-secondary text-muted fs-12 fw-semibold border">
                                        <i class="feather-check-circle text-success fs-14 me-1"></i> Shift finalized for today.
                                    </div>
                                @endif
                            </form>
                        </div>
                    </div>

                    <!-- 7-Day Mini Attendance Strip -->
                    <div class="mt-3 pt-2.5 border-top">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted fs-11 text-uppercase fw-bold">Recent 7 Days Attendance Log</span>
                            <a href="{{ route('hrms.attendance.myAttendance') }}" class="text-primary fs-11 fw-semibold text-decoration-none">Full Attendance Log &rarr;</a>
                        </div>
                        <div class="d-flex gap-2">
                            @foreach($recentPunches as $rp)
                                <div class="day-strip-box {{ $rp['is_today'] ? 'is-today shadow-sm' : '' }}">
                                    <div class="text-muted fs-10 fw-bold text-uppercase">{{ $rp['day_name'] }}</div>
                                    <div class="fw-bolder text-dark fs-14 my-1">{{ $rp['day_num'] }}</div>
                                    <div>
                                        @if($rp['status'] === 'present')
                                            <span class="badge bg-soft-success text-success fs-9 px-2 py-0.5 fw-bold">Present</span>
                                        @elseif($rp['status'] === 'late')
                                            <span class="badge bg-soft-warning text-warning fs-9 px-2 py-0.5 fw-bold">Late</span>
                                        @elseif($rp['status'] === 'wfh')
                                            <span class="badge bg-soft-info text-info fs-9 px-2 py-0.5 fw-bold">WFH</span>
                                        @elseif($rp['status'] === 'off')
                                            <span class="badge bg-soft-secondary text-muted fs-9 px-2 py-0.5">Off</span>
                                        @elseif($rp['status'] === 'absent')
                                            <span class="badge bg-soft-danger text-danger fs-9 px-2 py-0.5 fw-bold">Absent</span>
                                        @else
                                            <span class="badge bg-light text-muted fs-9 px-2 py-0.5">—</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Widget 2: Unified Action Center (Tabbed Inbox) -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header border-bottom p-2.5 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="dash-card-icon-avatar bg-soft-primary text-primary">
                            <i class="feather-inbox"></i>
                        </div>
                        <h6 class="card-title fw-bold text-dark mb-0 fs-13">Pending Approvals Action Center</h6>
                    </div>
                    <ul class="nav nav-pills action-inbox-pills" id="inboxTabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-leaves" type="button">
                                Leaves <span class="tab-count-badge bg-soft-primary text-primary">{{ $pendingLeaves->count() }}</span>
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-wfh" type="button">
                                WFH <span class="tab-count-badge bg-soft-info text-info">{{ $pendingWfh->count() }}</span>
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-punches" type="button">
                                Punches <span class="tab-count-badge bg-soft-warning text-warning">{{ $pendingCorrections->count() }}</span>
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-expenses" type="button">
                                Expenses <span class="tab-count-badge bg-soft-success text-success">{{ $pendingExpenses->count() }}</span>
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="card-body p-0">
                    <div class="tab-content">
                        <!-- Tab 1: Leaves -->
                        <div class="tab-pane fade show active" id="tab-leaves">
                            <div class="table-responsive" style="min-height: 299px;">
                                <table class="table table-hover align-middle mb-0 fs-12">
                                    <thead class="bg-light text-muted fs-11 text-uppercase">
                                        <tr>
                                            <th class="ps-3 py-2.5" style="width: 20%;">Employee</th>
                                            <th class="py-2.5" style="width: 22%;">Leave Type</th>
                                            <th class="py-2.5" style="width: 20%;">Duration & Timeline</th>
                                            <th class="text-center py-2.5" style="width: 8%;">Days</th>
                                            <th class="py-2.5" style="width: 15%;">Reason</th>
                                            <th class="text-end pe-3 py-2.5" style="width: 15%;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $leaveChunks = $pendingLeaves->chunk(5); @endphp
                                        @forelse($leaveChunks as $pageIndex => $chunk)
                                            @php $pageNo = $pageIndex + 1; @endphp
                                            @foreach($chunk as $leave)
                                                @php
                                                    $sDate = \Carbon\Carbon::parse($leave->start_date);
                                                    $eDate = \Carbon\Carbon::parse($leave->end_date);
                                                    $sameYear = $sDate->format('Y') === $eDate->format('Y');
                                                    $dateRange = $sDate->isSameDay($eDate) 
                                                        ? $sDate->format('d M Y') 
                                                        : $sDate->format($sameYear ? 'd M' : 'd M Y') . ' – ' . $eDate->format('d M Y');
                                                    
                                                    $numDays = $leave->duration ? floatval($leave->duration) : ($sDate->diffInDays($eDate) + 1);
                                                    $lTypeName = $leave->leaveType->name ?? (ucfirst($leave->leave_type ?? 'Casual Leave'));
                                                    $lColor = $leave->leaveType->color ?? '#3b82f6';
                                                    
                                                    $lBalance = \App\Domains\HRMS\Models\LeaveBalance::where('employee_id', $leave->employee_id)
                                                        ->where('leave_type_id', $leave->leave_type_id)
                                                        ->first();
                                                    $remDays = $lBalance ? floatval($lBalance->remaining) : 0;
                                                    $quotaDays = $lBalance ? floatval($lBalance->allocated) : ($leave->leaveType ? floatval($leave->leaveType->quota) : 0);
                                                @endphp
                                                <tr class="tab-leave-row tab-leave-page-{{ $pageNo }} {{ $pageNo > 1 ? 'd-none' : '' }}">
                                                    <td class="ps-3">
                                                        <div class="fw-bold text-dark fs-12">{{ $leave->employee->full_name ?? 'Employee' }}</div>
                                                        <div class="text-muted fs-11">{{ $leave->employee->employee_id ?? ($leave->employee->department->name ?? '') }}</div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <span class="rounded-circle flex-shrink-0" style="width: 8px; height: 8px; background: {{ $lColor }};"></span>
                                                            <span class="fw-bold text-dark fs-12">{{ $lTypeName }}</span>
                                                        </div>
                                                        @if($quotaDays > 0)
                                                            <div class="text-muted fs-11 mt-0.5" style="padding-left: 16px;">Rem: {{ $remDays }} / {{ $quotaDays }} Days</div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="fw-semibold text-dark fs-12">{{ $dateRange }}</span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-soft-primary text-primary fs-11 fw-bold px-2 py-0.5">{{ $numDays }}</span>
                                                    </td>
                                                    <td class="text-muted text-truncate" style="max-width: 140px;" title="{{ $leave->reason }}">{{ $leave->reason ?: '—' }}</td>
                                                    <td class="text-end pe-3">
                                                        <div class="d-inline-flex align-items-center justify-content-end gap-2.5">
                                                            <a href="{{ route('hrms.leaves.index') }}" class="btn btn-sm btn-light border p-0 d-inline-flex align-items-center justify-content-center text-muted rounded-circle" style="width: 28px; height: 28px;" title="View Details in Leave Module">
                                                                <i class="feather-eye fs-13"></i>
                                                            </a>
                                                            <div class="dropdown {{ ($loop->last || ($loop->count > 1 && $loop->iteration >= $loop->count - 1)) ? 'dropup' : '' }} d-inline-block position-relative">
                                                                <button class="btn btn-sm bg-soft-warning text-warning border-0 py-1.5 px-3 rounded-pill fw-bold fs-11 dropdown-toggle d-inline-flex align-items-center gap-1 shadow-2xs" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" title="Change Leave Status">
                                                                    <span><i class="feather-clock me-1"></i> Pending</span>
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 p-1.5 mt-1 fs-12" style="min-width: 135px; border-radius: 8px; z-index: 1050;">
                                                                    <li>
                                                                        <form action="{{ route('hrms.leaves.approve', $leave->id) }}" method="POST">
                                                                            @csrf
                                                                            <button type="submit" class="dropdown-item rounded py-1.5 px-2.5 text-success fw-medium d-flex align-items-center gap-2">
                                                                                <i class="feather-check-circle text-success fs-13"></i> Approve
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                    <li>
                                                                        <form action="{{ route('hrms.leaves.reject', $leave->id) }}" method="POST">
                                                                            @csrf
                                                                            <button type="submit" class="dropdown-item rounded py-1.5 px-2.5 text-danger fw-medium d-flex align-items-center gap-2">
                                                                                <i class="feather-x-circle text-danger fs-13"></i> Reject
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                    <li>
                                                                        <form action="{{ route('hrms.leaves.update-status', $leave->id) }}" method="POST">
                                                                            @csrf
                                                                            <input type="hidden" name="action" value="unpaid">
                                                                            <button type="submit" class="dropdown-item rounded py-1.5 px-2.5 text-info fw-medium d-flex align-items-center gap-2">
                                                                                <i class="feather-alert-circle text-info fs-13"></i> Unpaid
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center align-middle text-muted fs-12" style="height: 265px;">
                                                    <div>
                                                        <i class="feather-check-circle text-success fs-3 mb-2 d-block opacity-75"></i>
                                                        <div>No pending leave requests.</div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @php $totalLeavePages = ceil($pendingLeaves->count() / 5); @endphp
                            @if($totalLeavePages > 1)
                                <div class="card-footer bg-light p-2 px-3 d-flex justify-content-between align-items-center border-top">
                                    <span class="text-muted fs-11" id="leavePageIndicator">Page 1 of {{ $totalLeavePages }}</span>
                                    <div class="d-flex align-items-center gap-1">
                                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" id="leavePrevBtn" disabled onclick="changeTabActionPage('leave', -1, {{ $totalLeavePages }})">
                                            <i class="feather-chevron-left fs-12"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" id="leaveNextBtn" onclick="changeTabActionPage('leave', 1, {{ $totalLeavePages }})">
                                            <i class="feather-chevron-right fs-12"></i>
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Tab 2: WFH -->
                        <div class="tab-pane fade" id="tab-wfh">
                            <div class="table-responsive" style="min-height: 299px;">
                                <table class="table table-hover align-middle mb-0 fs-12">
                                    <thead class="bg-light text-muted fs-11 text-uppercase">
                                        <tr>
                                            <th class="ps-3 py-2.5">Employee</th>
                                            <th class="py-2.5">Period</th>
                                            <th class="py-2.5">Reason</th>
                                            <th class="text-end pe-3 py-2.5">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $wfhChunks = $pendingWfh->chunk(5); @endphp
                                        @forelse($wfhChunks as $pageIndex => $chunk)
                                            @php $pageNo = $pageIndex + 1; @endphp
                                            @foreach($chunk as $wfh)
                                                <tr class="tab-wfh-row tab-wfh-page-{{ $pageNo }} {{ $pageNo > 1 ? 'd-none' : '' }}">
                                                    <td class="ps-3">
                                                        <div class="fw-bold text-dark">{{ $wfh->employee->full_name ?? 'Employee' }}</div>
                                                        <span class="text-muted fs-11">{{ $wfh->employee->department->name ?? 'Dept' }}</span>
                                                    </td>
                                                    <td>
                                                        <span class="text-dark fw-semibold">{{ $wfh->start_date ? \Carbon\Carbon::parse($wfh->start_date)->format('d M, Y') : '' }}</span>
                                                        <div class="text-muted fs-11">{{ $wfh->total_days ?? 1 }} Days</div>
                                                    </td>
                                                    <td class="text-muted text-truncate" style="max-width: 160px;" title="{{ $wfh->reason }}">{{ $wfh->reason ?: 'WFH Request' }}</td>
                                                    <td class="text-end pe-3" style="white-space: nowrap;">
                                                        <div class="d-inline-flex align-items-center justify-content-end gap-2.5">
                                                            <form method="POST" action="{{ route('hrms.wfh.approve', $wfh->id) }}" class="d-inline m-0">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-soft-success py-1 px-2.5 fs-11 fw-bold" style="border-radius: 6px;">Approve</button>
                                                            </form>
                                                            <form method="POST" action="{{ route('hrms.wfh.reject', $wfh->id) }}" class="d-inline m-0">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-soft-danger py-1 px-2.5 fs-11 fw-bold" style="border-radius: 6px;">Reject</button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center align-middle text-muted fs-12" style="height: 265px;">
                                                    <div>
                                                        <i class="feather-check-circle text-success fs-3 mb-2 d-block opacity-75"></i>
                                                        <div>No pending WFH requests.</div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @php $totalWfhPages = ceil($pendingWfh->count() / 5); @endphp
                            @if($totalWfhPages > 1)
                                <div class="card-footer bg-light p-2 px-3 d-flex justify-content-between align-items-center border-top">
                                    <span class="text-muted fs-11" id="wfhPageIndicator">Page 1 of {{ $totalWfhPages }}</span>
                                    <div class="d-flex align-items-center gap-1">
                                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" id="wfhPrevBtn" disabled onclick="changeTabActionPage('wfh', -1, {{ $totalWfhPages }})">
                                            <i class="feather-chevron-left fs-12"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" id="wfhNextBtn" onclick="changeTabActionPage('wfh', 1, {{ $totalWfhPages }})">
                                            <i class="feather-chevron-right fs-12"></i>
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Tab 3: Attendance Regularizations -->
                        <div class="tab-pane fade" id="tab-punches">
                            <div class="table-responsive" style="min-height: 299px;">
                                <table class="table table-hover align-middle mb-0 fs-12">
                                    <thead class="bg-light text-muted fs-11 text-uppercase">
                                        <tr>
                                            <th class="ps-3 py-2.5">Employee</th>
                                            <th class="py-2.5">Date & Requested Times</th>
                                            <th class="py-2.5">Reason</th>
                                            <th class="text-end pe-3 py-2.5">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $punchesChunks = $pendingCorrections->chunk(5); @endphp
                                        @forelse($punchesChunks as $pageIndex => $chunk)
                                            @php $pageNo = $pageIndex + 1; @endphp
                                            @foreach($chunk as $corr)
                                                <tr class="tab-punches-row tab-punches-page-{{ $pageNo }} {{ $pageNo > 1 ? 'd-none' : '' }}">
                                                    <td class="ps-3">
                                                        <div class="fw-bold text-dark">{{ $corr->employee->full_name ?? 'Employee' }}</div>
                                                        <span class="text-muted fs-11">{{ $corr->date ? \Carbon\Carbon::parse($corr->date)->format('d M, Y') : '' }}</span>
                                                    </td>
                                                    <td>
                                                        <span class="text-success fw-bold">{{ $corr->requested_check_in ? \Carbon\Carbon::parse($corr->requested_check_in)->format('h:i A') : '—' }}</span> to <span class="text-danger fw-bold">{{ $corr->requested_check_out ? \Carbon\Carbon::parse($corr->requested_check_out)->format('h:i A') : '—' }}</span>
                                                    </td>
                                                    <td class="text-muted text-truncate" style="max-width: 160px;">{{ $corr->reason ?: 'Biometric issue' }}</td>
                                                    <td class="text-end pe-3" style="white-space: nowrap;">
                                                        <div class="d-inline-flex align-items-center justify-content-end gap-2.5">
                                                            <form method="POST" action="{{ route('hrms.attendance.corrections.approve', $corr->id) }}" class="d-inline m-0">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-soft-success py-1 px-2.5 fs-11 fw-bold" style="border-radius: 6px;">Approve</button>
                                                            </form>
                                                            <form method="POST" action="{{ route('hrms.attendance.corrections.reject', $corr->id) }}" class="d-inline m-0">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-soft-danger py-1 px-2.5 fs-11 fw-bold" style="border-radius: 6px;">Reject</button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center align-middle text-muted fs-12" style="height: 265px;">
                                                    <div>
                                                        <i class="feather-check-circle text-success fs-3 mb-2 d-block opacity-75"></i>
                                                        <div>No pending punch corrections.</div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @php $totalPunchesPages = ceil($pendingCorrections->count() / 5); @endphp
                            @if($totalPunchesPages > 1)
                                <div class="card-footer bg-light p-2 px-3 d-flex justify-content-between align-items-center border-top">
                                    <span class="text-muted fs-11" id="punchesPageIndicator">Page 1 of {{ $totalPunchesPages }}</span>
                                    <div class="d-flex align-items-center gap-1">
                                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" id="punchesPrevBtn" disabled onclick="changeTabActionPage('punches', -1, {{ $totalPunchesPages }})">
                                            <i class="feather-chevron-left fs-12"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" id="punchesNextBtn" onclick="changeTabActionPage('punches', 1, {{ $totalPunchesPages }})">
                                            <i class="feather-chevron-right fs-12"></i>
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Tab 4: Expenses -->
                        <div class="tab-pane fade" id="tab-expenses">
                            <div class="table-responsive" style="min-height: 299px;">
                                <table class="table table-hover align-middle mb-0 fs-12">
                                    <thead class="bg-light text-muted fs-11 text-uppercase">
                                        <tr>
                                            <th class="ps-3 py-2.5">Employee</th>
                                            <th class="py-2.5">Claim Title</th>
                                            <th class="py-2.5">Amount</th>
                                            <th class="text-end pe-3 py-2.5">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $expenseChunks = $pendingExpenses->chunk(5); @endphp
                                        @forelse($expenseChunks as $pageIndex => $chunk)
                                            @php $pageNo = $pageIndex + 1; @endphp
                                            @foreach($chunk as $exp)
                                                <tr class="tab-expenses-row tab-expenses-page-{{ $pageNo }} {{ $pageNo > 1 ? 'd-none' : '' }}">
                                                    <td class="ps-3">
                                                        <div class="fw-bold text-dark">{{ $exp->employee->full_name ?? 'Employee' }}</div>
                                                        <span class="text-muted fs-11">{{ $exp->employee->department->name ?? 'Dept' }}</span>
                                                    </td>
                                                    <td>{{ $exp->title ?? 'Expense Claim' }}</td>
                                                    <td class="fw-bold text-success">${{ number_format($exp->total_amount ?? 0, 2) }}</td>
                                                    <td class="text-end pe-3">
                                                        <a href="{{ route('hrms.travel-expense.index') }}" class="btn btn-sm btn-outline-primary py-1 px-2.5 fs-11 fw-semibold">View Claim</a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center align-middle text-muted fs-12" style="height: 265px;">
                                                    <div>
                                                        <i class="feather-check-circle text-success fs-3 mb-2 d-block opacity-75"></i>
                                                        <div>No pending expense claims.</div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @php $totalExpensePages = ceil($pendingExpenses->count() / 5); @endphp
                            @if($totalExpensePages > 1)
                                <div class="card-footer bg-light p-2 px-3 d-flex justify-content-between align-items-center border-top">
                                    <span class="text-muted fs-11" id="expensesPageIndicator">Page 1 of {{ $totalExpensePages }}</span>
                                    <div class="d-flex align-items-center gap-1">
                                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" id="expensesPrevBtn" disabled onclick="changeTabActionPage('expenses', -1, {{ $totalExpensePages }})">
                                            <i class="feather-chevron-left fs-12"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" id="expensesNextBtn" onclick="changeTabActionPage('expenses', 1, {{ $totalExpensePages }})">
                                            <i class="feather-chevron-right fs-12"></i>
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Widget 3: Attendance Discipline & Penalty Watch (Late Arrivals & Unprocessed Penalties) -->
            <div class="row g-3 mb-3">
                <!-- Late Arrivals (Last 7 Days) -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100" id="lateArrivalsCard">
                        <div class="card-header border-bottom p-2.5 px-3 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <div class="dash-card-icon-avatar bg-soft-warning text-warning">
                                    <i class="feather-clock"></i>
                                </div>
                                <h6 class="card-title fw-bold text-dark mb-0 fs-13">Late Arrivals (Last 7 Days)</h6>
                            </div>
                            <span class="badge bg-soft-warning text-warning fs-10 fw-bold">{{ $recentLateArrivals->count() }} Cases</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush fs-12">
                                @php $lateChunks = $recentLateArrivals->chunk(5); @endphp
                                @forelse($lateChunks as $pageIndex => $chunk)
                                    @php $pageNo = $pageIndex + 1; @endphp
                                    @foreach($chunk as $att)
                                        @php
                                            $lateFormatted = '';
                                            if ($att->check_in) {
                                                $ci = \Carbon\Carbon::parse($att->check_in);
                                                $dateStr = \Carbon\Carbon::parse($att->date)->format('Y-m-d');
                                                $shiftStart = \Carbon\Carbon::parse($dateStr . ' 09:00:00');
                                                if ($ci->gt($shiftStart)) {
                                                    $totalMins = (int) round($shiftStart->diffInMinutes($ci));
                                                    if ($totalMins >= 60) {
                                                        $h = intdiv($totalMins, 60);
                                                        $m = $totalMins % 60;
                                                        $lateFormatted = $m > 0 ? "{$h}h {$m}m Late" : "{$h}h Late";
                                                    } elseif ($totalMins > 0) {
                                                        $lateFormatted = "{$totalMins}m Late";
                                                    }
                                                }
                                            }
                                        @endphp
                                        <div class="list-group-item p-2.5 px-3 d-flex justify-content-between align-items-center late-page-item late-page-{{ $pageNo }} {{ $pageNo > 1 ? 'd-none' : '' }}" data-page="{{ $pageNo }}">
                                            <div>
                                                <div class="fw-bold text-dark">{{ $att->employee->full_name ?? 'Employee' }}</div>
                                                <span class="text-muted fs-11">{{ $att->employee->department->name ?? 'Dept' }} &bull; {{ \Carbon\Carbon::parse($att->date)->format('d M, Y') }}</span>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-soft-danger text-danger fs-10">
                                                    {{ $att->check_in ? \Carbon\Carbon::parse($att->check_in)->format('h:i A') : 'Late' }}
                                                </span>
                                                @if(!empty($lateFormatted))
                                                    <div class="text-muted fs-10 mt-0.5">{{ $lateFormatted }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                    @if($chunk->count() < 5)
                                        @for($i = 0; $i < (5 - $chunk->count()); $i++)
                                            <div class="list-group-item p-2.5 px-3 d-flex justify-content-between align-items-center late-page-item late-page-{{ $pageNo }} {{ $pageNo > 1 ? 'd-none' : '' }}" style="min-height: 56.5px; opacity: 0; pointer-events: none;" aria-hidden="true">
                                                &nbsp;
                                            </div>
                                        @endfor
                                    @endif
                                @empty
                                    <div class="d-flex align-items-center justify-content-center text-muted fs-12 text-center" style="min-height: 282px;">
                                        <div>
                                            <i class="feather-check-circle text-success fs-3 mb-2 d-block opacity-75"></i>
                                            <div>No late arrivals in the last 7 days.</div>
                                        </div>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                        @php $totalLatePages = ceil($recentLateArrivals->count() / 5); @endphp
                        @if($totalLatePages > 1)
                            <div class="card-footer bg-light p-2 px-3 d-flex justify-content-between align-items-center border-top">
                                <span class="text-muted fs-11" id="latePageIndicator">Page 1 of {{ $totalLatePages }}</span>
                                <div class="d-flex align-items-center gap-1">
                                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" id="latePrevBtn" disabled onclick="changeSectionPage('late', -1, {{ $totalLatePages }})">
                                        <i class="feather-chevron-left fs-12"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" id="lateNextBtn" onclick="changeSectionPage('late', 1, {{ $totalLatePages }})">
                                        <i class="feather-chevron-right fs-12"></i>
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Unprocessed Penalties -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100" id="penaltiesCard">
                        <div class="card-header border-bottom p-2.5 px-3 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <div class="dash-card-icon-avatar bg-soft-danger text-danger">
                                    <i class="feather-alert-triangle"></i>
                                </div>
                                <h6 class="card-title fw-bold text-dark mb-0 fs-13">Unprocessed Penalties</h6>
                            </div>
                            <span class="badge bg-soft-danger text-danger fs-10 fw-bold">{{ $unprocessedPenalties->count() }} Pending</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush fs-12">
                                @php $penChunks = $unprocessedPenalties->chunk(5); @endphp
                                @forelse($penChunks as $pageIndex => $chunk)
                                    @php $pageNo = $pageIndex + 1; @endphp
                                    @foreach($chunk as $pen)
                                        <div class="list-group-item p-2.5 px-3 d-flex justify-content-between align-items-center pen-page-item pen-page-{{ $pageNo }} {{ $pageNo > 1 ? 'd-none' : '' }}" data-page="{{ $pageNo }}">
                                            <div>
                                                <div class="fw-bold text-dark">{{ $pen->employee->full_name ?? 'Employee' }}</div>
                                                <span class="text-muted fs-11">{{ ucfirst(str_replace('_', ' ', $pen->rule_type ?? 'Penalty')) }} &bull; {{ $pen->date ? \Carbon\Carbon::parse($pen->date)->format('d M, Y') : '' }}</span>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-soft-danger text-danger fs-10 fw-bold">
                                                    ${{ number_format($pen->penalty_amount ?? 0, 2) }}
                                                </span>
                                                <div class="text-muted fs-10 mt-0.5 text-uppercase">Unprocessed</div>
                                            </div>
                                        </div>
                                    @endforeach
                                @empty
                                    <div class="d-flex align-items-center justify-content-center text-muted fs-12 text-center" style="min-height: 282px;">
                                        <div>
                                            <i class="feather-check-circle text-success fs-3 mb-2 d-block opacity-75"></i>
                                            <div>No unprocessed penalties pending.</div>
                                        </div>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                        @php $totalPenPages = ceil($unprocessedPenalties->count() / 5); @endphp
                        @if($totalPenPages > 1)
                            <div class="card-footer bg-light p-2 px-3 d-flex justify-content-between align-items-center border-top">
                                <span class="text-muted fs-11" id="penPageIndicator">Page 1 of {{ $totalPenPages }}</span>
                                <div class="d-flex align-items-center gap-1">
                                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" id="penPrevBtn" disabled onclick="changeSectionPage('pen', -1, {{ $totalPenPages }})">
                                        <i class="feather-chevron-left fs-12"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" id="penNextBtn" onclick="changeSectionPage('pen', 1, {{ $totalPenPages }})">
                                        <i class="feather-chevron-right fs-12"></i>
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Widget 3b: Probation & Offboarding Watch -->
            <div class="row g-3 mb-3">
                <!-- Probation Watch -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100" id="probCard">
                        <div class="card-header border-bottom p-2.5 px-3 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <div class="dash-card-icon-avatar bg-soft-warning text-warning">
                                    <i class="feather-award"></i>
                                </div>
                                <h6 class="card-title fw-bold text-dark mb-0 fs-13">Probation Ending Soon</h6>
                            </div>
                            <a href="{{ route('hrms.probation.index') }}" class="text-primary fs-11 fw-semibold">View All &rarr;</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush fs-12">
                                @php $probChunks = $upcomingProbationEmployees->chunk(5); @endphp
                                @forelse($probChunks as $pageIndex => $chunk)
                                    @php $pageNo = $pageIndex + 1; @endphp
                                    @foreach($chunk as $pEmp)
                                        @php
                                            $pEndDate = \Carbon\Carbon::parse($pEmp->probation_end_date);
                                            $today = \Carbon\Carbon::today();
                                            $daysLeft = $today->diffInDays($pEndDate, false);

                                            if ($daysLeft < 0) {
                                                $timeText = 'Ended ' . abs($daysLeft) . 'd ago';
                                                $badgeClass = 'bg-soft-danger text-danger';
                                            } elseif ($daysLeft == 0) {
                                                $timeText = 'Ends Today';
                                                $badgeClass = 'bg-soft-danger text-danger';
                                            } elseif ($daysLeft >= 30) {
                                                $diff = $today->diff($pEndDate);
                                                $m = $diff->m + ($diff->y * 12);
                                                $d = $diff->d;
                                                if ($m > 0 && $d > 0) {
                                                    $timeText = "{$m}m {$d}d left";
                                                } elseif ($m > 0) {
                                                    $timeText = "{$m}m left";
                                                } else {
                                                    $timeText = "{$d}d left";
                                                }
                                                $badgeClass = 'bg-soft-warning text-warning';
                                            } else {
                                                $timeText = $daysLeft == 1 ? "1 day left" : "{$daysLeft} days left";
                                                $badgeClass = 'bg-soft-warning text-warning';
                                            }
                                        @endphp
                                        <div class="list-group-item p-2.5 px-3 d-flex justify-content-between align-items-center prob-page-item prob-page-{{ $pageNo }} {{ $pageNo > 1 ? 'd-none' : '' }}" data-page="{{ $pageNo }}">
                                            <div class="pe-2 overflow-hidden">
                                                <div class="fw-bold text-dark text-truncate">{{ $pEmp->full_name }}</div>
                                                <span class="text-muted fs-11 text-truncate d-block">{{ $pEmp->designation->name ?? 'Role' }} &bull; {{ $pEndDate->format('d M, Y') }}</span>
                                            </div>
                                            <span class="badge {{ $badgeClass }} fs-10 fw-bold px-2 py-1 flex-shrink-0">
                                                {{ $timeText }}
                                            </span>
                                        </div>
                                    @endforeach
                                    @if($chunk->count() < 5)
                                        @for($i = 0; $i < (5 - $chunk->count()); $i++)
                                            <div class="list-group-item p-2.5 px-3 d-flex justify-content-between align-items-center prob-page-item prob-page-{{ $pageNo }} {{ $pageNo > 1 ? 'd-none' : '' }}" style="min-height: 49px; opacity: 0; pointer-events: none;" aria-hidden="true">
                                                &nbsp;
                                            </div>
                                        @endfor
                                    @endif
                                @empty
                                    <div class="d-flex align-items-center justify-content-center text-muted fs-12 text-center" style="min-height: 282px;">
                                        <div>
                                            <i class="feather-check-circle text-success fs-3 mb-2 d-block opacity-75"></i>
                                            <div>No employees due for probation review.</div>
                                        </div>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                        @php $totalProbPages = ceil($upcomingProbationEmployees->count() / 5); @endphp
                        @if($totalProbPages > 1)
                            <div class="card-footer bg-light p-2 px-3 d-flex justify-content-between align-items-center border-top">
                                <span class="text-muted fs-11" id="probPageIndicator">Page 1 of {{ $totalProbPages }}</span>
                                <div class="d-flex align-items-center gap-1">
                                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" id="probPrevBtn" disabled onclick="changeSectionPage('prob', -1, {{ $totalProbPages }})">
                                        <i class="feather-chevron-left fs-12"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" id="probNextBtn" onclick="changeSectionPage('prob', 1, {{ $totalProbPages }})">
                                        <i class="feather-chevron-right fs-12"></i>
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Offboarding Pipeline -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100" id="exitCard">
                        <div class="card-header border-bottom p-2.5 px-3 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <div class="dash-card-icon-avatar bg-soft-danger text-danger">
                                    <i class="feather-user-x"></i>
                                </div>
                                <h6 class="card-title fw-bold text-dark mb-0 fs-13">Active Exits & Offboarding</h6>
                            </div>
                            <a href="{{ route('hrms.exits.index') }}" class="text-primary fs-11 fw-semibold">Manage Exits &rarr;</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush fs-12">
                                @php $exitChunks = $activeExits->chunk(5); @endphp
                                @forelse($exitChunks as $pageIndex => $chunk)
                                    @php $pageNo = $pageIndex + 1; @endphp
                                    @foreach($chunk as $exit)
                                        @php
                                            $clrCount = $exit->clearances ? $exit->clearances->count() : 0;
                                            $clrDone = $exit->clearances ? $exit->clearances->where('status', 'cleared')->count() : 0;
                                            $pct = $clrCount > 0 ? round(($clrDone / $clrCount) * 100) : 0;
                                        @endphp
                                        <div class="list-group-item p-2.5 px-3 exit-page-item exit-page-{{ $pageNo }} {{ $pageNo > 1 ? 'd-none' : '' }}" data-page="{{ $pageNo }}">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <div class="fw-bold text-dark">{{ $exit->employee->full_name ?? 'Exiting Employee' }}</div>
                                                <span class="badge bg-soft-danger text-danger fs-10 text-uppercase">{{ str_replace('_', ' ', $exit->status) }}</span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center text-muted fs-11 mb-1.5">
                                                <span>LWD: <strong>{{ $exit->effective_lwd ? \Carbon\Carbon::parse($exit->effective_lwd)->format('d M, Y') : 'Pending' }}</strong></span>
                                                <span>Clearance: <strong>{{ $pct }}%</strong></span>
                                            </div>
                                            <div class="progress" style="height: 5px; border-radius: 4px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $pct }}%"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                    @if($chunk->count() < 5)
                                        @for($i = 0; $i < (5 - $chunk->count()); $i++)
                                            <div class="list-group-item p-2.5 px-3 exit-page-item exit-page-{{ $pageNo }} {{ $pageNo > 1 ? 'd-none' : '' }}" style="min-height: 56.5px; opacity: 0; pointer-events: none;" aria-hidden="true">
                                                &nbsp;
                                            </div>
                                        @endfor
                                    @endif
                                @empty
                                    <div class="d-flex align-items-center justify-content-center text-muted fs-12 text-center" style="min-height: 282px;">
                                        <div>
                                            <i class="feather-smile text-primary fs-3 mb-2 d-block opacity-75"></i>
                                            <div>No active exit or clearance cases.</div>
                                        </div>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                        @php $totalExitPages = ceil($activeExits->count() / 5); @endphp
                        @if($totalExitPages > 1)
                            <div class="card-footer bg-light p-2 px-3 d-flex justify-content-between align-items-center border-top">
                                <span class="text-muted fs-11" id="exitPageIndicator">Page 1 of {{ $totalExitPages }}</span>
                                <div class="d-flex align-items-center gap-1">
                                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" id="exitPrevBtn" disabled onclick="changeSectionPage('exit', -1, {{ $totalExitPages }})">
                                        <i class="feather-chevron-left fs-12"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" id="exitNextBtn" onclick="changeSectionPage('exit', 1, {{ $totalExitPages }})">
                                        <i class="feather-chevron-right fs-12"></i>
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Widget 4: New Joinees & Team Spotlight -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header border-bottom p-2.5 px-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="dash-card-icon-avatar bg-soft-info text-info">
                            <i class="feather-user-plus"></i>
                        </div>
                        <h6 class="card-title fw-bold text-dark mb-0 fs-13">New Joinees Spotlight (Last 30 Days)</h6>
                    </div>
                    <span class="badge bg-soft-primary text-primary fs-10 fw-bold">{{ $newHiresThisMonth }} New Hires</span>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3">
                        @forelse($newHiresList as $nh)
                            <div class="col-md-6">
                                <div class="p-3 rounded-3 border bg-light d-flex align-items-center gap-3">
                                    <div class="avatar-initials-dash bg-white text-primary border shadow-sm">
                                        {{ strtoupper(substr($nh->full_name, 0, 2)) }}
                                    </div>
                                    <div class="overflow-hidden">
                                        <h6 class="fw-bold text-dark mb-0 fs-13 text-truncate">{{ $nh->full_name }}</h6>
                                        <div class="text-muted fs-11 text-truncate">{{ $nh->designation->name ?? 'Role' }} &bull; {{ $nh->department->name ?? 'Dept' }}</div>
                                        <div class="text-primary fs-10 fw-semibold mt-0.5">Joined {{ $nh->date_of_joining ? \Carbon\Carbon::parse($nh->date_of_joining)->format('d M, Y') : 'Recently' }}</div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12 text-center py-4 text-muted fs-12">
                                <i class="feather-user-check text-primary fs-3 mb-2 d-block opacity-75"></i>
                                <div>No new employees joined in the last 30 days.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

        </div>

        <!-- ── SIDEBAR COLUMN (4 COLS) ── -->
        <div class="col-lg-4">

            <!-- 1. My Leave Balances (ESS Card) -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header border-bottom p-2.5 px-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="dash-card-icon-avatar bg-soft-primary text-primary">
                            <i class="feather-calendar"></i>
                        </div>
                        <h6 class="card-title fw-bold text-dark mb-0 fs-13">My Leave Balances</h6>
                    </div>
                    <a href="{{ route('hrms.leaves.index') }}" class="btn btn-xs btn-primary fw-semibold px-2 py-1 fs-11 d-inline-flex align-items-center gap-1">
                        <i class="feather-plus"></i> Apply
                    </a>
                </div>
                <div class="card-body p-3 px-3">
                    <div class="row g-2 text-center">
                        <div class="col-4">
                            <div class="p-2 rounded-2 bg-soft-primary border border-primary border-opacity-10">
                                <span class="text-primary fs-10 text-uppercase fw-bold d-block">Casual</span>
                                <h4 class="fw-bolder text-primary mb-0 mt-0.5 fs-18">{{ $myLeaveBalances['casual']['remaining'] }}</h4>
                                <span class="text-muted fs-9 d-block mt-0.5">/ {{ $myLeaveBalances['casual']['allocated'] }} days</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded-2 bg-soft-success border border-success border-opacity-10">
                                <span class="text-success fs-10 text-uppercase fw-bold d-block">Sick</span>
                                <h4 class="fw-bolder text-success mb-0 mt-0.5 fs-18">{{ $myLeaveBalances['sick']['remaining'] }}</h4>
                                <span class="text-muted fs-9 d-block mt-0.5">/ {{ $myLeaveBalances['sick']['allocated'] }} days</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded-2 bg-soft-info border border-info border-opacity-10">
                                <span class="text-info fs-10 text-uppercase fw-bold d-block">Earned</span>
                                <h4 class="fw-bolder text-info mb-0 mt-0.5 fs-18">{{ $myLeaveBalances['earned']['remaining'] }}</h4>
                                <span class="text-muted fs-9 d-block mt-0.5">/ {{ $myLeaveBalances['earned']['allocated'] }} days</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>



            <!-- 3. Latest Payslip Snapshot -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header border-bottom p-2.5 px-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="dash-card-icon-avatar bg-soft-secondary text-secondary">
                            <i class="feather-file-text"></i>
                        </div>
                        <h6 class="card-title fw-bold text-dark mb-0 fs-13">My Latest Salary Slip</h6>
                    </div>
                    <span class="badge bg-soft-success text-success fs-10">Available</span>
                </div>
                <div class="card-body p-2.5 px-3">
                    <p class="text-muted fs-11 mb-2.5">Download your official salary slip for the recent payroll disbursement period.</p>
                    <a href="{{ route('hrms.payroll.mySalary') }}" class="btn btn-sm btn-primary w-100 fw-bold fs-11 d-flex align-items-center justify-content-center gap-1.5 shadow-sm" style="background-color: #1c3faa; border-color: #1c3faa;">
                        <i class="feather-download"></i> View & Download Payslip (PDF)
                    </a>
                </div>
            </div>

            <!-- 4. Approved Leaves (Out of Office Schedule) -->
            <div class="card border-0 shadow-sm mb-3" id="approvedLeavesCard">
                <div class="card-header border-bottom p-2.5 px-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="dash-card-icon-avatar bg-soft-success text-success">
                            <i class="feather-check-square"></i>
                        </div>
                        <h6 class="card-title fw-bold text-dark mb-0 fs-13">Approved Leaves</h6>
                    </div>
                    <span class="badge bg-soft-success text-success fs-10 fw-bold">{{ $approvedLeaves->count() }} Approved</span>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush fs-12">
                        @php $apprChunks = $approvedLeaves->chunk(5); @endphp
                        @forelse($apprChunks as $pageIndex => $chunk)
                            @php $pageNo = $pageIndex + 1; @endphp
                            @foreach($chunk as $lReq)
                                @php
                                    $sDate = \Carbon\Carbon::parse($lReq->start_date);
                                    $eDate = \Carbon\Carbon::parse($lReq->end_date);
                                    $numDays = $sDate->diffInDays($eDate) + 1;
                                @endphp
                                <div class="list-group-item p-2.5 px-3 d-flex justify-content-between align-items-center appr-page-item appr-page-{{ $pageNo }} {{ $pageNo > 1 ? 'd-none' : '' }}" data-page="{{ $pageNo }}">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="dash-card-icon-avatar bg-soft-success text-success rounded-circle flex-shrink-0" style="width: 32px; height: 32px; font-size: 14px;">
                                            <i class="feather-calendar"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark fs-12">{{ $lReq->employee->full_name ?? 'Employee' }}</div>
                                            <span class="text-muted fs-10">{{ $lReq->employee->department->name ?? 'Dept' }} &bull; {{ $lReq->leaveType->name ?? 'Leave' }}</span>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-soft-success text-success fs-10 fw-semibold">
                                            {{ $sDate->format('d M') }} - {{ $eDate->format('d M') }} ({{ $numDays }}d)
                                        </span>
                                        <div class="text-muted fs-10 mt-0.5">Approved</div>
                                    </div>
                                </div>
                            @endforeach
                            @if($chunk->count() < 5)
                                @for($i = 0; $i < (5 - $chunk->count()); $i++)
                                    <div class="list-group-item p-2.5 px-3 d-flex justify-content-between align-items-center appr-page-item appr-page-{{ $pageNo }} {{ $pageNo > 1 ? 'd-none' : '' }}" style="min-height: 56.5px; opacity: 0; pointer-events: none;" aria-hidden="true">
                                        &nbsp;
                                    </div>
                                @endfor
                            @endif
                        @empty
                            <div class="d-flex align-items-center justify-content-center text-muted fs-12 text-center" style="min-height: 282px;">
                                <div>
                                    <i class="feather-check-circle text-success fs-3 mb-2 d-block opacity-75"></i>
                                    <div>No upcoming approved leaves.</div>
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>
                @php $totalApprPages = ceil($approvedLeaves->count() / 5); @endphp
                @if($totalApprPages > 1)
                    <div class="card-footer bg-light p-2 px-3 d-flex justify-content-between align-items-center border-top">
                        <span class="text-muted fs-11" id="apprPageIndicator">Page 1 of {{ $totalApprPages }}</span>
                        <div class="d-flex align-items-center gap-1">
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" id="apprPrevBtn" disabled onclick="changeSectionPage('appr', -1, {{ $totalApprPages }})">
                                <i class="feather-chevron-left fs-12"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" id="apprNextBtn" onclick="changeSectionPage('appr', 1, {{ $totalApprPages }})">
                                <i class="feather-chevron-right fs-12"></i>
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            <!-- 4b. Upcoming Public Holidays -->
            <div class="card border-0 shadow-sm mb-3" id="upcomingHolidaysCard">
                <div class="card-header border-bottom p-2.5 px-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="dash-card-icon-avatar bg-soft-danger text-danger">
                            <i class="feather-gift"></i>
                        </div>
                        <h6 class="card-title fw-bold text-dark mb-0 fs-13">Upcoming Holidays</h6>
                    </div>
                    <a href="{{ route('hrms.holidays.index') }}" class="text-muted fs-11">Calendar &rarr;</a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush fs-12">
                        @php
                            $holidayChunks = $upcomingHolidays->chunk(5);
                        @endphp
                        @forelse($holidayChunks as $pageIndex => $chunk)
                            @php
                                $pageNo = $pageIndex + 1;
                            @endphp
                            @foreach($chunk as $hol)
                                @php
                                    $holDate = \Carbon\Carbon::parse($hol->holiday_date);
                                    $today = \Carbon\Carbon::today();
                                    $hDaysLeft = $today->diffInDays($holDate, false);

                                    if ($hDaysLeft == 0) {
                                        $timeText = 'Today';
                                    } elseif ($hDaysLeft < 0) {
                                        $timeText = 'Passed';
                                    } elseif ($hDaysLeft >= 30) {
                                        $diff = $today->diff($holDate);
                                        $m = $diff->m + ($diff->y * 12);
                                        $d = $diff->d;
                                        if ($m > 0 && $d > 0) {
                                            $timeText = "in {$m}m {$d}d";
                                        } elseif ($m > 0) {
                                            $timeText = "in {$m}m";
                                        } else {
                                            $timeText = "in {$d}d";
                                        }
                                    } else {
                                        $timeText = "in {$hDaysLeft}d";
                                    }
                                @endphp
                                <div class="list-group-item p-2.5 px-3 d-flex justify-content-between align-items-center holiday-page-item holiday-page-{{ $pageNo }} {{ $pageNo > 1 ? 'd-none' : '' }}" data-page="{{ $pageNo }}">
                                    <div>
                                        <div class="fw-bold text-dark">{{ $hol->name ?? ($hol->holiday_name ?? 'Holiday') }}</div>
                                        <span class="text-muted fs-11">{{ $holDate->format('D, d M Y') }}</span>
                                    </div>
                                    <span class="badge bg-soft-danger text-danger fs-10">
                                        {{ $timeText }}
                                    </span>
                                </div>
                            @endforeach
                            {{-- Pad last page with empty slots if fewer than 5 items --}}
                            @if($chunk->count() < 5)
                                @for($i = 0; $i < (5 - $chunk->count()); $i++)
                                    <div class="list-group-item p-2.5 px-3 d-flex justify-content-between align-items-center holiday-page-item holiday-page-{{ $pageNo }} {{ $pageNo > 1 ? 'd-none' : '' }}" style="min-height: 56.5px; opacity: 0; pointer-events: none;" aria-hidden="true">
                                        &nbsp;
                                    </div>
                                @endfor
                            @endif
                        @empty
                            <div class="d-flex align-items-center justify-content-center text-muted fs-12 text-center" style="min-height: 282px;">
                                <div>
                                    <i class="feather-calendar fs-3 text-muted mb-2 d-block opacity-50"></i>
                                    <div>No upcoming holidays scheduled.</div>
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>
                @php
                    $totalHolidayPages = ceil($upcomingHolidays->count() / 5);
                @endphp
                @if($totalHolidayPages > 1)
                    <div class="card-footer bg-light p-2 px-3 d-flex justify-content-between align-items-center border-top">
                        <span class="text-muted fs-11" id="holidayPageIndicator">Page 1 of {{ $totalHolidayPages }}</span>
                        <div class="d-flex align-items-center gap-1">
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" id="holidayPrevBtn" disabled onclick="changeHolidayPage(-1, {{ $totalHolidayPages }})">
                                <i class="feather-chevron-left fs-12"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" id="holidayNextBtn" onclick="changeHolidayPage(1, {{ $totalHolidayPages }})">
                                <i class="feather-chevron-right fs-12"></i>
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            <!-- 5. Celebrations & Milestones (Birthdays & Anniversaries) -->
            @php
                $allCelebrations = collect();
                foreach($upcomingBirthdays as $bday) {
                    $allCelebrations->push([
                        'title' => $bday->full_name,
                        'subtitle' => ($bday->designation->name ?? 'Team Member') . ' • ' . \Carbon\Carbon::parse($bday->date_of_birth)->format('d M'),
                        'badge' => 'Birthday',
                        'badge_class' => 'bg-soft-warning text-warning',
                        'icon' => '🎂',
                        'icon_bg' => 'bg-soft-warning text-warning'
                    ]);
                }
                foreach($upcomingAnniversaries as $anni) {
                    $doj = \Carbon\Carbon::parse($anni->date_of_joining);
                    $years = max(1, date('Y') - $doj->year);
                    $allCelebrations->push([
                        'title' => $anni->full_name,
                        'subtitle' => "{$years}y Anniversary • " . $doj->format('d M'),
                        'badge' => "{$years} Year" . ($years > 1 ? 's' : ''),
                        'badge_class' => 'bg-soft-primary text-primary',
                        'icon' => '🎖️',
                        'icon_bg' => 'bg-soft-primary text-primary'
                    ]);
                }
                $celebChunks = $allCelebrations->chunk(5);
            @endphp
            <div class="card border-0 shadow-sm mb-3" id="celebCard">
                <div class="card-header border-bottom p-2.5 px-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="dash-card-icon-avatar bg-soft-warning text-warning">
                            <i class="feather-award"></i>
                        </div>
                        <h6 class="card-title fw-bold text-dark mb-0 fs-13">Celebrations This Month</h6>
                    </div>
                    @if($allCelebrations->count() > 0)
                        <span class="badge bg-soft-warning text-warning fs-10 fw-bold">{{ $allCelebrations->count() }} Events</span>
                    @endif
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush fs-12">
                        @forelse($celebChunks as $pageIndex => $chunk)
                            @php $pageNo = $pageIndex + 1; @endphp
                            @foreach($chunk as $item)
                                <div class="list-group-item p-2.5 px-3 d-flex justify-content-between align-items-center celeb-page-item celeb-page-{{ $pageNo }} {{ $pageNo > 1 ? 'd-none' : '' }}" data-page="{{ $pageNo }}">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="dash-card-icon-avatar {{ $item['icon_bg'] }} rounded-circle flex-shrink-0" style="width: 32px; height: 32px; font-size: 14px;">
                                            {{ $item['icon'] }}
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark fs-12">{{ $item['title'] }}</div>
                                            <span class="text-muted fs-10">{{ $item['subtitle'] }}</span>
                                        </div>
                                    </div>
                                    <span class="badge {{ $item['badge_class'] }} fs-10 fw-semibold">{{ $item['badge'] }}</span>
                                </div>
                            @endforeach
                            @if($chunk->count() < 5)
                                @for($i = 0; $i < (5 - $chunk->count()); $i++)
                                    <div class="list-group-item p-2.5 px-3 d-flex justify-content-between align-items-center celeb-page-item celeb-page-{{ $pageNo }} {{ $pageNo > 1 ? 'd-none' : '' }}" style="min-height: 56.5px; opacity: 0; pointer-events: none;" aria-hidden="true">
                                        &nbsp;
                                    </div>
                                @endfor
                            @endif
                        @empty
                            <div class="d-flex align-items-center justify-content-center text-muted fs-12 text-center" style="min-height: 282px;">
                                <div>
                                    <i class="feather-smile text-warning fs-3 mb-2 d-block opacity-75"></i>
                                    <div>No birthdays or work anniversaries this month.</div>
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>
                @php $totalCelebPages = ceil($allCelebrations->count() / 5); @endphp
                @if($totalCelebPages > 1)
                    <div class="card-footer bg-light p-2 px-3 d-flex justify-content-between align-items-center border-top">
                        <span class="text-muted fs-11" id="celebPageIndicator">Page 1 of {{ $totalCelebPages }}</span>
                        <div class="d-flex align-items-center gap-1">
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" id="celebPrevBtn" disabled onclick="changeSectionPage('celeb', -1, {{ $totalCelebPages }})">
                                <i class="feather-chevron-left fs-12"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" id="celebNextBtn" onclick="changeSectionPage('celeb', 1, {{ $totalCelebPages }})">
                                <i class="feather-chevron-right fs-12"></i>
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            <!-- 6. Department Workforce Distribution -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header border-bottom p-2.5 px-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="dash-card-icon-avatar bg-soft-info text-info">
                            <i class="feather-pie-chart"></i>
                        </div>
                        <h6 class="card-title fw-bold text-dark mb-0 fs-13">Department Headcount</h6>
                    </div>
                    <span class="badge bg-soft-info text-info fs-10">{{ $departments->count() }} Depts</span>
                </div>
                <div class="card-body p-2.5 px-3">
                    @foreach($departments as $dept)
                        @php
                            $deptPct = $totalEmployees > 0 ? round(($dept->employees_count / $totalEmployees) * 100) : 0;
                        @endphp
                        <div class="mb-2">
                            <div class="d-flex justify-content-between align-items-center fs-11 mb-1">
                                <span class="text-dark fw-semibold">{{ $dept->name }}</span>
                                <span class="text-muted">{{ $dept->employees_count }} ({{ $deptPct }}%)</span>
                            </div>
                            <div class="progress" style="height: 5px; border-radius: 4px;">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $deptPct }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>
    </div>

    <!-- ── DASHBOARD MODALS (Cleanly rendered with body relocation) ── -->
    <div id="dashModalsWrapper">
        <!-- 1. Apply Leave Modal -->
        <div class="modal fade" id="applyLeaveModal" tabindex="-1" aria-labelledby="applyLeaveModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-3">
                    <div class="modal-header border-bottom p-3">
                        <h5 class="modal-title fw-bold text-dark fs-15" id="applyLeaveModalLabel">
                            <i class="feather-calendar text-primary me-1.5"></i>Apply Leave
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="{{ route('hrms.leaves.store') }}">
                        @csrf
                        <input type="hidden" name="employee_id" value="{{ $currentEmployee->id ?? '' }}">
                        <div class="modal-body p-4 fs-13">
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-dark">Leave Type <span class="text-danger">*</span></label>
                                <select name="leave_type_id" class="form-select fs-13" required>
                                    <option value="">-- Select Leave Type --</option>
                                    @foreach($leaveTypes as $lt)
                                        <option value="{{ $lt->id }}">{{ $lt->name }} ({{ $lt->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-semibold text-dark">Start Date <span class="text-danger">*</span></label>
                                    <input type="date" name="start_date" class="form-control fs-13" value="{{ date('Y-m-d') }}" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold text-dark">End Date <span class="text-danger">*</span></label>
                                    <input type="date" name="end_date" class="form-control fs-13" value="{{ date('Y-m-d') }}" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-dark">Reason for Leave <span class="text-danger">*</span></label>
                                <textarea name="reason" class="form-control fs-13" rows="3" placeholder="Please provide specific reason for leave application..." required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer border-top p-3 bg-light">
                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-primary d-flex align-items-center gap-1" style="background-color: #1c3faa; border-color: #1c3faa;">
                                <i class="feather-send"></i> Submit Application
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 2. Request WFH Modal -->
        <div class="modal fade" id="requestWfhModal" tabindex="-1" aria-labelledby="requestWfhModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-3">
                    <div class="modal-header border-bottom p-3">
                        <h5 class="modal-title fw-bold text-dark fs-15" id="requestWfhModalLabel">
                            <i class="feather-home text-info me-1.5"></i>Request Work From Home (WFH)
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="{{ route('hrms.wfh.store') }}">
                        @csrf
                        <input type="hidden" name="employee_id" value="{{ $currentEmployee->id ?? '' }}">
                        <input type="hidden" name="start_date_type" value="full_day">
                        <input type="hidden" name="end_date_type" value="full_day">
                        <div class="modal-body p-4 fs-13">
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-semibold text-dark">Start Date <span class="text-danger">*</span></label>
                                    <input type="date" name="start_date" class="form-control fs-13" value="{{ date('Y-m-d') }}" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold text-dark">End Date <span class="text-danger">*</span></label>
                                    <input type="date" name="end_date" class="form-control fs-13" value="{{ date('Y-m-d') }}" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-dark">Reason / Task Plan <span class="text-danger">*</span></label>
                                <textarea name="reason" class="form-control fs-13" rows="3" placeholder="Provide details of tasks you will work on remotely..." required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer border-top p-3 bg-light">
                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-info text-white d-flex align-items-center gap-1">
                                <i class="feather-send"></i> Submit WFH Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- 3. Confirm Clock-Out Modal -->
        <div class="modal fade" id="confirmClockOutModal" tabindex="-1" aria-labelledby="confirmClockOutModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
                <div class="modal-content border-0 shadow-lg rounded-3">
                    <div class="modal-header border-bottom p-3 bg-light">
                        <h5 class="modal-title fw-bold text-dark fs-14" id="confirmClockOutModalLabel">
                            <i class="feather-log-out text-danger me-1.5"></i>Confirm Web Clock-Out
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="{{ route('hrms.dashboard.punch') }}">
                        @csrf
                        <input type="hidden" name="employee_id" value="{{ $currentEmployee->id ?? '' }}">
                        <input type="hidden" name="action" value="out">
                        <div class="modal-body p-4 text-center">
                            <div class="avatar-text avatar-lg bg-soft-danger text-danger rounded-circle mx-auto mb-3">
                                <i class="feather-alert-triangle fs-3"></i>
                            </div>
                            <h5 class="fw-bold text-dark mb-1">End Today's Shift?</h5>
                            <p class="text-muted fs-12 mb-3">
                                You are about to record your final <strong>Clock-Out</strong> timestamp for today ({{ date('d M Y') }}). Any active breaks will be automatically concluded.
                            </p>
                            @if($myTodayAttendance && $myTodayAttendance->check_in)
                                <div class="p-2.5 rounded-2 bg-light border text-start fs-12">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted">Clocked In at:</span>
                                        <strong class="text-dark">{{ \Carbon\Carbon::parse($myTodayAttendance->check_in)->format('h:i A') }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Clock-Out Time:</span>
                                        <strong class="text-danger">{{ date('h:i A') }}</strong>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer border-top p-2.5 px-3 bg-light d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-danger fw-bold d-flex align-items-center gap-1 shadow-sm">
                                <i class="feather-check"></i> Yes, Clock-Out
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
    // Live Digital Clock updating every second
    function updateLiveClock() {
        const now = new Date();
        let hours = now.getHours();
        let minutes = now.getMinutes();
        let seconds = now.getSeconds();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        
        hours = hours % 12;
        hours = hours ? hours : 12;
        hours = hours < 10 ? '0' + hours : hours;
        minutes = minutes < 10 ? '0' + minutes : minutes;
        seconds = seconds < 10 ? '0' + seconds : seconds;
        
        const clockEl = document.getElementById('liveDigitalClock');
        if (clockEl) {
            clockEl.textContent = hours + ':' + minutes + ':' + seconds + ' ' + ampm;
        }
    }
    setInterval(updateLiveClock, 1000);

    // Relocate modals to document.body to prevent any backdrop blur or stacking context traps
    function moveDashModalsToBody() {
        const wrapper = document.getElementById('dashModalsWrapper');
        if (wrapper && wrapper.parentElement !== document.body) {
            document.body.appendChild(wrapper);
        }
    }
    document.addEventListener('DOMContentLoaded', moveDashModalsToBody);
    document.addEventListener('show.bs.modal', moveDashModalsToBody);

    // Page-by-page navigation for Upcoming Holidays widget (5 items per page)
    let currentHolidayPage = 1;
    function changeHolidayPage(delta, maxPages) {
        const newPage = currentHolidayPage + delta;
        if (newPage < 1 || newPage > maxPages) return;

        currentHolidayPage = newPage;

        document.querySelectorAll('#upcomingHolidaysCard .holiday-page-item').forEach(el => {
            el.classList.add('d-none');
            el.classList.remove('d-flex');
        });

        document.querySelectorAll('#upcomingHolidaysCard .holiday-page-' + currentHolidayPage).forEach(el => {
            el.classList.remove('d-none');
            el.classList.add('d-flex');
        });

        const indicator = document.getElementById('holidayPageIndicator');
        if (indicator) {
            indicator.textContent = 'Page ' + currentHolidayPage + ' of ' + maxPages;
        }

        const prevBtn = document.getElementById('holidayPrevBtn');
        const nextBtn = document.getElementById('holidayNextBtn');
        if (prevBtn) prevBtn.disabled = (currentHolidayPage === 1);
        if (nextBtn) nextBtn.disabled = (currentHolidayPage === maxPages);
    }

    // Page-by-page navigation for Pending Approvals Action Center tabs
    const tabActionPages = { leave: 1, wfh: 1, punches: 1, expenses: 1 };
    function changeTabActionPage(tabKey, delta, maxPages) {
        if (!tabActionPages[tabKey]) tabActionPages[tabKey] = 1;
        const newPage = tabActionPages[tabKey] + delta;
        if (newPage < 1 || newPage > maxPages) return;

        tabActionPages[tabKey] = newPage;

        document.querySelectorAll(`.tab-${tabKey}-row`).forEach(el => {
            el.classList.add('d-none');
        });

        document.querySelectorAll(`.tab-${tabKey}-page-${newPage}`).forEach(el => {
            el.classList.remove('d-none');
        });

        const indicator = document.getElementById(`${tabKey}PageIndicator`);
        if (indicator) {
            indicator.textContent = `Page ${newPage} of ${maxPages}`;
        }

        const prevBtn = document.getElementById(`${tabKey}PrevBtn`);
        const nextBtn = document.getElementById(`${tabKey}NextBtn`);
        if (prevBtn) prevBtn.disabled = (newPage === 1);
        if (nextBtn) nextBtn.disabled = (newPage === maxPages);
    }

    // Page-by-page navigation for Probation Watch, Offboarding, Late Arrivals, Unprocessed Penalties & Approved Leaves cards
    const sectionPages = { prob: 1, exit: 1, celeb: 1, late: 1, pen: 1, appr: 1 };
    function changeSectionPage(secKey, delta, maxPages) {
        if (!sectionPages[secKey]) sectionPages[secKey] = 1;
        const newPage = sectionPages[secKey] + delta;
        if (newPage < 1 || newPage > maxPages) return;

        sectionPages[secKey] = newPage;

        document.querySelectorAll(`.${secKey}-page-item`).forEach(el => {
            el.classList.add('d-none');
            el.classList.remove('d-flex');
        });

        document.querySelectorAll(`.${secKey}-page-${newPage}`).forEach(el => {
            el.classList.remove('d-none');
            if (secKey !== 'exit') {
                el.classList.add('d-flex');
            }
        });

        const indicator = document.getElementById(`${secKey}PageIndicator`);
        if (indicator) {
            indicator.textContent = `Page ${newPage} of ${maxPages}`;
        }

        const prevBtn = document.getElementById(`${secKey}PrevBtn`);
        const nextBtn = document.getElementById(`${secKey}NextBtn`);
        if (prevBtn) prevBtn.disabled = (newPage === 1);
        if (nextBtn) nextBtn.disabled = (newPage === maxPages);
    }
</script>
@endpush
