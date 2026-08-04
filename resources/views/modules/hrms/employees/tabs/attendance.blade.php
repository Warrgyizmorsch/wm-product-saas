<div class="tab-pane fade {{ $activeTabName === 'attendance' ? 'show active' : '' }}" id="attendance-pane" role="tabpanel" aria-labelledby="attendance-tab">
    
    <div class="row g-4">
        <!-- 1. Attendance Action Widget -->
        <div class="col-xl-4 col-lg-5">
            <div class="card-custom mb-4">
                <div class="card-custom-header">
                    <h5 class="card-custom-title"><i class="feather-clock text-primary"></i> Daily Attendance Console</h5>
                </div>
                <div class="card-body p-4 text-center">
                    
                    <!-- Dynamic Timer -->
                    <div class="my-3">
                        <div class="d-inline-block px-4 py-2 bg-light border rounded-pill mb-2">
                            <span id="attendance-clock" class="fs-28 fw-bold text-dark" style="font-family: 'Outfit', sans-serif; letter-spacing: 1px;">00:00:00</span>
                        </div>
                        <div id="attendance-date" class="fs-12 text-muted fw-semibold text-uppercase tracking-wider">...</div>
                    </div>

                    <!-- Current Status Badge -->
                    <div class="mb-4">
                        @if(!$todayAttendance)
                            <span class="badge bg-soft-secondary text-secondary px-3 py-2 fs-11 fw-bold text-uppercase tracking-wider">Checked Out</span>
                        @elseif($todayAttendance->isWorking() && !$todayAttendance->isOnBreak())
                            <span class="badge bg-soft-success text-success px-3 py-2 fs-11 fw-bold text-uppercase tracking-wider">Working &bull; Active</span>
                        @elseif($todayAttendance->isOnBreak())
                            <span class="badge bg-soft-warning text-warning px-3 py-2 fs-11 fw-bold text-uppercase tracking-wider text-dark">On Break</span>
                        @else
                            <span class="badge bg-soft-danger text-danger px-3 py-2 fs-11 fw-bold text-uppercase tracking-wider">Shift Completed</span>
                        @endif
                    </div>

                    <!-- Actions Form Console -->
                    <div class="bg-light border border-dashed p-4 rounded-4 mx-auto text-center" style="max-width: 320px;">
                        @if(!$todayAttendance)
                            <div class="avatar-md bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 48px; height: 48px;">
                                <i class="feather-log-in fs-22"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-2 fs-14">Shift Not Started</h6>
                            <form action="{{ route('hrms.attendance.check-in') }}" method="POST">
                                @csrf
                                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                                <button type="submit" class="btn btn-primary w-100 py-2 fs-13 fw-bold d-flex align-items-center justify-content-center gap-2">
                                    Check In Now
                                </button>
                            </form>
                        @elseif($todayAttendance->isWorking())
                            <div class="avatar-md bg-soft-success text-success rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 48px; height: 48px;">
                                <i class="feather-activity fs-22"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1 fs-14">Shift Active</h6>
                            <p class="fs-12 text-muted mb-3">Checked in at: <strong class="text-dark">{{ \Carbon\Carbon::parse($todayAttendance->check_in)->format('h:i A') }}</strong> ({{ ucfirst($todayAttendance->location_type) }})</p>
                            
                            <div class="d-flex flex-column gap-2">
                                @if($todayAttendance->isOnBreak())
                                    <!-- End Break Button -->
                                    <form action="{{ route('hrms.attendance.break-out', $todayAttendance->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-success w-100 py-2 fs-13 fw-bold d-flex align-items-center justify-content-center gap-2">
                                            End Break
                                        </button>
                                    </form>
                                @else
                                    <!-- Start Break Button -->
                                    <form action="{{ route('hrms.attendance.break-in', $todayAttendance->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-warning w-100 py-2 fs-13 fw-bold text-dark d-flex align-items-center justify-content-center gap-2">
                                            Start Break
                                        </button>
                                    </form>
                                @endif

                                <!-- Check Out Button -->
                                <form action="{{ route('hrms.attendance.check-out', $todayAttendance->id) }}" method="POST" class="mt-1">
                                    @csrf
                                    <button type="submit" class="btn btn-danger w-100 py-2 fs-13 fw-bold d-flex align-items-center justify-content-center gap-2">
                                        Check Out
                                    </button>
                                </form>
                            </div>
                        @else
                            <div class="avatar-md bg-soft-success text-success rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 48px; height: 48px;">
                                <i class="feather-check-circle fs-22"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1 fs-14">Shift Completed Today!</h6>
                            <span class="fs-12 text-muted">Checked out at: <strong class="text-dark">{{ \Carbon\Carbon::parse($todayAttendance->check_out)->format('h:i A') }}</strong></span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Attendance History Logs -->
        <div class="col-xl-8 col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Attendance Log History</h5>
                        <p class="text-muted fs-12 mb-0">Recent logs of check-ins, outs, and breaks</p>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="min-width: 600px;">
                            <thead class="bg-light text-uppercase fs-10 tracking-wider">
                                <tr>
                                    <th class="ps-4">Date & Location</th>
                                    <th>Check In</th>
                                    <th>Check Out</th>
                                    <th>Breaks</th>
                                    <th>Work Hours</th>
                                    <th class="pe-4">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($empAttendances as $log)
                                    <tr>
                                        <td class="ps-4 text-nowrap">
                                            <span class="fw-semibold text-dark d-block mb-1">{{ $log->date->format('M d, Y') }}</span>
                                            @if($log->location_type === 'office')
                                                <span class="badge bg-soft-primary text-primary border-0 fs-10 rounded-pill">Office</span>
                                            @elseif($log->location_type === 'wfh')
                                                <span class="badge bg-soft-success text-success border-0 fs-10 rounded-pill">WFH</span>
                                            @elseif($log->location_type === 'onsite')
                                                <span class="badge bg-soft-warning text-warning border-0 fs-10 rounded-pill">On-Site</span>
                                            @else
                                                <span class="badge bg-soft-dark text-slate border-0 fs-10 rounded-pill">{{ $log->formatted_location_type }}</span>
                                            @endif
                                        </td>
                                        <td class="text-muted fs-13">{{ \Carbon\Carbon::parse($log->check_in)->format('h:i A') }}</td>
                                        <td class="text-muted fs-13">
                                            {{ $log->check_out ? \Carbon\Carbon::parse($log->check_out)->format('h:i A') : 'Active' }}
                                        </td>
                                        <td class="fs-13">
                                            @if($log->breaks->isNotEmpty())
                                                @foreach($log->breaks as $index => $brk)
                                                    <div class="fs-11 text-muted" style="line-height: 1.4;">
                                                        {{ \Carbon\Carbon::parse($brk->break_in)->format('h:i A') }} - 
                                                        {{ $brk->break_out ? \Carbon\Carbon::parse($brk->break_out)->format('h:i A') : 'Active' }}
                                                        ({{ $brk->duration_minutes !== null ? $brk->duration_minutes . 'm' : 'Active' }})
                                                    </div>
                                                @endforeach
                                                @if($log->total_break_hours > 0)
                                                    <div class="fw-bold mt-1 text-dark" style="font-size: 11px;">Total: {{ $log->formatted_break_hours }}</div>
                                                @endif
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="fw-semibold text-dark fs-13">
                                            @if($log->check_out)
                                                {{ $log->formatted_work_hours }}
                                            @else
                                                <span class="text-success fw-normal">In progress</span>
                                            @endif
                                        </td>
                                        <td class="pe-4">
                                            @if($log->status === 'present')
                                                <span class="badge bg-soft-success text-success">Present</span>
                                            @elseif($log->status === 'late')
                                                <span class="badge bg-soft-warning text-warning">Late</span>
                                            @elseif($log->status === 'half_day')
                                                <span class="badge bg-soft-danger text-danger">Half Day</span>
                                            @elseif($log->status === 'under_hours')
                                                <span class="badge bg-soft-secondary text-slate">Under Hours</span>
                                            @else
                                                <span class="badge bg-soft-primary text-primary">{{ ucfirst($log->status) }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted fs-13">No attendance logs found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Digital Clock Update for UI Widget - tracks shift duration
    function startAttendanceClock() {
        const clockEl = document.getElementById('attendance-clock');
        const dateEl = document.getElementById('attendance-date');
        
        if (!clockEl || !dateEl) return;
        
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        dateEl.textContent = new Date().toLocaleDateString('en-US', options);
        
        @if(!$todayAttendance)
            // Case 1: Not checked in yet
            clockEl.textContent = "00:00:00";
        @elseif($todayAttendance && !$todayAttendance->check_out)
            // Case 2: Checked in, timer running
            const checkInTime = new Date("{{ $todayAttendance->check_in->toIso8601String() }}");
            
            const updateTimer = () => {
                const now = new Date();
                let diffMs = now - checkInTime;
                if (diffMs < 0) diffMs = 0;
                
                const totalSecs = Math.floor(diffMs / 1000);
                const hrs = String(Math.floor(totalSecs / 3600)).padStart(2, '0');
                const mins = String(Math.floor((totalSecs % 3600) / 60)).padStart(2, '0');
                const secs = String(totalSecs % 60).padStart(2, '0');
                
                clockEl.textContent = `${hrs}:${mins}:${secs}`;
            };
            
            updateTimer();
            setInterval(updateTimer, 1000);
        @else
            // Case 3: Checked out, show static total duration
            const checkInTime = new Date("{{ $todayAttendance->check_in->toIso8601String() }}");
            const checkOutTime = new Date("{{ $todayAttendance->check_out->toIso8601String() }}");
            
            let diffMs = checkOutTime - checkInTime;
            if (diffMs < 0) diffMs = 0;
            
            const totalSecs = Math.floor(diffMs / 1000);
            const hrs = String(Math.floor(totalSecs / 3600)).padStart(2, '0');
            const mins = String(Math.floor((totalSecs % 3600) / 60)).padStart(2, '0');
            const secs = String(totalSecs % 60).padStart(2, '0');
            
            clockEl.textContent = `${hrs}:${mins}:${secs}`;
        @endif
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        startAttendanceClock();
    });
</script>
