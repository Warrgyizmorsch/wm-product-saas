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
                    <i class="feather-award text-primary me-2"></i> Probation & Confirmation Status
                </h5>
                <span class="text-muted fs-12">Track performance evaluations, review milestones, and confirmation records.</span>
            </div>
            <div>
                @if($employee->employee_stage === 'Probation')
                    <div class="d-flex align-items-center gap-2">
                        <x-ui.button variant="primary" size="sm" icon="feather-check-square" data-bs-toggle="modal" data-bs-target="#profileEvaluateModal" class="fw-semibold">
                            Review & Evaluate
                        </x-ui.button>
                        <form method="POST" action="{{ route('hrms.probation.quick-confirm', $employee->id) }}" class="d-inline" onsubmit="return confirm('Confirm employee {{ $employee->full_name }}?');">
                            @csrf
                            <x-ui.button variant="outline-success" size="sm" icon="feather-award" type="submit" class="fw-semibold">
                                Quick Confirm
                            </x-ui.button>
                        </form>
                    </div>
                @elseif($employee->employee_stage === 'Confirmed')
                    <x-ui.badge soft variant="success" class="fs-12 px-3 py-1.5 fw-semibold">
                        <i class="feather-check-circle me-1"></i> Formally Confirmed Employee
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
                            <i class="feather-user-check text-primary me-1"></i> Employment Stage
                        </span>
                        <div>
                            <x-ui.badge soft :variant="$stageVariant" class="fs-12 fw-semibold px-2.5 py-1">
                                {{ $employee->employee_stage ?: 'Not Set' }}
                            </x-ui.badge>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Date of Joining -->
                <div class="col-md-3 col-sm-6">
                    <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                        <span class="text-muted fs-11 fw-bold text-uppercase tracking-wider d-block mb-1">
                            <i class="feather-calendar text-info me-1"></i> Date of Joining
                        </span>
                        <div>
                            <strong class="fs-14 text-dark">{{ $doj ? $doj->format('d M, Y') : 'N/A' }}</strong>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Probation End Date -->
                <div class="col-md-3 col-sm-6">
                    <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                        <span class="text-muted fs-11 fw-bold text-uppercase tracking-wider d-block mb-1">
                            <i class="feather-calendar text-warning me-1"></i> Probation End Date
                        </span>
                        <div class="d-flex align-items-center justify-content-between gap-1 flex-wrap">
                            <strong class="fs-14 text-dark">{{ $probationEnd ? $probationEnd->format('d M, Y') : 'N/A' }}</strong>
                            @if($employee->employee_stage === 'Probation' && $probationEnd)
                                @if($isOverdue)
                                    <x-ui.badge soft variant="danger" class="fs-10">Overdue</x-ui.badge>
                                @else
                                    <x-ui.badge soft variant="warning" class="fs-10">{{ $daysRemaining }}d left</x-ui.badge>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Card 4: Confirmation Date -->
                <div class="col-md-3 col-sm-6">
                    <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                        <span class="text-muted fs-11 fw-bold text-uppercase tracking-wider d-block mb-1">
                            <i class="feather-award text-success me-1"></i> Confirmation Date
                        </span>
                        <div>
                            @if($employee->confirmation_date)
                                <strong class="fs-14 text-success">{{ \Carbon\Carbon::parse($employee->confirmation_date)->format('d M, Y') }}</strong>
                            @else
                                <span class="text-muted fs-13">Pending Evaluation</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Probation Period Milestone Progress (Visible during Probation) -->
            @if($employee->employee_stage === 'Probation' && $doj && $probationEnd)
                <div class="p-3.5 bg-light rounded-3 border mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fs-12 fw-bold text-dark">
                            <i class="feather-trending-up text-primary me-1"></i> Probation Timeline Progress
                        </span>
                        <span class="fs-12 fw-bold text-primary">{{ $probationProgress }}% Completed ({{ $daysPassed }} of {{ $totalDays }} Days)</span>
                    </div>
                    <div class="progress" style="height: 7px;">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: {{ min(100, $probationProgress) }}%" aria-valuenow="{{ $probationProgress }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="d-flex justify-content-between text-muted fs-11 mt-2">
                        <span>Joined: <strong>{{ $doj->format('d M, Y') }}</strong></span>
                        <span>Evaluation Due: <strong>{{ $probationEnd->format('d M, Y') }}</strong></span>
                    </div>
                </div>
            @endif

            <!-- 3. Review History Table -->
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="feather-list text-primary me-1.5"></i> Evaluation & Review History
                </h6>
                <span class="badge bg-light text-secondary border fs-11">
                    {{ $employee->probationEvaluations->count() }} Records Logged
                </span>
            </div>

            <div class="table-responsive border rounded-3">
                <table class="table table-hover align-middle mb-0 text-dark" style="font-size: 13px;">
                    <thead class="table-light fs-11 text-uppercase tracking-wider">
                        <tr>
                            <th class="ps-3 py-3">Review Date</th>
                            <th class="py-3">Reviewer</th>
                            <th class="py-3">Performance</th>
                            <th class="py-3">Attendance</th>
                            <th class="py-3">Culture Fit</th>
                            <th class="py-3">Recommendation</th>
                            <th class="pe-3 py-3">Remarks & Feedback</th>
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
                                    <span class="fw-semibold text-dark">{{ $eval->evaluation_date ? \Carbon\Carbon::parse($eval->evaluation_date)->format('d M, Y') : 'N/A' }}</span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-text avatar-xs bg-soft-primary text-primary rounded-circle fw-bold" style="width: 24px; height: 24px; font-size: 10px;">
                                            {{ strtoupper(substr($eval->reviewer->name ?? 'HR', 0, 1)) }}
                                        </div>
                                        <span>{{ $eval->reviewer->name ?? 'HR Admin' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-soft-warning text-dark border border-warning border-opacity-25 px-2 py-1 fs-11">
                                        ★ {{ $eval->performance_rating }}/5
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-soft-info text-dark border border-info border-opacity-25 px-2 py-1 fs-11">
                                        ★ {{ $eval->attendance_rating }}/5
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-soft-primary text-dark border border-primary border-opacity-25 px-2 py-1 fs-11">
                                        ★ {{ $eval->culture_rating }}/5
                                    </span>
                                </td>
                                <td>
                                    <x-ui.badge soft :variant="$recVariant" class="text-uppercase fs-11">
                                        {{ $eval->recommendation }}
                                    </x-ui.badge>
                                </td>
                                <td class="pe-3 text-muted fs-12" style="max-width: 250px;">
                                    {{ $eval->remarks ?: 'No remarks logged.' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center">
                                        <i class="feather-award fs-18"></i>
                                    </div>
                                    <h6 class="fw-bold mb-1 text-dark fs-13">No evaluation records logged yet</h6>
                                    <p class="fs-12 mb-0 text-muted">Click "Review & Evaluate" above to log performance scores and recommendations for this employee.</p>
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
                        <i class="feather-award text-primary me-2"></i>Probation Evaluation Form
                    </h5>
                    <p class="text-muted fs-13 mb-0">Evaluate <strong>{{ $employee->full_name }}</strong> ({{ $employee->employee_id }})</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('hrms.probation.evaluate', $employee->id) }}">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-bold fs-12 text-uppercase text-muted mb-1">1. Performance & Execution</label>
                            <x-ui.odoo-form-ui type="select" name="performance_rating" :required="true">
                                <option value="5">★★★★★ - Outstanding (5)</option>
                                <option value="4" selected>★★★★☆ - Exceeds Expectations (4)</option>
                                <option value="3">★★★☆☆ - Meets Expectations (3)</option>
                                <option value="2">★★☆☆☆ - Needs Improvement (2)</option>
                                <option value="1">★☆☆☆☆ - Unsatisfactory (1)</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold fs-12 text-uppercase text-muted mb-1">2. Attendance & Punctuality</label>
                            <x-ui.odoo-form-ui type="select" name="attendance_rating" :required="true">
                                <option value="5">★★★★★ - Excellent (5)</option>
                                <option value="4" selected>★★★★☆ - Very Good (4)</option>
                                <option value="3">★★★☆☆ - Good / Satisfactory (3)</option>
                                <option value="2">★★☆☆☆ - Frequent Delays (2)</option>
                                <option value="1">★☆☆☆☆ - Poor Attendance (1)</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold fs-12 text-uppercase text-muted mb-1">3. Culture Fit & Teamwork</label>
                            <x-ui.odoo-form-ui type="select" name="culture_rating" :required="true">
                                <option value="5">★★★★★ - Role Model (5)</option>
                                <option value="4" selected>★★★★☆ - Highly Collaborative (4)</option>
                                <option value="3">★★★☆☆ - Good Team Player (3)</option>
                                <option value="2">★★☆☆☆ - Struggling to Adapt (2)</option>
                                <option value="1">★☆☆☆☆ - Misaligned (1)</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <div class="p-3.5 bg-light rounded-3 border mb-3">
                        <label class="form-label fw-bold text-dark fs-13 mb-2">Final Recommendation</label>
                        <div class="d-flex gap-4 flex-wrap mt-1">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="recommendation" id="prof_rec_confirm" value="confirm" checked onchange="handleProfileRecChange('confirm')">
                                <label class="form-check-label fw-semibold text-success fs-13" for="prof_rec_confirm">
                                    <i class="feather-check-circle me-1"></i> Formally Confirm Employment
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="recommendation" id="prof_rec_extend" value="extend" onchange="handleProfileRecChange('extend')">
                                <label class="form-check-label fw-semibold text-warning fs-13" for="prof_rec_extend">
                                    <i class="feather-refresh-cw me-1"></i> Extend Probation
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="recommendation" id="prof_rec_terminate" value="terminate" onchange="handleProfileRecChange('terminate')">
                                <label class="form-check-label fw-semibold text-danger fs-13" for="prof_rec_terminate">
                                    <i class="feather-x-circle me-1"></i> Recommend Termination
                                </label>
                            </div>
                        </div>

                        <!-- Profile Extension Box -->
                        <div id="prof_extension_box" class="mt-3 p-3 bg-white rounded-3 border border-warning border-opacity-25 d-none">
                            <label class="form-label fw-bold fs-12 text-dark mb-1">Extension Duration</label>
                            <select name="extension_days" class="form-select form-select-sm" style="max-width: 250px;">
                                <option value="30">30 Days (1 Month)</option>
                                <option value="60">60 Days (2 Months)</option>
                                <option value="90">90 Days (3 Months)</option>
                            </select>
                        </div>

                        <!-- Profile Termination Box -->
                        <div id="prof_termination_box" class="mt-3 p-3 bg-white rounded-3 border border-danger border-opacity-25 d-none">
                            <div class="d-flex align-items-center gap-2 mb-2 text-danger fw-bold fs-13">
                                <i class="feather-alert-triangle"></i> Involuntary Separation Details
                            </div>
                            <p class="text-muted fs-12 mb-3">
                                Submitting termination will automatically initiate an Exit Case in the Offboarding Hub, set the Last Working Day (LWD), and assign multi-department clearance checklists.
                            </p>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold fs-12 text-dark">Termination Mode</label>
                                    <div class="d-flex gap-3 mt-1">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="termination_mode" id="prof_term_mode_notice" value="notice" checked onchange="toggleProfTerminationNotice(true)">
                                            <label class="form-check-label fs-13" for="prof_term_mode_notice">
                                                Serve Notice
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="termination_mode" id="prof_term_mode_imm" value="immediate" onchange="toggleProfTerminationNotice(false)">
                                            <label class="form-check-label fs-13" for="prof_term_mode_imm">
                                                Immediate (Today)
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6" id="prof_term_notice_days_box">
                                    <label class="form-label fw-bold fs-12 text-dark">Notice Duration</label>
                                    <select name="termination_notice_days" class="form-select form-select-sm">
                                        <option value="7">7 Days Notice</option>
                                        <option value="15" selected>15 Days Notice</option>
                                        <option value="30">30 Days Notice</option>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-bold fs-12 text-dark">Reason Category</label>
                                    <select name="termination_reason_category" class="form-select form-select-sm">
                                        <option value="Performance / Skill Gap">Performance / Skill Gap</option>
                                        <option value="Cultural / Team Misalignment">Cultural / Team Misalignment</option>
                                        <option value="Attendance & Discipline">Attendance & Punctuality Issues</option>
                                        <option value="Role Fit / Restructuring">Role Fit / Restructuring</option>
                                        <option value="Probation Unsuccessful" selected>General Probation Unsuccessful</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold fs-12 text-uppercase text-muted mb-1">Remarks & Performance Notes</label>
                        <textarea name="remarks" class="form-control" rows="3" placeholder="Provide notes, specific feedback, accomplishments or improvement areas..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top p-3 d-flex justify-content-between">
                    <x-ui.button variant="light" data-bs-dismiss="modal" class="border px-4 fw-semibold">Cancel</x-ui.button>
                    <x-ui.button variant="primary" type="submit" class="px-4 fw-bold">
                        <i class="feather-check-circle me-1"></i> Submit Evaluation
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
