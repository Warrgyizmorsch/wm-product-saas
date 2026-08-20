@extends('layouts.duralux')

@section('title', 'Travel & Expense Management | SaaS ERP')
@section('page-title', 'Travel & Expenses')
@section('breadcrumb', 'HRMS / Operations / Travel & Expenses')

@push('styles')
    <style>
        /* Underlined Horizontal Tabs (matching Shift Roster & Leave module) */
        #expenseTabs .nav-link {
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
        #expenseTabs .nav-link:hover {
            color: var(--bs-primary);
        }
        #expenseTabs .nav-link.active {
            color: var(--bs-primary) !important;
            border-bottom: 2px solid var(--bs-primary) !important;
            font-weight: 600;
        }


        /* Clean Odoo style table layouts */
        #claimsLinesTable {
            width: 100% !important;
            border-collapse: collapse !important;
            margin-top: 15px !important;
            font-size: 13px !important;
            border: none !important;
        }
        #claimsLinesTable thead {
            background-color: #f8fafc !important;
        }
        #claimsLinesTable th {
            background-color: #f8fafc !important;
            border: none !important;
            border-bottom: 2px solid #e2e8f0 !important;
            padding: 10px 12px !important;
            color: #475569 !important;
            font-weight: 700 !important;
            font-size: 11px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
        }
        #claimsLinesTable td {
            padding: 8px 6px !important;
            border: none !important;
            border-bottom: 1px solid #e9ecef !important;
            vertical-align: top !important;
            background-color: transparent !important;
        }
    </style>
@endpush

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button type="button" variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addTravelModal" id="btnRequestTravel" class="fw-bold text-uppercase">
            Request Travel
        </x-ui.button>
        <x-ui.button type="button" variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addAdvanceModal" id="btnRequestAdvance" class="fw-bold text-uppercase d-none">
            Request Advance
        </x-ui.button>
        <x-ui.button type="button" variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addReportModal" id="btnFileExpenseClaim" class="fw-bold text-uppercase d-none">
            File Expense Claim
        </x-ui.button>
    </div>
@endsection

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4 text-dark" role="alert">
            <i class="feather-check-circle me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Tabs Workspace --}}
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <ul class="nav gap-2 border-bottom pb-2" id="expenseTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="travel-tab" data-bs-toggle="tab" data-bs-target="#travel-pane" type="button" role="tab" aria-controls="travel-pane" aria-selected="true">
                    <i class="feather-map me-1"></i> Travel Requests
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link text-muted" id="advance-tab" data-bs-toggle="tab" data-bs-target="#advance-pane" type="button" role="tab" aria-controls="advance-pane" aria-selected="false">
                    <i class="feather-dollar-sign me-1"></i> Cash Advances
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link text-muted" id="report-tab" data-bs-toggle="tab" data-bs-target="#report-pane" type="button" role="tab" aria-controls="report-pane" aria-selected="false">
                    <i class="feather-file-text me-1"></i> Expense Reports
                </button>
            </li>
        </ul>

        <div class="tab-content pt-3" id="expenseTabsContent">
            {{-- TRAVEL REQUESTS TAB PANE --}}
            <div class="tab-pane fade show active" id="travel-pane" role="tabpanel" aria-labelledby="travel-tab">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Travel Requests</h5>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <form method="GET" action="{{ route('hrms.travel-expense.index') }}" class="d-flex align-items-center gap-2 m-0 flex-wrap">
                            <input type="hidden" name="tab" value="travel">
                            <input type="hidden" name="travel_sort" id="travel_sort_input" value="{{ $travelSort ?? 'newest' }}">
                            
                            <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; height: 38px;">
                                <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                <input type="text" name="travel_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search requests..." value="{{ $travelSearch ?? '' }}" style="box-shadow: none; height: 32px;">
                            </div>

                            <x-ui.sort-dropdown label="Sort">
                                <a class="dropdown-item py-2 d-flex align-items-center {{ ($travelSort ?? 'newest') === 'newest' ? 'active' : '' }}" href="#" onclick="setSortParam('travel', 'newest'); event.preventDefault();">
                                    <span>Newest First</span>
                                </a>
                                <a class="dropdown-item py-2 d-flex align-items-center {{ ($travelSort ?? '') === 'oldest' ? 'active' : '' }}" href="#" onclick="setSortParam('travel', 'oldest'); event.preventDefault();">
                                    <span>Oldest First</span>
                                </a>
                            </x-ui.sort-dropdown>

                            <x-ui.filter label="Filter" offset="0, 5">
                                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                                    <x-ui.odoo-form-ui type="select" name="travel_status">
                                        <option value="" {{ ($travelStatus ?? '') === '' ? 'selected' : '' }}>All Statuses</option>
                                        <option value="pending" {{ ($travelStatus ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="approved" {{ ($travelStatus ?? '') === 'approved' ? 'selected' : '' }}>Approved</option>
                                        <option value="rejected" {{ ($travelStatus ?? '') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                                    </x-ui.odoo-form-ui>
                                </div>
                                <div class="dropdown-divider my-3"></div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Apply</button>
                                    <a href="{{ route('hrms.travel-expense.index', ['tab' => 'travel']) }}" class="btn btn-light btn-sm border flex-grow-1 text-center">Reset</a>
                                </div>
                            </x-ui.filter>
                        </form>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr>
                                <th>Employee</th>
                                <th>Purpose</th>
                                <th>Destination</th>
                                <th>Dates</th>
                                <th>Budget</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($travelRequests as $req)
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $req->employee->full_name }}</div>
                                        <div class="text-muted fs-11">Emp ID: {{ $req->employee->employee_id }}</div>
                                    </td>
                                    <td>{{ $req->purpose }}</td>
                                    <td>{{ $req->destination }}</td>
                                    <td>
                                        <div class="text-dark fw-medium">{{ $req->start_date->format('M d, Y') }} - {{ $req->end_date->format('M d, Y') }}</div>
                                        <div class="text-muted fs-11">{{ $req->start_date->diffInDays($req->end_date) + 1 }} Days</div>
                                    </td>
                                    <td class="fw-semibold">${{ number_format($req->estimated_budget, 2) }}</td>
                                    <td>
                                        @if($req->status === 'approved')
                                            <span class="badge bg-soft-success text-success px-2 py-1 fs-11 rounded-pill">Approved</span>
                                        @elseif($req->status === 'rejected')
                                            <span class="badge bg-soft-danger text-danger px-2 py-1 fs-11 rounded-pill">Rejected</span>
                                        @else
                                            <span class="badge bg-soft-warning text-warning px-2 py-1 fs-11 rounded-pill">Pending</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if($req->status === 'pending')
                                            <div class="d-flex justify-content-end gap-1">
                                                <form method="POST" action="{{ route('hrms.travel-expense.travel.approve', $req) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-soft-success py-1 fw-bold fs-11">Approve</button>
                                                </form>
                                                <form method="POST" action="{{ route('hrms.travel-expense.travel.reject', $req) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-soft-danger py-1 fw-bold fs-11">Reject</button>
                                                </form>
                                            </div>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No travel requests found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- CASH ADVANCES TAB PANE --}}
            <div class="tab-pane fade" id="advance-pane" role="tabpanel" aria-labelledby="advance-tab">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Cash Advances</h5>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <form method="GET" action="{{ route('hrms.travel-expense.index') }}" class="d-flex align-items-center gap-2 m-0 flex-wrap">
                            <input type="hidden" name="tab" value="advance">
                            <input type="hidden" name="advance_sort" id="advance_sort_input" value="{{ $advanceSort ?? 'newest' }}">
                            
                            <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; height: 38px;">
                                <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                <input type="text" name="advance_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search advances..." value="{{ $advanceSearch ?? '' }}" style="box-shadow: none; height: 32px;">
                            </div>

                            <x-ui.sort-dropdown label="Sort">
                                <a class="dropdown-item py-2 d-flex align-items-center {{ ($advanceSort ?? 'newest') === 'newest' ? 'active' : '' }}" href="#" onclick="setSortParam('advance', 'newest'); event.preventDefault();">
                                    <span>Newest First</span>
                                </a>
                                <a class="dropdown-item py-2 d-flex align-items-center {{ ($advanceSort ?? '') === 'oldest' ? 'active' : '' }}" href="#" onclick="setSortParam('advance', 'oldest'); event.preventDefault();">
                                    <span>Oldest First</span>
                                </a>
                            </x-ui.sort-dropdown>

                            <x-ui.filter label="Filter" offset="0, 5">
                                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                                    <x-ui.odoo-form-ui type="select" name="advance_status">
                                        <option value="" {{ ($advanceStatus ?? '') === '' ? 'selected' : '' }}>All Statuses</option>
                                        <option value="pending" {{ ($advanceStatus ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="approved" {{ ($advanceStatus ?? '') === 'approved' ? 'selected' : '' }}>Approved</option>
                                        <option value="disbursed" {{ ($advanceStatus ?? '') === 'disbursed' ? 'selected' : '' }}>Disbursed</option>
                                        <option value="settled" {{ ($advanceStatus ?? '') === 'settled' ? 'selected' : '' }}>Settled</option>
                                        <option value="rejected" {{ ($advanceStatus ?? '') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                                    </x-ui.odoo-form-ui>
                                </div>
                                <div class="dropdown-divider my-3"></div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Apply</button>
                                    <a href="{{ route('hrms.travel-expense.index', ['tab' => 'advance']) }}" class="btn btn-light btn-sm border flex-grow-1 text-center">Reset</a>
                                </div>
                            </x-ui.filter>
                        </form>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr>
                                <th>Employee</th>
                                <th>Amount</th>
                                <th>Purpose / Trip</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cashAdvances as $adv)
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $adv->employee->full_name }}</div>
                                    </td>
                                    <td class="fw-bold text-dark">${{ number_format($adv->amount, 2) }}</td>
                                    <td>
                                        <div class="text-dark">{{ $adv->purpose }}</div>
                                        @if($adv->travelRequest)
                                            <div class="text-muted fs-11"><i class="feather-map me-1"></i>Trip to {{ $adv->travelRequest->destination }} ({{ $adv->travelRequest->purpose }})</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($adv->status === 'disbursed')
                                            <span class="badge bg-soft-info text-info px-2 py-1 fs-11 rounded-pill">Disbursed</span>
                                        @elseif($adv->status === 'settled')
                                            <span class="badge bg-soft-success text-success px-2 py-1 fs-11 rounded-pill">Settled</span>
                                        @elseif($adv->status === 'rejected')
                                            <span class="badge bg-soft-danger text-danger px-2 py-1 fs-11 rounded-pill">Rejected</span>
                                        @else
                                            <span class="badge bg-soft-warning text-warning px-2 py-1 fs-11 rounded-pill">Pending</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if($adv->status === 'pending')
                                            <div class="d-flex justify-content-end gap-1">
                                                <form method="POST" action="{{ route('hrms.travel-expense.advance.approve', $adv) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-soft-success py-1 fw-bold fs-11">Approve & Disburse</button>
                                                </form>
                                                <form method="POST" action="{{ route('hrms.travel-expense.advance.reject', $adv) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-soft-danger py-1 fw-bold fs-11">Reject</button>
                                                </form>
                                            </div>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No cash advance requests found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- EXPENSE REPORTS TAB PANE --}}
            <div class="tab-pane fade" id="report-pane" role="tabpanel" aria-labelledby="report-tab">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Expense Claims</h5>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <form method="GET" action="{{ route('hrms.travel-expense.index') }}" class="d-flex align-items-center gap-2 m-0 flex-wrap">
                            <input type="hidden" name="tab" value="report">
                            <input type="hidden" name="report_sort" id="report_sort_input" value="{{ $reportSort ?? 'newest' }}">
                            
                            <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; height: 38px;">
                                <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                <input type="text" name="report_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search claims..." value="{{ $reportSearch ?? '' }}" style="box-shadow: none; height: 32px;">
                            </div>

                            <x-ui.sort-dropdown label="Sort">
                                <a class="dropdown-item py-2 d-flex align-items-center {{ ($reportSort ?? 'newest') === 'newest' ? 'active' : '' }}" href="#" onclick="setSortParam('report', 'newest'); event.preventDefault();">
                                    <span>Newest First</span>
                                </a>
                                <a class="dropdown-item py-2 d-flex align-items-center {{ ($reportSort ?? '') === 'oldest' ? 'active' : '' }}" href="#" onclick="setSortParam('report', 'oldest'); event.preventDefault();">
                                    <span>Oldest First</span>
                                </a>
                            </x-ui.sort-dropdown>

                            <x-ui.filter label="Filter" offset="0, 5">
                                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                                    <x-ui.odoo-form-ui type="select" name="report_status">
                                        <option value="" {{ ($reportStatus ?? '') === '' ? 'selected' : '' }}>All Statuses</option>
                                        <option value="draft" {{ ($reportStatus ?? '') === 'draft' ? 'selected' : '' }}>Draft</option>
                                        <option value="submitted" {{ ($reportStatus ?? '') === 'submitted' ? 'selected' : '' }}>Submitted</option>
                                        <option value="approved" {{ ($reportStatus ?? '') === 'approved' ? 'selected' : '' }}>Approved</option>
                                        <option value="paid" {{ ($reportStatus ?? '') === 'paid' ? 'selected' : '' }}>Paid</option>
                                        <option value="rejected" {{ ($reportStatus ?? '') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                                    </x-ui.odoo-form-ui>
                                </div>
                                <div class="dropdown-divider my-3"></div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Apply</button>
                                    <a href="{{ route('hrms.travel-expense.index', ['tab' => 'report']) }}" class="btn btn-light btn-sm border flex-grow-1 text-center">Reset</a>
                                </div>
                            </x-ui.filter>
                        </form>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr>
                                <th>Employee / Title</th>
                                <th>Claim Items</th>
                                <th>Totals</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($expenseReports as $rep)
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $rep->title }}</div>
                                        <div class="text-muted fs-11">Submitted by: {{ $rep->employee->full_name }}</div>
                                        @if($rep->travelRequest)
                                            <span class="badge bg-soft-secondary text-secondary mt-1 fs-10"><i class="feather-map me-1"></i>Trip ID: TR-{{ $rep->travelRequest->id }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column gap-1">
                                            @foreach($rep->claims as $claim)
                                                <div class="fs-12 text-muted">
                                                    <span class="fw-semibold text-primary">• {{ $claim->category->name }}</span>: ${{ number_format($claim->amount, 2) }}
                                                    @if($claim->receipt_path)
                                                        <a href="{{ asset('storage/' . $claim->receipt_path) }}" target="_blank" class="ms-1 text-info fs-10 fw-bold"><i class="feather-image"></i> View Receipt</a>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fs-12 text-muted">Total Expense: <span class="fw-bold text-dark">${{ number_format($rep->total_amount, 2) }}</span></div>
                                        <div class="fs-12 text-muted">Advance Adjusted: <span class="text-dark">${{ number_format($rep->advance_adjusted, 2) }}</span></div>
                                        <div class="fs-12 fw-bold text-primary mt-1">Reimbursement: ${{ number_format($rep->net_reimbursement, 2) }}</div>
                                    </td>
                                    <td>
                                        @if($rep->status === 'draft')
                                            <span class="badge bg-secondary text-white px-2 py-1 fs-11 rounded-pill">Draft</span>
                                        @elseif($rep->status === 'submitted')
                                            <span class="badge bg-soft-warning text-warning px-2 py-1 fs-11 rounded-pill">Submitted</span>
                                        @elseif($rep->status === 'approved')
                                            <span class="badge bg-soft-success text-success px-2 py-1 fs-11 rounded-pill">Approved</span>
                                        @elseif($rep->status === 'rejected')
                                            <span class="badge bg-soft-danger text-danger px-2 py-1 fs-11 rounded-pill">Rejected</span>
                                        @elseif($rep->status === 'paid')
                                            <span class="badge bg-soft-primary text-primary px-2 py-1 fs-11 rounded-pill">Paid</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            @if($rep->status === 'draft')
                                                <form method="POST" action="{{ route('hrms.travel-expense.report.submit', $rep) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-primary py-1 fw-bold fs-11">Submit</button>
                                                </form>
                                            @endif

                                            @if($rep->status === 'submitted')
                                                <form method="POST" action="{{ route('hrms.travel-expense.report.approve', $rep) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-soft-success py-1 fw-bold fs-11">Approve</button>
                                                </form>
                                                <form method="POST" action="{{ route('hrms.travel-expense.report.reject', $rep) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-soft-danger py-1 fw-bold fs-11">Reject</button>
                                                </form>
                                            @endif

                                            @if($rep->status === 'approved')
                                                <form method="POST" action="{{ route('hrms.travel-expense.report.pay', $rep) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-success py-1 fw-bold fs-11"><i class="feather-credit-card"></i> Pay Out</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No expense reports filed.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL: REQUEST TRAVEL --}}
    <div class="modal fade" id="addTravelModal" tabindex="-1" aria-labelledby="addTravelModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content text-dark">
                <form method="POST" action="{{ route('hrms.travel-expense.travel.store') }}">
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $employee ? $employee->id : '' }}">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="addTravelModalLabel">Submit Travel Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body d-flex flex-column gap-3">
                        @if(!$employee)
                            <div class="alert alert-danger fs-12 mb-0"><i class="feather-alert-triangle me-1"></i>Your current user session is not linked to an Employee profile. Please select an employee to request for:</div>
                            <x-ui.odoo-form-ui type="select" label="Employee Scope" name="employee_id" select2-selector="default" :required="true">
                                <option value="" disabled selected>-- Select Employee --</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->full_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        @else
                            <div class="fs-13 text-muted mb-1">Requesting for employee: <strong class="text-dark">{{ $employee->full_name }}</strong></div>
                        @endif
                        <x-ui.odoo-form-ui type="input" label="Purpose / Reason" name="purpose" placeholder="e.g. Sales pitch to prospective client" :required="true" />
                        <x-ui.odoo-form-ui type="input" label="Destination Location" name="destination" placeholder="e.g. Chicago office" :required="true" />
                        <div class="row g-2 align-items-end">
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" inputType="date" label="Start Date" name="start_date" :required="true" />
                            </div>
                            <div class="col-6">
                                <x-ui.odoo-form-ui type="input" inputType="date" label="End Date" name="end_date" :required="true" />
                            </div>
                        </div>
                        <x-ui.odoo-form-ui type="input" inputType="number" label="Estimated Budget ($)" name="estimated_budget" placeholder="0.00" step="0.01" min="0" :required="true" />
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border text-uppercase fw-bold fs-12" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary text-uppercase fw-bold fs-12">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL: REQUEST ADVANCE --}}
    <div class="modal fade" id="addAdvanceModal" tabindex="-1" aria-labelledby="addAdvanceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content text-dark">
                <form method="POST" action="{{ route('hrms.travel-expense.advance.store') }}">
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $employee ? $employee->id : '' }}">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="addAdvanceModalLabel">Submit Cash Advance Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body d-flex flex-column gap-3">
                        @if(!$employee)
                            <div class="alert alert-danger fs-12 mb-0"><i class="feather-alert-triangle me-1"></i>Your current user session is not linked to an Employee profile. Please select an employee to request for:</div>
                            <x-ui.odoo-form-ui type="select" label="Employee Scope" name="employee_id" select2-selector="default" :required="true">
                                <option value="" disabled selected>-- Select Employee --</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->full_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        @else
                            <div class="fs-13 text-muted mb-1">Requesting for employee: <strong class="text-dark">{{ $employee->full_name }}</strong></div>
                        @endif

                        <x-ui.odoo-form-ui type="select" label="Associate Travel Request (Optional)" name="travel_request_id" select2-selector="default">
                            <option value="">Standalone / Business Expense</option>
                            @foreach($myApprovedTravelRequests as $tr)
                                <option value="{{ $tr->id }}">{{ $tr->purpose }} (to {{ $tr->destination }}) - Budget: ${{ number_format($tr->estimated_budget, 2) }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="input" inputType="number" label="Amount Requested ($)" name="amount" placeholder="0.00" step="0.01" min="1" :required="true" />
                        <x-ui.odoo-form-ui type="input" label="Purpose / Justification" name="purpose" placeholder="e.g. Client entertainment & dinner costs" :required="true" />
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border text-uppercase fw-bold fs-12" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary text-uppercase fw-bold fs-12">Request Advance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- MODAL: FILE EXPENSE REPORT --}}
    <div class="modal fade" id="addReportModal" tabindex="-1" aria-labelledby="addReportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content text-dark">
                <form method="POST" action="{{ route('hrms.travel-expense.report.store') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $employee ? $employee->id : '' }}">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="addReportModalLabel">File Expense Claim Report</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body d-flex flex-column gap-3">
                        @if(!$employee)
                            <div class="alert alert-danger fs-12 mb-0"><i class="feather-alert-triangle me-1"></i>Your current user session is not linked to an Employee profile. Please select an employee to request for:</div>
                            <x-ui.odoo-form-ui type="select" label="Employee Scope" name="employee_id" select2-selector="default" :required="true">
                                <option value="" disabled selected>-- Select Employee --</option>
                                @foreach($employees as $emp)
                                    <option value="{{ $emp->id }}">{{ $emp->full_name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        @else
                            <div class="fs-13 text-muted mb-1">Filing for employee: <strong class="text-dark">{{ $employee->full_name }}</strong></div>
                        @endif

                        <x-ui.odoo-form-ui type="input" label="Report Title / Description" name="title" placeholder="e.g. Sales Event Claims Aug 2026" :required="true" />
                        
                        <x-ui.odoo-form-ui type="select" label="Associate Travel Trip (Optional)" name="travel_request_id" select2-selector="default">
                            <option value="">None</option>
                            @foreach($myApprovedTravelRequests as $tr)
                                <option value="{{ $tr->id }}">{{ $tr->destination }} ({{ $tr->purpose }})</option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="select" label="Adjust Cash Advance" name="cash_advance_id" select2-selector="default">
                            <option value="">None</option>
                            @foreach($myOpenCashAdvances as $ca)
                                <option value="{{ $ca->id }}">${{ number_format($ca->amount, 2) }} - {{ $ca->purpose }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        {{-- Claims items array dynamically added --}}
                        <div class="border-top pt-3 mt-2">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h6 class="fw-bold mb-0" style="font-size: 13px;">Expense Claim Items *</h6>
                                <button type="button" class="btn btn-sm btn-soft-primary px-2.5 py-1 text-uppercase fw-bold fs-11" id="btnAddClaimLine">
                                    <i class="feather-plus"></i> Add Line
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="odoo-table" id="claimsLinesTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 22%;">Category</th>
                                            <th style="width: 15%;">Date</th>
                                            <th style="width: 15%;">Amount</th>
                                            <th style="width: 25%;">Merchant / Details</th>
                                            <th style="width: 18%;">Receipt Attachment</th>
                                            <th style="width: 5%;" class="text-center"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="claim-line-row">
                                            <td>
                                                <select name="claims[0][category_id]" class="odoo-table-select" required>
                                                    <option value="" disabled selected>-- Category --</option>
                                                    @foreach($categories as $cat)
                                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <input type="date" name="claims[0][date]" class="odoo-table-input" required value="{{ date('Y-m-d') }}">
                                            </td>
                                            <td>
                                                <input type="number" name="claims[0][amount]" step="0.01" min="0.01" class="odoo-table-input" placeholder="0.00" required>
                                            </td>
                                            <td>
                                                <input type="text" name="claims[0][merchant]" class="odoo-table-input mb-1" placeholder="Merchant name">
                                                <input type="text" name="claims[0][desc]" class="odoo-table-input" placeholder="Reason / description">
                                            </td>
                                            <td>
                                                <input type="file" name="claims[0][receipt]" class="odoo-table-input" accept="image/*,application/pdf">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-soft-danger btn-remove-line p-0" style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px;">
                                                    <i class="feather-trash-2"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border text-uppercase fw-bold fs-12" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary text-uppercase fw-bold fs-12">Save Draft</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Append modals to body to bypass stacking context / backdrop blur freeze issues
            ['addTravelModal', 'addAdvanceModal', 'addReportModal'].forEach(id => {
                const modal = document.getElementById(id);
                if (modal) document.body.appendChild(modal);
            });

            // Keep tabs active on page refresh
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam) {
                const tabButton = document.querySelector(`#${tabParam}-tab`);
                if (tabButton) {
                    const activeTab = bootstrap.Tab.getOrCreateInstance(tabButton);
                    activeTab.show();
                }
            }

            // Sync Header Action Buttons helper
            function syncHeaderButtons() {
                const activeTabEl = document.querySelector('button[data-bs-toggle="tab"].active');
                if (activeTabEl) {
                    const targetId = activeTabEl.id;
                    const travelBtn = document.getElementById('btnRequestTravel');
                    const advanceBtn = document.getElementById('btnRequestAdvance');
                    const claimBtn = document.getElementById('btnFileExpenseClaim');

                    if (travelBtn) travelBtn.classList.add('d-none');
                    if (advanceBtn) advanceBtn.classList.add('d-none');
                    if (claimBtn) claimBtn.classList.add('d-none');

                    if (targetId === 'travel-tab' && travelBtn) {
                        travelBtn.classList.remove('d-none');
                    } else if (targetId === 'advance-tab' && advanceBtn) {
                        advanceBtn.classList.remove('d-none');
                    } else if (targetId === 'report-tab' && claimBtn) {
                        claimBtn.classList.remove('d-none');
                    }
                }
            }

            // Style active/inactive tab buttons dynamically & Toggle Header action buttons
            const tabElList = document.querySelectorAll('button[data-bs-toggle="tab"]');
            tabElList.forEach(tabEl => {
                tabEl.addEventListener('shown.bs.tab', event => {
                    tabElList.forEach(el => {
                        el.classList.remove('active');
                        el.classList.add('text-muted');
                    });
                    event.target.classList.add('active');
                    event.target.classList.remove('text-muted');

                    // Update URL parameter without reload
                    const targetId = event.target.id;
                    const tabName = targetId.replace('-tab', '');
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', tabName);
                    window.history.replaceState({}, '', url.toString());
 
                    syncHeaderButtons();
                });
            });
 
            // Initial button synchronization
            syncHeaderButtons();

            // Set Sort Param helper
            window.setSortParam = function (tab, value) {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', tab);
                url.searchParams.set(tab + '_sort', value);
                window.location.href = url.toString();
            };

            // Dynamic claims row addition
            let lineIndex = 1;
            const btnAddLine = document.getElementById('btnAddClaimLine');
            const tableBody = document.querySelector('#claimsLinesTable tbody');

            if (btnAddLine) {
                btnAddLine.addEventListener('click', function () {
                    const rowTemplate = `
                        <tr class="claim-line-row">
                            <td>
                                <select name="claims[${lineIndex}][category_id]" class="odoo-table-select" required>
                                    <option value="" disabled selected>-- Category --</option>
                                    @foreach($categories as $cat)
                                        <option value="${cat.id}">${cat.name}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="date" name="claims[${lineIndex}][date]" class="odoo-table-input" required value="{{ date('Y-m-d') }}">
                            </td>
                            <td>
                                <input type="number" name="claims[${lineIndex}][amount]" step="0.01" min="0.01" class="odoo-table-input" placeholder="0.00" required>
                            </td>
                            <td>
                                <input type="text" name="claims[${lineIndex}][merchant]" class="odoo-table-input mb-1" placeholder="Merchant name">
                                <input type="text" name="claims[${lineIndex}][desc]" class="odoo-table-input" placeholder="Reason / description">
                            </td>
                            <td>
                                <input type="file" name="claims[${lineIndex}][receipt]" class="odoo-table-input" accept="image/*,application/pdf">
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-soft-danger btn-remove-line p-0" style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px;">
                                    <i class="feather-trash-2"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    tableBody.insertAdjacentHTML('beforeend', rowTemplate);
                    lineIndex++;
                });
            }

            // Remove claim line
            $(document).on('click', '.btn-remove-line', function () {
                const rows = document.querySelectorAll('.claim-line-row');
                if (rows.length > 1) {
                    $(this).closest('tr').remove();
                } else {
                    alert('At least one claim line is required in an expense report.');
                }
            });
        });
    </script>
@endpush
