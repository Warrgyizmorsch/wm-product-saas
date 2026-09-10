@extends('layouts.duralux')

@section('title', 'PIP Workspace: ' . $pip->pip_number . ' | HRMS')
@section('page-title', 'PIP Workspace: ' . $pip->pip_number)
@section('breadcrumb', 'HRMS / PIP / Workspace')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="light" icon="feather-arrow-left" href="{{ route('hrms.pip.index') }}" class="border text-dark fw-semibold">
            Back to PIP Plans
        </x-ui.button>
        @if(in_array($pip->status, ['active', 'under_review', 'extended']))
            <x-ui.button variant="primary" icon="feather-check-square" data-bs-toggle="modal" data-bs-target="#addCheckinModal" class="fw-bold">
                Log Check-in
            </x-ui.button>
            <x-ui.button variant="warning" icon="feather-flag" data-bs-toggle="modal" data-bs-target="#evaluateModal" class="fw-bold text-dark">
                Final Evaluation
            </x-ui.button>
        @endif
    </div>
@endsection

@push('styles')
<style>
    .avatar-initials-lg {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background-color: rgba(var(--bs-primary-rgb), 0.1);
        color: var(--bs-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 18px;
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

    @if(session('error'))
        <x-ui.alert variant="danger" dismissible class="mb-4">
            <i class="feather-alert-triangle me-2"></i>{{ session('error') }}
        </x-ui.alert>
    @endif

    <!-- ERP Single Panel Workspace -->
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">

        <!-- HEADER BANNER CARD -->
        <div class="p-4 bg-light rounded border mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-initials-lg">
                        {{ strtoupper(substr($pip->employee->full_name ?? 'E', 0, 2)) }}
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h4 class="fw-bold text-dark mb-0">{{ $pip->pip_number }}</h4>
                            @php
                                $badgeVariant = match($pip->status) {
                                    'active'            => 'success',
                                    'under_review'      => 'warning',
                                    'completed_success' => 'info',
                                    'extended'          => 'primary',
                                    'role_reassigned'   => 'secondary',
                                    'failed_terminated' => 'danger',
                                    default             => 'secondary'
                                };
                            @endphp
                            <x-ui.badge soft variant="{{ $badgeVariant }}" class="text-capitalize">
                                {{ str_replace('_', ' ', $pip->status) }}
                            </x-ui.badge>
                        </div>
                        <h5 class="fw-semibold text-dark mt-1 mb-0">{{ $pip->employee->full_name }}</h5>
                        <div class="text-muted fs-12 mt-1">
                            {{ $pip->employee->designation?->name ?? 'N/A' }} &bull; {{ $pip->employee->department?->name ?? 'N/A' }} &bull; Emp ID: <code>{{ $pip->employee->employee_id }}</code>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-column text-end">
                    <span class="fs-12 text-muted fw-semibold text-uppercase">Timeline</span>
                    <span class="fs-14 fw-bold text-dark mt-1">
                        {{ $pip->start_date ? $pip->start_date->format('M d, Y') : 'N/A' }} - {{ $pip->end_date ? $pip->end_date->format('M d, Y') : 'N/A' }}
                    </span>
                    <small class="text-muted mt-1 fs-11">
                        Manager: <strong class="text-dark">{{ $pip->manager?->full_name ?? 'N/A' }}</strong>
                    </small>
                </div>
            </div>
        </div>

        <!-- REASON FOR PIP & CORE DEFICIENCIES (FULL-WIDTH CARD) -->
        <div class="p-3 bg-light rounded border mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="flex-grow-1" style="min-width: 280px; max-width: 70%;">
                    <h6 class="fw-bold text-dark mb-1 fs-13"><i class="feather-alert-circle me-1 text-primary"></i> Reason for PIP & Core Deficiencies</h6>
                    <p class="fs-13 text-secondary mb-0 style-line-height">{{ $pip->reason_details ?? '—' }}</p>
                </div>
                <div class="d-flex align-items-center gap-4 border-start ps-3 flex-wrap">
                    <div>
                        <span class="text-muted d-block fs-11 text-uppercase fw-semibold">PIP Category</span>
                        <x-ui.badge soft variant="primary" class="mt-1">
                            <i class="feather-tag me-1"></i> {{ $pip->category?->name ?? 'General Performance' }}
                        </x-ui.badge>
                    </div>
                    <div>
                        <span class="text-muted d-block fs-11 text-uppercase fw-semibold">Check-in Frequency</span>
                        <span class="fw-semibold text-dark text-capitalize mt-1 d-inline-block fs-12">
                            <i class="feather-clock me-1 text-primary"></i> {{ $pip->checkin_frequency }}
                        </span>
                    </div>
                    <div>
                        <span class="text-muted d-block fs-11 text-uppercase fw-semibold">Next Check-in Due</span>
                        @if($pip->is_checkin_overdue)
                            <x-ui.badge soft variant="danger" class="mt-1 fs-12">
                                <i class="feather-alert-triangle me-1"></i> Overdue ({{ $pip->next_checkin_due_date?->format('M d') }})
                            </x-ui.badge>
                        @elseif($pip->next_checkin_due_date)
                            <span class="fw-semibold text-dark mt-1 d-inline-block fs-12">
                                <i class="feather-calendar me-1 text-primary"></i> {{ $pip->next_checkin_due_date->format('M d, Y') }}
                            </span>
                        @else
                            <span class="text-muted fs-12 mt-1 d-inline-block">—</span>
                        @endif
                    </div>
                </div>
            </div>

            @if($pip->final_outcome)
                <div class="mt-3 pt-3 border-top">
                    <h6 class="fw-bold text-dark mb-1 fs-11 text-uppercase">Final Evaluation Outcome</h6>
                    <x-ui.badge variant="primary" class="mb-1">{{ str_replace('_', ' ', strtoupper($pip->final_outcome)) }}</x-ui.badge>
                    <p class="fs-12 text-muted mb-0"><em>"{{ $pip->final_comments }}"</em></p>
                </div>
            @endif
        </div>

        <!-- SMART OBJECTIVES & DELIVERABLES (FULL-WIDTH CARD) -->
        <div class="p-3 bg-light rounded border mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-dark mb-0 fs-13"><i class="feather-target me-1 text-primary"></i> SMART Objectives & Deliverables</h6>
                @if(in_array($pip->status, ['active', 'under_review', 'extended']))
                    <x-ui.button variant="outline-primary" icon="feather-plus" size="sm" data-bs-toggle="modal" data-bs-target="#addObjectiveModal" class="fw-semibold">
                        Add Objective
                    </x-ui.button>
                @endif
            </div>

            <div class="table-responsive bg-white rounded border">
                <table class="table table-hover align-top mb-0 fs-12">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3 py-2 text-muted text-uppercase fs-10" style="width: 28%;">Objective & Expectation</th>
                            <th class="py-2 text-muted text-uppercase fs-10" style="width: 17%;">Benchmark / Criteria</th>
                            <th class="py-2 text-muted text-uppercase fs-10" style="width: 25%;">Support Provided by Company</th>
                            <th class="py-2 text-muted text-uppercase fs-10" style="width: 12%;">Status</th>
                            <th class="text-end pe-3 py-2 text-muted text-uppercase fs-10" style="width: 18%;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pip->objectives as $obj)
                            <tr>
                                <td class="ps-3 py-2.5">
                                    <div class="fw-bold text-dark fs-12 text-break">{{ $obj->title }}</div>
                                    @if($obj->description)
                                        <div class="d-flex align-items-start gap-1.5 text-muted fs-11 mt-1">
                                            <i class="feather-align-left text-muted flex-shrink-0 mt-0.5"></i>
                                            <span class="text-break" style="word-break: break-word;">{{ $obj->description }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="py-2.5 text-dark fs-12 fw-medium text-break" style="word-break: break-word;">
                                    {{ $obj->target_criteria ?? '—' }}
                                </td>
                                <td class="py-2.5">
                                    @if($obj->support_provided)
                                        <div class="d-flex align-items-start gap-2 text-dark fs-12">
                                            <i class="feather-life-buoy text-primary flex-shrink-0 mt-0.5"></i>
                                            <span class="text-break" style="word-break: break-word;">{{ $obj->support_provided }}</span>
                                        </div>
                                    @else
                                        <span class="text-muted fs-12">—</span>
                                    @endif
                                </td>
                                <td class="py-2.5">
                                    @php
                                        $objBadge = match($obj->status) {
                                            'achieved'           => 'success',
                                            'partially_achieved' => 'warning',
                                            'not_achieved'       => 'danger',
                                            'in_progress'        => 'primary',
                                            default              => 'secondary'
                                        };
                                    @endphp
                                    <x-ui.badge soft variant="{{ $objBadge }}" class="text-capitalize fs-10">
                                        {{ str_replace('_', ' ', $obj->status) }}
                                    </x-ui.badge>
                                </td>
                                <td class="text-end pe-3 py-2.5">
                                    @if(in_array($pip->status, ['active', 'under_review', 'extended']))
                                        <div class="d-inline-flex align-items-center gap-3">
                                            <form action="{{ route('hrms.pip.objective.status', [$pip->id, $obj->id]) }}" method="POST" class="d-inline me-1">
                                                @csrf
                                                <select name="status" class="form-select form-select-sm border rounded px-2.5 py-1 fs-12 fw-semibold text-dark bg-white shadow-none d-inline-block w-auto" onchange="this.form.submit()" style="cursor: pointer; min-width: 120px;">
                                                    <option value="pending"            @selected($obj->status === 'pending')>Pending</option>
                                                    <option value="in_progress"        @selected($obj->status === 'in_progress')>In Progress</option>
                                                    <option value="achieved"           @selected($obj->status === 'achieved')>Achieved</option>
                                                    <option value="partially_achieved" @selected($obj->status === 'partially_achieved')>Partially Achieved</option>
                                                    <option value="not_achieved"       @selected($obj->status === 'not_achieved')>Not Achieved</option>
                                                </select>
                                            </form>
                                            <x-ui.action-dropdown id="objActions{{ $obj->id }}">
                                                <li>
                                                    <a class="dropdown-item" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#editObjectiveModal_{{ $obj->id }}">
                                                        <i class="feather-edit-2 me-2 text-primary"></i>Edit Objective Details
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="javascript:void(0);" onclick="confirmAction('Are you sure you want to delete this SMART Objective? This action cannot be undone.', function() { document.getElementById('deleteObjForm{{ $obj->id }}').submit(); }, { title: 'Delete SMART Objective', confirmText: 'Yes, Delete', variant: 'danger' });">
                                                        <i class="feather-trash-2 me-2"></i>Delete Objective
                                                    </a>
                                                </li>
                                            </x-ui.action-dropdown>
                                            <form id="deleteObjForm{{ $obj->id }}" action="{{ route('hrms.pip.objective.destroy', [$pip->id, $obj->id]) }}" method="POST" class="d-none">
                                                @csrf
                                                @method('DELETE')
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-muted fs-12">—</span>
                                    @endif

                                    <!-- EDIT OBJECTIVE MODAL -->
                                    <div class="modal fade text-start" id="editObjectiveModal_{{ $obj->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content border-0 shadow">
                                                <div class="modal-header border-bottom py-3">
                                                    <h5 class="modal-title fw-bold text-dark fs-15"><i class="feather-edit me-1.5 text-primary"></i> Edit SMART Objective</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form action="{{ route('hrms.pip.objective.update', [$pip->id, $obj->id]) }}" method="POST">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="modal-body p-4 text-start">
                                                        <div class="mb-3">
                                                            <x-ui.odoo-form-ui type="input" label="Objective Title" name="title" value="{{ $obj->title }}" :required="true" />
                                                        </div>
                                                        <div class="mb-3">
                                                            <x-ui.odoo-form-ui type="textarea" label="Description" name="description" rows="2" value="{{ $obj->description }}" />
                                                        </div>
                                                        <div class="mb-3">
                                                            <x-ui.odoo-form-ui type="input" label="Success Benchmark / Target Criteria" name="target_criteria" value="{{ $obj->target_criteria }}" />
                                                        </div>
                                                        <div class="mb-3">
                                                            <x-ui.odoo-form-ui type="textarea" label="Support Provided by Company" name="support_provided" rows="2" value="{{ $obj->support_provided }}" />
                                                        </div>
                                                        <div class="mb-0">
                                                            <x-ui.odoo-form-ui type="select" label="Objective Status" name="status" :required="true">
                                                                <option value="pending" @selected($obj->status === 'pending')>Pending</option>
                                                                <option value="in_progress" @selected($obj->status === 'in_progress')>In Progress</option>
                                                                <option value="achieved" @selected($obj->status === 'achieved')>Achieved</option>
                                                                <option value="partially_achieved" @selected($obj->status === 'partially_achieved')>Partially Achieved</option>
                                                                <option value="not_achieved" @selected($obj->status === 'not_achieved')>Not Achieved</option>
                                                            </x-ui.odoo-form-ui>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-top bg-light py-2.5">
                                                        <x-ui.button variant="secondary" size="sm" data-bs-dismiss="modal">Cancel</x-ui.button>
                                                        <x-ui.button type="submit" variant="primary" size="sm" class="fw-bold px-4">Update Objective</x-ui.button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-3 text-muted fs-12">No specific SMART objectives logged yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 1-ON-1 MILESTONE CHECK-INS TIMELINE -->
        <div>
            <h6 class="fw-bold text-dark mb-3 fs-14"><i class="feather-clock me-1 text-primary"></i> 1-on-1 Milestone Check-ins Timeline</h6>

            <div class="table-responsive border rounded">
                <table class="table table-hover align-middle mb-0 fs-13">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3 py-3 text-muted text-uppercase fs-11">Check-in Date</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Reviewer</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Status / Rating</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Feedback & Discussion Notes</th>
                            <th class="text-end pe-3 py-3 text-muted text-uppercase fs-11">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pip->checkins as $checkin)
                            <tr>
                                <td class="ps-3 fw-bold text-dark">
                                    {{ $checkin->review_date ? $checkin->review_date->format('M d, Y') : 'N/A' }}
                                </td>
                                <td>{{ $checkin->reviewer?->name ?? 'Manager' }}</td>
                                <td>
                                    @php
                                        $chkBadge = match($checkin->rating_status) {
                                            'on_track'  => 'success',
                                            'off_track' => 'warning',
                                            'at_risk'   => 'danger',
                                            'exceeding' => 'info',
                                            default     => 'secondary'
                                        };
                                    @endphp
                                    <x-ui.badge soft variant="{{ $chkBadge }}" class="text-capitalize">
                                        {{ str_replace('_', ' ', $checkin->rating_status) }}
                                    </x-ui.badge>
                                </td>
                                <td class="text-secondary">
                                    {{ $checkin->manager_comments }}
                                </td>
                                <td class="text-end pe-3">
                                    @if(in_array($pip->status, ['active', 'under_review', 'extended']))
                                        <x-ui.action-dropdown id="chkActions{{ $checkin->id }}">
                                            <li>
                                                <a class="dropdown-item" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#editCheckinModal_{{ $checkin->id }}">
                                                    <i class="feather-edit-2 me-2 text-primary"></i>Edit Check-in
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item text-danger" href="javascript:void(0);" onclick="confirmAction('Are you sure you want to delete this Milestone Check-in record? This action cannot be undone.', function() { document.getElementById('deleteCheckinForm{{ $checkin->id }}').submit(); }, { title: 'Delete Milestone Check-in', confirmText: 'Yes, Delete', variant: 'danger' });">
                                                    <i class="feather-trash-2 me-2"></i>Delete Check-in Log
                                                </a>
                                            </li>
                                        </x-ui.action-dropdown>
                                        <form id="deleteCheckinForm{{ $checkin->id }}" action="{{ route('hrms.pip.checkin.destroy', [$pip->id, $checkin->id]) }}" method="POST" class="d-none">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                    @else
                                        <span class="text-muted fs-12">—</span>
                                    @endif

                                    <!-- EDIT CHECKIN MODAL -->
                                    <div class="modal fade text-start" id="editCheckinModal_{{ $checkin->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content border-0 shadow">
                                                <div class="modal-header border-bottom py-3">
                                                    <h5 class="modal-title fw-bold text-dark fs-15"><i class="feather-edit me-1.5 text-primary"></i> Edit Milestone Check-in</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form action="{{ route('hrms.pip.checkin.update', [$pip->id, $checkin->id]) }}" method="POST">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="modal-body p-4 text-start">
                                                        <div class="mb-3">
                                                            <x-ui.odoo-form-ui type="input" inputType="date" label="Check-in Date" name="checkin_date" value="{{ $checkin->review_date?->format('Y-m-d') }}" :required="true" />
                                                        </div>
                                                        <div class="mb-3">
                                                            <x-ui.odoo-form-ui type="select" label="Progress Rating" name="rating_status" :required="true">
                                                                <option value="on_track" @selected($checkin->rating_status === 'on_track')>On Track</option>
                                                                <option value="off_track" @selected($checkin->rating_status === 'off_track')>Off Track</option>
                                                                <option value="at_risk" @selected($checkin->rating_status === 'at_risk')>At Risk</option>
                                                                <option value="exceeding" @selected($checkin->rating_status === 'exceeding')>Exceeding Expectations</option>
                                                            </x-ui.odoo-form-ui>
                                                        </div>
                                                        <div class="mb-0">
                                                            <x-ui.odoo-form-ui type="textarea" label="Manager Notes & Feedback" name="manager_comments" rows="4" value="{{ $checkin->manager_comments }}" :required="true" />
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-top bg-light py-2.5">
                                                        <x-ui.button variant="secondary" size="sm" data-bs-dismiss="modal">Cancel</x-ui.button>
                                                        <x-ui.button type="submit" variant="primary" size="sm" class="fw-bold px-4">Update Check-in</x-ui.button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted fs-12">
                                    No milestone check-ins recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- LOG CHECK-IN MODAL (Outside single panel) -->
<div class="modal fade" id="addCheckinModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark fs-15"><i class="feather-check-square me-1.5 text-primary"></i> Log 1-on-1 Milestone Check-in</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.pip.checkin.store', $pip->id) }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="input" inputType="date" label="Check-in Date" name="checkin_date" value="{{ date('Y-m-d') }}" :required="true" />
                    </div>
                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="select" label="Progress Rating" name="rating_status" :required="true">
                            <option value="on_track">On Track</option>
                            <option value="off_track">Off Track</option>
                            <option value="at_risk">At Risk</option>
                            <option value="exceeding">Exceeding Expectations</option>
                        </x-ui.odoo-form-ui>
                    </div>
                    <div class="mb-0">
                        <x-ui.odoo-form-ui type="textarea" label="Manager Notes & Feedback" name="manager_comments" rows="4" placeholder="Summarize performance discussions, blockers, and agreed next steps..." :required="true" />
                    </div>
                </div>
                <div class="modal-footer border-top bg-light py-2.5">
                    <x-ui.button variant="secondary" size="sm" data-bs-dismiss="modal">Cancel</x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="sm" class="fw-bold px-4">Save Check-in</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- FINAL EVALUATION MODAL (Outside single panel) -->
<div class="modal fade" id="evaluateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark fs-15"><i class="feather-flag me-1.5 text-warning"></i> Final PIP Evaluation & Closure</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.pip.evaluate', $pip->id) }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="select" label="Evaluation Outcome" name="evaluation_outcome" id="evalOutcome" :required="true">
                            <option value="successful_completion">Successful Completion (PIP Passed)</option>
                            <option value="pip_extension">Extend PIP Period</option>
                            <option value="role_reassignment">Role Reassignment / Demotion</option>
                            <option value="termination">Initiate Employment Termination</option>
                        </x-ui.odoo-form-ui>
                    </div>
                    <div class="mb-3 d-none" id="extensionDaysField">
                        <x-ui.odoo-form-ui type="input" inputType="number" label="Extension Days" name="extension_days" value="30" min="7" max="90" placeholder="e.g. 30" />
                        <small class="text-muted fs-11">Current end date: <strong>{{ $pip->end_date?->format('M d, Y') }}</strong>. New end date will be calculated automatically.</small>
                    </div>
                    <div class="mb-0">
                        <x-ui.odoo-form-ui type="textarea" label="Final HR & Manager Remarks" name="final_remarks" rows="4" placeholder="Document overall evaluation details and official decision..." :required="true" />
                    </div>
                </div>
                <div class="modal-footer border-top bg-light py-2.5">
                    <x-ui.button variant="secondary" size="sm" data-bs-dismiss="modal">Cancel</x-ui.button>
                    <x-ui.button type="submit" variant="warning" size="sm" class="fw-bold text-dark px-4">Submit Final Evaluation</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ADD OBJECTIVE MODAL -->
<div class="modal fade" id="addObjectiveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark fs-15"><i class="feather-target me-1.5 text-primary"></i> Add SMART Objective</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.pip.objective.store', $pip->id) }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="input" label="Objective Title" name="title" placeholder="e.g. Improve Ticket Resolution Rate" :required="true" />
                    </div>
                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="textarea" label="Description" name="description" rows="2" placeholder="What is expected from the employee for this objective?" />
                    </div>
                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="input" label="Success Benchmark / Target Criteria" name="target_criteria" placeholder="e.g. Resolve 90% of tickets within SLA by end of PIP" />
                    </div>
                    <div class="mb-0">
                        <x-ui.odoo-form-ui type="textarea" label="Support Provided by Company" name="support_provided" rows="2" placeholder="e.g. Daily pair-review with senior developer, training access..." />
                    </div>
                </div>
                <div class="modal-footer border-top bg-light py-2.5">
                    <x-ui.button variant="secondary" size="sm" data-bs-dismiss="modal">Cancel</x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="sm" class="fw-bold px-4">Add Objective</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>

<x-ui.confirmation-modal />

@push('scripts')
<script>
    $(document).ready(function() {
        $('#addCheckinModal, #evaluateModal, #addObjectiveModal').each(function() {
            if ($(this).parent().get(0) !== document.body) {
                $(this).appendTo(document.body);
            }
        });

        // Show/hide extension days field based on evaluation outcome
        $(document).on('change', '#evalOutcome', function() {
            if ($(this).val() === 'pip_extension') {
                $('#extensionDaysField').removeClass('d-none');
                $('#extensionDaysField input').attr('required', true);
            } else {
                $('#extensionDaysField').addClass('d-none');
                $('#extensionDaysField input').removeAttr('required');
            }
        });
    });

    $(document).on('show.bs.modal', '.modal', function () {
        if ($(this).parent().get(0) !== document.body) {
            $(this).appendTo(document.body);
        }
    });
</script>
@endpush
@endsection
