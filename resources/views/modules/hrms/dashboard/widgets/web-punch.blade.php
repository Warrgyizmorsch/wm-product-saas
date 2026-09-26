<div class="card border-0 shadow-sm rounded-4 mb-0 bg-white h-100 d-flex flex-column" style="border: 1px solid #edf2f7 !important; border-radius: 16px !important; box-shadow: 0 1px 3px rgba(0,0,0,0.03), 0 6px 16px rgba(0,0,0,0.02) !important;">
    <div class="card-body p-3 p-md-3.5 d-flex flex-column justify-content-between h-100 gap-2">
        <!-- Header row: Web Punch Pill + Shift details + Top Right Action Pills -->
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 flex-shrink-0">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge px-2.5 py-1 fs-11 fw-semibold rounded-pill d-inline-flex align-items-center" style="background: #eef2ff; color: #4f46e5; border: 1px solid #e0e7ff; font-weight: 600; gap: 6px;">
                    <i class="feather-clock fs-12" style="color: #6366f1; margin-bottom: 2px; margin-right: 2px;"></i> Web Punch Station
                </span>
                @php
                    $shiftRawName = $myShiftDetails['name'] ?? 'General Day Shift';
                    $cleanShiftName = trim(preg_replace('/\s*\([^)]*\)/', '', $shiftRawName));
                    $shiftTiming = $myShiftDetails['timing'] ?? '09:00 AM - 06:00 PM';
                @endphp
                <span class="fs-12 fw-normal ms-1" style="color: #64748b !important;">
                    • {{ $cleanShiftName }} ({{ $shiftTiming }})
                </span>
            </div>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.wfh.index') ? route('hrms.wfh.index') : url('/hrms/wfh') }}" class="btn fw-bold text-uppercase fs-11 px-2.5 py-1 rounded-pill d-inline-flex align-items-center text-nowrap" style="background: #f8fafc; border: 1px solid #e2e8f0; color: #334155; font-size: 11px; font-weight: 700; letter-spacing: 0.03em; gap: 6px;">
                    <i class="feather-home fs-12" style="color: #0d9488; margin-bottom: 2px; margin-right: 2px;"></i> REQUEST WFH
                </a>
                <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.attendance.myAttendance') ? route('hrms.attendance.myAttendance') : url('/hrms/attendance/my-attendance') }}" class="btn fw-bold text-uppercase fs-11 px-2.5 py-1 rounded-pill d-inline-flex align-items-center text-nowrap" style="background: #f8fafc; border: 1px solid #e2e8f0; color: #334155; font-size: 11px; font-weight: 700; letter-spacing: 0.03em; gap: 6px;">
                    <i class="feather-clock fs-12" style="color: #f59e0b; margin-bottom: 2px; margin-right: 2px;"></i> REGULARIZE PUNCH
                </a>
            </div>
        </div>

        <!-- Main Live Clock & Punch Action -->
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 my-auto flex-grow-1 py-1">
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h1 class="fw-semibold mb-0 live-web-clock" style="font-size: 28px; font-weight: 900; color: #0f172a; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; letter-spacing: -0.02em; line-height: 1.1;">
                        {{ date('h:i:s A') }}
                    </h1>
                    <span class="badge px-2 py-1 fs-11 fw-normal rounded-2 d-inline-flex align-items-center ms-1" style="background: #edf2f7; color: #64748b; font-weight: 500; gap: 5px;">
                        <i class="feather-calendar fs-11 me-1" style="color: #6366f1;"></i> {{ date('l, d F Y') }}
                    </span>
                </div>

                @php
                    $isClockedIn = ($myTodayAttendance ?? null) && $myTodayAttendance->check_in && !$myTodayAttendance->check_out;
                    $isCompleted = ($myTodayAttendance ?? null) && $myTodayAttendance->check_in && $myTodayAttendance->check_out;
                    $activeBreak = ($myTodayAttendance ?? null) && $myTodayAttendance->breaks ? $myTodayAttendance->breaks->whereNull('break_out')->first() : null;
                @endphp

                @if($isClockedIn && !$activeBreak)
                    <div class="mt-1 d-flex align-items-center gap-2">
                        <span class="badge px-2 py-0.5 fs-11 fw-bold rounded-pill d-inline-flex align-items-center" style="background: #dcfce7; color: #16a34a; font-weight: 700; gap: 4px;">
                            <i class="feather-check-circle fs-12 me-1"></i> Clocked In
                        </span>
                        <span class="fs-12 fw-normal" style="color: #64748b;">
                            In at <strong class="fw-bold" style="color: #0f172a;">{{ \Carbon\Carbon::parse($myTodayAttendance->check_in)->format('h:i A') }}</strong>
                        </span>
                    </div>
                @elseif($isClockedIn && $activeBreak)
                    <div class="mt-1 d-flex align-items-center gap-2">
                        <span class="badge px-2 py-0.5 fs-11 fw-bold rounded-pill d-inline-flex align-items-center" style="background: #fef3c7; color: #d97706; font-weight: 700; gap: 4px;">
                            <i class="feather-coffee fs-12 me-1"></i> On Break
                        </span>
                        <span class="fs-12 fw-normal" style="color: #64748b;">
                            In at <strong class="fw-bold" style="color: #0f172a;">{{ \Carbon\Carbon::parse($myTodayAttendance->check_in)->format('h:i A') }}</strong>
                        </span>
                    </div>
                @elseif($isCompleted)
                    <div class="mt-1 d-flex align-items-center gap-2">
                        <span class="badge px-2 py-0.5 fs-11 fw-bold rounded-pill d-inline-flex align-items-center" style="background: #f1f5f9; color: #64748b; font-weight: 700; gap: 4px;">
                            <i class="feather-check fs-12 me-1"></i> Shift Completed
                        </span>
                        <span class="fs-12 fw-normal" style="color: #64748b;">
                            In: <strong class="fw-bold" style="color: #0f172a;">{{ \Carbon\Carbon::parse($myTodayAttendance->check_in)->format('h:i A') }}</strong> • Out: <strong class="fw-bold" style="color: #0f172a;">{{ \Carbon\Carbon::parse($myTodayAttendance->check_out)->format('h:i A') }}</strong>
                        </span>
                    </div>
                @else
                    <div class="mt-1 d-flex align-items-center gap-2">
                        <span class="badge px-2 py-0.5 fs-11 fw-bold rounded-pill d-inline-flex align-items-center" style="background: #fee2e2; color: #ef4444; font-weight: 700; gap: 4px;">
                            <i class="feather-alert-circle fs-12 me-1"></i> Not Clocked In
                        </span>
                        <span class="fs-11 text-muted">You have not clocked in yet today.</span>
                    </div>
                @endif
            </div>

            <div class="text-end">
                <form action="{{ \Illuminate\Support\Facades\Route::has('hrms.dashboard.punch') ? route('hrms.dashboard.punch') : url('/hrms/dashboard/punch') }}" method="POST" class="m-0">
                    @csrf
                    @if($currentEmployee ?? null)
                        <input type="hidden" name="employee_id" value="{{ $currentEmployee->id }}">
                    @endif

                    <div class="d-flex align-items-center gap-2">
                        @if(!$isClockedIn && !$isCompleted)
                            <input type="hidden" name="action" value="in">
                            <button type="submit" class="btn text-white fw-bold px-3 py-1.5 rounded-3 text-uppercase d-inline-flex align-items-center" style="background: #10b981; border: none; font-size: 11px; font-weight: 800; letter-spacing: 0.03em; border-radius: 8px; gap: 6px;">
                                <i class="feather-log-in fs-13 me-1"></i> CLOCK-IN
                            </button>
                        @elseif($isClockedIn)
                            @if(!$activeBreak)
                                <button type="submit" name="action" value="break_out" class="btn fw-bold px-3 py-1.5 rounded-3 text-uppercase d-inline-flex align-items-center" style="background: #ffffff; border: 1px solid #e2e8f0; color: #1e293b; font-size: 11px; font-weight: 800; letter-spacing: 0.03em; border-radius: 8px; gap: 6px;">
                                    <i class="feather-coffee fs-13 me-1" style="color: #f97316;"></i> TAKE BREAK
                                </button>
                            @else
                                <button type="submit" name="action" value="break_in" class="btn fw-bold px-3 py-1.5 rounded-3 text-uppercase d-inline-flex align-items-center" style="background: #eef2ff; border: 1px solid #c7d2fe; color: #4338ca; font-size: 11px; font-weight: 800; border-radius: 8px; gap: 6px;">
                                    <i class="feather-play fs-13 me-1" style="color: #6366f1;"></i> END BREAK
                                </button>
                            @endif

                            <button type="submit" name="action" value="out" class="btn text-white fw-bold px-3 py-1.5 rounded-3 text-uppercase d-inline-flex align-items-center" style="background: #df4e2e; border: none; font-size: 11px; font-weight: 800; letter-spacing: 0.03em; border-radius: 8px; gap: 6px;">
                                <i class="feather-log-out fs-13 me-1"></i> CLOCK-OUT
                            </button>
                        @else
                            <button type="button" class="btn btn-secondary disabled fw-bold px-3 py-1.5 rounded-3 text-uppercase opacity-75 d-inline-flex align-items-center" style="font-size: 11px; font-weight: 800; border-radius: 8px; gap: 6px;">
                                <i class="feather-check fs-13 me-1"></i> COMPLETED
                            </button>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <!-- 7-Day Attendance Log Section -->
        <div class="pt-2 border-top flex-shrink-0 mt-auto" style="border-color: #f1f5f9 !important;">
            <div class="d-flex align-items-center justify-content-between mb-1.5">
                <span class="fs-11 text-uppercase fw-bold" style="color: #475569 !important; letter-spacing: 0.04em; font-weight: 800;">RECENT 7 DAYS ATTENDANCE LOG</span>
                <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.attendance.myAttendance') ? route('hrms.attendance.myAttendance') : url('/hrms/attendance/my-attendance') }}" class="fs-11 fw-bold text-decoration-none" style="color: #3b82f6;">Full Attendance Log &rarr;</a>
            </div>
            <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px;">
                @foreach(($recentPunches ?? []) as $punch)
                    @php
                        $isToday = $punch['is_today'] ?? false;
                        $status = strtolower($punch['status'] ?? 'unmarked');
                        
                        $badgeBg = '#f1f5f9';
                        $badgeColor = '#64748b';

                        if ($status === 'late') {
                            $badgeBg = '#fef3c7';
                            $badgeColor = '#d97706';
                            $statusLabel = 'Late';
                        } elseif ($status === 'present') {
                            $badgeBg = '#dcfce7';
                            $badgeColor = '#16a34a';
                            $statusLabel = 'Present';
                        } elseif ($status === 'absent') {
                            $badgeBg = '#fee2e2';
                            $badgeColor = '#ef4444';
                            $statusLabel = 'Absent';
                        } elseif ($status === 'off') {
                            $badgeBg = '#f1f5f9';
                            $badgeColor = '#64748b';
                            $statusLabel = 'Off';
                        } elseif ($status === 'half_day') {
                            $badgeBg = '#f3e8ff';
                            $badgeColor = '#9333ea';
                            $statusLabel = 'Half Day';
                        } elseif ($status === 'leave' || $status === 'on_leave') {
                            $badgeBg = '#e0f2fe';
                            $badgeColor = '#0284c7';
                            $statusLabel = 'Leave';
                        } else {
                            if ($isToday) {
                                if ($isClockedIn) {
                                    $badgeBg = '#dcfce7';
                                    $badgeColor = '#16a34a';
                                    $statusLabel = 'Present';
                                } else {
                                    $badgeBg = '#fef3c7';
                                    $badgeColor = '#d97706';
                                    $statusLabel = 'Late';
                                }
                            } else {
                                $badgeBg = '#fee2e2';
                                $badgeColor = '#ef4444';
                                $statusLabel = 'Absent';
                            }
                        }
                    @endphp
                    <div class="h-100">
                        <div class="p-1.5 p-md-2 text-center d-flex flex-column justify-content-between h-100" style="background: #ffffff; border: {{ $isToday ? '2px solid #818cf8' : '1px solid #eaeff5' }}; border-radius: 8px !important;">
                            <div class="text-uppercase mb-0.5" style="color: #475569; font-size: 10px; font-weight: 700; letter-spacing: 0.02em;">
                                {{ $punch['day_name'] ?? '—' }}
                            </div>
                            <div class="my-0.5" style="color: #0f172a; font-size: 16px; font-weight: 800; line-height: 1.1;">
                                {{ $punch['day_num'] ?? '—' }}
                            </div>
                            <div class="mt-0.5">
                                <span class="badge px-1 py-0.5 rounded fw-bold d-inline-block" style="background: {{ $badgeBg }}; color: {{ $badgeColor }}; font-size: 9.5px; font-weight: 700; min-width: 38px;">
                                    {{ $statusLabel }}
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        function updateLiveClock() {
            const now = new Date();
            let hours = now.getHours();
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? String(hours).padStart(2, '0') : '12';
            const timeStr = hours + ':' + minutes + ':' + seconds + ' ' + ampm;
            
            document.querySelectorAll('.live-web-clock').forEach(function(el) {
                el.textContent = timeStr;
            });
        }
        setInterval(updateLiveClock, 1000);
    })();
</script>
