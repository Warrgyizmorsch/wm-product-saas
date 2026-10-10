@php
    $doj = $employee->date_of_joining ? \Carbon\Carbon::parse($employee->date_of_joining) : null;
    $probationEnd = $employee->probation_end_date ? \Carbon\Carbon::parse($employee->probation_end_date) : null;
    $today = \Carbon\Carbon::today();

    $totalDays = ($doj && $probationEnd) ? max(1, $doj->diffInDays($probationEnd)) : 90;
    $daysPassed = $doj ? max(0, min($totalDays, $doj->diffInDays($today))) : 0;
    $probationProgress = (int) round(($daysPassed / $totalDays) * 100);

    $isOverdue = ($employee->employee_stage === 'Probation' && $probationEnd && $today->greaterThan($probationEnd));
    $daysRemaining = ($probationEnd && !$isOverdue) ? $today->diffInDays($probationEnd) : 0;

    $stageVariant = match($employee->employee_stage) {
        'Probation'     => 'warning',
        'Confirmed'     => 'success',
        'Notice Period' => 'danger',
        'Exited'        => 'secondary',
        default         => 'light',
    };
@endphp

<div class="tab-pane fade {{ $activeTabName === 'probation' ? 'show active' : '' }}" id="probation-pane" role="tabpanel" aria-labelledby="probation-tab">
    <div class="card-custom mb-4">
        <div class="card-custom-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="card-custom-title mb-0">
                    <i class="feather-award text-primary me-2"></i> {{ __('hrms.employees.probation_confirmation_status') }}
                </h5>
                <span class="text-muted fs-12">{{ __('hrms.employees.probation_confirmation_desc') }}</span>
            </div>
            <div>
                @if($employee->employee_stage === 'Probation')
                    @php
                        $authUser = auth()->user();
                        $canEvaluate = $authUser && app(\App\Services\Access\AccessService::class)->allows($authUser, 'hrms.employees.update', ['tenant_id' => $authUser->tenant_id]);
                    @endphp
                    @if($canEvaluate)
                        <div class="d-flex align-items-center gap-2">
                            <x-ui.button variant="primary" size="sm" icon="feather-check-square" data-bs-toggle="modal" data-bs-target="#profileEvaluateModal" class="fw-semibold">
                                {{ __('hrms.employees.btn_review_evaluate') }}
                            </x-ui.button>
                            <form method="POST" action="{{ route('hrms.probation.quick-confirm', $employee->id) }}" class="d-inline" onsubmit="return confirm('{{ __('hrms.employees.confirm_emp_confirm_prompt', ['name' => addslashes($employee->full_name)]) }}');">
                                @csrf
                                <x-ui.button variant="outline-success" size="sm" icon="feather-award" type="submit" class="fw-semibold">
                                    {{ __('hrms.employees.btn_quick_confirm') }}
                                </x-ui.button>
                            </form>
                        </div>
                    @endif
                @elseif($employee->employee_stage === 'Confirmed')
                    <x-ui.badge soft variant="success" class="fs-12 px-3 py-1.5 fw-semibold">
                        <i class="feather-check-circle me-1"></i> {{ __('hrms.employees.formally_confirmed_emp') }}
                    </x-ui.badge>
                @else
                    <x-ui.badge soft :variant="$stageVariant" class="fs-12 px-3 py-1.5 fw-semibold">
                        {{ $employee->employee_stage }}
                    </x-ui.badge>
                @endif
            </div>
        </div>

        <div class="card-body p-4">
            <!-- 1. Key Metrics Cards Row -->
            <div class="row g-3 mb-4">
                <!-- Card 1: Current Stage -->
                <div class="col-md-3 col-sm-6">
                    <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                        <span class="text-muted fs-11 fw-bold text-uppercase tracking-wider d-block mb-1">
                            <i class="feather-user-check text-primary me-1"></i> {{ __('hrms.employees.employment_stage') }}
                        </span>
                        <div>
                            <x-ui.badge soft :variant="$stageVariant" class="fs-12 fw-semibold px-2.5 py-1">
                                {{ $employee->employee_stage ?: __('hrms.common.not_set') }}
                            </x-ui.badge>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Date of Joining -->
                <div class="col-md-3 col-sm-6">
                    <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                        <span class="text-muted fs-11 fw-bold text-uppercase tracking-wider d-block mb-1">
                            <i class="feather-calendar text-info me-1"></i> {{ __('hrms.employees.lbl_doj') }}
                        </span>
                        <div>
                            <strong class="fs-14 text-dark">{{ $doj ? $doj->format('d M, Y') : __('hrms.common.na') }}</strong>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Probation End Date -->
                <div class="col-md-3 col-sm-6">
                    <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                        <span class="text-muted fs-11 fw-bold text-uppercase tracking-wider d-block mb-1">
                            <i class="feather-calendar text-warning me-1"></i> {{ __('hrms.employees.probation_end_date') }}
                        </span>
                        <div class="d-flex align-items-center justify-content-between gap-1 flex-wrap">
                            <strong class="fs-14 text-dark">{{ $probationEnd ? $probationEnd->format('d M, Y') : __('hrms.common.na') }}</strong>
                            @if($employee->employee_stage === 'Probation' && $probationEnd)
                                @if($isOverdue)
                                    <x-ui.badge soft variant="danger" class="fs-10">{{ __('hrms.employees.overdue') }}</x-ui.badge>
                                @else
                                    <x-ui.badge soft variant="warning" class="fs-10">{{ __('hrms.employees.days_left', ['count' => $daysRemaining]) }}</x-ui.badge>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Card 4: Confirmation Date -->
                <div class="col-md-3 col-sm-6">
                    <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                        <span class="text-muted fs-11 fw-bold text-uppercase tracking-wider d-block mb-1">
                            <i class="feather-award text-success me-1"></i> {{ __('hrms.employees.lbl_confirmation_date') }}
                        </span>
                        <div>
                            @if($employee->confirmation_date)
                                <strong class="fs-14 text-success">{{ \Carbon\Carbon::parse($employee->confirmation_date)->format('d M, Y') }}</strong>
                            @else
                                <span class="text-muted fs-13">{{ __('hrms.employees.lbl_pending_verification') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Probation Period Milestone Progress (Visible during Probation) -->
            @if($employee->employee_stage === 'Probation' && $doj && $probationEnd)
                <div class="p-4 bg-white rounded-3 border shadow-sm mb-4" style="border-color: #e2e8f0 !important;">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                        <span class="fs-13 fw-bold text-dark d-flex align-items-center gap-2">
                            <span class="avatar-text avatar-xs bg-soft-primary text-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                <i class="feather-trending-up fs-13"></i>
                            </span>
                            {{ __('hrms.employees.probation_timeline_progress') }}
                        </span>
                        <span class="badge bg-soft-primary text-primary px-3 py-1.5 rounded-pill fs-12 fw-bold">
                            {{ __('hrms.employees.probation_progress_text', ['progress' => $probationProgress, 'passed' => $daysPassed, 'total' => $totalDays]) }}
                        </span>
                    </div>
                    <div class="progress my-3" style="height: 10px; border-radius: 999px; background-color: #e2e8f0; overflow: hidden;">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: {{ min(100, $probationProgress) }}%; border-radius: 999px; transition: width 0.6s ease;" aria-valuenow="{{ $probationProgress }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center text-muted fs-12 pt-2 border-top gap-2" style="border-color: #f1f5f9 !important;">
                        <div class="d-flex align-items-center gap-1.5">
                            <i class="feather-calendar text-muted fs-13"></i>
                            <span>{{ __('hrms.employees.joined_on', ['date' => $doj->format('d M, Y')]) }}</span>
                        </div>
                        <div class="d-flex align-items-center gap-1.5">
                            <i class="feather-flag text-muted fs-13"></i>
                            <span>{{ __('hrms.employees.evaluation_due_on', ['date' => $probationEnd->format('d M, Y')]) }}</span>
                        </div>
                    </div>
                </div>
            @endif

            <!-- 3. Review History Table -->
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="feather-list text-primary me-1.5"></i> {{ __('hrms.employees.eval_review_history') }}
                </h6>
                <x-ui.badge soft variant="secondary" class="fs-11">
                    {{ __('hrms.employees.records_logged', ['count' => $employee->probationEvaluations->count()]) }}
                </x-ui.badge>
            </div>

            <div class="border rounded-3 overflow-hidden">
                <table class="table table-hover align-middle mb-0 text-dark" style="font-size: 13px; width: 100%;">
                    <thead class="table-light fs-11 text-uppercase tracking-wider">
                        <tr>
                            <th class="ps-3 py-3" style="width: 12%;">{{ __('hrms.employees.tbl_review_date') }}</th>
                            <th class="py-3" style="width: 16%;">{{ __('hrms.employees.tbl_reviewer') }}</th>
                            <th class="py-3 text-center" style="width: 12%;">{{ __('hrms.employees.tbl_performance') }}</th>
                            <th class="py-3 text-center" style="width: 12%;">{{ __('hrms.employees.tbl_attendance') }}</th>
                            <th class="py-3 text-center" style="width: 12%;">{{ __('hrms.employees.tbl_culture_fit') }}</th>
                            <th class="py-3 text-center" style="width: 14%;">{{ __('hrms.employees.tbl_recommendation') }}</th>
                            <th class="pe-3 py-3" style="width: 22%;">{{ __('hrms.employees.tbl_remarks_feedback') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employee->probationEvaluations as $eval)
                            @php
                                $recVariant = match($eval->recommendation) {
                                    'confirm'   => 'success',
                                    'extend'    => 'warning',
                                    'terminate' => 'danger',
                                    default     => 'secondary',
                                };
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <span class="fw-semibold text-dark">{{ $eval->evaluation_date ? \Carbon\Carbon::parse($eval->evaluation_date)->format('d M, Y') : __('hrms.common.na') }}</span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-text avatar-xs bg-soft-primary text-primary rounded-circle fw-bold" style="width: 24px; height: 24px; font-size: 10px;">
                                            {{ strtoupper(substr($eval->reviewer->name ?? 'HR', 0, 1)) }}
                                        </div>
                                        <span>{{ $eval->reviewer->name ?? __('hrms.employees.hr_admin') }}</span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <x-ui.badge soft variant="warning" class="fs-11">
                                        ★ {{ $eval->performance_rating }}/5
                                    </x-ui.badge>
                                </td>
                                <td class="text-center">
                                    <x-ui.badge soft variant="info" class="fs-11">
                                        ★ {{ $eval->attendance_rating }}/5
                                    </x-ui.badge>
                                </td>
                                <td class="text-center">
                                    <x-ui.badge soft variant="primary" class="fs-11">
                                        ★ {{ $eval->culture_rating }}/5
                                    </x-ui.badge>
                                </td>
                                <td class="text-center">
                                    <x-ui.badge soft :variant="$recVariant" class="text-uppercase fs-11">
                                        {{ $eval->recommendation }}
                                    </x-ui.badge>
                                </td>
                                <td class="pe-3 text-muted fs-12" style="white-space: normal !important; word-wrap: break-word !important; word-break: break-word !important;">
                                    {{ $eval->remarks ?: __('hrms.employees.no_remarks_logged') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center">
                                        <i class="feather-award fs-18"></i>
                                    </div>
                                    <h6 class="fw-bold mb-1 text-dark fs-13">{{ __('hrms.employees.no_eval_records_yet') }}</h6>
                                    <p class="fs-12 mb-0 text-muted">{{ __('hrms.employees.no_eval_records_desc') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal for evaluate inside profile -->
@if($employee->employee_stage === 'Probation')
<div class="modal fade" id="profileEvaluateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-light border-bottom p-4">
                <div>
                    <h5 class="modal-title fw-bold text-dark mb-1">
                        <i class="feather-award text-primary me-2"></i>{{ __('hrms.employees.probation_evaluation') }}
                    </h5>
                    <p class="text-muted fs-13 mb-0">{{ __('hrms.employees.probation_eval_subtitle', ['name' => $employee->full_name, 'id' => $employee->employee_id]) }}</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('hrms.probation.evaluate', $employee->id) }}">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-bold fs-12 text-uppercase text-muted mb-1">1. {{ __('hrms.employees.performance_execution') }}</label>
                            <x-ui.odoo-form-ui type="select" name="performance_rating" :required="true">
                                <option value="5">{{ __('hrms.employees.perf_5') }}</option>
                                <option value="4" selected>{{ __('hrms.employees.perf_4') }}</option>
                                <option value="3">{{ __('hrms.employees.perf_3') }}</option>
                                <option value="2">{{ __('hrms.employees.perf_2') }}</option>
                                <option value="1">{{ __('hrms.employees.perf_1') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold fs-12 text-uppercase text-muted mb-1">2. {{ __('hrms.employees.attendance_punctuality') }}</label>
                            <x-ui.odoo-form-ui type="select" name="attendance_rating" :required="true">
                                <option value="5">{{ __('hrms.employees.att_5') }}</option>
                                <option value="4" selected>{{ __('hrms.employees.att_4') }}</option>
                                <option value="3">{{ __('hrms.employees.att_3') }}</option>
                                <option value="2">{{ __('hrms.employees.att_2') }}</option>
                                <option value="1">{{ __('hrms.employees.att_1') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold fs-12 text-uppercase text-muted mb-1">3. {{ __('hrms.employees.culture_fit_teamwork') }}</label>
                            <x-ui.odoo-form-ui type="select" name="culture_rating" :required="true">
                                <option value="5">{{ __('hrms.employees.cult_5') }}</option>
                                <option value="4" selected>{{ __('hrms.employees.cult_4') }}</option>
                                <option value="3">{{ __('hrms.employees.cult_3') }}</option>
                                <option value="2">{{ __('hrms.employees.cult_2') }}</option>
                                <option value="1">{{ __('hrms.employees.cult_1') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <div class="p-3.5 bg-light rounded-3 border mb-3">
                        <label class="form-label fw-bold text-dark fs-13 mb-2">{{ __('hrms.employees.final_recommendation') }}</label>
                        <div class="d-flex gap-4 flex-wrap mt-1">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="recommendation" id="prof_rec_confirm" value="confirm" checked onchange="handleProfileRecChange('confirm')">
                                <label class="form-check-label fw-semibold text-success fs-13 cursor-pointer" for="prof_rec_confirm">
                                    <i class="feather-check-circle me-1"></i> {{ __('hrms.employees.formally_confirm') }}
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="recommendation" id="prof_rec_extend" value="extend" onchange="handleProfileRecChange('extend')">
                                <label class="form-check-label fw-semibold text-warning fs-13 cursor-pointer" for="prof_rec_extend">
                                    <i class="feather-refresh-cw me-1"></i> {{ __('hrms.employees.extend_probation_period') }}
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="recommendation" id="prof_rec_terminate" value="terminate" onchange="handleProfileRecChange('terminate')">
                                <label class="form-check-label fw-semibold text-danger fs-13 cursor-pointer" for="prof_rec_terminate">
                                    <i class="feather-x-circle me-1"></i> {{ __('hrms.employees.recommend_termination') }}
                                </label>
                            </div>
                        </div>

                        <!-- Profile Extension Box -->
                        <div id="prof_extension_box" class="mt-3 p-3 bg-white rounded-3 border border-warning border-opacity-25 d-none">
                            <label class="form-label fw-bold fs-12 text-dark mb-1">{{ __('hrms.employees.extension_duration') }}</label>
                            <x-ui.odoo-form-ui type="select" name="extension_days">
                                <option value="30">{{ __('hrms.employees.days_1_month') }}</option>
                                <option value="60">{{ __('hrms.employees.days_2_months') }}</option>
                                <option value="90">{{ __('hrms.employees.days_3_months') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <!-- Profile Termination Box -->
                        <div id="prof_termination_box" class="mt-3 p-3 bg-white rounded-3 border border-danger border-opacity-25 d-none">
                            <div class="d-flex align-items-center gap-2 mb-2 text-danger fw-bold fs-13">
                                <i class="feather-alert-triangle"></i> {{ __('hrms.employees.involuntary_separation_details') }}
                            </div>
                            <p class="text-muted fs-12 mb-3">
                                {{ __('hrms.employees.involuntary_separation_desc') }}
                            </p>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold fs-12 text-dark">{{ __('hrms.employees.termination_mode') }}</label>
                                    <div class="d-flex gap-3 mt-1">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="termination_mode" id="prof_term_mode_notice" value="notice" checked onchange="toggleProfTerminationNotice(true)">
                                            <label class="form-check-label fs-13 cursor-pointer" for="prof_term_mode_notice">
                                                {{ __('hrms.employees.serve_notice') }}
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="termination_mode" id="prof_term_mode_imm" value="immediate" onchange="toggleProfTerminationNotice(false)">
                                            <label class="form-check-label fs-13 cursor-pointer" for="prof_term_mode_imm">
                                                {{ __('hrms.employees.immediate_today') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6" id="prof_term_notice_days_box">
                                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.notice_duration') }}" name="termination_notice_days">
                                        <option value="7">{{ __('hrms.employees.notice_7_days') }}</option>
                                        <option value="15" selected>{{ __('hrms.employees.notice_15_days') }}</option>
                                        <option value="30">{{ __('hrms.employees.notice_30_days') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="col-12">
                                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.employees.primary_reason_category') }}" name="termination_reason_category">
                                        <option value="Performance / Skill Gap">{{ __('hrms.employees.reason_perf_skill') }}</option>
                                        <option value="Cultural / Team Misalignment">{{ __('hrms.employees.reason_cultural_misalignment') }}</option>
                                        <option value="Attendance & Discipline">{{ __('hrms.employees.reason_attendance_discipline') }}</option>
                                        <option value="Role Fit / Restructuring">{{ __('hrms.employees.reason_role_restructuring') }}</option>
                                        <option value="Probation Unsuccessful" selected>{{ __('hrms.employees.reason_general_unsuccessful') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold fs-12 text-uppercase text-muted mb-1">{{ __('hrms.employees.eval_comments_notes') }}</label>
                        <textarea name="remarks" class="form-control" rows="3" placeholder="{{ __('hrms.employees.eval_comments_placeholder') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top p-3 d-flex justify-content-between">
                    <x-ui.button variant="light" data-bs-dismiss="modal" class="border px-4 fw-semibold">{{ __('hrms.common.cancel') }}</x-ui.button>
                    <x-ui.button variant="primary" type="submit" class="px-4 fw-bold">
                        <i class="feather-check-circle me-1"></i> {{ __('hrms.employees.submit_evaluation') }}
                    </x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function handleProfileRecChange(recValue) {
        const extBox = document.getElementById('prof_extension_box');
        const termBox = document.getElementById('prof_termination_box');
        if (extBox) extBox.classList.add('d-none');
        if (termBox) termBox.classList.add('d-none');

        if (recValue === 'extend' && extBox) {
            extBox.classList.remove('d-none');
        } else if (recValue === 'terminate' && termBox) {
            termBox.classList.remove('d-none');
        }
    }

    function toggleProfTerminationNotice(showNotice) {
        const box = document.getElementById('prof_term_notice_days_box');
        if (box) {
            if (showNotice) {
                box.classList.remove('d-none');
            } else {
                box.classList.add('d-none');
            }
        }
    }
</script>
@endif
