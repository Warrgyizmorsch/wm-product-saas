@extends('layouts.duralux')

@section('title', $cycle->name . ' | 360° Feedback')
@section('page-title', 'Feedback Cycle Dashboard')
@section('breadcrumb', 'HRMS / 360° Feedback / Cycle Dashboard')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="light" icon="feather-arrow-left" href="{{ route('hrms.feedback360.index', ['active_tab' => 'cycles']) }}" class="border fw-semibold">
            Back to Cycles
        </x-ui.button>
        <x-ui.button variant="light" icon="feather-bell" class="border fw-semibold" data-bs-toggle="modal" data-bs-target="#bulkRemindModal">
            Send Reminders
        </x-ui.button>
        <x-ui.button variant="primary" icon="feather-user-plus" class="fw-bold" data-bs-toggle="modal" data-bs-target="#addParticipantsModal">
            Enroll Reviewees
        </x-ui.button>
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
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
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
                    Period: <strong>{{ $cycle->start_date->format('M d, Y') }}</strong> to <strong>{{ $cycle->end_date->format('M d, Y') }}</strong>
                    @if($cycle->submission_deadline)
                        &bull; Submission Due: <strong class="text-danger">{{ $cycle->submission_deadline->format('M d, Y') }}</strong>
                    @endif
                </div>
            </div>

            <!-- Stage Transition Controller -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-light text-muted border fs-11 py-1.5 px-2.5 rounded-pill" title="Lifecycle advances automatically on deadlines and 100% review completion. You can also manually switch stages anytime.">
                    <i class="feather-zap text-warning me-1"></i>Auto-Progression Active
                </span>
                <span class="text-muted fs-12 fw-bold text-uppercase ms-1">Stage:</span>
                <div class="dropdown">
                    @php
                        $stageDetails = [
                            'draft'       => ['label' => 'Draft', 'desc' => 'Setup & participant roster', 'dot' => '#64748b'],
                            'nomination'  => ['label' => 'Nomination Phase', 'desc' => 'Employees nominate peer raters', 'dot' => '#0ea5e9'],
                            'in_progress' => ['label' => 'In Progress', 'desc' => 'Feedback collection underway', 'dot' => 'var(--bs-primary)'],
                            'review'      => ['label' => 'Manager Review', 'desc' => 'Calibration & assessment', 'dot' => '#f59e0b'],
                            'completed'   => ['label' => 'Completed', 'desc' => 'Published & shared with reviewees', 'dot' => '#10b981'],
                            'closed'      => ['label' => 'Closed', 'desc' => 'Cycle locked & archived', 'dot' => '#334155'],
                        ];
                        $currentStage = $stageDetails[$cycle->status] ?? [
                            'label' => ucfirst($cycle->status),
                            'desc' => '',
                            'dot' => '#6c757d',
                        ];
                    @endphp

                    <button class="stage-dropdown-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="stage-dot" style="background-color: {{ $currentStage['dot'] }};"></span>
                        <span>{{ $currentStage['label'] }}</span>
                    </button>

                    <div class="dropdown-menu dropdown-menu-end stage-dropdown-menu">
                        <div class="px-2 py-1 pb-2 border-bottom mb-2">
                            <span class="fs-11 text-uppercase fw-bold text-muted" style="letter-spacing: 0.5px;">Update Lifecycle Stage</span>
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
                </div>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="p-3 bg-light border rounded d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1">Enrolled Reviewees</span>
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
                        <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1">Total Assignments</span>
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
                        <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1">Reviews Completed</span>
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
                        <span class="text-muted fs-11 fw-bold text-uppercase d-block mb-1">Published Reports</span>
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
                        Competencies Evaluated in this Cycle ({{ $cycleCompetencies->count() }})
                    </span>
                    <span class="badge bg-white text-muted border fs-11 fw-normal">
                        {{ $questions->count() }} Total Questions
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
                <h6 class="fw-bold text-dark mb-0">Reviewees Roster & Feedback Status</h6>
                <span class="text-muted fs-13">All enrolled employees with their assigned multi-directional raters.</span>
            </div>
        </div>

        @if($participants->isEmpty())
            <div class="text-center py-5 border rounded bg-light">
                <div class="avatar-initials mx-auto mb-3" style="width: 50px; height: 50px; font-size: 20px;">
                    <i class="feather-users"></i>
                </div>
                <h6 class="fw-bold text-dark">No Reviewees Enrolled Yet</h6>
                <p class="text-muted fs-13 mb-3">Enroll employees into this cycle to automatically assign managers and direct reports.</p>
                <x-ui.button variant="primary" icon="feather-user-plus" class="fw-bold" data-bs-toggle="modal" data-bs-target="#addParticipantsModal">
                    Enroll Employees
                </x-ui.button>
            </div>
        @else
            <div class="table-responsive border rounded">
                <table class="table table-hover align-middle mb-0 fs-13">
                    <thead class="table-light">
                        <tr>
                            <th>Reviewee (Subject)</th>
                            <th>Direct Manager</th>
                            <th>Raters Progress</th>
                            <th>Overall Score</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
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
                                            <span class="text-muted fs-11 d-block">{{ $p->employee?->designation->name ?? 'Staff' }} &bull; {{ $p->employee?->department->name ?? 'Dept' }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-dark fw-semibold">{{ $p->manager?->full_name ?? 'Unassigned' }}</span>
                                </td>
                                <td style="min-width: 180px;">
                                    <div class="d-flex justify-content-between fs-11 text-muted mb-1">
                                        <span>{{ $completedRaters }} of {{ $totalRaters }} Raters</span>
                                        <strong class="{{ $pPct === 100 ? 'text-success' : 'text-dark' }}">{{ $pPct }}%</strong>
                                    </div>
                                    <div class="progress" style="height: 6px; border-radius: 4px; background-color: #f1f5f9;">
                                        <div class="progress-bar {{ $pPct === 100 ? 'bg-success' : 'bg-primary' }}" style="width: {{ $pPct }}%; border-radius: 4px;"></div>
                                    </div>
                                    @if($pPct === 100)
                                        <div class="mt-1.5">
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5 fs-11 fw-semibold d-inline-flex align-items-center gap-1"
                                                  title="All raters (Self, Manager, Peers) have submitted their feedback.">
                                                <i class="feather-check-circle" style="font-size: 11px;"></i> All {{ $totalRaters }} Reviews Completed
                                            </span>
                                        </div>
                                    @else
                                        <div class="mt-1.5 d-flex gap-1 flex-wrap">
                                            @foreach($p->nominations as $nom)
                                                @php
                                                    $typeLabel = match($nom->reviewer_type) {
                                                        'self'          => 'Self',
                                                        'manager'       => 'Manager',
                                                        'direct_report' => 'Report',
                                                        'peer'          => 'Peer',
                                                        default         => ucfirst($nom->reviewer_type),
                                                    };
                                                    $isDone = ($nom->status === 'completed');
                                                @endphp
                                                <span class="badge {{ $isDone ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-light text-muted border' }} rounded-pill px-2 py-0.5 d-inline-flex align-items-center" 
                                                      title="{{ $typeLabel }}: {{ $nom->reviewer?->full_name }} ({{ $isDone ? 'Submitted' : 'Pending' }})" 
                                                      style="font-size: 10px; font-weight: 600;">
                                                    <i class="feather-{{ $isDone ? 'check' : 'clock' }} me-1" style="font-size: 9px;"></i>
                                                    {{ $typeLabel }}
                                                    @if(!$isDone && $isHrAdmin && $nom->reviewer_type !== 'self')
                                                        <form action="{{ route('hrms.feedback360.nominations.destroy', $nom->id) }}" method="POST" class="d-inline ms-1 p-0 m-0" onsubmit="return confirm('Remove {{ addslashes($nom->reviewer?->full_name ?? 'this reviewer') }} ({{ $typeLabel }}) from this review roster?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="border-0 bg-transparent text-danger p-0 ms-0.5" style="font-size: 11px; line-height: 1; cursor: pointer;" title="Remove this rater">
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
                                        @if($isHrAdmin && in_array($cycle->status, ['draft', 'nomination', 'in_progress']))
                                            <x-ui.button variant="light" size="sm" icon="feather-user-plus" class="border px-2 py-1 text-dark fw-semibold" data-bs-toggle="modal" data-bs-target="#addRaterModal{{ $p->id }}" title="Assign individual rater">
                                                Rater
                                            </x-ui.button>
                                        @endif
                                        <x-ui.button variant="light" size="sm" icon="feather-pie-chart" class="border px-2.5 py-1 text-primary fw-semibold" href="{{ route('hrms.feedback360.report', $p->id) }}">
                                            360 Report
                                        </x-ui.button>
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
<x-ui.modal id="addParticipantsModal" title="Enroll Reviewees into Cycle" size="md" :centered="true" :showFooter="false">
    <form action="{{ route('hrms.feedback360.cycles.participants', $cycle->id) }}" method="POST">
        @csrf
        <x-ui.modal-form-ui type="select" label="Select Employees" name="employee_ids[]" :multiple="true" :searchable="true" :required="true" helperText="The system will automatically assign self, manager, and direct-report evaluation cards.">
            @foreach($employees as $emp)
                <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->designation->name ?? 'Staff' }} - {{ $emp->department->name ?? 'Team' }})</option>
            @endforeach
        </x-ui.modal-form-ui>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <x-ui.button variant="light" size="sm" class="border" data-bs-dismiss="modal">Cancel</x-ui.button>
            <x-ui.button variant="primary" size="sm" type="submit" class="fw-bold">Enroll Selected Employees</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- ========================================================================= -->
<!-- MODAL: BULK REMIND -->
<!-- ========================================================================= -->
<x-ui.modal id="bulkRemindModal" title="Send Feedback Reminders" size="md" :centered="true" :showFooter="false">
    <form action="{{ route('hrms.feedback360.cycles.bulk-remind', $cycle->id) }}" method="POST">
        @csrf
        <p class="fs-13 text-dark">
            This will dispatch reminder notifications to all <strong>{{ $stats['pending_raters'] }}</strong> reviewers who have not yet completed their feedback submissions.
        </p>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <x-ui.button variant="light" size="sm" class="border" data-bs-dismiss="modal">Cancel</x-ui.button>
            <x-ui.button variant="primary" size="sm" type="submit" class="fw-bold">Send Reminders Now</x-ui.button>
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
    <x-ui.modal id="addRaterModal{{ $p->id }}" title="Assign Rater for {{ $p->employee?->full_name }}" size="md" :centered="true" :showFooter="false">
        <form action="{{ route('hrms.feedback360.participants.add-rater', $p->id) }}" method="POST">
            @csrf

            <div class="mb-3">
                <label class="form-label text-dark fs-12 fw-semibold">Relationship / Reviewer Type <span class="text-danger">*</span></label>
                <select name="reviewer_type" class="form-select form-select-sm fs-12" required>
                    <option value="peer" selected>Peer Reviewer (Colleague)</option>
                    <option value="direct_report">Direct Report (Subordinate / Upward Feedback)</option>
                    <option value="manager">Manager / Evaluator</option>
                </select>
                <small class="text-muted fs-11 mt-1 d-block">Determines scoring perspective in 360 gap analysis.</small>
            </div>

            <div class="mb-3">
                <label class="form-label text-dark fs-12 fw-semibold">Select Employee <span class="text-danger">*</span></label>
                <select name="reviewer_id" class="form-select form-select-sm fs-12" required>
                    <option value="">-- Choose Employee --</option>
                    @if($isManagerReviewee)
                        <optgroup label="Direct Reports (Team Members)">
                            @foreach($directReports as $dr)
                                <option value="{{ $dr->id }}" {{ in_array($dr->id, $assignedIds) ? 'disabled' : '' }}>
                                    {{ $dr->full_name }} ({{ $dr->designation->name ?? 'Staff' }}) {{ in_array($dr->id, $assignedIds) ? '[Already Assigned]' : '' }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endif
                    <optgroup label="All Organization Employees">
                        @foreach($employees as $emp)
                            @if($emp->id !== $p->employee_id)
                                <option value="{{ $emp->id }}" {{ in_array($emp->id, $assignedIds) ? 'disabled' : '' }}>
                                    {{ $emp->full_name }} ({{ $emp->designation->name ?? 'Staff' }} - {{ $emp->department->name ?? 'Team' }}) {{ in_array($emp->id, $assignedIds) ? '[Already Assigned]' : '' }}
                                </option>
                            @endif
                        @endforeach
                    </optgroup>
                </select>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <x-ui.button variant="light" size="sm" class="border" data-bs-dismiss="modal">Cancel</x-ui.button>
                <x-ui.button variant="primary" size="sm" type="submit" class="fw-bold">Assign Reviewer</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endforeach

@endsection
