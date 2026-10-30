@extends('layouts.duralux')

@section('title', __('hrms.pip.meta_workspace_title', ['number' => $pip->pip_number]))
@section('page-title', __('hrms.pip.workspace_title', ['number' => $pip->pip_number]))
@section('breadcrumb', __('hrms.pip.breadcrumb_workspace'))

@section('page-actions')
    @php
        $authUser = auth()->user();
        $accessService = app(\App\Services\Access\AccessService::class);
        $canManagePip = $authUser && ($accessService->allows($authUser, 'hrms.pip.manage', ['tenant_id' => $authUser->tenant_id]) || $accessService->allows($authUser, 'hrms.performance.manage', ['tenant_id' => $authUser->tenant_id]) || $accessService->allows($authUser, 'hr.settings.manage', ['tenant_id' => $authUser->tenant_id]));
    @endphp
    <div class="d-flex align-items-center gap-2">
        @if($canManagePip)
            <x-ui.button variant="light" icon="feather-arrow-left" href="{{ route('hrms.pip.index') }}" class="border text-dark fw-semibold">
                {{ __('hrms.pip.back_to_plans') }}
            </x-ui.button>
        @else
            <x-ui.button variant="light" icon="feather-arrow-left" href="{{ route('hrms.employees.show', ['employee' => $pip->employee_id, 'tab' => 'pip']) }}" class="border text-dark fw-semibold">
                {{ __('hrms.pip.back_to_profile') }}
            </x-ui.button>
        @endif
        @if($canManagePip && in_array($pip->status, ['active', 'under_review', 'extended']))
            <x-ui.button variant="primary" icon="feather-check-square" data-bs-toggle="modal" data-bs-target="#addCheckinModal" class="fw-bold">
                {{ __('hrms.pip.btn_log_checkin') }}
            </x-ui.button>
            <x-ui.button variant="warning" icon="feather-flag" data-bs-toggle="modal" data-bs-target="#evaluateModal" class="fw-bold text-dark">
                {{ __('hrms.pip.btn_final_evaluation') }}
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
    .pip-table-fixed {
        table-layout: fixed !important;
        width: 100% !important;
    }
    .pip-table-fixed th,
    .pip-table-fixed td {
        white-space: normal !important;
        word-wrap: break-word !important;
        overflow-wrap: break-word !important;
        word-break: break-word !important;
    }
    .pip-table-wrapper {
        overflow-x: hidden;
        width: 100%;
    }
    @media (max-width: 767.98px) {
        .pip-table-wrapper {
            overflow-x: auto !important;
        }
        .pip-table-fixed {
            min-width: 650px;
        }
    }
    .pip-cell-icon {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex-shrink: 0 !important;
        margin-right: 8px !important;
        margin-top: 2.5px !important;
        line-height: 1 !important;
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
                                {{ __('hrms.pip.status_' . $pip->status) }}
                            </x-ui.badge>
                        </div>
                        <h5 class="fw-semibold text-dark mt-1 mb-0">{{ $pip->employee->full_name }}</h5>
                        <div class="text-muted fs-12 mt-1">
                            {{ $pip->employee->designation?->name ?? __('hrms.common.na') }} &bull; {{ $pip->employee->department?->name ?? __('hrms.common.na') }} &bull; {{ __('hrms.pip.emp_id_label') }} <code>{{ $pip->employee->employee_id }}</code>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-column text-end">
                    <span class="fs-12 text-muted fw-semibold text-uppercase">{{ __('hrms.pip.timeline') }}</span>
                    <span class="fs-14 fw-bold text-dark mt-1">
                        {{ $pip->start_date ? $pip->start_date->format('M d, Y') : __('hrms.common.na') }} - {{ $pip->end_date ? $pip->end_date->format('M d, Y') : __('hrms.common.na') }}
                    </span>
                    <small class="text-muted mt-1 fs-11">
                        {{ __('hrms.pip.manager_label') }} <strong class="text-dark">{{ $pip->manager?->full_name ?? __('hrms.common.na') }}</strong>
                    </small>
                </div>
            </div>

            <!-- ACKNOWLEDGEMENT STATUS BANNER -->
            @if($pip->employee_acknowledged_at)
                <x-ui.alert variant="success" class="d-flex flex-wrap align-items-center justify-content-between p-3 rounded mb-4 mt-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="feather-check-circle fs-18 text-success"></i>
                        <div>
                            <strong class="text-success fs-13">{{ __('hrms.pip.ack_employee_acknowledged') }}</strong>
                            <div class="text-muted fs-11">
                                {{ __('hrms.pip.ack_by_on', ['name' => $pip->employee->full_name, 'date' => $pip->employee_acknowledged_at->format('M d, Y h:i A')]) }}
                                @if($pip->employee_signature)
                                    &bull; {{ __('hrms.pip.signature_label') }} <code>{{ $pip->employee_signature }}</code>
                                @endif
                            </div>
                        </div>
                    </div>
                    <x-ui.badge soft variant="success" class="fs-11">{{ __('hrms.pip.status_acknowledged') }}</x-ui.badge>
                </x-ui.alert>
            @elseif(isset($canAcknowledge) && $canAcknowledge)
                <x-ui.alert variant="warning" class="d-flex flex-wrap align-items-center justify-content-between gap-3 p-3 rounded mb-4 mt-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="feather-alert-triangle fs-18 text-warning"></i>
                        <div>
                            <strong class="text-dark fs-13">{{ __('hrms.pip.ack_action_required') }}</strong>
                            <div class="text-muted fs-11">{{ __('hrms.pip.ack_instructions') }}</div>
                        </div>
                    </div>
                    <x-ui.button variant="warning" size="sm" class="fw-bold text-dark px-3" data-bs-toggle="modal" data-bs-target="#acknowledgePipModal">
                        <i class="feather-check-square me-1"></i> {{ __('hrms.pip.btn_acknowledge_plan') }}
                    </x-ui.button>
                </x-ui.alert>
            @endif

            <!-- REASON FOR PIP & CORE DEFICIENCIES -->
            <div class="p-3 bg-white rounded border mt-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div class="flex-grow-1" style="min-width: 280px; max-width: 70%;">
                        <h6 class="fw-bold text-dark mb-1 fs-13"><i class="feather-alert-circle me-1 text-primary"></i> {{ __('hrms.pip.reason_deficiencies') }}</h6>
                        <p class="fs-13 text-secondary mb-0 style-line-height">{{ $pip->reason_details ?? '—' }}</p>
                    </div>
                    <div class="d-flex align-items-center gap-4 border-start ps-3 flex-wrap">
                        <div>
                            <span class="text-muted d-block fs-11 text-uppercase fw-semibold">{{ __('hrms.pip.pip_category') }}</span>
                            <x-ui.badge soft variant="primary" class="mt-1 text-wrap text-break text-start" style="white-space: normal; line-height: 1.35; max-width: 250px;">
                                <i class="feather-tag me-1"></i> {{ $pip->category?->name ?? __('hrms.pip.general_performance') }}
                            </x-ui.badge>
                        </div>
                        @if($pip->template)
                        <div>
                            <span class="text-muted d-block fs-11 text-uppercase fw-semibold">{{ __('hrms.pip.field_template_name') }}</span>
                            <x-ui.badge soft variant="info" class="mt-1">
                                <i class="feather-file-text me-1"></i> {{ $pip->template->name }}
                            </x-ui.badge>
                        </div>
                        @endif
                        <div>
                            <span class="text-muted d-block fs-11 text-uppercase fw-semibold">{{ __('hrms.pip.checkin_frequency') }}</span>
                            <span class="fw-semibold text-dark text-capitalize mt-1 d-inline-block fs-12">
                                <i class="feather-clock me-1 text-primary"></i> {{ __('hrms.pip.freq_' . $pip->checkin_frequency) }}
                            </span>
                        </div>
                        <div>
                            <span class="text-muted d-block fs-11 text-uppercase fw-semibold">{{ __('hrms.pip.next_checkin_due') }}</span>
                            @if($pip->is_checkin_overdue)
                                <x-ui.badge soft variant="danger" class="mt-1 fs-12">
                                    <i class="feather-alert-triangle me-1"></i> {{ __('hrms.pip.checkin_overdue_badge', ['date' => $pip->next_checkin_due_date?->format('M d')]) }}
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

                @if($pip->support_provided || $pip->consequences || $pip->initial_notes)
                    <div class="mt-3 pt-3 border-top row g-3">
                        @if($pip->support_provided)
                            <div class="col-md-4">
                                <span class="text-muted d-block fs-11 text-uppercase fw-semibold"><i class="feather-help-circle me-1 text-primary"></i> {{ __('hrms.pip.field_support') }}</span>
                                <p class="fs-12 text-secondary mb-0 mt-1">{{ $pip->support_provided }}</p>
                            </div>
                        @endif
                        @if($pip->consequences)
                            <div class="col-md-4">
                                <span class="text-muted d-block fs-11 text-uppercase fw-semibold"><i class="feather-alert-octagon me-1 text-danger"></i> {{ __('hrms.pip.field_consequences') }}</span>
                                <p class="fs-12 text-secondary mb-0 mt-1">{{ $pip->consequences }}</p>
                            </div>
                        @endif
                        @if($pip->initial_notes)
                            <div class="col-md-4">
                                <span class="text-muted d-block fs-11 text-uppercase fw-semibold"><i class="feather-file-text me-1 text-muted"></i> {{ __('hrms.pip.field_initial_notes') }}</span>
                                <p class="fs-12 text-secondary mb-0 mt-1">{{ $pip->initial_notes }}</p>
                            </div>
                        @endif
                    </div>
                @endif

                @if($pip->final_outcome)
                    <div class="mt-3 pt-3 border-top">
                        <h6 class="fw-bold text-dark mb-1 fs-11 text-uppercase">{{ __('hrms.pip.final_evaluation_outcome') }}</h6>
                        <x-ui.badge variant="primary" class="mb-1">{{ __('hrms.pip.status_' . $pip->final_outcome) }}</x-ui.badge>
                        <p class="fs-12 text-muted mb-0"><em>"{{ $pip->final_comments }}"</em></p>
                    </div>
                @endif
            </div>
        </div>

        <!-- SMART OBJECTIVES & DELIVERABLES (FULL-WIDTH CARD) -->
        <div class="p-3 bg-light rounded border mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-dark mb-0 fs-13"><i class="feather-target me-1 text-primary"></i> {{ __('hrms.pip.smart_objectives_title') }}</h6>
                @if(isset($canManagePip) && $canManagePip && in_array($pip->status, ['active', 'under_review', 'extended']))
                    <x-ui.button variant="outline-primary" icon="feather-plus" size="sm" data-bs-toggle="modal" data-bs-target="#addObjectiveModal" class="fw-semibold">
                        {{ __('hrms.pip.btn_add_objective') }}
                    </x-ui.button>
                @endif
            </div>

            <div class="pip-table-wrapper bg-white rounded border">
                <table class="table table-hover align-top mb-0 fs-12 pip-table-fixed">
                    <thead class="bg-light border-bottom">
                        <tr>
                            <th class="ps-3 py-2.5 text-muted text-uppercase fs-10 fw-bold" style="width: 33%;">{{ __('hrms.pip.th_objective_expectation') }}</th>
                            <th class="py-2.5 text-muted text-uppercase fs-10 fw-bold" style="width: 24%;">{{ __('hrms.pip.th_benchmark_criteria') }}</th>
                            <th class="py-2.5 text-muted text-uppercase fs-10 fw-bold" style="width: 22%;">{{ __('hrms.pip.th_support_provided') }}</th>
                            <th class="py-2.5 text-muted text-uppercase fs-10 fw-bold" style="width: 14%;">{{ __('hrms.pip.th_objective_status') }}</th>
                            <th class="text-end pe-3 py-2.5 text-muted text-uppercase fs-10 fw-bold" style="width: 7%;">{{ __('hrms.pip.th_action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pip->objectives as $obj)
                            <tr>
                                <td class="ps-3 py-3 align-top">
                                    <div class="fw-bold text-dark fs-12 lh-sm mb-1 text-break">{{ $obj->title }}</div>
                                    @if($obj->description)
                                        <div class="d-flex align-items-start text-secondary fs-11 lh-base mt-1" style="min-width: 0;">
                                            <i class="feather-align-left text-muted pip-cell-icon me-2 flex-shrink-0 fs-11" style="margin-right: 8px; margin-top: 2.5px;"></i>
                                            <span class="flex-grow-1 text-break" style="min-width: 0; word-break: break-word; white-space: normal;">{{ $obj->description }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="py-3 align-top" style="white-space: normal; word-break: break-word;">
                                    @if($obj->target_criteria)
                                        <div class="d-flex align-items-start text-dark fs-12 lh-base" style="min-width: 0;">
                                            <i class="feather-target text-primary pip-cell-icon me-2 flex-shrink-0 fs-12" style="margin-right: 8px; margin-top: 2.5px;"></i>
                                            <span class="fw-medium flex-grow-1 text-break" style="min-width: 0; word-break: break-word; white-space: normal;">{{ $obj->target_criteria }}</span>
                                        </div>
                                    @else
                                        <span class="text-muted fs-12">—</span>
                                    @endif
                                </td>
                                <td class="py-3 align-top" style="white-space: normal; word-break: break-word;">
                                    @if($obj->support_provided)
                                        <div class="d-flex align-items-start text-dark fs-12 lh-base" style="min-width: 0;">
                                            <i class="feather-life-buoy text-primary pip-cell-icon me-2 flex-shrink-0 fs-12" style="margin-right: 8px; margin-top: 2.5px;"></i>
                                            <span class="text-secondary flex-grow-1 text-break" style="min-width: 0; word-break: break-word; white-space: normal;">{{ $obj->support_provided }}</span>
                                        </div>
                                    @else
                                        <span class="text-muted fs-12">—</span>
                                    @endif
                                </td>
                                <td class="py-3 align-top">
                                    @php
                                        $objBadge = match($obj->status) {
                                            'achieved'           => 'success',
                                            'partially_achieved' => 'warning',
                                            'not_achieved'       => 'danger',
                                            'in_progress'        => 'primary',
                                            default              => 'secondary'
                                        };
                                        $objIcon = match($obj->status) {
                                            'achieved'           => 'feather-check-circle',
                                            'partially_achieved' => 'feather-alert-circle',
                                            'not_achieved'       => 'feather-x-circle',
                                            'in_progress'        => 'feather-clock',
                                            default              => 'feather-help-circle'
                                        };
                                    @endphp
                                    <div class="d-flex flex-column align-items-start gap-1">
                                        <x-ui.badge soft variant="{{ $objBadge }}" class="text-capitalize fs-10 px-2 py-0.5 text-nowrap d-inline-flex align-items-center gap-1">
                                            <i class="{{ $objIcon }} fs-10"></i>
                                            <span>{{ __('hrms.pip.obj_' . $obj->status) }}</span>
                                        </x-ui.badge>
                                        @if(isset($canManagePip) && $canManagePip && in_array($pip->status, ['active', 'under_review', 'extended']))
                                            <form action="{{ route('hrms.pip.objective.status', [$pip->id, $obj->id]) }}" method="POST" class="m-0 w-100">
                                                @csrf
                                                <select name="status" class="form-select form-select-sm py-0.5 px-1.5 fs-10 text-muted border rounded bg-white shadow-none" onchange="this.form.submit()" style="cursor: pointer; max-width: 110px;" title="{{ __('hrms.pip.update_status_tooltip') }}">
                                                    <option value="pending"            @selected($obj->status === 'pending')>{{ __('hrms.pip.obj_pending') }}</option>
                                                    <option value="in_progress"        @selected($obj->status === 'in_progress')>{{ __('hrms.pip.obj_in_progress') }}</option>
                                                    <option value="achieved"           @selected($obj->status === 'achieved')>{{ __('hrms.pip.obj_achieved') }}</option>
                                                    <option value="partially_achieved" @selected($obj->status === 'partially_achieved')>{{ __('hrms.pip.obj_partially_achieved') }}</option>
                                                    <option value="not_achieved"       @selected($obj->status === 'not_achieved')>{{ __('hrms.pip.obj_not_achieved') }}</option>
                                                </select>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-end pe-3 py-3 align-top">
                                    @if(isset($canManagePip) && $canManagePip && in_array($pip->status, ['active', 'under_review', 'extended']))
                                        <div class="d-inline-flex align-items-center justify-content-end">
                                            <x-ui.action-dropdown id="objActions{{ $obj->id }}">
                                                <li>
                                                    <a class="dropdown-item" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#editObjectiveModal_{{ $obj->id }}">
                                                        <i class="feather-edit-2 me-2 text-primary"></i>{{ __('hrms.pip.edit_objective_details') }}
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item text-danger btn-delete-obj" href="javascript:void(0);" data-obj-id="{{ $obj->id }}">
                                                        <i class="feather-trash-2 me-2"></i>{{ __('hrms.pip.delete_objective') }}
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
                                    <x-ui.modal id="editObjectiveModal_{{ $obj->id }}" title="<i class='feather-edit me-1.5 text-primary'></i> {{ __('hrms.pip.modal_edit_objective_title') }}" size="md" :showFooter="false">
                                        <form action="{{ route('hrms.pip.objective.update', [$pip->id, $obj->id]) }}" method="POST">
                                            @csrf
                                            @method('PUT')
                                            <div class="mb-3">
                                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.pip.field_objective_title') }}" name="title" value="{{ $obj->title }}" :required="true" />
                                            </div>
                                            <div class="mb-3">
                                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.pip.th_description') }}" name="description" rows="2" value="{{ $obj->description }}" />
                                            </div>
                                            <div class="mb-3">
                                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.pip.field_success_benchmark') }}" name="target_criteria" value="{{ $obj->target_criteria }}" />
                                            </div>
                                            <div class="mb-3">
                                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.pip.th_support_provided') }}" name="support_provided" rows="2" value="{{ $obj->support_provided }}" />
                                            </div>
                                            <div class="mb-0">
                                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.pip.th_objective_status') }}" name="status" :required="true">
                                                    <option value="pending" @selected($obj->status === 'pending')>{{ __('hrms.pip.obj_pending') }}</option>
                                                    <option value="in_progress" @selected($obj->status === 'in_progress')>{{ __('hrms.pip.obj_in_progress') }}</option>
                                                    <option value="achieved" @selected($obj->status === 'achieved')>{{ __('hrms.pip.obj_achieved') }}</option>
                                                    <option value="partially_achieved" @selected($obj->status === 'partially_achieved')>{{ __('hrms.pip.obj_partially_achieved') }}</option>
                                                    <option value="not_achieved" @selected($obj->status === 'not_achieved')>{{ __('hrms.pip.obj_not_achieved') }}</option>
                                                </x-ui.odoo-form-ui>
                                            </div>
                                            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                                                <x-ui.button variant="secondary" size="sm" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
                                                <x-ui.button type="submit" variant="primary" size="sm" class="fw-bold px-4">{{ __('hrms.pip.btn_update_objective') }}</x-ui.button>
                                            </div>
                                        </form>
                                    </x-ui.modal>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-3 text-muted fs-12">{{ __('hrms.pip.no_objectives_logged') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 1-ON-1 MILESTONE CHECK-INS TIMELINE -->
        <div>
            <h6 class="fw-bold text-dark mb-3 fs-14"><i class="feather-clock me-1 text-primary"></i> {{ __('hrms.pip.milestone_checkins_title') }}</h6>

            <div class="pip-table-wrapper border rounded bg-white">
                <table class="table table-hover align-top mb-0 fs-12 pip-table-fixed">
                    <thead class="bg-light border-bottom">
                        <tr>
                            <th class="ps-3 py-2.5 text-muted text-uppercase fs-10 fw-bold" style="width: 15%;">{{ __('hrms.pip.th_checkin_date') }}</th>
                            <th class="py-2.5 text-muted text-uppercase fs-10 fw-bold" style="width: 15%;">{{ __('hrms.pip.th_reviewer') }}</th>
                            <th class="py-2.5 text-muted text-uppercase fs-10 fw-bold" style="width: 15%;">{{ __('hrms.pip.th_rating_status') }}</th>
                            <th class="py-2.5 text-muted text-uppercase fs-10 fw-bold" style="width: 48%;">{{ __('hrms.pip.th_feedback_notes') }}</th>
                            <th class="text-end pe-3 py-2.5 text-muted text-uppercase fs-10 fw-bold" style="width: 7%;">{{ __('hrms.pip.th_action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pip->checkins as $checkin)
                            <tr>
                                <td class="ps-3 py-3 align-top fw-bold text-dark">
                                    {{ $checkin->review_date ? $checkin->review_date->format('M d, Y') : __('hrms.common.na') }}
                                </td>
                                <td class="py-3 align-top text-secondary">{{ $checkin->reviewer?->name ?? __('hrms.pip.reviewer_manager') }}</td>
                                <td class="py-3 align-top">
                                    @php
                                        $chkBadge = match($checkin->rating_status) {
                                            'on_track'  => 'success',
                                            'off_track' => 'warning',
                                            'at_risk'   => 'danger',
                                            'exceeding' => 'info',
                                            default     => 'secondary'
                                        };
                                    @endphp
                                    <x-ui.badge soft variant="{{ $chkBadge }}" class="text-capitalize fs-10 px-2 py-0.5">
                                        {{ __('hrms.pip.rating_' . $checkin->rating_status) }}
                                    </x-ui.badge>
                                </td>
                                <td class="py-3 align-top text-secondary lh-base text-break" style="white-space: normal; word-break: break-word;">
                                    {{ $checkin->manager_comments }}
                                </td>
                                <td class="text-end pe-3 py-3 align-top">
                                    @if(isset($canManagePip) && $canManagePip && in_array($pip->status, ['active', 'under_review', 'extended']))
                                        <x-ui.action-dropdown id="chkActions{{ $checkin->id }}">
                                            <li>
                                                <a class="dropdown-item" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#editCheckinModal_{{ $checkin->id }}">
                                                    <i class="feather-edit-2 me-2 text-primary"></i>{{ __('hrms.pip.edit_checkin') }}
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item text-danger btn-delete-checkin" href="javascript:void(0);" data-chk-id="{{ $checkin->id }}">
                                                    <i class="feather-trash-2 me-2"></i>{{ __('hrms.pip.delete_checkin_log') }}
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
                                    <x-ui.modal id="editCheckinModal_{{ $checkin->id }}" title="<i class='feather-edit me-1.5 text-primary'></i> {{ __('hrms.pip.modal_edit_checkin_title') }}" size="md" :showFooter="false">
                                        <form action="{{ route('hrms.pip.checkin.update', [$pip->id, $checkin->id]) }}" method="POST">
                                            @csrf
                                            @method('PUT')
                                            <div class="row g-3 mb-3">
                                                <div class="col-md-6">
                                                    <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('hrms.pip.field_checkin_date') }}" name="checkin_date" value="{{ $checkin->review_date?->format('Y-m-d') }}" :required="true" />
                                                </div>
                                                <div class="col-md-6">
                                                    <x-ui.odoo-form-ui type="select" label="{{ __('hrms.pip.field_progress_rating') }}" name="rating_status" :required="true">
                                                        <option value="on_track" @selected($checkin->rating_status === 'on_track')>{{ __('hrms.pip.rating_on_track') }}</option>
                                                        <option value="off_track" @selected($checkin->rating_status === 'off_track')>{{ __('hrms.pip.rating_off_track') }}</option>
                                                        <option value="at_risk" @selected($checkin->rating_status === 'at_risk')>{{ __('hrms.pip.rating_at_risk') }}</option>
                                                        <option value="exceeding" @selected($checkin->rating_status === 'exceeding')>{{ __('hrms.pip.rating_exceeding') }}</option>
                                                    </x-ui.odoo-form-ui>
                                                </div>
                                            </div>
                                            <div class="row g-3 mb-3">
                                                <div class="col-md-6">
                                                    <x-ui.odoo-form-ui type="input" inputType="number" step="0.5" min="1" max="5" label="{{ __('hrms.pip.field_numeric_score') }}" name="rating" value="{{ $checkin->rating }}" placeholder="e.g. 3.5" />
                                                </div>
                                                <div class="col-md-6">
                                                    <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('hrms.pip.field_next_checkin_date') }}" name="next_checkin_date" value="{{ $checkin->next_checkin_date?->format('Y-m-d') }}" />
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.pip.field_manager_feedback') }}" name="manager_comments" rows="3" value="{{ $checkin->manager_comments }}" :required="true" />
                                            </div>
                                            <div class="mb-3">
                                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.pip.field_concerns_noted') }}" name="concerns_noted" rows="2" value="{{ $checkin->concerns_noted }}" placeholder="{{ __('hrms.pip.placeholder_concerns') }}" />
                                            </div>
                                            <div class="mb-0">
                                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.pip.field_support_action_items') }}" name="support_action_items" rows="2" value="{{ $checkin->support_action_items }}" placeholder="{{ __('hrms.pip.placeholder_support_actions') }}" />
                                            </div>
                                            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                                                <x-ui.button variant="secondary" size="sm" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
                                                <x-ui.button type="submit" variant="primary" size="sm" class="fw-bold px-4">{{ __('hrms.pip.btn_update_checkin') }}</x-ui.button>
                                            </div>
                                        </form>
                                    </x-ui.modal>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted fs-12">
                                    {{ __('hrms.pip.no_checkins_recorded') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

@if(isset($canManagePip) && $canManagePip)
<!-- LOG CHECK-IN MODAL (Outside single panel) -->
<x-ui.modal id="addCheckinModal" title="<i class='feather-check-square me-1.5 text-primary'></i> {{ __('hrms.pip.modal_log_checkin_title') }}" size="md" :showFooter="false">
    <form action="{{ route('hrms.pip.checkin.store', $pip->id) }}" method="POST">
        @csrf
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('hrms.pip.field_checkin_date') }}" name="checkin_date" value="{{ date('Y-m-d') }}" :required="true" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.pip.field_progress_rating') }}" name="rating_status" :required="true">
                    <option value="on_track">{{ __('hrms.pip.rating_on_track') }}</option>
                    <option value="off_track">{{ __('hrms.pip.rating_off_track') }}</option>
                    <option value="at_risk">{{ __('hrms.pip.rating_at_risk') }}</option>
                    <option value="exceeding">{{ __('hrms.pip.rating_exceeding') }}</option>
                </x-ui.odoo-form-ui>
            </div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="number" step="0.5" min="1" max="5" label="{{ __('hrms.pip.field_numeric_score') }}" name="rating" placeholder="e.g. 3.5" />
            </div>
            <div class="col-md-6">
                <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('hrms.pip.field_next_checkin_date') }}" name="next_checkin_date" />
            </div>
        </div>
        <div class="mb-3">
            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.pip.field_manager_feedback') }}" name="manager_comments" rows="3" placeholder="{{ __('hrms.pip.placeholder_manager_comments') }}" :required="true" />
        </div>
        <div class="mb-3">
            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.pip.field_concerns_noted') }}" name="concerns_noted" rows="2" placeholder="{{ __('hrms.pip.placeholder_concerns') }}" />
        </div>
        <div class="mb-0">
            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.pip.field_support_action_items') }}" name="support_action_items" rows="2" placeholder="{{ __('hrms.pip.placeholder_support_actions') }}" />
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <x-ui.button variant="secondary" size="sm" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="primary" size="sm" class="fw-bold px-4">{{ __('hrms.pip.btn_save_checkin') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- FINAL EVALUATION MODAL (Outside single panel) -->
<x-ui.modal id="evaluateModal" title="<i class='feather-flag me-1.5 text-warning'></i> {{ __('hrms.pip.modal_final_eval_title') }}" size="md" :showFooter="false">
    <form action="{{ route('hrms.pip.evaluate', $pip->id) }}" method="POST">
        @csrf
        <div class="mb-3">
            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.pip.field_evaluation_outcome') }}" name="evaluation_outcome" id="evalOutcome" :required="true">
                <option value="successful_completion">{{ __('hrms.pip.outcome_successful') }}</option>
                <option value="pip_extension">{{ __('hrms.pip.outcome_extension') }}</option>
                <option value="role_reassignment">{{ __('hrms.pip.outcome_role_reassignment') }}</option>
                <option value="termination">{{ __('hrms.pip.outcome_termination') }}</option>
            </x-ui.odoo-form-ui>
        </div>
        <div class="mb-3 d-none" id="extensionDaysField">
            <x-ui.odoo-form-ui type="input" inputType="number" label="{{ __('hrms.pip.field_extension_days') }}" name="extension_days" value="30" min="7" max="90" placeholder="e.g. 30" />
            <small class="text-muted fs-11">{{ __('hrms.pip.extension_current_end_date', ['date' => $pip->end_date?->format('M d, Y')]) }}</small>
        </div>
        <div class="mb-0">
            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.pip.field_final_remarks') }}" name="final_remarks" rows="4" placeholder="{{ __('hrms.pip.placeholder_final_remarks') }}" :required="true" />
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <x-ui.button variant="secondary" size="sm" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="warning" size="sm" class="fw-bold text-dark px-4">{{ __('hrms.pip.btn_submit_final_evaluation') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<!-- ADD OBJECTIVE MODAL -->
<x-ui.modal id="addObjectiveModal" title="<i class='feather-target me-1.5 text-primary'></i> {{ __('hrms.pip.modal_add_objective_title') }}" size="md" :showFooter="false">
    <form action="{{ route('hrms.pip.objective.store', $pip->id) }}" method="POST">
        @csrf
        <div class="mb-3">
            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.pip.field_objective_title') }}" name="title" placeholder="{{ __('hrms.pip.placeholder_objective_title') }}" :required="true" />
        </div>
        <div class="mb-3">
            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.pip.th_description') }}" name="description" rows="2" placeholder="{{ __('hrms.pip.placeholder_objective_desc') }}" />
        </div>
        <div class="mb-3">
            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.pip.field_success_benchmark') }}" name="target_criteria" placeholder="{{ __('hrms.pip.placeholder_target_criteria') }}" />
        </div>
        <div class="mb-0">
            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.pip.th_support_provided') }}" name="support_provided" rows="2" placeholder="{{ __('hrms.pip.placeholder_support_provided') }}" />
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <x-ui.button variant="secondary" size="sm" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="primary" size="sm" class="fw-bold px-4">{{ __('hrms.pip.btn_add_objective') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>
@endif

<!-- ACKNOWLEDGE PIP MODAL -->
<x-ui.modal id="acknowledgePipModal" title="<i class='feather-check-square me-1.5 text-warning'></i> {{ __('hrms.pip.modal_ack_title') }}" size="md" :showFooter="false">
    <form action="{{ route('hrms.pip.acknowledge', $pip->id) }}" method="POST">
        @csrf
        <p class="fs-13 text-secondary mb-3">
            {{ __('hrms.pip.ack_declaration_text', ['name' => $pip->employee?->full_name, 'number' => $pip->pip_number]) }}
        </p>
        <div class="mb-0">
            <x-ui.odoo-form-ui type="input" label="{{ __('hrms.pip.field_digital_signature') }}" name="signature_data" placeholder="{{ __('hrms.pip.placeholder_digital_signature') }}" value="{{ $pip->employee?->full_name }}" :required="true" />
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <x-ui.button variant="secondary" size="sm" data-bs-dismiss="modal">{{ __('hrms.common.cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="warning" size="sm" class="fw-bold text-dark px-4">{{ __('hrms.pip.btn_submit_acknowledgement') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>

<x-ui.confirmation-modal />

@push('scripts')
<script>
    $(document).ready(function() {
        $('#addCheckinModal, #evaluateModal, #addObjectiveModal, #acknowledgePipModal').each(function() {
            if ($(this).parent().get(0) !== document.body) {
                $(this).appendTo(document.body);
            }
        });

        // Delete Objective confirmation handler
        $(document).on('click', '.btn-delete-obj', function(e) {
            e.preventDefault();
            const id = $(this).data('obj-id');
            confirmAction(@json(__('hrms.pip.confirm_delete_objective')), function() {
                const form = document.getElementById('deleteObjForm' + id);
                if (form) form.submit();
            }, { title: @json(__('hrms.pip.modal_delete_objective_title')), confirmText: @json(__('hrms.pip.delete_confirm_btn')), variant: 'danger' });
        });

        // Delete Checkin confirmation handler
        $(document).on('click', '.btn-delete-checkin', function(e) {
            e.preventDefault();
            const id = $(this).data('chk-id');
            confirmAction(@json(__('hrms.pip.confirm_delete_checkin')), function() {
                const form = document.getElementById('deleteCheckinForm' + id);
                if (form) form.submit();
            }, { title: @json(__('hrms.pip.modal_delete_checkin_title')), confirmText: @json(__('hrms.pip.delete_confirm_btn')), variant: 'danger' });
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