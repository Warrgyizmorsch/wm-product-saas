@extends('layouts.duralux')

@section('title', 'Expense Policies & Categories | SaaS ERP')
@section('page-title', 'Expense Policy Master')
@section('breadcrumb', 'HRMS / Masters / Expense Policies')

@push('styles')
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
            <a class="nav-link {{ $activeTab === 'policies' ? 'active' : 'text-muted' }}" 
               href="{{ route('hrms.expense-policy.index', ['tab' => 'policies']) }}">
                <i class="feather-file-text me-1"></i> Expense Policies
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'categories' ? 'active' : 'text-muted' }}" 
               href="{{ route('hrms.expense-policy.index', ['tab' => 'categories']) }}">
                <i class="feather-tag me-1"></i> Expense Categories
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
        <div class="d-flex align-items-center gap-2 flex-wrap mb-4">
            {{-- Search --}}
            <form method="GET" action="{{ route('hrms.expense-policy.index') }}" id="policySearchForm" class="d-flex align-items-center bg-light border rounded px-3 py-1">
                <input type="hidden" name="tab"    value="policies">
                <input type="hidden" name="sort"   value="{{ $filters['sort'] }}">
                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                <i class="feather-search text-muted me-2" style="font-size:14px;"></i>
                <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search policy name..." value="{{ $filters['search'] }}" style="box-shadow:none; height:32px; min-width:200px;">
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
                        <x-ui.odoo-form-ui type="select" label="Status" name="status" id="policy_filter_status" select2-selector="default">
                            <option value="">All Statuses</option>
                            <option value="1" @selected($filters['status'] === '1')>Active</option>
                            <option value="0" @selected($filters['status'] === '0')>Inactive</option>
                        </x-ui.odoo-form-ui>
                    </div>
                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="{{ route('hrms.expense-policy.index', ['tab' => 'policies']) }}" class="btn btn-sm btn-light text-uppercase fw-bold py-2 px-3" style="border-radius:6px; font-size:11px; background-color:#f1f5f9; border:1px solid #e2e8f0; color:#475569;">Reset</a>
                        <button type="submit" class="btn btn-sm btn-primary text-uppercase fw-bold py-2 px-3" style="border-radius:6px; font-size:11px;">Apply</button>
                    </div>
                </form>
            </x-ui.filter>

            @if($filters['search'] || $filters['status'] !== '')
                <div class="d-flex align-items-center gap-2 ms-1">
                    @if($filters['search'])
                        <span class="badge bg-soft-primary text-primary px-2 py-1 fs-11 rounded-pill"><i class="feather-search me-1"></i>{{ $filters['search'] }}</span>
                    @endif
                    @if($filters['status'] !== '')
                        <span class="badge bg-soft-secondary text-secondary px-2 py-1 fs-11 rounded-pill">Status: {{ $filters['status'] === '1' ? 'Active' : 'Inactive' }}</span>
                    @endif
                    <a href="{{ route('hrms.expense-policy.index', ['tab' => 'policies']) }}" class="text-danger fs-12"><i class="feather-x"></i> Clear</a>
                </div>
            @endif
        </div>

        {{-- Policy Cards grid --}}
        @if($policies->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="feather-file-text fs-32 d-block mb-3 text-secondary"></i>
                <p class="mb-1">No expense policies found.</p>
                <p class="fs-12">Click <strong>New Policy</strong> to create one.</p>
            </div>
        @else
            <div class="row g-3">
                @foreach($policies as $policy)
                    <div class="col-md-6 col-xl-4">
                        <div class="border rounded-3 p-3 h-100 d-flex flex-column" style="transition: box-shadow 0.2s;" onmouseenter="this.style.boxShadow='0 4px 16px rgba(0,0,0,0.08)'" onmouseleave="this.style.boxShadow='none'">
                            <div class="d-flex align-items-start justify-content-between mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-2 bg-soft-primary d-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                                        <i class="feather-file-text text-primary fs-16"></i>
                                    </div>
                                    <div>
                                        <p class="fw-bold text-dark mb-0 fs-13">{{ $policy->name }}</p>
                                        <span class="fs-11 text-muted">
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
                                <x-ui.badge variant="{{ $policy->status ? 'success' : 'secondary' }}" soft class="px-2 py-1 fs-10 rounded-pill flex-shrink-0">
                                    {{ $policy->status ? 'Active' : 'Inactive' }}
                                </x-ui.badge>
                            </div>

                            @if($policy->description)
                                <p class="text-muted fs-12 mb-2">{{ Str::limit($policy->description, 80) }}</p>
                            @endif

                            <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                                <span class="badge bg-soft-info text-info px-2 py-1 fs-11 rounded-pill">
                                    <i class="feather-list me-1"></i>{{ $policy->rules->count() }} Limit Rules
                                </span>
                                @if($policy->company)
                                    <span class="badge bg-light text-muted border px-2 py-1 fs-10">
                                        <i class="feather-home me-1"></i>{{ $policy->company->company_name }}
                                    </span>
                                @endif
                            </div>

                            @if($policy->rules->isNotEmpty())
                                <div class="d-flex flex-wrap gap-1 mb-3">
                                    @foreach($policy->rules->take(4) as $rule)
                                        <span class="badge bg-light text-dark border fs-10 px-2 py-1">{{ $rule->category->name }}</span>
                                    @endforeach
                                    @if($policy->rules->count() > 4)
                                        <span class="badge bg-light text-muted border fs-10 px-2 py-1">+{{ $policy->rules->count() - 4 }} more</span>
                                    @endif
                                </div>
                            @endif

                            <div class="d-flex gap-2 mt-auto">
                                <a href="{{ route('hrms.expense-policy.rules', $policy) }}" class="btn btn-sm btn-primary flex-fill fw-bold fs-12 text-uppercase">
                                    <i class="feather-sliders me-1"></i> Manage Limits
                                </a>
                                <x-ui.button type="button" variant="light" size="sm" class="border btn-edit-policy"
                                    icon="feather-edit-3"
                                    data-id="{{ $policy->id }}"
                                    data-name="{{ $policy->name }}"
                                    data-description="{{ $policy->description }}"
                                    data-designation-id="{{ $policy->designation_id }}"
                                    data-department-id="{{ $policy->department_id }}"
                                    data-company-id="{{ $policy->company_id }}"
                                    data-business-unit-id="{{ $policy->business_unit_id }}"
                                    data-branch-id="{{ $policy->branch_id }}"
                                    data-status="{{ $policy->status ? 1 : 0 }}"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editPolicyModal"
                                />
                                <form method="POST" action="{{ route('hrms.expense-policy.destroy', $policy) }}" onsubmit="return confirm('Delete this policy and all its category limits?');" class="d-inline">
                                    @csrf @method('DELETE')
                                    <x-ui.button type="submit" variant="light" size="sm" class="border text-danger" icon="feather-trash-2" />
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

    {{-- ══════════════════════════════════════════════════════════════════════
         TAB 2: EXPENSE CATEGORIES
         ══════════════════════════════════════════════════════════════════════ --}}
    @else
        {{-- Toolbar --}}
        <div class="d-flex align-items-center gap-2 flex-wrap mb-4">
            {{-- Search --}}
            <form method="GET" action="{{ route('hrms.expense-policy.index') }}" id="categorySearchForm" class="d-flex align-items-center bg-light border rounded px-3 py-1">
                <input type="hidden" name="tab"        value="categories">
                <input type="hidden" name="cat_sort"   value="{{ $catFilters['sort'] }}">
                <input type="hidden" name="cat_status" value="{{ $catFilters['status'] }}">
                <i class="feather-search text-muted me-2" style="font-size:14px;"></i>
                <input type="text" name="cat_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search by name, code, description..." value="{{ $catFilters['search'] }}" style="box-shadow:none; height:32px; min-width:220px;">
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
                        <x-ui.odoo-form-ui type="select" label="Status" name="cat_status" id="cat_filter_status" select2-selector="default">
                            <option value="">All Statuses</option>
                            <option value="1" @selected($catFilters['status'] === '1')>Active</option>
                            <option value="0" @selected($catFilters['status'] === '0')>Inactive</option>
                        </x-ui.odoo-form-ui>
                    </div>
                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="{{ route('hrms.expense-policy.index', ['tab' => 'categories']) }}" class="btn btn-sm btn-light text-uppercase fw-bold py-2 px-3" style="border-radius:6px; font-size:11px; background-color:#f1f5f9; border:1px solid #e2e8f0; color:#475569;">Reset</a>
                        <button type="submit" class="btn btn-sm btn-primary text-uppercase fw-bold py-2 px-3" style="border-radius:6px; font-size:11px;">Apply</button>
                    </div>
                </form>
            </x-ui.filter>

            @if($catFilters['search'] || $catFilters['status'] !== '')
                <div class="d-flex align-items-center gap-2 ms-1">
                    @if($catFilters['search'])
                        <span class="badge bg-soft-primary text-primary px-2 py-1 fs-11 rounded-pill"><i class="feather-search me-1"></i>{{ $catFilters['search'] }}</span>
                    @endif
                    @if($catFilters['status'] !== '')
                        <span class="badge bg-soft-secondary text-secondary px-2 py-1 fs-11 rounded-pill">Status: {{ $catFilters['status'] === '1' ? 'Active' : 'Inactive' }}</span>
                    @endif
                    <a href="{{ route('hrms.expense-policy.index', ['tab' => 'categories']) }}" class="text-danger fs-12"><i class="feather-x"></i> Clear</a>
                </div>
            @endif
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
                <tbody>
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
                                <div class="d-flex justify-content-end gap-2">
                                    <x-ui.button type="button" variant="light" size="sm" class="border btn-edit-category" icon="feather-edit-3"
                                        data-id="{{ $cat->id }}"
                                        data-name="{{ $cat->name }}"
                                        data-code="{{ $cat->code }}"
                                        data-description="{{ $cat->description }}"
                                        data-status="{{ $cat->status ? 1 : 0 }}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editCategoryModal"
                                    />
                                    <form method="POST" action="{{ route('hrms.expense-categories.destroy', $cat) }}" onsubmit="return confirm('Delete this category?');" class="d-inline">
                                        @csrf @method('DELETE')
                                        <x-ui.button type="submit" variant="light" size="sm" class="border text-danger" icon="feather-trash-2" />
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
        <x-ui.odoo-form-ui type="select" label="Assign to Designation" name="designation_id" id="add_designation" select2-selector="default">
            <option value="">All Designations</option>
            @foreach($designations as $d)
                <option value="{{ $d->id }}">{{ $d->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
        <x-ui.odoo-form-ui type="select" label="Assign to Department" name="department_id" id="add_department" select2-selector="default">
            <option value="">All Departments</option>
            @foreach($departments as $d)
                <option value="{{ $d->id }}">{{ $d->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
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
        <x-ui.odoo-form-ui type="select" label="Status" name="status" id="add_status" select2-selector="default">
            <option value="1" selected>Active</option>
            <option value="0">Inactive</option>
        </x-ui.odoo-form-ui>
    </div>
</x-ui.modal>

{{-- Modal 2: Edit Policy --}}
<x-ui.modal id="editPolicyModal" title='<i class="feather-edit-3 me-2 text-primary"></i>Edit Expense Policy'
    centered formAction="" formMethod="PUT" submitText="Update Policy" closeText="Cancel">
    <div class="d-flex flex-column gap-3">
        <x-ui.odoo-form-ui type="input" label="Policy Name" name="name" id="edit_name" :required="true" />
        <x-ui.odoo-form-ui type="textarea" label="Description" name="description" id="edit_desc" rows="2" />
        <x-ui.odoo-form-ui type="select" label="Assign to Designation" name="designation_id" id="edit_designation" select2-selector="default">
            <option value="">All Designations</option>
            @foreach($designations as $d)
                <option value="{{ $d->id }}">{{ $d->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
        <x-ui.odoo-form-ui type="select" label="Assign to Department" name="department_id" id="edit_department" select2-selector="default">
            <option value="">All Departments</option>
            @foreach($departments as $d)
                <option value="{{ $d->id }}">{{ $d->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
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
    centered formAction="" formMethod="PUT" submitText="Update Category" closeText="Cancel">
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Submit search forms on Enter
    var pSearch = document.querySelector('#policySearchForm input[name="search"]');
    if (pSearch) pSearch.addEventListener('keydown', function(e) { if(e.key==='Enter') this.closest('form').submit(); });

    var cSearch = document.querySelector('#categorySearchForm input[name="cat_search"]');
    if (cSearch) cSearch.addEventListener('keydown', function(e) { if(e.key==='Enter') this.closest('form').submit(); });

    // 2. Populate Policy Edit Modal
    document.querySelectorAll('.btn-edit-policy').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id           = this.getAttribute('data-id');
            var name         = this.getAttribute('data-name');
            var description  = this.getAttribute('data-description');
            var designId     = this.getAttribute('data-designation-id');
            var deptId       = this.getAttribute('data-department-id');
            var companyId    = this.getAttribute('data-company-id');
            var buId         = this.getAttribute('data-business-unit-id');
            var branchId     = this.getAttribute('data-branch-id');
            var status       = this.getAttribute('data-status');

            var form = document.querySelector('#editPolicyModal form');
            if (form) form.action = '{{ url('hrms/expense-policy') }}/' + id;

            document.getElementById('edit_name').value = name || '';
            document.getElementById('edit_desc').value = description || '';

            function setSelect(id, val) {
                var el = document.getElementById(id);
                if (!el) return;
                el.value = val || '';
                if (window.$ && $(el).hasClass('select2-hidden-accessible')) $(el).trigger('change.select2');
            }
            setSelect('edit_designation', designId);
            setSelect('edit_department', deptId);
            setSelect('edit_company', companyId);
            setSelect('edit_business_unit', buId);
            setSelect('edit_branch', branchId);
            setSelect('edit_status', parseInt(status) === 1 ? '1' : '0');
        });
    });

    // 3. Populate Category Edit Modal
    document.querySelectorAll('.btn-edit-category').forEach(function(btn) {
        btn.addEventListener('click', function() {
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
    });
});
</script>
@endpush
