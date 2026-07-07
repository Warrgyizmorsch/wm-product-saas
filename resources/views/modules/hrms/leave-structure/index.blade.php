@extends('layouts.duralux')

@section('title', 'LEAVE PLAN SETTINGS | SaaS ERP')
@section('page-title', 'Leave Plans Configuration')
@section('breadcrumb', 'HRMS / Leave Plan Settings')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

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
                                            <form action="{{ route('hrms.leave-structure.plan.destroy', $plan->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this plan and all its configured leave types?');">
                                                @csrf
                                                @method('DELETE')
                                                <x-ui.action-dropdown>
                                                    <li>
                                                        <a class="dropdown-item edit-plan-btn" href="javascript:void(0)" data-plan="{{ base64_encode($plan->toJson()) }}">
                                                            <i class="feather feather-edit-3 me-3"></i>
                                                            <span>Edit Plan</span>
                                                        </a>
                                                    </li>
                                                    <li class="dropdown-divider"></li>
                                                    <li>
                                                        <button type="submit" class="dropdown-item text-danger border-0 bg-transparent w-100 text-start d-flex align-items-center">
                                                            <i class="feather feather-trash-2 me-3"></i>
                                                            <span>Delete Plan</span>
                                                        </button>
                                                    </li>
                                                </x-ui.action-dropdown>
                                            </form>
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
                                                                <form action="{{ route('hrms.leave-structure.type.destroy', $type->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this leave type?');">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <div class="hstack gap-2 justify-content-end">
                                                                        <a href="javascript:void(0)" class="action-dropdown-btn configure-rules-btn" data-type-id="{{ $type->id }}" data-type-name="{{ $type->name }}" data-rules="{{ json_encode($type->rules) }}" title="Configure Rules" data-bs-toggle="tooltip">
                                                                            <i class="feather feather-settings"></i>
                                                                        </a>
                                                                        <x-ui.action-dropdown>
                                                                            <li>
                                                                                <a class="dropdown-item edit-type-btn" href="javascript:void(0)" data-type="{{ base64_encode($type->toJson()) }}">
                                                                                    <i class="feather feather-edit-3 me-3"></i>
                                                                                    <span>Edit Type</span>
                                                                                </a>
                                                                            </li>
                                                                            <li class="dropdown-divider"></li>
                                                                            <li>
                                                                                <button type="submit" class="dropdown-item text-danger border-0 bg-transparent w-100 text-start d-flex align-items-center">
                                                                                    <i class="feather feather-trash-2 me-3"></i>
                                                                                    <span>Delete Type</span>
                                                                                </button>
                                                                            </li>
                                                                        </x-ui.action-dropdown>
                                                                    </div>
                                                                </form>
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
                                <x-ui.odoo-form-ui type="input" label="Plan Name" name="name" placeholder="e.g. Corporate Plan 2026" :required="true" :errorText="$errors->first('name')" />
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="select" label="Legal Entity (Company)" name="company_id" select2-selector="default" :errorText="$errors->first('company_id')">
                                    <option value="">Apply to All Companies</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="input" label="Effective From" name="effective_from" inputType="date" :required="true" :errorText="$errors->first('effective_from')" />
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="select" label="Status" name="status" select2-selector="default" :required="true" :errorText="$errors->first('status')">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-12 col-12">
                                <x-ui.odoo-form-ui type="textarea" label="Description" name="description" placeholder="Provide details about the plan parameters..." rows="3" :errorText="$errors->first('description')" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <x-ui.button variant="light" data-bs-dismiss="modal">Close</x-ui.button>
                        <x-ui.button type="submit" variant="primary">Create Plan</x-ui.button>
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
                                <x-ui.odoo-form-ui type="input" label="Plan Name" name="name" id="edit_plan_name" :required="true" :errorText="$errors->first('name')" />
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="select" label="Legal Entity (Company)" name="company_id" id="edit_plan_company_id" select2-selector="default" :errorText="$errors->first('company_id')">
                                    <option value="">Apply to All Companies</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="input" label="Effective From" name="effective_from" id="edit_plan_effective_from" inputType="date" :required="true" :errorText="$errors->first('effective_from')" />
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="select" label="Status" name="status" id="edit_plan_status" select2-selector="default" :required="true" :errorText="$errors->first('status')">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-12 col-12">
                                <x-ui.odoo-form-ui type="textarea" label="Description" name="description" id="edit_plan_description" rows="3" :errorText="$errors->first('description')" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <x-ui.button variant="light" data-bs-dismiss="modal">Close</x-ui.button>
                        <x-ui.button type="submit" variant="primary">Save Changes</x-ui.button>
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
                            <input type="hidden" name="leave_plan_id" id="add_type_plan_id" value="{{ $selectedPlan ? $selectedPlan->id : '' }}">
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="input" label="Type Name" name="name" placeholder="e.g. Sick Leave" :required="true" :errorText="$errors->first('name')" />
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="input" label="Color Theme" name="color" inputType="color" value="#3b82f6" class="form-control-color" style="width: 50px;" :required="true" helperText="Click to select color" :errorText="$errors->first('color')" />
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="input" label="Code" name="code" placeholder="e.g. SL" :required="true" :errorText="$errors->first('code')" />
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="input" label="Annual Quota (Days)" name="quota" inputType="number" step="0.5" placeholder="e.g. 12" min="0" :required="true" :errorText="$errors->first('quota')" />
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="radio" label="Classification" :required="true" :errorText="$errors->first('type')">
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
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="select" label="Status" name="status" select2-selector="default" :required="true" :errorText="$errors->first('status')">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-12 col-12">
                                <x-ui.odoo-form-ui type="textarea" label="Description" name="description" placeholder="Allotment rules, carry forward specifications, etc..." rows="3" :errorText="$errors->first('description')" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <x-ui.button variant="light" data-bs-dismiss="modal">Close</x-ui.button>
                        <x-ui.button type="submit" variant="primary">Add Type</x-ui.button>
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
                            <input type="hidden" name="leave_plan_id" id="edit_type_plan_id">
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="input" label="Type Name" name="name" id="edit_type_name" :required="true" :errorText="$errors->first('name')" />
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="input" label="Color Theme" name="color" id="edit_type_color" inputType="color" class="form-control-color" style="width: 50px;" :required="true" helperText="Click to select color" :errorText="$errors->first('color')" />
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="input" label="Code" name="code" id="edit_type_code" :required="true" :errorText="$errors->first('code')" />
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="input" label="Annual Quota (Days)" name="quota" id="edit_type_quota" inputType="number" step="0.5" min="0" :required="true" :errorText="$errors->first('quota')" />
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="radio" label="Classification" :required="true" :errorText="$errors->first('type')">
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
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="select" label="Status" name="status" id="edit_type_status" select2-selector="default" :required="true" :errorText="$errors->first('status')">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-12 col-12">
                                <x-ui.odoo-form-ui type="textarea" label="Description" name="description" id="edit_type_description" rows="3" :errorText="$errors->first('description')" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <x-ui.button variant="light" data-bs-dismiss="modal">Close</x-ui.button>
                        <x-ui.button type="submit" variant="primary">Save Changes</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal for Configuring Leave Rules -->
    <div class="modal fade" id="leaveRulesModal" tabindex="-1" aria-labelledby="leaveRulesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light px-4 py-3 border-bottom d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="modal-title fw-bold text-dark" id="leaveRulesModalLabel">Leave Rules Configuration</h5>
                        <p class="text-muted mb-0 fs-12">Set up policies, accruals, and constraints for <strong class="text-primary" id="rules-leave-type-name">Casual Leave</strong></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-0">
                    <div class="row g-0">
                        <!-- Left Navigation Column (col-3) -->
                        <div class="col-md-3 border-end bg-light-subtle" style="min-height: 480px;">
                            <div class="nav flex-column nav-pills p-3 gap-2" id="rulesTabList" role="tablist">
                                <button class="nav-link text-start active py-2.5 px-3 d-flex align-items-center gap-2" id="tab-accrual" data-bs-toggle="pill" data-bs-target="#pane-accrual" type="button" role="tab"><i class="feather-calendar"></i> Accrual</button>
                                <button class="nav-link text-start py-2.5 px-3 d-flex align-items-center gap-2" id="tab-application" data-bs-toggle="pill" data-bs-target="#pane-application" type="button" role="tab"><i class="feather-file-text"></i> Leave Application</button>
                                <button class="nav-link text-start py-2.5 px-3 d-flex align-items-center gap-2" id="tab-approval" data-bs-toggle="pill" data-bs-target="#pane-approval" type="button" role="tab"><i class="feather-check-square"></i> Approval</button>
                                <button class="nav-link text-start py-2.5 px-3 d-flex align-items-center gap-2" id="tab-yearend" data-bs-toggle="pill" data-bs-target="#pane-yearend" type="button" role="tab"><i class="feather-refresh-cw"></i> Year End Processing</button>
                                <button class="nav-link text-start py-2.5 px-3 d-flex align-items-center gap-2" id="tab-probation" data-bs-toggle="pill" data-bs-target="#pane-probation" type="button" role="tab"><i class="feather-shield"></i> Probation</button>
                                <button class="nav-link text-start py-2.5 px-3 d-flex align-items-center gap-2" id="tab-notice" data-bs-toggle="pill" data-bs-target="#pane-notice" type="button" role="tab"><i class="feather-alert-triangle"></i> Notice Period</button>
                            </div>
                        </div>
                        
                        <!-- Right Configurations Pane Column (col-9) -->
                        <div class="col-md-9 p-4" style="max-height: 520px; overflow-y: auto;">
                            <input type="hidden" id="rules-leave-type-id">
                            
                            <div class="tab-content" id="rulesTabContent">
                                <!-- Accrual Tab Pane -->
                                <div class="tab-pane fade show active" id="pane-accrual" role="tabpanel">
                                    <h5 class="fw-bold text-dark mb-3">Accrual</h5>
                                    
                                    <!-- Yearly Quota -->
                                    <div class="card border mb-3 bg-light-subtle rounded-3 shadow-none">
                                        <div class="card-header bg-white py-3 px-3 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapseQuota" aria-expanded="true">
                                            <h6 class="fw-bold text-dark mb-0 fs-14"><i class="feather-calendar me-2 text-muted"></i>Yearly Quota</h6>
                                        </div>
                                        <div id="collapseQuota" class="collapse show">
                                            <div class="card-body bg-white border-top p-3 fs-13">
                                                <div class="row align-items-center mb-3">
                                                    <div class="col-sm-4 text-muted">This leave is calculated in:</div>
                                                    <div class="col-sm-8 d-flex gap-3">
                                                        <label class="form-check-label d-flex align-items-center gap-2 cursor-pointer">
                                                            <input type="radio" name="accrual_calculate_in" value="days" class="form-check-input me-2" checked> Days
                                                        </label>
                                                        <label class="form-check-label d-flex align-items-center gap-2 cursor-pointer">
                                                            <input type="radio" name="accrual_calculate_in" value="hours" class="form-check-input me-2"> Hours
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="row align-items-center">
                                                    <div class="col-sm-4 text-muted">Yearly quota:</div>
                                                    <div class="col-sm-8 d-flex align-items-center gap-2">
                                                        <label class="form-check-label d-flex align-items-center gap-2 cursor-pointer me-3">
                                                            <input type="radio" name="accrual_quota_type" value="fixed" class="form-check-input me-2" checked> 
                                                            <input type="number" id="accrual_quota_value" class="odoo-table-input text-center d-inline-block mx-1" style="width: 70px;" value="12"> days
                                                        </label>
                                                        <label class="form-check-label d-flex align-items-center gap-2 cursor-pointer">
                                                            <input type="radio" name="accrual_quota_type" value="unlimited" class="form-check-input me-2"> Unlimited
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Allocation & Accrual Rate -->
                                    <div class="card border mb-3 bg-light-subtle rounded-3 shadow-none">
                                        <div class="card-header bg-white py-3 px-3 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapseRate" aria-expanded="true">
                                            <h6 class="fw-bold text-dark mb-0 fs-14"><i class="feather-trending-up me-2 text-muted"></i>Allocation & Accrual Rate</h6>
                                        </div>
                                        <div id="collapseRate" class="collapse show">
                                            <div class="card-body bg-white border-top p-3 fs-13 d-flex flex-column gap-3">
                                                <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                    <input type="radio" name="accrual_rate" value="periodic" class="form-check-input mt-1">
                                                    <div>
                                                        <span class="fw-semibold text-dark d-block">Leave accrued periodically</span>
                                                        <span class="text-muted fs-11">Leaves are credited automatically on a schedule (e.g. 1.5 days every month).</span>
                                                     </div>
                                                 </label>
                                                 <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                     <input type="radio" name="accrual_rate" value="attendance" class="form-check-input mt-1">
                                                     <div>
                                                         <span class="fw-semibold text-dark d-block">Leave accrues based on attendance</span>
                                                         <span class="text-muted fs-11">Leaves are earned based on actual days worked (e.g. 1 day for every 20 worked days).</span>
                                                     </div>
                                                 </label>
                                                 <div class="ps-4 mt-1 mb-2 d-none" id="accrual_attendance_div">
                                                     <div class="d-flex align-items-center gap-2 fs-13">
                                                         <span>Earn</span>
                                                         <input type="number" id="accrual_attendance_earn" class="odoo-table-input text-center" style="width: 70px;" value="1">
                                                         <span>day(s) of leave for every</span>
                                                         <input type="number" id="accrual_attendance_period" class="odoo-table-input text-center" style="width: 70px;" value="20">
                                                         <span>days worked.</span>
                                                     </div>
                                                 </div>
                                                 <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                     <input type="radio" name="accrual_rate" value="immediate" class="form-check-input mt-1" checked>
                                                     <div>
                                                         <span class="fw-semibold text-dark d-block">Leave quota available immediately</span>
                                                         <span class="text-muted fs-11">The full annual quota is credited upfront on the start of the year or joining date.</span>
                                                     </div>
                                                 </label>
                                             </div>
                                         </div>
                                     </div>
                                     
                                     <!-- Accrual Restrictions -->
                                     <div class="card border bg-light-subtle rounded-3 shadow-none">
                                         <div class="card-header bg-white py-3 px-3 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapseRestrictions" aria-expanded="true">
                                             <h6 class="fw-bold text-dark mb-0 fs-14"><i class="feather-alert-circle me-2 text-muted"></i>Accrual Restrictions</h6>
                                         </div>
                                         <div id="collapseRestrictions" class="collapse show">
                                             <div class="card-body bg-white border-top p-3 fs-13">
                                                 <div class="form-check form-switch mb-1">
                                                     <input class="form-check-input" type="checkbox" id="accrual_limit_carry">
                                                     <label class="form-check-label fw-bold text-dark ms-2" for="accrual_limit_carry">Limit maximum accumulation of leaves</label>
                                                     <div class="text-muted fs-11 ms-2">Cap total leaves an employee can accumulate at any given point.</div>
                                                 </div>
                                                 <div class="ps-4 mt-2 d-none" id="accrual_max_accum_div">
                                                     <div class="d-flex align-items-center gap-2">
                                                         <span>Maximum accumulation balance:</span>
                                                         <input type="number" id="accrual_max_accum_val" class="odoo-table-input text-center" style="width: 70px;" value="30"> days
                                                     </div>
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                 </div>
                                 
                                 <!-- Leave Application Tab Pane -->
                                 <div class="tab-pane fade" id="pane-application" role="tabpanel">
                                     <h5 class="fw-bold text-dark mb-3">Leave Application Settings</h5>
                                     
                                     <!-- Advance application -->
                                     <div class="card border mb-3 rounded-3 shadow-none">
                                         <div class="card-body p-3 fs-13">
                                             <div class="form-check form-switch mb-1">
                                                 <input class="form-check-input" type="checkbox" id="app_apply_in_advance">
                                                 <label class="form-check-label fw-bold text-dark ms-2" for="app_apply_in_advance">Must apply in advance</label>
                                                 <div class="text-muted fs-11 ms-2">Restrict leaves from being applied at the last minute or retroactively.</div>
                                             </div>
                                             <div class="ps-4 mt-2 d-none" id="app_advance_days_div">
                                                 <div class="d-flex align-items-center gap-2">
                                                     <span>Apply at least</span>
                                                     <input type="number" id="app_advance_days" class="odoo-table-input text-center" style="width: 70px;" value="3"> days before leave start date.
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                     
                                     <!-- Duration bounds -->
                                     <div class="card border mb-3 rounded-3 shadow-none">
                                         <div class="card-body p-3 fs-13">
                                             <h6 class="fw-bold text-dark mb-3">Duration Constraints</h6>
                                             <div class="row align-items-center mb-3">
                                                 <div class="col-sm-5 text-muted">Minimum duration per request:</div>
                                                 <div class="col-sm-7 d-flex align-items-center gap-2">
                                                     <input type="number" id="app_min_duration" class="odoo-table-input text-center" style="width: 70px;" value="1"> day(s)
                                                 </div>
                                             </div>
                                             <div class="row align-items-center">
                                                 <div class="col-sm-5 text-muted">Maximum duration per request:</div>
                                                 <div class="col-sm-7 d-flex align-items-center gap-2">
                                                     <input type="number" id="app_max_duration" class="odoo-table-input text-center" style="width: 70px;" value="10"> day(s)
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                     
                                     <!-- Attachments -->
                                     <div class="card border rounded-3 shadow-none">
                                         <div class="card-body p-3 fs-13">
                                             <div class="form-check form-switch mb-1">
                                                 <input class="form-check-input" type="checkbox" id="app_require_attachment">
                                                 <label class="form-check-label fw-bold text-dark" for="app_require_attachment">Require attachment/document proof</label>
                                                 <div class="text-muted fs-11">Force attachments (e.g. medical certificates) for long leaves.</div>
                                             </div>
                                             <div class="ps-4 mt-2 d-none" id="app_attachment_days_div">
                                                 <div class="d-flex align-items-center gap-2">
                                                     <span>Mandatory if leave duration exceeds</span>
                                                     <input type="number" id="app_attachment_days" class="odoo-table-input text-center" style="width: 70px;" value="3"> day(s).
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                 </div>
                                 
                                  <!-- Approval Tab Pane -->
                                  <div class="tab-pane fade" id="pane-approval" role="tabpanel">
                                      <h5 class="fw-bold text-dark mb-3">Approval Workflow</h5>
                                      
                                      <!-- Approval Level -->
                                      <div class="card border mb-3 rounded-3 shadow-none">
                                          <div class="card-body p-3 fs-13">
                                              <h6 class="fw-bold text-dark mb-3">Approval Routing Level</h6>
                                              <div class="d-flex flex-column gap-3">
                                                  <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                      <input type="radio" name="approval_workflow_level" value="auto" class="form-check-input mt-1">
                                                      <div>
                                                          <span class="fw-semibold text-dark d-block">Auto-Approved</span>
                                                          <span class="text-muted fs-11">Requests are approved automatically without manager reviews.</span>
                                                      </div>
                                                  </label>
                                                  <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                      <input type="radio" name="approval_workflow_level" value="1_level" class="form-check-input mt-1" checked>
                                                      <div>
                                                          <span class="fw-semibold text-dark d-block">1-Level Approval</span>
                                                          <span class="text-muted fs-11">Requires approval from one supervisor before being active.</span>
                                                      </div>
                                                  </label>
                                                  <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                      <input type="radio" name="approval_workflow_level" value="2_level" class="form-check-input mt-1">
                                                      <div>
                                                          <span class="fw-semibold text-dark d-block">2-Level Approval</span>
                                                          <span class="text-muted fs-11">Requires sequence of two approvals (e.g. Reporting Manager then HR).</span>
                                                      </div>
                                                  </label>
                                              </div>
                                          </div>
                                      </div>
                                      
                                      <!-- Approver roles definition -->
                                      <div class="card border rounded-3 shadow-none" id="approver_roles_card">
                                          <div class="card-body p-3 fs-13">
                                              <h6 class="fw-bold text-dark mb-3">Workflow Roles</h6>
                                              <div class="row align-items-center mb-3" id="first_approver_row">
                                                  <div class="col-sm-4 text-muted">First Approver:</div>
                                                  <div class="col-sm-8">
                                                      <select id="approval_first_approver" class="odoo-table-select" style="max-width: 250px;">
                                                          <option value="reporting_manager">Reporting Manager</option>
                                                          <option value="department_head">Department Head</option>
                                                          <option value="hr_manager">HR Manager</option>
                                                      </select>
                                                  </div>
                                              </div>
                                              <div class="row align-items-center d-none" id="second_approver_row">
                                                  <div class="col-sm-4 text-muted">Second Approver:</div>
                                                  <div class="col-sm-8">
                                                      <select id="approval_second_approver" class="odoo-table-select" style="max-width: 250px;">
                                                          <option value="hr_manager" selected>HR Manager</option>
                                                          <option value="department_head">Department Head</option>
                                                          <option value="ceo">CEO / Director</option>
                                                      </select>
                                                  </div>
                                              </div>
                                          </div>
                                      </div>
                                  </div>
                                  
                                  <!-- Year End Processing Tab Pane -->
                                  <div class="tab-pane fade" id="pane-yearend" role="tabpanel">
                                      <h5 class="fw-bold text-dark mb-3">Year End Processing</h5>
                                      
                                      <div class="card border rounded-3 shadow-none">
                                          <div class="card-body p-3 fs-13">
                                              <h6 class="fw-bold text-dark mb-3">Action on Unused Balance</h6>
                                              <div class="d-flex flex-column gap-3">
                                                  <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                      <input type="radio" name="yearend_action" value="lapse" class="form-check-input mt-1" checked>
                                                      <div>
                                                          <span class="fw-semibold text-dark d-block">Lapse (Use it or lose it)</span>
                                                          <span class="text-muted fs-11">Unused leaves will reset to 0 at the end of the year.</span>
                                                      </div>
                                                  </label>
                                                  <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                      <input type="radio" name="yearend_action" value="carry_forward" class="form-check-input mt-1">
                                                      <div>
                                                          <span class="fw-semibold text-dark d-block">Carry Forward to next year</span>
                                                          <span class="text-muted fs-11">Transfer unused balances forward, subject to limit rules.</span>
                                                      </div>
                                                  </label>
                                                  <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                      <input type="radio" name="yearend_action" value="encash" class="form-check-input mt-1">
                                                      <div>
                                                          <span class="fw-semibold text-dark d-block">Encashment (Pay out)</span>
                                                          <span class="text-muted fs-11">Compensate employees monetarily for unused leave balance.</span>
                                                      </div>
                                                  </label>
                                              </div>
                                              
                                              <!-- Carry forward value option -->
                                              <div class="border-top pt-3 mt-3 d-none" id="yearend_carry_limit_div">
                                                  <div class="d-flex align-items-center gap-2">
                                                      <span>Maximum days to carry forward:</span>
                                                      <input type="number" id="yearend_max_carry" class="odoo-table-input text-center" style="width: 70px;" value="6"> days
                                                  </div>
                                              </div>
                                              
                                              <!-- Encashment limit value option -->
                                              <div class="border-top pt-3 mt-3 d-none" id="yearend_encash_limit_div">
                                                  <div class="d-flex align-items-center gap-2">
                                                      <span>Maximum days to encash:</span>
                                                      <input type="number" id="yearend_max_encash" class="odoo-table-input text-center" style="width: 70px;" value="5"> days
                                                  </div>
                                              </div>
                                          </div>
                                      </div>
                                  </div>
                                  
                                  <!-- Probation Tab Pane -->
                                  <div class="tab-pane fade" id="pane-probation" role="tabpanel">
                                      <h5 class="fw-bold text-dark mb-3">Probation Period Rules</h5>
                                      
                                      <div class="card border rounded-3 shadow-none">
                                          <div class="card-body p-3 fs-13">
                                              <h6 class="fw-bold text-dark mb-3">Usage during Probation</h6>
                                              <div class="d-flex flex-column gap-3">
                                                  <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                      <input type="radio" name="probation_rule" value="allow" class="form-check-input mt-1" checked>
                                                      <div>
                                                          <span class="fw-semibold text-dark d-block">Allow applying during probation</span>
                                                          <span class="text-muted fs-11">Employees can take this leave immediately after joining.</span>
                                                      </div>
                                                  </label>
                                                  <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                      <input type="radio" name="probation_rule" value="disallow" class="form-check-input mt-1">
                                                      <div>
                                                          <span class="fw-semibold text-dark d-block">Do not allow applying during probation</span>
                                                          <span class="text-muted fs-11">Leave option remains locked until employee gets confirmed.</span>
                                                      </div>
                                                  </label>
                                                  <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                      <input type="radio" name="probation_rule" value="allow_after_months" class="form-check-input mt-1">
                                                      <div>
                                                          <span class="fw-semibold text-dark d-block">Allow after a certain period</span>
                                                          <span class="text-muted fs-11">Employees can apply for this leave after a specific length of service.</span>
                                                      </div>
                                                  </label>
                                              </div>
                                              
                                              <!-- Month value option -->
                                              <div class="border-top pt-3 mt-3 d-none" id="probation_months_div">
                                                  <div class="d-flex align-items-center gap-2">
                                                      <span>Allowed after completing</span>
                                                      <input type="number" id="probation_months" class="odoo-table-input text-center" style="width: 70px;" value="3"> month(s) of joining.
                                                  </div>
                                              </div>
                                          </div>
                                      </div>
                                  </div>
                                  
                                  <!-- Notice Period Tab Pane -->
                                  <div class="tab-pane fade" id="pane-notice" role="tabpanel">
                                      <h5 class="fw-bold text-dark mb-3">Notice Period Rules</h5>
                                      
                                      <div class="card border rounded-3 shadow-none">
                                          <div class="card-body p-3 fs-13">
                                              <h6 class="fw-bold text-dark mb-3">Usage during Notice Period</h6>
                                              <div class="d-flex flex-column gap-3">
                                                  <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                      <input type="radio" name="notice_rule" value="allow" class="form-check-input mt-1" checked>
                                                      <div>
                                                          <span class="fw-semibold text-dark d-block">Allow applying during notice period</span>
                                                          <span class="text-muted fs-11">Employees on notice period can apply for this leave normally.</span>
                                                      </div>
                                                  </label>
                                                  <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                      <input type="radio" name="notice_rule" value="disallow" class="form-check-input mt-1">
                                                      <div>
                                                          <span class="fw-semibold text-dark d-block">Do not allow applying during notice period</span>
                                                          <span class="text-muted fs-11">Leave option becomes unavailable once exit clearance starts.</span>
                                                      </div>
                                                  </label>
                                                  <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                      <input type="radio" name="notice_rule" value="special_approval" class="form-check-input mt-1">
                                                      <div>
                                                          <span class="fw-semibold text-dark d-block">Requires special HR approval</span>
                                                          <span class="text-muted fs-11">Bypasses default flow; direct approval from Corporate HR is mandatory.</span>
                                                      </div>
                                                  </label>
                                              </div>
                                          </div>
                                      </div>
                                  </div>
                             </div>
                         </div>
                     </div>
                 </div>
                 
                 <div class="modal-footer bg-light border-top d-flex justify-content-end gap-2 px-4 py-3">
                     <button type="button" class="btn btn-light fs-13" data-bs-dismiss="modal">Close</button>
                     <button type="button" class="btn btn-primary fs-13" onclick="saveLeaveRules()">Save Rules</button>
                 </div>
             </div>
         </div>
     </div>

     <style>
         /* Sidebar tabs styling inside leave rules modal */
         #rulesTabList .nav-link {
             border-radius: 6px;
             font-size: 13px;
             font-weight: 500;
             color: #475569;
             border: none;
             background-color: transparent;
             transition: all 0.2s ease;
         }
         #rulesTabList .nav-link:hover {
             background-color: #f1f5f9;
             color: var(--bs-primary);
         }
         #rulesTabList .nav-link.active {
             background-color: rgba(var(--bs-primary-rgb), 0.08);
             color: var(--bs-primary);
             font-weight: 600;
         }
     </style>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Initialize Select2 dropdowns on modal open
            $('#addLeavePlanModal, #editLeavePlanModal, #addLeaveTypeModal, #editLeaveTypeModal').on('shown.bs.modal', function () {
                $(this).find('select').each(function() {
                    var $select = $(this);
                    if ($select.hasClass("select2-hidden-accessible")) {
                        $select.select2('destroy');
                    }
                    $select.select2({
                        theme: 'bootstrap-5',
                        dropdownParent: $select.closest('.modal-content'),
                        width: '100%'
                    });
                });
            });

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

            // Trigger configuration modal for Leave Rules
            $(document).on('click', '.configure-rules-btn', function() {
                let typeId = $(this).attr('data-type-id');
                let typeName = $(this).attr('data-type-name');

                $('#rules-leave-type-id').val(typeId);
                $('#rules-leave-type-name').text(typeName);

                // Default fallback rules
                const defaultRules = {
                    accrual: {
                        calculate_in: 'days',
                        quota_type: 'fixed',
                        quota_value: 12,
                        rate: 'immediate',
                        attendance_earn: 1,
                        attendance_period: 20,
                        limit_carry: false,
                        max_accum: 30
                    },
                    application: {
                        apply_in_advance: false,
                        advance_days: 3,
                        min_duration: 1,
                        max_duration: 10,
                        require_attachment: false,
                        attachment_days: 3
                    },
                    approval: {
                        workflow_level: '1_level',
                        first_approver: 'reporting_manager',
                        second_approver: 'hr_manager'
                    },
                    yearend: {
                        action: 'lapse',
                        max_carry: 6,
                        max_encash: 5
                    },
                    probation: {
                        rule: 'allow',
                        months: 3
                    },
                    notice: {
                        rule: 'allow'
                    }
                };

                let rules = defaultRules;
                try {
                    let rulesDataStr = $(this).attr('data-rules');
                    if (rulesDataStr) {
                        let parsed = JSON.parse(rulesDataStr);
                        if (parsed && typeof parsed === 'object') {
                            rules = parsed;
                        }
                    }
                } catch(e) {
                    console.error("Failed to parse database rules:", e);
                }

                // Load state into UI
                $(`input[name="accrual_calculate_in"][value="${rules.accrual?.calculate_in || 'days'}"]`).prop('checked', true);
                $(`input[name="accrual_quota_type"][value="${rules.accrual?.quota_type || 'fixed'}"]`).prop('checked', true);
                $('#accrual_quota_value').val(rules.accrual?.quota_value !== undefined ? rules.accrual.quota_value : 12);
                $(`input[name="accrual_rate"][value="${rules.accrual?.rate || 'immediate'}"]`).prop('checked', true);
                
                // Attendance details loading
                $('#accrual_attendance_earn').val(rules.accrual?.attendance_earn !== undefined ? rules.accrual.attendance_earn : 1);
                $('#accrual_attendance_period').val(rules.accrual?.attendance_period !== undefined ? rules.accrual.attendance_period : 20);
                if (rules.accrual?.rate === 'attendance') {
                    $('#accrual_attendance_div').removeClass('d-none');
                } else {
                    $('#accrual_attendance_div').addClass('d-none');
                }

                $('#accrual_limit_carry').prop('checked', !!rules.accrual?.limit_carry).trigger('change');
                $('#accrual_max_accum_val').val(rules.accrual?.max_accum || 30);

                $('#app_apply_in_advance').prop('checked', !!rules.application?.apply_in_advance).trigger('change');
                $('#app_advance_days').val(rules.application?.advance_days || 3);
                $('#app_min_duration').val(rules.application?.min_duration || 1);
                $('#app_max_duration').val(rules.application?.max_duration || 10);
                $('#app_require_attachment').prop('checked', !!rules.application?.require_attachment).trigger('change');
                $('#app_attachment_days').val(rules.application?.attachment_days || 3);

                $(`input[name="approval_workflow_level"][value="${rules.approval?.workflow_level || '1_level'}"]`).prop('checked', true).trigger('change');
                $('#approval_first_approver').val(rules.approval?.first_approver || 'reporting_manager');
                $('#approval_second_approver').val(rules.approval?.second_approver || 'hr_manager');

                $(`input[name="yearend_action"][value="${rules.yearend?.action || 'lapse'}"]`).prop('checked', true).trigger('change');
                $('#yearend_max_carry').val(rules.yearend?.max_carry || 6);
                $('#yearend_max_encash').val(rules.yearend?.max_encash || 5);

                $(`input[name="probation_rule"][value="${rules.probation?.rule || 'allow'}"]`).prop('checked', true).trigger('change');
                $('#probation_months').val(rules.probation?.months || 3);

                $(`input[name="notice_rule"][value="${rules.notice?.rule || 'allow'}"]`).prop('checked', true);

                // Show modal
                $('#leaveRulesModal').modal('show');
            });

            // Toggle logic for conditional fields
            $(document).on('change', 'input[name="accrual_rate"]', function() {
                if ($(this).val() === 'attendance') {
                    $('#accrual_attendance_div').removeClass('d-none');
                } else {
                    $('#accrual_attendance_div').addClass('d-none');
                }
            });

            $('#accrual_limit_carry').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#accrual_max_accum_div').removeClass('d-none');
                } else {
                    $('#accrual_max_accum_div').addClass('d-none');
                }
            });

            $('#app_apply_in_advance').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#app_advance_days_div').removeClass('d-none');
                } else {
                    $('#app_advance_days_div').addClass('d-none');
                }
            });

            $('#app_require_attachment').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#app_attachment_days_div').removeClass('d-none');
                } else {
                    $('#app_attachment_days_div').addClass('d-none');
                }
            });

            $(document).on('change', 'input[name="approval_workflow_level"]', function() {
                let val = $(this).val();
                if (val === 'auto') {
                    $('#approver_roles_card').addClass('d-none');
                } else if (val === '1_level') {
                    $('#approver_roles_card').removeClass('d-none');
                    $('#first_approver_row').removeClass('d-none');
                    $('#second_approver_row').addClass('d-none');
                } else {
                    $('#approver_roles_card').removeClass('d-none');
                    $('#first_approver_row').removeClass('d-none');
                    $('#second_approver_row').removeClass('d-none');
                }
            });

            $(document).on('change', 'input[name="yearend_action"]', function() {
                let val = $(this).val();
                if (val === 'lapse') {
                    $('#yearend_carry_limit_div').addClass('d-none');
                    $('#yearend_encash_limit_div').addClass('d-none');
                } else if (val === 'carry_forward') {
                    $('#yearend_carry_limit_div').removeClass('d-none');
                    $('#yearend_encash_limit_div').addClass('d-none');
                } else {
                    $('#yearend_carry_limit_div').addClass('d-none');
                    $('#yearend_encash_limit_div').removeClass('d-none');
                }
            });

            $(document).on('change', 'input[name="probation_rule"]', function() {
                let val = $(this).val();
                if (val === 'allow_after_months') {
                    $('#probation_months_div').removeClass('d-none');
                } else {
                    $('#probation_months_div').addClass('d-none');
                }
            });

            // Save configuration logic
            window.saveLeaveRules = function() {
                let typeId = $('#rules-leave-type-id').val();
                if (!typeId) return;

                let data = {
                    accrual: {
                        calculate_in: $('input[name="accrual_calculate_in"]:checked').val(),
                        quota_type: $('input[name="accrual_quota_type"]:checked').val(),
                        quota_value: parseFloat($('#accrual_quota_value').val()) || 0,
                        rate: $('input[name="accrual_rate"]:checked').val(),
                        attendance_earn: parseFloat($('#accrual_attendance_earn').val()) || 1,
                        attendance_period: parseInt($('#accrual_attendance_period').val()) || 20,
                        limit_carry: $('#accrual_limit_carry').is(':checked'),
                        max_accum: parseFloat($('#accrual_max_accum_val').val()) || 30
                    },
                    application: {
                        apply_in_advance: $('#app_apply_in_advance').is(':checked'),
                        advance_days: parseInt($('#app_advance_days').val()) || 3,
                        min_duration: parseFloat($('#app_min_duration').val()) || 1,
                        max_duration: parseFloat($('#app_max_duration').val()) || 10,
                        require_attachment: $('#app_require_attachment').is(':checked'),
                        attachment_days: parseFloat($('#app_attachment_days').val()) || 3
                    },
                    approval: {
                        workflow_level: $('input[name="approval_workflow_level"]:checked').val(),
                        first_approver: $('#approval_first_approver').val(),
                        second_approver: $('#approval_second_approver').val()
                    },
                    yearend: {
                        action: $('input[name="yearend_action"]:checked').val(),
                        max_carry: parseFloat($('#yearend_max_carry').val()) || 6,
                        max_encash: parseFloat($('#yearend_max_encash').val()) || 5
                    },
                    probation: {
                        rule: $('input[name="probation_rule"]:checked').val(),
                        months: parseFloat($('#probation_months').val()) || 3
                    },
                    notice: {
                        rule: $('input[name="notice_rule"]:checked').val()
                    }
                };

                // Send AJAX request to save in the database
                $.ajax({
                    url: `/hrms/leave-structure/type/${typeId}/rules`,
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        rules: data
                    },
                    success: function(response) {
                        // Update settings button's local data-rules attribute
                        $(`.configure-rules-btn[data-type-id="${typeId}"]`).attr('data-rules', JSON.stringify(data));
                        
                        $('#leaveRulesModal').modal('hide');

                        // Floating Toast alert
                        let toast = document.createElement('div');
                        toast.className = 'position-fixed top-0 end-0 p-3';
                        toast.style.zIndex = '9999';
                        toast.innerHTML = `
                            <div class="toast show align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                              <div class="d-flex">
                                <div class="toast-body d-flex align-items-center gap-2">
                                  <i class="feather-check-circle"></i> Rules successfully saved to the database.
                                </div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                              </div>
                            </div>
                        `;
                        document.body.appendChild(toast);
                        setTimeout(() => {
                            toast.remove();
                        }, 3000);
                    },
                    error: function(xhr) {
                        alert('Error saving rules to the database. Make sure migrations are run.');
                    }
                });
            };
        });
    </script>
    <script>
        (function () {
            if (window.hrmsThemedValidationInstalled) {
                return;
            }

            window.hrmsThemedValidationInstalled = true;

            function getFieldLabel(field) {
                const group = field.closest('.odoo-form-group');
                const label = group ? group.querySelector('.odoo-form-label') : null;
                return label ? label.textContent.replace('*', '').trim() : 'This field';
            }

            function getValidationMessage(field) {
                const label = getFieldLabel(field).toLowerCase();

                if (field.validity.valueMissing) {
                    return field.tagName === 'SELECT' ? `Please select ${label}.` : `Please enter ${label}.`;
                }

                return field.validationMessage || 'Please enter a valid value.';
            }

            function getErrorAnchor(field) {
                if (field.tagName === 'SELECT' && field.nextElementSibling && field.nextElementSibling.classList.contains('select2-container')) {
                    return field.nextElementSibling;
                }

                if (field.type === 'radio') {
                    return field.closest('.odoo-form-group')?.querySelector('.flex-grow-1') || field;
                }

                return field;
            }

            function showFieldError(field) {
                field.classList.add('is-invalid');
                field.setAttribute('aria-invalid', 'true');

                const anchor = getErrorAnchor(field);
                let error = anchor.nextElementSibling;

                if (!error || !error.classList.contains('hrms-client-validation-error')) {
                    error = document.createElement('div');
                    error.className = 'invalid-feedback d-block fs-11 mt-1 hrms-client-validation-error';
                    anchor.insertAdjacentElement('afterend', error);
                }

                error.textContent = getValidationMessage(field);
            }

            function clearFieldError(field) {
                field.classList.remove('is-invalid');
                field.removeAttribute('aria-invalid');

                const error = getErrorAnchor(field).nextElementSibling;
                if (error && error.classList.contains('hrms-client-validation-error')) {
                    error.remove();
                }
            }

            function getRequiredFields(form) {
                return Array.from(form.querySelectorAll('[required]')).filter(field => !field.disabled && field.type !== 'hidden');
            }

            function validateField(field) {
                if (field.checkValidity()) {
                    clearFieldError(field);
                    return true;
                }

                showFieldError(field);
                return false;
            }

            function focusField(field) {
                const select2 = field.tagName === 'SELECT' && field.nextElementSibling?.classList.contains('select2-container') ? field.nextElementSibling : null;
                const target = select2 || field;
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                (select2?.querySelector('.select2-selection') || field).focus({ preventScroll: true });
            }

            function bindHrmsValidation(root) {
                root.querySelectorAll('form').forEach(function (form) {
                    if (form.dataset.hrmsThemedValidation === '1' || !form.querySelector('[required]')) {
                        return;
                    }

                    form.dataset.hrmsThemedValidation = '1';
                    form.setAttribute('novalidate', 'novalidate');

                    getRequiredFields(form).forEach(function (field) {
                        field.addEventListener('input', () => validateField(field));
                        field.addEventListener('change', () => validateField(field));
                    });

                    form.addEventListener('submit', function (event) {
                        const invalidField = getRequiredFields(form).find(field => !validateField(field));

                        if (invalidField) {
                            event.preventDefault();
                            event.stopImmediatePropagation();
                            focusField(invalidField);
                        }
                    });
                });
            }

            document.addEventListener('DOMContentLoaded', () => bindHrmsValidation(document));
            document.addEventListener('shown.bs.modal', event => bindHrmsValidation(event.target));
        })();
    </script>
@endsection
