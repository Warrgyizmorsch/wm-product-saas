@extends('layouts.duralux')

@section('title', 'Scorecard #' . $plan->plan_number . ' | KRA & KPI Performance')
@section('page-title', 'Appraisal Scorecard: ' . $plan->plan_number)
@section('breadcrumb', 'HRMS / Performance / Scorecard')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="light" icon="feather-arrow-left" href="{{ route('hrms.kra-kpi.index') }}" class="border fw-semibold">
            Back to Hub
        </x-ui.button>

        <!-- Action: Submit Goals for Approval (Employee) -->
        @if($plan->status === 'draft' && ($isOwner || $isHrOrAdmin))
            <form method="POST" action="{{ route('hrms.kra-kpi.submit-goals', $plan->id) }}" id="submitGoalsForm" class="d-inline">
                @csrf
                <x-ui.button type="button" variant="primary" icon="feather-send" class="fw-bold" onclick="confirmAction({ title: 'Submit Goal Sheet', message: 'Lock and submit your goals to your manager/evaluator for review and approval?', variant: 'primary', confirmText: 'Submit Goals' }, function() { document.getElementById('submitGoalsForm').submit(); })">
                    Submit Goals for Approval
                </x-ui.button>
            </form>
        @endif

        <!-- Action: Approve Goals (Manager / HR) -->
        @if($plan->status === 'submitted' && ($isManager || $isHrOrAdmin))
            <form method="POST" action="{{ route('hrms.kra-kpi.approve-goals', $plan->id) }}" id="approveGoalsForm" class="d-inline">
                @csrf
                <x-ui.button type="button" variant="success" icon="feather-check-circle" class="fw-bold" onclick="confirmAction({ title: 'Approve Goal Sheet', message: 'Approve assigned goals for this employee and activate this scorecard for progress tracking?', variant: 'success', confirmText: 'Approve Goals' }, function() { document.getElementById('approveGoalsForm').submit(); })">
                    Approve Goal Sheet
                </x-ui.button>
            </form>
        @endif

        <!-- Action: Open Self-Appraisal Modal -->
        @if(in_array($plan->status, ['approved', 'in_progress']) && ($isOwner || $isHrOrAdmin))
            <x-ui.button variant="warning" icon="feather-edit-3" data-bs-toggle="modal" data-bs-target="#selfAppraisalModal" class="fw-bold">
                Submit Self-Appraisal
            </x-ui.button>
        @endif

        <!-- Action: Open Evaluator Appraisal Modal -->
        @if(in_array($plan->status, ['self_reviewed', 'approved']) && ($isManager || $isHrOrAdmin))
            <x-ui.button variant="primary" icon="feather-award" data-bs-toggle="modal" data-bs-target="#managerAppraisalModal" class="fw-bold">
                Submit Evaluator Review
            </x-ui.button>
        @endif

        <!-- Action: Open HR Calibration Modal -->
        @if(in_array($plan->status, ['manager_reviewed', 'self_reviewed']) && $isHrOrAdmin)
            <x-ui.button variant="primary" icon="feather-sliders" data-bs-toggle="modal" data-bs-target="#calibrationModal" class="fw-bold">
                Calibrate Score
            </x-ui.button>
        @endif

        <!-- Action: Employee Sign-off -->
        @if($plan->status === 'calibrated' && ($isOwner || $isHrOrAdmin))
            <form method="POST" action="{{ route('hrms.kra-kpi.sign-off', $plan->id) }}" id="signOffForm" class="d-inline">
                @csrf
                <x-ui.button type="button" variant="success" icon="feather-check-square" class="fw-bold" onclick="confirmAction({ title: 'Acknowledge & Sign Off', message: 'Digitally acknowledge and sign off your final appraisal evaluation?', variant: 'success', confirmText: 'Sign Off Appraisal' }, function() { document.getElementById('signOffForm').submit(); })">
                    Acknowledge & Sign Off
                </x-ui.button>
            </form>
        @endif
    </div>
@endsection

@push('styles')
<style>
    .avatar-large {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: #eff6ff !important;
        color: #1d4ed8 !important;
        border: 2px solid #bfdbfe !important;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 19px;
        flex-shrink: 0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }
    .kpi-timeline {
        position: relative;
        padding-left: 24px;
    }
    .kpi-timeline::before {
        content: '';
        position: absolute;
        top: 8px;
        bottom: 8px;
        left: 8px;
        width: 2px;
        background: #e2e8f0;
    }
    .kpi-timeline-item {
        position: relative;
        margin-bottom: 20px;
    }
    .kpi-timeline-item:last-child {
        margin-bottom: 0;
    }
    .kpi-timeline-dot {
        position: absolute;
        left: -24px;
        top: 4px;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #eff6ff;
        border: 2px solid #3b82f6;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 9px;
        color: #3b82f6;
    }
</style>
@endpush

@section('content')
<div class="container-fluid p-0">

    @if(session('success'))
        <x-ui.alert variant="success" icon="feather-check-circle" dismissible class="mb-4">
            {{ session('success') }}
        </x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible class="mb-4">
            {{ session('error') }}
        </x-ui.alert>
    @endif

    <!-- 1-Click Bridge to PIP Banner if score is critical after review -->
    @if(in_array($plan->status, ['manager_reviewed', 'calibrated', 'signed_off']) && $plan->final_score !== null && $plan->final_score < 60)
        @if($plan->pip_triggered)
            <div class="alert alert-warning d-flex align-items-center justify-content-between p-3 rounded-3 mb-4 shadow-sm border-0 flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px; background-color: rgba(var(--bs-warning-rgb), 0.15); color: var(--bs-warning);">
                        <i class="feather-shield fs-18"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">Performance Improvement Plan (PIP) Active</h6>
                        <small class="text-muted">A formal 60-day corrective coaching track was initiated for this employee based on their appraisal results.</small>
                    </div>
                </div>
                <div>
                    @if($activePip)
                        <x-ui.button variant="warning" size="sm" href="{{ route('hrms.pip.show', $activePip->id) }}" class="fw-bold px-3" icon="feather-external-link">
                            View Active PIP (#{{ $activePip->pip_number }})
                        </x-ui.button>
                    @else
                        <x-ui.button variant="warning" size="sm" href="{{ route('hrms.pip.index') }}" class="fw-bold px-3" icon="feather-external-link">
                            View PIP Module
                        </x-ui.button>
                    @endif
                </div>
            </div>
        @else
            <div class="alert alert-danger d-flex align-items-center justify-content-between p-3 rounded-3 mb-4 shadow-sm border-0 flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px; background-color: rgba(var(--bs-danger-rgb), 0.15); color: var(--bs-danger);">
                        <i class="feather-alert-triangle fs-18"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-danger">Critical Low Performance Detected ({{ $plan->final_score }}% - {{ $plan->final_grade }})</h6>
                        <small class="text-danger-emphasis">This employee's appraisal score is below the organizational passing threshold (60%).</small>
                    </div>
                </div>
                @if($isHrOrAdmin)
                    <form method="POST" action="{{ route('hrms.kra-kpi.trigger-pip', $plan->id) }}" id="triggerPipBannerForm" class="m-0">
                        @csrf
                        <x-ui.button type="button" variant="danger" size="sm" class="fw-bold px-3" icon="feather-external-link" onclick="confirmAction({ title: 'Initiate 60-Day PIP', message: 'Initiate a 60-day formal Performance Improvement Plan (PIP) for this employee?', variant: 'danger', confirmText: 'Initiate PIP' }, function() { document.getElementById('triggerPipBannerForm').submit(); })">
                            Initiate 60-Day PIP
                        </x-ui.button>
                    </form>
                @endif
            </div>
        @endif
    @endif

    <!-- ERP Single Panel Workspace -->
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark mb-4">

        <!-- HEADER SUMMARY ROW -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-4 pb-4 border-bottom">
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-large">
                    {{ strtoupper(substr($plan->employee->full_name ?? 'EM', 0, 2)) }}
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h4 class="fw-extrabold text-dark mb-0">{{ $plan->employee->full_name ?? 'Employee' }}</h4>
                        <span class="badge bg-soft-primary text-primary fs-11">#{{ $plan->employee->employee_id ?? ('EMP-' . $plan->employee->id) }}</span>
                    </div>
                    <div class="text-muted fs-13">
                        <strong>{{ $plan->employee->designation->name ?? 'Role N/A' }}</strong> &bull; 
                        {{ $plan->employee->department->name ?? 'General' }} &bull; 
                        Reporting to: <strong>{{ $plan->manager->full_name ?? ($plan->employee->reportingManager->full_name ?? 'Direct Manager') }}</strong>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <!-- Status Card -->
                @php
                    $statusBadges = [
                        'draft' => ['secondary', 'Draft Goals'],
                        'submitted' => ['info', 'Goals Submitted'],
                        'approved' => ['primary', 'Goals Active'],
                        'self_reviewed' => ['warning', 'Self-Reviewed'],
                        'manager_reviewed' => ['primary', 'Manager-Reviewed'],
                        'calibrated' => ['success', 'Calibrated Score'],
                        'signed_off' => ['success', 'Completed & Signed Off'],
                    ];
                    $st = $statusBadges[$plan->status] ?? ['secondary', $plan->status];
                @endphp
                <div class="bg-light border rounded-3 px-3 py-2 text-center d-flex flex-column justify-content-between" style="min-width: 175px; min-height: 72px; box-sizing: border-box;">
                    <span class="text-muted d-block fs-10 text-uppercase fw-bold" style="letter-spacing: 0.5px;">Scorecard Status</span>
                    <div class="d-flex flex-column align-items-center justify-content-center flex-grow-1">
                        <x-ui.badge variant="{{ $st[0] }}" soft class="fs-11 px-2.5 py-1 text-nowrap">
                            {{ $st[1] }}
                        </x-ui.badge>
                        @if($plan->getOverdueStatus())
                            <x-ui.badge variant="danger" soft class="fs-10 px-2 py-0.5 mt-1 text-nowrap">
                                <i class="feather-alert-circle me-1"></i>{{ $plan->getOverdueStatus() }}
                            </x-ui.badge>
                        @endif
                    </div>
                </div>

                <!-- Final Score Card -->
                <div class="bg-light border rounded-3 px-3 py-2 text-center d-flex flex-column justify-content-between" style="min-width: 175px; height: 72px; box-sizing: border-box;">
                    <span class="text-muted d-block fs-10 text-uppercase fw-bold" style="letter-spacing: 0.5px;">Overall Score</span>
                    <div class="d-flex flex-column align-items-center justify-content-center flex-grow-1">
                        @if($plan->final_score !== null && in_array($plan->status, ['self_reviewed', 'manager_reviewed', 'calibrated', 'signed_off']))
                            @php
                                $scoreVariant = $plan->final_score >= 90 ? 'success' : ($plan->final_score >= 60 ? 'primary' : 'danger');
                            @endphp
                            <span class="fw-extrabold fs-16 text-{{ $scoreVariant }} lh-1">
                                {{ $plan->final_score }}%
                            </span>
                            <span class="fw-bold fs-10 text-{{ $scoreVariant }} mt-1 lh-1 text-nowrap">
                                {{ $plan->final_grade ?: 'Evaluated' }}
                            </span>
                        @else
                            <span class="text-muted fs-13 fw-semibold lh-1">In Progress</span>
                            <span class="text-muted fs-10 mt-1 lh-1">Pending Evaluation</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- APPRAISAL METADATA STRIP -->
        <div class="row g-3 my-3">
            <div class="col-md-3 col-sm-6">
                <div class="p-3 border rounded bg-light">
                    <small class="text-muted text-uppercase fs-10 fw-bold">Appraisal Cycle</small>
                    <div class="fw-bold text-dark fs-14 mt-1">{{ $plan->appraisalCycle->name ?? 'FY Review' }}</div>
                    <small class="text-muted fs-11">{{ $plan->appraisalCycle->start_date->format('d M Y') }} &rarr; {{ $plan->appraisalCycle->end_date->format('d M Y') }}</small>
                </div>
            </div>

            <div class="col-md-3 col-sm-6">
                <div class="p-3 border rounded bg-light">
                    <small class="text-muted text-uppercase fs-10 fw-bold">Goal Weightage</small>
                    <div class="fw-bold text-primary fs-14 mt-1">{{ $plan->total_weightage }}% of 100%</div>
                    <small class="text-muted fs-11">{{ $plan->items->count() }} Key Performance Indicators</small>
                </div>
            </div>

            <div class="col-md-3 col-sm-6">
                <div class="p-3 border rounded bg-light">
                    <small class="text-muted text-uppercase fs-10 fw-bold">Goal Score vs Comp</small>
                    <div class="fw-bold text-dark fs-14 mt-1">
                        {{ $plan->goal_score !== null ? $plan->goal_score . '%' : 'Pending' }} / 
                        {{ $plan->competency_score !== null ? $plan->competency_score . '%' : 'Pending' }}
                    </div>
                    <small class="text-muted fs-11">Goals (70%) + Values (30%)</small>
                </div>
            </div>

            <div class="col-md-3 col-sm-6">
                <div class="p-3 border rounded bg-light">
                    <small class="text-muted text-uppercase fs-10 fw-bold">Promotion Status</small>
                    <div class="fw-bold fs-14 mt-1 {{ $plan->promotion_recommended ? 'text-success' : 'text-muted' }}">
                        {{ $plan->promotion_recommended ? 'Recommended for Promotion' : 'Standard Trajectory' }}
                    </div>
                    <small class="text-muted fs-11">Evaluated by Direct Manager</small>
                </div>
            </div>
        </div>

        <!-- OVERDUE NOTIFICATION BANNER (Soft Enforcement) -->
        @if($plan->isGoalSettingOverdue())
            <div class="alert alert-danger d-flex align-items-center justify-content-between p-3 rounded-3 mb-3 shadow-sm border-0 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <i class="feather-alert-octagon fs-18 text-danger"></i>
                    <div>
                        <strong class="text-danger">Goal Setting Timeline Exceeded:</strong>
                        <span class="text-dark fs-12 ms-1">The deadline for submitting and approving goals was <strong>{{ $plan->appraisalCycle->goal_setting_deadline->format('d M Y') }}</strong> ({{ $plan->appraisalCycle->goal_setting_deadline->diffForHumans() }}). Please finalize and submit your goal sheet immediately.</span>
                    </div>
                </div>
            </div>
        @elseif($plan->isSelfReviewOverdue())
            <div class="alert alert-warning d-flex align-items-center justify-content-between p-3 rounded-3 mb-3 shadow-sm border-0 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <i class="feather-clock fs-18 text-warning"></i>
                    <div>
                        <strong class="text-warning">Self-Appraisal Submission Overdue:</strong>
                        <span class="text-dark fs-12 ms-1">The employee self-review deadline was <strong>{{ $plan->appraisalCycle->self_review_deadline->format('d M Y') }}</strong> ({{ $plan->appraisalCycle->self_review_deadline->diffForHumans() }}). Please complete your self-ratings to proceed to Manager Review.</span>
                    </div>
                </div>
            </div>
        @endif

        <!-- GOAL ITEMS & KPI SCORECARD TABLE -->
        <div class="d-flex justify-content-between align-items-center mb-3 mt-4">
            <h5 class="fw-bold text-dark mb-0">
                <i class="feather-target me-2 text-primary"></i> Assigned KPIs & Evaluation Rubric
            </h5>
            @if(in_array($plan->status, ['draft', 'submitted']) && ($isOwner || $isManager || $isHrOrAdmin))
                <x-ui.button variant="primary" size="sm" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addGoalItemModal" class="fw-bold">
                    Add KPI Goal
                </x-ui.button>
            @endif
        </div>

        <div class="table-responsive">
            <x-ui.table hoverable>
                <thead>
                    <tr>
                        <th style="width: 25%;">KPI Title & Strategic Focus</th>
                        <th style="width: 12%;">Target</th>
                        <th style="width: 12%;">Actual Achieved</th>
                        <th style="width: 8%;">Weight</th>
                        <th style="width: 12%;">Self-Rating</th>
                        <th style="width: 12%;">Evaluator Rating</th>
                        <th style="width: 9%;">Score</th>
                        <th style="width: 10%;" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($plan->items as $item)
                        @php
                            $unitLabel = match($item->unit) {
                                'percentage', '%' => '%',
                                'currency', '₹', '$' => '₹',
                                'number', 'qty' => 'Qty',
                                'rating' => 'Rating',
                                'boolean' => 'Milestone',
                                default => $item->unit,
                            };
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-bold text-dark fs-13">{{ $item->title }}</div>
                                @if($item->description)
                                    <div class="small text-muted fs-11 mb-1">{{ $item->description }}</div>
                                @endif
                                <div class="d-flex align-items-center gap-1 mt-1 flex-wrap">
                                    <x-ui.badge variant="primary" soft class="fs-10">{{ $item->kraCategory->name ?? 'Strategic Focus' }}</x-ui.badge>
                                    <span class="badge bg-light text-muted border fs-10 text-capitalize">{{ str_replace('_', ' ', $item->calculation_type) }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="fw-bold text-dark fs-13">{{ $item->target }}{{ $unitLabel === '%' ? '%' : ' ' . $unitLabel }}</span>
                            </td>
                            <td>
                                @if($item->actual !== null)
                                    <span class="fw-bold text-primary fs-13">{{ $item->actual }}{{ $unitLabel === '%' ? '%' : ' ' . $unitLabel }}</span>
                                @else
                                    <span class="text-muted fs-12">&mdash;</span>
                                @endif
                            </td>
                            <td>
                                <span class="fw-bold text-dark fs-13">{{ $item->weightage }}%</span>
                            </td>
                            <td>
                                @if($item->self_rating)
                                    <div class="fw-bold text-dark fs-12">{{ $item->self_rating }} / 5.0</div>
                                    @if($item->self_comment)
                                        <small class="text-muted d-block fs-11 text-truncate" style="max-width: 140px;" title="{{ $item->self_comment }}">{{ $item->self_comment }}</small>
                                    @endif
                                @else
                                    <span class="text-muted fs-12">Not Rated</span>
                                @endif
                            </td>
                            <td>
                                @if($item->manager_rating)
                                    <div class="fw-bold text-primary fs-12">{{ $item->manager_rating }} / 5.0</div>
                                    @if($item->manager_comment)
                                        <small class="text-muted d-block fs-11 text-truncate" style="max-width: 140px;" title="{{ $item->manager_comment }}">{{ $item->manager_comment }}</small>
                                    @endif
                                @else
                                    <span class="text-muted fs-12">Pending Review</span>
                                @endif
                            </td>
                            <td>
                                @if($item->final_score !== null)
                                    <span class="fw-bold fs-13 {{ $item->final_score >= 90 ? 'text-success' : ($item->final_score >= 60 ? 'text-primary' : 'text-danger') }}">
                                        {{ $item->final_score }}%
                                    </span>
                                @else
                                    <span class="text-muted fs-12">&mdash;</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="d-inline-flex justify-content-end align-items-center gap-1">
                                    <!-- View Check-in History Timeline Button -->
                                    <x-ui.icon-btn type="button" icon="feather-clock" variant="soft-info" size="sm" title="View Check-in History" data-bs-toggle="modal" data-bs-target="#historyModal{{ $item->id }}" />

                                    <!-- Log Progress Check-in Button -->
                                    @if(in_array($plan->status, ['approved', 'in_progress', 'self_reviewed']))
                                        <x-ui.icon-btn type="button" icon="feather-trending-up" variant="soft-primary" size="sm" title="Log Progress Check-in" data-bs-toggle="modal" data-bs-target="#progressModal{{ $item->id }}" />
                                    @endif

                                    <!-- Delete Item in Draft -->
                                    @if(in_array($plan->status, ['draft', 'submitted']) && ($isOwner || $isHrOrAdmin))
                                        <form method="POST" action="{{ route('hrms.kra-kpi.goal-item.destroy', $item->id) }}" id="deleteGoalItemForm{{ $item->id }}" class="d-inline m-0 p-0">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.icon-btn type="button" icon="feather-trash-2" variant="soft-danger" size="sm" title="Remove KPI Goal" onclick="confirmAction({ title: 'Remove KPI Goal', message: 'Remove this KPI goal from the scorecard?', variant: 'danger', confirmText: 'Remove Goal' }, function() { document.getElementById('deleteGoalItemForm{{ $item->id }}').submit(); })" />
                                        </form>
                                    @endif
                                </div>

                                <!-- MODAL: VIEW CHECK-IN HISTORY TIMELINE -->
                                <x-ui.modal id="historyModal{{ $item->id }}" title="Check-in History: {{ $item->title }}" size="lg" :showFooter="false">
                                    <div class="p-1">
                                        <!-- Header Summary Box -->
                                        <div class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border mb-4 flex-wrap gap-2">
                                            <div>
                                                <span class="fs-11 text-muted text-uppercase fw-bold d-block">Target vs Current Actual</span>
                                                <div class="fw-bold fs-15 text-dark mt-0.5">
                                                    <span class="text-muted">{{ $item->target }}{{ $unitLabel === '%' ? '%' : ' ' . $unitLabel }}</span>
                                                    <i class="feather-arrow-right mx-1 text-muted fs-12"></i>
                                                    <span class="text-primary">{{ $item->actual !== null ? $item->actual . ($unitLabel === '%' ? '%' : ' ' . $unitLabel) : 'No Progress Logged' }}</span>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <x-ui.badge variant="primary" soft>Weight: {{ $item->weightage }}%</x-ui.badge>
                                                <x-ui.badge variant="secondary" soft>{{ $item->kraCategory->name ?? 'Strategic Focus' }}</x-ui.badge>
                                            </div>
                                        </div>

                                        <!-- Timeline -->
                                        @if($item->progressLogs->isNotEmpty())
                                            <div class="kpi-timeline">
                                                @foreach($item->progressLogs->sortByDesc('created_at') as $log)
                                                    <div class="kpi-timeline-item">
                                                        <div class="kpi-timeline-dot">
                                                            <i class="feather-check"></i>
                                                        </div>
                                                        <div class="card border rounded-3 p-3 bg-white shadow-none">
                                                            <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-1">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <div class="avatar-initials bg-primary-subtle text-primary fw-bold" style="width: 28px; height: 28px; font-size: 11px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                                                        {{ strtoupper(substr($log->loggedBy->name ?? 'U', 0, 2)) }}
                                                                    </div>
                                                                    <div>
                                                                        <span class="fw-bold text-dark fs-12">{{ $log->loggedBy->name ?? 'System User' }}</span>
                                                                        <span class="text-muted fs-11 ms-1">&bull; {{ $log->created_at->format('d M Y, h:i A') }}</span>
                                                                    </div>
                                                                </div>
                                                                <div class="d-flex align-items-center gap-1">
                                                                    @if($log->previous_value !== null)
                                                                        <span class="text-muted fs-11 text-decoration-line-through">{{ $log->previous_value }}{{ $unitLabel === '%' ? '%' : ' ' . $unitLabel }}</span>
                                                                        <i class="feather-arrow-right fs-10 text-muted"></i>
                                                                    @endif
                                                                    <span class="badge bg-primary-subtle text-primary fw-bold fs-11">{{ $log->current_value }}{{ $unitLabel === '%' ? '%' : ' ' . $unitLabel }}</span>
                                                                </div>
                                                            </div>
                                                            @if($log->notes)
                                                                <div class="bg-light rounded p-2 text-dark fs-12 border-start border-3 border-primary mt-1">
                                                                    {{ $log->notes }}
                                                                </div>
                                                            @else
                                                                <div class="small text-muted fs-11 fst-italic mt-1">No additional notes provided.</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="text-center py-4 text-muted">
                                                <i class="feather-clock fs-32 d-block mb-2 text-muted"></i>
                                                <h6 class="fw-bold mb-1">No Check-in History Yet</h6>
                                                <p class="fs-12 mb-0">No periodic progress updates or check-ins have been recorded for this KPI goal.</p>
                                            </div>
                                        @endif

                                        <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                                            <x-ui.button type="button" variant="light" data-bs-dismiss="modal">Close</x-ui.button>
                                        </div>
                                    </div>
                                </x-ui.modal>

                                <!-- MODAL: LOG PROGRESS CHECK-IN FOR THIS ITEM -->
                                <x-ui.modal id="progressModal{{ $item->id }}" title="Log Progress: {{ $item->title }}" size="md" :showFooter="false">
                                    <form method="POST" action="{{ route('hrms.kra-kpi.log-progress', $item->id) }}">
                                        @csrf
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" label="Current Value / Milestone Achieved ({{ $unitLabel }})" name="current_value" value="{{ $item->actual ?? $item->target }}" :required="true" helperText="Target is: {{ $item->target }} {{ $unitLabel }}" />
                                            </div>
                                            <div class="col-12">
                                                <x-ui.odoo-form-ui type="textarea" label="Progress Notes / Evidence" name="notes" rows="3" placeholder="Briefly describe progress achieved..." />
                                            </div>
                                        </div>
                                        <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                                            <x-ui.button type="button" variant="light" data-bs-dismiss="modal">Cancel</x-ui.button>
                                            <x-ui.button type="submit" variant="primary" class="fw-bold px-4">Save Check-in</x-ui.button>
                                        </div>
                                    </form>
                                </x-ui.modal>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                No KPI goals added to this scorecard yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.table>
        </div>

        <!-- REMARKS & FEEDBACK AUDIT CARDS -->
        <div class="row g-4 mt-3">
            @if($plan->employee_comments)
                <div class="col-md-4">
                    <div class="card border rounded-3 p-3 bg-light">
                        <small class="text-muted fw-bold text-uppercase fs-10">Employee Self-Assessment Feedback</small>
                        <p class="text-dark fs-13 mb-0 mt-1.5">{{ $plan->employee_comments }}</p>
                    </div>
                </div>
            @endif

            @if($plan->manager_comments)
                <div class="col-md-4">
                    <div class="card border rounded-3 p-3 bg-light">
                        <small class="text-muted fw-bold text-uppercase fs-10">Manager Review Comments</small>
                        <p class="text-dark fs-13 mb-0 mt-1.5">{{ $plan->manager_comments }}</p>
                    </div>
                </div>
            @endif

            @if($plan->hr_comments)
                <div class="col-md-4">
                    <div class="card border rounded-3 p-3 bg-light">
                        <small class="text-muted fw-bold text-uppercase fs-10">HR Normalization & Committee Notes</small>
                        <p class="text-dark fs-13 mb-0 mt-1.5">{{ $plan->hr_comments }}</p>
                    </div>
                </div>
            @endif
        </div>

    </div>
</div>

<!-- MODAL: ADD GOAL ITEM TO SCORECARD -->
<x-ui.modal id="addGoalItemModal" title="Add Custom KPI Goal to Scorecard" size="lg" :showFooter="false">
    <form method="POST" action="{{ route('hrms.kra-kpi.goal-item.store', $plan->id) }}">
        @csrf
        <div class="row g-3">
            <div class="col-12">
                <x-ui.odoo-form-ui type="input" label="KPI Title" name="title" placeholder="e.g., Average Response Time < 15 Mins" :required="true" />
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="select" label="KRA Focus" name="kra_category_id">
                    <option value="">-- General Strategic Area --</option>
                    @foreach($kraCategories as $kra)
                        <option value="{{ $kra->id }}">{{ $kra->name }}</option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="Unit of Measure" name="unit" :required="true">
                    <option value="percentage">Percentage (%)</option>
                    <option value="number">Numeric Count</option>
                    <option value="currency">Currency (₹ / $)</option>
                    <option value="rating">Rating (1 to 5)</option>
                    <option value="boolean">Milestone (Yes/No)</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="Direction" name="calculation_type" :required="true">
                    <option value="higher_is_better">Higher is Better</option>
                    <option value="lower_is_better">Lower is Better</option>
                    <option value="milestone">Milestone</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" label="Target Goal" name="target" value="100.00" :required="true" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" label="Weightage %" name="weightage" value="20.00" :required="true" />
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="textarea" label="Description" name="description" rows="2" placeholder="Measurement criteria..." />
            </div>
        </div>
        <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
            <x-ui.button type="button" variant="light" data-bs-dismiss="modal">Cancel</x-ui.button>
            <x-ui.button type="submit" variant="primary" class="fw-bold px-4">Add Goal</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- MODAL: SELF-APPRAISAL -->
<x-ui.modal id="selfAppraisalModal" title="Self-Appraisal Evaluation" size="lg" :showFooter="false">
    <form method="POST" action="{{ route('hrms.kra-kpi.self-appraisal', $plan->id) }}">
        @csrf
        <p class="text-muted fs-13 mb-3">Rate your performance on each assigned KPI goal on a 1.0 to 5.0 scale.</p>
        <div class="d-flex flex-column gap-3">
            @foreach($plan->items as $item)
                <div class="p-3 border rounded-2 bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-dark">{{ $item->title }}</span>
                        <span class="badge bg-white border text-muted">Target: {{ $item->target }} {{ $item->unit }}</span>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-3">
                            <x-ui.odoo-form-ui type="input" inputType="number" step="0.1" label="Self-Rating (1-5)" name="items[{{ $item->id }}][self_rating]" value="{{ $item->self_rating ?? 4.0 }}" :required="true" />
                        </div>
                        <div class="col-md-9">
                            <x-ui.odoo-form-ui type="input" label="Self-Comments" name="items[{{ $item->id }}][self_comment]" value="{{ $item->self_comment }}" placeholder="Evidence of work..." />
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-3">
            <x-ui.odoo-form-ui type="textarea" label="Overall Self-Appraisal Summary / Aspirations" name="employee_comments" rows="3" placeholder="Summary of your performance throughout this cycle..." />
        </div>
        <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
            <x-ui.button type="button" variant="light" data-bs-dismiss="modal">Cancel</x-ui.button>
            <x-ui.button type="submit" variant="primary" class="fw-bold px-4">Submit Self-Appraisal</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- MODAL: EVALUATOR EVALUATION -->
<x-ui.modal id="managerAppraisalModal" title="Evaluator Review & Scoring" size="lg" :showFooter="false">
    <form method="POST" action="{{ route('hrms.kra-kpi.manager-appraisal', $plan->id) }}">
        @csrf
        <p class="text-muted fs-13 mb-3">Submit your evaluator ratings (1.0 to 5.0) and organizational core competency score.</p>
        <div class="d-flex flex-column gap-3">
            @foreach($plan->items as $item)
                <div class="p-3 border rounded-2 bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-bold text-dark">{{ $item->title }}</span>
                        <span class="text-muted fs-11">Employee Self-Rating: <strong>{{ $item->self_rating ?? 'N/A' }}/5.0</strong></span>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-3">
                            <x-ui.odoo-form-ui type="input" inputType="number" step="0.1" label="Evaluator Rating (1-5)" name="items[{{ $item->id }}][manager_rating]" value="{{ $item->manager_rating ?? ($item->self_rating ?? 4.0) }}" :required="true" />
                        </div>
                        <div class="col-md-9">
                            <x-ui.odoo-form-ui type="input" label="Evaluator Feedback" name="items[{{ $item->id }}][manager_comment]" value="{{ $item->manager_comment }}" placeholder="Evaluator remarks on this goal..." />
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="row g-3 mt-2">
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" label="Values & Competency Score %" name="competency_score" value="85.00" :required="true" />
            </div>
            <div class="col-md-6 d-flex align-items-center pt-3">
                <x-ui.odoo-form-ui type="checkbox" label="Promotion Recommended" name="promotion_recommended" :checked="$plan->promotion_recommended">
                    Recommend for Promotion / Role Upgrade
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="textarea" label="Evaluator Feedback & Growth Plan" name="manager_comments" rows="3" placeholder="Provide strategic performance remarks..." />
            </div>
        </div>
        <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
            <x-ui.button type="button" variant="light" data-bs-dismiss="modal">Cancel</x-ui.button>
            <x-ui.button type="submit" variant="primary" class="fw-bold px-4">Submit Evaluator Review</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- MODAL: HR CALIBRATION -->
<x-ui.modal id="calibrationModal" title="HR Committee Score Calibration & Normalization" size="md" :showFooter="false">
    <form method="POST" action="{{ route('hrms.kra-kpi.calibrate', $plan->id) }}">
        @csrf
        <div class="row g-3">
            <div class="col-12">
                <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" label="Normalized Final Score %" name="normalized_score" :value="$plan->final_score ?? 85.0" :required="true" />
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="select" label="Final Calibrated Grade" name="final_grade" :required="true">
                    <option value="Outstanding (O)" {{ $plan->final_grade == 'Outstanding (O)' ? 'selected' : '' }}>Outstanding (O)</option>
                    <option value="Exceeds Expectations (A)" {{ $plan->final_grade == 'Exceeds Expectations (A)' ? 'selected' : '' }}>Exceeds Expectations (A)</option>
                    <option value="Meets Expectations (B)" {{ $plan->final_grade == 'Meets Expectations (B)' ? 'selected' : '' }}>Meets Expectations (B)</option>
                    <option value="Needs Improvement (C)" {{ $plan->final_grade == 'Needs Improvement (C)' ? 'selected' : '' }}>Needs Improvement (C)</option>
                    <option value="Unsatisfactory (D)" {{ $plan->final_grade == 'Unsatisfactory (D)' ? 'selected' : '' }}>Unsatisfactory (D)</option>
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="textarea" label="Calibration Remarks" name="hr_comments" rows="3" placeholder="Normalization rationale..." />
            </div>
        </div>
        <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
            <x-ui.button type="button" variant="light" data-bs-dismiss="modal">Cancel</x-ui.button>
            <x-ui.button type="submit" variant="primary" class="fw-bold px-4">Publish Final Score</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<x-ui.confirmation-modal />
@endsection
