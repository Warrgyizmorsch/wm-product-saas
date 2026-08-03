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
            <!-- ABOVE THE CARD: Search, Sort, Filter Row -->
            <div class="d-flex align-items-center justify-content-end mb-3 gap-2 flex-wrap">
                {{-- Shift Apps Toolbar --}}
                <div id="shiftAppsToolbar" class="d-flex align-items-center gap-2">
                    <div class="d-flex align-items-center border rounded px-3 py-1" style="background:#f1f5f9; min-width: 180px; max-width: 240px; height: 38px; border-color: #e2e8f0 !important;">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" id="empShiftAppSearchInput" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search..." style="box-shadow: none; height: 32px;" autocomplete="off">
                    </div>
                    <x-ui.sort-dropdown label="SORT">
                        <a class="dropdown-item py-2 d-flex align-items-center emp-shift-app-sort-link active" href="#" onclick="event.preventDefault();" data-sort="date_desc">
                            <span>Newest First</span><i class="feather-check text-dark ms-auto shift-sort-check"></i>
                        </a>
                        <a class="dropdown-item py-2 d-flex align-items-center emp-shift-app-sort-link" href="#" onclick="event.preventDefault();" data-sort="date_asc">
                            <span>Oldest First</span><i class="feather-check text-dark ms-auto shift-sort-check d-none"></i>
                        </a>
                    </x-ui.sort-dropdown>
                    <x-ui.filter label="FILTER" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        <form id="empShiftAppFilterForm" onsubmit="return false;">
                            <div class="mb-3" style="min-width: 220px;">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                                <x-ui.odoo-form-ui type="select" name="status" id="empShiftAppFilterStatus">
                                    <option value="">All Statuses</option>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="mb-3" style="min-width: 220px;">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Type</label>
                                <x-ui.odoo-form-ui type="select" name="type" id="empShiftAppFilterType">
                                    <option value="">All Types</option>
                                    <option value="temporary">Temporary</option>
                                    <option value="permanent">Permanent</option>
                                    <option value="recurring">Recurring</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="dropdown-divider my-3"></div>
                            <div class="d-flex gap-2">
                                <x-ui.button type="button" id="btnEmpShiftAppFilterApply" variant="primary" size="sm" class="flex-grow-1">Apply</x-ui.button>
                                <x-ui.button type="button" id="btnEmpShiftAppFilterReset" variant="light" size="sm" class="border flex-grow-1">Reset</x-ui.button>
                            </div>
                        </form>
                    </x-ui.filter>
                </div>

                {{-- Overtime Apps Toolbar --}}
                <div id="overtimeAppsToolbar" class="d-flex align-items-center gap-2 d-none">
                    <div class="d-flex align-items-center border rounded px-3 py-1" style="background:#f1f5f9; min-width: 180px; max-width: 240px; height: 38px; border-color: #e2e8f0 !important;">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" id="empOvertimeAppSearchInput" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search..." style="box-shadow: none; height: 32px;" autocomplete="off">
                    </div>
                    <x-ui.sort-dropdown label="SORT">
                        <a class="dropdown-item py-2 d-flex align-items-center emp-overtime-app-sort-link active" href="#" onclick="event.preventDefault();" data-sort="date_desc">
                            <span>Newest First</span><i class="feather-check text-dark ms-auto ot-sort-check"></i>
                        </a>
                        <a class="dropdown-item py-2 d-flex align-items-center emp-overtime-app-sort-link" href="#" onclick="event.preventDefault();" data-sort="date_asc">
                            <span>Oldest First</span><i class="feather-check text-dark ms-auto ot-sort-check d-none"></i>
                        </a>
                        <a class="dropdown-item py-2 d-flex align-items-center emp-overtime-app-sort-link" href="#" onclick="event.preventDefault();" data-sort="hours_desc">
                            <span>Hours (High to Low)</span><i class="feather-check text-dark ms-auto ot-sort-check d-none"></i>
                        </a>
                        <a class="dropdown-item py-2 d-flex align-items-center emp-overtime-app-sort-link" href="#" onclick="event.preventDefault();" data-sort="hours_asc">
                            <span>Hours (Low to High)</span><i class="feather-check text-dark ms-auto ot-sort-check d-none"></i>
                        </a>
                    </x-ui.sort-dropdown>
                    <x-ui.filter label="FILTER" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        <form id="empOvertimeAppFilterForm" onsubmit="return false;">
                            <div class="mb-3" style="min-width: 220px;">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                                <x-ui.odoo-form-ui type="select" name="status" id="empOvertimeAppFilterStatus">
                                    <option value="">All Statuses</option>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="mb-3" style="min-width: 220px;">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Compensation</label>
                                <x-ui.odoo-form-ui type="select" name="compensation_type" id="empOvertimeAppFilterCompensation">
                                    <option value="">All Compensation Types</option>
                                    <option value="payout">Payout</option>
                                    <option value="comp_off">Comp-Off</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="dropdown-divider my-3"></div>
                            <div class="d-flex gap-2">
                                <x-ui.button type="button" id="btnEmpOvertimeAppFilterApply" variant="primary" size="sm" class="flex-grow-1">Apply</x-ui.button>
                                <x-ui.button type="button" id="btnEmpOvertimeAppFilterReset" variant="light" size="sm" class="border flex-grow-1">Reset</x-ui.button>
                            </div>
                        </form>
                    </x-ui.filter>
                </div>
            </div>
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
                                        <tr class="shift-app-row"
                                            data-status="{{ $req->status }}"
                                            data-type="{{ $req->type }}"
                                            data-created-at="{{ $req->created_at ? $req->created_at->timestamp : 0 }}"
                                            data-search-text="{{ strtolower($req->type . ' ' . $req->status . ' ' . ($req->requestedShift ? $req->requestedShift->name : '') . ' ' . ($req->currentShift ? $req->currentShift->name : '') . ' ' . $req->reason) }}">
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
                                    <tr id="no_matching_emp_shift_apps_row" class="d-none">
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="feather-folder fs-3 d-block mb-2 text-secondary"></i>
                                            No matching shift change requests found.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <!-- Shift Applications Pagination Container -->
                            <div class="erp-pagination-container py-3 px-3 border-top d-none" id="empShiftAppsPaginationContainer">
                                <ul class="erp-pagination mb-2 justify-content-center" id="emp_shift_apps_pagination_ul">
                                    <!-- Dynamically generated pagination links -->
                                </ul>
                                <div class="erp-pagination-info text-center">
                                    Showing <span id="emp_shift_apps_showing_start">0</span> to <span id="emp_shift_apps_showing_end">0</span> of <strong id="emp_shift_apps_total_count">0</strong> entries
                                </div>
                            </div>
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
                                        <tr class="overtime-app-row"
                                            data-status="{{ $req->status }}"
                                            data-compensation="{{ $req->compensation_type }}"
                                            data-hours="{{ $req->duration_hours }}"
                                            data-created-at="{{ $req->created_at ? $req->created_at->timestamp : 0 }}"
                                            data-search-text="{{ strtolower($req->status . ' ' . $req->compensation_type . ' ' . $req->date->format('d M Y') . ' ' . $req->reason) }}">
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
                                    <tr id="no_matching_emp_overtime_apps_row" class="d-none">
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="feather-folder fs-3 d-block mb-2 text-secondary"></i>
                                            No matching overtime requests found.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <!-- Overtime Applications Pagination Container -->
                            <div class="erp-pagination-container py-3 px-3 border-top d-none" id="empOvertimeAppsPaginationContainer">
                                <ul class="erp-pagination mb-2 justify-content-center" id="emp_overtime_apps_pagination_ul">
                                    <!-- Dynamically generated pagination links -->
                                </ul>
                                <div class="erp-pagination-info text-center">
                                    Showing <span id="emp_overtime_apps_showing_start">0</span> to <span id="emp_overtime_apps_showing_end">0</span> of <strong id="emp_overtime_apps_total_count">0</strong> entries
                                </div>
                            </div>
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
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function () {
        // Toggle toolbars on view switch
        $(document).on('click', '#btnToggleShiftOvertimeView', function () {
            var isOvertimeHidden = $('#overtimeApplicationsViewContainer').hasClass('d-none');
            if (isOvertimeHidden) {
                $('#shiftAppsToolbar').addClass('d-none');
                $('#overtimeAppsToolbar').removeClass('d-none');
            } else {
                $('#overtimeAppsToolbar').addClass('d-none');
                $('#shiftAppsToolbar').removeClass('d-none');
            }
        });

        // 1. Shift Applications Search / Sort / Filter Logic
        var empShiftAppSortMode = 'date_desc';
        var empShiftAppFilters = { status: '', type: '' };
        var empShiftAppCurrentPage = 1;
        var empShiftAppPerPage = 10;

        function refreshEmpShiftAppRows() {
            var query = ($('#empShiftAppSearchInput').val() || '').toLowerCase().trim();
            var $allRows = $('#shiftAppTable tbody tr.shift-app-row');

            var $matchingRows = $allRows.filter(function () {
                var $row = $(this);
                var searchText = ($row.data('search-text') || '').toString().toLowerCase();
                var status = ($row.data('status') || '').toString().toLowerCase();
                var type = ($row.data('type') || '').toString().toLowerCase();

                var matchesSearch = !query || searchText.indexOf(query) !== -1;
                var matchesStatus = !empShiftAppFilters.status || status === empShiftAppFilters.status;
                var matchesType = !empShiftAppFilters.type || type === empShiftAppFilters.type;

                return matchesSearch && matchesStatus && matchesType;
            });

            var totalItems = $matchingRows.length;
            var totalPages = Math.ceil(totalItems / empShiftAppPerPage) || 1;

            if (empShiftAppCurrentPage > totalPages) {
                empShiftAppCurrentPage = totalPages;
            }
            if (empShiftAppCurrentPage < 1) {
                empShiftAppCurrentPage = 1;
            }

            var matchingArr = $matchingRows.get();
            matchingArr.sort(function (a, b) {
                var $a = $(a), $b = $(b);
                var valA = parseInt($a.data('created-at') || 0, 10);
                var valB = parseInt($b.data('created-at') || 0, 10);
                if (empShiftAppSortMode === 'date_desc') {
                    return valB - valA;
                } else if (empShiftAppSortMode === 'date_asc') {
                    return valA - valB;
                }
                return 0;
            });

            var startIndex = (empShiftAppCurrentPage - 1) * empShiftAppPerPage;
            var endIndex = Math.min(startIndex + empShiftAppPerPage, totalItems);

            $allRows.addClass('d-none');

            $.each(matchingArr, function (idx, row) {
                var $r = $(row);
                $('#shiftAppTable tbody').append($r);
                if (idx >= startIndex && idx < endIndex) {
                    $r.removeClass('d-none');
                }
            });

            if (totalItems > empShiftAppPerPage) {
                $('#empShiftAppsPaginationContainer').removeClass('d-none');
            } else {
                $('#empShiftAppsPaginationContainer').addClass('d-none');
            }

            if (totalItems === 0) {
                $('#no_matching_emp_shift_apps_row').removeClass('d-none');
            } else {
                $('#no_matching_emp_shift_apps_row').addClass('d-none');
            }

            $('#emp_shift_apps_showing_start').text(totalItems === 0 ? 0 : startIndex + 1);
            $('#emp_shift_apps_showing_end').text(endIndex);
            $('#emp_shift_apps_total_count').text(totalItems);

            var paginationHtml = '';
            paginationHtml += '<li class="page-item ' + (empShiftAppCurrentPage === 1 ? 'disabled' : '') + '">';
            paginationHtml += '<a class="page-link" href="#" data-page="' + (empShiftAppCurrentPage - 1) + '" aria-label="Previous"><i class="feather-chevron-left"></i></a>';
            paginationHtml += '</li>';

            for (var i = 1; i <= totalPages; i++) {
                paginationHtml += '<li class="page-item ' + (empShiftAppCurrentPage === i ? 'active' : '') + '">';
                paginationHtml += '<a class="page-link" href="#" data-page="' + i + '">' + i + '</a>';
                paginationHtml += '</li>';
            }

            paginationHtml += '<li class="page-item ' + (empShiftAppCurrentPage === totalPages ? 'disabled' : '') + '">';
            paginationHtml += '<a class="page-link" href="#" data-page="' + (empShiftAppCurrentPage + 1) + '" aria-label="Next"><i class="feather-chevron-right"></i></a>';
            paginationHtml += '</li>';

            $('#emp_shift_apps_pagination_ul').html(paginationHtml);
        }

        // Event listeners for Shift tab
        $('#empShiftAppSearchInput').on('keyup input search', function () {
            empShiftAppCurrentPage = 1;
            refreshEmpShiftAppRows();
        });

        $(document).on('click', '.emp-shift-app-sort-link', function (e) {
            e.preventDefault();
            var $link = $(this);
            empShiftAppSortMode = $link.data('sort') || 'date_desc';
            $('.emp-shift-app-sort-link').removeClass('active').find('.shift-sort-check').addClass('d-none');
            $link.addClass('active').find('.shift-sort-check').removeClass('d-none');
            empShiftAppCurrentPage = 1;
            refreshEmpShiftAppRows();
            $link.closest('.erp-sort-dropdown').removeClass('show').find('.dropdown-menu').removeClass('show');
        });

        $('#btnEmpShiftAppFilterApply').on('click', function () {
            empShiftAppFilters.status = $('#empShiftAppFilterStatus').val() || '';
            empShiftAppFilters.type = $('#empShiftAppFilterType').val() || '';
            empShiftAppCurrentPage = 1;
            refreshEmpShiftAppRows();
            $(this).closest('.erp-filter-dropdown').removeClass('show').find('.dropdown-menu').removeClass('show');
        });

        $('#btnEmpShiftAppFilterReset').on('click', function () {
            $('#empShiftAppFilterStatus').val('').trigger('change');
            $('#empShiftAppFilterType').val('').trigger('change');
            empShiftAppFilters.status = '';
            empShiftAppFilters.type = '';
            empShiftAppCurrentPage = 1;
            refreshEmpShiftAppRows();
            $(this).closest('.erp-filter-dropdown').removeClass('show').find('.dropdown-menu').removeClass('show');
        });

        $(document).on('click', '#emp_shift_apps_pagination_ul .page-link', function (e) {
            e.preventDefault();
            var page = parseInt($(this).data('page') || 1, 10);
            empShiftAppCurrentPage = page;
            refreshEmpShiftAppRows();
        });

        // 2. Overtime Applications Search / Sort / Filter Logic
        var empOvertimeAppSortMode = 'date_desc';
        var empOvertimeAppFilters = { status: '', compensation: '' };
        var empOvertimeAppCurrentPage = 1;
        var empOvertimeAppPerPage = 10;

        function refreshEmpOvertimeAppRows() {
            var query = ($('#empOvertimeAppSearchInput').val() || '').toLowerCase().trim();
            var $allRows = $('#overtimeAppTable tbody tr.overtime-app-row');

            var $matchingRows = $allRows.filter(function () {
                var $row = $(this);
                var searchText = ($row.data('search-text') || '').toString().toLowerCase();
                var status = ($row.data('status') || '').toString().toLowerCase();
                var compensation = ($row.data('compensation') || '').toString().toLowerCase();

                var matchesSearch = !query || searchText.indexOf(query) !== -1;
                var matchesStatus = !empOvertimeAppFilters.status || status === empOvertimeAppFilters.status;
                var matchesComp = !empOvertimeAppFilters.compensation || compensation === empOvertimeAppFilters.compensation;

                return matchesSearch && matchesStatus && matchesComp;
            });

            var totalItems = $matchingRows.length;
            var totalPages = Math.ceil(totalItems / empOvertimeAppPerPage) || 1;

            if (empOvertimeAppCurrentPage > totalPages) {
                empOvertimeAppCurrentPage = totalPages;
            }
            if (empOvertimeAppCurrentPage < 1) {
                empOvertimeAppCurrentPage = 1;
            }

            var matchingArr = $matchingRows.get();
            matchingArr.sort(function (a, b) {
                var $a = $(a), $b = $(b);
                if (empOvertimeAppSortMode === 'date_desc') {
                    return ($b.data('created-at') || 0) - ($a.data('created-at') || 0);
                } else if (empOvertimeAppSortMode === 'date_asc') {
                    return ($a.data('created-at') || 0) - ($b.data('created-at') || 0);
                } else if (empOvertimeAppSortMode === 'hours_desc') {
                    return parseFloat($b.data('hours') || 0) - parseFloat($a.data('hours') || 0);
                } else if (empOvertimeAppSortMode === 'hours_asc') {
                    return parseFloat($a.data('hours') || 0) - parseFloat($b.data('hours') || 0);
                }
                return 0;
            });

            var startIndex = (empOvertimeAppCurrentPage - 1) * empOvertimeAppPerPage;
            var endIndex = Math.min(startIndex + empOvertimeAppPerPage, totalItems);

            $allRows.addClass('d-none');

            $.each(matchingArr, function (idx, row) {
                var $r = $(row);
                $('#overtimeAppTable tbody').append($r);
                if (idx >= startIndex && idx < endIndex) {
                    $r.removeClass('d-none');
                }
            });

            if (totalItems > empOvertimeAppPerPage) {
                $('#empOvertimeAppsPaginationContainer').removeClass('d-none');
            } else {
                $('#empOvertimeAppsPaginationContainer').addClass('d-none');
            }

            if (totalItems === 0) {
                $('#no_matching_emp_overtime_apps_row').removeClass('d-none');
            } else {
                $('#no_matching_emp_overtime_apps_row').addClass('d-none');
            }

            $('#emp_overtime_apps_showing_start').text(totalItems === 0 ? 0 : startIndex + 1);
            $('#emp_overtime_apps_showing_end').text(endIndex);
            $('#emp_overtime_apps_total_count').text(totalItems);

            var paginationHtml = '';
            paginationHtml += '<li class="page-item ' + (empOvertimeAppCurrentPage === 1 ? 'disabled' : '') + '">';
            paginationHtml += '<a class="page-link" href="#" data-page="' + (empOvertimeAppCurrentPage - 1) + '" aria-label="Previous"><i class="feather-chevron-left"></i></a>';
            paginationHtml += '</li>';

            for (var i = 1; i <= totalPages; i++) {
                paginationHtml += '<li class="page-item ' + (empOvertimeAppCurrentPage === i ? 'active' : '') + '">';
                paginationHtml += '<a class="page-link" href="#" data-page="' + i + '">' + i + '</a>';
                paginationHtml += '</li>';
            }

            paginationHtml += '<li class="page-item ' + (empOvertimeAppCurrentPage === totalPages ? 'disabled' : '') + '">';
            paginationHtml += '<a class="page-link" href="#" data-page="' + (empOvertimeAppCurrentPage + 1) + '" aria-label="Next"><i class="feather-chevron-right"></i></a>';
            paginationHtml += '</li>';

            $('#emp_overtime_apps_pagination_ul').html(paginationHtml);
        }

        // Event listeners for Overtime tab
        $('#empOvertimeAppSearchInput').on('keyup input search', function () {
            empOvertimeAppCurrentPage = 1;
            refreshEmpOvertimeAppRows();
        });

        $(document).on('click', '.emp-overtime-app-sort-link', function (e) {
            e.preventDefault();
            var $link = $(this);
            empOvertimeAppSortMode = $link.data('sort') || 'date_desc';
            $('.emp-overtime-app-sort-link').removeClass('active').find('.ot-sort-check').addClass('d-none');
            $link.addClass('active').find('.ot-sort-check').removeClass('d-none');
            empOvertimeAppCurrentPage = 1;
            refreshEmpOvertimeAppRows();
            $link.closest('.erp-sort-dropdown').removeClass('show').find('.dropdown-menu').removeClass('show');
        });

        $('#btnEmpOvertimeAppFilterApply').on('click', function () {
            empOvertimeAppFilters.status = $('#empOvertimeAppFilterStatus').val() || '';
            empOvertimeAppFilters.compensation = $('#empOvertimeAppFilterCompensation').val() || '';
            empOvertimeAppCurrentPage = 1;
            refreshEmpOvertimeAppRows();
            $(this).closest('.erp-filter-dropdown').removeClass('show').find('.dropdown-menu').removeClass('show');
        });

        $('#btnEmpOvertimeAppFilterReset').on('click', function () {
            $('#empOvertimeAppFilterStatus').val('').trigger('change');
            $('#empOvertimeAppFilterCompensation').val('').trigger('change');
            empOvertimeAppFilters.status = '';
            empOvertimeAppFilters.compensation = '';
            empOvertimeAppCurrentPage = 1;
            refreshEmpOvertimeAppRows();
            $(this).closest('.erp-filter-dropdown').removeClass('show').find('.dropdown-menu').removeClass('show');
        });

        $(document).on('click', '#emp_overtime_apps_pagination_ul .page-link', function (e) {
            e.preventDefault();
            var page = parseInt($(this).data('page') || 1, 10);
            empOvertimeAppCurrentPage = page;
            refreshEmpOvertimeAppRows();
        });

        // Initialize lists
        refreshEmpShiftAppRows();
        refreshEmpOvertimeAppRows();
    });
</script>
@endpush
