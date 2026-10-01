@extends('layouts.duralux')

@section('title', '360° Report: ' . $participant->employee?->full_name . ' | HRMS')
@section('page-title', '360° Assessment Report')
@section('breadcrumb', 'HRMS / 360° Feedback / Assessment Report')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="light" icon="feather-arrow-left" href="{{ route('hrms.feedback360.index', ['active_tab' => 'my_evaluations']) }}" class="border fw-semibold">
            Back
        </x-ui.button>
        <x-ui.button variant="light" icon="feather-printer" class="border fw-semibold" onclick="window.print();">
            Print / PDF
        </x-ui.button>
        @if($canPublish && !$isPublished)
            <x-ui.button variant="success" icon="feather-check-circle" class="fw-bold" data-bs-toggle="modal" data-bs-target="#publishReportModal">
                Publish Report
            </x-ui.button>
        @endif
    </div>
@endsection

@push('styles')
<style>
    .report-profile-banner {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        padding: 18px 22px;
    }
    .avatar-initials {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background-color: rgba(var(--bs-primary-rgb), 0.12) !important;
        color: var(--bs-primary) !important;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 18px;
        flex-shrink: 0;
        border: 2px solid rgba(var(--bs-primary-rgb), 0.2);
    }
    .rater-pill-item {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 6px 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .score-metric-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 16px 14px;
        text-align: center;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
    }
    .score-metric-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        transform: translateY(-2px);
    }
    .score-metric-card .metric-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: #64748b;
        margin-bottom: 10px;
    }
    .score-badge-circle {
        width: 68px;
        height: 68px;
        border-radius: 50%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: rgba(var(--bs-primary-rgb), 0.1);
        border: 2px solid var(--bs-primary);
        color: var(--bs-primary);
    }
    .blind-spot-card {
        background-color: #fff7ed;
        border: 1px solid #fed7aa;
        border-left: 4px solid #f97316;
        border-radius: 8px;
        padding: 12px 14px;
    }
    .strength-card {
        background-color: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-left: 4px solid #22c55e;
        border-radius: 8px;
        padding: 12px 14px;
    }
    .insight-empty-card {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px 14px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }
    @media print {
        .page-header, .sidebar, .duralux-header, .btn, .nav-tabs {
            display: none !important;
        }
    }
</style>
@endpush

@section('content')
<div class="container-fluid p-0">

    @if(session('success'))
        <x-ui.alert variant="success" dismissible class="mb-4">
            <i class="feather-check-circle me-2"></i>{{ session('success') }}
        </x-ui.alert>
    @endif

    <!-- ERP Single Panel Workspace -->
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">

        @php
            $totalRatersCount = $participant->nominations->count();
            $completedRatersCount = $participant->nominations->where('status', 'completed')->count();
        @endphp

        <!-- In-Progress Notice Banner when reviews are pending -->
        @if($completedRatersCount === 0)
            <x-ui.alert variant="info" icon="feather-info" class="mb-4" dismissible>
                <div>
                    <h6 class="fw-bold text-dark mb-1 fs-14">Feedback Collection in Progress ({{ $completedRatersCount }} of {{ $totalRatersCount }} Reviews Submitted)</h6>
                    <span class="fs-12 text-muted">Assigned raters have not yet submitted their evaluation forms. Once raters complete their evaluations from the <strong>"My Reviews to Complete"</strong> tab, multi-rater scores, radar chart overlays, and perception gap analyses will populate here in real time.</span>
                </div>
            </x-ui.alert>
        @endif

        <!-- Employee Summary & Status Banner -->
        <div class="report-profile-banner d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="avatar-initials">
                    {{ substr($participant->employee?->first_name ?? 'E', 0, 1) }}{{ substr($participant->employee?->last_name ?? '', 0, 1) }}
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                        <h5 class="fw-bold text-dark mb-0 fs-16">{{ $participant->employee?->full_name }}</h5>
                        @if($isPublished)
                            <x-ui.badge variant="success" soft>Published & Shared</x-ui.badge>
                        @else
                            <x-ui.badge variant="warning" soft>Manager Calibration Draft</x-ui.badge>
                        @endif
                    </div>
                    <span class="text-muted fs-13">
                        {{ $participant->employee?->designation->name ?? 'Staff' }} &bull; {{ $participant->employee?->department->name ?? 'General' }} &bull; Manager: <strong class="text-dark">{{ $participant->manager?->full_name ?? 'Unassigned' }}</strong>
                    </span>
                </div>
            </div>

            <!-- Rater Count Badges with Styled Rater Pills -->
            <div class="d-flex gap-2 flex-wrap">
                <div class="rater-pill-item">
                    <i class="feather-user {{ $raterCounts['self'] > 0 ? 'text-success' : 'text-warning' }} fs-14"></i>
                    <div>
                        <div class="fw-bold fs-12 {{ $raterCounts['self'] > 0 ? 'text-success' : 'text-dark' }}">
                            {{ $raterCounts['self'] > 0 ? 'Submitted' : 'Pending' }}
                        </div>
                        <div class="text-muted fs-10 text-uppercase fw-semibold" style="letter-spacing: 0.4px;">Self</div>
                    </div>
                </div>

                <div class="rater-pill-item">
                    <i class="feather-user-check {{ $raterCounts['manager'] > 0 ? 'text-success' : 'text-warning' }} fs-14"></i>
                    <div>
                        <div class="fw-bold fs-12 {{ $raterCounts['manager'] > 0 ? 'text-success' : 'text-dark' }}">
                            {{ $raterCounts['manager'] > 0 ? 'Submitted' : 'Pending' }}
                        </div>
                        <div class="text-muted fs-10 text-uppercase fw-semibold" style="letter-spacing: 0.4px;">Manager</div>
                    </div>
                </div>

                <div class="rater-pill-item">
                    <i class="feather-users {{ $raterCounts['peer'] > 0 ? 'text-success' : 'text-info' }} fs-14"></i>
                    <div>
                        <div class="fw-bold fs-12 {{ $raterCounts['peer'] > 0 ? 'text-success' : 'text-dark' }}">
                            {{ $raterCounts['peer'] }} Done
                        </div>
                        <div class="text-muted fs-10 text-uppercase fw-semibold" style="letter-spacing: 0.4px;">Peers</div>
                    </div>
                </div>

                <div class="rater-pill-item">
                    <i class="feather-corner-down-right {{ $raterCounts['direct_report'] > 0 ? 'text-success' : 'text-primary' }} fs-14"></i>
                    <div>
                        <div class="fw-bold fs-12 {{ $raterCounts['direct_report'] > 0 ? 'text-success' : 'text-dark' }}">
                            {{ $raterCounts['direct_report'] }} Done
                        </div>
                        <div class="text-muted fs-10 text-uppercase fw-semibold" style="letter-spacing: 0.4px;">Direct Reports</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- High Level Score Cards Strip -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="score-metric-card">
                    <span class="metric-label">Overall 360° Rating</span>
                    @if($participant->overall_score)
                        <h3 class="fw-bold text-primary mb-0">
                            ★ {{ number_format($participant->overall_score, 1) }}
                            <small class="fs-13 text-muted fw-normal">/ 5.0</small>
                        </h3>
                    @else
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 fs-12 rounded-2 fw-semibold">
                            Awaiting Ratings
                        </span>
                    @endif
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="score-metric-card">
                    <span class="metric-label">Self-Assessment</span>
                    @if($participant->self_score)
                        <h3 class="fw-bold text-dark mb-0">
                            ★ {{ number_format($participant->self_score, 1) }}
                            <small class="fs-13 text-muted fw-normal">/ 5.0</small>
                        </h3>
                    @else
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1.5 fs-12 rounded-2 fw-semibold">
                            Awaiting Self-Review
                        </span>
                    @endif
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="score-metric-card">
                    <span class="metric-label">Manager Rating</span>
                    @if($participant->manager_score)
                        <h3 class="fw-bold text-info mb-0">
                            ★ {{ number_format($participant->manager_score, 1) }}
                            <small class="fs-13 text-muted fw-normal">/ 5.0</small>
                        </h3>
                    @else
                        <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1.5 fs-12 rounded-2 fw-semibold">
                            Awaiting Manager
                        </span>
                    @endif
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="score-metric-card">
                    <span class="metric-label">Peers & Team Average</span>
                    @if($participant->peer_score)
                        <h3 class="fw-bold text-success mb-0">
                            ★ {{ number_format($participant->peer_score, 1) }}
                            <small class="fs-13 text-muted fw-normal">/ 5.0</small>
                        </h3>
                    @else
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5 fs-12 rounded-2 fw-semibold">
                            Awaiting Peer Reviews
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Visual Multi-Rater Radar Chart & Strengths Grid -->
        <div class="row g-4 mb-4">
            <!-- Radar Chart Container -->
            <div class="col-xl-7 col-12">
                <x-ui.card title="360° Competency Radar (Multi-Rater Overlay)" stretch>
                    <x-slot:headerAction>
                        <span class="text-muted fs-11">Self vs Manager vs Peers vs Direct Reports</span>
                    </x-slot:headerAction>
                    <div id="feedback360RadarChart" style="min-height: 380px;"></div>
                </x-ui.card>
            </div>

            <!-- Blind Spots & Hidden Strengths Callouts -->
            <div class="col-xl-5 col-12">
                <x-ui.card title="Perception Gap & Insights" stretch>
                    <!-- Blind Spots -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="feather-alert-triangle text-warning fs-13"></i>
                            <span class="text-warning fs-11 fw-bold text-uppercase" style="letter-spacing: 0.5px;">
                                Potential Blind Spots (Self > Others)
                            </span>
                        </div>
                        @forelse($blindSpots as $bs)
                            <div class="blind-spot-card mb-3">
                                <div class="d-flex justify-content-between align-items-center fw-bold text-dark mb-1 fs-13">
                                    <span>{{ $bs['competency'] }}</span>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle fw-bold fs-11">+{{ number_format($bs['gap'], 1) }} Gap</span>
                                </div>
                                <div class="text-muted fs-12">
                                    Self: <strong class="text-dark">{{ number_format($bs['self_score'], 1) }}</strong> vs Others: <strong class="text-dark">{{ number_format($bs['others_score'], 1) }}</strong>
                                </div>
                            </div>
                        @empty
                            <div class="insight-empty-card mb-2">
                                <i class="feather-check-circle text-success fs-15 mt-0.5 flex-shrink-0"></i>
                                <span class="text-muted fs-12 lh-base">No significant blind spots identified. Self-evaluation aligns closely with team feedback.</span>
                            </div>
                        @endforelse
                    </div>

                    <!-- Hidden Strengths -->
                    <div class="mb-2">
                        <div class="d-flex align-items-center gap-1.5 mb-2">
                            <i class="feather-star text-success fs-13"></i>
                            <span class="text-success fs-11 fw-bold text-uppercase" style="letter-spacing: 0.5px;">
                                Recognized Hidden Strengths (Others > Self)
                            </span>
                        </div>
                        @forelse($hiddenStrengths as $hs)
                            <div class="strength-card mb-3">
                                <div class="d-flex justify-content-between align-items-center fw-bold text-dark mb-1 fs-13">
                                    <span>{{ $hs['competency'] }}</span>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold fs-11">+{{ number_format($hs['gap'], 1) }} Superpower</span>
                                </div>
                                <div class="text-muted fs-12">
                                    Self: <strong class="text-dark">{{ number_format($hs['self_score'], 1) }}</strong> vs Others: <strong class="text-dark">{{ number_format($hs['others_score'], 1) }}</strong>
                                </div>
                            </div>
                        @empty
                            <div class="insight-empty-card">
                                <i class="feather-info text-primary fs-15 mt-0.5 flex-shrink-0"></i>
                                <span class="text-muted fs-12 lh-base">No significant unperceived hidden strengths identified.</span>
                            </div>
                        @endforelse
                    </div>
                </x-ui.card>
            </div>
        </div>

        <!-- Detailed Competency Breakdown Table -->
        <div class="card border rounded-3 shadow-none mb-4 overflow-hidden">
            <div class="card-header bg-light py-3 px-3.5 d-flex align-items-center justify-content-between border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <i class="feather-grid text-primary fs-15"></i>
                    <h6 class="fw-bold text-dark mb-0 fs-14">
                        Detailed Competency Scores & Perception Gap Matrix
                    </h6>
                </div>
                <span class="text-muted fs-12">Scored on a 1.0 &ndash; 5.0 scale</span>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0 fs-13">
                    <thead style="background: #f8fafc;">
                        <tr>
                            <th style="width: 38%; min-width: 240px; padding: 12px 16px; border-bottom: 2px solid #e2e8f0;" class="text-uppercase fs-11 fw-bold text-muted">Competency & Category</th>
                            <th style="width: 10%; padding: 12px 8px; border-bottom: 2px solid #e2e8f0;" class="text-center text-uppercase fs-11 fw-bold text-muted">Self</th>
                            <th style="width: 10%; padding: 12px 8px; border-bottom: 2px solid #e2e8f0;" class="text-center text-uppercase fs-11 fw-bold text-muted">Manager</th>
                            <th style="width: 10%; padding: 12px 8px; border-bottom: 2px solid #e2e8f0;" class="text-center text-uppercase fs-11 fw-bold text-muted">Peers</th>
                            <th style="width: 11%; padding: 12px 8px; border-bottom: 2px solid #e2e8f0;" class="text-center text-uppercase fs-11 fw-bold text-muted">Direct Reports</th>
                            <th style="width: 10%; padding: 12px 8px; border-bottom: 2px solid #e2e8f0; background: rgba(var(--bs-primary-rgb), 0.05);" class="text-center text-uppercase fs-11 fw-bold text-primary">Others Avg</th>
                            <th style="width: 11%; padding: 12px 8px; border-bottom: 2px solid #e2e8f0;" class="text-center text-uppercase fs-11 fw-bold text-muted">Perception Gap</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($competencyScores as $cs)
                            <tr>
                                <td style="padding: 14px 16px;">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="badge bg-light text-dark border rounded-pill px-2.5 py-0.5 fs-11 fw-semibold">
                                            {{ $cs['category'] }}
                                        </span>
                                    </div>
                                    <strong class="text-dark d-block fs-13 mb-0.5">{{ $cs['name'] }}</strong>
                                    @if(!empty($cs['description']))
                                        <span class="text-muted fs-12 lh-sm d-block">{{ Str::limit($cs['description'], 85) }}</span>
                                    @endif
                                </td>
                                <td class="text-center" style="padding: 14px 8px;">
                                    @if($cs['self_score'] !== null)
                                        <span class="fw-bold text-dark fs-13">{{ number_format($cs['self_score'], 1) }}</span>
                                    @else
                                        <span class="text-muted fs-13">&mdash;</span>
                                    @endif
                                </td>
                                <td class="text-center" style="padding: 14px 8px;">
                                    @if($cs['manager_score'] !== null)
                                        <span class="fw-bold text-dark fs-13">{{ number_format($cs['manager_score'], 1) }}</span>
                                    @else
                                        <span class="text-muted fs-13">&mdash;</span>
                                    @endif
                                </td>
                                <td class="text-center" style="padding: 14px 8px;">
                                    @if($cs['peer_score'] !== null)
                                        <span class="fw-bold text-dark fs-13">{{ number_format($cs['peer_score'], 1) }}</span>
                                    @else
                                        <span class="text-muted fs-13">&mdash;</span>
                                    @endif
                                </td>
                                <td class="text-center" style="padding: 14px 8px;">
                                    @if($cs['direct_report_score'] !== null)
                                        <span class="fw-bold text-dark fs-13">{{ number_format($cs['direct_report_score'], 1) }}</span>
                                    @else
                                        <span class="text-muted fs-13">&mdash;</span>
                                    @endif
                                </td>
                                <td class="text-center" style="padding: 14px 8px; background: rgba(var(--bs-primary-rgb), 0.02);">
                                    @if($cs['others_avg'] !== null)
                                        <span class="fw-bold text-primary fs-13">{{ number_format($cs['others_avg'], 1) }}</span>
                                    @else
                                        <span class="text-muted fs-13">&mdash;</span>
                                    @endif
                                </td>
                                <td class="text-center" style="padding: 14px 8px;">
                                    @if($cs['gap'] !== null)
                                        @if($cs['gap'] > 0.5)
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle fw-bold px-2 py-1 fs-11">
                                                +{{ number_format($cs['gap'], 1) }}
                                            </span>
                                        @elseif($cs['gap'] < -0.5)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold px-2 py-1 fs-11">
                                                {{ number_format($cs['gap'], 1) }}
                                            </span>
                                        @else
                                            <span class="badge bg-light text-secondary border px-2 py-1 fs-11">
                                                {{ number_format($cs['gap'], 1) }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-muted fs-13">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Anonymized Qualitative Feedback Responses -->
        @if(!empty($qualitativeFeedback))
            <div class="card border rounded-3 shadow-none mb-4 overflow-hidden">
                <div class="card-header bg-light py-3 px-3.5 d-flex align-items-center justify-content-between border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <i class="feather-message-square text-primary fs-15"></i>
                        <h6 class="fw-bold text-dark mb-0 fs-14">
                            Qualitative Comments & Written Feedback
                        </h6>
                    </div>
                    <span class="badge bg-light text-muted border rounded-pill px-2.5 py-1 fs-11">Anonymized Multi-Rater Input</span>
                </div>
                <div class="card-body p-4">
                    @foreach($qualitativeFeedback as $qf)
                        <div class="mb-4 pb-3 border-bottom">
                            <h6 class="fw-bold text-dark mb-3 fs-14">
                                <i class="feather-help-circle text-primary me-1"></i> {{ $qf['question'] }}
                            </h6>
                            <div class="row g-3">
                                @foreach($qf['responses'] as $resp)
                                    <div class="col-md-6">
                                        <div class="p-3 bg-light rounded-3 border h-100 fs-13">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-0.5 fs-11">
                                                    {{ ucfirst(str_replace('_', ' ', $resp['reviewer_type'])) }}
                                                </span>
                                                <span class="text-muted fs-11 fst-italic">{{ $resp['reviewer_name'] }}</span>
                                            </div>
                                            <p class="text-dark mb-0 lh-base">&ldquo;{{ $resp['text'] }}&rdquo;</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Manager Calibration Commentary & Development Goals -->
        <div class="card border rounded-3 shadow-none p-4 bg-light">
            <h6 class="fw-bold text-dark mb-3 fs-15 d-flex align-items-center gap-2">
                <i class="feather-file-text text-primary"></i> Executive Summary & Professional Development Plan
            </h6>

            @if($participant->manager_summary || $participant->development_plan)
                <div class="mb-3">
                    <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1.5" style="letter-spacing: 0.5px;">Manager Commentary:</span>
                    <p class="text-dark fs-13 bg-white p-3 rounded-3 border mb-0 lh-base">{{ $participant->manager_summary ?? 'No manager comments added yet.' }}</p>
                </div>
                <div>
                    <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1.5" style="letter-spacing: 0.5px;">Key Development Goals:</span>
                    <p class="text-dark fs-13 bg-white p-3 rounded-3 border mb-0 lh-base">{{ $participant->development_plan ?? 'No specific development plan recorded.' }}</p>
                </div>
            @else
                <p class="text-muted fs-13 mb-3">
                    Direct managers can record calibration notes and development action items before releasing the report to the employee.
                </p>
                @if($canPublish)
                    <x-ui.button variant="primary" icon="feather-edit-3" class="fw-bold" data-bs-toggle="modal" data-bs-target="#publishReportModal">
                        Add Manager Summary & Publish Report
                    </x-ui.button>
                @elseif($cycle?->status === 'in_progress')
                    <div class="d-inline-flex align-items-center gap-2 bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 rounded-3 fs-12 fw-semibold">
                        <i class="feather-clock fs-14"></i>
                        <span>Feedback collection is currently In Progress. Manager calibration and report publishing will unlock in the Manager Review stage.</span>
                    </div>
                @elseif($cycle?->status === 'draft' || $cycle?->status === 'nomination')
                    <div class="d-inline-flex align-items-center gap-2 bg-light text-muted border px-3 py-2 rounded-3 fs-12">
                        <i class="feather-info fs-14"></i>
                        <span>Cycle is currently in {{ ucfirst($cycle->status) }} stage. Calibration will open once feedback collection concludes.</span>
                    </div>
                @endif
            @endif
        </div>

    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: CALIBRATE & PUBLISH REPORT -->
<!-- ========================================================================= -->
<x-ui.modal id="publishReportModal" title="Calibrate & Publish 360° Report" size="lg" :centered="true" :showFooter="false">
    <form action="{{ route('hrms.feedback360.report.publish', $participant->id) }}" method="POST">
        @csrf
        <p class="fs-13 text-muted mb-3">
            Once published, this 360 feedback evaluation and radar chart will become visible to <strong>{{ $participant->employee?->full_name }}</strong> on their employee portal.
        </p>

        <x-ui.modal-form-ui type="textarea" label="Manager Executive Summary" name="manager_summary" rows="3" placeholder="Highlight key takeaways, performance calibration, and overarching feedback..." :value="$participant->manager_summary" />

        <x-ui.modal-form-ui type="textarea" label="Professional Development Plan & Action Items" name="development_plan" rows="3" placeholder="Outline 2-3 specific development goals or training focus areas for the upcoming quarter..." :value="$participant->development_plan" />

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <x-ui.button variant="light" size="sm" class="border" data-bs-dismiss="modal">Cancel</x-ui.button>
            <x-ui.button variant="success" size="sm" type="submit" class="fw-bold" icon="feather-check">
                Publish & Share Report
            </x-ui.button>
        </div>
    </form>
</x-ui.modal>

@push('scripts')
<script src="{{ asset('assets/vendors/js/apexcharts.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const radarLabels = @json($radarData['labels'] ?? []);
        const selfData = @json($radarData['self'] ?? []);
        const othersData = @json($radarData['othersAvg'] ?? []);
        const managerData = @json($radarData['manager'] ?? []);

        const themePrimary = (getComputedStyle(document.documentElement).getPropertyValue('--bs-primary') || '#3454d1').trim();

        const options = {
            series: [
                {
                    name: 'Self Evaluation',
                    data: selfData
                },
                {
                    name: 'Others Average',
                    data: othersData
                },
                {
                    name: 'Manager Review',
                    data: managerData
                }
            ],
            chart: {
                height: 380,
                type: 'radar',
                toolbar: { show: false },
                dropShadow: {
                    enabled: true,
                    blur: 1,
                    left: 1,
                    top: 1
                }
            },
            stroke: {
                width: 2
            },
            fill: {
                opacity: 0.15
            },
            markers: {
                size: 4
            },
            xaxis: {
                categories: radarLabels
            },
            yaxis: {
                show: false,
                min: 0,
                max: 5
            },
            colors: [themePrimary, '#10b981', '#f59e0b', '#8b5cf6'],
            legend: {
                position: 'bottom',
                horizontalAlign: 'center'
            }
        };

        const chartElement = document.querySelector("#feedback360RadarChart");
        if (chartElement && typeof ApexCharts !== 'undefined') {
            const chart = new ApexCharts(chartElement, options);
            chart.render();
        }
    });
</script>
@endpush

@endsection
