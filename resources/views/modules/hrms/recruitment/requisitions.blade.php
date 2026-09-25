@extends('layouts.duralux')
{{-- Job Requisitions List --}}

@section('title', 'Job Requisitions | Recruitment')
@section('page-title', 'Job Requisitions Management')
@section('breadcrumb', 'HRMS / Recruitment / Requisitions')

@php
    $authUser = auth()->user();
    $canCreateRecruitment = $authUser && ($authUser->hasHrPermission('hrms.recruitment.create') || $authUser->hasHrPermission('hr.settings.manage') || $authUser->hasHrPermission('hrms.recruitment.manage'));
    $canUpdateRecruitment = $authUser && ($authUser->hasHrPermission('hrms.recruitment.update') || $authUser->hasHrPermission('hr.settings.manage') || $authUser->hasHrPermission('hrms.recruitment.manage'));
    $canManageRecruitment = $authUser && ($authUser->hasHrPermission('hrms.recruitment.manage') || $authUser->hasHrPermission('hr.settings.manage'));
@endphp

@section('page-actions')
    @if($canCreateRecruitment)
        <div class="d-flex align-items-center gap-2">
            <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createRequisitionModal">
                New Job Requisition
            </x-ui.button>
        </div>
    @endif
@endsection

@push('styles')
<style>
    #recruitmentTabs .nav-link {
        border: none !important;
        background-color: transparent !important;
        color: #64748b;
        font-weight: 500;
        padding: 12px 18px;
        border-bottom: 2px solid transparent !important;
        transition: all 0.2s ease-in-out;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    #recruitmentTabs .nav-link:hover {
        color: var(--bs-primary);
    }
    #recruitmentTabs .nav-link.active {
        color: var(--bs-primary) !important;
        border-bottom: 2px solid var(--bs-primary) !important;
        font-weight: 600;
    }

    .req-search-form {
        min-width: 260px;
        max-width: 320px;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    }
    .req-search-form:focus-within {
        background-color: #fff !important;
        border-color: var(--bs-primary) !important;
        box-shadow: 0 0 0 0.18rem rgba(0, 0, 0, 0.05);
    }

    /* Recruitment Tables: Prevent horizontal scrollbar & wrap long content to next line */
    .recruitment-table {
        width: 100% !important;
        table-layout: fixed !important;
    }
    .recruitment-table th,
    .recruitment-table td {
        word-wrap: break-word !important;
        overflow-wrap: break-word !important;
        word-break: break-word !important;
        white-space: normal !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
            <i class="feather-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- ERP Single Panel Container -->
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">

        <!-- Panel Header with Sub Navigation Tabs -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-bottom pb-3 mb-4">
            <ul class="nav nav-tabs border-0" id="recruitmentTabs">
                <li class="nav-item">
                    <a class="nav-link active" href="{{ route('hrms.recruitment.requisitions.index') }}">
                        <i class="feather-briefcase"></i> Job Requisitions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('hrms.recruitment.candidates.index') }}">
                        <i class="feather-users"></i> Candidate Bank
                    </a>
                </li>
            </ul>
        </div>

        @if(isset($upcomingInterviews) && $upcomingInterviews->count() > 0)
            <!-- Upcoming Scheduled Interviews Widget -->
            <div class="mb-4">
                <div class="card border border-warning-subtle bg-soft-warning rounded-3 shadow-sm">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center py-2 px-3">
                        <span class="fw-bold text-dark fs-13">
                            <i class="feather-calendar text-warning me-2"></i> Upcoming Scheduled Interviews ({{ $upcomingInterviews->count() }})
                        </span>
                    </div>
                    <div class="card-body p-3 pt-0">
                        <div class="row g-2">
                            @foreach($upcomingInterviews as $interview)
                                @php
                                    $candidate = $interview->application->candidate ?? null;
                                    $req = $interview->application->requisition ?? null;
                                @endphp
                                <div class="col-md-6 col-lg-4">
                                    <div class="bg-white border rounded p-3 h-100 shadow-xs d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <h6 class="mb-0 fw-bold text-dark fs-13">{{ $candidate->full_name ?? 'Candidate' }}</h6>
                                                <x-ui.badge variant="warning" soft>Round {{ $interview->round_number }}</x-ui.badge>
                                            </div>
                                            <div class="text-muted fs-12 mb-2">
                                                <i class="feather-briefcase me-1"></i>{{ $req->job_title ?? 'Role' }}
                                            </div>
                                            <div class="text-secondary fs-12 mb-2">
                                                <i class="feather-clock text-warning me-1"></i>
                                                {{ $interview->scheduled_at ? $interview->scheduled_at->format('M d, Y - h:i A') : 'TBD' }}
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-between pt-2 border-top gap-2">
                                            @if($req)
                                                <x-ui.button :href="route('hrms.recruitment.pipeline', ['requisition' => $req->id, 'candidate_id' => $candidate?->id])" variant="outline-primary" size="sm" icon="feather-columns">
                                                    Pipeline
                                                </x-ui.button>
                                            @endif
                                            @if($interview->meeting_link)
                                                <a href="{{ $interview->meeting_link }}" target="_blank" class="btn btn-xs btn-primary text-white">
                                                    <i class="feather-video me-1"></i> Join
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Search, Sort & Filter Toolbar -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h5 class="fw-bold text-dark mb-0 fs-16">All Job Requisitions</h5>
                <small class="text-muted">Manage active openings and headcount requests</small>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Standard Search Bar -->
                <form method="GET" action="{{ route('hrms.recruitment.requisitions.index') }}" class="req-search-form d-flex align-items-center bg-light border rounded px-3 py-1">
                    <input type="hidden" name="department_id" value="{{ $filters['department_id'] }}">
                    <input type="hidden" name="status" value="{{ $filters['status'] }}">
                    <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                    <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                    <input 
                        type="text" 
                        name="search" 
                        class="form-control border-0 bg-transparent p-0 fs-13" 
                        placeholder="Search Job Title or Code..." 
                        value="{{ $filters['search'] }}"
                        style="box-shadow: none; height: 32px;"
                    >
                </form>

                <!-- Standard Sort Dropdown Component -->
                <x-ui.sort-dropdown label="Sort">
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'date_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'date_desc']) }}">
                        <span>Newest First</span>
                        @if($filters['sort'] === 'date_desc') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'date_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'date_asc']) }}">
                        <span>Oldest First</span>
                        @if($filters['sort'] === 'date_asc') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'title_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'title_asc']) }}">
                        <span>Job Title (A-Z)</span>
                        @if($filters['sort'] === 'title_asc') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'title_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'title_desc']) }}">
                        <span>Job Title (Z-A)</span>
                        @if($filters['sort'] === 'title_desc') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'vacancies_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'vacancies_desc']) }}">
                        <span>Most Vacancies</span>
                        @if($filters['sort'] === 'vacancies_desc') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                </x-ui.sort-dropdown>

                <!-- Standard Filter Component -->
                <x-ui.filter label="Filter" :resetUrl="route('hrms.recruitment.requisitions.index')">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> Filter Options</h6>
                    <form method="GET" action="{{ route('hrms.recruitment.requisitions.index') }}">
                        <input type="hidden" name="search" value="{{ $filters['search'] }}">
                        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                        
                        <x-ui.odoo-form-ui type="select" label="Department" name="department_id">
                            <option value="">All Departments</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" {{ (string) $filters['department_id'] === (string) $dept->id ? 'selected' : '' }}>
                                    {{ $dept->name }}
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="select" label="Status" name="status">
                            <option value="">All Statuses</option>
                            <option value="approved" {{ $filters['status'] === 'approved' ? 'selected' : '' }}>Open</option>
                            <option value="pending_approval" {{ $filters['status'] === 'pending_approval' ? 'selected' : '' }}>Pending Approval</option>
                            <option value="closed" {{ $filters['status'] === 'closed' ? 'selected' : '' }}>Closed</option>
                        </x-ui.odoo-form-ui>

                        <div class="pt-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">Apply Filters</button>
                            <a href="{{ route('hrms.recruitment.requisitions.index') }}" class="btn btn-light border btn-sm w-100">Reset</a>
                        </div>
                    </form>
                </x-ui.filter>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 recruitment-table">
                <thead class="bg-light text-muted fs-12 text-uppercase">
                    <tr>
                        <th class="ps-3" style="width: 25%;">Code & Job Title</th>
                        <th style="width: 20%;">Department & Role</th>
                        <th style="width: 14%;">Required Experience</th>
                        <th style="width: 11%;">Vacancies</th>
                        <th class="text-center" style="width: 9%;">Priority</th>
                        <th class="text-center" style="width: 8%;">Status</th>
                        <th class="text-end pe-3" style="width: 13%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requisitions as $req)
                        <tr>
                            <td class="ps-3">
                                <div class="fw-bold text-dark fs-14">{{ $req->job_title }}</div>
                                <span class="badge bg-light text-secondary border mt-1">{{ $req->requisition_code }}</span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark fs-13">{{ $req->department->name ?? 'N/A' }}</div>
                                <small class="text-muted fs-12 d-block">{{ $req->designation->name ?? 'N/A' }}</small>
                            </td>
                            <td>
                                <x-ui.badge variant="primary" soft>
                                    <i class="feather-award me-1"></i>{{ $req->min_experience_years }} - {{ $req->max_experience_years }} Years
                                </x-ui.badge>
                            </td>
                            <td>
                                <span class="fw-bold text-dark fs-13">{{ $req->vacancies }} Positions</span>
                            </td>
                            <td class="text-center">
                                @if($req->priority === 'urgent')
                                    <x-ui.badge variant="danger">Urgent</x-ui.badge>
                                @elseif($req->priority === 'high')
                                    <x-ui.badge variant="warning">High</x-ui.badge>
                                @else
                                    <x-ui.badge variant="info" soft>{{ ucfirst($req->priority) }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="text-center">
                                <x-ui.status-badge :status="$req->status" :label="in_array($req->status, ['approved', 'published', 'open']) ? 'Open' : null" />
                            </td>
                            <td class="text-end pe-3">
                                <x-ui.button :href="route('hrms.recruitment.pipeline', $req->id)" variant="primary" size="sm" icon="feather-columns" class="text-nowrap">
                                    View Pipeline
                                </x-ui.button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="feather-folder-minus fs-36 d-block mb-2 text-secondary"></i>
                                No job requisitions match your search/filter criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pt-3 border-top-0">
            {{ $requisitions->links() }}
        </div>
    </div>
</div>

<!-- Modal: New Job Requisition -->
<div class="modal fade" id="createRequisitionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form action="{{ route('hrms.recruitment.requisitions.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-light">
                    <h5 class="modal-header-title fw-bold"><i class="feather-plus-circle text-primary me-2"></i>Create Job Requisition</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" label="Job Title" name="job_title" :required="true" placeholder="e.g. Senior Software Engineer" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="Department" name="department_id" :required="true">
                                <option value="">Select Department...</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="Designation" name="designation_id" :required="true">
                                <option value="">Select Designation...</option>
                                @foreach($designations as $desig)
                                    <option value="{{ $desig->id }}">{{ $desig->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" inputType="number" label="Vacancies Count" name="vacancies" value="1" :required="true" />
                        </div>

                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" inputType="number" label="Min Exp (Yrs)" name="min_experience_years" value="0" :required="true" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" inputType="number" label="Max Exp (Yrs)" name="max_experience_years" value="5" :required="true" />
                        </div>

                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="Work Mode" name="work_mode">
                                <option value="onsite">Onsite</option>
                                <option value="remote">Remote</option>
                                <option value="hybrid">Hybrid</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="Employment Type" name="employment_type">
                                <option value="full_time">Full Time</option>
                                <option value="part_time">Part Time</option>
                                <option value="contract">Contract</option>
                                <option value="internship">Internship</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="Priority" name="priority">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" inputType="date" label="Target Joining Date" name="target_joining_date" />
                        </div>

                        <div class="col-md-12">
                            <x-ui.odoo-form-ui type="input" label="Required Skills" name="skills_required" placeholder="e.g. PHP, Laravel, MySQL, REST API" />
                        </div>

                        <div class="col-md-12">
                            <x-ui.odoo-form-ui type="textarea" label="Job Description" name="job_description" rows="3" placeholder="Enter key responsibilities and qualifications..." />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="feather-check me-1"></i> Save Job Requisition</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.modal').forEach(function(modalEl) {
            document.body.appendChild(modalEl);
        });
    });
</script>
@endpush
