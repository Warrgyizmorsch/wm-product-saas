@extends('layouts.duralux')

@section('title', 'Performance Improvement Plan (PIP) | HRMS')
@section('page-title', 'Performance Improvement Plan (PIP)')
@section('breadcrumb', 'HRMS / Performance / PIP')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="primary" icon="feather-plus-circle" data-bs-toggle="modal" data-bs-target="#createPipModal" class="fw-bold text-uppercase">
            Initiate New PIP
        </x-ui.button>
    </div>
@endsection

@push('styles')
<style>
    /* Underlined Horizontal Tabs (Matching Standard Platform UI) */
    #pipTabs .nav-link {
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
    #pipTabs .nav-link:hover {
        color: var(--bs-primary);
    }
    #pipTabs .nav-link.active {
        color: var(--bs-primary) !important;
        border-bottom: 2px solid var(--bs-primary) !important;
        font-weight: 600;
    }

    .avatar-initials {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background-color: rgba(var(--bs-primary-rgb), 0.1);
        color: var(--bs-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 13px;
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

        <!-- STATS SUMMARY ROW -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="card border-0 shadow-sm rounded-3 bg-white h-100 position-relative overflow-hidden" style="border-top: 3px solid var(--bs-primary) !important;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted fw-bold text-uppercase fs-11" style="letter-spacing: 0.04em;">Total Active PIPs</small>
                                <h3 class="fw-extrabold text-dark mb-0 mt-1.5 fs-22">{{ $totalActive }}</h3>
                            </div>
                            <div class="p-2.5 rounded-3 bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="feather-trending-up fs-18"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card border-0 shadow-sm rounded-3 bg-white h-100 position-relative overflow-hidden" style="border-top: 3px solid #10b981 !important;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted fw-bold text-uppercase fs-11" style="letter-spacing: 0.04em;">On Track</small>
                                <h3 class="fw-extrabold text-success mb-0 mt-1.5 fs-22">{{ $onTrackCount }}</h3>
                            </div>
                            <div class="p-2.5 rounded-3 bg-success-subtle text-success d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="feather-check-circle fs-18"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card border-0 shadow-sm rounded-3 bg-white h-100 position-relative overflow-hidden" style="border-top: 3px solid #f59e0b !important;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted fw-bold text-uppercase fs-11" style="letter-spacing: 0.04em;">At Risk / Review</small>
                                <h3 class="fw-extrabold text-warning mb-0 mt-1.5 fs-22">{{ $atRiskCount }}</h3>
                            </div>
                            <div class="p-2.5 rounded-3 bg-warning-subtle text-warning d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="feather-alert-triangle fs-18"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card border-0 shadow-sm rounded-3 bg-white h-100 position-relative overflow-hidden" style="border-top: 3px solid #06b6d4 !important;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted fw-bold text-uppercase fs-11" style="letter-spacing: 0.04em;">Completed (Success)</small>
                                <h3 class="fw-extrabold text-info mb-0 mt-1.5 fs-22">{{ $completedCount }}</h3>
                            </div>
                            <div class="p-2.5 rounded-3 bg-info-subtle text-info d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="feather-award fs-18"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs & Right Toolbar (Search, Sort, Filter at Right Corner) -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 border-bottom pb-2">
            <!-- Left Tabs -->
            <ul class="nav gap-1" id="pipTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'plans' ? 'active' : '' }}" href="{{ route('hrms.pip.index', ['active_tab' => 'plans']) }}">
                        <i class="feather-list"></i>
                        <span>Active & Past PIP Plans</span>
                        <x-ui.badge soft variant="primary" class="ms-1">{{ $plans->total() }}</x-ui.badge>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'categories' ? 'active' : '' }}" href="{{ route('hrms.pip.index', ['active_tab' => 'categories']) }}">
                        <i class="feather-tag"></i>
                        <span>PIP Categories Master</span>
                        <x-ui.badge soft variant="secondary" class="ms-1">{{ $categories->count() }}</x-ui.badge>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'templates' ? 'active' : '' }}" href="{{ route('hrms.pip.index', ['active_tab' => 'templates']) }}">
                        <i class="feather-file-text"></i>
                        <span>Policy Templates Master</span>
                        <x-ui.badge soft variant="info" class="ms-1">{{ $policyTemplates->count() }}</x-ui.badge>
                    </a>
                </li>
            </ul>

            <!-- Right Toolbar: Search, Sort, Filter aligned at Right Corner (Standard UI) -->
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                @if($activeTab === 'plans')
                    <!-- Search Input Form with Instant Auto-Submit (no Enter required) -->
                    <form method="GET" action="{{ route('hrms.pip.index') }}" id="pipSearchForm" class="d-flex align-items-center bg-light border rounded px-3 py-1 m-0" style="min-width: 250px; height: 36px !important; box-sizing: border-box !important;">
                        <input type="hidden" name="active_tab" value="plans">
                        <input type="hidden" name="status" value="{{ request('status') }}">
                        <input type="hidden" name="department_id" value="{{ request('department_id') }}">
                        <input type="hidden" name="category_id" value="{{ request('category_id') }}">
                        <input type="hidden" name="sort" value="{{ request('sort', 'newest') }}">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" name="search" id="pipSearchInput" class="w-100 border-0 bg-transparent p-0 fs-13" placeholder="Search PIP #, name, ID..." value="{{ request('search') }}" autocomplete="off" style="box-shadow: none; height: 100%; outline: none;">
                    </form>

                    <!-- Sort Dropdown -->
                    <x-ui.sort-dropdown label="SORT">
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort', 'newest') === 'newest' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'newest']) }}">
                            <span>Newest First</span>
                            @if(request('sort', 'newest') === 'newest') <i class="feather-check ms-3"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort') === 'oldest' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'oldest']) }}">
                            <span>Oldest First</span>
                            @if(request('sort') === 'oldest') <i class="feather-check ms-3"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort') === 'pip_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'pip_asc']) }}">
                            <span>PIP # (Ascending)</span>
                            @if(request('sort') === 'pip_asc') <i class="feather-check ms-3"></i> @endif
                        </a>
                        <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ request('sort') === 'pip_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'pip_desc']) }}">
                            <span>PIP # (Descending)</span>
                            @if(request('sort') === 'pip_desc') <i class="feather-check ms-3"></i> @endif
                        </a>
                    </x-ui.sort-dropdown>

                    <!-- Filter Dropdown -->
                    <x-ui.filter label="FILTER">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter PIP Records</h6>
                        <form method="GET" action="{{ route('hrms.pip.index') }}">
                            <input type="hidden" name="active_tab" value="plans">
                            <input type="hidden" name="search" value="{{ request('search') }}">
                            <input type="hidden" name="sort" value="{{ request('sort') }}">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                                <select name="status" class="form-select fs-13" style="border-radius: 6px; border: 1px solid #cbd5e1;">
                                    <option value="">All Statuses</option>
                                    <option value="active" @selected(request('status') === 'active')>Active</option>
                                    <option value="under_review" @selected(request('status') === 'under_review')>Under Review</option>
                                    <option value="completed_success" @selected(request('status') === 'completed_success')>Completed (Success)</option>
                                    <option value="extended" @selected(request('status') === 'extended')>Extended</option>
                                    <option value="role_reassigned" @selected(request('status') === 'role_reassigned')>Role Reassigned</option>
                                    <option value="failed_terminated" @selected(request('status') === 'failed_terminated')>Failed / Terminated</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Department</label>
                                <select name="department_id" class="form-select fs-13" style="border-radius: 6px; border: 1px solid #cbd5e1;">
                                    <option value="">All Departments</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" @selected(request('department_id') == $dept->id)>{{ $dept->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Category</label>
                                <select name="category_id" class="form-select fs-13" style="border-radius: 6px; border: 1px solid #cbd5e1;">
                                    <option value="">All Categories</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                                <a href="{{ route('hrms.pip.index') }}" class="btn btn-sm btn-light text-uppercase fw-bold py-2 px-3" style="border-radius: 6px; font-size: 11px; background-color: #f1f5f9; border: 1px solid #e2e8f0; color: #475569;">Reset</a>
                                <button type="submit" class="btn btn-sm btn-primary text-uppercase fw-bold py-2 px-3 text-white" style="border-radius: 6px; font-size: 11px;">Apply</button>
                            </div>
                        </form>
                    </x-ui.filter>
                @elseif($activeTab === 'categories')
                    <x-ui.button variant="outline-primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createCategoryModal" class="fw-bold">
                        Add PIP Category
                    </x-ui.button>
                @elseif($activeTab === 'templates')
                    <x-ui.button variant="outline-primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createTemplateModal" class="fw-bold">
                        Add Policy Template
                    </x-ui.button>
                @endif
            </div>
        </div>

        <!-- TAB CONTENT SLOTS -->
        @if($activeTab === 'plans')

            <!-- PIP PLANS TABLE -->
            <div class="table-responsive border rounded">
                <table class="table table-hover align-middle mb-0 fs-13">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3 py-3 text-muted text-uppercase fs-11">PIP #</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Employee</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Reason / Category</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Timeline</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Check-in Freq</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Status</th>
                            <th class="text-end pe-3 py-3 text-muted text-uppercase fs-11">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($plans as $plan)
                            <tr>
                                <td class="ps-3 fw-bold text-primary">
                                    <a href="{{ route('hrms.pip.show', $plan->id) }}" class="text-decoration-none text-primary">
                                        {{ $plan->pip_number }}
                                    </a>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-initials">
                                            {{ strtoupper(substr($plan->employee->full_name ?? 'E', 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark">{{ $plan->employee->full_name ?? 'N/A' }}</div>
                                            <small class="text-muted fs-11">{{ $plan->employee->designation?->name ?? 'N/A' }} &bull; {{ $plan->employee->department?->name ?? 'N/A' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border px-2 py-1 fs-11 fw-normal">
                                        <i class="feather-tag me-1 text-primary"></i> {{ $plan->category?->name ?? 'General Performance' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fs-12 fw-semibold text-dark">
                                        {{ $plan->start_date ? $plan->start_date->format('M d, Y') : 'N/A' }} - {{ $plan->end_date ? $plan->end_date->format('M d, Y') : 'N/A' }}
                                    </div>
                                    <small class="text-muted fs-11">
                                        @if($plan->end_date && $plan->end_date->isFuture() && $plan->status === 'active')
                                            {{ now()->diffInDays($plan->end_date) }} days remaining
                                        @else
                                            Timeline Finished
                                        @endif
                                    </small>
                                </td>
                                <td>
                                    <span class="text-capitalize fw-medium text-secondary">
                                        <i class="feather-clock me-1 text-muted"></i> {{ $plan->checkin_frequency }}
                                    </span>
                                </td>
                                <td>
                                    @php
                                        $badgeVariant = match($plan->status) {
                                            'active' => 'success',
                                            'under_review' => 'warning',
                                            'completed_success' => 'info',
                                            'extended' => 'primary',
                                            'failed_terminated' => 'danger',
                                            default => 'secondary'
                                        };
                                    @endphp
                                    <x-ui.badge soft variant="{{ $badgeVariant }}" class="text-capitalize">
                                        {{ str_replace('_', ' ', $plan->status) }}
                                    </x-ui.badge>
                                </td>
                                <td class="text-end pe-3">
                                    <x-ui.action-dropdown>
                                        <a class="dropdown-item" href="{{ route('hrms.pip.show', $plan->id) }}">
                                            <i class="feather-eye me-2 text-primary"></i> Open Workspace
                                        </a>
                                        <a class="dropdown-item" href="{{ route('hrms.employees.show', ['employee' => $plan->employee_id, 'tab' => 'pip']) }}">
                                            <i class="feather-user me-2 text-muted"></i> View Employee Profile
                                        </a>
                                    </x-ui.action-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="feather-inbox fs-34 d-block mb-2 text-secondary opacity-50"></i>
                                    No Performance Improvement Plans found. Click <strong>Initiate New PIP</strong> to get started.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($plans->hasPages())
                <div class="mt-3">
                    {{ $plans->links() }}
                </div>
            @endif

        @elseif($activeTab === 'categories')
            <!-- TAB 2: PIP CATEGORIES MASTER -->
            <div class="table-responsive border rounded">
                <table class="table table-hover align-middle mb-0 fs-13">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3 py-3 text-muted text-uppercase fs-11">Category Name</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Description</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Status</th>
                            <th class="text-end pe-3 py-3 text-muted text-uppercase fs-11">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $cat)
                            <tr>
                                <td class="ps-3 fw-bold text-dark">{{ $cat->name }}</td>
                                <td class="text-muted">{{ $cat->description ?? 'N/A' }}</td>
                                <td><x-ui.badge soft variant="success">Active</x-ui.badge></td>
                                <td class="text-end pe-3">
                                    <form action="{{ route('hrms.pip.category.destroy', $cat->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this category?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-outline-danger border-0"><i class="feather-trash-2"></i> Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">No PIP Categories configured.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        @elseif($activeTab === 'templates')
            <!-- TAB 3: POLICY TEMPLATES MASTER -->
            <div class="table-responsive border rounded">
                <table class="table table-hover align-middle mb-0 fs-13">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3 py-3 text-muted text-uppercase fs-11">Template Name</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Duration (Days)</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Check-in Frequency</th>
                            <th class="py-3 text-muted text-uppercase fs-11">Description</th>
                            <th class="text-end pe-3 py-3 text-muted text-uppercase fs-11">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($policyTemplates as $tmpl)
                            <tr>
                                <td class="ps-3 fw-bold text-dark">{{ $tmpl->name }}</td>
                                <td><span class="badge bg-light text-dark border px-2 py-1">{{ $tmpl->duration_days }} Days</span></td>
                                <td class="text-capitalize">{{ $tmpl->checkin_frequency }}</td>
                                <td class="text-muted">{{ $tmpl->description ?? 'N/A' }}</td>
                                <td class="text-end pe-3">
                                    <form action="{{ route('hrms.pip.template.destroy', $tmpl->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this policy template?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-outline-danger border-0"><i class="feather-trash-2"></i> Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No Policy Templates configured.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

    </div>
</div>

<!-- INITIATE PIP MODAL (Placed outside main panel to prevent backdrop blur issues) -->
<div class="modal fade" id="createPipModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark fs-15"><i class="feather-plus-circle me-1.5 text-primary"></i> Initiate Performance Improvement Plan (PIP)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.pip.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="Target Employee" name="employee_id" :required="true">
                                <option value="">Select Employee...</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_id }})</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="Manager / Supervisor" name="manager_id">
                                <option value="">Select Manager...</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->full_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="PIP Category" name="pip_category_id">
                                <option value="">Select Category...</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="Apply Policy Template (Optional)" name="policy_template_id" id="pipPolicyTemplate">
                                <option value="">-- No Template, Fill Manually --</option>
                                @foreach($policyTemplates as $tmpl)
                                    <option value="{{ $tmpl->id }}"
                                        data-duration="{{ $tmpl->duration_days }}"
                                        data-frequency="{{ $tmpl->checkin_frequency }}">
                                        {{ $tmpl->name }} ({{ $tmpl->duration_days }} days, {{ $tmpl->checkin_frequency }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="Check-in Frequency" name="checkin_frequency" id="pipCheckinFreq" :required="true">
                                <option value="weekly" selected>Weekly Check-ins</option>
                                <option value="biweekly">Bi-weekly Check-ins</option>
                                <option value="monthly">Monthly Check-ins</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" inputType="date" label="Start Date" name="start_date" value="{{ date('Y-m-d') }}" :required="true" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" inputType="date" label="End Date" name="end_date" value="{{ date('Y-m-d', strtotime('+30 days')) }}" :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="Reason for PIP & Core Deficiencies" name="reason_details" rows="3" placeholder="Describe the specific areas needing improvement..." :required="true" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light py-2.5">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold px-4">Initiate PIP</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ADD CATEGORY MODAL -->
<div class="modal fade" id="createCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark fs-15"><i class="feather-tag me-1.5 text-primary"></i> Add PIP Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.pip.category.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="input" label="Category Name" name="name" placeholder="e.g. Code Quality, Behavioral, Productivity" :required="true" />
                    </div>
                    <div class="mb-0">
                        <x-ui.odoo-form-ui type="textarea" label="Description" name="description" rows="3" placeholder="Category purpose and scope..." />
                    </div>
                </div>
                <div class="modal-footer border-top bg-light py-2.5">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold px-4">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ADD POLICY TEMPLATE MODAL -->
<div class="modal fade" id="createTemplateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark fs-15"><i class="feather-file-text me-1.5 text-primary"></i> Add Policy Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.pip.template.store') }}" method="POST" novalidate id="createTemplateForm">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="input" label="Template Name" name="name" placeholder="e.g. Standard 30-Day Technical PIP" :required="true" />
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <x-ui.odoo-form-ui type="input" inputType="number" label="Duration (Days)" name="duration_days" value="30" :required="true" min="7" max="180" />
                        </div>
                        <div class="col-6">
                            <x-ui.odoo-form-ui type="select" label="Check-in Frequency" name="checkin_frequency" :required="true">
                                <option value="weekly">Weekly</option>
                                <option value="biweekly">Bi-weekly</option>
                                <option value="monthly">Monthly</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>
                    <div class="mb-0">
                        <x-ui.odoo-form-ui type="textarea" label="Description" name="description" rows="3" placeholder="Template details..." />
                    </div>
                </div>
                <div class="modal-footer border-top bg-light py-2.5">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold px-4">Save Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        $('#createPipModal, #createCategoryModal, #createTemplateModal').each(function() {
            if ($(this).parent().get(0) !== document.body) {
                $(this).appendTo(document.body);
            }
        });

        // Instant Auto Search without pressing Enter key (matching legal-entities tab)
        let pipSearchDebounceTimer;
        $(document).on('input', '#pipSearchInput', function() {
            clearTimeout(pipSearchDebounceTimer);
            pipSearchDebounceTimer = setTimeout(function() {
                $('#pipSearchForm').submit();
            }, 450);
        });

        // Policy Template auto-fill: when template selected, update End Date & Check-in Frequency
        $(document).on('change', '#pipPolicyTemplate', function() {
            const selected = $(this).find('option:selected');
            const duration = selected.data('duration');
            const frequency = selected.data('frequency');

            if (duration) {
                // Compute end date from today + duration_days
                const startInput = $('input[name="start_date"]');
                const startVal = startInput.val() || '{{ date("Y-m-d") }}';
                const startDate = new Date(startVal);
                startDate.setDate(startDate.getDate() + parseInt(duration));
                const endDateStr = startDate.toISOString().split('T')[0];
                $('input[name="end_date"]').val(endDateStr);
            }

            if (frequency) {
                const freq = $('#pipCheckinFreq');
                freq.find('option').removeAttr('selected');
                freq.val(frequency).trigger('change');
            }
        });

        // When start_date changes and a template is selected, recalculate end date
        $(document).on('change', 'input[name="start_date"]', function() {
            const selected = $('#pipPolicyTemplate').find('option:selected');
            const duration = selected.data('duration');
            if (duration) {
                const startDate = new Date($(this).val());
                startDate.setDate(startDate.getDate() + parseInt(duration));
                $('input[name="end_date"]').val(startDate.toISOString().split('T')[0]);
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
