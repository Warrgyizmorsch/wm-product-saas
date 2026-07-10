@extends('layouts.duralux')

@section('title', 'SHIFT & ROSTER SETTINGS | SaaS ERP')
@section('page-title', 'Shift & Roster Management')
@section('breadcrumb', 'HRMS / Shift & Roster Settings')

@section('page-actions')
    @if($tab === 'shifts')
        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addShiftModal">
            Add Shift
        </x-ui.button>
    @else
        <div class="d-flex gap-2">
            <x-ui.button variant="danger" icon="feather-trash" data-bs-toggle="modal" data-bs-target="#clearRosterModal">
                Clear Roster
            </x-ui.button>
            <x-ui.button variant="primary" icon="feather-calendar" data-bs-toggle="modal" data-bs-target="#assignRosterModal">
                Assign Roster
            </x-ui.button>
        </div>
    @endif
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
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

        /* Underlined Horizontal Tabs */
        #rosterTabs .nav-link {
            border: none !important;
            background-color: transparent !important;
            color: #64748b;
            font-weight: 500;
            padding: 12px 20px;
            border-bottom: 2px solid transparent !important;
            transition: all 0.2s ease-in-out;
        }
        #rosterTabs .nav-link:hover {
            color: var(--bs-primary);
        }
        #rosterTabs .nav-link.active {
            color: var(--bs-primary) !important;
            border-bottom: 2px solid var(--bs-primary) !important;
        }

        /* Responsive Grid & Size constraints to fit within 90% viewports */
        .roster-grid-table {
            table-layout: fixed !important;
            width: 100% !important;
        }
        .roster-grid-table th.employee-head,
        .roster-grid-table td.employee-cell {
            width: 200px !important;
            min-width: 200px !important;
            max-width: 200px !important;
            text-align: left !important;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .roster-grid-table th.date-head,
        .roster-grid-table td.date-cell {
            width: 90px !important;
            min-width: 90px !important;
            max-width: 90px !important;
            padding: 4px !important;
        }
        .roster-grid-table th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            vertical-align: middle;
        }
        .roster-cell-select {
            cursor: pointer;
            font-size: 11px !important;
            font-weight: 700 !important;
            text-align: center !important;
            padding: 2px 4px !important;
            height: 28px !important;
            border: 1px solid transparent !important;
            background-color: transparent !important;
            border-radius: 4px;
            transition: all 0.15s ease-in-out;
            width: 100%;
        }
        .roster-cell-select:hover, .roster-cell-select:focus {
            background-color: rgba(0, 0, 0, 0.04) !important;
            border-color: #cbd5e1 !important;
        }

        /* Soft badge colors */
        .bg-soft-primary { background-color: rgba(79, 70, 229, 0.08) !important; }
        .text-primary { color: #4f46e5 !important; }
        .bg-soft-secondary { background-color: rgba(100, 116, 139, 0.08) !important; }
        .text-secondary { color: #64748b !important; }
        .bg-soft-light { background-color: rgba(241, 245, 249, 0.6) !important; }
        .text-muted { color: #94a3b8 !important; }

        /* Multi-select styling adjustment in Modal */
        .select2-container--bootstrap-5 .select2-selection--multiple {
            border: 1px solid #ced4da !important;
            border-radius: 4px !important;
            padding: 2px 4px !important;
        }
        
        /* Theme Filter Apply and Reset Buttons styling */
        .roster-filter-apply-btn {
            border-radius: 6px !important;
            font-size: 11px !important;
            letter-spacing: 0.05em !important;
            background-color: var(--bs-primary) !important;
            border-color: var(--bs-primary) !important;
            color: #fff !important;
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.08) !important;
            transition: all 0.2s ease-in-out !important;
        }
        .roster-filter-apply-btn:hover,
        .roster-filter-apply-btn:focus {
            background-color: var(--bs-primary) !important;
            border-color: var(--bs-primary) !important;
            filter: brightness(0.9) !important;
            color: #fff !important;
        }
        .roster-filter-reset-btn {
            border-radius: 6px !important;
            font-size: 11px !important;
            letter-spacing: 0.05em !important;
            background-color: #f1f5f9 !important;
            border: 1px solid #cbd5e1 !important;
            color: #475569 !important;
            transition: all 0.2s ease-in-out !important;
        }
        .roster-filter-reset-btn:hover,
        .roster-filter-reset-btn:focus {
            background-color: #e2e8f0 !important;
            color: #0f172a !important;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('content')
    <div class="settings-container">
        <!-- Left Subsidebar Column -->
        <div class="settings-sidebar-col">
            @include('modules.hrms.partials.settings-sidebar')
        </div>

        <!-- Right Content Column -->
        <div class="settings-content-col flex-grow-1">
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

            <div class="tab-content" id="rosterSettingsContent">
                <div class="tab-pane fade show active" id="roster-pane" role="tabpanel">
                    <div class="row">
                        <!-- Horizontal Navigation directly above content (Shift Master is now First/Default) -->
                        <div class="col-12 mb-3">
                            <ul class="nav gap-2 border-bottom pb-2" id="rosterTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link {{ $tab === 'shifts' ? 'active' : '' }}" href="{{ route('hrms.roster.index', ['tab' => 'shifts']) }}">
                                        <i class="feather-clock me-2"></i>Shift Master
                                    </a>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link {{ $tab === 'roster' ? 'active' : '' }}" href="{{ route('hrms.roster.index', ['tab' => 'roster']) }}">
                                        <i class="feather-calendar me-2"></i>Roster Board
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <!-- Tab Content Views -->
                        <div class="col-12">
                            @if($tab === 'shifts')
                                <!-- SHIFT MASTER TAB (Default View) -->
                                <div class="row">
                                    <div class="col-12">
                                        <x-ui.card title="Shifts" stretch bodyClass="p-0">
                                            <div class="table-responsive">
                                                <table class="table table-hover mb-0 align-middle">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th width="60">#</th>
                                                            <th>Shift Code</th>
                                                            <th>Shift Name</th>
                                                            <th>Start Time</th>
                                                            <th>End Time</th>
                                                            <th>Break Duration</th>
                                                            <th>Overtime Allowed</th>
                                                            <th>Status</th>
                                                            <th width="150" class="text-end">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($shifts as $sf)
                                                        <tr>
                                                            <td>{{ $loop->iteration }}</td>
                                                            <td><code>{{ $sf->code }}</code></td>
                                                            <td><span class="fw-bold text-dark">{{ $sf->name }}</span></td>
                                                            <td><span class="font-monospace text-muted">{{ substr($sf->start_time, 0, 5) }}</span></td>
                                                            <td><span class="font-monospace text-muted">{{ substr($sf->end_time, 0, 5) }}</span></td>
                                                            <td><span>{{ $sf->break_minutes ?? 0 }} mins</span></td>
                                                            <td>
                                                                @if($sf->overtime_allowed)
                                                                    <x-ui.badge variant="success" soft>Yes</x-ui.badge>
                                                                @else
                                                                    <x-ui.badge variant="danger" soft>No</x-ui.badge>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                @if($sf->active)
                                                                    <x-ui.badge variant="success" soft>Active</x-ui.badge>
                                                                @else
                                                                    <x-ui.badge variant="danger" soft>Inactive</x-ui.badge>
                                                                @endif
                                                            </td>
                                                            <td class="text-end">
                                                                <form action="{{ route('hrms.shift.destroy', $sf->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this shift?');">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <div class="hstack gap-2 justify-content-end">
                                                                        <a href="javascript:void(0)" class="action-dropdown-btn btn-view-shift" data-bs-toggle="modal" data-bs-target="#viewShiftModal" data-shift="{{ base64_encode($sf->toJson()) }}" title="View Details" data-bs-toggle="tooltip">
                                                                            <i class="feather feather-eye"></i>
                                                                        </a>
                                                                        <x-ui.action-dropdown>
                                                                            <li>
                                                                                <a class="dropdown-item btn-edit-shift" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#editShiftModal" data-shift="{{ base64_encode($sf->toJson()) }}">
                                                                                    <i class="feather feather-edit-3 me-3"></i>
                                                                                    <span>Edit</span>
                                                                                </a>
                                                                            </li>
                                                                            <li class="dropdown-divider"></li>
                                                                            <li>
                                                                                <button type="submit" class="dropdown-item text-danger border-0 bg-transparent w-100 text-start d-flex align-items-center">
                                                                                    <i class="feather feather-trash-2 me-3"></i>
                                                                                    <span>Delete</span>
                                                                                </button>
                                                                            </li>
                                                                        </x-ui.action-dropdown>
                                                                    </div>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                        @if($shifts->isEmpty())
                                                        <tr>
                                                            <td colspan="9" class="text-center py-5 text-muted">
                                                                No Shifts found. Click "Add Shift" to create one.
                                                            </td>
                                                        </tr>
                                                        @endif
                                                    </tbody>
                                                </table>
                                            </div>
                                        </x-ui.card>
                                    </div>
                                </div>
                            @else
                                <!-- ROSTER BOARD TAB -->
                                <x-ui.card title="Roster Scheduler Grid" stretch>
                                    <x-slot name="headerAction">
                                        <form method="GET" action="{{ route('hrms.roster.index') }}" id="rosterFilterForm" class="d-flex align-items-center gap-2">
                                            <input type="hidden" name="tab" value="roster">
                                            <input type="hidden" name="sort" id="filterSortInput" value="{{ $sortBy }}">

                                            <!-- 1. Search Bar (Order 1: Single element, soft grey bg, rounded) -->
                                            <div class="position-relative" style="width: 240px;">
                                                <i class="feather-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 14px;"></i>
                                                <input type="text" id="rosterSearch" name="search" class="form-control form-control-sm ps-5 border-0" placeholder="Search employees..." value="{{ $search }}" style="height: 38px; border-radius: 8px; font-size: 13px; color: #475569; background-color: #f1f5f9;">
                                            </div>

                                            <!-- 2. SORT Button (using Duralux component) -->
                                            <x-ui.sort-dropdown label="SORT">
                                                <button type="button" class="dropdown-item sort-option {{ $sortBy === 'name-asc' ? 'active' : '' }}" data-sort="name-asc">
                                                    Name (A - Z)
                                                </button>
                                                <button type="button" class="dropdown-item sort-option {{ $sortBy === 'name-desc' ? 'active' : '' }}" data-sort="name-desc">
                                                    Name (Z - A)
                                                </button>
                                                <button type="button" class="dropdown-item sort-option {{ $sortBy === 'designation' ? 'active' : '' }}" data-sort="designation">
                                                    Designation
                                                </button>
                                            </x-ui.sort-dropdown>

                                            <!-- 3. FILTER Button (using Duralux component) -->
                                            <x-ui.filter label="FILTER">
                                                <div class="d-flex align-items-center gap-2 mb-3">
                                                    <i class="feather-sliders text-primary fs-14"></i>
                                                    <h6 class="fw-bold mb-0 text-dark" style="font-size: 13px;">Filter Options</h6>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-bold text-secondary fs-10 text-uppercase mb-1" style="letter-spacing: 0.05em; color: #64748b !important;">Company</label>
                                                    <select name="company_id" class="form-select form-select-sm" style="border-color: #cbd5e1; border-radius: 6px;">
                                                        <option value="">All Companies</option>
                                                        @foreach($companies as $company)
                                                            <option value="{{ $company->id }}" {{ $selectedCompanyId == $company->id ? 'selected' : '' }}>
                                                                {{ $company->company_name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-bold text-secondary fs-10 text-uppercase mb-1" style="letter-spacing: 0.05em; color: #64748b !important;">Department</label>
                                                    <select name="department_id" class="form-select form-select-sm" style="border-color: #cbd5e1; border-radius: 6px;">
                                                        <option value="">All Departments</option>
                                                        @foreach($departments as $dept)
                                                            <option value="{{ $dept->id }}" data-company-id="{{ $dept->company_id }}" {{ $selectedDepartmentId == $dept->id ? 'selected' : '' }}>
                                                                {{ $dept->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-bold text-secondary fs-10 text-uppercase mb-1" style="letter-spacing: 0.05em; color: #64748b !important;">Start Date</label>
                                                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate->format('Y-m-d') }}" style="border-color: #cbd5e1; border-radius: 6px;">
                                                </div>

                                                <div class="dropdown-divider my-3"></div>

                                                <div class="d-flex gap-2">
                                                    <button type="submit" class="btn btn-sm roster-filter-apply-btn w-100 fw-bold py-2 text-uppercase">APPLY FILTERS</button>
                                                    <a href="{{ route('hrms.roster.index', ['tab' => 'roster']) }}" class="btn btn-sm roster-filter-reset-btn w-100 fw-bold py-2 text-center text-uppercase">RESET</a>
                                                </div>
                                            </x-ui.filter>
                                        </form>
                                    </x-slot>

                                    <!-- Grid Board Matrix (Fixed Column Width Layout to prevent viewport overflow) -->
                                    <div class="table-responsive border rounded bg-white">
                                        <table class="table table-bordered table-hover mb-0 align-middle text-center roster-grid-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="employee-head">Employee Name</th>
                                                    @foreach($dates as $date)
                                                        <th class="date-head">
                                                            <div class="fw-bold text-dark">{{ $date->format('D') }}</div>
                                                            <div class="text-muted" style="font-size: 10px; font-weight: 500;">{{ $date->format('d M') }}</div>
                                                        </th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($employees as $employee)
                                                    <tr>
                                                        <td class="employee-cell">
                                                            <div class="d-flex align-items-center gap-2">
                                                                <div class="avatar-text avatar-sm bg-soft-primary text-primary fw-bold" style="width: 32px; height: 32px; min-width: 32px; min-height: 32px; font-size: 11px;">
                                                                    {{ strtoupper(substr($employee->full_name, 0, 2)) ?: 'EM' }}
                                                                </div>
                                                                <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 140px;">
                                                                    <div class="fw-bold text-dark fs-12 employee-name-label" style="font-size: 12px; line-height: 1.2;">{{ $employee->full_name }}</div>
                                                                    <div class="text-muted fs-10 employee-designation-label" style="font-size: 10px; line-height: 1.2;">{{ $employee->designation?->name ?? 'No Designation' }}</div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        @foreach($dates as $date)
                                                            @php
                                                                $dateStr = $date->format('Y-m-d');
                                                                $roster = $rosterMap[$employee->id][$dateStr] ?? null;
                                                                $assignedShiftId = $roster ? $roster->shift_id : null;
                                                                
                                                                // Color coding background based on state
                                                                $cellBg = 'bg-transparent';
                                                                if ($roster) {
                                                                    if (is_null($roster->shift_id)) {
                                                                        $cellBg = 'bg-soft-secondary'; // Day Off
                                                                    } else {
                                                                        $cellBg = 'bg-soft-primary'; // Assigned Shift
                                                                    }
                                                                } else {
                                                                    $cellBg = 'bg-soft-light'; // Fallback
                                                                }
                                                            @endphp
                                                            <td class="date-cell {{ $cellBg }}" style="transition: all 0.2s ease;">
                                                                <select 
                                                                    class="form-select form-select-sm roster-cell-select" 
                                                                    data-employee-id="{{ $employee->id }}" 
                                                                    data-date="{{ $dateStr }}"
                                                                >
                                                                    <option value="" {{ is_null($assignedShiftId) && !$roster ? 'selected' : '' }}>
                                                                        {{ $employee->shift?->code ? $employee->shift->code . ' (D)' : 'Off (D)' }}
                                                                    </option>
                                                                    <option value="off" class="text-secondary fw-bold" {{ $roster && is_null($roster->shift_id) ? 'selected' : '' }}>
                                                                        OFF
                                                                    </option>
                                                                    @foreach($activeShifts as $ashift)
                                                                        <option value="{{ $ashift->id }}" class="text-primary fw-bold" {{ $assignedShiftId == $ashift->id ? 'selected' : '' }}>
                                                                            {{ $ashift->code }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                                @if($employees->isEmpty())
                                                    <tr>
                                                        <td colspan="{{ count($dates) + 1 }}" class="text-center py-5 text-muted">
                                                            No employees found matching the current filters.
                                                        </td>
                                                    </tr>
                                                @endif
                                            </tbody>
                                        </table>
                                    </div>
                                </x-ui.card>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Roster Board Modals -->
    <!-- Bulk Assign Roster Modal -->
    <div class="modal fade" id="assignRosterModal" tabindex="-1" aria-labelledby="assignRosterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="assignRosterModalLabel"><i class="feather-calendar me-2 text-primary"></i>Assign Roster</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.roster.assign') }}" method="POST">
                    @csrf
                    <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                    <input type="hidden" name="department_id" value="{{ $selectedDepartmentId }}">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <!-- 1. Cascading Organization Group Multi-Selectors (Vertical, No labels cutoff) -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">Companies</label>
                                <select id="assign_company_select" name="bulk_company_ids[]" class="form-control select2-modal" multiple data-placeholder="All Companies">
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">Business Units</label>
                                <select id="assign_bu_select" name="bulk_business_unit_ids[]" class="form-control select2-modal" multiple data-placeholder="All Business Units">
                                    @foreach($businessUnits as $bu)
                                        <option value="{{ $bu->id }}" data-company-id="{{ $bu->company_id }}">{{ $bu->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">Branches</label>
                                <select id="assign_branch_select" name="bulk_branch_ids[]" class="form-control select2-modal" multiple data-placeholder="All Branches">
                                    @foreach($branches as $br)
                                        <option value="{{ $br->id }}" data-business-unit-id="{{ $br->business_unit_id }}">{{ $br->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">Departments</label>
                                <select id="assign_dept_select" name="bulk_department_ids[]" class="form-control select2-modal" multiple data-placeholder="All Departments">
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" data-company-id="{{ $dept->company_id }}">{{ $dept->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">Designations</label>
                                <select id="assign_desg_select" name="bulk_designation_ids[]" class="form-control select2-modal" multiple data-placeholder="All Designations">
                                    @foreach($designations as $desg)
                                        <option value="{{ $desg->id }}">{{ $desg->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- 2. Dynamic Search & Checkboxes (Employee List) -->
                            <div class="col-12 mt-4">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">Select Employees</label>
                                <div class="input-group input-group-sm mb-2">
                                    <span class="input-group-text bg-light"><i class="feather-search text-muted"></i></span>
                                    <input type="text" id="assignEmpSearch" class="form-control form-control-sm" placeholder="Type name to filter list...">
                                </div>
                                <div class="border rounded p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="selectAllAssignEmployees">
                                        <label class="form-check-label fw-bold text-primary" for="selectAllAssignEmployees">Select All Visible</label>
                                    </div>
                                    <hr class="my-2">
                                    <div id="assignEmployeeList">
                                        @foreach($employees as $emp)
                                            <div class="form-check mb-1 assign-emp-item" 
                                                 data-company-id="{{ $emp->company_id }}" 
                                                 data-business-unit-id="{{ $emp->business_unit_id }}"
                                                 data-branch-id="{{ $emp->branch_id }}"
                                                 data-department-id="{{ $emp->department_id }}" 
                                                 data-designation-id="{{ $emp->designation_id }}"
                                                 data-name="{{ strtolower($emp->full_name) }}">
                                                <input class="form-check-input assign-emp-checkbox" type="checkbox" name="employee_ids[]" value="{{ $emp->id }}" id="emp_assign_{{ $emp->id }}">
                                                <label class="form-check-label text-dark fs-12" for="emp_assign_{{ $emp->id }}">
                                                    {{ $emp->full_name }} 
                                                    <span class="text-muted" style="font-size: 10px;">
                                                        ({{ $emp->department?->name ?? 'No Dept' }} / {{ $emp->designation?->name ?? 'No Desg' }})
                                                    </span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div id="assignNoEmployeesMsg" class="text-center text-muted py-3 d-none">
                                        No employees match the current filters.
                                    </div>
                                </div>
                                <div class="text-muted fs-11 mt-1">If no checkboxes are selected, shifts will assign to everyone matching the filters selected above.</div>
                            </div>

                            <!-- 3. Scheduling Settings (Vertical Date Fields, No cut-offs) -->
                            <div class="col-12 mt-4">
                                <hr class="my-2">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">Shift to Assign</label>
                                <select name="shift_id" class="form-select form-select-sm">
                                    <option value="">— Day Off (OFF) —</option>
                                    @foreach($activeShifts as $ashift)
                                        <option value="{{ $ashift->id }}">{{ $ashift->name }} ({{ $ashift->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">Status</label>
                                <select name="status" class="form-select form-select-sm">
                                    <option value="scheduled" selected>Scheduled</option>
                                    <option value="approved">Approved</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">Start Date <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" class="form-control form-control-sm" required value="{{ $startDate->format('Y-m-d') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">End Date <span class="text-danger">*</span></label>
                                <input type="date" name="end_date" class="form-control form-control-sm" required value="{{ $startDate->copy()->addDays(6)->format('Y-m-d') }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">Notes</label>
                                <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Optional comments or rotation notes..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Assign Shift</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Clear Roster Modal -->
    <div class="modal fade" id="clearRosterModal" tabindex="-1" aria-labelledby="clearRosterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-danger" id="clearRosterModalLabel"><i class="feather-trash me-2"></i>Clear Roster Assignments</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('hrms.roster.clear') }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                    <input type="hidden" name="department_id" value="{{ $selectedDepartmentId }}">
                    <input type="hidden" name="start_date" value="{{ $startDate->format('Y-m-d') }}">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <!-- 1. Cascading Organization Group Multi-Selectors -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">Companies</label>
                                <select id="clear_company_select" name="bulk_company_ids[]" class="form-control select2-modal" multiple data-placeholder="All Companies">
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->company_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">Business Units</label>
                                <select id="clear_bu_select" name="bulk_business_unit_ids[]" class="form-control select2-modal" multiple data-placeholder="All Business Units">
                                    @foreach($businessUnits as $bu)
                                        <option value="{{ $bu->id }}" data-company-id="{{ $bu->company_id }}">{{ $bu->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">Branches</label>
                                <select id="clear_branch_select" name="bulk_branch_ids[]" class="form-control select2-modal" multiple data-placeholder="All Branches">
                                    @foreach($branches as $br)
                                        <option value="{{ $br->id }}" data-business-unit-id="{{ $br->business_unit_id }}">{{ $br->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">Departments</label>
                                <select id="clear_dept_select" name="bulk_department_ids[]" class="form-control select2-modal" multiple data-placeholder="All Departments">
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" data-company-id="{{ $dept->company_id }}">{{ $dept->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">Designations</label>
                                <select id="clear_desg_select" name="bulk_designation_ids[]" class="form-control select2-modal" multiple data-placeholder="All Designations">
                                    @foreach($designations as $desg)
                                        <option value="{{ $desg->id }}">{{ $desg->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- 2. Dynamic Checkbox Container -->
                            <div class="col-12 mt-4">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">Select Employees to Clear</label>
                                <div class="input-group input-group-sm mb-2">
                                    <span class="input-group-text bg-light"><i class="feather-search text-muted"></i></span>
                                    <input type="text" id="clearEmpSearch" class="form-control form-control-sm" placeholder="Type name to filter list...">
                                </div>
                                <div class="border rounded p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="selectAllClearEmployees">
                                        <label class="form-check-label fw-bold text-danger" for="selectAllClearEmployees">Select All Visible</label>
                                    </div>
                                    <hr class="my-2">
                                    <div id="clearEmployeeList">
                                        @foreach($employees as $emp)
                                            <div class="form-check mb-1 clear-emp-item" 
                                                 data-company-id="{{ $emp->company_id }}" 
                                                 data-business-unit-id="{{ $emp->business_unit_id }}"
                                                 data-branch-id="{{ $emp->branch_id }}"
                                                 data-department-id="{{ $emp->department_id }}" 
                                                 data-designation-id="{{ $emp->designation_id }}"
                                                 data-name="{{ strtolower($emp->full_name) }}">
                                                <input class="form-check-input clear-emp-checkbox" type="checkbox" name="employee_ids[]" value="{{ $emp->id }}" id="emp_clear_{{ $emp->id }}">
                                                <label class="form-check-label text-dark fs-12" for="emp_clear_{{ $emp->id }}">
                                                    {{ $emp->full_name }}
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div id="clearNoEmployeesMsg" class="text-center text-muted py-3 d-none">
                                        No employees match the current filters.
                                    </div>
                                </div>
                                <div class="text-muted fs-11 mt-1">If no checkboxes are selected, assignments will clear for everyone in the selected groups.</div>
                            </div>

                            <div class="col-12 mt-4">
                                <hr class="my-2">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">Start Date <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" class="form-control form-control-sm" required value="{{ $startDate->format('Y-m-d') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">End Date <span class="text-danger">*</span></label>
                                <input type="date" name="end_date" class="form-control form-control-sm" required value="{{ $startDate->copy()->addDays(6)->format('Y-m-d') }}">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">Clear Entries</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Include all HRMS Modals (includes shift add, edit, view modals) -->
    @include('modules.hrms.partials.modals')
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Move modals to body root to avoid stacking context issues
            document.querySelectorAll('.modal').forEach(modal => {
                document.body.appendChild(modal);
            });

            // Prevent dropdown menus from closing on clicking inputs inside them (Vanilla JS)
            document.addEventListener('click', function (e) {
                if (e.target.closest('.dropdown-menu')) {
                    e.stopPropagation();
                }
            });

            // 1. TABLE ROW SEARCHING AND SORTING ACTIONS (Vanilla JS)
            const searchInput = document.getElementById('rosterSearch');
            const filterSearchInput = document.getElementById('filterSearchInput');

            function applySearch(val) {
                const searchVal = val.toLowerCase().replace(/\s+/g, ' ').trim();
                document.querySelectorAll('.roster-grid-table tbody tr').forEach(row => {
                    const nameEl = row.querySelector('.employee-name-label');
                    const desgEl = row.querySelector('.employee-designation-label');
                    if (!nameEl) return;

                    const name = nameEl.textContent.toLowerCase().replace(/\s+/g, ' ').trim();
                    const desg = desgEl ? desgEl.textContent.toLowerCase().replace(/\s+/g, ' ').trim() : '';

                    if (!searchVal || name.includes(searchVal) || desg.includes(searchVal)) {
                        row.classList.remove('d-none');
                    } else {
                        row.classList.add('d-none');
                    }
                });
            }

            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    applySearch(this.value);
                });
                searchInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') e.preventDefault();
                });
            }

            const sortOptions = document.querySelectorAll('.sort-option');
            sortOptions.forEach(opt => {
                opt.addEventListener('click', function(e) {
                    e.preventDefault();
                    sortOptions.forEach(o => o.classList.remove('active'));
                    this.classList.add('active');

                    // Close the dropdown menu
                    const openMenus = document.querySelectorAll('.dropdown-menu.show, .dropdown.show, .erp-sort-dropdown.show');
                    openMenus.forEach(menu => menu.classList.remove('show'));

                    const sortBy = this.dataset.sort;
                    const tbody = document.querySelector('.roster-grid-table tbody');
                    if (!tbody) return;

                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    const emptyRow = rows.find(r => r.querySelector('td[colspan]'));
                    if (emptyRow) return;

                    rows.sort((a, b) => {
                        const nameAEl = a.querySelector('.employee-name-label');
                        const nameBEl = b.querySelector('.employee-name-label');
                        if (!nameAEl || !nameBEl) return 0;

                        const nameA = nameAEl.textContent.toLowerCase().replace(/\s+/g, ' ').trim();
                        const nameB = nameBEl.textContent.toLowerCase().replace(/\s+/g, ' ').trim();

                        const desgAEl = a.querySelector('.employee-designation-label');
                        const desgBEl = b.querySelector('.employee-designation-label');
                        const desgA = desgAEl ? desgAEl.textContent.toLowerCase().replace(/\s+/g, ' ').trim() : '';
                        const desgB = desgBEl ? desgBEl.textContent.toLowerCase().replace(/\s+/g, ' ').trim() : '';

                        if (sortBy === 'name-asc') {
                            return nameA.localeCompare(nameB);
                        } else if (sortBy === 'name-desc') {
                            return nameB.localeCompare(nameA);
                        } else if (sortBy === 'designation') {
                            return desgA.localeCompare(desgB);
                        }
                        return 0;
                    });

                    rows.forEach(r => tbody.appendChild(r));
                });
            });

            // 2. JQUERY MODAL CASCADE AND SELECT2 INITIALIZATION (Wrapped safely)
            if (window.jQuery) {
                (function($) {
                    let originalBUs = [];
                    let originalBranches = [];
                    let originalDepts = [];

                    function cacheOriginalOptions() {
                        originalBUs = Array.from(document.querySelectorAll('#assign_bu_select option')).map(opt => ({
                            value: opt.value,
                            text: opt.textContent,
                            companyId: opt.getAttribute('data-company-id')
                        }));
                        originalBranches = Array.from(document.querySelectorAll('#assign_branch_select option')).map(opt => ({
                            value: opt.value,
                            text: opt.textContent,
                            businessUnitId: opt.getAttribute('data-business-unit-id')
                        }));
                        originalDepts = Array.from(document.querySelectorAll('#assign_dept_select option')).map(opt => ({
                            value: opt.value,
                            text: opt.textContent,
                            companyId: opt.getAttribute('data-company-id')
                        }));
                    }

                    cacheOriginalOptions();

                    function setupCascadeSelectors(prefix) {
                        const compSelect = $(`#${prefix}_company_select`);
                        const buSelect = $(`#${prefix}_bu_select`);
                        const branchSelect = $(`#${prefix}_branch_select`);
                        const deptSelect = $(`#${prefix}_dept_select`);
                        const desgSelect = $(`#${prefix}_desg_select`);

                        compSelect.on('change', function() {
                            const selectedComps = $(this).val() || [];
                            const filteredBUs = originalBUs.filter(bu => selectedComps.length === 0 || selectedComps.includes(bu.companyId));
                            const currentBuVal = buSelect.val() || [];
                            buSelect.empty();
                            filteredBUs.forEach(bu => {
                                const isSelected = currentBuVal.includes(bu.value);
                                buSelect.append(new Option(bu.text, bu.value, isSelected, isSelected));
                            });
                            buSelect.trigger('change.select2');

                            const filteredDepts = originalDepts.filter(dept => selectedComps.length === 0 || selectedComps.includes(dept.companyId));
                            const currentDeptVal = deptSelect.val() || [];
                            deptSelect.empty();
                            filteredDepts.forEach(dept => {
                                const isSelected = currentDeptVal.includes(dept.value);
                                deptSelect.append(new Option(dept.text, dept.value, isSelected, isSelected));
                            });
                            deptSelect.trigger('change.select2');
                            filterEmployees(prefix);
                        });

                        buSelect.on('change', function() {
                            const selectedBUs = $(this).val() || [];
                            const filteredBranches = originalBranches.filter(br => selectedBUs.length === 0 || selectedBUs.includes(br.businessUnitId));
                            const currentBranchVal = branchSelect.val() || [];
                            branchSelect.empty();
                            filteredBranches.forEach(br => {
                                const isSelected = currentBranchVal.includes(br.value);
                                branchSelect.append(new Option(br.text, br.value, isSelected, isSelected));
                            });
                            branchSelect.trigger('change.select2');
                            filterEmployees(prefix);
                        });

                        branchSelect.on('change', () => filterEmployees(prefix));
                        deptSelect.on('change', () => filterEmployees(prefix));
                        desgSelect.on('change', () => filterEmployees(prefix));
                    }

                    function filterEmployees(prefix) {
                        const compVal = $(`#${prefix}_company_select`).val() || [];
                        const buVal = $(`#${prefix}_bu_select`).val() || [];
                        const branchVal = $(`#${prefix}_branch_select`).val() || [];
                        const deptVal = $(`#${prefix}_dept_select`).val() || [];
                        const desgVal = $(`#${prefix}_desg_select`).val() || [];
                        const searchVal = $(`#${prefix}EmpSearch`).val().toLowerCase().trim();

                        let visibleCount = 0;
                        document.querySelectorAll(`.${prefix}-emp-item`).forEach(item => {
                            const c = item.dataset.companyId;
                            const u = item.dataset.businessUnitId;
                            const b = item.dataset.branchId;
                            const d = item.dataset.departmentId;
                            const s = item.dataset.designationId;
                            const n = item.dataset.name;

                            const matchesComp = compVal.length === 0 || compVal.includes(c);
                            const matchesBU = buVal.length === 0 || buVal.includes(u);
                            const matchesBranch = branchVal.length === 0 || branchVal.includes(b);
                            const matchesDept = deptVal.length === 0 || deptVal.includes(d);
                            const matchesDesg = desgVal.length === 0 || desgVal.includes(s);
                            const matchesSearch = !searchVal || n.includes(searchVal);

                            if (matchesComp && matchesBU && matchesBranch && matchesDept && matchesDesg && matchesSearch) {
                                item.classList.remove('d-none');
                                visibleCount++;
                            } else {
                                item.classList.add('d-none');
                                const cb = item.querySelector(`.${prefix}-emp-checkbox`);
                                if (cb) cb.checked = false;
                            }
                        });
                        const noMsg = document.getElementById(`${prefix}NoEmployeesMsg`);
                        if (visibleCount === 0) noMsg.classList.remove('d-none'); else noMsg.classList.add('d-none');
                    }

                    setupCascadeSelectors('assign');
                    setupCascadeSelectors('clear');

                    $(`#assignEmpSearch`).on('input', () => filterEmployees('assign'));
                    $(`#clearEmpSearch`).on('input', () => filterEmployees('clear'));

                    $('#selectAllAssignEmployees').on('change', function() {
                        $('.assign-emp-item:not(.d-none) .assign-emp-checkbox').prop('checked', this.checked);
                    });
                    $('#selectAllClearEmployees').on('change', function() {
                        $('.clear-emp-item:not(.d-none) .clear-emp-checkbox').prop('checked', this.checked);
                    });

                    function initModalSelect2() {
                        if ($.fn.select2) {
                            $('.select2-modal').each(function() {
                                var select = $(this);
                                var modal = select.closest('.modal');
                                if (!select.hasClass('select2-hidden-accessible')) {
                                    select.select2({
                                        theme: "bootstrap-5",
                                        dropdownParent: modal.length ? modal : $(document.body),
                                        width: "100%",
                                        placeholder: select.data('placeholder')
                                    });
                                }
                            });
                        }
                    }

                    initModalSelect2();
                    $(document).on('shown.bs.modal', function () {
                        initModalSelect2();
                    });

                    const companySelect = document.querySelector('select[name="company_id"]');
                    const departmentSelect = document.querySelector('select[name="department_id"]');

                    if (companySelect && departmentSelect) {
                        const originalDeptOptions = Array.from(departmentSelect.options);
                        function filterDepartments() {
                            const companyId = companySelect.value;
                            departmentSelect.innerHTML = '';
                            const defaultOpt = originalDeptOptions[0];
                            departmentSelect.appendChild(defaultOpt);
                            originalDeptOptions.slice(1).forEach(opt => {
                                const optCompanyId = opt.getAttribute('data-company-id');
                                if (!companyId || optCompanyId === companyId) departmentSelect.appendChild(opt);
                            });
                            const currentSelected = departmentSelect.value;
                            const isValid = Array.from(departmentSelect.options).some(o => o.value === currentSelected);
                            if (!isValid) departmentSelect.value = '';
                        }
                        companySelect.addEventListener('change', filterDepartments);
                        filterDepartments();
                    }
                })(window.jQuery);
            }

            // 3. AJAX CELL SCHEDULE MATRIX UPDATE (Vanilla JS)
            document.querySelectorAll('.roster-cell-select').forEach(select => {
                select.addEventListener('change', function() {
                    const employeeId = this.dataset.employeeId;
                    const date = this.dataset.date;
                    const rawVal = this.value;
                    const shiftId = rawVal === 'off' ? null : (rawVal || null);
                    const cellTd = this.closest('td');

                    this.style.opacity = '0.5';
                    fetch("{{ route('hrms.roster.update-cell') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ employee_id: employeeId, date: date, shift_id: shiftId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        this.style.opacity = '1';
                        if (data.success) {
                            cellTd.className = 'date-cell';
                            if (rawVal === 'off') cellTd.classList.add('bg-soft-secondary');
                            else if (rawVal === '') cellTd.classList.add('bg-soft-light');
                            else cellTd.classList.add('bg-soft-primary');

                            if (typeof Swal !== 'undefined') {
                                Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true })
                                    .fire({ icon: 'success', title: data.message || 'Roster updated successfully.' });
                            }
                        } else alert('An error occurred while saving the shift.');
                    })
                    .catch(error => {
                        this.style.opacity = '1';
                        console.error('Error updating roster:', error);
                        alert('Network error. Failed to save shift.');
                    });
                });
            });

            // 4. SHIFT MASTER VIEW & EDIT BINDINGS (Vanilla JS)
            const btnViewShift = document.querySelector('.btn-view-shift');
            if (btnViewShift) {
                document.querySelectorAll('.btn-view-shift').forEach(btn => {
                    btn.addEventListener('click', function() {
                        let shift = JSON.parse(atob(this.dataset.shift));
                        document.getElementById('modal_view_shift_name').innerText = shift.name;
                        document.getElementById('modal_view_shift_code').innerText = shift.code;
                        document.getElementById('modal_view_shift_start').innerText = shift.start_time ? shift.start_time.substring(0, 5) : 'N/A';
                        document.getElementById('modal_view_shift_end').innerText = shift.end_time ? shift.end_time.substring(0, 5) : 'N/A';
                        document.getElementById('modal_view_shift_break').innerText = (shift.break_minutes || 0) + ' mins';
                        document.getElementById('modal_view_shift_overtime').innerHTML = (shift.overtime_allowed ? '<span class="badge bg-soft-success text-success">Yes</span>' : '<span class="badge bg-soft-danger text-danger">No</span>');
                        document.getElementById('modal_view_shift_status').innerHTML = (shift.active ? '<span class="badge bg-soft-success text-success">Active</span>' : '<span class="badge bg-soft-danger text-danger">Inactive</span>');
                    });
                });
            }

            const btnEditShift = document.querySelector('.btn-edit-shift');
            if (btnEditShift) {
                document.querySelectorAll('.btn-edit-shift').forEach(btn => {
                    btn.addEventListener('click', function() {
                        let shift = JSON.parse(atob(this.dataset.shift));
                        document.getElementById('edit_shift_name').value = shift.name || '';
                        document.getElementById('edit_shift_code').value = shift.code || '';
                        document.getElementById('edit_shift_start').value = shift.start_time ? shift.start_time.substring(0, 5) : '';
                        document.getElementById('edit_shift_end').value = shift.end_time ? shift.end_time.substring(0, 5) : '';
                        document.getElementById('edit_shift_break').value = shift.break_minutes || 0;
                        
                        let overtimeSelect = document.getElementById('edit_shift_overtime');
                        if (overtimeSelect && window.jQuery) {
                            overtimeSelect.value = (shift.overtime_allowed ? '1' : '0');
                            if (window.jQuery(overtimeSelect).hasClass('select2-hidden-accessible')) window.jQuery(overtimeSelect).trigger('change');
                        }
                        let activeSelect = document.getElementById('edit_shift_active');
                        if (activeSelect && window.jQuery) {
                            activeSelect.value = (shift.active ? '1' : '0');
                            if (window.jQuery(activeSelect).hasClass('select2-hidden-accessible')) window.jQuery(activeSelect).trigger('change');
                        }
                        let form = document.getElementById('shift_edit_form');
                        if (form) form.action = '/hrms/roster/shift/update/' + shift.id;
                    });
                });
            }
        });
    </script>
@endsection
