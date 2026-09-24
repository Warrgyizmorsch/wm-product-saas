@php
    $defaultShiftName = $myShiftDetails['name'] ?? 'Day Shift';
    $pattern = !empty($myWeeklyPattern) && count($myWeeklyPattern) > 0 ? $myWeeklyPattern : [
        ['day' => 'SUN', 'status' => 'Day Off', 'is_off' => true],
        ['day' => 'MON', 'status' => $defaultShiftName, 'is_off' => false],
        ['day' => 'TUE', 'status' => $defaultShiftName, 'is_off' => false],
        ['day' => 'WED', 'status' => $defaultShiftName, 'is_off' => false],
        ['day' => 'THU', 'status' => $defaultShiftName, 'is_off' => false],
        ['day' => 'FRI', 'status' => $defaultShiftName, 'is_off' => false],
        ['day' => 'SAT', 'status' => 'Day Off', 'is_off' => true],
    ];
@endphp

<div class="card border-0 mb-0 h-100 shadow-sm" style="border-radius: 16px; background: #ffffff;">
    <div class="card-body p-4 d-flex flex-column">
        <div>
            <!-- Header: Current Shift Details -->
            <div class="d-flex align-items-center gap-2.5 mb-3" style="gap: 10px;">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; background: #eef2ff; border-radius: 8px; color: #4f46e5;">
                    <i class="feather-clock fs-14"></i>
                </div>
                <h6 class="fw-bold mb-0 text-dark" style="font-size: 16px; color: #1e293b !important; font-weight: 700;">Current Shift Details</h6>
            </div>

            <!-- Banner Box -->
            <div class="p-3 rounded-3 mb-3" style="background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 12px;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fw-bold text-dark" style="font-size: 15px; color: #1e293b !important; font-weight: 700;">{{ $myShiftDetails['name'] ?? 'Day Shift' }}</span>
                    <span class="badge px-2.5 py-1 fs-11 fw-bold rounded-2" style="background: #dcfce7; color: #15803d;">DAY</span>
                </div>
                <div class="fs-12 text-muted mb-1 d-flex align-items-center gap-2" style="color: #64748b !important;">
                    <i class="feather-clock me-2 text-muted fs-13"></i>Timing: <strong class="text-dark ms-1" style="color: #1e293b !important;">{{ $myShiftDetails['timing'] ?? '09:00 - 18:00' }}</strong>
                </div>
                <div class="fs-12 text-muted d-flex align-items-center gap-2" style="color: #64748b !important;">
                    <i class="feather-zap me-2 text-muted fs-13"></i>Overtime: <strong class="ms-1 {{ ($myShiftDetails['is_ot_allowed'] ?? false) ? 'text-success' : 'text-danger' }}">{{ $myShiftDetails['overtime_status'] ?? 'Not Allowed' }}</strong>
                </div>
            </div>

            <!-- Weekly Shift Pattern Header -->
            <div class="fs-11 text-uppercase fw-bold text-muted mt-3 mb-2" style="letter-spacing: 0.05em; color: #64748b !important; font-size: 11px;">
                WEEKLY SHIFT PATTERN
            </div>

            <div class="d-flex justify-content-between text-center mb-2" style="gap: 3px;">
                @foreach($pattern as $wp)
                    @php
                        $isOff = is_array($wp) ? ($wp['is_off'] ?? false) : ($wp->is_off ?? false);
                        $dayName = is_array($wp) ? ($wp['day'] ?? '—') : ($wp->day ?? '—');
                        $shiftStatus = is_array($wp) ? ($wp['status'] ?? ($isOff ? 'Day Off' : ($myShiftDetails['name'] ?? 'Day Shift'))) : ($wp->status ?? ($isOff ? 'Day Off' : ($myShiftDetails['name'] ?? 'Day Shift')));
                    @endphp
                    <div class="py-1.5 px-0.5 rounded-2 text-center" 
                         style="flex: 1 1 0; min-width: 0; overflow: hidden; {{ $isOff ? 'background: #fff5f5; border: 1px solid #fee2e2;' : 'background: #f0fdf4; border: 1px solid #dcfce7;' }}"
                         title="{{ $dayName }}: {{ $shiftStatus }}">
                        <div class="fs-10 fw-extrabold text-uppercase text-truncate" style="letter-spacing: 0; color: {{ $isOff ? '#dc2626' : '#166534' }};">{{ $dayName }}</div>
                        <div class="fw-bold text-truncate mt-0.5" style="font-size: 9.5px; color: {{ $isOff ? '#ef4444' : '#15803d' }};">{{ $shiftStatus }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex mt-3 pt-2" style="gap: 16px !important;">
            <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.shift-overtime.index') ? route('hrms.shift-overtime.index', ['tab' => 'shift', 'action' => 'apply_shift']) : url('/hrms/shift-overtime?tab=shift&action=apply_shift') }}" class="btn text-white fw-bold py-2.5 flex-fill text-uppercase fs-12 d-inline-flex align-items-center justify-content-center gap-1.5 shadow-2xs" style="background: #4a3838 !important; color: #ffffff !important; border: none !important; border-radius: 10px !important; font-weight: 700 !important; font-size: 12px !important; letter-spacing: 0.03em !important;">
                <i class="feather-plus fs-13"></i> SHIFT CHANGE
            </a>
            <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.shift-overtime.index') ? route('hrms.shift-overtime.index', ['tab' => 'overtime', 'action' => 'apply_overtime']) : url('/hrms/shift-overtime?tab=overtime&action=apply_overtime') }}" class="btn text-white fw-bold py-2.5 flex-fill text-uppercase fs-12 d-inline-flex align-items-center justify-content-center gap-1.5 shadow-2xs" style="background: #4a3838 !important; color: #ffffff !important; border: none !important; border-radius: 10px !important; font-weight: 700 !important; font-size: 12px !important; letter-spacing: 0.03em !important;">
                <i class="feather-plus fs-13"></i> OVERTIME
            </a>
        </div>
    </div>
</div>
