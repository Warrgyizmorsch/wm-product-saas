<div class="tab-pane fade {{ $activeTabName === 'leaves' ? 'show active' : '' }}" id="leaves-pane" role="tabpanel" aria-labelledby="leaves-tab">
    @php
        $formatLeaveRulePoints = static function (?array $rules = null): array {
            if (empty($rules)) {
                return [];
            }

            $humanize = static fn ($value) => strtolower(str_replace('_', ' ', (string) $value));
            $formatNumber = static fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
            $sections = [];

            if (!empty($rules['application'])) {
                $application = $rules['application'];
                $points = [];

                if (!empty($application['apply_in_advance'])) {
                    $points[] = 'You have to apply for this leave at least ' . ($application['advance_days'] ?? 0) . ' day(s) in advance.';
                } else {
                    $points[] = 'You can apply for this leave without an advance-day restriction.';
                }

                $points[] = 'One request can be from ' . ($application['min_duration'] ?? 1) . ' to ' . ($application['max_duration'] ?? 10) . ' day(s).';

                if (!empty($application['require_attachment'])) {
                    $points[] = 'You must attach supporting documents when the leave duration is more than ' . ($application['attachment_days'] ?? 0) . ' day(s).';
                } else {
                    $points[] = 'No supporting document is required for applying.';
                }

                $sections[] = ['title' => 'Application Rules', 'icon' => 'feather-file-text', 'points' => $points];
            }

            if (!empty($rules['approval'])) {
                $approval = $rules['approval'];
                $points = [];

                if (($approval['workflow_level'] ?? null) === 'auto') {
                    $points[] = 'This leave is approved automatically after submission.';
                } elseif (($approval['workflow_level'] ?? null) === '2_level') {
                    $points[] = 'This leave needs two approvals: first by ' . $humanize($approval['first_approver'] ?? 'reporting_manager') . ', then by ' . $humanize($approval['second_approver'] ?? 'hr_manager') . '.';
                } else {
                    $points[] = 'This leave needs approval from ' . $humanize($approval['first_approver'] ?? 'reporting_manager') . '.';
                }

                $sections[] = ['title' => 'Approval Rules', 'icon' => 'feather-check-square', 'points' => $points];
            }

            if (!empty($rules['accrual'])) {
                $accrual = $rules['accrual'];
                $points = [];
                $unit = $humanize($accrual['calculate_in'] ?? 'days');

                if (($accrual['quota_type'] ?? 'fixed') === 'unlimited') {
                    $points[] = 'This leave has unlimited quota.';
                } else {
                    $points[] = 'You get ' . $formatNumber($accrual['quota_value'] ?? 0) . ' ' . $unit . ' of this leave.';
                }

                $rate = $accrual['rate'] ?? 'immediate';
                if ($rate === 'attendance') {
                    $points[] = 'Leave is earned based on attendance: ' . ($accrual['attendance_earn'] ?? 1) . ' day for every ' . ($accrual['attendance_period'] ?? 20) . ' present day(s).';
                } elseif ($rate === 'periodic') {
                    $freq = ucfirst($accrual['frequency'] ?? 'monthly');
                    $prorate = !empty($accrual['prorate']) ? ' (Prorated)' : '';
                    $points[] = 'Leave is credited ' . strtolower($freq) . ' as configured in the leave policy' . $prorate . '.';
                } else {
                    $points[] = 'Leave is credited immediately.';
                }

                if (!empty($accrual['limit_carry'])) {
                    $points[] = 'Maximum accumulated balance is limited to ' . ($accrual['max_accum'] ?? 0) . ' day(s).';
                } else {
                    $points[] = 'No accumulated balance cap enforced.';
                }

                $sections[] = ['title' => 'Accrual Rules', 'icon' => 'feather-calendar', 'points' => $points];
            }

            if (!empty($rules['yearend'])) {
                $yearend = $rules['yearend'];
                $points = [];

                if (($yearend['action'] ?? 'lapse') === 'carry_forward') {
                    $points[] = 'Unused leave can be carried forward at year end.';
                    $points[] = 'Maximum carry-forward limit is ' . ($yearend['max_carry'] ?? 0) . ' day(s).';
                } elseif (($yearend['action'] ?? null) === 'encash') {
                    $points[] = 'Unused leave can be encashed at year end.';
                    $points[] = 'Maximum encashment limit is ' . ($yearend['max_encash'] ?? 0) . ' day(s).';
                } else {
                    $points[] = 'Unused leave lapses at year end.';
                }

                $sections[] = ['title' => 'Year-End Policy', 'icon' => 'feather-rotate-ccw', 'points' => $points];
            }

            if (!empty($rules['encashment'])) {
                $encash = $rules['encashment'];
                $points = [];

                if (!empty($encash['enabled'])) {
                    $freq = $humanize($encash['frequency'] ?? 'anytime');
                    $points[] = 'Encashment is enabled for this leave type (' . $freq . ').';
                    $points[] = 'Maximum encashment per request is ' . ($encash['max_days_per_request'] ?? 5) . ' day(s).';
                    $points[] = 'Minimum leave balance to maintain is ' . ($encash['min_balance_to_keep'] ?? 10) . ' day(s).';
                } else {
                    $points[] = 'Encashment is not enabled for this leave type.';
                }

                $sections[] = ['title' => 'Encashment Rules', 'icon' => 'feather-dollar-sign', 'points' => $points];
            }

            if (!empty($rules['probation'])) {
                $probation = $rules['probation'];
                $points = [];
                $rule = $probation['rule'] ?? 'allow';

                if ($rule === 'allow') {
                    $points[] = 'Employees can apply for this leave during their probation period.';
                } elseif ($rule === 'allow_after_months') {
                    $points[] = 'Employees can apply for this leave after completing ' . ($probation['months'] ?? 3) . ' month(s) of service.';
                } else {
                    $points[] = 'Applying for this leave is not allowed during the probation period.';
                }

                $sections[] = ['title' => 'Probation Period Rules', 'icon' => 'feather-user-check', 'points' => $points];
            }

            if (!empty($rules['notice'])) {
                $notice = $rules['notice'];
                $points = [];
                $rule = $notice['rule'] ?? 'allow';

                if ($rule === 'allow') {
                    $points[] = 'Employees can apply for this leave during their notice period.';
                } elseif ($rule === 'allow_with_permission') {
                    $points[] = 'Applying for this leave during notice period requires special HR/Manager permission.';
                } else {
                    $points[] = 'Applying for this leave is not allowed during the notice period.';
                }

                $sections[] = ['title' => 'Notice Period Rules', 'icon' => 'feather-alert-triangle', 'points' => $points];
            }

            return $sections;
        };
    @endphp
    
    @php
        $empLeaveRequests = \App\Domains\HRMS\Models\LeaveRequest::where('employee_id', $employee->id)->with('leaveType')->orderBy('created_at', 'desc')->get();
        $empLeaveEncashments = \App\Domains\HRMS\Models\LeaveEncashment::where('employee_id', $employee->id)->with('leaveType')->orderBy('created_at', 'desc')->get();
        $allLeaveTypes    = $employee->leavePlan ? $employee->leavePlan->types : \App\Domains\HRMS\Models\LeaveType::where('is_active', true)->orderBy('name')->get();
    @endphp

    <div class="row g-4">
        <!-- LEFT COLUMN: Assigned Leave Plan & Brief Allowances -->
        <div class="col-lg-4 col-12">
            <div class="card-custom">
                <div class="card-custom-header">
                    <h5 class="card-custom-title"><i class="feather-info text-secondary me-1.5"></i> {{ __('hrms.employees.lbl_assigned_leave_plan') }}</h5>
                </div>
                <div class="card-body p-3">
                    @if($employee->leavePlan)
                        <div class="p-3 bg-light rounded border border-light-subtle">
                            <div class="d-flex align-items-center justify-content-between">
                                <h6 class="fw-bold mb-0 text-dark fs-14">{{ $employee->leavePlan->name }}</h6>
                                @if(!$employee->leavePlan->status)
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-10 px-2 rounded">Inactive</span>
                                @else
                                    <span class="badge bg-success-subtle text-success border border-success-subtle fs-10 px-2 rounded">Active</span>
                                @endif
                            </div>
                            <p class="text-muted fs-12 mb-0 mt-2">{{ $employee->leavePlan->description ?: 'Default annual leave policy for employees.' }}</p>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
                            <span class="text-muted fs-11 text-uppercase fw-semibold tracking-wide">EFFECTIVE FROM</span>
                            <span class="fw-bold text-dark fs-12">{{ $employee->leavePlan->effective_from ? $employee->leavePlan->effective_from->format('d M, Y') : 'N/A' }}</span>
                        </div>
                    @else
                        <div class="text-center py-4 bg-light rounded text-muted fs-12 border border-light-subtle mb-3">
                            <i class="feather-alert-circle d-block fs-20 text-warning mb-1"></i>
                            No leave plan assigned.
                        </div>
                    @endif

                    {{-- Leave Types Table --}}
                    <table class="table table-sm mb-0" style="border-collapse: separate; border-spacing: 0; table-layout: fixed; width: 100%;">
                        <thead>
                            <tr>
                                <th class="fs-11 text-uppercase text-muted fw-semibold ps-0 pb-2" style="border-bottom: 1px solid #e9ecef; width: 62%;">Type Name</th>
                                <th class="fs-11 text-uppercase text-muted fw-semibold text-center pb-2" style="border-bottom: 1px solid #e9ecef; width: 23%;">Balance</th>
                                <th class="fs-11 text-uppercase text-muted fw-semibold text-center pb-2" style="border-bottom: 1px solid #e9ecef; width: 15%;">Rules</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($leaveAllowances as $allowance)
                                <tr style="border-bottom: 1px solid #f3f4f6;">
                                    <td class="ps-0 py-2 align-middle" style="border: none;">
                                        <div class="d-flex align-items-center" style="white-space: nowrap;">
                                            <span class="rounded-circle flex-shrink-0" style="width:7px;height:7px;background:{{ $allowance->leaveType->color ?: '#6c757d' }};display:inline-block;margin-right:8px;"></span>
                                            <span class="fw-semibold text-dark fs-12" style="line-height: 1.2;">{{ $allowance->leaveType->name }}</span>
                                            <span class="text-muted fs-10" style="font-size:10px; font-weight: 500; margin-left: 6px;">{{ $allowance->leaveType->code }}</span>
                                        </div>
                                    </td>
                                    <td class="text-center py-2 align-middle" style="border: none; white-space: nowrap;">
                                        <span class="fw-bold text-dark fs-12">
                                            {{ rtrim(rtrim(number_format($allowance->remaining, 2, '.', ''), '0'), '.') }} / {{ rtrim(rtrim(number_format($allowance->allocated, 2, '.', ''), '0'), '.') }}
                                        </span>
                                    </td>
                                    <td class="text-center py-2 align-middle" style="border: none;">
                                        <button type="button" class="btn btn-light border d-inline-flex align-items-center justify-content-center p-0 rounded-circle view-emp-leave-rules-btn" style="width:24px;height:24px;background:#f8fafc;" data-name="{{ $allowance->leaveType->name }}" data-code="{{ $allowance->leaveType->code }}" data-type="{{ ucfirst($allowance->leaveType->type) }}" data-quota="{{ floatval($allowance->allocated) }}" data-rules='@json($allowance->leaveType->rules ?? [])' title="View rules">
                                            <i class="feather-sliders text-muted" style="font-size: 11px;"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted fs-12 py-3">No active allowances found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>

                    {{-- Action Buttons --}}
                    @if($employee->leavePlan && $employee->leavePlan->status)
                        <div class="d-flex align-items-center gap-2 mt-3 pt-2 border-top">
                            <button type="button" class="btn btn-sm btn-primary text-white flex-grow-1 fw-bold d-flex align-items-center justify-content-center gap-1 py-2" style="font-size:10px; border-radius:8px; border:none; text-transform:uppercase;" data-bs-toggle="modal" data-bs-target="#empApplyLeaveModal">
                                <i class="feather-plus" style="font-size:11px;"></i> APPLY FOR LEAVE
                            </button>
                            <button type="button" class="btn btn-sm btn-primary text-white flex-grow-1 fw-bold d-flex align-items-center justify-content-center gap-1 py-2" style="font-size:10px; border-radius:8px; border:none; text-transform:uppercase;" data-bs-toggle="modal" data-bs-target="#empApplyEncashmentModal">
                                <i class="feather-dollar-sign" style="font-size:11px;"></i> APPLY FOR LEAVE ENCASHMENT
                            </button>
                        </div>
                    @endif
                </div>

            </div>
        </div>
 
        <!-- RIGHT COLUMN: Application Logs & History -->
        <div class="col-lg-8 col-12">

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                    <i class="feather-alert-triangle me-2"></i>
                    <ul class="mb-0 ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <!-- MAIN CARD BOX -->
            <!-- ABOVE THE CARD: Search, Sort, Filter Row -->
            <div class="d-flex align-items-center justify-content-end mb-3 gap-2 flex-wrap">
                {{-- Leave Apps Toolbar --}}
                <div id="leaveAppsToolbar" class="d-flex align-items-center gap-2">
                    <div class="d-flex align-items-center border rounded px-3 py-1" style="background:#f1f5f9; min-width: 180px; max-width: 240px; height: 38px; border-color: #e2e8f0 !important;">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" id="empLeaveAppSearchInput" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search..." style="box-shadow: none; height: 32px;" autocomplete="off">
                    </div>
                    <x-ui.sort-dropdown label="SORT">
                        <a class="dropdown-item py-2 d-flex align-items-center emp-leave-app-sort-link active" href="#" onclick="event.preventDefault();" data-sort="date_desc">
                            <span>Newest First</span><i class="feather-check text-dark ms-auto sort-check"></i>
                        </a>
                        <a class="dropdown-item py-2 d-flex align-items-center emp-leave-app-sort-link" href="#" onclick="event.preventDefault();" data-sort="date_asc">
                            <span>Oldest First</span><i class="feather-check text-dark ms-auto sort-check d-none"></i>
                        </a>
                        <a class="dropdown-item py-2 d-flex align-items-center emp-leave-app-sort-link" href="#" onclick="event.preventDefault();" data-sort="duration_desc">
                            <span>Duration (High to Low)</span><i class="feather-check text-dark ms-auto sort-check d-none"></i>
                        </a>
                        <a class="dropdown-item py-2 d-flex align-items-center emp-leave-app-sort-link" href="#" onclick="event.preventDefault();" data-sort="duration_asc">
                            <span>Duration (Low to High)</span><i class="feather-check text-dark ms-auto sort-check d-none"></i>
                        </a>
                    </x-ui.sort-dropdown>
                    <x-ui.filter label="FILTER" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        <form id="empLeaveAppFilterForm" onsubmit="return false;">
                            <div class="mb-3" style="min-width: 220px;">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                                <x-ui.odoo-form-ui type="select" name="status" id="empLeaveAppFilterStatus">
                                    <option value="">All Statuses</option>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                    <option value="unauthorized">Unauthorized</option>
                                    <option value="unpaid">Unpaid</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="mb-3" style="min-width: 220px;">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Leave Type</label>
                                <x-ui.odoo-form-ui type="select" name="leave_type_id" id="empLeaveAppFilterType">
                                    <option value="">All Types</option>
                                    @foreach($allLeaveTypes as $lt)
                                        <option value="{{ $lt->id }}">{{ $lt->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="dropdown-divider my-3"></div>
                            <div class="d-flex gap-2">
                                <x-ui.button type="button" id="btnEmpLeaveAppFilterApply" variant="primary" size="sm" class="flex-grow-1">Apply</x-ui.button>
                                <x-ui.button type="button" id="btnEmpLeaveAppFilterReset" variant="light" size="sm" class="border flex-grow-1">Reset</x-ui.button>
                            </div>
                        </form>
                    </x-ui.filter>
                </div>

                {{-- Leave Encashments Toolbar --}}
                <div id="leaveEncashmentsToolbar" class="d-flex align-items-center gap-2 d-none">
                    <div class="d-flex align-items-center border rounded px-3 py-1" style="background:#f1f5f9; min-width: 180px; max-width: 240px; height: 38px; border-color: #e2e8f0 !important;">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" id="empLeaveEncSearchInput" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search..." style="box-shadow: none; height: 32px;" autocomplete="off">
                    </div>
                    <x-ui.sort-dropdown label="SORT">
                        <a class="dropdown-item py-2 d-flex align-items-center emp-leave-enc-sort-link active" href="#" onclick="event.preventDefault();" data-sort="date_desc">
                            <span>Newest First</span><i class="feather-check text-dark ms-auto encash-sort-check"></i>
                        </a>
                        <a class="dropdown-item py-2 d-flex align-items-center emp-leave-enc-sort-link" href="#" onclick="event.preventDefault();" data-sort="date_asc">
                            <span>Oldest First</span><i class="feather-check text-dark ms-auto encash-sort-check d-none"></i>
                        </a>
                        <a class="dropdown-item py-2 d-flex align-items-center emp-leave-enc-sort-link" href="#" onclick="event.preventDefault();" data-sort="days_desc">
                            <span>Days (High to Low)</span><i class="feather-check text-dark ms-auto encash-sort-check d-none"></i>
                        </a>
                        <a class="dropdown-item py-2 d-flex align-items-center emp-leave-enc-sort-link" href="#" onclick="event.preventDefault();" data-sort="days_asc">
                            <span>Days (Low to High)</span><i class="feather-check text-dark ms-auto encash-sort-check d-none"></i>
                        </a>
                    </x-ui.sort-dropdown>
                    <x-ui.filter label="FILTER" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        <form id="empLeaveEncFilterForm" onsubmit="return false;">
                            <div class="mb-3" style="min-width: 220px;">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                                <x-ui.odoo-form-ui type="select" name="status" id="empLeaveEncFilterStatus">
                                    <option value="">All Statuses</option>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="mb-3" style="min-width: 220px;">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Leave Type</label>
                                <x-ui.odoo-form-ui type="select" name="leave_type_id" id="empLeaveEncFilterType">
                                    <option value="">All Types</option>
                                    @foreach($allLeaveTypes as $lt)
                                        <option value="{{ $lt->id }}">{{ $lt->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="dropdown-divider my-3"></div>
                            <div class="d-flex gap-2">
                                <x-ui.button type="button" id="btnEmpLeaveEncFilterApply" variant="primary" size="sm" class="flex-grow-1">Apply</x-ui.button>
                                <x-ui.button type="button" id="btnEmpLeaveEncFilterReset" variant="light" size="sm" class="border flex-grow-1">Reset</x-ui.button>
                            </div>
                        </form>
                    </x-ui.filter>
                </div>
            </div>

            <!-- MAIN CARD BOX -->
            <div class="card-custom">
                <div class="card-custom-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    {{-- Left: Title + Count Badge --}}
                    <div class="d-flex align-items-center gap-2">
                        <div id="leaveAppsHeaderTitle" class="d-flex align-items-center gap-2">
                            <h5 class="card-custom-title mb-0">
                                <i class="feather-calendar text-primary me-1"></i> Leave Applications &amp; History
                            </h5>
                            <span class="badge bg-soft-primary text-primary rounded-pill px-2 py-1 fs-11 fw-bold" id="empLeaveRequestsCountBadge">
                                {{ $empLeaveRequests->count() }} Applications
                            </span>
                        </div>
                        <div id="leaveEncashmentsHeaderTitle" class="d-flex align-items-center gap-2 d-none">
                            <h5 class="card-custom-title mb-0">
                                <i class="feather-dollar-sign text-primary me-1"></i> Leave Encashments
                            </h5>
                            <span class="badge bg-soft-primary text-primary rounded-pill px-2 py-1 fs-11 fw-bold" id="empLeaveEncashCountBadge">
                                {{ $empLeaveEncashments->count() }} Encashments
                            </span>
                        </div>
                    </div>

                    {{-- Right: Toggle Button --}}
                    <div>
                        <button type="button" id="btnToggleLeaveView" class="btn btn-sm btn-soft-primary fw-bold text-uppercase" style="font-size:11px;padding:7px 14px;border-radius:6px;">
                            <span id="toggleBtnLabel"><i class="feather-dollar-sign me-1"></i> ENCASHMENT DETAILS</span>
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">

                    <!-- 1. LEAVE APPLICATIONS VIEW -->
                    <div id="leaveApplicationsViewContainer">

                        @if(!isset($empLeaveRequests) || $empLeaveRequests->isEmpty())
                            <div class="p-5 text-center text-muted">
                                <i class="feather-calendar fs-24 text-secondary d-block mb-2"></i>
                                {{ __('hrms.leave.no_applications_submitted') }}
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" id="leaveAppTable" style="table-layout: fixed; width: 100%;">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="fs-12 text-uppercase text-muted fw-semibold ps-3" style="width: 26%;">Leave Type</th>
                                            <th class="fs-12 text-uppercase text-muted fw-semibold" style="width: 22%;">Period</th>
                                            <th class="fs-12 text-uppercase text-muted fw-semibold text-center" style="width: 8%;">Days</th>
                                            <th class="fs-12 text-uppercase text-muted fw-semibold" style="width: 20%;">Status</th>
                                            <th class="fs-12 text-uppercase text-muted fw-semibold text-center" style="width: 10%;">File</th>
                                            <th class="fs-12 text-uppercase text-muted fw-semibold text-end pe-3" style="width: 14%;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($empLeaveRequests as $req)
                                            @php
                                                $sameYear = $req->start_date && $req->end_date && $req->start_date->format('Y') === $req->end_date->format('Y');
                                                $startStr = $req->start_date ? $req->start_date->format($sameYear ? 'd M' : 'd M Y') : '-';
                                                $endStr   = $req->end_date   ? $req->end_date->format('d M Y')   : '-';
                                                $dateRange = ($req->start_date && $req->end_date && $req->start_date->isSameDay($req->end_date))
                                                    ? $req->start_date->format('d M Y')
                                                    : $startStr . ' – ' . $endStr;

                                                $statusBadge = match($req->status) {
                                                    'approved'               => ['cls' => 'bg-soft-success text-success',  'icon' => 'feather-check-circle', 'lbl' => __('hrms.leave.status_approved')],
                                                    'pending'                => ['cls' => 'bg-soft-warning text-warning',  'icon' => 'feather-clock',         'lbl' => __('hrms.leave.status_pending')],
                                                    'rejected'               => ['cls' => 'bg-soft-danger text-danger',    'icon' => 'feather-x-circle',      'lbl' => __('hrms.leave.status_rejected')],
                                                    'cancellation_requested' => ['cls' => 'bg-soft-info text-info',          'icon' => 'feather-rotate-ccw',     'lbl' => __('hrms.wfh.cancellation_requested')],
                                                    'cancelled'              => ['cls' => 'bg-soft-secondary text-secondary','icon' => 'feather-slash',          'lbl' => __('hrms.wfh.cancelled')],
                                                    'unauthorized'           => ['cls' => 'bg-soft-secondary text-secondary','icon' => 'feather-slash',        'lbl' => __('hrms.leave.status_unauthorized')],
                                                    'unpaid'                 => ['cls' => 'bg-soft-info text-info',        'icon' => 'feather-alert-circle',  'lbl' => __('hrms.leave.status_unpaid')],
                                                    default                  => ['cls' => 'bg-light text-secondary',       'icon' => 'feather-circle',        'lbl' => ucfirst($req->status)],
                                                };

                                                $rowBalance = \App\Domains\HRMS\Models\LeaveBalance::where('employee_id', $req->employee_id)
                                                    ->where('leave_type_id', $req->leave_type_id)
                                                    ->first();
                                                $rowRemaining = $rowBalance ? floatval($rowBalance->remaining) : 0.0;
                                                $rowAllocated = $rowBalance ? floatval($rowBalance->allocated) : floatval($req->leaveType?->quota ?: 0);

                                                $notifiedNames = '';
                                                if (!empty($req->notified_contacts)) {
                                                    $contacts = \App\Domains\HRMS\Models\Employee::whereIn('id', $req->notified_contacts)->pluck('full_name')->toArray();
                                                    $notifiedNames = implode(', ', $contacts);
                                                }
                                            @endphp
                                            <tr class="leave-app-row"
                                                style="cursor:pointer;"
                                                data-req-id="{{ $req->id }}"
                                                data-employee-name="{{ $employee->full_name }}"
                                                data-employee-code="{{ $employee->employee_id }}"
                                                data-leave-type="{{ $req->leaveType?->name ?: 'n/a' }}"
                                                data-leave-type-id="{{ $req->leave_type_id }}"
                                                data-leave-code="{{ strtolower($req->leaveType?->code ?? '') }}"
                                                data-leave-color="{{ $req->leaveType?->color ?: '#3b82f6' }}"
                                                data-date-range="{{ $dateRange }}"
                                                data-start="{{ $req->start_date?->format('d M Y') }}"
                                                data-end="{{ $req->end_date?->format('d M Y') }}"
                                                data-start-type="{{ str_replace('_',' ', $req->start_date_type) }}"
                                                data-end-type="{{ str_replace('_',' ', $req->end_date_type) }}"
                                                data-duration="{{ floatval($req->duration) }}"
                                                data-reason="{{ strtolower(addslashes($req->reason ?? '')) }}"
                                                data-status="{{ strtolower($req->status) }}"
                                                data-status-label="{{ $statusBadge['lbl'] }}"
                                                data-status-cls="{{ $statusBadge['cls'] }}"
                                                data-status-icon="{{ $statusBadge['icon'] }}"
                                                data-applied="{{ $req->created_at?->format('d M Y, h:i A') }}"
                                                data-created-at="{{ $req->created_at?->timestamp ?: 0 }}"
                                                data-rejection="{{ addslashes($req->rejection_reason ?? '') }}"
                                                data-attachment="{{ $req->attachment_path ? asset('storage/'.$req->attachment_path) : '' }}"
                                                data-workflow="{{ $req->status === 'approved' ? (__('hrms.leave.app.status_approved') ?? 'Approved') : ($req->status === 'rejected' ? (__('hrms.leave.app.status_rejected') ?? 'Rejected') : (in_array($req->status,['unauthorized','unpaid']) ? (__('hrms.leave.app.processed') ?? 'Processed') : (__('hrms.leave.app.level_n', ['level' => $req->current_level]) ?? ('Level ' . $req->current_level)))) }}"
                                                data-update-url="{{ route('hrms.leaves.update-status', $req->id) }}"
                                                data-approve-cancel-url="{{ route('hrms.leaves.approve-cancellation', $req->id) }}"
                                                data-deny-cancel-url="{{ route('hrms.leaves.deny-cancellation', $req->id) }}"
                                                data-cancellation="{{ addslashes($req->cancellation_reason ?? '') }}"
                                                data-notified-names="{{ $notifiedNames }}"
                                                data-remaining="{{ $rowRemaining }}"
                                                data-allocated="{{ $rowAllocated }}"
                                            >
                                                <td class="ps-3" style="white-space: normal; word-break: break-word; overflow-wrap: anywhere;">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="flex-shrink-0 rounded-circle" style="width:9px;height:9px;background:{{ $req->leaveType?->color ?: '#3b82f6' }};display:inline-block;"></span>
                                                        <div style="min-width:0; flex-grow:1; white-space: normal; word-break: break-word; overflow-wrap: anywhere;">
                                                            <div class="fw-semibold text-dark fs-13" style="line-height:1.3;">{{ $req->leaveType?->name ?: 'N/A' }}</div>
                                                            <div class="text-muted fs-11 mt-0.5" style="font-weight: 500;">{{ $req->leaveType?->code ?? '' }}</div>
                                                            @if(in_array($req->status, ['cancellation_requested', 'cancelled']) && !empty($req->cancellation_reason))
                                                                @php
                                                                    $isLongCancelReason = (mb_strlen($req->cancellation_reason ?? '') > 70) || (substr_count($req->cancellation_reason ?? '', "\n") > 1);
                                                                @endphp
                                                                <div class="text-warning fs-11 mt-2" style="max-width: 250px;">
                                                                    <span class="fw-semibold"><i class="feather-rotate-ccw me-1"></i>Cancellation:</span>
                                                                    <div class="leave-cancel-reason-text mb-0 text-muted fs-11" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; word-break: break-word; white-space: normal; line-height: 1.4; color: inherit;">
                                                                        {{ $req->cancellation_reason }}
                                                                    </div>
                                                                    @if($isLongCancelReason)
                                                                        <a href="#" class="leave-toggle-cancel-reason-btn fs-10 text-primary fw-semibold d-inline-block mt-0.5" onclick="toggleLeaveCancelReasonText(this); return false;">See more</a>
                                                                    @endif
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </td>
                                                <td style="white-space: nowrap;">
                                                    <span class="fw-semibold text-dark fs-13">{{ $dateRange }}</span>
                                                    <div class="text-muted fs-11 mt-0.5">Applied {{ $req->created_at ? $req->created_at->format('d M, H:i') : '—' }}</div>
                                                </td>
                                                <td class="text-center" style="white-space: nowrap;">
                                                    <span class="badge bg-light text-dark fw-bold px-2.5 py-1.5 fs-12" style="border: 1px solid #e2e8f0; border-radius: 4px;">{{ floatval($req->duration) }}</span>
                                                </td>
                                                <td style="white-space: nowrap;">
                                                    <span class="badge {{ $statusBadge['cls'] }} rounded-pill px-2.5 py-1 fs-11">
                                                        <i class="{{ $statusBadge['icon'] }} me-1"></i>{{ $statusBadge['lbl'] }}
                                                    </span>
                                                </td>
                                                <td class="text-center" style="white-space: nowrap;">
                                                    @if($req->attachment_path)
                                                        <a href="{{ asset('storage/'.$req->attachment_path) }}" target="_blank" class="text-primary text-decoration-none" onclick="event.stopPropagation();">
                                                            <i class="feather-paperclip fs-14"></i>
                                                        </a>
                                                    @else
                                                        <span class="text-muted fs-13">—</span>
                                                    @endif
                                                </td>
                                                <td class="text-end pe-3" style="white-space: nowrap; min-width:110px;">
                                                    <div class="d-flex align-items-center justify-content-end gap-1 flex-wrap">
                                                        {{-- Eye / detail button --}}
                                                        <button type="button"
                                                            class="btn btn-sm open-leave-detail"
                                                            title="View Details"
                                                            data-bs-toggle="offcanvas"
                                                            data-bs-target="#leaveDetailDrawer"
                                                            style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0; background: #f1f5f9; border: 1px solid #e2e8f0; color: #475569;">
                                                            <i class="feather-eye fs-14"></i>
                                                        </button>

                                                        {{-- Unified Withdraw / Cancellation Delete button --}}
                                                        @if($req->canWithdraw())
                                                            <form method="POST" action="{{ route('hrms.leaves.withdraw', $req->id) }}" onsubmit="return confirm('Withdraw this leave application?')" class="d-inline" onclick="event.stopPropagation();">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-soft-danger border" 
                                                                        title="Withdraw Application"
                                                                        style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                                                                    <i class="feather-trash-2 fs-14"></i>
                                                                </button>
                                                            </form>
                                                        @elseif($req->canRequestCancellation())
                                                            <button type="button" class="btn btn-sm btn-soft-danger border" 
                                                                    title="Request Cancellation"
                                                                    onclick="event.stopPropagation(); openLeaveCancellationModal({{ $req->id }}, '{{ route('hrms.leaves.request-cancellation', $req->id) }}')"
                                                                    style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                                                                <i class="feather-trash-2 fs-14"></i>
                                                            </button>
                                                        @else
                                                            <button type="button" class="btn btn-sm btn-light border disabled" 
                                                                    style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;" disabled onclick="event.stopPropagation();">
                                                                <i class="feather-trash-2 fs-14"></i>
                                                            </button>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                        <tr id="no_matching_emp_leave_apps_row" class="d-none">
                                            <td colspan="10" class="text-center py-5 text-muted">
                                                <i class="feather-folder fs-3 d-block mb-2 text-secondary"></i>
                                                No matching leave applications found.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Leave Applications Pagination Container -->
                            <div class="erp-pagination-container py-3 px-3 border-top d-none" id="empLeaveAppsPaginationContainer">
                                <ul class="erp-pagination mb-2 justify-content-center" id="emp_leave_apps_pagination_ul">
                                    <!-- Dynamically generated pagination links -->
                                </ul>
                                <div class="erp-pagination-info text-center">
                                    Showing <span id="emp_leave_apps_showing_start">0</span> to <span id="emp_leave_apps_showing_end">0</span> of <strong id="emp_leave_apps_total_count">0</strong> entries
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- 2. LEAVE ENCASHMENTS VIEW -->
                    <div id="leaveEncashmentsViewContainer" class="d-none">
                        @if(!isset($empLeaveEncashments) || $empLeaveEncashments->isEmpty())
                            <div class="p-5 text-center text-muted">
                                <i class="feather-dollar-sign fs-24 text-secondary d-block mb-2"></i>
                                No leave encashment requests submitted by this employee yet.
                            </div>
                        @else
                            <div class="table-responsive" style="overflow: visible; width: 100%;">
                                <table class="table table-hover align-middle mb-0" id="empLeaveEncashmentTable" style="table-layout: fixed; width: 100%;">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="fs-12 text-uppercase text-muted fw-semibold ps-3" style="width: 30%;">LEAVE TYPE</th>
                                            <th class="fs-12 text-uppercase text-muted fw-semibold" style="width: 30%;">REASON</th>
                                            <th class="fs-12 text-uppercase text-muted fw-semibold text-center" style="width: 20%;">STATUS</th>
                                            <th class="fs-12 text-uppercase text-muted fw-semibold text-end pe-4" style="width: 20%;">ACTION</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($empLeaveEncashments as $enc)
                                            @php
                                                $encStatusBadge = match($enc->status) {
                                                    'approved' => ['cls' => 'bg-soft-success text-success', 'icon' => 'feather-check-circle', 'lbl' => __('hrms.leave.status_approved')],
                                                    'pending'  => ['cls' => 'bg-soft-warning text-warning', 'icon' => 'feather-clock',        'lbl' => __('hrms.leave.status_pending')],
                                                    'rejected' => ['cls' => 'bg-soft-danger text-danger',   'icon' => 'feather-x-circle',     'lbl' => __('hrms.leave.status_rejected')],
                                                    default    => ['cls' => 'bg-light text-secondary',      'icon' => 'feather-circle',       'lbl' => ucfirst($enc->status)],
                                                };
                                            @endphp
                                            <tr class="emp-encash-row"
                                                data-enc-id="{{ $enc->id }}"
                                                data-leave-type="{{ strtolower($enc->leaveType?->name ?: 'n/a') }}"
                                                data-leave-type-id="{{ $enc->leave_type_id }}"
                                                data-reason="{{ strtolower(addslashes($enc->reason ?? '')) }}"
                                                data-status="{{ strtolower($enc->status) }}"
                                                data-days="{{ floatval($enc->requested_days) }}"
                                                data-created-at="{{ $enc->created_at?->timestamp ?: 0 }}"
                                            >
                                                <td class="ps-3" style="white-space: normal; word-break: break-word; overflow-wrap: anywhere;">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="flex-shrink-0 rounded-circle" style="width:9px;height:9px;background:{{ $enc->leaveType?->color ?: '#3b82f6' }};display:inline-block;"></span>
                                                        <div style="min-width:0; flex-grow:1;">
                                                            <div class="fw-semibold text-dark fs-13" style="line-height:1.3;">{{ $enc->leaveType?->name ?: 'N/A' }}</div>
                                                            <div class="text-muted fs-11 mt-0.5" style="font-weight: 500;">{{ $enc->leaveType?->code ?? '' }}</div>
                                                            <div class="text-muted fs-11 mt-1 d-flex align-items-center gap-1">
                                                                <i class="feather-calendar" style="font-size: 11px;"></i>
                                                                <span>{{ $enc->created_at ? $enc->created_at->format('d M Y') : '—' }}</span>
                                                                <span class="mx-1">•</span>
                                                                <i class="feather-clock" style="font-size: 11px;"></i>
                                                                <span>{{ floatval($enc->requested_days) }} Days</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td style="white-space: normal; word-break: break-word; overflow-wrap: anywhere;">
                                                    <span class="fs-12 text-muted" style="line-height: 1.3;">{{ $enc->reason ?: __('hrms.leave.app.no_reason_provided') }}</span>
                                                </td>
                                                <td class="text-center" style="white-space: nowrap;">
                                                    <span class="badge {{ $encStatusBadge['cls'] }} rounded-pill px-2.5 py-1 fs-11">
                                                        <i class="{{ $encStatusBadge['icon'] }} me-1"></i>{{ $encStatusBadge['lbl'] }}
                                                    </span>
                                                </td>
                                                <td class="text-end pe-3" style="white-space: nowrap;">
                                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                                        @if($isAdmin)
                                                            <div class="dropdown {{ ($loop->last || ($loop->count > 1 && $loop->iteration >= $loop->count - 1)) ? 'dropup' : '' }} d-inline-block position-relative" onclick="event.stopPropagation();">
                                                                <button class="btn btn-sm dropdown-toggle py-1 px-3 d-inline-flex align-items-center justify-content-between text-capitalize fw-semibold shadow-sm" 
                                                                        type="button" 
                                                                        data-bs-toggle="dropdown" 
                                                                        data-bs-boundary="viewport"
                                                                        aria-expanded="false" 
                                                                        style="background-color: var(--bs-primary) !important; color: #ffffff !important; font-size: 11.5px; height: 32px; border-radius: 8px; min-width: 130px; border: none;" 
                                                                        title="Change Status">
                                                                    <span>{{ $enc->status === 'approved' ? __('hrms.leave.app.status_approved') : ($enc->status === 'rejected' ? __('hrms.leave.app.status_rejected') : __('hrms.leave.app.status_pending')) }}</span>
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-1.5 mt-1 fs-12" style="min-width: 130px; border-radius: 8px; background: #ffffff; z-index: 1050;">
                                                                    <li>
                                                                        <form action="{{ route('hrms.leaves.encashment.approve', $enc->id) }}" method="POST" class="m-0">
                                                                            @csrf
                                                                            <button type="submit" class="dropdown-item rounded py-1.5 px-3 text-dark fw-medium d-flex align-items-center justify-content-between {{ $enc->status === 'approved' ? 'bg-light text-primary fw-bold' : '' }}" style="{{ $enc->status === 'approved' ? 'color: var(--bs-primary) !important;' : '' }}">
                                                                                <span>{{ __('hrms.leave.app.status_approved') }}</span>
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                    <li>
                                                                        <form action="{{ route('hrms.leaves.encashment.reject', $enc->id) }}" method="POST" class="m-0">
                                                                            @csrf
                                                                            <button type="submit" class="dropdown-item rounded py-1.5 px-3 text-dark fw-medium d-flex align-items-center justify-content-between {{ $enc->status === 'rejected' ? 'bg-light text-primary fw-bold' : '' }}" style="{{ $enc->status === 'rejected' ? 'color: var(--bs-primary) !important;' : '' }}">
                                                                                <span>{{ __('hrms.leave.app.status_rejected') }}</span>
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        @endif

                                                        @if($enc->status === 'pending')
                                                            <form action="{{ route('hrms.leaves.encashment.destroy', $enc->id) }}" method="POST" onsubmit="return confirm('Withdraw this encashment request?')" class="d-inline-flex m-0" onclick="event.stopPropagation();">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-soft-danger border" 
                                                                        title="Withdraw Request"
                                                                        style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                                                                    <i class="feather-trash-2 fs-14"></i>
                                                                </button>
                                                            </form>
                                                        @elseif(!$isAdmin)
                                                            <span class="text-muted fs-13">—</span>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                        <tr id="no_matching_emp_leave_enc_row" class="d-none">
                                            <td colspan="10" class="text-center py-5 text-muted">
                                                <i class="feather-dollar-sign fs-3 d-block mb-2 text-secondary"></i>
                                                No matching leave encashment requests found.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Leave Encashments Pagination Container -->
                            <div class="erp-pagination-container py-3 px-3 border-top d-none" id="empLeaveEncPaginationContainer">
                                <ul class="erp-pagination mb-2 justify-content-center" id="emp_leave_enc_pagination_ul">
                                    <!-- Dynamically generated encashment pagination links -->
                                </ul>
                                <div class="erp-pagination-info text-center">
                                    Showing <span id="emp_leave_enc_showing_start">0</span> to <span id="emp_leave_enc_showing_end">0</span> of <strong id="emp_leave_enc_total_count">0</strong> entries
                                </div>
                            </div>
                        @endif
                    </div>
            </div>
        </div>
    </div>
</div>
</div>

    {{-- Detail Drawer --}}
    {{-- Detail Drawer --}}
    <x-ui.drawer id="leaveDetailDrawer" :title="__('hrms.employees.lbl_leave_app_detail')" style="width:440px;max-width:100%;">
        {{-- Merged Employee & Leave Type Card --}}
        <div class="mb-3 p-3 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
            <div class="d-flex align-items-center gap-2 mb-2 pb-2 border-bottom" style="border-color: #e2e8f0 !important;">
                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white fw-bold" style="width:34px;height:34px;font-size:13px;" id="ld-emp-avatar">E</div>
                <div>
                    <div class="fw-bold fs-13 text-dark" id="ld-emp-name">—</div>
                    <div class="fs-11 text-muted" id="ld-emp-code"></div>
                </div>
            </div>

            <div class="d-flex align-items-start gap-3">
                <span id="ld-color-dot" class="rounded-circle flex-shrink-0 mt-1" style="width:12px;height:12px;display:inline-block;"></span>
                <div class="flex-grow-1">
                    <div class="fw-bold fs-14 text-dark" id="ld-leave-type">—</div>
                    <div class="fs-12 text-muted mt-1" id="ld-balance-inline"></div>
                    <div class="fs-11 text-muted mt-1">{{ __('hrms.leave.app.applied_on') }} <span class="fw-semibold text-dark" id="ld-applied">—</span></div>
                </div>
                <span class="badge rounded-pill px-2 py-1 fs-11 flex-shrink-0" id="ld-status-badge"></span>
            </div>
        </div>

        <hr class="my-3">

        {{-- Period & Duration --}}
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">{{ __('hrms.leave.app.duration_timeline') }}</div>
                <div class="fw-semibold text-dark fs-13" id="ld-date-range">—</div>
                <div class="text-muted fs-12 mt-1" id="ld-session-info"></div>
            </div>
            <div class="text-end">
                <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">{{ __('hrms.leave.app.duration') }}</div>
                <div class="fw-bold fs-22 text-primary" id="ld-duration">—</div>
            </div>
        </div>

        <hr class="my-3">

        {{-- Reason --}}
        <div class="mb-3">
            <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">{{ __('hrms.leave.app.reason') }}</div>
            <div class="fs-13 text-dark" id="ld-reason" style="white-space:pre-line;">—</div>
        </div>

        {{-- Workflow Level & Attachment --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">{{ __('hrms.leave.app.workflow_level') }}</div>
                <div class="fs-13 text-dark" id="ld-workflow">—</div>
            </div>
            <div class="d-none text-end" id="ld-attach-wrap">
                <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">{{ __('hrms.leave.app.view_attachment') }}</div>
                <a id="ld-attach-link" href="#" target="_blank" class="btn btn-sm btn-soft-primary d-inline-flex align-items-center gap-1">
                    <i class="feather-paperclip fs-12"></i> {{ __('hrms.leave.app.view_attachment') }}
                </a>
            </div>
        </div>

        {{-- Rejection Reason --}}
        <div class="mb-3 d-none" id="ld-rejection-wrap">
            <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">{{ __('hrms.leave.app.rejection_reason') }}</div>
            <div class="alert alert-soft-danger py-2 px-3 fs-13 mb-0" id="ld-rejection"></div>
        </div>

        {{-- Cancellation Reason --}}
        <div class="mb-3 d-none" id="ld-cancellation-wrap">
            <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">Cancellation Reason</div>
            <div class="alert alert-soft-warning py-2 px-3 fs-13 mb-0" id="ld-cancellation" style="word-break: break-word !important; overflow-wrap: anywhere !important;"></div>
        </div>

        {{-- Notified Members --}}
        <div class="mb-3 d-none" id="ld-notified-wrap">
            <div class="text-muted fs-11 text-uppercase fw-semibold mb-1" style="letter-spacing:.5px;">{{ __('hrms.leave.app.notify_members') }}</div>
            <div class="fs-13 text-dark" id="ld-notified-names">—</div>
        </div>

        {{-- Status Change (For Admins) --}}
        @if($isAdmin)
            <hr class="my-3" id="ld-status-hr">
            <div id="ld-status-change-wrap">
                <div class="text-muted fs-11 text-uppercase fw-semibold mb-2" style="letter-spacing:.5px;">{{ __('hrms.employees.lbl_update_status') }}</div>
                <form method="POST" id="ld-status-form" action="">
                    @csrf
                    <div class="d-flex gap-2 align-items-center">
                        <div class="flex-grow-1" style="margin-bottom: -1rem;">
                            <x-ui.select name="status" id="ld-status-select" class="odoo-select2">
                                <option value="pending">{{ __('hrms.leave.app.status_pending') }}</option>
                                <option value="approved">{{ __('hrms.leave.app.status_approved') }}</option>
                                <option value="rejected">{{ __('hrms.leave.app.status_rejected') }}</option>
                                <option value="unauthorized">{{ __('hrms.leave.app.status_unauthorized') }}</option>
                                <option value="unpaid">{{ __('hrms.leave.app.status_unpaid') }}</option>
                            </x-ui.select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm fw-bold px-3 d-flex align-items-center gap-1" style="height: 38px; border-radius: 6px;">
                            <i class="feather-check fs-12"></i> {{ __('hrms.common.apply') }}
                        </button>
                    </div>
                    <div class="mt-2 d-none" id="ld-rejection-input-wrap">
                        <div class="text-muted fs-11 text-uppercase fw-semibold mb-2 mt-2" style="letter-spacing:.5px;">{{ __('hrms.leave.app.rejection_reason') }}</div>
                        <x-ui.textarea name="rejection_reason" id="ld-rejection-reason-input" rows="2" placeholder="{{ __('hrms.leave.app.rejection_reason_placeholder') }}" />
                    </div>
                </form>
            </div>
        @endif

        <x-slot:footer>
            <button type="button" class="btn btn-light border fw-semibold text-uppercase" data-bs-dismiss="offcanvas">CLOSE PANEL</button>
        </x-slot:footer>
    </x-ui.drawer>

    {{-- Leave Cancellation Request Modal --}}
    <div class="modal fade" id="leaveCancellationModal" tabindex="-1" aria-labelledby="leaveCancellationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title fw-bold text-dark" id="leaveCancellationModalLabel">
                        <i class="feather-x-circle text-warning me-2"></i>Request Leave Cancellation
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="leaveCancellationForm" method="POST" action="">
                    @csrf
                    <div class="modal-body p-4">
                        <p class="text-muted fs-13 mb-3">
                            Please provide a reason for requesting cancellation of this approved leave. The admin will review and approve or deny your request.
                        </p>
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark fs-13">Cancellation Reason <span class="text-danger">*</span></label>
                            <textarea name="cancellation_reason" id="leave_cancellation_reason" class="form-control fs-13" rows="3" placeholder="Explain why you want to cancel this leave..." required maxlength="1000"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-top py-3 d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning text-dark fw-semibold">
                            <i class="feather-send me-1"></i>Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Leave Rules Dynamic Modal --}}
    <div class="modal fade" id="empLeaveRulesDynamicModal" tabindex="-1" aria-hidden="true" style="display: none;">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fw-bold">
                            <i class="feather-sliders text-primary me-2"></i><span id="empDynamicLeaveTypeName"></span> {{ __('hrms.employees.mdl_leave_rules_title') }}
                        </h5>
                        <div class="text-muted fs-12 mt-1" id="empDynamicLeaveTypeMeta"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body bg-light" id="empDynamicLeaveRulesBody">
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('hrms.employees.mdl_btn_close') }}</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Apply Leave Modal --}}
    <div class="modal fade" id="empApplyLeaveModal" aria-labelledby="empApplyLeaveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title fw-bold text-dark" id="empApplyLeaveModalLabel">{{ __('hrms.leave.app.apply_for_leave') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.leaves.store') }}" method="POST" enctype="multipart/form-data" id="empApplyLeaveForm">
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                    <input type="hidden" name="redirect_tab" value="leaves">
                    <div class="modal-body p-4">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="row">
                            <div class="col-12 mb-3">
                                <x-ui.odoo-form-ui type="select" :label="__('hrms.leave.leave_types')" name="leave_type_id" id="emp_leave_type_select" :required="true" class="emp-odoo-select2-custom">
                                    <option value="">{{ __('hrms.leave.app.select_leave_type') }}</option>
                                    @foreach($allLeaveTypes as $lt)
                                        <option value="{{ $lt->id }}" data-quota="{{ floatval($lt->quota) }}" data-rules='@json($lt->rules ?? [])'>{{ $lt->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <x-ui.odoo-form-ui type="input" inputType="date" :label="__('hrms.leave.app.start_date')" name="start_date" id="emp_start_date" :required="true" class="odoo-underline-input" />
                            </div>
                            <div class="col-md-6 mb-3">
                                <x-ui.odoo-form-ui type="select" :label="__('hrms.leave.app.start_session')" name="start_date_type" id="emp_start_date_type" :required="true" class="emp-odoo-select2-custom">
                                    <option value="full_day">{{ __('hrms.leave.app.full_day') }}</option>
                                    <option value="first_half">{{ __('hrms.leave.app.first_half') }}</option>
                                    <option value="second_half">{{ __('hrms.leave.app.second_half') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <x-ui.odoo-form-ui type="input" inputType="date" :label="__('hrms.leave.app.end_date')" name="end_date" id="emp_end_date" :required="true" class="odoo-underline-input" />
                            </div>
                            <div class="col-md-6 mb-3">
                                <x-ui.odoo-form-ui type="select" :label="__('hrms.leave.app.end_session')" name="end_date_type" id="emp_end_date_type" :required="true" class="emp-odoo-select2-custom">
                                    <option value="full_day">{{ __('hrms.leave.app.full_day') }}</option>
                                    <option value="first_half">{{ __('hrms.leave.app.first_half') }}</option>
                                    <option value="second_half">{{ __('hrms.leave.app.second_half') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>

                        <div class="col-12 mb-3">
                            <div id="emp_calculated_duration_display" class="alert alert-info py-2 fs-12 mb-0">
                                {{ __('hrms.leave.app.estimated_duration_simple', ['duration' => 0]) }}
                            </div>
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="textarea" :label="__('hrms.leave.app.reason_for_leave')" name="reason" :required="true" class="odoo-underline-input" :placeholder="__('hrms.leave.app.reason_placeholder')"></x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="file" :label="__('hrms.leave.app.upload_attachment')" name="attachment" id="emp_attachment" :required="false" helperText="{{ __('hrms.leave.app.formats_allowed') }}" />
                            <div id="emp_attachment_required_warning" class="text-danger fs-12 mt-1 d-none fw-semibold">
                                <i class="feather-alert-triangle"></i> {{ __('hrms.leave.app.attachment_required_warning') }}
                            </div>
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" :label="__('hrms.leave.app.notify_members')" name="notified_contacts[]" id="emp_notified_contacts" :required="false" :multiple="true" class="emp-odoo-select2-custom" :placeholder="__('hrms.leave.app.notify_placeholder')">
                                @foreach ($allEmployees as $emp)
                                    @if ($emp->id !== $employee->id)
                                        <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_id }})</option>
                                    @endif
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>
                    <div class="modal-header border-top py-3 d-flex justify-content-end gap-2" style="border-bottom: none;">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</button>
                        <button type="submit" class="btn btn-primary text-dark">{{ __('hrms.leave.app.submit_request') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Apply Encashment Modal --}}
    <div class="modal fade" id="empApplyEncashmentModal" tabindex="-1" aria-labelledby="empApplyEncashmentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title fw-bold text-dark" id="empApplyEncashmentModalLabel">{{ __('hrms.leave.encashment_app.apply_for_encashment') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="{{ route('hrms.leaves.encashment.store') }}">
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" :label="__('hrms.leave.encashment_app.select_leave_type')" name="leave_type_id" id="emp_encashment_leave_type_id" :required="true" class="emp-odoo-select2-custom">
                                <option value="">{{ __('hrms.leave.encashment_app.select_leave_type') }}...</option>
                                @foreach($allLeaveTypes as $lt)
                                    <option value="{{ $lt->id }}">{{ $lt->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="input" inputType="number" :label="__('hrms.leave.encashment_app.requested_days')" name="requested_days" id="emp_encashment_requested_days" :required="true" class="odoo-underline-input" step="0.5" min="0.5" placeholder="e.g. 2.5" />
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="textarea" :label="__('hrms.leave.encashment_app.reason')" name="reason" id="emp_encashment_reason" :required="false" class="odoo-underline-input" :placeholder="__('hrms.leave.encashment_app.reason_placeholder')" />
                        </div>
                    </div>
                    <div class="modal-header border-top py-3 d-flex justify-content-end gap-2" style="border-bottom: none;">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</button>
                        <button type="submit" class="btn btn-primary text-dark"><i class="feather-check me-1"></i> {{ __('hrms.leave.encashment_app.submit_encashment') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    @push('scripts')
        <script>
            $(document).ready(function() {
                // Initialize Select2 dropdowns
                $('#emp_notified_contacts').select2({
                    theme: 'bootstrap-5',
                    dropdownParent: $('#empApplyLeaveModal .modal-content'),
                    width: '100%'
                });

                $('#emp_leave_type_select').select2({
                    theme: 'bootstrap-5',
                    dropdownParent: $('#empApplyLeaveModal .modal-content'),
                    width: '100%'
                });

                $('#emp_encashment_leave_type_id').select2({
                    theme: 'bootstrap-5',
                    dropdownParent: $('#empApplyEncashmentModal .modal-content'),
                    width: '100%'
                });

                // Toggle view between Leave Applications and Leave Encashments in Employee Profile
                $(document).on('click', '#btnToggleLeaveView', function () {
                    var isEncashmentHidden = $('#leaveEncashmentsViewContainer').hasClass('d-none');
                    if (isEncashmentHidden) {
                        $('#leaveApplicationsViewContainer').addClass('d-none');
                        $('#leaveAppsHeaderTitle').addClass('d-none');
                        $('#leaveAppsToolbar').addClass('d-none');

                        $('#leaveEncashmentsViewContainer').removeClass('d-none');
                        $('#leaveEncashmentsHeaderTitle').removeClass('d-none');
                        $('#leaveEncashmentsToolbar').removeClass('d-none');

                        $('#toggleBtnLabel').html('<i class="feather-list me-1"></i> LEAVE APPLICATIONS');
                    } else {
                        $('#leaveEncashmentsViewContainer').addClass('d-none');
                        $('#leaveEncashmentsHeaderTitle').addClass('d-none');
                        $('#leaveEncashmentsToolbar').addClass('d-none');

                        $('#leaveApplicationsViewContainer').removeClass('d-none');
                        $('#leaveAppsHeaderTitle').removeClass('d-none');
                        $('#leaveAppsToolbar').removeClass('d-none');

                        $('#toggleBtnLabel').html('<i class="feather-dollar-sign me-1"></i> ENCASHMENT DETAILS');
                    }
                });

                // Client-side Leave Application filtering/search/sorting
                var empLeaveAppSortMode = 'date_desc';
                var empLeaveAppFilters = { status: '', leave_type_id: '' };
                var empLeaveAppCurrentPage = 1;
                var empLeaveAppPerPage = 10;

                function refreshEmpLeaveAppRows() {
                    var query = ($('#empLeaveAppSearchInput').val() || '').toLowerCase().trim();
                    var $allRows = $('#leaveAppTable tbody tr.leave-app-row');

                    var $matchingRows = $allRows.filter(function () {
                        var $row = $(this);
                        var lType   = ($row.data('leave-type') || '').toString().toLowerCase();
                        var lCode   = ($row.data('leave-code') || '').toString().toLowerCase();
                        var lReason = ($row.data('reason') || '').toString().toLowerCase();
                        var lStatus = ($row.data('status') || '').toString().toLowerCase();
                        var typeId  = ($row.data('leave-type-id') || '').toString();

                        var matchesSearch = !query || lType.indexOf(query) !== -1 || lCode.indexOf(query) !== -1 || lReason.indexOf(query) !== -1 || lStatus.indexOf(query) !== -1;
                        var matchesStatus = !empLeaveAppFilters.status || lStatus === empLeaveAppFilters.status;
                        var matchesType   = !empLeaveAppFilters.leave_type_id || typeId === empLeaveAppFilters.leave_type_id;

                        return matchesSearch && matchesStatus && matchesType;
                    });

                    var totalItems = $matchingRows.length;
                    var totalPages = Math.ceil(totalItems / empLeaveAppPerPage) || 1;

                    if (empLeaveAppCurrentPage > totalPages) {
                        empLeaveAppCurrentPage = totalPages;
                    }
                    if (empLeaveAppCurrentPage < 1) {
                        empLeaveAppCurrentPage = 1;
                    }

                    var matchingArr = $matchingRows.get();
                    matchingArr.sort(function (a, b) {
                        var $a = $(a), $b = $(b);
                        if (empLeaveAppSortMode === 'date_desc') {
                            return ($b.data('created-at') || 0) - ($a.data('created-at') || 0);
                        } else if (empLeaveAppSortMode === 'date_asc') {
                            return ($a.data('created-at') || 0) - ($b.data('created-at') || 0);
                        } else if (empLeaveAppSortMode === 'duration_desc') {
                            return parseFloat($b.data('duration') || 0) - parseFloat($a.data('duration') || 0);
                        } else if (empLeaveAppSortMode === 'duration_asc') {
                            return parseFloat($a.data('duration') || 0) - parseFloat($b.data('duration') || 0);
                        }
                        return 0;
                    });

                    var startIndex = (empLeaveAppCurrentPage - 1) * empLeaveAppPerPage;
                    var endIndex = Math.min(startIndex + empLeaveAppPerPage, totalItems);

                    $allRows.addClass('d-none');

                    $.each(matchingArr, function (idx, row) {
                        var $r = $(row);
                        $('#leaveAppTable tbody').append($r);
                        if (idx >= startIndex && idx < endIndex) {
                            $r.removeClass('d-none');
                        }
                    });

                    if (totalItems > empLeaveAppPerPage) {
                        $('#empLeaveAppsPaginationContainer').removeClass('d-none');
                    } else {
                        $('#empLeaveAppsPaginationContainer').addClass('d-none');
                    }

                    if (totalItems === 0) {
                        $('#no_matching_emp_leave_apps_row').removeClass('d-none');
                    } else {
                        $('#no_matching_emp_leave_apps_row').addClass('d-none');
                    }

                    $('#emp_leave_apps_showing_start').text(totalItems === 0 ? 0 : startIndex + 1);
                    $('#emp_leave_apps_showing_end').text(endIndex);
                    $('#emp_leave_apps_total_count').text(totalItems);

                    var paginationHtml = '';
                    paginationHtml += '<li class="page-item ' + (empLeaveAppCurrentPage === 1 ? 'disabled' : '') + '">';
                    paginationHtml += '<a class="page-link" href="#" data-page="' + (empLeaveAppCurrentPage - 1) + '" aria-label="Previous"><i class="feather-chevron-left"></i></a>';
                    paginationHtml += '</li>';

                    for (var i = 1; i <= totalPages; i++) {
                        paginationHtml += '<li class="page-item ' + (empLeaveAppCurrentPage === i ? 'active' : '') + '">';
                        paginationHtml += '<a class="page-link" href="#" data-page="' + i + '">' + i + '</a>';
                        paginationHtml += '</li>';
                    }

                    paginationHtml += '<li class="page-item ' + (empLeaveAppCurrentPage === totalPages ? 'disabled' : '') + '">';
                    paginationHtml += '<a class="page-link" href="#" data-page="' + (empLeaveAppCurrentPage + 1) + '" aria-label="Next"><i class="feather-chevron-right"></i></a>';
                    paginationHtml += '</li>';

                    $('#emp_leave_apps_pagination_ul').html(paginationHtml);
                }

                $('#empLeaveAppSearchInput').on('keyup input search', function () {
                    empLeaveAppCurrentPage = 1;
                    refreshEmpLeaveAppRows();
                });

                $('.emp-leave-app-sort-link').on('click', function (e) {
                    e.preventDefault();
                    empLeaveAppSortMode = $(this).data('sort') || 'date_desc';
                    $('.emp-leave-app-sort-link').removeClass('active').find('.sort-check').addClass('d-none');
                    $(this).addClass('active').find('.sort-check').removeClass('d-none');
                    empLeaveAppCurrentPage = 1;
                    refreshEmpLeaveAppRows();
                });

                $('#btnEmpLeaveAppFilterApply').on('click', function () {
                    empLeaveAppFilters.status = $('#empLeaveAppFilterStatus').val() || '';
                    empLeaveAppFilters.leave_type_id = $('#empLeaveAppFilterType').val() || '';
                    empLeaveAppCurrentPage = 1;
                    refreshEmpLeaveAppRows();
                    $('#btnEmpLeaveAppFilterApply').closest('.dropdown-menu').removeClass('show');
                });

                $('#btnEmpLeaveAppFilterReset').on('click', function () {
                    $('#empLeaveAppFilterStatus').val('').trigger('change');
                    $('#empLeaveAppFilterType').val('').trigger('change');
                    empLeaveAppFilters = { status: '', leave_type_id: '' };
                    empLeaveAppCurrentPage = 1;
                    refreshEmpLeaveAppRows();
                    $('#btnEmpLeaveAppFilterReset').closest('.dropdown-menu').removeClass('show');
                });

                $(document).on('click', '#emp_leave_apps_pagination_ul .page-link', function (e) {
                    e.preventDefault();
                    var page = $(this).data('page');
                    if (page && !$(this).parent().hasClass('disabled')) {
                        empLeaveAppCurrentPage = parseInt(page);
                        refreshEmpLeaveAppRows();
                    }
                });

                // Client-side Leave Encashment filtering/search/sorting
                var empLeaveEncSortMode = 'date_desc';
                var empLeaveEncFilters = { status: '', leave_type_id: '' };
                var empLeaveEncCurrentPage = 1;
                var empLeaveEncPerPage = 10;

                function refreshEmpLeaveEncRows() {
                    var query = ($('#empLeaveEncSearchInput').val() || '').toLowerCase().trim();
                    var $allRows = $('#empLeaveEncashmentTable tbody tr.emp-encash-row');

                    var $matchingRows = $allRows.filter(function () {
                        var $row = $(this);
                        var lType   = ($row.data('leave-type') || '').toString().toLowerCase();
                        var lReason = ($row.data('reason') || '').toString().toLowerCase();
                        var lStatus = ($row.data('status') || '').toString().toLowerCase();
                        var typeId  = ($row.data('leave-type-id') || '').toString();

                        var matchesSearch = !query || lType.indexOf(query) !== -1 || lReason.indexOf(query) !== -1 || lStatus.indexOf(query) !== -1;
                        var matchesStatus = !empLeaveEncFilters.status || lStatus === empLeaveEncFilters.status;
                        var matchesType   = !empLeaveEncFilters.leave_type_id || typeId === empLeaveEncFilters.leave_type_id;

                        return matchesSearch && matchesStatus && matchesType;
                    });

                    var totalItems = $matchingRows.length;
                    var totalPages = Math.ceil(totalItems / empLeaveEncPerPage) || 1;

                    if (empLeaveEncCurrentPage > totalPages) {
                        empLeaveEncCurrentPage = totalPages;
                    }
                    if (empLeaveEncCurrentPage < 1) {
                        empLeaveEncCurrentPage = 1;
                    }

                    var matchingArr = $matchingRows.get();
                    matchingArr.sort(function (a, b) {
                        var $a = $(a), $b = $(b);
                        if (empLeaveEncSortMode === 'date_desc') {
                            return ($b.data('created-at') || 0) - ($a.data('created-at') || 0);
                        } else if (empLeaveEncSortMode === 'date_asc') {
                            return ($a.data('created-at') || 0) - ($b.data('created-at') || 0);
                        } else if (empLeaveEncSortMode === 'days_desc') {
                            return parseFloat($b.data('days') || 0) - parseFloat($a.data('days') || 0);
                        } else if (empLeaveEncSortMode === 'days_asc') {
                            return parseFloat($a.data('days') || 0) - parseFloat($b.data('days') || 0);
                        }
                        return 0;
                    });

                    var startIndex = (empLeaveEncCurrentPage - 1) * empLeaveEncPerPage;
                    var endIndex = Math.min(startIndex + empLeaveEncPerPage, totalItems);

                    $allRows.addClass('d-none');

                    $.each(matchingArr, function (idx, row) {
                        var $r = $(row);
                        $('#empLeaveEncashmentTable tbody').append($r);
                        if (idx >= startIndex && idx < endIndex) {
                            $r.removeClass('d-none');
                        }
                    });

                    if (totalItems > empLeaveEncPerPage) {
                        $('#empLeaveEncPaginationContainer').removeClass('d-none');
                    } else {
                        $('#empLeaveEncPaginationContainer').addClass('d-none');
                    }

                    if (totalItems === 0) {
                        $('#no_matching_emp_leave_enc_row').removeClass('d-none');
                    } else {
                        $('#no_matching_emp_leave_enc_row').addClass('d-none');
                    }

                    $('#emp_leave_enc_showing_start').text(totalItems === 0 ? 0 : startIndex + 1);
                    $('#emp_leave_enc_showing_end').text(endIndex);
                    $('#emp_leave_enc_total_count').text(totalItems);

                    var paginationHtml = '';
                    paginationHtml += '<li class="page-item ' + (empLeaveEncCurrentPage === 1 ? 'disabled' : '') + '">';
                    paginationHtml += '<a class="page-link" href="#" data-page="' + (empLeaveEncCurrentPage - 1) + '" aria-label="Previous"><i class="feather-chevron-left"></i></a>';
                    paginationHtml += '</li>';

                    for (var i = 1; i <= totalPages; i++) {
                        paginationHtml += '<li class="page-item ' + (empLeaveEncCurrentPage === i ? 'active' : '') + '">';
                        paginationHtml += '<a class="page-link" href="#" data-page="' + i + '">' + i + '</a>';
                        paginationHtml += '</li>';
                    }

                    paginationHtml += '<li class="page-item ' + (empLeaveEncCurrentPage === totalPages ? 'disabled' : '') + '">';
                    paginationHtml += '<a class="page-link" href="#" data-page="' + (empLeaveEncCurrentPage + 1) + '" aria-label="Next"><i class="feather-chevron-right"></i></a>';
                    paginationHtml += '</li>';

                    $('#emp_leave_enc_pagination_ul').html(paginationHtml);
                }

                $('#empLeaveEncSearchInput').on('keyup input search', function () {
                    empLeaveEncCurrentPage = 1;
                    refreshEmpLeaveEncRows();
                });

                $('.emp-leave-enc-sort-link').on('click', function (e) {
                    e.preventDefault();
                    empLeaveEncSortMode = $(this).data('sort') || 'date_desc';
                    $('.emp-leave-enc-sort-link').removeClass('active').find('.encash-sort-check').addClass('d-none');
                    $(this).addClass('active').find('.encash-sort-check').removeClass('d-none');
                    empLeaveEncCurrentPage = 1;
                    refreshEmpLeaveEncRows();
                });

                $('#btnEmpLeaveEncFilterApply').on('click', function () {
                    empLeaveEncFilters.status = $('#empLeaveEncFilterStatus').val() || '';
                    empLeaveEncFilters.leave_type_id = $('#empLeaveEncFilterType').val() || '';
                    empLeaveEncCurrentPage = 1;
                    refreshEmpLeaveEncRows();
                    $('#btnEmpLeaveEncFilterApply').closest('.dropdown-menu').removeClass('show');
                });

                $('#btnEmpLeaveEncFilterReset').on('click', function () {
                    $('#empLeaveEncFilterStatus').val('').trigger('change');
                    $('#empLeaveEncFilterType').val('').trigger('change');
                    empLeaveEncFilters = { status: '', leave_type_id: '' };
                    empLeaveEncCurrentPage = 1;
                    refreshEmpLeaveEncRows();
                    $('#btnEmpLeaveEncFilterReset').closest('.dropdown-menu').removeClass('show');
                });

                $(document).on('click', '#emp_leave_enc_pagination_ul .page-link', function (e) {
                    e.preventDefault();
                    var page = $(this).data('page');
                    if (page && !$(this).parent().hasClass('disabled')) {
                        empLeaveEncCurrentPage = parseInt(page);
                        refreshEmpLeaveEncRows();
                    }
                });

                // Eye / Detail drawer logic
                $(document).on('click', '.open-leave-detail', function () {
                    var $row = $(this).closest('tr.leave-app-row');
                    var empName = $row.attr('data-employee-name') || '—';
                    var empCode = $row.attr('data-employee-code') || '';
                    var leaveType = $row.attr('data-leave-type') || '—';
                    var leaveColor = $row.attr('data-leave-color') || '#3b82f6';
                    var dateRange = $row.attr('data-date-range') || '—';
                    var startType = $row.attr('data-start-type') || 'full_day';
                    var endType = $row.attr('data-end-type') || 'full_day';
                    var duration = $row.attr('data-duration') || '0';
                    var reason = $row.attr('data-reason') || '—';
                    var statusLbl = $row.attr('data-status-label') || '—';
                    var statusCls = $row.attr('data-status-cls') || 'bg-light text-secondary';
                    var applied = $row.attr('data-applied') || '—';
                    var workflow = $row.attr('data-workflow') || '—';
                    var rejection = $row.attr('data-rejection') || '';
                    var cancellation = $row.attr('data-cancellation') || '';
                    var attachment = $row.attr('data-attachment') || '';
                    var notifiedNames = $row.attr('data-notified-names') || '';
                    var remaining = $row.attr('data-remaining') || '0';
                    var allocated = $row.attr('data-allocated') || '0';

                    $('#ld-emp-name').text(empName);
                    $('#ld-emp-code').text(empCode ? '#' + empCode : '');
                    $('#ld-emp-avatar').text(empName.charAt(0).toUpperCase());
                    $('#ld-leave-type').text(leaveType);
                    $('#ld-color-dot').css('background-color', leaveColor);
                    $('#ld-date-range').text(dateRange);
                    $('#ld-duration').text(parseFloat(duration) + (parseFloat(duration) === 1 ? ' Day' : ' Days'));
                    $('#ld-reason').text(reason);
                    $('#ld-status-badge').text(statusLbl).removeClass().addClass('badge rounded-pill px-2 py-1 fs-11 flex-shrink-0 ' + statusCls);
                    $('#ld-applied').text(applied);
                    $('#ld-workflow').text(workflow);
                    $('#ld-balance-inline').text('Remaining Balance: ' + remaining + ' / ' + allocated + ' Days');

                    var sessionText = startType.replace('_', ' ').toLowerCase();
                    if ($row.attr('data-start') !== $row.attr('data-end')) {
                        sessionText += ' to ' + endType.replace('_', ' ').toLowerCase();
                    }
                    $('#ld-session-info').text('Session: ' + sessionText);

                    if (attachment) {
                        $('#ld-attach-wrap').removeClass('d-none');
                        $('#ld-attach-link').attr('href', attachment);
                    } else {
                        $('#ld-attach-wrap').addClass('d-none');
                    }

                    if (rejection) {
                        $('#ld-rejection-wrap').removeClass('d-none');
                        $('#ld-rejection').text(rejection);
                    } else {
                        $('#ld-rejection-wrap').addClass('d-none');
                    }

                    if (cancellation) {
                        $('#ld-cancellation-wrap').removeClass('d-none');
                        $('#ld-cancellation').text(cancellation);
                    } else {
                        $('#ld-cancellation-wrap').addClass('d-none');
                    }

                    if (notifiedNames) {
                        $('#ld-notified-wrap').removeClass('d-none');
                        $('#ld-notified-names').text(notifiedNames);
                    } else {
                        $('#ld-notified-wrap').addClass('d-none');
                    }

                    var status = ($row.attr('data-status') || '').toLowerCase();
                    var updateUrl = $row.attr('data-update-url') || '';
                    var approveCancelUrl = $row.attr('data-approve-cancel-url') || '';
                    var denyCancelUrl = $row.attr('data-deny-cancel-url') || '';

                    var form = $('#ld-status-form');
                    form.data('update-url', updateUrl);
                    form.data('approve-cancel-url', approveCancelUrl);
                    form.data('deny-cancel-url', denyCancelUrl);

                    if (status === 'cancelled') {
                        $('#ld-status-change-wrap').addClass('d-none');
                        $('#ld-status-hr').addClass('d-none');
                    } else {
                        $('#ld-status-change-wrap').removeClass('d-none');
                        $('#ld-status-hr').removeClass('d-none');
                    }

                    var $select = $('#ld-status-select');
                    if ($select.length) {
                        $select.empty();
                        if (status === 'cancellation_requested') {
                            $select.append('<option value="approve_cancellation">Approve Cancellation</option>');
                            $select.append('<option value="deny_cancellation">Deny Cancellation</option>');
                            $select.val('approve_cancellation').trigger('change');
                        } else {
                            $select.append('<option value="approved">Approve</option>');
                            $select.append('<option value="rejected">Reject</option>');
                            $select.append('<option value="pending">Pending</option>');
                            $select.append('<option value="unauthorized">Unauthorized</option>');
                            $select.append('<option value="unpaid">Unpaid</option>');
                            $select.val(status).trigger('change');
                        }
                    }

                    if (status === 'rejected') {
                        $('#ld-rejection-input-wrap').removeClass('d-none');
                        $('#ld-rejection-reason-input').val(rejection || '');
                    } else {
                        $('#ld-rejection-input-wrap').addClass('d-none');
                        $('#ld-rejection-reason-input').val('');
                    }
                });

                // Leave rules Dynamic Modal
                const langLeaveRules = {
                    yearlyQuota: "{{ __('hrms.employees.mdl_yearly_quota') ?? 'Yearly Quota' }}",
                    standardRules: "{{ __('hrms.employees.mdl_std_rules') ?? 'Standard Rules' }}",
                    noCustomRules: "{{ __('hrms.employees.mdl_no_custom_rules') ?? 'No custom rules defined.' }}",
                    
                    applicationRules: "{{ __('hrms.leave_rules.application_rules') }}",
                    approvalRules: "{{ __('hrms.leave_rules.approval_rules') }}",
                    accrualRules: "{{ __('hrms.leave_rules.accrual_rules') }}",
                    yearendPolicy: "{{ __('hrms.leave_rules.yearend_policy') }}",
                    encashmentRules: "{{ __('hrms.leave_rules.encashment_rules') }}",
                    probationRules: "{{ __('hrms.leave_rules.probation_rules') }}",
                    noticeRules: "{{ __('hrms.leave_rules.notice_rules') }}",

                    applyAdvance: "{{ __('hrms.leave_rules.apply_advance', ['days' => ':days']) }}",
                    applyNoAdvance: "{{ __('hrms.leave_rules.apply_no_advance') }}",
                    durationLimit: "{{ __('hrms.leave_rules.duration_limit', ['min' => ':min', 'max' => ':max']) }}",
                    requireAttachment: "{{ __('hrms.leave_rules.require_attachment', ['days' => ':days']) }}",
                    noAttachment: "{{ __('hrms.leave_rules.no_attachment') }}",

                    autoApproved: "{{ __('hrms.leave_rules.auto_approved') }}",
                    twoApprovals: "{{ __('hrms.leave_rules.two_approvals', ['first' => ':first', 'second' => ':second']) }}",
                    oneApproval: "{{ __('hrms.leave_rules.one_approval', ['first' => ':first']) }}",

                    unlimitedQuota: "{{ __('hrms.leave_rules.unlimited_quota') }}",
                    quotaAllowance: "{{ __('hrms.leave_rules.quota_allowance', ['value' => ':value', 'unit' => ':unit']) }}",
                    earnAttendance: "{{ __('hrms.leave_rules.earn_attendance', ['earn' => ':earn', 'period' => ':period']) }}",
                    creditPeriodic: "{{ __('hrms.leave_rules.credit_periodic', ['freq' => ':freq', 'prorate' => ':prorate']) }}",
                    creditImmediate: "{{ __('hrms.leave_rules.credit_immediate') }}",
                    accumLimit: "{{ __('hrms.leave_rules.accum_limit', ['max' => ':max']) }}",
                    noAccumLimit: "{{ __('hrms.leave_rules.no_accum_limit') }}",

                    carryForward: "{{ __('hrms.leave_rules.carry_forward') }}",
                    carryForwardLimit: "{{ __('hrms.leave_rules.carry_forward_limit', ['max' => ':max']) }}",
                    encashYearend: "{{ __('hrms.leave_rules.encash_yearend') }}",
                    encashLimit: "{{ __('hrms.leave_rules.encash_limit', ['max' => ':max']) }}",
                    lapseYearend: "{{ __('hrms.leave_rules.lapse_yearend') }}",

                    encashEnabled: "{{ __('hrms.leave_rules.encash_enabled', ['freq' => ':freq']) }}",
                    encashMaxPerRequest: "{{ __('hrms.leave_rules.encash_max_per_request', ['max' => ':max']) }}",
                    encashMinBalance: "{{ __('hrms.leave_rules.encash_min_balance', ['min' => ':min']) }}",
                    encashDisabled: "{{ __('hrms.leave_rules.encash_disabled') }}",

                    probationAllow: "{{ __('hrms.leave_rules.probation_allow') }}",
                    probationAfterMonths: "{{ __('hrms.leave_rules.probation_after_months', ['months' => ':months']) }}",
                    probationDeny: "{{ __('hrms.leave_rules.probation_deny') }}",

                    noticeAllow: "{{ __('hrms.leave_rules.notice_allow') }}",
                    noticePermission: "{{ __('hrms.leave_rules.notice_permission') }}",
                    noticeDeny: "{{ __('hrms.leave_rules.notice_deny') }}",
                    prorated: "{{ __('hrms.leave_rules.prorated') }}",
                    
                    reporting_manager: "{{ __('hrms.leave.reporting_manager') }}",
                    department_head: "{{ __('hrms.leave.department_head') }}",
                    hr_manager: "{{ __('hrms.leave.hr_manager') }}",
                    ceo: "{{ __('hrms.leave.ceo') }}",
                    
                    days: "{{ __('hrms.leave.days') }}",
                    hours: "{{ __('hrms.leave.hours') }}",
                    
                    frequency_anytime: "{{ __('hrms.leave.frequency_anytime') }}",
                    frequency_monthly: "{{ __('hrms.leave.frequency_monthly') }}",
                    frequency_quarterly: "{{ __('hrms.leave.frequency_quarterly') }}",
                    frequency_half_yearly: "{{ __('hrms.leave.frequency_half_yearly') }}",
                    frequency_yearly: "{{ __('hrms.leave.frequency_yearly') }}"
                };

                $(document).on('click', '.view-emp-leave-rules-btn', function () {
                    var name = $(this).attr('data-name') || '';
                    var code = $(this).attr('data-code') || '';
                    var type = $(this).attr('data-type') || '';
                    var quota = $(this).attr('data-quota') || '0';
                    var rulesStr = $(this).attr('data-rules');
                    var rules = {};
                    try {
                        rules = rulesStr ? JSON.parse(rulesStr) : {};
                    } catch(e) {
                        rules = {};
                    }

                    $('#empDynamicLeaveTypeName').text(name);
                    var metaParts = [];
                    if (code) metaParts.push(code);
                    if (type) metaParts.push(type);
                    if (quota) metaParts.push(quota + ' ' + (langLeaveRules.yearlyQuota || 'days yearly quota'));
                    $('#empDynamicLeaveTypeMeta').text(metaParts.join(' · '));

                    var sections = [];
                    var humanize = function(val) {
                        return (val || '').toString().replace(/_/g, ' ').toLowerCase();
                    };

                    var translateRole = function(role) {
                        if (!role) return '';
                        var roleLower = role.toLowerCase();
                        return langLeaveRules[roleLower] || humanize(role);
                    };

                    var translateFrequency = function(freq) {
                        if (!freq) return '';
                        var freqKey = 'frequency_' + freq.toLowerCase();
                        return langLeaveRules[freqKey] || humanize(freq);
                    };

                    var translateUnit = function(unit) {
                        if (!unit) return '';
                        var unitLower = unit.toLowerCase();
                        return langLeaveRules[unitLower] || humanize(unit);
                    };

                    // 1. Application Rules
                    if (rules.application) {
                        var app = rules.application;
                        var points = [];
                        if (app.apply_in_advance) {
                            points.push(langLeaveRules.applyAdvance.replace(':days', app.advance_days || 0));
                        } else {
                            points.push(langLeaveRules.applyNoAdvance);
                        }
                        points.push(langLeaveRules.durationLimit.replace(':min', app.min_duration || 1).replace(':max', app.max_duration || 10));
                        if (app.require_attachment) {
                            points.push(langLeaveRules.requireAttachment.replace(':days', app.attachment_days || 0));
                        } else {
                            points.push(langLeaveRules.noAttachment);
                        }
                        sections.push({ title: langLeaveRules.applicationRules, icon: 'feather-file-text', points: points });
                    }

                    // 2. Approval Rules
                    if (rules.approval) {
                        var appr = rules.approval;
                        var points = [];
                        if (appr.workflow_level === 'auto') {
                            points.push(langLeaveRules.autoApproved);
                        } else if (appr.workflow_level === '2_level') {
                            points.push(langLeaveRules.twoApprovals
                                .replace(':first', translateRole(appr.first_approver || 'reporting_manager'))
                                .replace(':second', translateRole(appr.second_approver || 'hr_manager'))
                            );
                        } else {
                            points.push(langLeaveRules.oneApproval.replace(':first', translateRole(appr.first_approver || 'reporting_manager')));
                        }
                        sections.push({ title: langLeaveRules.approvalRules, icon: 'feather-check-square', points: points });
                    }

                    // 3. Accrual Rules
                    if (rules.accrual) {
                        var acc = rules.accrual;
                        var points = [];
                        var unit = translateUnit(acc.calculate_in || 'days');
                        if (acc.quota_type === 'unlimited') {
                            points.push(langLeaveRules.unlimitedQuota);
                        } else {
                            var quotaVal = (acc.quota_value !== undefined ? acc.quota_value : quota);
                            var formattedQuota = parseFloat(quotaVal).toString();
                            points.push(langLeaveRules.quotaAllowance.replace(':value', formattedQuota).replace(':unit', unit));
                        }
                        if (acc.rate === 'attendance') {
                            points.push(langLeaveRules.earnAttendance.replace(':earn', acc.attendance_earn || 1).replace(':period', acc.attendance_period || 20));
                        } else if (acc.rate === 'periodic') {
                            var freq = translateFrequency(acc.frequency || 'monthly');
                            var prorate = acc.prorate ? ' (' + langLeaveRules.prorated + ')' : '';
                            points.push(langLeaveRules.creditPeriodic.replace(':freq', freq.toLowerCase()).replace(':prorate', prorate));
                        } else {
                            points.push(langLeaveRules.creditImmediate);
                        }
                        if (acc.limit_carry) {
                            points.push(langLeaveRules.accumLimit.replace(':max', acc.max_accum || 0));
                        } else {
                            points.push(langLeaveRules.noAccumLimit);
                        }
                        sections.push({ title: langLeaveRules.accrualRules, icon: 'feather-calendar', points: points });
                    }

                    // 4. Year-End Policy
                    if (rules.yearend) {
                        var ye = rules.yearend;
                        var points = [];
                        if (ye.action === 'carry_forward') {
                            points.push(langLeaveRules.carryForward);
                            points.push(langLeaveRules.carryForwardLimit.replace(':max', ye.max_carry || 0));
                        } else if (ye.action === 'encash') {
                            points.push(langLeaveRules.encashYearend);
                            points.push(langLeaveRules.encashLimit.replace(':max', ye.max_encash || 0));
                        } else {
                            points.push(langLeaveRules.lapseYearend);
                        }
                        sections.push({ title: langLeaveRules.yearendPolicy, icon: 'feather-rotate-ccw', points: points });
                    }

                    // 5. Encashment Rules
                    if (rules.encashment) {
                        var enc = rules.encashment;
                        var points = [];
                        if (enc.enabled) {
                            var freq = translateFrequency(enc.frequency || 'anytime');
                            points.push(langLeaveRules.encashEnabled.replace(':freq', freq));
                            points.push(langLeaveRules.encashMaxPerRequest.replace(':max', enc.max_days_per_request || 5));
                            points.push(langLeaveRules.encashMinBalance.replace(':min', enc.min_balance_to_keep || 10));
                        } else {
                            points.push(langLeaveRules.encashDisabled);
                        }
                        sections.push({ title: langLeaveRules.encashmentRules, icon: 'feather-dollar-sign', points: points });
                    }

                    // 6. Probation Period Rules
                    if (rules.probation) {
                        var prob = rules.probation;
                        var points = [];
                        if (prob.rule === 'allow') {
                            points.push(langLeaveRules.probationAllow);
                        } else if (prob.rule === 'allow_after_months') {
                            points.push(langLeaveRules.probationAfterMonths.replace(':months', prob.months || 3));
                        } else {
                            points.push(langLeaveRules.probationDeny);
                        }
                        sections.push({ title: langLeaveRules.probationRules, icon: 'feather-user-check', points: points });
                    }

                    // 7. Notice Period Rules
                    if (rules.notice) {
                        var not = rules.notice;
                        var points = [];
                        if (not.rule === 'allow') {
                            points.push(langLeaveRules.noticeAllow);
                        } else if (not.rule === 'allow_with_permission') {
                            points.push(langLeaveRules.noticePermission);
                        } else {
                            points.push(langLeaveRules.noticeDeny);
                        }
                        sections.push({ title: langLeaveRules.noticeRules, icon: 'feather-alert-triangle', points: points });
                    }

                    var html = '';
                    if (sections.length === 0) {
                        html = '<div class="text-center py-5 text-muted">' +
                               '<i class="feather-check-circle d-block fs-32 mb-3 text-success"></i>' +
                               '<div class="fw-bold text-dark mb-1">' + langLeaveRules.standardRules + '</div>' +
                               '<div>' + langLeaveRules.noCustomRules + '</div>' +
                               '</div>';
                    } else {
                        html = '<div class="leave-rules-masonry-grid">';
                        sections.forEach(function(sec) {
                            html += '<div class="leave-rule-detail-card">';
                            html += '<div class="leave-rule-detail-section">';
                            html += '<div class="leave-rule-detail-title"><i class="' + sec.icon + ' text-primary me-2"></i> <span>' + sec.title + '</span></div>';
                            html += '<ul class="leave-rule-points">';
                            sec.points.forEach(function(pt) {
                                html += '<li class="leave-rule-point">' + pt + '</li>';
                            });
                            html += '</ul></div></div>';
                        });
                        html += '</div>';
                    }

                    $('#empDynamicLeaveRulesBody').html(html);
                    var $modal = $('#empLeaveRulesDynamicModal');
                    if ($modal.length) {
                        $modal.appendTo('body').modal('show');
                    }
                });

                // Apply Leave Form Duration Calculation
                $('#emp_start_date, #emp_end_date, #emp_start_date_type, #emp_end_date_type').on('change', function() {
                    empCalculateExpectedLeaveDuration();
                });

                function empCalculateExpectedLeaveDuration() {
                    var startDateStr = $('#emp_start_date').val();
                    var endDateStr = $('#emp_end_date').val();
                    var startType = $('#emp_start_date_type').val() || 'full_day';
                    var endType = $('#emp_end_date_type').val() || 'full_day';

                    if (!startDateStr || !endDateStr) return;

                    var start = new Date(startDateStr);
                    var end = new Date(endDateStr);

                    if (end < start) {
                        $('#emp_calculated_duration_display').removeClass('alert-info').addClass('alert-danger').text('Invalid Date Range');
                        return;
                    }

                    $('#emp_calculated_duration_display').removeClass('alert-danger').addClass('alert-info');

                    var duration = 0;
                    var current = new Date(start);

                    if (start.getTime() === end.getTime()) {
                        duration = (startType === 'full_day') ? 1.0 : 0.5;
                    } else {
                        while (current <= end) {
                            var isStart = current.getTime() === start.getTime();
                            var isEnd = current.getTime() === end.getTime();

                            if (isStart) {
                                duration += (startType === 'full_day') ? 1.0 : 0.5;
                            } else if (isEnd) {
                                duration += (endType === 'full_day') ? 1.0 : 0.5;
                            } else {
                                duration += 1.0;
                            }
                            current.setDate(current.getDate() + 1);
                        }
                    }

                    var durationText = 'Estimated Duration: ' + duration.toFixed(1) + ' day(s)';
                    $('#emp_calculated_duration_display').text(durationText);
                }

                // Initial trigger on load
                refreshEmpLeaveAppRows();
                refreshEmpLeaveEncRows();
            });

            window.openLeaveCancellationModal = function(id, actionUrl) {
                $('#leaveCancellationForm').attr('action', actionUrl);
                $('#leave_cancellation_reason').val('');
                var myModal = new bootstrap.Modal(document.getElementById('leaveCancellationModal'));
                myModal.show();
            };

            window.toggleLeaveCancelReasonText = function(btn) {
                var textEl = btn.previousElementSibling;
                if (textEl.style.display === 'block') {
                    textEl.style.display = '-webkit-box';
                    textEl.style.webkitLineClamp = '2';
                    btn.textContent = 'See more';
                } else {
                    textEl.style.display = 'block';
                    textEl.style.webkitLineClamp = 'none';
                    btn.textContent = 'See less';
                }
            };
        </script>
    @endpush
