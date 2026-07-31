<div class="tab-pane fade {{ in_array($activeTabName, ['shift_overtime', 'shift-overtime']) ? 'show active' : '' }}" id="shift-overtime-pane" role="tabpanel" aria-labelledby="shift-overtime-tab">
    <div class="row g-4">
        <!-- LEFT COLUMN: Current Shift & Weekly Pattern Details -->
        <div class="col-lg-4 col-12 shift-left-col">
            <div class="card-custom">
                <div class="card-custom-header">
                    <h5 class="card-custom-title"><i class="feather-info text-primary me-1.5"></i> {{ __('hrms.shift_change.current_shift_details') }}</h5>
                </div>
                <div class="card-body p-3">
                    <!-- Default Shift Info -->
                    <div class="p-3 bg-light rounded border border-light-subtle">
                        <div class="d-flex align-items-center justify-content-between">
                            <h6 class="fw-bold mb-0 text-dark fs-14">
                                @if($employee->shift)
                                    {{ $employee->shift->name }}
                                @else
                                    {{ __('hrms.shift_change.no_shift_assigned') }}
                                @endif
                            </h6>
                            @if($employee->shift)
                                <span class="badge bg-success-subtle text-success border border-success-subtle fs-10 px-2 py-0.5 rounded">{{ __('hrms.shift_change.default') }}</span>
                            @endif
                        </div>
                        @if($employee->shift)
                            <p class="text-muted fs-12 mb-0 mt-1">
                                <i class="feather-clock me-1"></i> {{ __('hrms.shift_change.timing') }}: {{ substr($employee->shift->start_time, 0, 5) }} - {{ substr($employee->shift->end_time, 0, 5) }}
                            </p>
                            <p class="text-muted fs-12 mb-0 mt-1">
                                <i class="feather-zap me-1"></i> {{ __('hrms.shift_change.overtime') }}: 
                                @if($employee->shift->overtime_allowed)
                                    <span class="text-success fw-bold">{{ __('hrms.shift_change.allowed') }}</span>
                                @else
                                    <span class="text-danger fw-bold">{{ __('hrms.shift_change.not_allowed') }}</span>
                                @endif
                            </p>
                        @endif
                    </div>

                    <!-- Weekly Pattern Details -->
                    @if(isset($employee->weekly_pattern) && !empty($employee->weekly_pattern))
                        <div class="mt-3">
                            <span class="text-muted fs-11 text-uppercase fw-semibold mb-2 d-block">{{ __('hrms.shift_change.weekly_shift_pattern') }}</span>
                            <div class="list-group list-group-flush fs-12">
                                @php
                                    $dayNames = is_array(__('hrms.days')) ? __('hrms.days') : [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'];
                                @endphp
                                @foreach($dayNames as $dayNum => $dayName)
                                    @if(isset($employee->weekly_pattern[$dayNum]))
                                        @php
                                            $val = $employee->weekly_pattern[$dayNum];
                                            $patternShiftName = __('hrms.shift_change.off_day');
                                            if ($val !== 'off') {
                                                $ps = $shifts->firstWhere('id', $val);
                                                $patternShiftName = $ps ? $ps->name : 'Shift #' . $val;
                                            }
                                        @endphp
                                        <div class="d-flex justify-content-between align-items-center py-1">
                                            <span class="text-dark fw-medium">{{ $dayName }}</span>
                                            <span class="{{ $val === 'off' ? 'text-danger fw-semibold' : 'text-primary fw-semibold' }}">{{ $patternShiftName }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Action Buttons: Apply Shift Change & Apply Overtime -->
                    <div class="d-flex align-items-center gap-2 mt-3 pt-2 border-top">
                        <button type="button"
                            class="btn btn-sm btn-primary flex-grow-1 fw-bold text-uppercase d-flex align-items-center justify-content-center gap-1 px-2"
                            style="font-size: 11px; white-space: nowrap;"
                            data-bs-toggle="modal" data-bs-target="#empApplyShiftChangeModal">
                            <i class="feather-plus fs-12"></i> {{ __('hrms.shift_change.apply_shift_change') }}
                        </button>
                        <button type="button"
                            class="btn btn-sm btn-primary flex-grow-1 fw-bold text-uppercase d-flex align-items-center justify-content-center gap-1 px-2"
                            style="font-size: 11px; white-space: nowrap;"
                            data-bs-toggle="modal" data-bs-target="#empApplyOvertimeModal">
                            <i class="feather-plus fs-12"></i> {{ __('hrms.overtime.apply_overtime') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Shift Change Requests & Overtime Toggle Container -->
        <div class="col-lg-8 col-12 shift-right-col">
            <!-- MAIN CARD BOX -->
            <div class="card-custom">
                <div class="card-custom-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <!-- Shift Change Title -->
                        <div id="shiftAppsHeaderTitle" class="d-flex align-items-center gap-2">
                            <h5 class="card-custom-title mb-0">
                                <i class="feather-git-pull-request text-primary me-1.5"></i> {{ __('hrms.shift_change.title') }}
                            </h5>
                            <span class="badge bg-soft-primary text-primary rounded-pill px-2.5 py-1 fs-11 ms-1 fw-bold">
                                {{ $empShiftChangeRequests->count() }} {{ $empShiftChangeRequests->count() === 1 ? __('hrms.wfh.application') : __('hrms.wfh.applications') }}
                            </span>
                        </div>
                        <!-- Overtime Title -->
                        <div id="overtimeAppsHeaderTitle" class="d-flex align-items-center gap-2 d-none">
                            <h5 class="card-custom-title mb-0">
                                <i class="feather-clock text-primary me-1.5"></i> {{ __('hrms.overtime.title') }}
                            </h5>
                            <span class="badge bg-soft-primary text-primary rounded-pill px-2.5 py-1 fs-11 ms-1 fw-bold">
                                {{ $empOvertimeRequests->count() }} {{ $empOvertimeRequests->count() === 1 ? __('hrms.wfh.application') : __('hrms.wfh.applications') }}
                            </span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <!-- View Toggle Button -->
                        <x-ui.button 
                            type="button" 
                            id="btnToggleShiftOvertimeView" 
                            variant="soft-primary" 
                            size="sm" 
                            class="fw-bold text-uppercase" 
                            style="font-size: 11px;"
                        >
                            <span id="toggleShiftOvertimeBtnLabel"><i class="feather-clock me-1"></i> {{ __('hrms.shift_change.overtime_details') }}</span>
                        </x-ui.button>
                    </div>
                </div>
                <div class="card-body p-0">
                     <!-- 1. SHIFT APPLICATIONS VIEW -->
                    <div id="shiftApplicationsViewContainer">
                        <div>
                            <table class="table table-hover align-middle mb-0" style="width:100%; table-layout: fixed;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:13%; white-space:nowrap;">{{ __('hrms.shift_change.type') }}</th>
                                        <th style="width:14%; white-space:nowrap;">{{ __('hrms.shift_change.effective_period') }}</th>
                                        <th style="width:20%; white-space:nowrap;">{{ __('hrms.shift_change.current_shift') }}</th>
                                        <th style="width:18%; white-space:nowrap;">{{ __('hrms.shift_change.requested_shift') }}</th>
                                        <th style="width:10%; white-space:nowrap;">{{ __('hrms.shift_change.status') }}</th>
                                        <th class="text-end" style="width:25%; white-space:nowrap;">{{ __('hrms.shift_change.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($empShiftChangeRequests as $req)
                                        <tr>
                                            <td>
                                                <span class="badge text-uppercase fs-10" style="background-color: {{ $req->type === 'permanent' ? 'rgba(25, 135, 84, 0.1)' : ($req->type === 'recurring' ? 'rgba(13, 110, 253, 0.1)' : 'rgba(108, 117, 125, 0.1)') }}; color: {{ $req->type === 'permanent' ? '#198754' : ($req->type === 'recurring' ? '#0d6efd' : '#6c757d') }};">
                                                    {{ __('hrms.shift_change.type_' . $req->type) }}
                                                </span>
                                                @if($req->type === 'recurring' && is_array($req->recurring_days))
                                                    @php
                                                        $dayNames = is_array(__('hrms.days')) ? __('hrms.days') : [0 => 'Sun', 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat'];
                                                        $mapped = array_map(fn($d) => $dayNames[$d] ?? '', $req->recurring_days);
                                                    @endphp
                                                    <div class="fs-10 text-muted mt-1 fw-medium" style="max-width: 120px; line-height: 1.2;">{{ implode(', ', $mapped) }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="fw-medium text-dark">{{ $req->start_date->format('d M Y') }}</div>
                                                @if($req->type === 'temporary' && $req->end_date)
                                                    <div class="text-muted fs-11">{{ __('hrms.shift_change.to') }} {{ $req->end_date->format('d M Y') }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                @if($req->currentShift)
                                                    <div class="fw-medium text-dark">{{ $req->currentShift->name }}</div>
                                                @else
                                                    <span class="badge bg-soft-secondary text-secondary">{{ __('hrms.shift_change.day_off') }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($req->requestedShift)
                                                    <div class="fw-medium text-dark">{{ $req->requestedShift->name }}</div>
                                                @else
                                                    <span class="badge bg-soft-secondary text-secondary">{{ __('hrms.shift_change.day_off') }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge text-uppercase fs-10 px-2 py-1" style="white-space: nowrap; background-color: {{ $req->status === 'approved' ? 'rgba(25, 135, 84, 0.1)' : ($req->status === 'rejected' ? 'rgba(220, 53, 69, 0.1)' : 'rgba(255, 193, 7, 0.1)') }}; color: {{ $req->status === 'approved' ? '#198754' : ($req->status === 'rejected' ? '#dc3545' : '#ffc107') }};">
                                                    {{ __('hrms.shift_change.' . $req->status) }}
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <div class="d-flex align-items-center justify-content-end flex-nowrap gap-2">
                                                    <div class="dropdown {{ ($loop->last || ($loop->count > 1 && $loop->iteration >= $loop->count - 1)) ? 'dropup' : '' }} d-inline-block position-relative">
                                                        <button class="btn btn-sm dropdown-toggle py-1 px-3 d-inline-flex align-items-center justify-content-between text-capitalize fw-semibold shadow-sm btn-status-dropdown text-white" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" style="white-space:nowrap;">
                                                            <span>{{ __('hrms.shift_change.' . $req->status) }}</span>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end status-dropdown-menu">
                                                            <li>
                                                                <button type="button" class="dropdown-item {{ $req->status === 'approved' ? 'active-status' : '' }}" onclick="handleEmpShiftDecision('approve', {{ $req->id }})">
                                                                    {{ __('hrms.shift_change.approved') }}
                                                                </button>
                                                            </li>
                                                            <li>
                                                                <button type="button" class="dropdown-item {{ $req->status === 'rejected' ? 'active-status' : '' }}" onclick="handleEmpShiftDecision('reject', {{ $req->id }})">
                                                                    {{ __('hrms.shift_change.rejected') }}
                                                                </button>
                                                            </li>
                                                            <li>
                                                                <button type="button" class="dropdown-item {{ $req->status === 'pending' ? 'active-status' : '' }}" onclick="handleEmpShiftDecision('pending', {{ $req->id }})">
                                                                    {{ __('hrms.shift_change.pending') }}
                                                                </button>
                                                            </li>
                                                        </ul>
                                                    </div>

                                                    <form action="{{ route('hrms.shift-change.destroy', $req->id) }}" method="POST" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.shift_change.confirm_delete') }}', { title: '{{ __('hrms.shift_change.delete_application') }}', variant: 'danger', confirmButtonText: '{{ __('hrms.shift_change.delete_btn') }}' });" class="d-inline m-0">
                                                        @csrf
                                                        @method('DELETE')
                                                        @if($req->status === 'approved')
                                                            <button type="button" class="btn btn-sm btn-light border disabled"
                                                                    title="{{ __('hrms.shift_change.approved_no_delete') }}"
                                                                    style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;"
                                                                    disabled>
                                                                <i class="feather-trash-2 fs-14"></i>
                                                            </button>
                                                        @else
                                                            <button type="submit" class="btn btn-sm btn-soft-danger border"
                                                                    title="{{ __('hrms.shift_change.delete_application') }}"
                                                                    style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                                                                <i class="feather-trash-2 fs-14"></i>
                                                            </button>
                                                        @endif
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted fs-13">{{ __('hrms.shift_change.no_requests_found') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- 2. OVERTIME APPLICATIONS VIEW -->
                    <div id="overtimeApplicationsViewContainer" class="d-none">
                        <div>
                            <table class="table table-hover align-middle mb-0" style="width:100%; table-layout: fixed;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:13%; white-space:nowrap;">{{ __('hrms.overtime.date') }}</th>
                                        <th style="width:16%; white-space:nowrap;">{{ __('hrms.overtime.time_frame') }}</th>
                                        <th style="width:12%; white-space:nowrap;">{{ __('hrms.overtime.req_hours') }}</th>
                                        <th style="width:11%; white-space:nowrap;">{{ __('hrms.overtime.app_hours') }}</th>
                                        <th style="width:13%; white-space:nowrap;">{{ __('hrms.overtime.comp_type') }}</th>
                                        <th style="width:13%; white-space:nowrap;">{{ __('hrms.overtime.status') }}</th>
                                        <th class="text-end" style="width:22%; white-space:nowrap;">{{ __('hrms.overtime.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($empOvertimeRequests as $req)
                                        <tr>
                                            <td>
                                                <div class="fw-medium text-dark" style="white-space: nowrap;">{{ $req->date->format('d M Y') }}</div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark">{{ substr($req->start_time, 0, 5) }} - {{ substr($req->end_time, 0, 5) }}</div>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark">{{ number_format($req->duration_hours, 1) }} {{ __('hrms.overtime.hrs') }}</div>
                                            </td>
                                            <td>
                                                @if($req->status === 'approved' && $req->approved_duration_hours !== null)
                                                    <div class="fw-bold text-success">{{ number_format($req->approved_duration_hours, 1) }} {{ __('hrms.overtime.hrs') }}</div>
                                                @else
                                                    <span class="text-muted fs-11">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge text-uppercase fs-10" style="background-color: {{ $req->compensation_type === 'comp_off' ? 'rgba(13, 110, 253, 0.1)' : 'rgba(25, 135, 84, 0.1)' }}; color: {{ $req->compensation_type === 'comp_off' ? '#0d6efd' : '#198754' }};">
                                                    {{ $req->compensation_type === 'comp_off' ? __('hrms.overtime.comp_off') : __('hrms.overtime.payout') }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge text-uppercase fs-10 px-2 py-1" style="white-space: nowrap; background-color: {{ $req->status === 'approved' ? 'rgba(25, 135, 84, 0.1)' : ($req->status === 'rejected' ? 'rgba(220, 53, 69, 0.1)' : 'rgba(255, 193, 7, 0.1)') }}; color: {{ $req->status === 'approved' ? '#198754' : ($req->status === 'rejected' ? '#dc3545' : '#ffc107') }};">
                                                    {{ __('hrms.overtime.' . $req->status) }}
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <div class="d-flex align-items-center justify-content-end flex-nowrap gap-2">
                                                    <div class="dropdown {{ ($loop->last || ($loop->count > 1 && $loop->iteration >= $loop->count - 1)) ? 'dropup' : '' }} d-inline-block position-relative">
                                                        <button class="btn btn-sm dropdown-toggle py-1 px-3 d-inline-flex align-items-center justify-content-between text-capitalize fw-semibold shadow-sm btn-status-dropdown text-white" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" style="white-space:nowrap;">
                                                            <span>{{ __('hrms.overtime.' . $req->status) }}</span>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end status-dropdown-menu">
                                                            <li>
                                                                <button type="button" class="dropdown-item {{ $req->status === 'approved' ? 'active-status' : '' }}" onclick="handleEmpOvertimeDecision('approve', {{ $req->id }}, {{ $req->duration_hours }})">
                                                                    {{ __('hrms.overtime.approved') }}
                                                                </button>
                                                            </li>
                                                            <li>
                                                                <button type="button" class="dropdown-item {{ $req->status === 'rejected' ? 'active-status' : '' }}" onclick="handleEmpOvertimeDecision('reject', {{ $req->id }}, {{ $req->duration_hours }})">
                                                                    {{ __('hrms.overtime.rejected') }}
                                                                </button>
                                                            </li>
                                                            <li>
                                                                <button type="button" class="dropdown-item {{ $req->status === 'pending' ? 'active-status' : '' }}" onclick="handleEmpOvertimeDecision('pending', {{ $req->id }}, {{ $req->duration_hours }})">
                                                                    {{ __('hrms.overtime.pending') }}
                                                                </button>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                    <form action="{{ route('hrms.overtime.destroy', $req->id) }}" method="POST" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.overtime.confirm_delete') }}', { title: '{{ __('hrms.overtime.delete_application') }}', variant: 'danger', confirmButtonText: '{{ __('hrms.overtime.delete_btn') }}' });" class="d-inline m-0">
                                                        @csrf
                                                        @method('DELETE')
                                                        @if($req->status === 'approved')
                                                            <button type="button" class="btn btn-sm btn-light border disabled"
                                                                    title="{{ __('hrms.overtime.approved_no_delete') }}"
                                                                    style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;"
                                                                    disabled>
                                                                <i class="feather-trash-2 fs-14"></i>
                                                            </button>
                                                        @else
                                                            <button type="submit" class="btn btn-sm btn-soft-danger border"
                                                                    title="{{ __('hrms.overtime.delete_application') }}"
                                                                    style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                                                                <i class="feather-trash-2 fs-14"></i>
                                                            </button>
                                                        @endif
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted fs-13">{{ __('hrms.overtime.no_requests_found') }}</td>
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
</div>

<div class="modal fade" id="empApplyShiftChangeModal" tabindex="-1" aria-labelledby="empApplyShiftChangeModalLabel" aria-hidden="true" data-bs-backdrop="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('hrms.shift-change.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title fw-bold" id="empApplyShiftChangeModalLabel">Apply Shift Change</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="select" label="Type" name="type" id="profile_shift_change_type" :required="true">
                           <option value="temporary">Temporary (Date Range)</option>
                           <option value="permanent">Permanent (Effective Date onwards)</option>
                           <option value="recurring">Recurring Weekdays</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="input" inputType="date" label="Start Date" name="start_date" id="profile_shift_start_date" :required="true" class="odoo-underline-input" value="{{ date('Y-m-d') }}" />
                    </div>

                    <div class="mb-3" id="profile_end_date_container">
                        <x-ui.odoo-form-ui type="input" inputType="date" label="End Date" name="end_date" id="profile_shift_end_date" :required="false" class="odoo-underline-input" value="{{ date('Y-m-d') }}" />
                    </div>

                    <div class="mb-3 d-none" id="profile_recurring_days_container">
                        <label class="form-label fw-bold d-block text-dark fs-12 mb-2">Recurring Weekdays</label>
                        <div class="d-flex flex-wrap gap-2">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="recurring_days[]" value="1" id="profile_day_mon">
                                <label class="form-check-label fs-12 text-muted" for="profile_day_mon">Mon</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="recurring_days[]" value="2" id="profile_day_tue">
                                <label class="form-check-label fs-12 text-muted" for="profile_day_tue">Tue</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="recurring_days[]" value="3" id="profile_day_wed">
                                <label class="form-check-label fs-12 text-muted" for="profile_day_wed">Wed</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="recurring_days[]" value="4" id="profile_day_thu">
                                <label class="form-check-label fs-12 text-muted" for="profile_day_thu">Thu</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="recurring_days[]" value="5" id="profile_day_fri">
                                <label class="form-check-label fs-12 text-muted" for="profile_day_fri">Fri</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="recurring_days[]" value="6" id="profile_day_sat">
                                <label class="form-check-label fs-12 text-muted" for="profile_day_sat">Sat</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="recurring_days[]" value="0" id="profile_day_sun">
                                <label class="form-check-label fs-12 text-muted" for="profile_day_sun">Sun</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="select" label="Requested Shift" name="requested_shift_id" id="profile_shift_requested_shift_id" :required="false">
                            <option value="">Select Shift (Empty for Day Off)</option>
                            @foreach($shifts as $sf)
                                @if((int)$sf->id !== (int)$employee->shift_id)
                                    <option value="{{ $sf->id }}">{{ $sf->name }} ({{ substr($sf->start_time, 0, 5) }} - {{ substr($sf->end_time, 0, 5) }})</option>
                                @endif
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="textarea" label="Reason" name="reason" id="profile_shift_reason" :required="true" class="odoo-underline-input" placeholder="Describe the reason for change..." />
                    </div>

                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="file" label="Attachment (Optional)" name="attachment" id="profile_shift_attachment" :required="false" />
                    </div>
                </div>
                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="empApplyOvertimeModal" tabindex="-1" aria-labelledby="empApplyOvertimeModalLabel" aria-hidden="true" data-bs-backdrop="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('hrms.overtime.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title fw-bold" id="empApplyOvertimeModalLabel">Apply Overtime</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="input" inputType="date" label="Date" name="date" id="profile_ot_date" :required="true" class="odoo-underline-input" value="{{ date('Y-m-d') }}" />
                    </div>

                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="input" inputType="time" label="{{ __('hrms.overtime.start_time') }}" name="start_time" id="profile_ot_start_time" :required="true" class="odoo-underline-input" value="18:00" />
                    </div>

                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="input" inputType="time" label="{{ __('hrms.overtime.end_time') }}" name="end_time" id="profile_ot_end_time" :required="true" class="odoo-underline-input" value="20:00" />
                    </div>

                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="select" label="Compensation" name="compensation_type" id="profile_ot_compensation_type" :required="true">
                            <option value="payout">Payout (Financial Payout)</option>
                            <option value="comp_off">Comp-Off (Credit to Leave Balance)</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="textarea" label="Reason" name="reason" id="profile_ot_reason" :required="true" class="odoo-underline-input" placeholder="Describe details of work performed..." />
                    </div>

                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="file" label="Attachment (Optional)" name="attachment" id="profile_ot_attachment" :required="false" />
                    </div>
                </div>
                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Submit Application</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="empApproveOvertimeModal" tabindex="-1" aria-labelledby="empApproveOvertimeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="empApproveOvertimeModalLabel">Approve Overtime</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Approved Hours</label>
                    <input type="number" id="empApproveHoursInput" class="form-control" step="0.5" min="0.5" placeholder="e.g. 2.0">
                    <div class="form-text text-muted">Enter the actual hours to approve (can differ from requested hours).</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmEmpApproveBtn">Approve</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="empRejectOvertimeModal" tabindex="-1" aria-labelledby="empRejectOvertimeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="empRejectOvertimeModalLabel">Reject Overtime</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Rejection Reason <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea id="empRejectReasonInput" class="form-control" rows="3" placeholder="Enter reason for rejection..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmEmpRejectBtn">Reject</button>
            </div>
        </div>
    </div>
</div>
