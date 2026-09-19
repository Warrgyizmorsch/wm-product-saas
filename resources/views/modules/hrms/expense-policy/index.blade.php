@extends('layouts.duralux')

@section('title', 'Expense Master | SaaS ERP')
@section('page-title', 'Expense Master')
@section('breadcrumb', 'HRMS / Masters / Expense Master')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        /* Underlined Horizontal Tabs (matching Shift Roster & Leave module) */
        #policyTabs .nav-link {
            border: none !important;
            background-color: transparent !important;
            color: #64748b;
            font-weight: 500;
            padding: 12px 20px;
            border-bottom: 2px solid transparent !important;
            transition: all 0.2s ease-in-out;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        #policyTabs .nav-link:hover {
            color: var(--bs-primary);
        }
        #policyTabs .nav-link.active {
            color: var(--bs-primary) !important;
            border-bottom: 2px solid var(--bs-primary) !important;
            font-weight: 600;
        }
    </style>
@endpush

@section('page-actions')
    @if($activeTab === 'policies')
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addPolicyModal" class="fw-bold text-uppercase">
            New Policy
        </x-ui.button>
    @elseif($activeTab === 'workflows')
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addWorkflowModal" class="fw-bold text-uppercase">
            New Workflow
        </x-ui.button>
    @else
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addCategoryModal" class="fw-bold text-uppercase">
            Add Category
        </x-ui.button>
    @endif
@endsection

@section('content')
<div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">

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

    {{-- Tabs header navigation --}}
    <ul class="nav gap-2 border-bottom pb-2 mb-4" id="policyTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'categories' ? 'active' : 'text-muted' }}" 
               href="{{ route('hrms.expense-policy.index', ['tab' => 'categories']) }}">
                <i class="feather-tag me-1"></i> Expense Categories
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'workflows' ? 'active' : 'text-muted' }}" 
               href="{{ route('hrms.expense-policy.index', ['tab' => 'workflows']) }}">
                <i class="feather-shield me-1"></i> Approval Workflows
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'policies' ? 'active' : 'text-muted' }}" 
               href="{{ route('hrms.expense-policy.index', ['tab' => 'policies']) }}">
                <i class="feather-file-text me-1"></i> Expense Policies
            </a>
        </li>
    </ul>

    {{-- ══════════════════════════════════════════════════════════════════════
         TAB 1: EXPENSE POLICIES
         ══════════════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'policies')
        {{-- Info banner --}}
        <div class="alert bg-soft-primary border-0 rounded-3 p-3 mb-4 fs-13 text-primary">
            <i class="feather-info me-2"></i>
            <strong>How it works:</strong>
            Create a named policy (e.g. <em>Manager Travel Policy</em>), assign it to a Designation, Department, or Scope,
            then click <strong>Manage Limits</strong> to define category-wise spending limits within that policy.
        </div>

        {{-- Toolbar --}}
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            {{-- Heading & Active Badges --}}
            <div class="d-flex align-items-center gap-3">
                <h5 class="fw-bold text-dark mb-0 fs-15">Expense Policies</h5>
                @if($filters['search'] || $filters['status'] !== '')
                    <div class="d-flex align-items-center gap-2">
                        @if($filters['search'])
                            <span class="badge bg-soft-primary text-primary px-2 py-1 fs-11 rounded-pill"><i class="feather-search me-1"></i>{{ $filters['search'] }}</span>
                        @endif
                        @if($filters['status'] !== '')
                            <span class="badge bg-soft-secondary text-secondary px-2 py-1 fs-11 rounded-pill">Status: {{ $filters['status'] === '1' ? 'Active' : 'Inactive' }}</span>
                        @endif
                        <a href="{{ route('hrms.expense-policy.index', ['tab' => 'policies']) }}" class="text-danger fs-12 fw-semibold"><i class="feather-x"></i> Clear</a>
                    </div>
                @endif
            </div>

            {{-- Actions (Search, Sort, Filter) --}}
            <div class="d-flex align-items-center gap-2 flex-wrap">
                {{-- Search --}}
                <form method="GET" action="{{ route('hrms.expense-policy.index') }}" id="policySearchForm" 
                      class="d-flex align-items-center border rounded px-3 py-1 m-0" 
                      style="background-color: #f1f5f9; min-width: 220px; height: 38px;">
                    <input type="hidden" name="tab"    value="policies">
                    <input type="hidden" name="sort"   value="{{ $filters['sort'] }}">
                    <input type="hidden" name="status" value="{{ $filters['status'] }}">
                    <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                    <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-13 text-dark" placeholder="Search policy..." value="{{ $filters['search'] }}" style="box-shadow:none; outline:none; height:32px;">
                </form>

                {{-- Sort --}}
                <x-ui.sort-dropdown label="Sort">
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'name_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'name_asc']) }}">
                        <span>Name A → Z</span>
                        @if($filters['sort'] === 'name_asc') <i class="feather-check ms-3"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'name_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'name_desc']) }}">
                        <span>Name Z → A</span>
                        @if($filters['sort'] === 'name_desc') <i class="feather-check ms-3"></i> @endif
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'newest' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'newest']) }}">
                        <span>Newest First</span>
                        @if($filters['sort'] === 'newest') <i class="feather-check ms-3"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'oldest' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'oldest']) }}">
                        <span>Oldest First</span>
                        @if($filters['sort'] === 'oldest') <i class="feather-check ms-3"></i> @endif
                    </a>
                </x-ui.sort-dropdown>

                {{-- Filter --}}
                <x-ui.filter label="Filter">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> Filter Options</h6>
                    <form method="GET" action="{{ route('hrms.expense-policy.index') }}" id="policyFilterForm">
                        <input type="hidden" name="tab"    value="policies">
                        <input type="hidden" name="search" value="{{ $filters['search'] }}">
                        <input type="hidden" name="sort"   value="{{ $filters['sort'] }}">
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.odoo-form-ui type="select" name="status" id="policy_filter_status">
                                <option value="">All Statuses</option>
                                <option value="1" @selected($filters['status'] === '1')>Active</option>
                                <option value="0" @selected($filters['status'] === '0')>Inactive</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="dropdown-divider my-3"></div>
                        <div class="d-flex gap-2">
                            <x-ui.button type="submit" variant="primary" size="sm" class="flex-grow-1">Apply Filters</x-ui.button>
                            <a href="{{ route('hrms.expense-policy.index', ['tab' => 'policies']) }}" class="btn btn-sm btn-light border flex-grow-1 d-flex align-items-center justify-content-center" style="font-size: 12px; font-weight: 500;">Reset</a>
                        </div>
                    </form>
                </x-ui.filter>
            </div>
        </div>

        {{-- Policy Cards grid --}}
        @if($policies->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="feather-file-text fs-32 d-block mb-3 text-secondary"></i>
                <p class="mb-1">No expense policies found.</p>
                <p class="fs-12">Click <strong>New Policy</strong> to create one.</p>
            </div>
        @else
            <div class="row g-3" id="policyGridRow">
                @foreach($policies as $policy)
                    <div class="col-sm-6 col-md-4 col-xl-3">
                        <div class="card h-100 shadow-sm" style="border: 1px solid #e2e8f0 !important; border-radius: 12px !important; transition: all 0.2s ease-in-out;">
                            <div class="card-body p-3 d-flex flex-column">
                                {{-- Card Header --}}
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded bg-soft-primary d-flex align-items-center justify-content-center text-primary" style="width:32px;height:32px;">
                                            <i class="feather-file-text fs-14"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold text-dark mb-0 fs-13" style="letter-spacing: -0.1px;">{{ $policy->name }}</h6>
                                            <span class="fs-11 text-muted d-block mt-0.5">
                                                @if($policy->designation)
                                                    {{ $policy->designation->name }}
                                                @elseif($policy->department)
                                                    {{ $policy->department->name }}
                                                @else
                                                    All Employees
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                    <x-ui.badge variant="{{ $policy->status ? 'success' : 'secondary' }}" soft class="px-2 py-0.5 fs-10 rounded-pill flex-shrink-0">
                                        {{ $policy->status ? 'Active' : 'Inactive' }}
                                    </x-ui.badge>
                                </div>

                                {{-- Description --}}
                                @if($policy->description)
                                    <p class="text-muted fs-11 mb-2 mt-1" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4;">
                                        {{ $policy->description }}
                                    </p>
                                @endif

                                {{-- Badges Row --}}
                                <div class="d-flex align-items-center gap-1 mb-2 flex-wrap fs-10">
                                    <span class="badge bg-soft-info text-info px-2 py-1 rounded-pill d-inline-flex align-items-center">
                                        <i class="feather-list me-1" style="font-size: 11px; margin-top: -1px;"></i>{{ $policy->rules->count() }} Rules
                                    </span>
                                    @if($policy->company)
                                        <span class="badge bg-light text-muted border px-2 py-1 rounded-pill d-inline-flex align-items-center" title="{{ $policy->company->company_name }}">
                                            <i class="feather-home me-1" style="font-size: 11px; margin-top: -1px;"></i>{{ Str::limit($policy->company->company_name, 12) }}
                                        </span>
                                    @endif
                                </div>

                                {{-- Categories list if not empty --}}
                                @if($policy->rules->isNotEmpty())
                                    <div class="d-flex flex-wrap gap-1 mb-3">
                                        @foreach($policy->rules->take(3) as $rule)
                                            <span class="badge bg-light text-dark border fs-10 px-2 py-0.5" style="border-radius: 4px;">{{ $rule->category->name }}</span>
                                        @endforeach
                                        @if($policy->rules->count() > 3)
                                            <span class="badge bg-light text-muted border fs-9 px-1.5 py-0.5" style="border-radius: 4px;">+{{ $policy->rules->count() - 3 }}</span>
                                        @endif
                                    </div>
                                    <div class="flex-grow-1"></div>
                                @else
                                    <div class="flex-grow-1"></div>
                                @endif

                                {{-- Actions Divider & Buttons --}}
                                <div class="d-flex align-items-center gap-2 mt-auto pt-2 border-top">
                                    <a href="{{ route('hrms.expense-policy.rules', $policy) }}" class="btn btn-sm btn-primary flex-fill fw-bold fs-11 text-uppercase d-flex align-items-center justify-content-center py-1.5" style="border-radius: 6px;">
                                        <i class="feather-sliders me-1 fs-11"></i> Limits
                                    </a>
                                    
                                    <div class="d-flex align-items-center gap-1">
                                        <x-ui.icon-btn type="button" variant="soft-primary" size="sm" class="btn-edit-policy"
                                            icon="feather-edit-3"
                                            title="Edit Policy"
                                            data-id="{{ $policy->id }}"
                                            data-name="{{ $policy->name }}"
                                            data-description="{{ $policy->description }}"
                                            data-designation-id="{{ $policy->designation_id }}"
                                            data-department-id="{{ $policy->department_id }}"
                                            data-company-id="{{ $policy->company_id }}"
                                            data-business-unit-id="{{ $policy->business_unit_id }}"
                                            data-branch-id="{{ $policy->branch_id }}"
                                            data-status="{{ $policy->status ? 1 : 0 }}"
                                            data-approval-type="{{ $policy->approval_type ?? '1_level' }}"
                                            data-first-approver="{{ $policy->first_approver ?? 'reporting_manager' }}"
                                            data-second-approver="{{ $policy->second_approver ?? 'finance_manager' }}"
                                            data-amount-threshold="{{ $policy->amount_threshold_for_2_level ?? '' }}"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editPolicyModal"
                                        />
                                        
                                        <form method="POST" action="{{ route('hrms.expense-policy.destroy', $policy) }}" onsubmit="return confirmFormSubmit(event, 'Are you sure you want to delete this policy and all its category limits?', { title: 'Delete Policy', variant: 'danger', confirmButtonText: 'Delete' });" class="m-0 d-flex">
                                            @csrf @method('DELETE')
                                            <x-ui.icon-btn type="submit" variant="soft-danger" size="sm" icon="feather-trash-2" title="Delete Policy" />
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

    {{-- ══════════════════════════════════════════════════════════════════════
         TAB 2: APPROVAL WORKFLOWS
         ══════════════════════════════════════════════════════════════════════ --}}
    @elseif($activeTab === 'workflows')
        {{-- Info banner --}}
        <div class="alert bg-soft-info border-0 rounded-3 p-3 mb-4 fs-13 text-info">
            <i class="feather-shield me-2"></i>
            <strong>Approval Workflow Engine:</strong> Define approval hierarchies independently of expense policies. Set 1-Level or 2-Level approval requirements, select approver roles (Reporting Manager, Department Head, HR Admin, Finance Manager), and set optional amount thresholds for automatic 2-level escalation.
        </div>

        {{-- Toolbar --}}
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            {{-- Heading & Active Badges --}}
            <div class="d-flex align-items-center gap-3">
                <h5 class="fw-bold text-dark mb-0 fs-15">Approval Workflows</h5>
                @if($workflowFilters['search'] || $workflowFilters['status'] !== '')
                    <div class="d-flex align-items-center gap-2">
                        @if($workflowFilters['search'])
                            <span class="badge bg-soft-primary text-primary px-2 py-1 fs-11 rounded-pill"><i class="feather-search me-1"></i>{{ $workflowFilters['search'] }}</span>
                        @endif
                        @if($workflowFilters['status'] !== '')
                            <span class="badge bg-soft-secondary text-secondary px-2 py-1 fs-11 rounded-pill">Status: {{ $workflowFilters['status'] === '1' ? 'Active' : 'Inactive' }}</span>
                        @endif
                        <a href="{{ route('hrms.expense-policy.index', ['tab' => 'workflows']) }}" class="text-danger fs-12 fw-semibold"><i class="feather-x"></i> Clear</a>
                    </div>
                @endif
            </div>

            {{-- Actions (Search, Sort, Filter) --}}
            <div class="d-flex align-items-center gap-2 flex-wrap">
                {{-- Search --}}
                <form method="GET" action="{{ route('hrms.expense-policy.index') }}" id="workflowSearchForm" 
                      class="d-flex align-items-center border rounded px-3 py-1 m-0" 
                      style="background-color: #f1f5f9; min-width: 220px; height: 38px;">
                    <input type="hidden" name="tab"             value="workflows">
                    <input type="hidden" name="wf_sort"         value="{{ $workflowFilters['sort'] }}">
                    <input type="hidden" name="wf_status"       value="{{ $workflowFilters['status'] }}">
                    <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                    <input type="text" name="wf_search" class="form-control border-0 bg-transparent p-0 fs-13 text-dark" placeholder="Search workflows..." value="{{ $workflowFilters['search'] }}" style="box-shadow:none; outline:none; height:32px;">
                </form>

                {{-- Sort --}}
                <x-ui.sort-dropdown label="Sort">
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $workflowFilters['sort'] === 'name_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['wf_sort' => 'name_asc', 'tab' => 'workflows']) }}">
                        <span>Name A → Z</span>
                        @if($workflowFilters['sort'] === 'name_asc') <i class="feather-check ms-3"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $workflowFilters['sort'] === 'name_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['wf_sort' => 'name_desc', 'tab' => 'workflows']) }}">
                        <span>Name Z → A</span>
                        @if($workflowFilters['sort'] === 'name_desc') <i class="feather-check ms-3"></i> @endif
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $workflowFilters['sort'] === 'newest' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['wf_sort' => 'newest', 'tab' => 'workflows']) }}">
                        <span>Newest First</span>
                        @if($workflowFilters['sort'] === 'newest') <i class="feather-check ms-3"></i> @endif
                    </a>
                </x-ui.sort-dropdown>

                {{-- Filter --}}
                <x-ui.filter label="Filter">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> Filter Options</h6>
                    <form method="GET" action="{{ route('hrms.expense-policy.index') }}" id="workflowFilterForm">
                        <input type="hidden" name="tab"       value="workflows">
                        <input type="hidden" name="wf_search" value="{{ $workflowFilters['search'] }}">
                        <input type="hidden" name="wf_sort"   value="{{ $workflowFilters['sort'] }}">
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.odoo-form-ui type="select" name="wf_status" id="wf_filter_status">
                                <option value="">All Statuses</option>
                                <option value="1" @selected($workflowFilters['status'] === '1')>Active</option>
                                <option value="0" @selected($workflowFilters['status'] === '0')>Inactive</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="dropdown-divider my-3"></div>
                        <div class="d-flex gap-2">
                            <x-ui.button type="submit" variant="primary" size="sm" class="flex-grow-1">Apply Filters</x-ui.button>
                            <a href="{{ route('hrms.expense-policy.index', ['tab' => 'workflows']) }}" class="btn btn-sm btn-light border flex-grow-1 d-flex align-items-center justify-content-center" style="font-size: 12px; font-weight: 500;">Reset</a>
                        </div>
                    </form>
                </x-ui.filter>
            </div>
        </div>

        {{-- Workflows Cards List --}}
        @if($workflowsList->isEmpty())
            <div class="text-center text-muted py-5 border rounded bg-light">
                <i class="feather-shield fs-24 d-block mb-2 text-secondary"></i>
                <p class="mb-1 fw-medium text-dark">No approval workflows configured yet.</p>
                <span class="fs-12">Click <strong>New Workflow</strong> to set up custom multi-level approvals for departments, designations, or company-wide.</span>
            </div>
        @else
            <div class="row g-3">
                @foreach($workflowsList as $wf)
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 border shadow-none hover-shadow transition-all">
                            <div class="card-body p-3 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="fw-bold text-dark mb-0 fs-14">
                                            {{ $wf->name }}
                                            @if($wf->is_default)
                                                <span class="badge bg-soft-warning text-warning ms-1 fs-10 rounded-pill">Default</span>
                                            @endif
                                        </h6>
                                        <x-ui.badge variant="{{ $wf->status ? 'success' : 'danger' }}" soft class="px-2 py-1 fs-11 rounded-pill">
                                            {{ $wf->status ? 'Active' : 'Inactive' }}
                                        </x-ui.badge>
                                    </div>
                                    <p class="text-muted fs-12 mb-3">{{ $wf->description ?: 'No description provided.' }}</p>

                                    <div class="bg-light p-2 rounded mb-3 fs-12">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="text-muted">Approval Hierarchy:</span>
                                            <span class="fw-semibold text-primary">
                                                {{ $wf->approval_type === '2_level' ? '2-Level Approval' : '1-Level Approval' }}
                                            </span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="text-muted">1st Level Approver:</span>
                                            <span class="fw-semibold text-dark text-capitalize">
                                                {{ str_replace('_', ' ', $wf->first_approver ?? 'reporting_manager') }}
                                            </span>
                                        </div>
                                        @if($wf->approval_type === '2_level' || $wf->amount_threshold_for_2_level)
                                            <div class="d-flex justify-content-between mb-1">
                                                <span class="text-muted">2nd Level Approver:</span>
                                                <span class="fw-semibold text-dark text-capitalize">
                                                    {{ str_replace('_', ' ', $wf->second_approver ?? 'finance_manager') }}
                                                </span>
                                            </div>
                                        @endif
                                        @if($wf->amount_threshold_for_2_level)
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">Auto Escalation Threshold:</span>
                                                <span class="fw-bold text-warning">
                                                    > ₹{{ number_format($wf->amount_threshold_for_2_level, 2) }}
                                                </span>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="fs-12 text-muted">
                                        <i class="feather-tag me-1"></i>
                                        @if($wf->designation)
                                            Designation: <strong>{{ $wf->designation->name }}</strong>
                                        @elseif($wf->department)
                                            Department: <strong>{{ $wf->department->name }}</strong>
                                        @elseif($wf->company)
                                            Company: <strong>{{ $wf->company->company_name }}</strong>
                                        @else
                                            Scope: <strong>Global System Default</strong>
                                        @endif
                                    </div>
                                </div>

                                <div class="border-top pt-2 mt-3 d-flex justify-content-end gap-2">
                                    <x-ui.icon-btn type="button" variant="soft-primary" size="sm" class="btn-edit-workflow" icon="feather-edit-3"
                                        title="Edit Workflow"
                                        data-id="{{ $wf->id }}"
                                        data-name="{{ $wf->name }}"
                                        data-description="{{ $wf->description }}"
                                        data-designation-id="{{ $wf->designation_id }}"
                                        data-department-id="{{ $wf->department_id }}"
                                        data-company-id="{{ $wf->company_id }}"
                                        data-approval-type="{{ $wf->approval_type }}"
                                        data-first-approver="{{ $wf->first_approver }}"
                                        data-second-approver="{{ $wf->second_approver }}"
                                        data-amount-threshold="{{ $wf->amount_threshold_for_2_level }}"
                                        data-is-default="{{ $wf->is_default ? 1 : 0 }}"
                                        data-status="{{ $wf->status ? 1 : 0 }}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editWorkflowModal"
                                    />
                                    <form method="POST" action="{{ route('hrms.expense-policy.workflows.destroy', $wf) }}" onsubmit="return confirmFormSubmit(event, 'Are you sure you want to delete this approval workflow?', { title: 'Delete Workflow', variant: 'danger', confirmButtonText: 'Delete' });" class="m-0 d-flex">
                                        @csrf @method('DELETE')
                                        <x-ui.icon-btn type="submit" variant="soft-danger" size="sm" icon="feather-trash-2" title="Delete Workflow" />
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

    {{-- ══════════════════════════════════════════════════════════════════════
         TAB 3: EXPENSE CATEGORIES
         ══════════════════════════════════════════════════════════════════════ --}}
    @else
        {{-- Toolbar --}}
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            {{-- Heading & Active Badges --}}
            <div class="d-flex align-items-center gap-3">
                <h5 class="fw-bold text-dark mb-0 fs-15">Expense Categories</h5>
                @if($catFilters['search'] || $catFilters['status'] !== '')
                    <div class="d-flex align-items-center gap-2">
                        @if($catFilters['search'])
                            <span class="badge bg-soft-primary text-primary px-2 py-1 fs-11 rounded-pill"><i class="feather-search me-1"></i>{{ $catFilters['search'] }}</span>
                        @endif
                        @if($catFilters['status'] !== '')
                            <span class="badge bg-soft-secondary text-secondary px-2 py-1 fs-11 rounded-pill">Status: {{ $catFilters['status'] === '1' ? 'Active' : 'Inactive' }}</span>
                        @endif
                        <a href="{{ route('hrms.expense-policy.index', ['tab' => 'categories']) }}" class="text-danger fs-12 fw-semibold"><i class="feather-x"></i> Clear</a>
                    </div>
                @endif
            </div>

            {{-- Actions (Search, Sort, Filter) --}}
            <div class="d-flex align-items-center gap-2 flex-wrap">
                {{-- Search --}}
                <form method="GET" action="{{ route('hrms.expense-policy.index') }}" id="categorySearchForm" 
                      class="d-flex align-items-center border rounded px-3 py-1 m-0" 
                      style="background-color: #f1f5f9; min-width: 220px; height: 38px;">
                    <input type="hidden" name="tab"        value="categories">
                    <input type="hidden" name="cat_sort"   value="{{ $catFilters['sort'] }}">
                    <input type="hidden" name="cat_status" value="{{ $catFilters['status'] }}">
                    <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                    <input type="text" name="cat_search" class="form-control border-0 bg-transparent p-0 fs-13 text-dark" placeholder="Search categories..." value="{{ $catFilters['search'] }}" style="box-shadow:none; outline:none; height:32px;">
                </form>

                {{-- Sort --}}
                <x-ui.sort-dropdown label="Sort">
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $catFilters['sort'] === 'name_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['cat_sort' => 'name_asc', 'tab' => 'categories']) }}">
                        <span>Name A → Z</span>
                        @if($catFilters['sort'] === 'name_asc') <i class="feather-check ms-3"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $catFilters['sort'] === 'name_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['cat_sort' => 'name_desc', 'tab' => 'categories']) }}">
                        <span>Name Z → A</span>
                        @if($catFilters['sort'] === 'name_desc') <i class="feather-check ms-3"></i> @endif
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $catFilters['sort'] === 'code_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['cat_sort' => 'code_asc', 'tab' => 'categories']) }}">
                        <span>Code A → Z</span>
                        @if($catFilters['sort'] === 'code_asc') <i class="feather-check ms-3"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $catFilters['sort'] === 'code_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['cat_sort' => 'code_desc', 'tab' => 'categories']) }}">
                        <span>Code Z → A</span>
                        @if($catFilters['sort'] === 'code_desc') <i class="feather-check ms-3"></i> @endif
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $catFilters['sort'] === 'newest' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['cat_sort' => 'newest', 'tab' => 'categories']) }}">
                        <span>Newest First</span>
                        @if($catFilters['sort'] === 'newest') <i class="feather-check ms-3"></i> @endif
                    </a>
                </x-ui.sort-dropdown>

                {{-- Filter --}}
                <x-ui.filter label="Filter">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> Filter Options</h6>
                    <form method="GET" action="{{ route('hrms.expense-policy.index') }}" id="categoryFilterForm">
                        <input type="hidden" name="tab"        value="categories">
                        <input type="hidden" name="cat_search" value="{{ $catFilters['search'] }}">
                        <input type="hidden" name="cat_sort"   value="{{ $catFilters['sort'] }}">
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.odoo-form-ui type="select" name="cat_status" id="cat_filter_status">
                                <option value="">All Statuses</option>
                                <option value="1" @selected($catFilters['status'] === '1')>Active</option>
                                <option value="0" @selected($catFilters['status'] === '0')>Inactive</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="dropdown-divider my-3"></div>
                        <div class="d-flex gap-2">
                            <x-ui.button type="submit" variant="primary" size="sm" class="flex-grow-1">Apply Filters</x-ui.button>
                            <a href="{{ route('hrms.expense-policy.index', ['tab' => 'categories']) }}" class="btn btn-sm btn-light border flex-grow-1 d-flex align-items-center justify-content-center" style="font-size: 12px; font-weight: 500;">Reset</a>
                        </div>
                    </form>
                </x-ui.filter>
            </div>
        </div>

        {{-- Categories Table --}}
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                <thead class="table-light">
                    <tr>
                        <th style="width: 15%;">Code</th>
                        <th style="width: 25%;">Category Name</th>
                        <th style="width: 40%;">Description</th>
                        <th style="width: 10%;">Status</th>
                        <th style="width: 10%;" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="categoriesTableBody">
                    @forelse($categoriesList as $cat)
                        <tr>
                            <td class="fw-bold text-primary">{{ $cat->code }}</td>
                            <td class="text-dark fw-semibold">{{ $cat->name }}</td>
                            <td class="text-muted">{{ $cat->description ?: '-' }}</td>
                            <td>
                                <x-ui.badge variant="{{ $cat->status ? 'success' : 'danger' }}" soft class="px-2 py-1 fs-11 rounded-pill">
                                    {{ $cat->status ? 'Active' : 'Inactive' }}
                                </x-ui.badge>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <x-ui.icon-btn type="button" variant="soft-primary" size="sm" class="btn-edit-category" icon="feather-edit-3"
                                        title="Edit Category"
                                        data-id="{{ $cat->id }}"
                                        data-name="{{ $cat->name }}"
                                        data-code="{{ $cat->code }}"
                                        data-description="{{ $cat->description }}"
                                        data-status="{{ $cat->status ? 1 : 0 }}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editCategoryModal"
                                    />
                                    <form method="POST" action="{{ route('hrms.expense-categories.destroy', $cat) }}" onsubmit="return confirmFormSubmit(event, 'Are you sure you want to delete this category?', { title: 'Delete Category', variant: 'danger', confirmButtonText: 'Delete' });" class="m-0 d-flex">
                                        @csrf @method('DELETE')
                                        <x-ui.icon-btn type="submit" variant="soft-danger" size="sm" icon="feather-trash-2" title="Delete Category" />
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="feather-tag fs-24 d-block mb-2 text-secondary"></i>
                                No categories found. Click <strong>Add Category</strong> to create one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection

{{-- ══════════════════════════════════════════════════════════════════════
     MODALS
     ══════════════════════════════════════════════════════════════════════ --}}

{{-- Modal 1: Add Policy --}}
<x-ui.modal id="addPolicyModal" title='<i class="feather-file-text me-2 text-primary"></i>New Expense Policy'
    centered formAction="{{ route('hrms.expense-policy.store') }}" formMethod="POST"
    submitText="Create Policy" closeText="Cancel">
    <div class="d-flex flex-column gap-3">
        <x-ui.odoo-form-ui type="input" label="Policy Name" name="name" id="add_name" placeholder="e.g. Manager Travel Policy" :required="true" />
        <x-ui.odoo-form-ui type="textarea" label="Description" name="description" id="add_desc" placeholder="Brief description..." rows="2" />
        <x-ui.odoo-form-ui type="select" label="Company Scope" name="company_id" id="add_company" select2-selector="default">
            <option value="">All Companies (Global)</option>
            @foreach($companies as $c)
                <option value="{{ $c->id }}">{{ $c->company_name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
        <x-ui.odoo-form-ui type="select" label="Business Unit Scope" name="business_unit_id" id="add_business_unit" select2-selector="default">
            <option value="">All Business Units</option>
            @foreach($businessUnits as $bu)
                <option value="{{ $bu->id }}">{{ $bu->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
        <x-ui.odoo-form-ui type="select" label="Branch Scope" name="branch_id" id="add_branch" select2-selector="default">
            <option value="">All Branches</option>
            @foreach($branches as $b)
                <option value="{{ $b->id }}">{{ $b->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
        <x-ui.odoo-form-ui type="select" label="Assign to Department" name="department_id" id="add_department" select2-selector="default">
            <option value="">All Departments</option>
            @foreach($departments as $d)
                <option value="{{ $d->id }}">{{ $d->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
        <x-ui.odoo-form-ui type="select" label="Assign to Designation" name="designation_id" id="add_designation" select2-selector="default">
            <option value="">All Designations</option>
            @foreach($designations as $d)
                <option value="{{ $d->id }}">{{ $d->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
        <div class="p-3 bg-light rounded border my-1">
            <h6 class="fw-bold text-dark fs-12 mb-2"><i class="feather-shield text-primary me-1"></i> Approval Workflow Configuration</h6>
            <div class="d-flex flex-column gap-2">
                <x-ui.odoo-form-ui type="select" label="Workflow Mode" name="approval_type" id="add_approval_type" select2-selector="default">
                    <option value="1_level" selected>1-Level Approval (Standard)</option>
                    <option value="2_level">2-Level Approval (Dual Level)</option>
                    <option value="conditional_threshold">Conditional 2-Level (Amount Threshold)</option>
                </x-ui.odoo-form-ui>
                <x-ui.odoo-form-ui type="select" label="Level 1 Approver Role" name="first_approver" id="add_first_approver" select2-selector="default">
                    <option value="reporting_manager" selected>Reporting Manager (Direct Supervisor)</option>
                    <option value="department_head">Department Head (HOD)</option>
                    <option value="hr_admin">HR Admin / System Admin</option>
                </x-ui.odoo-form-ui>
                <x-ui.odoo-form-ui type="select" label="Level 2 Approver Role" name="second_approver" id="add_second_approver" select2-selector="default">
                    <option value="finance_manager" selected>Finance / Accounts Manager</option>
                    <option value="hr_admin">HR Admin / System Admin</option>
                    <option value="department_head">Department Head (HOD)</option>
                </x-ui.odoo-form-ui>
                <x-ui.odoo-form-ui type="input" label="Threshold Amount for 2-Level (₹ / $)" name="amount_threshold_for_2_level" id="add_amount_threshold" placeholder="e.g. 15000 (Triggers 2-level if claim > threshold)" />
            </div>
        </div>
        <x-ui.odoo-form-ui type="select" label="Status" name="status" id="add_status" select2-selector="default">
            <option value="1" selected>Active</option>
            <option value="0">Inactive</option>
        </x-ui.odoo-form-ui>
    </div>
</x-ui.modal>

{{-- Modal 2: Edit Policy --}}
<x-ui.modal id="editPolicyModal" title='<i class="feather-edit-3 me-2 text-primary"></i>Edit Expense Policy'
    centered formAction="#" formMethod="PUT" submitText="Update Policy" closeText="Cancel">
    <div class="d-flex flex-column gap-3">
        <x-ui.odoo-form-ui type="input" label="Policy Name" name="name" id="edit_name" :required="true" />
        <x-ui.odoo-form-ui type="textarea" label="Description" name="description" id="edit_desc" rows="2" />
        <x-ui.odoo-form-ui type="select" label="Company Scope" name="company_id" id="edit_company" select2-selector="default">
            <option value="">All Companies (Global)</option>
            @foreach($companies as $c)
                <option value="{{ $c->id }}">{{ $c->company_name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
        <x-ui.odoo-form-ui type="select" label="Business Unit Scope" name="business_unit_id" id="edit_business_unit" select2-selector="default">
            <option value="">All Business Units</option>
            @foreach($businessUnits as $bu)
                <option value="{{ $bu->id }}">{{ $bu->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
        <x-ui.odoo-form-ui type="select" label="Branch Scope" name="branch_id" id="edit_branch" select2-selector="default">
            <option value="">All Branches</option>
            @foreach($branches as $b)
                <option value="{{ $b->id }}">{{ $b->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
        <x-ui.odoo-form-ui type="select" label="Assign to Department" name="department_id" id="edit_department" select2-selector="default">
            <option value="">All Departments</option>
            @foreach($departments as $d)
                <option value="{{ $d->id }}">{{ $d->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
        <x-ui.odoo-form-ui type="select" label="Assign to Designation" name="designation_id" id="edit_designation" select2-selector="default">
            <option value="">All Designations</option>
            @foreach($designations as $d)
                <option value="{{ $d->id }}">{{ $d->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
        <div class="p-3 bg-light rounded border my-1">
            <h6 class="fw-bold text-dark fs-12 mb-2"><i class="feather-shield text-primary me-1"></i> Approval Workflow Configuration</h6>
            <div class="d-flex flex-column gap-2">
                <x-ui.odoo-form-ui type="select" label="Workflow Mode" name="approval_type" id="edit_approval_type" select2-selector="default">
                    <option value="1_level">1-Level Approval (Standard)</option>
                    <option value="2_level">2-Level Approval (Dual Level)</option>
                    <option value="conditional_threshold">Conditional 2-Level (Amount Threshold)</option>
                </x-ui.odoo-form-ui>
                <x-ui.odoo-form-ui type="select" label="Level 1 Approver Role" name="first_approver" id="edit_first_approver" select2-selector="default">
                    <option value="reporting_manager">Reporting Manager (Direct Supervisor)</option>
                    <option value="department_head">Department Head (HOD)</option>
                    <option value="hr_admin">HR Admin / System Admin</option>
                </x-ui.odoo-form-ui>
                <x-ui.odoo-form-ui type="select" label="Level 2 Approver Role" name="second_approver" id="edit_second_approver" select2-selector="default">
                    <option value="finance_manager">Finance / Accounts Manager</option>
                    <option value="hr_admin">HR Admin / System Admin</option>
                    <option value="department_head">Department Head (HOD)</option>
                </x-ui.odoo-form-ui>
                <x-ui.odoo-form-ui type="input" label="Threshold Amount for 2-Level (₹ / $)" name="amount_threshold_for_2_level" id="edit_amount_threshold" placeholder="e.g. 15000" />
            </div>
        </div>
        <x-ui.odoo-form-ui type="select" label="Status" name="status" id="edit_status" select2-selector="default">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </x-ui.odoo-form-ui>
    </div>
</x-ui.modal>

{{-- Modal 3: Add Category --}}
<x-ui.modal id="addCategoryModal" title='<i class="feather-tag me-2 text-primary"></i>Add Expense Category'
    centered formAction="{{ route('hrms.expense-categories.store') }}" formMethod="POST" submitText="Save Category" closeText="Cancel">
    <div class="d-flex flex-column gap-3">
        <x-ui.odoo-form-ui type="input" label="Code" name="code" id="add_cat_code" placeholder="e.g. MEALS, LODGING" :required="true" />
        <x-ui.odoo-form-ui type="input" label="Category Name" name="name" id="add_cat_name" placeholder="e.g. Food & Dining" :required="true" />
        <x-ui.odoo-form-ui type="textarea" label="Description" name="description" id="add_cat_desc" placeholder="Optional description..." rows="3" />
        <x-ui.odoo-form-ui type="select" label="Status" name="status" id="add_cat_status" select2-selector="default">
            <option value="1" selected>Active</option>
            <option value="0">Inactive</option>
        </x-ui.odoo-form-ui>
    </div>
</x-ui.modal>

{{-- Modal 4: Edit Category --}}
<x-ui.modal id="editCategoryModal" title='<i class="feather-edit-3 me-2 text-primary"></i>Edit Expense Category'
    centered formAction="#" formMethod="PUT" submitText="Update Category" closeText="Cancel">
    <div class="d-flex flex-column gap-3">
        <x-ui.odoo-form-ui type="input" label="Code" name="code" id="edit_cat_code" :required="true" />
        <x-ui.odoo-form-ui type="input" label="Category Name" name="name" id="edit_cat_name" :required="true" />
        <x-ui.odoo-form-ui type="textarea" label="Description" name="description" id="edit_cat_desc" rows="3" />
        <x-ui.odoo-form-ui type="select" label="Status" name="status" id="edit_cat_status" select2-selector="default">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </x-ui.odoo-form-ui>
    </div>
</x-ui.modal>

{{-- Modal 5: Add Approval Workflow --}}
<x-ui.modal id="addWorkflowModal" title='<i class="feather-shield me-2 text-primary"></i>New Approval Workflow'
    centered formAction="{{ route('hrms.expense-policy.workflows.store') }}" formMethod="POST"
    submitText="Create Workflow" closeText="Cancel">
    <div class="d-flex flex-column gap-3">
        <x-ui.odoo-form-ui type="input" label="Workflow Name" name="name" id="wf_add_name" placeholder="e.g. Standard Travel Approval Workflow" :required="true" />
        <x-ui.odoo-form-ui type="textarea" label="Description" name="description" id="wf_add_desc" placeholder="Brief description..." rows="2" />
        
        <x-ui.odoo-form-ui type="select" label="Company Scope" name="company_id" id="wf_add_company" select2-selector="default">
            <option value="">All Companies (Global)</option>
            @foreach($companies as $c)
                <option value="{{ $c->id }}">{{ $c->company_name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>

        <x-ui.odoo-form-ui type="select" label="Assign to Department" name="department_id" id="wf_add_department" select2-selector="default">
            <option value="">All Departments</option>
            @foreach($departments as $dept)
                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>

        <x-ui.odoo-form-ui type="select" label="Assign to Designation" name="designation_id" id="wf_add_designation" select2-selector="default">
            <option value="">All Designations</option>
            @foreach($designations as $d)
                <option value="{{ $d->id }}">{{ $d->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>

        <div class="p-3 bg-light rounded border my-1">
            <h6 class="fw-bold text-dark fs-12 mb-2"><i class="feather-layers text-primary me-1"></i> Approval Workflow Configuration</h6>
            <div class="d-flex flex-column gap-2">
                <x-ui.odoo-form-ui type="select" label="Workflow Mode" name="approval_type" id="wf_add_approval_type" select2-selector="default">
                    <option value="1_level" selected>1-Level Approval (Standard)</option>
                    <option value="2_level">2-Level Approval (Dual Level)</option>
                </x-ui.odoo-form-ui>
                <x-ui.odoo-form-ui type="select" label="Level 1 Approver Role" name="first_approver" id="wf_add_first_approver" select2-selector="default">
                    <option value="reporting_manager" selected>Reporting Manager (Direct Supervisor)</option>
                    <option value="department_head">Department Head (HOD)</option>
                    <option value="hr_admin">HR Admin / System Admin</option>
                    <option value="finance_manager">Finance / Accounts Manager</option>
                </x-ui.odoo-form-ui>
                <x-ui.odoo-form-ui type="select" label="Level 2 Approver Role" name="second_approver" id="wf_add_second_approver" select2-selector="default">
                    <option value="finance_manager" selected>Finance / Accounts Manager</option>
                    <option value="hr_admin">HR Admin / System Admin</option>
                    <option value="department_head">Department Head (HOD)</option>
                    <option value="reporting_manager">Reporting Manager (Direct Supervisor)</option>
                </x-ui.odoo-form-ui>
                <x-ui.odoo-form-ui type="input" label="Threshold Amount for 2-Level (₹ / $)" name="amount_threshold_for_2_level" id="wf_add_threshold" placeholder="e.g. 5000 (Triggers 2-level if claim > threshold)" />
            </div>
        </div>

        <x-ui.odoo-form-ui type="select" label="Set as Default Workflow" name="is_default" id="wf_add_is_default" select2-selector="default">
            <option value="0" selected>No (Scope-specific Workflow)</option>
            <option value="1">Yes (System-wide Default Workflow)</option>
        </x-ui.odoo-form-ui>

        <x-ui.odoo-form-ui type="select" label="Status" name="status" id="wf_add_status" select2-selector="default">
            <option value="1" selected>Active</option>
            <option value="0">Inactive</option>
        </x-ui.odoo-form-ui>
    </div>
</x-ui.modal>

{{-- Modal 6: Edit Approval Workflow --}}
<x-ui.modal id="editWorkflowModal" title='<i class="feather-shield me-2 text-primary"></i>Edit Approval Workflow'
    centered formAction="#" formMethod="PUT" submitText="Update Workflow" closeText="Cancel">
    <div class="d-flex flex-column gap-3">
        <x-ui.odoo-form-ui type="input" label="Workflow Name" name="name" id="wf_edit_name" :required="true" />
        <x-ui.odoo-form-ui type="textarea" label="Description" name="description" id="wf_edit_desc" rows="2" />
        
        <x-ui.odoo-form-ui type="select" label="Company Scope" name="company_id" id="wf_edit_company" select2-selector="default">
            <option value="">All Companies (Global)</option>
            @foreach($companies as $c)
                <option value="{{ $c->id }}">{{ $c->company_name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>

        <x-ui.odoo-form-ui type="select" label="Assign to Department" name="department_id" id="wf_edit_department" select2-selector="default">
            <option value="">All Departments</option>
            @foreach($departments as $dept)
                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>

        <x-ui.odoo-form-ui type="select" label="Assign to Designation" name="designation_id" id="wf_edit_designation" select2-selector="default">
            <option value="">All Designations</option>
            @foreach($designations as $d)
                <option value="{{ $d->id }}">{{ $d->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>

        <div class="p-3 bg-light rounded border my-1">
            <h6 class="fw-bold text-dark fs-12 mb-2"><i class="feather-layers text-primary me-1"></i> Approval Workflow Configuration</h6>
            <div class="d-flex flex-column gap-2">
                <x-ui.odoo-form-ui type="select" label="Workflow Mode" name="approval_type" id="wf_edit_approval_type" select2-selector="default">
                    <option value="1_level">1-Level Approval (Standard)</option>
                    <option value="2_level">2-Level Approval (Dual Level)</option>
                </x-ui.odoo-form-ui>
                <x-ui.odoo-form-ui type="select" label="Level 1 Approver Role" name="first_approver" id="wf_edit_first_approver" select2-selector="default">
                    <option value="reporting_manager">Reporting Manager (Direct Supervisor)</option>
                    <option value="department_head">Department Head (HOD)</option>
                    <option value="hr_admin">HR Admin / System Admin</option>
                    <option value="finance_manager">Finance / Accounts Manager</option>
                </x-ui.odoo-form-ui>
                <x-ui.odoo-form-ui type="select" label="Level 2 Approver Role" name="second_approver" id="wf_edit_second_approver" select2-selector="default">
                    <option value="finance_manager">Finance / Accounts Manager</option>
                    <option value="hr_admin">HR Admin / System Admin</option>
                    <option value="department_head">Department Head (HOD)</option>
                    <option value="reporting_manager">Reporting Manager (Direct Supervisor)</option>
                </x-ui.odoo-form-ui>
                <x-ui.odoo-form-ui type="input" label="Threshold Amount for 2-Level (₹ / $)" name="amount_threshold_for_2_level" id="wf_edit_threshold" placeholder="e.g. 5000" />
            </div>
        </div>

        <x-ui.odoo-form-ui type="select" label="Set as Default Workflow" name="is_default" id="wf_edit_is_default" select2-selector="default">
            <option value="0">No (Scope-specific Workflow)</option>
            <option value="1">Yes (System-wide Default Workflow)</option>
        </x-ui.odoo-form-ui>

        <x-ui.odoo-form-ui type="select" label="Status" name="status" id="wf_edit_status" select2-selector="default">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </x-ui.odoo-form-ui>
    </div>
</x-ui.modal>

@push('scripts')
<script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
<script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
<script>
window.orgData = {
    businessUnits: @json($businessUnits),
    branches: @json($branches),
    departments: @json($departments),
    designations: @json($designations)
};

document.addEventListener('DOMContentLoaded', function () {
    // Re-initialize select2 on load
    if (window.$ && $.fn.select2) {
        $('.odoo-select2').select2({ theme: 'bootstrap-5', width: '100%' });
    }

    var searchTimeout;
    var activeRequest = null;

    function refreshPanel(url) {
        if (activeRequest) {
            activeRequest.abort();
        }
        var controller = new AbortController();
        activeRequest = controller;

        var panel = document.querySelector('.erp-single-panel');
        if (panel) panel.style.opacity = '0.5';

        fetch(url.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            signal: controller.signal
        })
        .then(function(response) {
            if (!response.ok) throw new Error('Error reloading page.');
            return response.text();
        })
        .then(function(html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var newPanel = doc.querySelector('.erp-single-panel');
            var oldPanel = document.querySelector('.erp-single-panel');
            if (newPanel && oldPanel) {
                oldPanel.innerHTML = newPanel.innerHTML;
            }
            
            // Re-initialize select2 after panel content replacement
            if (window.$ && $.fn.select2) {
                $('.odoo-select2').select2({ theme: 'bootstrap-5', width: '100%' });
            }
            
            history.pushState(null, '', url.toString());
        })
        .catch(function(err) {
            if (err.name !== 'AbortError') {
                window.location.href = url.toString();
            }
        })
        .finally(function() {
            if (panel) panel.style.opacity = '1';
        });
    }

    // 1. Debounced Real-time Search
    $(document).on('input keyup search', '#policySearchForm input[name="search"], #categorySearchForm input[name="cat_search"]', function() {
        var form = this.closest('form');
        var url = new URL(form.action || window.location.href);
        var formData = new FormData(form);
        for (var [key, val] of formData.entries()) {
            url.searchParams.set(key, val);
        }

        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            refreshPanel(url);
        }, 300);
    });

    // 2. Intercept Sort Links Click
    $(document).on('click', '.dropdown-item[href*="sort="], .dropdown-item[href*="cat_sort="]', function(e) {
        e.preventDefault();
        var href = $(this).attr('href');
        if (href) {
            var url = new URL(href, window.location.origin);
            refreshPanel(url);
        }
    });

    // 3. Intercept Filters Submission
    $(document).on('submit', '#policyFilterForm, #categoryFilterForm', function(e) {
        e.preventDefault();
        var form = this;
        var url = new URL(form.action || window.location.href);
        var formData = new FormData(form);
        for (var [key, val] of formData.entries()) {
            url.searchParams.set(key, val);
        }
        refreshPanel(url);
        $(this).closest('.dropdown').find('[data-bs-toggle="dropdown"]').dropdown('toggle');
    });

    // Dependent Organizational Cascading Dropdown Filtering
    window.updateOrgDropdowns = function(prefix) {
        var companyId = $('#' + prefix + 'company').val();
        var buId = $('#' + prefix + 'business_unit').val();
        var branchId = $('#' + prefix + 'branch').val();
        var deptId = $('#' + prefix + 'department').val();

        // 1. Business Units Scope
        var filteredBUs = window.orgData.businessUnits;
        if (companyId) {
            filteredBUs = window.orgData.businessUnits.filter(function(bu) {
                return bu.company_id == companyId;
            });
        }
        var buGroup = $('#' + prefix + 'business_unit').closest('.odoo-form-group');
        if (companyId && filteredBUs.length === 0) {
            buGroup.addClass('d-none');
            $('#' + prefix + 'business_unit').val('').trigger('change.select2');
            buId = '';
        } else {
            buGroup.removeClass('d-none');
            var $buSelect = $('#' + prefix + 'business_unit');
            var currentBuVal = $buSelect.val();
            $buSelect.empty().append('<option value="">All Business Units</option>');
            filteredBUs.forEach(function(bu) {
                $buSelect.append('<option value="' + bu.id + '">' + bu.name + '</option>');
            });
            if (filteredBUs.some(function(bu) { return bu.id == currentBuVal; })) {
                $buSelect.val(currentBuVal);
            } else {
                $buSelect.val('');
                buId = '';
            }
            $buSelect.trigger('change.select2');
        }

        // 2. Branch Scope
        var filteredBranches = window.orgData.branches;
        if (companyId) {
            filteredBranches = filteredBranches.filter(function(b) {
                return b.company_id == companyId;
            });
        }
        if (buId) {
            filteredBranches = filteredBranches.filter(function(b) {
                return b.business_unit_id == buId;
            });
        }
        var branchGroup = $('#' + prefix + 'branch').closest('.odoo-form-group');
        if ((companyId || buId) && filteredBranches.length === 0) {
            branchGroup.addClass('d-none');
            $('#' + prefix + 'branch').val('').trigger('change.select2');
            branchId = '';
        } else {
            branchGroup.removeClass('d-none');
            var $branchSelect = $('#' + prefix + 'branch');
            var currentBranchVal = $branchSelect.val();
            $branchSelect.empty().append('<option value="">All Branches</option>');
            filteredBranches.forEach(function(b) {
                $branchSelect.append('<option value="' + b.id + '">' + b.name + '</option>');
            });
            if (filteredBranches.some(function(b) { return b.id == currentBranchVal; })) {
                $branchSelect.val(currentBranchVal);
            } else {
                $branchSelect.val('');
                branchId = '';
            }
            $branchSelect.trigger('change.select2');
        }

        // 3. Department Assignment
        var filteredDepts = window.orgData.departments;
        if (companyId) {
            filteredDepts = filteredDepts.filter(function(d) {
                return d.company_id == companyId;
            });
        }
        if (buId) {
            filteredDepts = filteredDepts.filter(function(d) {
                return d.business_unit_id == buId;
            });
        }
        if (branchId) {
            filteredDepts = filteredDepts.filter(function(d) {
                return d.branch_id == branchId;
            });
        }
        var $deptSelect = $('#' + prefix + 'department');
        var currentDeptVal = $deptSelect.val();
        $deptSelect.empty().append('<option value="">All Departments</option>');
        filteredDepts.forEach(function(d) {
            $deptSelect.append('<option value="' + d.id + '">' + d.name + '</option>');
        });
        if (filteredDepts.some(function(d) { return d.id == currentDeptVal; })) {
            $deptSelect.val(currentDeptVal);
        } else {
            $deptSelect.val('');
            deptId = '';
        }
        $deptSelect.trigger('change.select2');

        // 4. Designation Assignment
        var allowedDeptIds = filteredDepts.map(function(d) { return d.id; });
        var filteredDesignations = window.orgData.designations;
        if (deptId) {
            filteredDesignations = window.orgData.designations.filter(function(ds) {
                return ds.department_id == deptId;
            });
        } else if (allowedDeptIds.length > 0) {
            filteredDesignations = window.orgData.designations.filter(function(ds) {
                return allowedDeptIds.indexOf(parseInt(ds.department_id)) > -1;
            });
        }
        var $desSelect = $('#' + prefix + 'designation');
        var currentDesVal = $desSelect.val();
        $desSelect.empty().append('<option value="">All Designations</option>');
        filteredDesignations.forEach(function(ds) {
            $desSelect.append('<option value="' + ds.id + '">' + ds.name + '</option>');
        });
        if (filteredDesignations.some(function(ds) { return ds.id == currentDesVal; })) {
            $desSelect.val(currentDesVal);
        } else {
            $desSelect.val('');
        }
        $desSelect.trigger('change.select2');
    };

    // Bind change listeners for Add Policy form
    $('#add_company').on('change', function() { window.updateOrgDropdowns('add_'); });
    $('#add_business_unit').on('change', function() { window.updateOrgDropdowns('add_'); });
    $('#add_branch').on('change', function() { window.updateOrgDropdowns('add_'); });
    $('#add_department').on('change', function() { window.updateOrgDropdowns('add_'); });

    // Bind change listeners for Edit Policy form
    $('#edit_company').on('change', function() { window.updateOrgDropdowns('edit_'); });
    $('#edit_business_unit').on('change', function() { window.updateOrgDropdowns('edit_'); });
    $('#edit_branch').on('change', function() { window.updateOrgDropdowns('edit_'); });
    $('#edit_department').on('change', function() { window.updateOrgDropdowns('edit_'); });

    // Initial setup on load to check optional scopes visibility
    window.updateOrgDropdowns('add_');
    window.updateOrgDropdowns('edit_');

    // 4. Populate Policy Edit Modal (using delegation)
    $(document).on('click', '.btn-edit-policy', function() {
        var id           = this.getAttribute('data-id');
        var name         = this.getAttribute('data-name');
        var description  = this.getAttribute('data-description');
        var designId     = this.getAttribute('data-designation-id');
        var deptId       = this.getAttribute('data-department-id');
        var companyId    = this.getAttribute('data-company-id');
        var buId         = this.getAttribute('data-business-unit-id');
        var branchId     = this.getAttribute('data-branch-id');
        var status       = this.getAttribute('data-status');
        var approvalType = this.getAttribute('data-approval-type');
        var firstAppr    = this.getAttribute('data-first-approver');
        var secondAppr   = this.getAttribute('data-second-approver');
        var threshold    = this.getAttribute('data-amount-threshold');

        var form = document.querySelector('#editPolicyModal form');
        if (form) form.action = '{{ url('hrms/expense-policy') }}/' + id;

        document.getElementById('edit_name').value = name || '';
        document.getElementById('edit_desc').value = description || '';

        $('#edit_approval_type').val(approvalType || '1_level').trigger('change.select2');
        $('#edit_first_approver').val(firstAppr || 'reporting_manager').trigger('change.select2');
        $('#edit_second_approver').val(secondAppr || 'finance_manager').trigger('change.select2');
        var thresholdInput = document.getElementById('edit_amount_threshold');
        if (thresholdInput) thresholdInput.value = threshold || '';

        // Populate cascading fields sequentially to ensure correct options filtering
        $('#edit_company').val(companyId || '').trigger('change.select2');
        window.updateOrgDropdowns('edit_');

        $('#edit_business_unit').val(buId || '').trigger('change.select2');
        window.updateOrgDropdowns('edit_');

        $('#edit_branch').val(branchId || '').trigger('change.select2');
        window.updateOrgDropdowns('edit_');

        $('#edit_department').val(deptId || '').trigger('change.select2');
        window.updateOrgDropdowns('edit_');

        $('#edit_designation').val(designId || '').trigger('change.select2');

        var statusSelect = document.getElementById('edit_status');
        if (statusSelect) {
            statusSelect.value = parseInt(status) === 1 ? '1' : '0';
            $(statusSelect).trigger('change.select2');
        }
    });

    // 5. Populate Category Edit Modal (using delegation)
    $(document).on('click', '.btn-edit-category', function() {
        var id          = this.getAttribute('data-id');
        var code        = this.getAttribute('data-code');
        var name        = this.getAttribute('data-name');
        var description = this.getAttribute('data-description');
        var status      = this.getAttribute('data-status');

        var form = document.querySelector('#editCategoryModal form');
        if (form) form.action = '{{ url('hrms/expense-categories') }}/' + id;

        document.getElementById('edit_cat_code').value = code || '';
        document.getElementById('edit_cat_name').value = name || '';
        document.getElementById('edit_cat_desc').value = description || '';

        var statusSelect = document.getElementById('edit_cat_status');
        if (statusSelect) {
            statusSelect.value = parseInt(status) === 1 ? '1' : '0';
            if (window.$ && $(statusSelect).hasClass('select2-hidden-accessible')) $(statusSelect).trigger('change.select2');
        }
    });

    // 6. Populate Workflow Edit Modal (using delegation)
    $(document).on('click', '.btn-edit-workflow', function() {
        var btn = $(this);
        var id = btn.data('id');
        var formAction = "{{ url('hrms/expense-policy/workflows') }}/" + id;
        
        $('#editWorkflowModal form').attr('action', formAction);
        $('#wf_edit_name').val(btn.data('name'));
        $('#wf_edit_desc').val(btn.data('description'));
        $('#wf_edit_designation').val(btn.data('designation-id')).trigger('change.select2');
        $('#wf_edit_department').val(btn.data('department-id')).trigger('change.select2');
        $('#wf_edit_company').val(btn.data('company-id')).trigger('change.select2');
        $('#wf_edit_approval_type').val(btn.data('approval-type') || '1_level').trigger('change.select2');
        $('#wf_edit_first_approver').val(btn.data('first-approver') || 'reporting_manager').trigger('change.select2');
        $('#wf_edit_second_approver').val(btn.data('second-approver') || 'finance_manager').trigger('change.select2');
        $('#wf_edit_threshold').val(btn.data('amount-threshold') || '');
        $('#wf_edit_is_default').val(btn.data('is-default') == 1 ? '1' : '0').trigger('change.select2');
        $('#wf_edit_status').val(btn.data('status') == 1 ? '1' : '0').trigger('change.select2');
    });
});
</script>
@endpush
