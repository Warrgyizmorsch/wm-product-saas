@extends('layouts.duralux')
{{-- Recruitment & Talent Acquisition Dashboard --}}

@section('title', 'Recruitment & ATS | HRMS')
@section('page-title', 'Recruitment & Talent Acquisition')
@section('breadcrumb', 'HRMS / Recruitment')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createRequisitionModal">
            New Job Requisition
        </x-ui.button>
    </div>
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

    .kpi-stat-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background-color: #ffffff;
        padding: 18px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .kpi-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
    }
    .kpi-icon-box {
        width: 46px;
        height: 46px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }
    .bg-soft-primary { background-color: rgba(13, 110, 253, 0.1) !important; color: #0d6efd !important; }
    .bg-soft-success { background-color: rgba(25, 135, 84, 0.1) !important; color: #198754 !important; }
    .bg-soft-info { background-color: rgba(13, 202, 240, 0.12) !important; color: #0dcaf0 !important; }
    .bg-soft-warning { background-color: rgba(255, 193, 7, 0.15) !important; color: #ffc107 !important; }
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
                    <a class="nav-link active" href="{{ route('hrms.recruitment.index') }}">
                        <i class="feather-grid"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('hrms.recruitment.requisitions.index') }}">
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

        <!-- KPI Metrics Header Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="kpi-stat-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">Total Requisitions</span>
                            <h2 class="fw-bold mb-0 text-dark">{{ $totalRequisitions }}</h2>
                        </div>
                        <div class="kpi-icon-box bg-soft-primary">
                            <i class="feather-briefcase"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="kpi-stat-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">Active Job Openings</span>
                            <h2 class="fw-bold mb-0 text-dark">{{ $openRequisitions }}</h2>
                        </div>
                        <div class="kpi-icon-box bg-soft-success">
                            <i class="feather-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="kpi-stat-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">Total Candidates</span>
                            <h2 class="fw-bold mb-0 text-dark">{{ $totalCandidates }}</h2>
                        </div>
                        <div class="kpi-icon-box bg-soft-info">
                            <i class="feather-users"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="kpi-stat-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">Scheduled Interviews</span>
                            <h2 class="fw-bold mb-0 text-dark">{{ $scheduledInterviewsCount }}</h2>
                        </div>
                        <div class="kpi-icon-box bg-soft-warning">
                            <i class="feather-calendar"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Open Requisitions Section -->
            <div class="col-lg-8">
                <div class="card border border-light shadow-none rounded-3">
                    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between border-bottom-0">
                        <h5 class="mb-0 fw-bold"><i class="feather-briefcase text-primary me-2"></i>Active Job Requisitions</h5>
                        <a href="{{ route('hrms.recruitment.requisitions.index') }}" class="btn btn-sm btn-light text-muted">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted fs-12 text-uppercase">
                                <tr>
                                    <th>Code & Title</th>
                                    <th>Dept / Role</th>
                                    <th>Experience</th>
                                    <th>Openings</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentRequisitions as $req)
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $req->job_title }}</div>
                                            <small class="text-muted">{{ $req->requisition_code }}</small>
                                        </td>
                                        <td>
                                            <div>{{ $req->department->name ?? 'N/A' }}</div>
                                            <small class="text-muted">{{ $req->designation->name ?? 'N/A' }}</small>
                                        </td>
                                        <td>
                                            <x-ui.badge variant="primary" soft>
                                                <i class="feather-clock me-1"></i>{{ $req->min_experience_years }} - {{ $req->max_experience_years }} Yrs
                                            </x-ui.badge>
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-primary">{{ $req->vacancies }} Openings</span>
                                        </td>
                                        <td>
                                             <x-ui.status-badge :status="$req->status" />
                                        </td>
                                        <td>
                                            <a href="{{ route('hrms.recruitment.pipeline', $req->id) }}" class="btn btn-sm btn-outline-primary fw-semibold">
                                                <i class="feather-columns me-1"></i> Pipeline Kanban
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="feather-inbox fs-32 d-block mb-2 text-secondary"></i>
                                            No active job requisitions found. Click <strong>New Job Requisition</strong> to create one!
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Upcoming Interviews Sidebar -->
            <div class="col-lg-4">
                <div class="card border border-light shadow-none rounded-3">
                    <div class="card-header bg-white py-3 border-bottom-0">
                        <h5 class="mb-0 fw-bold"><i class="feather-calendar text-warning me-2"></i>Upcoming Interviews</h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            @forelse($upcomingInterviews as $interview)
                                @php
                                    $candidate = $interview->application->candidate ?? null;
                                @endphp
                                <li class="list-group-item p-3">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <h6 class="mb-0 fw-bold text-dark">{{ $candidate->full_name ?? 'Candidate' }}</h6>
                                         <x-ui.badge variant="primary" soft>Round {{ $interview->round_number }}</x-ui.badge>
                                    </div>
                                    <div class="text-muted fs-12 mb-2">
                                        <i class="feather-briefcase me-1"></i>{{ $interview->application->requisition->job_title ?? 'Role' }}
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between fs-12 bg-light p-2 rounded">
                                        <div>
                                            <i class="feather-clock text-warning me-1"></i>
                                            {{ $interview->scheduled_at ? $interview->scheduled_at->format('M d, Y - h:i A') : 'TBD' }}
                                        </div>
                                        @if($interview->meeting_link)
                                            <a href="{{ $interview->meeting_link }}" target="_blank" class="btn btn-xs btn-primary text-white">Join Meeting</a>
                                        @endif
                                    </div>
                                </li>
                            @empty
                                <li class="list-group-item p-4 text-center text-muted">
                                    <i class="feather-calendar fs-28 text-muted mb-2 d-block"></i>
                                    No upcoming interviews scheduled.
                                </li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal: New Job Requisition using odoo-form-ui platform component -->
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
