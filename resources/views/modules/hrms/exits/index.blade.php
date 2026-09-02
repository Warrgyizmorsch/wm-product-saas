@extends('layouts.duralux')

@section('title', 'Exit & Offboarding Management | HRMS')
@section('page-title', 'Exit & Offboarding')
@section('breadcrumb', 'HRMS / Lifecycle / Exit & Offboarding')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="light" icon="feather-users" href="{{ route('hrms.employees.index') }}" class="border text-dark fw-semibold">
            Employee Directory
        </x-ui.button>
        <x-ui.button variant="primary" icon="feather-user-minus" data-bs-toggle="modal" data-bs-target="#initiateExitModal" class="fw-bold text-uppercase">
            Initiate Exit
        </x-ui.button>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        #exitTabs .nav-link {
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
        #exitTabs .nav-link:hover {
            color: var(--bs-primary);
        }
        #exitTabs .nav-link.active {
            color: var(--bs-primary) !important;
            border-bottom: 2px solid var(--bs-primary) !important;
            font-weight: 600;
        }
        .avatar-initials {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background-color: rgba(var(--bs-danger-rgb), 0.1);
            color: var(--bs-danger);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
        }
        .btn-doc-card {
            border: 1px solid #cbd5e1 !important;
            background-color: #ffffff !important;
            color: #334155 !important;
            padding: 5px 11px !important;
            border-radius: 6px !important;
            font-weight: 500 !important;
            font-size: 12px !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
            transition: all 0.15s ease-in-out !important;
            text-decoration: none !important;
        }
        .btn-doc-card:hover {
            border-color: #64748b !important;
            background-color: #f8fafc !important;
            color: #0f172a !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06) !important;
        }
        .clearance-item-box {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
            background: #ffffff;
            transition: all 0.2s;
        }
        .clearance-item-box:hover {
            border-color: #cbd5e1;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        }
    </style>
@endpush

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4 text-dark" role="alert">
            <i class="feather-check-circle me-2 text-success"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4 text-dark" role="alert">
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
            <ul class="nav gap-1" id="exitTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'exits' ? 'active' : '' }}" href="{{ route('hrms.exits.index', ['tab' => 'exits', 'search' => $search, 'department_id' => $departmentId, 'status' => $statusFilter, 'sort_by' => $sortBy, 'sort_order' => $sortOrder]) }}">
                        <i class="feather-list"></i>
                        <span>Exit Cases</span>
                        <x-ui.badge soft variant="primary" class="ms-1">{{ $activeExitsCount }}</x-ui.badge>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'clearances' ? 'active' : '' }}" href="{{ route('hrms.exits.index', ['tab' => 'clearances', 'search' => $search, 'department_id' => $departmentId, 'status' => $statusFilter, 'sort_by' => $sortBy, 'sort_order' => $sortOrder]) }}">
                        <i class="feather-check-square"></i>
                        <span>Clearance & NOCs</span>
                        <x-ui.badge soft variant="warning" class="ms-1">{{ $inClearanceCount }}</x-ui.badge>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'assets' ? 'active' : '' }}" href="{{ route('hrms.exits.index', ['tab' => 'assets', 'search' => $search, 'department_id' => $departmentId, 'status' => $statusFilter, 'sort_by' => $sortBy, 'sort_order' => $sortOrder]) }}">
                        <i class="feather-package"></i>
                        <span>Asset Recovery</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'fnf' ? 'active' : '' }}" href="{{ route('hrms.exits.index', ['tab' => 'fnf', 'search' => $search, 'department_id' => $departmentId, 'status' => $statusFilter, 'sort_by' => $sortBy, 'sort_order' => $sortOrder]) }}">
                        <i class="feather-credit-card"></i>
                        <span>FnF Settlements</span>
                        <x-ui.badge soft variant="info" class="ms-1">{{ $pendingFnfCount }}</x-ui.badge>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $activeTab === 'documents' ? 'active' : '' }}" href="{{ route('hrms.exits.index', ['tab' => 'documents', 'search' => $search, 'department_id' => $departmentId, 'status' => $statusFilter, 'sort_by' => $sortBy, 'sort_order' => $sortOrder]) }}">
                        <i class="feather-file-text"></i>
                        <span>Relieving Certificates</span>
                        <x-ui.badge soft variant="success" class="ms-1">{{ $settledExitsCount }}</x-ui.badge>
                    </a>
                </li>
            </ul>

            <!-- Right: Search, Sort & Filter Toolbar (Common UI Elements) -->
            <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
                <!-- Search Form -->
                <form method="GET" action="{{ route('hrms.exits.index') }}" class="d-flex align-items-center m-0">
                    <input type="hidden" name="tab" value="{{ $activeTab }}">
                    <input type="hidden" name="status" value="{{ $statusFilter }}">
                    <input type="hidden" name="department_id" value="{{ $departmentId }}">
                    <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                    <input type="hidden" name="sort_order" value="{{ $sortOrder }}">
                    
                    <div class="d-flex align-items-center bg-light border rounded px-3 py-1" style="min-width: 240px; height: 36px;">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" name="search" value="{{ $search }}" class="form-control border-0 bg-transparent p-0 fs-13 text-dark" placeholder="Search exit cases..." style="box-shadow: none; height: 100%; outline: none;" onchange="this.form.submit()">
                    </div>
                </form>

                <!-- Sort Dropdown -->
                <x-ui.sort-dropdown label="Sort">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'desc']) }}" class="dropdown-item {{ ($sortBy === 'created_at' && $sortOrder === 'desc') ? 'active' : '' }}">
                        <span>Date Created (Newest)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'asc']) }}" class="dropdown-item {{ ($sortBy === 'created_at' && $sortOrder === 'asc') ? 'active' : '' }}">
                        <span>Date Created (Oldest)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'lwd', 'sort_order' => 'asc']) }}" class="dropdown-item {{ ($sortBy === 'lwd' && $sortOrder === 'asc') ? 'active' : '' }}">
                        <span>Last Working Day (Nearest)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'lwd', 'sort_order' => 'desc']) }}" class="dropdown-item {{ ($sortBy === 'lwd' && $sortOrder === 'desc') ? 'active' : '' }}">
                        <span>Last Working Day (Furthest)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'employee', 'sort_order' => 'asc']) }}" class="dropdown-item {{ ($sortBy === 'employee' && $sortOrder === 'asc') ? 'active' : '' }}">
                        <span>Employee Name (A - Z)</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Filter Dropdown -->
                <form method="GET" action="{{ route('hrms.exits.index') }}" class="d-inline">
                    <input type="hidden" name="tab" value="{{ $activeTab }}">
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
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Exit Status</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">All Statuses</option>
                                <option value="in_clearance" @selected($statusFilter === 'in_clearance')>In Clearance</option>
                                <option value="approved" @selected($statusFilter === 'approved')>Approved (Serving Notice)</option>
                                <option value="settled" @selected($statusFilter === 'settled')>Settled & Exited</option>
                                <option value="rejected" @selected($statusFilter === 'rejected')>Rejected</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        
                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('hrms.exits.index', ['tab' => $activeTab]) }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- TAB CONTENT AREA -->
        @if($activeTab === 'exits')
            <!-- TAB 1: EXIT CASES LIST -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-dark">
                    <thead class="table-light fs-11 text-uppercase tracking-wider">
                        <tr>
                            <th class="ps-3 py-3">Employee Details</th>
                            <th class="py-3">Separation Type</th>
                            <th class="py-3">Resignation Date</th>
                            <th class="py-3">Last Working Day (LWD)</th>
                            <th class="py-3">Clearance Status</th>
                            <th class="py-3">Exit Status</th>
                            <th class="text-end pe-3 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($exits as $exit)
                            @php
                                $emp = $exit->employee;
                                $progress = $exit->getClearanceProgressPercentage();
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
                                            <span class="text-muted fs-12">{{ $emp->employee_id }} &bull; {{ $emp->designation->name ?? 'N/A' }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <x-ui.badge soft variant="secondary" class="text-uppercase">
                                        {{ ucfirst(str_replace('_', ' ', $exit->separation_type)) }}
                                    </x-ui.badge>
                                    <div class="text-muted fs-11 mt-1">{{ $exit->reason_category }}</div>
                                </td>
                                <td>
                                    <div class="fw-medium text-dark">{{ $exit->resignation_date ? \Carbon\Carbon::parse($exit->resignation_date)->format('d M, Y') : 'N/A' }}</div>
                                    <span class="text-muted fs-11">Notice: {{ $exit->notice_period_days }} days</span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark d-flex align-items-center gap-1">
                                        {{ $exit->effective_lwd ? \Carbon\Carbon::parse($exit->effective_lwd)->format('d M, Y') : 'Pending Decision' }}
                                        @if($exit->status !== 'settled' && $exit->status !== 'rejected')
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#approveExitModal_{{ $exit->id }}" class="text-primary fs-12 ms-1 text-decoration-none" title="Change / Reschedule LWD">
                                                <i class="feather-edit-2"></i>
                                            </a>
                                        @endif
                                    </div>
                                    @if($exit->approved_lwd)
                                        <span class="text-success fs-11"><i class="feather-check me-1"></i> Approved LWD</span>
                                    @else
                                        <span class="text-warning fs-11"><i class="feather-calendar me-1"></i> Preferred: {{ $exit->preferred_lwd ? \Carbon\Carbon::parse($exit->preferred_lwd)->format('d M, Y') : 'None' }}</span>
                                    @endif
                                </td>
                                <td style="min-width: 150px;">
                                    <div class="d-flex align-items-center justify-content-between fs-11 mb-1">
                                        <span class="fw-semibold">NOC Sign-offs</span>
                                        <span class="fw-bold {{ $progress === 100 ? 'text-success' : 'text-primary' }}">{{ $progress }}%</span>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar {{ $progress === 100 ? 'bg-success' : 'bg-primary' }}" role="progressbar" style="width: {{ $progress }}%"></div>
                                    </div>
                                </td>
                                <td>
                                    @if($exit->status === 'settled')
                                        <x-ui.badge soft variant="success">
                                            <i class="feather-check-circle me-1"></i> Settled & Exited
                                        </x-ui.badge>
                                    @elseif($exit->status === 'in_clearance')
                                        <x-ui.badge soft variant="warning">
                                            <i class="feather-shield me-1"></i> In Clearance
                                        </x-ui.badge>
                                    @elseif($exit->status === 'rejected')
                                        <x-ui.badge soft variant="danger">
                                            <i class="feather-x-circle me-1"></i> Rejected
                                        </x-ui.badge>
                                    @else
                                        <x-ui.badge soft variant="info">
                                            {{ ucfirst(str_replace('_', ' ', $exit->status)) }}
                                        </x-ui.badge>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-flex align-items-center justify-content-end gap-1.5">
                                        <x-ui.button variant="primary" size="sm" icon="feather-check-square" href="{{ route('hrms.exits.index', ['tab' => 'clearances']) }}" class="fw-semibold py-1 px-2.5 fs-12">
                                            NOC Hub
                                        </x-ui.button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <div class="avatar-text avatar-lg bg-soft-primary text-primary rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                                        <i class="feather-user-minus fs-24"></i>
                                    </div>
                                    <h6 class="fw-bold mb-1 text-dark">No exit cases found</h6>
                                    <p class="fs-13 mb-0 text-muted">There are no active resignation or termination records matching the current filters.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        @elseif($activeTab === 'clearances')
            <!-- TAB 2: MULTI-DEPARTMENT CLEARANCE HUB -->
            <div class="row g-4">
                @forelse($exits->where('status', '!=', 'rejected') as $exit)
                    @php
                        $emp = $exit->employee;
                        $progress = $exit->getClearanceProgressPercentage();
                        $deptClearances = $exit->clearances->groupBy('department');
                    @endphp
                    <div class="col-xl-6">
                        <div class="card border rounded-4 shadow-sm h-100 mb-0">
                            <div class="card-header bg-light border-bottom p-3 d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="fw-bold text-dark mb-0">{{ $emp->full_name }} ({{ $emp->employee_id }})</h6>
                                    <span class="text-muted fs-12">{{ $emp->designation->name ?? 'N/A' }} &bull; LWD: <strong>{{ $exit->effective_lwd ? \Carbon\Carbon::parse($exit->effective_lwd)->format('d M, Y') : 'TBD' }}</strong></span>
                                </div>
                                <div class="text-end">
                                    @if($progress === 100)
                                        <x-ui.badge soft variant="success" class="px-3 py-1.5 fw-bold fs-12">
                                            <i class="feather-check-circle me-1"></i> 100% Cleared
                                        </x-ui.badge>
                                    @elseif($progress > 0)
                                        <x-ui.badge soft variant="primary" class="px-3 py-1.5 fw-bold fs-12">
                                            {{ $progress }}% Cleared
                                        </x-ui.badge>
                                    @else
                                        <x-ui.badge soft variant="secondary" class="px-3 py-1.5 fw-semibold fs-12">
                                            0% Cleared
                                        </x-ui.badge>
                                    @endif
                                </div>
                            </div>
                            <div class="card-body p-3">
                                <div class="row g-2">
                                    @php
                                        $deptMeta = [
                                            'it' => ['label' => 'IT & Systems', 'icon' => 'feather-monitor'],
                                            'admin' => ['label' => 'Facilities & Admin', 'icon' => 'feather-briefcase'],
                                            'finance' => ['label' => 'Finance & Payroll', 'icon' => 'feather-dollar-sign'],
                                            'hr' => ['label' => 'HR & Operations', 'icon' => 'feather-users'],
                                            'manager' => ['label' => 'Reporting Manager', 'icon' => 'feather-user-check'],
                                        ];
                                    @endphp
                                    @foreach($deptMeta as $deptKey => $meta)
                                        @php
                                            $items = $deptClearances->get($deptKey, collect());
                                            $clearedOrWaived = $items->whereIn('status', ['cleared', 'waived'])->count();
                                            $issuesCount = $items->where('status', 'issues_found')->count();
                                            $allCleared = $items->count() > 0 && $clearedOrWaived === $items->count();
                                        @endphp
                                        <div class="col-12">
                                            <div class="clearance-item-box d-flex align-items-center justify-content-between flex-wrap gap-2">
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="avatar-text avatar-sm bg-soft-{{ $allCleared ? 'success' : ($issuesCount > 0 ? 'danger' : 'primary') }} text-{{ $allCleared ? 'success' : ($issuesCount > 0 ? 'danger' : 'primary') }} rounded-circle d-flex align-items-center justify-content-center">
                                                        <i class="{{ $allCleared ? 'feather-check' : ($issuesCount > 0 ? 'feather-alert-triangle' : $meta['icon']) }} fs-14"></i>
                                                    </div>
                                                    <div>
                                                        <span class="fw-bold text-dark fs-13">{{ $meta['label'] }}</span>
                                                        <span class="text-muted fs-11 d-block">
                                                            @if($issuesCount > 0)
                                                                <span class="text-danger fw-semibold"><i class="feather-alert-circle me-1"></i>{{ $issuesCount }} issue(s) flagged</span> &bull; {{ $clearedOrWaived }}/{{ $items->count() }} resolved
                                                            @else
                                                                ({{ $clearedOrWaived }}/{{ $items->count() }} completed)
                                                            @endif
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    @if($allCleared)
                                                        <x-ui.badge soft variant="success" class="px-3 py-2 fw-semibold fs-12">
                                                            <i class="feather-check-circle me-1"></i> Department Approved
                                                        </x-ui.badge>
                                                    @elseif($issuesCount > 0)
                                                        <x-ui.badge soft variant="danger" class="px-2.5 py-1.5 fw-semibold fs-11">
                                                            Issues / Dues Found
                                                        </x-ui.badge>
                                                        <x-ui.button variant="outline-primary" size="sm" icon="feather-edit-2" data-bs-toggle="modal" data-bs-target="#clearanceModal_{{ $exit->id }}_{{ $deptKey }}" class="fw-bold px-3">
                                                            Review
                                                        </x-ui.button>
                                                    @else
                                                        <x-ui.button variant="primary" size="sm" icon="feather-check-square" data-bs-toggle="modal" data-bs-target="#clearanceModal_{{ $exit->id }}_{{ $deptKey }}" class="fw-bold px-3">
                                                            Review & Sign Off
                                                        </x-ui.button>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="card-footer bg-light border-top p-3 d-flex justify-content-between align-items-center">
                                <a href="{{ route('hrms.exits.noc-certificate.view', $exit->id) }}" target="_blank" class="btn btn-sm btn-light border fw-semibold">
                                    <i class="feather-printer me-1"></i> Print NOC Certificate
                                </a>
                                @if($progress === 100)
                                    <span class="text-success fw-bold fs-13"><i class="feather-check-circle me-1"></i> Ready for FnF Settlement</span>
                                @else
                                    <span class="text-warning fw-semibold fs-12"><i class="feather-alert-circle me-1"></i> Clearances in progress</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12 text-center py-5 text-muted">
                        <h6>No active department clearances pending.</h6>
                    </div>
                @endforelse
            </div>

        @elseif($activeTab === 'assets')
            <!-- TAB 3: ASSET RECOVERY CENTER -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-dark">
                    <thead class="table-light fs-11 text-uppercase tracking-wider">
                        <tr>
                            <th class="ps-3 py-3">Exiting Employee</th>
                            <th class="py-3">Asset Tag & Name</th>
                            <th class="py-3">Serial Number</th>
                            <th class="py-3">Allocation Date</th>
                            <th class="py-3">Return Status</th>
                            <th class="text-end pe-3 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $hasAssets = false; @endphp
                        @foreach($exits as $exit)
                            @if($exit->employee && $exit->employee->assets)
                                @foreach($exit->employee->assets as $asset)
                                    @php
                                        $hasAssets = true;
                                        $isReturned = empty($asset->assigned_employee_id) || in_array($asset->status, ['available', 'returned', 'scrapped']);
                                    @endphp
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-bold text-dark">{{ $exit->employee->full_name }}</div>
                                            <span class="text-muted fs-12">{{ $exit->employee->employee_id }}</span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark">{{ $asset->name }}</div>
                                            <span class="badge bg-light text-secondary border fs-11">{{ $asset->asset_code ?? $asset->asset_tag ?? 'TAG-AUTO' }}</span>
                                        </td>
                                        <td>{{ $asset->serial_number ?: 'N/A' }}</td>
                                        <td>{{ $asset->allocated_at ? \Carbon\Carbon::parse($asset->allocated_at)->format('d M, Y') : ($asset->created_at ? $asset->created_at->format('d M, Y') : 'N/A') }}</td>
                                        <td>
                                            @if($isReturned)
                                                <x-ui.badge soft variant="success">
                                                    <i class="feather-check-circle me-1"></i> Returned / Accounted
                                                </x-ui.badge>
                                            @else
                                                <x-ui.badge soft variant="danger">
                                                    <i class="feather-alert-triangle me-1"></i> In Possession (Unreturned)
                                                </x-ui.badge>
                                            @endif
                                        </td>
                                        <td class="text-end pe-3">
                                            @if(!$isReturned)
                                                <x-ui.button variant="primary" size="sm" icon="feather-corner-down-left" data-bs-toggle="modal" data-bs-target="#assetReturnModal_{{ $exit->id }}_{{ $asset->id }}" class="fw-semibold">
                                                    Mark Returned
                                                </x-ui.button>
                                            @else
                                                <span class="text-success fs-12 fw-semibold"><i class="feather-check"></i> Accounted For</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach

                        @if(!$hasAssets)
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <div class="avatar-text avatar-lg bg-soft-success text-success rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                                        <i class="feather-check-circle fs-24"></i>
                                    </div>
                                    <h6 class="fw-bold mb-1 text-dark">All hardware assets are accounted for</h6>
                                    <p class="fs-13 mb-0 text-muted">No unreturned hardware assets remaining for exiting employees.</p>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

        @elseif($activeTab === 'fnf')
            <!-- TAB 4: FULL & FINAL (FnF) SETTLEMENTS -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-dark w-100" style="table-layout: auto;">
                    <thead class="table-light fs-11 text-uppercase tracking-wider">
                        <tr>
                            <th class="ps-3 py-3" style="width: 22%;">Employee</th>
                            <th class="py-3" style="width: 12%;">Exit LWD</th>
                            <th class="py-3" style="width: 18%;">Gross Earnings</th>
                            <th class="py-3" style="width: 18%;">Total Deductions</th>
                            <th class="py-3" style="width: 12%;">Net Payout</th>
                            <th class="py-3" style="width: 8%;">Status</th>
                            <th class="text-end pe-3 py-3" style="width: 10%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($exits as $exit)
                            @php
                                $fnf = $exit->fnfSettlement;
                                $emp = $exit->employee;
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark fs-13">{{ $emp->full_name }}</div>
                                    <span class="text-muted fs-11">{{ $emp->employee_id }} &bull; {{ $emp->designation->name ?? 'N/A' }}</span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark fs-13">{{ $exit->effective_lwd ? \Carbon\Carbon::parse($exit->effective_lwd)->format('d M, Y') : 'TBD' }}</div>
                                </td>
                                <td>
                                    <div class="fw-bold text-success fs-13">${{ number_format($fnf->total_earnings ?? 0, 2) }}</div>
                                    <div class="text-muted fs-11">Salary ({{ $fnf->unpaid_salary_days ?? 0 }}d) + Leave ({{ $fnf->leave_encashment_days ?? 0 }}d)</div>
                                </td>
                                <td>
                                    <div class="fw-bold text-danger fs-13">${{ number_format($fnf->total_deductions ?? 0, 2) }}</div>
                                    <div class="text-muted fs-11">
                                        @if(($fnf->asset_damage_recovery ?? 0) > 0)
                                            <span class="text-danger fw-semibold">Dues: ${{ number_format($fnf->asset_damage_recovery, 0) }}</span> &bull;
                                        @endif
                                        Advances: ${{ number_format($fnf->unsettled_advances_recovery ?? 0, 0) }}
                                    </div>
                                </td>
                                <td>
                                    <h6 class="fw-bold text-primary mb-0 fs-13">${{ number_format($fnf->net_payable_amount ?? 0, 2) }}</h6>
                                </td>
                                <td>
                                    @php $fnfProgress = $exit->getClearanceProgressPercentage(); @endphp
                                    @if(($fnf->status ?? '') === 'paid')
                                        <x-ui.badge soft variant="success" class="fs-11">
                                            <i class="feather-check-circle me-1"></i> Paid
                                        </x-ui.badge>
                                    @elseif($fnfProgress === 100)
                                        <x-ui.badge soft variant="primary" class="fs-11">
                                            <i class="feather-check me-1"></i> Ready to Settle
                                        </x-ui.badge>
                                    @else
                                        <x-ui.badge soft variant="warning" class="fs-11" title="Clearances Pending">
                                            NOC Pending ({{ $fnfProgress }}%)
                                        </x-ui.badge>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-flex align-items-center justify-content-end gap-1 flex-nowrap">
                                        <form method="POST" action="{{ route('hrms.exits.fnf.recalculate', $exit->id) }}" class="d-inline m-0">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-light border p-1 px-2" title="Recalculate FnF Values">
                                                <i class="feather-refresh-cw text-muted fs-12"></i>
                                            </button>
                                        </form>
                                        <a href="{{ route('hrms.exits.fnf-statement.view', $exit->id) }}" target="_blank" class="btn btn-sm btn-light border p-1 px-2 text-dark fs-12 fw-semibold" title="View / Print Statement">
                                            <i class="feather-printer fs-12"></i>
                                        </a>
                                        @if(($fnf->status ?? '') !== 'paid')
                                            <x-ui.button variant="{{ $fnfProgress === 100 ? 'success' : 'outline-primary' }}" size="sm" icon="feather-check" data-bs-toggle="modal" data-bs-target="#finalizeFnfModal_{{ $exit->id }}" class="fw-semibold fs-11 py-1 px-2">
                                                Pay
                                            </x-ui.button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <div class="avatar-text avatar-lg bg-soft-info text-info rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                                        <i class="feather-credit-card fs-24"></i>
                                    </div>
                                    <h6 class="fw-bold mb-1 text-dark">No settlement records found</h6>
                                    <p class="fs-13 mb-0 text-muted">All active exits are either settled or awaiting clearance sign-offs.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        @elseif($activeTab === 'documents')
            <!-- TAB 5: EXIT CERTIFICATES & LETTERS (GROUPED BY EMPLOYEE) -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-dark">
                    <thead class="table-light fs-11 text-uppercase tracking-wider">
                        <tr>
                            <th class="ps-3 py-3" style="width: 25%;">Exiting Employee</th>
                            <th class="py-3" style="width: 15%;">Last Working Day</th>
                            <th class="py-3" style="width: 15%;">Status</th>
                            <th class="py-3 text-start" style="width: 45%;">Generated Certificates & Letters</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($exits as $exit)
                            @php
                                $emp = $exit->employee;
                                $progress = $exit->getClearanceProgressPercentage();
                                $isSettled = $exit->status === 'settled' || ($exit->fnfSettlement->status ?? '') === 'paid';
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-text avatar-sm bg-soft-primary text-primary rounded-circle fw-bold">
                                            {{ strtoupper(substr($emp->full_name ?? 'E', 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark fs-13">{{ $emp->full_name }}</div>
                                            <span class="text-muted fs-11">{{ $emp->employee_id }} &bull; {{ $emp->designation->name ?? 'N/A' }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark fs-13">{{ $exit->effective_lwd ? \Carbon\Carbon::parse($exit->effective_lwd)->format('d M, Y') : 'TBD' }}</div>
                                    <span class="text-muted fs-11">{{ ucfirst(str_replace('_', ' ', $exit->separation_type)) }}</span>
                                </td>
                                <td>
                                    @if($isSettled)
                                        <x-ui.badge soft variant="success" class="fs-11">
                                            <i class="feather-check-circle me-1"></i> Settled & Issued
                                        </x-ui.badge>
                                    @elseif($progress === 100)
                                        <x-ui.badge soft variant="primary" class="fs-11">
                                            <i class="feather-check me-1"></i> 100% Cleared
                                        </x-ui.badge>
                                    @else
                                        <x-ui.badge soft variant="warning" class="fs-11">
                                            Clearance ({{ $progress }}%)
                                        </x-ui.badge>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <!-- 1. Relieving Letter -->
                                        <a href="{{ route('hrms.exits.relieving-letter.view', $exit->id) }}" target="_blank" class="btn-doc-card" title="Open & Print Relieving Letter">
                                            <i class="feather-file-text text-primary"></i>
                                            <span>Relieving Letter</span>
                                        </a>

                                        <!-- 2. Experience Certificate -->
                                        <a href="{{ route('hrms.exits.experience-certificate.view', $exit->id) }}" target="_blank" class="btn-doc-card" title="Open & Print Experience Certificate">
                                            <i class="feather-award text-success"></i>
                                            <span>Experience Certificate</span>
                                        </a>

                                        <!-- 3. NOC Certificate -->
                                        <a href="{{ route('hrms.exits.noc-certificate.view', $exit->id) }}" target="_blank" class="btn-doc-card" title="Open & Print Clearance NOC">
                                            <i class="feather-shield text-info"></i>
                                            <span>NOC Certificate</span>
                                        </a>

                                        <!-- 4. FnF Statement -->
                                        <a href="{{ route('hrms.exits.fnf-statement.view', $exit->id) }}" target="_blank" class="btn-doc-card" title="Open & Print Full & Final Statement">
                                            <i class="feather-dollar-sign text-secondary"></i>
                                            <span>F&F Statement</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <div class="avatar-text avatar-lg bg-soft-primary text-primary rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                                        <i class="feather-file-text fs-24"></i>
                                    </div>
                                    <h6 class="fw-bold mb-1 text-dark">No settled exit cases yet</h6>
                                    <p class="fs-13 mb-0 text-muted">Relieving letters and Experience certificates will be listed here once an employee's Full & Final (FnF) settlement is finalized and disbursed.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        @if($exits->hasPages())
            <div class="pt-4 border-top mt-3 d-flex justify-content-between align-items-center">
                <span class="text-muted fs-13">Showing {{ $exits->firstItem() ?? 0 }} to {{ $exits->lastItem() ?? 0 }} of {{ $exits->total() }} records</span>
                {{ $exits->links() }}
            </div>
        @endif
    </div>

    <!-- ── MODALS SECTION (Appended to body via jQuery) ── -->

    <!-- 1. Initiate Exit Modal -->
    <div class="modal fade text-start" id="initiateExitModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-light border-bottom p-4">
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-1"><i class="feather-user-minus text-primary me-2"></i>Initiate Employee Exit & Offboarding</h5>
                        <p class="text-muted fs-13 mb-0">Initiate resignation or involuntary termination, calculate LWD, and generate multi-department clearances.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="{{ route('hrms.exits.initiate') }}">
                    @csrf
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Select Employee <span class="text-danger">*</span></label>
                                <x-ui.odoo-form-ui type="select" name="employee_id" :required="true">
                                    <option value="">Choose employee...</option>
                                    @foreach($employees as $emp)
                                        <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_id }}) - {{ $emp->designation->name ?? 'N/A' }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Separation Type <span class="text-danger">*</span></label>
                                <x-ui.odoo-form-ui type="select" name="separation_type" :required="true">
                                    <option value="resignation">Voluntary Resignation</option>
                                    <option value="termination">Involuntary Termination</option>
                                    <option value="retirement">Retirement</option>
                                    <option value="layoff">Operational Layoff</option>
                                    <option value="contract_end">Contract End / Non-Renewal</option>
                                    <option value="absconding">Absconding</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Resignation / Notice Date <span class="text-danger">*</span></label>
                                <x-ui.odoo-form-ui type="input" inputType="date" name="resignation_date" :value="date('Y-m-d')" :required="true" />
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Notice Period (Days) <span class="text-danger">*</span></label>
                                <x-ui.odoo-form-ui type="select" name="notice_period_days" :required="true">
                                    <option value="0">0 Days (Immediate Exit)</option>
                                    <option value="15">15 Days</option>
                                    <option value="30" selected>30 Days (Standard 1 Month)</option>
                                    <option value="60">60 Days (2 Months)</option>
                                    <option value="90">90 Days (3 Months)</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Preferred Last Working Day (LWD)</label>
                                <x-ui.odoo-form-ui type="input" inputType="date" name="preferred_lwd" />
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Reason Category <span class="text-danger">*</span></label>
                                <x-ui.odoo-form-ui type="select" name="reason_category" :required="true">
                                    <option value="Career Growth">Better Career Opportunity / Growth</option>
                                    <option value="Compensation">Higher Compensation & Benefits</option>
                                    <option value="Relocation">Relocation / Personal Reasons</option>
                                    <option value="Higher Education">Pursuing Higher Education</option>
                                    <option value="Health / Family">Health / Family Concerns</option>
                                    <option value="Performance / Role Mismatch">Performance / Role Mismatch</option>
                                    <option value="Other">Other Reasons</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Reason & Feedback Details</label>
                                <textarea name="reason_details" class="form-control" rows="3" placeholder="Provide additional background, feedback, or transition handover notes..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-top p-3">
                        <x-ui.button variant="light" data-bs-dismiss="modal" class="border px-4 fw-semibold">
                            Cancel
                        </x-ui.button>
                        <x-ui.button variant="primary" type="submit" class="px-4 fw-bold">
                            Initiate Exit & Checklists
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Dynamic Modals for Clearance Sign-offs, LWD Decisions, and FnF Payouts -->
    @foreach($exits as $exit)
        <!-- 2. Approve / Edit LWD Modal -->
        @if($exit->status !== 'settled' && $exit->status !== 'rejected')
            <div class="modal fade text-start" id="approveExitModal_{{ $exit->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg rounded-4">
                        <div class="modal-header bg-light border-bottom p-3">
                            <h6 class="modal-title fw-bold text-dark mb-0"><i class="feather-calendar text-primary me-2"></i>{{ $exit->approved_lwd ? 'Edit / Reschedule Official LWD' : 'Approve & Set Official LWD' }} - {{ $exit->employee->full_name }}</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" action="{{ route('hrms.exits.approve', $exit->id) }}">
                            @csrf
                            <div class="modal-body p-4">
                                <div class="p-3 bg-light rounded-3 border mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Employee Requested LWD</span>
                                            <strong class="text-dark fs-13">{{ $exit->preferred_lwd ? \Carbon\Carbon::parse($exit->preferred_lwd)->format('d M, Y') : 'No preference specified' }}</strong>
                                        </div>
                                        <div class="text-end">
                                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Notice Period Policy</span>
                                            <strong class="text-primary fs-13">{{ $exit->notice_period_days ?? 30 }} Days</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Approved Official Last Working Day (LWD) <span class="text-danger">*</span></label>
                                    <x-ui.odoo-form-ui type="input" inputType="date" name="approved_lwd" :value="$exit->approved_lwd ?: ($exit->preferred_lwd ?: date('Y-m-d', strtotime('+30 days')))" :required="true" />
                                    <div class="text-muted fs-11 mt-1"><i class="feather-info me-1"></i> FnF unpaid days and pro-rata salary will automatically re-calculate for this date.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Notice Period Action</label>
                                    <x-ui.odoo-form-ui type="select" name="notice_action" :required="true">
                                        <option value="serve" @selected(($exit->notice_action ?? 'serve') === 'serve')>Full Notice Period Served</option>
                                        <option value="waive" @selected(($exit->notice_action ?? '') === 'waive')>Waive Shortfall (No Deductions)</option>
                                        <option value="recover" @selected(($exit->notice_action ?? '') === 'recover')>Recover Shortfall in FnF Settlement</option>
                                    </x-ui.odoo-form-ui>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Shortfall Days (If Early Release)</label>
                                    <x-ui.odoo-form-ui type="input" inputType="number" name="notice_shortfall_days" value="{{ $exit->notice_shortfall_days ?? 0 }}" />
                                </div>
                            </div>
                            <div class="modal-footer bg-light border-top p-3 d-flex justify-content-between">
                                <x-ui.button variant="light" data-bs-dismiss="modal" class="border px-4 fw-semibold">Cancel</x-ui.button>
                                <x-ui.button variant="primary" type="submit" class="px-4 fw-bold">
                                    <i class="feather-check-circle me-1"></i> Save & Recalculate FnF
                                </x-ui.button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        <!-- 3. Clearance Sign-off Modals per Department (Single Batch Form with Quick Actions) -->
        @foreach(['it', 'admin', 'finance', 'hr', 'manager'] as $deptKey)
            @php 
                $deptItems = $exit->clearances->where('department', $deptKey); 
                $deptTitles = [
                    'it' => 'IT & Systems Clearance Checklist',
                    'admin' => 'Facilities & Admin Clearance Checklist',
                    'finance' => 'Finance & Payroll Clearance Checklist',
                    'hr' => 'HR & Operations Clearance Checklist',
                    'manager' => 'Reporting Manager Clearance Checklist',
                ];
            @endphp
            @if($deptItems->count() > 0)
                <div class="modal fade text-start" id="clearanceModal_{{ $exit->id }}_{{ $deptKey }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content border-0 shadow-lg rounded-4">
                            <form method="POST" action="{{ route('hrms.exits.clearances.batch-update', ['exit' => $exit->id, 'department' => $deptKey]) }}">
                                @csrf
                                <div class="modal-header bg-light border-bottom p-3 d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="modal-title fw-bold text-dark mb-0"><i class="feather-check-square text-primary me-2"></i>{{ $deptTitles[$deptKey] ?? (strtoupper($deptKey) . ' Department Clearance') }}</h6>
                                        <span class="text-muted fs-12">Employee: <strong>{{ $exit->employee->full_name }}</strong> ({{ $exit->employee->employee_id }})</span>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-4">
                                    <!-- Quick Batch Actions Toolbar -->
                                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                        <span class="text-muted fs-12"><i class="feather-info me-1"></i> Update any or all checklist items below and save once.</span>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-xs btn-outline-success fw-semibold px-2 py-1 fs-11" onclick="setClearanceBatchStatus('clearanceModal_{{ $exit->id }}_{{ $deptKey }}', 'cleared')">
                                                <i class="feather-check-circle me-1"></i> Mark All Cleared
                                            </button>
                                            <button type="button" class="btn btn-xs btn-outline-secondary fw-semibold px-2 py-1 fs-11" onclick="setClearanceBatchStatus('clearanceModal_{{ $exit->id }}_{{ $deptKey }}', 'pending')">
                                                <i class="feather-rotate-ccw me-1"></i> Reset to Pending
                                            </button>
                                        </div>
                                    </div>

                                    <div class="d-flex flex-column gap-3">
                                        @foreach($deptItems as $cItem)
                                            @php $isIssue = $cItem->status === 'issues_found'; @endphp
                                            <div class="p-3 bg-light rounded-3 border">
                                                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <i class="feather-check-circle text-muted fs-14"></i>
                                                        <span class="fw-bold text-dark fs-13">{{ $cItem->item_name }}</span>
                                                    </div>
                                                    <x-ui.badge soft variant="{{ $cItem->status === 'cleared' ? 'success' : ($cItem->status === 'issues_found' ? 'danger' : ($cItem->status === 'waived' ? 'warning' : 'secondary')) }}" class="text-uppercase fw-bold">
                                                        {{ strtoupper(str_replace('_', ' ', $cItem->status)) }}
                                                    </x-ui.badge>
                                                </div>
                                                <div class="row g-2 align-items-end mt-1">
                                                    <div class="col-md-4">
                                                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Clearance Status</label>
                                                        <x-ui.odoo-form-ui type="select" name="clearances[{{ $cItem->id }}][status]" class="clearance-status-select" data-item-id="{{ $cItem->id }}">
                                                            <option value="pending" @selected($cItem->status === 'pending')>Pending Sign-off</option>
                                                            <option value="cleared" @selected($cItem->status === 'cleared')>Clear & Handed Over</option>
                                                            <option value="issues_found" @selected($cItem->status === 'issues_found')>Issues / Dues Found</option>
                                                            <option value="waived" @selected($cItem->status === 'waived')>Waived by Manager</option>
                                                        </x-ui.odoo-form-ui>
                                                    </div>
                                                    <div class="col-md-3 penalty-field-col" id="penalty_col_{{ $cItem->id }}" style="{{ $isIssue ? '' : 'display: none;' }}">
                                                        <label class="form-label fw-bold fs-11 text-uppercase text-danger mb-1"><i class="feather-alert-circle me-1"></i>Due / Penalty ($)</label>
                                                        <x-ui.odoo-form-ui type="input" inputType="number" name="clearances[{{ $cItem->id }}][deduction_amount]" placeholder="0.00" value="{{ $cItem->deduction_amount > 0 ? $cItem->deduction_amount : '0.00' }}" />
                                                    </div>
                                                    <div class="{{ $isIssue ? 'col-md-5' : 'col-md-8' }} remarks-field-col" id="remarks_col_{{ $cItem->id }}">
                                                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Sign-off Remarks / Notes</label>
                                                        <x-ui.odoo-form-ui type="input" name="clearances[{{ $cItem->id }}][remarks]" placeholder="Enter sign-off comments..." value="{{ $cItem->remarks }}" />
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="modal-footer bg-light border-top p-3 d-flex justify-content-between">
                                    <x-ui.button variant="light" data-bs-dismiss="modal" class="border px-4 fw-semibold">Cancel</x-ui.button>
                                    <x-ui.button variant="primary" type="submit" class="px-4 fw-bold">
                                        <i class="feather-check-circle me-1"></i> Save All Clearances
                                    </x-ui.button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach

        <!-- 4. Asset Return Modal -->
        @if($exit->employee && $exit->employee->assets)
            @foreach($exit->employee->assets as $asset)
                @if($asset->assigned_employee_id === $exit->employee_id)
                    <div class="modal fade text-start" id="assetReturnModal_{{ $exit->id }}_{{ $asset->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 shadow-lg rounded-4">
                                <div class="modal-header bg-light border-bottom p-3">
                                    <h6 class="modal-title fw-bold text-dark mb-0"><i class="feather-package text-primary me-2"></i>Record Hardware Asset Return</h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST" action="{{ route('hrms.exits.assets.return', ['exit' => $exit->id, 'asset' => $asset->id]) }}">
                                    @csrf
                                    <input type="hidden" name="asset_id" value="{{ $asset->id }}">
                                    <div class="modal-body p-4">
                                        <div class="p-3 bg-light rounded-3 border mb-3">
                                            <div class="fw-bold text-dark">{{ $asset->name }}</div>
                                            <div class="text-muted fs-12">Code: {{ $asset->asset_code ?? $asset->asset_tag ?? 'TAG-AUTO' }} &bull; S/N: {{ $asset->serial_number ?: 'N/A' }}</div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Asset Physical Condition <span class="text-danger">*</span></label>
                                            <x-ui.odoo-form-ui type="select" name="condition" :required="true" onchange="var p = document.getElementById('asset_deduction_wrap_{{ $exit->id }}_{{ $asset->id }}'); if (this.value === 'damaged' || this.value === 'lost') { p.style.display = 'block'; } else { p.style.display = 'none'; p.querySelector('input').value = '0.00'; }">
                                                <option value="good" selected>Good / Intact (Restock as Available)</option>
                                                <option value="fair">Fair / Normal Wear (Restock as Available)</option>
                                                <option value="damaged">Damaged / Needs Maintenance (Deduct in FnF)</option>
                                                <option value="lost">Lost / Scrapped (Deduct Full Value in FnF)</option>
                                            </x-ui.odoo-form-ui>
                                        </div>
                                        <div class="mb-3" id="asset_deduction_wrap_{{ $exit->id }}_{{ $asset->id }}" style="display: none;">
                                            <label class="form-label fw-bold fs-11 text-uppercase text-danger mb-1"><i class="feather-alert-circle me-1"></i>Damage / Loss Deduction Amount ($)</label>
                                            <x-ui.odoo-form-ui type="input" inputType="number" name="damage_deduction" value="0.00" placeholder="0.00" />
                                            <div class="text-muted fs-11 mt-1">This penalty will automatically be deducted from the employee's FnF settlement.</div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Return Inspection Remarks</label>
                                            <textarea name="remarks" class="form-control" rows="2" placeholder="Notes on physical inspection, serial check, accessories returned..."></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer bg-light border-top p-3 d-flex justify-content-between">
                                        <x-ui.button variant="light" data-bs-dismiss="modal" class="border px-4 fw-semibold">Cancel</x-ui.button>
                                        <x-ui.button variant="primary" type="submit" class="px-4 fw-bold">
                                            <i class="feather-check-circle me-1"></i> Confirm Return & Update Master
                                        </x-ui.button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        @endif

        <!-- 5. Finalize FnF Settlement Modal (Standard Enterprise Clearance Gate) -->
        @if(($exit->fnfSettlement->status ?? '') !== 'paid')
            @php
                $progress = $exit->getClearanceProgressPercentage();
                $isIncomplete = $exit->clearances->where('status', 'pending')->count() > 0;
                $pendingDepts = [];
                foreach(['it' => 'IT & Systems', 'admin' => 'Facilities & Admin', 'finance' => 'Finance & Payroll', 'hr' => 'HR & Operations', 'manager' => 'Reporting Manager'] as $deptKey => $deptLabel) {
                    $pCount = $exit->clearances->where('department', $deptKey)->where('status', 'pending')->count();
                    if ($pCount > 0) {
                        $pendingDepts[] = "$deptLabel ($pCount unreviewed)";
                    }
                }
                $clearanceDues = $exit->fnfSettlement->asset_damage_recovery ?? 0;
            @endphp
            <div class="modal fade text-start" id="finalizeFnfModal_{{ $exit->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content border-0 shadow-lg rounded-4">
                        <div class="modal-header bg-light border-bottom p-3">
                            <h6 class="modal-title fw-bold text-dark mb-0"><i class="feather-check-circle text-success me-2"></i>Finalize & Disburse Settlement - {{ $exit->employee->full_name }}</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" action="{{ route('hrms.exits.fnf.finalize', $exit->id) }}">
                            @csrf
                            <div class="modal-body p-4">
                                @if($isIncomplete)
                                    <div class="alert alert-warning border border-warning border-opacity-25 rounded-3 p-3 mb-3">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <i class="feather-alert-triangle text-warning fs-18"></i>
                                            <h6 class="fw-bold text-dark mb-0 fs-13">Unreviewed Department Clearances ({{ $progress }}% Reviewed)</h6>
                                        </div>
                                        <p class="fs-12 text-muted mb-2">
                                            The following department clearance reviews have not been submitted yet:
                                        </p>
                                        <div class="d-flex flex-wrap gap-2 mb-3">
                                            @foreach($pendingDepts as $pDept)
                                                <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 fs-11">{{ $pDept }}</span>
                                            @endforeach
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="override_clearances" value="1" id="override_chk_{{ $exit->id }}" required>
                                            <label class="form-check-label fw-bold text-dark fs-12" for="override_chk_{{ $exit->id }}">
                                                I authorize Managerial Override to disburse settlement despite pending unreviewed clearances.
                                            </label>
                                        </div>
                                        <div class="mt-2">
                                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Override Reason <span class="text-danger">*</span></label>
                                            <x-ui.odoo-form-ui type="input" name="override_reason" placeholder="State the business reason for early settlement override..." />
                                        </div>
                                    </div>
                                @elseif($clearanceDues > 0)
                                    <div class="alert alert-success border border-success border-opacity-25 rounded-3 p-3 mb-3 fs-12 text-dark">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <i class="feather-check-circle text-success fs-18"></i>
                                            <h6 class="fw-bold text-dark mb-0 fs-13">All Department Clearances Signed Off (100%)</h6>
                                        </div>
                                        <p class="fs-12 text-muted mb-0">
                                            All 5 department sign-offs are complete. Departmental dues & recovery penalties (<strong class="text-danger">${{ number_format($clearanceDues, 2) }}</strong>) have been deducted from the final payout below.
                                        </p>
                                    </div>
                                @else
                                    <div class="alert alert-success border border-success border-opacity-25 rounded-3 py-2.5 px-3 mb-3 fs-12 text-dark">
                                        <i class="feather-check-circle text-success me-1"></i> All 5 department clearances are <strong>100% Signed Off</strong> with zero outstanding dues. Ready for final payout.
                                    </div>
                                @endif

                                <!-- Settlement Financial Breakdown Summary -->
                                <div class="p-3 bg-light rounded-3 border mb-3">
                                    <div class="row g-2 text-center text-md-start align-items-center">
                                        <div class="col-md-4 border-end">
                                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Gross Earnings</span>
                                            <h6 class="fw-bold text-success mb-0 fs-14">+${{ number_format($exit->fnfSettlement->total_earnings ?? 0, 2) }}</h6>
                                            <span class="text-muted fs-11">Salary & Encashment</span>
                                        </div>
                                        <div class="col-md-4 border-end">
                                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Total Deductions</span>
                                            <h6 class="fw-bold text-danger mb-0 fs-14">-${{ number_format($exit->fnfSettlement->total_deductions ?? 0, 2) }}</h6>
                                            @if(($exit->fnfSettlement->asset_damage_recovery ?? 0) > 0)
                                                <span class="text-danger fs-11 fw-bold"><i class="feather-alert-triangle me-0.5"></i> Incl. ${{ number_format($exit->fnfSettlement->asset_damage_recovery, 2) }} Clearance Dues</span>
                                            @else
                                                <span class="text-muted fs-11">Advances & Penalties</span>
                                            @endif
                                        </div>
                                        <div class="col-md-4">
                                            <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Net Payable Amount</span>
                                            <h5 class="fw-bold text-primary mb-0 fs-16">${{ number_format($exit->fnfSettlement->net_payable_amount ?? 0, 2) }}</h5>
                                            <span class="text-muted fs-11">Final Disbursed Payout</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Settlement Payout Channel</label>
                                        <x-ui.odoo-form-ui type="select" name="settlement_channel">
                                            <option value="monthly_payroll">Process with Regular Monthly Payroll Run</option>
                                            <option value="off_cycle">Direct / Off-Cycle Immediate Payout</option>
                                        </x-ui.odoo-form-ui>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Payment Mode</label>
                                        <x-ui.odoo-form-ui type="select" name="payment_method">
                                            <option value="Bank Transfer">Bank Transfer (NEFT/RTGS)</option>
                                            <option value="Cheque">Company Cheque</option>
                                            <option value="UPI / Online">UPI / Online Gateway</option>
                                        </x-ui.odoo-form-ui>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Payment Reference / Txn ID</label>
                                        <x-ui.odoo-form-ui type="input" name="payment_reference" placeholder="e.g. TXN-99887722" />
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Settlement Notes</label>
                                        <textarea name="notes" class="form-control" rows="2" placeholder="Any additional notes..."></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer bg-light border-top p-3 d-flex justify-content-between">
                                <x-ui.button variant="light" data-bs-dismiss="modal" class="border px-4 fw-semibold">Cancel</x-ui.button>
                                <x-ui.button variant="success" type="submit" class="px-4 fw-bold">
                                    <i class="feather-check-circle me-1"></i> Disburse & Complete Exit
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
    function togglePenaltyField($select) {
        var itemId = $select.data('item-id');
        if (!itemId) {
            var nameAttr = $select.attr('name') || '';
            var match = nameAttr.match(/\[(\d+)\]/);
            if (match) itemId = match[1];
        }
        if (!itemId) return;

        var status = $select.val();
        var $penaltyCol = $('#penalty_col_' + itemId);
        var $remarksCol = $('#remarks_col_' + itemId);

        if (status === 'issues_found') {
            $penaltyCol.stop(true, true).fadeIn(150);
            $remarksCol.removeClass('col-md-8').addClass('col-md-5');
        } else {
            $penaltyCol.stop(true, true).hide();
            $penaltyCol.find('input').val('0.00');
            $remarksCol.removeClass('col-md-5').addClass('col-md-8');
        }
    }

    $(document).on('change', '.clearance-status-select, select[name^="clearances"]', function() {
        togglePenaltyField($(this));
    });

    function setClearanceBatchStatus(modalId, statusVal) {
        var $modal = $('#' + modalId);
        $modal.find('select[name^="clearances"]').each(function() {
            $(this).val(statusVal);
            $(this).trigger('change');
            if ($(this).hasClass('select2-hidden-accessible') || $(this).data('select2')) {
                $(this).trigger('change.select2');
            }
            togglePenaltyField($(this));
        });
    }

    $(document).ready(function() {
        // Append all modals directly to body to guarantee no backdrop stacking context / blur issues
        $('[id^="initiateExitModal"], [id^="approveExitModal_"], [id^="clearanceModal_"], [id^="assetReturnModal_"], [id^="finalizeFnfModal_"]').appendTo('body');
    });
</script>
@endpush
