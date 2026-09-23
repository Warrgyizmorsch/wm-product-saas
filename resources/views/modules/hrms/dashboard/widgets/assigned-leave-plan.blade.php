@php
    $leaveTypes = !empty($myLeaveTypesList) && count($myLeaveTypesList) > 0 ? $myLeaveTypesList : [
        ['name' => 'Casual Leave', 'code' => 'CL', 'remaining' => 12, 'allocated' => 12, 'color' => '#3b82f6'],
        ['name' => 'Sick Leave', 'code' => 'SL', 'remaining' => 12, 'allocated' => 12, 'color' => '#3b82f6'],
        ['name' => 'Earned Leave', 'code' => 'EL', 'remaining' => 18, 'allocated' => 18, 'color' => '#3b82f6'],
        ['name' => 'Unpaid Leave', 'code' => 'UL', 'remaining' => 0, 'allocated' => 0, 'color' => '#3b82f6'],
    ];
@endphp

<div class="card border-0 mb-0 h-100 shadow-sm" style="border-radius: 16px; background: #ffffff;">
    <div class="card-body p-4 d-flex flex-column justify-content-between">
        <div>
            <!-- Header: i  Assigned Leave Plan -->
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="fs-12 text-muted fw-bold d-inline-flex align-items-center justify-content-center" style="width: 16px; height: 16px; color: #94a3b8 !important;"><i class="feather-info"></i></span>
                <h6 class="fw-bold mb-0 text-dark" style="font-size: 16px; color: #1e293b !important; font-weight: 700;">Assigned Leave Plan</h6>
            </div>

            <!-- Standard Leave Plan Banner -->
            <div class="p-3.5 rounded-3 mb-4" style="background: #f0f3f8; border-radius: 12px; padding: 16px 20px;">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="fw-bold text-dark" style="font-size: 15px; color: #1e293b !important; font-weight: 700;">{{ $myAssignedPlan->name ?? 'Standard Leave Plan' }}</span>
                    <span class="badge px-2.5 py-1 fs-11 fw-semibold" style="background: #dcfce7; color: #16a34a; border-radius: 6px;">Active</span>
                </div>
                <span class="fs-12 text-muted d-block" style="color: #64748b !important; font-weight: 400; margin-top: 4px;">{{ $myAssignedPlan->description ?? 'Default starter leave plan' }}</span>
            </div>

            <!-- Effective From Header -->
            <div class="d-flex align-items-center justify-content-between fs-11 text-muted fw-bold mt-4 mb-3">
                <span class="text-uppercase" style="letter-spacing: 0.5px; color: #64748b !important; font-weight: 700; font-size: 11px;">EFFECTIVE FROM</span>
                <span class="text-dark fw-bold" style="color: #1e293b !important; font-weight: 700; font-size: 13px;">{{ date('d M, Y') }}</span>
            </div>

            <!-- Table Header & List -->
            <div class="mb-3">
                <!-- Header row -->
                <div class="d-flex align-items-center justify-content-between text-uppercase fs-11 text-muted fw-bold pb-2 mb-1" style="color: #64748b !important; font-weight: 700; letter-spacing: 0.5px; font-size: 11px;">
                    <div style="flex: 2;">TYPE NAME</div>
                    <div class="text-center" style="flex: 1;">BALANCE</div>
                    <div class="text-end" style="width: 48px;">RULES</div>
                </div>

                <!-- Rows -->
                <div class="d-flex flex-column gap-3 pt-1">
                    @foreach($leaveTypes as $lt)
                        <div class="d-flex align-items-center justify-content-between py-1">
                            <!-- Type Name & Code -->
                            <div class="d-flex align-items-center" style="flex: 2;">
                                <span class="d-inline-block rounded-circle me-2 flex-shrink-0" style="width: 8px; height: 8px; background-color: {{ $lt['color'] ?? '#3b82f6' }};"></span>
                                <span class="fw-bold text-dark fs-14" style="color: #1e293b !important; font-weight: 700;">{{ $lt['name'] }}</span>
                                <span class="fs-11 text-muted fw-normal ms-1" style="color: #94a3b8 !important; font-weight: 400;">{{ $lt['code'] }}</span>
                            </div>

                            <!-- Balance -->
                            <div class="text-center fw-bold text-dark fs-14" style="flex: 1; color: #1e293b !important; font-weight: 700;">
                                {{ $lt['remaining'] }} / {{ $lt['allocated'] }}
                            </div>

                            <!-- Rules Button -->
                            <div class="text-end" style="width: 48px;">
                                <button type="button" class="btn border-0 p-0 d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: #f1f3f9; color: #64748b; border-radius: 8px;" title="View Leave Rules">
                                    <i class="feather-sliders fs-13"></i>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex gap-3 pt-4">
            <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.leave_requests.index') ? route('hrms.leave_requests.index') : '#' }}" class="btn text-white fw-bold py-3 flex-fill text-uppercase fs-12 d-inline-flex align-items-center justify-content-center gap-1.5" style="background: var(--bs-primary, #6337fa) !important; color: #ffffff !important; border: none !important; border-radius: 10px !important; font-weight: 700 !important; font-size: 12px !important; letter-spacing: 0.5px !important;">
                <i class="feather-plus fs-14"></i> APPLY LEAVE
            </a>
            <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.leave_requests.index') ? route('hrms.leave_requests.index') : '#' }}" class="btn text-white fw-bold py-3 flex-fill text-uppercase fs-12 d-inline-flex align-items-center justify-content-center gap-1.5" style="background: var(--bs-primary, #6337fa) !important; color: #ffffff !important; border: none !important; border-radius: 10px !important; font-weight: 700 !important; font-size: 12px !important; letter-spacing: 0.5px !important;">
                <span class="fw-bold fs-14 me-0.5">$</span> ENCASHMENT
            </a>
        </div>
    </div>
</div>


