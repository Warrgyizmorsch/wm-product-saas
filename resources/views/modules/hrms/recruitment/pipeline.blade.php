@extends('layouts.duralux')

@section('title', 'Candidate Pipeline | ' . $requisition->job_title)
@section('page-title', 'Pipeline: ' . $requisition->job_title)
@section('breadcrumb', 'HRMS / Recruitment / Pipeline')

@php
    $authUser = auth()->user();
    $canCreateRecruitment = $authUser && ($authUser->hasHrPermission('hrms.recruitment.create') || $authUser->hasHrPermission('hr.settings.manage') || $authUser->hasHrPermission('hrms.recruitment.manage'));
    $canUpdateRecruitment = $authUser && ($authUser->hasHrPermission('hrms.recruitment.update') || $authUser->hasHrPermission('hr.settings.manage') || $authUser->hasHrPermission('hrms.recruitment.manage'));
    $canManageRecruitment = $authUser && ($authUser->hasHrPermission('hrms.recruitment.manage') || $authUser->hasHrPermission('hr.settings.manage'));
@endphp

@section('page-actions')
    @if($canCreateRecruitment)
        <div class="d-flex align-items-center gap-2">
            <x-ui.button variant="primary" icon="feather-user-plus" data-bs-toggle="modal" data-bs-target="#addCandidateModal">
                Add Candidate to Job
            </x-ui.button>
        </div>
    @endif
@endsection

@push('styles')
<style>
    #recruitmentTabs .nav-link {
        border: none !important;
        background-color: transparent !important;
        color: #64748b;
        font-weight: 500;
        padding: 12px 18px;
        border-bottom: 2px solid transparent !important;
        transition: all 0.2s ease-in-out;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    #recruitmentTabs .nav-link:hover {
        color: var(--bs-primary);
    }
    #recruitmentTabs .nav-link.active {
        color: var(--bs-primary) !important;
        border-bottom: 2px solid var(--bs-primary) !important;
        font-weight: 600;
    }

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
</style>
@endpush

@section('content')
<div class="container-fluid">

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
            <i class="feather-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
            <i class="feather-alert-triangle me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- ERP Single Panel Container -->
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">

        <!-- Panel Header with Sub Navigation Tabs -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-bottom pb-3 mb-4">
            <ul class="nav nav-tabs border-0" id="recruitmentTabs">
                <li class="nav-item">
                    <a class="nav-link active" href="{{ route('hrms.recruitment.requisitions.index') }}">
                        <i class="feather-briefcase"></i> Job Requisitions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('hrms.recruitment.candidates.index') }}">
                        <i class="feather-users"></i> Candidate Bank
                    </a>
                </li>
            </ul>

            <x-ui.button href="{{ route('hrms.recruitment.requisitions.index') }}" variant="light" size="sm" icon="feather-arrow-left" class="border">
                Back to Requisitions
            </x-ui.button>
        </div>

        <!-- Job Header Summary -->
        <div class="bg-light p-3 rounded-3 mb-4 border">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h4 class="fw-bold mb-1 text-dark">{{ $requisition->job_title }} <span class="badge bg-white text-secondary border ms-2">{{ $requisition->requisition_code }}</span></h4>
                    <div class="text-muted fs-13">
                        <span class="me-3"><i class="feather-folder me-1"></i>{{ $requisition->department->name ?? 'N/A' }}</span>
                        <span class="me-3"><i class="feather-award me-1"></i>Required Exp: <strong>{{ $requisition->min_experience_years }} - {{ $requisition->max_experience_years }} Yrs</strong></span>
                        <span class="me-3"><i class="feather-users me-1"></i>Openings: <strong>{{ $requisition->vacancies }} Vacancies</strong></span>
                    </div>
                </div>
                <div>
                    <x-ui.status-badge status="{{ $requisition->status }}" label="Status: {{ strtoupper($requisition->status) }}" size="lg" />
                </div>
            </div>
        </div>

        @php
            $targetCandidateId = request('candidate_id');
            $targetApp = $targetCandidateId ? $applications->firstWhere('candidate_id', $targetCandidateId) : null;
            $activeStageKey = 'applied';
            if ($targetApp) {
                if (in_array($targetApp->current_stage, ['interview', 'interview_round_1', 'interview_round_2', 'interview_round_3'])) {
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
                $stageTabs[] = [
                    'id' => 'stage-content-' . $stageKey,
                    'label' => $stageLabel . ' (' . $count . ')',
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
                                                <small class="text-muted fs-11">{{ $candidate->current_designation ?? 'Applicant' }} @ {{ $candidate->current_company ?? 'N/A' }}</small>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="badge bg-light text-secondary border fs-11">{{ $candidate->candidate_code }}</span>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center gap-2 mb-3">
                                        <x-ui.badge variant="info" soft class="fs-11">
                                            <i class="feather-clock me-1"></i>{{ $candidate->total_experience_years }} Yrs Exp
                                        </x-ui.badge>
                                        @if($candidate->resume_path)
                                            <x-ui.button 
                                                :href="asset('storage/' . $candidate->resume_path)" 
                                                target="_blank" 
                                                variant="outline-primary" 
                                                size="sm" 
                                                icon="feather-file-text"
                                            >
                                                View CV
                                            </x-ui.button>
                                        @endif
                                    </div>

                                    @if($app->interviews->count() > 0)
                                        <button type="button" class="btn btn-sm btn-light border w-100 mb-3 py-2 px-3 text-start d-flex align-items-center justify-content-between text-dark rounded-2 shadow-xs" data-bs-toggle="modal" data-bs-target="#interviewDetailsModal{{ $app->id }}">
                                            <span class="fw-semibold text-secondary fs-12"><i class="feather-calendar text-primary me-1"></i> {{ $app->interviews->count() }} Interview {{ Str::plural('Round', $app->interviews->count()) }}</span>
                                            @if($scorecard)
                                                <x-ui.badge variant="{{ $scorecard->recommendation === 'pass' ? 'success' : ($scorecard->recommendation === 'reject' ? 'danger' : 'warning') }}" soft>
                                                    {{ $scorecard->overall_rating }}/5 ⭐ {{ strtoupper($scorecard->recommendation) }}
                                                </x-ui.badge>
                                            @elseif($latestInterview && $latestInterview->status === 'scheduled')
                                                <x-ui.badge variant="warning" soft>
                                                    Scheduled <i class="feather-eye ms-1"></i>
                                                </x-ui.badge>
                                            @else
                                                <x-ui.badge variant="secondary" soft>
                                                    Details <i class="feather-chevron-right ms-1"></i>
                                                </x-ui.badge>
                                            @endif
                                        </button>
                                    @endif

                                    @if($canUpdateRecruitment || $canManageRecruitment)
                                        <div class="mt-auto pt-2 border-top d-flex align-items-center flex-nowrap gap-1 w-100 overflow-x-auto pb-1">
                                            @if($canUpdateRecruitment)
                                                <!-- Change Stage Dropdown (Left) -->
                                                <div class="dropdown flex-shrink-0 me-auto">
                                                    <button class="btn btn-sm btn-light text-secondary border dropdown-toggle fw-semibold fs-11 px-2 py-1" type="button" data-bs-toggle="dropdown" title="Move Stage">
                                                        Move
                                                    </button>
                                                    <ul class="dropdown-menu fs-12 shadow-sm border-0">
                                                        @foreach($stages as $key => $lbl)
                                                            <li>
                                                                <form action="{{ route('hrms.recruitment.stage.update', $app->id) }}" method="POST">
                                                                    @csrf
                                                                    <input type="hidden" name="current_stage" value="{{ $key }}">
                                                                    <button type="submit" class="dropdown-item {{ $key === $stageKey ? 'active' : '' }}">{{ $lbl }}</button>
                                                                </form>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>

                                                <!-- Action Buttons Group (Right) -->
                                                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                                    @if(!in_array($stageKey, ['offer_sent', 'hired', 'rejected']))
                                                        <button class="btn btn-sm btn-outline-success fw-semibold fs-11 px-2 py-1 flex-shrink-0" data-bs-toggle="modal" data-bs-target="#scheduleInterviewModal{{ $app->id }}">
                                                            <i class="feather-calendar me-1"></i>+ Round
                                                        </button>
                                                    @endif

                                                    @if($latestInterview && $latestInterview->status === 'scheduled' && !in_array($stageKey, ['offer_sent', 'hired', 'rejected']))
                                                        <button class="btn btn-sm btn-outline-warning fw-semibold fs-11 px-2 py-1 flex-shrink-0" data-bs-toggle="modal" data-bs-target="#scorecardModal{{ $latestInterview->id }}">
                                                            <i class="feather-check-square me-1"></i>Scorecard
                                                        </button>
                                                    @endif

                                                    @if(!in_array($stageKey, ['hired', 'rejected']) && ($app->interviews->count() > 0 || $offer || $stageKey === 'offer_sent'))
                                                        @if(!$offer)
                                                            <button class="btn btn-sm btn-outline-info fw-semibold fs-11 px-2 py-1 flex-shrink-0" data-bs-toggle="modal" data-bs-target="#offerModal{{ $app->id }}">
                                                                <i class="feather-file-plus me-1"></i>Offer
                                                            </button>
                                                        @else
                                                            <button class="btn btn-sm btn-info text-white fw-semibold fs-11 px-2 py-1 flex-shrink-0" data-bs-toggle="modal" data-bs-target="#viewOfferModal{{ $offer->id }}">
                                                                <i class="feather-file-text me-1"></i>View Offer
                                                            </button>
                                                            <button class="btn btn-sm btn-primary fw-semibold fs-11 px-2 py-1 flex-shrink-0" data-bs-toggle="modal" data-bs-target="#sendOfferEmailModal{{ $offer->id }}">
                                                                <i class="feather-send me-1"></i>Send Mail
                                                            </button>
                                                        @endif
                                                    @endif

                                                    @if($canManageRecruitment && $offer && $stageKey !== 'hired' && $stageKey !== 'rejected')
                                                        <button type="button" class="btn btn-sm btn-success text-white fw-bold fs-11 px-2 py-1 flex-shrink-0" data-bs-toggle="modal" data-bs-target="#confirmConvertModal{{ $offer->id }}">
                                                            ⚡ Convert
                                                        </button>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endif

                                        @if($stageKey === 'hired')
                                            <div class="badge bg-soft-success text-success fw-bold p-2 w-100 text-center"><i class="feather-check-circle me-1"></i>Hired Employee</div>
                                        @endif

                                        @if($stageKey === 'rejected')
                                            <div class="badge bg-soft-danger text-danger fw-semibold p-2 w-100 text-center"><i class="feather-x-circle me-1"></i>Rejected Candidate</div>
                                        @endif
                                    </div>
                                </div>

                            <!-- Modal: Detailed View of All Interviews & Scorecards for this Candidate -->
                            <div class="modal fade" id="interviewDetailsModal{{ $app->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                                    <div class="modal-content border-0 shadow-lg">
                                        <div class="modal-header bg-light">
                                            <div>
                                                <h5 class="modal-title fw-bold text-dark"><i class="feather-calendar text-primary me-2"></i>Interview Schedule & Scorecards</h5>
                                                <small class="text-muted">{{ $candidate->full_name }} — {{ $requisition->job_title }} ({{ $candidate->candidate_code }})</small>
                                            </div>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-4 text-start">
                                            <!-- Candidate Summary Strip -->
                                            <div class="bg-light p-3 rounded-3 mb-4 border d-flex flex-wrap align-items-center justify-content-between gap-3">
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="candidate-avatar" style="width: 42px; height: 42px; font-size: 16px;">{{ $initials }}</div>
                                                    <div>
                                                        <h6 class="fw-bold mb-0 text-dark">{{ $candidate->full_name }}</h6>
                                                        <small class="text-muted">{{ $candidate->current_designation ?? 'N/A' }} @ {{ $candidate->current_company ?? 'N/A' }}</small>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="badge bg-white text-secondary border px-3 py-2"><i class="feather-clock me-1"></i>{{ $candidate->total_experience_years }} Yrs Exp</span>
                                                    @if($candidate->email)
                                                        <a href="mailto:{{ $candidate->email }}" class="btn btn-xs btn-outline-secondary py-1"><i class="feather-mail me-1"></i>Email</a>
                                                    @endif
                                                    @if($candidate->resume_path)
                                                        <x-ui.button 
                                                            :href="asset('storage/' . $candidate->resume_path)" 
                                                            target="_blank" 
                                                            variant="outline-primary" 
                                                            size="sm" 
                                                            icon="feather-file-text"
                                                        >
                                                            View CV
                                                        </x-ui.button>
                                                    @endif
                                                </div>
                                            </div>

                                            <!-- Rounds Timeline -->
                                            <h6 class="fw-bold text-dark mb-3"><i class="feather-list text-primary me-1"></i> Conducted & Scheduled Rounds ({{ $app->interviews->count() }})</h6>
                                            
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
                                                                <span class="badge bg-warning text-dark fs-11 px-2 py-1"><i class="feather-clock me-1"></i>Scheduled</span>
                                                            @else
                                                                <span class="badge bg-secondary fs-11 px-2 py-1">Pending</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="card-body p-3">
                                                        <div class="row g-3 mb-2 fs-13">
                                                            <div class="col-md-4">
                                                                <div class="text-muted fs-11">Date & Time</div>
                                                                <div class="fw-semibold text-dark"><i class="feather-clock text-warning me-1"></i>{{ $itw->scheduled_at ? $itw->scheduled_at->format('M d, Y - h:i A') : 'TBD' }}</div>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <div class="text-muted fs-11">Interviewer</div>
                                                                <div class="fw-semibold text-dark"><i class="feather-user text-info me-1"></i>{{ $itw->interviewer->full_name ?? 'Assigned Team' }}</div>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <div class="text-muted fs-11">Meeting Link</div>
                                                                @if($itw->meeting_link)
                                                                    <a href="{{ $itw->meeting_link }}" target="_blank" class="btn btn-xs btn-primary text-white mt-1 fw-bold">
                                                                        <i class="feather-video me-1"></i> Join Video Call
                                                                    </a>
                                                                @else
                                                                    <span class="text-muted fs-12">In-Person / No Link</span>
                                                                @endif
                                                            </div>
                                                        </div>

                                                        @if($sc)
                                                            <!-- Detailed Scorecard Breakdown -->
                                                            <div class="mt-3 pt-3 border-top bg-light p-3 rounded-2">
                                                                <h6 class="fw-bold text-dark fs-12 mb-2"><i class="feather-award text-success me-1"></i> Scorecard Evaluation Breakdown</h6>
                                                                <div class="row g-2 text-center mb-2">
                                                                    <div class="col-3">
                                                                        <div class="bg-white p-2 rounded border">
                                                                            <small class="text-muted d-block fs-10">Technical</small>
                                                                            <strong class="text-primary fs-13">{{ $sc->technical_rating }}/5 ⭐</strong>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-3">
                                                                        <div class="bg-white p-2 rounded border">
                                                                            <small class="text-muted d-block fs-10">Communication</small>
                                                                            <strong class="text-primary fs-13">{{ $sc->communication_rating }}/5 ⭐</strong>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-3">
                                                                        <div class="bg-white p-2 rounded border">
                                                                            <small class="text-muted d-block fs-10">Culture Fit</small>
                                                                            <strong class="text-primary fs-13">{{ $sc->culture_fit_rating }}/5 ⭐</strong>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-3">
                                                                        <div class="bg-white p-2 rounded border">
                                                                            <small class="text-muted d-block fs-10">Overall</small>
                                                                            <strong class="text-success fs-13">{{ $sc->overall_rating }}/5 ⭐</strong>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                @if($sc->feedback_notes)
                                                                    <div class="mt-2">
                                                                        <small class="text-muted fw-bold d-block mb-1">Feedback Notes:</small>
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
                                                    No interview rounds scheduled yet.
                                                </div>
                                            @endforelse
                                        </div>
                                        <div class="modal-footer bg-light py-2">
                                            <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal: Schedule Interview for Application -->
                            <div class="modal fade" id="scheduleInterviewModal{{ $app->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 shadow">
                                        <form action="{{ route('hrms.recruitment.interview.schedule', $app->id) }}" method="POST">
                                            @csrf
                                            <div class="modal-header bg-light">
                                                <h5 class="modal-title fw-bold">Schedule Interview: {{ $candidate->full_name }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4 text-start">
                                                <div class="mb-3">
                                                    <x-ui.odoo-form-ui type="input" inputType="number" label="Round Number" name="round_number" value="{{ $app->interviews->count() + 1 }}" :required="true" />
                                                </div>
                                                <div class="mb-3">
                                                    <x-ui.odoo-form-ui type="input" label="Round Name" name="round_name" placeholder="e.g. Technical Round 1" :required="true" />
                                                </div>
                                                <div class="mb-3">
                                                    <x-ui.odoo-form-ui type="input" inputType="datetime-local" label="Date & Time" name="scheduled_at" :required="true" />
                                                </div>
                                                <div class="mb-3">
                                                    <x-ui.odoo-form-ui type="select" label="Interviewer" name="interviewer_employee_id">
                                                        <option value="">Select Employee...</option>
                                                        @foreach($employees as $emp)
                                                            <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_code }})</option>
                                                        @endforeach
                                                    </x-ui.odoo-form-ui>
                                                </div>
                                                <div class="mb-3">
                                                    <x-ui.odoo-form-ui type="input" label="Meeting Link" name="meeting_link" placeholder="e.g. Google Meet URL" />
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light py-2">
                                                <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-success px-4 fw-bold">Schedule Interview</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal: Fill Scorecard -->
                            @if($latestInterview)
                                <div class="modal fade" id="scorecardModal{{ $latestInterview->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content border-0 shadow">
                                            <form action="{{ route('hrms.recruitment.scorecard.submit', $latestInterview->id) }}" method="POST">
                                                @csrf
                                                <div class="modal-header bg-light">
                                                    <h5 class="modal-title fw-bold">Interview Scorecard: {{ $latestInterview->round_name }}</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4 text-start">
                                                    <div class="row g-3">
                                                        <div class="col-12">
                                                            <x-ui.odoo-form-ui type="select" label="Technical Rating" name="technical_rating" :required="true">
                                                                <option value="5">5 - Excellent</option>
                                                                <option value="4" selected>4 - Good</option>
                                                                <option value="3">3 - Average</option>
                                                                <option value="2">2 - Poor</option>
                                                                <option value="1">1 - Very Poor</option>
                                                            </x-ui.odoo-form-ui>
                                                        </div>
                                                        <div class="col-12">
                                                            <x-ui.odoo-form-ui type="select" label="Comm. Rating" name="communication_rating" :required="true">
                                                                <option value="5">5 - Excellent</option>
                                                                <option value="4" selected>4 - Good</option>
                                                                <option value="3">3 - Average</option>
                                                                <option value="2">2 - Poor</option>
                                                                <option value="1">1 - Very Poor</option>
                                                            </x-ui.odoo-form-ui>
                                                        </div>
                                                        <div class="col-12">
                                                            <x-ui.odoo-form-ui type="select" label="Culture Fit" name="culture_fit_rating" :required="true">
                                                                <option value="5">5 - Excellent</option>
                                                                <option value="4" selected>4 - Good</option>
                                                                <option value="3">3 - Average</option>
                                                                <option value="2">2 - Poor</option>
                                                                <option value="1">1 - Very Poor</option>
                                                            </x-ui.odoo-form-ui>
                                                        </div>
                                                        <div class="col-12">
                                                            <x-ui.odoo-form-ui type="select" label="Overall Rating" name="overall_rating" :required="true">
                                                                <option value="5">5 - Strong Hire</option>
                                                                <option value="4" selected>4 - Hire</option>
                                                                <option value="3">3 - Neutral / Hold</option>
                                                                <option value="2">2 - Reject</option>
                                                                <option value="1">1 - Strong Reject</option>
                                                            </x-ui.odoo-form-ui>
                                                        </div>
                                                        <div class="col-md-12">
                                                            <x-ui.odoo-form-ui type="select" label="Recommendation" name="recommendation" :required="true">
                                                                <option value="pass">Pass to Next Round / Hire</option>
                                                                <option value="hold">On Hold</option>
                                                                <option value="reject">Reject Candidate</option>
                                                            </x-ui.odoo-form-ui>
                                                        </div>
                                                        <div class="col-md-12">
                                                            <x-ui.odoo-form-ui type="textarea" label="Feedback Notes" name="feedback_notes" rows="3" placeholder="Enter strengths, weaknesses, and interview observations..." />
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light py-2">
                                                    <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary px-4 fw-bold">Submit Scorecard</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <!-- Modal: Generate Job Offer -->
                            <div class="modal fade" id="offerModal{{ $app->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 shadow">
                                        <form action="{{ route('hrms.recruitment.offer.create', $app->id) }}" method="POST">
                                            @csrf
                                            <div class="modal-header bg-light">
                                                <h5 class="modal-title fw-bold">Generate Job Offer: {{ $candidate->full_name }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4 text-start">
                                                <div class="mb-3">
                                                    <x-ui.odoo-form-ui type="select" label="Document Master Template" name="document_template_id">
                                                        <option value="">-- Standard Offer Letter --</option>
                                                        @foreach($templates as $tmpl)
                                                            <option value="{{ $tmpl->id }}">{{ $tmpl->name }} ({{ $tmpl->code }})</option>
                                                        @endforeach
                                                    </x-ui.odoo-form-ui>
                                                    <small class="text-muted fs-11 ms-1">Choose template from Document Master or use system default.</small>
                                                </div>
                                                <div class="mb-3">
                                                    <x-ui.odoo-form-ui type="select" label="Offered Role" name="offered_designation_id" :required="true">
                                                        @foreach($designations as $desig)
                                                            <option value="{{ $desig->id }}" {{ $desig->id == $requisition->designation_id ? 'selected' : '' }}>{{ $desig->name }}</option>
                                                        @endforeach
                                                    </x-ui.odoo-form-ui>
                                                </div>
                                                <div class="mb-3">
                                                    <x-ui.odoo-form-ui type="select" label="Department" name="offered_department_id" :required="true">
                                                        @foreach($departments as $dept)
                                                            <option value="{{ $dept->id }}" {{ $dept->id == $requisition->department_id ? 'selected' : '' }}>{{ $dept->name }}</option>
                                                        @endforeach
                                                    </x-ui.odoo-form-ui>
                                                </div>
                                                <div class="mb-3">
                                                    <x-ui.odoo-form-ui type="input" inputType="number" label="Annual CTC ($)" name="offered_annual_ctc" placeholder="e.g. 75000.00" :required="true" />
                                                </div>
                                                <div class="mb-3">
                                                    <x-ui.odoo-form-ui type="input" inputType="date" label="Joining Date" name="joining_date" value="{{ optional($requisition->target_joining_date)->format('Y-m-d') }}" :required="true" />
                                                </div>
                                                <div class="mb-3">
                                                    <x-ui.odoo-form-ui type="textarea" label="Offer Letter Notes / Terms" name="offer_letter_notes" rows="2" placeholder="e.g. Probation period, signing bonus, special conditions..." />
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light py-2">
                                                <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-info text-white px-4 fw-bold">Generate Offer</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal: View Rendered Job Offer Letter -->
                            @if($offer)
                                <div class="modal fade" id="viewOfferModal{{ $offer->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                                        <div class="modal-content border-0 shadow-lg">
                                            <div class="modal-header bg-light">
                                                <div>
                                                    <h5 class="modal-title fw-bold text-dark"><i class="feather-file-text text-info me-2"></i>Job Offer Letter — {{ $offer->offer_code }}</h5>
                                                    <small class="text-muted">Issued to <strong>{{ $candidate->full_name }}</strong> on {{ optional($offer->created_at)->format('d M, Y') }}</small>
                                                </div>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-4 text-start">
                                                <!-- Key Offer Summary Pill Row -->
                                                <div class="row g-3 mb-4 bg-light p-3 rounded-3 border">
                                                    <div class="col-md-3 col-6">
                                                        <small class="text-muted d-block fs-11">Candidate Name</small>
                                                        <strong class="text-dark fs-13">{{ $candidate->full_name }}</strong>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <small class="text-muted d-block fs-11">Offered Role</small>
                                                        <strong class="text-dark fs-13">{{ $offer->designation->name ?? $requisition->designation->name ?? 'N/A' }}</strong>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <small class="text-muted d-block fs-11">Annual CTC</small>
                                                        <strong class="text-success fs-13">${{ number_format($offer->offered_annual_ctc, 2) }}</strong>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <small class="text-muted d-block fs-11">Joining Date</small>
                                                        <strong class="text-primary fs-13">{{ optional($offer->joining_date)->format('d M, Y') ?? 'TBD' }}</strong>
                                                    </div>
                                                </div>

                                                @if($offer->offer_letter_content)
                                                    <div class="border rounded-3 p-4 bg-white shadow-xs text-dark fs-13 lh-base position-relative" style="white-space: pre-wrap;">
                                                        {!! preg_replace('/\{([^{}\n]*)\}/', '$1', $offer->offer_letter_content) !!}
                                                    </div>
                                                @else
                                                    <div class="border rounded-3 p-4 bg-white shadow-xs text-dark fs-13 lh-base">
                                                        <h5 class="fw-bold text-dark mb-3">JOB OFFER LETTER</h5>
                                                        <p>Dear <strong>{{ $candidate->full_name }}</strong>,</p>
                                                        <p>We are pleased to offer you the position of <strong>{{ $offer->designation->name ?? $requisition->designation->name }}</strong> in our <strong>{{ $offer->department->name ?? $requisition->department->name }}</strong> department.</p>
                                                        <p><strong>Offer Details:</strong></p>
                                                        <ul>
                                                            <li><strong>Offer Reference:</strong> {{ $offer->offer_code }}</li>
                                                            <li><strong>Annual Compensation (CTC):</strong> ${{ number_format($offer->offered_annual_ctc, 2) }} per annum</li>
                                                            <li><strong>Target Joining Date:</strong> {{ optional($offer->joining_date)->format('d M, Y') }}</li>
                                                        </ul>
                                                        @if($offer->offer_letter_notes)
                                                            <p><strong>Special Terms & Notes:</strong> {{ $offer->offer_letter_notes }}</p>
                                                        @endif
                                                        <p class="mt-4">We look forward to welcoming you to the team!</p>
                                                        <p class="mt-3 mb-0">Sincerely,<br><strong>HR Talent Acquisition Team</strong></p>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="modal-footer bg-light py-2 justify-content-between">
                                                <button type="button" class="btn btn-outline-secondary px-3" onclick="window.print()"><i class="feather-printer me-1"></i> Print / Download PDF</button>
                                                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            <!-- Modal: Send Email Confirmation for Job Offer -->
                                <x-ui.modal id="sendOfferEmailModal{{ $offer->id }}" title="<i class='feather-send text-primary me-2'></i>Send Job Offer Email to Candidate" size="lg" :centered="true" :showFooter="false">
                                    <form action="{{ route('hrms.recruitment.offer.send-email', $offer->id) }}" method="POST">
                                        @csrf
                                        @php
                                            $availableSmtps = \App\Models\EmailConfiguration::where('is_active', true)->orderByDesc('is_default')->get();
                                            $offerDocText = $offer->offer_letter_content ? preg_replace('/\{([^{}\n]*)\}/', '$1', $offer->offer_letter_content) : '';
                                        @endphp

                                        @if($availableSmtps->isNotEmpty())
                                            <div class="mb-3">
                                                <x-ui.modal-form-ui type="select" label="From Sender Email (SMTP Account)" name="account_id">
                                                    @foreach($availableSmtps as $s)
                                                        <option value="{{ $s->id }}" {{ $s->is_default ? 'selected' : '' }}>
                                                            {{ $s->name }} ({{ $s->email_address }})
                                                        </option>
                                                    @endforeach
                                                </x-ui.modal-form-ui>
                                            </div>
                                        @endif

                                        <div class="row g-3 mb-3">
                                            <div class="col-md-6">
                                                <x-ui.modal-form-ui type="input" inputType="email" label="Candidate Email Address (To)" name="to_email" value="{{ $candidate->email }}" :required="true" />
                                            </div>
                                            <div class="col-md-6">
                                                <x-ui.modal-form-ui type="input" label="Email Subject" name="subject" value="Job Offer Letter — {{ $offer->offer_code }} ({{ $requisition->job_title }})" :required="true" />
                                            </div>
                                        </div>

                                        <div class="p-3 rounded-3 border bg-light mb-3 d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="feather-file-text text-danger fs-18"></i>
                                                <span class="fs-13 fw-bold text-dark">Job_Offer_{{ $offer->offer_code }}.pdf</span>
                                                <span class="badge bg-soft-danger text-danger border px-2 py-1 fs-11"><i class="feather-paperclip me-1"></i>Official PDF Document Attached</span>
                                            </div>
                                            <span class="fs-12 text-muted">Recipient: <strong>{{ $candidate->full_name }}</strong></span>
                                        </div>

                                        <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
                                            <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="feather-send me-1"></i> Send Offer Email</button>
                                        </div>
                                    </form>
                                </x-ui.modal>

                                <!-- Modal: Confirmation for Candidate to Employee Conversion -->
                                <x-ui.modal id="confirmConvertModal{{ $offer->id }}" title="<i class='feather-user-check text-success me-2'></i>Convert Candidate to HRMS Employee" size="md" :centered="true" :showFooter="false">
                                    <form action="{{ route('hrms.recruitment.offer.convert', $offer->id) }}" method="POST">
                                        @csrf
                                        @php
                                            $availableSmtps = \App\Models\EmailConfiguration::where('is_active', true)->orderByDesc('is_default')->get();
                                        @endphp

                                        @if($availableSmtps->isNotEmpty())
                                            <div class="mb-3 text-start">
                                                <x-ui.modal-form-ui type="select" label="From Sender Email (SMTP Account)" name="account_id">
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
                                            <h5 class="fw-bold text-dark mb-1">Convert Candidate to Employee?</h5>
                                            <p class="text-muted fs-12 mb-3">
                                                This action will create/link a system User account for <code>{{ $candidate->email }}</code> and open the Employee Profile form.
                                            </p>

                                            <div class="p-3 bg-light border rounded-3 text-start mb-3 fs-12">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span class="text-muted">Candidate Name:</span>
                                                    <strong class="text-dark">{{ $candidate->full_name }}</strong>
                                                </div>
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span class="text-muted">Email Address:</span>
                                                    <strong class="text-dark">{{ $candidate->email }}</strong>
                                                </div>
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span class="text-muted">Offered Position:</span>
                                                    <strong class="text-dark">{{ $offer->designation->name ?? 'N/A' }}</strong>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <span class="text-muted">Department:</span>
                                                    <strong class="text-dark">{{ $offer->department->name ?? 'N/A' }}</strong>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
                                            <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-success text-white px-4 fw-bold"><i class="feather-check-circle me-1"></i> Confirm & Proceed</button>
                                        </div>
                                    </form>
                                </x-ui.modal>
                            @endif

                        @empty
                            <div class="text-center text-muted py-5 fs-12">
                                No candidates in this stage.
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>

    </div>
</div>

<!-- Modal: Add Candidate directly to this Requisition -->
<div class="modal fade" id="addCandidateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form action="{{ route('hrms.recruitment.candidates.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="job_requisition_id" value="{{ $requisition->id }}">
                <div class="modal-header bg-light">
                    <h5 class="modal-header-title fw-bold"><i class="feather-user-plus text-primary me-2"></i>Add Candidate to {{ $requisition->job_title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-start">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="Source" name="source" :required="true">
                                <option value="direct">Direct Entry</option>
                                <option value="referral">Employee Referral</option>
                                <option value="linkedin">LinkedIn</option>
                                <option value="agency">Recruitment Agency</option>
                                <option value="other">Other</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" inputType="number" label="Total Exp (Yrs)" name="total_experience_years" value="0" :required="true" />
                        </div>

                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="First Name" name="first_name" :required="true" placeholder="e.g. John" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="Last Name" name="last_name" placeholder="e.g. Doe" />
                        </div>

                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" inputType="email" label="Email Address" name="email" :required="true" placeholder="john.doe@example.com" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="Phone Number" name="phone" placeholder="+1 234 567 8900" />
                        </div>

                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="Current Role" name="current_designation" placeholder="e.g. Software Developer" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="Current Company" name="current_company" placeholder="e.g. Tech Corp" />
                        </div>

                        <div class="col-12">
                            <x-ui.odoo-form-ui type="file" label="Attach Resume / CV" name="resume" :required="true" helperText="Formats: .pdf, .doc, .docx" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="feather-check me-1"></i> Add Candidate to Job</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.modal').forEach(function(modalEl) {
            document.body.appendChild(modalEl);
        });
    });
</script>
@endpush
