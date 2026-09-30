@extends('layouts.duralux')

@section('title', $goal->title . ' | Goals & OKRs')
@section('page-title', $goal->title)
@section('breadcrumb', 'HRMS / Goals & OKRs / ' . $goal->code)

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="light" icon="feather-arrow-left" href="{{ route('hrms.goals.index') }}" class="border fw-semibold">
            Back to Goals
        </x-ui.button>
        <x-ui.button variant="primary" icon="feather-check-square" data-bs-toggle="modal" data-bs-target="#quickCheckInModal" class="fw-bold">
            Log Check-in
        </x-ui.button>
        @if($isHrAdmin)
        <x-ui.button variant="light" icon="feather-edit-2" data-bs-toggle="modal" data-bs-target="#editGoalModal" class="border">
            Edit
        </x-ui.button>
        @endif
    </div>
@endsection

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

    <div class="row g-4">
        <!-- LEFT COLUMN: OBJECTIVE, KEY RESULTS & CHECK-IN TIMELINE -->
        <div class="col-lg-8">

            <!-- 1. Objective Overview Card -->
            <div class="card border rounded-3 shadow-sm bg-white mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary text-white fs-12 fw-bold px-2.5 py-1">{{ $goal->code }}</span>
                            @if($goal->category)
                                <span class="badge rounded-pill text-white" style="background-color: {{ $goal->category->color }}; font-size: 11px;">
                                    {{ $goal->category->name }}
                                </span>
                            @endif
                            <span class="badge bg-light text-muted border fs-11">{{ $goal->cycle?->name ?? 'General' }}</span>
                        </div>
                        <div>
                            @if($goal->health_status === 'on_track')
                                <span class="badge bg-success-subtle text-success fs-12 px-3 py-1.5 fw-bold">🟢 On Track</span>
                            @elseif($goal->health_status === 'at_risk')
                                <span class="badge bg-warning-subtle text-warning fs-12 px-3 py-1.5 fw-bold">🟡 At Risk</span>
                            @elseif($goal->health_status === 'behind')
                                <span class="badge bg-danger-subtle text-danger fs-12 px-3 py-1.5 fw-bold">🔴 Behind</span>
                            @else
                                <span class="badge bg-info-subtle text-info fs-12 px-3 py-1.5 fw-bold">🔵 Completed</span>
                            @endif
                        </div>
                    </div>

                    <h4 class="fw-extrabold text-dark mb-2">{{ $goal->title }}</h4>

                    @if($goal->parentGoal)
                        <div class="p-2.5 bg-light rounded-3 border mb-3 d-flex align-items-center gap-2">
                            <i class="feather-corner-down-right text-primary fs-14"></i>
                            <span class="text-muted fs-12">Aligned with Parent Objective:</span>
                            <a href="{{ route('hrms.goals.show', $goal->parentGoal->id) }}" class="fw-bold text-dark text-decoration-none fs-12">
                                {{ $goal->parentGoal->code }} - {{ $goal->parentGoal->title }}
                            </a>
                        </div>
                    @endif

                    @if($goal->description)
                        <p class="text-muted fs-13 mb-0">{{ $goal->description }}</p>
                    @endif
                </div>
            </div>

            <!-- 2. Measurable Key Results Card -->
            <div class="card border rounded-3 shadow-sm bg-white mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center py-3">
                    <span class="fw-bold text-dark fs-14"><i class="feather-list text-primary me-2"></i>Measurable Key Results ({{ $goal->keyResults->count() }})</span>
                    <span class="text-muted fs-12">Weighted Progress Engine</span>
                </div>
                <div class="card-body p-4">
                    @if($goal->keyResults->isEmpty())
                        <div class="text-center py-4 text-muted fs-13">
                            No specific key results defined for this objective. Overall progress is updated directly via check-ins.
                        </div>
                    @else
                        <div class="d-flex flex-column gap-3">
                            @foreach($goal->keyResults as $kr)
                                <div class="p-3 bg-light-subtle rounded-3 border">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <div class="fw-bold text-dark fs-13">{{ $kr->title }}</div>
                                            <small class="text-muted fs-11">
                                                Metric: <span class="fw-semibold text-capitalize">{{ str_replace('_', ' ', $kr->metric_type) }}</span> | 
                                                Weight: <span class="fw-semibold">{{ $kr->weightage }}%</span>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="fw-extrabold fs-14 text-dark">{{ number_format($kr->current_value, 1) }}</span>
                                            <span class="text-muted fs-12">/ {{ number_format($kr->target_value, 1) }} {{ $kr->unit }}</span>
                                            <span class="badge bg-primary-subtle text-primary ms-2">{{ number_format($kr->progress_percentage, 0) }}%</span>
                                        </div>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar {{ $kr->progress_percentage >= 100 ? 'bg-success' : 'bg-primary' }}" 
                                             role="progressbar" style="width: {{ min(100, max(0, $kr->progress_percentage)) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- 3. Check-ins Activity Feed & Timeline -->
            <div class="card border rounded-3 shadow-sm bg-white">
                <div class="card-header bg-light d-flex justify-content-between align-items-center py-3">
                    <span class="fw-bold text-dark fs-14"><i class="feather-clock text-primary me-2"></i>Check-in Activity & Feedback History</span>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#quickCheckInModal">
                        <i class="feather-plus me-1"></i> New Check-in
                    </button>
                </div>
                <div class="card-body p-4">
                    @if($goal->checkIns->isEmpty())
                        <div class="text-center py-4 text-muted fs-13">
                            <i class="feather-message-square fs-28 d-block mb-2 text-muted"></i>
                            No check-in updates recorded yet. Click "Log Check-in" above to post the first update.
                        </div>
                    @else
                        <div class="timeline position-relative">
                            @foreach($goal->checkIns as $ci)
                                <div class="p-3 bg-white border rounded-3 mb-3 shadow-none">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-initials" style="width: 32px; height: 32px; font-size: 11px;">
                                                {{ strtoupper(substr($ci->employee?->full_name ?? ($ci->user?->name ?? 'U'), 0, 1)) }}
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark fs-12">{{ $ci->employee?->full_name ?? ($ci->user?->name ?? 'System User') }}</div>
                                                <small class="text-muted fs-11">{{ $ci->check_in_date?->format('M d, Y h:i A') }}</small>
                                            </div>
                                        </div>
                                        <div>
                                            @if($ci->health_status === 'on_track')
                                                <span class="badge bg-success-subtle text-success fs-10">🟢 On Track</span>
                                            @elseif($ci->health_status === 'at_risk')
                                                <span class="badge bg-warning-subtle text-warning fs-10">🟡 At Risk</span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger fs-10">🔴 Behind</span>
                                            @endif
                                            <span class="badge bg-light text-dark border fs-10 ms-1">{{ number_format($ci->new_progress, 0) }}%</span>
                                        </div>
                                    </div>

                                    @if($ci->keyResult)
                                        <div class="mb-2">
                                            <small class="badge bg-light text-primary border fs-11">
                                                <i class="feather-target me-1"></i> KR: {{ $ci->keyResult->title }} ({{ $ci->new_value }} {{ $ci->keyResult->unit }})
                                            </small>
                                        </div>
                                    @endif

                                    <p class="text-dark fs-13 mb-1">{{ $ci->comment }}</p>

                                    @if($ci->blockers)
                                        <div class="p-2 bg-danger-subtle text-danger rounded-2 fs-12 mt-2">
                                            <i class="feather-alert-octagon me-1 fw-bold"></i> <strong>Roadblock / Blocker:</strong> {{ $ci->blockers }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: METADATA & CASCADING TREE -->
        <div class="col-lg-4">

            <!-- Progress Gauge Card -->
            <div class="card border rounded-3 shadow-sm bg-white mb-4">
                <div class="card-header bg-light py-3">
                    <span class="fw-bold text-dark fs-13"><i class="feather-activity text-primary me-1.5"></i> Objective Health & Score</span>
                </div>
                <div class="card-body p-4 text-center">
                    <h2 class="fw-extrabold text-primary mb-1">{{ number_format($goal->progress_percentage, 0) }}%</h2>
                    <small class="text-muted fw-bold text-uppercase fs-11">Overall Achievement</small>
                    <div class="progress my-3" style="height: 8px;">
                        <div class="progress-bar {{ $goal->progress_percentage >= 100 ? 'bg-success' : 'bg-primary' }}" 
                             role="progressbar" style="width: {{ min(100, max(0, $goal->progress_percentage)) }}%"></div>
                    </div>

                    <div class="row g-2 pt-2 border-top text-start">
                        <div class="col-6">
                            <small class="text-muted fs-11">Priority</small>
                            <div class="fw-bold text-dark fs-12 text-capitalize">{{ $goal->priority }}</div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted fs-11">Target Due Date</small>
                            <div class="fw-bold text-dark fs-12">{{ $goal->due_date?->format('M d, Y') ?? 'N/A' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ownership & Meta Card -->
            <div class="card border rounded-3 shadow-sm bg-white mb-4">
                <div class="card-header bg-light py-3">
                    <span class="fw-bold text-dark fs-13"><i class="feather-user text-primary me-1.5"></i> Assignment & Ownership</span>
                </div>
                <div class="card-body p-3.5">
                    <div class="mb-3">
                        <small class="text-muted fs-11 text-uppercase fw-bold">Owner Scope</small>
                        <div class="fw-bold text-dark fs-13 mt-1">
                            @if($goal->owner_type === 'company')
                                🏢 Organization-Wide Objective
                            @elseif($goal->owner_type === 'department')
                                👥 Department: {{ $goal->department?->name ?? 'General' }}
                            @else
                                @php
                                    $assignedEmployees = $goal->employees->isNotEmpty() ? $goal->employees : ($goal->employee ? collect([$goal->employee]) : collect());
                                @endphp
                                @if($assignedEmployees->count() > 1)
                                    <div class="d-flex flex-column gap-2 mt-2">
                                        @foreach($assignedEmployees as $emp)
                                            <div class="d-flex align-items-center gap-2 p-2 bg-light rounded-2 border">
                                                <div class="avatar-initials" style="width: 28px; height: 28px; font-size: 11px;">
                                                    {{ strtoupper(substr($emp->full_name, 0, 1)) }}
                                                </div>
                                                <div class="text-truncate">
                                                    <div class="fw-bold text-dark fs-12 text-truncate">{{ $emp->full_name }}</div>
                                                    <small class="text-muted fs-10">{{ $emp->department?->name ?? 'Team Member' }}</small>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @elseif($assignedEmployees->count() === 1)
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        <div class="avatar-initials" style="width: 30px; height: 30px; font-size: 11px;">
                                            {{ strtoupper(substr($assignedEmployees->first()->full_name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark fs-13">{{ $assignedEmployees->first()->full_name }}</div>
                                            <small class="text-muted fs-11">{{ $assignedEmployees->first()->department?->name ?? 'Assigned' }}</small>
                                        </div>
                                    </div>
                                @else
                                    👤 {{ $goal->employee?->full_name ?? 'Individual' }}
                                @endif
                            @endif
                        </div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted fs-11 text-uppercase fw-bold">Created By</small>
                        <div class="fw-semibold text-dark fs-12">{{ $goal->creator?->name ?? 'System' }} on {{ $goal->created_at?->format('M d, Y') }}</div>
                    </div>
                    <div>
                        <small class="text-muted fs-11 text-uppercase fw-bold">Status</small>
                        <div>
                            <span class="badge {{ $goal->status === 'active' ? 'bg-success-subtle text-success' : 'bg-light text-muted border' }} fs-11 text-uppercase">
                                {{ $goal->status }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sub-Goals / Child Goals Card -->
            @if($goal->childGoals->isNotEmpty())
                <div class="card border rounded-3 shadow-sm bg-white">
                    <div class="card-header bg-light py-3">
                        <span class="fw-bold text-dark fs-13"><i class="feather-git-pull-request text-primary me-1.5"></i> Cascaded Sub-Goals ({{ $goal->childGoals->count() }})</span>
                    </div>
                    <div class="card-body p-3">
                        @foreach($goal->childGoals as $child)
                            <div class="p-2.5 bg-light-subtle rounded border mb-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <a href="{{ route('hrms.goals.show', $child->id) }}" class="fw-bold text-dark fs-12 text-decoration-none">
                                        {{ $child->title }}
                                    </a>
                                    <span class="fw-bold text-primary fs-11">{{ number_format($child->progress_percentage, 0) }}%</span>
                                </div>
                                <small class="text-muted fs-10">👤 {{ $child->employee?->full_name ?? ($child->department?->name ?? 'Team') }}</small>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: QUICK CHECK-IN -->
<!-- ========================================================================= -->
<x-ui.modal id="quickCheckInModal" title="<i class='feather-check-square text-primary me-2'></i> Log Progress Check-in" size="lg" :showFooter="false" :centered="true">
    <form method="POST" action="{{ route('hrms.goals.check-in', $goal->id) }}">
        @csrf
        <x-ui.odoo-form-ui type="sheet" class="p-2">
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <x-ui.odoo-form-ui type="select" label="Target Key Result" name="goal_key_result_id">
                        <option value="">Objective Overall Progress (%)</option>
                        @foreach($goal->keyResults as $kr)
                            <option value="{{ $kr->id }}">{{ $kr->title }} (Current: {{ $kr->current_value }} / Target: {{ $kr->target_value }} {{ $kr->unit }})</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="New Value" name="new_value" placeholder="New current value" :required="true" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Health Status" name="health_status" :required="true">
                        <option value="on_track" {{ $goal->health_status === 'on_track' ? 'selected' : '' }}>🟢 On Track</option>
                        <option value="at_risk" {{ $goal->health_status === 'at_risk' ? 'selected' : '' }}>🟡 At Risk</option>
                        <option value="behind" {{ $goal->health_status === 'behind' ? 'selected' : '' }}>🔴 Behind</option>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-12">
                    <x-ui.odoo-form-ui type="textarea" label="Accomplishment" name="comment" placeholder="Brief note on latest progress..." rows="2" :required="true" />
                </div>
                <div class="col-12">
                    <x-ui.odoo-form-ui type="textarea" label="Blockers" name="blockers" placeholder="Any obstacles?" rows="1" />
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                <x-ui.button type="button" variant="light" data-bs-dismiss="modal">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="feather-check" class="fw-bold">Save Check-in</x-ui.button>
            </div>
        </x-ui.odoo-form-ui>
    </form>
</x-ui.modal>

<!-- ========================================================================= -->
<!-- MODAL: EDIT GOAL -->
<!-- ========================================================================= -->
@if($isHrAdmin)
<x-ui.modal id="editGoalModal" title="<i class='feather-edit text-primary me-2'></i> Edit Objective" size="xl" :showFooter="false" :centered="true">
    <form method="POST" action="{{ route('hrms.goals.update', $goal->id) }}">
        @csrf
        @method('PUT')
        <x-ui.odoo-form-ui type="sheet" class="p-2">
            <div class="row g-4 mb-3">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" label="Objective Title" name="title" value="{{ $goal->title }}" :required="true" />
                    <x-ui.odoo-form-ui type="select" label="Goal Cycle" name="goal_cycle_id">
                        <option value="">Choose Goal Cycle...</option>
                        @foreach($cycles as $c)
                            <option value="{{ $c->id }}" {{ $goal->goal_cycle_id == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                    <x-ui.odoo-form-ui type="select" label="Strategic Pillar" name="goal_category_id">
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ $goal->goal_category_id == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Scope" name="owner_type">
                        <option value="company" {{ $goal->owner_type === 'company' ? 'selected' : '' }}>Organization-Wide</option>
                        <option value="department" {{ $goal->owner_type === 'department' ? 'selected' : '' }}>Department</option>
                        <option value="employee" {{ $goal->owner_type === 'employee' ? 'selected' : '' }}>Individual Employee</option>
                    </x-ui.odoo-form-ui>
                    @php
                        $selectedEmpIds = $goal->employees->pluck('id')->toArray();
                        if (empty($selectedEmpIds) && $goal->employee_id) {
                            $selectedEmpIds = [$goal->employee_id];
                        }
                    @endphp
                    <x-ui.odoo-form-ui type="select" label="Assigned Employees" name="employee_ids[]" :multiple="true" select2Selector="select2">
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}" {{ in_array($emp->id, $selectedEmpIds) ? 'selected' : '' }}>
                                {{ $emp->full_name }} ({{ $emp->employee_id }})
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                    <x-ui.odoo-form-ui type="select" label="Parent Goal" name="parent_goal_id">
                        <option value="">None (Top-Level)</option>
                        @foreach($parentGoalOptions as $pg)
                            <option value="{{ $pg->id }}" {{ $goal->parent_goal_id == $pg->id ? 'selected' : '' }}>{{ $pg->code }} - {{ $pg->title }}</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-12">
                    <x-ui.odoo-form-ui type="textarea" label="Description" name="description" rows="2">{{ $goal->description }}</x-ui.odoo-form-ui>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                <x-ui.button type="button" variant="light" data-bs-dismiss="modal">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="feather-check-circle" class="fw-bold">Update Objective</x-ui.button>
            </div>
        </x-ui.odoo-form-ui>
    </form>
</x-ui.modal>
@endif
@endsection
