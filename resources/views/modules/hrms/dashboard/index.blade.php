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
</style>
@endpush

@section('page-actions')
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <button type="button" class="btn btn-sm btn-light border text-dark fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#applyLeaveModal">
            <i class="feather-calendar me-1.5 text-primary"></i> Apply Leave
        </button>
        <button type="button" class="btn btn-sm btn-light border text-dark fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#requestWfhModal">
            <i class="feather-home me-1.5 text-info"></i> Request WFH
        </button>
        <a href="{{ route('hrms.attendance.index', ['view' => 'corrections']) }}" class="btn btn-sm btn-light border text-dark fw-semibold shadow-sm">
            <i class="feather-clock me-1.5 text-warning"></i> Regularize Punch
        </a>
        <a href="{{ route('hrms.employees.index') }}" class="btn btn-sm btn-primary fw-semibold shadow-sm" style="background-color: #1c3faa; border-color: #1c3faa;">
            <i class="feather-user-plus me-1.5"></i> Add Employee
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
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-initials-dash bg-soft-primary text-primary shadow-sm border border-primary border-opacity-10">
                            {{ strtoupper(substr(auth()->user()?->name ?? ($currentEmployee?->full_name ?? 'AD'), 0, 2)) }}
                        </div>
                        <div>
                            @php
                                $hour = date('H');
                                $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
                            @endphp
                            <h5 class="fw-bold mb-1 text-dark">{{ $greeting }}, {{ auth()->user()?->name ?? ($currentEmployee?->full_name ?? 'Rahul Sharma') }}! 👋</h5>
                            <div class="d-flex align-items-center gap-2 flex-wrap fs-12 text-muted">
                                <span class="fw-semibold text-secondary">{{ $currentEmployee ? ($currentEmployee->designation->name ?? 'Staff') : 'Engineering' }}</span>
                                <span>&bull;</span>
                                <span>{{ $currentEmployee ? ($currentEmployee->department->name ?? 'Engineering Department') : 'Operations' }}</span>
                                <span>&bull;</span>
                                <span class="text-dark"><i class="feather-calendar text-primary me-1"></i>{{ date('l, d F Y') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 text-lg-end mt-2 mt-lg-0">
                    <span class="badge bg-soft-primary text-primary px-3 py-1.5 fs-11 fw-bold rounded-pill border border-primary border-opacity-20">
                        <i class="feather-shield text-primary me-1.5"></i> Role: {{ $roleDisplayName }}
                    </span>
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
                    <div class="row align-items-center">
                        <div class="col-lg-7">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-soft-primary text-primary fs-11 fw-bold px-2.5 py-1 rounded-pill">
                                    <i class="feather-clock me-1"></i> Web Punch Station
                                </span>
                                <span class="text-muted fs-12">&bull; General Shift (09:30 AM - 06:30 PM)</span>
                            </div>
                            <div class="d-flex align-items-baseline gap-3 mb-2">
                                <div class="fw-bolder text-dark" id="liveDigitalClock" style="font-size: 32px; letter-spacing: -0.5px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                                    {{ date('h:i:s A') }}
                                </div>
                                <div class="badge bg-soft-secondary text-dark fs-12 fw-normal px-2.5 py-1">
                                    <i class="feather-calendar text-primary me-1"></i> {{ date('l, d F Y') }}
                                </div>
                            </div>

                            @if($myTodayAttendance && $myTodayAttendance->check_in)
                                <div class="d-flex align-items-center gap-2 fs-13 flex-wrap">
                                    <span class="badge bg-soft-success text-success px-3 py-1.5 rounded-pill fw-bold">
                                        <i class="feather-check-circle me-1"></i> Clocked In
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
                                <div class="text-warning fs-13 fw-semibold">
                                    <i class="feather-alert-triangle me-1"></i> You have not clocked in yet today.
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
                                            <select name="location_type" class="form-select form-select-sm" style="width: 140px; height: 38px;">
                                                <option value="office">🏢 Office</option>
                                                <option value="wfh">🏠 WFH</option>
                                                <option value="field">🚗 Field</option>
                                            </select>
                                            <button type="submit" name="action" value="in" class="btn btn-success fw-bold px-4 d-flex align-items-center gap-1.5 shadow-sm" style="height: 38px;">
                                                <i class="feather-log-in"></i> Web Clock-In
                                            </button>
                                        </div>
                                        <span class="text-muted fs-11">Click to record your check-in timestamp</span>
                                    </div>
                                @elseif($myTodayAttendance && !$myTodayAttendance->check_out)
                                    <div class="d-flex flex-wrap gap-2 justify-content-lg-end align-items-center">
                                        @if($myTodayAttendance->isOnBreak())
                                            <button type="submit" name="action" value="break_in" class="btn btn-warning text-dark fw-bold px-3 d-flex align-items-center gap-1.5 shadow-sm" style="height: 38px;">
                                                <i class="feather-play"></i> Resume Work
                                            </button>
                                        @else
                                            <button type="submit" name="action" value="break_out" class="btn btn-light border text-dark fw-semibold px-3 d-flex align-items-center gap-1.5 shadow-sm" style="height: 38px;">
                                                <i class="feather-coffee text-warning"></i> Take Break
                                            </button>
                                        @endif
                                        
                                        <button type="button" class="btn btn-danger fw-bold px-4 d-flex align-items-center gap-1.5 shadow-sm" style="height: 38px;" data-bs-toggle="modal" data-bs-target="#confirmClockOutModal">
                                            <i class="feather-log-out"></i> Clock-Out
                                        </button>
                                    </div>
                                @else
                                    <div class="badge bg-soft-success text-success fs-13 p-2 px-3 fw-bold">
                                        <i class="feather-check-circle me-1"></i> Shift Completed for Today
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
                        <div class="avatar-text avatar-xs bg-soft-primary text-primary rounded-2">
                            <i class="feather-inbox fs-6"></i>
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
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 fs-12">
                                    <thead class="bg-light text-muted fs-11 text-uppercase">
                                        <tr>
                                            <th class="ps-3 py-2.5">Employee</th>
                                            <th class="py-2.5">Type & Period</th>
                                            <th class="py-2.5">Reason</th>
                                            <th class="text-end pe-3 py-2.5">Quick Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($pendingLeaves as $leave)
                                            <tr class="{{ $loop->index >= 5 ? 'extra-leaves-row d-none' : '' }}">
                                                <td class="ps-3">
                                                    <div class="fw-bold text-dark">{{ $leave->employee->full_name ?? 'Employee' }}</div>
                                                    <span class="text-muted fs-11">{{ $leave->employee->department->name ?? 'Dept' }}</span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-soft-primary text-primary fs-10">{{ ucfirst($leave->leave_type ?? 'Casual') }}</span>
                                                    <div class="text-muted fs-11 mt-0.5">{{ $leave->start_date ? \Carbon\Carbon::parse($leave->start_date)->format('d M') : '' }} &bull; {{ $leave->total_days ?? 1 }} Days</div>
                                                </td>
                                                <td class="text-muted text-truncate" style="max-width: 160px;" title="{{ $leave->reason }}">{{ $leave->reason ?: 'No reason provided' }}</td>
                                                <td class="text-end pe-3">
                                                    <form method="POST" action="{{ route('hrms.leaves.approve', $leave->id) }}" class="d-inline m-0">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-soft-success py-1 px-2.5 fs-11 fw-semibold">
                                                            Approve
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="{{ route('hrms.leaves.reject', $leave->id) }}" class="d-inline m-0">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-soft-danger py-1 px-2.5 fs-11 fw-semibold ms-1">
                                                            Reject
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center py-4 text-muted fs-12">
                                                    <i class="feather-check-circle text-success fs-16 me-1"></i> No pending leave requests.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @if($pendingLeaves->count() > 5)
                                <div class="card-footer bg-light p-2 text-center border-top">
                                    <button type="button" class="btn btn-link btn-sm text-primary fw-bold fs-11 p-0 text-decoration-none d-inline-flex align-items-center gap-1" onclick="toggleExtraRows(this, '.extra-leaves-row')">
                                        <span class="btn-text">Show {{ $pendingLeaves->count() - 5 }} More</span>
                                        <i class="feather-chevron-down"></i>
                                    </button>
                                </div>
                            @endif
                        </div>

                        <!-- Tab 2: WFH -->
                        <div class="tab-pane fade" id="tab-wfh">
                            <div class="table-responsive">
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
                                        @forelse($pendingWfh as $wfh)
                                            <tr class="{{ $loop->index >= 5 ? 'extra-wfh-row d-none' : '' }}">
                                                <td class="ps-3">
                                                    <div class="fw-bold text-dark">{{ $wfh->employee->full_name ?? 'Employee' }}</div>
                                                    <span class="text-muted fs-11">{{ $wfh->employee->department->name ?? 'Dept' }}</span>
                                                </td>
                                                <td>
                                                    <span class="text-dark fw-semibold">{{ $wfh->start_date ? \Carbon\Carbon::parse($wfh->start_date)->format('d M, Y') : '' }}</span>
                                                    <div class="text-muted fs-11">{{ $wfh->total_days ?? 1 }} Days</div>
                                                </td>
                                                <td class="text-muted text-truncate" style="max-width: 160px;" title="{{ $wfh->reason }}">{{ $wfh->reason ?: 'WFH Request' }}</td>
                                                <td class="text-end pe-3">
                                                    <form method="POST" action="{{ route('hrms.wfh.approve', $wfh->id) }}" class="d-inline m-0">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-soft-success py-1 px-2.5 fs-11 fw-semibold">
                                                            Approve
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="{{ route('hrms.wfh.reject', $wfh->id) }}" class="d-inline m-0">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-soft-danger py-1 px-2.5 fs-11 fw-semibold ms-1">
                                                            Reject
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center py-4 text-muted fs-12">
                                                    <i class="feather-check-circle text-success fs-16 me-1"></i> No pending WFH requests.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @if($pendingWfh->count() > 5)
                                <div class="card-footer bg-light p-2 text-center border-top">
                                    <button type="button" class="btn btn-link btn-sm text-primary fw-bold fs-11 p-0 text-decoration-none d-inline-flex align-items-center gap-1" onclick="toggleExtraRows(this, '.extra-wfh-row')">
                                        <span class="btn-text">Show {{ $pendingWfh->count() - 5 }} More</span>
                                        <i class="feather-chevron-down"></i>
                                    </button>
                                </div>
                            @endif
                        </div>

                        <!-- Tab 3: Attendance Regularizations -->
                        <div class="tab-pane fade" id="tab-punches">
                            <div class="table-responsive">
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
                                        @forelse($pendingCorrections as $corr)
                                            <tr class="{{ $loop->index >= 5 ? 'extra-punches-row d-none' : '' }}">
                                                <td class="ps-3">
                                                    <div class="fw-bold text-dark">{{ $corr->employee->full_name ?? 'Employee' }}</div>
                                                    <span class="text-muted fs-11">{{ $corr->date ? \Carbon\Carbon::parse($corr->date)->format('d M, Y') : '' }}</span>
                                                </td>
                                                <td>
                                                    <span class="text-success fw-bold">{{ $corr->requested_check_in ? \Carbon\Carbon::parse($corr->requested_check_in)->format('h:i A') : '—' }}</span> to <span class="text-danger fw-bold">{{ $corr->requested_check_out ? \Carbon\Carbon::parse($corr->requested_check_out)->format('h:i A') : '—' }}</span>
                                                </td>
                                                <td class="text-muted text-truncate" style="max-width: 160px;">{{ $corr->reason ?: 'Biometric issue' }}</td>
                                                <td class="text-end pe-3">
                                                    <form method="POST" action="{{ route('hrms.attendance.corrections.approve', $corr->id) }}" class="d-inline m-0">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-soft-success py-1 px-2.5 fs-11 fw-semibold">
                                                            Approve
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="{{ route('hrms.attendance.corrections.reject', $corr->id) }}" class="d-inline m-0">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-soft-danger py-1 px-2.5 fs-11 fw-semibold ms-1">
                                                            Reject
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center py-4 text-muted fs-12">
                                                    <i class="feather-check-circle text-success fs-16 me-1"></i> No pending punch corrections.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @if($pendingCorrections->count() > 5)
                                <div class="card-footer bg-light p-2 text-center border-top">
                                    <button type="button" class="btn btn-link btn-sm text-primary fw-bold fs-11 p-0 text-decoration-none d-inline-flex align-items-center gap-1" onclick="toggleExtraRows(this, '.extra-punches-row')">
                                        <span class="btn-text">Show {{ $pendingCorrections->count() - 5 }} More</span>
                                        <i class="feather-chevron-down"></i>
                                    </button>
                                </div>
                            @endif
                        </div>

                        <!-- Tab 4: Expenses -->
                        <div class="tab-pane fade" id="tab-expenses">
                            <div class="table-responsive">
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
                                        @forelse($pendingExpenses as $exp)
                                            <tr class="{{ $loop->index >= 5 ? 'extra-expenses-row d-none' : '' }}">
                                                <td class="ps-3">
                                                    <div class="fw-bold text-dark">{{ $exp->employee->full_name ?? 'Employee' }}</div>
                                                    <span class="text-muted fs-11">{{ $exp->employee->department->name ?? 'Dept' }}</span>
                                                </td>
                                                <td>{{ $exp->title ?? 'Expense Claim' }}</td>
                                                <td class="fw-bold text-success">${{ number_format($exp->total_amount ?? 0, 2) }}</td>
                                                <td class="text-end pe-3">
                                                    <a href="{{ route('hrms.travel-expense.index') }}" class="btn btn-sm btn-outline-primary py-1 px-2.5 fs-11 fw-semibold">
                                                        View Claim
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center py-4 text-muted fs-12">
                                                    <i class="feather-check-circle text-success fs-16 me-1"></i> No pending expense claims.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @if($pendingExpenses->count() > 5)
                                <div class="card-footer bg-light p-2 text-center border-top">
                                    <button type="button" class="btn btn-link btn-sm text-primary fw-bold fs-11 p-0 text-decoration-none d-inline-flex align-items-center gap-1" onclick="toggleExtraRows(this, '.extra-expenses-row')">
                                        <span class="btn-text">Show {{ $pendingExpenses->count() - 5 }} More</span>
                                        <i class="feather-chevron-down"></i>
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Widget 3: Probation & Offboarding Watch -->
            <div class="row g-3 mb-3">
                <!-- Probation Watch -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header border-bottom p-2.5 px-3 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-text avatar-xs bg-soft-warning text-warning rounded-2">
                                    <i class="feather-award fs-6"></i>
                                </div>
                                <h6 class="card-title fw-bold text-dark mb-0 fs-13">Probation Ending Soon</h6>
                            </div>
                            <a href="{{ route('hrms.probation.index') }}" class="text-primary fs-11 fw-semibold">View All &rarr;</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush fs-12">
                                @forelse($upcomingProbationEmployees as $pEmp)
                                    @php
                                        $pEndDate = \Carbon\Carbon::parse($pEmp->probation_end_date);
                                        $daysLeft = \Carbon\Carbon::today()->diffInDays($pEndDate, false);
                                    @endphp
                                    <div class="list-group-item p-2.5 px-3 d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold text-dark">{{ $pEmp->full_name }}</div>
                                            <span class="text-muted fs-11">{{ $pEmp->designation->name ?? 'Role' }} &bull; {{ $pEndDate->format('d M, Y') }}</span>
                                        </div>
                                        <div class="text-end">
                                            @if($daysLeft <= 0)
                                                <span class="badge bg-soft-danger text-danger fs-10">Ended {{ abs($daysLeft) }}d ago</span>
                                            @else
                                                <span class="badge bg-soft-warning text-warning fs-10">{{ $daysLeft }} days left</span>
                                            @endif
                                            <div class="mt-1">
                                                <a href="{{ route('hrms.probation.index') }}" class="btn btn-xs btn-outline-primary py-0.5 px-2 fs-10 fw-semibold">
                                                    Review
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center py-4 text-muted fs-12">
                                        <i class="feather-check-circle text-success me-1"></i> No employees due for probation review.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Offboarding Pipeline -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header border-bottom p-2.5 px-3 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-text avatar-xs bg-soft-danger text-danger rounded-2">
                                    <i class="feather-user-x fs-6"></i>
                                </div>
                                <h6 class="card-title fw-bold text-dark mb-0 fs-13">Active Exits & Offboarding</h6>
                            </div>
                            <a href="{{ route('hrms.exits.index') }}" class="text-primary fs-11 fw-semibold">Manage Exits &rarr;</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush fs-12">
                                @forelse($activeExits as $exit)
                                    @php
                                        $clrCount = $exit->clearances ? $exit->clearances->count() : 0;
                                        $clrDone = $exit->clearances ? $exit->clearances->where('status', 'cleared')->count() : 0;
                                        $pct = $clrCount > 0 ? round(($clrDone / $clrCount) * 100) : 0;
                                    @endphp
                                    <div class="list-group-item p-2.5 px-3">
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
                                @empty
                                    <div class="text-center py-4 text-muted fs-12">
                                        <i class="feather-smile text-primary me-1"></i> No active exit or clearance cases.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Widget 4: New Joinees & Team Spotlight -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header border-bottom p-2.5 px-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-text avatar-xs bg-soft-warning text-warning rounded-2">
                            <i class="feather-star fs-6"></i>
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
                            <div class="col-12 text-center py-3 text-muted fs-12">
                                No new employees joined in the last 30 days.
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
                        <div class="avatar-text avatar-xs bg-soft-primary text-primary rounded-2">
                            <i class="feather-calendar fs-6"></i>
                        </div>
                        <h6 class="card-title fw-bold text-dark mb-0 fs-13">My Leave Balances</h6>
                    </div>
                    <button type="button" class="btn btn-xs btn-primary fw-semibold px-2 py-1 fs-11" data-bs-toggle="modal" data-bs-target="#applyLeaveModal">
                        <i class="feather-plus me-1"></i> Apply
                    </button>
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

            <!-- 2. Profile & KYC Completion -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header border-bottom p-2.5 px-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-text avatar-xs bg-soft-success text-success rounded-2">
                            <i class="feather-user-check fs-6"></i>
                        </div>
                        <h6 class="card-title fw-bold text-dark mb-0 fs-13">Profile & KYC Status</h6>
                    </div>
                    <span class="badge bg-soft-primary text-primary fs-10 fw-bold">{{ $profileCompletion }}%</span>
                </div>
                <div class="card-body p-2.5 px-3">
                    <div class="progress mb-2" style="height: 5px; border-radius: 4px;">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $profileCompletion }}%"></div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between fs-11 text-muted">
                        <span><i class="feather-check-circle text-success me-1"></i>Bank & Tax Verified</span>
                        <a href="{{ route('hrms.employees.index') }}" class="text-primary fw-semibold text-decoration-none">Update Profile &rarr;</a>
                    </div>
                </div>
            </div>

            <!-- 3. Latest Payslip Snapshot -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header border-bottom p-2.5 px-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-text avatar-xs bg-soft-secondary text-secondary rounded-2">
                            <i class="feather-file-text fs-6"></i>
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

            <!-- 4. Upcoming Public Holidays -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header border-bottom p-2.5 px-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-text avatar-xs bg-soft-danger text-danger rounded-2">
                            <i class="feather-calendar fs-6"></i>
                        </div>
                        <h6 class="card-title fw-bold text-dark mb-0 fs-13">Upcoming Holidays</h6>
                    </div>
                    <a href="{{ route('hrms.holidays.index') }}" class="text-muted fs-11">Calendar &rarr;</a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush fs-12">
                        @forelse($upcomingHolidays as $hol)
                            @php
                                $holDate = \Carbon\Carbon::parse($hol->holiday_date);
                                $hDaysLeft = \Carbon\Carbon::today()->diffInDays($holDate, false);
                            @endphp
                            <div class="list-group-item p-2.5 px-3 d-flex justify-content-between align-items-center {{ $loop->index >= 5 ? 'extra-holidays-row d-none' : '' }}">
                                <div>
                                    <div class="fw-bold text-dark">{{ $hol->name ?? ($hol->holiday_name ?? 'Holiday') }}</div>
                                    <span class="text-muted fs-11">{{ $holDate->format('D, d M Y') }}</span>
                                </div>
                                <span class="badge bg-soft-danger text-danger fs-10">
                                    {{ $hDaysLeft == 0 ? 'Today' : ($hDaysLeft > 0 ? "in {$hDaysLeft}d" : 'Passed') }}
                                </span>
                            </div>
                        @empty
                            <div class="text-center py-2.5 text-muted fs-11">No upcoming holidays scheduled.</div>
                        @endforelse
                    </div>
                </div>
                @if($upcomingHolidays->count() > 5)
                    <div class="card-footer bg-light p-2 text-center border-top">
                        <button type="button" class="btn btn-link btn-sm text-primary fw-bold fs-11 p-0 text-decoration-none d-inline-flex align-items-center gap-1" onclick="toggleExtraRows(this, '.extra-holidays-row')">
                            <span class="btn-text">Show {{ $upcomingHolidays->count() - 5 }} More</span>
                            <i class="feather-chevron-down"></i>
                        </button>
                    </div>
                @endif
            </div>

            <!-- 5. Celebrations & Milestones (Birthdays & Anniversaries) -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header border-bottom p-2.5 px-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-text avatar-xs bg-soft-warning text-warning rounded-2">
                            <i class="feather-award fs-6"></i>
                        </div>
                        <h6 class="card-title fw-bold text-dark mb-0 fs-13">Celebrations This Month</h6>
                    </div>
                    @php
                        $totalCelebrations = $upcomingBirthdays->count() + $upcomingAnniversaries->count();
                    @endphp
                    @if($totalCelebrations > 0)
                        <span class="badge bg-soft-warning text-warning fs-10 fw-bold">{{ $totalCelebrations }} Events</span>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if($upcomingBirthdays->isNotEmpty() || $upcomingAnniversaries->isNotEmpty())
                        <div class="list-group list-group-flush fs-12">
                            @foreach($upcomingBirthdays as $bday)
                                <div class="list-group-item p-2.5 px-3 d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-2.5">
                                        <div class="avatar-text avatar-xs bg-soft-warning text-warning rounded-circle fw-bold fs-11">
                                            🎂
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark fs-12">{{ $bday->full_name }}</div>
                                            <span class="text-muted fs-10">{{ $bday->designation->name ?? 'Team Member' }} &bull; {{ \Carbon\Carbon::parse($bday->date_of_birth)->format('d M') }}</span>
                                        </div>
                                    </div>
                                    <span class="badge bg-soft-warning text-warning fs-10 fw-semibold">Birthday</span>
                                </div>
                            @endforeach

                            @foreach($upcomingAnniversaries as $anni)
                                @php
                                    $doj = \Carbon\Carbon::parse($anni->date_of_joining);
                                    $years = max(1, date('Y') - $doj->year);
                                @endphp
                                <div class="list-group-item p-2.5 px-3 d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-2.5">
                                        <div class="avatar-text avatar-xs bg-soft-primary text-primary rounded-circle fw-bold fs-11">
                                            🎖️
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark fs-12">{{ $anni->full_name }}</div>
                                            <span class="text-muted fs-10">{{ $years }}y Anniversary &bull; {{ $doj->format('d M') }}</span>
                                        </div>
                                    </div>
                                    <span class="badge bg-soft-primary text-primary fs-10 fw-semibold">{{ $years }} Year{{ $years > 1 ? 's' : '' }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-3 text-muted fs-11">
                            <i class="feather-smile text-warning fs-16 d-block mb-1"></i> No birthdays or work anniversaries this month.
                        </div>
                    @endif
                </div>
            </div>

            <!-- 6. Department Workforce Distribution -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header border-bottom p-2.5 px-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-text avatar-xs bg-soft-info text-info rounded-2">
                            <i class="feather-layers fs-6"></i>
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

    // Toggle extra entries (beyond first 5) with smooth arrow rotation
    function toggleExtraRows(btn, selector) {
        const rows = document.querySelectorAll(selector);
        if (!rows.length) return;
        
        const isHidden = rows[0].classList.contains('d-none');
        rows.forEach(r => {
            if (isHidden) {
                r.classList.remove('d-none');
            } else {
                r.classList.add('d-none');
            }
        });

        const textSpan = btn.querySelector('.btn-text');
        const icon = btn.querySelector('i');
        
        if (isHidden) {
            if (textSpan) textSpan.textContent = 'Show Less';
            if (icon) {
                icon.classList.remove('feather-chevron-down');
                icon.classList.add('feather-chevron-up');
            }
        } else {
            const count = rows.length;
            if (textSpan) textSpan.textContent = `Show ${count} More`;
            if (icon) {
                icon.classList.remove('feather-chevron-up');
                icon.classList.add('feather-chevron-down');
            }
        }
    }
</script>
@endpush
