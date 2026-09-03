@extends('layouts.duralux')

@section('title', 'Probation Reviews & Evaluations | SaaS ERP')
@section('page-title', 'Probation Reviews')
@section('breadcrumb', 'HRMS / Employees / Probation Reviews')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="primary" icon="feather-log-out" href="{{ route('hrms.exits.index') }}" class="fw-bold text-uppercase">
            Offboarding Workspace
        </x-ui.button>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        /* Evaluation Modal Label Width & Select Adjustments (Prevents wrapping & text overlapping) */
        [id^="evaluateModal_"] .odoo-form-group {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }
        [id^="evaluateModal_"] .odoo-form-label {
            width: 210px !important;
            min-width: 210px !important;
            flex-shrink: 0 !important;
            white-space: nowrap !important;
            font-size: 13px !important;
        }
        [id^="evaluateModal_"] .odoo-form-control,
        [id^="evaluateModal_"] .select2-container--bootstrap-5 .select2-selection--single {
            border: none !important;
            border-bottom: 1px solid #ced4da !important;
            border-radius: 0 !important;
            background-color: transparent !important;
            padding-right: 20px !important;
            min-height: 28px !important;
        }
        [id^="evaluateModal_"] .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            padding-left: 2px !important;
            padding-right: 18px !important;
            font-size: 13px !important;
            white-space: nowrap !important;
        }
        [id^="evaluateModal_"] .select2-container--bootstrap-5 .select2-dropdown {
            border: 1px solid #e2e8f0 !important;
            border-radius: 8px !important;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1) !important;
            z-index: 1065 !important;
        }
        [id^="evaluateModal_"] .select2-container--bootstrap-5 .select2-results__option {
            font-size: 13px !important;
            padding: 8px 14px !important;
        }
        [id^="evaluateModal_"] .select2-container--bootstrap-5 .select2-results__option--highlighted[aria-selected] {
            background-color: var(--bs-primary) !important;
            color: #fff !important;
        }

        /* Underlined Horizontal Tabs (Matching HRMS Standard) */
        #probationTabs .nav-link {
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
        #probationTabs .nav-link:hover {
            color: var(--bs-primary);
        }
        #probationTabs .nav-link.active {
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
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4 text-dark shadow-sm border-0" role="alert">
            <i class="feather-check-circle me-2 text-success"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4 text-dark shadow-sm border-0" role="alert">
            <i class="feather-alert-circle me-2 text-danger"></i>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- ERP Single Panel Workspace -->
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">

        <!-- Navigation Tabs & Right-Aligned Toolbar -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3 border-bottom pb-2">
            <!-- Left: Tabs -->
            <ul class="nav gap-1" id="probationTabs" role="tablist">
                <li class="nav-item">
                    <a href="{{ route('hrms.probation.index', ['status' => 'in_probation', 'department_id' => $departmentId, 'search' => $search, 'sort_by' => $sortBy, 'sort_order' => $sortOrder, 'eval_status' => $evalStatus]) }}" 
                       class="nav-link {{ $filterStatus === 'in_probation' ? 'active' : '' }}">
                        <i class="feather-user-check"></i>
                        <span>In Probation</span>
                        <x-ui.badge soft variant="primary" class="ms-1">{{ $totalInProbation }}</x-ui.badge>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('hrms.probation.index', ['status' => 'due_soon', 'department_id' => $departmentId, 'search' => $search, 'sort_by' => $sortBy, 'sort_order' => $sortOrder, 'eval_status' => $evalStatus]) }}" 
                       class="nav-link {{ $filterStatus === 'due_soon' ? 'active' : '' }}">
                        <i class="feather-calendar"></i>
                        <span>Due Soon (15d)</span>
                        <x-ui.badge soft variant="warning" class="ms-1">{{ $dueSoonCount }}</x-ui.badge>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('hrms.probation.index', ['status' => 'overdue', 'department_id' => $departmentId, 'search' => $search, 'sort_by' => $sortBy, 'sort_order' => $sortOrder, 'eval_status' => $evalStatus]) }}" 
                       class="nav-link {{ $filterStatus === 'overdue' ? 'active' : '' }}">
                        <i class="feather-alert-triangle"></i>
                        <span>Overdue</span>
                        <x-ui.badge soft variant="danger" class="ms-1">{{ $overdueCount }}</x-ui.badge>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('hrms.probation.index', ['status' => 'confirmed', 'department_id' => $departmentId, 'search' => $search, 'sort_by' => $sortBy, 'sort_order' => $sortOrder, 'eval_status' => $evalStatus]) }}" 
                       class="nav-link {{ $filterStatus === 'confirmed' ? 'active' : '' }}">
                        <i class="feather-award"></i>
                        <span>Confirmed Employees</span>
                        <x-ui.badge soft variant="success" class="ms-1">{{ $confirmedThisMonthCount }}</x-ui.badge>
                    </a>
                </li>
            </ul>

            <!-- Right: Search, Sort Dropdown & Filter Dropdown (Standard UI Elements) -->
            <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
                <!-- Search Form -->
                <form method="GET" action="{{ route('hrms.probation.index') }}" class="d-flex align-items-center m-0" id="probationSearchForm">
                    <input type="hidden" name="status" value="{{ $filterStatus }}">
                    <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                    <input type="hidden" name="sort_order" value="{{ $sortOrder }}">
                    <input type="hidden" name="department_id" value="{{ $departmentId }}">
                    <input type="hidden" name="eval_status" value="{{ $evalStatus }}">
                    
                    <div class="d-flex align-items-center bg-light border rounded px-3 py-1" style="min-width: 240px; height: 36px;">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" name="search" id="probation_search_input" value="{{ $search }}" class="form-control border-0 bg-transparent p-0 fs-13 text-dark" placeholder="Search employee..." style="box-shadow: none; height: 100%; outline: none;" autocomplete="off">
                    </div>
                </form>

                <!-- Sort Dropdown -->
                <x-ui.sort-dropdown label="Sort">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'probation_end_date', 'sort_order' => 'asc']) }}" class="dropdown-item {{ ($sortBy === 'probation_end_date' && $sortOrder === 'asc') ? 'active' : '' }}">
                        <span>Probation End (Nearest)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'probation_end_date', 'sort_order' => 'desc']) }}" class="dropdown-item {{ ($sortBy === 'probation_end_date' && $sortOrder === 'desc') ? 'active' : '' }}">
                        <span>Probation End (Furthest)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'full_name', 'sort_order' => 'asc']) }}" class="dropdown-item {{ ($sortBy === 'full_name' && $sortOrder === 'asc') ? 'active' : '' }}">
                        <span>Name (A - Z)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'full_name', 'sort_order' => 'desc']) }}" class="dropdown-item {{ ($sortBy === 'full_name' && $sortOrder === 'desc') ? 'active' : '' }}">
                        <span>Name (Z - A)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'date_of_joining', 'sort_order' => 'desc']) }}" class="dropdown-item {{ ($sortBy === 'date_of_joining' && $sortOrder === 'desc') ? 'active' : '' }}">
                        <span>Joining Date (Newest)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'date_of_joining', 'sort_order' => 'asc']) }}" class="dropdown-item {{ ($sortBy === 'date_of_joining' && $sortOrder === 'asc') ? 'active' : '' }}">
                        <span>Joining Date (Oldest)</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Filter Dropdown -->
                <form method="GET" action="{{ route('hrms.probation.index') }}" class="d-inline">
                    <input type="hidden" name="status" value="{{ $filterStatus }}">
                    <input type="hidden" name="search" value="{{ $search }}">
                    <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                    <input type="hidden" name="sort_order" value="{{ $sortOrder }}">

                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Department</label>
                            <x-ui.odoo-form-ui type="select" name="department_id">
                                <option value="">All Departments</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" @selected($departmentId == $dept->id)>{{ $dept->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Evaluation Status</label>
                            <x-ui.odoo-form-ui type="select" name="eval_status">
                                <option value="">All Statuses</option>
                                <option value="reviewed" @selected($evalStatus === 'reviewed')>Reviewed</option>
                                <option value="unreviewed" @selected($evalStatus === 'unreviewed')>Not Reviewed Yet</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        
                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('hrms.probation.index', ['status' => $filterStatus]) }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- Table Container -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-dark">
                <thead class="table-light fs-11 text-uppercase tracking-wider">
                    <tr>
                        <th class="ps-3 py-3">Employee Details</th>
                        <th class="py-3">Department & Role</th>
                        <th class="py-3">Reporting Manager</th>
                        <th class="py-3">Joining Date</th>
                        <th class="py-3">Probation End Date</th>
                        <th class="py-3">Evaluation & Score</th>
                        <th class="text-end pe-3 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody id="probationTableBody">
                    @forelse($employees as $emp)
                        @php
                            $today = \Carbon\Carbon::today();
                            $probEnd = $emp->probation_end_date ? \Carbon\Carbon::parse($emp->probation_end_date) : null;
                            $isOverdue = $probEnd && $probEnd->isPast() && $emp->employee_stage === 'Probation';
                            $isDueSoon = $probEnd && $probEnd->isFuture() && $probEnd->diffInDays($today) <= 15 && $emp->employee_stage === 'Probation';
                            $lastEval = $emp->probationEvaluations->first();
                        @endphp
                        <tr>
                            <td class="ps-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="avatar-initials">
                                        {{ strtoupper(substr($emp->full_name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <a href="{{ route('hrms.employees.show', $emp->id) }}" class="fw-bold text-dark text-decoration-none d-block">
                                            {{ $emp->full_name }}
                                        </a>
                                        <span class="text-muted fs-12">{{ $emp->employee_id }} &bull; {{ $emp->personal_email ?: 'No email' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $emp->designation->name ?? 'N/A' }}</div>
                                <span class="text-muted fs-12">{{ $emp->department->name ?? 'General' }}</span>
                            </td>
                            <td>
                                @if($emp->reportingManager)
                                    <div class="fw-medium text-dark">{{ $emp->reportingManager->full_name }}</div>
                                    <span class="text-muted fs-12">{{ $emp->reportingManager->employee_id }}</span>
                                @else
                                    <span class="text-muted fs-12 fst-italic">Not Assigned</span>
                                @endif
                            </td>
                            <td>
                                <div class="fw-medium text-dark">{{ $emp->date_of_joining ? \Carbon\Carbon::parse($emp->date_of_joining)->format('d M, Y') : 'N/A' }}</div>
                                <span class="text-muted fs-12">{{ $emp->date_of_joining ? \Carbon\Carbon::parse($emp->date_of_joining)->diffForHumans() : '' }}</span>
                            </td>
                            <td>
                                @if($emp->employee_stage === 'Confirmed')
                                    <x-ui.badge soft variant="success">
                                        <i class="feather-check-circle me-1"></i> Confirmed: {{ $emp->confirmation_date ? \Carbon\Carbon::parse($emp->confirmation_date)->format('d M, Y') : 'Yes' }}
                                    </x-ui.badge>
                                @elseif($probEnd)
                                    <div class="fw-bold {{ $isOverdue ? 'text-danger' : ($isDueSoon ? 'text-warning' : 'text-dark') }}">
                                        {{ $probEnd->format('d M, Y') }}
                                    </div>
                                    @if($isOverdue)
                                        <x-ui.badge soft variant="danger" class="fs-11">
                                            Overdue by {{ abs($probEnd->diffInDays($today)) }} days
                                        </x-ui.badge>
                                    @elseif($isDueSoon)
                                        <x-ui.badge soft variant="warning" class="fs-11">
                                            Due in {{ $probEnd->diffInDays($today) }} days
                                        </x-ui.badge>
                                    @else
                                        <span class="text-muted fs-11">{{ $probEnd->diffForHumans() }}</span>
                                    @endif
                                @else
                                    <span class="text-muted fs-12">Not Set</span>
                                @endif
                            </td>
                            <td>
                                @if($lastEval)
                                    <div class="d-flex align-items-center gap-1 mb-1">
                                        <x-ui.badge soft variant="primary" class="fs-11">
                                            Rating: {{ $lastEval->average_rating }}/5 ★
                                        </x-ui.badge>
                                        <x-ui.badge soft variant="{{ $lastEval->recommendation === 'confirm' ? 'success' : ($lastEval->recommendation === 'extend' ? 'warning' : 'danger') }}" class="fs-11 text-uppercase">
                                            {{ $lastEval->recommendation }}
                                        </x-ui.badge>
                                    </div>
                                    <span class="text-muted fs-11">Last review: {{ $lastEval->evaluation_date ? \Carbon\Carbon::parse($lastEval->evaluation_date)->format('d M, Y') : 'N/A' }}</span>
                                @else
                                    <span class="badge bg-light text-secondary border fs-11">No Review Logged</span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-flex align-items-center justify-content-end gap-2">
                                    @if($emp->employee_stage === 'Probation')
                                        <x-ui.button variant="primary" size="sm" icon="feather-check-square" data-bs-toggle="modal" data-bs-target="#evaluateModal_{{ $emp->id }}" class="fw-semibold">
                                            Review & Evaluate
                                        </x-ui.button>

                                        <x-ui.button variant="outline-success" size="sm" icon="feather-award" data-bs-toggle="modal" data-bs-target="#confirmEmployeeModal_{{ $emp->id }}" class="fw-semibold" title="Confirm Employee">
                                            Confirm
                                        </x-ui.button>
                                    @else
                                        <x-ui.badge soft variant="success" class="px-3 py-2">
                                            <i class="feather-check me-1"></i> Confirmed
                                        </x-ui.badge>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <div class="avatar-text avatar-lg bg-soft-primary text-primary rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                                    <i class="feather-user-check fs-24"></i>
                                </div>
                                <h6 class="fw-bold mb-1 text-dark">No employees found</h6>
                                <p class="fs-13 mb-0 text-muted">No employees match the selected probation status or department filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div id="probationPaginationWrapper">
            @if($employees->hasPages())
                <div class="pt-4 border-top mt-3 d-flex justify-content-between align-items-center">
                    <span class="text-muted fs-13">Showing {{ $employees->firstItem() ?? 0 }} to {{ $employees->lastItem() ?? 0 }} of {{ $employees->total() }} records</span>
                    {{ $employees->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Review & Evaluation Modals Container -->
    <div id="probationModalsWrapper">
        @foreach($employees as $emp)
            @if($emp->employee_stage === 'Probation')
                <div class="modal fade text-start" id="evaluateModal_{{ $emp->id }}" tabindex="-1" aria-labelledby="evaluateModalLabel_{{ $emp->id }}" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content border-0 shadow-lg rounded-4">
                            <div class="modal-header bg-light border-bottom p-4">
                                <div>
                                    <h5 class="modal-title fw-bold text-dark mb-1" id="evaluateModalLabel_{{ $emp->id }}">Probation Performance Evaluation</h5>
                                    <p class="text-muted fs-13 mb-0">Evaluate performance, attendance, and record decision for <strong>{{ $emp->full_name }}</strong> ({{ $emp->employee_id }})</p>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="{{ route('hrms.probation.evaluate', $emp->id) }}">
                                @csrf
                                <div class="modal-body p-4">
                                    <!-- Ratings Section (Individual Full-Width Rows) -->
                                    <div class="row g-3 mb-4">
                                        <div class="col-12">
                                            <x-ui.odoo-form-ui type="select" label="1. Performance & Execution" name="performance_rating" :required="true">
                                                <option value="5">★★★★★ - Outstanding (5)</option>
                                                <option value="4" selected>★★★★☆ - Exceeds Expectations (4)</option>
                                                <option value="3">★★★☆☆ - Meets Expectations (3)</option>
                                                <option value="2">★★☆☆☆ - Needs Improvement (2)</option>
                                                <option value="1">★☆☆☆☆ - Unsatisfactory (1)</option>
                                            </x-ui.odoo-form-ui>
                                        </div>
                                        <div class="col-12">
                                            <x-ui.odoo-form-ui type="select" label="2. Attendance & Punctuality" name="attendance_rating" :required="true">
                                                <option value="5">★★★★★ - Excellent (5)</option>
                                                <option value="4" selected>★★★★☆ - Very Good (4)</option>
                                                <option value="3">★★★☆☆ - Good / Satisfactory (3)</option>
                                                <option value="2">★★☆☆☆ - Frequent Delays (2)</option>
                                                <option value="1">★☆☆☆☆ - Poor Attendance (1)</option>
                                            </x-ui.odoo-form-ui>
                                        </div>
                                        <div class="col-12">
                                            <x-ui.odoo-form-ui type="select" label="3. Culture Fit & Teamwork" name="culture_rating" :required="true">
                                                <option value="5">★★★★★ - Role Model (5)</option>
                                                <option value="4" selected>★★★★☆ - Highly Collaborative (4)</option>
                                                <option value="3">★★★☆☆ - Good Team Player (3)</option>
                                                <option value="2">★★☆☆☆ - Struggling to Adapt (2)</option>
                                                <option value="1">★☆☆☆☆ - Misaligned (1)</option>
                                            </x-ui.odoo-form-ui>
                                        </div>
                                    </div>

                                    <!-- Recommendation Box -->
                                    <div class="p-3 bg-light rounded-3 border mb-4">
                                        <label class="form-label fw-bold text-dark fs-12 text-uppercase mb-2 d-block">Final Recommendation</label>
                                        <div class="d-flex align-items-center gap-4 flex-wrap mt-1">
                                            <div class="form-check erp-premium-radio d-flex align-items-center gap-2 m-0">
                                                <input class="form-check-input" type="radio" name="recommendation" id="rec_confirm_{{ $emp->id }}" value="confirm" checked onchange="handleRecommendationChange({{ $emp->id }}, 'confirm')">
                                                <label class="form-check-label fw-semibold text-success fs-13 d-flex align-items-center gap-1.5 cursor-pointer" for="rec_confirm_{{ $emp->id }}">
                                                    <i class="feather-check-circle fs-15"></i> Formally Confirm Employment
                                                </label>
                                            </div>
                                            <div class="form-check erp-premium-radio d-flex align-items-center gap-2 m-0">
                                                <input class="form-check-input" type="radio" name="recommendation" id="rec_extend_{{ $emp->id }}" value="extend" onchange="handleRecommendationChange({{ $emp->id }}, 'extend')">
                                                <label class="form-check-label fw-semibold text-warning fs-13 d-flex align-items-center gap-1.5 cursor-pointer" for="rec_extend_{{ $emp->id }}">
                                                    <i class="feather-refresh-cw fs-15"></i> Extend Probation Period
                                                </label>
                                            </div>
                                            <div class="form-check erp-premium-radio d-flex align-items-center gap-2 m-0">
                                                <input class="form-check-input" type="radio" name="recommendation" id="rec_terminate_{{ $emp->id }}" value="terminate" onchange="handleRecommendationChange({{ $emp->id }}, 'terminate')">
                                                <label class="form-check-label fw-semibold text-danger fs-13 d-flex align-items-center gap-1.5 cursor-pointer" for="rec_terminate_{{ $emp->id }}">
                                                    <i class="feather-x-circle fs-15"></i> Recommend Termination
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Extension Options Box -->
                                        <div id="extension_container_{{ $emp->id }}" class="mt-3 p-3 bg-white rounded-3 border border-warning border-opacity-25 d-none">
                                            <div style="max-width: 380px;">
                                                <x-ui.odoo-form-ui type="select" label="Extension Duration" name="extension_days">
                                                    <option value="30">30 Days (1 Month)</option>
                                                    <option value="60">60 Days (2 Months)</option>
                                                    <option value="90">90 Days (3 Months)</option>
                                                </x-ui.odoo-form-ui>
                                            </div>
                                        </div>

                                        <!-- Termination Details Box -->
                                        <div id="termination_container_{{ $emp->id }}" class="mt-3 p-3 bg-white rounded-3 border border-danger border-opacity-25 d-none">
                                            <div class="d-flex align-items-center gap-2 mb-2 text-danger fw-bold fs-13">
                                                <i class="feather-alert-triangle"></i> Involuntary Separation & Offboarding Details
                                            </div>
                                            <p class="text-muted fs-12 mb-3">
                                                Submitting termination will automatically initiate an Exit Case in the Offboarding Hub, set the Last Working Day (LWD), and assign multi-department clearance checklists (IT, Admin, Finance, HR).
                                            </p>

                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label fw-bold fs-12 text-uppercase text-muted mb-1 d-block">Termination Mode</label>
                                                    <div class="d-flex gap-3 mt-2">
                                                        <div class="form-check erp-premium-radio d-flex align-items-center gap-2 m-0">
                                                            <input class="form-check-input" type="radio" name="termination_mode" id="term_mode_notice_{{ $emp->id }}" value="notice" checked onchange="toggleTerminationNotice({{ $emp->id }}, true)">
                                                            <label class="form-check-label fs-13 text-dark cursor-pointer" for="term_mode_notice_{{ $emp->id }}">
                                                                Serve Probation Notice
                                                            </label>
                                                        </div>
                                                        <div class="form-check erp-premium-radio d-flex align-items-center gap-2 m-0">
                                                            <input class="form-check-input" type="radio" name="termination_mode" id="term_mode_imm_{{ $emp->id }}" value="immediate" onchange="toggleTerminationNotice({{ $emp->id }}, false)">
                                                            <label class="form-check-label fs-13 text-dark cursor-pointer" for="term_mode_imm_{{ $emp->id }}">
                                                                Immediate (Today)
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6" id="term_notice_days_box_{{ $emp->id }}">
                                                    <x-ui.odoo-form-ui type="select" label="Notice Duration" name="termination_notice_days">
                                                        <option value="7">7 Days Notice</option>
                                                        <option value="15" selected>15 Days Notice</option>
                                                        <option value="30">30 Days Notice</option>
                                                    </x-ui.odoo-form-ui>
                                                </div>

                                                <div class="col-12">
                                                    <x-ui.odoo-form-ui type="select" label="Primary Reason Category" name="termination_reason_category">
                                                        <option value="Performance / Skill Gap">Performance / Skill Gap</option>
                                                        <option value="Cultural / Team Misalignment">Cultural / Team Misalignment</option>
                                                        <option value="Attendance & Discipline">Attendance & Punctuality Issues</option>
                                                        <option value="Role Fit / Restructuring">Role Fit / Operational Restructuring</option>
                                                        <option value="Probation Unsuccessful" selected>General Probation Unsuccessful</option>
                                                    </x-ui.odoo-form-ui>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold fs-12 text-uppercase text-muted mb-1.5 d-block">Evaluation Comments & Notes</label>
                                        <textarea name="remarks" class="form-control" rows="3" placeholder="Provide notes, strengths, feedback, or justification..."></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light border-top p-3">
                                    <x-ui.button variant="light" data-bs-dismiss="modal" class="border px-4 fw-semibold">
                                        Cancel
                                    </x-ui.button>
                                    <x-ui.button variant="primary" type="submit" class="px-4 fw-bold">
                                        Submit Evaluation
                                    </x-ui.button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Confirm Employee Modal (Replaces browser alert with structured confirmation modal) -->
                <div class="modal fade text-start" id="confirmEmployeeModal_{{ $emp->id }}" tabindex="-1" aria-labelledby="confirmEmployeeModalLabel_{{ $emp->id }}" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg rounded-4">
                            <div class="modal-header bg-light border-bottom p-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="d-flex align-items-center justify-content-center rounded-circle bg-soft-success text-success" style="width: 36px; height: 36px;">
                                        <i class="feather-award fs-18"></i>
                                    </div>
                                    <div>
                                        <h6 class="modal-title fw-bold text-dark mb-0" id="confirmEmployeeModalLabel_{{ $emp->id }}">Confirm Employee from Probation</h6>
                                        <span class="text-muted fs-11">Official Permanent Confirmation Decision</span>
                                    </div>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="{{ route('hrms.probation.quick-confirm', $emp->id) }}">
                                @csrf
                                <div class="modal-body p-4">
                                    <!-- Employee Summary Card -->
                                    <div class="p-3 bg-light rounded-3 border mb-3">
                                        <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                                            <div>
                                                <div class="fw-bold text-dark fs-13">{{ $emp->full_name }}</div>
                                                <div class="text-muted fs-11">{{ $emp->employee_id }} &bull; {{ $emp->designation->name ?? 'N/A' }}</div>
                                            </div>
                                            <x-ui.badge soft variant="warning" class="fs-10">
                                                {{ $emp->employee_stage }}
                                            </x-ui.badge>
                                        </div>
                                        <div class="row g-2 fs-11 text-muted">
                                            <div class="col-6">
                                                <span>Department:</span> <strong class="text-dark">{{ $emp->department->name ?? 'General' }}</strong>
                                            </div>
                                            <div class="col-6">
                                                <span>Joining Date:</span> <strong class="text-dark">{{ $emp->date_of_joining ? \Carbon\Carbon::parse($emp->date_of_joining)->format('d M, Y') : 'N/A' }}</strong>
                                            </div>
                                            <div class="col-6">
                                                <span>Probation End:</span> <strong class="text-dark">{{ $emp->probation_end_date ? \Carbon\Carbon::parse($emp->probation_end_date)->format('d M, Y') : 'N/A' }}</strong>
                                            </div>
                                            <div class="col-6">
                                                <span>Manager:</span> <strong class="text-dark">{{ $emp->reportingManager->full_name ?? 'N/A' }}</strong>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-success bg-soft-success border border-success border-opacity-25 rounded-3 p-2.5 mb-3 fs-12 text-dark">
                                        <i class="feather-check-circle text-success me-1"></i>
                                        Confirming will transition this employee's stage to <strong>Confirmed</strong> and officially complete their probation period.
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Effective Confirmation Date <span class="text-danger">*</span></label>
                                        <x-ui.odoo-form-ui type="input" inputType="date" name="confirmation_date" :value="date('Y-m-d')" :required="true" />
                                    </div>

                                    <div class="mb-0">
                                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Confirmation Remarks / Decision Notes</label>
                                        <textarea name="remarks" class="form-control fs-12" rows="2" placeholder="e.g. Completed probation period satisfactorily with positive manager recommendation..."></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light border-top p-3 d-flex justify-content-between">
                                    <x-ui.button variant="light" data-bs-dismiss="modal" class="border px-3 fw-semibold">Cancel</x-ui.button>
                                    <x-ui.button variant="success" type="submit" class="px-4 fw-bold">
                                        <i class="feather-check-circle me-1"></i> Confirm Employee
                                    </x-ui.button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </div>
@endsection

@push('scripts')
<script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
<script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
<script>
    function handleRecommendationChange(empId, recValue) {
        const extBox = document.getElementById('extension_container_' + empId);
        const termBox = document.getElementById('termination_container_' + empId);
        
        if (extBox) extBox.classList.add('d-none');
        if (termBox) termBox.classList.add('d-none');

        if (recValue === 'extend' && extBox) {
            extBox.classList.remove('d-none');
        } else if (recValue === 'terminate' && termBox) {
            termBox.classList.remove('d-none');
        }
    }

    function toggleTerminationNotice(empId, showNotice) {
        const noticeBox = document.getElementById('term_notice_days_box_' + empId);
        if (noticeBox) {
            if (showNotice) {
                noticeBox.classList.remove('d-none');
            } else {
                noticeBox.classList.add('d-none');
            }
        }
    }

    function moveProbationModalsToBody() {
        $('#probationModalsWrapper .modal, .modal[id^="evaluateModal_"], .modal[id^="confirmEmployeeModal_"]').each(function() {
            if ($(this).parent().get(0) !== document.body) {
                $(this).appendTo(document.body);
            }
        });
    }

    $(document).ready(function() {
        moveProbationModalsToBody();
    });

    $(document).on('show.bs.modal', '.modal', function () {
        if ($(this).parent().get(0) !== document.body) {
            $(this).appendTo(document.body);
        }
    });

    let probationSearchTimeout = null;
    let activeProbationRequest = null;

    function refreshProbationList(targetUrl) {
        if (activeProbationRequest) {
            activeProbationRequest.abort();
        }
        const controller = new AbortController();
        activeProbationRequest = controller;

        const tableBody = document.getElementById('probationTableBody');
        if (tableBody) {
            tableBody.style.opacity = '0.5';
        }

        fetch(targetUrl.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: controller.signal,
        })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('Unable to refresh list.');
            }
            return response.text();
        })
        .then(function (html) {
            const doc = new DOMParser().parseFromString(html, 'text/html');

            const newTbody = doc.getElementById('probationTableBody');
            const oldTbody = document.getElementById('probationTableBody');
            if (newTbody && oldTbody) {
                oldTbody.innerHTML = newTbody.innerHTML;
            }

            const newPagination = doc.getElementById('probationPaginationWrapper');
            const oldPagination = document.getElementById('probationPaginationWrapper');
            if (newPagination && oldPagination) {
                oldPagination.innerHTML = newPagination.innerHTML;
            }

            const newModals = doc.getElementById('probationModalsWrapper');
            const oldModals = document.getElementById('probationModalsWrapper');
            if (newModals && oldModals) {
                oldModals.innerHTML = newModals.innerHTML;
                moveProbationModalsToBody();
            }

            // Sync hidden inputs in filter dropdowns
            const searchVal = targetUrl.searchParams.get('search') || '';
            $('input[name="search"]').not('#probation_search_input').val(searchVal);

            // Update browser URL without full page reload
            history.pushState(null, '', targetUrl.toString());
        })
        .catch(function (error) {
            if (error.name !== 'AbortError') {
                window.location.href = targetUrl.toString();
            }
        })
        .finally(function () {
            if (activeProbationRequest === controller) {
                if (tableBody) {
                    tableBody.style.opacity = '1';
                }
                activeProbationRequest = null;
            }
        });
    }

    // 1. Debounced live search on typing (works automatically as you write without clicking Enter)
    $(document).on('input', '#probation_search_input', function () {
        const form = this.closest('form');
        if (!form) return;
        const url = new URL(form.action || window.location.href);
        
        const formData = new FormData(form);
        for (const [key, val] of formData.entries()) {
            url.searchParams.set(key, val);
        }
        url.searchParams.delete('page');

        clearTimeout(probationSearchTimeout);
        probationSearchTimeout = setTimeout(function () {
            refreshProbationList(url);
        }, 250);
    });

    // 2. Form submit prevention for search form
    $(document).on('submit', '#probationSearchForm', function (e) {
        e.preventDefault();
        const url = new URL(this.action || window.location.href);
        const formData = new FormData(this);
        for (const [key, val] of formData.entries()) {
            url.searchParams.set(key, val);
        }
        url.searchParams.delete('page');
        clearTimeout(probationSearchTimeout);
        refreshProbationList(url);
    });

    // 3. Intercept pagination clicks for instant page changes
    $(document).on('click', '#probationPaginationWrapper a.page-link', function (e) {
        e.preventDefault();
        const href = this.getAttribute('href');
        if (href && href !== '#' && !href.startsWith('javascript:')) {
            const url = new URL(href, window.location.origin);
            refreshProbationList(url);
        }
    });

    $(document).on('shown.bs.modal', '[id^="evaluateModal_"]', function () {
        var $modal = $(this);
        $modal.find('.probation-select2').each(function() {
            var $select = $(this);
            if (!$select.hasClass('select2-hidden-accessible')) {
                $select.select2({
                    theme: 'bootstrap-5',
                    dropdownParent: $modal.find('.modal-content'),
                    minimumResultsForSearch: Infinity,
                    width: '100%'
                });
            }
        });
    });
</script>
@endpush
