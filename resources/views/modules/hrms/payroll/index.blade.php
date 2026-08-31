@extends('layouts.duralux')

@section('title', 'Payroll Operations | SaaS ERP')
@section('page-title', 'Payroll Operations')
@section('breadcrumb', 'HRMS / Payroll Runs')

@section('page-actions')
    <x-ui.button variant="primary" icon="feather-play" data-bs-toggle="modal" data-bs-target="#initiateRunModal">
        Process New Month
    </x-ui.button>
@endsection

@section('content')
<style>
    .run-item {
        border-left: 4px solid transparent !important;
        transition: all 0.15s ease-in-out;
        border-bottom: 1px solid #f1f5f9 !important;
    }
    .run-item.active {
        background-color: #f1f5f9 !important;
        border-left-color: var(--bs-primary) !important;
    }
    .run-item:hover:not(.active) {
        background-color: #f8fafc !important;
    }
    #bulkAdhocModal .odoo-form-label {
        width: 160px !important;
    }
</style>

<div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
    <div class="row g-4">
        <!-- LEFT: Runs History List -->
        <div class="col-md-3 col-12 pe-4" style="border-right: 1px solid #e5e7eb !important;">
            <div class="pb-2.5 mb-3 border-bottom">
                <h6 class="fw-bold mb-0 text-dark"><i class="feather-calendar me-2 text-primary"></i>Payroll Runs</h6>
            </div>
            <div class="list-group list-group-flush border rounded-3 overflow-hidden">
                @forelse($runs as $run)
                    @php $isActive = $selectedRun && $selectedRun->id === $run->id; @endphp
                    <a href="{{ route('hrms.payroll.index', ['run_id' => $run->id]) }}" 
                       class="list-group-item list-group-item-action py-3 px-4 run-item {{ $isActive ? 'active' : '' }}">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold text-dark fs-14">
                                {{ Carbon\Carbon::parse($run->payroll_month . '-01')->format('F Y') }}
                            </span>
                            @if($run->status === 'draft')
                                <span class="badge bg-soft-warning text-warning px-2.5 py-1 rounded-pill fs-10">Draft</span>
                            @elseif($run->status === 'locked')
                                <span class="badge bg-soft-primary text-primary px-2.5 py-1 rounded-pill fs-10">Locked</span>
                            @else
                                <span class="badge bg-soft-success text-success px-2.5 py-1 rounded-pill fs-10">Paid</span>
                            @endif
                        </div>
                        <div class="fs-11 text-muted">
                            {{ $run->start_date->format('d M') }} - {{ $run->end_date->format('d M Y') }}
                        </div>
                    </a>
                @empty
                    <div class="text-center py-5 text-muted">
                        <i class="feather-info fs-24 mb-2 d-block text-secondary"></i>
                        <span>No payroll runs initiated.</span>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- RIGHT: Active Run Register -->
        <div class="col-md-9 col-12 ps-4" style="max-height: calc(100vh - 180px); overflow-y: auto;">
            @if($selectedRun)
                <div>
                    <!-- Cycle Metadata & Payout Actions -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center border-bottom pb-3 mb-4 gap-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-1">
                                Payroll Register &mdash; {{ Carbon\Carbon::parse($selectedRun->payroll_month . '-01')->format('F Y') }}
                                @if($selectedRun->payGroup)
                                    <span class="badge bg-soft-info text-info fs-12 ms-2 fw-semibold">{{ $selectedRun->payGroup->name }}</span>
                                @else
                                    <span class="badge bg-soft-secondary text-secondary fs-12 ms-2 fw-semibold">All Pay Groups</span>
                                @endif
                            </h5>
                            <div class="text-muted fs-12">
                                Cycle dates: {{ $selectedRun->start_date->format('F d, Y') }} to {{ $selectedRun->end_date->format('F d, Y') }}
                            </div>
                        </div>
                        <div class="d-inline-flex gap-2 align-items-center">
                            @if($selectedRun->status !== 'draft')
                                <x-ui.button variant="outline-primary" icon="feather-download-cloud" href="{{ route('hrms.payroll.run.export-bank-file', $selectedRun->id) }}" class="fw-bold">
                                    Export Bank File
                                </x-ui.button>
                            @endif
                            @if($selectedRun->status === 'draft')
                                <button type="button" class="btn btn-secondary fw-bold" data-bs-toggle="modal" data-bs-target="#bulkAdhocModal">
                                    <i class="feather-plus-circle me-1.5"></i>Bulk Ad-hoc
                                </button>
                                <form action="{{ route('hrms.payroll.run.lock', $selectedRun->id) }}" method="POST" class="d-inline m-0">
                                    @csrf
                                    <button type="submit" class="btn btn-warning text-white fw-bold"><i class="feather-lock me-2"></i>Lock Payroll Register</button>
                                </form>
                            @elseif($selectedRun->status === 'locked')
                                <form action="{{ route('hrms.payroll.run.release', $selectedRun->id) }}" method="POST" class="d-inline m-0">
                                    @csrf
                                    <button type="submit" class="btn btn-success fw-bold"><i class="feather-check-circle me-2"></i>Release Payouts</button>
                                </form>
                            @else
                                <button class="btn btn-outline-success fw-bold border-2 m-0" disabled><i class="feather-check me-2"></i>Payout Completed</button>
                            @endif
                        </div>
                    </div>

                    <!-- Search, Sort, Filter Bar -->
                    <div class="d-flex justify-content-end align-items-center mb-3 pb-3 border-bottom gap-2 w-100 flex-wrap" id="payrollRegisterFiltersBar">
                        <!-- Search Form -->
                        <form method="GET" action="{{ route('hrms.payroll.index') }}" id="payrollSearchForm" class="d-flex align-items-center bg-light border rounded px-3 py-1 m-0" style="min-width: 260px; height: 36px !important; box-sizing: border-box !important;">
                            <input type="hidden" name="run_id" value="{{ $selectedRun->id }}">
                            <input type="hidden" name="status" id="payroll_status_input" value="{{ $filters['status'] ?? '' }}">
                            <input type="hidden" name="department_id" id="payroll_dept_input" value="{{ $filters['department_id'] ?? '' }}">
                            <input type="hidden" name="sort" id="payroll_sort_input" value="{{ $filters['sort'] ?? 'name_asc' }}">
                            <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                            <input type="text" name="search" id="payrollSearchInput" class="w-100 border-0 bg-transparent p-0 fs-13" placeholder="Search employee by name or ID..." value="{{ $filters['search'] ?? '' }}" style="box-shadow: none; height: 100%; outline: none;">
                        </form>

                        <!-- Sort Dropdown -->
                        <x-ui.sort-dropdown label="Sort">
                            <a class="dropdown-item payroll-sort-link d-flex justify-content-between align-items-center py-2 {{ ($filters['sort'] ?? '') === 'name_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'name_asc']) }}" data-sort="name_asc">
                                <span>Name: A to Z</span>
                                @if(($filters['sort'] ?? '') === 'name_asc') <i class="feather-check ms-3"></i> @endif
                            </a>
                            <a class="dropdown-item payroll-sort-link d-flex justify-content-between align-items-center py-2 {{ ($filters['sort'] ?? '') === 'name_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'name_desc']) }}" data-sort="name_desc">
                                <span>Name: Z to A</span>
                                @if(($filters['sort'] ?? '') === 'name_desc') <i class="feather-check ms-3"></i> @endif
                            </a>
                            <a class="dropdown-item payroll-sort-link d-flex justify-content-between align-items-center py-2 {{ ($filters['sort'] ?? '') === 'net_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'net_desc']) }}" data-sort="net_desc">
                                <span>Net Payout: High to Low</span>
                                @if(($filters['sort'] ?? '') === 'net_desc') <i class="feather-check ms-3"></i> @endif
                            </a>
                            <a class="dropdown-item payroll-sort-link d-flex justify-content-between align-items-center py-2 {{ ($filters['sort'] ?? '') === 'net_asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'net_asc']) }}" data-sort="net_asc">
                                <span>Net Payout: Low to High</span>
                                @if(($filters['sort'] ?? '') === 'net_asc') <i class="feather-check ms-3"></i> @endif
                            </a>
                            <a class="dropdown-item payroll-sort-link d-flex justify-content-between align-items-center py-2 {{ ($filters['sort'] ?? '') === 'lop_desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'lop_desc']) }}" data-sort="lop_desc">
                                <span>LOP Days: High to Low</span>
                                @if(($filters['sort'] ?? '') === 'lop_desc') <i class="feather-check ms-3"></i> @endif
                            </a>
                        </x-ui.sort-dropdown>

                        <!-- Filter Dropdown -->
                        <x-ui.filter label="Filter">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders text-primary me-1"></i> Filter Options</h6>
                            <form id="payrollFilterForm" method="GET" action="{{ route('hrms.payroll.index') }}">
                                <input type="hidden" name="run_id" value="{{ $selectedRun->id }}">
                                <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
                                <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'name_asc' }}">
                                
                                <div class="mb-3" style="min-width: 200px;">
                                    <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">PAYOUT STATUS</label>
                                    <x-ui.odoo-form-ui type="select" name="status" id="payrollFilterStatus" class="form-select" style="border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                                        <option value="">All Statuses</option>
                                        <option value="approved" @selected(($filters['status'] ?? '') === 'approved')>Approved / Paid</option>
                                        <option value="held" @selected(($filters['status'] ?? '') === 'held')>Withheld</option>
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="mb-3" style="min-width: 200px;">
                                    <label class="form-label fw-bold fs-11 text-muted text-uppercase mb-1">DEPARTMENT</label>
                                    <x-ui.odoo-form-ui type="select" name="department_id" id="payrollFilterDept" class="form-select" style="border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                                        <option value="">All Departments</option>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept->id }}" @selected(($filters['department_id'] ?? '') == $dept->id)>{{ $dept->name }}</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>
                                
                                <div class="d-flex gap-3 justify-content-between mt-4">
                                    <button type="submit" class="btn btn-sm text-uppercase fw-bold py-2.5 px-4 text-white payroll-apply-btn flex-grow-1 text-center" style="border-radius: 12px !important; font-size: 11px; letter-spacing: 0.05em; background-color: #4a3b32; border: none;">APPLY FILTERS</button>
                                    <a href="{{ route('hrms.payroll.index', ['run_id' => $selectedRun->id]) }}" class="btn btn-sm text-uppercase fw-bold py-2.5 px-4 payroll-reset-btn flex-grow-1 text-center" style="border-radius: 12px !important; font-size: 11px; letter-spacing: 0.05em; background-color: #f1f3f7; border: 1px solid #cbd5e1; color: #334155;">RESET</a>
                                </div>
                            </form>
                        </x-ui.filter>


                    </div>

                    <!-- PENDING APPROVALS CHECKLIST -->
                    @if($selectedRun->status === 'draft' && $pendingIssues['total'] > 0)
                        <div class="alert alert-danger border-danger-subtle rounded-3 p-4 mb-4 animate__animated animate__fadeIn">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 40px; height: 40px; background-color: rgba(220, 53, 69, 0.1); color: #dc3545;">
                                        <i class="feather-alert-triangle fs-18"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-dark mb-1">Payroll Blocked: Unresolved Pending Approvals Detected</h6>
                                        <p class="fs-12 text-muted mb-2">There are <strong>{{ $pendingIssues['total'] }}</strong> pending items in this pay cycle that must be resolved before payroll can be locked.</p>
                                        <div class="d-flex flex-wrap gap-3 fs-11 text-dark fw-semibold">
                                            <span class="badge bg-soft-secondary text-secondary px-2.5 py-1.5"><i class="feather-minus-circle me-1"></i>Leaves: {{ $pendingIssues['leaves'] }}</span>
                                            <span class="badge bg-soft-secondary text-secondary px-2.5 py-1.5"><i class="feather-clock me-1"></i>Corrections: {{ $pendingIssues['corrections'] }}</span>
                                            <span class="badge bg-soft-secondary text-secondary px-2.5 py-1.5"><i class="feather-watch me-1"></i>Overtime: {{ $pendingIssues['overtime'] }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <button class="btn btn-danger fw-bold text-white shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#resolveIssuesModal">
                                        <i class="feather-check-square me-2"></i>Resolve Issues
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- PENDING PRIOR HOLDS ALERTS -->
                    @if(!empty($pendingPriorHolds))
                        <div class="alert alert-warning border-warning-subtle rounded-3 p-3.5 mb-4 animate__animated animate__fadeIn">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="feather-alert-triangle text-warning fs-18"></i>
                                <h6 class="fw-bold text-dark mb-0">Pending Withheld Salaries Detected</h6>
                            </div>
                            <p class="fs-12 text-muted mb-3">The following employees have active holds on their payouts from previous months. You can release them directly into the current <strong>{{ Carbon\Carbon::parse($selectedRun->payroll_month . '-01')->format('F Y') }}</strong> payroll cycle as Arrears:</p>
                            <div class="list-group list-group-flush border rounded-3 bg-white overflow-hidden" style="max-width: 650px;">
                                @foreach($pendingPriorHolds as $prior)
                                    <div class="list-group-item d-flex justify-content-between align-items-center py-2.5 px-3 fs-12 text-dark">
                                        <div>
                                            <span class="fw-bold">{{ $prior['employee']->full_name }}</span> 
                                            <span class="text-muted">({{ $prior['employee']->employee_id }}) &bull; Withheld for {{ Carbon\Carbon::parse($prior['hold']->payroll_month . '-01')->format('F Y') }}</span>
                                        </div>
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="fw-bold text-primary">₹{{ number_format($prior['net_payout'], 2) }}</span>
                                            @if($selectedRun->status === 'draft')
                                                <form action="{{ route('hrms.payroll.hold.toggle', [$prior['employee']->id, $prior['hold']->payroll_month]) }}" method="POST" class="m-0">
                                                    @csrf
                                                    <input type="hidden" name="target_month" value="{{ $selectedRun->payroll_month }}">
                                                    <button type="submit" class="btn btn-xs btn-primary text-white fw-bold px-2.5 py-1 fs-11">
                                                        <i class="feather-plus me-1"></i>Release to {{ Carbon\Carbon::parse($selectedRun->payroll_month . '-01')->format('M') }} Payout
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-muted fs-11"><i class="feather-lock me-1"></i>Cycle Locked</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Calculations Register Table -->
                    <div class="table-responsive" id="payrollRegisterTableWrapper">
                        <table class="table table-hover align-middle mb-0 text-dark">
                            <thead class="table-light fs-11 text-uppercase tracking-wider">
                                <tr>
                                    <th>Employee Details</th>
                                    <th class="text-center">LOP Days</th>
                                    <th class="text-end">Base Gross</th>
                                    <th class="text-end">Salary Deductions</th>
                                    <th class="text-end">Ad-hoc</th>
                                    <th class="text-end">Overtime</th>
                                    <th class="text-end">Retro Refunds</th>
                                    <th class="text-end">Net Payout</th>
                                    <th class="text-center">Payout Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="payrollRegisterTableBody">
                                @forelse($registerData as $row)
                                    @php $summary = $row['calc']; @endphp
                                    <tr class="payroll-register-row" 
                                        data-name="{{ strtolower($row['employee']->full_name) }}" 
                                        data-id="{{ strtolower($row['employee']->employee_id) }}" 
                                        data-department="{{ $row['employee']->department_id }}"
                                        data-held="{{ ($selectedRun->status === 'paid' ? $row['hold_status'] === 'on_hold' : $row['is_held']) ? 'held' : 'approved' }}"
                                        data-net="{{ (float)($summary['net_payout'] ?? 0) }}"
                                        data-lop="{{ (float)($summary['lop_days'] ?? 0) }}">
                                        <td>
                                            <div class="fw-bold text-dark">{{ $row['employee']->full_name }}</div>
                                            <span class="fs-12 text-muted">{{ $row['employee']->employee_id }} &bull; {{ $row['employee']->job_title }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge {{ ($summary['lop_days'] ?? 0) > 0 ? 'bg-soft-danger text-danger' : 'bg-soft-secondary text-secondary' }} px-2 py-1">
                                                {{ $summary['lop_days'] ?? 0 }} days
                                            </span>
                                        </td>
                                        <td class="text-end fw-semibold">₹{{ number_format($summary['base_gross_earnings'] ?? 0, 2) }}</td>
                                        <td class="text-end text-danger">₹{{ number_format($summary['base_deductions'] ?? 0, 2) }}</td>
                                        @php
                                            $netAdhoc = ($summary['adhoc_earnings'] ?? 0) - ($summary['adhoc_deductions'] ?? 0);
                                        @endphp
                                        <td class="text-end fw-semibold @if($netAdhoc > 0) text-success @elseif($netAdhoc < 0) text-danger @else text-secondary @endif">
                                            @if($netAdhoc > 0)
                                                +₹{{ number_format($netAdhoc, 2) }}
                                            @elseif($netAdhoc < 0)
                                                -₹{{ number_format(abs($netAdhoc), 2) }}
                                            @else
                                                ₹0.00
                                            @endif
                                        </td>
                                        <td class="text-end fw-semibold text-success">
                                            @if(($summary['overtime_payout'] ?? 0) > 0)
                                                ₹{{ number_format($summary['overtime_payout'], 2) }}
                                                <div class="fs-10 text-muted" style="font-size: 10px;">{{ number_format($summary['overtime_hours'] ?? 0, 1) }} hrs</div>
                                            @else
                                                <span class="text-secondary">₹0.00</span>
                                            @endif
                                        </td>
                                        <td class="text-end text-primary">₹{{ number_format($summary['retro_lop_reversals'] ?? 0, 2) }}</td>
                                        <td class="text-end fw-bold text-primary">₹{{ number_format($summary['net_payout'] ?? 0, 2) }}</td>
                                        <td class="text-center text-nowrap">
                                            @if($selectedRun->status === 'paid')
                                                @if($row['hold_status'] === 'on_hold')
                                                    <span class="badge bg-soft-danger text-danger px-2.5 py-1.5 rounded-pill fs-11">Withheld</span>
                                                @elseif($row['hold_status'] === 'released')
                                                    <span class="badge bg-soft-info text-info px-2.5 py-1.5 rounded-pill fs-11">Released (Paid)</span>
                                                @else
                                                    <span class="badge bg-soft-success text-success px-2.5 py-1.5 rounded-pill fs-11">Paid</span>
                                                @endif
                                            @else
                                                @if($row['is_held'])
                                                    <span class="badge bg-soft-danger text-danger px-2.5 py-1.5 rounded-pill fs-11">Withheld</span>
                                                @else
                                                    <span class="badge bg-soft-success text-success px-2.5 py-1.5 rounded-pill fs-11">Approved</span>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="text-center text-nowrap">
                                            <div class="d-flex align-items-center justify-content-center gap-2">
                                                <button type="button" class="btn btn-icon btn-sm btn-soft-primary rounded-circle show-salary-details" 
                                                        data-employee-name="{{ $row['employee']->full_name }}" 
                                                        data-employee-id="{{ $row['employee']->employee_id }}"
                                                        data-employee-db-id="{{ $row['employee']->id }}"
                                                        data-run-id="{{ $selectedRun->id }}"
                                                        data-job-title="{{ $row['employee']->job_title }}"
                                                        data-bank="{{ $row['employee']->bank_name ?? '—' }}"
                                                        data-bank-account="{{ $row['employee']->account_number ?? '—' }}"
                                                        data-proration-days="{{ ($summary['total_days'] ?? 30) - ($summary['lop_days'] ?? 0) }} / {{ $summary['total_days'] ?? 30 }} Days @if(($summary['lop_days'] ?? 0) > 0) (-{{ $summary['lop_days'] }} LOP) @endif"
                                                        data-run-status="{{ $selectedRun->status }}"
                                                        data-released-by="{{ $selectedRun->processedBy?->name ?? 'Finance' }}"
                                                        data-released-at="{{ $selectedRun->updated_at ? $selectedRun->updated_at->format('d M Y H:i') : '' }}"
                                                        data-month="{{ Carbon\Carbon::parse($selectedRun->payroll_month . '-01')->format('F Y') }}" 
                                                        data-items='@json($row['items'] ?? [])'
                                                        data-summary='@json($row['calc'] ?? [])'
                                                        title="View salary components breakdown">
                                                    <i class="feather-eye"></i>
                                                </button>
                                                @if($selectedRun->status === 'draft' || ($selectedRun->status === 'paid' && $row['hold_status'] === 'on_hold'))
                                                    <form action="{{ route('hrms.payroll.hold.toggle', [$row['employee']->id, $selectedRun->payroll_month]) }}" method="POST" class="m-0">
                                                        @csrf
                                                        @if($selectedRun->status === 'paid')
                                                            <input type="hidden" name="target_month" value="{{ $selectedRun->payroll_month }}">
                                                        @endif
                                                        <button type="submit" class="btn btn-icon btn-sm {{ $row['is_held'] ? 'btn-soft-success' : 'btn-soft-danger' }} rounded-circle" title="{{ $row['is_held'] ? 'Release Payout' : 'Hold' }}">
                                                            <i class="feather-{{ $row['is_held'] ? 'play' : 'pause' }}"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center py-5 text-muted">
                                            <i class="feather-info fs-24 mb-2 d-block text-secondary"></i>
                                            <span>No matching payroll records found.</span>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="p-5 text-center">
                    <i class="feather-info fs-36 text-secondary mb-3"></i>
                    <h6 class="fw-bold text-dark">No Active Payroll Run</h6>
                    <p class="text-muted fs-13 mb-0">Select an existing cycle from the history panel or click "Process New Month" to begin calculations.</p>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- INITIATE RUN MODAL -->
<div class="modal fade" id="initiateRunModal" tabindex="-1" aria-labelledby="initiateRunModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="initiateRunModalLabel"><i class="feather-play me-2 text-primary"></i>Initiate Payroll Run</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.payroll.run.store') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" inputType="month" label="Payroll Month (YYYY-MM)" name="payroll_month" id="initiate_payroll_month" :required="true" placeholder="e.g. 2026-08" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="Pay Group" name="pay_group_id" id="initiate_pay_group_id" helperText="Select a specific pay group to process or leave as all pay groups.">
                                <option value="">All Pay Groups (Process All Employees)</option>
                                @foreach($payGroups as $pg)
                                    <option value="{{ $pg->id }}">{{ $pg->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" inputType="date" label="Start Date" name="start_date" id="initiate_start_date" :required="true" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" inputType="date" label="End Date" name="end_date" id="initiate_end_date" :required="true" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary text-white">Start Calculations</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SALARY DETAILS MODAL -->
<div class="modal fade" id="salaryDetailsModal" tabindex="-1" aria-labelledby="salaryDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 12px; overflow: hidden;">

            <!-- Header -->
            <div class="modal-header border-0 text-white px-4 py-3" style="background: linear-gradient(135deg, #3b5bdb 0%, #1c3faa 100%);">
                <div class="d-flex align-items-center gap-2">
                    <div class="d-flex align-items-center justify-content-center rounded-circle" style="width:34px;height:34px;background:rgba(255,255,255,0.2);">
                        <i class="feather-dollar-sign" style="font-size:16px;"></i>
                    </div>
                    <h5 class="modal-title fw-bold mb-0 text-white" id="salaryDetailsModalLabel" style="font-size:15px; color: #ffffff !important;">
                        Payslip Details — <span id="modalPayrollMonth"></span>
                    </h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body px-4 pt-4 pb-2" style="background:#fff;">

                <!-- Employee Info Row -->
                <div class="row g-3 pb-3 mb-3" style="border-bottom:1px solid #f0f2f5;">
                    <div class="col-6 col-md-3">
                        <div class="text-muted" style="font-size:10px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;">Employee Name</div>
                        <div class="fw-bold text-dark mt-1" style="font-size:13px;" id="modalEmployeeName"></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted" style="font-size:10px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;">Employee ID &amp; Role</div>
                        <div class="fw-bold text-dark mt-1" style="font-size:13px;" id="modalEmployeeIdRole"></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted" style="font-size:10px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;">Bank &amp; Account</div>
                        <div class="fw-bold text-dark mt-1" style="font-size:13px;" id="modalBankAccount"></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted" style="font-size:10px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;">Proration Days</div>
                        <div class="fw-bold text-dark mt-1" style="font-size:13px;" id="modalProrationDays"></div>
                    </div>
                </div>

                <!-- Earnings & Deductions Two-Column -->
                <div class="row g-4">
                    <!-- Earnings -->
                    <div class="col-md-6">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="d-flex align-items-center justify-content-center rounded-circle" style="width:22px;height:22px;background:#e9faf2;">
                                <i class="feather-plus" style="font-size:12px;color:#1e9b60;"></i>
                            </div>
                            <span class="fw-bold" style="font-size:13px;color:#1e9b60;">Earnings</span>
                        </div>
                        <div id="earningsTableBody" class="d-flex flex-column gap-2" style="min-height:60px;"></div>
                        <div class="d-flex justify-content-between align-items-center mt-3 pt-2" style="border-top:2px solid #e9ecef;">
                            <span class="fw-bold text-dark" style="font-size:13px;">Gross Earnings</span>
                            <span class="fw-bold text-dark" style="font-size:13px;" id="modalGrossEarnings">₹0.00</span>
                        </div>
                    </div>
                    <!-- Deductions -->
                    <div class="col-md-6">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="d-flex align-items-center justify-content-center rounded-circle" style="width:22px;height:22px;background:#fdecea;">
                                <i class="feather-minus" style="font-size:12px;color:#e53e3e;"></i>
                            </div>
                            <span class="fw-bold" style="font-size:13px;color:#e53e3e;">Deductions</span>
                        </div>
                        <div id="deductionsTableBody" class="d-flex flex-column gap-2" style="min-height:60px;"></div>
                        <div class="d-flex justify-content-between align-items-center mt-3 pt-2" style="border-top:2px solid #e9ecef;">
                            <span class="fw-bold text-dark" style="font-size:13px;">Total Deductions</span>
                            <span class="fw-bold text-dark" style="font-size:13px;" id="modalTotalDeductions">₹0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Net Payout Footer -->
                <div class="d-flex align-items-center justify-content-between rounded-3 mt-4 px-4 py-3" style="background:#f0fdf6;border:1px solid #bbf7d0;">
                    <div class="d-flex align-items-center gap-2">
                        <i class="feather-check-circle" style="color:#16a34a;font-size:18px;"></i>
                        <span class="fw-semibold" style="font-size:13px;color:#166534;" id="modalNetLabel">Net Salary Payout</span>
                    </div>
                    <span class="fw-bold" style="font-size:18px;color:#16a34a;" id="modalNetPayout">₹0.00</span>
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer bg-white border-top px-4 py-3 d-flex align-items-center justify-content-between" style="border-color:#f0f2f5 !important;">
                <div class="text-muted" style="font-size:11px;" id="modalReleasedBy"></div>
                <div class="d-flex gap-2">
                    <a href="#" id="modalDownloadPayslipBtn" class="btn btn-primary fw-semibold px-4 d-flex align-items-center gap-1" style="font-size:12px;border-radius:6px;"><i class="feather-download" style="font-size:13px;"></i>DOWNLOAD PAYSLIP</a>
                    <button type="button" class="btn btn-light border fw-semibold px-4" style="font-size:12px;border-radius:6px;" data-bs-dismiss="modal">CLOSE</button>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- RESOLVE PENDING ISSUES MODAL -->
<div class="modal fade" id="resolveIssuesModal" tabindex="-1" aria-labelledby="resolveIssuesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg text-dark">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold" id="resolveIssuesModalLabel">
                    <i class="feather-alert-triangle me-2"></i>Resolve Pending Requests
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            @if($selectedRun)
            <div class="modal-body p-4">
                <p class="fs-13 text-muted mb-4">
                    Review and resolve outstanding requests for the period of <strong>{{ Carbon\Carbon::parse($selectedRun->payroll_month . '-01')->format('F Y') }}</strong>. Select a card below to review and approve/reject requests individually.
                </p>

                <div class="row g-3 mb-2">
                    <!-- Leaves Card -->
                    <div class="col-md-4 col-12">
                        @if(($pendingIssues['leaves'] ?? 0) > 0)
                            <a href="{{ route('hrms.leaves.index', ['leaves_status' => 'pending', 'payroll_month' => $selectedRun->payroll_month]) }}" class="card border border-warning-subtle rounded-3 p-3 text-decoration-none h-100 hover-shadow transition-all" style="background: #fffdf5; min-height: 120px;">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 48px; height: 48px; background-color: rgba(255, 193, 7, 0.15);">
                                        <i class="feather-minus-circle fs-20" style="color: #d97706;"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0.5" style="font-size: 14px;">Leave Requests</h6>
                                        <div class="fs-11 text-muted mb-1.5">Resolve pending leaves</div>
                                        <span class="badge bg-warning text-white rounded-pill px-2.5 py-1" style="font-size: 10px;">{{ $pendingIssues['leaves'] }} pending</span>
                                    </div>
                                </div>
                            </a>
                        @else
                            <div class="card border border-light bg-light rounded-3 p-3 h-100" style="opacity: 0.65; cursor: not-allowed; background-color: #f8fafc; min-height: 120px;">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 48px; height: 48px; background-color: #e2e8f0;">
                                        <i class="feather-minus-circle fs-20" style="color: #94a3b8;"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-muted mb-0.5" style="font-size: 14px;">Leave Requests</h6>
                                        <div class="fs-11 text-muted mb-1.5">No pending leaves</div>
                                        <span class="badge bg-secondary text-white rounded-pill px-2.5 py-1" style="font-size: 10px;">0 pending</span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Attendance Corrections Card -->
                    <div class="col-md-4 col-12">
                        @if(($pendingIssues['corrections'] ?? 0) > 0)
                            <a href="{{ route('hrms.attendance.index', ['view' => 'corrections', 'corrections_status' => 'pending', 'payroll_month' => $selectedRun->payroll_month]) }}" class="card border border-primary-subtle rounded-3 p-3 text-decoration-none h-100 hover-shadow transition-all" style="background: #f5f9ff; min-height: 120px;">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 48px; height: 48px; background-color: rgba(13, 110, 253, 0.15);">
                                        <i class="feather-clock fs-20" style="color: #0d6efd;"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0.5" style="font-size: 14px;">Attendance Corrections</h6>
                                        <div class="fs-11 text-muted mb-1.5">Resolve check-ins</div>
                                        <span class="badge bg-primary text-white rounded-pill px-2.5 py-1" style="font-size: 10px;">{{ $pendingIssues['corrections'] }} pending</span>
                                    </div>
                                </div>
                            </a>
                        @else
                            <div class="card border border-light bg-light rounded-3 p-3 h-100" style="opacity: 0.65; cursor: not-allowed; background-color: #f8fafc; min-height: 120px;">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 48px; height: 48px; background-color: #e2e8f0;">
                                        <i class="feather-clock fs-20" style="color: #94a3b8;"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-muted mb-0.5" style="font-size: 14px;">Attendance Corrections</h6>
                                        <div class="fs-11 text-muted mb-1.5">No pending corrections</div>
                                        <span class="badge bg-secondary text-white rounded-pill px-2.5 py-1" style="font-size: 10px;">0 pending</span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Overtime Requests Card -->
                    <div class="col-md-4 col-12">
                        @if(($pendingIssues['overtime'] ?? 0) > 0)
                            <a href="{{ route('hrms.shift-overtime.index', ['tab' => 'overtime', 'overtime_status' => 'pending', 'payroll_month' => $selectedRun->payroll_month]) }}" class="card border border-success-subtle rounded-3 p-3 text-decoration-none h-100 hover-shadow transition-all" style="background: #f5fdf7; min-height: 120px;">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 48px; height: 48px; background-color: rgba(25, 135, 84, 0.15);">
                                        <i class="feather-watch fs-20" style="color: #198754;"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0.5" style="font-size: 14px;">Overtime Requests</h6>
                                        <div class="fs-11 text-muted mb-1.5">Resolve overtime claims</div>
                                        <span class="badge bg-success text-white rounded-pill px-2.5 py-1" style="font-size: 10px;">{{ $pendingIssues['overtime'] }} pending</span>
                                    </div>
                                </div>
                            </a>
                        @else
                            <div class="card border border-light bg-light rounded-3 p-3 h-100" style="opacity: 0.65; cursor: not-allowed; background-color: #f8fafc; min-height: 120px;">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width: 48px; height: 48px; background-color: #e2e8f0;">
                                        <i class="feather-watch fs-20" style="color: #94a3b8;"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-muted mb-0.5" style="font-size: 14px;">Overtime Requests</h6>
                                        <div class="fs-11 text-muted mb-1.5">No pending claims</div>
                                        <span class="badge bg-secondary text-white rounded-pill px-2.5 py-1" style="font-size: 10px;">0 pending</span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Bulk Actions Tab Card -->
                    <div class="col-12 mt-3">
                        <div class="card border rounded-3 p-4" style="background-color: #f8fafc;">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <div>
                                    <h6 class="fw-bold text-dark mb-1" style="font-size: 14px;"><i class="feather-zap me-1.5 text-warning"></i>Bulk Auto-Resolve</h6>
                                    <p class="fs-12 text-muted mb-0">Apply standard resolution actions (Approve All or Reject All) across all remaining pending checklist items.</p>
                                </div>
                                <form action="{{ route('hrms.payroll.run.resolve-pending', $selectedRun->id) }}" method="POST" class="m-0">
                                    @csrf
                                    <div class="d-flex gap-2">
                                        <button type="submit" name="resolution_action" value="approve_all" class="btn btn-sm btn-primary fw-bold px-4 py-2" style="font-size: 12px;"><i class="feather-check-circle me-1.5"></i>Auto-Approve All</button>
                                        <button type="submit" name="resolution_action" value="reject_all" class="btn btn-sm btn-outline-danger fw-bold px-4 py-2" style="font-size: 12px;"><i class="feather-x-circle me-1.5"></i>Auto-Reject / LOP All</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
            
            <div class="modal-footer bg-light border-top">
                <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Ad-hoc Adjustments Modal -->
@if($selectedRun)
<div class="modal fade" id="bulkAdhocModal" tabindex="-1" aria-labelledby="bulkAdhocModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="bulkAdhocModalLabel"><i class="feather-plus-circle me-2 text-primary"></i>Bulk Ad-hoc</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.payroll.run.bulk-adhoc') }}" method="POST">
                @csrf
                <input type="hidden" name="payroll_month" value="{{ $selectedRun->payroll_month }}">
                
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="Ad-hoc Component" name="salary_component_id" :required="true">
                                <option value="">Select component...</option>
                                @foreach($salaryComponents as $component)
                                    <option value="{{ $component->id }}">{{ $component->name }} ({{ strtoupper($component->type) }})</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="col-12">
                            <x-ui.odoo-form-ui type="select" label="Target Pay Group" name="pay_group_id" helperText="Filters the checklist below automatically.">
                                <option value="">All Pay Groups</option>
                                @foreach($payGroups as $pg)
                                    <option value="{{ $pg->id }}" {{ $selectedRun->pay_group_id == $pg->id ? 'selected' : '' }}>{{ $pg->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold fs-12 mb-1" style="color: #dc3545 !important;">Recipient Employees <span class="text-danger">*</span></label>
                            <input type="text" id="employeeSearchInput" class="form-control mb-2" placeholder="Search employee name or ID..." style="font-size: 12px; height: 34px;">
                            
                            <div class="border rounded-3 p-3 bg-white" style="max-height: 180px; overflow-y: auto; border-color: #dee2e6;">
                                <div class="form-check mb-2 pb-2 border-bottom">
                                    <input class="form-check-input" type="checkbox" id="selectAllEmployees" checked>
                                    <label class="form-check-label fw-bold text-dark fs-12" for="selectAllEmployees">
                                        Select All (Filtered)
                                    </label>
                                </div>
                                <div id="employeeChecklistContainer">
                                    @foreach($allEmployees as $emp)
                                        <div class="form-check mb-1.5 employee-check-item" data-paygroup-id="{{ $emp->pay_group_id }}" data-name="{{ strtolower($emp->full_name) }}" data-id="{{ strtolower($emp->employee_id) }}">
                                            <input class="form-check-input employee-checkbox" type="checkbox" name="employee_ids[]" value="{{ $emp->id }}" id="empCheck_{{ $emp->id }}" checked>
                                            <label class="form-check-label fs-12 text-dark" for="empCheck_{{ $emp->id }}">
                                                <span class="fw-semibold text-dark">{{ $emp->full_name }}</span> 
                                                <span class="text-muted fs-11">({{ $emp->employee_id }})</span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <x-ui.odoo-form-ui type="input" inputType="number" label="Amount (₹)" name="amount" :required="true" placeholder="0.00" step="0.01" />
                        </div>

                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="Remarks / Description" name="remarks" :required="true" placeholder="e.g. Festival Bonus 2026 or Special Allowance Adjustment" rows="3" />
                        </div>

                        <div class="col-12">
                            <div class="alert alert-info rounded-3 mb-0" style="font-size: 11px;">
                                <i class="feather-info me-1.5 text-primary"></i>This adjustment will be applied to the selected checked employees for the month of <strong>{{ Carbon\Carbon::parse($selectedRun->payroll_month . '-01')->format('F Y') }}</strong>.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary text-white">Apply Adjustments</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        let payrollSearchTimeout = null;
        let activePayrollRequest = null;

        function syncPayrollForms(params) {
            const fields = ['search', 'status', 'department_id', 'sort'];
            fields.forEach(name => {
                document.querySelectorAll(`#payrollRegisterFiltersBar [name="${name}"]`).forEach(field => {
                    // Skip the active search input so we don't disrupt active typing focus
                    if (field === document.activeElement) return;

                    field.value = params.get(name) || '';
                    if (field.tagName === 'SELECT' && $(field).hasClass('select2-hidden-accessible')) {
                        $(field).trigger('change.select2');
                    }
                });
            });

            // Sync the hidden inputs on the search form
            const statusInput = document.getElementById('payroll_status_input');
            if (statusInput) statusInput.value = params.get('status') || '';

            const deptInput = document.getElementById('payroll_dept_input');
            if (deptInput) deptInput.value = params.get('department_id') || '';

            const sortInput = document.getElementById('payroll_sort_input');
            if (sortInput) sortInput.value = params.get('sort') || 'name_asc';
        }

        function syncPayrollSortLinks(params) {
            const currentSort = params.get('sort') || 'name_asc';
            const links = document.querySelectorAll('.payroll-sort-link');
            links.forEach(link => {
                const sortVal = link.getAttribute('data-sort');
                link.classList.remove('active');
                const checkIcon = link.querySelector('.feather-check');
                if (checkIcon) {
                    checkIcon.remove();
                }

                if (sortVal === currentSort) {
                    link.classList.add('active');
                    const icon = document.createElement('i');
                    icon.className = 'feather-check ms-3';
                    link.appendChild(icon);
                }
            });
        }

        function refreshPayrollRegisterList(url) {
            const targetUrl = url instanceof URL ? url : new URL(url, window.location.origin);

            if (activePayrollRequest) {
                activePayrollRequest.abort();
            }

            const controller = new AbortController();
            activePayrollRequest = controller;

            const tableWrapper = document.getElementById('payrollRegisterTableWrapper');
            if (tableWrapper) {
                tableWrapper.style.opacity = '0.5';
            }

            fetch(targetUrl.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: controller.signal,
            })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Unable to refresh payroll register.');
                }
                return response.text();
            })
            .then(function (html) {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                
                const newTbody = doc.getElementById('payrollRegisterTableBody');
                const oldTbody = document.getElementById('payrollRegisterTableBody');
                if (newTbody && oldTbody) {
                    oldTbody.innerHTML = newTbody.innerHTML;
                }

                // Sync forms and active sorting icons without disrupting active typing focus
                syncPayrollForms(targetUrl.searchParams);
                syncPayrollSortLinks(targetUrl.searchParams);

                // Push state to update browser URL
                history.pushState(null, '', targetUrl.toString());
            })
            .catch(function (error) {
                if (error.name !== 'AbortError') {
                    window.location.href = targetUrl.toString();
                }
            })
            .finally(function () {
                if (activePayrollRequest === controller) {
                    if (tableWrapper) {
                        tableWrapper.style.opacity = '1';
                    }
                    activePayrollRequest = null;
                }
            });
        }

        // Debounced search input handler
        $(document).on('input', '#payrollSearchInput', function () {
            const form = this.closest('form');
            if (!form) return;
            const url = new URL(form.action || window.location.href);
            
            const formData = new FormData(form);
            for (const [key, val] of formData.entries()) {
                url.searchParams.set(key, val);
            }

            if (payrollSearchTimeout) clearTimeout(payrollSearchTimeout);
            payrollSearchTimeout = setTimeout(function() {
                refreshPayrollRegisterList(url);
            }, 400);
        });

        // Sort click handler
        $(document).on('click', '.payroll-sort-link', function (e) {
            e.preventDefault();
            const sortVal = this.getAttribute('data-sort');
            const form = document.getElementById('payrollSearchForm');
            if (!form) return;
            
            const url = new URL(form.action || window.location.href);
            const formData = new FormData(form);
            for (const [key, val] of formData.entries()) {
                url.searchParams.set(key, val);
            }
            url.searchParams.set('sort', sortVal);

            refreshPayrollRegisterList(url);
        });

        // Filter Form submit handler
        $(document).on('submit', '#payrollFilterForm', function (e) {
            e.preventDefault();
            const form = this;
            const url = new URL(form.action || window.location.href);
            
            const formData = new FormData(form);
            for (const [key, val] of formData.entries()) {
                url.searchParams.set(key, val);
            }

            refreshPayrollRegisterList(url);
            
            // Close Bootstrap dropdown menu manually by triggering click on the filter toggle button
            const dropdownToggle = this.closest('.dropdown').querySelector('[data-bs-toggle="dropdown"]');
            if (dropdownToggle) {
                dropdownToggle.click();
            }
        });

        // Reset Button and Clear All links click handlers
        $(document).on('click', '.payroll-reset-btn, .payroll-clear-all', function (e) {
            e.preventDefault();
            const url = new URL(this.getAttribute('href') || window.location.href);
            refreshPayrollRegisterList(url);
            
            // Close Bootstrap dropdown menu if clicked reset inside dropdown
            const dropdown = this.closest('.dropdown');
            if (dropdown) {
                const dropdownToggle = dropdown.querySelector('[data-bs-toggle="dropdown"]');
                if (dropdownToggle) {
                    dropdownToggle.click();
                }
            }
        });

        // Initialize Select2 on dropdown display or page load
        if (typeof initOdooComponents === 'function') {
            initOdooComponents();
        }
        $(document).on('show.bs.dropdown', '.dropdown', function() {
            if (typeof initOdooComponents === 'function') {
                initOdooComponents();
            }
        });

        const modal = document.getElementById('initiateRunModal');
        if (modal) {
            document.body.appendChild(modal);
        }

        const salaryModal = document.getElementById('salaryDetailsModal');
        if (salaryModal) {
            document.body.appendChild(salaryModal);
        }

        const resolveModal = document.getElementById('resolveIssuesModal');
        if (resolveModal) {
            document.body.appendChild(resolveModal);
        }

        const bulkAdhocModal = document.getElementById('bulkAdhocModal');
        if (bulkAdhocModal) {
            document.body.appendChild(bulkAdhocModal);
            
            const payGroupSelect = bulkAdhocModal.querySelector('select[name="pay_group_id"]');
            const searchInput = document.getElementById('employeeSearchInput');
            const selectAllCheck = document.getElementById('selectAllEmployees');
            const checkItems = bulkAdhocModal.querySelectorAll('.employee-check-item');
            
            const filterEmployees = function() {
                const selectedGroupId = payGroupSelect ? $(payGroupSelect).val() : '';
                const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
                
                checkItems.forEach(item => {
                    const pgId = item.getAttribute('data-paygroup-id');
                    const name = item.getAttribute('data-name');
                    const id = item.getAttribute('data-id');
                    
                    const matchesGroup = !selectedGroupId || pgId === selectedGroupId;
                    const matchesSearch = !query || name.includes(query) || id.includes(query);
                    
                    if (matchesGroup && matchesSearch) {
                        item.style.setProperty('display', 'block', 'important');
                    } else {
                        item.style.setProperty('display', 'none', 'important');
                        // Auto uncheck hidden ones if pay group changed and it does not match
                        if (!matchesGroup) {
                            const checkbox = item.querySelector('.employee-checkbox');
                            if (checkbox) checkbox.checked = false;
                        }
                    }
                });
                
                updateSelectAllState();
            };

            const updateSelectAllState = function() {
                const visibleCheckboxes = Array.from(checkItems)
                    .filter(item => item.style.display !== 'none')
                    .map(item => item.querySelector('.employee-checkbox'))
                    .filter(checkbox => checkbox);
                
                if (visibleCheckboxes.length === 0) {
                    if (selectAllCheck) selectAllCheck.checked = false;
                    return;
                }
                
                const allChecked = visibleCheckboxes.every(checkbox => checkbox.checked);
                if (selectAllCheck) selectAllCheck.checked = allChecked;
            };

            if (payGroupSelect) {
                $(payGroupSelect).on('change', function() {
                    const selectedGroupId = this.value;
                    checkItems.forEach(item => {
                        const pgId = item.getAttribute('data-paygroup-id');
                        const checkbox = item.querySelector('.employee-checkbox');
                        if (checkbox) {
                            if (!selectedGroupId || pgId === selectedGroupId) {
                                checkbox.checked = true;
                            } else {
                                checkbox.checked = false;
                            }
                        }
                    });
                    filterEmployees();
                });
            }

            if (searchInput) {
                searchInput.addEventListener('input', filterEmployees);
            }

            if (selectAllCheck) {
                selectAllCheck.addEventListener('change', function() {
                    const isChecked = selectAllCheck.checked;
                    checkItems.forEach(item => {
                        if (item.style.display !== 'none') {
                            const checkbox = item.querySelector('.employee-checkbox');
                            if (checkbox) checkbox.checked = isChecked;
                        }
                    });
                });
            }

            // Individual checkbox click updates "Select All"
            bulkAdhocModal.addEventListener('change', function(e) {
                if (e.target.classList.contains('employee-checkbox')) {
                    updateSelectAllState();
                }
            });

            // Initial trigger to align checkboxes with default pay group filter if selected
            if (payGroupSelect && $(payGroupSelect).val()) {
                const selectedGroupId = $(payGroupSelect).val();
                checkItems.forEach(item => {
                    const pgId = item.getAttribute('data-paygroup-id');
                    const checkbox = item.querySelector('.employee-checkbox');
                    if (checkbox) {
                        checkbox.checked = (pgId === selectedGroupId);
                    }
                });
                filterEmployees();
            }
        }

        const payrollMonthInput = document.getElementById('initiate_payroll_month');
        if (payrollMonthInput) {
            const updateDates = function() {
                const value = payrollMonthInput.value.trim();
                if (/^\d{4}-\d{2}$/.test(value)) {
                    const parts = value.split('-');
                    const year = parseInt(parts[0], 10);
                    const month = parseInt(parts[1], 10);

                    const startDateStr = `${year}-${String(month).padStart(2, '0')}-01`;
                    const lastDay = new Date(year, month, 0).getDate();
                    const endDateStr = `${year}-${String(month).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;

                    const startDateEl = document.getElementById('initiate_start_date');
                    const endDateEl = document.getElementById('initiate_end_date');
                    
                    if (startDateEl) startDateEl.value = startDateStr;
                    if (endDateEl) endDateEl.value = endDateStr;
                }
            };
            payrollMonthInput.addEventListener('input', updateDates);
            payrollMonthInput.addEventListener('change', updateDates);
        }

        // Handle salary breakdown click
        $(document).on('click', '.show-salary-details', function() {
            const btn      = this;
            const name     = btn.getAttribute('data-employee-name') || '';
            const empId    = btn.getAttribute('data-employee-id') || '';
            const empDbId  = btn.getAttribute('data-employee-db-id') || '';
            const runId    = btn.getAttribute('data-run-id') || '';
            const jobTitle = btn.getAttribute('data-job-title') || '';
            const bank     = btn.getAttribute('data-bank') || '—';
            const bankAcc  = btn.getAttribute('data-bank-account') || '—';
            const proration = btn.getAttribute('data-proration-days') || '—';
            const month    = btn.getAttribute('data-month') || '';
            const runStatus = btn.getAttribute('data-run-status') || '';
            const releasedBy = btn.getAttribute('data-released-by') || '';
            const releasedAt = btn.getAttribute('data-released-at') || '';
            const items    = JSON.parse(btn.getAttribute('data-items') || '{}');
            const summary  = JSON.parse(btn.getAttribute('data-summary') || '{}');

            const downloadBtn = document.getElementById('modalDownloadPayslipBtn');
            if (downloadBtn) {
                downloadBtn.setAttribute('href', `/hrms/payroll/payslip/${runId}/${empDbId}/download`);
            }

            // Populate header info
            document.getElementById('modalPayrollMonth').textContent  = month;
            document.getElementById('modalEmployeeName').textContent   = name;
            document.getElementById('modalEmployeeIdRole').textContent = empId + (jobTitle ? ' • ' + jobTitle : '');
            document.getElementById('modalBankAccount').textContent    = bank !== '—' ? bank + ' (' + bankAcc + ')' : '—';
            document.getElementById('modalProrationDays').textContent  = proration;

            // Released / status label
            const netLabel = document.getElementById('modalNetLabel');
            const paidDaysVal = (summary.total_days || 30) - (summary.lop_days || 0);
            if (runStatus === 'paid') {
                netLabel.innerHTML = `<span style="color:#16a34a;">Net Salary Payout (Paid for ${paidDaysVal} Days)</span> <span class="badge ms-2" style="background:#dcfce7;color:#15803d;font-size:10px;border-radius:20px;padding:2px 8px;">Released</span>`;
            } else if (runStatus === 'locked') {
                netLabel.innerHTML = `<span style="color:#b45309;">Net Salary Payout (Paid for ${paidDaysVal} Days)</span> <span class="badge ms-2" style="background:#fef9c3;color:#854d0e;font-size:10px;border-radius:20px;padding:2px 8px;">Locked</span>`;
            } else {
                netLabel.innerHTML = `<span style="color:#166534;">Net Salary Payout (Paid for ${paidDaysVal} Days)</span> <span class="badge ms-2" style="background:#dbeafe;color:#1e40af;font-size:10px;border-radius:20px;padding:2px 8px;">Draft</span>`;
            }

            const fmt = v => '₹' + parseFloat(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            let earningsHtml = '';
            let deductionsHtml = '';
            let grossEarnings = 0;
            let totalDeductions = 0;

            // Sort: earning items first, then deduction items
            const codes = Object.keys(items);

            codes.forEach(code => {
                const item      = items[code];
                const calcVal   = parseFloat(item.calculated_value || 0);
                const baseVal   = parseFloat(item.base_monthly || 0);
                const lopDed    = parseFloat(item.deduction || 0);

                if (item.type === 'earning') {
                    grossEarnings += calcVal;
                    const isAdhoc    = code.startsWith('BONUS') || parseFloat(item.deduction||0) === 0 && baseVal === calcVal && baseVal > 0 && lopDed === 0;
                    const isSpecial  = lopDed > 0;
                    earningsHtml += `<div class="d-flex justify-content-between align-items-center" style="font-size:13px;">
                        <span class="text-secondary">${item.name}</span>
                        <span class="fw-semibold ${code.startsWith('ADHOC') || code.startsWith('BONUS') ? 'text-success' : 'text-dark'}">${code.startsWith('ADHOC') || code.startsWith('BONUS') ? '+' : ''}${fmt(calcVal)}</span>
                    </div>`;
                } else {
                    totalDeductions += calcVal;
                    deductionsHtml += `<div class="d-flex justify-content-between align-items-center" style="font-size:13px;">
                        <span class="text-secondary">${item.name}</span>
                        <span class="fw-semibold text-dark">${fmt(calcVal)}</span>
                    </div>`;
                }
            });

            if (summary.lop_deduction && parseFloat(summary.lop_deduction) > 0) {
                const lopRowHtml = `<div class="d-flex justify-content-between align-items-center mb-2" style="font-size:13px;">
                    <span class="text-secondary">Loss of Pay (${summary.lop_days} days)</span>
                    <span class="badge bg-soft-secondary text-secondary fs-10 fw-semibold px-2 py-0.5" title="Amount is already spliced/deducted directly from proration-based earnings.">Spliced (-${fmt(summary.lop_deduction)})</span>
                </div>`;
                if (!deductionsHtml || deductionsHtml.includes('No deductions defined')) {
                    deductionsHtml = lopRowHtml;
                } else {
                    deductionsHtml += lopRowHtml;
                }
            }

            if (!earningsHtml) {
                earningsHtml = '<div class="text-center text-muted py-3" style="font-size:12px;">No earnings defined.</div>';
            }
            if (!deductionsHtml) {
                deductionsHtml = '<div class="text-center text-muted py-3" style="font-size:12px;">No deductions defined.</div>';
            }

            document.getElementById('earningsTableBody').innerHTML   = earningsHtml;
            document.getElementById('deductionsTableBody').innerHTML = deductionsHtml;
            // Use PHP pre-computed summary totals — JS accumulation can miss items due to encoding
            document.getElementById('modalGrossEarnings').textContent  = fmt(summary.total_earnings || 0);
            document.getElementById('modalTotalDeductions').textContent = fmt(summary.total_deductions || 0);
            document.getElementById('modalNetPayout').textContent = fmt(summary.net_payout || 0);

            // Footer release info
            const releasedByEl = document.getElementById('modalReleasedBy');
            if (runStatus === 'paid' && releasedBy) {
                releasedByEl.textContent = 'Released by ' + releasedBy + (releasedAt ? ' on ' + releasedAt : '');
            } else {
                releasedByEl.textContent = '';
            }

            $('#salaryDetailsModal').modal('show');
        });
    });
</script>
@endpush
