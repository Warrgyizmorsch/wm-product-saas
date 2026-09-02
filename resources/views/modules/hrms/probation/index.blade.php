@extends('layouts.duralux')

@section('title', 'Probation Reviews & Evaluations | SaaS ERP')
@section('page-title', 'Probation Reviews')
@section('breadcrumb', 'HRMS / Lifecycle / Probation Reviews')

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
                <form method="GET" action="{{ route('hrms.probation.index') }}" class="d-flex align-items-center m-0">
                    <input type="hidden" name="status" value="{{ $filterStatus }}">
                    <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                    <input type="hidden" name="sort_order" value="{{ $sortOrder }}">
                    <input type="hidden" name="department_id" value="{{ $departmentId }}">
                    <input type="hidden" name="eval_status" value="{{ $evalStatus }}">
                    
                    <div class="d-flex align-items-center bg-light border rounded px-3 py-1" style="min-width: 240px; height: 36px;">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" name="search" value="{{ $search }}" class="form-control border-0 bg-transparent p-0 fs-13 text-dark" placeholder="Search employee..." style="box-shadow: none; height: 100%; outline: none;" onchange="this.form.submit()">
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
                <tbody>
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

                                        <form method="POST" action="{{ route('hrms.probation.quick-confirm', $emp->id) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to directly confirm {{ $emp->full_name }}?');">
                                            @csrf
                                            <x-ui.button variant="outline-success" size="sm" icon="feather-award" type="submit" class="fw-semibold" title="1-Click Quick Confirm">
                                                Confirm
                                            </x-ui.button>
                                        </form>
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

        @if($employees->hasPages())
            <div class="pt-4 border-top mt-3 d-flex justify-content-between align-items-center">
                <span class="text-muted fs-13">Showing {{ $employees->firstItem() ?? 0 }} to {{ $employees->lastItem() ?? 0 }} of {{ $employees->total() }} records</span>
                {{ $employees->links() }}
            </div>
        @endif
    </div>

    <!-- Review & Evaluation Modals (Positioned outside single panel to prevent backdrop issues) -->
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
                                <!-- Ratings Row -->
                                <div class="row g-3 mb-4">
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold fs-13 text-dark">1. Performance & Execution</label>
                                        <select name="performance_rating" class="form-select" required>
                                            <option value="5">★★★★★ - Outstanding (5)</option>
                                            <option value="4" selected>★★★★☆ - Exceeds Expectations (4)</option>
                                            <option value="3">★★★☆☆ - Meets Expectations (3)</option>
                                            <option value="2">★★☆☆☆ - Needs Improvement (2)</option>
                                            <option value="1">★☆☆☆☆ - Unsatisfactory (1)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold fs-13 text-dark">2. Attendance & Punctuality</label>
                                        <select name="attendance_rating" class="form-select" required>
                                            <option value="5">★★★★★ - Excellent (5)</option>
                                            <option value="4" selected>★★★★☆ - Very Good (4)</option>
                                            <option value="3">★★★☆☆ - Good / Satisfactory (3)</option>
                                            <option value="2">★★☆☆☆ - Frequent Delays (2)</option>
                                            <option value="1">★☆☆☆☆ - Poor Attendance (1)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold fs-13 text-dark">3. Culture Fit & Teamwork</label>
                                        <select name="culture_rating" class="form-select" required>
                                            <option value="5">★★★★★ - Role Model (5)</option>
                                            <option value="4" selected>★★★★☆ - Highly Collaborative (4)</option>
                                            <option value="3">★★★☆☆ - Good Team Player (3)</option>
                                            <option value="2">★★☆☆☆ - Struggling to Adapt (2)</option>
                                            <option value="1">★☆☆☆☆ - Misaligned (1)</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Recommendation Box -->
                                <div class="p-3 bg-light rounded-3 border mb-4">
                                    <label class="form-label fw-bold text-dark fs-13">Final Recommendation</label>
                                    <div class="d-flex gap-4 flex-wrap mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="recommendation" id="rec_confirm_{{ $emp->id }}" value="confirm" checked onchange="handleRecommendationChange({{ $emp->id }}, 'confirm')">
                                            <label class="form-check-label fw-semibold text-success" for="rec_confirm_{{ $emp->id }}">
                                                <i class="feather-check-circle me-1"></i> Formally Confirm Employment
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="recommendation" id="rec_extend_{{ $emp->id }}" value="extend" onchange="handleRecommendationChange({{ $emp->id }}, 'extend')">
                                            <label class="form-check-label fw-semibold text-warning" for="rec_extend_{{ $emp->id }}">
                                                <i class="feather-refresh-cw me-1"></i> Extend Probation Period
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="recommendation" id="rec_terminate_{{ $emp->id }}" value="terminate" onchange="handleRecommendationChange({{ $emp->id }}, 'terminate')">
                                            <label class="form-check-label fw-semibold text-danger" for="rec_terminate_{{ $emp->id }}">
                                                <i class="feather-x-circle me-1"></i> Recommend Termination
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Extension Options Box -->
                                    <div id="extension_container_{{ $emp->id }}" class="mt-3 p-3 bg-white rounded-3 border border-warning border-opacity-25 d-none">
                                        <label class="form-label fw-bold fs-13 text-dark mb-1">Extension Duration (Days)</label>
                                        <select name="extension_days" class="form-select form-select-sm" style="max-width: 250px;">
                                            <option value="30">30 Days (1 Month)</option>
                                            <option value="60">60 Days (2 Months)</option>
                                            <option value="90">90 Days (3 Months)</option>
                                        </select>
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
                                                <label class="form-label fw-bold fs-12 text-dark">Termination Mode</label>
                                                <div class="d-flex gap-3 mt-1">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="termination_mode" id="term_mode_notice_{{ $emp->id }}" value="notice" checked onchange="toggleTerminationNotice({{ $emp->id }}, true)">
                                                        <label class="form-check-label fs-13 text-dark" for="term_mode_notice_{{ $emp->id }}">
                                                            Serve Probation Notice
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="termination_mode" id="term_mode_imm_{{ $emp->id }}" value="immediate" onchange="toggleTerminationNotice({{ $emp->id }}, false)">
                                                        <label class="form-check-label fs-13 text-dark" for="term_mode_imm_{{ $emp->id }}">
                                                            Immediate (Today)
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-6" id="term_notice_days_box_{{ $emp->id }}">
                                                <label class="form-label fw-bold fs-12 text-dark">Probation Notice Duration</label>
                                                <select name="termination_notice_days" class="form-select form-select-sm">
                                                    <option value="7">7 Days Notice</option>
                                                    <option value="15" selected>15 Days Notice</option>
                                                    <option value="30">30 Days Notice</option>
                                                </select>
                                            </div>

                                            <div class="col-12">
                                                <label class="form-label fw-bold fs-12 text-dark">Primary Reason Category</label>
                                                <select name="termination_reason_category" class="form-select form-select-sm">
                                                    <option value="Performance / Skill Gap">Performance / Skill Gap</option>
                                                    <option value="Cultural / Team Misalignment">Cultural / Team Misalignment</option>
                                                    <option value="Attendance & Discipline">Attendance & Punctuality Issues</option>
                                                    <option value="Role Fit / Restructuring">Role Fit / Operational Restructuring</option>
                                                    <option value="Probation Unsuccessful" selected>General Probation Unsuccessful</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-13 text-dark">Evaluation Comments & Notes</label>
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
        @endif
    @endforeach
@endsection

@push('scripts')
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

    $(document).ready(function() {
        // Ensure all evaluation modals are appended directly to body to avoid backdrop stacking context issues
        $('[id^="evaluateModal_"]').appendTo('body');
    });
</script>
@endpush
