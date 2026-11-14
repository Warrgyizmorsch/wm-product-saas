@extends('layouts.duralux')

@section('title', __('hrms.kra_kpi.scorecard_title', ['plan' => $plan->plan_number]))
@section('page-title', __('hrms.kra_kpi.appraisal_scorecard_title', ['plan' => $plan->plan_number]))
@section('breadcrumb', __('hrms.kra_kpi.breadcrumb_hrms_perf_scorecard'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="light" icon="feather-arrow-left" href="{{ route('hrms.kra-kpi.index') }}" class="border fw-semibold">
            {{ __('hrms.kra_kpi.back_to_hub') }}
        </x-ui.button>

        <!-- Action: Submit Goals (Employee to Evaluator) -->
        @if($plan->status === 'draft' && $isOwner)
            <form method="POST" action="{{ route('hrms.kra-kpi.submit-goals', $plan->id) }}" id="submitGoalsForm" class="d-inline">
                @csrf
                <x-ui.button type="button" variant="primary" icon="feather-send" class="fw-bold" onclick="handleSubmitGoalSheet()">
                    {{ __('hrms.kra_kpi.submit_goals_for_approval') }}
                </x-ui.button>
            </form>
        @endif

        <!-- Action: Approve / Activate Goals (Evaluator / HR) -->
        @if(in_array($plan->status, ['draft', 'submitted']) && (($isManager && !$isOwner) || ($isHrOrAdmin && !$isOwner)))
            <form method="POST" action="{{ route('hrms.kra-kpi.approve-goals', $plan->id) }}" id="approveGoalsForm" class="d-inline">
                @csrf
                <x-ui.button type="button" variant="success" icon="feather-check-circle" class="fw-bold" onclick="handleApproveGoalSheet()">
                    {{ __('hrms.kra_kpi.approve_goal_sheet') }}
                </x-ui.button>
            </form>
        @endif

        <!-- Action: Open Self-Appraisal Modal (Employee Only) -->
        @if(in_array($plan->status, ['approved', 'in_progress']) && $isOwner)
            <x-ui.button variant="primary" icon="feather-edit-3" data-bs-toggle="modal" data-bs-target="#selfAppraisalModal" class="fw-bold">
                {{ __('hrms.kra_kpi.submit_self_appraisal') }}
            </x-ui.button>
        @endif

        <!-- Action: Open Evaluator Appraisal Modal (Manager or HR Evaluating Employee) -->
        @if(($isManager || $isHrOrAdmin) && !$isOwner)
            @if(in_array($plan->status, ['self_reviewed', 'approved']))
                <x-ui.button variant="primary" icon="feather-award" data-bs-toggle="modal" data-bs-target="#managerAppraisalModal" class="fw-bold">
                    {{ __('hrms.kra_kpi.submit_evaluator_review') }}
                </x-ui.button>
            @endif
        @endif

    </div>
@endsection

@push('styles')
<style>
    body > .select2-container--open {
        z-index: 2000;
    }

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

    /* Scorecard Table Text Wrapping & Elimination of Horizontal Scrollbar */
    .kra-scorecard-table-wrap .table-responsive {
        overflow-x: hidden !important;
        padding: 0 !important;
    }
    .kra-scorecard-table-wrap table.table {
        table-layout: fixed !important;
        width: 100% !important;
        margin-bottom: 0 !important;
    }
    .kra-scorecard-table-wrap th,
    .kra-scorecard-table-wrap td {
        white-space: normal !important;
        word-wrap: break-word !important;
        overflow-wrap: break-word !important;
        word-break: normal !important;
        vertical-align: top !important;
        padding: 12px 10px !important;
    }
    .kra-scorecard-table-wrap th.score-col,
    .kra-scorecard-table-wrap td.score-col,
    .kra-scorecard-table-wrap th.actions-col,
    .kra-scorecard-table-wrap td.actions-col,
    .kra-scorecard-table-wrap th.target-col,
    .kra-scorecard-table-wrap td.target-col,
    .kra-scorecard-table-wrap th.actual-col,
    .kra-scorecard-table-wrap td.actual-col,
    .kra-scorecard-table-wrap th.weight-col,
    .kra-scorecard-table-wrap td.weight-col {
        white-space: nowrap !important;
        word-wrap: normal !important;
        overflow-wrap: normal !important;
    }
    .kra-scorecard-table-wrap th.actions-col,
    .kra-scorecard-table-wrap td.actions-col {
        text-align: right !important;
        padding-right: 12px !important;
    }

    /* Single-line text truncation with ellipsis (...) for selects & inputs */
    .odoo-table-select,
    select.odoo-table-select,
    select.odoo-form-control,
    .odoo-form-control select,
    .kpi-select-wrap select,
    .kra-select-wrap select,
    #scorecardKpiSelectWrap select,
    #scorecardKraSelectWrap select {
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        padding-right: 24px !important;
        max-width: 100% !important;
    }

    /* Select2 container and single selection truncation */
    .select2-container {
        max-width: 100% !important;
        width: 100% !important;
    }
    .select2-container--bootstrap-5 .select2-selection--single {
        position: relative !important;
        padding-right: 24px !important;
        height: 30px !important;
        min-height: 30px !important;
        display: flex !important;
        align-items: center !important;
        box-sizing: border-box !important;
    }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        padding-left: 0 !important;
        padding-right: 16px !important;
        font-size: 13px !important;
        color: #212529 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        display: block !important;
        max-width: 100% !important;
        line-height: normal !important;
    }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
        position: absolute !important;
        top: 50% !important;
        right: 0 !important;
        transform: translateY(-50%) !important;
        width: 20px !important;
        height: 20px !important;
    }

    /* Keep dropdown menu results showing complete text with wrapping */
    .select2-container--bootstrap-5 .select2-results__option {
        white-space: normal !important;
        word-break: break-word !important;
        line-height: 1.35 !important;
        padding: 8px 12px !important;
        font-size: 13px !important;
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
    @if(in_array($plan->status, ['manager_reviewed']) && $plan->final_score !== null && $plan->final_score < 60)
        @if($plan->pip_triggered)
            <div class="alert alert-warning d-flex align-items-center justify-content-between p-3 rounded-3 mb-4 shadow-sm border-0 flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px; background-color: rgba(var(--bs-warning-rgb), 0.15); color: var(--bs-warning);">
                        <i class="feather-shield fs-18"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">{{ __('hrms.kra_kpi.pip_active_title') }}</h6>
                        <small class="text-muted">{{ __('hrms.kra_kpi.pip_active_desc') }}</small>
                    </div>
                </div>
                <div>
                    @if($activePip)
                        <x-ui.button variant="warning" size="sm" href="{{ route('hrms.pip.show', $activePip->id) }}" class="fw-bold px-3" icon="feather-external-link">
                            {{ __('hrms.kra_kpi.view_active_pip', ['number' => $activePip->pip_number]) }}
                        </x-ui.button>
                    @else
                        <x-ui.button variant="warning" size="sm" href="{{ route('hrms.pip.index') }}" class="fw-bold px-3" icon="feather-external-link">
                            {{ __('hrms.kra_kpi.view_pip_module') }}
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
                        <h6 class="fw-bold mb-0 text-danger">{{ __('hrms.kra_kpi.critical_low_performance', ['score' => $plan->final_score, 'grade' => $plan->final_grade]) }}</h6>
                        <small class="text-danger-emphasis">{{ __('hrms.kra_kpi.critical_low_desc') }}</small>
                    </div>
                </div>
                @if($isHrOrAdmin)
                    <form method="POST" action="{{ route('hrms.kra-kpi.trigger-pip', $plan->id) }}" id="triggerPipBannerForm" class="m-0">
                        @csrf
                        <x-ui.button type="button" variant="danger" size="sm" class="fw-bold px-3" icon="feather-external-link" onclick="confirmAction({ title: '{{ addslashes(__('hrms.kra_kpi.initiate_60day_pip')) }}', message: '{{ addslashes(__('hrms.kra_kpi.confirm_initiate_60day_pip')) }}', variant: 'danger', confirmText: '{{ addslashes(__('hrms.kra_kpi.initiate_60day_pip')) }}' }, function() { document.getElementById('triggerPipBannerForm').submit(); })">
                            {{ __('hrms.kra_kpi.initiate_60day_pip') }}
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
                        <h4 class="fw-extrabold text-dark mb-0">{{ $plan->employee->full_name ?? __('hrms.common.employee') }}</h4>
                        <span class="badge bg-soft-primary text-primary fs-11">#{{ $plan->employee->employee_id ?? ('EMP-' . $plan->employee->id) }}</span>
                    </div>
                    <div class="text-muted fs-13">
                        <strong>{{ $plan->employee->designation->name ?? __('hrms.kra_kpi.role_na') }}</strong> &bull; 
                        {{ $plan->employee->department->name ?? __('hrms.kra_kpi.general_dept') }} &bull; 
                        {{ __('hrms.kra_kpi.reporting_to') }} <strong>{{ $plan->manager->full_name ?? ($plan->employee->reportingManager->full_name ?? __('hrms.kra_kpi.direct_manager')) }}</strong>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <!-- Status Card -->
                @php
                    $statusBadges = [
                        'draft' => ['secondary', __('hrms.kra_kpi.status_draft_goals')],
                        'submitted' => ['info', __('hrms.kra_kpi.status_goals_submitted')],
                        'approved' => ['primary', __('hrms.kra_kpi.status_goals_active')],
                        'self_reviewed' => ['warning', __('hrms.kra_kpi.status_self_reviewed')],
                        'manager_reviewed' => ['success', __('hrms.kra_kpi.status_completed')],
                    ];
                    $st = $statusBadges[$plan->status] ?? ['secondary', $plan->status];
                @endphp
                <div class="bg-light border rounded-3 px-3 py-2 text-center d-flex flex-column justify-content-between" style="min-width: 175px; min-height: 72px; box-sizing: border-box;">
                    <span class="text-muted d-block fs-10 text-uppercase fw-bold" style="letter-spacing: 0.5px;">{{ __('hrms.kra_kpi.scorecard_status') }}</span>
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
                    <span class="text-muted d-block fs-10 text-uppercase fw-bold" style="letter-spacing: 0.5px;">{{ __('hrms.kra_kpi.overall_score') }}</span>
                    <div class="d-flex flex-column align-items-center justify-content-center flex-grow-1">
                        @if($plan->final_score !== null && in_array($plan->status, ['self_reviewed', 'manager_reviewed']))
                            @php
                                $scoreVariant = $plan->final_score >= 90 ? 'success' : ($plan->final_score >= 60 ? 'primary' : 'danger');
                            @endphp
                            <span class="fw-extrabold fs-16 text-{{ $scoreVariant }} lh-1">
                                {{ $plan->final_score }}%
                            </span>
                            <span class="fw-bold fs-10 text-{{ $scoreVariant }} mt-1 lh-1 text-nowrap">
                                {{ $plan->final_grade ?: __('hrms.kra_kpi.evaluated') }}
                            </span>
                        @else
                            <span class="text-muted fs-13 fw-semibold lh-1">{{ __('hrms.kra_kpi.in_progress') }}</span>
                            <span class="text-muted fs-10 mt-1 lh-1">{{ __('hrms.kra_kpi.pending_evaluation') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- APPRAISAL METADATA STRIP -->
        <div class="row g-3 my-3">
            <div class="col-md-3 col-sm-6">
                <div class="p-3 border rounded bg-light h-100">
                    <small class="text-muted text-uppercase fs-10 fw-bold">{{ __('hrms.kra_kpi.th_appraisal_cycle') }}</small>
                    <div class="fw-bold text-dark fs-14 mt-1">{{ $plan->appraisalCycle->name ?? __('hrms.kra_kpi.performance_review') }}</div>
                    <small class="text-muted fs-11">{{ $plan->appraisalCycle->start_date ? $plan->appraisalCycle->start_date->format('d M Y') : '' }} &rarr; {{ $plan->appraisalCycle->end_date ? $plan->appraisalCycle->end_date->format('d M Y') : '' }}</small>
                </div>
            </div>

            <div class="col-md-3 col-sm-6">
                <div class="p-3 border rounded bg-light h-100">
                    <small class="text-muted text-uppercase fs-10 fw-bold">{{ __('hrms.kra_kpi.review_due_date') }}</small>
                    <div class="fw-bold text-dark fs-14 mt-1">
                        {{ $plan->appraisalCycle->self_review_deadline ? $plan->appraisalCycle->self_review_deadline->format('d M Y') : ($plan->appraisalCycle->end_date ? $plan->appraisalCycle->end_date->format('d M Y') : __('hrms.kra_kpi.end_of_cycle')) }}
                    </div>
                    <small class="fs-11">
                        @if($plan->appraisalCycle->self_review_deadline)
                            @if($plan->appraisalCycle->self_review_deadline->isPast())
                                <span class="text-danger fw-semibold">{{ __('hrms.kra_kpi.review_window_closed') }}</span>
                            @else
                                <span class="text-muted">{{ $plan->appraisalCycle->self_review_deadline->diffForHumans() }}</span>
                            @endif
                        @else
                            <span class="text-muted">{{ __('hrms.kra_kpi.self_evaluator_review') }}</span>
                        @endif
                    </small>
                </div>
            </div>

            <div class="col-md-3 col-sm-6">
                <div class="p-3 border rounded bg-light h-100">
                    @if((float)$plan->total_weightage < 100)
                        <small class="text-muted text-uppercase fs-10 fw-bold">{{ __('hrms.kra_kpi.goal_weightage') }}</small>
                        <div class="fw-bold fs-14 mt-1 text-primary">
                            {{ (float)$plan->total_weightage }}% / 100%
                        </div>
                        <small class="text-warning fs-11 fw-semibold"><i class="feather-alert-circle me-1"></i>{{ __('hrms.kra_kpi.unallocated', ['diff' => round(100 - (float)$plan->total_weightage, 2)]) }}</small>
                    @elseif((float)$plan->total_weightage > 100)
                        <small class="text-muted text-uppercase fs-10 fw-bold">{{ __('hrms.kra_kpi.goal_weightage') }}</small>
                        <div class="fw-bold fs-14 mt-1 text-danger">
                            {{ (float)$plan->total_weightage }}% / 100%
                        </div>
                        <small class="text-danger fs-11 fw-semibold"><i class="feather-alert-triangle me-1"></i>{{ __('hrms.kra_kpi.exceeds_by', ['diff' => round((float)$plan->total_weightage - 100, 2)]) }}</small>
                    @else
                        <small class="text-muted text-uppercase fs-10 fw-bold">{{ __('hrms.kra_kpi.assigned_goals') }}</small>
                        <div class="fw-bold text-dark fs-14 mt-1">
                            {{ __('hrms.kra_kpi.key_performance_indicators', ['count' => $plan->items->count()]) }}
                        </div>
                        <small class="text-success fs-11 fw-semibold"><i class="feather-check-circle me-1"></i>{{ __('hrms.kra_kpi.weightage_allocated_100') }}</small>
                    @endif
                </div>
            </div>

            <div class="col-md-3 col-sm-6">
                <div class="p-3 border rounded bg-light h-100">
                    <small class="text-muted text-uppercase fs-10 fw-bold">{{ __('hrms.kra_kpi.direct_evaluator') }}</small>
                    <div class="fw-bold text-dark fs-14 mt-1">
                        {{ $plan->manager->full_name ?? ($plan->employee->reportingManager->full_name ?? __('hrms.kra_kpi.assigned_manager')) }}
                    </div>
                    <small class="text-muted fs-11">
                        @if($plan->promotion_recommended)
                            <span class="text-success fw-bold"><i class="feather-trending-up me-1"></i>{{ __('hrms.kra_kpi.promotion_recommended') }}</span>
                        @else
                            {{ __('hrms.kra_kpi.reporting_manager_review') }}
                        @endif
                    </small>
                </div>
            </div>
        </div>

        <!-- OVERDUE NOTIFICATION BANNER (Soft Enforcement) -->
        @if($plan->isGoalSettingOverdue())
            <div class="alert alert-danger d-flex align-items-center justify-content-between p-3 rounded-3 mb-3 shadow-sm border-0 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <i class="feather-alert-octagon fs-18 text-danger"></i>
                    <div>
                        <strong class="text-danger">{{ __('hrms.kra_kpi.goal_setting_overdue_title') }}</strong>
                        <span class="text-dark fs-12 ms-1">{{ __('hrms.kra_kpi.goal_setting_overdue_desc', ['deadline' => $plan->appraisalCycle->goal_setting_deadline->format('d M Y'), 'diff' => $plan->appraisalCycle->goal_setting_deadline->diffForHumans()]) }}</span>
                    </div>
                </div>
            </div>
        @elseif($plan->isSelfReviewOverdue())
            <div class="alert alert-warning d-flex align-items-center justify-content-between p-3 rounded-3 mb-3 shadow-sm border-0 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <i class="feather-clock fs-18 text-warning"></i>
                    <div>
                        <strong class="text-warning">{{ __('hrms.kra_kpi.self_review_overdue_title') }}</strong>
                        <span class="text-dark fs-12 ms-1">{{ __('hrms.kra_kpi.self_review_overdue_desc', ['deadline' => $plan->appraisalCycle->self_review_deadline->format('d M Y'), 'diff' => $plan->appraisalCycle->self_review_deadline->diffForHumans()]) }}</span>
                    </div>
                </div>
            </div>
        @endif

        @if(in_array($plan->status, ['draft', 'submitted']) && (float)$plan->total_weightage < 100)
            <div class="alert alert-warning d-flex align-items-center justify-content-between p-3 rounded-3 mb-3 border-0 flex-wrap gap-2 shadow-sm">
                <div class="d-flex align-items-center gap-3">
                    <i class="feather-alert-triangle fs-18 text-warning flex-shrink-0"></i>
                    <div>
                        <strong class="text-warning">{{ __('hrms.kra_kpi.goal_weightage_incomplete_title') }}</strong>
                        <span class="text-dark fs-12 ms-1">
                            {{ __('hrms.kra_kpi.goal_weightage_incomplete_desc', ['weight' => (float)$plan->total_weightage, 'remaining' => round(100 - (float)$plan->total_weightage, 2)]) }}
                        </span>
                    </div>
                </div>
                @if($isOwner || $isManager || $isHrOrAdmin)
                    <x-ui.button variant="warning" size="sm" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addGoalItemModal" class="fw-bold fs-11">
                        {{ __('hrms.kra_kpi.allocate_remaining', ['diff' => round(100 - (float)$plan->total_weightage, 2)]) }}
                    </x-ui.button>
                @endif
            </div>
        @elseif(in_array($plan->status, ['draft', 'submitted']) && (float)$plan->total_weightage > 100)
            <div class="alert alert-danger d-flex align-items-center justify-content-between p-3 rounded-3 mb-3 border-0 flex-wrap gap-2 shadow-sm">
                <div class="d-flex align-items-center gap-3">
                    <i class="feather-alert-octagon fs-18 text-danger flex-shrink-0"></i>
                    <div>
                        <strong class="text-danger">{{ __('hrms.kra_kpi.goal_weightage_exceeded_title') }}</strong>
                        <span class="text-dark fs-12 ms-1">
                            {{ __('hrms.kra_kpi.goal_weightage_exceeded_desc', ['weight' => (float)$plan->total_weightage, 'diff' => round((float)$plan->total_weightage - 100, 2)]) }}
                        </span>
                    </div>
                </div>
            </div>
        @endif

        <!-- GOAL ITEMS & KPI SCORECARD TABLE -->
        <div class="d-flex justify-content-between align-items-center mb-3 mt-4">
            <h5 class="fw-bold text-dark mb-0">
                <i class="feather-target me-2 text-primary"></i> {{ __('hrms.kra_kpi.assigned_kpis_rubric') }}
            </h5>
            @if(in_array($plan->status, ['draft', 'submitted']) && ($isOwner || $isManager || $isHrOrAdmin))
                <x-ui.button variant="primary" size="sm" icon="feather-edit-2" data-bs-toggle="modal" data-bs-target="#addGoalItemModal" class="fw-bold">
                    {{ __('hrms.kra_kpi.manage_kpi_goals') }}
                </x-ui.button>
            @endif
        </div>

        <div class="kra-scorecard-table-wrap">
            <x-ui.table hoverable>
                <thead>
                    <tr>
                        <th style="width: 26%;">{{ __('hrms.kra_kpi.th_kpi_title_focus') }}</th>
                        <th style="width: 7%;" class="target-col">{{ __('hrms.kra_kpi.th_target') }}</th>
                        <th style="width: 7%;" class="actual-col">{{ __('hrms.kra_kpi.th_actual') }}</th>
                        <th style="width: 6%;" class="weight-col">{{ __('hrms.kra_kpi.th_weight') }}</th>
                        <th style="width: 19%;">{{ __('hrms.kra_kpi.th_self_rating') }}</th>
                        <th style="width: 19%;">{{ __('hrms.kra_kpi.th_evaluator_rating') }}</th>
                        <th style="width: 7%;" class="score-col">{{ __('hrms.kra_kpi.th_score') }}</th>
                        <th style="width: 9%;" class="text-end text-nowrap actions-col">{{ __('hrms.kra_kpi.th_action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($plan->items as $item)
                        @php
                            $unitLabel = match($item->unit) {
                                'percentage', '%' => '%',
                                'currency', '₹', '$' => '₹',
                                'number', 'qty' => __('hrms.kra_kpi.unit_qty_short'),
                                'rating' => __('hrms.kra_kpi.unit_rating_scale'),
                                'boolean' => __('hrms.kra_kpi.unit_milestone_label'),
                                default => $item->unit,
                            };
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-bold text-dark fs-13 text-wrap text-break" style="line-height: 1.35;">{{ $item->title }}</div>
                                @if($item->description)
                                    <div class="small text-muted fs-11 mb-1 text-wrap text-break" style="line-height: 1.35;">{{ $item->description }}</div>
                                @endif
                                <div class="d-flex align-items-center gap-1 mt-1 flex-wrap">
                                    <x-ui.badge variant="primary" soft class="fs-10 text-wrap">{{ $item->kraCategory->name ?? __('hrms.kra_kpi.strategic_focus') }}</x-ui.badge>
                                    <span class="badge bg-light text-muted border fs-10 text-capitalize text-wrap">{{ str_replace('_', ' ', $item->calculation_type) }}</span>
                                </div>
                            </td>
                            <td class="target-col">
                                <span class="fw-bold text-dark fs-13 text-nowrap">{{ $item->target }}{{ $unitLabel === '%' ? '%' : ' ' . $unitLabel }}</span>
                            </td>
                            <td class="actual-col">
                                @if($item->actual !== null)
                                    <span class="fw-bold text-primary fs-13 text-nowrap">{{ $item->actual }}{{ $unitLabel === '%' ? '%' : ' ' . $unitLabel }}</span>
                                @else
                                    <span class="text-muted fs-12">&mdash;</span>
                                @endif
                            </td>
                            <td class="weight-col">
                                <span class="fw-bold text-dark fs-13 text-nowrap">{{ $item->weightage }}%</span>
                            </td>
                            <td>
                                @if($item->self_rating)
                                    <div class="fw-bold text-dark fs-12">{{ $item->self_rating }} / 5.0</div>
                                    @if($item->self_comment)
                                        <div class="text-muted fs-11 mt-1 text-wrap text-break" style="line-height: 1.35;">{{ $item->self_comment }}</div>
                                    @endif
                                @else
                                    <span class="text-muted fs-12">{{ __('hrms.kra_kpi.not_rated') }}</span>
                                @endif
                            </td>
                            <td>
                                @if($item->manager_rating)
                                    <div class="fw-bold text-primary fs-12">{{ $item->manager_rating }} / 5.0</div>
                                    @if($item->manager_comment)
                                        <div class="text-muted fs-11 mt-1 text-wrap text-break" style="line-height: 1.35;">{{ $item->manager_comment }}</div>
                                    @endif
                                @else
                                    <span class="text-muted fs-12">{{ __('hrms.kra_kpi.pending_review') }}</span>
                                @endif
                            </td>
                            <td class="score-col">
                                @if($item->final_score !== null)
                                    <span class="fw-bold fs-13 {{ $item->final_score >= 90 ? 'text-success' : ($item->final_score >= 60 ? 'text-primary' : 'text-danger') }}">
                                        {{ $item->final_score }}%
                                    </span>
                                @else
                                    <span class="text-muted fs-12">&mdash;</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap actions-col" style="vertical-align: top;">
                                <div class="d-inline-flex justify-content-end align-items-center gap-1">
                                    <!-- View Check-in History Timeline Button -->
                                    <x-ui.icon-btn type="button" icon="feather-clock" variant="soft-info" size="sm" title="{{ __('hrms.kra_kpi.view_checkin_history') }}" data-bs-toggle="modal" data-bs-target="#historyModal{{ $item->id }}" />

                                    <!-- Log Progress Check-in Button -->
                                    @if(in_array($plan->status, ['approved', 'in_progress', 'self_reviewed']))
                                        <x-ui.icon-btn type="button" icon="feather-trending-up" variant="soft-primary" size="sm" title="{{ __('hrms.kra_kpi.log_progress_checkin') }}" data-bs-toggle="modal" data-bs-target="#progressModal{{ $item->id }}" />
                                    @endif

                                    <!-- Delete Item in Draft (Owner, Manager, or HR/Admin) -->
                                    @if(in_array($plan->status, ['draft', 'submitted']) && ($isOwner || $isManager || $isHrOrAdmin))
                                        <form method="POST" action="{{ route('hrms.kra-kpi.goal-item.destroy', $item->id) }}" id="deleteGoalItemForm{{ $item->id }}" class="d-inline m-0 p-0">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.icon-btn type="button" icon="feather-trash-2" variant="soft-danger" size="sm" title="{{ __('hrms.kra_kpi.remove_kpi_goal') }}" onclick="confirmAction({ title: '{{ __('hrms.kra_kpi.remove_kpi_goal') }}', message: '{{ __('hrms.kra_kpi.confirm_remove_goal') }}', variant: 'danger', confirmText: '{{ __('hrms.kra_kpi.remove_goal') }}' }, function() { document.getElementById('deleteGoalItemForm{{ $item->id }}').submit(); })" />
                                        </form>
                                    @endif
                                </div>

                                <!-- MODAL: VIEW CHECK-IN HISTORY TIMELINE -->
                                <x-ui.modal id="historyModal{{ $item->id }}" title="{{ __('hrms.kra_kpi.checkin_history_title', ['title' => $item->title]) }}" size="lg" :showFooter="false">
                                    <div class="p-1">
                                        <!-- Header Summary Box -->
                                        <div class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border mb-4 flex-wrap gap-2">
                                            <div>
                                                <span class="fs-11 text-muted text-uppercase fw-bold d-block">{{ __('hrms.kra_kpi.target_vs_actual') }}</span>
                                                <div class="fw-bold fs-15 text-dark mt-0.5">
                                                    <span class="text-muted">{{ $item->target }}{{ $unitLabel === '%' ? '%' : ' ' . $unitLabel }}</span>
                                                    <i class="feather-arrow-right mx-1 text-muted fs-12"></i>
                                                    <span class="text-primary">{{ $item->actual !== null ? $item->actual . ($unitLabel === '%' ? '%' : ' ' . $unitLabel) : __('hrms.kra_kpi.no_progress_logged') }}</span>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <x-ui.badge variant="primary" soft>{{ __('hrms.kra_kpi.total_weight_label', ['weight' => $item->weightage]) }}</x-ui.badge>
                                                <x-ui.badge variant="secondary" soft>{{ $item->kraCategory->name ?? __('hrms.kra_kpi.strategic_focus') }}</x-ui.badge>
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
                                                                        <span class="fw-bold text-dark fs-12">{{ $log->loggedBy->name ?? __('hrms.kra_kpi.system_user') }}</span>
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
                                                                <div class="small text-muted fs-11 fst-italic mt-1">{{ __('hrms.kra_kpi.no_additional_notes') }}</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="text-center py-4 text-muted">
                                                <i class="feather-clock fs-32 d-block mb-2 text-muted"></i>
                                                <h6 class="fw-bold mb-1">{{ __('hrms.kra_kpi.no_checkin_history_yet') }}</h6>
                                                <p class="fs-12 mb-0">{{ __('hrms.kra_kpi.no_checkin_history_desc') }}</p>
                                            </div>
                                        @endif

                                        <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                                            <x-ui.button type="button" variant="light" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</x-ui.button>
                                        </div>
                                    </div>
                                </x-ui.modal>

                                <!-- MODAL: LOG PROGRESS CHECK-IN FOR THIS ITEM -->
                                <x-ui.modal id="progressModal{{ $item->id }}" title="{{ __('hrms.kra_kpi.log_progress_title', ['title' => $item->title]) }}" size="md" :showFooter="false">
                                    <form method="POST" action="{{ route('hrms.kra-kpi.log-progress', $item->id) }}">
                                        @csrf
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" label="{{ __('hrms.kra_kpi.current_value_label', ['unit' => $unitLabel]) }}" name="current_value" value="{{ $item->actual ?? $item->target }}" :required="true" helperText="{{ __('hrms.kra_kpi.target_helper', ['target' => $item->target, 'unit' => $unitLabel]) }}" />
                                            </div>
                                            <div class="col-12">
                                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.kra_kpi.progress_notes_evidence') }}" name="notes" rows="3" placeholder="{{ __('hrms.kra_kpi.placeholder_progress_notes') }}" />
                                            </div>
                                        </div>
                                        <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                                            <x-ui.button type="button" variant="light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
                                            <x-ui.button type="submit" variant="primary" class="fw-bold px-4">{{ __('hrms.kra_kpi.btn_save_checkin') }}</x-ui.button>
                                        </div>
                                    </form>
                                </x-ui.modal>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="feather-target fs-32 d-block mb-2 text-muted"></i>
                                <h6 class="fw-bold mb-1">{{ __('hrms.kra_kpi.no_goals_assigned_yet') }}</h6>
                                <p class="fs-12 mb-0">
                                    @if($isManager || $isHrOrAdmin)
                                        {{ __('hrms.kra_kpi.manager_add_goals_tip') }}
                                    @else
                                        {{ __('hrms.kra_kpi.employee_wait_goals_tip') }}
                                    @endif
                                </p>
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
                        <small class="text-muted fw-bold text-uppercase fs-10">{{ __('hrms.kra_kpi.employee_self_assessment_feedback') }}</small>
                        <p class="text-dark fs-13 mb-0 mt-1.5">{{ $plan->employee_comments }}</p>
                    </div>
                </div>
            @endif

            @if($plan->manager_comments)
                <div class="col-md-4">
                    <div class="card border rounded-3 p-3 bg-light">
                        <small class="text-muted fw-bold text-uppercase fs-10">{{ __('hrms.kra_kpi.manager_review_comments') }}</small>
                        <p class="text-dark fs-13 mb-0 mt-1.5">{{ $plan->manager_comments }}</p>
                    </div>
                </div>
            @endif

            @if($plan->hr_comments)
                <div class="col-md-4">
                    <div class="card border rounded-3 p-3 bg-light">
                        <small class="text-muted fw-bold text-uppercase fs-10">{{ __('hrms.kra_kpi.hr_normalization_notes') }}</small>
                        <p class="text-dark fs-13 mb-0 mt-1.5">{{ $plan->hr_comments }}</p>
                    </div>
                </div>
            @endif
        </div>

    </div>
</div>

<!-- MODAL: MANAGE KPI GOALS TO SCORECARD (MULTI-ROW REPEATER) -->
<x-ui.modal id="addGoalItemModal" title="{{ __('hrms.kra_kpi.manage_kpi_goals_weights') }}" size="xl" :showFooter="false">
    <form id="addGoalItemForm" method="POST" action="{{ route('hrms.kra-kpi.goal-item.store', $plan->id) }}">
        @csrf
        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
            <div>
                <small class="text-muted">{{ __('hrms.kra_kpi.manage_goals_subtitle') }}</small>
            </div>
            <div>
                <x-ui.button type="button" variant="light" size="sm" icon="feather-plus" id="addScorecardRowBtn" class="border fw-semibold">
                    {{ __('hrms.kra_kpi.add_goal_row') }}
                </x-ui.button>
            </div>
        </div>

        <x-ui.odoo-form-ui type="table">
            <thead>
                <tr>
                    <th style="width: 27%;">{{ __('hrms.kra_kpi.th_kpi_metric_title') }} <span class="text-danger">*</span></th>
                    <th style="width: 21%;">{{ __('hrms.kra_kpi.th_kra_focus') }}</th>
                    <th style="width: 17%;">{{ __('hrms.kra_kpi.th_direction') }}</th>
                    <th style="width: 14%;">{{ __('hrms.kra_kpi.th_unit') }}</th>
                    <th style="width: 10%;">{{ __('hrms.kra_kpi.th_target') }} <span class="text-danger">*</span></th>
                    <th style="width: 11%;">{{ __('hrms.kra_kpi.th_weight') }} <span class="text-danger">*</span></th>
                    <th style="width: 42px;" class="text-center">{{ __('hrms.kra_kpi.th_action') }}</th>
                </tr>
            </thead>
            <tbody id="scorecardRowsTbody">
                @forelse($plan->items as $index => $item)
                    @php
                        $matchedKm = $kpiMasters->firstWhere('name', $item->title);
                        $isStandard = (bool) $matchedKm;
                        $kraCategoryName = $item->kraCategory->name ?? ($matchedKm->kraCategory->name ?? '');
                    @endphp
                    <tr>
                        <td>
                            <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}" />
                            <div class="kpi-select-wrap {{ $isStandard ? 'd-flex' : 'd-none' }} align-items-center" style="width: 100%;">
                                <select class="odoo-table-select odoo-select2 kpi-row-select" style="width: 100%;">
                                    <option value="">{{ __('hrms.kra_kpi.choose_standard_kpi') }}</option>
                                    <option value="__custom__">{{ __('hrms.kra_kpi.add_custom_kpi') }}</option>
                                    @foreach($kpiMasters as $km)
                                        <option value="{{ $km->name }}"
                                            data-kpi-id="{{ $km->id }}"
                                            data-kra-id="{{ $km->kra_category_id }}"
                                            data-kra-name="{{ $km->kraCategory->name ?? '' }}"
                                            data-calc="{{ $km->calculation_type }}"
                                            data-unit="{{ $km->unit }}"
                                            data-target="{{ $km->default_target }}"
                                            data-weight="{{ $km->default_weightage }}"
                                            {{ $item->title === $km->name ? 'selected' : '' }}>
                                            {{ $km->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="kpi-input-wrap {{ $isStandard ? 'd-none' : 'd-flex' }} align-items-center gap-1">
                                <input type="text" name="items[{{ $index }}][title]" class="odoo-table-input kpi-title-input" value="{{ $item->title }}" placeholder="{{ __('hrms.kra_kpi.placeholder_custom_goal') }}" />
                                <button type="button" class="btn btn-sm btn-light border px-1.5 py-0 text-muted" title="{{ __('hrms.kra_kpi.switch_back_to_list') }}" onclick="switchToRowKpiSelect(this)" style="height: 22px; font-size: 10px; white-space: nowrap;">
                                    <i class="feather-list"></i>
                                </button>
                            </div>
                        </td>
                        <td>
                            <div class="kra-select-wrap {{ ($isStandard && $kraCategoryName) ? 'd-none' : 'd-flex' }} align-items-center" style="width: 100%;">
                                <select class="odoo-table-select odoo-select2 kra-row-select" style="width: 100%;">
                                    <option value="">{{ __('hrms.kra_kpi.choose_kra_focus') }}</option>
                                    <option value="__custom__">{{ __('hrms.kra_kpi.add_custom_kra') }}</option>
                                    @foreach($kraCategories as $kra)
                                        <option value="{{ $kra->id }}" {{ $item->kra_category_id == $kra->id ? 'selected' : '' }}>{{ $kra->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="kra-locked-wrap {{ ($isStandard && $kraCategoryName) ? 'd-flex' : 'd-none' }} align-items-center justify-content-between px-2 py-1 bg-light border rounded text-dark fs-12" style="height: 31px; width: 100%;">
                                <div class="d-flex align-items-center gap-1.5 text-truncate" style="min-width: 0;">
                                    <i class="feather-lock text-muted fs-11 flex-shrink-0" title="{{ __('hrms.kra_kpi.fixed_standard_kpi_category') }}"></i>
                                    <span class="kra-locked-text text-truncate fw-medium text-dark">{{ $kraCategoryName }}</span>
                                </div>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-0.5 px-1 fs-10 flex-shrink-0" style="font-size: 9px; line-height: 1.2;">{{ __('hrms.kra_kpi.library_badge') }}</span>
                            </div>
                            <div class="kra-input-wrap d-none align-items-center gap-1">
                                <input type="text" class="odoo-table-input kra-title-input" placeholder="{{ __('hrms.kra_kpi.type_custom_kra') }}" />
                                <button type="button" class="btn btn-sm btn-light border px-1.5 py-0 text-muted" title="{{ __('hrms.kra_kpi.switch_back_to_list') }}" onclick="switchToRowKraSelect(this)" style="height: 22px; font-size: 10px; white-space: nowrap;">
                                    <i class="feather-list"></i>
                                </button>
                            </div>
                            <input type="hidden" name="items[{{ $index }}][kra_category_id]" class="kra-final-id-input" value="{{ $item->kra_category_id }}" />
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="select" name="items[{{ $index }}][calculation_type]">
                                <option value="higher_is_better" {{ $item->calculation_type === 'higher_is_better' ? 'selected' : '' }}>{{ __('hrms.kra_kpi.dir_higher_short') }}</option>
                                <option value="lower_is_better" {{ $item->calculation_type === 'lower_is_better' ? 'selected' : '' }}>{{ __('hrms.kra_kpi.dir_lower_short') }}</option>
                                <option value="milestone" {{ $item->calculation_type === 'milestone' ? 'selected' : '' }}>{{ __('hrms.kra_kpi.dir_milestone_short') }}</option>
                            </x-ui.odoo-form-ui>
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="select" name="items[{{ $index }}][unit]">
                                <option value="percentage" {{ in_array($item->unit, ['percentage', '%']) ? 'selected' : '' }}>%</option>
                                <option value="number" {{ in_array($item->unit, ['number', 'qty']) ? 'selected' : '' }}>{{ __('hrms.kra_kpi.unit_qty_short') }}</option>
                                <option value="currency" {{ in_array($item->unit, ['currency', '₹', '$']) ? 'selected' : '' }}>{{ __('hrms.kra_kpi.unit_currency_symbol') }}</option>
                                <option value="rating" {{ $item->unit === 'rating' ? 'selected' : '' }}>{{ __('hrms.kra_kpi.unit_rating_scale') }}</option>
                                <option value="boolean" {{ in_array($item->unit, ['boolean', 'milestone']) ? 'selected' : '' }}>{{ __('hrms.kra_kpi.unit_milestone_label') }}</option>
                            </x-ui.odoo-form-ui>
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" name="items[{{ $index }}][target]" value="{{ $item->target }}" :required="true" />
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" name="items[{{ $index }}][weightage]" value="{{ $item->weightage }}" class="row-weight-input" :required="true" />
                        </td>
                        <td class="text-center">
                            <x-ui.icon-btn variant="soft-danger" icon="feather-trash-2" size="sm" class="remove-scorecard-row" title="{{ __('hrms.kra_kpi.remove_goal') }}" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td>
                            <div class="kpi-select-wrap d-flex align-items-center" style="width: 100%;">
                                <select class="odoo-table-select odoo-select2 kpi-row-select" style="width: 100%;">
                                    <option value="">{{ __('hrms.kra_kpi.choose_standard_kpi') }}</option>
                                    <option value="__custom__">{{ __('hrms.kra_kpi.add_custom_kpi') }}</option>
                                    @foreach($kpiMasters as $km)
                                        <option value="{{ $km->name }}"
                                            data-kpi-id="{{ $km->id }}"
                                            data-kra-id="{{ $km->kra_category_id }}"
                                            data-kra-name="{{ $km->kraCategory->name ?? '' }}"
                                            data-calc="{{ $km->calculation_type }}"
                                            data-unit="{{ $km->unit }}"
                                            data-target="{{ $km->default_target }}"
                                            data-weight="{{ $km->default_weightage }}">
                                            {{ $km->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="kpi-input-wrap d-none align-items-center gap-1">
                                <input type="text" name="items[0][title]" class="odoo-table-input kpi-title-input" placeholder="{{ __('hrms.kra_kpi.placeholder_custom_goal') }}" />
                                <button type="button" class="btn btn-sm btn-light border px-1.5 py-0 text-muted" title="{{ __('hrms.kra_kpi.switch_back_to_list') }}" onclick="switchToRowKpiSelect(this)" style="height: 22px; font-size: 10px; white-space: nowrap;">
                                    <i class="feather-list"></i>
                                </button>
                            </div>
                        </td>
                        <td>
                            <div class="kra-select-wrap d-flex align-items-center" style="width: 100%;">
                                <select class="odoo-table-select odoo-select2 kra-row-select" style="width: 100%;">
                                    <option value="">{{ __('hrms.kra_kpi.choose_kra_focus') }}</option>
                                    <option value="__custom__">{{ __('hrms.kra_kpi.add_custom_kra') }}</option>
                                    @foreach($kraCategories as $kra)
                                        <option value="{{ $kra->id }}">{{ $kra->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="kra-locked-wrap d-none align-items-center justify-content-between px-2 py-1 bg-light border rounded text-dark fs-12" style="height: 31px; width: 100%;">
                                <div class="d-flex align-items-center gap-1.5 text-truncate" style="min-width: 0;">
                                    <i class="feather-lock text-muted fs-11 flex-shrink-0" title="{{ __('hrms.kra_kpi.fixed_standard_kpi_category') }}"></i>
                                    <span class="kra-locked-text text-truncate fw-medium text-dark"></span>
                                </div>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-0.5 px-1 fs-10 flex-shrink-0" style="font-size: 9px; line-height: 1.2;">{{ __('hrms.kra_kpi.library_badge') }}</span>
                            </div>
                            <div class="kra-input-wrap d-none align-items-center gap-1">
                                <input type="text" class="odoo-table-input kra-title-input" placeholder="{{ __('hrms.kra_kpi.type_custom_kra') }}" />
                                <button type="button" class="btn btn-sm btn-light border px-1.5 py-0 text-muted" title="{{ __('hrms.kra_kpi.switch_back_to_list') }}" onclick="switchToRowKraSelect(this)" style="height: 22px; font-size: 10px; white-space: nowrap;">
                                    <i class="feather-list"></i>
                                </button>
                            </div>
                            <input type="hidden" name="items[0][kra_category_id]" class="kra-final-id-input" value="" />
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="select" name="items[0][calculation_type]">
                                <option value="higher_is_better">{{ __('hrms.kra_kpi.dir_higher_short') }}</option>
                                <option value="lower_is_better">{{ __('hrms.kra_kpi.dir_lower_short') }}</option>
                                <option value="milestone">{{ __('hrms.kra_kpi.dir_milestone_short') }}</option>
                            </x-ui.odoo-form-ui>
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="select" name="items[0][unit]">
                                <option value="percentage">%</option>
                                <option value="number">{{ __('hrms.kra_kpi.unit_qty_short') }}</option>
                                <option value="currency">{{ __('hrms.kra_kpi.unit_currency_symbol') }}</option>
                                <option value="rating">{{ __('hrms.kra_kpi.unit_rating_scale') }}</option>
                                <option value="boolean">{{ __('hrms.kra_kpi.unit_milestone_label') }}</option>
                            </x-ui.odoo-form-ui>
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" name="items[0][target]" value="100" :required="true" />
                        </td>
                        <td>
                            <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" name="items[0][weightage]" value="25" class="row-weight-input" :required="true" />
                        </td>
                        <td class="text-center">
                            <x-ui.icon-btn variant="soft-danger" icon="feather-trash-2" size="sm" class="remove-scorecard-row" title="{{ __('hrms.kra_kpi.remove_goal') }}" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.odoo-form-ui>

        <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="fs-12 text-muted fw-semibold">{{ __('hrms.kra_kpi.total_scorecard_weight') }}</span>
                <span id="scorecardTotalWeightBadge" class="badge bg-primary-subtle text-primary border border-primary-subtle fs-12 fw-bold">--</span>
                <small class="text-muted fs-11" id="scorecardWeightTip"></small>
            </div>
            <div class="d-flex gap-2">
                <x-ui.button type="button" variant="light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
                <x-ui.button type="submit" id="saveScorecardGoalsBtn" variant="primary" class="fw-bold px-4">{{ __('hrms.kra_kpi.save_goals_to_scorecard') }}</x-ui.button>
            </div>
        </div>
    </form>
</x-ui.modal>

<!-- MODAL: SELF-APPRAISAL -->
<x-ui.modal id="selfAppraisalModal" title="{{ __('hrms.kra_kpi.self_appraisal_evaluation') }}" size="lg" :showFooter="false">
    <form method="POST" action="{{ route('hrms.kra-kpi.self-appraisal', $plan->id) }}">
        @csrf
        <p class="text-muted fs-13 mb-3">{{ __('hrms.kra_kpi.self_appraisal_desc') }}</p>
        <div class="d-flex flex-column gap-3">
            @foreach($plan->items as $item)
                <div class="p-3 border rounded-2 bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-dark">{{ $item->title }}</span>
                        <span class="badge bg-white border text-muted">{{ __('hrms.kra_kpi.target_badge', ['target' => $item->target, 'unit' => $item->unit]) }}</span>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-3">
                            <x-ui.odoo-form-ui type="input" inputType="number" step="0.1" label="{{ __('hrms.kra_kpi.self_rating_label') }}" name="items[{{ $item->id }}][self_rating]" value="{{ $item->self_rating ?? 4.0 }}" :required="true" />
                        </div>
                        <div class="col-md-9">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.kra_kpi.self_comments_label') }}" name="items[{{ $item->id }}][self_comment]" value="{{ $item->self_comment }}" placeholder="{{ __('hrms.kra_kpi.placeholder_evidence') }}" />
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-3">
            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.kra_kpi.overall_self_summary') }}" name="employee_comments" rows="3" placeholder="{{ __('hrms.kra_kpi.placeholder_self_summary') }}" />
        </div>
        <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
            <x-ui.button type="button" variant="light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="primary" class="fw-bold px-4">{{ __('hrms.kra_kpi.btn_submit_self_appraisal') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- MODAL: EVALUATOR EVALUATION -->
<x-ui.modal id="managerAppraisalModal" title="{{ __('hrms.kra_kpi.evaluator_review_scoring') }}" size="lg" :showFooter="false">
    <form method="POST" action="{{ route('hrms.kra-kpi.manager-appraisal', $plan->id) }}">
        @csrf
        <p class="text-muted fs-13 mb-3">{{ __('hrms.kra_kpi.evaluator_desc') }}</p>
        <div class="d-flex flex-column gap-3">
            @foreach($plan->items as $item)
                <div class="p-3 border rounded-2 bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-bold text-dark">{{ $item->title }}</span>
                        <span class="text-muted fs-11">{{ __('hrms.kra_kpi.employee_self_rating_label', ['rating' => $item->self_rating ?? 'N/A']) }}</span>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-3">
                            <x-ui.odoo-form-ui type="input" inputType="number" step="0.1" label="{{ __('hrms.kra_kpi.evaluator_rating_label') }}" name="items[{{ $item->id }}][manager_rating]" value="{{ $item->manager_rating ?? ($item->self_rating ?? 4.0) }}" :required="true" />
                        </div>
                        <div class="col-md-9">
                            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.kra_kpi.evaluator_feedback_label') }}" name="items[{{ $item->id }}][manager_comment]" value="{{ $item->manager_comment }}" placeholder="{{ __('hrms.kra_kpi.placeholder_evaluator_remarks') }}" />
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="row g-3 mt-2">
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" label="{{ __('hrms.kra_kpi.values_competency_score') }}" name="competency_score" value="85.00" :required="true" />
            </div>
            <div class="col-md-6 d-flex align-items-center pt-3">
                <x-ui.odoo-form-ui type="checkbox" label="{{ __('hrms.kra_kpi.promotion_recommended') }}" name="promotion_recommended" :checked="$plan->promotion_recommended">
                    {{ __('hrms.kra_kpi.recommend_for_promotion') }}
                </x-ui.odoo-form-ui>
            </div>
            <div class="col-12">
                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.kra_kpi.growth_plan_label') }}" name="manager_comments" rows="3" placeholder="{{ __('hrms.kra_kpi.placeholder_growth_plan') }}" />
            </div>
        </div>
        <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
            <x-ui.button type="button" variant="light" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="primary" class="fw-bold px-4">{{ __('hrms.kra_kpi.btn_submit_evaluator_review') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<x-ui.confirmation-modal />

@push('scripts')
<script>
    window.handleApproveGoalSheet = function() {
        const totalWeight = {{ (float)$plan->total_weightage }};
        const itemCount = {{ $plan->items->count() }};

        if (itemCount === 0) {
            confirmAction({
                title: '{{ __('hrms.kra_kpi.no_goals_assigned_title') }}',
                message: '{{ __('hrms.kra_kpi.cannot_approve_empty_goals') }}',
                variant: 'warning',
                confirmText: '{{ __('hrms.kra_kpi.add_kpi_goals_btn') }}',
                cancelText: '{{ __('hrms.common.close') }}',
                onConfirm: function() {
                    const modalEl = document.getElementById('addGoalItemModal');
                    if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
            });
            return;
        }

        if (Math.abs(totalWeight - 100) > 0.01) {
            const diff = Math.round(Math.abs(100 - totalWeight) * 100) / 100;
            if (totalWeight < 100) {
                confirmAction({
                    title: '{{ __('hrms.kra_kpi.goal_weightage_incomplete_dialog_title') }}',
                    message: '{{ __('hrms.kra_kpi.weightage_incomplete_dialog_approve') }}'.replace(':total', totalWeight).replace(':diff', diff),
                    variant: 'warning',
                    confirmText: '{{ __('hrms.kra_kpi.allocate_remaining_btn') }}'.replace(':diff', diff),
                    cancelText: '{{ __('hrms.common.close') }}',
                    onConfirm: function() {
                        const modalEl = document.getElementById('addGoalItemModal');
                        if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    }
                });
            } else {
                confirmAction({
                    title: '{{ __('hrms.kra_kpi.goal_weightage_exceeded_dialog_title') }}',
                    message: '{{ __('hrms.kra_kpi.weightage_exceeded_dialog_approve') }}'.replace(':total', totalWeight).replace(':diff', diff),
                    variant: 'danger',
                    confirmText: '{{ __('hrms.kra_kpi.understood_btn') }}',
                    hideCancel: true
                });
            }
            return;
        }

        confirmAction({
            title: '{{ __('hrms.kra_kpi.approve_activate_goals_title') }}',
            message: '{{ __('hrms.kra_kpi.confirm_approve_goals_message') }}',
            variant: 'success',
            confirmText: '{{ __('hrms.kra_kpi.approve_goals_btn') }}'
        }, function() {
            document.getElementById('approveGoalsForm').submit();
        });
    };

    window.handleSubmitGoalSheet = function() {
        const totalWeight = {{ (float)$plan->total_weightage }};
        const itemCount = {{ $plan->items->count() }};

        if (itemCount === 0) {
            confirmAction({
                title: '{{ __('hrms.kra_kpi.no_goals_assigned_title') }}',
                message: '{{ __('hrms.kra_kpi.cannot_submit_empty_goals') }}',
                variant: 'warning',
                confirmText: '{{ __('hrms.kra_kpi.add_kpi_goals_btn') }}',
                cancelText: '{{ __('hrms.common.close') }}',
                onConfirm: function() {
                    const modalEl = document.getElementById('addGoalItemModal');
                    if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
            });
            return;
        }

        if (Math.abs(totalWeight - 100) > 0.01) {
            const diff = Math.round(Math.abs(100 - totalWeight) * 100) / 100;
            if (totalWeight < 100) {
                confirmAction({
                    title: '{{ __('hrms.kra_kpi.goal_weightage_incomplete_dialog_title') }}',
                    message: '{{ __('hrms.kra_kpi.weightage_incomplete_dialog_submit') }}'.replace(':total', totalWeight).replace(':diff', diff),
                    variant: 'warning',
                    confirmText: '{{ __('hrms.kra_kpi.allocate_remaining_btn') }}'.replace(':diff', diff),
                    cancelText: '{{ __('hrms.common.close') }}',
                    onConfirm: function() {
                        const modalEl = document.getElementById('addGoalItemModal');
                        if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    }
                });
            } else {
                confirmAction({
                    title: '{{ __('hrms.kra_kpi.goal_weightage_exceeded_dialog_title') }}',
                    message: '{{ __('hrms.kra_kpi.weightage_exceeded_dialog_submit') }}'.replace(':total', totalWeight).replace(':diff', diff),
                    variant: 'danger',
                    confirmText: '{{ __('hrms.kra_kpi.understood_btn') }}',
                    hideCancel: true
                });
            }
            return;
        }

        confirmAction({
            title: '{{ __('hrms.kra_kpi.submit_goals_approval_title') }}',
            message: '{{ __('hrms.kra_kpi.confirm_submit_goals_message') }}',
            variant: 'primary',
            confirmText: '{{ __('hrms.kra_kpi.submit_goals_btn') }}'
        }, function() {
            document.getElementById('submitGoalsForm').submit();
        });
    };

    document.addEventListener('DOMContentLoaded', function() {
        const kpiOptionsHtml = `
            <option value="">{{ __('hrms.kra_kpi.choose_standard_kpi') }}</option>
            <option value="__custom__">{{ __('hrms.kra_kpi.add_custom_kpi') }}</option>
            @foreach($kpiMasters as $km)
                <option value="{{ addslashes($km->name) }}"
                    data-kpi-id="{{ $km->id }}"
                    data-kra-id="{{ $km->kra_category_id }}"
                    data-kra-name="{{ addslashes($km->kraCategory->name ?? '') }}"
                    data-calc="{{ $km->calculation_type }}"
                    data-unit="{{ $km->unit }}"
                    data-target="{{ $km->default_target }}"
                    data-weight="{{ $km->default_weightage }}">
                    {{ addslashes($km->name) }}
                </option>
            @endforeach
        `;

        const kraOptionsHtml = `
            <option value="">{{ __('hrms.kra_kpi.choose_kra_focus') }}</option>
            <option value="__custom__">{{ __('hrms.kra_kpi.add_custom_kra') }}</option>
            @foreach($kraCategories as $kra)
                <option value="{{ $kra->id }}">{{ addslashes($kra->name) }}</option>
            @endforeach
        `;

        let scorecardRowIndex = {{ $plan->items->count() > 0 ? $plan->items->count() : 1 }};

        // Auto-synchronize Direction, Unit, and Target
        window.syncRowDirectionAndUnit = function(tr, changedField) {
            if (!tr) return;
            const $calcSelect = $(tr).find('select[name$="[calculation_type]"]');
            const $unitSelect = $(tr).find('select[name$="[unit]"]');
            const targetInput = tr.querySelector('input[name$="[target]"]');
            if (!$calcSelect.length || !$unitSelect.length || !targetInput) return;

            const calcVal = $calcSelect.val();
            const unitVal = $unitSelect.val();

            if (changedField === 'direction') {
                if (calcVal === 'milestone') {
                    // Milestone calculation: Unit must be Milestone (boolean), Target must be 1
                    if (unitVal !== 'boolean') {
                        $unitSelect.val('boolean').trigger('change.select2');
                    }
                    targetInput.value = 1;
                    targetInput.setAttribute('step', '1');
                    targetInput.setAttribute('min', '1');
                    targetInput.setAttribute('max', '1');
                    targetInput.setAttribute('placeholder', '1 (Done)');
                } else {
                    // Switched back to Higher or Lower is better
                    if (unitVal === 'boolean') {
                        $unitSelect.val('percentage').trigger('change.select2');
                        targetInput.value = 100;
                        targetInput.removeAttribute('max');
                        targetInput.setAttribute('step', '0.01');
                        targetInput.setAttribute('min', '0');
                        targetInput.setAttribute('placeholder', '100%');
                    }
                }
            } else if (changedField === 'unit') {
                if (unitVal === 'boolean') {
                    // Milestone unit selected
                    if (calcVal !== 'milestone') {
                        $calcSelect.val('milestone').trigger('change.select2');
                    }
                    targetInput.value = 1;
                    targetInput.setAttribute('step', '1');
                    targetInput.setAttribute('min', '1');
                    targetInput.setAttribute('max', '1');
                    targetInput.setAttribute('placeholder', '1 (Done)');
                } else {
                    // Quantitative units: %, Qty, ₹, Rating
                    if (calcVal === 'milestone') {
                        $calcSelect.val('higher_is_better').trigger('change.select2');
                    }
                    targetInput.removeAttribute('max');
                    targetInput.setAttribute('step', '0.01');
                    targetInput.setAttribute('min', '0');

                    if (unitVal === 'percentage') {
                        if (parseFloat(targetInput.value) > 100 || targetInput.value === '1') {
                            targetInput.value = 100;
                        }
                        targetInput.setAttribute('max', '100');
                        targetInput.setAttribute('placeholder', '100%');
                    } else if (unitVal === 'rating') {
                        if (parseFloat(targetInput.value) > 5 || targetInput.value === '100' || targetInput.value === '1') {
                            targetInput.value = 4.5;
                        }
                        targetInput.setAttribute('max', '5');
                        targetInput.setAttribute('step', '0.1');
                        targetInput.setAttribute('placeholder', '4.5 (1-5)');
                    } else if (unitVal === 'currency') {
                        if (targetInput.value === '1' || targetInput.value === '100' || targetInput.value === '95') {
                            targetInput.value = 100000;
                        }
                        targetInput.setAttribute('placeholder', '₹ e.g. 500000');
                    } else if (unitVal === 'number') {
                        if (targetInput.value === '100' || targetInput.value === '1') {
                            targetInput.value = 10;
                        }
                        targetInput.setAttribute('placeholder', 'Qty e.g. 10');
                    }
                }
            }
        };

        // Initialize select2 inside modal
        if (window.jQuery) {
            $('#addGoalItemModal').on('shown.bs.modal', function() {
                $(this).find('.odoo-select2').each(function() {
                    if ($(this).hasClass('select2-hidden-accessible')) {
                        $(this).select2('destroy');
                    }
                    const isSearchable = $(this).hasClass('kpi-row-select') || $(this).hasClass('kra-row-select');
                    $(this).select2({
                        theme: 'bootstrap-5',
                        dropdownParent: $('body'),
                        width: '100%',
                        minimumResultsForSearch: isSearchable ? 0 : Infinity
                    });
                });
                updateScorecardTotalWeight();
            });

            $(document).on('change change.select2 select2:select', '.kpi-row-select', function() {
                handleRowKpiSelect(this);
            });

            $(document).on('change change.select2 select2:select', '.kra-row-select', function() {
                handleRowKraSelect(this);
            });

            $(document).on('change change.select2 select2:select', '#scorecardRowsTbody select[name$="[calculation_type]"]', function() {
                syncRowDirectionAndUnit(this.closest('tr'), 'direction');
            });

            $(document).on('change change.select2 select2:select', '#scorecardRowsTbody select[name$="[unit]"]', function() {
                syncRowDirectionAndUnit(this.closest('tr'), 'unit');
            });
        }

        // Live weightage recalculation
        function updateScorecardTotalWeight() {
            let total = 0;
            document.querySelectorAll('#scorecardRowsTbody input[name$="[weightage]"]').forEach(function(inp) {
                const val = parseFloat(inp.value);
                if (!isNaN(val)) total += val;
            });

            total = Math.round(total * 100) / 100;
            const badge = document.getElementById('scorecardTotalWeightBadge');
            const tip = document.getElementById('scorecardWeightTip');

            if (badge) {
                badge.innerText = `${total}%`;
                badge.className = 'badge fs-12 fw-bold ' + (
                    total === 100 
                        ? 'bg-success text-white' 
                        : (total > 100 ? 'bg-danger text-white' : 'bg-primary-subtle text-primary border border-primary-subtle')
                );
            }

            if (tip) {
                if (total === 100) {
                    tip.innerText = '{{ __('hrms.kra_kpi.balanced_100') }}';
                    tip.className = 'text-success fs-11 fw-semibold';
                } else if (total < 100) {
                    tip.innerText = '{{ __('hrms.kra_kpi.remaining_to_reach_100') }}'.replace(':diff', Math.round((100 - total) * 100) / 100);
                    tip.className = 'text-muted fs-11';
                } else {
                    tip.innerText = '{{ __('hrms.kra_kpi.exceeds_100_by') }}'.replace(':diff', Math.round((total - 100) * 100) / 100);
                    tip.className = 'text-danger fs-11 fw-semibold';
                }
            }

            const saveBtn = document.getElementById('saveScorecardGoalsBtn');
            if (saveBtn) {
                if (total > 100) {
                    saveBtn.disabled = true;
                    saveBtn.title = '{{ __('hrms.kra_kpi.weight_exceed_tooltip') }}';
                    saveBtn.classList.add('opacity-50');
                } else {
                    saveBtn.disabled = false;
                    saveBtn.title = '';
                    saveBtn.classList.remove('opacity-50');
                }
            }
        }

        $(document).on('input', '#scorecardRowsTbody input[name$="[weightage]"]', function() {
            updateScorecardTotalWeight();
        });

        // KPI selection handler
        window.handleRowKpiSelect = function(sel) {
            const tr = sel.closest('tr');
            if (!tr) return;

            const selectWrap = tr.querySelector('.kpi-select-wrap');
            const inputWrap = tr.querySelector('.kpi-input-wrap');
            const titleInput = tr.querySelector('.kpi-title-input');
            const val = $(sel).val();

            const kraSelectWrap = tr.querySelector('.kra-select-wrap');
            const kraLockedWrap = tr.querySelector('.kra-locked-wrap');
            const kraInputWrap = tr.querySelector('.kra-input-wrap');
            const kraLockedText = tr.querySelector('.kra-locked-text');
            const $kraSelect = $(tr).find('.kra-row-select');
            const finalKra = tr.querySelector('.kra-final-id-input');

            if (val === '__custom__') {
                if (selectWrap) selectWrap.classList.add('d-none');
                if (inputWrap) {
                    inputWrap.classList.remove('d-none');
                    inputWrap.classList.add('d-flex');
                }
                if (titleInput) {
                    titleInput.value = '';
                    titleInput.focus();
                }

                // Unlock KRA for custom KPI
                if (kraLockedWrap) {
                    kraLockedWrap.classList.add('d-none');
                    kraLockedWrap.classList.remove('d-flex');
                }
                if (!kraInputWrap || kraInputWrap.classList.contains('d-none')) {
                    if (kraSelectWrap) {
                        kraSelectWrap.classList.remove('d-none');
                        kraSelectWrap.classList.add('d-flex');
                    }
                }
                return;
            }

            if (val) {
                if (titleInput) titleInput.value = val;
                const opt = sel.options ? sel.selectedOptions[0] : null;
                if (opt) {
                    const kraId = opt.dataset.kraId;
                    const kraName = opt.dataset.kraName || (kraId && $kraSelect.find(`option[value="${kraId}"]`).text().trim()) || '{{ __('hrms.kra_kpi.standard_kra') }}';
                    const calc = opt.dataset.calc;
                    const unit = opt.dataset.unit;
                    const target = opt.dataset.target;
                    const weight = opt.dataset.weight;

                    // Standard KPI picked: Auto-set and LOCK KRA to its official library category
                    if (kraId) {
                        if (kraInputWrap) {
                            kraInputWrap.classList.add('d-none');
                            kraInputWrap.classList.remove('d-flex');
                        }
                        if (kraSelectWrap) {
                            kraSelectWrap.classList.add('d-none');
                            kraSelectWrap.classList.remove('d-flex');
                        }
                        if (kraLockedWrap) {
                            kraLockedWrap.classList.remove('d-none');
                            kraLockedWrap.classList.add('d-flex');
                        }
                        if (kraLockedText) {
                            kraLockedText.innerText = kraName;
                        }
                        $kraSelect.val(kraId);
                        if (finalKra) finalKra.value = kraId;
                    }

                    const $calcSelect = $(tr).find('select[name$="[calculation_type]"]');
                    if ($calcSelect.length && calc) $calcSelect.val(calc).trigger('change.select2');

                    const $unitSelect = $(tr).find('select[name$="[unit]"]');
                    if ($unitSelect.length && unit) $unitSelect.val(unit).trigger('change.select2');

                    const targetInput = tr.querySelector('input[name$="[target]"]');
                    if (targetInput && target) targetInput.value = target;

                    const weightInput = tr.querySelector('input[name$="[weightage]"]');
                    if (weightInput && weight) {
                        weightInput.value = weight;
                        updateScorecardTotalWeight();
                    }
                }
            } else {
                if (titleInput) titleInput.value = '';
                if (kraLockedWrap) {
                    kraLockedWrap.classList.add('d-none');
                    kraLockedWrap.classList.remove('d-flex');
                }
                if (kraInputWrap) {
                    kraInputWrap.classList.add('d-none');
                    kraInputWrap.classList.remove('d-flex');
                }
                if (kraSelectWrap) {
                    kraSelectWrap.classList.remove('d-none');
                    kraSelectWrap.classList.add('d-flex');
                }
                $kraSelect.val('').trigger('change.select2');
                if (finalKra) finalKra.value = '';
            }
        };

        window.switchToRowKpiSelect = function(btn) {
            const tr = btn.closest('tr');
            if (!tr) return;
            const selectWrap = tr.querySelector('.kpi-select-wrap');
            const inputWrap = tr.querySelector('.kpi-input-wrap');
            const selectEl = tr.querySelector('.kpi-row-select');
            const titleInput = tr.querySelector('.kpi-title-input');

            const kraSelectWrap = tr.querySelector('.kra-select-wrap');
            const kraLockedWrap = tr.querySelector('.kra-locked-wrap');
            const kraInputWrap = tr.querySelector('.kra-input-wrap');
            const $kraSelect = $(tr).find('.kra-row-select');
            const finalKra = tr.querySelector('.kra-final-id-input');

            if (inputWrap) {
                inputWrap.classList.add('d-none');
                inputWrap.classList.remove('d-flex');
            }
            if (selectWrap) {
                selectWrap.classList.remove('d-none');
                selectWrap.classList.add('d-flex');
            }
            if (selectEl) {
                $(selectEl).val('').trigger('change.select2');
                $(selectEl).select2('open');
            }
            if (titleInput) titleInput.value = '';

            // Reset KRA when switching back to select
            if (kraLockedWrap) {
                kraLockedWrap.classList.add('d-none');
                kraLockedWrap.classList.remove('d-flex');
            }
            if (kraInputWrap) {
                kraInputWrap.classList.add('d-none');
                kraInputWrap.classList.remove('d-flex');
            }
            if (kraSelectWrap) {
                kraSelectWrap.classList.remove('d-none');
                kraSelectWrap.classList.add('d-flex');
            }
            if ($kraSelect.length) $kraSelect.val('').trigger('change.select2');
            if (finalKra) finalKra.value = '';
        };

        // KRA selection handler
        window.handleRowKraSelect = function(sel) {
            const tr = sel.closest('tr');
            if (!tr) return;

            const selectWrap = tr.querySelector('.kra-select-wrap');
            const inputWrap = tr.querySelector('.kra-input-wrap');
            const titleInput = tr.querySelector('.kra-title-input');
            const finalInput = tr.querySelector('.kra-final-id-input');
            const val = $(sel).val();

            if (val === '__custom__') {
                if (selectWrap) selectWrap.classList.add('d-none');
                if (inputWrap) {
                    inputWrap.classList.remove('d-none');
                    inputWrap.classList.add('d-flex');
                }
                if (titleInput) {
                    titleInput.value = '';
                    titleInput.focus();
                }
                if (finalInput) finalInput.value = '';
                return;
            }

            if (val) {
                if (finalInput) finalInput.value = val;
            } else {
                if (finalInput) finalInput.value = '';
            }
        };

        window.switchToRowKraSelect = function(btn) {
            const tr = btn.closest('tr');
            if (!tr) return;
            const selectWrap = tr.querySelector('.kra-select-wrap');
            const inputWrap = tr.querySelector('.kra-input-wrap');
            const selectEl = tr.querySelector('.kra-row-select');
            const titleInput = tr.querySelector('.kra-title-input');
            const finalInput = tr.querySelector('.kra-final-id-input');

            if (inputWrap) {
                inputWrap.classList.add('d-none');
                inputWrap.classList.remove('d-flex');
            }
            if (selectWrap) {
                selectWrap.classList.remove('d-none');
                selectWrap.classList.add('d-flex');
            }
            if (selectEl) {
                $(selectEl).val('').trigger('change.select2');
                $(selectEl).select2('open');
            }
            if (titleInput) titleInput.value = '';
            if (finalInput) finalInput.value = '';
        };

        $(document).on('input', '.kra-title-input', function() {
            const tr = this.closest('tr');
            if (!tr) return;
            const finalInput = tr.querySelector('.kra-final-id-input');
            if (finalInput) finalInput.value = this.value;
        });

        // Add Row Button
        document.getElementById('addScorecardRowBtn')?.addEventListener('click', function() {
            const tbody = document.getElementById('scorecardRowsTbody');
            if (!tbody) return;

            let currentSum = 0;
            document.querySelectorAll('#scorecardRowsTbody input[name$="[weightage]"]').forEach(function(inp) {
                const val = parseFloat(inp.value);
                if (!isNaN(val)) currentSum += val;
            });
            const remainingWeight = Math.round((100 - currentSum) * 100) / 100;
            const initialRowWeight = remainingWeight > 0 ? remainingWeight : 10;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <div class="kpi-select-wrap d-flex align-items-center" style="width: 100%;">
                        <select class="odoo-table-select odoo-select2 kpi-row-select" style="width: 100%;">
                            ${kpiOptionsHtml}
                        </select>
                    </div>
                    <div class="kpi-input-wrap d-none align-items-center gap-1">
                        <input type="text" name="items[${scorecardRowIndex}][title]" class="odoo-table-input kpi-title-input" placeholder="{{ __('hrms.kra_kpi.placeholder_custom_goal') }}" />
                        <button type="button" class="btn btn-sm btn-light border px-1.5 py-0 text-muted" title="{{ __('hrms.kra_kpi.switch_back_to_list') }}" onclick="switchToRowKpiSelect(this)" style="height: 22px; font-size: 10px; white-space: nowrap;">
                            <i class="feather-list"></i>
                        </button>
                    </div>
                </td>
                <td>
                    <div class="kra-select-wrap d-flex align-items-center" style="width: 100%;">
                        <select class="odoo-table-select odoo-select2 kra-row-select" style="width: 100%;">
                            ${kraOptionsHtml}
                        </select>
                    </div>
                    <div class="kra-locked-wrap d-none align-items-center justify-content-between px-2 py-1 bg-light border rounded text-dark fs-12" style="height: 31px; width: 100%;">
                        <div class="d-flex align-items-center gap-1.5 text-truncate" style="min-width: 0;">
                            <i class="feather-lock text-muted fs-11 flex-shrink-0" title="{{ __('hrms.kra_kpi.fixed_standard_kpi_category') }}"></i>
                            <span class="kra-locked-text text-truncate fw-medium text-dark"></span>
                        </div>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle py-0.5 px-1 fs-10 flex-shrink-0" style="font-size: 9px; line-height: 1.2;">{{ __('hrms.kra_kpi.library_badge') }}</span>
                    </div>
                    <div class="kra-input-wrap d-none align-items-center gap-1">
                        <input type="text" class="odoo-table-input kra-title-input" placeholder="{{ __('hrms.kra_kpi.type_custom_kra') }}" />
                        <button type="button" class="btn btn-sm btn-light border px-1.5 py-0 text-muted" title="{{ __('hrms.kra_kpi.switch_back_to_list') }}" onclick="switchToRowKraSelect(this)" style="height: 22px; font-size: 10px; white-space: nowrap;">
                            <i class="feather-list"></i>
                        </button>
                    </div>
                    <input type="hidden" name="items[${scorecardRowIndex}][kra_category_id]" class="kra-final-id-input" />
                </td>
                <td>
                    <select name="items[${scorecardRowIndex}][calculation_type]" class="odoo-table-select odoo-select2" style="width: 100%;">
                        <option value="higher_is_better">{{ __('hrms.kra_kpi.dir_higher_short') }}</option>
                        <option value="lower_is_better">{{ __('hrms.kra_kpi.dir_lower_short') }}</option>
                        <option value="milestone">{{ __('hrms.kra_kpi.dir_milestone_short') }}</option>
                    </select>
                </td>
                <td>
                    <select name="items[${scorecardRowIndex}][unit]" class="odoo-table-select odoo-select2" style="width: 100%;">
                        <option value="percentage">%</option>
                        <option value="number">{{ __('hrms.kra_kpi.unit_qty_short') }}</option>
                        <option value="currency">{{ __('hrms.kra_kpi.unit_currency_symbol') }}</option>
                        <option value="rating">{{ __('hrms.kra_kpi.unit_rating_scale') }}</option>
                        <option value="boolean">{{ __('hrms.kra_kpi.unit_milestone_label') }}</option>
                    </select>
                </td>
                <td>
                    <input type="number" step="0.01" name="items[${scorecardRowIndex}][target]" value="100" class="odoo-table-input" required />
                </td>
                <td>
                    <input type="number" step="0.01" name="items[${scorecardRowIndex}][weightage]" value="${initialRowWeight}" class="odoo-table-input row-weight-input" required />
                </td>
                <td class="text-center">
                    <button type="button" class="btn erp-icon-btn erp-icon-btn--danger btn-sm remove-scorecard-row" title="{{ __('hrms.kra_kpi.remove_goal') }}">
                        <i class="feather-trash-2"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);

            const $newRow = $(tr);
            $newRow.find('.odoo-select2').each(function() {
                const isSearchable = $(this).hasClass('kpi-row-select') || $(this).hasClass('kra-row-select');
                $(this).select2({
                    theme: 'bootstrap-5',
                    dropdownParent: $('body'),
                    width: '100%',
                    minimumResultsForSearch: isSearchable ? 0 : Infinity
                });
            });

            scorecardRowIndex++;
            updateScorecardTotalWeight();
        });

        // Remove row delegated listener
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.remove-scorecard-row');
            if (btn) {
                const tr = btn.closest('tr');
                const tbody = tr?.closest('tbody');
                if (tbody && tbody.querySelectorAll('tr').length > 1) {
                    tr.remove();
                    updateScorecardTotalWeight();
                } else {
                    confirmAction({
                        title: '{{ __('hrms.kra_kpi.minimum_goal_required_title') }}',
                        message: '{{ __('hrms.kra_kpi.minimum_goal_required_msg') }}',
                        variant: 'warning',
                        confirmText: '{{ __('hrms.common.ok') }}',
                        hideCancel: true
                    });
                }
            }
        });

        // Form Submit Validation: ensure titles are synced from selects if not typing custom
        document.getElementById('addGoalItemForm')?.addEventListener('submit', function(e) {
            const rows = document.querySelectorAll('#scorecardRowsTbody tr');
            let hasValidRow = false;

            rows.forEach(function(tr) {
                const titleInput = tr.querySelector('.kpi-title-input');
                const selectWrap = tr.querySelector('.kpi-select-wrap');
                const kpiSelect = tr.querySelector('.kpi-row-select');

                if (selectWrap && !selectWrap.classList.contains('d-none') && kpiSelect) {
                    const selVal = $(kpiSelect).val();
                    if (selVal && selVal !== '__custom__') {
                        if (titleInput) titleInput.value = selVal;
                    }
                }

                const kraSelect = tr.querySelector('.kra-row-select');
                const kraFinalInput = tr.querySelector('.kra-final-id-input');
                const kraSelectWrap = tr.querySelector('.kra-select-wrap');
                if (kraSelectWrap && !kraSelectWrap.classList.contains('d-none') && kraSelect) {
                    const kraVal = $(kraSelect).val();
                    if (kraVal && kraVal !== '__custom__') {
                        if (kraFinalInput) kraFinalInput.value = kraVal;
                    }
                }

                if (titleInput && titleInput.value.trim() !== '') {
                    hasValidRow = true;
                }
            });

            if (!hasValidRow) {
                e.preventDefault();
                confirmAction({
                    title: '{{ __('hrms.kra_kpi.title_required_title') }}',
                    message: '{{ __('hrms.kra_kpi.title_required_msg') }}',
                    variant: 'warning',
                    confirmText: '{{ __('hrms.common.ok') }}',
                    hideCancel: true
                });
                return;
            }

            let totalWeight = 0;
            rows.forEach(function(tr) {
                const wInput = tr.querySelector('input[name$="[weightage]"]');
                const val = parseFloat(wInput ? wInput.value : 0);
                if (!isNaN(val)) totalWeight += val;
            });
            totalWeight = Math.round(totalWeight * 100) / 100;

            if (totalWeight > 100) {
                e.preventDefault();
                confirmAction({
                    title: '{{ __('hrms.kra_kpi.goal_weightage_exceeded_dialog_title') }}',
                    message: '{{ __('hrms.kra_kpi.total_weight_exceeded_save_msg') }}'.replace(':total', totalWeight),
                    variant: 'danger',
                    confirmText: '{{ __('hrms.kra_kpi.understood_btn') }}',
                    hideCancel: true
                });
                return;
            }
        });
    });
</script>
@endpush

@push('styles')
<style>
    .select2-container--bootstrap-5 .select2-dropdown {
        min-width: 130px !important;
    }
    .select2-container--bootstrap-5 .select2-results__option {
        white-space: nowrap !important;
    }
</style>
@endpush
@endsection
