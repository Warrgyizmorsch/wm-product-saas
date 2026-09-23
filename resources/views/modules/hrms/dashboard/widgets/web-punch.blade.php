<div class="card border mb-0 h-100 shadow-sm rounded-3">
    <div class="card-body p-4 d-flex flex-column justify-content-between">
        <div>
            <!-- Header row: Badge + Shift + Request Buttons -->
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge px-3 py-1.5 fs-12 fw-bold rounded-pill d-inline-flex align-items-center gap-1.5" style="background: #eff6ff; color: #3b82f6;">
                        <i class="feather-clock fs-13"></i> Web Punch Station
                    </span>
                    <span class="fs-12 text-muted fw-medium ms-1">
                        • {{ $myShiftDetails['name'] ?? 'Day Shift' }} ({{ $myShiftDetails['timing'] ?? '09:00 - 18:00' }})
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.wfh_requests.index') ? route('hrms.wfh_requests.index') : '#' }}" class="btn btn-white border fw-bold text-uppercase fs-10 px-3 py-1.5 rounded-2 text-secondary d-inline-flex align-items-center gap-1 shadow-2xs" style="background: #ffffff; border-color: #cbd5e1 !important; color: #475569 !important;">
                        <i class="feather-home text-success fs-12 me-1"></i> REQUEST WFH
                    </a>
                    <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.attendance_corrections.index') ? route('hrms.attendance_corrections.index') : '#' }}" class="btn btn-white border fw-bold text-uppercase fs-10 px-3 py-1.5 rounded-2 text-secondary d-inline-flex align-items-center gap-1 shadow-2xs" style="background: #ffffff; border-color: #cbd5e1 !important; color: #475569 !important;">
                        <i class="feather-clock text-warning fs-12 me-1"></i> REGULARIZE PUNCH
                    </a>
                </div>
            </div>

            <!-- Main Live Clock & Punch Action -->
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                <div>
                    <div class="d-flex align-items-baseline gap-2">
                        <h1 class="fw-bolder text-dark mb-0 tracking-tight live-web-clock" style="font-size: 34px; font-weight: 900; color: #0f172a !important; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; letter-spacing: -0.02em;">
                            {{ date('h:i:s A') }}
                        </h1>
                        <span class="badge px-2.5 py-1 fs-12 fw-semibold rounded-2 d-inline-flex align-items-center gap-1" style="background: #eef2ff; color: #6366f1; border: 1px solid #e0e7ff;">
                            <i class="feather-calendar fs-12"></i> {{ date('l, d F Y') }}
                        </span>
                    </div>
                    <div class="mt-1.5 fs-13 fw-bold text-warning d-inline-flex align-items-center gap-1">
                        <i class="feather-alert-triangle fs-14"></i> You have not clocked in yet today.
                    </div>
                </div>

                <div class="text-end">
                    <form action="{{ \Illuminate\Support\Facades\Route::has('hrms.dashboard.punch') ? route('hrms.dashboard.punch') : url('/hrms/dashboard/punch') }}" method="POST" class="m-0">
                        @csrf
                        @if($currentEmployee ?? null)
                            <input type="hidden" name="employee_id" value="{{ $currentEmployee->id }}">
                        @endif

                        @if(!($myTodayAttendance ?? null) || !$myTodayAttendance->check_in)
                            <input type="hidden" name="action" value="in">
                            <button type="submit" class="btn text-white fw-bold px-4 py-2.5 rounded-2 text-uppercase d-inline-flex align-items-center gap-2 shadow-2xs" style="background: #489476; border: none; font-size: 13px; font-weight: 800;">
                                <i class="feather-log-in fs-15"></i> WEB CLOCK-IN
                            </button>
                        @elseif(($myTodayAttendance ?? null) && $myTodayAttendance->check_in && !$myTodayAttendance->check_out)
                            @php
                                $activeBreak = $myTodayAttendance->breaks ? $myTodayAttendance->breaks->whereNull('break_out')->first() : null;
                            @endphp

                            @if(!$activeBreak)
                                <button type="submit" name="action" value="break_out" class="btn btn-warning text-dark fw-bold px-3 py-2 shadow-2xs d-inline-flex align-items-center gap-1.5 fs-12">
                                    <i class="feather-coffee fs-14"></i> START BREAK
                                </button>
                            @else
                                <button type="submit" name="action" value="break_in" class="btn btn-info text-white fw-bold px-3 py-2 shadow-2xs d-inline-flex align-items-center gap-1.5 fs-12">
                                    <i class="feather-play fs-14"></i> RESUME WORK
                                </button>
                            @endif

                            <button type="submit" name="action" value="out" class="btn btn-danger fw-bold px-4 py-2.5 rounded-2 text-uppercase d-inline-flex align-items-center gap-2 shadow-2xs" style="font-size: 13px; font-weight: 800;">
                                <i class="feather-log-out fs-15"></i> WEB CLOCK-OUT
                            </button>
                        @else
                            <button type="button" class="btn btn-secondary disabled fw-bold px-4 py-2.5 rounded-2 text-uppercase opacity-75" style="font-size: 13px; font-weight: 800;">
                                <i class="feather-check fs-15"></i> SHIFT COMPLETED
                            </button>
                        @endif
                    </form>
                    <span class="fs-11 text-muted d-block mt-1 text-end" style="color: #94a3b8 !important;">Click to record your check-in timestamp</span>
                </div>
            </div>
        </div>

        <!-- 7-Day Attendance Log Section -->
        <div class="pt-3 border-top">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="fs-11 text-uppercase fw-bold text-muted tracking-wider" style="letter-spacing: 0.04em;">RECENT 7 DAYS ATTENDANCE LOG</span>
                <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.attendance.index') ? route('hrms.attendance.index') : '#' }}" class="fs-11 text-primary fw-bold text-decoration-none">Full Attendance Log &rarr;</a>
            </div>
            <div class="d-flex gap-2 justify-content-between">
                @foreach(($recentPunches ?? []) as $punch)
                    @php
                        $isToday = $punch['is_today'] ?? false;
                        $status = strtolower($punch['status'] ?? 'unmarked');
                        
                        if ($isToday) {
                            $boxStyle = 'background: #eff6ff; border: 2px solid #4f46e5; border-radius: 10px; color: #4f46e5;';
                        } elseif ($status === 'absent') {
                            $boxStyle = 'background: #fff1f2; border: 1px solid #fecdd3; border-radius: 10px; color: #e11d48;';
                        } elseif ($status === 'off') {
                            $boxStyle = 'background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; color: #64748b;';
                        } else {
                            $boxStyle = 'background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; color: #16a34a;';
                        }
                    @endphp
                    <div class="p-2 text-center flex-fill" style="{{ $boxStyle }}">
                        <div class="fs-10 fw-bold text-uppercase opacity-75 mb-0.5" style="font-size: 10px; font-weight: 700;">{{ $punch['day_name'] ?? '—' }}</div>
                        <div class="fs-14 fw-bolder mb-0.5" style="color: #0f172a; font-size: 15px; font-weight: 800;">{{ $punch['day_num'] ?? '—' }}</div>
                        <div class="fs-10 fw-bold text-capitalize" style="font-size: 10px; font-weight: 700;">{{ $isToday ? '—' : ucfirst($status) }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
