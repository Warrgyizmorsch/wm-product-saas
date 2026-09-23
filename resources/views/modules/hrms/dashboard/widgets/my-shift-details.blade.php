<div class="card border-0 mb-0 h-100 shadow-sm" style="border-radius: 16px; background: #ffffff;">
    <div class="card-body p-4 d-flex flex-column justify-content-between">
        <div>
            <!-- Header: i  Current Shift Details -->
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="fs-12 text-muted fw-bold d-inline-flex align-items-center justify-content-center" style="width: 16px; height: 16px; color: #94a3b8 !important;"><i class="feather-info"></i></span>
                <h6 class="fw-bold mb-0 text-dark" style="font-size: 16px; color: #1e293b !important; font-weight: 700;">Current Shift Details</h6>
            </div>

            <!-- Banner Box -->
            <div class="p-3.5 rounded-3 mb-4" style="background: #f0f3f8; border-radius: 12px; padding: 16px 20px;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fw-bold text-dark" style="font-size: 15px; color: #1e293b !important; font-weight: 700;">{{ $myShiftDetails['name'] ?? 'Day Shift' }}</span>
                    <span class="badge px-2.5 py-1 fs-11 fw-semibold" style="background: #dcfce7; color: #16a34a; border-radius: 6px;">DAY</span>
                </div>
                <div class="fs-12 text-muted mb-1" style="color: #64748b !important;">
                    <i class="feather-clock me-1.5 text-muted"></i>Timing: <strong class="text-dark ms-1" style="color: #1e293b !important;">{{ $myShiftDetails['timing'] ?? '09:00 - 18:00' }}</strong>
                </div>
                <div class="fs-12 text-muted" style="color: #64748b !important;">
                    <i class="feather-zap me-1.5 text-muted"></i>Overtime: <strong class="ms-1 {{ ($myShiftDetails['is_ot_allowed'] ?? false) ? 'text-success' : 'text-danger' }}">{{ $myShiftDetails['overtime_status'] ?? 'Not Allowed' }}</strong>
                </div>
            </div>

            <!-- Weekly Shift Pattern Header -->
            <div class="fs-11 text-uppercase fw-bold text-muted mt-4 mb-3" style="letter-spacing: 0.5px; color: #64748b !important; font-size: 11px;">
                WEEKLY SHIFT PATTERN
            </div>

            <div class="d-flex gap-1.5 justify-content-between text-center mb-3">
                @foreach(($myWeeklyPattern ?? []) as $wp)
                    <div class="p-2 border rounded-2 flex-fill {{ ($wp['is_off'] ?? false) ? 'bg-light text-danger' : 'bg-soft-success text-success border-success border-opacity-20' }}">
                        <div class="fs-10 fw-bold text-uppercase">{{ $wp['day'] }}</div>
                        <div class="fs-11 fw-bolder mt-0.5">{{ ($wp['is_off'] ?? false) ? 'Day Off' : 'Shift' }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="d-flex gap-3 pt-3">
            <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.attendance.index') ? route('hrms.attendance.index') : '#' }}" class="btn text-white fw-bold py-3 flex-fill text-uppercase fs-12 d-inline-flex align-items-center justify-content-center gap-1.5" style="background: var(--bs-primary, #6337fa) !important; color: #ffffff !important; border: none !important; border-radius: 10px !important; font-weight: 700 !important; font-size: 12px !important; letter-spacing: 0.5px !important;">
                <i class="feather-plus me-1"></i> SHIFT CHANGE
            </a>
            <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.attendance.index') ? route('hrms.attendance.index') : '#' }}" class="btn text-white fw-bold py-3 flex-fill text-uppercase fs-12 d-inline-flex align-items-center justify-content-center gap-1.5" style="background: var(--bs-primary, #6337fa) !important; color: #ffffff !important; border: none !important; border-radius: 10px !important; font-weight: 700 !important; font-size: 12px !important; letter-spacing: 0.5px !important;">
                <i class="feather-plus me-1"></i> OVERTIME
            </a>
        </div>
    </div>
</div>

