@extends('layouts.duralux')

@section('title', 'LEAVE PLAN SETTINGS | SaaS ERP')
@section('page-title', 'Leave Plans Configuration')
@section('breadcrumb', 'HRMS / Leave Plan Settings')

@section('content')
    <style>
        /* Sidebar and Workspace layout alignment */
        @media (min-width: 992px) {
            .nxl-content {
                padding: 0 !important;
            }
            .page-header {
                padding: 24px 24px 16px 24px !important;
                margin-bottom: 0 !important;
                border-bottom: 1px solid #e5e7eb;
                background-color: #fff;
            }
            .main-content {
                padding: 0 !important;
            }
            .settings-container {
                display: flex;
                min-height: calc(100vh - 120px);
                background-color: #f8fafc;
            }
            .settings-sidebar-col {
                width: 280px;
                min-width: 280px;
                background-color: #fff;
                border-right: 1px solid #e5e7eb;
                display: flex;
                flex-direction: column;
            }
            .settings-content-col {
                flex-grow: 1;
                padding: 24px 30px;
                background-color: #f8fafc;
            }
        }

        @media (max-width: 991.98px) {
            .settings-sidebar-col {
                width: 100%;
                background-color: #fff;
                border-bottom: 1px solid #e5e7eb;
                margin-bottom: 20px;
                padding: 10px;
            }
            .settings-content-col {
                width: 100%;
                padding: 0 15px;
            }
        }

        /* Sidebar pills overrides */
        #settingsSubSidebar .nav-link {
            background-color: transparent;
            transition: all 0.2s ease-in-out;
            border-radius: 6px !important;
            font-size: 14px;
            font-weight: 500;
            color: #475569 !important;
            padding: 12px 16px !important;
            border: 0 !important;
        }
        #settingsSubSidebar .nav-link:hover {
            background-color: #f1f5f9;
            color: var(--bs-primary) !important;
        }
        #settingsSubSidebar .nav-link.active {
            background-color: var(--bs-primary) !important;
            color: #fff !important;
            font-weight: 600;
        }

        /* Plans List items styling */
        .plan-item {
            border-left: 4px solid transparent !important;
            transition: all 0.15s ease-in-out;
            border-bottom: 1px solid #f1f5f9 !important;
        }
        .plan-item.active {
            background-color: #f1f5f9 !important;
            border-left-color: var(--bs-primary) !important;
        }
        .plan-item:hover:not(.active) {
            background-color: #f8fafc !important;
        }
    </style>

    <div class="settings-container">
        <!-- Sidebar Column -->
        <div class="settings-sidebar-col">
            @include('modules.hrms.partials.settings-sidebar')
        </div>

        <!-- Content Column -->
        <div class="settings-content-col">
            @if(session('success'))
                <x-ui.alert variant="success" icon="feather-check-circle" dismissible>
                    {{ session('success') }}
                </x-ui.alert>
            @endif
            @if(session('error'))
                <x-ui.alert variant="danger" icon="feather-alert-triangle" dismissible>
                    {{ session('error') }}
                </x-ui.alert>
            @endif

            <!-- Single Outer Card spanning full width -->
            <div class="col-12">
                <x-ui.card title="Leave Plans" bodyClass="p-0" stretch>
                    <x-slot name="headerAction">
                        <x-ui.button variant="primary" size="sm" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addLeavePlanModal">
                            Add Leave Plan
                        </x-ui.button>
                    </x-slot>

                    <div class="row g-0">
                        <!-- LEFT COLUMN: ALL PLANS NAMES ONLY -->
                        <div class="col-md-4 col-12 border-end">
                            <div class="list-group list-group-flush rounded-0" style="min-height: 400px; max-height: 600px; overflow-y: auto;">
                                @forelse($leavePlans as $plan)
                                    @php
                                        $isActive = ($selectedPlan && $selectedPlan->id === $plan->id) || (!$selectedPlan && $loop->first);
                                    @endphp
                                    <a href="javascript:void(0);" 
                                       class="list-group-item list-group-item-action py-3 px-4 plan-item plan-switch-btn {{ $isActive ? 'active' : '' }}"
                                       data-target="#plan-details-{{ $plan->id }}"
                                       data-plan-id="{{ $plan->id }}">
                                        <span class="fw-bold {{ $isActive ? 'text-primary' : 'text-dark' }}" style="font-size: 14px;">
                                            {{ $plan->name }}
                                        </span>
                                    </a>
                                @empty
                                    <div class="text-center py-5 text-muted px-3">
                                        <i class="feather-calendar fs-24 mb-2 d-block text-secondary"></i>
                                        <span>No leave plans configured yet.</span>
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <!-- RIGHT COLUMN: SELECTED PLAN DETAILS & LEAVE TYPES TABLE -->
                        <div class="col-md-8 col-12">
                            @forelse($leavePlans as $plan)
                                @php
                                    $isPaneActive = ($selectedPlan && $selectedPlan->id === $plan->id) || (!$selectedPlan && $loop->first);
                                @endphp
                                <div class="plan-details-pane {{ $isPaneActive ? '' : 'd-none' }}" id="plan-details-{{ $plan->id }}">
                                    <div class="p-4">
                                        <!-- Selected Plan Details and Actions -->
                                        <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                                            <div>
                                                <h5 class="fw-bold text-dark mb-1" style="font-size: 16px;">{{ $plan->name }}</h5>
                                                <div class="text-muted d-flex align-items-center gap-2" style="font-size: 12px;">
                                                    <span><i class="feather-briefcase me-1"></i>{{ $plan->company ? $plan->company->company_name : 'All Companies' }}</span>
                                                    <span>&bull;</span>
                                                    <span><i class="feather-calendar me-1"></i>Effective From: <strong>{{ $plan->effective_from ? $plan->effective_from->format('d M, Y') : '-' }}</strong></span>
                                                    <span>&bull;</span>
                                                    <span>
                                                        @if($plan->status)
                                                            <span class="text-success"><i class="feather-check-circle me-1"></i>Active</span>
                                                        @else
                                                            <span class="text-danger"><i class="feather-slash me-1"></i>Inactive</span>
                                                        @endif
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <!-- Actions Dropdown for Leave Plan -->
                                            <div class="dropdown">
                                                <a href="javascript:void(0);" class="text-muted px-2" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="feather-more-vertical fs-18"></i>
                                                </a>
                                                <ul class="dropdown-menu dropdown-menu-end shadow border p-0" style="min-width: 95px; width: 95px; font-size: 12px; z-index: 1050;">
                                                    <li>
                                                        <a class="dropdown-item edit-plan-btn py-1.5 px-3" href="javascript:void(0);" data-plan="{{ base64_encode($plan->toJson()) }}">
                                                            <i class="feather-edit me-1" style="font-size: 11px;"></i> Edit
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider m-0"></li>
                                                    <li>
                                                        <form action="{{ route('hrms.leave-structure.plan.destroy', $plan->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this plan and all its configured leave types?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="dropdown-item text-danger py-1.5 px-3">
                                                                <i class="feather-trash-2 me-1" style="font-size: 11px;"></i> Delete
                                                            </button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>

                                        @if($plan->description)
                                            <div class="p-3 bg-light rounded mb-4" style="font-size: 13px; color: #475569;">
                                                <i class="feather-info me-2 text-primary"></i>{{ $plan->description }}
                                            </div>
                                        @endif

                                        <!-- Sub-header for Leave Types list -->
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="fw-bold text-dark mb-0" style="font-size: 14px;">Leave Types</h6>
                                            <x-ui.button variant="primary" size="sm" icon="feather-plus" class="add-type-trigger-btn" data-plan-id="{{ $plan->id }}" data-bs-toggle="modal" data-bs-target="#addLeaveTypeModal">
                                                Add Leave Type
                                            </x-ui.button>
                                        </div>

                                        <!-- Table (Columns: Leave Type, Quota, Action) -->
                                        <div class="border rounded bg-white">
                                            <table class="table table-hover mb-0 align-middle" style="font-size: 13px;">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Leave Type</th>
                                                        <th>Quota</th>
                                                        <th width="120" class="text-end">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse($plan->types as $type)
                                                        <tr>
                                                            <td>
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <span class="badge text-uppercase px-2 py-1 fw-bold" style="background-color: {{ $type->color }}22; color: {{ $type->color }}; border: 1px solid {{ $type->color }}; font-size: 10px;">
                                                                        {{ $type->code }}
                                                                    </span>
                                                                    <span class="fw-semibold text-dark">{{ $type->name }}</span>
                                                                    @if($type->type === 'unpaid')
                                                                        <span class="text-muted fs-11">(Unpaid)</span>
                                                                    @endif
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <span class="fw-bold text-dark">{{ floatval($type->quota) }} Days</span>
                                                            </td>
                                                            <td class="text-end">
                                                                <div class="d-flex justify-content-end align-items-center gap-1">
                                                                    <!-- Settings icon -->
                                                                    <x-ui.icon-btn variant="soft-secondary" icon="feather-settings" title="Configure Rules" />
                                                                    
                                                                    <!-- 3-Dot Dropdown -->
                                                                    <div class="dropdown d-inline-block">
                                                                        <a href="javascript:void(0);" class="text-muted px-2" data-bs-toggle="dropdown" aria-expanded="false">
                                                                            <i class="feather-more-vertical fs-14"></i>
                                                                        </a>
                                                                        <ul class="dropdown-menu dropdown-menu-end shadow border p-0" style="min-width: 95px; width: 95px; font-size: 12px; z-index: 1050;">
                                                                            <li>
                                                                                <a class="dropdown-item edit-type-btn py-1.5 px-3" href="javascript:void(0);" data-type="{{ base64_encode($type->toJson()) }}">
                                                                                    <i class="feather-edit me-1" style="font-size: 11px;"></i> Edit
                                                                                </a>
                                                                            </li>
                                                                            <li><hr class="dropdown-divider m-0"></li>
                                                                            <li>
                                                                                <form action="{{ route('hrms.leave-structure.type.destroy', $type->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this leave type?');">
                                                                                    @csrf
                                                                                    @method('DELETE')
                                                                                    <button type="submit" class="dropdown-item text-danger py-1.5 px-3">
                                                                                        <i class="feather-trash-2 me-1" style="font-size: 11px;"></i> Delete
                                                                                    </button>
                                                                                </form>
                                                                            </li>
                                                                        </ul>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="3" class="text-center py-4 text-muted">
                                                                No leave types configured for this plan yet. Click "Add Leave Type" above to configure.
                                                            </td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <!-- Empty state when no plan selected/exists -->
                                <div class="text-center py-5 text-muted my-5">
                                    <i class="feather-calendar text-secondary mb-3 d-block" style="font-size: 48px;"></i>
                                    <h5 class="fw-bold text-dark">No Leave Plan Selected</h5>
                                    <p class="text-muted">Please select a leave plan from the left list or create a new plan.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </x-ui.card>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- ADD LEAVE PLAN MODAL -->
    <!-- ========================================================================= -->
    <div class="modal fade" id="addLeavePlanModal" tabindex="-1" aria-labelledby="addLeavePlanModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="addLeavePlanModalLabel">Create Leave Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.leave-structure.plan.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-bold">Plan Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="e.g. Corporate Plan 2026" required>
                            </div>
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-bold">Legal Entity (Company)</label>
                                <select name="company_id" class="form-select">
                                    <option value="">Apply to All Companies</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-bold">Effective From <span class="text-danger">*</span></label>
                                <input type="date" name="effective_from" class="form-control" required>
                            </div>
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-bold">Status <span class="text-danger">*</span></label>
                                <select name="status" class="form-select">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-12 col-12">
                                <label class="form-label fw-bold">Description</label>
                                <textarea name="description" class="form-control" placeholder="Provide details about the plan parameters..." rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Create Plan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- EDIT LEAVE PLAN MODAL -->
    <!-- ========================================================================= -->
    <div class="modal fade" id="editLeavePlanModal" tabindex="-1" aria-labelledby="editLeavePlanModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="editLeavePlanModalLabel">Edit Leave Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editLeavePlanForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-bold">Plan Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="edit_plan_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-bold">Legal Entity (Company)</label>
                                <select name="company_id" id="edit_plan_company_id" class="form-select">
                                    <option value="">Apply to All Companies</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-bold">Effective From <span class="text-danger">*</span></label>
                                <input type="date" name="effective_from" id="edit_plan_effective_from" class="form-control" required>
                            </div>
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-bold">Status <span class="text-danger">*</span></label>
                                <select name="status" id="edit_plan_status" class="form-select">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-12 col-12">
                                <label class="form-label fw-bold">Description</label>
                                <textarea name="description" id="edit_plan_description" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- ADD LEAVE TYPE MODAL -->
    <!-- ========================================================================= -->
    <div class="modal fade" id="addLeaveTypeModal" tabindex="-1" aria-labelledby="addLeaveTypeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="addLeaveTypeModalLabel">Add Leave Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.leave-structure.type.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-bold">Leave Plan <span class="text-danger">*</span></label>
                                <select name="leave_plan_id" id="add_type_plan_id" class="form-select" required>
                                    <option value="">Select Leave Plan</option>
                                    @foreach($leavePlans as $plan)
                                        <option value="{{ $plan->id }}" {{ $selectedPlan && $selectedPlan->id === $plan->id ? 'selected' : '' }}>{{ $plan->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-bold">Type Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="e.g. Sick Leave" required>
                            </div>
                            <div class="col-md-4 col-12">
                                <label class="form-label fw-bold">Code <span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control" placeholder="e.g. SL" required>
                            </div>
                            <div class="col-md-4 col-12">
                                <label class="form-label fw-bold">Annual Quota (Days) <span class="text-danger">*</span></label>
                                <input type="number" step="0.5" name="quota" class="form-control" placeholder="e.g. 12" min="0" required>
                            </div>
                            <div class="col-md-4 col-12">
                                <label class="form-label fw-bold">Color Theme <span class="text-danger">*</span></label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="color" name="color" class="form-control form-control-color" value="#3b82f6" style="width: 50px;" required>
                                    <span class="text-muted fs-12">Click to select color</span>
                                </div>
                            </div>
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-bold d-block">Classification <span class="text-danger">*</span></label>
                                <div class="d-flex gap-4 mt-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="type" id="add_type_paid" value="paid" checked required>
                                        <label class="form-check-label fw-semibold text-dark" for="add_type_paid">
                                            Paid Leave
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="type" id="add_type_unpaid" value="unpaid" required>
                                        <label class="form-check-label fw-semibold text-dark" for="add_type_unpaid">
                                            Unpaid Leave
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-bold">Status <span class="text-danger">*</span></label>
                                <select name="status" class="form-select">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-12 col-12">
                                <label class="form-label fw-bold">Description</label>
                                <textarea name="description" class="form-control" placeholder="Allotment rules, carry forward specifications, etc..." rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Add Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- EDIT LEAVE TYPE MODAL -->
    <!-- ========================================================================= -->
    <div class="modal fade" id="editLeaveTypeModal" tabindex="-1" aria-labelledby="editLeaveTypeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="editLeaveTypeModalLabel">Edit Leave Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editLeaveTypeForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-bold">Leave Plan <span class="text-danger">*</span></label>
                                <select name="leave_plan_id" id="edit_type_plan_id" class="form-select" required>
                                    <option value="">Select Leave Plan</option>
                                    @foreach($leavePlans as $plan)
                                        <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-bold">Type Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="edit_type_name" class="form-control" required>
                            </div>
                            <div class="col-md-4 col-12">
                                <label class="form-label fw-bold">Code <span class="text-danger">*</span></label>
                                <input type="text" name="code" id="edit_type_code" class="form-control" required>
                            </div>
                            <div class="col-md-4 col-12">
                                <label class="form-label fw-bold">Annual Quota (Days) <span class="text-danger">*</span></label>
                                <input type="number" step="0.5" name="quota" id="edit_type_quota" class="form-control" min="0" required>
                            </div>
                            <div class="col-md-4 col-12">
                                <label class="form-label fw-bold">Color Theme <span class="text-danger">*</span></label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="color" name="color" id="edit_type_color" class="form-control form-control-color" style="width: 50px;" required>
                                    <span class="text-muted fs-12">Click to select color</span>
                                </div>
                            </div>
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-bold d-block">Classification <span class="text-danger">*</span></label>
                                <div class="d-flex gap-4 mt-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="type" id="edit_type_paid" value="paid" required>
                                        <label class="form-check-label fw-semibold text-dark" for="edit_type_paid">
                                            Paid Leave
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="type" id="edit_type_unpaid" value="unpaid" required>
                                        <label class="form-check-label fw-semibold text-dark" for="edit_type_unpaid">
                                            Unpaid Leave
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-bold">Status <span class="text-danger">*</span></label>
                                <select name="status" id="edit_type_status" class="form-select">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-12 col-12">
                                <label class="form-label fw-bold">Description</label>
                                <textarea name="description" id="edit_type_description" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Move modals to body root to avoid stacking context issues
            document.querySelectorAll('.modal').forEach(modal => {
                document.body.appendChild(modal);
            });

            // Manual dropdown toggle handler fallback to bypass theme intercepts
            $(document).on('click', '[data-bs-toggle="dropdown"]', function(e) {
                e.preventDefault();
                e.stopPropagation();
                let dropdownMenu = $(this).siblings('.dropdown-menu');
                let isShown = dropdownMenu.hasClass('show');
                
                // Close all other open dropdowns first
                $('.dropdown-menu').removeClass('show');
                
                if (!isShown) {
                    dropdownMenu.addClass('show');
                }
            });

            // Close dropdowns when clicking anywhere outside
            $(document).on('click', function() {
                $('.dropdown-menu').removeClass('show');
            });

            // Client-side plan switching (no page reloads)
            $(document).on('click', '.plan-switch-btn', function(e) {
                e.preventDefault();
                let clicked = $(this);
                let targetPaneId = clicked.attr('data-target');
                let planId = clicked.attr('data-plan-id');

                // Switch active class in list items
                $('.plan-switch-btn').removeClass('active');
                $('.plan-switch-btn span').removeClass('text-primary').addClass('text-dark');
                
                clicked.addClass('active');
                clicked.find('span').removeClass('text-dark').addClass('text-primary');

                // Toggle visibility of plan details panes
                $('.plan-details-pane').addClass('d-none');
                $(targetPaneId).removeClass('d-none');

                // Update URL parameter without reload
                if (history.pushState) {
                    let newurl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?plan_id=' + planId;
                    window.history.pushState({path:newurl}, '', newurl);
                }
            });

            // Set up preselected plan when opening add leave type
            $(document).on('click', '.add-type-trigger-btn', function() {
                let planId = $(this).attr('data-plan-id');
                $('#add_type_plan_id').val(planId);
            });

            // Edit Leave Plan Trigger (opens modal, populates form values)
            $(document).on('click', '.edit-plan-btn', function() {
                let dataStr = $(this).attr('data-plan');
                if (!dataStr) return;

                let plan = JSON.parse(atob(dataStr));
                
                $('#editLeavePlanForm').attr('action', `/hrms/leave-structure/plan/update/${plan.id}`);
                $('#edit_plan_name').val(plan.name);
                $('#edit_plan_company_id').val(plan.company_id || '');
                
                if (plan.effective_from) {
                    let dateObj = new Date(plan.effective_from);
                    let formattedDate = dateObj.toISOString().split('T')[0];
                    $('#edit_plan_effective_from').val(formattedDate);
                } else {
                    $('#edit_plan_effective_from').val('');
                }
                
                let statusVal = (plan.status === true || plan.status === 1 || plan.status === '1') ? '1' : '0';
                $('#edit_plan_status').val(statusVal);
                $('#edit_plan_description').val(plan.description || '');

                $('#editLeavePlanModal').modal('show');
            });

            // Edit Leave Type Trigger (opens modal, populates form values)
            $(document).on('click', '.edit-type-btn', function() {
                let dataStr = $(this).attr('data-type');
                if (!dataStr) return;

                let type = JSON.parse(atob(dataStr));
                
                $('#editLeaveTypeForm').attr('action', `/hrms/leave-structure/type/update/${type.id}`);
                $('#edit_type_plan_id').val(type.leave_plan_id);
                $('#edit_type_name').val(type.name);
                $('#edit_type_code').val(type.code);
                $('#edit_type_quota').val(type.quota);
                $('#edit_type_color').val(type.color || '#3b82f6');
                
                if (type.type === 'paid') {
                    $('#edit_type_paid').prop('checked', true);
                } else {
                    $('#edit_type_unpaid').prop('checked', true);
                }
                
                let statusVal = (type.status === true || type.status === 1 || type.status === '1') ? '1' : '0';
                $('#edit_type_status').val(statusVal);
                $('#edit_type_description').val(type.description || '');

                $('#editLeaveTypeModal').modal('show');
            });
        });
    </script>
@endsection
