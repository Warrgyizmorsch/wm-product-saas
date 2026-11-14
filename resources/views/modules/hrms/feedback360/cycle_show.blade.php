@extends('layouts.duralux')

@section('title', __('hrms.feedback_360.title_cycle_show', ['name' => $cycle->name]))
@section('page-title', __('hrms.feedback_360.page_title_cycle_show'))
@section('breadcrumb', __('hrms.feedback_360.breadcrumb_cycle_show'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="light" icon="feather-arrow-left" href="{{ route('hrms.feedback360.index', ['active_tab' => 'cycles']) }}" class="border fw-semibold">
            {{ __('hrms.feedback_360.btn_back_to_cycles') }}
        </x-ui.button>
        @if($cycle->capabilities['can_bulk_remind'] ?? false)
            <x-ui.button variant="light" icon="feather-bell" class="border fw-semibold" data-bs-toggle="modal" data-bs-target="#bulkRemindModal">
                {{ __('hrms.feedback_360.btn_send_reminders') }}
            </x-ui.button>
        @endif
        @if($cycle->capabilities['can_add_participants'] ?? false)
            <x-ui.button variant="primary" icon="feather-user-plus" class="fw-bold" data-bs-toggle="modal" data-bs-target="#addParticipantsModal">
                {{ __('hrms.feedback_360.btn_enroll_reviewees') }}
            </x-ui.button>
        @endif
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .avatar-initials {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background-color: rgba(var(--bs-primary-rgb), 0.12) !important;
            color: var(--bs-primary) !important;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
            flex-shrink: 0;
        }
        .stage-dropdown-btn {
            height: 34px;
            padding: 0 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #cbd5e1;
            background-color: #ffffff;
            color: #1e293b;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: all 0.2s;
        }
        .stage-dropdown-btn:hover, .stage-dropdown-btn:focus {
            background-color: #f8fafc;
            border-color: #94a3b8;
            color: #0f172a;
        }
        .stage-dropdown-menu {
            width: 310px;
            padding: 8px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.12), 0 8px 10px -6px rgba(0, 0, 0, 0.08);
            background: #ffffff;
        }
        .stage-item-btn {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 9px 12px;
            border-radius: 8px;
            border: none;
            background: transparent;
            text-align: left;
            cursor: pointer;
            transition: background 0.15s ease;
            margin-bottom: 2px;
        }
        .stage-item-btn:hover {
            background-color: #f1f5f9;
        }
        .stage-item-btn.active {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .stage-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
            display: inline-block;
        }
        .select2-dropdown {
            z-index: 99999 !important;
        }
        .select2-container--open {
            z-index: 99999 !important;
        }
        [id^="addRaterModal"] .modal-content,
        [id^="addRaterModal"] .modal-body,
        #addParticipantsModal .modal-content,
        #addParticipantsModal .modal-body {
            overflow: visible !important;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script>
        $(document).ready(function () {
            // Prevent Bootstrap modal from blocking focus on Select2 dropdown attached to body
            if (typeof $.fn.modal !== 'undefined' && $.fn.modal.Constructor) {
                $.fn.modal.Constructor.prototype._enforceFocus = function () {};
            }
            document.addEventListener('focusin', function (e) {
                if (e.target && (e.target.closest('.select2-container') || e.target.closest('.select2-search__field') || e.target.closest('.select2-dropdown'))) {
                    e.stopImmediatePropagation();
                }
            }, true);

            // Initialize rater select2 dropdowns attached to body so options extend outside the modal
            $(document).on('show.bs.modal shown.bs.modal', '[id^="addRaterModal"], #addParticipantsModal', function () {
                var $modal = $(this);
                $modal.find('.rater-select2-body').each(function () {
                    var $select = $(this);
                    if (!$select.hasClass('select2-hidden-accessible')) {
                        $select.select2({
                            theme: "bootstrap-5",
                            width: "100%",
                            dropdownParent: $(document.body)
                        });
                    }
                });
            });

            // Close open select2 dropdown when modal hides
            $(document).on('hide.bs.modal hidden.bs.modal', '[id^="addRaterModal"], #addParticipantsModal', function () {
                $('.rater-select2-body').each(function () {
                    if ($(this).data('select2')) {
                        $(this).select2('close');
                    }
                });
            });
        });
    </script>
@endpush

@section('content')
<div class="container-fluid p-0">

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

    <!-- ERP Single Panel Workspace -->
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">

        <!-- Cycle Header & Stage Control -->
        <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h5 class="fw-bold text-dark mb-0 fs-16">{{ $cycle->name }}</h5>
                    <span class="badge bg-light text-dark border"><code>{{ $cycle->code }}</code></span>
                </div>
                <div class="text-muted fs-13">
                    {{ __('hrms.feedback_360.period_colon') }} <strong>{{ $cycle->start_date->format('M d, Y') }}</strong> {{ __('hrms.common.to') ?? 'to' }} <strong>{{ $cycle->end_date->format('M d, Y') }}</strong>
                    @if($cycle->submission_deadline)
                        &bull; {{ __('hrms.feedback_360.submission_due_colon') }} <strong class="text-danger">{{ $cycle->submission_deadline->format('M d, Y') }}</strong>
                    @endif
                </div>
            </div>

            <!-- Stage Transition Controller -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-light text-muted border fs-11 py-1.5 px-2.5 rounded-pill" title="Lifecycle advances automatically on deadlines and 100% review completion. You can also manually switch stages anytime.">
                    <i class="feather-zap text-warning me-1"></i>{{ __('hrms.feedback_360.auto_progression_active') }}
                </span>
                <span class="text-muted fs-12 fw-bold text-uppercase ms-1">{{ __('hrms.feedback_360.stage_colon') }}</span>
                <div class="dropdown">
                    @php
                        $stageDetails = [
                            'draft'       => ['label' => __('hrms.feedback_360.stage_draft'), 'desc' => __('hrms.feedback_360.stage_draft_desc'), 'dot' => '#64748b'],
                            'nomination'  => ['label' => __('hrms.feedback_360.stage_nomination'), 'desc' => __('hrms.feedback_360.stage_nomination_desc'), 'dot' => '#0ea5e9'],
                            'in_progress' => ['label' => __('hrms.feedback_360.stage_in_progress'), 'desc' => __('hrms.feedback_360.stage_in_progress_desc'), 'dot' => 'var(--bs-primary)'],
                            'review'      => ['label' => __('hrms.feedback_360.stage_review'), 'desc' => __('hrms.feedback_360.stage_review_desc'), 'dot' => '#f59e0b'],
                            'completed'   => ['label' => __('hrms.feedback_360.stage_completed'), 'desc' => __('hrms.feedback_360.stage_completed_desc'), 'dot' => '#10b981'],
                            'closed'      => ['label' => __('hrms.feedback_360.stage_closed'), 'desc' => __('hrms.feedback_360.stage_closed_desc'), 'dot' => '#334155'],
                        ];
                        $currentStage = $stageDetails[$cycle->status] ?? [
                            'label' => ucfirst($cycle->status),
                            'desc' => '',
                            'dot' => '#6c757d',
                        ];
                    @endphp

                    @if($cycle->capabilities['can_launch'] ?? false)
                        <button class="stage-dropdown-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="stage-dot" style="background-color: {{ $currentStage['dot'] }};"></span>
                            <span>{{ $currentStage['label'] }}</span>
                        </button>

                        <div class="dropdown-menu dropdown-menu-end stage-dropdown-menu">
                            <div class="px-2 py-1 pb-2 border-bottom mb-2">
                                <span class="fs-11 text-uppercase fw-bold text-muted" style="letter-spacing: 0.5px;">{{ __('hrms.feedback_360.update_lifecycle_stage') }}</span>
                            </div>
                            
                            @foreach($stageDetails as $stageKey => $stageInfo)
                                <form action="{{ route('hrms.feedback360.cycles.launch', $cycle->id) }}" method="POST" class="m-0 p-0">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $stageKey }}">
                                    <button type="submit" class="stage-item-btn {{ $cycle->status === $stageKey ? 'active' : '' }}">
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="stage-dot" style="background-color: {{ $stageInfo['dot'] }};"></span>
                                            <div>
                                                <div class="fs-13 fw-bold text-dark lh-sm">{{ $stageInfo['label'] }}</div>
                                                <div class="fs-11 text-muted fw-normal">{{ $stageInfo['desc'] }}</div>
                                            </div>
                                        </div>
                                        @if($cycle->status === $stageKey)
                                            <i class="feather-check text-primary fs-14 ms-2"></i>
                                        @endif
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    @else
                        <span class="stage-dropdown-btn" style="cursor: default;">
                            <span class="stage-dot" style="background-color: {{ $currentStage['dot'] }};"></span>
                            <span>{{ $currentStage['label'] }}</span>
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="p-3 bg-light border rounded d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1">{{ __('hrms.feedback_360.kpi_enrolled_reviewees') }}</span>
                        <h4 class="fw-bold mb-0 text-dark">{{ $stats['total_participants'] }}</h4>
                    </div>
                    <div class="avatar-initials bg-primary-subtle text-primary" style="width: 40px; height: 40px; font-size: 16px;">
                        <i class="feather-users"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="p-3 bg-light border rounded d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1">{{ __('hrms.feedback_360.kpi_total_assignments') }}</span>
                        <h4 class="fw-bold mb-0 text-dark">{{ $stats['total_raters'] }}</h4>
                    </div>
                    <div class="avatar-initials bg-info-subtle text-info" style="width: 40px; height: 40px; font-size: 16px;">
                        <i class="feather-layers"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="p-3 bg-light border rounded d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1">{{ __('hrms.feedback_360.kpi_reviews_completed') }}</span>
                        <h4 class="fw-bold mb-0 text-success">{{ $stats['completed_raters'] }} <small class="fs-12 text-muted fw-normal">({{ $stats['completion_rate'] }}%)</small></h4>
                    </div>
                    <div class="avatar-initials bg-success-subtle text-success" style="width: 40px; height: 40px; font-size: 16px;">
                        <i class="feather-check-circle"></i>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="p-3 bg-light border rounded d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1">{{ __('hrms.feedback_360.kpi_published_reports') }}</span>
                        <h4 class="fw-bold mb-0 text-primary">{{ $stats['published_reports'] }}</h4>
                    </div>
                    <div class="avatar-initials bg-primary-subtle text-primary" style="width: 40px; height: 40px; font-size: 16px;">
                        <i class="feather-pie-chart"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Competencies Evaluated in this Cycle -->
        @if(isset($cycleCompetencies) && $cycleCompetencies->isNotEmpty())
            <div class="mb-4 p-3 bg-light border rounded">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted fs-11 fw-bold text-uppercase" style="letter-spacing: 0.5px;">
                        {{ __('hrms.feedback_360.competencies_evaluated_in_cycle', ['count' => $cycleCompetencies->count()]) }}
                    </span>
                    <span class="badge bg-white text-muted border fs-11 fw-normal">
                        {{ __('hrms.feedback_360.total_questions_badge', ['count' => $questions->count()]) }}
                    </span>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($cycleCompetencies as $comp)
                        <div class="d-inline-flex align-items-center gap-2 px-2.5 py-1.5 bg-white border rounded shadow-xs">
                            <span class="badge bg-primary-subtle text-primary rounded-circle p-1" style="width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; font-size: 10px;">
                                <i class="feather-check"></i>
                            </span>
                            <span class="fw-semibold text-dark fs-12">{{ $comp->name }}</span>
                            <span class="badge bg-light text-muted border fs-10 px-1.5 py-0.5 rounded-pill">{{ $comp->questions_count ?? $comp->questions->count() }} Qs</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Participants & Rater Roster Table -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h6 class="fw-bold text-dark mb-0">{{ __('hrms.feedback_360.reviewees_roster_heading') }}</h6>
                <span class="text-muted fs-13">{{ __('hrms.feedback_360.reviewees_roster_subtitle') }}</span>
            </div>
        </div>

        @if($participants->isEmpty())
            <div class="text-center py-5 border rounded bg-light">
                <div class="avatar-initials mx-auto mb-3" style="width: 50px; height: 50px; font-size: 20px;">
                    <i class="feather-users"></i>
                </div>
                <h6 class="fw-bold text-dark">{{ __('hrms.feedback_360.no_reviewees_enrolled') }}</h6>
                <p class="text-muted fs-13 mb-3">{{ __('hrms.feedback_360.no_reviewees_desc') }}</p>
                <x-ui.button variant="primary" icon="feather-user-plus" class="fw-bold" data-bs-toggle="modal" data-bs-target="#addParticipantsModal">
                    {{ __('hrms.feedback_360.btn_enroll_employees') }}
                </x-ui.button>
            </div>
        @else
            <div class="table-responsive border rounded">
                <table class="table table-hover align-middle mb-0 fs-13">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('hrms.feedback_360.th_reviewee_subject') }}</th>
                            <th>{{ __('hrms.feedback_360.th_direct_manager') }}</th>
                            <th>{{ __('hrms.feedback_360.th_raters_progress') }}</th>
                            <th>{{ __('hrms.feedback_360.th_overall_score') }}</th>
                            <th>{{ __('hrms.feedback_360.th_status') }}</th>
                            <th class="text-end">{{ __('hrms.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($participants as $p)
                            @php
                                $totalRaters = $p->nominations->count();
                                $completedRaters = $p->nominations->where('status', 'completed')->count();
                                $pPct = $totalRaters > 0 ? round(($completedRaters / $totalRaters) * 100) : 0;
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-initials">
                                            {{ substr($p->employee?->first_name ?? 'E', 0, 1) }}{{ substr($p->employee?->last_name ?? '', 0, 1) }}
                                        </div>
                                        <div>
                                            <a href="{{ route('hrms.feedback360.report', $p->id) }}" class="fw-bold text-dark text-decoration-none hover-primary">
                                                {{ $p->employee?->full_name }}
                                            </a>
                                            <span class="text-muted fs-11 d-block">{{ $p->employee?->designation->name ?? __('hrms.feedback_360.staff_fallback') }} &bull; {{ $p->employee?->department->name ?? __('hrms.feedback_360.team_fallback') }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-dark fw-semibold">{{ $p->manager?->full_name ?? __('hrms.feedback_360.unassigned') }}</span>
                                </td>
                                <td style="min-width: 180px;">
                                    <div class="d-flex justify-content-between fs-11 text-muted mb-1">
                                        <span>{{ __('hrms.feedback_360.raters_progress_ratio', ['completed' => $completedRaters, 'total' => $totalRaters]) }}</span>
                                        <strong class="{{ $pPct === 100 ? 'text-success' : 'text-dark' }}">{{ $pPct }}%</strong>
                                    </div>
                                    <div class="progress" style="height: 6px; border-radius: 4px; background-color: #f1f5f9;">
                                        <div class="progress-bar {{ $pPct === 100 ? 'bg-success' : 'bg-primary' }}" style="width: {{ $pPct }}%; border-radius: 4px;"></div>
                                    </div>
                                    @if($pPct === 100)
                                        <div class="mt-1.5">
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5 fs-11 fw-semibold d-inline-flex align-items-center gap-1"
                                                  title="All raters (Self, Manager, Peers) have submitted their feedback.">
                                                <i class="feather-check-circle" style="font-size: 11px;"></i> {{ __('hrms.feedback_360.all_reviews_completed', ['total' => $totalRaters]) }}
                                            </span>
                                        </div>
                                    @else
                                        <div class="mt-1.5 d-flex gap-1 flex-wrap">
                                            @foreach($p->nominations as $nom)
                                                @php
                                                    $typeLabel = match($nom->reviewer_type) {
                                                        'self'          => __('hrms.feedback_360.rater_badge_self'),
                                                        'manager'       => __('hrms.feedback_360.rater_badge_manager'),
                                                        'direct_report' => __('hrms.feedback_360.rater_badge_report'),
                                                        'peer'          => __('hrms.feedback_360.rater_badge_peer'),
                                                        default         => ucfirst($nom->reviewer_type),
                                                    };
                                                    $isDone = ($nom->status === 'completed');
                                                @endphp
                                                <span class="badge {{ $isDone ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-light text-muted border' }} rounded-pill px-2 py-0.5 d-inline-flex align-items-center" 
                                                      title="{{ $typeLabel }}: {{ $nom->reviewer?->full_name }} ({{ $isDone ? __('hrms.feedback_360.status_submitted') : __('hrms.feedback_360.status_pending') }})" 
                                                      style="font-size: 10px; font-weight: 600;">
                                                    <i class="feather-{{ $isDone ? 'check' : 'clock' }} me-1" style="font-size: 9px;"></i>
                                                    {{ $typeLabel }}
                                                    @php
                                                        $canManageRater = $isHrAdmin || ($currentEmployee && ($p->manager_id === $currentEmployee->id || $p->employee?->reporting_manager_id === $currentEmployee->id));
                                                    @endphp
                                                    @if(!$isDone && $canManageRater && $nom->reviewer_type !== 'self')
                                                        <form action="{{ route('hrms.feedback360.nominations.destroy', $nom->id) }}" method="POST" class="d-inline ms-1 p-0 m-0" onsubmit="return confirm('{{ __('hrms.feedback_360.confirm_remove_rater', ['name' => addslashes($nom->reviewer?->full_name ?? 'this reviewer'), 'type' => $typeLabel]) }}');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="border-0 bg-transparent text-danger p-0 ms-0.5" style="font-size: 11px; line-height: 1; cursor: pointer;" title="{{ __('hrms.feedback_360.tooltip_remove_this_rater') }}">
                                                                &times;
                                                            </button>
                                                        </form>
                                                    @endif
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if($p->overall_score)
                                        <span class="badge bg-light text-dark border fs-12 px-2.5 py-1 fw-bold">
                                            <i class="feather-star text-warning me-1"></i>{{ number_format($p->overall_score, 1) }} <small class="text-muted fw-normal">/ 5.0</small>
                                        </span>
                                    @else
                                        <span class="text-muted fs-12">&mdash;</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $p->status === 'published' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} rounded-pill px-2.5 py-1">
                                        {{ ucfirst(str_replace('_', ' ', $p->status)) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1.5">
                                        @if(($p->capabilities['can_nominate_peers'] ?? false) && in_array($cycle->status, ['draft', 'nomination', 'in_progress']))
                                            <x-ui.button variant="light" size="sm" icon="feather-user-plus" class="border px-2 py-1 text-dark fw-semibold" data-bs-toggle="modal" data-bs-target="#addRaterModal{{ $p->id }}" :title="__('hrms.feedback_360.tooltip_assign_individual_rater')">
                                                {{ __('hrms.feedback_360.btn_assign_individual_rater') }}
                                            </x-ui.button>
                                        @endif
                                        @php
                                            $canViewReport = ($p->status === 'published') || $isHrAdmin || ($currentEmployee && ($p->manager_id === $currentEmployee->id || $p->employee?->reporting_manager_id === $currentEmployee->id));
                                        @endphp
                                        @if($canViewReport)
                                            <x-ui.button variant="light" size="sm" icon="feather-pie-chart" class="border px-2.5 py-1 text-primary fw-semibold" href="{{ route('hrms.feedback360.report', $p->id) }}">
                                                {{ __('hrms.feedback_360.btn_360_report') }}
                                            </x-ui.button>
                                        @else
                                            <button type="button" class="btn btn-sm btn-light border px-2.5 py-1 text-muted fw-semibold" disabled title="{{ __('hrms.feedback_360.tooltip_report_will_unlock') }}">
                                                <i class="feather-lock me-1"></i> {{ __('hrms.feedback_360.pending_report_unlock') }}
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: ADD PARTICIPANTS -->
<!-- ========================================================================= -->
<x-ui.modal id="addParticipantsModal" :title="__('hrms.feedback_360.modal_enroll_reviewees_title')" size="md" :centered="true" :scrollable="false" :showFooter="false">
    <form action="{{ route('hrms.feedback360.cycles.participants', $cycle->id) }}" method="POST">
        @csrf
        <x-ui.modal-form-ui type="select" :label="__('hrms.feedback_360.field_select_employees')" name="employee_ids[]" select2Selector="rater-select2-body" :multiple="true" :required="true" :helperText="__('hrms.feedback_360.helper_enroll_auto_cards')">
            @foreach($employees as $emp)
                <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->designation->name ?? __('hrms.feedback_360.staff_fallback') }} - {{ $emp->department->name ?? __('hrms.feedback_360.team_fallback') }})</option>
            @endforeach
        </x-ui.modal-form-ui>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <x-ui.button variant="light" size="sm" class="border" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button variant="primary" size="sm" type="submit" class="fw-bold">{{ __('hrms.feedback_360.btn_enroll_selected') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- ========================================================================= -->
<!-- MODAL: BULK REMIND -->
<!-- ========================================================================= -->
<x-ui.modal id="bulkRemindModal" :title="__('hrms.feedback_360.modal_send_reminders_title')" size="md" :centered="true" :showFooter="false">
    <form action="{{ route('hrms.feedback360.cycles.bulk-remind', $cycle->id) }}" method="POST">
        @csrf
        <p class="fs-13 text-dark">
            {{ __('hrms.feedback_360.send_reminders_confirm_msg', ['count' => $stats['pending_raters']]) }}
        </p>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <x-ui.button variant="light" size="sm" class="border" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button variant="primary" size="sm" type="submit" class="fw-bold">{{ __('hrms.feedback_360.btn_send_reminders_now') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- ========================================================================= -->
<!-- MODALS: ADD INDIVIDUAL RATER TO PARTICIPANT -->
<!-- ========================================================================= -->
@foreach($participants as $p)
    @php
        $directReports = $p->employee?->directReports ?? collect();
        $isManagerReviewee = $directReports->isNotEmpty();
        $assignedIds = $p->nominations->pluck('reviewer_id')->toArray();
    @endphp
    <x-ui.modal id="addRaterModal{{ $p->id }}" :title="__('hrms.feedback_360.modal_assign_rater_title', ['name' => $p->employee?->full_name])" size="md" :centered="true" :scrollable="false" :showFooter="false">
        <form action="{{ route('hrms.feedback360.participants.add-rater', $p->id) }}" method="POST">
            @csrf

            <x-ui.modal-form-ui type="select" :label="__('hrms.feedback_360.field_relationship_type')" name="reviewer_type" :searchable="false" :required="true" :helperText="__('hrms.feedback_360.relationship_type_helper')">
                <option value="peer" selected>{{ __('hrms.feedback_360.option_peer_colleague') }}</option>
                <option value="direct_report">{{ __('hrms.feedback_360.option_direct_report_subordinate') }}</option>
                <option value="manager">{{ __('hrms.feedback_360.option_manager_evaluator') }}</option>
            </x-ui.modal-form-ui>

            <x-ui.modal-form-ui type="select" :label="__('hrms.feedback_360.field_select_employee')" name="reviewer_ids[]" select2Selector="rater-select2-body" :multiple="true" :required="true">
                @if($isManagerReviewee)
                    <optgroup label="{{ __('hrms.feedback_360.optgroup_direct_reports') }}">
                        @foreach($directReports as $dr)
                            <option value="{{ $dr->id }}" {{ in_array($dr->id, $assignedIds) ? 'disabled' : '' }}>
                                {{ $dr->full_name }} ({{ $dr->designation->name ?? __('hrms.feedback_360.staff_fallback') }}) {{ in_array($dr->id, $assignedIds) ? __('hrms.feedback_360.already_assigned_tag') : '' }}
                            </option>
                        @endforeach
                    </optgroup>
                @endif
                <optgroup label="{{ __('hrms.feedback_360.optgroup_all_employees') }}">
                    @foreach($employees as $emp)
                        @if($emp->id !== $p->employee_id)
                            <option value="{{ $emp->id }}" {{ in_array($emp->id, $assignedIds) ? 'disabled' : '' }}>
                                {{ $emp->full_name }} ({{ $emp->designation->name ?? __('hrms.feedback_360.staff_fallback') }} - {{ $emp->department->name ?? __('hrms.feedback_360.team_fallback') }}) {{ in_array($emp->id, $assignedIds) ? __('hrms.feedback_360.already_assigned_tag') : '' }}
                            </option>
                        @endif
                    @endforeach
                </optgroup>
            </x-ui.modal-form-ui>

            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <x-ui.button variant="light" size="sm" class="border" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
                <x-ui.button variant="primary" size="sm" type="submit" class="fw-bold">{{ __('hrms.feedback_360.btn_assign_reviewer') }}</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endforeach

@endsection
