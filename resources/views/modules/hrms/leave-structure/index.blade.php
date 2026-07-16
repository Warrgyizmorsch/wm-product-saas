@extends('layouts.duralux')

@section('title', __('hrms.leave.title') . ' | SaaS ERP')
@section('page-title', __('hrms.leave.title'))
@section('breadcrumb', 'HRMS / ' . __('hrms.leave.title'))

@section('page-actions')
    <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addLeavePlanModal">
        {{ __('hrms.leave.add_plan') }}
    </x-ui.button>
@endsection

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
                min-width: 0;
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

        /* Only stretch Sort/Filter buttons inside the left sidebar panel */
        .col-md-4.border-end .erp-filter-dropdown,
        .col-md-4.border-end .erp-sort-dropdown {
            flex: 1 1 auto;
        }
        .col-md-4.border-end .erp-filter-dropdown .btn,
        .col-md-4.border-end .erp-sort-dropdown .btn {
            width: 100% !important;
            justify-content: center !important;
        }

        .theme-search-container {
            position: relative !important;
            width: 100% !important;
        }
        .theme-search-container i {
            position: absolute !important;
            left: 16px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            color: #64748b !important;
            font-size: 14px !important;
        }
        .theme-search-input {
            background-color: #f1f5f9 !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 8px !important;
            padding: 8px 16px 8px 40px !important;
            font-size: 13px !important;
            height: 40px !important;
            width: 100% !important;
            outline: none !important;
            transition: all 0.2s ease-in-out !important;
        }
        .theme-search-input:focus {
            background-color: #fff !important;
            border-color: var(--bs-primary) !important;
            box-shadow: 0 0 0 2px rgba(var(--bs-primary-rgb), 0.1) !important;
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

        /* Leave rules configuration inline input fields styling */
        #leaveRulesModal .odoo-table-input {
            border-bottom: 1px solid #ced4da !important;
            padding: 2px 4px !important;
            display: inline-block !important;
            height: auto !important;
            font-weight: bold;
            color: #212529;
        }
        #leaveRulesModal .odoo-table-input:focus {
            border-bottom: 1px solid var(--bs-primary) !important;
            background-color: transparent !important;
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

            <div class="col-12">
                <x-ui.card title="{{ __('hrms.leave.leave_plans') }}" bodyClass="p-0" stretch>
                    <x-slot name="headerAction">
                        <div class="d-flex align-items-center gap-2">
                            <!-- Search Input -->
                            <div class="theme-search-container" style="width: 240px !important; position: relative;">
                                <i class="feather-search"></i>
                                <input type="text" id="leavePlanSearch" class="theme-search-input" placeholder="{{ __('hrms.leave.search_plans') }}">
                            </div>

                            <!-- Sort Dropdown -->
                            <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                                <a class="dropdown-item py-2 active" href="#" data-sort="name_asc" onclick="sortLeavePlans('name_asc', this); event.preventDefault();">{{ __('hrms.common.sort_name_asc') }}</a>
                                <a class="dropdown-item py-2" href="#" data-sort="name_desc" onclick="sortLeavePlans('name_desc', this); event.preventDefault();">{{ __('hrms.common.sort_name_desc') }}</a>
                                <a class="dropdown-item py-2" href="#" data-sort="newest" onclick="sortLeavePlans('newest', this); event.preventDefault();">{{ __('hrms.salary.newest_first') }}</a>
                            </x-ui.sort-dropdown>

                            <!-- Filter Dropdown -->
                            <x-ui.filter label="{{ __('hrms.common.filter') }}">
                                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.org.status') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="lp_status" id="lp_filter_status">
                                        <option value="">{{ __('hrms.common.all_statuses') }}</option>
                                        <option value="1">{{ __('hrms.employees.frm_status_active') }}</option>
                                        <option value="0">{{ __('hrms.employees.frm_status_inactive') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.leave.legal_entity') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="lp_company" id="lp_filter_company">
                                        <option value="">{{ __('hrms.salary.all_companies') }}</option>
                                        @foreach($companies as $company)
                                            <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="dropdown-divider my-3"></div>

                                <div class="d-flex gap-2">
                                    <x-ui.button type="button" variant="primary" size="sm" class="flex-grow-1" onclick="filterLeavePlans()">{{ __('hrms.common.apply') }}</x-ui.button>
                                    <x-ui.button type="button" variant="light" size="sm" class="border flex-grow-1" onclick="resetLeavePlanFilters()">{{ __('hrms.common.reset') }}</x-ui.button>
                                </div>
                            </x-ui.filter>
                        </div>
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
                                       data-plan-id="{{ $plan->id }}"
                                       data-name="{{ strtolower($plan->name) }}"
                                       data-status="{{ $plan->status ? 'active' : 'inactive' }}"
                                       data-company-id="{{ $plan->company_id }}"
                                       data-created-at="{{ $plan->created_at ? $plan->created_at->timestamp : 0 }}">
                                        <span class="fw-bold {{ $isActive ? 'text-primary' : 'text-dark' }}" style="font-size: 14px;">
                                            {{ $plan->name }}
                                        </span>
                                    </a>
                                @empty
                                    <div class="text-center py-5 text-muted px-3">
                                        <i class="feather-calendar fs-24 mb-2 d-block text-secondary"></i>
                                        <span>{{ __('hrms.leave.no_plans') }}</span>
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <!-- RIGHT COLUMN: SELECTED PLAN DETAILS & LEAVE TYPES TABLE -->
                        <div class="col-md-8 col-12" id="activePlanDetailsContainer">
                            @if($selectedPlan)
                                <input type="hidden" id="lt_sort_value" value="{{ $ltSort }}">
                                <input type="hidden" id="lt_type_value" value="{{ $ltType }}">
                                <div class="plan-details-pane" id="plan-details-{{ $selectedPlan->id }}">
                                    <div class="p-4">
                                        <!-- Selected Plan Details and Actions -->
                                        <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                                            <div>
                                                <h5 class="fw-bold text-dark mb-1" style="font-size: 16px;">{{ $selectedPlan->name }}</h5>
                                                <div class="text-muted d-flex align-items-center gap-2" style="font-size: 12px;">
                                                    <span><i class="feather-briefcase me-1"></i>{{ $selectedPlan->company ? $selectedPlan->company->company_name : __('hrms.salary.all_companies') }}</span>
                                                    <span>&bull;</span>
                                                    <span><i class="feather-calendar me-1"></i>{{ __('hrms.leave.effective_from') }}: <strong>{{ $selectedPlan->effective_from ? $selectedPlan->effective_from->format('d M, Y') : '-' }}</strong></span>
                                                    <span>&bull;</span>
                                                    <span>
                                                        @if($selectedPlan->status)
                                                            <span class="text-success"><i class="feather-check-circle me-1"></i>{{ __('hrms.employees.frm_status_active') }}</span>
                                                        @else
                                                            <span class="text-danger"><i class="feather-slash me-1"></i>{{ __('hrms.employees.frm_status_inactive') }}</span>
                                                        @endif
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <!-- Actions Dropdown for Leave Plan -->
                                            <form action="{{ route('hrms.leave-structure.plan.destroy', $selectedPlan->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('hrms.leave.delete_plan_confirm') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <x-ui.action-dropdown>
                                                    <li>
                                                        <a class="dropdown-item edit-plan-btn" href="javascript:void(0)" data-plan="{{ base64_encode($selectedPlan->toJson()) }}">
                                                            <i class="feather feather-edit-3 me-3"></i>
                                                            <span>{{ __('hrms.leave.edit_plan') }}</span>
                                                        </a>
                                                    </li>
                                                    <li class="dropdown-divider"></li>
                                                    <li>
                                                        <button type="submit" class="dropdown-item text-danger border-0 bg-transparent w-100 text-start d-flex align-items-center">
                                                            <i class="feather feather-trash-2 me-3"></i>
                                                            <span>{{ __('hrms.leave.delete_plan') }}</span>
                                                        </button>
                                                    </li>
                                                </x-ui.action-dropdown>
                                            </form>
                                        </div>

                                        @if($selectedPlan->description)
                                            <div class="p-3 bg-light rounded mb-4" style="font-size: 13px; color: #475569;">
                                                <i class="feather-info me-2 text-primary"></i>{{ $selectedPlan->description }}
                                            </div>
                                        @endif

                                        <!-- Sub-header for Leave Types list -->
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="fw-bold text-dark mb-0" style="font-size: 14px;">{{ __('hrms.leave.leave_types') }}</h6>
                                            <x-ui.button variant="primary" size="sm" icon="feather-plus" class="add-type-trigger-btn" data-plan-id="{{ $selectedPlan->id }}" data-bs-toggle="modal" data-bs-target="#addLeaveTypeModal">
                                                {{ __('hrms.leave.add_leave_type') }}
                                            </x-ui.button>
                                        </div>

                                        <!-- Table (Columns: Leave Type, Quota, Action) -->
                                        <div class="px-4 py-3 border-bottom bg-white d-flex align-items-center gap-2 mb-3 rounded border" style="position: relative; z-index: 10;">
                                            <!-- Search Input -->
                                            <div class="theme-search-container flex-grow-1">
                                                <i class="feather-search"></i>
                                                <input type="text" class="theme-search-input leave-type-search-input" data-plan-id="{{ $selectedPlan->id }}" placeholder="{{ __('hrms.leave.search_types') }}" value="{{ $ltSearch }}">
                                            </div>

                                            <!-- Sort Dropdown -->
                                            <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}" style="flex-shrink: 0;">
                                                <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $ltSort === 'name_asc' ? 'active' : '' }}" href="#" data-sort="name_asc" onclick="changeLeaveTypeSort('{{ $selectedPlan->id }}', 'name_asc', this); event.preventDefault();">
                                                    <span>{{ __('hrms.common.sort_name_asc') }}</span>
                                                </a>
                                                <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $ltSort === 'name_desc' ? 'active' : '' }}" href="#" data-sort="name_desc" onclick="changeLeaveTypeSort('{{ $selectedPlan->id }}', 'name_desc', this); event.preventDefault();">
                                                    <span>{{ __('hrms.common.sort_name_desc') }}</span>
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $ltSort === 'quota_asc' ? 'active' : '' }}" href="#" data-sort="quota_asc" onclick="changeLeaveTypeSort('{{ $selectedPlan->id }}', 'quota_asc', this); event.preventDefault();">
                                                    <span>{{ __('hrms.leave.quota_low_high') }}</span>
                                                </a>
                                                <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $ltSort === 'quota_desc' ? 'active' : '' }}" href="#" data-sort="quota_desc" onclick="changeLeaveTypeSort('{{ $selectedPlan->id }}', 'quota_desc', this); event.preventDefault();">
                                                    <span>{{ __('hrms.leave.quota_high_low') }}</span>
                                                </a>
                                            </x-ui.sort-dropdown>

                                            <!-- Filter Dropdown -->
                                            <x-ui.filter label="{{ __('hrms.common.filter') }}" style="flex-shrink: 0;">
                                                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                                                <div class="mb-3 leave-type-filter-select-wrapper" data-plan-id="{{ $selectedPlan->id }}">
                                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.org.type') }}</label>
                                                    <x-ui.odoo-form-ui type="select" name="lt_filter_type" id="lt_filter_type">
                                                        <option value="">{{ __('hrms.common.all_statuses') }}</option>
                                                        <option value="paid" @selected($ltType === 'paid')>{{ __('hrms.leave.paid') }}</option>
                                                        <option value="unpaid" @selected($ltType === 'unpaid')>{{ __('hrms.leave.unpaid') }}</option>
                                                    </x-ui.odoo-form-ui>
                                                </div>

                                                <div class="dropdown-divider my-3"></div>

                                                <div class="d-flex gap-2">
                                                    <x-ui.button type="button" variant="primary" size="sm" class="flex-grow-1" onclick="applyLeaveTypeFilter('{{ $selectedPlan->id }}')">{{ __('hrms.common.apply') }}</x-ui.button>
                                                    <x-ui.button type="button" variant="light" size="sm" class="border flex-grow-1" onclick="resetLeaveTypeFilters('{{ $selectedPlan->id }}')">{{ __('hrms.common.reset') }}</x-ui.button>
                                                </div>
                                            </x-ui.filter>
                                        </div>

                                        <div class="border rounded bg-white">
                                            <table class="table table-hover mb-0 align-middle" style="font-size: 13px;" id="leaveTypesTable">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>{{ __('hrms.leave.leave_types') }}</th>
                                                        <th>{{ __('hrms.leave.yearly_quota') }}</th>
                                                        <th width="120" class="text-end">{{ __('hrms.org.tbl_actions') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse($leaveTypes as $type)
                                                        <tr class="leave-type-row-{{ $selectedPlan->id }}"
                                                            data-name="{{ strtolower($type->name) }}"
                                                            data-type="{{ strtolower($type->type) }}"
                                                            data-quota="{{ floatval($type->quota) }}">
                                                            <td>
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <span class="badge text-uppercase px-2 py-1 fw-bold" style="background-color: {{ $type->color }}22; color: {{ $type->color }}; border: 1px solid {{ $type->color }}; font-size: 10px;">
                                                                        {{ $type->code }}
                                                                    </span>
                                                                    <span class="fw-semibold text-dark type-name">{{ $type->name }}</span>
                                                                    @if($type->type === 'unpaid')
                                                                        <span class="text-muted fs-11">({{ __('hrms.leave.unpaid') }})</span>
                                                                    @endif
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <span class="fw-bold text-dark">{{ floatval($type->quota) }} {{ __('hrms.leave.days') }}</span>
                                                            </td>
                                                            <td class="text-end">
                                                                <form action="{{ route('hrms.leave-structure.type.destroy', $type->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('hrms.leave.delete_type_confirm') }}');">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <div class="hstack gap-2 justify-content-end">
                                                                        <a href="javascript:void(0)" class="action-dropdown-btn configure-rules-btn" data-type-id="{{ $type->id }}" data-type-name="{{ $type->name }}" data-rules="{{ json_encode($type->rules) }}" title="{{ __('hrms.leave.configure_rules') }}" data-bs-toggle="tooltip">
                                                                            <i class="feather feather-settings"></i>
                                                                        </a>
                                                                        <x-ui.action-dropdown>
                                                                            <li>
                                                                                <a class="dropdown-item edit-type-btn" href="javascript:void(0)" data-type="{{ base64_encode($type->toJson()) }}">
                                                                                    <i class="feather feather-edit-3 me-3"></i>
                                                                                    <span>{{ __('hrms.leave.edit_type') }}</span>
                                                                                </a>
                                                                            </li>
                                                                            <li class="dropdown-divider"></li>
                                                                            <li>
                                                                                <button type="submit" class="dropdown-item text-danger border-0 bg-transparent w-100 text-start d-flex align-items-center">
                                                                                    <i class="feather feather-trash-2 me-3"></i>
                                                                                    <span>{{ __('hrms.leave.delete_type') }}</span>
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
                                                                {{ __('hrms.leave.no_types_for_plan') }}
                                                            </td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                        @php
                                            $currentPage = $leaveTypes->currentPage();
                                            $totalPages = $leaveTypes->lastPage();
                                            $totalResults = $leaveTypes->total();
                                            $perPage = $leaveTypes->perPage();
                                        @endphp
                                        @if($leaveTypes->hasPages())
                                            <div class="card-footer bg-white border-top px-4 py-3 leave-type-pagination-container">
                                                <x-ui.pagination
                                                    class="px-0 py-0"
                                                    :current-page="$currentPage"
                                                    :total-pages="$totalPages"
                                                    :total-results="$totalResults"
                                                    :per-page="$perPage"
                                                    page-param="lt_page"
                                                />
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <!-- Empty state when no plan selected/exists -->
                                <div class="text-center py-5 text-muted my-5">
                                    <i class="feather-calendar text-secondary mb-3 d-block" style="font-size: 48px;"></i>
                                    <h5 class="fw-bold text-dark">{{ __('hrms.leave.no_plan_selected') }}</h5>
                                    <p class="text-muted">{{ __('hrms.leave.select_plan_desc') }}</p>
                                </div>
                            @endif
                        </div>
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
                    <h5 class="modal-title fw-bold" id="addLeavePlanModalLabel">{{ __('hrms.leave.create_plan') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.leave-structure.plan.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.leave.plan_name') }}" name="name" placeholder="{{ __('hrms.leave.plan_name_placeholder') }}" :required="true" :errorText="$errors->first('name')" />
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.leave.legal_entity') }}" name="company_id" select2-selector="default" :errorText="$errors->first('company_id')">
                                    <option value="">{{ __('hrms.org.apply_to_all_companies') }}</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.leave.effective_from') }}" name="effective_from" inputType="date" :required="true" :errorText="$errors->first('effective_from')" />
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="status" select2-selector="default" :required="true" :errorText="$errors->first('status')">
                                    <option value="1">{{ __('hrms.employees.frm_status_active') }}</option>
                                    <option value="0">{{ __('hrms.employees.frm_status_inactive') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-12 col-12">
                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.leave.description') }}" name="description" placeholder="{{ __('hrms.leave.desc_placeholder') }}" rows="3" :errorText="$errors->first('description')" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <x-ui.button variant="light" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</x-ui.button>
                        <x-ui.button type="submit" variant="primary">{{ __('hrms.leave.create_plan') }}</x-ui.button>
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
                    <h5 class="modal-title fw-bold" id="editLeavePlanModalLabel">{{ __('hrms.leave.edit_plan') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editLeavePlanForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.leave.plan_name') }}" name="name" id="edit_plan_name" :required="true" :errorText="$errors->first('name')" />
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.leave.legal_entity') }}" name="company_id" id="edit_plan_company_id" select2-selector="default" :errorText="$errors->first('company_id')">
                                    <option value="">{{ __('hrms.org.apply_to_all_companies') }}</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.leave.effective_from') }}" name="effective_from" id="edit_plan_effective_from" inputType="date" :required="true" :errorText="$errors->first('effective_from')" />
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="status" id="edit_plan_status" select2-selector="default" :required="true" :errorText="$errors->first('status')">
                                    <option value="1">{{ __('hrms.employees.frm_status_active') }}</option>
                                    <option value="0">{{ __('hrms.employees.frm_status_inactive') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-12 col-12">
                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.leave.description') }}" name="description" id="edit_plan_description" rows="3" :errorText="$errors->first('description')" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <x-ui.button variant="light" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</x-ui.button>
                        <x-ui.button type="submit" variant="primary">{{ __('hrms.common.save_changes') }}</x-ui.button>
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
                    <h5 class="modal-title fw-bold" id="addLeaveTypeModalLabel">{{ __('hrms.leave.add_type_title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.leave-structure.type.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <input type="hidden" name="leave_plan_id" id="add_type_plan_id" value="{{ $selectedPlan ? $selectedPlan->id : '' }}">
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.leave.type_name') }}" name="name" placeholder="{{ __('hrms.leave.type_name_placeholder') }}" :required="true" :errorText="$errors->first('name')" />
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.leave.color_theme') }}" name="color" inputType="color" value="#3b82f6" class="form-control-color" style="width: 50px;" :required="true" helperText="{{ __('hrms.leave.click_select_color') }}" :errorText="$errors->first('color')" />
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.leave.code') }}" name="code" placeholder="{{ __('hrms.leave.code_placeholder') }}" :required="true" :errorText="$errors->first('code')" />
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.leave.annual_quota') }}" name="quota" inputType="number" step="0.5" placeholder="{{ __('hrms.leave.quota_placeholder') }}" min="0" :required="true" :errorText="$errors->first('quota')" />
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="radio" label="{{ __('hrms.leave.classification') }}" :required="true" :errorText="$errors->first('type')">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="type" id="add_type_paid" value="paid" checked required>
                                        <label class="form-check-label fw-semibold text-dark" for="add_type_paid">
                                            {{ __('hrms.leave.paid_leave') }}
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="type" id="add_type_unpaid" value="unpaid" required>
                                        <label class="form-check-label fw-semibold text-dark" for="add_type_unpaid">
                                            {{ __('hrms.leave.unpaid_leave') }}
                                        </label>
                                    </div>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="status" select2-selector="default" :required="true" :errorText="$errors->first('status')">
                                    <option value="1">{{ __('hrms.employees.frm_status_active') }}</option>
                                    <option value="0">{{ __('hrms.employees.frm_status_inactive') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-12 col-12">
                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.leave.description') }}" name="description" placeholder="{{ __('hrms.leave.desc_type_placeholder') }}" rows="3" :errorText="$errors->first('description')" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <x-ui.button variant="light" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</x-ui.button>
                        <x-ui.button type="submit" variant="primary">{{ __('hrms.leave.add_leave_type') }}</x-ui.button>
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
                    <h5 class="modal-title fw-bold" id="editLeaveTypeModalLabel">{{ __('hrms.leave.edit_type_title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editLeaveTypeForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row g-3">
                            <input type="hidden" name="leave_plan_id" id="edit_type_plan_id">
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.leave.type_name') }}" name="name" id="edit_type_name" :required="true" :errorText="$errors->first('name')" />
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.leave.color_theme') }}" name="color" id="edit_type_color" inputType="color" class="form-control-color" style="width: 50px;" :required="true" helperText="{{ __('hrms.leave.click_select_color') }}" :errorText="$errors->first('color')" />
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.leave.code') }}" name="code" id="edit_type_code" :required="true" :errorText="$errors->first('code')" />
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="input" label="{{ __('hrms.leave.annual_quota') }}" name="quota" id="edit_type_quota" inputType="number" step="0.5" min="0" :required="true" :errorText="$errors->first('quota')" />
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="radio" label="{{ __('hrms.leave.classification') }}" :required="true" :errorText="$errors->first('type')">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="type" id="edit_type_paid" value="paid" required>
                                        <label class="form-check-label fw-semibold text-dark" for="edit_type_paid">
                                            {{ __('hrms.leave.paid_leave') }}
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="type" id="edit_type_unpaid" value="unpaid" required>
                                        <label class="form-check-label fw-semibold text-dark" for="edit_type_unpaid">
                                            {{ __('hrms.leave.unpaid_leave') }}
                                        </label>
                                    </div>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6 col-12">
                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.org.status') }}" name="status" id="edit_type_status" select2-selector="default" :required="true" :errorText="$errors->first('status')">
                                    <option value="1">{{ __('hrms.employees.frm_status_active') }}</option>
                                    <option value="0">{{ __('hrms.employees.frm_status_inactive') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-12 col-12">
                                <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.leave.description') }}" name="description" id="edit_type_description" rows="3" :errorText="$errors->first('description')" />
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <x-ui.button variant="light" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</x-ui.button>
                        <x-ui.button type="submit" variant="primary">{{ __('hrms.common.save_changes') }}</x-ui.button>
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
                        <h5 class="modal-title fw-bold text-dark" id="leaveRulesModalLabel">{{ __('hrms.leave.rules_config_title') }}</h5>
                        <p class="text-muted mb-0 fs-12">{{ __('hrms.leave.rules_config_subtitle') }} <strong class="text-primary" id="rules-leave-type-name">Casual Leave</strong></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-0">
                    <div class="row g-0">
                        <!-- Left Navigation Column (col-3) -->
                        <div class="col-md-3 border-end bg-light-subtle" style="min-height: 480px;">
                            <div class="nav flex-column nav-pills p-3 gap-2" id="rulesTabList" role="tablist">
                                <button class="nav-link text-start active py-2.5 px-3 d-flex align-items-center gap-2" id="tab-accrual" data-bs-toggle="pill" data-bs-target="#pane-accrual" type="button" role="tab"><i class="feather-calendar"></i> {{ __('hrms.leave.accrual') }}</button>
                                <button class="nav-link text-start py-2.5 px-3 d-flex align-items-center gap-2" id="tab-application" data-bs-toggle="pill" data-bs-target="#pane-application" type="button" role="tab"><i class="feather-file-text"></i> {{ __('hrms.leave.application') }}</button>
                                <button class="nav-link text-start py-2.5 px-3 d-flex align-items-center gap-2" id="tab-approval" data-bs-toggle="pill" data-bs-target="#pane-approval" type="button" role="tab"><i class="feather-check-square"></i> {{ __('hrms.leave.approval') }}</button>
                                <button class="nav-link text-start py-2.5 px-3 d-flex align-items-center gap-2" id="tab-yearend" data-bs-toggle="pill" data-bs-target="#pane-yearend" type="button" role="tab"><i class="feather-refresh-cw"></i> {{ __('hrms.leave.yearend') }}</button>
                                <button class="nav-link text-start py-2.5 px-3 d-flex align-items-center gap-2" id="tab-probation" data-bs-toggle="pill" data-bs-target="#pane-probation" type="button" role="tab"><i class="feather-shield"></i> {{ __('hrms.leave.probation') }}</button>
                                <button class="nav-link text-start py-2.5 px-3 d-flex align-items-center gap-2" id="tab-notice" data-bs-toggle="pill" data-bs-target="#pane-notice" type="button" role="tab"><i class="feather-alert-triangle"></i> {{ __('hrms.leave.notice') }}</button>
                            </div>
                        </div>
                        
                        <!-- Right Configurations Pane Column (col-9) -->
                        <div class="col-md-9 p-4" style="max-height: 520px; overflow-y: auto;">
                            <input type="hidden" id="rules-leave-type-id">
                            
                            <div class="tab-content" id="rulesTabContent">
                                <!-- Accrual Tab Pane -->
                                <div class="tab-pane fade show active" id="pane-accrual" role="tabpanel">
                                    <h5 class="fw-bold text-dark mb-3">{{ __('hrms.leave.accrual') }}</h5>
                                    
                                    <!-- Yearly Quota -->
                                    <div class="card border mb-3 bg-light-subtle rounded-3 shadow-none">
                                        <div class="card-header bg-white py-3 px-3 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapseQuota" aria-expanded="true">
                                            <h6 class="fw-bold text-dark mb-0 fs-14"><i class="feather-calendar me-2 text-muted"></i>{{ __('hrms.leave.yearly_quota') }}</h6>
                                        </div>
                                        <div id="collapseQuota" class="collapse show">
                                            <div class="card-body bg-white border-top p-3 fs-13">
                                                <div class="row align-items-center mb-3">
                                                    <div class="col-sm-4 text-muted">{{ __('hrms.leave.quota_calculated_in') }}</div>
                                                    <div class="col-sm-8 d-flex gap-3">
                                                        <label class="form-check-label d-flex align-items-center gap-2 cursor-pointer">
                                                            <input type="radio" name="accrual_calculate_in" value="days" class="form-check-input me-2" checked> {{ __('hrms.leave.days') }}
                                                        </label>
                                                        <label class="form-check-label d-flex align-items-center gap-2 cursor-pointer">
                                                            <input type="radio" name="accrual_calculate_in" value="hours" class="form-check-input me-2"> {{ __('hrms.leave.hours') }}
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="row align-items-center">
                                                    <div class="col-sm-4 text-muted">{{ __('hrms.leave.yearly_quota') }}:</div>
                                                    <div class="col-sm-8 d-flex align-items-center gap-2">
                                                        <label class="form-check-label d-flex align-items-center gap-2 cursor-pointer me-3">
                                                            <input type="radio" name="accrual_quota_type" value="fixed" class="form-check-input me-2" checked> 
                                                            <input type="number" id="accrual_quota_value" class="odoo-table-input text-center d-inline-block mx-1" style="width: 70px;" value="12"> {{ __('hrms.leave.days') }}
                                                        </label>
                                                        <label class="form-check-label d-flex align-items-center gap-2 cursor-pointer">
                                                            <input type="radio" name="accrual_quota_type" value="unlimited" class="form-check-input me-2"> {{ __('hrms.leave.unlimited') }}
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Allocation & Accrual Rate -->
                                    <div class="card border mb-3 bg-light-subtle rounded-3 shadow-none">
                                        <div class="card-header bg-white py-3 px-3 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapseRate" aria-expanded="true">
                                            <h6 class="fw-bold text-dark mb-0 fs-14"><i class="feather-trending-up me-2 text-muted"></i>{{ __('hrms.leave.allocation_accrual_rate') }}</h6>
                                        </div>
                                        <div id="collapseRate" class="collapse show">
                                            <div class="card-body bg-white border-top p-3 fs-13 d-flex flex-column gap-3">
                                                <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                    <input type="radio" name="accrual_rate" value="periodic" class="form-check-input mt-1">
                                                    <div>
                                                        <span class="fw-semibold text-dark d-block">{{ __('hrms.leave.accrued_periodically') }}</span>
                                                        <span class="text-muted fs-11">{{ __('hrms.leave.accrued_periodically_desc') }}</span>
                                                     </div>
                                                 </label>
                                                 <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                     <input type="radio" name="accrual_rate" value="attendance" class="form-check-input mt-1">
                                                     <div>
                                                         <span class="fw-semibold text-dark d-block">{{ __('hrms.leave.accrued_attendance') }}</span>
                                                         <span class="text-muted fs-11">{{ __('hrms.leave.accrued_attendance_desc') }}</span>
                                                     </div>
                                                 </label>
                                                 <div class="ps-4 mt-1 mb-2 d-none" id="accrual_attendance_div">
                                                     <div class="d-flex align-items-center gap-2 fs-13">
                                                         <span>{{ __('hrms.leave.earn') }}</span>
                                                         <input type="number" id="accrual_attendance_earn" class="odoo-table-input text-center" style="width: 70px;" value="1">
                                                         <span>{{ __('hrms.leave.days_of_leave_for_every') }}</span>
                                                         <input type="number" id="accrual_attendance_period" class="odoo-table-input text-center" style="width: 70px;" value="20">
                                                         <span>{{ __('hrms.leave.days_worked') }}</span>
                                                     </div>
                                                 </div>
                                                 <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                     <input type="radio" name="accrual_rate" value="immediate" class="form-check-input mt-1" checked>
                                                     <div>
                                                         <span class="fw-semibold text-dark d-block">{{ __('hrms.leave.immediate_quota') }}</span>
                                                         <span class="text-muted fs-11">{{ __('hrms.leave.immediate_quota_desc') }}</span>
                                                     </div>
                                                 </label>
                                             </div>
                                         </div>
                                     </div>
                                     
                                     <!-- Accrual Restrictions -->
                                     <div class="card border bg-light-subtle rounded-3 shadow-none">
                                         <div class="card-header bg-white py-3 px-3 d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#collapseRestrictions" aria-expanded="true">
                                             <h6 class="fw-bold text-dark mb-0 fs-14"><i class="feather-alert-circle me-2 text-muted"></i>{{ __('hrms.leave.accrual_restrictions') }}</h6>
                                         </div>
                                         <div id="collapseRestrictions" class="collapse show">
                                             <div class="card-body bg-white border-top p-3 fs-13">
                                                 <div class="form-check form-switch mb-1">
                                                     <input class="form-check-input" type="checkbox" id="accrual_limit_carry">
                                                     <label class="form-check-label fw-bold text-dark ms-2" for="accrual_limit_carry">{{ __('hrms.leave.limit_max_accumulation') }}</label>
                                                     <div class="text-muted fs-11 ms-2">{{ __('hrms.leave.limit_max_accumulation_desc') }}</div>
                                                 </div>
                                                 <div class="ps-4 mt-2 d-none" id="accrual_max_accum_div">
                                                     <div class="d-flex align-items-center gap-2">
                                                         <span>{{ __('hrms.leave.max_accum_balance') }}</span>
                                                         <input type="number" id="accrual_max_accum_val" class="odoo-table-input text-center" style="width: 70px;" value="30"> {{ __('hrms.leave.days') }}
                                                     </div>
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                 </div>
                                 
                                 <!-- Leave Application Tab Pane -->
                                 <div class="tab-pane fade" id="pane-application" role="tabpanel">
                                     <h5 class="fw-bold text-dark mb-3">{{ __('hrms.leave.app_settings_title') }}</h5>
                                     
                                     <!-- Advance application -->
                                     <div class="card border mb-3 rounded-3 shadow-none">
                                         <div class="card-body p-3 fs-13">
                                             <div class="form-check form-switch mb-1">
                                                 <input class="form-check-input" type="checkbox" id="app_apply_in_advance">
                                                 <label class="form-check-label fw-bold text-dark ms-2" for="app_apply_in_advance">{{ __('hrms.leave.apply_in_advance') }}</label>
                                                 <div class="text-muted fs-11 ms-2">{{ __('hrms.leave.apply_in_advance_desc') }}</div>
                                             </div>
                                             <div class="ps-4 mt-2 d-none" id="app_advance_days_div">
                                                 <div class="d-flex align-items-center gap-2">
                                                     <span>{{ __('hrms.leave.apply_at_least') }}</span>
                                                     <input type="number" id="app_advance_days" class="odoo-table-input text-center" style="width: 70px;" value="3"> {{ __('hrms.leave.days_before_start') }}
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                     
                                     <!-- Duration bounds -->
                                     <div class="card border mb-3 rounded-3 shadow-none">
                                         <div class="card-body p-3 fs-13">
                                             <h6 class="fw-bold text-dark mb-3">{{ __('hrms.leave.duration_constraints') }}</h6>
                                             <div class="row align-items-center mb-3">
                                                 <div class="col-sm-5 text-muted">{{ __('hrms.leave.min_duration') }}</div>
                                                 <div class="col-sm-7 d-flex align-items-center gap-2">
                                                     <input type="number" id="app_min_duration" class="odoo-table-input text-center" style="width: 70px;" value="1"> {{ __('hrms.leave.days') }}
                                                 </div>
                                             </div>
                                             <div class="row align-items-center">
                                                 <div class="col-sm-5 text-muted">{{ __('hrms.leave.max_duration') }}</div>
                                                 <div class="col-sm-7 d-flex align-items-center gap-2">
                                                     <input type="number" id="app_max_duration" class="odoo-table-input text-center" style="width: 70px;" value="10"> {{ __('hrms.leave.days') }}
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                     
                                     <!-- Attachments -->
                                     <div class="card border rounded-3 shadow-none">
                                         <div class="card-body p-3 fs-13">
                                             <div class="form-check form-switch mb-1">
                                                 <input class="form-check-input" type="checkbox" id="app_require_attachment">
                                                 <label class="form-check-label fw-bold text-dark" for="app_require_attachment">{{ __('hrms.leave.require_attachment') }}</label>
                                                 <div class="text-muted fs-11">{{ __('hrms.leave.require_attachment_desc') }}</div>
                                             </div>
                                             <div class="ps-4 mt-2 d-none" id="app_attachment_days_div">
                                                 <div class="d-flex align-items-center gap-2">
                                                     <span>{{ __('hrms.leave.mandatory_if_exceeds') }}</span>
                                                     <input type="number" id="app_attachment_days" class="odoo-table-input text-center" style="width: 70px;" value="3"> {{ __('hrms.leave.days') }}.
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                 </div>
                                 
                                  <!-- Approval Tab Pane -->
                                  <div class="tab-pane fade" id="pane-approval" role="tabpanel">
                                      <h5 class="fw-bold text-dark mb-3">{{ __('hrms.leave.approval_workflow') }}</h5>
                                      
                                      <!-- Approval Level -->
                                      <div class="card border mb-3 rounded-3 shadow-none">
                                          <div class="card-body p-3 fs-13">
                                              <h6 class="fw-bold text-dark mb-3">{{ __('hrms.leave.approval_routing_level') }}</h6>
                                              <div class="d-flex flex-column gap-3">
                                                  <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                      <input type="radio" name="approval_workflow_level" value="auto" class="form-check-input mt-1">
                                                      <div>
                                                          <span class="fw-semibold text-dark d-block">{{ __('hrms.leave.auto_approved') }}</span>
                                                          <span class="text-muted fs-11">{{ __('hrms.leave.auto_approved_desc') }}</span>
                                                      </div>
                                                  </label>
                                                  <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                      <input type="radio" name="approval_workflow_level" value="1_level" class="form-check-input mt-1" checked>
                                                      <div>
                                                          <span class="fw-semibold text-dark d-block">{{ __('hrms.leave.one_level_approval') }}</span>
                                                          <span class="text-muted fs-11">{{ __('hrms.leave.one_level_approval_desc') }}</span>
                                                      </div>
                                                  </label>
                                                  <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                      <input type="radio" name="approval_workflow_level" value="2_level" class="form-check-input mt-1">
                                                      <div>
                                                          <span class="fw-semibold text-dark d-block">{{ __('hrms.leave.two_level_approval') }}</span>
                                                          <span class="text-muted fs-11">{{ __('hrms.leave.two_level_approval_desc') }}</span>
                                                      </div>
                                                  </label>
                                              </div>
                                          </div>
                                      </div>
                                      
                                      <!-- Approver roles definition -->
                                      <div class="card border rounded-3 shadow-none" id="approver_roles_card">
                                          <div class="card-body p-3 fs-13">
                                              <h6 class="fw-bold text-dark mb-3">{{ __('hrms.leave.workflow_roles') }}</h6>
                                              <div class="row align-items-center mb-3" id="first_approver_row">
                                                  <div class="col-sm-4 text-muted">{{ __('hrms.leave.first_approver') }}</div>
                                                  <div class="col-sm-8">
                                                      <select id="approval_first_approver" class="odoo-table-select" style="max-width: 250px;">
                                                          <option value="reporting_manager">{{ __('hrms.leave.reporting_manager') }}</option>
                                                          <option value="department_head">{{ __('hrms.leave.department_head') }}</option>
                                                          <option value="hr_manager">{{ __('hrms.leave.hr_manager') }}</option>
                                                      </select>
                                                  </div>
                                              </div>
                                              <div class="row align-items-center d-none" id="second_approver_row">
                                                  <div class="col-sm-4 text-muted">{{ __('hrms.leave.second_approver') }}</div>
                                                  <div class="col-sm-8">
                                                      <select id="approval_second_approver" class="odoo-table-select" style="max-width: 250px;">
                                                          <option value="hr_manager" selected>{{ __('hrms.leave.hr_manager') }}</option>
                                                          <option value="department_head">{{ __('hrms.leave.department_head') }}</option>
                                                          <option value="ceo">{{ __('hrms.leave.ceo') }}</option>
                                                      </select>
                                                  </div>
                                              </div>
                                          </div>
                                      </div>
                                  </div>
                                  
                                  <!-- Year End Processing Tab Pane -->
                                  <div class="tab-pane fade" id="pane-yearend" role="tabpanel">
                                      <h5 class="fw-bold text-dark mb-3">{{ __('hrms.leave.yearend') }}</h5>
                                      
                                      <div class="card border rounded-3 shadow-none">
                                          <div class="card-body p-3 fs-13">
                                              <h6 class="fw-bold text-dark mb-3">{{ __('hrms.leave.action_unused_balance') }}</h6>
                                              <div class="d-flex flex-column gap-3">
                                                  <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                      <input type="radio" name="yearend_action" value="lapse" class="form-check-input mt-1" checked>
                                                      <div>
                                                          <span class="fw-semibold text-dark d-block">{{ __('hrms.leave.lapse') }}</span>
                                                          <span class="text-muted fs-11">{{ __('hrms.leave.lapse_desc') }}</span>
                                                      </div>
                                                  </label>
                                                  <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                      <input type="radio" name="yearend_action" value="carry_forward" class="form-check-input mt-1">
                                                      <div>
                                                          <span class="fw-semibold text-dark d-block">{{ __('hrms.leave.carry_forward') }}</span>
                                                          <span class="text-muted fs-11">{{ __('hrms.leave.carry_forward_desc') }}</span>
                                                      </div>
                                                  </label>
                                                  <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                      <input type="radio" name="yearend_action" value="encash" class="form-check-input mt-1">
                                                      <div>
                                                          <span class="fw-semibold text-dark d-block">{{ __('hrms.leave.encashment') }}</span>
                                                          <span class="text-muted fs-11">{{ __('hrms.leave.encashment_desc') }}</span>
                                                      </div>
                                                  </label>
                                              </div>
                                              
                                              <!-- Carry forward value option -->
                                              <div class="border-top pt-3 mt-3 d-none" id="yearend_carry_limit_div">
                                                  <div class="d-flex align-items-center gap-2">
                                                      <span>{{ __('hrms.leave.max_carry') }}</span>
                                                      <input type="number" id="yearend_max_carry" class="odoo-table-input text-center" style="width: 70px;" value="6"> {{ __('hrms.leave.days') }}
                                                  </div>
                                              </div>
                                              
                                              <!-- Encashment limit value option -->
                                              <div class="border-top pt-3 mt-3 d-none" id="yearend_encash_limit_div">
                                                  <div class="d-flex align-items-center gap-2">
                                                      <span>{{ __('hrms.leave.max_encash') }}</span>
                                                      <input type="number" id="yearend_max_encash" class="odoo-table-input text-center" style="width: 70px;" value="5"> {{ __('hrms.leave.days') }}
                                                  </div>
                                              </div>
                                          </div>
                                      </div>
                                  </div>
                                  
                                  <!-- Probation Tab Pane -->
                                  <div class="tab-pane fade" id="pane-probation" role="tabpanel">
                                      <h5 class="fw-bold text-dark mb-3">{{ __('hrms.leave.probation_rules') }}</h5>
                                      
                                      <div class="card border rounded-3 shadow-none">
                                          <div class="card-body p-3 fs-13">
                                              <h6 class="fw-bold text-dark mb-3">{{ __('hrms.leave.usage_during_probation') }}</h6>
                                              <div class="d-flex flex-column gap-3">
                                                  <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                      <input type="radio" name="probation_rule" value="allow" class="form-check-input mt-1" checked>
                                                      <div>
                                                          <span class="fw-semibold text-dark d-block">{{ __('hrms.leave.allow_probation') }}</span>
                                                          <span class="text-muted fs-11">{{ __('hrms.leave.allow_probation_desc') }}</span>
                                                      </div>
                                                  </label>
                                                  <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                      <input type="radio" name="probation_rule" value="disallow" class="form-check-input mt-1">
                                                      <div>
                                                          <span class="fw-semibold text-dark d-block">{{ __('hrms.leave.disallow_probation') }}</span>
                                                          <span class="text-muted fs-11">{{ __('hrms.leave.disallow_probation_desc') }}</span>
                                                      </div>
                                                  </label>
                                                  <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                      <input type="radio" name="probation_rule" value="allow_after_months" class="form-check-input mt-1">
                                                      <div>
                                                          <span class="fw-semibold text-dark d-block">{{ __('hrms.leave.allow_after_period') }}</span>
                                                          <span class="text-muted fs-11">{{ __('hrms.leave.allow_after_period_desc') }}</span>
                                                      </div>
                                                  </label>
                                              </div>
                                              
                                              <!-- Month value option -->
                                              <div class="border-top pt-3 mt-3 d-none" id="probation_months_div">
                                                  <div class="d-flex align-items-center gap-2">
                                                      <span>{{ __('hrms.leave.allowed_after_completing') }}</span>
                                                      <input type="number" id="probation_months" class="odoo-table-input text-center" style="width: 70px;" value="3"> {{ __('hrms.leave.months_of_joining') }}
                                                  </div>
                                              </div>
                                          </div>
                                      </div>
                                  </div>
                                  
                                  <!-- Notice Period Tab Pane -->
                                  <div class="tab-pane fade" id="pane-notice" role="tabpanel">
                                      <h5 class="fw-bold text-dark mb-3">{{ __('hrms.leave.notice_rules') }}</h5>
                                      
                                      <div class="card border rounded-3 shadow-none">
                                          <div class="card-body p-3 fs-13">
                                              <h6 class="fw-bold text-dark mb-3">{{ __('hrms.leave.usage_during_notice') }}</h6>
                                              <div class="d-flex flex-column gap-3">
                                                  <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                      <input type="radio" name="notice_rule" value="allow" class="form-check-input mt-1" checked>
                                                      <div>
                                                          <span class="fw-semibold text-dark d-block">{{ __('hrms.leave.allow_notice') }}</span>
                                                          <span class="text-muted fs-11">{{ __('hrms.leave.allow_notice_desc') }}</span>
                                                      </div>
                                                  </label>
                                                  <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                      <input type="radio" name="notice_rule" value="disallow" class="form-check-input mt-1">
                                                      <div>
                                                          <span class="fw-semibold text-dark d-block">{{ __('hrms.leave.disallow_notice') }}</span>
                                                          <span class="text-muted fs-11">{{ __('hrms.leave.disallow_notice_desc') }}</span>
                                                      </div>
                                                  </label>
                                                  <label class="form-check-label d-flex align-items-start gap-3 cursor-pointer">
                                                      <input type="radio" name="notice_rule" value="special_approval" class="form-check-input mt-1">
                                                      <div>
                                                          <span class="fw-semibold text-dark d-block">{{ __('hrms.leave.special_hr_approval') }}</span>
                                                          <span class="text-muted fs-11">{{ __('hrms.leave.special_hr_approval_desc') }}</span>
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
                      <button type="button" class="btn btn-light fs-13" data-bs-dismiss="modal">{{ __('hrms.common.close') }}</button>
                      <button type="button" class="btn btn-primary fs-13" onclick="saveLeaveRules()">{{ __('hrms.leave.save_rules') }}</button>
                  </div></div>
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

            // Close dropdowns when clicking anywhere outside (excluding clicks inside the dropdown or select2 containers)
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.dropdown, .erp-sort-dropdown, .erp-filter-dropdown, .select2-container, .select2-dropdown').length) {
                    $('.dropdown-menu').removeClass('show');
                }
            });


            // Instant client-side search for Leave Plans on left sidebar
            const leavePlanSearchInput = document.getElementById('leavePlanSearch');
            if (leavePlanSearchInput) {
                leavePlanSearchInput.addEventListener('input', filterLeavePlans);
            }

            // AJAX-based plan switching (no full page reloads)
            $(document).on('click', '.plan-switch-btn', function(e) {
                e.preventDefault();
                let clicked = $(this);
                let planId = clicked.attr('data-plan-id');

                // Switch active class in list items
                $('.plan-switch-btn').removeClass('active');
                $('.plan-switch-btn span').removeClass('text-primary').addClass('text-dark');
                
                clicked.addClass('active');
                clicked.find('span').removeClass('text-dark').addClass('text-primary');

                // Update URL parameter without reload
                if (history.pushState) {
                    let newurl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?plan_id=' + planId;
                    window.history.pushState({path:newurl}, '', newurl);
                }

                // Load plan details via AJAX (full reload of details container)
                loadPlanDetails(planId, 1, true);
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
                                  <i class="feather-check-circle"></i> {{ __('hrms.leave.rules_saved') }}
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
                        alert('{{ __('hrms.leave.rules_save_error') }}');
                    }
                });
            };

            // Client-side filtering and sorting functions for Leave Plans
            function filterLeavePlans() {
                const search = $('#leavePlanSearch').val().toLowerCase().trim();
                const statusVal = $('#lp_filter_status').val();
                const status = statusVal === '1' ? 'active' : (statusVal === '0' ? 'inactive' : 'all');
                const companyId = $('#lp_filter_company').val();
                
                $('.plan-item').each(function() {
                    const name = $(this).attr('data-name') || '';
                    const itemStatus = $(this).attr('data-status') || '';
                    const itemCompanyId = $(this).attr('data-company-id') || '';
                    
                    const matchesSearch = name.includes(search);
                    const matchesStatus = (status === 'all') || (itemStatus === status);
                    const matchesCompany = (companyId === '') || (itemCompanyId === companyId);
                    
                    if (matchesSearch && matchesStatus && matchesCompany) {
                        $(this).removeClass('d-none');
                    } else {
                        $(this).addClass('d-none');
                    }
                });

                // Auto close dropdowns
                $('.dropdown-menu').removeClass('show');
            }

            function resetLeavePlanFilters() {
                $('#lp_filter_status').val('').trigger('change');
                $('#lp_filter_company').val('').trigger('change');
                filterLeavePlans();
            }

            function sortLeavePlans(criteria, element) {
                // Toggle active class
                $(element).closest('.dropdown-menu').find('.dropdown-item').removeClass('active');
                $(element).addClass('active');
                
                const list = $('.list-group-flush');
                const items = list.find('.plan-item').get();
                
                items.sort((a, b) => {
                    if (criteria === 'name_asc') {
                        return $(a).attr('data-name').localeCompare($(b).attr('data-name'));
                    } else if (criteria === 'name_desc') {
                        return $(b).attr('data-name').localeCompare($(a).attr('data-name'));
                    } else if (criteria === 'newest') {
                        return parseInt($(b).attr('data-created-at') || 0) - parseInt($(a).attr('data-created-at') || 0);
                    }
                    return 0;
                });
                
                $.each(items, function(i, item) {
                    list.append(item);
                });

                // Auto close dropdowns
                $('.dropdown-menu').removeClass('show');
            }

            // AJAX-based search, sort, filter and pagination for Leave Types
            function loadPlanDetails(planId, page = 1, isPlanSwitch = false) {
                var search = $('.leave-type-search-input').val() || '';
                var sort = $('#lt_sort_value').val() || 'name_asc';
                var type = $('#lt_type_value').val() || '';
                
                var url = '{{ route("hrms.leave-structure.index") }}?plan_id=' + planId + 
                          '&lt_search=' + encodeURIComponent(search) + 
                          '&lt_sort=' + encodeURIComponent(sort) + 
                          '&lt_type=' + encodeURIComponent(type) + 
                          '&lt_page=' + page;
                          
                $.ajax({
                    url: url,
                    type: 'GET',
                    success: function(response) {
                        var parser = new DOMParser();
                        var doc = parser.parseFromString(response, 'text/html');
                        
                        if (isPlanSwitch) {
                            // Update active details container completely
                            var oldContainer = $('#activePlanDetailsContainer');
                            var newContainer = $(doc).find('#activePlanDetailsContainer');
                            if (newContainer.length && oldContainer.length) {
                                oldContainer.html(newContainer.html());
                            }
                        } else {
                            // Update only the table and pagination to preserve input focus & open states
                            var oldTable = $('#leaveTypesTable');
                            var newTable = $(doc).find('#leaveTypesTable');
                            if (newTable.length && oldTable.length) {
                                oldTable.html(newTable.html());
                            }
                            
                            var oldPagination = $('.leave-type-pagination-container');
                            var newPagination = $(doc).find('.leave-type-pagination-container');
                            if (newPagination.length && oldPagination.length) {
                                oldPagination.replaceWith(newPagination);
                            } else if (newPagination.length) {
                                $('#leaveTypesTable').parent().after(newPagination);
                            } else {
                                oldPagination.empty();
                            }
                        }

                        // Re-initialize Select2 inside active details container (especially the filter select)
                        if (window.jQuery && $.fn.select2) {
                            $('#activePlanDetailsContainer').find('.odoo-select2').each(function() {
                                var select = $(this);
                                if (!select.hasClass('select2-hidden-accessible')) {
                                    select.select2({
                                        theme: "bootstrap-5",
                                        width: "100%"
                                    });
                                }
                            });
                        }
                    }
                });
            }

            let leaveTypeSearchTimeout = null;
            $(document).on('input', '.leave-type-search-input', function() {
                clearTimeout(leaveTypeSearchTimeout);
                var planId = $(this).attr('data-plan-id');
                leaveTypeSearchTimeout = setTimeout(function() {
                    loadPlanDetails(planId, 1, false);
                }, 300);
            });

            window.changeLeaveTypeSort = function(planId, criteria, element) {
                var input = document.getElementById('lt_sort_value');
                if (input) {
                    input.value = criteria;
                }

                if (element) {
                    var menu = element.closest('.dropdown-menu');
                    if (menu) {
                        menu.querySelectorAll('.dropdown-item').forEach(function(el) {
                            el.classList.remove('active');
                        });
                    }
                    element.classList.add('active');
                }

                loadPlanDetails(planId, 1, false);
            };

            window.applyLeaveTypeFilter = function(planId) {
                var typeVal = $('#lt_filter_type').val() || '';
                var input = document.getElementById('lt_type_value');
                if (input) {
                    input.value = typeVal;
                }

                loadPlanDetails(planId, 1, false);
                $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                $('.erp-filter-dropdown.show').removeClass('show');
            };

            window.resetLeaveTypeFilters = function(planId) {
                $('#lt_filter_type').val('').trigger('change');
                var input = document.getElementById('lt_type_value');
                if (input) {
                    input.value = '';
                }
                $('.leave-type-search-input').val('');

                loadPlanDetails(planId, 1, false);
                $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                $('.erp-filter-dropdown.show').removeClass('show');
            };

            $(document).on('click', '#activePlanDetailsContainer .leave-type-pagination-container a', function(e) {
                e.preventDefault();
                var url = $(this).attr('href');
                if (!url) return;
                var urlParams = new URLSearchParams(url.substring(url.indexOf('?')));
                var page = urlParams.get('lt_page') || 1;
                var planId = $('.leave-type-search-input').attr('data-plan-id');
                loadPlanDetails(planId, page, false);
            });

            // Expose function references to window to bypass scope restrictions
            window.filterLeavePlans = filterLeavePlans;
            window.resetLeavePlanFilters = resetLeavePlanFilters;
            window.sortLeavePlans = sortLeavePlans;
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
