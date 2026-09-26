@php
    $defaultShiftName = $myShiftDetails['name'] ?? 'Day Shift';
    $pattern = !empty($myWeeklyPattern) && count($myWeeklyPattern) > 0 ? $myWeeklyPattern : [
        ['day' => 'SUN', 'status' => 'Day Off', 'is_off' => true],
        ['day' => 'MON', 'status' => $defaultShiftName, 'is_off' => false],
        ['day' => 'TUE', 'status' => $defaultShiftName, 'is_off' => false],
        ['day' => 'WED', 'status' => $defaultShiftName, 'is_off' => false],
        ['day' => 'THU', 'status' => $defaultShiftName, 'is_off' => false],
        ['day' => 'FRI', 'status' => $defaultShiftName, 'is_off' => false],
        ['day' => 'SAT', 'status' => $defaultShiftName, 'is_off' => false],
    ];
@endphp

<div class="card border-0 mb-0 h-100 shadow-sm" style="border-radius: 16px; background: #ffffff; overflow: hidden;">
    <div class="card-body p-3 p-md-3.5 d-flex flex-column justify-content-between h-100">
        <div>
            <!-- Header: Current Shift Details -->
            <div class="d-flex align-items-center gap-2 mb-2.5">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 28px; height: 28px; background: rgba(var(--bs-primary-rgb, 99, 55, 250), 0.1); border-radius: 8px; color: var(--bs-primary, #6337fa);">
                    <i class="feather-clock fs-13"></i>
                </div>
                <h6 class="fw-bold mb-0 text-dark fs-14" style="color: #0f172a !important;">Current Shift Details</h6>
            </div>

            <!-- Banner Box -->
            <div class="p-2.5 rounded-3 mb-2" style="background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 12px;">
                <div class="d-flex align-items-center justify-content-between mb-1.5">
                    <span class="fw-semibold text-dark fs-13" style="color: #1e293b !important;">{{ $myShiftDetails['name'] ?? 'Day Shift' }}</span>
                    <span class="badge px-2 py-0.5 fs-10 fw-semibold rounded-pill" style="background: #dcfce7; color: #15803d;">DAY</span>
                </div>
                <div class="fs-12 text-muted mb-1 d-flex align-items-center gap-2" style="color: #64748b !important;">
                    <i class="feather-clock me-1 text-muted fs-12"></i>Timing: <span class="fw-semibold text-dark ms-1" style="color: #1e293b !important;">{{ $myShiftDetails['timing'] ?? '09:00 - 18:00' }}</span>
                </div>
                <div class="fs-12 text-muted d-flex align-items-center gap-2" style="color: #64748b !important;">
                    <i class="feather-zap me-1 text-muted fs-12"></i>Overtime: <span class="fw-semibold ms-1 {{ ($myShiftDetails['is_ot_allowed'] ?? false) ? 'text-success' : 'text-danger' }}">{{ $myShiftDetails['overtime_status'] ?? 'Not Allowed' }}</span>
                </div>
            </div>

            <!-- Weekly Shift Pattern Header -->
            <div class="fs-11 text-uppercase fw-bold text-muted mt-2 mb-1.5" style="letter-spacing: 0.05em; color: #64748b !important; font-size: 11px;">
                WEEKLY SHIFT PATTERN
            </div>

            <div class="d-flex justify-content-between text-center mb-1" style="gap: 4px;">
                @foreach($pattern as $wp)
                    @php
                        $isOff = is_array($wp) ? ($wp['is_off'] ?? false) : ($wp->is_off ?? false);
                        $dayName = is_array($wp) ? ($wp['day'] ?? '—') : ($wp->day ?? '—');
                        $shiftStatus = is_array($wp) ? ($wp['status'] ?? ($isOff ? 'Day Off' : ($myShiftDetails['name'] ?? 'Day Shift'))) : ($wp->status ?? ($isOff ? 'Day Off' : ($myShiftDetails['name'] ?? 'Day Shift')));
                    @endphp
                    <div class="py-1 px-0.5 rounded-2 text-center" 
                         style="flex: 1 1 0; min-width: 0; overflow: hidden; {{ $isOff ? 'background: #fff5f5; border: 1px solid #fee2e2;' : 'background: #f0fdf4; border: 1px solid #dcfce7;' }}"
                         title="{{ $dayName }}: {{ $shiftStatus }}">
                        <div class="fs-10 fw-extrabold text-uppercase text-truncate" style="letter-spacing: 0; color: {{ $isOff ? '#dc2626' : '#166534' }};">{{ $dayName }}</div>
                        <div class="fw-bold text-truncate mt-0.5" style="font-size: 9.5px; color: {{ $isOff ? '#ef4444' : '#15803d' }};">{{ $shiftStatus }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex mt-auto pt-2" style="gap: 12px !important;">
            <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.shift-overtime.index') ? route('hrms.shift-overtime.index', ['tab' => 'shift', 'action' => 'apply_shift']) : url('/hrms/shift-overtime?tab=shift&action=apply_shift') }}" class="btn text-white fw-bold py-2 flex-fill text-uppercase fs-11 d-inline-flex align-items-center justify-content-center gap-1.5 shadow-2xs" style="background: var(--bs-primary, #6337fa) !important; color: #ffffff !important; border: none !important; border-radius: 10px !important; font-weight: 700 !important; font-size: 11px !important; letter-spacing: 0.03em !important;">
                <i class="feather-plus fs-12"></i> SHIFT CHANGE
            </a>
            <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.shift-overtime.index') ? route('hrms.shift-overtime.index', ['tab' => 'overtime', 'action' => 'apply_overtime']) : url('/hrms/shift-overtime?tab=overtime&action=apply_overtime') }}" class="btn text-white fw-bold py-2 flex-fill text-uppercase fs-11 d-inline-flex align-items-center justify-content-center gap-1.5 shadow-2xs" style="background: var(--bs-primary, #6337fa) !important; color: #ffffff !important; border: none !important; border-radius: 10px !important; font-weight: 700 !important; font-size: 11px !important; letter-spacing: 0.03em !important;">
                <i class="feather-plus fs-12"></i> OVERTIME
            </a>
        </div>
    </div>
</div>
