@extends('layouts.duralux')

@section('title', __('hrms.recruitment.pipeline_title', ['title' => $requisition->job_title]))
@section('page-title', __('hrms.recruitment.pipeline_page_title', ['title' => $requisition->job_title]))
@section('breadcrumb', __('hrms.recruitment.pipeline_breadcrumb'))

@php
    $authUser = auth()->user();
    $canCreateRecruitment = $authUser && ($authUser->hasHrPermission('hrms.recruitment.create') || $authUser->hasHrPermission('hr.settings.manage') || $authUser->hasHrPermission('hrms.recruitment.manage'));
    $canUpdateRecruitment = $authUser && ($authUser->hasHrPermission('hrms.recruitment.update') || $authUser->hasHrPermission('hr.settings.manage') || $authUser->hasHrPermission('hrms.recruitment.manage'));
    $canManageRecruitment = $authUser && ($authUser->hasHrPermission('hrms.recruitment.manage') || $authUser->hasHrPermission('hr.settings.manage'));

    $recruitmentTabs = [
        [
            'id' => 'tab-requisitions',
            'label' => __('hrms.recruitment.tab_requisitions'),
            'url' => route('hrms.recruitment.requisitions.index'),
            'active' => true,
            'icon' => 'feather-briefcase',
        ],
        [
            'id' => 'tab-candidates',
            'label' => __('hrms.recruitment.tab_candidates'),
            'url' => route('hrms.recruitment.candidates.index'),
            'active' => false,
            'icon' => 'feather-users',
        ],
    ];

    $stageNames = [
        'applied' => __('hrms.recruitment.stage_applied'),
        'screening' => __('hrms.recruitment.stage_screening'),
        'interview' => __('hrms.recruitment.stage_interview'),
        'offer_sent' => __('hrms.recruitment.stage_offer_sent'),
        'hired' => __('hrms.recruitment.stage_hired'),
        'rejected' => __('hrms.recruitment.stage_rejected'),
    ];
@endphp

@section('page-actions')
    @if($canCreateRecruitment)
        <div class="d-flex align-items-center gap-2">
            <x-ui.button variant="primary" icon="feather-user-plus" data-bs-toggle="modal" data-bs-target="#addCandidateModal">
                {{ __('hrms.recruitment.btn_add_candidate_to_job') }}
            </x-ui.button>
        </div>
    @endif
@endsection

@push('styles')
<style>
    .kanban-cards {
        padding: 12px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        min-height: 420px;
    }
    .kanban-card {
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 14px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .kanban-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 18px rgba(15,23,42,0.08);
    }
    .candidate-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background-color: #eff6ff;
        color: #2563eb;
        font-weight: 700;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    /* Dark mode support */
    html.app-skin-dark .kanban-card {
        background-color: #1e293b !important;
        border-color: #334155 !important;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3) !important;
    }
    html.app-skin-dark .kanban-card:hover {
        background-color: #243248 !important;
        border-color: #475569 !important;
    }
    html.app-skin-dark .candidate-avatar {
        background-color: #1e3a8a !important;
        color: #93c5fd !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">

    @if(session('success'))
        <x-ui.alert variant="success" dismissible class="mb-4">
            <i class="feather-check-circle me-2"></i>{{ session('success') }}
        </x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert variant="danger" dismissible class="mb-4">
            <i class="feather-alert-triangle me-2"></i>{{ session('error') }}
        </x-ui.alert>
    @endif

    <!-- ERP Single Panel Container -->
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">

        <!-- Panel Header with Sub Navigation Tabs -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-bottom pb-3 mb-4">
            <div class="flex-grow-1">
                <x-ui.horizontal-tabs id="recruitmentTabs" :tabs="$recruitmentTabs" />
            </div>

            <x-ui.button href="{{ route('hrms.recruitment.requisitions.index') }}" variant="light" size="sm" icon="feather-arrow-left" class="border">
                {{ __('hrms.recruitment.btn_back_to_requisitions') }}
            </x-ui.button>
        </div>

        <!-- Job Header Summary -->
        <div class="bg-light p-3 rounded-3 mb-4 border">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h4 class="fw-bold mb-1 text-dark">{{ $requisition->job_title }} <span class="badge bg-white text-secondary border ms-2 font-monospace fs-12">{{ $requisition->requisition_code }}</span></h4>
                    <div class="text-muted fs-13">
                        <span class="me-3"><i class="feather-folder me-1"></i>{{ $requisition->department->name ?? __('hrms.common.na') }}</span>
                        <span class="me-3"><i class="feather-award me-1"></i>{{ __('hrms.recruitment.req_exp_colon') }} <strong>{{ __('hrms.recruitment.yrs_range', ['min' => $requisition->min_experience_years, 'max' => $requisition->max_experience_years]) }}</strong></span>
                        <span class="me-3"><i class="feather-users me-1"></i>{{ __('hrms.recruitment.openings_colon') }} <strong>{{ __('hrms.recruitment.vacancies_count', ['count' => $requisition->vacancies]) }}</strong></span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <x-ui.status-badge :status="$requisition->status" :label="in_array($requisition->status, ['approved', 'published', 'open']) ? __('hrms.recruitment.status_open') : (in_array($requisition->status, ['closed']) ? __('hrms.recruitment.status_closed') : null)" size="lg" />
                    
                    @if($canUpdateRecruitment)
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light border dropdown-toggle text-secondary" 
                                    type="button" 
                                    data-bs-toggle="dropdown" 
                                    data-bs-boundary="viewport" 
                                    data-bs-popper-config='{"strategy":"fixed"}' 
                                    aria-expanded="false" 
                                    title="{{ __('hrms.recruitment.change_status_title') }}">
                                <i class="feather-settings me-1"></i> {{ __('hrms.common.status') }}
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 fs-12 py-1" style="min-width: 220px; z-index: 1065;">
                                <li>
                                    <form action="{{ route('hrms.recruitment.requisitions.status', $requisition->id) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="status" value="approved">
                                        <button type="submit" class="dropdown-item py-2 px-3 {{ $requisition->status === 'approved' ? 'active fw-bold' : '' }}">
                                            <i class="feather-check-circle text-success me-2 fs-13"></i> {{ __('hrms.recruitment.btn_mark_open') }}
                                        </button>
                                    </form>
                                </li>
                                <li>
                                    <form action="{{ route('hrms.recruitment.requisitions.status', $requisition->id) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="status" value="published">
                                        <button type="submit" class="dropdown-item py-2 px-3 {{ $requisition->status === 'published' ? 'active fw-bold' : '' }}">
                                            <i class="feather-globe text-primary me-2 fs-13"></i> {{ __('hrms.recruitment.btn_mark_published') }}
                                        </button>
                                    </form>
                                </li>
                                <li>
                                    <form action="{{ route('hrms.recruitment.requisitions.status', $requisition->id) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="status" value="closed">
                                        <button type="submit" class="dropdown-item py-2 px-3 {{ $requisition->status === 'closed' ? 'active fw-bold' : '' }}">
                                            <i class="feather-lock text-danger me-2 fs-13"></i> {{ __('hrms.recruitment.btn_mark_closed') }}
                                        </button>
                                    </form>
                                </li>
                                <li>
                                    <form action="{{ route('hrms.recruitment.requisitions.status', $requisition->id) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="status" value="cancelled">
                                        <button type="submit" class="dropdown-item py-2 px-3 {{ $requisition->status === 'cancelled' ? 'active fw-bold' : '' }}">
                                            <i class="feather-slash text-muted me-2 fs-13"></i> {{ __('hrms.recruitment.btn_mark_cancelled') }}
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @php
            $targetCandidateId = request('candidate_id');
            $targetApp = $targetCandidateId ? $applications->firstWhere('candidate_id', $targetCandidateId) : null;
            $activeStageKey = 'applied';
            if ($targetApp) {
                if (in_array($targetApp->current_stage, ['interview', 'interview_round_1', 'interview_round_2', 'interview_round_3', 'final_hr'])) {
                    $activeStageKey = 'interview';
                } else {
                    $activeStageKey = $targetApp->current_stage;
                }
            }

            $stageTabs = [];
            $stageIcons = [
                'applied' => 'feather-user-check',
                'screening' => 'feather-search',
                'interview' => 'feather-calendar',
                'offer_sent' => 'feather-file-text',
                'hired' => 'feather-check-circle',
                'rejected' => 'feather-x-circle'
            ];
            $isFirstStage = true;
            foreach($stages as $stageKey => $stageLabel) {
                if ($stageKey === 'interview') {
                    $count = $applications->filter(fn($a) => in_array($a->current_stage, ['interview', 'interview_round_1', 'interview_round_2', 'interview_round_3', 'final_hr']))->count();
                } else {
                    $count = $applications->where('current_stage', $stageKey)->count();
                }
                $isActive = $targetApp ? ($stageKey === $activeStageKey) : $isFirstStage;
                $displayLabel = $stageNames[$stageKey] ?? $stageLabel;
                $stageTabs[] = [
                    'id' => 'stage-content-' . $stageKey,
                    'label' => $displayLabel . ' (' . $count . ')',
                    'active' => $isActive,
                    'icon' => $stageIcons[$stageKey] ?? 'feather-disc'
                ];
                $isFirstStage = false;
            }
        @endphp

        <!-- Horizontal Stage Tabs Navigation -->
        <div class="mb-4">
            <x-ui.horizontal-tabs id="stagePipelineTabs" :tabs="$stageTabs" />
        </div>

        <!-- Tab Content Panes -->
        <div class="tab-content mb-4" id="stagePipelineTabContent">
            @foreach($stages as $stageKey => $stageLabel)
                @php
                    if ($stageKey === 'interview') {
                        $stageApps = $applications->filter(fn($a) => in_array($a->current_stage, ['interview', 'interview_round_1', 'interview_round_2', 'interview_round_3', 'final_hr']));
                    } else {
                        $stageApps = $applications->where('current_stage', $stageKey);
                    }
                    $isPaneActive = $targetApp ? ($stageKey === $activeStageKey) : $loop->first;
                @endphp
                <div class="tab-pane fade {{ $isPaneActive ? 'show active' : '' }}" id="stage-content-{{ $stageKey }}" role="tabpanel">
                    <div class="row g-3">
                        @forelse($stageApps as $app)
                            @php
                                $candidate = $app->candidate;
                                $latestInterview = $app->interviews->last();
                                $scorecard = $latestInterview?->scorecard;
                                $offer = $app->offer;
                                $initials = strtoupper(substr($candidate->first_name ?? 'C', 0, 1) . substr($candidate->last_name ?? '', 0, 1));
                                $isFocused = ($targetCandidateId && $candidate && $candidate->id == $targetCandidateId);
                            @endphp
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100 rounded-3 shadow-xs p-3 {{ $isFocused ? 'border-primary border-2 bg-light-primary' : 'border' }}" style="{{ $isFocused ? 'box-shadow: 0 0 16px rgba(13,110,253,0.2) !important;' : '' }}">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="candidate-avatar" style="width: 36px; height: 36px; font-size: 13px;">{{ $initials }}</div>
                                            <div>
                                                <h6 class="fw-bold mb-0 text-dark fs-14">{{ $candidate->full_name }}</h6>
                                                <small class="text-muted fs-11">{{ $candidate->current_designation ?? __('hrms.recruitment.applicant_fallback') }} @ {{ $candidate->current_company ?? __('hrms.common.na') }}</small>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="badge bg-light text-secondary border fs-11 font-monospace">{{ $candidate->candidate_code }}</span>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center gap-2 mb-3">
                                        <x-ui.badge variant="info" soft class="fs-11">
                                            <i class="feather-clock me-1"></i>{{ __('hrms.recruitment.yrs_exp_badge', ['count' => $candidate->total_experience_years]) }}
                                        </x-ui.badge>
                                        @if($candidate->resume_path)
                                            <x-ui.button 
                                                :href="route('hrms.recruitment.candidates.download-resume', $candidate)" 
                                                target="_blank" 
                                                variant="outline-primary" 
                                                size="sm" 
                                                icon="feather-file-text"
                                            >
                                                {{ __('hrms.recruitment.btn_view_cv') }}
                                            </x-ui.button>
                                        @endif
                                    </div>

                                    @if($offer)
                                        <div class="mb-3 p-2 rounded-2 border bg-light d-flex align-items-center justify-content-between shadow-xs">
                                            <div>
                                                <small class="text-muted d-block fs-10 text-uppercase fw-semibold">{{ __('hrms.recruitment.offer_letter_badge') }}</small>
                                                <strong class="text-dark fs-12">{{ $offer->offer_code }}</strong>
                                            </div>
                                            <div>
                                                @if($offer->status === 'accepted')
                                                    <span class="badge bg-soft-success text-success border border-success-subtle fs-11 px-2 py-1">
                                                        <i class="feather-check-circle me-1"></i>{{ __('hrms.recruitment.offer_accepted') }}
                                                    </span>
                                                @elseif($offer->status === 'declined')
                                                    <span class="badge bg-soft-danger text-danger border border-danger-subtle fs-11 px-2 py-1">
                                                        <i class="feather-x-circle me-1"></i>{{ __('hrms.recruitment.offer_declined') }}
                                                    </span>
                                                @elseif($offer->status === 'sent')
                                                    <span class="badge bg-soft-info text-info border border-info-subtle fs-11 px-2 py-1">
                                                        <i class="feather-mail me-1"></i>{{ __('hrms.recruitment.offer_sent') }}
                                                    </span>
                                                @else
                                                    <span class="badge bg-soft-warning text-warning border border-warning-subtle fs-11 px-2 py-1">
                                                        <i class="feather-file-text me-1"></i>{{ __('hrms.recruitment.offer_draft') }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    @endif

                                    @if($app->interviews->count() > 0)
                                        @php
                                            $roundWord = $app->interviews->count() == 1 ? __('hrms.recruitment.round') : __('hrms.recruitment.round') . (app()->getLocale() === 'en' ? 's' : '');
                                        @endphp
                                        <button type="button" class="btn btn-sm btn-light border w-100 mb-3 py-2 px-3 text-start d-flex align-items-center justify-content-between text-dark rounded-2 shadow-xs" data-bs-toggle="modal" data-bs-target="#interviewDetailsModal{{ $app->id }}">
                                            <span class="fw-semibold text-secondary fs-12"><i class="feather-calendar text-primary me-1"></i> {{ __('hrms.recruitment.interview_rounds_badge', ['count' => $app->interviews->count(), 'round' => $roundWord]) }}</span>
                                            @if($scorecard)
                                                <x-ui.badge variant="{{ $scorecard->recommendation === 'pass' ? 'success' : ($scorecard->recommendation === 'reject' ? 'danger' : 'warning') }}" soft>
                                                    {{ $scorecard->overall_rating }}/5 ⭐ {{ strtoupper($scorecard->recommendation) }}
                                                </x-ui.badge>
                                            @elseif($latestInterview && $latestInterview->status === 'scheduled')
                                                <x-ui.badge variant="warning" soft>
                                                    {{ __('hrms.recruitment.btn_scheduled') }} <i class="feather-eye ms-1"></i>
                                                </x-ui.badge>
                                            @else
                                                <x-ui.badge variant="secondary" soft>
                                                    {{ __('hrms.recruitment.btn_details') }} <i class="feather-chevron-right ms-1"></i>
                                                </x-ui.badge>
                                            @endif
                                        </button>
                                    @endif

                                    @if($canUpdateRecruitment || $canManageRecruitment)
                                        <div class="mt-auto pt-2 border-top d-flex align-items-center flex-nowrap gap-1 w-100 overflow-x-auto pb-1">
                                            @if($canUpdateRecruitment)
                                                @php
                                                    $stageOrderMap = [
                                                        'applied'    => 0,
                                                        'screening'  => 1,
                                                        'interview'  => 2,
                                                        'offer_sent' => 3,
                                                        'hired'      => 4,
                                                        'rejected'   => 99,
                                                    ];
                                                    $curStageNormalized = in_array($app->current_stage, ['interview', 'interview_round_1', 'interview_round_2', 'interview_round_3', 'final_hr']) ? 'interview' : $app->current_stage;
                                                    $curOrderIdx = $stageOrderMap[$curStageNormalized] ?? 0;
                                                @endphp

                                                <!-- Change Stage Dropdown (Left) -->
                                                <div class="dropdown flex-shrink-0 me-auto">
                                                    <button class="btn btn-sm btn-light text-secondary border dropdown-toggle fw-semibold fs-11 px-2 py-1" 
                                                            type="button" 
                                                            data-bs-toggle="dropdown" 
                                                            data-bs-boundary="viewport" 
                                                            title="{{ __('hrms.recruitment.title_move_stage') }}">
                                                        {{ __('hrms.recruitment.btn_move') }}
                                                    </button>
                                                    <ul class="dropdown-menu fs-12 shadow-lg border-0 py-1" style="min-width: 180px; z-index: 1065;">
                                                        @foreach($stages as $key => $lbl)
                                                            @php
                                                                $targetOrderIdx = $stageOrderMap[$key] ?? 0;
                                                                $isPreviousStage = ($curStageNormalized !== 'rejected' && $key !== 'rejected' && $targetOrderIdx < $curOrderIdx);
                                                                $isCurrentStage = ($key === $curStageNormalized);
                                                                $targetStageLabel = $stageNames[$key] ?? $lbl;
                                                            @endphp
                                                            <li>
                                                                @if($isPreviousStage)
                                                                    <div class="dropdown-item text-muted disabled opacity-50 py-1.5 px-3 fs-11 d-flex align-items-center justify-content-between" style="cursor: not-allowed; background-color: transparent;">
                                                                        <span><i class="feather-lock me-1.5 text-muted fs-10"></i>{{ $targetStageLabel }}</span>
                                                                        <small class="badge bg-light text-muted border-0 fs-10">{{ __('hrms.recruitment.stage_passed') }}</small>
                                                                    </div>
                                                                @elseif($isCurrentStage)
                                                                    <div class="dropdown-item active fw-bold py-1.5 px-3 fs-11 d-flex align-items-center justify-content-between">
                                                                        <span><i class="feather-check me-1.5 fs-10"></i>{{ $targetStageLabel }}</span>
                                                                        <small class="badge bg-primary-subtle text-primary border border-primary-subtle fs-10">{{ __('hrms.recruitment.stage_current') }}</small>
                                                                    </div>
                                                                @elseif($key === 'rejected')
                                                                    <form action="{{ route('hrms.recruitment.stage.update', $app->id) }}" method="POST">
                                                                        @csrf
                                                                        <input type="hidden" name="current_stage" value="rejected">
                                                                        <button type="submit" class="dropdown-item text-danger fw-semibold py-1.5 px-3 fs-11 d-flex align-items-center justify-content-between">
                                                                            <span><i class="feather-x-circle me-1.5 text-danger"></i>{{ __('hrms.recruitment.btn_reject_candidate') }}</span>
                                                                        </button>
                                                                    </form>
                                                                @else
                                                                    <form action="{{ route('hrms.recruitment.stage.update', $app->id) }}" method="POST">
                                                                        @csrf
                                                                        <input type="hidden" name="current_stage" value="{{ $key }}">
                                                                        <button type="submit" class="dropdown-item text-dark fw-medium py-1.5 px-3 fs-11 d-flex align-items-center justify-content-between">
                                                                            <span><i class="feather-arrow-right me-1.5 text-primary"></i>{{ __('hrms.recruitment.btn_advance_to_stage', ['stage' => $targetStageLabel]) }}</span>
                                                                        </button>
                                                                    </form>
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>

                                                <!-- Action Buttons Group (Right) -->
                                                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                                    @if(!in_array($stageKey, ['offer_sent', 'hired', 'rejected']))
                                                        <x-ui.button variant="outline-success" size="sm" class="fw-semibold fs-11 px-2 py-1 flex-shrink-0" data-bs-toggle="modal" data-bs-target="#scheduleInterviewModal{{ $app->id }}" icon="feather-calendar">
                                                            {{ __('hrms.recruitment.btn_plus_round') }}
                                                        </x-ui.button>
                                                    @endif

                                                    @if($latestInterview && $latestInterview->status === 'scheduled' && !in_array($stageKey, ['offer_sent', 'hired', 'rejected']))
                                                        <x-ui.button variant="outline-warning" size="sm" class="fw-semibold fs-11 px-2 py-1 flex-shrink-0" data-bs-toggle="modal" data-bs-target="#scorecardModal{{ $latestInterview->id }}" icon="feather-check-square">
                                                            {{ __('hrms.recruitment.btn_scorecard') }}
                                                        </x-ui.button>
                                                    @endif

                                                    @if(!in_array($stageKey, ['hired', 'rejected']) && ($app->interviews->count() > 0 || $offer || $stageKey === 'offer_sent'))
                                                        @if(!$offer)
                                                            <x-ui.button variant="outline-info" size="sm" class="fw-semibold fs-11 px-2 py-1 flex-shrink-0" data-bs-toggle="modal" data-bs-target="#offerModal{{ $app->id }}" icon="feather-file-plus">
                                                                {{ __('hrms.recruitment.btn_offer') }}
                                                            </x-ui.button>
                                                        @else
                                                            <x-ui.button variant="info" size="sm" class="text-white fw-semibold fs-11 px-2 py-1 flex-shrink-0" data-bs-toggle="modal" data-bs-target="#viewOfferModal{{ $offer->id }}" icon="feather-file-text">
                                                                {{ __('hrms.recruitment.btn_offer') }}
                                                            </x-ui.button>
                                                            <x-ui.button variant="primary" size="sm" class="fw-semibold fs-11 px-2 py-1 flex-shrink-0" data-bs-toggle="modal" data-bs-target="#sendOfferEmailModal{{ $offer->id }}" icon="feather-send">
                                                                {{ __('hrms.recruitment.btn_email') }}
                                                            </x-ui.button>
                                                            @if(!in_array($offer->status, ['accepted', 'declined']))
                                                                <x-ui.button variant="outline-success" size="sm" class="fw-semibold fs-11 px-2 py-1 flex-shrink-0" data-bs-toggle="modal" data-bs-target="#offerStatusModal{{ $offer->id }}" title="Record candidate acceptance or decline" icon="feather-check-circle">
                                                                    {{ __('hrms.recruitment.btn_decision') }}
                                                                </x-ui.button>
                                                            @endif
                                                        @endif
                                                    @endif

                                                    @if($canManageRecruitment && $offer && $stageKey !== 'hired' && $stageKey !== 'rejected')
                                                        @if($offer->status === 'accepted')
                                                            <x-ui.button type="button" variant="success" size="sm" class="text-white fw-bold fs-11 px-2 py-1 flex-shrink-0" data-bs-toggle="modal" data-bs-target="#confirmConvertModal{{ $offer->id }}">
                                                                {{ __('hrms.recruitment.btn_convert') }}
                                                            </x-ui.button>
                                                        @else
                                                            <x-ui.button type="button" variant="secondary" size="sm" class="text-white fw-semibold fs-11 px-2 py-1 flex-shrink-0 opacity-75" title="{{ __('hrms.recruitment.tooltip_offer_accepted_first') }}" data-bs-toggle="modal" data-bs-target="#confirmConvertModal{{ $offer->id }}">
                                                                {{ __('hrms.recruitment.btn_convert') }}
                                                            </x-ui.button>
                                                        @endif
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endif

                                    @if($stageKey === 'hired')
                                        <x-ui.badge variant="success" soft class="p-2 w-100 text-center d-block"><i class="feather-check-circle me-1"></i>{{ __('hrms.recruitment.badge_hired_employee') }}</x-ui.badge>
                                    @endif

                                    @if($stageKey === 'rejected')
                                        <x-ui.badge variant="danger" soft class="p-2 w-100 text-center d-block"><i class="feather-x-circle me-1"></i>{{ __('hrms.recruitment.badge_rejected_candidate') }}</x-ui.badge>
                                    @endif
                                </div>
                            </div>

                            <!-- Modal: Detailed View of All Interviews & Scorecards for this Candidate -->
                            <x-ui.modal id="interviewDetailsModal{{ $app->id }}" :title="'<i class=\'feather-calendar text-primary me-2\'></i>' . __('hrms.recruitment.modal_interview_details_title')" size="lg" :centered="true" :showFooter="false">
                                <div class="text-start">
                                    <div class="mb-3">
                                        <small class="text-muted">{{ $candidate->full_name }} — {{ $requisition->job_title }} ({{ $candidate->candidate_code }})</small>
                                    </div>

                                    <!-- Candidate Summary Strip -->
                                    <div class="bg-light p-3 rounded-3 mb-4 border d-flex flex-wrap align-items-center justify-content-between gap-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="candidate-avatar" style="width: 42px; height: 42px; font-size: 16px;">{{ $initials }}</div>
                                            <div>
                                                <h6 class="fw-bold mb-0 text-dark">{{ $candidate->full_name }}</h6>
                                                <small class="text-muted">{{ $candidate->current_designation ?? __('hrms.common.na') }} @ {{ $candidate->current_company ?? __('hrms.common.na') }}</small>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-white text-secondary border px-3 py-2"><i class="feather-clock me-1"></i>{{ __('hrms.recruitment.yrs_exp_badge', ['count' => $candidate->total_experience_years]) }}</span>
                                            @if($candidate->email)
                                                <x-ui.button :href="'mailto:' . $candidate->email" variant="light" size="sm" class="border py-1" icon="feather-mail">{{ __('hrms.recruitment.btn_email') }}</x-ui.button>
                                            @endif
                                            @if($candidate->resume_path)
                                                <x-ui.button 
                                                    :href="route('hrms.recruitment.candidates.download-resume', $candidate)" 
                                                    target="_blank" 
                                                    variant="outline-primary" 
                                                    size="sm" 
                                                    icon="feather-file-text"
                                                >
                                                    {{ __('hrms.recruitment.btn_view_cv') }}
                                                </x-ui.button>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Rounds Timeline -->
                                    <h6 class="fw-bold text-dark mb-3"><i class="feather-list text-primary me-1"></i> {{ __('hrms.recruitment.conducted_scheduled_rounds', ['count' => $app->interviews->count()]) }}</h6>
                                    
                                    @forelse($app->interviews as $itw)
                                        @php $sc = $itw->scorecard; @endphp
                                        <div class="card border rounded-3 mb-3 shadow-xs">
                                            <div class="card-header bg-white py-2 px-3 d-flex align-items-center justify-content-between border-bottom">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="badge bg-primary rounded-circle">R{{ $itw->round_number }}</span>
                                                    <span class="fw-bold text-dark fs-14">{{ $itw->round_name }}</span>
                                                </div>
                                                <div>
                                                    @if($sc)
                                                        <span class="badge bg-{{ $sc->recommendation === 'pass' ? 'success' : ($sc->recommendation === 'reject' ? 'danger' : 'warning') }} fs-11 px-2 py-1">
                                                            <i class="feather-check-circle me-1"></i>{{ strtoupper($sc->recommendation) }} (Overall: {{ $sc->overall_rating }}/5 ⭐)
                                                        </span>
                                                    @elseif($itw->status === 'scheduled')
                                                        <span class="badge bg-warning text-dark fs-11 px-2 py-1"><i class="feather-clock me-1"></i>{{ __('hrms.recruitment.btn_scheduled') }}</span>
                                                    @else
                                                        <span class="badge bg-secondary fs-11 px-2 py-1">{{ __('hrms.common.pending') }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="card-body p-3">
                                                <div class="row g-3 mb-2 fs-13">
                                                    <div class="col-md-4">
                                                        <div class="text-muted fs-11">{{ __('hrms.recruitment.date_time_label') }}</div>
                                                        <div class="fw-semibold text-dark"><i class="feather-clock text-warning me-1"></i>{{ $itw->scheduled_at ? $itw->scheduled_at->format('M d, Y - h:i A') : __('hrms.common.tbd') }}</div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="text-muted fs-11">{{ __('hrms.recruitment.interviewer_label') }}</div>
                                                        <div class="fw-semibold text-dark"><i class="feather-user text-info me-1"></i>{{ $itw->interviewer->full_name ?? __('hrms.recruitment.assigned_team_fallback') }}</div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="text-muted fs-11">{{ __('hrms.recruitment.meeting_link_label') }}</div>
                                                        @if($itw->meeting_link)
                                                            <x-ui.button :href="$itw->meeting_link" target="_blank" variant="primary" size="sm" class="mt-1 fw-bold" icon="feather-video">
                                                                {{ __('hrms.recruitment.btn_join_video') }}
                                                            </x-ui.button>
                                                        @else
                                                            <span class="text-muted fs-12">{{ __('hrms.recruitment.in_person_no_link') }}</span>
                                                        @endif
                                                    </div>
                                                </div>

                                                @if($sc)
                                                    <!-- Detailed Scorecard Breakdown -->
                                                    <div class="mt-3 pt-3 border-top bg-light p-3 rounded-2">
                                                        <h6 class="fw-bold text-dark fs-12 mb-2"><i class="feather-award text-success me-1"></i> {{ __('hrms.recruitment.scorecard_breakdown') }}</h6>
                                                        <div class="row g-2 text-center mb-2">
                                                            <div class="col-3">
                                                                <div class="bg-white p-2 rounded border">
                                                                    <small class="text-muted d-block fs-10">{{ __('hrms.recruitment.score_technical') }}</small>
                                                                    <strong class="text-primary fs-13">{{ $sc->technical_rating }}/5 ⭐</strong>
                                                                </div>
                                                            </div>
                                                            <div class="col-3">
                                                                <div class="bg-white p-2 rounded border">
                                                                    <small class="text-muted d-block fs-10">{{ __('hrms.recruitment.score_communication') }}</small>
                                                                    <strong class="text-primary fs-13">{{ $sc->communication_rating }}/5 ⭐</strong>
                                                                </div>
                                                            </div>
                                                            <div class="col-3">
                                                                <div class="bg-white p-2 rounded border">
                                                                    <small class="text-muted d-block fs-10">{{ __('hrms.recruitment.score_culture_fit') }}</small>
                                                                    <strong class="text-primary fs-13">{{ $sc->culture_fit_rating }}/5 ⭐</strong>
                                                                </div>
                                                            </div>
                                                            <div class="col-3">
                                                                <div class="bg-white p-2 rounded border">
                                                                    <small class="text-muted d-block fs-10">{{ __('hrms.recruitment.score_overall') }}</small>
                                                                    <strong class="text-success fs-13">{{ $sc->overall_rating }}/5 ⭐</strong>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        @if($sc->feedback_notes)
                                                            <div class="mt-2">
                                                                <small class="text-muted fw-bold d-block mb-1">{{ __('hrms.recruitment.feedback_notes_label') }}</small>
                                                                <div class="bg-white p-2 rounded border fs-12 text-secondary fst-italic">
                                                                    "{{ $sc->feedback_notes }}"
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-center py-4 text-muted bg-light rounded border">
                                            <i class="feather-calendar fs-24 d-block mb-2 text-secondary"></i>
                                            {{ __('hrms.recruitment.no_rounds_scheduled') }}
                                        </div>
                                    @endforelse
                                </div>
                                <div class="d-flex justify-content-end pt-3 border-top mt-3">
                                    <x-ui.button variant="secondary" size="sm" class="px-4" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</x-ui.button>
                                </div>
                            </x-ui.modal>

                            <!-- Modal: Schedule Interview for Application -->
                            <x-ui.modal id="scheduleInterviewModal{{ $app->id }}" :title="'<i class=\'feather-calendar text-primary me-2\'></i>' . __('hrms.recruitment.modal_schedule_interview_title', ['name' => $candidate->full_name])" size="md" :centered="true" :showFooter="false">
                                <form action="{{ route('hrms.recruitment.interview.schedule', $app->id) }}" method="POST">
                                    @csrf
                                    <div class="text-start">
                                        <div class="mb-3">
                                            <x-ui.odoo-form-ui type="input" inputType="number" :label="__('hrms.recruitment.field_round_number')" name="round_number" value="{{ $app->interviews->count() + 1 }}" :required="true" />
                                        </div>
                                        <div class="mb-3">
                                            <x-ui.odoo-form-ui type="input" :label="__('hrms.recruitment.field_round_name')" name="round_name" :placeholder="__('hrms.recruitment.placeholder_round_name')" :required="true" />
                                        </div>
                                        <div class="mb-3">
                                            <x-ui.odoo-form-ui type="input" inputType="datetime-local" :label="__('hrms.recruitment.field_schedule_datetime')" name="scheduled_at" :required="true" />
                                        </div>
                                        <div class="mb-3">
                                            <x-ui.odoo-form-ui type="select" :label="__('hrms.recruitment.field_interviewer')" name="interviewer_employee_id">
                                                <option value="">{{ __('hrms.recruitment.select_employee') }}</option>
                                                @foreach($employees as $emp)
                                                    <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_code }})</option>
                                                @endforeach
                                            </x-ui.odoo-form-ui>
                                        </div>
                                        <div class="mb-3">
                                            <x-ui.odoo-form-ui type="input" :label="__('hrms.recruitment.field_meeting_link')" name="meeting_link" :placeholder="__('hrms.recruitment.placeholder_meeting_link')" />
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-end gap-2 pt-3 mt-3 border-top">
                                        <x-ui.button variant="light" size="sm" class="border px-4" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
                                        <x-ui.button type="submit" variant="success" size="sm" class="px-4 fw-bold" icon="feather-calendar">{{ __('hrms.recruitment.btn_schedule_interview') }}</x-ui.button>
                                    </div>
                                </form>
                            </x-ui.modal>

                            <!-- Modal: Fill Scorecard -->
                            @if($latestInterview)
                                <x-ui.modal id="scorecardModal{{ $latestInterview->id }}" :title="'<i class=\'feather-check-square text-primary me-2\'></i>' . __('hrms.recruitment.modal_scorecard_title', ['round' => $latestInterview->round_name])" size="md" :centered="true" :showFooter="false">
                                    <form action="{{ route('hrms.recruitment.scorecard.submit', $latestInterview->id) }}" method="POST">
                                        @csrf
                                        <div class="text-start">
                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <x-ui.odoo-form-ui type="select" :label="__('hrms.recruitment.field_technical_rating')" name="technical_rating" :required="true">
                                                        <option value="5">{{ __('hrms.recruitment.rating_5_excellent') }}</option>
                                                        <option value="4" selected>{{ __('hrms.recruitment.rating_4_good') }}</option>
                                                        <option value="3">{{ __('hrms.recruitment.rating_3_average') }}</option>
                                                        <option value="2">{{ __('hrms.recruitment.rating_2_poor') }}</option>
                                                        <option value="1">{{ __('hrms.recruitment.rating_1_very_poor') }}</option>
                                                    </x-ui.odoo-form-ui>
                                                </div>
                                                <div class="col-12">
                                                    <x-ui.odoo-form-ui type="select" :label="__('hrms.recruitment.field_comm_rating')" name="communication_rating" :required="true">
                                                        <option value="5">{{ __('hrms.recruitment.rating_5_excellent') }}</option>
                                                        <option value="4" selected>{{ __('hrms.recruitment.rating_4_good') }}</option>
                                                        <option value="3">{{ __('hrms.recruitment.rating_3_average') }}</option>
                                                        <option value="2">{{ __('hrms.recruitment.rating_2_poor') }}</option>
                                                        <option value="1">{{ __('hrms.recruitment.rating_1_very_poor') }}</option>
                                                    </x-ui.odoo-form-ui>
                                                </div>
                                                <div class="col-12">
                                                    <x-ui.odoo-form-ui type="select" :label="__('hrms.recruitment.field_culture_fit_rating')" name="culture_fit_rating" :required="true">
                                                        <option value="5">{{ __('hrms.recruitment.rating_5_excellent') }}</option>
                                                        <option value="4" selected>{{ __('hrms.recruitment.rating_4_good') }}</option>
                                                        <option value="3">{{ __('hrms.recruitment.rating_3_average') }}</option>
                                                        <option value="2">{{ __('hrms.recruitment.rating_2_poor') }}</option>
                                                        <option value="1">{{ __('hrms.recruitment.rating_1_very_poor') }}</option>
                                                    </x-ui.odoo-form-ui>
                                                </div>
                                                <div class="col-12">
                                                    <x-ui.odoo-form-ui type="select" :label="__('hrms.recruitment.field_overall_rating')" name="overall_rating" :required="true">
                                                        <option value="5">{{ __('hrms.recruitment.rating_5_strong_hire') }}</option>
                                                        <option value="4" selected>{{ __('hrms.recruitment.rating_4_hire') }}</option>
                                                        <option value="3">{{ __('hrms.recruitment.rating_3_neutral') }}</option>
                                                        <option value="2">{{ __('hrms.recruitment.rating_2_reject') }}</option>
                                                        <option value="1">{{ __('hrms.recruitment.rating_1_strong_reject') }}</option>
                                                    </x-ui.odoo-form-ui>
                                                </div>
                                                <div class="col-md-12">
                                                    <x-ui.odoo-form-ui type="select" :label="__('hrms.recruitment.field_recommendation')" name="recommendation" :required="true">
                                                        <option value="pass">{{ __('hrms.recruitment.rec_pass') }}</option>
                                                        <option value="hold">{{ __('hrms.recruitment.rec_hold') }}</option>
                                                        <option value="reject">{{ __('hrms.recruitment.rec_reject') }}</option>
                                                    </x-ui.odoo-form-ui>
                                                </div>
                                                <div class="col-md-12">
                                                    <x-ui.odoo-form-ui type="textarea" :label="__('hrms.recruitment.field_feedback_notes')" name="feedback_notes" rows="3" :placeholder="__('hrms.recruitment.placeholder_feedback_notes')" />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-end gap-2 pt-3 mt-3 border-top">
                                            <x-ui.button variant="light" size="sm" class="border px-4" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
                                            <x-ui.button type="submit" variant="primary" size="sm" class="px-4 fw-bold" icon="feather-check">{{ __('hrms.recruitment.btn_submit_scorecard') }}</x-ui.button>
                                        </div>
                                    </form>
                                </x-ui.modal>
                            @endif

                            <!-- Modal: Generate Job Offer -->
                            <x-ui.modal id="offerModal{{ $app->id }}" :title="'<i class=\'feather-file-plus text-info me-2\'></i>' . __('hrms.recruitment.modal_generate_offer_title', ['name' => $candidate->full_name])" size="lg" :centered="true" :showFooter="false">
                                <form action="{{ route('hrms.recruitment.offer.create', $app->id) }}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    <div class="text-start">
                                        <div class="mb-3">
                                            <x-ui.odoo-form-ui type="select" :label="__('hrms.recruitment.field_doc_template')" name="document_template_id" class="offer-tmpl-select" onchange="handleOfferTmplChange(this, '{{ $app->id }}')">
                                                <option value="" data-requires-signature="0" data-has-hr-sig="0">{{ __('hrms.recruitment.option_standard_offer_letter') }}</option>
                                                @foreach($templates as $tmpl)
                                                    @php
                                                        $hasSigTag = (str_contains(strtolower($tmpl->body_content ?? ''), 'hr_signature') || str_contains(strtolower($tmpl->body_content ?? ''), 'hr signature') || $tmpl->requires_signature);
                                                    @endphp
                                                    <option value="{{ $tmpl->id }}" data-requires-signature="{{ $tmpl->requires_signature ? '1' : '0' }}" data-has-hr-sig="{{ $hasSigTag ? '1' : '0' }}">{{ $tmpl->name }} ({{ $tmpl->code }})</option>
                                                @endforeach
                                            </x-ui.odoo-form-ui>
                                            <small class="text-muted fs-11 ms-1">{{ __('hrms.recruitment.helper_choose_template') }}</small>
                                        </div>
                                        <div class="mb-3">
                                            <x-ui.odoo-form-ui type="select" :label="__('hrms.recruitment.field_offered_role')" name="offered_designation_id" :required="true">
                                                @foreach($designations as $desig)
                                                    <option value="{{ $desig->id }}" {{ $desig->id == $requisition->designation_id ? 'selected' : '' }}>{{ $desig->name }}</option>
                                                @endforeach
                                            </x-ui.odoo-form-ui>
                                        </div>
                                        <div class="mb-3">
                                            <x-ui.odoo-form-ui type="select" :label="__('hrms.common.department')" name="offered_department_id" :required="true">
                                                @foreach($departments as $dept)
                                                    <option value="{{ $dept->id }}" {{ $dept->id == $requisition->department_id ? 'selected' : '' }}>{{ $dept->name }}</option>
                                                @endforeach
                                            </x-ui.odoo-form-ui>
                                        </div>
                                        <div class="mb-3">
                                            <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" min="0" :label="__('hrms.recruitment.field_annual_ctc')" name="offered_annual_ctc" :placeholder="__('hrms.recruitment.placeholder_annual_ctc')" :required="true" />
                                        </div>
                                        <div class="mb-3">
                                            <x-ui.odoo-form-ui type="input" inputType="date" :label="__('hrms.recruitment.field_joining_date')" name="joining_date" value="{{ optional($requisition->target_joining_date)->format('Y-m-d') }}" :required="true" />
                                        </div>
                                        <div class="mb-3">
                                            <x-ui.odoo-form-ui type="textarea" :label="__('hrms.recruitment.field_offer_terms')" name="offer_letter_notes" rows="2" :placeholder="__('hrms.recruitment.placeholder_offer_terms')" />
                                        </div>

                                        <!-- HR DIGITAL SIGNATURE SECTION (SHOWN ONLY WHEN HR SIGNATURE IS REQUIRED BY TEMPLATE) -->
                                        <div id="offer_hr_sig_section_{{ $app->id }}" class="mt-3 pt-3 border-top d-none">
                                            <h6 class="fw-bold text-dark fs-13 mb-2"><i class="feather-edit-3 text-warning me-1"></i> {{ __('hrms.recruitment.section_hr_signature') }}</h6>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <x-ui.odoo-form-ui type="input" :label="__('hrms.recruitment.field_hr_name')" name="hr_name" value="{{ auth()->user()?->name }}" placeholder="e.g. {{ auth()->user()?->name }}" />
                                                </div>
                                                <div class="col-md-6">
                                                    <x-ui.odoo-form-ui type="input" :label="__('hrms.recruitment.field_hr_designation')" name="hr_designation" value="HR Manager" placeholder="e.g. HR Manager / Director" />
                                                </div>
                                                <div class="col-12">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <label class="form-label fw-bold fs-12 text-dark mb-0">{{ __('hrms.recruitment.field_hr_sig_image') }}</label>
                                                        <ul class="nav nav-pills bg-light p-1 rounded-pill border gap-1" role="tablist">
                                                            <li class="nav-item">
                                                                <button type="button" id="btn_offer_hr_sig_draw_{{ $app->id }}" class="nav-link btn-xs py-1 px-3 fs-11 fw-bold rounded-pill active border-0 btn-primary" style="background-color: var(--bs-primary) !important; color: #ffffff !important;" onclick="switchOfferHrSigMode('{{ $app->id }}', 'draw')">
                                                                    <i class="feather-edit-2 me-1"></i> {{ __('hrms.recruitment.tab_draw_sig') }}
                                                                </button>
                                                            </li>
                                                            <li class="nav-item">
                                                                <button type="button" id="btn_offer_hr_sig_upload_{{ $app->id }}" class="nav-link btn-xs py-1 px-3 fs-11 fw-bold text-secondary rounded-pill border-0" onclick="switchOfferHrSigMode('{{ $app->id }}', 'upload')">
                                                                    <i class="feather-upload-cloud me-1"></i> {{ __('hrms.recruitment.tab_upload_sig') }}
                                                                </button>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                    <input type="hidden" name="hr_signature_data" id="offer_hr_sig_input_data_{{ $app->id }}">

                                                    <!-- DRAW HR SIGNATURE TAB -->
                                                    <div id="offer_hr_sig_draw_container_{{ $app->id }}">
                                                        <div class="d-flex justify-content-end mb-1">
                                                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="clearOfferHrSigCanvas('{{ $app->id }}')">
                                                                <i class="feather-rotate-ccw me-1"></i>{{ __('hrms.recruitment.btn_clear_canvas') }}
                                                            </button>
                                                        </div>
                                                        <div class="p-1 text-center position-relative shadow-sm" style="border: 2px dashed #94a3b8 !important; background-color: #f8fafc; border-radius: 8px;">
                                                            <canvas id="offerHrSignatureCanvas_{{ $app->id }}" width="400" height="100" style="touch-action: none; cursor: crosshair; background: #ffffff; width: 100%; height: 100px; border-radius: 6px;"></canvas>
                                                        </div>
                                                        <small class="text-muted fs-11 mt-1 d-block"><i class="feather-info me-1"></i> {{ __('hrms.recruitment.draw_sig_hint') }}</small>
                                                    </div>

                                                    <!-- UPLOAD HR SIGNATURE IMAGE TAB -->
                                                    <div id="offer_hr_sig_upload_container_{{ $app->id }}" class="d-none">
                                                        <div class="position-relative w-100">
                                                            <input type="file" name="hr_signature_file" accept="image/png, image/jpeg, image/jpg, image/webp" class="position-absolute opacity-0 w-100 h-100" style="left:0; top:0; cursor:pointer; z-index:5;" onchange="handleOfferHrSigUpload(this, '{{ $app->id }}')">
                                                            <div class="form-control d-flex flex-column align-items-center justify-content-center gap-1.5 p-3 text-center" style="border: 2px dashed rgba(var(--bs-primary-rgb, 59, 130, 246), 0.3); background-color: rgba(var(--bs-primary-rgb, 59, 130, 246), 0.02); border-radius: 8px; min-height: 110px;">
                                                                <div id="offer_hr_sig_upload_preview_box_{{ $app->id }}" class="d-flex flex-column align-items-center justify-content-center">
                                                                    <i class="feather-upload-cloud mb-1" style="font-size: 24px; color: var(--bs-primary);"></i>
                                                                    <span class="fw-bold text-dark fs-12 file-name-label">{{ __('hrms.recruitment.upload_sig_prompt') }}</span>
                                                                    <small class="text-muted fs-10 mt-0.5">{{ __('hrms.recruitment.upload_sig_hint') }}</small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-end gap-2 pt-3 mt-3 border-top">
                                        <x-ui.button variant="light" size="sm" class="border px-4" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
                                        <x-ui.button type="submit" variant="info" size="sm" class="text-white px-4 fw-bold" icon="feather-file-text">{{ __('hrms.recruitment.btn_generate_offer') }}</x-ui.button>
                                    </div>
                                </form>
                            </x-ui.modal>

                            <!-- Modal: View Rendered Job Offer Letter -->
                            @if($offer)
                                <x-ui.modal id="viewOfferModal{{ $offer->id }}" :title="'<i class=\'feather-file-text text-info me-2\'></i>' . __('hrms.recruitment.modal_view_offer_title', ['code' => $offer->offer_code])" size="lg" :centered="true" :showFooter="false">
                                    <div class="text-start">
                                        <div class="mb-3">
                                            <small class="text-muted">{{ __('hrms.recruitment.issued_to_on', ['name' => $candidate->full_name, 'date' => optional($offer->created_at)->format('d M, Y')]) }}</small>
                                        </div>

                                        <!-- Key Offer Summary Pill Row -->
                                        <div class="row g-3 mb-4 bg-light p-3 rounded-3 border">
                                            <div class="col-md-3 col-6">
                                                <small class="text-muted d-block fs-11">{{ __('hrms.recruitment.candidate_name') }}</small>
                                                <strong class="text-dark fs-13">{{ $candidate->full_name }}</strong>
                                            </div>
                                            <div class="col-md-3 col-6">
                                                <small class="text-muted d-block fs-11">{{ __('hrms.recruitment.offered_role') }}</small>
                                                <strong class="text-dark fs-13">{{ $offer->designation->name ?? $requisition->designation->name ?? __('hrms.common.na') }}</strong>
                                            </div>
                                            <div class="col-md-3 col-6">
                                                <small class="text-muted d-block fs-11">{{ __('hrms.recruitment.annual_ctc') }}</small>
                                                <strong class="text-success fs-13">${{ number_format($offer->offered_annual_ctc, 2) }}</strong>
                                            </div>
                                            <div class="col-md-3 col-6">
                                                <small class="text-muted d-block fs-11">{{ __('hrms.recruitment.joining_date') }}</small>
                                                <strong class="text-primary fs-13">{{ optional($offer->joining_date)->format('d M, Y') ?? __('hrms.common.tbd') }}</strong>
                                            </div>
                                        </div>

                                        @if($offer->offer_letter_content)
                                            <div class="border rounded-3 p-4 bg-white shadow-xs text-dark fs-13 lh-base position-relative" style="white-space: pre-wrap;">
                                                {!! preg_replace('/\{([^{}\n]*)\}/', '$1', $offer->offer_letter_content) !!}
                                            </div>
                                        @else
                                            <div class="border rounded-3 p-4 bg-white shadow-xs text-dark fs-13 lh-base">
                                                <h5 class="fw-bold text-dark mb-3">{{ __('hrms.recruitment.job_offer_letter_heading') }}</h5>
                                                <p>{{ __('hrms.recruitment.dear_candidate', ['name' => $candidate->full_name]) }}</p>
                                                <p>{{ __('hrms.recruitment.offer_welcome_body', ['designation' => $offer->designation->name ?? $requisition->designation->name, 'department' => $offer->department->name ?? $requisition->department->name]) }}</p>
                                                <p><strong>{{ __('hrms.recruitment.offer_details_heading') }}</strong></p>
                                                <ul>
                                                    <li><strong>{{ __('hrms.recruitment.offer_ref_label') }}</strong> {{ $offer->offer_code }}</li>
                                                    <li><strong>{{ __('hrms.recruitment.offer_ctc_label') }}</strong> {{ __('hrms.recruitment.offer_ctc_per_annum', ['amount' => '$' . number_format($offer->offered_annual_ctc, 2)]) }}</li>
                                                    <li><strong>{{ __('hrms.recruitment.offer_joining_label') }}</strong> {{ optional($offer->joining_date)->format('d M, Y') }}</li>
                                                </ul>
                                                @if($offer->offer_letter_notes)
                                                    <p><strong>{{ __('hrms.recruitment.special_terms_label') }}</strong> {{ $offer->offer_letter_notes }}</p>
                                                @endif
                                                <p class="mt-4">{{ __('hrms.recruitment.offer_welcome_closing') }}</p>
                                                <p class="mt-3 mb-0">{{ __('hrms.recruitment.sincerely') }}<br><strong>{{ __('hrms.recruitment.hr_talent_team') }}</strong></p>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between pt-3 mt-3 border-top">
                                        <div class="d-flex align-items-center gap-2">
                                            <x-ui.button :href="route('hrms.recruitment.offer.download', $offer->id)" target="_blank" variant="primary" size="sm" class="px-3 fw-bold" icon="feather-download">
                                                {{ __('hrms.recruitment.btn_download_pdf') }}
                                            </x-ui.button>
                                            <x-ui.button variant="outline-secondary" size="sm" class="px-3" onclick="window.print()" icon="feather-printer">
                                                {{ __('hrms.recruitment.btn_print') }}
                                            </x-ui.button>
                                            @if(!in_array($offer->status, ['accepted', 'declined']))
                                                <x-ui.button variant="success" size="sm" class="px-3 fw-bold" data-bs-dismiss="modal" data-bs-toggle="modal" :data-bs-target="'#offerStatusModal' . $offer->id" icon="feather-check-circle">
                                                    {{ __('hrms.recruitment.btn_record_decision') }}
                                                </x-ui.button>
                                            @endif
                                        </div>
                                        <x-ui.button variant="secondary" size="sm" class="px-4" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</x-ui.button>
                                    </div>
                                </x-ui.modal>

                                <!-- Modal: Candidate Offer Decision (Accept / Decline) -->
                                <x-ui.modal id="offerStatusModal{{ $offer->id }}" :title="'<i class=\'feather-check-circle text-primary me-2\'></i>' . __('hrms.recruitment.modal_offer_decision_title')" size="md" :centered="true" :showFooter="false">
                                    <form action="{{ route('hrms.recruitment.offer.status', $offer->id) }}" method="POST">
                                        @csrf
                                        <div class="text-start py-2">
                                            <div class="p-3 bg-light border rounded-3 mb-3 fs-12">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span class="text-muted">{{ __('hrms.recruitment.offer_code_colon') }}</span>
                                                    <strong class="text-dark">{{ $offer->offer_code }}</strong>
                                                </div>
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span class="text-muted">{{ __('hrms.recruitment.candidate_colon') }}</span>
                                                    <strong class="text-dark">{{ $candidate->full_name }}</strong>
                                                </div>
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span class="text-muted">{{ __('hrms.recruitment.offered_ctc_colon') }}</span>
                                                    <strong class="text-success">${{ number_format($offer->offered_annual_ctc, 2) }}</strong>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <span class="text-muted">{{ __('hrms.recruitment.current_status_colon') }}</span>
                                                    <span class="badge bg-secondary">{{ strtoupper($offer->status) }}</span>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold text-dark fs-12">{{ __('hrms.recruitment.field_candidate_decision') }} <span class="text-danger">*</span></label>
                                                <div class="d-flex gap-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="status" id="status_accept_{{ $offer->id }}" value="accepted" checked>
                                                        <label class="form-check-label fw-semibold text-success fs-13" for="status_accept_{{ $offer->id }}">
                                                            <i class="feather-check-circle me-1"></i>{{ __('hrms.recruitment.decision_accepted') }}
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="status" id="status_decline_{{ $offer->id }}" value="declined">
                                                        <label class="form-check-label fw-semibold text-danger fs-13" for="status_decline_{{ $offer->id }}">
                                                            <i class="feather-x-circle me-1"></i>{{ __('hrms.recruitment.decision_declined') }}
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <x-ui.odoo-form-ui type="textarea" :label="__('hrms.recruitment.field_decision_notes')" name="reason" rows="2" :placeholder="__('hrms.recruitment.placeholder_decision_notes')" />
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
                                            <x-ui.button variant="light" size="sm" class="border px-4" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
                                            <x-ui.button type="submit" variant="primary" size="sm" class="px-4 fw-bold" icon="feather-check">{{ __('hrms.recruitment.btn_save_decision') }}</x-ui.button>
                                        </div>
                                    </form>
                                </x-ui.modal>

                                <!-- Modal: Send Email Confirmation for Job Offer -->
                                <x-ui.modal id="sendOfferEmailModal{{ $offer->id }}" :title="'<i class=\'feather-send text-primary me-2\'></i>' . __('hrms.recruitment.modal_send_email_title')" size="lg" :centered="true" :showFooter="false">
                                    <form action="{{ route('hrms.recruitment.offer.send-email', $offer->id) }}" method="POST">
                                        @csrf
                                        @php
                                            $availableSmtps = \App\Models\EmailConfiguration::where('is_active', true)->orderByDesc('is_default')->get();
                                        @endphp

                                        @if($availableSmtps->isNotEmpty())
                                            <div class="mb-3 text-start">
                                                <x-ui.modal-form-ui type="select" :label="__('hrms.recruitment.field_from_sender')" name="account_id">
                                                    @foreach($availableSmtps as $s)
                                                        <option value="{{ $s->id }}" {{ $s->is_default ? 'selected' : '' }}>
                                                            {{ $s->name }} ({{ $s->email_address }})
                                                        </option>
                                                    @endforeach
                                                </x-ui.modal-form-ui>
                                            </div>
                                        @endif

                                        <div class="row g-3 mb-3 text-start">
                                            <div class="col-md-6">
                                                <x-ui.modal-form-ui type="input" inputType="email" :label="__('hrms.recruitment.field_to_email')" name="to_email" value="{{ $candidate->email }}" :required="true" />
                                            </div>
                                            <div class="col-md-6">
                                                <x-ui.modal-form-ui type="input" :label="__('hrms.recruitment.field_email_subject')" name="subject" value="{{ __('hrms.recruitment.email_subject_default', ['code' => $offer->offer_code, 'title' => $requisition->job_title]) }}" :required="true" />
                                            </div>
                                        </div>

                                        <div class="p-3 rounded-3 border bg-light mb-3 d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="feather-file-text text-danger fs-18"></i>
                                                <span class="fs-13 fw-bold text-dark">Job_Offer_{{ $offer->offer_code }}.pdf</span>
                                                <span class="badge bg-soft-danger text-danger border px-2 py-1 fs-11"><i class="feather-paperclip me-1"></i>{{ __('hrms.recruitment.badge_official_pdf_attached') }}</span>
                                            </div>
                                            <span class="fs-12 text-muted">{{ __('hrms.recruitment.recipient_label', ['name' => $candidate->full_name]) }}</span>
                                        </div>

                                        <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
                                            <x-ui.button variant="light" size="sm" class="border px-4" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
                                            <x-ui.button type="submit" variant="primary" size="sm" class="px-4 fw-bold" icon="feather-send">{{ __('hrms.recruitment.btn_send_offer_email') }}</x-ui.button>
                                        </div>
                                    </form>
                                </x-ui.modal>

                                <!-- Modal: Confirmation for Candidate to Employee Conversion -->
                                <x-ui.modal id="confirmConvertModal{{ $offer->id }}" :title="'<i class=\'feather-user-check text-success me-2\'></i>' . __('hrms.recruitment.modal_convert_employee_title')" size="md" :centered="true" :showFooter="false">
                                    <form action="{{ route('hrms.recruitment.offer.convert', $offer->id) }}" method="POST">
                                        @csrf
                                        @php
                                            $availableSmtps = \App\Models\EmailConfiguration::where('is_active', true)->orderByDesc('is_default')->get();
                                        @endphp

                                        @if($availableSmtps->isNotEmpty())
                                            <div class="mb-3 text-start">
                                                <x-ui.modal-form-ui type="select" :label="__('hrms.recruitment.field_from_sender')" name="account_id">
                                                    @foreach($availableSmtps as $s)
                                                        <option value="{{ $s->id }}" {{ $s->is_default ? 'selected' : '' }}>
                                                            {{ $s->name }} ({{ $s->email_address }})
                                                        </option>
                                                    @endforeach
                                                </x-ui.modal-form-ui>
                                            </div>
                                        @endif

                                        <div class="text-center py-2">
                                            <div class="avatar-lg bg-soft-success text-success rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                                                <i class="feather-user-plus fs-22"></i>
                                            </div>
                                            <h5 class="fw-bold text-dark mb-1">{{ __('hrms.recruitment.convert_confirm_heading') }}</h5>
                                            <p class="text-muted fs-12 mb-3">
                                                {!! __('hrms.recruitment.convert_confirm_desc', ['email' => '<code>' . e($candidate->email) . '</code>']) !!}
                                            </p>

                                            @if($offer->status !== 'accepted')
                                                <div class="alert alert-warning text-start fs-12 mb-3 border-warning">
                                                    <i class="feather-alert-triangle me-1 fw-bold"></i>
                                                    {!! __('hrms.recruitment.offer_not_accepted_warning', ['status' => '<code>' . strtoupper($offer->status) . '</code>']) !!}
                                                </div>
                                                <div class="d-flex justify-content-center mb-3">
                                                    <x-ui.button variant="outline-success" size="sm" class="px-3 fw-semibold" data-bs-dismiss="modal" data-bs-toggle="modal" :data-bs-target="'#offerStatusModal' . $offer->id" icon="feather-check-circle">
                                                        {{ __('hrms.recruitment.btn_record_acceptance_first') }}
                                                    </x-ui.button>
                                                </div>
                                            @endif

                                            <div class="p-3 bg-light border rounded-3 text-start mb-3 fs-12">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span class="text-muted">{{ __('hrms.recruitment.candidate_name') }}:</span>
                                                    <strong class="text-dark">{{ $candidate->full_name }}</strong>
                                                </div>
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span class="text-muted">{{ __('hrms.recruitment.field_email') }}:</span>
                                                    <strong class="text-dark">{{ $candidate->email }}</strong>
                                                </div>
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span class="text-muted">{{ __('hrms.common.status') }}:</span>
                                                    <span class="badge bg-{{ $offer->status === 'accepted' ? 'success' : 'warning' }}">{{ strtoupper($offer->status) }}</span>
                                                </div>
                                                @if($offer->accepted_at)
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span class="text-muted">{{ __('hrms.recruitment.accepted_at_colon') }}</span>
                                                        <strong class="text-success">{{ optional($offer->accepted_at)->format('d M, Y H:i') }}</strong>
                                                    </div>
                                                @endif
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span class="text-muted">{{ __('hrms.recruitment.offered_position_colon') }}</span>
                                                    <strong class="text-dark">{{ $offer->designation->name ?? __('hrms.common.na') }}</strong>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <span class="text-muted">{{ __('hrms.recruitment.department_colon') }}</span>
                                                    <strong class="text-dark">{{ $offer->department->name ?? __('hrms.common.na') }}</strong>
                                                </div>
                                            </div>

                                            @if($offer->status === 'accepted')
                                                <div class="mb-3 text-start">
                                                    <x-ui.modal-form-ui type="input" :label="__('hrms.recruitment.field_initial_password')" name="initial_password" :placeholder="__('hrms.recruitment.placeholder_initial_password')" :helperText="__('hrms.recruitment.helper_initial_password')" />
                                                </div>
                                            @endif
                                        </div>

                                        <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
                                            <x-ui.button variant="light" size="sm" class="border px-4" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
                                            @if($offer->status === 'accepted')
                                                <x-ui.button type="submit" variant="success" size="sm" class="text-white px-4 fw-bold" icon="feather-check-circle">{{ __('hrms.recruitment.btn_confirm_proceed') }}</x-ui.button>
                                            @else
                                                <button type="submit" class="btn btn-sm btn-secondary px-4 fw-bold" disabled><i class="feather-lock me-1"></i> {{ __('hrms.recruitment.btn_acceptance_required') }}</button>
                                            @endif
                                        </div>
                                    </form>
                                </x-ui.modal>
                            @endif

                        @empty
                            <div class="text-center text-muted py-5 fs-12">
                                {{ __('hrms.recruitment.no_candidates_in_stage') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>

    </div>
</div>

<!-- Modal: Add Candidate directly to this Requisition -->
<x-ui.modal id="addCandidateModal" :title="'<i class=\'feather-user-plus text-primary me-2\'></i>' . __('hrms.recruitment.modal_add_candidate_to_job_title', ['title' => $requisition->job_title])" size="lg" :centered="true" :showFooter="false">
    <form action="{{ route('hrms.recruitment.candidates.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="job_requisition_id" value="{{ $requisition->id }}">
        <div class="text-start">
            <div class="row g-3">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" :label="__('hrms.recruitment.th_source')" name="source" :required="true">
                        <option value="direct">{{ __('hrms.recruitment.source_direct') }}</option>
                        <option value="referral">{{ __('hrms.recruitment.source_referral') }}</option>
                        <option value="linkedin">{{ __('hrms.recruitment.source_linkedin') }}</option>
                        <option value="agency">{{ __('hrms.recruitment.source_agency') }}</option>
                        <option value="other">{{ __('hrms.recruitment.source_other') }}</option>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="number" :label="__('hrms.recruitment.field_total_exp')" name="total_experience_years" value="0" :required="true" />
                </div>

                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" :label="__('hrms.recruitment.field_first_name')" name="first_name" :required="true" :placeholder="__('hrms.recruitment.placeholder_first_name')" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" :label="__('hrms.recruitment.field_last_name')" name="last_name" :placeholder="__('hrms.recruitment.placeholder_last_name')" />
                </div>

                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="email" :label="__('hrms.recruitment.field_email')" name="email" :required="true" :placeholder="__('hrms.recruitment.placeholder_email')" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="tel" pattern="(?=.*[0-9])[0-9+().\s-]+" :label="__('hrms.recruitment.field_phone')" name="phone" :placeholder="__('hrms.recruitment.placeholder_phone')" />
                </div>

                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" :label="__('hrms.recruitment.field_current_designation')" name="current_designation" :placeholder="__('hrms.recruitment.placeholder_current_designation')" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" :label="__('hrms.recruitment.field_current_company')" name="current_company" :placeholder="__('hrms.recruitment.placeholder_current_company')" />
                </div>

                <div class="col-12">
                    <x-ui.odoo-form-ui type="file" :label="__('hrms.recruitment.field_attach_resume')" name="resume" :required="true" :helperText="__('hrms.recruitment.helper_resume_formats')" />
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center justify-content-end gap-2 pt-3 mt-3 border-top">
            <x-ui.button variant="light" size="sm" class="border px-4" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="primary" size="sm" class="px-4 fw-bold" icon="feather-check">{{ __('hrms.recruitment.btn_add_candidate_to_job') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>
@endsection

@push('scripts')
<script>
    var offerCanvasContexts = {};

    function handleOfferTmplChange(selectEl, appId) {
        var selectedOpt = selectEl.options[selectEl.selectedIndex];
        var reqSig = selectedOpt ? selectedOpt.getAttribute('data-requires-signature') : '0';
        var hasSigTag = selectedOpt ? selectedOpt.getAttribute('data-has-hr-sig') : '0';
        
        var sigSec = document.getElementById('offer_hr_sig_section_' + appId);
        if (sigSec) {
            if (reqSig === '1' || hasSigTag === '1') {
                sigSec.classList.remove('d-none');
                setTimeout(function() { initOfferSigCanvas(appId); }, 100);
            } else {
                sigSec.classList.add('d-none');
            }
        }
    }

    function initOfferSigCanvas(appId) {
        var canvas = document.getElementById('offerHrSignatureCanvas_' + appId);
        if (!canvas || canvas.dataset.initialized === '1') return;
        canvas.dataset.initialized = '1';

        var ctx = canvas.getContext('2d');
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#0f172a';
        offerCanvasContexts[appId] = { canvas: canvas, ctx: ctx, isDrawing: false };

        function getCanvasPos(e) {
            var rect = canvas.getBoundingClientRect();
            var clientX = e.clientX || (e.touches && e.touches[0] ? e.touches[0].clientX : 0);
            var clientY = e.clientY || (e.touches && e.touches[0] ? e.touches[0].clientY : 0);
            return {
                x: (clientX - rect.left) * (canvas.width / rect.width),
                y: (clientY - rect.top) * (canvas.height / rect.height)
            };
        }

        canvas.addEventListener('mousedown', function(e) {
            offerCanvasContexts[appId].isDrawing = true;
            var pos = getCanvasPos(e);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
        });

        canvas.addEventListener('mousemove', function(e) {
            if (!offerCanvasContexts[appId].isDrawing) return;
            var pos = getCanvasPos(e);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            var hiddenInput = document.getElementById('offer_hr_sig_input_data_' + appId);
            if (hiddenInput) hiddenInput.value = canvas.toDataURL('image/png');
        });

        canvas.addEventListener('mouseup', function() { offerCanvasContexts[appId].isDrawing = false; });
        canvas.addEventListener('mouseleave', function() { offerCanvasContexts[appId].isDrawing = false; });

        canvas.addEventListener('touchstart', function(e) {
            offerCanvasContexts[appId].isDrawing = true;
            var pos = getCanvasPos(e);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
            e.preventDefault();
        }, { passive: false });

        canvas.addEventListener('touchmove', function(e) {
            if (!offerCanvasContexts[appId].isDrawing) return;
            var pos = getCanvasPos(e);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            var hiddenInput = document.getElementById('offer_hr_sig_input_data_' + appId);
            if (hiddenInput) hiddenInput.value = canvas.toDataURL('image/png');
            e.preventDefault();
        }, { passive: false });

        canvas.addEventListener('touchend', function() { offerCanvasContexts[appId].isDrawing = false; });
    }

    function switchOfferHrSigMode(appId, mode) {
        var drawCont = document.getElementById('offer_hr_sig_draw_container_' + appId);
        var uploadCont = document.getElementById('offer_hr_sig_upload_container_' + appId);
        var btnDraw = document.getElementById('btn_offer_hr_sig_draw_' + appId);
        var btnUpload = document.getElementById('btn_offer_hr_sig_upload_' + appId);

        if (mode === 'draw') {
            if (drawCont) drawCont.classList.remove('d-none');
            if (uploadCont) uploadCont.classList.add('d-none');
            if (btnDraw) {
                btnDraw.className = 'nav-link btn-xs py-1 px-3 fs-11 fw-bold rounded-pill active border-0 btn-primary';
                btnDraw.style.cssText = 'background-color: var(--bs-primary) !important; color: #ffffff !important;';
            }
            if (btnUpload) {
                btnUpload.className = 'nav-link btn-xs py-1 px-3 fs-11 fw-bold text-secondary rounded-pill border-0';
                btnUpload.style.cssText = '';
            }
        } else {
            if (drawCont) drawCont.classList.add('d-none');
            if (uploadCont) uploadCont.classList.remove('d-none');
            if (btnUpload) {
                btnUpload.className = 'nav-link btn-xs py-1 px-3 fs-11 fw-bold rounded-pill active border-0 btn-primary';
                btnUpload.style.cssText = 'background-color: var(--bs-primary) !important; color: #ffffff !important;';
            }
            if (btnDraw) {
                btnDraw.className = 'nav-link btn-xs py-1 px-3 fs-11 fw-bold text-secondary rounded-pill border-0';
                btnDraw.style.cssText = '';
            }
        }
    }

    function clearOfferHrSigCanvas(appId) {
        var obj = offerCanvasContexts[appId];
        if (obj && obj.ctx && obj.canvas) {
            obj.ctx.clearRect(0, 0, obj.canvas.width, obj.canvas.height);
            var hiddenInput = document.getElementById('offer_hr_sig_input_data_' + appId);
            if (hiddenInput) hiddenInput.value = '';
        }
    }

    function handleOfferHrSigUpload(input, appId) {
        var box = document.getElementById('offer_hr_sig_upload_preview_box_' + appId);
        if (input.files && input.files[0] && box) {
            var file = input.files[0];
            var reader = new FileReader();
            reader.onload = function(e) {
                box.innerHTML = '<img src="' + e.target.result + '" style="max-height: 60px; max-width: 200px; object-fit: contain;" class="mb-1" />' +
                                '<span class="fw-bold text-success fs-12"><i class="feather-check-circle me-1"></i>' + file.name + '</span>' +
                                '<small class="text-muted fs-10">Click to change signature image</small>';
            };
            reader.readAsDataURL(file);
        }
    }
</script>
@endpush
