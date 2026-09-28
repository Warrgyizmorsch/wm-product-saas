@extends('layouts.duralux')

@section('title', 'Candidate Bank | Recruitment')
@section('page-title', 'Candidate Database')
@section('breadcrumb', 'HRMS / Recruitment / Candidates')

@php
    $authUser = auth()->user();
    $canCreateRecruitment = $authUser && ($authUser->hasHrPermission('hrms.recruitment.create') || $authUser->hasHrPermission('hr.settings.manage') || $authUser->hasHrPermission('hrms.recruitment.manage'));
    $canUpdateRecruitment = $authUser && ($authUser->hasHrPermission('hrms.recruitment.update') || $authUser->hasHrPermission('hr.settings.manage') || $authUser->hasHrPermission('hrms.recruitment.manage'));
    $canManageRecruitment = $authUser && ($authUser->hasHrPermission('hrms.recruitment.manage') || $authUser->hasHrPermission('hr.settings.manage'));
@endphp

@section('page-actions')
    @if($canCreateRecruitment)
        <div class="d-flex align-items-center gap-2">
            <x-ui.button variant="primary" icon="feather-user-plus" data-bs-toggle="modal" data-bs-target="#addCandidateModal">
                Add Candidate
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

    .cnd-search-form {
        min-width: 260px;
        max-width: 320px;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    }
    .cnd-search-form:focus-within {
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
                    <a class="nav-link" href="{{ route('hrms.recruitment.requisitions.index') }}">
                        <i class="feather-briefcase"></i> Job Requisitions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="{{ route('hrms.recruitment.candidates.index') }}">
                        <i class="feather-users"></i> Candidate Bank
                    </a>
                </li>
            </ul>
        </div>

        <!-- Search, Sort & Filter Toolbar -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h5 class="fw-bold text-dark mb-0 fs-16">Candidate Database</h5>
                <small class="text-muted">Browse talent bank and manage applicants</small>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Standard Search Bar -->
                <form method="GET" action="{{ route('hrms.recruitment.candidates.index') }}" class="cnd-search-form d-flex align-items-center bg-light border rounded px-3 py-1">
                    <input type="hidden" name="status" value="{{ $filters['status'] }}">
                    <input type="hidden" name="source" value="{{ $filters['source'] }}">
                    <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                    <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                    <input 
                        type="text" 
                        name="search" 
                        class="form-control border-0 bg-transparent p-0 fs-13" 
                        placeholder="Search Name, Email, or Code..." 
                        value="{{ $filters['search'] }}"
                        style="box-shadow: none; height: 32px;"
                    >
                </form>

                <!-- Standard Sort Dropdown Component -->
                <x-ui.sort-dropdown label="Sort">
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'date_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'date_desc']) }}">
                        <span>Newest Added</span>
                        @if($filters['sort'] === 'date_desc') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'date_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'date_asc']) }}">
                        <span>Oldest Added</span>
                        @if($filters['sort'] === 'date_asc') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'name_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'name_asc']) }}">
                        <span>Name (A-Z)</span>
                        @if($filters['sort'] === 'name_asc') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'name_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'name_desc']) }}">
                        <span>Name (Z-A)</span>
                        @if($filters['sort'] === 'name_desc') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $filters['sort'] === 'exp_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'exp_desc']) }}">
                        <span>Highest Experience</span>
                        @if($filters['sort'] === 'exp_desc') <i class="feather-check ms-3 text-primary"></i> @endif
                    </a>
                </x-ui.sort-dropdown>

                <!-- Standard Filter Component -->
                <x-ui.filter label="Filter" :resetUrl="route('hrms.recruitment.candidates.index')">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> Filter Options</h6>
                    <form method="GET" action="{{ route('hrms.recruitment.candidates.index') }}">
                        <input type="hidden" name="search" value="{{ $filters['search'] }}">
                        <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                        
                        <x-ui.odoo-form-ui type="select" label="Candidate Status" name="status">
                            <option value="">All Statuses</option>
                            <option value="active" {{ $filters['status'] === 'active' ? 'selected' : '' }}>Active Candidate</option>
                            <option value="hired" {{ $filters['status'] === 'hired' ? 'selected' : '' }}>Hired</option>
                            <option value="rejected" {{ $filters['status'] === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="select" label="Source Channel" name="source">
                            <option value="">All Sources</option>
                            <option value="direct" {{ $filters['source'] === 'direct' ? 'selected' : '' }}>Direct Entry</option>
                            <option value="referral" {{ $filters['source'] === 'referral' ? 'selected' : '' }}>Employee Referral</option>
                            <option value="linkedin" {{ $filters['source'] === 'linkedin' ? 'selected' : '' }}>LinkedIn</option>
                            <option value="agency" {{ $filters['source'] === 'agency' ? 'selected' : '' }}>Agency</option>
                        </x-ui.odoo-form-ui>

                        <div class="pt-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">Apply Filters</button>
                            <a href="{{ route('hrms.recruitment.candidates.index') }}" class="btn btn-light border btn-sm w-100">Reset</a>
                        </div>
                    </form>
                </x-ui.filter>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 recruitment-table">
                <thead class="bg-light text-muted fs-12 text-uppercase">
                    <tr>
                        <th class="ps-3" style="width: 24%;">Candidate Details</th>
                        <th style="width: 20%;">Contact Info</th>
                        <th style="width: 13%;">Experience</th>
                        <th style="width: 19%;">Applied Position</th>
                        <th class="text-center" style="width: 8%;">Source</th>
                        <th class="text-center" style="width: 8%;">Resume</th>
                        <th class="text-center pe-3" style="width: 8%;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($candidates as $candidate)
                        @php
                            $latestApp = $candidate->applications->first();
                        @endphp
                        <tr>
                            <td class="ps-3">
                                <div class="fw-bold text-dark fs-14">{{ $candidate->full_name }}</div>
                                <div class="text-muted fs-12">{{ $candidate->current_designation ?? 'N/A' }} @ {{ $candidate->current_company ?? 'N/A' }}</div>
                                <span class="badge bg-light text-secondary border mt-1">{{ $candidate->candidate_code }}</span>
                            </td>
                            <td>
                                <div><i class="feather-mail text-muted me-1"></i>{{ $candidate->email }}</div>
                                <small class="text-muted"><i class="feather-phone me-1"></i>{{ $candidate->phone ?? 'N/A' }}</small>
                            </td>
                            <td>
                                <x-ui.badge variant="info" soft>
                                    <i class="feather-clock me-1"></i>{{ $candidate->total_experience_years }} Yrs Exp
                                </x-ui.badge>
                            </td>
                            <td>
                                @if($latestApp && $latestApp->requisition)
                                    <a href="{{ route('hrms.recruitment.pipeline', ['requisition' => $latestApp->job_requisition_id, 'candidate_id' => $candidate->id]) }}" class="fw-bold text-primary">
                                        {{ $latestApp->requisition->job_title }}
                                    </a>
                                @else
                                    <span class="text-muted">Unassigned</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <x-ui.badge variant="light">{{ ucfirst($candidate->source) }}</x-ui.badge>
                            </td>
                            <td class="text-center">
                                @if($candidate->resume_path)
                                    <x-ui.button 
                                        :href="route('hrms.recruitment.candidates.download-resume', $candidate)" 
                                        target="_blank" 
                                        variant="outline-primary" 
                                        size="sm" 
                                        icon="feather-file-text"
                                        class="text-nowrap"
                                    >
                                        View CV
                                    </x-ui.button>
                                @else
                                    <span class="text-muted fs-12">No Resume</span>
                                @endif
                            </td>
                            <td class="text-center pe-3">
                                @if($candidate->status === 'hired')
                                    <x-ui.status-badge status="approved" label="Hired" />
                                @elseif($candidate->status === 'rejected')
                                    <x-ui.status-badge status="rejected" label="Rejected" />
                                @else
                                    <x-ui.status-badge status="active" label="Active" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="feather-users fs-36 d-block mb-2 text-secondary"></i>
                                No candidates match your search/filter criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pt-3 border-top-0">
            {{ $candidates->links() }}
        </div>
    </div>
</div>

<!-- Modal: Add Candidate -->
<div class="modal fade" id="addCandidateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form action="{{ route('hrms.recruitment.candidates.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header bg-light">
                    <h5 class="modal-header-title fw-bold"><i class="feather-user-plus text-primary me-2"></i>Add Candidate</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-start">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="Target Job Requisition" name="job_requisition_id" :required="true">
                                <option value="">Select Job Opening...</option>
                                @foreach($requisitions as $req)
                                    <option value="{{ $req->id }}">{{ $req->job_title }} ({{ $req->requisition_code }})</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="Candidate Source" name="source" :required="true">
                                <option value="direct">Direct Entry</option>
                                <option value="referral">Employee Referral</option>
                                <option value="linkedin">LinkedIn</option>
                                <option value="agency">Recruitment Agency</option>
                                <option value="other">Other</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="First Name" name="first_name" :required="true" placeholder="e.g. John" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="Last Name" name="last_name" placeholder="e.g. Doe" />
                        </div>

                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" inputType="email" label="Email Address" name="email" :required="true" placeholder="john.doe@example.com" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="Phone Number" name="phone" placeholder="+1 234 567 8900" />
                        </div>

                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" inputType="number" label="Total Experience (Yrs)" name="total_experience_years" value="0" :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="Current Designation" name="current_designation" placeholder="e.g. Software Developer" />
                        </div>

                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="Current Company" name="current_company" placeholder="e.g. Tech Corp" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" inputType="number" label="Notice Period (Days)" name="notice_period_days" value="30" />
                        </div>

                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" label="Current Location" name="current_location" placeholder="e.g. New York, USA" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="file" label="Attach Resume / CV" name="resume" :required="true" helperText="Formats: .pdf, .doc, .docx" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="feather-check me-1"></i> Save Candidate</button>
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
